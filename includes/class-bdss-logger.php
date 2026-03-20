<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BDSS_Logger {

	/**
	 * Log a message safely.
	 *
	 * Uses WooCommerce logger when available.
	 * Falls back to error_log only in debug mode.
	 *
	 * @param string $message Log message.
	 * @param string $level   Log level: emergency|alert|critical|error|warning|notice|info|debug
	 * @return void
	 */
	public static function log( $message, $level = 'info' ) {
		$message = is_scalar( $message ) ? (string) $message : wp_json_encode( $message );
		$level   = self::sanitize_level( $level );

		if ( class_exists( 'WC_Logger' ) && function_exists( 'wc_get_logger' ) ) {
			$logger  = wc_get_logger();
			$context = array(
				'source' => 'bd-simple-subscription',
			);

			$logger->log( $level, $message, $context );
			return;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[bd-simple-subscription] ' . $level . ': ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Normalize log level to accepted WooCommerce logger levels.
	 *
	 * @param string $level Raw level.
	 * @return string
	 */
	private static function sanitize_level( $level ) {
		$allowed = array(
			'emergency',
			'alert',
			'critical',
			'error',
			'warning',
			'notice',
			'info',
			'debug',
		);

		$level = strtolower( sanitize_key( (string) $level ) );

		if ( ! in_array( $level, $allowed, true ) ) {
			return 'info';
		}

		return $level;
	}
}