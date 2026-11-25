<?php
/**
 * Populate a disposable local WordPress/WooCommerce storefront for catalog screenshots.
 *
 * Usage: wp eval-file release-preparation/design/storefront.php /path/to/rendered-demo-images
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '127.0.0.1' !== wp_parse_url( home_url(), PHP_URL_HOST ) || 'Recently Bought Stage A' !== get_option( 'blogname' ) ) {
	exit( 1 );
}

add_filter( 'pre_wp_mail', '__return_true' );
wp_set_current_user( 1 );
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$assets = $args[0] ?? '';
$catalog = array(
	array( 'Harbor Mug', 'harbor-mug', '28', 'A ribbed, sage-glazed stoneware mug.' ),
	array( 'Dune Bowl', 'dune-bowl', '34', 'A shallow terracotta bowl with a warm sand glaze.' ),
	array( 'Moss Vase', 'moss-vase', '46', 'A rounded stoneware vase with a soft olive glaze.' ),
);
$products = array();
foreach ( $catalog as list( $name, $slug, $price, $description ) ) {
	$temp = wp_tempnam( $slug . '.png' );
	if ( ! copy( $assets . '/' . $slug . '.png', $temp ) ) {
		WP_CLI::error( "Cannot read the original demo image for $name." );
	}
	$attachment = media_handle_sideload( array( 'name' => $slug . '.png', 'tmp_name' => $temp ), 0, $description );
	if ( is_wp_error( $attachment ) ) {
		WP_CLI::error( $attachment->get_error_message() );
	}
	update_post_meta( $attachment, '_wp_attachment_image_alt', $description );

	$product = new WC_Product_Simple();
	$product->set_name( $name );
	$product->set_slug( $slug );
	$product->set_status( 'publish' );
	$product->set_regular_price( $price );
	$product->set_description( $description );
	$product->set_short_description( $description );
	$product->set_image_id( $attachment );
	$product->save();
	$products[] = $product;
}

$order = wc_create_order( array( 'created_via' => 'rbt-release-demo' ) );
$order->set_date_created( current_datetime()->modify( '-1 day' )->getTimestamp() );
$order->set_payment_method( 'cod' );
$order->add_product( $products[0] );
$order->calculate_totals( false );
$order->set_status( 'processing' );
$order->save();

update_option( 'blogname', 'Morrow Kiln' );
update_option( 'blogdescription', 'Fictional ceramics studio' );
update_option( 'show_avatars', 0 );
wp_update_user( array( 'ID' => 1, 'display_name' => 'Demo Administrator' ) );
switch_theme( 'twentytwentyfive' );

$hero = esc_url( wp_get_attachment_url( $products[0]->get_image_id() ) );
$hero_alt = esc_attr( $catalog[0][3] );
$ids = implode( ',', array_map( static fn( $product ) => $product->get_id(), $products ) );
$content = <<<PAGE
<!-- wp:html -->
<style>
html{color-scheme:light dark}
body{margin:0;background:#f7f4eb;color:#203731}
.morrow-shell{max-width:1440px;margin:auto;font-family:system-ui,sans-serif}
.morrow-header{padding:0 5%;height:76px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #dcded2}
.morrow-brand{font-size:19px;font-weight:760;letter-spacing:.19em;text-decoration:none}
.morrow-nav{font-size:14px;text-decoration:none;color:#435b50}
.morrow-hero{margin:34px 5% 0;display:grid;grid-template-columns:1fr 1.08fr;min-height:380px;background:#e9ede1;border-radius:16px;overflow:hidden}
.morrow-copy{padding:clamp(28px,5.15vw,80px);align-content:center}
.morrow-copy h1{font-family:Georgia,serif;font-weight:400;font-size:clamp(39px,4.8vw,68px);line-height:1.02;letter-spacing:-.055em;margin:0 0 18px;color:inherit}
.morrow-copy p{max-width:29ch;font-size:15px;line-height:1.55;margin:0 0 23px;color:#425d51}
.morrow-hero a{display:inline-block;padding:11px 17px;border-radius:6px;background:#234d3e;color:#fff;font-size:14px;text-decoration:none}
.morrow-hero img{width:100%;height:100%;max-height:414px;object-fit:cover;display:block}
.morrow-collection{margin:34px 5% 56px}
.morrow-collection h2{font-family:Georgia,serif;font-size:36px;letter-spacing:-.04em;font-weight:400;margin:0 0 16px}
.morrow-collection .woocommerce ul.products li.product a img{border-radius:10px;margin-bottom:13px;width:100%;aspect-ratio:1.42;object-fit:cover}
.morrow-collection .woocommerce ul.products li.product h2{font-family:system-ui,sans-serif;font-size:17px;font-weight:650;letter-spacing:0}
.morrow-collection .woocommerce ul.products li.product a{color:inherit;text-decoration:none}
.morrow-collection .woocommerce ul.products li.product a.button{padding:8px 13px;border:1px solid #8f9f8c;border-radius:5px;background:#e9ede1;color:#234d3e;font-size:13px}
.morrow-collection .woocommerce ul.products li.product .price{color:inherit}
.morrow-dock{display:none}
@media(max-width:900px){
 .morrow-shell{padding-bottom:52px}
 .morrow-dock{position:fixed;z-index:9999;inset:auto 0 0;display:grid;grid-template-columns:repeat(3,1fr);height:52px;background:#f7f4eb;color:#203731;border-top:1px solid #bdc8ba;text-align:center}
 .morrow-dock a{display:grid;place-items:center;color:inherit;text-decoration:none;font-size:14px;font-weight:650}
 .morrow-dock a+a{border-left:1px solid #dcded2}
}
@media(max-width:640px){
 .morrow-header{height:58px}
 .morrow-brand{font-size:16px}
 .morrow-hero{margin-top:19px;grid-template-columns:1fr;min-height:0}
 .morrow-copy{padding:29px}
 .morrow-hero img{max-height:250px}
 .morrow-collection{margin-top:23px}
 .morrow-collection h2{font-size:30px}
}
@media(max-width:600px){
 .morrow-shell{padding-bottom:80px}
 .morrow-dock{height:80px}
}
@media(prefers-color-scheme:dark){
 body{background:#141f1b;color:#f1f2e9}
 .morrow-hero{background:#263b33}
 .morrow-header{border-color:#405248}
 .morrow-copy p,.morrow-nav{color:#cfddce}
 .morrow-hero a{background:#bcdcbb;color:#203c2d}
 .morrow-collection .woocommerce ul.products li.product a.button{background:#263b33;color:#e3f0e5}
 .morrow-dock{background:#1d2d27;color:#f1f2e9;border-color:#405248}
 .morrow-dock a+a{border-color:#405248}
}
</style>
<main class="morrow-shell">
  <header class="morrow-header"><a class="morrow-brand" href="/">MORROW KILN</a><a class="morrow-nav" href="#collection">Collection</a></header>
  <section class="morrow-hero">
    <div class="morrow-copy"><h1>Objects for<br>the everyday</h1><p>Glazed stoneware with soft colors and shapes for the table.</p><a href="#collection">Explore the collection</a></div>
    <img src="$hero" alt="$hero_alt" width="920" height="760" fetchpriority="high">
  </section>
  <section class="morrow-collection" id="collection"><h2>Studio collection</h2>
<!-- /wp:html -->
<!-- wp:shortcode -->
[products ids="$ids" columns="3" orderby="post__in" order="ASC"]
<!-- /wp:shortcode -->
<!-- wp:html -->
  </section>
</main>
<nav class="morrow-dock" aria-label="Shop"><a href="#collection">Collection</a><a href="/?post_type=product">Search</a><a href="/cart/">Cart</a></nav>
<!-- /wp:html -->
PAGE;

$page_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Morrow Kiln', 'post_name' => 'morrow-kiln', 'post_content' => wp_slash( $content ) ), true );
if ( is_wp_error( $page_id ) ) {
	WP_CLI::error( $page_id->get_error_message() );
}
$template_id = wp_insert_post(
	array(
		'post_type'    => 'wp_template',
		'post_status'  => 'publish',
		'post_name'    => 'front-page',
		'post_title'   => 'Morrow Kiln front page',
		'post_content' => '<!-- wp:post-content /-->',
	)
);
wp_set_object_terms( $template_id, 'twentytwentyfive', 'wp_theme' );
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $page_id );
update_option( 'recently_bought_settings_pnumber', 5 );
update_option( 'recently_bought_settings_text', 'recently bought' );
update_option( 'recently_bought_settings_randomize', 0 );
update_option( 'recently_bought_settings_slide_in_delay', 0 );
update_option( 'recently_bought_settings_corner', 1 );
update_option( 'recently_bought_settings_time_display', 'none' );

WP_CLI::success( 'Created the fictional Morrow Kiln storefront and catalog.' );
