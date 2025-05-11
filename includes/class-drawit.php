<?php

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once DRAWIT_PLUGIN_DIR . 'includes/class-drawit-shortcode.php';

/**
 * Main DrawIt plugin class
 */
class DrawIt {
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
     * Initialize the plugin
     */
    public function __construct() {
        $this->options = DrawIt_Config::get_options();
        $this->valid_types = DrawIt_Config::get_filtered_valid_types();
    }
    
    /**
     * Run the plugin - set up hooks and filters
     */
    public function run() {
        // Initialize admin functionality
        $admin = new DrawIt_Admin();
        $admin->init();
        
        // Initialize media functionality
        $media = new DrawIt_Media();
        $media->init();
        
        // Initialize AJAX functionality
        $ajax = new DrawIt_Ajax();
        $ajax->init();

        // Initialize shortcode functionality
        $drawit_shortcode = new DrawIt_Shortcode();
        $drawit_shortcode->init();
        
        // Register frontend scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Add upload mime types
        add_filter('upload_mimes', array($this, 'add_mime_type'));
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        // Admin user, use non-minified CSS
        if (current_user_can('manage_options')) {
            wp_enqueue_style(
                DRAWIT_PLUGIN_SLUG . '-css', 
                DRAWIT_PLUGIN_URL . 'css/' . DRAWIT_PLUGIN_SLUG . '.css', 
                array(), 
                DRAWIT_VERSION
            );
        } else {
            wp_enqueue_style(
                DRAWIT_PLUGIN_SLUG . '-css', 
                DRAWIT_PLUGIN_URL . 'css/' . DRAWIT_PLUGIN_SLUG . '.min.css', 
                array(), 
                DRAWIT_VERSION
            );
        }
    }
    
    /**
     * Add SVG MIME type if enabled
     * 
     * @param array $mimes Current MIME types
     * @return array Modified MIME types
     */
    public function add_mime_type($mimes) {
        if (strtolower($this->options['allow_svg']) == 'yes') {
            $mimes['svg'] = 'image/svg+xml';
        }
        return $mimes;
    }
}
