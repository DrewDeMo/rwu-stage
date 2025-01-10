<?php
/**
 * TCL Builder DID Handler
 * 
 * Handles the Campaign DID functionality for the TCL Builder
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class TCL_Builder_DID {
    /**
     * Instance of this class
     */
    private static $instance = null;
    private $logger;
    const META_KEY = '_tcl_builder_campaign_did';

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
        $this->logger->log('TCL Builder DID handler initialized', 'info');
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register meta
        add_action('init', array($this, 'register_meta'));

        // Add DID meta box
        add_action('add_meta_boxes', array($this, 'add_did_meta_box'));
        
        // Ensure DID field is initialized after DOM is ready
        add_action('admin_footer', array($this, 'init_did_field'));

        // AJAX handlers
        add_action('wp_ajax_tcl_builder_save_did', array($this, 'save_did'));
        add_action('wp_ajax_tcl_builder_get_did', array($this, 'get_did'));

        // Register shortcode
        add_shortcode('campaign_did', array($this, 'render_did_shortcode'));

        // Add DID to REST API
        add_action('rest_api_init', array($this, 'register_rest_field'));

        // Add DID to common shortcodes list
        add_filter('tcl_builder_common_shortcodes', array($this, 'add_did_shortcode'));
    }

    /**
     * Register meta field
     */
    public function register_meta() {
        register_post_meta('', self::META_KEY, array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'auth_callback' => function($allowed, $meta_key, $post_id) {
                return current_user_can('edit_post', $post_id);
            },
            'sanitize_callback' => array($this, 'sanitize_did')
        ));
    }

    /**
     * Add DID meta box
     */
    public function add_did_meta_box() {
        add_meta_box(
            'tcl-builder-did',
            __('Campaign DID', 'tcl-builder'),
            array($this, 'render_did_meta_box'),
            array('post', 'page', 'shared_section'),
            'side',
            'default'
        );
    }

    /**
     * Render DID meta box content
     */
    public function render_did_meta_box($post) {
        $did = get_post_meta($post->ID, self::META_KEY, true);
        wp_nonce_field('tcl_builder_did_nonce', 'tcl_builder_did_nonce');
        ?>
        <div class="tcl-builder-did-meta-box">
            <p>
                <label for="campaign_did">
                    <?php _e('Phone Number:', 'tcl-builder'); ?>
                </label>
                <input 
                    type="tel" 
                    id="campaign_did" 
                    name="campaign_did" 
                    value="<?php echo esc_attr($did); ?>"
                    placeholder="<?php esc_attr_e('Enter 10-digit phone number...', 'tcl-builder'); ?>"
                    pattern="[0-9]{3}-[0-9]{3}-[0-9]{4}"
                    class="widefat"
                >
            </p>
            <p>
                <button type="button" class="button save-did-btn">
                    <i data-lucide="save"></i>
                    <?php _e('Save DID', 'tcl-builder'); ?>
                </button>
            </p>
            <p class="description">
                <?php _e('Use the shortcode:', 'tcl-builder'); ?>
                <code>[campaign_did]</code>
                <button type="button" class="button-link copy-shortcode-btn" title="<?php esc_attr_e('Copy shortcode', 'tcl-builder'); ?>">
                    <i data-lucide="copy"></i>
                </button>
            </p>
        </div>
        <?php
    }

    /**
     * Save DID via AJAX
     */
    public function save_did() {
        try {
            // Verify nonce
            if (!check_ajax_referer('tcl_builder_nonce', 'nonce', false)) {
                throw new Exception(__('Invalid security token.', 'tcl-builder'));
            }

            // Get and validate post ID
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            if (!$post_id || !current_user_can('edit_post', $post_id)) {
                throw new Exception(__('Invalid post ID or insufficient permissions.', 'tcl-builder'));
            }

            // Get and sanitize DID
            $did = isset($_POST['did']) ? $this->sanitize_did($_POST['did']) : '';
            
            // Update post meta with error logging
            $result = update_post_meta($post_id, self::META_KEY, $did);
            
            if ($result === false) {
                $this->logger->log('Failed to save DID', 'error', array(
                    'post_id' => $post_id,
                    'did' => $did,
                    'user_id' => get_current_user_id()
                ));
                throw new Exception(__('Failed to save DID. Please try again.', 'tcl-builder'));
            }

            $this->logger->log('DID saved successfully', 'info', array(
                'post_id' => $post_id,
                'did' => $did
            ));

            wp_send_json_success(array(
                'message' => __('DID saved successfully.', 'tcl-builder'),
                'did' => $did
            ));

        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Get DID via AJAX
     */
    public function get_did() {
        try {
            // Verify nonce
            if (!check_ajax_referer('tcl_builder_nonce', 'nonce', false)) {
                throw new Exception(__('Invalid security token.', 'tcl-builder'));
            }

            // Get and validate post ID
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            if (!$post_id) {
                throw new Exception(__('Invalid post ID.', 'tcl-builder'));
            }

            // Get DID
            $did = get_post_meta($post_id, self::META_KEY, true);

            wp_send_json_success(array('did' => $did));

        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Render DID shortcode
     */
    public function render_did_shortcode($atts) {
        $post_id = get_the_ID();
        $did = get_post_meta($post_id, self::META_KEY, true);

        if (empty($did)) {
            return '';
        }

        // Format number as XXX-XXX-XXXX
        $clean_number = preg_replace('/[^0-9]/', '', $did);
        if (strlen($clean_number) === 10) {
            return sprintf(
                '%s-%s-%s',
                substr($clean_number, 0, 3),
                substr($clean_number, 3, 3),
                substr($clean_number, 6)
            );
        }

        // If not a 10-digit number, return as-is
        return esc_html($did);
    }

    /**
     * Register REST API field
     */
    public function register_rest_field() {
        register_rest_field(
            array('post', 'page'),
            'campaign_did',
            array(
                'get_callback' => array($this, 'get_did_rest'),
                'update_callback' => array($this, 'update_did_rest'),
                'schema' => array(
                    'description' => __('Campaign DID for the page', 'tcl-builder'),
                    'type' => 'string',
                    'context' => array('view', 'edit')
                )
            )
        );
    }

    /**
     * Get DID for REST API
     */
    public function get_did_rest($post) {
        return get_post_meta($post['id'], self::META_KEY, true);
    }

    /**
     * Update DID for REST API
     */
    public function update_did_rest($value, $post) {
        if (!current_user_can('edit_post', $post->ID)) {
            return new WP_Error(
                'rest_cannot_update',
                __('Sorry, you are not allowed to update this post.', 'tcl-builder'),
                array('status' => rest_authorization_required_code())
            );
        }

        $did = $this->sanitize_did($value);
        return update_post_meta($post->ID, self::META_KEY, $did);
    }

    /**
     * Sanitize DID
     */
    public function sanitize_did($did) {
        // Remove any characters that aren't numbers or hyphens
        $did = preg_replace('/[^0-9\-]/', '', $did);
        
        // Clean number and format as XXX-XXX-XXXX
        $clean_number = preg_replace('/[^0-9]/', '', $did);
        if (strlen($clean_number) === 10) {
            return sprintf(
                '%s-%s-%s',
                substr($clean_number, 0, 3),
                substr($clean_number, 3, 3),
                substr($clean_number, 6)
            );
        }
        
        return trim($did);
    }

    /**
     * Add Campaign DID shortcode to common shortcodes list
     */
    public function add_did_shortcode($shortcodes) {
        return array_merge($shortcodes, array(
            '[campaign_did]' => __('Campaign DID (XXX-XXX-XXXX)', 'tcl-builder')
        ));
    }
    /**
     * Initialize DID field functionality
     */
    public function init_did_field() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            if (typeof TCLBuilder !== 'undefined' && TCLBuilder.DID) {
                TCLBuilder.DID.init();
            } else {
                console.error('TCLBuilder.DID module not found');
            }
        });
        </script>
        <?php
    }
}

// Initialize the DID handler
TCL_Builder_DID::get_instance();
