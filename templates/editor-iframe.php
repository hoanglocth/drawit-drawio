<?php

/**
 * Editor iframe template
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

$post_id = isset($_REQUEST['post_id']) ? intval($_REQUEST['post_id']) : 0;
$form_action_url = admin_url("admin-post.php?post_id=$post_id");
$form_action_url = apply_filters('media_upload_form_url', $form_action_url);
$form_class = 'media-upload-form type-form validate';
$edit_xml = '';
$edit_imgtype = '';
$edit_imgdata = '';

// Title of diagram
$diag_title = DRAWIT_PLUGIN_SLUG . ' diagram';
if (isset($_REQUEST['title'])) {
    $diag_title = sanitize_text_field($_REQUEST['title']);  // FIXED: Added sanitization
}

// Valid image types
$valid_types = DrawIt_Config::get_filtered_valid_types();

// File type
$save_type = $this->options['default_type'];

// If editing existing diagram, get its data
if (isset($_REQUEST['img_id']) && $_REQUEST['img_id'] != "" && ctype_digit($_REQUEST['img_id'])) {
    $img_id = (int) $_REQUEST['img_id'];
    $metadata = wp_get_attachment_metadata($img_id);

    if ($metadata !== false) {
        $image_meta = $metadata['image_meta'];
        $save_type = strtolower(end(explode('.', wp_get_attachment_url($img_id))));

        if ($image_meta['title'] != "") {
            $diag_title = $image_meta['title'];
        }

        if (
            array_key_exists('is_' . DRAWIT_PLUGIN_SLUG, $image_meta) &&
            array_key_exists(DRAWIT_PLUGIN_SLUG . '_xml', $image_meta)
        ) {

            $orig_xml = simplexml_load_string($image_meta[DRAWIT_PLUGIN_SLUG . '_xml']);

            if ($orig_xml !== false) {
                // Override these attributes for editing
                $xml_attr = array(
                    'grid' => '1',
                    'page' => '1',
                );

                foreach ($xml_attr as $key => $val) {
                    if (isset($orig_xml[$key])) {
                        $orig_xml[$key] = $val;
                    } else {
                        $orig_xml->addAttribute($key, $val);
                    }
                }

                $edit_xml = $orig_xml->asXML();
            }
        }
    }
}

if (get_user_setting('uploader')) {
    $form_class .= ' html-uploader';
}
?>

<?php if (function_exists('wp_nonce_field')) wp_nonce_field('media-form_' . DRAWIT_PLUGIN_SLUG, DRAWIT_PLUGIN_SLUG . '-nonce'); ?>
<form class="<?php echo DRAWIT_PLUGIN_SLUG; ?>-media-form" id="<?php echo DRAWIT_PLUGIN_SLUG; ?>-form" method="post" action="">
    <input type="hidden" name="<?php echo DRAWIT_PLUGIN_SLUG; ?>-action" value="submit-form-<?php echo DRAWIT_PLUGIN_SLUG; ?>">
</form>
<input type="hidden" name="<?php echo DRAWIT_PLUGIN_SLUG; ?>-post-id" id="<?php echo DRAWIT_PLUGIN_SLUG; ?>-post-id" value="<?php echo (int) $post_id; ?>">
<input type="hidden" id="<?php echo DRAWIT_PLUGIN_SLUG; ?>-xml" value="<?php echo htmlspecialchars($edit_xml); ?>">
<input type="hidden" id="<?php echo DRAWIT_PLUGIN_SLUG; ?>-imgtype" value="<?php echo htmlspecialchars($edit_imgtype); ?>">
<input type="hidden" id="<?php echo DRAWIT_PLUGIN_SLUG; ?>-imgdata" value="<?php echo htmlspecialchars($edit_imgdata); ?>">
<?php if (isset($img_id)): ?>
<input type="hidden" id="<?php echo DRAWIT_PLUGIN_SLUG; ?>-img-id" value="<?php echo (int) $img_id; ?>">
<?php endif; ?>

<div class="<?php echo DRAWIT_PLUGIN_SLUG; ?>-form-title-block">
    <label class="<?php echo DRAWIT_PLUGIN_SLUG; ?>-form-label" for="<?php echo DRAWIT_PLUGIN_SLUG; ?>-title">
        Title:
    </label>
    <input type="text" class="<?php echo DRAWIT_PLUGIN_SLUG; ?>-form-text-input"
        id="<?php echo DRAWIT_PLUGIN_SLUG; ?>-title"
        name="<?php echo DRAWIT_PLUGIN_SLUG; ?>-title"
        value="<?php echo htmlspecialchars($diag_title); ?>">

    Filetype: <select id="<?php echo DRAWIT_PLUGIN_SLUG; ?>-type"
        class="<?php echo DRAWIT_PLUGIN_SLUG; ?>-type"
        name="type">
        <?php
        foreach ($valid_types as $tp) {
            $select_type = (strtolower($tp) == strtolower($save_type)) ? " selected" : "";
            echo '<option value="' . strtolower($tp) . '"' . $select_type . '>' . strtoupper($tp) . '</option>';
        }
        ?>
    </select>
    
    <div style="margin-top: 10px;">
        <label class="<?php echo DRAWIT_PLUGIN_SLUG; ?>-form-label" for="<?php echo DRAWIT_PLUGIN_SLUG; ?>-as-shortcode">
            <?php
            // Check if we're editing from a shortcode
            $from_shortcode = isset($_REQUEST['from_shortcode']) && $_REQUEST['from_shortcode'] === 'true';
            ?>
            <input type="checkbox" id="<?php echo DRAWIT_PLUGIN_SLUG; ?>-as-shortcode" name="<?php echo DRAWIT_PLUGIN_SLUG; ?>-as-shortcode" <?php checked($from_shortcode, true); ?>>
            Save as shortcode
        </label>
        <span class="<?php echo DRAWIT_PLUGIN_SLUG; ?>-help-tip" title="When checked, a shortcode will be inserted instead of the image">?</span>
    </div>
    
    <div id="<?php echo DRAWIT_PLUGIN_SLUG; ?>-draft-info" class="<?php echo DRAWIT_PLUGIN_SLUG; ?>-draft-info" style="display:none; margin-top:5px; font-size:12px; color:#666;">
        Changes are automatically saved as drafts
    </div>
</div>

<iframe class="<?php echo DRAWIT_PLUGIN_SLUG; ?>-editor-iframe"
    id="<?php echo DRAWIT_PLUGIN_SLUG; ?>-iframe"
    style="width:100%; height:700px; border:none;">
</iframe>

<div class="<?php echo DRAWIT_PLUGIN_SLUG; ?>-editor-mask"
    id="<?php echo DRAWIT_PLUGIN_SLUG; ?>-editor-mask"
    style="display:none;">
    <div class="<?php echo DRAWIT_PLUGIN_SLUG; ?>-editor-saving">
        Saving...
        <div class="<?php echo DRAWIT_PLUGIN_SLUG; ?>-editor-saving-x"
            onclick="jQuery('.<?php echo DRAWIT_PLUGIN_SLUG; ?>-editor-mask').css('display','none');">
            x
        </div>
    </div>
</div>