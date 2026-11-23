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
?>
<div class="wrap">
	<h2><?php esc_html_e( 'Recently Bought This for WooCommerce - Settings', 'recently-bought-this-for-woocommerce' ); ?></h2>
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
					<p id="recently-bought-text-description" class="description"><?php esc_html_e( 'Enter the complete message as plain text. The default “recently bought” displays as “Someone recently bought”. Leaving the field empty displays “Purchased in this store”.', 'recently-bought-this-for-woocommerce' ); ?></p>
				</td>
			</tr>
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
