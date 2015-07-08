<?php
/**
 * @package DrawIt
 * @version 1.0.3
 */
/*
Plugin Name:    DrawIt
Plugin URI:     http://www.assortedchips.com/#drawit
Description:    Draw and edit flow charts, diagrams and images while editing a post. This plugin interfaces with the <a href="https://www.draw.io/">draw.io website</a> (not affiliated with this plugin).
Version:        1.0.3
Author:         assorted[chips]
Author URI:     http://www.assortedchips.com/
License:        GPL3 or later
License URI:    https://www.gnu.org/licenses/gpl-3.0.html


    Copyright 2015  Mike Thomson  (email : contact@mike-thomson.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 3, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

*/

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

$plugin_slug = "drawit";
$plugin_label = 'DrawIt';
$plugin_default_options = array(
    //'default_width'     => '6.5in',
    //'default_height'    => '5in',
    //'max_width'         => '100%',
    //'max_height'        => '9in',
    'default_type'      => 'png',
    'allow_svg'         => 'no'
);
$valid_types = array(
    'png',
    'svg'
);
$valid_units = array(
    'em',
    'ex',
    '%',
    'px',
    'cm',
    'mm',
    'in',
    'pt',
    'pc',
    'ch',
    'rem',
    'vh',
    'vw',
    'vmin',
    'vmax'
);

class drawit {

    public function __construct($plugin_slug, $plugin_label, $plugin_default_options, $valid_types, $valid_units) {
        $this->plugin_slug = $plugin_slug;
        $this->plugin_label = $plugin_label;
        $this->plugin_default_options = $plugin_default_options;
        $this->valid_units = $valid_units;

        // Options saved to database are used throughout the functions here, so 
        // make a copy now so they are easily accessible later.
        $this->options = get_option($this->plugin_slug . '_options', $this->plugin_default_options);

        // If the user has selected to not allow SVG uploads, then remove that 
        // from the "valid types".
        $tmp_types = array();
        foreach($valid_types as $type) {
            if(strtolower($type) != 'svg' || strtolower($this->options['allow_svg']) == 'yes') {
                array_push($tmp_types, strtolower($type));
            }
        }
        $this->valid_types = $tmp_types;

        add_action('admin_menu', array($this, 'admin_add_page'));
        add_action('admin_init', array($this, 'admin_init'));
        add_filter('plugin_action_links', array($this, 'settings_link'), 10, 2);
        add_filter('media_upload_tabs', array($this, 'add_tab'));
        add_filter('upload_mimes', array($this, 'add_mime_type'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('media_upload_' . $this->plugin_slug, array($this, 'media_menu_handler'));
        add_action('wp_ajax_submit-form-' . $this->plugin_slug, array($this, 'sideload_handler'));
        add_action('admin_print_scripts', array($this, 'quicktags_add_button'));
    }

    // "Settings" link on plugin list page.
    public function settings_link($links, $file) {
        $this_plugin_basename = plugin_basename(__FILE__);
        if($file == $this_plugin_basename) {
            $settings_link = '<a href="options-general.php?page=' . $this->plugin_slug . '">' . __('Settings', $this->plugin_slug) . '</a>';
            array_unshift($links, $settings_link);
        }
        return $links;
    }

    // Enqueue the javascript.
    public function enqueue_scripts() {
        // Admin user, so don't use minified CSS.
        if(current_user_can('manage_options')) {
            wp_enqueue_style($this->plugin_slug . '-css', plugins_url('css/' . $this->plugin_slug . '.css', __FILE__));
        } else {
            wp_enqueue_style($this->plugin_slug . '-css', plugins_url('css/' . $this->plugin_slug . '.min.css', __FILE__));
        }
        wp_enqueue_script($this->plugin_slug . '-iframe-js', plugins_url('js/' . $this->plugin_slug . '-iframe.js', __FILE__), array(), false, true);
        //wp_enqueue_script($this->plugin_slug . '-js-embed', 'https://www.draw.io/embed.js?s=basic', array(), false, true);
    }

    // Add draw.io tab to "Insert Media" page when editing a post or page.
    public function add_tab($tabs) {
        $tabs[$this->plugin_slug] = 'Draw with draw.io';
        return $tabs;
    }

    // Add MIME type for svg.
    public function add_mime_type($mimes) {
        if(strtolower($this->options['allow_svg']) == 'yes') {
            $mimes['svg'] = 'image/svg+xml';
        }
        return $mimes;
    }

    // This calls the iframe-maker and enqueues associated javascript for generating
    // iframe that will hold editor.
    public function media_menu_handler() {
        $errors = '';
        wp_enqueue_script($this->plugin_slug . '-js', plugins_url('js/' . $this->plugin_slug . '.js', __FILE__));
        if(current_user_can('manage_options')) {
            wp_enqueue_style($this->plugin_slug . '-css', plugins_url('css/' . $this->plugin_slug . '.css', __FILE__));
        } else {
            wp_enqueue_style($this->plugin_slug . '-css', plugins_url('css/' . $this->plugin_slug . '.min.css', __FILE__));
        }
        return wp_iframe(array($this, 'iframe'), $errors);
    }

    // After user presses "save", this function gets called via admin-ajax.php.
    public function sideload_handler() {
        $resp = array('success' => false, 'html' => '');
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string(stripslashes($_POST['xml']));
        libxml_use_internal_errors(false);
        $specified_title = '';

        // Make sure nonce matches.
        if(!isset($_POST['nonce']) || !check_ajax_referer('media-form_' . $this->plugin_slug, 'nonce')) {
            $resp['html'] = 'Sorry, your nonce did not verify.';

        // Check other submitted values.
        } elseif(!isset($_POST['img_type']) || $_POST['img_type'] == "") {
            $resp['html'] = 'Sorry, no image type was specified.';

        } elseif(strtolower($_POST['img_type']) == 'svg' && $this->options['allow_svg'] != 'yes') {
            $resp['html'] = 'Sorry, uploading SVG images has been disabled.';

        } elseif(!isset($_POST['img_data']) || $_POST['img_data'] == "") {
            $resp['html'] = 'Sorry, no image data was provided.';

        // Make sure we received nonempty content that is valid XML.
        } elseif(!isset($_POST['xml']) || $_POST['xml'] == "" || !$xml) {
            $resp['html'] = 'Sorry, invalid XML was received.';

        // Make sure this is associated with a post ID.
        } elseif(!isset($_POST['post_id']) || $_POST['post_id'] == "" || !ctype_digit($_POST['post_id'])) {
            $resp['html'] = 'Sorry, post ID was not an integer.';

        // All is well.
        } else {
            $post_id = (int) $_POST['post_id'];
            $img_type = $_POST['img_type'];
            $img_b64 = explode(',', $_POST['img_data']);
            if(strpos($img_b64[0], 'image/svg') !== false) {
                unset($img_b64[0]);
                $img_data = stripslashes(implode($img_b64));
            } else {
                $img_data = base64_decode($img_b64[1]);
            }

            if(!isset($_POST['title']) || sanitize_file_name($_POST['title']) == "") {
                $title = $this->plugin_slug . '_diagram';
            } else {
                $title = $_POST['title'];
            }
            $file_title = sanitize_file_name($title . '.' . $img_type);

            // We want to set or override these attributes of the XML entity.
            $xml_attr = array(
                'grid'      => '0',
                'page'      => '0',
                'pageScale' => '1',
                'pan'       => '1',
                'zoom'      => '1',
                'resize'    => '1',
                'fit'       => '1',
                'nav'       => '0',
                'border'    => '0',
                'links'     => '1'
            );
            foreach($xml_attr as $key => $val) {
                // If attribute exists, then change it.
                if(isset($xml[$key])) {
                    $xml[$key] = $val;

                // If attribute doesn't exist, create it.
                } else {
                    $xml->addAttribute($key, $val);
                }
            }

            // Write the XML to a temp file.
            $ftmp = tmpfile();
            $ftmp_size = fwrite($ftmp, $img_data);
            fflush($ftmp);
            $ftmp_meta = stream_get_meta_data($ftmp);
            $file_array = array(
                'name' => $file_title,
                'tmp_name' => $ftmp_meta['uri'],
                'error' => '',
                'type' => $img_type,
                'size' => $ftmp_size
            );

            // Check if any file renaming is needed (e.g., to avoid overwriting existing file).
            $file_array = apply_filters('wp_handle_upload_prefilter', $file_array);

            // Add file to uploads directory, add to media library and attach to post.
            $attach_id = media_handle_sideload($file_array, $post_id);

            // Get attachment URL and return the HTML to the post editor.
            if($attach_id) {
                if(!is_wp_error($attach_id)) {
                    // Update attachment metadata with plugin info.
                    $metadata = wp_get_attachment_metadata($attach_id);
                    $image_meta = $metadata['image_meta'];
                    $image_meta['is_' . $this->plugin_slug] = true;
                    $image_meta[$this->plugin_slug . '_xml'] = $xml->asXML();
                    $image_meta['title'] = $title;
                    $metadata['image_meta'] = $image_meta;
                    wp_update_attachment_metadata($attach_id, $metadata);

                    $file_url = wp_get_attachment_url($attach_id);
                    $resp['success'] = true;
                    $resp['att_id'] = $attach_id;
                    $resp['misc'] = $movefile['url'];

                    $img_html = wp_get_attachment_image($attach_id, 'full', false, array(
                        'class' => 'aligncenter wp-image-' . $attach_id,
                        'title' => htmlentities($title)
                    ));
                    if($img_html != '') {
                        $resp['html'] = $img_html;
                        
                    } else {
                        $resp['html'] = '<img class="' . $this->plugin_slug . '-img wp-image-' . $attach_id . '" src="' . $file_url . '" title="' . htmlentities($title) . '">';
                    }

                } else {
                    if($ftmp_size !== false) {
                    $resp['html'] = 'Sorry, could not insert attachment. true';
                    } else {
                    $resp['html'] = 'Sorry, could not insert attachment. false';
                    }
                }

            } else {
                $resp['html'] = 'Sorry, file attachment failed.';
            }

            fclose($ftmp);
        }

        echo json_encode($resp);
        exit;
    }

    // This is the actual iframe content for the editor.
    public function iframe() {
        $post_id = isset($_REQUEST['post_id']) ? intval( $_REQUEST['post_id'] ) : 0;
        $form_action_url = admin_url("admin-post.php?post_id=$post_id");
        $form_action_url = apply_filters('media_upload_form_url', $form_action_url);
        $form_class = 'media-upload-form type-form validate';
        $edit_xml = '';
        $edit_imgtype = '';
        $edit_imgdata = '';

        // Title of diagram.
        $diag_title = $this->plugin_slug . ' diagram';
        if(isset($_REQUEST['title'])) {
            $diag_title = $_REQUEST['title'];
        }

        // File type
        $save_type = $this->options['default_type'];

        if(isset($_REQUEST['img_id']) && $_REQUEST['img_id'] != "" && ctype_digit($_REQUEST['img_id'])) {
            $img_id = (int) $_REQUEST['img_id'];
            $metadata = wp_get_attachment_metadata($img_id);
            if($metadata !== false) {
                $image_meta = $metadata['image_meta'];
                $save_type = strtolower(end(explode('.', wp_get_attachment_url($img_id))));

                if($image_meta['title'] != "") {
                    $diag_title = $image_meta['title'];
                }

                if(array_key_exists('is_' . $this->plugin_slug, $image_meta) && array_key_exists($this->plugin_slug . '_xml', $image_meta)) {
                    $orig_xml = simplexml_load_string($image_meta[$this->plugin_slug . '_xml']);
                    if($orig_xml !== false) {
                        // Override these attributes of the XML entity for ease of editing existing diagram.
                        $xml_attr = array(
                            'grid'      => '1',
                            'page'      => '1',
                        );
                        foreach($xml_attr as $key => $val) {
                            // If attribute exists, then change it.
                            if(isset($orig_xml[$key])) {
                                $orig_xml[$key] = $val;

                            // If attribute doesn't exist, create it.
                            } else {
                                $orig_xml->addAttribute($key, $val);
                            }
                        }

                        $edit_xml = $orig_xml->asXML();
                    }
                }
            }
        }

        if ( get_user_setting('uploader') )
            $form_class .= ' html-uploader';
    ?>

        <?php if(function_exists('wp_nonce_field')) wp_nonce_field('media-form_' . $this->plugin_slug, $this->plugin_slug . '-nonce'); ?>
        <form class="<?php echo $this->plugin_slug; ?>-media-form" id="<?php echo $this->plugin_slug; ?>-form" method="post" action="">
            <input type="hidden" name="<?php echo $this->plugin_slug; ?>-action" value="submit-form-<?php echo $this->plugin_slug; ?>">
        </form>
        <input type="hidden" name="<?php echo $this->plugin_slug; ?>-post-id" id="<?php echo $this->plugin_slug; ?>-post-id" value="<?php echo (int) $post_id; ?>">
        <input type="hidden" id="<?php echo $this->plugin_slug; ?>-xml" value="<?php echo htmlspecialchars($edit_xml); ?>">
        <input type="hidden" id="<?php echo $this->plugin_slug; ?>-imgtype" value="<?php echo htmlspecialchars($edit_imgtype); ?>">
        <input type="hidden" id="<?php echo $this->plugin_slug; ?>-imgdata" value="<?php echo htmlspecialchars($edit_imgdata); ?>">
        <div class="<?php echo $this->plugin_slug; ?>-form-title-block"><label class="<?php echo $this->plugin_slug; ?>-form-label" for="<?php echo $this->plugin_slug; ?>-title">Title: </label><input type="text" class="<?php echo $this->plugin_slug; ?>-form-text-input" id="<?php echo $this->plugin_slug; ?>-title" name="<?php echo $this->plugin_slug; ?>-title" value="<?php echo htmlspecialchars($diag_title); ?>"> Filetype: <select id="<?php echo $this->plugin_slug; ?>-type" class="<?php echo $this->plugin_slug; ?>-type" name="type"><?php
            foreach($this->valid_types as &$tp) {
                if(strtolower($tp) == strtolower($save_type)) {
                    $select_type = " selected";
                } else {
                    $select_type = "";
                }
                echo '<option value="' . strtolower($tp) . '"' . $select_type . '>' . strtoupper($tp) . '</option>';
            }
            unset($tp);
            ?></select></div>
        <iframe class="<?php echo $this->plugin_slug; ?>-editor-iframe" id="<?php echo $this->plugin_slug; ?>-iframe" src="https://www.draw.io/?embed=1&analytics=0&gapi=0&db=0&od=0&proto=json&spin=1"></iframe>
        <div class="<?php echo $this->plugin_slug; ?>-editor-mask" id="<?php echo $this->plugin_slug; ?>-editor-mask" style="display:none;"><div class="<?php echo $this->plugin_slug; ?>-editor-saving">Saving...<div class="<?php echo $this->plugin_slug; ?>-editor-saving-x" onclick="jQuery('.<?php echo $this->plugin_slug; ?>-editor-mask').css('display','none');">x</div></div></div>

    <?php
    }

    // Plugin options page
    public function admin_init(){
        if (current_user_can('edit_posts') || current_user_can('edit_pages')) {
            add_filter('mce_external_plugins', array($this, 'add_mce_plugin'));
            add_filter('mce_buttons', array($this, 'register_mce_button'));
        }

        register_setting( $this->plugin_slug . '_options', $this->plugin_slug . '_options', array($this, 'options_validate') );
        add_settings_section($this->plugin_slug . '_img_type', 'Diagram Save-as Image Type', array($this, 'img_type_settings_section_text'), $this->plugin_slug);
        add_settings_field($this->plugin_slug . '_allow_svg', 'Allow uploading SVG', array($this, 'setting_allow_svg'), $this->plugin_slug, $this->plugin_slug . '_img_type');
        add_settings_field($this->plugin_slug . '_default_type', 'Default image type', array($this, 'setting_default_type'), $this->plugin_slug, $this->plugin_slug . '_img_type');
        /*
        add_settings_section($this->plugin_slug . '_diagram_size', 'Diagram Size Settings', array($this, 'diagram_settings_section_text'), $this->plugin_slug);
        add_settings_field($this->plugin_slug . '_default_width', 'Default diagram iframe width', array($this, 'setting_default_width'), $this->plugin_slug, $this->plugin_slug . '_iframe_size');
        add_settings_field($this->plugin_slug . '_default_height', 'Default diagram iframe height', array($this, 'setting_default_height'), $this->plugin_slug, $this->plugin_slug . '_iframe_size');
        add_settings_field($this->plugin_slug . '_max_width', 'Max diagram width', array($this, 'setting_max_width'), $this->plugin_slug, $this->plugin_slug . '_diagram_size');
        add_settings_field($this->plugin_slug . '_max_height', 'Max diagram height', array($this, 'setting_max_height'), $this->plugin_slug, $this->plugin_slug . '_diagram_size');
         */
    }

    public function admin_add_page() {
        add_options_page($this->plugin_label . ' Options', $this->plugin_label, 'manage_options', $this->plugin_slug, array($this, 'options_page'));
    }

    public function img_type_settings_section_text() {
        echo '<p>These settings specify if you would like to allow uploading of images in SVG format and the default image type to save as (either PNG or SVG). Note that whatever you choose for the default selection can be overridden per-diagram when saving a diagram.</p>';
    }

    /*
    public function diagram_settings_section_text() {
        echo '<p>These settings specify the default size of the diagram/drawing in the post/page that you have created. Sizes must follow typical CSS syntax: a number followed by a unit of measurement (e.g., "100%", "400px", "6in", "35em", etc.). The diagram size will be the lesser of the "default" and "max" values for each dimension. The maximum numeric value that can be entered for any of these is 9999.</p><p><strong>NOTE:</strong> These values are only applied to newly created diagrams. A diagram\'s size can be maually adjusted when creating the diagram.</p>';
    }
     */

    // Displaying settings fields.
    public function setting_allow_svg() {
        if(strtolower($this->options['allow_svg']) == 'yes') {
            $yes_checked = " checked";
            $no_checked = "";
        } else {
            $no_checked = " checked";
            $yes_checked = "";
        }
        echo "<input type='radio' id='allow_svg' name='" . $this->plugin_slug . "_options[allow_svg]' value='yes'" . $yes_checked . "><label for='allow_svg'> Yes</label><br>";
        echo "<input type='radio' id='disallow_svg' name='" . $this->plugin_slug . "_options[allow_svg]' value='no'" . $no_checked . "><label for='disallow_svg'> No</label>";
    }

    public function setting_default_type() {
        echo "<select id='" . $this->plugin_slug . "_default_type' name='" . $this->plugin_slug . "_options[default_type]'>";
        foreach($this->valid_types as &$tp) {
            if(strtolower($this->options['default_type']) == strtolower($tp)) {
                $selected = " selected";
            } else {
                $selected = "";
            }
            echo "<option value='" . strtolower($tp) . "'" . $selected . ">" . strtoupper($tp) . "</option>";
        }
        unset($tp);
        echo "</select>";
    }

    /*
    public function setting_default_width() {
        echo "<input id='" . $this->plugin_slug . "_default_width' name='" . $this->plugin_slug . "_options[default_width]' size='10' type='text' value='{$this->options['default_width']}' />";
    }

    public function setting_default_height() {
        echo "<input id='" . $this->plugin_slug . "_default_height' name='" . $this->plugin_slug . "_options[default_height]' size='10' type='text' value='{$this->options['default_height']}' />";
    }

    public function setting_max_width() {
        echo "<input id='" . $this->plugin_slug . "_max_width' name='" . $this->plugin_slug . "_options[max_width]' size='10' type='text' value='{$this->options['max_width']}' />";
    }

    public function setting_max_height() {
        echo "<input id='" . $this->plugin_slug . "_max_height' name='" . $this->plugin_slug . "_options[max_height]' size='10' type='text' value='{$this->options['max_height']}' />";
    }
     */

    // Validating settings fields.
    public function options_validate($input) {
        $old_options = get_option($this->plugin_slug . '_options', $this->plugin_default_options);
        $opt = $old_options;
        $units = implode('|', $this->valid_units);
        $unit_pregmatch_str = '/^([0-9]{0,4}\.)?[0-9]{1,4}(' . $units . ')$/i';

        // Copy over values
        $opt['default_type'] = $input['default_type'];
        $opt['allow_svg'] = $input['allow_svg'];

        // Remove characters that might be commonly added by mistake.
        /*
        $opt['default_width'] = strtolower(preg_replace("/[\s\"]+/", "", $input['default_width']));
        $opt['default_height'] = strtolower(preg_replace("/[\s\"]+/", "", $input['default_height']));
        $opt['max_width'] = strtolower(preg_replace("/[\s\"]+/", "", $input['max_width']));
        $opt['max_height'] = strtolower(preg_replace("/[\s\"]+/", "", $input['max_height']));
         */

        // Default values for each field.
        if(!in_array($opt['default_type'], $this->valid_types)) {
            $opt['default_type'] = $this->plugin_default_options['default_type'];
        }

        if(strtolower($opt['allow_svg']) != 'yes' && strtolower($opt['allow_svg']) != 'no') {
            $opt['default_type'] = $this->plugin_default_options['allow_svg'];
        }

        /*
        if(!preg_match($unit_pregmatch_str, $opt['default_width'])) {
            $opt['default_width'] = array_key_exists('default_width', $old_options) ? $old_options['default_width'] : $this->plugin_default_options['default_width'];
        }

        if(!preg_match($unit_pregmatch_str, $opt['default_height'])) {
            $opt['default_height'] = array_key_exists('default_height', $old_options) ? $old_options['default_height'] : $this->plugin_default_options['default_height'];
        }

        if(!preg_match($unit_pregmatch_str, $opt['max_width'])) {
            $opt['max_width'] = array_key_exists('max_width', $old_options) ? $old_options['max_width'] : $this->plugin_default_options['max_width'];
        }

        if(!preg_match($unit_pregmatch_str, $opt['max_height'])) {
            $opt['max_height'] = array_key_exists('max_height', $old_options) ? $old_options['max_height'] : $this->plugin_default_options['max_height'];
        }
         */

        return $opt;
    }

    public function options_page() {
    ?>
    <div>
    <h2><?php echo $this->plugin_label; ?> Options</h2>
    <form action="options.php" method="post">
    <?php settings_fields($this->plugin_slug . '_options'); ?>
    <?php do_settings_sections($this->plugin_slug); ?>
     
    <input name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" />
    </form></div>
     
    <?php
    }

    // TinyMCE editor buttons
    public function add_mce_plugin($plugin_array) {
        $plugin_array[$this->plugin_slug . '_mce_button'] = plugins_url('js/mce-btn.js', __FILE__);
        return $plugin_array;
    }

    public function register_mce_button($buttons) {
        array_push($buttons, $this->plugin_slug . '_mce_button');
        return $buttons;
    }

    public function quicktags_add_button() {
        wp_enqueue_script('quicktags_' . $this->plugin_slug, plugins_url('js/qt-btn.js', __FILE__), array('quicktags'));
    }

} // End class

$custom_plugin = new $plugin_slug($plugin_slug, $plugin_label, $plugin_default_options, $valid_types, $valid_units);

?>
