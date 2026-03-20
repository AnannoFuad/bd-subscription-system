<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BDSS_Settings {

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_save_settings' ) );
	}

	public static function get_defaults() {
		return array(
			'default_teaser_mode'              => 'words',
			'default_teaser_words'             => 80,
			'default_subscribe_url'            => '',
			'default_locker_message'           => 'This content is for subscribers only.',
			'hide_plugin_locker'               => 'no',
			'expiring_soon_days'               => 7,

			'purchase_destination_type'        => 'shop',
			'purchase_destination_category'    => 0,
			'purchase_destination_page'        => 0,
			'purchase_destination_custom_url'  => '',

			'dashboard_page_id'                => 0,
			'login_page_id'                    => 0,

			'premium_enabled'                  => 'no',
			'locker_mode'                      => 'plugin_default',

			'refund_cancel_handling'           => 'basic',
			'refund_cancel_notice'             => 'Refunded and cancelled orders receive basic status handling. Partial refunds, mixed-order edge cases, and custom business rules may still require manual admin review.',
		);
	}

	public static function get_settings() {
		$defaults = self::get_defaults();
		$saved    = get_option( 'bdss_settings', array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$settings = wp_parse_args( $saved, $defaults );

		$settings['default_teaser_mode']             = sanitize_key( $settings['default_teaser_mode'] );
		$settings['default_teaser_words']            = absint( $settings['default_teaser_words'] );
		$settings['default_subscribe_url']           = esc_url_raw( $settings['default_subscribe_url'] );
		$settings['default_locker_message']          = sanitize_textarea_field( $settings['default_locker_message'] );
		$settings['hide_plugin_locker']              = ( 'yes' === $settings['hide_plugin_locker'] ) ? 'yes' : 'no';
		$settings['expiring_soon_days']              = absint( $settings['expiring_soon_days'] );

		$settings['purchase_destination_type']       = sanitize_key( $settings['purchase_destination_type'] );
		$settings['purchase_destination_category']   = absint( $settings['purchase_destination_category'] );
		$settings['purchase_destination_page']       = absint( $settings['purchase_destination_page'] );
		$settings['purchase_destination_custom_url'] = esc_url_raw( $settings['purchase_destination_custom_url'] );

		$settings['dashboard_page_id']               = absint( $settings['dashboard_page_id'] );
		$settings['login_page_id']                   = absint( $settings['login_page_id'] );

		$settings['premium_enabled']                 = ( 'yes' === $settings['premium_enabled'] ) ? 'yes' : 'no';
		$settings['locker_mode']                     = sanitize_key( $settings['locker_mode'] );

		$settings['refund_cancel_handling']          = sanitize_key( $settings['refund_cancel_handling'] );
		$settings['refund_cancel_notice']            = sanitize_text_field( $settings['refund_cancel_notice'] );

		if ( ! in_array( $settings['default_teaser_mode'], array( 'words', 'more', 'excerpt' ), true ) ) {
			$settings['default_teaser_mode'] = 'words';
		}

		if ( $settings['default_teaser_words'] < 10 ) {
			$settings['default_teaser_words'] = 80;
		}

		if ( $settings['expiring_soon_days'] < 1 ) {
			$settings['expiring_soon_days'] = 7;
		}

		if ( ! in_array( $settings['purchase_destination_type'], array( 'shop', 'product_category', 'page', 'custom_url' ), true ) ) {
			$settings['purchase_destination_type'] = 'shop';
		}

		if ( ! in_array( $settings['locker_mode'], array( 'plugin_default', 'custom_theme_locker' ), true ) ) {
			$settings['locker_mode'] = 'plugin_default';
		}

		if ( ! in_array( $settings['refund_cancel_handling'], array( 'basic', 'manual' ), true ) ) {
			$settings['refund_cancel_handling'] = 'basic';
		}

		if ( 'yes' !== $settings['premium_enabled'] ) {
			if ( in_array( $settings['purchase_destination_type'], array( 'page', 'custom_url' ), true ) ) {
				$settings['purchase_destination_type'] = 'shop';
			}

			$settings['locker_mode'] = 'plugin_default';
		}

		return $settings;
	}

	public static function get( $key, $default = '' ) {
		$settings = self::get_settings();
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	public static function maybe_save_settings() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( empty( $_POST['bdss_save_settings'] ) ) {
			return;
		}

		if ( empty( $_POST['bdss_settings_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['bdss_settings_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'bdss_save_settings_action' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'bd-simple-subscription' ) );
		}

		$existing = self::get_settings();

		$settings = array(
			'default_teaser_mode'              => isset( $_POST['default_teaser_mode'] ) ? sanitize_key( wp_unslash( $_POST['default_teaser_mode'] ) ) : 'words',
			'default_teaser_words'             => isset( $_POST['default_teaser_words'] ) ? absint( $_POST['default_teaser_words'] ) : 80,
			'default_subscribe_url'            => isset( $_POST['default_subscribe_url'] ) ? esc_url_raw( wp_unslash( $_POST['default_subscribe_url'] ) ) : '',
			'default_locker_message'           => isset( $_POST['default_locker_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['default_locker_message'] ) ) : '',
			'hide_plugin_locker'               => isset( $_POST['hide_plugin_locker'] ) ? 'yes' : 'no',
			'expiring_soon_days'               => isset( $_POST['expiring_soon_days'] ) ? absint( $_POST['expiring_soon_days'] ) : 7,

			'purchase_destination_type'        => isset( $_POST['purchase_destination_type'] ) ? sanitize_key( wp_unslash( $_POST['purchase_destination_type'] ) ) : 'shop',
			'purchase_destination_category'    => isset( $_POST['purchase_destination_category'] ) ? absint( $_POST['purchase_destination_category'] ) : 0,
			'purchase_destination_page'        => isset( $_POST['purchase_destination_page'] ) ? absint( $_POST['purchase_destination_page'] ) : 0,
			'purchase_destination_custom_url'  => isset( $_POST['purchase_destination_custom_url'] ) ? esc_url_raw( wp_unslash( $_POST['purchase_destination_custom_url'] ) ) : '',

			'dashboard_page_id'                => isset( $_POST['dashboard_page_id'] ) ? absint( $_POST['dashboard_page_id'] ) : 0,
			'login_page_id'                    => isset( $_POST['login_page_id'] ) ? absint( $_POST['login_page_id'] ) : 0,

			'premium_enabled'                  => isset( $_POST['premium_enabled'] ) ? 'yes' : 'no',
			'locker_mode'                      => isset( $_POST['locker_mode'] ) ? sanitize_key( wp_unslash( $_POST['locker_mode'] ) ) : 'plugin_default',

			'refund_cancel_handling'           => isset( $_POST['refund_cancel_handling'] ) ? sanitize_key( wp_unslash( $_POST['refund_cancel_handling'] ) ) : $existing['refund_cancel_handling'],
			'refund_cancel_notice'             => $existing['refund_cancel_notice'],
		);

		if ( ! in_array( $settings['default_teaser_mode'], array( 'words', 'more', 'excerpt' ), true ) ) {
			$settings['default_teaser_mode'] = 'words';
		}

		if ( $settings['default_teaser_words'] < 10 ) {
			$settings['default_teaser_words'] = 80;
		}

		if ( $settings['expiring_soon_days'] < 1 ) {
			$settings['expiring_soon_days'] = 7;
		}

		if ( ! in_array( $settings['purchase_destination_type'], array( 'shop', 'product_category', 'page', 'custom_url' ), true ) ) {
			$settings['purchase_destination_type'] = 'shop';
		}

		if ( ! in_array( $settings['locker_mode'], array( 'plugin_default', 'custom_theme_locker' ), true ) ) {
			$settings['locker_mode'] = 'plugin_default';
		}

		if ( ! in_array( $settings['refund_cancel_handling'], array( 'basic', 'manual' ), true ) ) {
			$settings['refund_cancel_handling'] = 'basic';
		}

		if ( 'yes' !== $settings['premium_enabled'] ) {
			if ( in_array( $settings['purchase_destination_type'], array( 'page', 'custom_url' ), true ) ) {
				$settings['purchase_destination_type'] = 'shop';
			}

			$settings['locker_mode'] = 'plugin_default';
		}

		update_option( 'bdss_settings', $settings );

		$redirect_url = add_query_arg(
			array(
				'page'        => 'bdss-settings',
				'bdss_notice' => 'settings_saved',
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}
}