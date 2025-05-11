<?php

/**
 * AJAX functionality for DrawIt plugin
 */
class DrawIt_Ajax {
    /**
     * Plugin options
     *
     * @var array
     */
    protected $options;
    
    /**
     * Initialize AJAX functionality
     */
    public function __construct() {
        $this->options = DrawIt_Config::get_options();
    }
    
    /**
     * Initialize AJAX hooks
     */
    public function init() {
        add_action('wp_ajax_submit-form-' . DRAWIT_PLUGIN_SLUG, array($this, 'sideload_handler'));
    }
    
    /**
     * Handle AJAX submission to save diagram
     */
    public function sideload_handler() {
        $resp = array('success' => false, 'html' => '');
        
        // Parse XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string(stripslashes($_POST['xml']));
        libxml_use_internal_errors(false);
        
        // Process image data
        $image_data = $this->process_image_data($_POST['img_data']);
        
        // Validate the request
        $validation_error = $this->validate_request($xml, $image_data);
        if ($validation_error) {
            $resp['html'] = $validation_error;
            echo json_encode($resp);
            exit;
        }
        
        $post_id = (int) $_POST['post_id'];
        $title = $this->get_sanitized_title($_POST['title']);
        $file_title = sanitize_file_name($title . '.' . $image_data['type']);
        
        // Set XML attributes
        $xml = $this->prepare_xml_attributes($xml);
        
        // Check if we're editing an existing SVG file
        $existing_data = $this->check_existing_attachment();
        
        // If updating existing SVG
        if ($existing_data['can_update']) {
            $update_result = $this->update_existing_attachment(
                $existing_data['id'], 
                $image_data['data'], 
                $xml, 
                $title
            );
            
            if ($update_result['success']) {
                echo json_encode($update_result);
                exit;
            }
            // If update failed, fall through to create new attachment
        }
        
        // Create new attachment
        $resp = $this->create_new_attachment($post_id, $file_title, $image_data, $xml, $title);
        
        echo json_encode($resp);
        exit;
    }
    
    /**
     * Process image data from base64 or raw format
     * 
     * @param string $img_b64 The raw or base64 encoded image data
     * @return array Array with 'data' and 'type' keys
     */
    private function process_image_data($img_b64) {
        $result = array(
            'data' => '',
            'type' => 'png'
        );
        
        $comma_pos = strpos($img_b64, ',');
        
        if ($comma_pos === false) {
            $result['data'] = stripslashes($img_b64);
        } else {
            // SVG
            if (strpos($img_b64, 'image/svg') !== false) {
                if (strpos($img_b64, 'base64') < $comma_pos) {
                    $result['data'] = base64_decode(substr($img_b64, $comma_pos + 1));
                } else {
                    $result['data'] = urldecode(stripslashes(substr($img_b64, $comma_pos + 1)));
                }
                $result['data'] = $this->sanitize_svg($result['data']); // Sanitize SVG
                $result['type'] = 'svg';
            // PNG
            } else {
                $result['data'] = base64_decode(substr($img_b64, $comma_pos + 1));
            }
        }
        
        return $result;
    }

    /**
     * Sanitize SVG content to remove potentially harmful elements and attributes
     * 
     * @param string $svg_content The raw SVG content
     * @return string Sanitized SVG content
     */
    private function sanitize_svg($svg_content) {
        if (empty($svg_content)) {
            return $svg_content;
        }

        // Load SVG into DOMDocument
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadXML($svg_content, LIBXML_NOENT | LIBXML_DTDLOAD | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        // Remove dangerous elements
        $dangerous_tags = ['script', 'iframe', 'object', 'embed', 'use'];
        foreach ($dangerous_tags as $tag) {
            $elements = $dom->getElementsByTagName($tag);
            while ($elements->length > 0) {
                $elements->item(0)->parentNode->removeChild($elements->item(0));
            }
        }

        // Remove dangerous attributes
        $xpath = new DOMXPath($dom);
        foreach ($xpath->query('//@*[starts-with(name(), "on") or contains(translate(., "JAVASCRIPT:", "javascript:"), "javascript:") or contains(translate(., "DATA:", "data:"), "data:")]') as $attr) {
            $attr->parentNode->removeAttribute($attr->nodeName);
        }

        return $dom->saveXML($dom->documentElement);
    }
    
    /**
     * Validate the request parameters
     * 
     * @param SimpleXMLElement|false $xml The parsed XML
     * @param array $image_data The processed image data
     * @return string|false Error message or false if valid
     */
    private function validate_request($xml, $image_data) {
        // Verify nonce
        if (!isset($_POST['nonce']) || !check_ajax_referer('media-form_' . DRAWIT_PLUGIN_SLUG, 'nonce', false)) {
            return 'Sorry, your nonce did not verify.';
        }
        
        // Check if SVG is allowed
        if (strtolower($image_data['type']) == 'svg' && $this->options['allow_svg'] != 'yes') {
            return 'Sorry, uploading SVG images has been disabled.';
        }
        
        // Check image data
        if (empty($image_data['data'])) {
            return 'Sorry, no image data was provided.';
        }
        
        // Check XML validity
        if (!isset($_POST['xml']) || $_POST['xml'] == "" || !$xml) {
            return 'Sorry, invalid XML was received.';
        }
        
        // Check post ID
        if (!isset($_POST['post_id']) || $_POST['post_id'] == "" || !ctype_digit($_POST['post_id'])) {
            return 'Sorry, post ID was not an integer.';
        }
        
        return false;
    }
    
    /**
     * Get sanitized title or default
     * 
     * @param string $title The submitted title
     * @return string Sanitized title
     */
    private function get_sanitized_title($title) {
        if (!isset($title) || sanitize_file_name($title) == "") {
            return DRAWIT_PLUGIN_SLUG . '_diagram';
        }
        return $title;
    }
    
    /**
     * Prepare XML attributes for saving
     * 
     * @param SimpleXMLElement $xml The XML object
     * @return SimpleXMLElement Modified XML object
     */
    private function prepare_xml_attributes($xml) {
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
        
        foreach ($xml_attr as $key => $val) {
            // Update or add attribute
            if (isset($xml[$key])) {
                $xml[$key] = $val;
            } else {
                $xml->addAttribute($key, $val);
            }
        }
        
        return $xml;
    }
    
    /**
     * Check if there's an existing attachment that can be updated
     * 
     * @return array Status array with 'can_update' and 'id' keys
     */
    private function check_existing_attachment() {
        $result = array(
            'can_update' => false,
            'id' => 0
        );
        
        if (isset($_POST['img_id']) && !empty($_POST['img_id']) && ctype_digit($_POST['img_id'])) {
            $existing_attachment_id = (int) $_POST['img_id'];
            $attachment = get_post($existing_attachment_id);
            
            // Check if the attachment exists and is an SVG image
            if ($attachment && $attachment->post_type === 'attachment') {
                $attachment_url = wp_get_attachment_url($existing_attachment_id);
                $file_info = pathinfo($attachment_url);
                
                // Only replace if it's an SVG and we're saving as SVG
                if (isset($file_info['extension']) && 
                    strtolower($file_info['extension']) === 'svg' && 
                    isset($_POST['img_type']) && 
                    strtolower($_POST['img_type']) === 'svg') {
                    
                    $result['can_update'] = true;
                    $result['id'] = $existing_attachment_id;
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Update an existing attachment
     * 
     * @param int $attachment_id The attachment ID
     * @param string $img_data The image data
     * @param SimpleXMLElement $xml The XML object
     * @param string $title The diagram title
     * @return array Result array with success status and HTML
     */
    private function update_existing_attachment($attachment_id, $img_data, $xml, $title) {
        $resp = array('success' => false, 'html' => '');
        $attachment = get_post($attachment_id);
        
        // Get the file path of the existing attachment
        $file_path = get_attached_file($attachment_id);
        
        if ($file_path && file_exists($file_path)) {
            // Update the existing file
            $update_successful = file_put_contents($file_path, $img_data);
            
            if ($update_successful !== false) {
                // Update attachment metadata
                $metadata = wp_get_attachment_metadata($attachment_id);
                if (is_array($metadata) && array_key_exists('image_meta', $metadata)) {
                    $image_meta = $metadata['image_meta'];
                } else {
                    $image_meta = array();
                }
                
                $image_meta['is_' . DRAWIT_PLUGIN_SLUG] = true;
                $image_meta[DRAWIT_PLUGIN_SLUG . '_xml'] = $xml->asXML();
                $image_meta['title'] = $title;
                $metadata['image_meta'] = $image_meta;
                
                // Update file metadata
                wp_update_attachment_metadata($attachment_id, $metadata);
                
                // Update attachment title if needed
                if ($title !== $attachment->post_title) {
                    wp_update_post(array(
                        'ID' => $attachment_id,
                        'post_title' => $title
                    ));
                }
                
                // Generate response with cache busting
                $resp = $this->generate_success_response($attachment_id, $title, true);
            }
        }
        
        return $resp;
    }
    
    /**
     * Create a new attachment
     * 
     * @param int $post_id The post ID
     * @param string $file_title The file title
     * @param array $image_data The image data array
     * @param SimpleXMLElement $xml The XML object
     * @param string $title The diagram title
     * @return array Result array with success status and HTML
     */
    private function create_new_attachment($post_id, $file_title, $image_data, $xml, $title) {
        $resp = array('success' => false, 'html' => '');
        
        // Determine temp directory
        $tempdir = $this->get_temp_directory();
        
        // Write image to temp file
        $tmpfname = tempnam($tempdir, "php");
        if (strtolower($image_data['type']) == 'svg') {
            $ftmp = fopen($tmpfname, "w");
        } else {
            $ftmp = fopen($tmpfname, "wb");
        }
        
        $ftmp_size = fwrite($ftmp, $image_data['data']);
        $ftmp_meta = stream_get_meta_data($ftmp);
        $file_array = array(
            'name' => $file_title,
            'tmp_name' => $ftmp_meta['uri'],
            'error' => '',
            'type' => $image_data['type'],
            'size' => $ftmp_size
        );
        fclose($ftmp);
        
        // Apply file name filters
        $file_array = apply_filters('wp_handle_upload_prefilter', $file_array);
        
        // Add to media library and attach to post
        $attach_id = media_handle_sideload($file_array, $post_id);
        
        // Process result
        if ($attach_id) {
            if (!is_wp_error($attach_id)) {
                // Update attachment metadata
                $this->update_attachment_metadata($attach_id, $xml, $title);
                
                // Generate success response
                $resp = $this->generate_success_response($attach_id, $title);
            } else {
                if ($ftmp_size !== false) {
                    $resp['html'] = 'Sorry, could not insert attachment into media library. WP error: ' . $attach_id->get_error_message();
                } else {
                    $resp['html'] = 'Sorry, could not save temp file to filesystem. WP error: ' . $attach_id->get_error_message();
                }
            }
        } else {
            $resp['html'] = 'Sorry, file attachment failed.';
        }
        
        // Clean up temp file
        if (file_exists($tmpfname)) {
            unlink($tmpfname);
        }
        
        return $resp;
    }
    
    /**
     * Get the temporary directory for file upload
     * 
     * @return string Path to temporary directory
     */
    private function get_temp_directory() {
        if ($this->options['temp_dir'] == "wp_content") {
            $tempdir_base = wp_upload_dir();
            $tempdir = $tempdir_base['basedir'] . "/" . DRAWIT_PLUGIN_SLUG . "_temp";
            
            // Create temp dir if needed
            if (!file_exists($tempdir)) {
                if (!mkdir($tempdir)) {
                    $tempdir = get_temp_dir();
                }
            }
        } else {
            $tempdir = get_temp_dir();
        }
        
        return $tempdir;
    }
    
    /**
     * Update attachment metadata
     * 
     * @param int $attach_id The attachment ID
     * @param SimpleXMLElement $xml The XML object
     * @param string $title The diagram title
     */
    private function update_attachment_metadata($attach_id, $xml, $title) {
        $metadata = wp_get_attachment_metadata($attach_id);
        if (is_array($metadata) && array_key_exists('image_meta', $metadata)) {
            $image_meta = $metadata['image_meta'];
        } else {
            $image_meta = array();
        }
        
        $image_meta['is_' . DRAWIT_PLUGIN_SLUG] = true;
        $image_meta[DRAWIT_PLUGIN_SLUG . '_xml'] = $xml->asXML();
        $image_meta['title'] = $title;
        $metadata['image_meta'] = $image_meta;
        wp_update_attachment_metadata($attach_id, $metadata);
    }
    
    /**
     * Generate a success response with HTML
     * 
     * @param int $attach_id The attachment ID
     * @param string $title The diagram title
     * @param bool $add_cache_busting Whether to add cache busting
     * @return array Result array with success status and HTML
     */
    private function generate_success_response($attach_id, $title, $add_cache_busting = false) {
        $resp = array('success' => true, 'att_id' => $attach_id);
        
        // Check if we should generate a shortcode instead of HTML
        $generate_shortcode = isset($_POST['as_shortcode']) && ($_POST['as_shortcode'] === 'true' || $_POST['as_shortcode'] === true);
        
        // Get attachment URL
        $file_url = wp_get_attachment_url($attach_id);
        
        // Add cache busting if needed
        if ($add_cache_busting) {
            $timestamp = time();
            $file_url = add_query_arg('ver', $timestamp, $file_url);
            
            // Clean browser cache
            clean_attachment_cache($attach_id);
        }
        
        if ($generate_shortcode) {
            // Generate shortcode
            $resp['html'] = '[' . DRAWIT_PLUGIN_SLUG . ' id="' . $attach_id . '" title="' . esc_attr($title) . '"]';
        } else {
            // Generate HTML
            $img_html = wp_get_attachment_image($attach_id, 'full', false, array(
                'class' => 'aligncenter wp-image-' . $attach_id,
                'title' => htmlentities($title)
            ));
            
            if ($img_html != '') {
                // If cache busting is enabled, replace the original URL with the cache-busted URL
                if ($add_cache_busting) {
                    $img_html = str_replace(wp_get_attachment_url($attach_id), $file_url, $img_html);
                }
                $resp['html'] = $img_html;
            } else {
                $resp['html'] = '<img class="' . DRAWIT_PLUGIN_SLUG . '-img wp-image-' . $attach_id . 
                            '" src="' . $file_url . '" title="' . htmlentities($title) . '">';
            }
        }
        
        return $resp;
    }
}
