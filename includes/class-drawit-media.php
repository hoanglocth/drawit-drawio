<?php

/**
 * Media functionality for DrawIt plugin
 */
class DrawIt_Media {
    /**
     * Plugin options
     *
     * @var array
     */
    protected $options;
    
    /**
     * Initialize media functionality
     */
    public function __construct() {
        $this->options = DrawIt_Config::get_options();
    }
    
    /**
     * Initialize media hooks
     */
    public function init() {
        add_filter('media_upload_tabs', array($this, 'add_tab'));
        add_action('media_upload_' . DRAWIT_PLUGIN_SLUG, array($this, 'media_menu_handler'));
    }
    
    /**
     * Add diagrams.net tab to media upload page
     * 
     * @param array $tabs Media tabs
     * @return array Modified media tabs
     */
    public function add_tab($tabs) {
        $tabs[DRAWIT_PLUGIN_SLUG] = 'Draw with diagrams.net';
        return $tabs;
    }
    
    /**
     * Media menu handler - loads the editor iframe
     */
    public function media_menu_handler() {
        $errors = '';
        
        // Enqueue scripts with proper dependencies
        wp_register_script(
            DRAWIT_PLUGIN_SLUG . '-utils',
            DRAWIT_PLUGIN_URL . 'js/drawit-utils.js',
            array('jquery'),
            DRAWIT_VERSION,
            true
        );
        
        wp_register_script(
            DRAWIT_PLUGIN_SLUG . '-js', 
            DRAWIT_PLUGIN_URL . 'js/drawit.js',
            array('jquery', DRAWIT_PLUGIN_SLUG . '-utils'),
            DRAWIT_VERSION,
            true
        );
        
        // Enqueue both scripts
        wp_enqueue_script(DRAWIT_PLUGIN_SLUG . '-utils');
        wp_enqueue_script(DRAWIT_PLUGIN_SLUG . '-js');
        
        // Enqueue styles based on user role
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
        
        return wp_iframe(array($this, 'iframe'), $errors);
    }
    
    /**
     * Render the editor iframe content
     * 
     * @param string $errors Error messages
     */
    public function iframe($errors = '') {
        include DRAWIT_PLUGIN_DIR . 'templates/editor-iframe.php';
    }
}
