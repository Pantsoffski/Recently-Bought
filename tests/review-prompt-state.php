<?php
/**
 * Manage synthetic review state for the browser regression.
 *
 * @package Recently_Bought_This
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '127.0.0.1' !== wp_parse_url( home_url(), PHP_URL_HOST ) ||
	! str_starts_with( get_option( 'blogname' ), 'Recently Bought Stage A' ) ) {
	exit( 1 );
}

global $wpdb;
$user  = get_user_by( 'login', $args[0] );
$key   = $wpdb->get_blog_prefix() . 'recently_bought_review_visit';
$shown = 'recently_bought_review_shown_' . $user->ID;

if ( 'state' === $args[1] ) {
	WP_CLI::line( wp_json_encode( array( get_user_meta( $user->ID, $key ), get_option( $shown ) ) ) );
	return;
}

delete_user_meta( $user->ID, $key );
delete_option( $shown );
foreach ( array_slice( $args, 2 ) as $age ) {
	add_user_meta( $user->ID, $key, time() - (int) $age );
}
