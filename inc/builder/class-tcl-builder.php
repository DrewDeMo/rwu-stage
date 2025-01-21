<?php
/**
 * TCL Builder main class
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class TCL_Builder {
    /**
     * Instance of this class
     */
    private static $instance = null;
    private $logger;

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
    protected function __construct() {
        $this->logger = TCL_Builder_Logger::get_instance();
        
        // Load revisions handler
        require_once dirname(__FILE__) . '/class-tcl-builder-revisions.php';
        TCL_Builder_Revisions::get_instance();
        
        $this->init_hooks();
        $this->logger->log('TCL Builder initialized', 'info');
    }

    /**
     * Initialize hooks
     */
    protected function init_hooks() {
        // Replace Gutenberg with our builder
        add_action('init', array($this, 'remove_gutenberg_support'), 100);
        add_action('add_meta_boxes', array($this, 'add_builder_meta_box'));
        
        // Add builder assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        
        // Handle builder data
        add_action('save_post', array($this, 'save_builder_data'), 10, 2);
        add_filter('the_content', array($this, 'render_builder_content'));
        
        // AJAX handlers
        add_action('wp_ajax_tcl_builder_reorder_sections', array($this, 'handle_reorder_sections'));
    }

    /**
     * Handle section reordering
     */
    public function handle_reorder_sections() {
        try {
            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'tcl_builder_nonce')) {
                throw new Exception('Security verification failed');
            }

            // Get post ID and new order
            $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
            $order = isset($_POST['order']) ? array_map('absint', $_POST['order']) : [];
            $sections_data = isset($_POST['sections']) ? json_decode(stripslashes($_POST['sections']), true) : [];

            if (!$post_id || empty($order) || empty($sections_data)) {
                throw new Exception('Invalid request parameters');
            }

            // Get current sections
            $sections = TCL_Builder_Meta::get_sections($post_id);

            // Create mapping of section IDs to their data
            $section_map = [];
            foreach ($sections_data as $section) {
                $section_map[$section['id']] = $section;
            }

            // Reorder sections and update designations
            $reordered_sections = [];
            foreach ($order as $section_id) {
                if (isset($section_map[$section_id])) {
                    // Find the original section
                    $original_section = array_filter($sections, function($s) use ($section_id) {
                        return $s['id'] === $section_id;
                    });
                    
                    if (!empty($original_section)) {
                        $section = array_shift($original_section);
                        // Update designation from the received data
                        $section['designation'] = $section_map[$section_id]['designation'];
                        $reordered_sections[] = $section;
                    }
                }
            }

            // Update sections
            TCL_Builder_Meta::update_sections($post_id, $reordered_sections);

            wp_send_json_success([
                'sections' => $reordered_sections
            ]);
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Remove Gutenberg support
     */
    public function remove_gutenberg_support() {
        $this->logger->log('Removing Gutenberg support', 'info');
        remove_post_type_support('post', 'editor');
        remove_post_type_support('page', 'editor');
        remove_post_type_support('shared_section', 'editor');
    }

    /**
     * Add builder meta box
     */
    public function add_builder_meta_box() {
        add_meta_box(
            'tcl-builder',
            __('TCL Builder', 'tcl-builder'),
            array($this, 'render_builder_meta_box'),
            array('post', 'page', 'shared_section'), // Add shared_section post type
            'normal',
            'high'
        );
    }

    /**
     * Render builder meta box
     */
    public function render_builder_meta_box($post) {
        // Output builder container
        ?>
        <div class="tcl-builder-container">
            <div class="content-sections" id="tcl-builder-sections">
                <!-- Primary Add Section Button -->
                <button class="add-section-btn primary">
                    <i data-lucide="plus"></i>
                    <span><?php _e('Add Section', 'tcl-builder'); ?></span>
                </button>

                <!-- Sections will be rendered here by JavaScript -->

                <!-- Dotted Add Section Button -->
                <button class="add-section-btn dashed">
                    <div class="button-content">
                        <i data-lucide="plus-circle"></i>
                        <span><?php _e('Add New Section', 'tcl-builder'); ?></span>
                    </div>
                </button>
            </div>

            <!-- Add Section Modal -->
            <div class="modal-overlay" data-modal="main" aria-hidden="true">
                <div class="modal" role="dialog" aria-labelledby="modal-title">
                    <div class="modal-header">
                        <h3 id="modal-title" class="modal-title"><?php _e('Add New Section', 'tcl-builder'); ?></h3>
                        <button class="modal-close" aria-label="Close modal">
                            <i data-lucide="x"></i>
                        </button>
                    </div>
                    <div class="modal-content">
                        <div class="section-options">
                            <button class="section-option" data-type="html">
                                <i data-lucide="code-2"></i>
                                <div class="option-details">
                                    <h4><?php _e('HTML Section', 'tcl-builder'); ?></h4>
                                    <p><?php _e('Add custom HTML and CSS content', 'tcl-builder'); ?></p>
                                </div>
                                <i data-lucide="chevron-right" class="arrow-icon"></i>
                            </button>
                            <button class="section-option" data-type="shortcode">
                                <i data-lucide="braces"></i>
                                <div class="option-details">
                                    <h4><?php _e('Shortcode Section', 'tcl-builder'); ?></h4>
                                    <p><?php _e('Insert dynamic shortcode content', 'tcl-builder'); ?></p>
                                </div>
                                <i data-lucide="chevron-right" class="arrow-icon"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- HTML Editor Modal -->
            <div class="modal-overlay editor-modal" data-modal="editor" aria-hidden="true">
                <div class="modal modal-lg" role="dialog" aria-labelledby="editor-modal-title">
                    <div class="modal-header">
                        <h3 id="editor-modal-title" class="modal-title">
                            <i data-lucide="code-2"></i>
                            <?php _e('HTML Editor', 'tcl-builder'); ?>
                        </h3>
                        <button class="modal-close" aria-label="Close modal">
                            <i data-lucide="x"></i>
                        </button>
                    </div>
                    <div class="modal-content">
                        <div class="section-meta">
                            <div class="input-group">
                                <label for="section-title"><?php _e('Section Title', 'tcl-builder'); ?></label>
                                <div class="input-wrapper">
                                    <i data-lucide="type"></i>
                                    <input type="text" 
                                           id="section-title" 
                                           class="title-input" 
                                           placeholder="<?php esc_attr_e('Enter section title...', 'tcl-builder'); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="editor-container">
                            <div class="editor-wrapper">
                                <div class="editor-tabs">
                                    <button type="button" class="tab-btn active" data-tab="html">
                                        <i data-lucide="code"></i>
                                        <?php _e('HTML', 'tcl-builder'); ?>
                                    </button>
                                    <button type="button" class="tab-btn" data-tab="css">
                                        <i data-lucide="palette"></i>
                                        <?php _e('CSS', 'tcl-builder'); ?>
                                    </button>
                                    <button type="button" class="tab-btn" data-tab="js">
                                        <i data-lucide="file-code"></i>
                                        <?php _e('JavaScript', 'tcl-builder'); ?>
                                    </button>
                                </div>

                                <div class="editor-panels">
                                    <div class="editor-panel active" data-panel="html">
                                        <textarea class="code-editor html-editor" 
                                                placeholder="<?php esc_attr_e('Enter HTML code...', 'tcl-builder'); ?>"></textarea>
                                    </div>
                                    <div class="editor-panel" data-panel="css">
                                        <textarea class="code-editor css-editor" 
                                                placeholder="<?php esc_attr_e('Enter CSS code...', 'tcl-builder'); ?>"></textarea>
                                    </div>
                                    <div class="editor-panel" data-panel="js">
                                        <textarea class="code-editor js-editor" 
                                                placeholder="<?php esc_attr_e('Enter JavaScript code...', 'tcl-builder'); ?>"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button class="btn secondary-btn">
                                <i data-lucide="x"></i>
                                <?php _e('Cancel', 'tcl-builder'); ?>
                            </button>
                            <button class="btn primary-btn save-section">
                                <i data-lucide="save"></i>
                                <?php _e('Save Changes', 'tcl-builder'); ?>
                            </button>

                        </div>
                    </div>
                </div>
            </div>

            <!-- Shortcode Editor Modal -->
            <div class="modal-overlay shortcode-modal" data-modal="shortcode" aria-hidden="true">
                <div class="modal modal-lg" role="dialog" aria-labelledby="shortcode-modal-title">
                    <div class="modal-header">
                        <h3 id="shortcode-modal-title" class="modal-title"><?php _e('Shortcode Editor', 'tcl-builder'); ?></h3>
                        <button class="modal-close" aria-label="Close modal">
                            <i data-lucide="x"></i>
                        </button>
                    </div>
                    <div class="modal-content">
                        <div class="section-title-input">
                            <label for="shortcode-section-title" class="input-label"><?php _e('Section Title', 'tcl-builder'); ?></label>
                            <div class="input-wrapper">
                                <i data-lucide="type"></i>
                                <input type="text" id="shortcode-section-title" class="title-input" placeholder="<?php esc_attr_e('Enter section title...', 'tcl-builder'); ?>">
                            </div>
                        </div>

                        <div class="editor-container">
                            <div class="shortcode-editor">
                                <label class="editor-label">
                                    <i data-lucide="braces"></i>
                                    <?php _e('Shortcode', 'tcl-builder'); ?>
                                </label>
                                <input type="text" class="shortcode-input" placeholder="<?php esc_attr_e('Enter shortcode...', 'tcl-builder'); ?>">
                                
                                <div class="shortcode-suggestions">
                                    <h4><?php _e('Common Shortcodes', 'tcl-builder'); ?></h4>
                                    <div class="suggestion-chips">
                                        <?php
                                        $common_shortcodes = apply_filters('tcl_builder_common_shortcodes', array(
                                            '[contact_form]' => __('Contact Form', 'tcl-builder'),
                                            '[gallery]' => __('Gallery', 'tcl-builder'),
                                            '[slider]' => __('Slider', 'tcl-builder'),
                                            '[map]' => __('Map', 'tcl-builder')
                                        ));

                                        foreach ($common_shortcodes as $shortcode => $label) {
                                            printf(
                                                '<button class="chip" data-shortcode="%s">%s</button>',
                                                esc_attr($shortcode),
                                                esc_html($shortcode)
                                            );
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button class="btn secondary-btn"><?php _e('Cancel', 'tcl-builder'); ?></button>
                            <button class="btn primary-btn"><?php _e('Save Changes', 'tcl-builder'); ?></button>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <?php
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Always enqueue shared sections styles for admin
        wp_enqueue_style('tcl-builder-shared-sections', TCL_BUILDER_URI . '/assets/css/shared-sections.css', array(), TCL_BUILDER_VERSION);

        if (!in_array($hook, array('post.php', 'post-new.php'))) {
            return;
        }

        // Enqueue builder styles
        wp_enqueue_style('tcl-builder-variables', TCL_BUILDER_URI . '/assets/css/variables.css', array(), TCL_BUILDER_VERSION);
        wp_enqueue_style('tcl-builder-designations', TCL_BUILDER_URI . '/assets/css/section-designations.css', array('tcl-builder-variables'), TCL_BUILDER_VERSION);
        wp_enqueue_style('tcl-builder-modal', TCL_BUILDER_URI . '/assets/css/modal.css', array('tcl-builder-variables'), TCL_BUILDER_VERSION);
        wp_enqueue_style('tcl-builder-editor', TCL_BUILDER_URI . '/assets/css/editor.css', array('tcl-builder-variables'), TCL_BUILDER_VERSION);
        
        // Enqueue builder scripts and dependencies
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('tcl-builder-core', TCL_BUILDER_URI . '/assets/js/builder/core.js', array('jquery'), TCL_BUILDER_VERSION, true);
        wp_enqueue_script('tcl-builder-utils', TCL_BUILDER_URI . '/assets/js/builder/utils.js', array('tcl-builder-core'), TCL_BUILDER_VERSION, true);
        wp_enqueue_script('tcl-builder-events', TCL_BUILDER_URI . '/assets/js/builder/events.js', array('tcl-builder-core'), TCL_BUILDER_VERSION, true);
        wp_enqueue_script('tcl-builder-modal', TCL_BUILDER_URI . '/assets/js/builder/modal.js', array('tcl-builder-core'), TCL_BUILDER_VERSION, true);
        wp_enqueue_script('tcl-builder-sections', TCL_BUILDER_URI . '/assets/js/builder/sections.js', array('tcl-builder-core', 'jquery-ui-sortable'), TCL_BUILDER_VERSION, true);
        wp_enqueue_script('tcl-builder-wordpress', TCL_BUILDER_URI . '/assets/js/builder/wordpress.js', array('tcl-builder-core'), TCL_BUILDER_VERSION, true);
        wp_enqueue_script('tcl-builder-drag-drop', TCL_BUILDER_URI . '/assets/js/builder/drag-drop.js', array('tcl-builder-core', 'jquery-ui-sortable'), TCL_BUILDER_VERSION, true);
        wp_enqueue_script('tcl-builder-tabs', TCL_BUILDER_URI . '/assets/js/builder/tabs.js', array('jquery', 'tcl-builder-core'), TCL_BUILDER_VERSION, true);

        // Localize script data
        wp_localize_script('tcl-builder-core', 'tclBuilderData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tcl_builder_nonce'),
            'version' => TCL_BUILDER_VERSION,
            'sections' => TCL_Builder_Meta::get_sections(get_the_ID()),
            'strings' => array(
                'confirmDelete' => __('Are you sure you want to delete this section?', 'tcl-builder'),
                'confirmImport' => __('Are you sure you want to import these sections? This will replace any existing sections.', 'tcl-builder'),
                'orderUpdateFailed' => __('Failed to update section order', 'tcl-builder'),
                'networkError' => __('Network error occurred', 'tcl-builder')
            )
        ));

        // Enqueue DID field styles and scripts
        wp_enqueue_style('tcl-builder-did', TCL_BUILDER_URI . '/assets/css/did-field.css', array(), TCL_BUILDER_VERSION);
        wp_enqueue_script('tcl-builder-did', TCL_BUILDER_URI . '/assets/js/builder/did.js', array('jquery', 'wp-i18n'), TCL_BUILDER_VERSION, true);
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style('tcl-builder-frontend', TCL_BUILDER_URI . '/assets/css/frontend.css', array(), TCL_BUILDER_VERSION);
        wp_enqueue_style('tcl-builder-shared-sections', TCL_BUILDER_URI . '/assets/css/shared-sections.css', array(), TCL_BUILDER_VERSION);
    }

    /**
     * Save builder data
     */
    public function save_builder_data($post_id, $post) {
        $start_time = microtime(true);
        $this->logger->log('Attempting to save builder data', 'info', array(
            'post_id' => $post_id,
            'post_type' => get_post_type($post_id),
            'user_id' => get_current_user_id(),
            'memory_usage' => memory_get_usage(true)
        ));
        
        try {
            // Verify nonce and permissions with detailed logging
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'tcl_builder_nonce')) {
                throw new Exception('Security verification failed');
            }

            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                $this->logger->log('Skipping save during autosave', 'debug');
                return;
            }

            if (!current_user_can('edit_post', $post_id)) {
                throw new Exception('User lacks required permissions');
            }

            if (!isset($_POST['sections'])) {
                throw new Exception('No sections data provided');
            }

            // Process and validate sections data
            $raw_sections = stripslashes($_POST['sections']);
            $sections = json_decode($raw_sections, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('JSON parsing error: ' . json_last_error_msg());
            }

            if (!is_array($sections)) {
                throw new Exception('Invalid sections data structure');
            }

            // Validate and sanitize each section
            $sanitized_sections = array();
            foreach ($sections as $index => $section) {
                // Validate required fields
                if (!isset($section['id'], $section['type'], $section['content'])) {
                    $this->logger->log('Invalid section structure', 'warning', array(
                        'section_index' => $index,
                        'missing_fields' => array_diff(['id', 'type', 'content'], array_keys($section))
                    ));
                    continue;
                }

                $sanitized_section = array(
                    'id' => absint($section['id']),
                    'type' => sanitize_text_field($section['type']),
                    'title' => isset($section['title']) ? sanitize_text_field($section['title']) : '',
                    'designation' => isset($section['designation']) ? sanitize_text_field($section['designation']) : 'library',
                );

                // Ensure designation is included in final section data
                if (isset($section['designation'])) {
                    $sanitized_section['designation'] = sanitize_text_field($section['designation']);
                }

                // Process content based on section type
                if ($section['type'] === 'html') {
                    // Validate HTML content structure
                    if (!is_array($section['content'])) {
                        $this->logger->log('Invalid HTML section content', 'warning', array(
                            'section_id' => $section['id']
                        ));
                        continue;
                    }

                    // Validate HTML structure
                    $dom = new DOMDocument();
                    libxml_use_internal_errors(true);
                    $test_html = $section['content']['html'] ?? '';
                    $dom->loadHTML($test_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                    $html_errors = libxml_get_errors();
                    libxml_clear_errors();

                    if (!empty($html_errors)) {
                        $this->logger->log('HTML validation issues', 'warning', array(
                            'section_id' => $section['id'],
                            'errors' => array_map(function($error) {
                                return $error->message;
                            }, $html_errors)
                        ));
                    }

                    // Process HTML content
                    $sanitized_section['content'] = array(
                        'html' => $section['content']['html'] ?? '',
                        'css' => $this->sanitize_css($section['content']['css'] ?? ''),
                        'js' => isset($section['content']['js']) ? $section['content']['js'] : ''
                    );
                } else {
                    // Process shortcode content
                    $sanitized_section['content'] = $section['content'];
                }

                $sanitized_sections[] = $sanitized_section;
            }

            if (empty($sanitized_sections)) {
                throw new Exception('No valid sections after sanitization');
            }

            // Save sanitized sections
            $result = TCL_Builder_Meta::update_sections($post_id, $sanitized_sections);
            
            if (!$result) {
                throw new Exception('Failed to update sections in database');
            }

            $execution_time = microtime(true) - $start_time;
            $this->logger->log('Successfully saved sections', 'info', array(
                'post_id' => $post_id,
                'section_count' => count($sanitized_sections),
                'execution_time' => round($execution_time, 4),
                'memory_peak' => memory_get_peak_usage(true)
            ));

        } catch (Exception $e) {
            $this->logger->log('Error saving sections', 'error', array(
                'post_id' => $post_id,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'execution_time' => microtime(true) - $start_time,
                'memory_peak' => memory_get_peak_usage(true)
            ));

            // Store error in transient for admin notice
            set_transient('tcl_builder_save_error_' . $post_id, array(
                'message' => $e->getMessage(),
                'time' => current_time('mysql')
            ), 45);
        }
    }

    /**
     * Sanitize CSS content
     */
    protected function sanitize_css($css) {
        if (empty($css)) return '';
        return $css; // Return CSS unaltered
    }

    /**
     * Render builder content on frontend
     */
    public function render_builder_content($content) {
        $start_time = microtime(true);
        $memory_start = memory_get_usage(true);

        // Get post type
        $post_type = get_post_type();
        
        // Only modify content for posts/pages/shared sections using the builder
        if (!is_singular(array('post', 'page', 'shared_section'))) {
            return $content;
        }

        // For shared sections, we want to return just the builder content
        if ($post_type === 'shared_section') {
            $content = ''; // Clear any default content
            
            // Add wrapper for shared section
            echo '<div class="tcl-shared-section-wrapper">';
            echo '<div class="tcl-shared-section-container">';
        }

        try {
            $post_id = get_the_ID();
            
            // Use TCL_Builder_Meta to get sections with error handling
            try {
                $sections = TCL_Builder_Meta::get_sections($post_id);
            } catch (Exception $e) {
                $this->logger->log('Failed to retrieve sections', 'error', array(
                    'post_id' => $post_id,
                    'error' => $e->getMessage()
                ));
                return $this->render_error_message($content);
            }
            
            if (empty($sections)) {
                return $content;
            }

            ob_start();
            $rendered_sections = 0;
            $failed_sections = 0;

            echo '<div class="tcl-builder-content" data-post-id="' . esc_attr($post_id) . '">';

            foreach ($sections as $section_index => $section) {
                try {
                    // Validate section structure
                    if (!$this->validate_section_structure($section)) {
                        $failed_sections++;
                        continue;
                    }

                    // Generate unique section identifier with version for cache busting
                    $section_id = 'tcl-section-' . esc_attr($section['id']) . '-v' . TCL_BUILDER_VERSION;
                    
                    echo '<div class="tcl-builder-section" data-section-id="' . esc_attr($section['id']) . '" data-designation="' . esc_attr($section['designation'] ?? 'library') . '">';
                    
                    if ($section['type'] === 'html') {
                        $this->render_html_section($section, $section_id, $post_id);
                    } else {
                        $this->render_shortcode_section($section, $section_id);
                    }
                    
                    echo '</div>';
                    $rendered_sections++;

                } catch (Exception $e) {
                    $failed_sections++;
                    $this->logger->log('Section render failed', 'error', array(
                        'post_id' => $post_id,
                        'section_index' => $section_index,
                        'error' => $e->getMessage()
                    ));
                    
                    // Display fallback content for failed section in development
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        echo '<div class="tcl-builder-section-error">';
                        echo '<p>Error rendering section: ' . esc_html($e->getMessage()) . '</p>';
                        echo '</div>';
                    }
                }
            }

            echo '</div>';

            $rendered_content = ob_get_clean();
            
            // Performance metrics
            $execution_time = microtime(true) - $start_time;
            $memory_usage = memory_get_usage(true) - $memory_start;
            
            $this->logger->log('Content rendering complete', 'info', array(
                'post_id' => $post_id,
                'sections_rendered' => $rendered_sections,
                'sections_failed' => $failed_sections,
                'execution_time' => round($execution_time, 4),
                'memory_usage' => size_format($memory_usage),
                'peak_memory' => size_format(memory_get_peak_usage(true))
            ));

            // Close wrapper for shared section
            if ($post_type === 'shared_section') {
                echo '</div></div>';
            }

            return $rendered_content;

        } catch (Exception $e) {
            $this->logger->log('Critical rendering error', 'error', array(
                'post_id' => get_the_ID(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            return $this->render_error_message($content);
        }
    }

    /**
     * Validate section structure
     */
    protected function validate_section_structure($section) {
        if (!isset($section['type'], $section['content'], $section['id'])) {
            $this->logger->log('Invalid section structure', 'warning', array(
                'section' => $section
            ));
            return false;
        }

        if ($section['type'] === 'html' && (!is_array($section['content']) || 
            !isset($section['content']['html']) || !isset($section['content']['css']))) {
            $this->logger->log('Invalid HTML section content', 'warning', array(
                'section_id' => $section['id']
            ));
            return false;
        }

        if ($section['type'] !== 'html' && !is_string($section['content'])) {
            $this->logger->log('Invalid shortcode content', 'warning', array(
                'section_id' => $section['id']
            ));
            return false;
        }

        return true;
    }

    /**
     * Render HTML section
     */
    protected function render_html_section($section, $section_id, $post_id) {
        try {
            $css = !empty($section['content']['css']) ? $this->sanitize_css($section['content']['css']) : '';
            $html = isset($section['content']['html']) ? $section['content']['html'] : '';
            $js = isset($section['content']['js']) ? $section['content']['js'] : '';

            // Add default styles to prevent unwanted spacing
            $css = ":host { display: block; margin: 0; padding: 0; } :host > div { margin: 0; padding: 0; }\n" . $css;
            
            // Process PHP template tags in the HTML content
            $processed_html = preg_replace_callback(
                '/\<\?php\s+echo\s+([^;]+);\s*\?\>/i',
                function($matches) {
                    $php_code = trim($matches[1]);
                    
                    // Map of common template tags to their functions
                    $template_tags = array(
                        'get_template_directory_uri()' => 'get_template_directory_uri',
                        'get_stylesheet_directory_uri()' => 'get_stylesheet_directory_uri',
                        'get_theme_file_uri()' => 'get_theme_file_uri',
                        'bloginfo(\'url\')' => array('bloginfo', 'url'),
                        'home_url()' => 'home_url'
                    );
                    
                    if (isset($template_tags[$php_code])) {
                        if (is_array($template_tags[$php_code])) {
                            $func = $template_tags[$php_code][0];
                            $param = $template_tags[$php_code][1];
                            return esc_url($func($param));
                        } else {
                            $func = $template_tags[$php_code];
                            return esc_url($func());
                        }
                    }
                    
                    return '';
                },
                $html
            );

            // Create a template for the Shadow DOM content
            echo '<template id="' . esc_attr($section_id) . '-template">';
            
            // Add FontAwesome and jQuery
            echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';
            if (strpos($js, 'jQuery') !== false || strpos($js, '$') !== false) {
                echo '<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>';
            }
            
            // Add wrapper div and custom CSS
            echo '<style>' . $css . '</style>';
            echo '<div class="section-content">';
            
            // Process shortcodes in HTML content
            $processed_html = do_shortcode($processed_html);
            echo apply_filters('tcl_builder_section_html', $processed_html, $section, $post_id);
            echo '</div></template>';

            // Create the host element
            echo '<div id="' . esc_attr($section_id) . '" class="tcl-builder-section-host" style="display: block; margin: 0; padding: 0;"></div>';

            // Initialize Shadow DOM and execute JavaScript
            echo '<script>
                (function() {
                    const host = document.getElementById("' . esc_js($section_id) . '");
                    const template = document.getElementById("' . esc_js($section_id) . '-template");
                    const shadowRoot = host.attachShadow({mode: "open"});
                    shadowRoot.appendChild(template.content.cloneNode(true));

                    // Initialize Lucide icons within shadow DOM
                    if (window.lucide) {
                        window.lucide.createIcons(shadowRoot);
                    }

                    // Execute section JavaScript within shadow DOM context
                    const sectionJS = `' . str_replace('`', '\\`', $js) . '`;
                    if (sectionJS) {
                        try {
                            const scriptEl = document.createElement("script");
                            scriptEl.id = "' . esc_js($section_id) . '-script";
                            scriptEl.textContent = `
                                (function(root, $) {
                                    try {
                                        ${sectionJS}
                                    } catch (error) {
                                        console.error("Error in section script:", error);
                                    }
                                })(document.getElementById("' . esc_js($section_id) . '").shadowRoot, window.jQuery);
                            `;
                            shadowRoot.appendChild(scriptEl);
                        } catch (error) {
                            console.error("Error executing section JavaScript:", error);
                        }
                    }
                })();
            </script>';
            
        } catch (Exception $e) {
            $this->logger->log('Section render failed', 'error', array(
                'section_id' => $section_id,
                'error' => $e->getMessage()
            ));
            echo '<div class="tcl-builder-section-error">';
            echo 'Error processing section content';
            echo '</div>';
        }
    }



    /**
     * Render shortcode section
     */
    protected function render_shortcode_section($section, $section_id) {
        try {
            echo '<div class="' . $section_id . '" data-type="shortcode">';
            echo do_shortcode($section['content']);
            echo '</div>';
        } catch (Exception $e) {
            $this->logger->log('Shortcode processing failed', 'error', array(
                'section_id' => $section_id,
                'error' => $e->getMessage()
            ));
            echo '<div class="' . $section_id . ' tcl-builder-section-error">';
            echo 'Error processing shortcode';
            echo '</div>';
        }
    }

    /**
     * Render error message
     */
    protected function render_error_message($fallback_content) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return '<div class="tcl-builder-error">' .
                   '<p>Error loading builder content. Please try refreshing the page.</p>' .
                   '</div>' . $fallback_content;
        }
        return $fallback_content;
    }
}

// Initialize the builder
TCL_Builder::get_instance();
