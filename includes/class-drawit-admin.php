<?php

/**
 * Admin functionality for DrawIt plugin
 */
class DrawIt_Admin {
    /**
     * Plugin options
     *
     * @var array
     */
    protected $options;
    
    /**
     * Valid image types
     *
     * @var array
     */
    protected $valid_types;
    
    /**
     * Valid CSS units
     *
     * @var array
     */
    protected $valid_units;
    
    /**
     * Valid temp directories
     *
     * @var array
     */
    protected $valid_temp_dirs;
    
    /**
     * Initialize the admin functionality
     */
    public function __construct() {
        $this->options = DrawIt_Config::get_options();
        $this->valid_types = DrawIt_Config::get_filtered_valid_types();
        $this->valid_units = DrawIt_Config::get_valid_units();
        $this->valid_temp_dirs = DrawIt_Config::get_valid_temp_dirs();
    }
    
    /**
     * Initialize admin hooks
     */
    public function init() {
        // Admin settings
        add_action('admin_menu', array($this, 'admin_add_page'));
        add_action('admin_init', array($this, 'admin_init'));
        add_filter('plugin_action_links', array($this, 'settings_link'), 10, 2);
        
        // Editor buttons
        add_action('admin_init', array($this, 'editor_buttons_init'));
        add_action('admin_print_scripts', array($this, 'quicktags_add_button'));
    }
    
    /**
     * Settings link on plugins page
     *
     * @param array $links Plugin links
     * @param string $file Plugin file
     * @return array Modified links
     */
    public function settings_link($links, $file) {
        $plugin_basename = plugin_basename(DRAWIT_PLUGIN_DIR . 'drawit.php');
        if ($file == $plugin_basename) {
            $settings_link = '<a href="options-general.php?page=' . DRAWIT_PLUGIN_SLUG . '">' 
                . __('Settings', DRAWIT_PLUGIN_SLUG) . '</a>';
            array_unshift($links, $settings_link);
        }
        return $links;
    }
    
    /**
     * Register editor buttons if user has permission
     */
    public function editor_buttons_init() {
        if (current_user_can('edit_posts') || current_user_can('edit_pages')) {
            // Register utils script first
            wp_register_script(
                DRAWIT_PLUGIN_SLUG . '-utils',
                DRAWIT_PLUGIN_URL . 'js/drawit-utils.js',
                array('jquery'),
                DRAWIT_VERSION,
                true
            );

            // Register MCE button with utils dependency
            wp_register_script(
                DRAWIT_PLUGIN_SLUG . '-mce-button',
                DRAWIT_PLUGIN_URL . 'js/mce-btn.js',
                array('jquery', DRAWIT_PLUGIN_SLUG . '-utils'),
                DRAWIT_VERSION,
                true
            );

            // Enqueue utils for editor
            wp_enqueue_script(DRAWIT_PLUGIN_SLUG . '-utils');

            add_filter('mce_external_plugins', array($this, 'add_mce_plugin'));
            add_filter('mce_buttons', array($this, 'register_mce_button'));
        }
    }
    
    /**
     * Add settings page to admin menu
     */
    public function admin_add_page() {
        add_options_page(
            DRAWIT_PLUGIN_LABEL . ' (diagrams.net) Settings', 
            DRAWIT_PLUGIN_LABEL . ' (diagrams.net)', 
            'manage_options', 
            DRAWIT_PLUGIN_SLUG, 
            array($this, 'options_page')
        );
    }
    
    /**
     * Register admin settings
     */
    public function admin_init() {
        register_setting(
            DRAWIT_PLUGIN_SLUG . '_options', 
            DRAWIT_PLUGIN_SLUG . '_options', 
            array($this, 'options_validate')
        );
        
        // Image type settings
        add_settings_section(
            DRAWIT_PLUGIN_SLUG . '_img_type', 
            'Diagram Save-as Image Type', 
            array($this, 'img_type_settings_section_text'), 
            DRAWIT_PLUGIN_SLUG
        );
        add_settings_field(
            DRAWIT_PLUGIN_SLUG . '_allow_svg', 
            'Allow uploading SVG', 
            array($this, 'setting_allow_svg'), 
            DRAWIT_PLUGIN_SLUG, 
            DRAWIT_PLUGIN_SLUG . '_img_type'
        );
        add_settings_field(
            DRAWIT_PLUGIN_SLUG . '_default_type', 
            'Default image type', 
            array($this, 'setting_default_type'), 
            DRAWIT_PLUGIN_SLUG, 
            DRAWIT_PLUGIN_SLUG . '_img_type'
        );
        
        // Advanced options
        add_settings_section(
            DRAWIT_PLUGIN_SLUG . '_advanced', 
            'Advanced Options', 
            array($this, 'advanced_settings_section_text'), 
            DRAWIT_PLUGIN_SLUG
        );
        add_settings_field(
            DRAWIT_PLUGIN_SLUG . '_temp_dir', 
            'Default temporary directory', 
            array($this, 'setting_temp_dir'), 
            DRAWIT_PLUGIN_SLUG, 
            DRAWIT_PLUGIN_SLUG . '_advanced'
        );
    }
    
    /**
     * Validate options before saving
     * 
     * @param array $input Input options
     * @return array Validated options
     */
    public function options_validate($input) {
        $old_options = $this->options;
        $opt = $old_options;
        
        // Copy over values
        $opt['default_type'] = $input['default_type'];
        $opt['allow_svg'] = $input['allow_svg'];
        $opt['temp_dir'] = $input['temp_dir'];
        
        // Validate default type
        if (!in_array($opt['default_type'], DrawIt_Config::get_valid_types())) {
            $opt['default_type'] = DrawIt_Config::get_default_options()['default_type'];
        }
        
        // Validate allow SVG
        if (strtolower($opt['allow_svg']) != 'yes' && strtolower($opt['allow_svg']) != 'no') {
            $opt['allow_svg'] = DrawIt_Config::get_default_options()['allow_svg'];
        }
        
        // Validate temp directory
        if (!in_array($opt['temp_dir'], $this->valid_temp_dirs)) {
            $opt['temp_dir'] = DrawIt_Config::get_default_options()['temp_dir'];
        }
        
        return $opt;
    }
    
    /**
     * Image type settings section description
     */
    public function img_type_settings_section_text() {
        include DRAWIT_PLUGIN_DIR . 'templates/settings-section-image-type.php';
    }
    
    /**
     * Advanced settings section description
     */
    public function advanced_settings_section_text() {
        echo '<p>These are various settings that generally you would not need to change as a typical user, unless you run into a specific problem or have a very customized server configuration.</p>';
    }
    
    /**
     * Allow SVG setting field
     */
    public function setting_allow_svg() {
        $yes_checked = (strtolower($this->options['allow_svg']) == 'yes') ? ' checked' : '';
        $no_checked = (strtolower($this->options['allow_svg']) != 'yes') ? ' checked' : '';
        
        echo "<input type='radio' id='allow_svg' name='" . DRAWIT_PLUGIN_SLUG . "_options[allow_svg]' value='yes'" . $yes_checked . ">";
        echo "<label for='allow_svg'> Yes</label><br>";
        echo "<input type='radio' id='disallow_svg' name='" . DRAWIT_PLUGIN_SLUG . "_options[allow_svg]' value='no'" . $no_checked . ">";
        echo "<label for='disallow_svg'> No</label>";
    }
    
    /**
     * Default type setting field
     */
    public function setting_default_type() {
        echo "<select id='" . DRAWIT_PLUGIN_SLUG . "_default_type' name='" . DRAWIT_PLUGIN_SLUG . "_options[default_type]'>";
        foreach ($this->valid_types as $type) {
            $selected = (strtolower($this->options['default_type']) == strtolower($type)) ? " selected" : "";
            echo "<option value='" . strtolower($type) . "'" . $selected . ">" . strtoupper($type) . "</option>";
        }
        echo "</select>";
    }
    
    /**
     * Temp directory setting field
     */
    public function setting_temp_dir() {
        $tempdir_base = wp_upload_dir();
        $content_checked = (strtolower($this->options['temp_dir']) == 'wp_content') ? " checked" : "";
        $default_checked = (strtolower($this->options['temp_dir']) != 'wp_content') ? " checked" : "";
        
        echo "<input type='radio' id='tmp_wpdefault' name='" . DRAWIT_PLUGIN_SLUG . "_options[temp_dir]' value='wp_default'" . $default_checked . ">";
        echo "<label for='tmp_wpdefault'> Default system temp location, via get_temp_dir():</label><br><code>" . get_temp_dir() . "</code><br>";
        echo "<input type='radio' id='tmp_wpcontent' name='" . DRAWIT_PLUGIN_SLUG . "_options[temp_dir]' value='wp_content'" . $content_checked . ">";
        echo "<label for='tmp_wpcontent'> In wp-content/uploads:</label><br><code>" . $tempdir_base['basedir'] . "/" . DRAWIT_PLUGIN_SLUG . "_temp</code><br>";
        echo '<p>This selects where to save the temporary files while saving a diagram. This is not the final location, only where it temporarily saves them during processing. Sometimes this setting needs to change if WordPress\'s built-in get_temp_dir() function does not return a valid temp directory location on your system.</p>';
    }
    
    /**
     * Options page HTML
     */
    public function options_page() {
        include DRAWIT_PLUGIN_DIR . 'templates/settings-page.php';
    }
    
    /**
     * Add TinyMCE plugin
     * 
     * @param array $plugin_array Plugin array
     * @return array Modified plugin array
     */
    public function add_mce_plugin($plugin_array) {
        $plugin_array[DRAWIT_PLUGIN_SLUG . '_mce_button'] = DRAWIT_PLUGIN_URL . 'js/mce-btn.js';
        return $plugin_array;
    }
    
    /**
     * Register TinyMCE button
     * 
     * @param array $buttons Buttons array
     * @return array Modified buttons array
     */
    public function register_mce_button($buttons) {
        array_push($buttons, DRAWIT_PLUGIN_SLUG . '_mce_button');
        return $buttons;
    }
    
    /**
     * Add QuickTags button
     */
    public function quicktags_add_button() {
        // QuickTags button depends on utils
        wp_enqueue_script(
            'quicktags_' . DRAWIT_PLUGIN_SLUG, 
            DRAWIT_PLUGIN_URL . 'js/qt-btn.js', 
            array('quicktags', DRAWIT_PLUGIN_SLUG . '-utils'), 
            DRAWIT_VERSION,
            true
        );
    }
}
