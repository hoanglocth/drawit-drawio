<?php

/**
 * Shortcode functionality for DrawIt plugin
 */
class DrawIt_Shortcode {
    /**
     * Plugin options
     *
     * @var array
     */
    protected $options;
    
    /**
     * Initialize shortcode functionality
     */
    public function __construct() {
        $this->options = DrawIt_Config::get_options();
    }
    
    /**
     * Initialize shortcode hooks
     */
    public function init() {
        add_shortcode(DRAWIT_PLUGIN_SLUG, array($this, 'render_shortcode'));
    }
    
    /**
     * Render the shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string Rendered HTML
     */
    public function render_shortcode($atts) {
        $attributes = shortcode_atts(array(
            'id' => 0,
            'title' => '',
            'class' => '',
            'align' => 'center',
            'inline_svg' => true  // New parameter to control inline SVG rendering
        ), $atts);
        
        // Validate attachment ID
        $attachment_id = intval($attributes['id']);
        if ($attachment_id <= 0) {
            return '<!-- ' . DRAWIT_PLUGIN_SLUG . ' error: Invalid attachment ID -->';
        }
        
        // Get attachment information
        $attachment = get_post($attachment_id);
        if (!$attachment || $attachment->post_type !== 'attachment') {
            return '<!-- ' . DRAWIT_PLUGIN_SLUG . ' error: Attachment not found -->';
        }
        
        // Get attachment URL and verify it exists
        $attachment_url = wp_get_attachment_url($attachment_id);
        if (!$attachment_url) {
            return '<!-- ' . DRAWIT_PLUGIN_SLUG . ' error: Attachment URL not found -->';
        }
        
        // Get attachment metadata to verify it's a DrawIt diagram
        $metadata = wp_get_attachment_metadata($attachment_id);
        $is_drawit = false;
        
        if (is_array($metadata) && isset($metadata['image_meta'])) {
            $image_meta = $metadata['image_meta'];
            $is_drawit = isset($image_meta['is_' . DRAWIT_PLUGIN_SLUG]) && $image_meta['is_' . DRAWIT_PLUGIN_SLUG];
        }
        
        if (!$is_drawit) {
            return '<!-- ' . DRAWIT_PLUGIN_SLUG . ' error: Not a valid diagram -->';
        }
        
        // Get title from shortcode attribute or attachment
        $title = !empty($attributes['title']) ? $attributes['title'] : get_the_title($attachment_id);
        
        // Build CSS classes
        $classes = array(DRAWIT_PLUGIN_SLUG . '-shortcode', 'wp-image-' . $attachment_id);
        
        if (!empty($attributes['class'])) {
            $classes[] = esc_attr($attributes['class']);
        }
        
        $align_class = 'align' . esc_attr($attributes['align']);
        $classes[] = $align_class;

        $html = '';
        
        // Check if this is an SVG and we should render it as inline SVG
        $is_svg = strtolower(pathinfo($attachment_url, PATHINFO_EXTENSION)) === 'svg';
        $use_inline_svg = filter_var($attributes['inline_svg'], FILTER_VALIDATE_BOOLEAN) && $is_svg;
        
        if ($use_inline_svg) {
            // Get the SVG file path from URL
            $svg_path = $this->get_svg_path_from_url($attachment_url);
            
            // If the SVG file exists, get its content
            if (file_exists($svg_path)) {
                $svg_content = file_get_contents($svg_path);
                
                // Make sure it's valid SVG content
                if (strpos($svg_content, '<svg') !== false) {
                    // Add classes to the SVG
                    $svg_content = $this->add_classes_to_svg($svg_content, implode(' ', $classes));
                    
                    // Add title if needed
                    if (!empty($title) && strpos($svg_content, '<title>') === false) {
                        $svg_content = $this->add_title_to_svg($svg_content, $title);
                    }
                    
                    $html = $svg_content;
                }
            }
        }
        
        // If inline SVG failed or wasn't requested, use the standard img tag
        if (empty($html)) {
            $html = wp_get_attachment_image($attachment_id, 'full', false, array(
                'class' => implode(' ', $classes),
                'title' => esc_attr($title),
                'alt' => esc_attr($title)
            ));
            
            // If the standard function fails, build our own image tag
            if (empty($html)) {
                $html = sprintf(
                    '<img src="%s" alt="%s" title="%s" class="%s" />',
                    esc_url($attachment_url),
                    esc_attr($title),
                    esc_attr($title),
                    esc_attr(implode(' ', $classes))
                );
            }
        }
        
        // Wrap with figure if needed
        if ($this->options['use_figure_tag'] === 'yes') {
            $html = sprintf(
                '<figure class="%s-figure %s">%s</figure>',
                DRAWIT_PLUGIN_SLUG,
                $align_class,
                $html
            );
        }
        
        return $html;
    }
    
    /**
     * Convert SVG URL to file path
     * 
     * @param string $url URL to SVG file
     * @return string File path to SVG
     */
    protected function get_svg_path_from_url($url) {
        // Handle different URL formats
        $site_url = site_url();
        $upload_url = wp_upload_dir()['baseurl'];
        $upload_dir = wp_upload_dir()['basedir'];
        
        // If it's a full URL in the uploads directory
        if (strpos($url, $upload_url) === 0) {
            return str_replace($upload_url, $upload_dir, $url);
        }
        
        // If it's a full site URL
        if (strpos($url, $site_url) === 0) {
            return str_replace($site_url, ABSPATH, $url);
        }
        
        // If it's a relative URL starting with /
        if (strpos($url, '/') === 0) {
            return ABSPATH . ltrim($url, '/');
        }
        
        // Default fallback - attached file path
        $attached_file = get_attached_file($this->get_attachment_id_from_url($url));
        if ($attached_file && file_exists($attached_file)) {
            return $attached_file;
        }
        
        return $url;
    }
    
    /**
     * Get attachment ID from URL
     * 
     * @param string $url The URL to the attachment
     * @return int|null Attachment ID or null if not found
     */
    protected function get_attachment_id_from_url($url) {
        global $wpdb;
        $url = preg_replace('/-\d+x\d+(?=\.(jpg|jpeg|png|gif|svg)$)/i', '', $url);
        $url = preg_replace('/\?.*$/', '', $url);
        
        $attachment = $wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid='%s';", $url));
        
        if (!empty($attachment)) {
            return $attachment[0];
        }
        
        return null;
    }
    
    /**
     * Add classes to SVG element
     * 
     * @param string $svg_content The SVG content
     * @param string $classes The classes to add
     * @return string Modified SVG content
     */
    protected function add_classes_to_svg($svg_content, $classes) {
        // If already has a class attribute, add to it
        if (preg_match('/<svg[^>]*class="([^"]*)"[^>]*>/i', $svg_content, $matches)) {
            $existing_classes = $matches[1];
            $new_classes = $existing_classes . ' ' . $classes;
            $svg_content = preg_replace(
                '/<svg([^>]*?)class="' . preg_quote($existing_classes, '/') . '"([^>]*?)>/i',
                '<svg$1class="' . esc_attr($new_classes) . '"$2>',
                $svg_content
            );
        } 
        // If no class attribute, add one
        else {
            $svg_content = preg_replace(
                '/<svg([^>]*?)>/i',
                '<svg$1 class="' . esc_attr($classes) . '">',
                $svg_content
            );
        }
        
        return $svg_content;
    }
    
    /**
     * Add title element to SVG
     * 
     * @param string $svg_content The SVG content
     * @param string $title The title to add
     * @return string Modified SVG content
     */
    protected function add_title_to_svg($svg_content, $title) {
        // Add title element right after the opening SVG tag
        return preg_replace(
            '/<svg([^>]*?)>/i',
            '<svg$1><title>' . esc_html($title) . '</title>',
            $svg_content
        );
    }
}
