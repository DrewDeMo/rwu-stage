<?php
/**
 * TCL Builder Meta Handler
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class TCL_Builder_Meta {
    private static $instance = null;
    private static $logger = null;
    const META_KEY = '_tcl_builder_sections';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        self::$logger = TCL_Builder_Logger::get_instance();
        $this->init_hooks();
        self::$logger->log('TCL Builder Meta handler initialized', 'info');
    }

    private function init_hooks() {
        add_action('init', array($this, 'register_meta'));
        add_action('post_updated', array($this, 'handle_post_duplication'), 10, 3);
        add_action('before_delete_post', array($this, 'cleanup_meta'));
    }

    public function register_meta() {
        register_post_meta('', self::META_KEY, array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'auth_callback' => function($allowed, $meta_key, $post_id) {
                return current_user_can('edit_post', $post_id);
            }
        ));
    }

    public static function get_sections($post_id) {
        $sections = get_post_meta($post_id, self::META_KEY, true);
        
        if (empty($sections)) {
            return array();
        }

        $decoded = json_decode($sections, true);
        if (!is_array($decoded)) {
            return array();
        }

        $sections_data = isset($decoded['sections']) ? $decoded['sections'] : $decoded;
        
        if (!is_array($sections_data)) {
            return array();
        }

        // Decode base64 content for HTML sections
        return array_map(function($section) {
            $section['id'] = isset($section['id']) ? intval($section['id']) : time();
            
            // Ensure shadow_context is set
            $section['shadow_context'] = isset($section['shadow_context']) ? (bool)$section['shadow_context'] : false;
            
            if ($section['type'] === 'html' && isset($section['content'])) {
                // Handle legacy sections without js field
                if (!isset($section['content']['js'])) {
                    $section['content']['js'] = '';
                }
                
                $section['content'] = array(
                    'html' => base64_decode($section['content']['html'] ?? ''),
                    'css' => base64_decode($section['content']['css'] ?? ''),
                    'js' => base64_decode($section['content']['js'])
                );
            }
            
            return $section;
        }, $sections_data);
    }

    public static function update_sections($post_id, $sections) {
        try {
            if (!is_array($sections)) {
                throw new Exception('Invalid sections data structure');
            }

            // Validate post exists
            $post = get_post($post_id);
            if (!$post) {
                throw new Exception('Post not found');
            }

            $processed_sections = array();
            $section_ids = array(); // Track section IDs to ensure uniqueness

            foreach ($sections as $section) {
                if (!isset($section['type'])) {
                    throw new Exception('Section type is required');
                }

                // Generate or validate section ID
                $section_id = isset($section['id']) ? absint($section['id']) : time() + mt_rand(1000, 9999);
                if (in_array($section_id, $section_ids)) {
                    $section_id = time() + mt_rand(1000, 9999); // Generate new ID if duplicate
                }
                $section_ids[] = $section_id;

                $processed_section = array(
                    'id' => $section_id,
                    'type' => sanitize_text_field($section['type']),
                    'title' => isset($section['title']) ? sanitize_text_field($section['title']) : '',
                    'designation' => isset($section['designation']) ? sanitize_text_field($section['designation']) : 'default',
                    'shadow_context' => isset($section['shadow_context']) ? (bool)$section['shadow_context'] : false,
                    'last_modified' => current_time('mysql'),
                    'modified_by' => get_current_user_id(),
                    'version' => TCL_BUILDER_VERSION
                );

                // Validate and process content based on type
                if ($section['type'] === 'html') {
                    if (!isset($section['content'])) {
                        throw new Exception('Missing content for HTML section');
                    }

                    // Handle both string and array content formats
                    $content = is_array($section['content']) ? $section['content'] : array(
                        'html' => '',
                        'css' => ''
                    );

                    // Process HTML content
                    $html_content = isset($content['html']) ? $content['html'] : '';
                    $css_content = isset($content['css']) ? $content['css'] : '';

                    // Skip strict validation for empty content
                    if (!empty($html_content) && !self::is_valid_html($html_content)) {
                        self::$logger->log('HTML validation failed', 'warning', array(
                            'content' => substr($html_content, 0, 500),
                            'section_id' => $section_id
                        ));
                        // Don't throw, just log warning
                    }

                    if (!empty($css_content) && !self::is_valid_css($css_content)) {
                        self::$logger->log('CSS validation failed', 'warning', array(
                            'content' => substr($css_content, 0, 500),
                            'section_id' => $section_id
                        ));
                        // Don't throw, just log warning
                    }

                    // Base64 encode only if not already encoded
                    // Process content with Shadow DOM awareness
                    $html_content = isset($content['html']) ? $content['html'] : '';
                    $css_content = isset($content['css']) ? $content['css'] : '';
                    $js_content = isset($content['js']) ? $content['js'] : '';

                    // Additional validation for Shadow DOM sections
                    if ($processed_section['shadow_context']) {
                        self::$logger->log('[ShadowDOM] Processing Shadow DOM section', 'info', array(
                            'section_id' => $section_id
                        ));
                    }

                    $processed_section['content'] = array(
                        'html' => base64_encode(base64_decode($html_content, true) ?: $html_content),
                        'css' => base64_encode(base64_decode($css_content, true) ?: $css_content),
                        'js' => base64_encode(base64_decode($js_content, true) ?: $js_content)
                    );
                } else {
                    // For non-HTML sections, validate and store content
                    if (!isset($section['content']) || !is_string($section['content'])) {
                        throw new Exception('Invalid shortcode content');
                    }
                    $processed_section['content'] = wp_kses($section['content'], self::get_allowed_html());

                }

                $processed_sections[] = $processed_section;
            }

            if (empty($processed_sections)) {
                throw new Exception('No valid sections after processing');
            }

            // Prepare metadata
            $meta = array(
                'sections' => $processed_sections,
                'version' => TCL_BUILDER_VERSION,
                'last_modified' => current_time('mysql'),
                'modified_by' => get_current_user_id(),
                'checksum' => md5(serialize($processed_sections))
            );

            // Encode with error checking
            $encoded = wp_json_encode($meta);
            if ($encoded === false) {
                throw new Exception('JSON encoding failed: ' . json_last_error_msg());
            }

            // Update post meta with backup
            $old_meta = get_post_meta($post_id, self::META_KEY, true);
            $result = update_post_meta($post_id, self::META_KEY, $encoded);
            
            if ($result === false) {
                throw new Exception('Failed to update post meta');
            }

            // Store backup
            update_post_meta($post_id, self::META_KEY . '_backup', $old_meta);

            // Clear caches
            wp_cache_delete($post_id, 'post_meta');
            clean_post_cache($post_id);

            // Fire sections updated action
            do_action('tcl_builder_sections_updated', $post_id, $processed_sections);

            return true;

        } catch (Exception $e) {
            self::$logger->log('Error updating sections', 'error', array(
                'post_id' => $post_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            return false;
        }
    }

    private static function get_allowed_html() {
        return array_merge(
            wp_kses_allowed_html('post'),
            array(
                'iframe' => array(
                    'src' => true,
                    'width' => true,
                    'height' => true,
                    'frameborder' => true,
                    'style' => true,
                    'loading' => true,
                    'allowfullscreen' => true,
                    'allow' => true,
                    'title' => true,
                    'class' => true,
                    'id' => true
                ),
                'input' => array(
                    'type' => true,
                    'id' => true,
                    'class' => true,
                    'placeholder' => true,
                    'value' => true,
                    'name' => true,
                    'required' => true,
                    'min' => true,
                    'max' => true,
                    'pattern' => true,
                    'style' => true,
                    'autocomplete' => true
                ),
                'select' => array(
                    'id' => true,
                    'class' => true,
                    'name' => true,
                    'required' => true,
                    'style' => true
                ),
                'option' => array(
                    'value' => true,
                    'selected' => true
                ),
                'textarea' => array(
                    'id' => true,
                    'class' => true,
                    'name' => true,
                    'required' => true,
                    'rows' => true,
                    'cols' => true,
                    'style' => true,
                    'placeholder' => true
                ),
                'form' => array(
                    'id' => true,
                    'class' => true,
                    'action' => true,
                    'method' => true,
                    'style' => true
                ),
                'button' => array(
                    'type' => true,
                    'id' => true,
                    'class' => true,
                    'style' => true,
                    'onclick' => true
                )
            )
        );
    }

    private static function is_valid_html($html) {
        if (empty($html)) return true;

        // Check if already base64 encoded
        if (base64_decode($html, true)) {
            $html = base64_decode($html);
        }

        // Sanitize HTML while preserving all allowed elements
        $sanitized = wp_kses($html, self::get_allowed_html());
        
        // Check if essential content was stripped
        if (empty($sanitized) && !empty($html)) {
            self::$logger->log('HTML validation warning: content was stripped', 'warning');
            return false;
        }

        return true;
    }

    private static function is_valid_css($css) {
        if (empty($css)) return true;

        // Basic CSS validation
        $css = trim($css);
        
        // Check for balanced braces
        $open_braces = substr_count($css, '{');
        $close_braces = substr_count($css, '}');
        
        if ($open_braces !== $close_braces) {
            return false;
        }

        // Check for common syntax errors
        if (preg_match('/[^}]*{[^}]*{/', $css)) { // Nested without closing
            return false;
        }

        return true;
    }

    public function handle_post_duplication($post_id, $post_after, $post_before) {
        if (wp_is_post_revision($post_id) || defined('DOING_AUTOSAVE') && DOING_AUTOSAVE || $post_before->post_status === 'new') {
            return;
        }

        if ($post_after->post_status === 'publish' && $post_before->post_status === 'publish') {
            $sections = self::get_sections($post_before->ID);
            if (is_array($sections) && !empty($sections)) {
                $sections = array_map(function($section) {
                    $section['id'] = time() + rand(1, 1000);
                    return $section;
                }, $sections);
                self::update_sections($post_id, $sections);
            }
        }
    }

    public function cleanup_meta($post_id) {
        delete_post_meta($post_id, self::META_KEY);
        delete_post_meta($post_id, self::META_KEY . '_backup');
    }
}

TCL_Builder_Meta::get_instance();
