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
	private const DEFAULTS = array(
		'pnumber'        => 5,
		'text'           => 'recently bought',
		'randomize'      => 0,
		'corner'         => 1,
		'slide_in_delay' => 1,
		'time_display'   => 'none',
	);

	private const RANGES = array(
		'pnumber'        => array( 2, 10 ),
		'randomize'      => array( 0, 1 ),
		'corner'         => array( 0, 3 ),
		'slide_in_delay' => array( 0, 30 ),
	);

	/** Register hooks once WooCommerce is available. */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 5 );
		add_action( 'wp_enqueue_scripts', array( 'Someone_Recently_Bought_Main', 'enqueue' ) );
		add_action( 'wp_footer', array( 'Someone_Recently_Bought_Main', 'main_draw' ), 100 );
		add_filter( 'plugin_action_links_' . RECENTLY_BOUGHT_PLUGIN_BASENAME, array( __CLASS__, 'plugin_action_links_rb' ) );
	}

	/** Register the bundled translations. */
	public static function load_translations() {
		load_plugin_textdomain( 'recently-bought-this-for-woocommerce', false, dirname( RECENTLY_BOUGHT_PLUGIN_BASENAME ) . '/languages' );
	}

	/** Initialize missing options on activation. */
	public static function activate_plugin() {
		foreach ( self::DEFAULTS as $key => $default ) {
			add_option( 'recently_bought_settings_' . $key, $default );
		}
	}

	/** Remove the plugin options on uninstall. */
	public static function uninstall_plugin() {
		foreach ( self::DEFAULTS as $key => $default ) {
			delete_option( 'recently_bought_settings_' . $key );
		}
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
	 * Apply identical validation at save and read time.
	 *
	 * @param string $key   Setting suffix.
	 * @param mixed  $value Setting value.
	 * @return int|string Normalized setting.
	 */
	private static function normalize_setting( $key, $value ) {
		$default = self::DEFAULTS[ $key ];

		if ( 'text' === $key ) {
			return is_string( $value ) ? sanitize_text_field( $value ) : $default;
		}

		if ( 'time_display' === $key ) {
			return 'approximate' === $value ? $value : $default;
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
		add_menu_page( $title, $title, 'manage_options', 'recently-bought-settings', array( __CLASS__, 'options_page_rb' ), 'dashicons-cart' );
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
			?>
			<div class="notice notice-warning"><p><?php esc_html_e( 'Recently Bought This for WooCommerce requires WooCommerce to be installed and active.', 'recently-bought-this-for-woocommerce' ); ?></p></div>
			<?php
		}
	}
}

/** Selects verified purchases and renders storefront notifications. */
class Someone_Recently_Bought_Main {
	private const MAX_ORDER_PAGES = 3;

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

		wp_enqueue_style( 'wp-jquery-ui-dialog' );
		wp_enqueue_style( 'recently-bought-style', plugins_url( '_inc/recently-bought-style.css', __FILE__ ), array( 'wp-jquery-ui-dialog' ), '0.4.0' );
		wp_enqueue_script( 'recently-bought-notifications', plugins_url( '_inc/recently-bought.js', __FILE__ ), array( 'jquery-ui-dialog', 'jquery-effects-fade' ), '0.4.0', true );
	}

	/** Print escaped notification markup in an inert template. */
	public static function main_draw() {
		if ( ! self::$notifications ) {
			return;
		}
		$settings      = wp_json_encode(
			array(
				'corner' => Someone_Recently_Bought_Init::get_setting( 'corner' ),
				'delay'  => Someone_Recently_Bought_Init::get_setting( 'slide_in_delay' ),
			)
		);
		$allowed_image = array(
			'img' => array_fill_keys( array( 'alt', 'class', 'decoding', 'fetchpriority', 'height', 'loading', 'sizes', 'src', 'srcset', 'style', 'title', 'width' ), true ),
		);
		?>
		<template id="recently-bought-notifications" data-settings="<?php echo esc_attr( $settings ); ?>">
			<?php foreach ( self::$notifications as $notification ) : ?>
				<a href="<?php echo esc_url( $notification['url'] ); ?>">
					<?php echo wp_kses( $notification['image'], $allowed_image ); ?>
					<?php echo esc_html( $notification['message'] ); ?><br>
					<span id="productTitle"><?php echo esc_html( $notification['name'] ); ?></span>
					<?php if ( '' !== $notification['time'] ) : ?>
						<br><small><?php echo esc_html( $notification['time'] ); ?></small>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
		</template>
		<div id="justBought" title=""><p id="itemsToShow"></p></div>
		<?php
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
		$neutral_message = __( 'Purchased in this store', 'recently-bought-this-for-woocommerce' );
		$message         = $text;
		if ( 'recently bought' === $text ) {
			$message = __( 'Someone recently bought', 'recently-bought-this-for-woocommerce' );
		} elseif ( '' === $text ) {
			$message = $neutral_message;
		}
		$notifications = array();
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
				$product    = $product_id ? wc_get_product( $product_id ) : false;
				if ( ! $product || 'publish' !== $product->get_status() || ! is_post_type_viewable( get_post_type( $product_id ) ) || '' !== get_post_field( 'post_password', $product_id ) ) {
					continue;
				}
				$url  = sanitize_url( $product->get_permalink(), array( 'http', 'https' ) );
				$name = $item->get_name();
				if ( '' === $url || '' === trim( $name ) ) {
					continue;
				}

				$time            = self::purchase_time_label( $order->get_date_created(), $now );
				$notifications[] = array(
					'url'     => $url,
					'image'   => $product->get_image(
						'shop_thumbnail',
						array(
							'style' => 'height:80px;width:auto;',
							'class' => 'alignleft',
						),
						false
					),
					'name'    => $name,
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

		if ( 1 === Someone_Recently_Bought_Init::get_setting( 'randomize' ) ) {
			shuffle( $notifications );
		}
		return $notifications;
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
			return __( 'Ordered today', 'recently-bought-this-for-woocommerce' );
		}
		if ( 1 === $days ) {
			return __( 'Ordered yesterday', 'recently-bought-this-for-woocommerce' );
		}
		if ( $days <= 7 ) {
			return __( 'Ordered within the last 7 days', 'recently-bought-this-for-woocommerce' );
		}
		if ( $days <= 30 ) {
			return __( 'Ordered within the last 30 days', 'recently-bought-this-for-woocommerce' );
		}
		return '';
	}
}
