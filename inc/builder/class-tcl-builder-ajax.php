<?php
/**
 * TCL Builder AJAX Handler
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!defined('TCL_BUILDER_SHADOW_DOM_VERSION')) {
    define('TCL_BUILDER_SHADOW_DOM_VERSION', '1.0.0');
}

class TCL_Builder_AJAX {
    /**
     * Instance of this class
     */
    private static $instance = null;
    private $logger;

    /**
     * Sanitize Shadow DOM JavaScript content
     */
    private function sanitize_shadow_dom_js($js_content) {
        // Basic JS sanitization while preserving Shadow DOM patterns
        $js_content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $js_content);
        return wp_strip_all_tags($js_content);
    }

    /**
     * Validate Shadow DOM content
     */
    private function validate_shadow_dom_content($content) {
        // Validate content structure and shadow DOM references
        if (empty($content)) {
            return false;
        }
        
        // Check for basic UTF-8 compliance
        if (!mb_check_encoding($content, 'UTF-8')) {
            return false;
        }
        
        return true;
    }

    /**
     * Get single instance of this class
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->logger = TCL_Builder_Logger::get_instance();
        $this->init_hooks();
        $this->logger->log('TCL Builder AJAX handler initialized', 'info');
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // AJAX handlers for logged-in users
        add_action('wp_ajax_tcl_builder_save_sections', array($this, 'save_sections'));
        add_action('wp_ajax_tcl_builder_load_sections', array($this, 'load_sections'));
        add_action('wp_ajax_tcl_builder_delete_section', array($this, 'delete_section'));
        add_action('wp_ajax_tcl_builder_reorder_sections', array($this, 'reorder_sections'));
        add_action('wp_ajax_tcl_builder_log', array($this, 'handle_js_log'));
        
        // Import/Export handlers
        add_action('wp_ajax_tcl_builder_export_sections', array($this, 'export_sections'));
        add_action('wp_ajax_tcl_builder_import_sections', array($this, 'import_sections'));
    }

    /**
     * Save sections
     */
    public function save_sections() {
        try {
            $this->logger->log('Attempting to save sections via AJAX', 'info');

            // Enable error reporting for debugging
            if (WP_DEBUG) {
                error_reporting(E_ALL);
                ini_set('display_errors', 1);
            }

            // Verify nonce with detailed error
            if (!check_ajax_referer('tcl_builder_nonce', 'nonce', false)) {
                $this->logger->log('Nonce verification failed in save_sections', 'error');
                throw new Exception(__('Security verification failed. Please refresh the page and try again.', 'tcl-builder'));
            }

            // Verify user can edit this post
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            if (!$post_id) {
                throw new Exception(__('Invalid post ID provided.', 'tcl-builder'));
            }

            if (!current_user_can('edit_post', $post_id)) {
                throw new Exception(__('You do not have permission to edit this post.', 'tcl-builder'));
            }

            // Get and validate raw sections data
            if (!isset($_POST['sections'])) {
                throw new Exception(__('No sections data provided in request.', 'tcl-builder'));
            }

            $raw_sections = $_POST['sections'];
            if (empty($raw_sections)) {
                throw new Exception(__('Empty sections data provided.', 'tcl-builder'));
            }

            // Log raw data length for debugging
            $this->logger->log('Raw sections data received', 'debug', array(
                'length' => strlen($raw_sections),
                'post_id' => $post_id
            ));

            // Clean and decode JSON data
            $raw_sections = wp_unslash($raw_sections);
            
            // Validate JSON structure
            $sections = json_decode($raw_sections, true);
            $json_error = json_last_error();
            if ($json_error !== JSON_ERROR_NONE) {
                $this->logger->log('JSON decode error', 'error', array(
                    'error' => json_last_error_msg(),
                    'raw_length' => strlen($raw_sections)
                ));
                throw new Exception(sprintf(
                    __('Invalid JSON data provided: %s', 'tcl-builder'),
                    json_last_error_msg()
                ));
            }

            if (!is_array($sections)) {
                throw new Exception(__('Decoded sections data is not an array.', 'tcl-builder'));
            }

            if (empty($sections)) {
                $this->logger->log('Empty sections array after decode', 'warning');
            }

            // Normalize and validate sections with improved error handling
            $normalized_sections = array();
            foreach ($sections as $index => $section) {
                try {
                    // Validate section structure
                    if (!is_array($section)) {
                        $this->logger->log('Invalid section format', 'warning', array(
                            'index' => $index,
                            'type' => gettype($section)
                        ));
                        continue;
                    }

                    // Required fields validation
                    $required_fields = array('type', 'content');
                    foreach ($required_fields as $field) {
                        if (!isset($section[$field])) {
                            throw new Exception("Missing required field: {$field}");
                        }
                    }

                    // Create normalized section with defaults
                    $normalized_section = array(
                        'id' => isset($section['id']) ? absint($section['id']) : time() + $index,
                        'type' => sanitize_text_field($section['type']),
                        'title' => isset($section['title']) ? sanitize_text_field($section['title']) : 'Untitled Section',
                        'designation' => isset($section['designation']) ? sanitize_text_field($section['designation']) : 'library',
                        'shadow_context' => isset($section['shadow_context']) ? (bool)$section['shadow_context'] : false,
                        'last_modified' => current_time('mysql')
                    );

                    // Handle content based on type with validation
                    if ($section['type'] === 'html') {
                        if (!isset($section['content']['html']) || !isset($section['content']['css'])) {
                            throw new Exception('Invalid HTML section content structure');
                        }

                        $js_content = isset($section['content']['js']) ? $section['content']['js'] : '';
                        if ($normalized_section['shadow_context']) {
                            $js_content = $this->sanitize_shadow_dom_js($js_content);
                            if (!$this->validate_shadow_dom_content($js_content)) {
                                throw new Exception('Invalid Shadow DOM JavaScript content');
                            }
                            $this->logger->log('[ShadowDOM] Processed shadow DOM section', 'info', array(
                                'section_id' => $normalized_section['id']
                            ));
                        }

                        $normalized_section['content'] = array(
                            'html' => wp_kses_post($section['content']['html']),
                            'css' => wp_strip_all_tags($section['content']['css']),
                            'js' => $js_content
                        );
                    } else {
                        if (!is_string($section['content'])) {
                            throw new Exception('Invalid shortcode content type');
                        }
                        $normalized_section['content'] = wp_kses_post($section['content']);
                    }

                    $normalized_sections[] = $normalized_section;

                } catch (Exception $e) {
                    $this->logger->log('Section normalization error', 'error', array(
                        'index' => $index,
                        'error' => $e->getMessage(),
                        'section' => $section
                    ));
                    // Continue processing other sections
                    continue;
                }
            }

            // Ensure we have at least one valid section
            if (empty($normalized_sections)) {
                throw new Exception(__('No valid sections after normalization.', 'tcl-builder'));
            }

            // Get current post for revision
            $post = get_post($post_id);
            if (!$post) {
                throw new Exception(__('Post not found.', 'tcl-builder'));
            }

            // Begin transaction-like operation
            global $wpdb;
            $wpdb->query('START TRANSACTION');

            try {
                // Temporarily remove revision creation action
                remove_action('tcl_builder_sections_updated', array(TCL_Builder_Revisions::get_instance(), 'maybe_save_revision'), 10);

                // Save sections to meta
                $meta_result = TCL_Builder_Meta::update_sections($post_id, $normalized_sections);
                if ($meta_result === false) {
                    throw new Exception(__('Failed to save sections to post meta.', 'tcl-builder'));
                }

                // Update post to trigger single revision
                $post_data = array(
                    'ID' => $post_id,
                    'post_modified' => current_time('mysql'),
                    'post_modified_gmt' => current_time('mysql', true)
                );

                // Add filter to ensure sections are included in the revision
                add_filter('wp_save_post_revision_post_has_changed', '__return_true');
                
                $updated = wp_update_post($post_data);
                
                // Remove the filter
                remove_filter('wp_save_post_revision_post_has_changed', '__return_true');

                if (is_wp_error($updated)) {
                    throw new Exception($updated->get_error_message());
                }

                if ($updated === 0) {
                    throw new Exception(__('Failed to update post.', 'tcl-builder'));
                }

                // Re-add revision creation action
                add_action('tcl_builder_sections_updated', array(TCL_Builder_Revisions::get_instance(), 'maybe_save_revision'), 10, 2);

                $wpdb->query('COMMIT');

                // Get fresh sections data
                $saved_sections = TCL_Builder_Meta::get_sections($post_id);

                // Clear any caches
                clean_post_cache($post_id);
                wp_cache_delete($post_id, 'post_meta');

                $this->logger->log('Sections saved successfully', 'info', array(
                    'post_id' => $post_id,
                    'section_count' => count($saved_sections)
                ));

                wp_send_json_success(array(
                    'message' => __('Sections saved successfully.', 'tcl-builder'),
                    'sections' => $saved_sections
                ));

            } catch (Exception $e) {
                $wpdb->query('ROLLBACK');
                throw $e;
            }

        } catch (Exception $e) {
            $this->logger->log('Error saving sections', 'error', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));

            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'code' => $e->getCode() ?: 500
            ));
        }
    }

    /**
     * Load sections
     */
    public function load_sections() {
        $this->logger->log('Attempting to load sections via AJAX', 'info');
        
        // Verify nonce
        if (!check_ajax_referer('tcl_builder_nonce', 'nonce', false)) {
            $this->logger->log('Nonce verification failed in load_sections', 'error');
            wp_send_json_error(array('message' => __('Invalid security token.', 'tcl-builder')));
        }

        // Verify user can edit this post
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => __('You do not have permission to edit this post.', 'tcl-builder')));
        }

        try {
            // Use TCL_Builder_Meta to get sections
            $sections = TCL_Builder_Meta::get_sections($post_id);
            
            if (!is_array($sections)) {
                throw new Exception(__('Invalid sections data structure.', 'tcl-builder'));
            }

            wp_send_json_success(array(
                'sections' => $sections
            ));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Delete section
     */
    public function delete_section() {
        $this->logger->log('Attempting to delete section via AJAX', 'info');
        
        // Verify nonce
        if (!check_ajax_referer('tcl_builder_nonce', 'nonce', false)) {
            $this->logger->log('Nonce verification failed in delete_section', 'error');
            wp_send_json_error(array('message' => __('Invalid security token.', 'tcl-builder')));
        }

        // Verify user can edit this post
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => __('You do not have permission to edit this post.', 'tcl-builder')));
        }

        try {
            // Get and validate section ID
            $section_id = isset($_POST['section_id']) ? intval($_POST['section_id']) : 0;
            if (!$section_id) {
                throw new Exception(__('Invalid section ID.', 'tcl-builder'));
            }

            // Use TCL_Builder_Meta to delete section
            $result = TCL_Builder_Meta::delete_section($post_id, $section_id);
            
            if ($result === false) {
                throw new Exception(__('Failed to delete section.', 'tcl-builder'));
            }

            // Get updated sections
            $sections = TCL_Builder_Meta::get_sections($post_id);

            wp_send_json_success(array(
                'message' => __('Section deleted successfully.', 'tcl-builder'),
                'sections' => $sections
            ));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Reorder sections
     */
    public function reorder_sections() {
        $this->logger->log('Attempting to reorder sections via AJAX', 'info');
        
        // Verify nonce
        if (!check_ajax_referer('tcl_builder_nonce', 'nonce', false)) {
            $this->logger->log('Nonce verification failed in reorder_sections', 'error');
            wp_send_json_error(array('message' => __('Invalid security token.', 'tcl-builder')));
        }

        // Verify user can edit this post
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => __('You do not have permission to edit this post.', 'tcl-builder')));
        }

        try {
            // Get and validate section order
            $order = isset($_POST['order']) ? array_map('intval', $_POST['order']) : array();
            if (!is_array($order) || empty($order)) {
                throw new Exception(__('Invalid section order.', 'tcl-builder'));
            }

            // Use TCL_Builder_Meta to reorder sections
            $result = TCL_Builder_Meta::reorder_sections($post_id, $order);
            
            if ($result === false) {
                throw new Exception(__('Failed to reorder sections.', 'tcl-builder'));
            }

            // Get updated sections
            $sections = TCL_Builder_Meta::get_sections($post_id);

            wp_send_json_success(array(
                'message' => __('Sections reordered successfully.', 'tcl-builder'),
                'sections' => $sections
            ));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    /**
     * Handle JavaScript logging
     */
    public function handle_js_log() {
        // Verify nonce
        if (!check_ajax_referer('tcl_builder_nonce', 'nonce', false)) {
            $this->logger->log('JS logging nonce verification failed', 'error');
            wp_send_json_error(array('message' => __('Invalid security token.', 'tcl-builder')));
        }

        // Get log data
        $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';
        $level = isset($_POST['level']) ? sanitize_text_field($_POST['level']) : 'info';
        $context = isset($_POST['context']) ? json_decode(stripslashes($_POST['context']), true) : array();

        if (empty($message)) {
            wp_send_json_error(array('message' => __('No log message provided.', 'tcl-builder')));
        }

        // Add client-side indicator to context
        $context['source'] = 'javascript';

        // Log the message
        $this->logger->log($message, $level, $context);

        wp_send_json_success();
    }

    /**
     * Export sections
     */
    public function export_sections() {
        try {
            // Verify nonce
            if (!check_ajax_referer('tcl_builder_sections_manager_nonce', 'nonce', false)) {
                throw new Exception(__('Security verification failed.', 'tcl-builder'));
            }

            // Get and validate post ID
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            if (!$post_id || !current_user_can('edit_post', $post_id)) {
                throw new Exception(__('Invalid post ID or insufficient permissions.', 'tcl-builder'));
            }

            // Get sections
            $sections = TCL_Builder_Meta::get_sections($post_id);
            if (empty($sections)) {
                throw new Exception(__('No sections found for this post.', 'tcl-builder'));
            }

            // Prepare export data with metadata
            $export_data = array(
                'version' => TCL_BUILDER_VERSION,
                'shadow_dom_version' => TCL_BUILDER_SHADOW_DOM_VERSION,
                'timestamp' => current_time('mysql'),
                'post_id' => $post_id,
                'post_title' => get_the_title($post_id),
                'sections' => $sections
            );

            wp_send_json_success($export_data);

        } catch (Exception $e) {
            $this->logger->log('Export sections error', 'error', array(
                'error' => $e->getMessage(),
                'post_id' => isset($post_id) ? $post_id : 0
            ));
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Import sections
     */
    public function import_sections() {
        try {
            // Verify nonce
            if (!check_ajax_referer('tcl_builder_sections_manager_nonce', 'nonce', false)) {
                throw new Exception(__('Security verification failed.', 'tcl-builder'));
            }

            // Get and validate post ID
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            if (!$post_id || !current_user_can('edit_post', $post_id)) {
                throw new Exception(__('Invalid post ID or insufficient permissions.', 'tcl-builder'));
            }

            // Get and validate import data
            $import_data = json_decode(stripslashes($_POST['sections']), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception(__('Invalid JSON data.', 'tcl-builder'));
            }

            // Validate sections structure
            if (!isset($import_data['sections']) || !TCL_Builder_Sections_Manager::validate_sections_data($import_data['sections'])) {
                throw new Exception(__('Invalid sections data structure.', 'tcl-builder'));
            }

            // Handle legacy imports
            if (!isset($import_data['shadow_dom_version'])) {
                // Legacy import - add shadow_context = false to all sections
                foreach ($import_data['sections'] as &$section) {
                    $section['shadow_context'] = false;
                }
                $this->logger->log('[ShadowDOM] Processed legacy import', 'info');
            }

            // Begin transaction-like operation
            global $wpdb;
            $wpdb->query('START TRANSACTION');

            try {
                // Import sections
                $result = TCL_Builder_Meta::update_sections($post_id, $import_data['sections']);
                if (!$result) {
                    throw new Exception(__('Failed to import sections.', 'tcl-builder'));
                }

                // Update post modification time
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_modified' => current_time('mysql'),
                    'post_modified_gmt' => current_time('mysql', true)
                ));

                $wpdb->query('COMMIT');

                // Get updated sections
                $sections = TCL_Builder_Meta::get_sections($post_id);

                // Clear caches
                clean_post_cache($post_id);
                wp_cache_delete($post_id, 'post_meta');

                wp_send_json_success(array(
                    'message' => __('Sections imported successfully.', 'tcl-builder'),
                    'sections' => $sections
                ));

            } catch (Exception $e) {
                $wpdb->query('ROLLBACK');
                throw $e;
            }

        } catch (Exception $e) {
            $this->logger->log('Import sections error', 'error', array(
                'error' => $e->getMessage(),
                'post_id' => isset($post_id) ? $post_id : 0
            ));
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
}

// Initialize the AJAX handler
TCL_Builder_AJAX::get_instance();
