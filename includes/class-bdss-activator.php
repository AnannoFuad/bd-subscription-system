<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BDSS_Activator {

	public static function activate() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		BDSS_DB::create_tables();
		self::add_roles();

		if ( ! get_option( 'bdss_settings' ) ) {
			update_option( 'bdss_settings', BDSS_Settings::get_defaults() );
		}

		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}

	private static function add_roles() {
		add_role(
			'bdss_subscriber',
			'BDSS Subscriber',
			array(
				'read' => true,
			)
		);
	}
}