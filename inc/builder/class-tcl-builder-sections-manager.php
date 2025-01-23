<?php
/**
 * TCL Builder Sections Manager
 * Handles section import/export functionality
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class TCL_Builder_Sections_Manager {
    private static $instance = null;
    private $logger;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->logger = TCL_Builder_Logger::get_instance();
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_tcl_builder_import_sections', array($this, 'handle_import_sections'));
    }

    public function handle_import_sections() {
        try {
            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'tcl_builder_sections_manager_nonce')) {
                throw new Exception(__('Security verification failed', 'tcl-builder'));
            }

            // Get post ID and sections data
            $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
            $raw_sections = isset($_POST['sections']) ? stripslashes($_POST['sections']) : '';
            
            if (!$post_id) {
                throw new Exception(__('Invalid post ID', 'tcl-builder'));
            }

            if (empty($raw_sections)) {
                throw new Exception(__('No sections data provided', 'tcl-builder'));
            }

            // Decode and validate sections
            $sections = json_decode($raw_sections, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception(__('Invalid JSON format', 'tcl-builder'));
            }

            if (!self::validate_sections_data($sections)) {
                throw new Exception(__('Invalid sections data structure', 'tcl-builder'));
            }

            // Add shadow DOM version check
            if (isset($sections['shadow_dom_version'])) {
                $this->logger->log('[ShadowDOM] Importing sections with Shadow DOM support', 'info', [
                    'version' => $sections['shadow_dom_version']
                ]);
            }

            // Backup current sections
            $backup_sections = TCL_Builder_Meta::get_sections($post_id);
            
            // Validate post exists and is editable
            $post = get_post($post_id);
            if (!$post || !current_user_can('edit_post', $post_id)) {
                throw new Exception(__('You do not have permission to edit this post', 'tcl-builder'));
            }

            // Update sections
            $result = TCL_Builder_Meta::update_sections($post_id, $sections);

            if (!$result) {
                // Restore backup if update fails
                TCL_Builder_Meta::update_sections($post_id, $backup_sections);
                throw new Exception(__('Failed to update sections', 'tcl-builder'));
            }

            // Log successful import
            $this->logger->log('Sections imported successfully', 'info', [
                'post_id' => $post_id,
                'section_count' => count($sections)
            ]);

            wp_send_json_success([
                'message' => __('Sections imported successfully', 'tcl-builder'),
                'count' => count($sections)
            ]);
        } catch (Exception $e) {
            // Log error
            $this->logger->log('Section import failed', 'error', [
                'post_id' => $post_id ?? 0,
                'error' => $e->getMessage()
            ]);

            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
        }
    }

    public function add_admin_menu() {
        add_management_page(
            __('TCL Builder Sections', 'tcl-builder'),
            __('Builder Sections', 'tcl-builder'),
            'edit_posts',
            'tcl-builder-sections',
            array($this, 'render_admin_page')
        );
    }

    public function enqueue_scripts($hook) {
        if ('tools_page_tcl-builder-sections' !== $hook) {
            return;
        }

        wp_enqueue_style('tcl-builder-admin');
        wp_enqueue_script(
            'tcl-builder-sections-manager',
            get_template_directory_uri() . '/assets/js/builder/sections-manager.js',
            array('jquery'),
            TCL_BUILDER_VERSION,
            true
        );

        wp_localize_script('tcl-builder-sections-manager', 'tclBuilderSectionsManager', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tcl_builder_sections_manager_nonce'),
            'strings' => array(
                'confirmImport' => __('Are you sure you want to import these sections? This will replace any existing sections.', 'tcl-builder'),
                'importSuccess' => __('Sections imported successfully.', 'tcl-builder'),
                'exportSuccess' => __('Sections exported successfully.', 'tcl-builder'),
                'error' => __('An error occurred. Please try again.', 'tcl-builder')
            )
        ));
    }

    public function render_admin_page() {
        // Get all posts with builder sections
        $posts = $this->get_posts_with_sections();
        ?>
        <div class="wrap tcl-builder-sections-manager">
            <h1><?php _e('TCL Builder Sections Manager', 'tcl-builder'); ?></h1>
            
            <div class="notice notice-info">
                <p><?php _e('Here you can manage TCL Builder sections across your site. Use the export feature to backup sections or transfer them between posts.', 'tcl-builder'); ?></p>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Post Title', 'tcl-builder'); ?></th>
                        <th><?php _e('Post Type', 'tcl-builder'); ?></th>
                        <th><?php _e('Sections', 'tcl-builder'); ?></th>
                        <th><?php _e('Last Modified', 'tcl-builder'); ?></th>
                        <th><?php _e('Actions', 'tcl-builder'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($posts)) : ?>
                        <tr>
                            <td colspan="5"><?php _e('No posts found with builder sections.', 'tcl-builder'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($posts as $post) : ?>
                            <tr>
                                <td>
                                    <a href="<?php echo get_edit_post_link($post->ID); ?>">
                                        <?php echo esc_html($post->post_title); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html(get_post_type_object($post->post_type)->labels->singular_name); ?></td>
                                <td><?php echo esc_html($this->get_sections_count($post->ID)); ?></td>
                                <td><?php echo esc_html(get_the_modified_date('', $post->ID)); ?></td>
                                <td>
                                    <button class="button export-sections" data-post-id="<?php echo esc_attr($post->ID); ?>">
                                        <?php _e('Export', 'tcl-builder'); ?>
                                    </button>
                                    <button class="button import-sections" data-post-id="<?php echo esc_attr($post->ID); ?>">
                                        <?php _e('Import', 'tcl-builder'); ?>
                                    </button>
                                    <input type="file" class="sections-import-file" style="display: none;" accept="application/json">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function get_posts_with_sections() {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT DISTINCT p.* 
            FROM {$wpdb->posts} p 
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
            WHERE pm.meta_key = %s 
            AND p.post_status = 'publish' 
            ORDER BY p.post_modified DESC",
            TCL_Builder_Meta::META_KEY
        );

        return $wpdb->get_results($query);
    }

    private function get_sections_count($post_id) {
        $sections = TCL_Builder_Meta::get_sections($post_id);
        return count($sections);
    }

    private function validate_shadow_dom_section($section) {
        if (!isset($section['shadow_context'])) {
            return true; // Legacy section, no validation needed
        }

        if (!is_bool($section['shadow_context'])) {
            return false;
        }

        if ($section['shadow_context'] && $section['type'] === 'html') {
            // Validate shadow DOM specific content
            if (!isset($section['content']['js'])) {
                return true; // JS is optional
            }

            // Basic UTF-8 validation for JS content
            return mb_check_encoding($section['content']['js'], 'UTF-8');
        }

        return true;
    }

    public static function validate_sections_data($sections) {
        if (!is_array($sections)) {
            return false;
        }

        foreach ($sections as $section) {
            if (!isset($section['type']) || !isset($section['content'])) {
                return false;
            }

            // Validate shadow DOM context if present
            if (!self::get_instance()->validate_shadow_dom_section($section)) {
                return false;
            }

            if ($section['type'] === 'html') {
                if (!isset($section['content']['html']) || !isset($section['content']['css'])) {
                    return false;
                }
            } elseif (!is_string($section['content'])) {
                return false;
            }
        }

        return true;
    }
}

// Initialize the sections manager
TCL_Builder_Sections_Manager::get_instance();
