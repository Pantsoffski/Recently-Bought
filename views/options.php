<div class="wrap">
    <h2><?php _e('Recently Bought This for WooCommerce - Settings', 'recently-bought'); ?></h2>

    <form method="post" action="options.php">
        <?php settings_fields('recently_bought_settings_group'); ?>
        <?php do_settings_sections('recently_bought_settings_group'); ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php _e('Text to show:', 'recently-bought'); ?></th>
                <td>
                    <input type="text" name="recently_bought_settings_text" value="<?php echo esc_attr(get_option('recently_bought_settings_text')); ?>" />
                </td>
            </tr>

            <tr valign="top">
                <th scope="row"><?php _e('Number of orders to show (2-10):', 'recently-bought'); ?></th>
                <td>
                    <input type="number" name="recently_bought_settings_pnumber" value="<?php echo esc_attr(get_option('recently_bought_settings_pnumber')); ?>" min="2" max="10" />
                </td>
            </tr>

            <tr valign="top">
                <th scope="row"><?php _e('Randomize orders:', 'recently-bought'); ?></th>
                <td>
                    <input type="checkbox" name="recently_bought_settings_randomize" value="1" <?php checked(get_option('recently_bought_settings_randomize')); ?>/>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>

    </form>
</div>