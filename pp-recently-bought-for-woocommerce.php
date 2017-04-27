<?php

/*
 * Plugin Name: Recently Bought This for WooCommerce
 * Description: Plugin that popup little snippet on the WooCommerce shop (e.g. on the bottom of site) and tells customer that someone recently bought some product. If clicked - it takes to the product they bought.
 * Author: Piotr Pesta
 * Version: 0.3.0
 * Author URI: http://ordin.pl/
 * License: GPL12
 * Text Domain: recently-bought-for-woocommerce
 */

// Make sure we don't expose any info if called directly
if (!function_exists('add_action')) {
    echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
    exit;
}

define('RECENTLY_BOUGHT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RECENTLY_BOUGHT_PLUGIN_BASENAME', plugin_basename(__FILE__));
require_once( RECENTLY_BOUGHT_PLUGIN_DIR . 'classes.php' );

register_activation_hook(__FILE__, array('Someone_Recently_Bought_Init', 'activate_plugin'));
register_uninstall_hook(__FILE__, array('Someone_Recently_Bought_Init', 'uninstall_plugin'));

add_action('plugins_loaded', 'pp_recently_bought_for_woocommerce_main_init');

function pp_recently_bought_for_woocommerce_main_init() {
    if (class_exists('WooCommerce')) {
        add_action('init', array('Someone_Recently_Bought_Init', 'init'));
    } else {
        ?>
        <script type="text/javascript">
            alert("Recently Bought This plugin: WooCommerce not installed!! You need to install and activate WooCommerce to use this plugin!!");
        </script>
        <?php

    }
}
