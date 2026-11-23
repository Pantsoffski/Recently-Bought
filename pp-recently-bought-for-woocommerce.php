<?php
/**
 * Plugin Name: Recently Bought This for WooCommerce
 * Description: Plugin that popup little snippet on the WooCommerce shop (e.g. on the bottom of site) and tells customer that someone recently bought some product. If clicked - it takes to the product they bought.
 * Author: Piotr Pesta
 * Version: 0.4.0
 * Author URI: http://ordin.pl/
 * License: GPL12
 * Text Domain: recently-bought-for-woocommerce
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

add_action( 'plugins_loaded', 'pp_recently_bought_for_woocommerce_main_init' );
add_action( 'init', array( 'Someone_Recently_Bought_Init', 'load_translations' ) );
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/** Set up the plugin or register a notice when WooCommerce is unavailable. */
function pp_recently_bought_for_woocommerce_main_init() {
	if ( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_orders' ) ) {
		add_action( 'init', array( 'Someone_Recently_Bought_Init', 'init' ) );
		return;
	}
	add_action( 'admin_notices', array( 'Someone_Recently_Bought_Init', 'missing_woocommerce_notice' ) );
	add_action( 'network_admin_notices', array( 'Someone_Recently_Bought_Init', 'missing_woocommerce_notice' ) );
}
