<?php
/**
 * TCL Builder Shared Sections Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class TCL_Builder_Shared_Sections extends TCL_Builder {
    private static $instance = null;
    private $post_type = 'shared_section';
    private $base_url = '';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    protected function __construct() {
        parent::__construct();
        $this->base_url = $this->get_base_url();
        $this->init_hooks();
    }

    protected function get_base_url() {
        $base_url = get_theme_mod('tcl_builder_shared_sections_base_url', 'https://tclmore.windowworlddeals.com/');
        return trailingslashit($base_url);
    }

    protected function init_hooks() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'add_rewrite_rules'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('template_redirect', array($this, 'add_cors_headers'), 1);
        
        add_shortcode('shared_section', array($this, 'render_shared_section'));
        add_filter('post_type_link', array($this, 'filter_post_type_link'), 10, 2);
    }

    public function register_rest_routes() {
        register_rest_route('tcl-builder/v1', '/shared-section/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_shared_section_by_id'),
            'args' => array(
                'id' => array(
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ),
            ),
            'permission_callback' => '__return_true'
        ));

        register_rest_route('tcl-builder/v1', '/shared-section/(?P<name>[a-zA-Z0-9-_]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_shared_section_by_name'),
            'args' => array(
                'name' => array(
                    'validate_callback' => function($param) {
                        return is_string($param);
                    }
                ),
            ),
            'permission_callback' => '__return_true'
        ));
    }

    public function get_shared_section_by_id($request) {
        $post = get_post($request['id']);
        if (!$post || $post->post_type !== $this->post_type) {
            return new WP_Error('not_found', 'Shared section not found', array('status' => 404));
        }
        return $this->prepare_shared_section_response($post);
    }

    public function get_shared_section_by_name($request) {
        $post = get_page_by_path($request['name'], OBJECT, $this->post_type);
        if (!$post || $post->post_type !== $this->post_type) {
            return new WP_Error('not_found', 'Shared section not found', array('status' => 404));
        }
        return $this->prepare_shared_section_response($post);
    }

    protected function prepare_shared_section_response($post) {
        $sections = TCL_Builder_Meta::get_sections($post->ID);
        return array(
            'id' => $post->ID,
            'name' => $post->post_name,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'sections' => $sections,
            'modified' => $post->post_modified,
            'status' => $post->post_status
        );
    }

    public function register_post_type() {
        register_post_type($this->post_type, array(
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'shared'),
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => 20,
            'menu_icon' => 'dashicons-share',
            'supports' => array('title', 'editor', 'custom-fields'),
            'show_in_rest' => true
        ));
    }

    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^shared/([^/]+)/?$',
            'index.php?post_type=' . $this->post_type . '&name=$matches[1]',
            'top'
        );
    }

    public function filter_post_type_link($post_link, $post) {
        if ($post->post_type === $this->post_type) {
            return home_url('shared/' . $post->post_name);
        }
        return $post_link;
    }

    public function render_shared_section($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'name' => '',
        ), $atts);

        try {
            static $section_cache = array();
            $cache_key = !empty($atts['id']) ? 'id_' . $atts['id'] : 'name_' . $atts['name'];

            if (isset($section_cache[$cache_key])) {
                $post = $section_cache[$cache_key];
                $sections = isset($post->sections) ? $post->sections : TCL_Builder_Meta::get_sections($post->ID);
            } else {
                $post = null;
                
                if (parse_url(home_url(), PHP_URL_HOST) !== parse_url($this->base_url, PHP_URL_HOST)) {
                    $api_base = rtrim($this->base_url, '/') . '/wp-json/';
                    $url = $api_base . 'tcl-builder/v1/shared-section/' . 
                           (!empty($atts['id']) ? $atts['id'] : urlencode($atts['name']));

                    $response = wp_remote_get($url, array(
                        'timeout' => 30,
                        'headers' => array(
                            'Accept' => 'application/json'
                        )
                    ));
                    
                    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                        return '';
                    }

                    $data = json_decode(wp_remote_retrieve_body($response), true); // Decode as array
                    if (!$data) {
                        return '';
                    }

                    $post = (object) array(
                        'ID' => $data['id'],
                        'post_name' => $data['name'],
                        'post_title' => $data['title'],
                        'post_content' => $data['content'],
                        'post_type' => $this->post_type,
                        'sections' => $data['sections']
                    );
                    
                    $sections = $data['sections'];
                } else {
                    if (!empty($atts['id'])) {
                        $post = get_post($atts['id']);
                    } elseif (!empty($atts['name'])) {
                        $post = get_page_by_path($atts['name'], OBJECT, $this->post_type);
                    }
                    
                    if (!$post || $post->post_type !== $this->post_type) {
                        return '';
                    }
                    
                    $sections = TCL_Builder_Meta::get_sections($post->ID);
                }

                $section_cache[$cache_key] = $post;
            }

            if (empty($sections)) {
                return '';
            }

            ob_start();
            
            $section_id = isset($post->ID) ? $post->ID : (isset($post->id) ? $post->id : '');
            echo '<div class="tcl-shared-section" data-section-id="' . esc_attr($section_id) . '">';
            
            foreach ((array)$sections as $section) {
                echo '<div class="tcl-builder-section">';
                if ($section['type'] === 'html') {
                    $section_id = 'tcl-shared-section-' . $post->ID . '-' . $section['id'];
                    
                    // Render HTML section directly since we inherit from TCL_Builder
                    $this->render_html_section($section, $section_id, $post->ID);
                } else {
                    echo do_shortcode($section['content']);
                }
                echo '</div>';
            }
            
            echo '</div>';

            return ob_get_clean();

        } catch (Exception $e) {
            return '';
        }
    }

    public function add_cors_headers() {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: *');
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            status_header(200);
            exit();
        }
    }

    public function enqueue_assets() {
        if (is_admin()) return;

        global $post;
        $should_enqueue = 
            is_singular($this->post_type) || 
            (is_singular() && has_shortcode($post->post_content, 'shared_section')) ||
            is_archive() || 
            is_home() || 
            is_search();

        if (!$should_enqueue) return;

        $is_base_domain = parse_url(home_url(), PHP_URL_HOST) === parse_url($this->base_url, PHP_URL_HOST);
        $css_url = $is_base_domain ? 
            get_theme_file_uri('assets/css/shared-sections.css') : 
            $this->base_url . 'wp-content/themes/' . get_template() . '/assets/css/shared-sections.css';

        wp_enqueue_style(
            'tcl-builder-shared-sections',
            $css_url,
            array(),
            TCL_BUILDER_VERSION
        );

        add_action('wp_footer', array($this, 'print_shared_sections_script'));
    }

    protected function render_html_section($section, $section_id, $post_id) {
        try {
            $css = !empty($section['content']['css']) ? $this->sanitize_css($section['content']['css']) : '';
            $html = isset($section['content']['html']) ? $section['content']['html'] : '';
            
            // Get theme URLs for asset paths
            $theme_url = esc_url(get_template_directory_uri());
            $stylesheet_url = esc_url(get_stylesheet_directory_uri());
            $home_url = esc_url(home_url());
            
            // Process PHP template tags and handle asset paths
            $processed_html = preg_replace_callback(
                '/\<\?php\s+echo\s+([^;]+);\s*\?\>/i',
                function($matches) use ($theme_url, $stylesheet_url, $home_url) {
                    $php_code = trim($matches[1]);
                    
                    // Map of common template tags to their values
                    $template_tags = array(
                        'get_template_directory_uri()' => $theme_url,
                        'get_stylesheet_directory_uri()' => $stylesheet_url,
                        'get_theme_file_uri()' => $theme_url,
                        'bloginfo(\'url\')' => $home_url,
                        'home_url()' => $home_url
                    );
                    
                    return isset($template_tags[$php_code]) ? $template_tags[$php_code] : '';
                },
                $html
            );
            
            // Fix asset paths to use absolute URLs
            $processed_html = preg_replace(
                array(
                    '/(src|href)=(["\'])\/assets\//i',
                    '/{template_directory_uri}|%template_directory_uri%|{stylesheet_directory_uri}|{theme_file_uri}/i'
                ),
                array(
                    '$1=$2' . $theme_url . '/assets/',
                    $theme_url
                ),
                $processed_html
            );
            
            // Create a unique ID for style scoping
            $style_id = 'tcl-shared-style-' . $section_id;
            
            // Add styles with scoped selectors
            echo '<style id="' . esc_attr($style_id) . '">';
            
            // Add FontAwesome
            echo '@import url("https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css");';
            
            // Add base styles
            echo '
                #' . $section_id . ' {
                    display: block;
                    width: 100%;
                    height: auto;
                    position: relative;
                    color: inherit;
                    font-family: inherit;
                    font-size: inherit;
                    line-height: inherit;
                }
                #' . $section_id . ' * {
                    box-sizing: border-box;
                }
            ';

            // Add custom CSS with scoped selectors
            if ($css) {
                // Process the CSS to scope it to this section
                $scoped_css = preg_replace_callback(
                    '/([^{}]+){([^{}]*)}/',
                    function($matches) use ($section_id) {
                        $selectors = explode(',', $matches[1]);
                        $processed_selectors = array_map(function($selector) use ($section_id) {
                            $selector = trim($selector);
                            // Don't modify @media or @keyframes
                            if (strpos($selector, '@') === 0) {
                                return $selector;
                            }
                            // Scope selector to this section
                            return '#' . $section_id . ' ' . $selector;
                        }, $selectors);
                        return implode(',', $processed_selectors) . '{' . $matches[2] . '}';
                    },
                    $css
                );
                
                echo $scoped_css;
            }
            
            echo '</style>';
            
            // Render content directly with proper wrapper
            echo '<div id="' . esc_attr($section_id) . '" class="tcl-shared-section-content">';
            echo '<div class="tcl-shared-inner">';
            $processed_html = do_shortcode($processed_html);
            echo apply_filters('tcl_builder_section_html', $processed_html, $section, $post_id);
            echo '</div>';
            echo '</div>';
            
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

    public function print_shared_sections_script() {
        // No JavaScript needed anymore since we're not using Shadow DOM
    }
}

TCL_Builder_Shared_Sections::get_instance();
