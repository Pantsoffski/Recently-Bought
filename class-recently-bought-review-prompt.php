<?php
/**
 * One-time review prompt for the settings page.
 *
 * @package Recently_Bought_This
 */

defined( 'ABSPATH' ) || exit;

/** Tracks review eligibility for each authorized user and site. */
final class Recently_Bought_Review_Prompt {
	/** Number of settings visits required to show the prompt. */
	private const MIN_VISITS = 3;

	/** Site-prefixed user metadata for review visits. */
	private const VISIT_META = 'recently_bought_review_visit';

	/** Prefix for the one-time claim, stored as a site option. */
	private const SHOWN_OPTION = 'recently_bought_review_shown_';

	/**
	 * Whether this request claimed the prompt.
	 *
	 * @var bool
	 */
	private static $show = false;

	/** Process the settings page request before admin output. */
	public static function prepare() {
		if ( ! is_blog_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$method = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) );
		if ( 'POST' === $method && isset( $_POST['recently_bought_dismiss_review'] ) ) {
			$nonce = is_string( $_POST['recently_bought_dismiss_review'] ) ? sanitize_text_field( wp_unslash( $_POST['recently_bought_dismiss_review'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, self::nonce_action() ) ) {
				wp_nonce_ays( self::nonce_action() );
			}
			// The notice was persisted before rendering. Closing only returns to settings.
			wp_safe_redirect( self::settings_url(), 303 );
			exit;
		}

		self::$show = 'GET' === $method && self::record_visit();
		if ( self::$show ) {
			add_action( 'admin_print_styles', array( __CLASS__, 'add_styles' ) );
			wp_add_inline_script( 'common', "window.addEventListener( 'pagehide', function () { document.getElementById( 'recently-bought-review' )?.setAttribute( 'hidden', '' ); } );" );
		}
	}

	/** Add styles when the notice is present. */
	public static function add_styles() {
		wp_add_inline_style(
			'common',
			'#recently-bought-review { position: relative; padding-inline-end: 50px; overflow-wrap: anywhere; }' .
			'#recently-bought-review[hidden] { display: none; }' .
			'#recently-bought-review h3 { margin: 12px 0 4px; }' .
			'#recently-bought-review .recently-bought-review-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; margin: 8px 0 12px; }' .
			'#recently-bought-review .notice-dismiss { min-width: 44px; min-height: 44px; }'
		);
	}

	/** Render the notice outside the settings form. */
	public static function render() {
		if ( ! self::$show ) {
			return;
		}
		?>
		<section id="recently-bought-review" class="notice notice-info" aria-labelledby="recently-bought-review-heading">
			<h3 id="recently-bought-review-heading"><?php esc_html_e( 'Have you tried Recently Bought This for WooCommerce?', 'recently-bought-this-for-woocommerce' ); ?></h3>
			<p><?php esc_html_e( "We'd love to hear about your experience. Your feedback helps us improve the plugin and supports its continued development.", 'recently-bought-this-for-woocommerce' ); ?></p>
			<form class="recently-bought-review-actions" method="post" action="<?php echo esc_url( self::settings_url() ); ?>">
				<?php wp_nonce_field( self::nonce_action(), 'recently_bought_dismiss_review', false ); ?>
				<a class="button" href="https://wordpress.org/support/plugin/recently-bought-this-for-woocommerce/reviews/#new-post" rel="noreferrer"><?php esc_html_e( 'Leave a review', 'recently-bought-this-for-woocommerce' ); ?></a>
				<button class="button-link" type="submit"><?php esc_html_e( "Don't show again", 'recently-bought-this-for-woocommerce' ); ?></button>
				<button class="notice-dismiss" type="submit"><span class="screen-reader-text"><?php esc_html_e( 'Dismiss review request', 'recently-bought-this-for-woocommerce' ); ?></span></button>
			</form>
		</section>
		<?php
	}

	/**
	 * Record the visit and claim the notice before rendering it.
	 *
	 * @return bool Whether this request won the one-time claim.
	 */
	private static function record_visit() {
		global $wpdb;
		$user_id = get_current_user_id();
		$shown   = self::SHOWN_OPTION . $user_id;
		if ( get_option( $shown ) ) {
			return false;
		}

		$key    = $wpdb->get_blog_prefix() . self::VISIT_META;
		$now    = time();
		$visits = get_user_meta( $user_id, $key, false );
		if ( count( $visits ) < self::MIN_VISITS ) {
			// Append instead of overwriting a counter so concurrent visits are retained.
			if ( ! add_user_meta( $user_id, $key, $now ) ) {
				return false;
			}
			$visits = get_user_meta( $user_id, $key, false );
		}

		if ( count( $visits ) < self::MIN_VISITS || $now - (int) min( $visits ) < 7 * DAY_IN_SECONDS ) {
			return false;
		}

		// The unique option name and identical value let only one request claim the notice.
		// Autoloading these small flags allows uninstall to discover them, even for deleted users.
		return add_option( $shown, '1', '', true );
	}

	/** Remove review state on full uninstall, including sites in a multisite installation. */
	public static function uninstall() {
		if ( ! is_multisite() ) {
			self::delete_site_state();
			return;
		}

		$offset = 0;
		do {
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 100,
					'offset' => $offset,
				)
			);
			foreach ( $site_ids as $site_id ) {
				switch_to_blog( $site_id );
				self::delete_site_state();
				restore_current_blog();
			}
			$site_count = count( $site_ids );
			$offset    += $site_count;
		} while ( 100 === $site_count );
	}

	/** Delete the metadata and claims belonging to the current site. */
	private static function delete_site_state() {
		global $wpdb;

		wp_cache_delete( 'alloptions', 'options' );
		foreach ( array_keys( wp_load_alloptions() ) as $name ) {
			if ( str_starts_with( $name, self::SHOWN_OPTION ) ) {
				delete_option( $name );
			}
		}
		delete_metadata( 'user', 0, $wpdb->get_blog_prefix() . self::VISIT_META, '', true );
	}

	/**
	 * Tie dismissal to the current site.
	 *
	 * @return string
	 */
	private static function nonce_action() {
		return 'recently_bought_dismiss_review_' . get_current_blog_id();
	}

	/**
	 * Get the settings URL for the current site.
	 *
	 * @return string
	 */
	private static function settings_url() {
		return admin_url( 'admin.php?page=recently-bought-settings' );
	}
}
