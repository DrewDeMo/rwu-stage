<?php
/**
 * TCL Builder Contact Form Handler
 * 
 * Handles storing and replacing Contact Form 7 shortcodes with [contact_form]
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class TCL_Builder_Contact_Form {
    /**
     * Instance of this class
     */
    private static $instance = null;
    private $logger;
    const META_KEY = '_tcl_builder_contact_form';

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
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Add meta box
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        
        // Save meta box data
        add_action('save_post', array($this, 'save_meta_box'));

        // Register shortcode
        add_shortcode('contact_form', array($this, 'render_shortcode'));

        // AJAX handlers
        add_action('wp_ajax_tcl_builder_save_contact_form', array($this, 'ajax_save'));
    }

    /**
     * Add meta box
     */
    public function add_meta_box() {
        add_meta_box(
            'tcl-builder-contact-form',
            __('Contact Form Shortcode', 'tcl-builder'),
            array($this, 'render_meta_box'),
            array('post', 'page'),
            'side'
        );
    }

    /**
     * Render meta box content
     */
    public function render_meta_box($post) {
        // Add nonce for security
        wp_nonce_field('tcl_builder_contact_form', 'tcl_builder_contact_form_nonce');

        // Get current value
        $value = get_post_meta($post->ID, self::META_KEY, true);
        ?>
        <div class="tcl-builder-contact-form-field">
            <p>
                <label for="contact_form_shortcode"><?php _e('Contact Form 7 Shortcode:', 'tcl-builder'); ?></label>
                <input 
                    type="text" 
                    id="contact_form_shortcode" 
                    name="contact_form_shortcode" 
                    value="<?php echo esc_attr($value); ?>"
                    placeholder="[contact-form-7 id=&quot;123&quot; title=&quot;Contact form 1&quot;]"
                    class="widefat"
                />
            </p>
            <p>
                <button type="button" class="button save-contact-form-btn">
                    <span class="dashicons dashicons-save"></span>
                    <?php _e('Save', 'tcl-builder'); ?>
                </button>
                <button type="button" class="button copy-shortcode-btn" title="<?php esc_attr_e('Copy shortcode', 'tcl-builder'); ?>">
                    <span class="dashicons dashicons-clipboard"></span>
                    <?php _e('Copy [contact_form]', 'tcl-builder'); ?>
                </button>
            </p>
            <p class="description">
                <?php _e('Use [contact_form] anywhere to display this contact form.', 'tcl-builder'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Save meta box data
     */
    public function save_meta_box($post_id) {
        // Security checks
        if (!isset($_POST['tcl_builder_contact_form_nonce'])) {
            return;
        }
        if (!wp_verify_nonce($_POST['tcl_builder_contact_form_nonce'], 'tcl_builder_contact_form')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save the shortcode
        if (isset($_POST['contact_form_shortcode'])) {
            $shortcode = sanitize_text_field($_POST['contact_form_shortcode']);
            update_post_meta($post_id, self::META_KEY, $shortcode);
        }
    }

    /**
     * AJAX save handler
     */
    public function ajax_save() {
        try {
            // Security checks
            if (!check_ajax_referer('tcl_builder_nonce', 'nonce', false)) {
                throw new Exception(__('Invalid security token.', 'tcl-builder'));
            }

            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            if (!$post_id || !current_user_can('edit_post', $post_id)) {
                throw new Exception(__('Invalid post ID or insufficient permissions.', 'tcl-builder'));
            }

            $shortcode = isset($_POST['shortcode']) ? sanitize_text_field($_POST['shortcode']) : '';
            
            // Validate shortcode format
            if (!preg_match('/\[contact-form-7[^\]]+\]/', $shortcode)) {
                throw new Exception(__('Invalid Contact Form 7 shortcode format.', 'tcl-builder'));
            }

            update_post_meta($post_id, self::META_KEY, $shortcode);

            wp_send_json_success(array(
                'message' => __('Contact form shortcode saved successfully.', 'tcl-builder')
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Render the shortcode
     */
    public function render_shortcode($atts) {
        $post_id = get_the_ID();
        $shortcode = get_post_meta($post_id, self::META_KEY, true);

        if (empty($shortcode)) {
            return '';
        }

        // Return the stored Contact Form 7 shortcode
        return do_shortcode($shortcode);
    }
}

// Initialize the handler
TCL_Builder_Contact_Form::get_instance();
