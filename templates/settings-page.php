<?php

/**
 * Settings page template
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}
?>
<div>
    <h2><?php echo DRAWIT_PLUGIN_LABEL . ' ' . DRAWIT_VERSION; ?> (diagrams.net) Settings</h2>
    <hr>
    <form action="options.php" method="post">
        <?php settings_fields(DRAWIT_PLUGIN_SLUG . '_options'); ?>
        <?php do_settings_sections(DRAWIT_PLUGIN_SLUG); ?>

        <input name="Submit" type="submit" id="submit" class="button button-primary" value="<?php esc_attr_e('Save Changes'); ?>" />
    </form>
</div>
<br>