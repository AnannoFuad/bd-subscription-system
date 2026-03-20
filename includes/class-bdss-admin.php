<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BDSS_Admin {

	const CACHE_GROUP = 'bdss_admin';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_settings_tools' ) );
	}

	public static function register_menu() {
		add_menu_page(
			__( 'BD Subscriptions', 'bd-simple-subscription' ),
			__( 'BD Subscriptions', 'bd-simple-subscription' ),
			'manage_options',
			'bdss-subscriptions',
			array( __CLASS__, 'render_page' ),
			'dashicons-lock',
			56
		);

		add_submenu_page(
			'bdss-subscriptions',
			__( 'Subscribers', 'bd-simple-subscription' ),
			__( 'Subscribers', 'bd-simple-subscription' ),
			'manage_options',
			'bdss-subscriptions',
			array( __CLASS__, 'render_page' )
		);

		add_submenu_page(
			'bdss-subscriptions',
			__( 'Settings', 'bd-simple-subscription' ),
			__( 'Settings', 'bd-simple-subscription' ),
			'manage_options',
			'bdss-settings',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	public static function handle_actions() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( 'bdss-subscriptions' !== $page ) {
			return;
		}

		$action          = isset( $_GET['bdss_action'] ) ? sanitize_key( wp_unslash( $_GET['bdss_action'] ) ) : '';
		$subscription_id = isset( $_GET['subscription_id'] ) ? absint( $_GET['subscription_id'] ) : 0;

		if ( empty( $action ) || empty( $subscription_id ) ) {
			return;
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'bdss_admin_action_' . $subscription_id . '_' . $action ) ) {
			wp_die( esc_html__( 'Security check failed.', 'bd-simple-subscription' ) );
		}

		$result = false;
		$msg    = 'failed';

		switch ( $action ) {
			case 'expire':
				if ( class_exists( 'BDSS_Subscription_Service' ) && method_exists( 'BDSS_Subscription_Service', 'expire_by_id' ) ) {
					$result = BDSS_Subscription_Service::expire_by_id( $subscription_id );
					$msg    = $result ? 'expired' : 'failed';
				}
				break;

			case 'activate':
				if ( class_exists( 'BDSS_Subscription_Service' ) && method_exists( 'BDSS_Subscription_Service', 'activate_by_id' ) ) {
					$result = BDSS_Subscription_Service::activate_by_id( $subscription_id );
					$msg    = $result ? 'activated' : 'failed';
				}
				break;
		}

		self::flush_admin_cache();

		$redirect_url = add_query_arg(
			array(
				'page'        => 'bdss-subscriptions',
				'bdss_notice' => $msg,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	public static function handle_settings_tools() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( 'bdss-settings' !== $page ) {
			return;
		}

		$tool = isset( $_GET['bdss_tool'] ) ? sanitize_key( wp_unslash( $_GET['bdss_tool'] ) ) : '';

		if ( empty( $tool ) ) {
			return;
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'bdss_settings_tool_' . $tool ) ) {
			wp_die( esc_html__( 'Security check failed.', 'bd-simple-subscription' ) );
		}

		$settings = BDSS_Settings::get_settings();
		$notice   = 'failed';

		switch ( $tool ) {
			case 'create_dashboard_page':
				$existing_dashboard_id = self::find_existing_dashboard_page();

				if ( $existing_dashboard_id ) {
					$settings['dashboard_page_id'] = (int) $existing_dashboard_id;
					update_option( 'bdss_settings', $settings );
					$notice = 'dashboard_page_assigned_existing';
					break;
				}

				$page_id = wp_insert_post(
					array(
						'post_title'   => 'My Subscription',
						'post_content' => "[bdss_subscription_status]\n\n[bdss_my_subscription]",
						'post_status'  => 'publish',
						'post_type'    => 'page',
					),
					true
				);

				if ( ! is_wp_error( $page_id ) && $page_id ) {
					$settings['dashboard_page_id'] = (int) $page_id;
					update_option( 'bdss_settings', $settings );
					$notice = 'dashboard_page_created';
				}
				break;

			case 'create_login_page':
				$existing_login_id = self::find_existing_login_page();

				if ( $existing_login_id ) {
					$settings['login_page_id'] = (int) $existing_login_id;
					update_option( 'bdss_settings', $settings );
					$notice = 'login_page_assigned_existing';
					break;
				}

				$page_id = wp_insert_post(
					array(
						'post_title'   => 'Login / My Account',
						'post_content' => '[woocommerce_my_account]',
						'post_status'  => 'publish',
						'post_type'    => 'page',
					),
					true
				);

				if ( ! is_wp_error( $page_id ) && $page_id ) {
					$settings['login_page_id'] = (int) $page_id;
					update_option( 'bdss_settings', $settings );
					$notice = 'login_page_created';
				}
				break;
		}

		$redirect_url = add_query_arg(
			array(
				'page'        => 'bdss-settings',
				'bdss_notice' => $notice,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	private static function find_existing_dashboard_page() {
		$assigned_page_id = absint( BDSS_Settings::get( 'dashboard_page_id' ) );

		if ( $assigned_page_id && 'page' === get_post_type( $assigned_page_id ) && 'trash' !== get_post_status( $assigned_page_id ) ) {
			return $assigned_page_id;
		}

		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $pages as $page_id ) {
			$content = (string) get_post_field( 'post_content', $page_id );

			if ( false !== strpos( $content, '[bdss_my_subscription]' ) || false !== strpos( $content, '[bdss_subscription_status]' ) ) {
				return (int) $page_id;
			}
		}

		return 0;
	}

	private static function find_existing_login_page() {
		$assigned_page_id = absint( BDSS_Settings::get( 'login_page_id' ) );

		if ( $assigned_page_id && 'page' === get_post_type( $assigned_page_id ) && 'trash' !== get_post_status( $assigned_page_id ) ) {
			return $assigned_page_id;
		}

		if ( function_exists( 'wc_get_page_id' ) ) {
			$my_account_page_id = absint( wc_get_page_id( 'myaccount' ) );
			if ( $my_account_page_id > 0 && 'trash' !== get_post_status( $my_account_page_id ) ) {
				return $my_account_page_id;
			}
		}

		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $pages as $page_id ) {
			$content = (string) get_post_field( 'post_content', $page_id );

			if ( false !== strpos( $content, '[woocommerce_my_account]' ) ) {
				return (int) $page_id;
			}
		}

		return 0;
	}

	public static function get_existing_plan_keys() {
		$cache_key = 'plan_keys';
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Needed to discover enabled subscription products in admin settings.
		$product_ids = get_posts(
			array(
				'post_type'      => array( 'product', 'product_variation' ),
				'post_status'    => array( 'publish', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_bdss_enable_subscription',
						'value' => 'yes',
					),
				),
			)
		);

		$plans = array();

		foreach ( $product_ids as $product_id ) {
			$plan_key = get_post_meta( $product_id, '_bdss_plan_key', true );
			if ( ! empty( $plan_key ) ) {
				$plans[] = sanitize_key( $plan_key );
			}
		}

		$plans = array_unique( array_filter( $plans ) );
		sort( $plans );

		wp_cache_set( $cache_key, $plans, self::CACHE_GROUP, MINUTE_IN_SECONDS * 10 );

		return $plans;
	}

	public static function get_existing_roles() {
		$cache_key = 'roles';
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		$roles = array();

		if ( function_exists( 'wp_roles' ) ) {
			$wp_roles = wp_roles();

			if ( $wp_roles && ! empty( $wp_roles->roles ) ) {
				foreach ( $wp_roles->roles as $role_key => $role_data ) {
					if ( 0 === strpos( $role_key, 'bdss_' ) ) {
						$roles[ $role_key ] = isset( $role_data['name'] ) ? $role_data['name'] : $role_key;
					}
				}
			}
		}

		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Needed to discover enabled subscription products in admin settings.
		$product_ids = get_posts(
			array(
				'post_type'      => array( 'product', 'product_variation' ),
				'post_status'    => array( 'publish', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_bdss_enable_subscription',
						'value' => 'yes',
					),
				),
			)
		);

		foreach ( $product_ids as $product_id ) {
			$role_slug = get_post_meta( $product_id, '_bdss_role_slug', true );

			if ( ! empty( $role_slug ) ) {
				$role_slug = sanitize_key( $role_slug );

				if ( ! isset( $roles[ $role_slug ] ) ) {
					$roles[ $role_slug ] = $role_slug;
				}
			}
		}

		asort( $roles );

		wp_cache_set( $cache_key, $roles, self::CACHE_GROUP, MINUTE_IN_SECONDS * 10 );

		return $roles;
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$search    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$status    = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$plan_key  = isset( $_GET['plan_key'] ) ? sanitize_key( wp_unslash( $_GET['plan_key'] ) ) : '';
		$role_slug = isset( $_GET['role_slug'] ) ? sanitize_key( wp_unslash( $_GET['role_slug'] ) ) : '';

		$rows          = self::get_subscription_rows( $search, $status, $plan_key, $role_slug );
		$total_count   = self::get_subscription_count();
		$active_count  = self::get_subscription_count( 'active' );
		$expired_count = self::get_subscription_count( 'expired' );

		$plan_options = self::get_existing_plan_keys();
		$role_options = self::get_existing_roles();

		echo '<div class="wrap bdss-admin-wrap">';
		echo '<h1>' . esc_html__( 'BD Subscriptions', 'bd-simple-subscription' ) . '</h1>';

		self::render_notice();
		self::render_admin_styles();

		echo '<div class="bdss-stat-grid">';
		echo '<div class="bdss-stat-card"><div class="bdss-stat-label">' . esc_html__( 'Total', 'bd-simple-subscription' ) . '</div><div class="bdss-stat-value">' . esc_html( $total_count ) . '</div></div>';
		echo '<div class="bdss-stat-card"><div class="bdss-stat-label">' . esc_html__( 'Active', 'bd-simple-subscription' ) . '</div><div class="bdss-stat-value">' . esc_html( $active_count ) . '</div></div>';
		echo '<div class="bdss-stat-card"><div class="bdss-stat-label">' . esc_html__( 'Expired', 'bd-simple-subscription' ) . '</div><div class="bdss-stat-value">' . esc_html( $expired_count ) . '</div></div>';
		echo '</div>';

		echo '<form method="get" class="bdss-filter-box">';
		echo '<input type="hidden" name="page" value="bdss-subscriptions" />';

		echo '<div class="bdss-filter-grid">';

		echo '<div class="bdss-filter-field">';
		echo '<label for="bdss-search">' . esc_html__( 'Search', 'bd-simple-subscription' ) . '</label>';
		echo '<input type="search" id="bdss-search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'User, email, plan, role, order...', 'bd-simple-subscription' ) . '" />';
		echo '</div>';

		echo '<div class="bdss-filter-field">';
		echo '<label for="bdss-status">' . esc_html__( 'Status', 'bd-simple-subscription' ) . '</label>';
		echo '<select id="bdss-status" name="status">';
		echo '<option value="">' . esc_html__( 'All', 'bd-simple-subscription' ) . '</option>';
		echo '<option value="active"' . selected( $status, 'active', false ) . '>' . esc_html__( 'Active', 'bd-simple-subscription' ) . '</option>';
		echo '<option value="expired"' . selected( $status, 'expired', false ) . '>' . esc_html__( 'Expired', 'bd-simple-subscription' ) . '</option>';
		echo '<option value="cancelled"' . selected( $status, 'cancelled', false ) . '>' . esc_html__( 'Cancelled', 'bd-simple-subscription' ) . '</option>';
		echo '<option value="refunded"' . selected( $status, 'refunded', false ) . '>' . esc_html__( 'Refunded', 'bd-simple-subscription' ) . '</option>';
		echo '</select>';
		echo '</div>';

		echo '<div class="bdss-filter-field">';
		echo '<label for="bdss-plan-key">' . esc_html__( 'Plan Key', 'bd-simple-subscription' ) . '</label>';
		echo '<select id="bdss-plan-key" name="plan_key">';
		echo '<option value="">' . esc_html__( 'All Plans', 'bd-simple-subscription' ) . '</option>';
		foreach ( $plan_options as $plan ) {
			echo '<option value="' . esc_attr( $plan ) . '"' . selected( $plan_key, $plan, false ) . '>' . esc_html( $plan ) . '</option>';
		}
		echo '</select>';
		echo '</div>';

		echo '<div class="bdss-filter-field">';
		echo '<label for="bdss-role-slug">' . esc_html__( 'Role', 'bd-simple-subscription' ) . '</label>';
		echo '<select id="bdss-role-slug" name="role_slug">';
		echo '<option value="">' . esc_html__( 'All Roles', 'bd-simple-subscription' ) . '</option>';
		foreach ( $role_options as $role_key => $role_label ) {
			echo '<option value="' . esc_attr( $role_key ) . '"' . selected( $role_slug, $role_key, false ) . '>' . esc_html( $role_label ) . '</option>';
		}
		echo '</select>';
		echo '</div>';

		echo '<div class="bdss-filter-actions">';
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Filter', 'bd-simple-subscription' ) . '</button> ';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=bdss-subscriptions' ) ) . '" class="button">' . esc_html__( 'Reset', 'bd-simple-subscription' ) . '</a>';
		echo '</div>';

		echo '</div>';
		echo '</form>';

		echo '<table class="widefat striped bdss-subscriber-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'ID', 'bd-simple-subscription' ) . '</th>';
		echo '<th>' . esc_html__( 'User', 'bd-simple-subscription' ) . '</th>';
		echo '<th>' . esc_html__( 'Email', 'bd-simple-subscription' ) . '</th>';
		echo '<th>' . esc_html__( 'Order', 'bd-simple-subscription' ) . '</th>';
		echo '<th>' . esc_html__( 'Product', 'bd-simple-subscription' ) . '</th>';
		echo '<th>' . esc_html__( 'Plan', 'bd-simple-subscription' ) . '</th>';
		echo '<th>' . esc_html__( 'Role', 'bd-simple-subscription' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'bd-simple-subscription' ) . '</th>';
		echo '<th>' . esc_html__( 'Starts', 'bd-simple-subscription' ) . '</th>';
		echo '<th>' . esc_html__( 'Expires', 'bd-simple-subscription' ) . '</th>';
		echo '<th>' . esc_html__( 'Action', 'bd-simple-subscription' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		if ( ! empty( $rows ) ) {
			foreach ( $rows as $row ) {
				$user_display   = $row->user_login ? $row->user_login : 'User #' . absint( $row->user_id );
				$user_email     = $row->user_email ? $row->user_email : '—';
				$product_title  = get_the_title( $row->product_id );
				$product_output = $product_title ? $product_title . ' (#' . absint( $row->product_id ) . ')' : 'Product #' . absint( $row->product_id );
				$status_label   = ucfirst( $row->status );
				$row_class      = self::get_row_class( $row->status );

				echo '<tr class="' . esc_attr( $row_class ) . '">';
				echo '<td>' . esc_html( $row->id ) . '</td>';
				echo '<td>' . esc_html( $user_display ) . '<br><small>(#' . esc_html( $row->user_id ) . ')</small></td>';
				echo '<td>' . esc_html( $user_email ) . '</td>';
				echo '<td>#' . esc_html( $row->order_id ) . '</td>';
				echo '<td>' . esc_html( $product_output ) . '</td>';
				echo '<td>' . esc_html( $row->plan_key ) . '</td>';
				echo '<td>' . esc_html( $row->role_slug ) . '</td>';
				echo '<td><span class="bdss-status-pill bdss-status-' . esc_attr( $row->status ) . '">' . esc_html( $status_label ) . '</span></td>';
				echo '<td>' . wp_kses_post( self::format_datetime( $row->starts_at ) ) . '</td>';
				echo '<td>' . wp_kses_post( self::format_datetime( $row->expires_at ) ) . '</td>';
				echo '<td>' . wp_kses_post( self::action_links( $row ) ) . '</td>';
				echo '</tr>';
			}
		} else {
			echo '<tr><td colspan="11">' . esc_html__( 'No subscriptions found.', 'bd-simple-subscription' ) . '</td></tr>';
		}

		echo '</tbody>';
		echo '</table>';
		echo '</div>';
	}

	private static function get_subscription_rows( $search, $status, $plan_key, $role_slug ) {
		global $wpdb;

		$search    = sanitize_text_field( $search );
		$status    = sanitize_key( $status );
		$plan_key  = sanitize_key( $plan_key );
		$role_slug = sanitize_key( $role_slug );

		$cache_key = 'rows_' . md5( wp_json_encode( array( $search, $status, $plan_key, $role_slug ) ) );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		$table_name  = BDSS_DB::subscriptions_table();
		$users_table = $wpdb->users;
		$like        = '%' . $wpdb->esc_like( $search ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin listing query for internal subscription table with prepared filters.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.*, u.user_login, u.user_email
				FROM {$table_name} s
				LEFT JOIN {$users_table} u ON s.user_id = u.ID
				WHERE ( %s = '' OR s.status = %s )
				AND ( %s = '' OR s.plan_key = %s )
				AND ( %s = '' OR s.role_slug = %s )
				AND (
					%s = ''
					OR u.user_login LIKE %s
					OR u.user_email LIKE %s
					OR s.plan_key LIKE %s
					OR s.role_slug LIKE %s
					OR CAST(s.order_id AS CHAR) LIKE %s
				)
				ORDER BY s.id DESC",
				$status,
				$status,
				$plan_key,
				$plan_key,
				$role_slug,
				$role_slug,
				$search,
				$like,
				$like,
				$like,
				$like,
				$like
			)
		);

		wp_cache_set( $cache_key, $rows, self::CACHE_GROUP, MINUTE_IN_SECONDS * 5 );

		return $rows;
	}

	private static function get_subscription_count( $status = '' ) {
		global $wpdb;

		$status    = sanitize_key( $status );
		$cache_key = 'count_' . ( $status ? $status : 'all' );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		$table_name = BDSS_DB::subscriptions_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin count query for internal subscription table with prepared filter.
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$table_name}
				WHERE ( %s = '' OR status = %s )",
				$status,
				$status
			)
		);

		wp_cache_set( $cache_key, $count, self::CACHE_GROUP, MINUTE_IN_SECONDS * 5 );

		return $count;
	}

	private static function render_admin_styles() {
		echo '<style>
			.bdss-stat-grid{
				display:flex;
				gap:20px;
				margin:20px 0 30px;
				flex-wrap:wrap;
			}
			.bdss-stat-card{
				background:#fff;
				border:1px solid #dcdcde;
				padding:22px 24px;
				min-width:170px;
			}
			.bdss-stat-label{
				font-size:16px;
				font-weight:600;
				color:#1d2327;
				margin-bottom:6px;
			}
			.bdss-stat-value{
				font-size:24px;
				line-height:1.2;
				color:#1d2327;
			}
			.bdss-filter-box{
				background:#fff;
				border:1px solid #dcdcde;
				padding:20px 22px;
				margin:0 0 24px;
			}
			.bdss-filter-grid{
				display:grid;
				grid-template-columns:1.2fr .6fr .8fr 1fr auto;
				gap:16px;
				align-items:end;
			}
			.bdss-filter-field label{
				display:block;
				margin-bottom:8px;
				font-weight:600;
			}
			.bdss-filter-field input,
			.bdss-filter-field select{
				width:100%;
			}
			.bdss-filter-actions{
				display:flex;
				gap:12px;
				align-items:center;
			}
			.bdss-subscriber-table td,
			.bdss-subscriber-table th{
				vertical-align:top;
			}
			.bdss-row-active{
				background:#eefaf1 !important;
			}
			.bdss-row-expired{
				background:#fff1f0 !important;
			}
			.bdss-row-cancelled,
			.bdss-row-refunded{
				background:#f8f8f8 !important;
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
			.bdss-action-link{
				display:inline-block;
				margin-right:10px;
				font-weight:600;
				text-decoration:none;
			}
			.bdss-action-expire{
				color:#b42318;
			}
			.bdss-action-activate{
				color:#1570ef;
			}
			@media (max-width: 1200px){
				.bdss-filter-grid{
					grid-template-columns:1fr 1fr;
				}
				.bdss-filter-actions{
					grid-column:1 / -1;
				}
			}
		</style>';
	}

	private static function get_row_class( $status ) {
		switch ( $status ) {
			case 'active':
				return 'bdss-row-active';
			case 'expired':
				return 'bdss-row-expired';
			case 'cancelled':
				return 'bdss-row-cancelled';
			case 'refunded':
				return 'bdss-row-refunded';
			default:
				return '';
		}
	}

	private static function format_datetime( $datetime ) {
		if ( empty( $datetime ) || '0000-00-00 00:00:00' === $datetime ) {
			return '—';
		}

		$timestamp = strtotime( $datetime );

		if ( ! $timestamp ) {
			return esc_html( $datetime );
		}

		$date = date_i18n( 'Y-m-d', $timestamp );
		$time = date_i18n( 'H:i:s', $timestamp );

		return esc_html( $date ) . '<br>' . esc_html( $time );
	}

	private static function render_notice() {
		if ( empty( $_GET['bdss_notice'] ) ) {
			return;
		}

		$notice = sanitize_key( wp_unslash( $_GET['bdss_notice'] ) );
		$text   = '';
		$class  = 'notice notice-success is-dismissible';

		switch ( $notice ) {
			case 'expired':
				$text = __( 'Subscription expired successfully.', 'bd-simple-subscription' );
				break;
			case 'activated':
				$text = __( 'Subscription activated successfully.', 'bd-simple-subscription' );
				break;
			case 'settings_saved':
				$text = __( 'Settings saved successfully.', 'bd-simple-subscription' );
				break;
			case 'dashboard_page_created':
				$text = __( 'Dashboard page created and assigned successfully.', 'bd-simple-subscription' );
				break;
			case 'dashboard_page_assigned_existing':
				$text = __( 'Existing dashboard page found and assigned. No duplicate page was created.', 'bd-simple-subscription' );
				break;
			case 'login_page_created':
				$text = __( 'Login / account page created and assigned successfully.', 'bd-simple-subscription' );
				break;
			case 'login_page_assigned_existing':
				$text = __( 'Existing login / account page found and assigned. No duplicate page was created.', 'bd-simple-subscription' );
				break;
			default:
				$text  = __( 'Action failed.', 'bd-simple-subscription' );
				$class = 'notice notice-error is-dismissible';
				break;
		}

		echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $text ) . '</p></div>';
	}

	private static function action_links( $row ) {
		$links = array();

		if ( 'active' === $row->status ) {
			$expire_url = wp_nonce_url(
				add_query_arg(
					array(
						'page'            => 'bdss-subscriptions',
						'bdss_action'     => 'expire',
						'subscription_id' => $row->id,
					),
					admin_url( 'admin.php' )
				),
				'bdss_admin_action_' . $row->id . '_expire'
			);

			$links[] = '<a class="bdss-action-link bdss-action-expire" href="' . esc_url( $expire_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Are you sure you want to expire this subscription?', 'bd-simple-subscription' ) ) . '\');">' . esc_html__( 'Expire', 'bd-simple-subscription' ) . '</a>';
		}

		if ( 'expired' === $row->status ) {
			$activate_url = wp_nonce_url(
				add_query_arg(
					array(
						'page'            => 'bdss-subscriptions',
						'bdss_action'     => 'activate',
						'subscription_id' => $row->id,
					),
					admin_url( 'admin.php' )
				),
				'bdss_admin_action_' . $row->id . '_activate'
			);

			$links[] = '<a class="bdss-action-link bdss-action-activate" href="' . esc_url( $activate_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Are you sure you want to activate this subscription again?', 'bd-simple-subscription' ) ) . '\');">' . esc_html__( 'Activate', 'bd-simple-subscription' ) . '</a>';
		}

		if ( empty( $links ) ) {
			return '—';
		}

		return implode( '', $links );
	}

	private static function flush_admin_cache() {
		wp_cache_delete( 'plan_keys', self::CACHE_GROUP );
		wp_cache_delete( 'roles', self::CACHE_GROUP );
		wp_cache_delete( 'count_all', self::CACHE_GROUP );
		wp_cache_delete( 'count_active', self::CACHE_GROUP );
		wp_cache_delete( 'count_expired', self::CACHE_GROUP );
	}

	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings   = BDSS_Settings::get_settings();
		$pages      = get_pages(
			array(
				'sort_column' => 'post_title',
				'sort_order'  => 'asc',
			)
		);
		$categories = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'BD Subscription Settings', 'bd-simple-subscription' ) . '</h1>';

		self::render_notice();

		echo '<form method="post" action="">';
		wp_nonce_field( 'bdss_save_settings_action', 'bdss_settings_nonce' );

		echo '<table class="form-table">';

		echo '<tr>';
		echo '<th scope="row"><label for="default_teaser_mode">' . esc_html__( 'Default Teaser Mode', 'bd-simple-subscription' ) . '</label></th>';
		echo '<td>';
		echo '<select id="default_teaser_mode" name="default_teaser_mode">';
		echo '<option value="words"' . selected( $settings['default_teaser_mode'], 'words', false ) . '>' . esc_html__( 'Words', 'bd-simple-subscription' ) . '</option>';
		echo '<option value="more"' . selected( $settings['default_teaser_mode'], 'more', false ) . '>' . esc_html__( 'More Tag', 'bd-simple-subscription' ) . '</option>';
		echo '<option value="excerpt"' . selected( $settings['default_teaser_mode'], 'excerpt', false ) . '>' . esc_html__( 'Excerpt', 'bd-simple-subscription' ) . '</option>';
		echo '</select>';
		echo '</td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="default_teaser_words">' . esc_html__( 'Default Teaser Words', 'bd-simple-subscription' ) . '</label></th>';
		echo '<td>';
		echo '<input type="number" min="10" step="1" id="default_teaser_words" name="default_teaser_words" value="' . esc_attr( $settings['default_teaser_words'] ) . '" />';
		echo '</td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="default_locker_message">' . esc_html__( 'Default Locker Message', 'bd-simple-subscription' ) . '</label></th>';
		echo '<td>';
		echo '<textarea id="default_locker_message" name="default_locker_message" rows="4" class="large-text">' . esc_textarea( $settings['default_locker_message'] ) . '</textarea>';
		echo '</td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="hide_plugin_locker">' . esc_html__( 'Disable Plugin Locker UI', 'bd-simple-subscription' ) . '</label></th>';
		echo '<td>';
		echo '<label><input type="checkbox" id="hide_plugin_locker" name="hide_plugin_locker" value="1" ' . checked( $settings['hide_plugin_locker'], 'yes', false ) . ' /> ' . esc_html__( 'Hide the default plugin locker box and show only teaser / excerpt content.', 'bd-simple-subscription' ) . '</label>';
		echo '</td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="expiring_soon_days">' . esc_html__( 'Expiring Soon Threshold (Days)', 'bd-simple-subscription' ) . '</label></th>';
		echo '<td>';
		echo '<input type="number" min="1" step="1" id="expiring_soon_days" name="expiring_soon_days" value="' . esc_attr( $settings['expiring_soon_days'] ) . '" />';
		echo '</td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="purchase_destination_type">' . esc_html__( 'Subscription Purchase Destination', 'bd-simple-subscription' ) . '</label></th>';
		echo '<td>';
		echo '<select id="purchase_destination_type" name="purchase_destination_type">';
		echo '<option value="shop"' . selected( $settings['purchase_destination_type'], 'shop', false ) . '>' . esc_html__( 'WooCommerce Shop Page', 'bd-simple-subscription' ) . '</option>';
		echo '<option value="product_category"' . selected( $settings['purchase_destination_type'], 'product_category', false ) . '>' . esc_html__( 'Specific Product Category', 'bd-simple-subscription' ) . '</option>';
		echo '</select>';
		echo '</td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="purchase_destination_category">' . esc_html__( 'Subscription Product Category', 'bd-simple-subscription' ) . '</label></th>';
		echo '<td>';
		echo '<select id="purchase_destination_category" name="purchase_destination_category">';
		echo '<option value="0">' . esc_html__( '-- Select Product Category --', 'bd-simple-subscription' ) . '</option>';

		if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
			foreach ( $categories as $category ) {
				echo '<option value="' . esc_attr( $category->term_id ) . '"' . selected( absint( $settings['purchase_destination_category'] ), (int) $category->term_id, false ) . '>' . esc_html( $category->name ) . '</option>';
			}
		}

		echo '</select>';
		echo '</td>';
		echo '</tr>';

		echo '<tr><th colspan="2"><hr></th></tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="dashboard_page_id">' . esc_html__( 'Subscription Dashboard Page', 'bd-simple-subscription' ) . '</label></th>';
		echo '<td>';
		echo '<select id="dashboard_page_id" name="dashboard_page_id">';
		echo '<option value="0">' . esc_html__( '-- Select Page --', 'bd-simple-subscription' ) . '</option>';

		foreach ( $pages as $page ) {
			echo '<option value="' . esc_attr( $page->ID ) . '"' . selected( absint( $settings['dashboard_page_id'] ), (int) $page->ID, false ) . '>' . esc_html( $page->post_title ) . '</option>';
		}

		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Assign a page for [bdss_subscription_status] and [bdss_my_subscription].', 'bd-simple-subscription' ) . '</p>';

		$create_dashboard_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'      => 'bdss-settings',
					'bdss_tool' => 'create_dashboard_page',
				),
				admin_url( 'admin.php' )
			),
			'bdss_settings_tool_create_dashboard_page'
		);

		echo '<p><a href="' . esc_url( $create_dashboard_url ) . '" class="button">' . esc_html__( 'Assign or Create Dashboard Page', 'bd-simple-subscription' ) . '</a></p>';
		echo '</td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="login_page_id">' . esc_html__( 'Login / Account Page', 'bd-simple-subscription' ) . '</label></th>';
		echo '<td>';
		echo '<select id="login_page_id" name="login_page_id">';
		echo '<option value="0">' . esc_html__( '-- Select Page --', 'bd-simple-subscription' ) . '</option>';

		foreach ( $pages as $page ) {
			echo '<option value="' . esc_attr( $page->ID ) . '"' . selected( absint( $settings['login_page_id'] ), (int) $page->ID, false ) . '>' . esc_html( $page->post_title ) . '</option>';
		}

		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Use the WooCommerce My Account page or a page containing [woocommerce_my_account].', 'bd-simple-subscription' ) . '</p>';

		$create_login_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'      => 'bdss-settings',
					'bdss_tool' => 'create_login_page',
				),
				admin_url( 'admin.php' )
			),
			'bdss_settings_tool_create_login_page'
		);

		echo '<p><a href="' . esc_url( $create_login_url ) . '" class="button">' . esc_html__( 'Assign or Create Login / Account Page', 'bd-simple-subscription' ) . '</a></p>';
		echo '</td>';
		echo '</tr>';

		echo '</table>';

		echo '<p class="submit">';
		echo '<button type="submit" name="bdss_save_settings" value="1" class="button button-primary">' . esc_html__( 'Save Settings', 'bd-simple-subscription' ) . '</button>';
		echo '</p>';

		echo '</form>';
		echo '</div>';
	}
}