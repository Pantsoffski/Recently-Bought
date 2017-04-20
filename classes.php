<?php
if (!defined('ABSPATH')) {
    exit; # Exit if accessed directly
}

class Someone_Recently_Bought_Init { # Initialization

    private static $initiated = false;

    public static function init() {
        if (!self::$initiated) {
            self::init_hooks();
        }
    }

    public static function init_hooks() {
        self::$initiated = true;
        add_action('admin_init', array('Someone_Recently_Bought_Init', 'admin_init'));
        add_action('wp_loaded', array('Someone_Recently_Bought_Init', 'just_init'));
        add_action('admin_menu', array('Someone_Recently_Bought_Init', 'admin_menu'), 5); # Priority 5
        add_action('wp_footer', array('Someone_Recently_Bought_Main', 'main_draw'), 100);
    }

    static function activate_plugin() {
        add_option('recently_bought_settings_pnumber', 5);
        add_option('recently_bought_settings_text', 'recently bought');
        add_option('recently_bought_settings_randomize', 0);
    }

    public static function uninstall_plugin() {
        delete_option('recently_bought_settings_pnumber');
        delete_option('recently_bought_settings_text');
        delete_option('recently_bought_settings_randomize');
    }

    public static function admin_init() {
        register_setting('recently_bought_settings_group', 'recently_bought_settings_text');
        register_setting('recently_bought_settings_group', 'recently_bought_settings_pnumber');
        register_setting('recently_bought_settings_group', 'recently_bought_settings_randomize');
    }

    public static function admin_menu() {
        $optionsTitle = __('Recently Bought', 'recently-bought');
        add_menu_page($optionsTitle, $optionsTitle, 'administrator', 'recently-bought-settings', array('Someone_Recently_Bought_Init', 'options_page'), 'dashicons-cart');
    }

    public static function just_init() {
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_script('jquery-effects-core');
        wp_enqueue_script('jquery-effects-fade');
        wp_enqueue_style('wp-jquery-ui-dialog');
        wp_enqueue_style('pp_recently_bought_for_woocommerce_main_style', plugins_url('_inc/recently-bought-style.css', __FILE__));
    }

    public static function options_page() {
        require_once( RECENTLY_BOUGHT_PLUGIN_DIR . 'views/options.php' );
    }

}

class Someone_Recently_Bought_Main {

    public static function main_draw() {
        //if (current_user_can('administrator')) {
        $toShow = self::data_miner();
        ?>

        <script type="text/javascript">
            jQuery(document).ready((function () {
                function getCookie(cname) {
                    var name = cname + "=";
                    var decodedCookie = decodeURIComponent(document.cookie);
                    var ca = decodedCookie.split(';');
                    for (var i = 0; i < ca.length; i++) {
                        var c = ca[i];
                        while (c.charAt(0) == ' ') {
                            c = c.substring(1);
                        }
                        if (c.indexOf(name) == 0) {
                            return c.substring(name.length, c.length);
                        }
                    }
                    return "";
                }

                var cCookie = getCookie("justBought");

                var toShow = <?php echo $toShow; ?>;

                if (cCookie == "" && typeof toShow !== 'undefined' && toShow.length > 0) {
                    jQuery('#justBought').dialog({
                        position: {my: 'right bottom', at: 'right bottom', of: window},
                        dialogClass: 'fixed-dialog',
                        draggable: false,
                        resizable: false,
                        //height: 'auto', //check that later
                        show: {effect: 'fade', duration: 1000},
                        hide: {effect: 'fade', duration: 1000},
                        close: function (event, ui) {
                            var date = new Date();
                            date.setTime(date.getTime() + (600 * 1000));
                            var expires = "; expires=" + date.toGMTString();
                            document.cookie = "justBought = closed;" + expires + "; path=/";
                        }
                    });

                    jQuery('#itemsToShow').html(toShow[0]);

                    var timesRun = 0;
                    var numberOfOrders = <?php echo get_option('recently_bought_settings_pnumber') ?> - 1;
                    var interval = setInterval(function () {
                        timesRun += 1;
                        if (timesRun > numberOfOrders) {
                            jQuery('#justBought').dialog('destroy');
                            jQuery('p').remove('#itemsToShow');
                            var date = new Date();
                            date.setTime(date.getTime() + (15 * 1000));
                            var expires = "; expires=" + date.toGMTString();
                            document.cookie = "justBought = closed;" + expires + "; path=/";
                            clearInterval(interval);
                        } else if (timesRun <= numberOfOrders) {
                            jQuery('#itemsToShow').fadeOut(500, function () {
                                jQuery(this).html(toShow[timesRun]).fadeIn(500);
                            });
                        }
                    }, 5 * 1000);
                }


            }));
        </script>
        <div id="justBought" title="">
            <p id="itemsToShow"></p>
        </div>

        <?php
        //}
    }

    public static function data_miner() {
        $args = array('post_type' => 'shop_order', 'category' => '', 'post_status' => 'wc-on-hold, wc-completed, wc-pending, wc-processing', 'orderby' => 'ID', 'order' => 'DESC', 'posts_per_page' => get_option('recently_bought_settings_pnumber'));
        $ordersToShow = get_posts($args); //gets args and return posts that match
        $counting = count($ordersToShow);
        for ($i = 1; $i <= $counting; $i++) {
            $c = $i - 1;
            $orders[$c] = new WC_Order($ordersToShow[$c]->ID);
            $items[$c] = $orders[$c]->get_items();
            $items[$c] = array_values($items[$c]);
            $htmlToShow[$c] = '<a href="' .
                    get_permalink($items[$c][0]['product_id']) .
                    '">' .
                    get_the_post_thumbnail($items[$c][0]['product_id'], 'shop_thumbnail', array('style' => 'height:80px;width:auto;', 'class' => 'alignleft')) .
                    @$orders[$c]->shipping_first_name .
                    ' ' .
                    get_option('recently_bought_settings_text') .
                    ' </br><span id="productTitle">' .
                    $items[$c][0]['name'] .
                    '</span></a>';
        }

        if (isset($htmlToShow)) {
            if (get_option('recently_bought_settings_randomize') == 1) { //randomizer
                shuffle($htmlToShow);
            }
            $toShow = json_encode($htmlToShow);
        } else {
            $toShow = '';
        }

        return $toShow;
    }

}
