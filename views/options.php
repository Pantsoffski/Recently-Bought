<?php
/**
 * Settings page.
 *
 * @package Recently_Bought_This
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$recently_bought_corner       = Someone_Recently_Bought_Init::get_setting( 'corner' );
$recently_bought_time_display = Someone_Recently_Bought_Init::get_setting( 'time_display' );
$recently_bought_spacing      = Someone_Recently_Bought_Init::get_setting( 'bottom_spacing' );
?>
<div class="wrap">
	<h2><?php esc_html_e( 'Recently Bought This for WooCommerce - Settings', 'recently-bought-this-for-woocommerce' ); ?></h2>
	<?php Recently_Bought_Review_Prompt::render(); ?>
	<form method="post" action="options.php">
		<?php
		settings_fields( 'recently_bought_settings_group' );
		do_settings_sections( 'recently_bought_settings_group' );
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="recently-bought-text"><?php esc_html_e( 'Text to show:', 'recently-bought-this-for-woocommerce' ); ?></label></th>
				<td>
					<input id="recently-bought-text" type="text" name="recently_bought_settings_text" value="<?php echo esc_attr( Someone_Recently_Bought_Init::get_setting( 'text' ) ); ?>" aria-describedby="recently-bought-text-description">
					<p id="recently-bought-text-description" class="description"><?php esc_html_e( 'Enter the complete message as plain text. The default “recently bought” displays as “Someone recently bought”. Leaving the field empty uses the neutral purchase message below.', 'recently-bought-this-for-woocommerce' ); ?></p>
				</td>
			</tr>
			<tr>
				<td colspan="2">
					<h3><?php esc_html_e( 'Notification texts', 'recently-bought-this-for-woocommerce' ); ?></h3>
					<p id="recently-bought-notification-texts-description" class="description"><?php esc_html_e( 'Leave blank to use the WordPress translation.', 'recently-bought-this-for-woocommerce' ); ?></p>
				</td>
			</tr>
			<?php foreach ( array( 'text_today', 'text_yesterday', 'text_last_7', 'text_last_30', 'text_neutral' ) as $recently_bought_text_key ) : ?>
				<?php
				$recently_bought_text_id    = 'recently-bought-' . $recently_bought_text_key;
				$recently_bought_text_label = Someone_Recently_Bought_Init::default_notification_text( $recently_bought_text_key );
				?>
				<tr>
					<th scope="row"><label for="<?php echo esc_attr( $recently_bought_text_id ); ?>"><?php echo esc_html( $recently_bought_text_label ); ?></label></th>
					<td><input id="<?php echo esc_attr( $recently_bought_text_id ); ?>" type="text" class="regular-text" name="<?php echo esc_attr( 'recently_bought_settings_' . $recently_bought_text_key ); ?>" value="<?php echo esc_attr( Someone_Recently_Bought_Init::get_setting( $recently_bought_text_key ) ); ?>" placeholder="<?php echo esc_attr( $recently_bought_text_label ); ?>" aria-describedby="recently-bought-notification-texts-description"></td>
				</tr>
			<?php endforeach; ?>
			<tr>
				<th scope="row"><label for="recently-bought-number"><?php esc_html_e( 'Number of orders to show (2-10):', 'recently-bought-this-for-woocommerce' ); ?></label></th>
				<td><input id="recently-bought-number" type="number" name="recently_bought_settings_pnumber" value="<?php echo esc_attr( Someone_Recently_Bought_Init::get_setting( 'pnumber' ) ); ?>" min="2" max="10"></td>
			</tr>
			<tr>
				<th scope="row" id="recently-bought-corner-label"><?php esc_html_e( 'In which shop corner you wish to show it?', 'recently-bought-this-for-woocommerce' ); ?></th>
				<td>
					<fieldset aria-labelledby="recently-bought-corner-label">
					<label><input type="radio" name="recently_bought_settings_corner" value="0" <?php checked( 0, $recently_bought_corner ); ?>> <?php esc_html_e( 'Right-Top', 'recently-bought-this-for-woocommerce' ); ?></label><br>
					<label><input type="radio" name="recently_bought_settings_corner" value="1" <?php checked( 1, $recently_bought_corner ); ?>> <?php esc_html_e( 'Right-Bottom', 'recently-bought-this-for-woocommerce' ); ?></label><br>
					<label><input type="radio" name="recently_bought_settings_corner" value="2" <?php checked( 2, $recently_bought_corner ); ?>> <?php esc_html_e( 'Left-Bottom', 'recently-bought-this-for-woocommerce' ); ?></label><br>
					<label><input type="radio" name="recently_bought_settings_corner" value="3" <?php checked( 3, $recently_bought_corner ); ?>> <?php esc_html_e( 'Left-Top', 'recently-bought-this-for-woocommerce' ); ?></label>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row" id="recently-bought-spacing-label"><?php esc_html_e( 'Bottom spacing', 'recently-bought-this-for-woocommerce' ); ?></th>
				<td>
					<fieldset id="recently-bought-spacing" aria-labelledby="recently-bought-spacing-label">
						<p class="description"><?php esc_html_e( 'Applies to the bottom corners.', 'recently-bought-this-for-woocommerce' ); ?></p>
						<p><label><input type="radio" name="recently_bought_settings_bottom_spacing" value="automatic" aria-describedby="recently-bought-spacing-automatic-help" <?php checked( 'automatic', $recently_bought_spacing ); ?>> <?php esc_html_e( 'Automatic (recommended)', 'recently-bought-this-for-woocommerce' ); ?></label><br>
							<span class="description" id="recently-bought-spacing-automatic-help"><?php esc_html_e( 'Automatically avoid bottom navigation bars.', 'recently-bought-this-for-woocommerce' ); ?></span></p>
						<p><label><input type="radio" name="recently_bought_settings_bottom_spacing" value="standard" aria-describedby="recently-bought-spacing-standard-help" <?php checked( 'standard', $recently_bought_spacing ); ?>> <?php esc_html_e( 'Standard', 'recently-bought-this-for-woocommerce' ); ?></label><br>
							<span class="description" id="recently-bought-spacing-standard-help"><?php esc_html_e( 'Use the normal corner position without additional spacing.', 'recently-bought-this-for-woocommerce' ); ?></span></p>
						<p><label><input id="recently-bought-spacing-manual" type="radio" name="recently_bought_settings_bottom_spacing" value="manual" aria-describedby="recently-bought-spacing-manual-help" <?php checked( 'manual', $recently_bought_spacing ); ?>> <?php esc_html_e( 'Manual', 'recently-bought-this-for-woocommerce' ); ?></label><br>
							<span class="description" id="recently-bought-spacing-manual-help"><?php esc_html_e( 'Set an additional bottom offset for a theme or component that Automatic does not detect.', 'recently-bought-this-for-woocommerce' ); ?></span></p>
						<p class="recently-bought-manual-offset"><label for="recently-bought-bottom-offset"><?php esc_html_e( 'Additional bottom spacing (px)', 'recently-bought-this-for-woocommerce' ); ?></label>
							<input id="recently-bought-bottom-offset" type="number" name="recently_bought_settings_bottom_offset" value="<?php echo esc_attr( Someone_Recently_Bought_Init::get_setting( 'bottom_offset' ) ); ?>" min="0" max="300" step="1" class="small-text"></p>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="recently-bought-randomize"><?php esc_html_e( 'Randomize orders:', 'recently-bought-this-for-woocommerce' ); ?></label></th>
				<td><input id="recently-bought-randomize" type="checkbox" name="recently_bought_settings_randomize" value="1" <?php checked( 1, Someone_Recently_Bought_Init::get_setting( 'randomize' ) ); ?>></td>
			</tr>
			<tr>
				<th scope="row"><label for="recently-bought-delay"><?php esc_html_e( 'Popup after some delay (0-30 seconds):', 'recently-bought-this-for-woocommerce' ); ?></label></th>
				<td><input id="recently-bought-delay" type="number" name="recently_bought_settings_slide_in_delay" value="<?php echo esc_attr( Someone_Recently_Bought_Init::get_setting( 'slide_in_delay' ) ); ?>" min="0" max="30"></td>
			</tr>
			<tr>
				<th scope="row" id="recently-bought-time-label"><?php esc_html_e( 'Purchase time information', 'recently-bought-this-for-woocommerce' ); ?></th>
				<td>
					<fieldset aria-labelledby="recently-bought-time-label">
					<label><input type="radio" name="recently_bought_settings_time_display" value="none" <?php checked( 'none', $recently_bought_time_display ); ?>> <?php esc_html_e( 'Do not display purchase time', 'recently-bought-this-for-woocommerce' ); ?></label><br>
					<label><input type="radio" name="recently_bought_settings_time_display" value="approximate" <?php checked( 'approximate', $recently_bought_time_display ); ?>> <?php esc_html_e( 'Display approximate purchase time', 'recently-bought-this-for-woocommerce' ); ?></label>
					</fieldset>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>
</div>
