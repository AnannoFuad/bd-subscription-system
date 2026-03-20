<?php
/**
 * Plugin Name: BD Subscription System for WooCommerce
 * Description: WooCommerce-based subscription and content protection plugin with fixed-duration access, automatic expiry, post/page protection, subscriber management, and frontend subscription status pages.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Muahtasim Fuad
 * Author URI: https://mfuad.pro/
 * Text Domain: bd-simple-subscription
 * Domain Path: /languages
 * WC requires at least: 7.0
 * WC tested up to: 9.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'before_woocommerce_init',
	function() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'product_instance_caching', __FILE__, true );
		}
	}
);

define( 'BDSS_VERSION', '1.0.0' );
define( 'BDSS_DB_VERSION', '1.0.2' );
define( 'BDSS_PLUGIN_FILE', __FILE__ );
define( 'BDSS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BDSS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once BDSS_PLUGIN_DIR . 'includes/class-bdss-db.php';
require_once BDSS_PLUGIN_DIR . 'includes/class-bdss-logger.php';
require_once BDSS_PLUGIN_DIR . 'includes/class-bdss-settings.php';
require_once BDSS_PLUGIN_DIR . 'includes/class-bdss-cta.php';
require_once BDSS_PLUGIN_DIR . 'includes/class-bdss-activator.php';
require_once BDSS_PLUGIN_DIR . 'includes/class-bdss-product-fields.php';
require_once BDSS_PLUGIN_DIR . 'includes/class-bdss-order-handler.php';
require_once BDSS_PLUGIN_DIR . 'includes/class-bdss-subscription-service.php';
require_once BDSS_PLUGIN_DIR . 'includes/class-bdss-access.php';
require_once BDSS_PLUGIN_DIR . 'includes/class-bdss-content-protection.php';
require_once BDSS_PLUGIN_DIR . 'includes/class-bdss-post-locker.php';
require_once BDSS_PLUGIN_DIR . 'includes/class-bdss-user-dashboard.php';
require_once BDSS_PLUGIN_DIR . 'includes/class-bdss-admin.php';
require_once BDSS_PLUGIN_DIR . 'includes/class-bdss-plugin.php';

register_activation_hook( __FILE__, array( 'BDSS_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'BDSS_Activator', 'deactivate' ) );

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function( $links ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=bdss-settings' ) ) . '">' . esc_html__( 'Settings', 'bd-simple-subscription' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
);

BDSS_Plugin::instance();