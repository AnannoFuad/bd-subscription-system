<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BDSS_Post_Locker {

	private static $inside_teaser = false;

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save_meta_box' ) );
		add_filter( 'the_content', array( __CLASS__, 'maybe_filter_locked_content' ), 99 );
	}

	public static function register_meta_box() {
		$post_types = array( 'post', 'page' );

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'bdss_post_locker',
				__( 'BD Subscription Lock', 'bd-simple-subscription' ),
				array( __CLASS__, 'render_meta_box' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	public static function render_meta_box( $post ) {
		wp_nonce_field( 'bdss_post_locker_save', 'bdss_post_locker_nonce' );

		$enabled        = get_post_meta( $post->ID, '_bdss_lock_enabled', true );
		$plan_key       = get_post_meta( $post->ID, '_bdss_required_plan_key', true );
		$role_slug      = get_post_meta( $post->ID, '_bdss_required_role_slug', true );
		$teaser_mode    = get_post_meta( $post->ID, '_bdss_teaser_mode', true );
		$teaser_words   = get_post_meta( $post->ID, '_bdss_teaser_words', true );
		$subscribe_url  = get_post_meta( $post->ID, '_bdss_subscribe_url', true );
		$locker_message = get_post_meta( $post->ID, '_bdss_locker_message', true );
		$button_text    = get_post_meta( $post->ID, '_bdss_button_text', true );

		if ( empty( $teaser_mode ) ) {
			$teaser_mode = BDSS_Settings::get( 'default_teaser_mode', 'words' );
		}

		if ( empty( $teaser_words ) ) {
			$teaser_words = BDSS_Settings::get( 'default_teaser_words', 80 );
		}

		$plan_options = array();
		$role_options = array();

		if ( class_exists( 'BDSS_Admin' ) && method_exists( 'BDSS_Admin', 'get_existing_plan_keys' ) ) {
			$plan_options = BDSS_Admin::get_existing_plan_keys();
		}

		if ( class_exists( 'BDSS_Admin' ) && method_exists( 'BDSS_Admin', 'get_existing_roles' ) ) {
			$role_options = BDSS_Admin::get_existing_roles();
		}

		echo '<p><label><input type="checkbox" name="bdss_lock_enabled" value="1" ' . checked( $enabled, 'yes', false ) . '> ' . esc_html__( 'Enable lock for this content', 'bd-simple-subscription' ) . '</label></p>';

		echo '<p>';
		echo '<label for="bdss_required_plan_key"><strong>' . esc_html__( 'Required Plan Key', 'bd-simple-subscription' ) . '</strong></label><br>';
		echo '<select id="bdss_required_plan_key" name="bdss_required_plan_key" class="widefat">';
		echo '<option value="">' . esc_html__( '— Select Plan Key —', 'bd-simple-subscription' ) . '</option>';

		if ( ! empty( $plan_options ) ) {
			foreach ( $plan_options as $plan_option ) {
				echo '<option value="' . esc_attr( $plan_option ) . '"' . selected( $plan_key, $plan_option, false ) . '>' . esc_html( $plan_option ) . '</option>';
			}
		}

		echo '</select>';
		echo '<small style="display:block;margin-top:6px;color:#666;">' . esc_html__( 'Choose from subscription plan keys already used in BDSS-enabled WooCommerce products.', 'bd-simple-subscription' ) . '</small>';
		echo '</p>';

		echo '<p>';
		echo '<label for="bdss_required_role_slug"><strong>' . esc_html__( 'Required Role Slug', 'bd-simple-subscription' ) . '</strong></label><br>';
		echo '<select id="bdss_required_role_slug" name="bdss_required_role_slug" class="widefat">';
		echo '<option value="">' . esc_html__( '— Select Role Slug —', 'bd-simple-subscription' ) . '</option>';

		if ( ! empty( $role_options ) ) {
			foreach ( $role_options as $role_key => $role_label ) {
				$option_label = $role_key;

				if ( ! empty( $role_label ) && $role_label !== $role_key ) {
					$option_label = $role_label . ' (' . $role_key . ')';
				}

				echo '<option value="' . esc_attr( $role_key ) . '"' . selected( $role_slug, $role_key, false ) . '>' . esc_html( $option_label ) . '</option>';
			}
		}

		echo '</select>';
		echo '<small style="display:block;margin-top:6px;color:#666;">' . esc_html__( 'Choose from existing BDSS subscription roles.', 'bd-simple-subscription' ) . '</small>';
		echo '</p>';

		echo '<p><label for="bdss_teaser_mode"><strong>' . esc_html__( 'Teaser Mode', 'bd-simple-subscription' ) . '</strong></label><br>';
		echo '<select id="bdss_teaser_mode" name="bdss_teaser_mode" class="widefat">';
		echo '<option value="words"' . selected( $teaser_mode, 'words', false ) . '>' . esc_html__( 'Words', 'bd-simple-subscription' ) . '</option>';
		echo '<option value="excerpt"' . selected( $teaser_mode, 'excerpt', false ) . '>' . esc_html__( 'Excerpt', 'bd-simple-subscription' ) . '</option>';
		echo '<option value="more"' . selected( $teaser_mode, 'more', false ) . '>' . esc_html__( 'More Tag', 'bd-simple-subscription' ) . '</option>';
		echo '</select></p>';

		echo '<p><label for="bdss_teaser_words"><strong>' . esc_html__( 'Teaser Words', 'bd-simple-subscription' ) . '</strong></label><br>';
		echo '<input type="number" min="10" step="1" id="bdss_teaser_words" name="bdss_teaser_words" value="' . esc_attr( absint( $teaser_words ) ) . '" class="small-text"></p>';

		echo '<p><label for="bdss_subscribe_url"><strong>' . esc_html__( 'Custom Subscribe URL', 'bd-simple-subscription' ) . '</strong></label><br>';
		echo '<input type="url" id="bdss_subscribe_url" name="bdss_subscribe_url" value="' . esc_attr( $subscribe_url ) . '" class="widefat"></p>';

		echo '<p><label for="bdss_locker_message"><strong>' . esc_html__( 'Custom Locker Message', 'bd-simple-subscription' ) . '</strong></label><br>';
		echo '<textarea id="bdss_locker_message" name="bdss_locker_message" rows="4" class="widefat">' . esc_textarea( $locker_message ) . '</textarea></p>';

		echo '<p><label for="bdss_button_text"><strong>' . esc_html__( 'Button Text', 'bd-simple-subscription' ) . '</strong></label><br>';
		echo '<input type="text" id="bdss_button_text" name="bdss_button_text" value="' . esc_attr( $button_text ) . '" class="widefat" placeholder="Subscribe to continue reading"></p>';

		echo '<p style="margin-top:10px;color:#666;font-size:12px;">';
		echo esc_html__( 'You can choose a plan, a role, or both. If both are empty, the plugin will fall back to bdss_subscriber.', 'bd-simple-subscription' );
		echo '</p>';
	}

	public static function save_meta_box( $post_id ) {
		if ( empty( $_POST['bdss_post_locker_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['bdss_post_locker_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'bdss_post_locker_save' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		update_post_meta( $post_id, '_bdss_lock_enabled', isset( $_POST['bdss_lock_enabled'] ) ? 'yes' : 'no' );
		update_post_meta( $post_id, '_bdss_required_plan_key', isset( $_POST['bdss_required_plan_key'] ) ? sanitize_key( wp_unslash( $_POST['bdss_required_plan_key'] ) ) : '' );
		update_post_meta( $post_id, '_bdss_required_role_slug', isset( $_POST['bdss_required_role_slug'] ) ? sanitize_key( wp_unslash( $_POST['bdss_required_role_slug'] ) ) : '' );
		update_post_meta( $post_id, '_bdss_teaser_mode', isset( $_POST['bdss_teaser_mode'] ) ? sanitize_key( wp_unslash( $_POST['bdss_teaser_mode'] ) ) : 'words' );
		update_post_meta( $post_id, '_bdss_teaser_words', isset( $_POST['bdss_teaser_words'] ) ? absint( $_POST['bdss_teaser_words'] ) : 80 );
		update_post_meta( $post_id, '_bdss_subscribe_url', isset( $_POST['bdss_subscribe_url'] ) ? esc_url_raw( wp_unslash( $_POST['bdss_subscribe_url'] ) ) : '' );
		update_post_meta( $post_id, '_bdss_locker_message', isset( $_POST['bdss_locker_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bdss_locker_message'] ) ) : '' );
		update_post_meta( $post_id, '_bdss_button_text', isset( $_POST['bdss_button_text'] ) ? sanitize_text_field( wp_unslash( $_POST['bdss_button_text'] ) ) : '' );
	}

	public static function maybe_filter_locked_content( $content ) {
		if ( is_admin() || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		if ( self::$inside_teaser ) {
			return $content;
		}

		$post = get_post();

		if ( ! $post || empty( $post->ID ) ) {
			return $content;
		}

		if ( ! self::is_post_locked( $post->ID ) ) {
			return $content;
		}

		if ( self::current_user_can_access_post( $post->ID ) ) {
			return $content;
		}

		$teaser = self::get_teaser_content( $post, $content );

		if ( 'yes' === BDSS_Settings::get( 'hide_plugin_locker', 'no' ) ) {
			return $teaser;
		}

		return $teaser . self::get_subscribe_box(
			array(
				'post_id' => $post->ID,
			)
		);
	}

	public static function is_post_locked( $post_id ) {
		return 'yes' === get_post_meta( $post_id, '_bdss_lock_enabled', true );
	}

	public static function current_user_can_access_post( $post_id ) {
		$post_id = absint( $post_id );

		if ( $post_id < 1 ) {
			return true;
		}

		if ( current_user_can( 'manage_options' ) || current_user_can( 'edit_post', $post_id ) ) {
			return true;
		}

		$required_plan = sanitize_key( get_post_meta( $post_id, '_bdss_required_plan_key', true ) );
		$required_role = sanitize_key( get_post_meta( $post_id, '_bdss_required_role_slug', true ) );

		if ( empty( $required_plan ) && empty( $required_role ) ) {
			$required_role = 'bdss_subscriber';
		}

		$user_id = get_current_user_id();

		if ( $user_id < 1 ) {
			return false;
		}

		return BDSS_Access::user_has_active_plan( $user_id, $required_plan, $required_role );
	}

	public static function get_teaser_content( $post, $content ) {
		$teaser_mode  = sanitize_key( get_post_meta( $post->ID, '_bdss_teaser_mode', true ) );
		$teaser_words = absint( get_post_meta( $post->ID, '_bdss_teaser_words', true ) );

		if ( empty( $teaser_mode ) ) {
			$teaser_mode = BDSS_Settings::get( 'default_teaser_mode', 'words' );
		}

		if ( $teaser_words < 10 ) {
			$teaser_words = absint( BDSS_Settings::get( 'default_teaser_words', 80 ) );
		}

		if ( 'excerpt' === $teaser_mode ) {
			$excerpt = ! empty( $post->post_excerpt ) ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( $content ), $teaser_words, '...' );
			return '<div class="bdss-teaser-content">' . wpautop( esc_html( $excerpt ) ) . '</div>';
		}

		if ( 'more' === $teaser_mode && false !== strpos( $post->post_content, '<!--more-->' ) ) {
			$parts = get_extended( $post->post_content );
			$main  = isset( $parts['main'] ) ? $parts['main'] : '';

			if ( ! empty( $main ) ) {
				self::$inside_teaser = true;
				$output              = '<div class="bdss-teaser-content">' . wpautop( do_shortcode( $main ) ) . '</div>';
				self::$inside_teaser = false;
				return $output;
			}
		}

		$trimmed = wp_trim_words( wp_strip_all_tags( $content ), $teaser_words, '...' );
		return '<div class="bdss-teaser-content">' . wpautop( esc_html( $trimmed ) ) . '</div>';
	}

	public static function get_subscribe_box( $args = array() ) {
		$post_id = ! empty( $args['post_id'] ) ? absint( $args['post_id'] ) : 0;

		$default_message = BDSS_Settings::get( 'default_locker_message', 'This content is for subscribers only.' );
		$default_url     = class_exists( 'BDSS_CTA' ) ? BDSS_CTA::get_purchase_url( 'locker' ) : home_url( '/' );

		$custom_message = $post_id ? get_post_meta( $post_id, '_bdss_locker_message', true ) : '';
		$custom_url     = $post_id ? get_post_meta( $post_id, '_bdss_subscribe_url', true ) : '';
		$button_text    = $post_id ? get_post_meta( $post_id, '_bdss_button_text', true ) : '';

		$defaults = array(
			'subscribe_url' => ! empty( $custom_url ) ? $custom_url : $default_url,
			'message'       => ! empty( $custom_message ) ? $custom_message : $default_message,
			'button_text'   => ! empty( $button_text ) ? $button_text : __( 'Subscribe to continue reading', 'bd-simple-subscription' ),
		);

		$args = wp_parse_args( $args, $defaults );

		$subscribe_url = ! empty( $args['subscribe_url'] ) ? esc_url( $args['subscribe_url'] ) : home_url( '/' );
		$message       = ! empty( $args['message'] ) ? $args['message'] : $default_message;
		$button_text   = ! empty( $args['button_text'] ) ? $args['button_text'] : __( 'Subscribe to continue reading', 'bd-simple-subscription' );

		ob_start();
		?>
		<div class="bdss-subscribe-box" style="margin:25px 0;padding:28px;border:1px solid #d9d9d9;background:#f8f8f8;border-radius:8px;">
			<div class="bdss-subscribe-box-inner" style="max-width:720px;">
				<div class="bdss-subscribe-badge" style="display:inline-block;padding:6px 10px;background:#111;color:#fff;border-radius:4px;font-size:12px;font-weight:600;margin-bottom:12px;">
					<?php echo esc_html__( 'Subscribers Only', 'bd-simple-subscription' ); ?>
				</div>
				<h3 style="margin:0 0 10px;font-size:24px;line-height:1.3;"><?php echo esc_html__( 'Unlock full access', 'bd-simple-subscription' ); ?></h3>
				<p style="margin:0 0 16px;font-size:15px;line-height:1.7;color:#333;">
					<?php echo esc_html( $message ); ?>
				</p>
				<a href="<?php echo esc_url( $subscribe_url ); ?>" style="display:inline-block;padding:12px 18px;background:#111;color:#fff;text-decoration:none;border-radius:4px;font-weight:600;">
					<?php echo esc_html( $button_text ); ?>
				</a>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}