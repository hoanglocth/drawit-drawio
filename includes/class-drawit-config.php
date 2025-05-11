<?php

/**
 * Configuration class for DrawIt plugin
 */
class DrawIt_Config {
    
    /**
     * Get default plugin options
     *
     * @return array Default options
     */
    public static function get_default_options() {
        return array(
            'default_type' => 'png',
            'allow_svg'    => 'no',
            'temp_dir'     => 'wp_default'
        );
    }
    
    /**
     * Get valid image types
     *
     * @return array Valid image types
     */
    public static function get_valid_types() {
        return array(
            'png',
            'svg'
        );
    }
    
    /**
     * Get valid CSS units
     *
     * @return array Valid CSS units
     */
    public static function get_valid_units() {
        return array(
            'em', 'ex', '%', 'px', 'cm', 'mm', 'in', 'pt', 'pc',
            'ch', 'rem', 'vh', 'vw', 'vmin', 'vmax'
        );
    }
    
    /**
     * Get valid temporary directory options
     *
     * @return array Valid temporary directory options
     */
    public static function get_valid_temp_dirs() {
        return array(
            'wp_default',
            'wp_content'
        );
    }
    
    /**
     * Get plugin options
     *
     * @return array Plugin options with defaults applied
     */
    public static function get_options() {
        $options = get_option(DRAWIT_PLUGIN_SLUG . '_options', self::get_default_options());
        
        // Update options if necessary items don't exist
        // Version 1.0.10+ check
        if (!array_key_exists('temp_dir', $options)) {
            $options['temp_dir'] = self::get_default_options()['temp_dir'];
            update_option(DRAWIT_PLUGIN_SLUG . '_options', $options);
        }
        
        return $options;
    }
    
    /**
     * Get valid image types filtered by settings
     *
     * @return array Valid image types filtered by settings
     */
    public static function get_filtered_valid_types() {
        $options = self::get_options();
        $valid_types = self::get_valid_types();
        
        // If SVG is disabled, remove it from valid types
        if (strtolower($options['allow_svg']) != 'yes') {
            $valid_types = array_filter($valid_types, function($type) {
                return strtolower($type) != 'svg';
            });
        }
        
        return $valid_types;
    }
}
