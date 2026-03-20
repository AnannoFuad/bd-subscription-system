<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BDSS_Content_Protection {

	public static function init() {
		add_shortcode( 'bdss_protect', array( __CLASS__, 'render_protect_shortcode' ) );
	}

	public static function render_protect_shortcode( $atts, $content = '' ) {
		$atts = shortcode_atts(
			array(
				'plan'         => '',
				'plan_key'     => '',
				'role'         => '',
				'message'      => '',
				'button_text'  => '',
				'subscribe_url'=> '',
				'login_url'    => '',
			),
			(array) $atts,
			'bdss_protect'
		);

		$required_plan = ! empty( $atts['plan_key'] ) ? $atts['plan_key'] : $atts['plan'];
		$required_plan = sanitize_key( $required_plan );
		$required_role = sanitize_key( $atts['role'] );

		if ( empty( $required_plan ) && empty( $required_role ) ) {
			$required_role = 'bdss_subscriber';
		}

		$protected_content = do_shortcode( (string) $content );

		if ( current_user_can( 'manage_options' ) ) {
			return $protected_content;
		}

		$user_id = get_current_user_id();

		if ( $user_id > 0 ) {
			if ( class_exists( 'BDSS_Access' ) && BDSS_Access::user_has_active_plan( $user_id, $required_plan, $required_role ) ) {
				return $protected_content;
			}
		}

		return self::render_unauthorized_box(
			array(
				'message'       => $atts['message'],
				'button_text'   => $atts['button_text'],
				'subscribe_url' => $atts['subscribe_url'],
				'login_url'     => $atts['login_url'],
			)
		);
	}

	private static function render_unauthorized_box( $args = array() ) {
		$default_message = BDSS_Settings::get( 'default_locker_message', __( 'This content is for subscribers only.', 'bd-simple-subscription' ) );

		$subscribe_url = ! empty( $args['subscribe_url'] )
			? esc_url( $args['subscribe_url'] )
			: self::get_purchase_url();

		$login_url = ! empty( $args['login_url'] )
			? esc_url( $args['login_url'] )
			: self::get_login_url();

		$message = ! empty( $args['message'] )
			? sanitize_text_field( $args['message'] )
			: $default_message;

		$button_text = ! empty( $args['button_text'] )
			? sanitize_text_field( $args['button_text'] )
			: __( 'Subscribe to continue', 'bd-simple-subscription' );

		$is_logged_in = is_user_logged_in();

		ob_start();
		?>
		<div class="bdss-shortcode-lock-box" style="margin:20px 0;padding:24px;border:1px solid #ddd;background:#f8f8f8;border-radius:8px;">
			<div style="display:inline-block;padding:6px 10px;background:#111;color:#fff;border-radius:4px;font-size:12px;font-weight:600;margin-bottom:12px;">
				<?php echo esc_html__( 'Subscribers Only', 'bd-simple-subscription' ); ?>
			</div>

			<h3 style="margin:0 0 10px;font-size:22px;line-height:1.35;">
				<?php echo esc_html__( 'Premium content locked', 'bd-simple-subscription' ); ?>
			</h3>

			<p style="margin:0 0 16px;font-size:15px;line-height:1.7;color:#333;">
				<?php echo esc_html( $message ); ?>
			</p>

			<?php if ( $is_logged_in ) : ?>
				<a href="<?php echo esc_url( $subscribe_url ); ?>" style="display:inline-block;padding:12px 18px;background:#111;color:#fff;text-decoration:none;border-radius:4px;font-weight:600;">
					<?php echo esc_html( $button_text ); ?>
				</a>
			<?php else : ?>
				<a href="<?php echo esc_url( $login_url ); ?>" style="display:inline-block;padding:12px 18px;background:#111;color:#fff;text-decoration:none;border-radius:4px;font-weight:600;margin-right:10px;">
					<?php echo esc_html__( 'Log In', 'bd-simple-subscription' ); ?>
				</a>
				<a href="<?php echo esc_url( $subscribe_url ); ?>" style="display:inline-block;padding:12px 18px;background:#fff;color:#111;text-decoration:none;border-radius:4px;font-weight:600;border:1px solid #111;">
					<?php echo esc_html( $button_text ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function get_purchase_url() {
		if ( class_exists( 'BDSS_CTA' ) && method_exists( 'BDSS_CTA', 'get_purchase_url' ) ) {
			return esc_url( BDSS_CTA::get_purchase_url( 'shortcode' ) );
		}

		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$shop_url = wc_get_page_permalink( 'shop' );
			if ( ! empty( $shop_url ) ) {
				return esc_url( $shop_url );
			}
		}

		return esc_url( home_url( '/' ) );
	}

	private static function get_login_url() {
		$page_id = absint( BDSS_Settings::get( 'login_page_id', 0 ) );

		if ( $page_id > 0 ) {
			$page_url = get_permalink( $page_id );
			if ( $page_url ) {
				return esc_url( $page_url );
			}
		}

		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$my_account_url = wc_get_page_permalink( 'myaccount' );
			if ( ! empty( $my_account_url ) ) {
				return esc_url( $my_account_url );
			}
		}

		return esc_url( wp_login_url() );
	}
}