<?php
/**
 * Plugin Name: Recently Bought This for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/recently-bought-this-for-woocommerce/
 * Description: Show linked product notifications for confirmed WooCommerce orders without revealing buyer details.
 * Author: Piotr Pesta
 * Author URI: https://profiles.wordpress.org/piotr-pesta/
 * Version: 1.0.3
 * Requires at least: 7.0
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce
 * WC requires at least: 11.2
 * WC tested up to: 11.3.1
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: recently-bought-this-for-woocommerce
 * Domain Path: /languages
 *
 * @package Recently_Bought_This
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RECENTLY_BOUGHT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RECENTLY_BOUGHT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
require_once RECENTLY_BOUGHT_PLUGIN_DIR . 'classes.php';

register_activation_hook( __FILE__, array( 'Someone_Recently_Bought_Init', 'activate_plugin' ) );
register_uninstall_hook( __FILE__, array( 'Someone_Recently_Bought_Init', 'uninstall_plugin' ) );

// Register the packaged fallback for JIT loading. WordPress checks installed language packs first.
$GLOBALS['wp_textdomain_registry']->set_custom_path( 'recently-bought-this-for-woocommerce', RECENTLY_BOUGHT_PLUGIN_DIR . 'languages' );

add_action( 'plugins_loaded', array( 'Someone_Recently_Bought_Init', 'plugins_loaded' ) );
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);
