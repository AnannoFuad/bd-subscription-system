<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BDSS_DB {

	public static function subscriptions_table() {
		global $wpdb;
		return $wpdb->prefix . 'bdss_subscriptions';
	}

	public static function create_tables() {
		global $wpdb;

		$table_name      = self::subscriptions_table();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			order_id BIGINT UNSIGNED NOT NULL,
			product_id BIGINT UNSIGNED NOT NULL,
			plan_key VARCHAR(100) NOT NULL DEFAULT '',
			role_slug VARCHAR(100) NOT NULL DEFAULT 'bdss_subscriber',
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			starts_at DATETIME NOT NULL,
			expires_at DATETIME NOT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY order_id (order_id),
			KEY product_id (product_id),
			KEY status (status),
			KEY expires_at (expires_at),
			KEY plan_key (plan_key)
		) {$charset_collate};";

		dbDelta( $sql );
		update_option( 'bdss_db_version', BDSS_DB_VERSION );
	}
}