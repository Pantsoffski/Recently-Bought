<?php
if (!defined('ABSPATH')) {
    exit; # Exit if accessed directly
}

require_once(plugin_dir_path(__FILE__) . '/../woocommerce/includes/admin/reports/class-wc-admin-report.php');
require_once(plugin_dir_path(__FILE__) . '/../woocommerce/includes/admin/reports/class-wc-report-sales-by-date.php');

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

    public static function admin_init() {
        
    }

    public static function admin_menu() {
        
    }

    public static function just_init() {
        if (!is_admin()) {
            wp_enqueue_script('jquery-ui-dialog');
            wp_enqueue_script('jquery-effects-core');
            wp_enqueue_script('jquery-effects-fade');
            wp_enqueue_style('wp-jquery-ui-dialog');
            wp_enqueue_style('pp_recently_bought_for_woocommerce_main_style', plugins_url('recently-bought-style.css', __FILE__));
        }
    }

}

class Someone_Recently_Bought_Main {

    public static function main_draw() {
        if (current_user_can('administrator')) {
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
                        var numberOfOrders = 4;
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
        }
    }

    public static function data_miner() {
        $args = array('post_type' => 'shop_order', 'category' => '', 'post_status' => 'wc-on-hold, wc-completed, wc-pending, wc-processing', 'orderby' => 'ID', 'order' => 'DESC', 'post_status' => 'publish', 'posts_per_page' => 5);
        $ordersToShow = get_posts($args); //gets args and return posts that match
        $counting = count($ordersToShow);
        for ($i = 1; $i <= $counting; $i++) {
            $c = $i - 1;
            $orders[$c] = new WC_Order($ordersToShow[$c]->ID);
            $items[$c] = $orders[$c]->get_items();
            $items[$c] = array_values($items[$c]);
            $htmlToShow[$c] = '<a href="' . get_permalink($items[$c][0]['product_id']) . '">' . get_the_post_thumbnail($items[$c][0]['product_id'], 'thumbnail', array('style' => 'height:80px;width:auto;', 'class' => 'alignleft')) . $orders[$c]->shipping_first_name . ' recently bought </br><span id="productTitle">' . $items[$c][0]['name'] . '</span></a>';
        }

        if (isset($htmlToShow)) {
            $toShow = json_encode($htmlToShow);
        } else {
            $toShow = '';
        }

        return $toShow;
    }

}
