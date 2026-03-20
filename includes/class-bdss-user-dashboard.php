<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BDSS_User_Dashboard {

	const CACHE_GROUP = 'bdss_user_dashboard';

	public static function init() {
		add_shortcode( 'bdss_my_subscription', array( __CLASS__, 'render_my_subscription_shortcode' ) );
		add_shortcode( 'bdss_subscription_status', array( __CLASS__, 'render_subscription_status_shortcode' ) );
	}

	public static function render_my_subscription_shortcode( $atts = array() ) {
		if ( ! is_user_logged_in() ) {
			return self::render_login_required();
		}

		$user_id = get_current_user_id();

		if ( class_exists( 'BDSS_Subscription_Service' ) ) {
			BDSS_Subscription_Service::validate_user_subscriptions( $user_id );
		}

		$history = self::get_user_subscription_history( $user_id );

		ob_start();

		echo '<div class="bdss-dashboard-wrap bdss-dashboard-history">';
		self::render_inline_styles();

		echo '<div class="bdss-dashboard-card">';
		echo '<h3>' . esc_html__( 'My Subscription History', 'bd-simple-subscription' ) . '</h3>';

		if ( empty( $history ) ) {
			echo '<p>' . esc_html__( 'No subscription history found.', 'bd-simple-subscription' ) . '</p>';
		} else {
			echo '<div class="bdss-history-table-wrap">';
			echo '<table class="bdss-history-table">';
			echo '<thead><tr>';
			echo '<th>' . esc_html__( 'Plan', 'bd-simple-subscription' ) . '</th>';
			echo '<th>' . esc_html__( 'Role', 'bd-simple-subscription' ) . '</th>';
			echo '<th>' . esc_html__( 'Status', 'bd-simple-subscription' ) . '</th>';
			echo '<th>' . esc_html__( 'Start Date', 'bd-simple-subscription' ) . '</th>';
			echo '<th>' . esc_html__( 'Expiry Date', 'bd-simple-subscription' ) . '</th>';
			echo '</tr></thead>';
			echo '<tbody>';

			foreach ( $history as $row ) {
				echo '<tr>';
				echo '<td>' . esc_html( ! empty( $row->plan_key ) ? $row->plan_key : '—' ) . '</td>';
				echo '<td>' . esc_html( ! empty( $row->role_slug ) ? $row->role_slug : '—' ) . '</td>';
				echo '<td><span class="bdss-status-pill bdss-status-' . esc_attr( $row->status ) . '">' . esc_html( ucfirst( $row->status ) ) . '</span></td>';
				echo '<td>' . esc_html( self::format_date( $row->starts_at ) ) . '</td>';
				echo '<td>' . esc_html( self::format_date( $row->expires_at ) ) . '</td>';
				echo '</tr>';
			}

			echo '</tbody>';
			echo '</table>';
			echo '</div>';
		}

		echo '</div>';
		echo '</div>';

		return ob_get_clean();
	}

	public static function render_subscription_status_shortcode( $atts = array() ) {
		if ( ! is_user_logged_in() ) {
			return self::render_login_required();
		}

		$user_id = get_current_user_id();

		if ( class_exists( 'BDSS_Subscription_Service' ) ) {
			BDSS_Subscription_Service::validate_user_subscriptions( $user_id );
		}

		$active_subscription = self::get_active_subscription( $user_id );
		$latest_subscription = self::get_latest_subscription( $user_id );
		$purchase_url        = self::get_purchase_url();

		ob_start();

		echo '<div class="bdss-dashboard-wrap bdss-dashboard-status">';
		self::render_inline_styles();

		if ( $active_subscription ) {
			echo '<div class="bdss-dashboard-card">';
			echo '<div class="bdss-card-badge bdss-badge-active">' . esc_html__( 'Active Subscription', 'bd-simple-subscription' ) . '</div>';
			echo '<h3>' . esc_html__( 'Your subscription is active', 'bd-simple-subscription' ) . '</h3>';
			echo '<p>' . esc_html__( 'You currently have access to subscriber-only content.', 'bd-simple-subscription' ) . '</p>';

			echo '<div class="bdss-details-grid">';
			echo '<div><strong>' . esc_html__( 'Plan', 'bd-simple-subscription' ) . ':</strong><br>' . esc_html( ! empty( $active_subscription->plan_key ) ? $active_subscription->plan_key : '—' ) . '</div>';
			echo '<div><strong>' . esc_html__( 'Role', 'bd-simple-subscription' ) . ':</strong><br>' . esc_html( ! empty( $active_subscription->role_slug ) ? $active_subscription->role_slug : '—' ) . '</div>';
			echo '<div><strong>' . esc_html__( 'Started', 'bd-simple-subscription' ) . ':</strong><br>' . esc_html( self::format_date( $active_subscription->starts_at ) ) . '</div>';
			echo '<div><strong>' . esc_html__( 'Expires', 'bd-simple-subscription' ) . ':</strong><br>' . esc_html( self::format_date( $active_subscription->expires_at ) ) . '</div>';
			echo '</div>';

			echo '<div class="bdss-actions">';
			echo '<a class="bdss-btn" href="' . esc_url( $purchase_url ) . '">' . esc_html__( 'Renew / Upgrade', 'bd-simple-subscription' ) . '</a>';
			echo '</div>';

			echo '</div>';
			echo '</div>';

			return ob_get_clean();
		}

		if ( $latest_subscription && 'expired' === $latest_subscription->status ) {
			echo '<div class="bdss-dashboard-card">';
			echo '<div class="bdss-card-badge bdss-badge-expired">' . esc_html__( 'Expired Subscription', 'bd-simple-subscription' ) . '</div>';
			echo '<h3>' . esc_html__( 'Your subscription has expired', 'bd-simple-subscription' ) . '</h3>';
			echo '<p>' . esc_html__( 'Renew your subscription to regain access to premium content.', 'bd-simple-subscription' ) . '</p>';

			echo '<div class="bdss-details-grid">';
			echo '<div><strong>' . esc_html__( 'Plan', 'bd-simple-subscription' ) . ':</strong><br>' . esc_html( ! empty( $latest_subscription->plan_key ) ? $latest_subscription->plan_key : '—' ) . '</div>';
			echo '<div><strong>' . esc_html__( 'Role', 'bd-simple-subscription' ) . ':</strong><br>' . esc_html( ! empty( $latest_subscription->role_slug ) ? $latest_subscription->role_slug : '—' ) . '</div>';
			echo '<div><strong>' . esc_html__( 'Last Start', 'bd-simple-subscription' ) . ':</strong><br>' . esc_html( self::format_date( $latest_subscription->starts_at ) ) . '</div>';
			echo '<div><strong>' . esc_html__( 'Expired On', 'bd-simple-subscription' ) . ':</strong><br>' . esc_html( self::format_date( $latest_subscription->expires_at ) ) . '</div>';
			echo '</div>';

			echo '<div class="bdss-actions">';
			echo '<a class="bdss-btn" href="' . esc_url( $purchase_url ) . '">' . esc_html__( 'Renew Subscription', 'bd-simple-subscription' ) . '</a>';
			echo '</div>';

			echo '</div>';
			echo '</div>';

			return ob_get_clean();
		}

		echo '<div class="bdss-dashboard-card">';
		echo '<div class="bdss-card-badge bdss-badge-none">' . esc_html__( 'No Active Subscription', 'bd-simple-subscription' ) . '</div>';
		echo '<h3>' . esc_html__( 'You do not have any active subscription', 'bd-simple-subscription' ) . '</h3>';
		echo '<p>' . esc_html__( 'Subscribe now to unlock premium content and features.', 'bd-simple-subscription' ) . '</p>';

		echo '<div class="bdss-actions">';
		echo '<a class="bdss-btn" href="' . esc_url( $purchase_url ) . '">' . esc_html__( 'View Subscription Plans', 'bd-simple-subscription' ) . '</a>';
		echo '</div>';

		echo '</div>';
		echo '</div>';

		return ob_get_clean();
	}

	private static function render_login_required() {
		$login_url = self::get_login_url();

		ob_start();

		echo '<div class="bdss-dashboard-wrap bdss-dashboard-login">';
		self::render_inline_styles();

		echo '<div class="bdss-dashboard-card">';
		echo '<div class="bdss-card-badge bdss-badge-none">' . esc_html__( 'Login Required', 'bd-simple-subscription' ) . '</div>';
		echo '<h3>' . esc_html__( 'Please log in to view your subscription', 'bd-simple-subscription' ) . '</h3>';
		echo '<p>' . esc_html__( 'You need to log in to access your subscription dashboard and protected content.', 'bd-simple-subscription' ) . '</p>';
		echo '<div class="bdss-actions">';
		echo '<a class="bdss-btn" href="' . esc_url( $login_url ) . '">' . esc_html__( 'Log In', 'bd-simple-subscription' ) . '</a>';
		echo '</div>';
		echo '</div>';
		echo '</div>';

		return ob_get_clean();
	}

	private static function get_active_subscription( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );

		if ( $user_id < 1 ) {
			return false;
		}

		$cache_key = 'active_' . $user_id . '_' . self::get_user_cache_version( $user_id );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		$table_name = self::table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal plugin table name.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT *
				FROM {$table_name}
				WHERE user_id = %d
				AND status = %s
				AND expires_at >= %s
				ORDER BY expires_at DESC, id DESC
				LIMIT 1",
				$user_id,
				'active',
				current_time( 'mysql' )
			)
		);

		wp_cache_set( $cache_key, $row, self::CACHE_GROUP, MINUTE_IN_SECONDS * 5 );

		return $row;
	}

	private static function get_latest_subscription( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );

		if ( $user_id < 1 ) {
			return false;
		}

		$cache_key = 'latest_' . $user_id . '_' . self::get_user_cache_version( $user_id );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		$table_name = self::table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal plugin table name.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT *
				FROM {$table_name}
				WHERE user_id = %d
				ORDER BY id DESC
				LIMIT 1",
				$user_id
			)
		);

		wp_cache_set( $cache_key, $row, self::CACHE_GROUP, MINUTE_IN_SECONDS * 5 );

		return $row;
	}

	private static function get_user_subscription_history( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );

		if ( $user_id < 1 ) {
			return array();
		}

		$cache_key = 'history_' . $user_id . '_' . self::get_user_cache_version( $user_id );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		$table_name = self::table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal plugin table name.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *
				FROM {$table_name}
				WHERE user_id = %d
				ORDER BY id DESC
				LIMIT %d",
				$user_id,
				20
			)
		);

		wp_cache_set( $cache_key, $rows, self::CACHE_GROUP, MINUTE_IN_SECONDS * 5 );

		return $rows;
	}

	private static function get_purchase_url() {
		if ( class_exists( 'BDSS_CTA' ) && method_exists( 'BDSS_CTA', 'get_purchase_url' ) ) {
			return BDSS_CTA::get_purchase_url( 'dashboard' );
		}

		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$shop_url = wc_get_page_permalink( 'shop' );
			if ( ! empty( $shop_url ) ) {
				return $shop_url;
			}
		}

		return home_url( '/' );
	}

	private static function get_login_url() {
		$page_id = absint( BDSS_Settings::get( 'login_page_id', 0 ) );

		if ( $page_id > 0 ) {
			$page_url = get_permalink( $page_id );
			if ( $page_url ) {
				return $page_url;
			}
		}

		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$my_account_url = wc_get_page_permalink( 'myaccount' );
			if ( ! empty( $my_account_url ) ) {
				return $my_account_url;
			}
		}

		return wp_login_url();
	}

	private static function format_date( $datetime ) {
		if ( empty( $datetime ) || '0000-00-00 00:00:00' === $datetime ) {
			return '—';
		}

		$timestamp = strtotime( $datetime );

		if ( ! $timestamp ) {
			return $datetime;
		}

		return date_i18n( 'Y-m-d H:i', $timestamp );
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

	private static function render_inline_styles() {
		static $printed = false;

		if ( $printed ) {
			return;
		}

		$printed = true;

		echo '<style>
			.bdss-dashboard-wrap{
				margin:24px 0;
			}
			.bdss-dashboard-card{
				background:#fff;
				border:1px solid #ddd;
				border-radius:10px;
				padding:24px;
				box-shadow:0 2px 10px rgba(0,0,0,.04);
			}
			.bdss-card-badge{
				display:inline-block;
				padding:6px 10px;
				border-radius:999px;
				font-size:12px;
				font-weight:600;
				margin-bottom:14px;
			}
			.bdss-badge-active{
				background:#dff5e3;
				color:#0f6b2f;
			}
			.bdss-badge-expired{
				background:#fde2df;
				color:#a12622;
			}
			.bdss-badge-none{
				background:#ececec;
				color:#555;
			}
			.bdss-details-grid{
				display:grid;
				grid-template-columns:repeat(2, minmax(180px, 1fr));
				gap:16px;
				margin:18px 0 20px;
			}
			.bdss-actions{
				margin-top:10px;
			}
			.bdss-btn{
				display:inline-block;
				padding:11px 18px;
				background:#111;
				color:#fff !important;
				text-decoration:none;
				border-radius:6px;
				font-weight:600;
			}
			.bdss-history-table-wrap{
				overflow-x:auto;
				margin-top:14px;
			}
			.bdss-history-table{
				width:100%;
				border-collapse:collapse;
			}
			.bdss-history-table th,
			.bdss-history-table td{
				padding:12px 10px;
				border-bottom:1px solid #e5e5e5;
				text-align:left;
			}
			.bdss-status-pill{
				display:inline-block;
				padding:4px 10px;
				border-radius:999px;
				font-size:12px;
				font-weight:600;
				line-height:1.4;
			}
			.bdss-status-active{
				background:#dff5e3;
				color:#0f6b2f;
			}
			.bdss-status-expired{
				background:#fde2df;
				color:#a12622;
			}
			.bdss-status-cancelled,
			.bdss-status-refunded{
				background:#ececec;
				color:#555;
			}
			@media (max-width: 767px){
				.bdss-details-grid{
					grid-template-columns:1fr;
				}
			}
		</style>';
	}
}