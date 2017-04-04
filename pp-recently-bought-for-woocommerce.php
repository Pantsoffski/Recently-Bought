<?php

/*
 * Plugin Name: Someone Recently Bought This for WooCommerce
 * Plugin URI: http://ordin.pl/
 * Description: Plugin that popup little snippet on the WooCommerce shop (e.g. on the bottom of site) and tells customer that someone recently bought some product. If clicked - it takes to the product they bought.
 * Author: Piotr Pesta
 * Version: 0.1
 * Author URI: http://ordin.pl/
 * License: GPL12
 * Text Domain: recently-bought-for-woocommerce
 */
define('ADVANCED_DASHBOARD_PLUGIN_DIR', plugin_dir_path(__FILE__));
//register_activation_hook(__FILE__, array('Someone Recently Bought This for WooCommerce', 'plugin_activation'));
//register_deactivation_hook(__FILE__, array('Someone Recently Bought This for WooCommerce', 'plugin_deactivation'));
add_action('plugins_loaded', 'pp_recently_bought_for_woocommerce_main_init');

function pp_recently_bought_for_woocommerce_main_init() {
    if (class_exists('WooCommerce')) {
        require_once( ADVANCED_DASHBOARD_PLUGIN_DIR . 'classes.php' );
        add_action('init', array('Someone_Recently_Bought_Init', 'init'));
    } else {
        echo 'WooCommerce is not Active.';
    }
}
