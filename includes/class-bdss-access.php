<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BDSS_Access {

	const CACHE_GROUP = 'bdss_access';

	public static function user_has_active_plan( $user_id, $plan_key = '', $role_slug = '' ) {
		global $wpdb;

		$user_id   = absint( $user_id );
		$plan_key  = sanitize_key( $plan_key );
		$role_slug = sanitize_key( $role_slug );

		if ( $user_id < 1 ) {
			return false;
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return false;
		}

		if ( class_exists( 'BDSS_Subscription_Service' ) ) {
			BDSS_Subscription_Service::validate_user_subscriptions( $user_id );
		}

		$cache_key = 'has_active_' . md5(
			wp_json_encode(
				array(
					'user_id'   => $user_id,
					'plan_key'  => $plan_key,
					'role_slug' => $role_slug,
					'touch'     => self::get_user_cache_version( $user_id ),
				)
			)
		);

		$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return (bool) $cached;
		}

		$table_name = self::table_name();
		$now        = current_time( 'mysql' );
		$result     = null;

		if ( ! empty( $plan_key ) && ! empty( $role_slug ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching -- Internal subscription access check with prepared values and short cache.
			$result = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id
					FROM {$table_name}
					WHERE user_id = %d
					AND status = %s
					AND expires_at >= %s
					AND ( plan_key = %s OR role_slug = %s )
					ORDER BY id DESC
					LIMIT 1",
					$user_id,
					'active',
					$now,
					$plan_key,
					$role_slug
				)
			);
		} elseif ( ! empty( $plan_key ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching -- Internal subscription access check with prepared values and short cache.
			$result = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id
					FROM {$table_name}
					WHERE user_id = %d
					AND status = %s
					AND expires_at >= %s
					AND plan_key = %s
					ORDER BY id DESC
					LIMIT 1",
					$user_id,
					'active',
					$now,
					$plan_key
				)
			);
		} elseif ( ! empty( $role_slug ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching -- Internal subscription access check with prepared values and short cache.
			$result = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id
					FROM {$table_name}
					WHERE user_id = %d
					AND status = %s
					AND expires_at >= %s
					AND role_slug = %s
					ORDER BY id DESC
					LIMIT 1",
					$user_id,
					'active',
					$now,
					$role_slug
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching -- Internal subscription access check with prepared values and short cache.
			$result = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id
					FROM {$table_name}
					WHERE user_id = %d
					AND status = %s
					AND expires_at >= %s
					ORDER BY id DESC
					LIMIT 1",
					$user_id,
					'active',
					$now
				)
			);
		}

		$has_it = ! empty( $result );

		wp_cache_set( $cache_key, $has_it, self::CACHE_GROUP, MINUTE_IN_SECONDS * 5 );

		return $has_it;
	}

	private static function table_name() {
		return BDSS_DB::subscriptions_table();
	}

	private static function get_user_cache_version( $user_id ) {
		$user_id = absint( $user_id );

		if ( $user_id < 1 ) {
			return '0';
		}

		$touch = wp_cache_get( 'user_touch_' . $user_id, 'bdss_subscriptions' );

		if ( false === $touch ) {
			$touch = '0';
		}

		return (string) $touch;
	}
}