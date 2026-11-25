<?php
/**
 * Run with wp eval-file tests/integration.php <mode> on a disposable local store.
 *
 * @package Recently_Bought_This
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'Recently Bought Stage A' !== get_option( 'blogname' ) || '127.0.0.1' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
	exit( 1 );
}

add_filter( 'pre_wp_mail', '__return_true' );

function rbt_assert( $condition, $message ) {
	if ( ! $condition ) {
		WP_CLI::error( $message );
	}
}

function rbt_product( $name, $virtual = false ) {
	$product = new WC_Product_Simple();
	$product->set_name( $name );
	$product->set_status( 'publish' );
	$product->set_regular_price( '20' );
	$product->set_virtual( $virtual );
	$product->save();
	return $product;
}

function rbt_order( $product, $name, $parameters = array() ) {
	$parameters = wp_parse_args(
		$parameters,
		array(
			'status'      => 'processing',
			'method'      => 'cod',
			'price'       => 20,
			'quantity'    => 1,
			'date'        => time() - 120,
			'paid'        => null,
			'transaction' => '',
		)
	);
	$order      = wc_create_order( array( 'created_via' => 'recently-bought-stage-a' ) );
	$order->set_payment_method( $parameters['method'] );
	$order->set_date_created( $parameters['date'] );
	$order->set_shipping_first_name( 'TEST_PRIVATE_SURNAME_7391 Synthetic "Customer"' );
	$order->set_shipping_last_name( 'TEST_PRIVATE_FAMILY_7391' );
	$order->set_billing_email( 'test-private-7391@example.test' );
	$order->set_shipping_address_1( 'TEST_PRIVATE_ADDRESS_7391' );
	$order->set_billing_phone( '739100000001' );
	if ( $product ) {
		$item = new WC_Order_Item_Product();
		$item->set_product( $product );
		$item->set_name( $name );
		$item->set_quantity( $parameters['quantity'] );
		$item->set_subtotal( $parameters['price'] );
		$item->set_total( $parameters['price'] );
		$order->add_item( $item );
	}
	$order->calculate_totals( false );
	$order->set_status( $parameters['status'] );
	$order->set_date_paid( $parameters['paid'] );
	$order->set_transaction_id( $parameters['transaction'] );
	$order->save();
	return $order;
}

function rbt_refund( $order, $amount, $quantity = null ) {
	$items = $order->get_items();
	$item  = reset( $items );
	$lines = null === $quantity ? array() : array( $item->get_id() => array( 'qty' => $quantity, 'refund_total' => $amount, 'refund_tax' => array() ) );
	rbt_assert( ! is_wp_error( wc_create_refund( array( 'order_id' => $order->get_id(), 'amount' => $amount, 'line_items' => $lines ) ) ), 'Could not create a synthetic refund.' );
}

function rbt_names() {
	return array_column( Someone_Recently_Bought_Main::data_miner(), 'name' );
}

function rbt_clear_fixtures( $option ) {
	$fixture = get_option( $option, array() );
	foreach ( $fixture['orders'] ?? array() as $id ) {
		$order = wc_get_order( $id );
		if ( $order && 'recently-bought-stage-a' === $order->get_created_via() ) {
			$order->delete( true );
		}
	}
	foreach ( $fixture['products'] ?? array() as $id ) {
		$product = wc_get_product( $id );
		if ( $product && str_starts_with( $product->get_name(), 'Stage A Synthetic' ) ) {
			$product->delete( true );
		}
	}
	if ( ! empty( $fixture['attachment'] ) ) {
		wp_delete_attachment( $fixture['attachment'], true );
	}
	delete_option( $option );
}

function rbt_fixture_image() {
	$image    = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAGQAAABkCAIAAAD/gAIDAAAAo0lEQVR42u3QMQ0AAAgDsKlFDwbwigU+niZV0FQPR1EgS5YsWbJkKZAlS5YsWbIUyJIlS5YsWQpkyZIlS5YsBbJkyZIlS5YCWbJkyZIlS4EsWbJkyZKlQJYsWbJkyVIgS5YsWbJkKZAlS5YsWbIUyJIlS5YsWQpkyZIlS5YsBbJkyZIlS5YCWbJkyZIlS4EsWbJkyZKlQJYsWbJkyVIgS9Z31gJhnIHE3+PwcQAAAABJRU5ErkJggg==', true );
	$uploaded = wp_upload_bits( 'stage-a-synthetic.png', null, $image );
	rbt_assert( empty( $uploaded['error'] ), 'Could not upload the synthetic image.' );
	return wp_insert_attachment( array( 'post_title' => 'Synthetic product thumbnail', 'post_mime_type' => 'image/png' ), $uploaded['file'] );
}

$mode      = $args[0] ?? '';
$text_keys = array( 'text_today', 'text_yesterday', 'text_last_7', 'text_last_30', 'text_neutral' );
if ( 'fresh' === $mode || 'uninstalled' === $mode ) {
	$defaults = array( 'pnumber' => '5', 'text' => 'recently bought', 'randomize' => '0', 'corner' => '1', 'slide_in_delay' => '1', 'time_display' => 'none' );
	$defaults += array_fill_keys( $text_keys, '' ) + array( 'bottom_spacing' => 'automatic', 'bottom_offset' => '0' );
	foreach ( $defaults as $key => $default ) {
		$expected = 'fresh' === $mode ? $default : false;
		rbt_assert( $expected === get_option( 'recently_bought_settings_' . $key ), 'Incorrect activation or uninstall value: ' . $key );
	}
	WP_CLI::success( "$mode: all thirteen stored values have the expected lifecycle." );
	return;
}

if ( 'seed-legacy' === $mode ) {
	$fixture = rbt_product( 'Stage A Synthetic legacy product' );
	$order   = rbt_order( $fixture, 'Legacy item snapshot' );
	update_option( 'recently_bought_test_legacy', array( 'orders' => array( $order->get_id() ), 'products' => array( $fixture->get_id() ) ) );
	WP_CLI::success( 'Original 0.4.0 fixture created.' );
	return;
}

$legacies       = array( 'migration' => 0, 'migration-1.0.0' => 1, 'migration-1.0.1' => 2 );
$migration_mode = str_starts_with( $mode, 'seed-' ) ? substr( $mode, 5 ) : $mode;
if ( isset( $legacies[ $migration_mode ] ) ) {
	$version  = $legacies[ $migration_mode ];
	$expected = array( 'pnumber' => '10', 'text' => 'Someone already purchased this.', 'randomize' => '1', 'corner' => '0', 'slide_in_delay' => '0' );
	if ( $version > 0 ) {
		$expected['time_display'] = 'approximate';
	}
	if ( 2 === $version ) {
		foreach ( $text_keys as $key ) {
			$expected[ $key ] = 'Custom migration label: ' . $key;
		}
	}
	if ( str_starts_with( $mode, 'seed-' ) ) {
		foreach ( $expected as $key => $value ) {
			update_option( 'recently_bought_settings_' . $key, $value );
		}
		return;
	}
	foreach ( $expected as $key => $value ) {
		rbt_assert( $value === get_option( 'recently_bought_settings_' . $key ), 'Lost original option: ' . $key );
	}
	if ( 0 === $version ) {
		rbt_assert( ! get_option( 'recently_bought_settings_time_display' ), 'The test must upgrade without activation.' );
		rbt_assert( 'none' === Someone_Recently_Bought_Init::get_setting( 'time_display' ), 'Missing time option must default to none.' );
	}
	if ( $version < 2 ) {
		foreach ( $text_keys as $key ) {
			rbt_assert( false === get_option( 'recently_bought_settings_' . $key ) && '' === Someone_Recently_Bought_Init::get_setting( $key ), 'Missing text must fall back without writing an option during upgrade: ' . $key );
		}
	}
	rbt_assert( false === get_option( 'recently_bought_settings_bottom_spacing' ) && 'automatic' === Someone_Recently_Bought_Init::get_setting( 'bottom_spacing' ), 'Missing spacing mode must default to Automatic without a database write.' );
	rbt_assert( false === get_option( 'recently_bought_settings_bottom_offset' ) && 0 === Someone_Recently_Bought_Init::get_setting( 'bottom_offset' ), 'Missing offset must default to zero without a database write.' );
	$notifications = Someone_Recently_Bought_Main::data_miner();
	rbt_assert( array( 'Stage A Synthetic legacy product' ) === array_column( $notifications, 'name' ), 'Use the public catalog title for the original order.' );
	rbt_assert( 'Someone already purchased this.' === $notifications[0]['message'], 'The migrated message must appear once.' );
	rbt_assert( ( 0 === $version ) === ( '' === $notifications[0]['time'] ), 'Unexpected time label after upgrade.' );
	WP_CLI::success( 'Upgrade without reactivation preserved configuration and rendered the public catalog name.' );
	return;
}

if ( 'bottom-spacing' === $mode ) {
	Someone_Recently_Bought_Init::admin_init();
	foreach ( array( 'automatic', 'standard', 'manual', '<b>manual</b>', '', array( 'manual' ) ) as $value ) {
		update_option( 'recently_bought_settings_bottom_spacing', $value );
		$expected = in_array( $value, array( 'standard', 'manual' ), true ) ? $value : 'automatic';
		rbt_assert( $expected === get_option( 'recently_bought_settings_bottom_spacing' ) && $expected === Someone_Recently_Bought_Init::get_setting( 'bottom_spacing' ), 'Unexpected stored or normalized spacing mode.' );
	}
	foreach ( array( 0, '120', '300', '301', '-1', '80.5', array( '80' ), '81px' ) as $value ) {
		update_option( 'recently_bought_settings_bottom_offset', $value );
		$expected = in_array( $value, array( '120', '300' ), true ) ? (int) $value : 0;
		rbt_assert( (string) $expected === (string) get_option( 'recently_bought_settings_bottom_offset' ) && $expected === Someone_Recently_Bought_Init::get_setting( 'bottom_offset' ), 'Unexpected stored or normalized manual offset.' );
	}
	WP_CLI::success( 'Bottom spacing settings accept the three modes and offsets from 0 to 300; unsafe values use defaults.' );
	return;
}

if ( 'notification-texts' === $mode ) {
	Someone_Recently_Bought_Init::admin_init();
	$domain = 'recently-bought-this-for-woocommerce';
	$locale = 'en_US';
	add_filter( 'pre_determine_locale', static function () use ( &$locale ) {
		return $locale;
	} );
	$product = rbt_product( 'Stage A Synthetic translations' );
	$orders  = array();
	foreach ( array( 45, 12, 4, 1, 0 ) as $days ) {
		$orders[] = rbt_order( $product, 'TEST_PRIVATE_LINE_7391', array( 'date' => current_datetime()->modify( "-$days days" )->getTimestamp() ) );
	}
	update_option( 'recently_bought_settings_pnumber', 5 );
	update_option( 'recently_bought_settings_time_display', 'approximate' );
	update_option( 'recently_bought_settings_text', 'recently bought' );
	foreach ( $text_keys as $key ) {
		update_option( 'recently_bought_settings_' . $key, '' );
	}
	$english = array( 'Ordered today', 'Ordered yesterday', 'Ordered within the last 7 days', 'Ordered within the last 30 days', 'Purchased in this store' );
	$polish  = array( 'Zamówiono dzisiaj', 'Zamówiono wczoraj', 'Zamówiono w ostatnich 7 dniach', 'Zamówiono w ostatnich 30 dniach', 'Kupiono w tym sklepie' );
	$czech   = array( 'Objednáno dnes', 'Objednáno včera', 'Objednáno v posledních 7 dnech', 'Objednáno v posledních 30 dnech', 'Zakoupeno v tomto obchodě' );
	foreach ( array( 'en_US' => $english, 'pl_PL' => $polish, 'cs_CZ' => $english ) as $locale => $expected ) {
		unload_textdomain( $domain, true );
		$notices = Someone_Recently_Bought_Main::data_miner();
		rbt_assert( array_slice( $expected, 0, 4 ) === array_slice( array_column( $notices, 'time' ), 0, 4 ), 'Incorrect fallback time labels for ' . $locale );
		rbt_assert( $expected[4] === $notices[4]['message'] && '' === $notices[4]['time'], 'Incorrect neutral fallback for ' . $locale );
	}
	foreach ( array_combine( $text_keys, $czech ) as $key => $label ) {
		update_option( 'recently_bought_settings_' . $key, $label );
	}
	update_option( 'recently_bought_settings_text', 'Někdo nedávno koupil' );
	$notices = Someone_Recently_Bought_Main::data_miner();
	rbt_assert( array( 'Někdo nedávno koupil', 'Někdo nedávno koupil', 'Někdo nedávno koupil', 'Někdo nedávno koupil', $czech[4] ) === array_column( $notices, 'message' ), 'Store copy and neutral copy must apply to the correct order ages.' );
	rbt_assert( array_merge( array_slice( $czech, 0, 4 ), array( '' ) ) === array_column( $notices, 'time' ), 'All Czech overrides should be used verbatim.' );
	update_option( 'recently_bought_settings_text_today', '<script></script><img onerror="">Dziś & včera' );
	update_option( 'recently_bought_settings_text_yesterday', array( 'bad value' ) );
	update_option( 'recently_bought_settings_text_last_30', '0' );
	update_option( 'recently_bought_settings_text', '' );
	$locale = 'pl_PL';
	unload_textdomain( $domain, true );
	$notices = Someone_Recently_Bought_Main::data_miner();
	rbt_assert( array( 'Dziś & včera', $polish[1], $czech[2], '0', '' ) === array_column( $notices, 'time' ), 'Mixed text, gettext, sanitization and the literal zero must work independently.' );
	rbt_assert( array_fill( 0, 5, $czech[4] ) === array_column( $notices, 'message' ), 'Empty original message must use the configurable neutral copy.' );
	rbt_assert( 'Teksty powiadomień' === __( 'Notification texts', 'recently-bought-this-for-woocommerce' ), 'Missing Polish section heading.' );
	rbt_assert( 'Dolny odstęp' === __( 'Bottom spacing', 'recently-bought-this-for-woocommerce' ), 'Missing Polish spacing label.' );
	rbt_assert( 'Pozostaw puste, aby użyć tłumaczenia WordPressa.' === __( 'Leave blank to use the WordPress translation.', 'recently-bought-this-for-woocommerce' ), 'Missing Polish settings hint.' );
	update_option( 'recently_bought_settings_time_display', 'none' );
	rbt_assert( array_fill( 0, 5, '' ) === array_column( Someone_Recently_Bought_Main::data_miner(), 'time' ), 'Disabled time mode must omit custom labels.' );
	foreach ( $text_keys as $key ) {
		delete_option( 'recently_bought_settings_' . $key );
	}
	foreach ( $orders as $order ) {
		$order->delete( true );
	}
	$product->delete( true );
	WP_CLI::success( 'English, Polish and Czech fallback, all Czech overrides, mixed and unsafe input, neutral copy and disabled time mode.' );
	return;
}

if ( 'clear-legacy' === $mode || 'clear-frontend' === $mode ) {
	rbt_clear_fixtures( 'clear-legacy' === $mode ? 'recently_bought_test_legacy' : 'recently_bought_test_frontend' );
	return;
}

if ( 'clear-order-matrix' === $mode ) {
	foreach ( wc_get_orders( array( 'limit' => 100 ) ) as $order ) {
		if ( 'recently-bought-stage-a' === $order->get_created_via() ) {
			$order->delete( true );
		}
	}
	return;
}

if ( 'settings' === $mode ) {
	foreach ( array( 'time_display', 'slide_in_delay', 'corner' ) as $key ) {
		delete_option( 'recently_bought_settings_' . $key );
	}
	rbt_assert( 'none' === Someone_Recently_Bought_Init::get_setting( 'time_display' ), 'Missing time option.' );
	rbt_assert( 1 === Someone_Recently_Bought_Init::get_setting( 'slide_in_delay' ), 'Missing 0.4.0 delay.' );
	rbt_assert( 1 === Someone_Recently_Bought_Init::get_setting( 'corner' ), 'Missing 0.3.0 corner.' );
	$invalid = array( 'pnumber' => array( -1, 5 ), 'corner' => array( 4, 1 ), 'slide_in_delay' => array( 'invalid', 1 ), 'randomize' => array( array( 1 ), 0 ), 'time_display' => array( 'exact', 'none' ), 'text' => array( array( 'test' ), 'recently bought' ) );
	foreach ( $invalid as $key => $values ) {
		update_option( 'recently_bought_settings_' . $key, $values[0] );
		rbt_assert( $values[1] === Someone_Recently_Bought_Init::get_setting( $key ), 'Invalid stored option: ' . $key );
	}
	Someone_Recently_Bought_Init::admin_init();
	$registered = get_registered_settings();
	$valid      = array( 'pnumber' => array( '2', 2 ), 'corner' => array( '0', 0 ), 'slide_in_delay' => array( '0', 0 ), 'randomize' => array( '1', 1 ), 'time_display' => array( 'approximate', 'approximate' ), 'text' => array( '', '' ) );
	foreach ( $valid as $key => $values ) {
		$option = 'recently_bought_settings_' . $key;
		rbt_assert( isset( $registered[ $option ]['sanitize_callback'] ), 'Unregistered setting: ' . $key );
		update_option( $option, $values[0] );
		rbt_assert( $values[1] === Someone_Recently_Bought_Init::get_setting( $key ), 'Lost valid edge value: ' . $key );
	}
	update_option( 'recently_bought_settings_text', '<strong>Fixture</strong> "O’Neil" café' );
	rbt_assert( 'Fixture "O’Neil" café' === get_option( 'recently_bought_settings_text' ), 'Settings API must store plain text.' );
	$subscriber = wp_insert_user( array( 'user_login' => 'rbt-local-subscriber', 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
	rbt_assert( ! is_wp_error( $subscriber ), 'Cannot create test subscriber.' );
	wp_set_current_user( $subscriber );
	ob_start();
	Someone_Recently_Bought_Init::options_page_rb();
	rbt_assert( '' === ob_get_clean(), 'A subscriber must not see the settings form.' );
	wp_set_current_user( 0 );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $subscriber );
	WP_CLI::success( 'Settings validity, sanitization, empty text and legacy missing defaults.' );
	return;
}

if ( 'time' === $mode ) {
	$method = new ReflectionMethod( Someone_Recently_Bought_Main::class, 'purchase_time_label' );
	$zone   = new DateTimeZone( 'Europe/Warsaw' );
	$now    = new DateTimeImmutable( '2026-03-30 00:05:00', $zone );
	$labels = array( 0 => 'Ordered today', 1 => 'Ordered yesterday', 2 => 'Ordered within the last 7 days', 7 => 'Ordered within the last 7 days', 8 => 'Ordered within the last 30 days', 30 => 'Ordered within the last 30 days', 31 => '' );
	foreach ( $labels as $days => $label ) {
		$created = $now->modify( "-$days days" );
		rbt_assert( $label === $method->invoke( null, $created, $now ), 'Wrong label at day ' . $days );
	}
	rbt_assert( '' === $method->invoke( null, $now->modify( '+1 second' ), $now ), 'Future date must omit the label.' );
	rbt_assert( '' === $method->invoke( null, null, $now ), 'Missing date must omit the label.' );
	$midnight = new DateTimeImmutable( '2026-10-26 00:00:00', $zone );
	rbt_assert( 'Ordered yesterday' === $method->invoke( null, $midnight->modify( '-1 second' ), $midnight ), 'Midnight after autumn time change.' );
	$created = new DateTimeImmutable( '2026-03-29 23:59:00', $zone );
	$utc_now = $now->setTimezone( new DateTimeZone( 'UTC' ) );
	rbt_assert( 'Ordered yesterday' === $method->invoke( null, $created, $now ), 'Midnight in Warsaw.' );
	rbt_assert( 'Ordered today' === $method->invoke( null, $created, $utc_now ), 'Same instant is today in UTC.' );
	$product = rbt_product( 'Stage A Synthetic time test' );
	$past    = rbt_order( $product, 'Old purchase', array( 'date' => current_datetime()->modify( '-31 days' )->getTimestamp() ) );
	$future  = rbt_order( $product, 'Future date', array( 'date' => current_datetime()->modify( '+1 day' )->getTimestamp() ) );
	update_option( 'recently_bought_settings_text', 'Someone recently bought this.' );
	foreach ( array( 'none', 'approximate' ) as $time_mode ) {
		update_option( 'recently_bought_settings_time_display', $time_mode );
		$notifications = Someone_Recently_Bought_Main::data_miner();
		rbt_assert( array( 'Purchased in this store', 'Purchased in this store' ) === array_column( $notifications, 'message' ), 'Invalid or old dates must show neutral copy.' );
		rbt_assert( array( '', '' ) === array_column( $notifications, 'time' ), 'Old or future dates must omit the time.' );
	}
	rbt_assert( 'Someone recently bought this.' === get_option( 'recently_bought_settings_text' ), 'Neutral copy must leave the original option intact.' );
	$past->delete( true );
	$future->delete( true );
	$product->delete( true );
	add_filter( 'pre_determine_locale', static fn() => 'pl_PL' );
	unload_textdomain( 'recently-bought-this-for-woocommerce', true );
	rbt_assert( 'Zamówiono dzisiaj' === $method->invoke( null, $now, $now ), 'Polish purchase-time translation did not load.' );
	rbt_assert( 'Nie pokazuj czasu zakupu' === __( 'Do not display purchase time', 'recently-bought-this-for-woocommerce' ), 'Polish settings translation did not load.' );
	WP_CLI::success( 'Date buckets, future and missing dates, midnight, both DST changes, two zones and actual Polish translation.' );
	return;
}

if ( 'frontend' === $mode ) {
	rbt_clear_fixtures( 'recently_bought_test_frontend' );
	$product = rbt_product( 'Stage A Synthetic storefront product' );
	$second  = rbt_product( 'Stage A Synthetic second product' );
	$image   = rbt_fixture_image();
	update_post_meta( $image, '_wp_attachment_image_alt', 'Synthetic product swatch' );
	$product->set_image_id( $image );
	$product->save();
	$orders = array( rbt_order( $second, 'Second synthetic item' ), rbt_order( $product, 'TEST_PRIVATE_LINE_7391 <strong>Fixture</strong> & café' ) );
	update_option( 'recently_bought_test_frontend', array( 'orders' => array_map( static fn( $order ) => $order->get_id(), $orders ), 'products' => array( $product->get_id(), $second->get_id() ), 'attachment' => $image ) );
	update_option( 'recently_bought_settings_pnumber', 5 );
	update_option( 'recently_bought_settings_text', 'Someone bought: “fixture”; const sample = 1.' );
	update_option( 'recently_bought_settings_randomize', 0 );
	WP_CLI::success( 'Synthetic storefront fixtures created.' );
	return;
}

if ( 'cookie-scopes' === $mode ) {
	$method = new ReflectionMethod( Someone_Recently_Bought_Main::class, 'cookie_settings' );
	$home   = 'http://127.0.0.1:38989/';
	$filter = static function () use ( &$home ) {
		return $home;
	};
	add_filter( 'pre_option_home', $filter );
	$root = $method->invoke( null );
	$home = 'http://127.0.0.1:38989/store-a';
	$one  = $method->invoke( null );
	$home = 'https://127.0.0.1:38989/store-a';
	rbt_assert( $one === $method->invoke( null ), 'Changing the scheme must preserve the site-specific cookie name.' );
	$home = 'http://127.0.0.1:38989/store-b';
	$two  = $method->invoke( null );
	rbt_assert( '/' === $root['cookiePath'] && '/store-a' === $one['cookiePath'] && '/store-b' === $two['cookiePath'], 'Wrong storefront cookie paths.' );
	rbt_assert( 3 === count( array_unique( array_column( array( $root, $one, $two ), 'cookieName' ) ) ), 'Separate storefronts must have separate cookies.' );
	remove_filter( 'pre_option_home', $filter );
	WP_CLI::success( 'Scoped cookie for root, subdirectories and HTTPS.' );
	return;
}

if ( 'refresh' === $mode ) {
	$product = rbt_product( 'Stage A Synthetic refresh product' );
	$orders  = array( rbt_order( $product, 'Refund candidate' ), rbt_order( $product, 'Cancellation candidate' ), rbt_order( $product, 'Age candidate' ) );
	update_option( 'recently_bought_settings_time_display', 'approximate' );
	update_option( 'recently_bought_settings_pnumber', 5 );
	rbt_assert( 3 === count( rbt_names() ), 'Expected three current notifications before changes.' );
	rbt_refund( $orders[0], 20, 1 );
	$orders[1]->update_status( 'cancelled' );
	$orders[2]->set_date_created( current_datetime()->modify( '-8 days' )->getTimestamp() );
	$orders[2]->save();
	$remaining = Someone_Recently_Bought_Main::data_miner();
	rbt_assert( array( 'Stage A Synthetic refresh product' ) === array_column( $remaining, 'name' ), 'A fresh render must drop refunded and cancelled purchases.' );
	rbt_assert( 'Ordered within the last 30 days' === $remaining[0]['time'], 'A fresh render must recompute the age label.' );
	foreach ( $orders as $order ) {
		$order->delete( true );
	}
	$product->delete( true );
	WP_CLI::success( 'Fresh render respects refund, cancellation and age changes.' );
	return;
}

rbt_assert( in_array( $mode, array( 'cpt', 'hpos-on', 'hpos-off' ), true ), 'Unknown test mode.' );
rbt_assert( ( 'cpt' !== $mode ) === Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled(), 'Incorrect active order datastore.' );
rbt_assert( ( 'hpos-on' === $mode ) === ( 'yes' === get_option( 'woocommerce_custom_orders_table_data_sync_enabled' ) ), 'Incorrect synchronization setting.' );
update_option( 'recently_bought_settings_pnumber', 10 );
update_option( 'recently_bought_settings_randomize', 0 );
update_option( 'recently_bought_settings_time_display', 'none' );
update_option( 'recently_bought_settings_text', 'recently bought' );
rbt_assert( array() === rbt_names(), 'The order matrix must start with an empty store.' );

$product = rbt_product( 'Stage A Synthetic product', true );
$orders  = array();
$products = array();
for ( $number = 1; $number <= 11; ++$number ) {
	$numbered_product = rbt_product( 'Catalog ' . $number, true );
	$products[] = $numbered_product;
	$orders[] = rbt_order( $numbered_product, 'Private snapshot ' . $number );
	$count    = min( 10, $number );
	if ( in_array( $number, array( 1, 2, 5, 10, 11 ), true ) ) {
		rbt_assert( array_map( static fn( $i ) => 'Catalog ' . $i, range( $number, $number - $count + 1 ) ) === rbt_names(), 'Count or ID ordering failure.' );
	}
}
$created_after_switch = end( $orders );
rbt_assert( ( 'hpos-off' !== $mode ) === ( 'shop_order' === get_post_type( $created_after_switch->get_id() ) ), 'Unexpected CPT record for a newly created order.' );

update_option( 'recently_bought_settings_randomize', 1 );
$ordered = array_map( static fn( $i ) => 'Catalog ' . $i, range( 11, 2 ) );
rbt_assert( $ordered === rbt_names(), 'The server must keep stable ID order, including when the browser shuffles.' );
update_option( 'recently_bought_settings_randomize', 0 );
foreach ( $orders as $order ) {
	$order->delete( true );
}

$invoice_time = time() - 60;
$cases        = array(
	'online confirmed'   => array( 'method' => 'stripe', 'paid' => $invoice_time, 'transaction' => 'synthetic-order-123' ),
	'online manual edit' => array( 'method' => 'stripe', 'paid' => $invoice_time ),
	'online ID only'     => array( 'method' => 'stripe', 'transaction' => 'synthetic-order-456' ),
	'custom no proof'    => array( 'method' => 'custom_gateway' ),
	'COD processing'     => array(),
	'COD complete'       => array( 'status' => 'completed' ),
	'BACS on hold'       => array( 'status' => 'on-hold', 'method' => 'bacs' ),
	'BACS confirmed'     => array( 'status' => 'completed', 'method' => 'bacs' ),
	'CHEQUE confirmed'   => array( 'method' => 'cheque' ),
	'zero'               => array( 'method' => '', 'price' => 0 ),
	'pending'            => array( 'status' => 'pending' ),
	'cancelled'          => array( 'status' => 'cancelled' ),
	'failed'             => array( 'status' => 'failed' ),
	'checkout draft'     => array( 'status' => 'checkout-draft' ),
	'fully refunded'     => array(),
	'partially refunded' => array( 'price' => 40, 'quantity' => 2 ),
	'unallocated refund' => array(),
);
$orders       = array();
foreach ( $cases as $name => $parameters ) {
	$case_product = rbt_product( $name, true );
	$products[] = $case_product;
	$orders[ $name ] = rbt_order( $case_product, 'Private item snapshot', $parameters );
}
rbt_refund( $orders['fully refunded'], 20, 1 );
rbt_refund( $orders['partially refunded'], 20, 1 );
rbt_refund( $orders['unallocated refund'], 5 );

$selected_line_refunded = rbt_order( $product, 'First item fully refunded' );
$selected_line_refunded->add_product( $product, 1 );
$selected_line_refunded->calculate_totals( false );
rbt_refund( $selected_line_refunded, 20, 1 );
$orders[] = $selected_line_refunded;
$orders[] = rbt_order( null, 'empty order' );
$removed  = rbt_product( 'Stage A Synthetic deleted' );
$orders[] = rbt_order( $removed, 'deleted order product' );
$removed->delete( true );
$private  = rbt_product( 'Stage A Synthetic private' );
$orders[] = rbt_order( $private, 'private order product' );
$private->set_status( 'private' );
$private->save();

$parent = new WC_Product_Variable();
$parent->set_name( 'Stage A Synthetic parent' );
$parent->set_status( 'publish' );
$parent->save();
$child = new WC_Product_Variation();
$child->set_parent_id( $parent->get_id() );
$child->set_regular_price( '20' );
$child->save();
$orders[]      = rbt_order( $child, 'Variant purchase snapshot' );
$notifications = Someone_Recently_Bought_Main::data_miner();
$expected      = array( 'Stage A Synthetic parent', 'partially refunded', 'zero', 'CHEQUE confirmed', 'BACS confirmed', 'COD complete', 'COD processing', 'online confirmed' );
rbt_assert( $expected === array_column( $notifications, 'name' ), 'Wrong order eligibility: ' . implode( ', ', array_column( $notifications, 'name' ) ) );
rbt_assert( $parent->get_permalink() === $notifications[0]['url'], 'Variation link must use the parent.' );
rbt_assert( '' === $notifications[0]['image'], 'Missing image must not add a placeholder.' );
rbt_assert( 'Someone recently bought' === $notifications[0]['message'] && '' === $notifications[0]['time'], 'Default message and no time.' );

foreach ( $orders as $order ) {
	$order->delete( true );
}
$orders = array();
for ( $index = 0; $index < 12; ++$index ) {
	$orders[] = rbt_order( $product, 'Unconfirmed candidate', array( 'method' => 'stripe' ) );
}
update_option( 'recently_bought_settings_pnumber', 2 );
$query_count = 0;
add_filter(
	'woocommerce_order_query_args',
	static function ( $query ) use ( &$query_count ) {
		if ( 'shop_order' === $query['type'] && 'ID' === $query['orderby'] && 2 === $query['limit'] ) {
			++$query_count;
		}
		return $query;
	}
);
$names = rbt_names();
rbt_assert( array() === $names && 3 === $query_count, 'All rejected candidates must stop at the three-page limit: ' . $query_count . ' candidate queries, ' . count( $names ) . ' matches.' );
foreach ( $orders as $order ) {
	$order->delete( true );
}
$child->delete( true );
$parent->delete( true );
$private->delete( true );
$product->delete( true );
foreach ( $products as $test_product ) {
	$test_product->delete( true );
}
WP_CLI::success( "$mode: count, limit, bounded pages, shuffle, statuses, payment, refunds, guest and digital orders, variant, empty order and unavailable products." );
