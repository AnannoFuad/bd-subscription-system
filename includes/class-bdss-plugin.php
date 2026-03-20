<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class BDSS_Plugin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	public function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		$this->init_components();
	}

	private function init_components() {
		if ( class_exists( 'BDSS_Settings' ) ) {
			BDSS_Settings::init();
		}

		if ( class_exists( 'BDSS_Product_Fields' ) ) {
			BDSS_Product_Fields::init();
		}

		if ( class_exists( 'BDSS_Order_Handler' ) ) {
			BDSS_Order_Handler::init();
		}

		if ( class_exists( 'BDSS_Content_Protection' ) ) {
			BDSS_Content_Protection::init();
		}

		if ( class_exists( 'BDSS_Post_Locker' ) ) {
			BDSS_Post_Locker::init();
		}

		if ( class_exists( 'BDSS_User_Dashboard' ) ) {
			BDSS_User_Dashboard::init();
		}

		if ( class_exists( 'BDSS_Admin' ) ) {
			BDSS_Admin::init();
		}
	}

	public function woocommerce_missing_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'BD Subscription System for WooCommerce requires WooCommerce to be installed and active.', 'bd-simple-subscription' );
		echo '</p></div>';
	}
}