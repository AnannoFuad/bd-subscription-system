<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BDSS_Subscription_Service {

	const FALLBACK_VALIDATION_INTERVAL = 900; // 15 minutes.
	const CACHE_GROUP = 'bdss_subscriptions';

	public static function grant_or_extend_subscription( $args ) {
		global $wpdb;

		$defaults = array(
			'user_id'    => 0,
			'order_id'   => 0,
			'product_id' => 0,
			'role_slug'  => 'bdss_subscriber',
			'plan_key'   => '',
			'days'       => 30,
		);

		$args = wp_parse_args( $args, $defaults );

		$user_id    = (int) $args['user_id'];
		$order_id   = (int) $args['order_id'];
		$product_id = (int) $args['product_id'];
		$role_slug  = sanitize_key( $args['role_slug'] );
		$plan_key   = sanitize_key( $args['plan_key'] );
		$days       = absint( $args['days'] );

		if ( ! $user_id || ! $order_id || ! $product_id || $days < 1 ) {
			return array(
				'success' => false,
				'message' => 'Invalid subscription data.',
			);
		}

		if ( empty( $role_slug ) ) {
			$role_slug = 'bdss_subscriber';
		}

		$table_name = self::table_name();

		$order_row_cache_key = 'order_row_' . md5( $user_id . '|' . $order_id . '|' . $product_id );
		$existing_order_row  = wp_cache_get( $order_row_cache_key, self::CACHE_GROUP );

		if ( false === $existing_order_row ) {
			$existing_order_row = self::get_subscription_by_order( $user_id, $order_id, $product_id );
			wp_cache_set( $order_row_cache_key, $existing_order_row, self::CACHE_GROUP, MINUTE_IN_SECONDS * 10 );
		}

		if ( $existing_order_row && 'active' === $existing_order_row->status && ! self::is_expired_datetime( $existing_order_row->expires_at ) ) {
			return array(
				'success' => true,
				'message' => 'Subscription already exists for this order and product.',
			);
		}

		$existing_active = self::find_existing_subscription( $user_id, $plan_key, $product_id, $role_slug, 'active' );

		if ( $existing_active && self::is_expired_datetime( $existing_active->expires_at ) ) {
			self::expire_by_id( (int) $existing_active->id );
			$existing_active = false;
		}

		if ( $existing_active ) {
			$new_expires_at = self::add_days_to_datetime( $existing_active->expires_at, $days );

			$updated = $wpdb->update(
				$table_name,
				array(
					'expires_at' => $new_expires_at,
					'updated_at' => current_time( 'mysql' ),
					'order_id'   => $order_id,
					'status'     => 'active',
				),
				array(
					'id' => (int) $existing_active->id,
				),
				array( '%s', '%s', '%d', '%s' ),
				array( '%d' )
			);

			self::flush_subscription_cache( $user_id, (int) $existing_active->id );

			self::ensure_user_has_role( $user_id, $role_slug );
			self::schedule_expiry( $user_id, $product_id, $order_id, $new_expires_at );

			return array(
				'success' => false !== $updated,
				'message' => false !== $updated ? 'Existing subscription extended until ' . $new_expires_at . '.' : 'Could not extend existing subscription.',
			);
		}

		$existing_inactive = self::find_latest_inactive_subscription( $user_id, $plan_key, $product_id, $role_slug );

		if ( $existing_inactive ) {
			$starts_at  = current_time( 'mysql' );
			$expires_at = self::add_days_to_datetime( $starts_at, $days );

			$updated = $wpdb->update(
				$table_name,
				array(
					'order_id'   => $order_id,
					'product_id' => $product_id,
					'plan_key'   => $plan_key,
					'role_slug'  => $role_slug,
					'status'     => 'active',
					'starts_at'  => $starts_at,
					'expires_at' => $expires_at,
					'updated_at' => current_time( 'mysql' ),
				),
				array(
					'id' => (int) $existing_inactive->id,
				),
				array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);

			self::flush_subscription_cache( $user_id, (int) $existing_inactive->id );

			self::ensure_user_has_role( $user_id, $role_slug );
			self::schedule_expiry( $user_id, $product_id, $order_id, $expires_at );

			return array(
				'success' => false !== $updated,
				'message' => false !== $updated ? 'Inactive subscription reactivated until ' . $expires_at . '.' : 'Could not reactivate subscription.',
			);
		}

		$starts_at  = current_time( 'mysql' );
		$expires_at = self::add_days_to_datetime( $starts_at, $days );

		$inserted = $wpdb->insert(
			$table_name,
			array(
				'user_id'    => $user_id,
				'order_id'   => $order_id,
				'product_id' => $product_id,
				'plan_key'   => $plan_key,
				'role_slug'  => $role_slug,
				'status'     => 'active',
				'starts_at'  => $starts_at,
				'expires_at' => $expires_at,
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return array(
				'success' => false,
				'message' => 'Database insert failed.',
			);
		}

		self::flush_subscription_cache( $user_id );

		self::ensure_user_has_role( $user_id, $role_slug );
		self::schedule_expiry( $user_id, $product_id, $order_id, $expires_at );

		return array(
			'success' => true,
			'message' => 'New subscription created until ' . $expires_at . '.',
		);
	}

	private static function find_existing_subscription( $user_id, $plan_key, $product_id, $role_slug, $status ) {
		global $wpdb;

		$cache_key = 'find_existing_' . md5( wp_json_encode( array( $user_id, $plan_key, $product_id, $role_slug, $status ) ) );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		$table_name = self::table_name();

		if ( ! empty( $plan_key ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal plugin table with prepared values and cached result.
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT *
					FROM {$table_name}
					WHERE user_id = %d
					AND plan_key = %s
					AND status = %s
					ORDER BY id DESC
					LIMIT 1",
					(int) $user_id,
					$plan_key,
					$status
				)
			);

			wp_cache_set( $cache_key, $row, self::CACHE_GROUP, MINUTE_IN_SECONDS * 10 );
			return $row;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal plugin table with prepared values and cached result.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT *
				FROM {$table_name}
				WHERE user_id = %d
				AND product_id = %d
				AND role_slug = %s
				AND status = %s
				ORDER BY id DESC
				LIMIT 1",
				(int) $user_id,
				(int) $product_id,
				$role_slug,
				$status
			)
		);

		wp_cache_set( $cache_key, $row, self::CACHE_GROUP, MINUTE_IN_SECONDS * 10 );

		return $row;
	}

	private static function find_latest_inactive_subscription( $user_id, $plan_key, $product_id, $role_slug ) {
		global $wpdb;

		$cache_key = 'find_inactive_' . md5( wp_json_encode( array( $user_id, $plan_key, $product_id, $role_slug ) ) );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		$table_name = self::table_name();

		if ( ! empty( $plan_key ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal plugin table with prepared values and cached result.
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT *
					FROM {$table_name}
					WHERE user_id = %d
					AND plan_key = %s
					AND status IN ('expired','cancelled','refunded')
					ORDER BY id DESC
					LIMIT 1",
					(int) $user_id,
					$plan_key
				)
			);

			wp_cache_set( $cache_key, $row, self::CACHE_GROUP, MINUTE_IN_SECONDS * 10 );
			return $row;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal plugin table with prepared values and cached result.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT *
				FROM {$table_name}
				WHERE user_id = %d
				AND product_id = %d
				AND role_slug = %s
				AND status IN ('expired','cancelled','refunded')
				ORDER BY id DESC
				LIMIT 1",
				(int) $user_id,
				(int) $product_id,
				$role_slug
			)
		);

		wp_cache_set( $cache_key, $row, self::CACHE_GROUP, MINUTE_IN_SECONDS * 10 );

		return $row;
	}

	public static function expire_subscription( $user_id, $product_id, $order_id ) {
		global $wpdb;

		$table_name = self::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal plugin table with prepared values.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT *
				FROM {$table_name}
				WHERE user_id = %d
				AND product_id = %d
				AND order_id = %d
				AND status = %s
				LIMIT 1",
				(int) $user_id,
				(int) $product_id,
				(int) $order_id,
				'active'
			)
		);

		if ( ! $row ) {
			BDSS_Logger::log( 'No active subscription found to expire for user ' . $user_id . ', product ' . $product_id . ', order ' . $order_id );
			return;
		}

		self::expire_by_id( (int) $row->id );
	}

	public static function expire_by_id( $subscription_id ) {
		global $wpdb;

		$table_name = self::table_name();
		$row        = self::get_subscription_by_id( $subscription_id );

		if ( ! $row ) {
			return false;
		}

		$expired_at = current_time( 'mysql' );

		if ( 'expired' === $row->status ) {
			$wpdb->update(
				$table_name,
				array(
					'expires_at' => $expired_at,
					'updated_at' => $expired_at,
				),
				array(
					'id' => (int) $subscription_id,
				),
				array( '%s', '%s' ),
				array( '%d' )
			);

			self::flush_subscription_cache( (int) $row->user_id, (int) $subscription_id );
			self::maybe_remove_role_from_user( (int) $row->user_id, sanitize_key( $row->role_slug ) );

			return true;
		}

		$updated = $wpdb->update(
			$table_name,
			array(
				'status'     => 'expired',
				'expires_at' => $expired_at,
				'updated_at' => $expired_at,
			),
			array(
				'id' => (int) $subscription_id,
			),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		self::flush_subscription_cache( (int) $row->user_id, (int) $subscription_id );
		self::maybe_remove_role_from_user( (int) $row->user_id, sanitize_key( $row->role_slug ) );
		BDSS_Logger::log( 'Subscription expired. ID: ' . $subscription_id . ' at ' . $expired_at );

		return false !== $updated;
	}

	public static function activate_by_id( $subscription_id ) {
		global $wpdb;

		$table_name = self::table_name();
		$row        = self::get_subscription_by_id( $subscription_id );

		if ( ! $row ) {
			return false;
		}

		$user_id    = (int) $row->user_id;
		$product_id = (int) $row->product_id;
		$order_id   = (int) $row->order_id;
		$role_slug  = sanitize_key( $row->role_slug );
		$plan_key   = sanitize_key( $row->plan_key );

		if ( empty( $role_slug ) ) {
			$role_slug = 'bdss_subscriber';
		}

		$days = self::get_product_duration_days( $product_id );

		if ( $days < 1 ) {
			$days = self::guess_subscription_days_from_row( $row );
		}

		if ( $days < 1 ) {
			$days = 30;
		}

		$starts_at  = current_time( 'mysql' );
		$expires_at = self::add_days_to_datetime( $starts_at, $days );

		$updated = $wpdb->update(
			$table_name,
			array(
				'status'     => 'active',
				'starts_at'  => $starts_at,
				'expires_at' => $expires_at,
				'updated_at' => current_time( 'mysql' ),
				'role_slug'  => $role_slug,
				'plan_key'   => $plan_key,
			),
			array(
				'id' => (int) $subscription_id,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return false;
		}

		self::flush_subscription_cache( $user_id, (int) $subscription_id );
		self::ensure_user_has_role( $user_id, $role_slug );
		self::schedule_expiry( $user_id, $product_id, $order_id, $expires_at );

		BDSS_Logger::log( 'Subscription activated. ID: ' . $subscription_id . ' until ' . $expires_at );

		return true;
	}

	private static function get_product_duration_days( $product_id ) {
		$days = absint( get_post_meta( $product_id, '_bdss_duration_days', true ) );
		return $days > 0 ? $days : 0;
	}

	private static function guess_subscription_days_from_row( $row ) {
		$starts  = ! empty( $row->starts_at ) ? strtotime( $row->starts_at ) : false;
		$expires = ! empty( $row->expires_at ) ? strtotime( $row->expires_at ) : false;

		if ( ! $starts || ! $expires || $expires <= $starts ) {
			return 0;
		}

		$seconds = $expires - $starts;
		$days    = (int) ceil( $seconds / DAY_IN_SECONDS );

		return $days > 0 ? $days : 0;
	}

	public static function validate_user_subscriptions( $user_id ) {
		global $wpdb;

		$user_id = (int) $user_id;

		if ( $user_id < 1 ) {
			return 0;
		}

		$table_name = self::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching -- Validation query on internal table with prepared values.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id
				FROM {$table_name}
				WHERE user_id = %d
				AND status = %s
				AND expires_at < %s",
				$user_id,
				'active',
				current_time( 'mysql' )
			)
		);

		if ( empty( $rows ) ) {
			return 0;
		}

		$count = 0;

		foreach ( $rows as $row ) {
			if ( self::expire_by_id( (int) $row->id ) ) {
				$count++;
			}
		}

		return $count;
	}

	public static function maybe_run_fallback_expiry_validation() {
		$last_run = (int) get_option( 'bdss_last_fallback_expiry_validation', 0 );
		$now      = time();

		if ( $last_run > 0 && ( $now - $last_run ) < self::FALLBACK_VALIDATION_INTERVAL ) {
			return;
		}

		update_option( 'bdss_last_fallback_expiry_validation', $now, false );
		self::run_fallback_expiry_validation();
	}

	public static function run_fallback_expiry_validation( $limit = 50 ) {
		global $wpdb;

		$limit = absint( $limit );

		if ( $limit < 1 ) {
			$limit = 50;
		}

		$table_name = self::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching -- Periodic validator query on internal table with prepared values.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id
				FROM {$table_name}
				WHERE status = %s
				AND expires_at < %s
				ORDER BY expires_at ASC
				LIMIT %d",
				'active',
				current_time( 'mysql' ),
				$limit
			)
		);

		if ( empty( $rows ) ) {
			return 0;
		}

		$expired_count = 0;

		foreach ( $rows as $row ) {
			if ( self::expire_by_id( (int) $row->id ) ) {
				$expired_count++;
			}
		}

		if ( $expired_count > 0 ) {
			BDSS_Logger::log( 'Fallback expiry validator expired ' . $expired_count . ' overdue subscription(s).' );
		}

		return $expired_count;
	}

	private static function ensure_user_has_role( $user_id, $role_slug ) {
		$user = get_userdata( $user_id );

		if ( $user && $role_slug && ! in_array( $role_slug, (array) $user->roles, true ) ) {
			$user->add_role( $role_slug );
		}
	}

	private static function maybe_remove_role_from_user( $user_id, $role_slug ) {
		global $wpdb;

		if ( empty( $role_slug ) ) {
			return;
		}

		$table_name = self::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching -- Internal table count query with prepared values.
		$active_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$table_name}
				WHERE user_id = %d
				AND role_slug = %s
				AND status = %s
				AND expires_at >= %s",
				(int) $user_id,
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

	private static function schedule_expiry( $user_id, $product_id, $order_id, $expires_at ) {
		$timestamp = strtotime( get_gmt_from_date( $expires_at, 'Y-m-d H:i:s' ) );

		if ( ! $timestamp ) {
			return;
		}

		$args = array(
			(int) $user_id,
			(int) $product_id,
			(int) $order_id,
		);

		$existing = wp_next_scheduled( 'bdss_expire_subscription_event', $args );

		if ( $existing && (int) $existing === (int) $timestamp ) {
			return;
		}

		if ( $existing ) {
			wp_unschedule_event( $existing, 'bdss_expire_subscription_event', $args );
		}

		wp_schedule_single_event(
			$timestamp,
			'bdss_expire_subscription_event',
			$args
		);
	}

	private static function add_days_to_datetime( $datetime, $days ) {
		$timestamp = strtotime( $datetime );

		if ( ! $timestamp ) {
			$timestamp = current_time( 'timestamp' );
		}

		return date_i18n( 'Y-m-d H:i:s', strtotime( '+' . absint( $days ) . ' days', $timestamp ) );
	}

	private static function is_expired_datetime( $datetime ) {
		$timestamp = strtotime( $datetime );

		if ( ! $timestamp ) {
			return false;
		}

		return $timestamp < current_time( 'timestamp' );
	}

	private static function get_subscription_by_id( $subscription_id ) {
		global $wpdb;

		$subscription_id = absint( $subscription_id );

		if ( $subscription_id < 1 ) {
			return false;
		}

		$cache_key = 'subscription_id_' . $subscription_id;
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		$table_name = self::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal plugin table with prepared values and cached result.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT *
				FROM {$table_name}
				WHERE id = %d
				LIMIT 1",
				$subscription_id
			)
		);

		wp_cache_set( $cache_key, $row, self::CACHE_GROUP, MINUTE_IN_SECONDS * 10 );

		return $row;
	}

	private static function get_subscription_by_order( $user_id, $order_id, $product_id ) {
		global $wpdb;

		$table_name = self::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching -- Internal plugin table with prepared values.
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT *
				FROM {$table_name}
				WHERE user_id = %d
				AND order_id = %d
				AND product_id = %d
				ORDER BY id DESC
				LIMIT 1",
				(int) $user_id,
				(int) $order_id,
				(int) $product_id
			)
		);
	}

	private static function flush_subscription_cache( $user_id = 0, $subscription_id = 0 ) {
		if ( $subscription_id > 0 ) {
			wp_cache_delete( 'subscription_id_' . absint( $subscription_id ), self::CACHE_GROUP );
		}

		if ( $user_id > 0 ) {
			wp_cache_set( 'user_touch_' . absint( $user_id ), microtime( true ), self::CACHE_GROUP, DAY_IN_SECONDS );
		}
	}

	private static function table_name() {
		return BDSS_DB::subscriptions_table();
	}
}

add_action( 'bdss_expire_subscription_event', array( 'BDSS_Subscription_Service', 'expire_subscription' ), 10, 3 );
add_action( 'init', array( 'BDSS_Subscription_Service', 'maybe_run_fallback_expiry_validation' ) );