<?php
/**
 * Settings and storefront notifications.
 *
 * @package Recently_Bought_This
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Registers the plugin settings and hooks. */
class Someone_Recently_Bought_Init {
	private const MIN_WOOCOMMERCE = '11.2';

	private const DEFAULTS = array(
		'pnumber'        => 5,
		'text'           => 'recently bought',
		'randomize'      => 0,
		'corner'         => 1,
		'slide_in_delay' => 1,
		'time_display'   => 'none',
		'text_today'     => '',
		'text_yesterday' => '',
		'text_last_7'    => '',
		'text_last_30'   => '',
		'text_neutral'   => '',
		'bottom_spacing' => 'automatic',
		'bottom_offset'  => 0,
	);

	private const RANGES = array(
		'pnumber'        => array( 2, 10 ),
		'randomize'      => array( 0, 1 ),
		'corner'         => array( 0, 3 ),
		'slide_in_delay' => array( 0, 30 ),
		'bottom_offset'  => array( 0, 300 ),
	);

	/** Register hooks once WooCommerce is available. */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 5 );
		add_action( 'wp_enqueue_scripts', array( 'Someone_Recently_Bought_Main', 'enqueue' ) );
		add_action( 'wp_footer', array( 'Someone_Recently_Bought_Main', 'main_draw' ), 100 );
		add_filter( 'plugin_action_links_' . RECENTLY_BOUGHT_PLUGIN_BASENAME, array( __CLASS__, 'plugin_action_links_rb' ) );
	}

	/** Set up the plugin when the WooCommerce dependency is available. */
	public static function plugins_loaded() {
		if ( defined( 'WC_VERSION' ) && function_exists( 'wc_get_orders' ) && version_compare( WC_VERSION, self::MIN_WOOCOMMERCE, '>=' ) ) {
			add_action( 'init', array( __CLASS__, 'init' ) );
			return;
		}
		add_action( 'admin_notices', array( __CLASS__, 'missing_woocommerce_notice' ) );
		add_action( 'network_admin_notices', array( __CLASS__, 'missing_woocommerce_notice' ) );
	}

	/** Initialize missing options on activation. */
	public static function activate_plugin() {
		foreach ( self::DEFAULTS as $key => $default ) {
			add_option( 'recently_bought_settings_' . $key, $default );
		}
	}

	/** Remove the plugin options and review state on uninstall. */
	public static function uninstall_plugin() {
		foreach ( self::DEFAULTS as $key => $default ) {
			delete_option( 'recently_bought_settings_' . $key );
		}

		require_once RECENTLY_BOUGHT_PLUGIN_DIR . 'class-recently-bought-review-prompt.php';
		Recently_Bought_Review_Prompt::uninstall();
	}

	/** Register settings and their sanitizers with WordPress. */
	public static function admin_init() {
		foreach ( self::DEFAULTS as $key => $default ) {
			register_setting(
				'recently_bought_settings_group',
				'recently_bought_settings_' . $key,
				array(
					'type'              => is_int( $default ) ? 'integer' : 'string',
					'default'           => $default,
					'sanitize_callback' => static function ( $value ) use ( $key ) {
						return self::normalize_setting( $key, $value );
					},
				)
			);
		}
	}

	/**
	 * Read a setting with a safe fallback for upgrades and old data.
	 *
	 * @param string $key Setting suffix.
	 * @return int|string Normalized setting.
	 */
	public static function get_setting( $key ) {
		return self::normalize_setting( $key, get_option( 'recently_bought_settings_' . $key, self::DEFAULTS[ $key ] ) );
	}

	/**
	 * Get the translated default for a customizable notification label.
	 *
	 * @param string $key Notification text setting suffix.
	 * @return string Label in the current WordPress locale.
	 */
	public static function default_notification_text( $key ) {
		return match ( $key ) {
			'text_today'     => __( 'Ordered today', 'recently-bought-this-for-woocommerce' ),
			'text_yesterday' => __( 'Ordered yesterday', 'recently-bought-this-for-woocommerce' ),
			'text_last_7'    => __( 'Ordered within the last 7 days', 'recently-bought-this-for-woocommerce' ),
			'text_last_30'   => __( 'Ordered within the last 30 days', 'recently-bought-this-for-woocommerce' ),
			'text_neutral'   => __( 'Purchased in this store', 'recently-bought-this-for-woocommerce' ),
		};
	}

	/**
	 * Resolve a notification label with a per-field translation fallback.
	 *
	 * @param string $key Notification text setting suffix.
	 * @return string Plain-text label.
	 */
	public static function notification_text( $key ) {
		$text = self::get_setting( $key );
		return '' === $text ? self::default_notification_text( $key ) : $text;
	}

	/**
	 * Apply identical validation at save and read time.
	 *
	 * @param string $key   Setting suffix.
	 * @param mixed  $value Setting value.
	 * @return int|string Normalized setting.
	 */
	private static function normalize_setting( $key, $value ) {
		$default = self::DEFAULTS[ $key ];

		if ( 'text' === $key || '' === $default ) {
			return is_string( $value ) ? sanitize_text_field( $value ) : $default;
		}

		if ( 'time_display' === $key ) {
			return 'approximate' === $value ? $value : $default;
		}
		if ( 'bottom_spacing' === $key ) {
			return in_array( $value, array( 'standard', 'manual' ), true ) ? $value : $default;
		}

		if ( ! is_int( $value ) && ! is_string( $value ) ) {
			return $default;
		}
		list( $min, $max ) = self::RANGES[ $key ];
		$number            = filter_var(
			$value,
			FILTER_VALIDATE_INT,
			array(
				'options' => array(
					'min_range' => $min,
					'max_range' => $max,
				),
			)
		);
		return false === $number ? $default : $number;
	}

	/** Add the existing settings page to the admin menu. */
	public static function admin_menu() {
		$title = __( 'Recently Bought', 'recently-bought-this-for-woocommerce' );
		$page  = add_menu_page( $title, $title, 'manage_options', 'recently-bought-settings', array( __CLASS__, 'options_page_rb' ), 'dashicons-cart' );
		add_action( 'load-' . $page, array( __CLASS__, 'load_options_page' ) );
		add_action(
			'admin_print_styles-' . $page,
			static function () {
				wp_add_inline_style( 'common', '#recently-bought-spacing:not(:has(#recently-bought-spacing-manual:checked)) .recently-bought-manual-offset { display: none; }' );
			}
		);
	}

	/** Load the review prompt only on the plugin's own settings screen. */
	public static function load_options_page() {
		require_once RECENTLY_BOUGHT_PLUGIN_DIR . 'class-recently-bought-review-prompt.php';
		Recently_Bought_Review_Prompt::prepare();
	}

	/** Render the settings page for authorized users. */
	public static function options_page_rb() {
		if ( current_user_can( 'manage_options' ) ) {
			require_once RECENTLY_BOUGHT_PLUGIN_DIR . 'views/options.php';
		}
	}

	/**
	 * Add a link to settings on the Plugins screen.
	 *
	 * @param string[] $links Existing plugin action links.
	 * @return string[] Plugin action links.
	 */
	public static function plugin_action_links_rb( $links ) {
		if ( current_user_can( 'manage_options' ) ) {
			$links = array(
				'settings' => '<a href="' . esc_url( admin_url( 'admin.php?page=recently-bought-settings' ) ) . '">' . esc_html__( 'Settings', 'recently-bought-this-for-woocommerce' ) . '</a>',
			) + $links;
		}
		return $links;
	}

	/** Show the dependency notice to users who can act on it. */
	public static function missing_woocommerce_notice() {
		if ( current_user_can( 'activate_plugins' ) || current_user_can( 'manage_network_plugins' ) ) {
			$message = defined( 'WC_VERSION' )
				? __( 'Recently Bought This for WooCommerce requires WooCommerce 11.2 or newer.', 'recently-bought-this-for-woocommerce' )
				: __( 'Recently Bought This for WooCommerce requires WooCommerce to be installed and active.', 'recently-bought-this-for-woocommerce' );
			?>
			<div class="notice notice-warning"><p><?php echo esc_html( $message ); ?></p></div>
			<?php
		}
	}
}

/** Selects verified purchases and renders storefront notifications. */
class Someone_Recently_Bought_Main {
	private const MAX_ORDER_PAGES = 3;
	private const ASSET_VERSION   = '1.0.2-2';

	/**
	 * Storefront payload prepared for the current response.
	 *
	 * @var array[]
	 */
	private static $notifications = array();

	/** Load the dialog and its resources only when there are notifications. */
	public static function enqueue() {
		self::$notifications = self::data_miner();
		if ( ! self::$notifications ) {
			return;
		}

		wp_enqueue_style( 'recently-bought-style', plugins_url( '_inc/recently-bought-style.css', __FILE__ ), array(), self::ASSET_VERSION );
		wp_enqueue_script( 'recently-bought-notifications', plugins_url( '_inc/recently-bought.js', __FILE__ ), array( 'jquery-ui-dialog' ), self::ASSET_VERSION, true );
	}

	/** Print escaped notification markup in an inert template. */
	public static function main_draw() {
		if ( ! self::$notifications ) {
			return;
		}
		$settings      = wp_json_encode(
			array(
				'corner'        => Someone_Recently_Bought_Init::get_setting( 'corner' ),
				'delay'         => Someone_Recently_Bought_Init::get_setting( 'slide_in_delay' ),
				'randomize'     => 1 === Someone_Recently_Bought_Init::get_setting( 'randomize' ),
				'closeText'     => __( 'Close notification', 'recently-bought-this-for-woocommerce' ),
				'bottomSpacing' => Someone_Recently_Bought_Init::get_setting( 'bottom_spacing' ),
				'bottomOffset'  => Someone_Recently_Bought_Init::get_setting( 'bottom_offset' ),
			) + self::cookie_settings()
		);
		$allowed_image = array(
			'img' => array_fill_keys( array( 'alt', 'class', 'decoding', 'fetchpriority', 'height', 'loading', 'sizes', 'src', 'srcset', 'style', 'title', 'width' ), true ),
		);
		?>
		<template id="recently-bought-notifications" data-settings="<?php echo esc_attr( $settings ); ?>">
			<?php foreach ( self::$notifications as $notification ) : ?>
				<a href="<?php echo esc_url( $notification['url'] ); ?>">
					<?php echo wp_kses( $notification['image'], $allowed_image ); ?>
					<span class="recently-bought-copy">
						<span><?php echo esc_html( $notification['message'] ); ?></span>
						<span id="productTitle" title="<?php echo esc_attr( $notification['name'] ); ?>"><?php echo esc_html( $notification['name'] ); ?></span>
						<?php if ( '' !== $notification['time'] ) : ?>
							<small><?php echo esc_html( $notification['time'] ); ?></small>
						<?php endif; ?>
					</span>
				</a>
			<?php endforeach; ?>
		</template>
		<div id="justBought" title="<?php esc_attr_e( 'Recent purchases', 'recently-bought-this-for-woocommerce' ); ?>"><p id="itemsToShow"></p></div>
		<?php
	}

	/**
	 * Scope dismissal to the storefront, including sites sharing a hostname.
	 *
	 * @return string[] Public cookie settings for this site.
	 */
	private static function cookie_settings() {
		$home = wp_parse_url( home_url( '/' ) );
		$path = untrailingslashit( $home['path'] ?? '/' );
		$path = '' === $path ? '/' : $path;
		$site = strtolower( $home['host'] ?? '' ) . ':' . ( $home['port'] ?? '' ) . $path;

		return array(
			'cookieName' => 'recentlyBoughtClosed_' . substr( hash( 'sha256', $site ), 0, 16 ),
			'cookiePath' => $path,
		);
	}

	/**
	 * Collect public product information from a limited set of confirmed orders.
	 *
	 * @return array[] Storefront notifications in display order.
	 */
	public static function data_miner() {
		$limit           = Someone_Recently_Bought_Init::get_setting( 'pnumber' );
		$text            = Someone_Recently_Bought_Init::get_setting( 'text' );
		$include_time    = 'approximate' === Someone_Recently_Bought_Init::get_setting( 'time_display' );
		$now             = new DateTimeImmutable( 'now', new DateTimeZone( wc_timezone_string() ) );
		$neutral_message = Someone_Recently_Bought_Init::notification_text( 'text_neutral' );
		$message         = $text;
		if ( 'recently bought' === $text ) {
			$message = __( 'Someone recently bought', 'recently-bought-this-for-woocommerce' );
		} elseif ( '' === $text ) {
			$message = $neutral_message;
		}
		$notifications = array();
		$products      = array();
		for ( $page = 1; $page <= self::MAX_ORDER_PAGES; ++$page ) {
			$orders = wc_get_orders(
				array(
					'type'    => 'shop_order',
					'status'  => array( 'wc-processing', 'wc-completed' ),
					'orderby' => 'ID',
					'order'   => 'DESC',
					'limit'   => $limit,
					'page'    => $page,
				)
			);
			foreach ( $orders as $order ) {
				if ( ! self::is_confirmed( $order, $now ) ) {
					continue;
				}
				$items = $order->get_items( 'line_item' );
				$item  = reset( $items );
				if ( ! $item instanceof WC_Order_Item_Product || $item->get_quantity() <= 0 || ! self::has_remaining_purchase( $order, $item ) ) {
					continue;
				}
				$product_id = $item->get_product_id();
				if ( ! array_key_exists( $product_id, $products ) ) {
					$products[ $product_id ] = self::product_presentation( $product_id );
				}
				if ( ! $products[ $product_id ] ) {
					continue;
				}

				$time            = self::purchase_time_label( $order->get_date_created(), $now );
				$notifications[] = $products[ $product_id ] + array(
					'message' => '' === $time ? $neutral_message : $message,
					'time'    => $include_time ? $time : '',
				);
				if ( count( $notifications ) === $limit ) {
					break 2;
				}
			}
			if ( count( $orders ) < $limit ) {
				break;
			}
		}

		return $notifications;
	}

	/**
	 * Get the shared presentation for a publicly viewable product.
	 *
	 * @param int $product_id Product or variation parent ID.
	 * @return string[]|null Public product data, if available.
	 */
	private static function product_presentation( $product_id ) {
		$product = $product_id ? wc_get_product( $product_id ) : false;
		if ( ! $product || 'publish' !== $product->get_status() || ! is_post_type_viewable( get_post_type( $product_id ) ) || '' !== get_post_field( 'post_password', $product_id ) ) {
			return null;
		}
		// Use the catalog title. An order item name may include customer-specific personalization.
		$name = $product->get_name( 'edit' );
		$url  = sanitize_url( $product->get_permalink(), array( 'http', 'https' ) );
		if ( '' === $url || '' === trim( $name ) ) {
			return null;
		}
		return array(
			'url'   => $url,
			'name'  => $name,
			'image' => $product->get_image(
				'shop_thumbnail',
				array(
					'style' => 'height:80px;width:auto;',
					'class' => 'alignleft',
				),
				false
			),
		);
	}

	/**
	 * Check order status and the evidence available for the payment method.
	 *
	 * @param WC_Order          $order Candidate order.
	 * @param DateTimeImmutable $now   Current time in the site timezone.
	 * @return bool Whether the purchase is confirmed.
	 */
	private static function is_confirmed( WC_Order $order, DateTimeImmutable $now ) {
		if ( ! $order->has_status( array( 'processing', 'completed' ) ) ) {
			return false;
		}
		$total = (float) $order->get_total();
		if ( $total < 0 ) {
			return false;
		}
		if ( 0.0 === $total || in_array( $order->get_payment_method(), array( 'cod', 'bacs', 'cheque' ), true ) ) {
			return true;
		}

		// WooCommerce can set date_paid on a manual status change. Also require a transaction reference.
		$paid = $order->get_date_paid();
		return $paid && $paid->getTimestamp() <= $now->getTimestamp() && '' !== trim( $order->get_transaction_id() );
	}

	/**
	 * Check that the selected product still represents a purchase after refunds.
	 *
	 * @param WC_Order              $order Candidate order.
	 * @param WC_Order_Item_Product $item  First product on the order.
	 * @return bool Whether the product is still eligible.
	 */
	private static function has_remaining_purchase( WC_Order $order, WC_Order_Item_Product $item ) {
		$refunds = $order->get_refunds();
		if ( ! $refunds ) {
			return true;
		}

		$decimals       = wc_get_price_decimals();
		$total_refunded = 0.0;
		foreach ( $refunds as $refund ) {
			$amount          = (float) $refund->get_amount();
			$allocated       = 0.0;
			$total_refunded += $amount;
			foreach ( $refund->get_items( array( 'line_item', 'fee', 'shipping' ) ) as $refund_item ) {
				$allocated -= (float) $refund_item->get_total() + (float) $refund_item->get_total_tax();
			}
			// An unallocated refund could have covered the selected product.
			if ( round( $amount - $allocated, $decimals ) > 0 ) {
				return false;
			}
		}

		$total = (float) $order->get_total();
		if ( $total > 0 && round( $total - $total_refunded, $decimals ) <= 0 ) {
			return false;
		}
		if ( abs( $order->get_qty_refunded_for_item( $item->get_id() ) ) >= $item->get_quantity() ) {
			return false;
		}

		$item_total = (float) $item->get_total();
		return $item_total <= 0 || round( $item_total - $order->get_total_refunded_for_item( $item->get_id() ), $decimals ) > 0;
	}

	/**
	 * Turn the private order timestamp into a public calendar-day label.
	 *
	 * @param DateTimeInterface|null $created Order creation time.
	 * @param DateTimeImmutable      $now     Current time in the site timezone.
	 * @return string Translated label or an empty string for unusable dates.
	 */
	private static function purchase_time_label( $created, DateTimeImmutable $now ) {
		if ( ! $created || $created->getTimestamp() > $now->getTimestamp() ) {
			return '';
		}
		$order_day = $now->setTimestamp( $created->getTimestamp() )->setTime( 0, 0 );
		$days      = $order_day->diff( $now->setTime( 0, 0 ) )->days;
		if ( 0 === $days ) {
			return Someone_Recently_Bought_Init::notification_text( 'text_today' );
		}
		if ( 1 === $days ) {
			return Someone_Recently_Bought_Init::notification_text( 'text_yesterday' );
		}
		if ( $days <= 7 ) {
			return Someone_Recently_Bought_Init::notification_text( 'text_last_7' );
		}
		if ( $days <= 30 ) {
			return Someone_Recently_Bought_Init::notification_text( 'text_last_30' );
		}
		return '';
	}
}
