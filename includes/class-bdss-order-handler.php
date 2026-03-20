<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BDSS_Order_Handler {

	const CACHE_GROUP = 'bdss_order_handler';

	public static function init() {
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'maybe_process_order' ) );
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'maybe_process_order' ) );

		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'handle_cancelled_order' ) );
		add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'handle_refunded_order' ) );
		add_action( 'woocommerce_order_status_failed', array( __CLASS__, 'handle_failed_order' ) );
	}

	public static function maybe_process_order( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$user_id = self::resolve_order_user_id( $order );

		if ( ! $user_id ) {
			BDSS_Logger::log( 'Could not resolve user for order #' . $order_id, 'warning' );
			return;
		}

		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();

			if ( ! $product_id ) {
				continue;
			}

			$enabled = get_post_meta( $product_id, '_bdss_enable_subscription', true );

			if ( 'yes' !== $enabled ) {
				continue;
			}

			$days      = absint( get_post_meta( $product_id, '_bdss_duration_days', true ) );
			$role_slug = sanitize_key( get_post_meta( $product_id, '_bdss_role_slug', true ) );
			$plan_key  = sanitize_key( get_post_meta( $product_id, '_bdss_plan_key', true ) );

			if ( $days < 1 ) {
				$days = 30;
			}

			if ( empty( $role_slug ) ) {
				$role_slug = 'bdss_subscriber';
			}

			$result = BDSS_Subscription_Service::grant_or_extend_subscription(
				array(
					'user_id'    => $user_id,
					'order_id'   => $order_id,
					'product_id' => $product_id,
					'role_slug'  => $role_slug,
					'plan_key'   => $plan_key,
					'days'       => $days,
				)
			);

			BDSS_Logger::log(
				sprintf(
					'Order #%d processed for subscription product #%d. Result: %s',
					(int) $order_id,
					(int) $product_id,
					isset( $result['message'] ) ? (string) $result['message'] : 'No message'
				),
				'info'
			);
		}
	}

	public static function handle_cancelled_order( $order_id ) {
		self::mark_order_subscriptions_status( $order_id, 'cancelled' );
	}

	public static function handle_refunded_order( $order_id ) {
		self::mark_order_subscriptions_status( $order_id, 'refunded' );
	}

	public static function handle_failed_order( $order_id ) {
		self::mark_order_subscriptions_status( $order_id, 'cancelled' );
	}

	private static function resolve_order_user_id( $order ) {
		$user_id = absint( $order->get_user_id() );

		if ( $user_id > 0 ) {
			return $user_id;
		}

		$billing_email = sanitize_email( $order->get_billing_email() );

		if ( empty( $billing_email ) ) {
			return 0;
		}

		$existing_user = get_user_by( 'email', $billing_email );

		if ( $existing_user ) {
			if ( ! $order->get_user_id() ) {
				$order->set_customer_id( $existing_user->ID );
				$order->save();
			}

			return (int) $existing_user->ID;
		}

		$username_base = sanitize_user( current( explode( '@', $billing_email ) ), true );
		$username_base = $username_base ? $username_base : 'bdssuser';
		$username      = $username_base;
		$counter       = 1;

		while ( username_exists( $username ) ) {
			$username = $username_base . $counter;
			$counter++;
		}

		$password = wp_generate_password( 12, true, true );
		$user_id  = wp_create_user( $username, $password, $billing_email );

		if ( is_wp_error( $user_id ) ) {
			BDSS_Logger::log(
				'User creation failed for billing email ' . $billing_email . ': ' . $user_id->get_error_message(),
				'error'
			);
			return 0;
		}

		$first_name = sanitize_text_field( $order->get_billing_first_name() );
		$last_name  = sanitize_text_field( $order->get_billing_last_name() );

		if ( $first_name ) {
			update_user_meta( $user_id, 'first_name', $first_name );
		}

		if ( $last_name ) {
			update_user_meta( $user_id, 'last_name', $last_name );
		}

		wp_update_user(
			array(
				'ID'           => $user_id,
				'display_name' => trim( $first_name . ' ' . $last_name ),
			)
		);

		$order->set_customer_id( $user_id );
		$order->save();

		BDSS_Logger::log(
			'Created customer account #' . (int) $user_id . ' for guest order #' . (int) $order->get_id(),
			'info'
		);

		return (int) $user_id;
	}

	private static function mark_order_subscriptions_status( $order_id, $new_status ) {
		global $wpdb;

		$order_id   = absint( $order_id );
		$new_status = sanitize_key( $new_status );

		if ( $order_id < 1 || empty( $new_status ) ) {
			return;
		}

		$table     = esc_sql( BDSS_DB::subscriptions_table() );
		$cache_key = 'order_status_rows_' . md5( $order_id . '|' . $new_status );
		$rows      = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false === $rows ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM $table WHERE order_id = %d AND status = %s",
					$order_id,
					'active'
				)
			);

			wp_cache_set( $cache_key, $rows, self::CACHE_GROUP, MINUTE_IN_SECONDS * 5 );
		}

		if ( empty( $rows ) ) {
			BDSS_Logger::log(
				'No active BDSS subscriptions found for order #' . $order_id . ' during status change to ' . $new_status,
				'notice'
			);
			return;
		}

		foreach ( $rows as $row ) {
			$updated = $wpdb->update(
				$table,
				array(
					'status'     => $new_status,
					'updated_at' => current_time( 'mysql' ),
				),
				array(
					'id' => (int) $row->id,
				),
				array( '%s', '%s' ),
				array( '%d' )
			);

			if ( false !== $updated ) {
				wp_cache_delete( $cache_key, self::CACHE_GROUP );

				self::maybe_remove_role_after_status_change(
					(int) $row->user_id,
					sanitize_key( $row->role_slug )
				);

				BDSS_Logger::log(
					sprintf(
						'Subscription #%d from order #%d marked as %s.',
						(int) $row->id,
						(int) $order_id,
						$new_status
					),
					'info'
				);
			}
		}
	}

	private static function maybe_remove_role_after_status_change( $user_id, $role_slug ) {
		global $wpdb;

		if ( $user_id < 1 || empty( $role_slug ) ) {
			return;
		}

		$table = esc_sql( BDSS_DB::subscriptions_table() );

		$active_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table WHERE user_id = %d AND role_slug = %s AND status = %s AND expires_at >= %s",
				$user_id,
				$role_slug,
				'active',
				current_time( 'mysql' )
			)
		);

		if ( $active_count > 0 ) {
			return;
		}

		$user = get_userdata( $user_id );

		if ( $user && in_array( $role_slug, (array) $user->roles, true ) ) {
			$user->remove_role( $role_slug );
		}
	}
}