<?php
/**
 * TCL Builder Theme functions and definitions
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define theme constants
define('TCL_BUILDER_DIR', get_template_directory());
define('TCL_BUILDER_URI', get_template_directory_uri());

/**
 * Get dynamic theme version based on file modifications
 */
function tcl_builder_get_version() {
    static $version = null;
    
    if ($version === null) {
        // Get modification times of key files
        $files = array(
            TCL_BUILDER_DIR . '/assets/css/frontend.css',
            TCL_BUILDER_DIR . '/assets/css/admin.css',
            TCL_BUILDER_DIR . '/assets/css/fonts.css',
            TCL_BUILDER_DIR . '/assets/js/builder.js'
        );
        
        // Get the latest modification time
        $latest_mod_time = 0;
        foreach ($files as $file) {
            if (file_exists($file)) {
                $mod_time = filemtime($file);
                if ($mod_time > $latest_mod_time) {
                    $latest_mod_time = $mod_time;
                }
            }
        }
        
        // Create version string: 1.0.{timestamp}
        $version = '1.0.' . $latest_mod_time;
    }
    
    return $version;
}

// Define version constant using dynamic versioning
define('TCL_BUILDER_VERSION', tcl_builder_get_version());

/**
 * Theme Setup
 */
function tcl_builder_setup() {
    // Add default posts and comments RSS feed links to head
    add_theme_support('automatic-feed-links');

    // Let WordPress manage the document title
    add_theme_support('title-tag');

    // Enable support for Post Thumbnails
    add_theme_support('post-thumbnails');

    // Add support for responsive embeds
    add_theme_support('responsive-embeds');

    // Add support for custom logo
    add_theme_support('custom-logo');

    // Add support for full and wide align images
    add_theme_support('align-wide');

    // Register nav menus
    register_nav_menus(array(
        'primary' => esc_html__('Primary Menu', 'tcl-builder'),
    ));
}
add_action('after_setup_theme', 'tcl_builder_setup');

/**
 * Enqueue frontend scripts and styles
 */
function tcl_builder_scripts() {
    // Frontend styles
    wp_enqueue_style(
        'tcl-builder-fonts',
        TCL_BUILDER_URI . '/assets/css/fonts.css',
        array(),
        TCL_BUILDER_VERSION
    );
    
    wp_enqueue_style(
        'tcl-builder-frontend',
        TCL_BUILDER_URI . '/assets/css/frontend.css',
        array('tcl-builder-fonts'),
        TCL_BUILDER_VERSION
    );

    // Enqueue FontAwesome
    wp_enqueue_style(
        'font-awesome',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
        array(),
        '6.4.0'
    );

    // Enqueue Lucide icons with specific version
    wp_enqueue_script(
        'lucide-icons',
        'https://unpkg.com/lucide@0.263.0/dist/umd/lucide.min.js',
        array(),
        '0.263.0',
        true
    );

    // Add inline script to check Lucide initialization
    wp_add_inline_script('lucide-icons', 
        'window.addEventListener("load", function() {
            if (typeof Lucide === "undefined" || !Lucide.createIcons) {
                console.error("Lucide icons failed to load");
            }
        });'
    );
}
add_action('wp_enqueue_scripts', 'tcl_builder_scripts');

/**
 * Enqueue admin scripts and styles
 */
function tcl_builder_admin_scripts() {
    $screen = get_current_screen();
    if (!$screen || !in_array($screen->base, array('post', 'page'))) {
        return;
    }

    // Enqueue jQuery UI and its dependencies in correct order
    wp_enqueue_script('jquery');
    wp_enqueue_script('jquery-ui-core');
    wp_enqueue_script('jquery-ui-widget');
    wp_enqueue_script('jquery-ui-mouse');
    wp_enqueue_script('jquery-ui-draggable');
    wp_enqueue_script('jquery-ui-sortable');

    // Ensure jQuery UI styles are loaded before builder scripts
    wp_enqueue_style(
        'jquery-ui',
        'https://code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css',
        array(),
        '1.13.2'
    );

    // Enqueue Satoshi font
    wp_enqueue_style(
        'satoshi-font',
        'https://api.fontshare.com/v2/css?f[]=satoshi@1,900,700,500,301,701,300,501,401,901,400&display=swap',
        array(),
        '1.0.0' // External resource, keep static version
    );

    // Enqueue Lucide icons with specific version
    wp_enqueue_script(
        'lucide-icons',
        'https://unpkg.com/lucide@0.263.0/dist/umd/lucide.min.js',
        array(),
        '0.263.0',
        true
    );

    // Admin styles
    wp_enqueue_style(
        'tcl-builder-admin',
        TCL_BUILDER_URI . '/assets/css/admin.css',
        array(),
        TCL_BUILDER_VERSION
    );

    // Component styles
    $styles = array('variables', 'base', 'buttons', 'sections', 'modals', 'editors', 'shortcode', 'section-options', 'did-field');
    foreach ($styles as $style) {
        wp_enqueue_style(
            "tcl-builder-{$style}",
            TCL_BUILDER_URI . "/assets/css/{$style}.css",
            array(),
            TCL_BUILDER_VERSION
        );
    }

    // Ensure jQuery UI Touch Punch is loaded for mobile drag-drop
    wp_enqueue_script(
        'jquery-ui-touch-punch',
        'https://cdnjs.cloudflare.com/ajax/libs/jqueryui-touch-punch/0.2.3/jquery.ui.touch-punch.min.js',
        array('jquery-ui-sortable'),
        '0.2.3',
        true
    );

    // Initialize CodeMirror for HTML
    $html_settings = wp_enqueue_code_editor(array(
        'type' => 'text/html',
        'codemirror' => array(
            'mode' => 'htmlmixed',
            'lineNumbers' => true,
            'lineWrapping' => true,
            'styleActiveLine' => true,
            'continueComments' => true,
            'extraKeys' => array(
                'Ctrl-/' => 'toggleComment',
                'Cmd-/' => 'toggleComment'
            ),
            'matchBrackets' => true,
            'matchTags' => true,
            'theme' => 'dracula',
            'indentUnit' => 4,
            'smartIndent' => true,
            'indentWithTabs' => false,
            'viewportMargin' => PHP_INT_MAX,
            'addModeClass' => true,
            'autoCloseBrackets' => true,
            'foldGutter' => true,
            'lint' => true,
            'styleActiveLine' => true,
            'autoRefresh' => true,
            'gutters' => array('CodeMirror-lint-markers', 'CodeMirror-foldgutter'),
            'extraKeys' => array(
                'Ctrl-Q' => 'toggleFold',
                'Ctrl-J' => 'toMatchingTag',
                'Ctrl-Space' => 'autocomplete',
                'Ctrl-/' => 'toggleComment',
                'Cmd-/' => 'toggleComment',
                'Alt-F' => 'findPersistent',
                'Ctrl-F' => 'findPersistent'
            ),
            'addon' => array(
                'fold/foldcode',
                'fold/foldgutter',
                'fold/brace-fold',
                'fold/xml-fold',
                'fold/indent-fold',
                'fold/markdown-fold',
                'fold/comment-fold',
                'mode/overlay',
                'edit/matchbrackets',
                'edit/closebrackets',
                'edit/matchtags',
                'edit/closetag',
                'selection/active-line',
                'search/searchcursor',
                'search/search',
                'search/jump-to-line',
                'search/matchesonscrollbar',
                'scroll/annotatescrollbar',
                'hint/show-hint',
                'hint/xml-hint',
                'hint/html-hint',
                'lint/lint',
                'lint/html-lint',
                'lint/css-lint',
                'lint/javascript-lint',
                'selection/mark-selection',
                'runmode/colorize',
                'edit/continuelist',
                'display/fullscreen',
                'display/placeholder',
                'display/rulers',
                'comment/comment',
                'wrap/hardwrap',
                'format/formatting'
            )
        )
    ));

    // Initialize CodeMirror for CSS
    $css_settings = wp_enqueue_code_editor(array(
        'type' => 'text/css',
        'codemirror' => array(
            'mode' => 'css',
            'lineNumbers' => true,
            'lineWrapping' => true,
            'styleActiveLine' => true,
            'continueComments' => true,
            'autoCloseBrackets' => true,
            'matchBrackets' => true,
            'theme' => 'dracula',
            'indentUnit' => 4,
            'smartIndent' => true,
            'indentWithTabs' => false,
            'viewportMargin' => PHP_INT_MAX,
            'autoRefresh' => true,
            'lint' => true,
            'gutters' => array('CodeMirror-lint-markers', 'CodeMirror-linenumbers'),
            'extraKeys' => array(
                'Ctrl-Space' => 'autocomplete',
                'Ctrl-/' => 'toggleComment',
                'Cmd-/' => 'toggleComment',
                'Alt-F' => 'findPersistent',
                'Ctrl-F' => 'findPersistent',
                'Shift-Tab' => 'indentLess',
                'Tab' => 'indentMore'
            ),
            'addon' => array(
                'edit/closebrackets',
                'edit/matchbrackets',
                'selection/active-line',
                'search/searchcursor',
                'search/search',
                'scroll/annotatescrollbar',
                'scroll/simplescrollbars',
                'lint/lint',
                'lint/css-lint',
                'hint/show-hint',
                'hint/css-hint',
                'selection/mark-selection',
                'fold/foldcode',
                'fold/foldgutter',
                'fold/brace-fold',
                'edit/matchtags'
            )
        )
    ));

    // Combine settings for JavaScript
    $cm_settings['codeEditor'] = array(
        'html' => $html_settings['codemirror'],
        'css' => $css_settings['codemirror']
    );

    // While the Dracula theme is configured in wp_enqueue_code_editor,
    // we still need to explicitly enqueue its CSS file for the theme to work
    wp_enqueue_style(
        'codemirror-dracula',
        TCL_BUILDER_URI . '/assets/css/dracula.css',
        array(),
        TCL_BUILDER_VERSION
    );

    // Main builder script (loads first to define TCLBuilder object)
    wp_enqueue_script(
        'tcl-builder-scripts',
        TCL_BUILDER_URI . '/assets/js/builder.js',
        array('jquery', 'jquery-ui-sortable', 'lucide-icons', 'wp-theme-plugin-editor', 'underscore'),
        TCL_BUILDER_VERSION,
        true
    );

    // Enqueue builder modules in correct order
    $modules = array(
        'events',    // Events system first as other modules may use it
        'utils',     // Utility functions used by other modules
        'core',      // Core functionality
        'drag-drop', // Drag and drop functionality must come before sections
        'sections',  // Section management depends on drag-drop
        'editors',   // Editor initialization
        'modal',     // Modal management
        'wordpress', // WordPress integration
        'did',      // Campaign DID functionality
        'contact-form' // Contact Form functionality
    );

    foreach ($modules as $module) {
        wp_enqueue_script(
            "tcl-builder-{$module}",
            TCL_BUILDER_URI . "/assets/js/builder/{$module}.js",
            array('jquery', 'jquery-ui-sortable', 'lucide-icons', 'wp-theme-plugin-editor', 'underscore', 'tcl-builder-scripts'),
            TCL_BUILDER_VERSION,
            true
        );
    }

    // Pass CodeMirror settings to JavaScript
    wp_localize_script('tcl-builder-scripts', 'tclBuilderCodeMirror', $cm_settings);

    // Localize script with sections data
    $post_id = get_the_ID();
    // Get sections using the meta handler to properly decode the metadata wrapper
    $sections = TCL_Builder_Meta::get_sections($post_id);
    
    wp_localize_script('tcl-builder-scripts', 'tclBuilderData', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('tcl_builder_nonce'),
        'sections' => $sections, // Pass array directly, wp_localize_script will handle JSON encoding
        'templateUri' => get_template_directory_uri(),
        'version' => TCL_BUILDER_VERSION,
        'campaignDid' => get_post_meta($post_id, TCL_Builder_DID::META_KEY, true),
        'contactForm' => get_post_meta($post_id, TCL_Builder_Contact_Form::META_KEY, true)
    ));
}
add_action('admin_enqueue_scripts', 'tcl_builder_admin_scripts');

/**
 * Disable Gutenberg
 */
function tcl_builder_disable_gutenberg() {
    // Disable Gutenberg on all post types
    add_filter('use_block_editor_for_post_type', '__return_false', 100);
    
    // Remove Gutenberg styles
    add_action('wp_enqueue_scripts', function() {
        wp_dequeue_style('wp-block-library');
        wp_dequeue_style('wp-block-library-theme');
        wp_dequeue_style('wc-block-style');
    }, 100);
}
add_action('init', 'tcl_builder_disable_gutenberg');

/**
 * Include required files
 */
require_once TCL_BUILDER_DIR . '/inc/class-tcl-builder-logger.php';
require_once TCL_BUILDER_DIR . '/inc/builder/class-tcl-builder.php';
require_once TCL_BUILDER_DIR . '/inc/builder/class-tcl-builder-ajax.php';
require_once TCL_BUILDER_DIR . '/inc/builder/class-tcl-builder-meta.php';
require_once TCL_BUILDER_DIR . '/inc/builder/class-tcl-builder-did.php';
require_once TCL_BUILDER_DIR . '/inc/builder/class-tcl-builder-contact-form.php';
require_once TCL_BUILDER_DIR . '/inc/builder/class-tcl-builder-shared-sections.php';

// Initialize the builder and shared sections
TCL_Builder::get_instance();
TCL_Builder_Shared_Sections::get_instance();

/**
 * Flush rewrite rules on theme activation
 */
function tcl_builder_activate() {
    flush_rewrite_rules();
}
add_action('after_switch_theme', 'tcl_builder_activate');
