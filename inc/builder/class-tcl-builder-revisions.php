<?php
/**
 * TCL Builder Revisions Handler
 * 
 * Handles revision support for TCL Builder sections
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class TCL_Builder_Revisions {
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
    private function __construct() {
        $this->logger = TCL_Builder_Logger::get_instance();
        $this->init_hooks();
        $this->logger->log('TCL Builder Revisions handler initialized', 'info');
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register meta for revisions
        add_filter('_wp_post_revision_meta_keys', array($this, 'register_revision_meta_keys'));
        
        // Handle saving and restoring revisions
        add_action('tcl_builder_sections_updated', array($this, 'maybe_save_revision'), 10, 2);
        add_action('_wp_put_post_revision', array($this, 'save_revision_meta'));
        add_action('wp_restore_post_revision', array($this, 'restore_revision'), 10, 2);
        
        // Add sections to revision fields
        add_filter('wp_get_revision_ui_diff', array($this, 'revision_ui_diff'), 10, 3);
    }

    /**
     * Register meta keys that should be copied to revisions
     */
    public function register_revision_meta_keys($keys) {
        $keys[] = TCL_Builder_Meta::META_KEY;
        return $keys;
    }

    /**
     * Maybe save a revision when sections are updated
     */
    public function maybe_save_revision($post_id, $sections) {
        try {
            // Skip if this is a revision or autosave
            if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
                return;
            }

            // Skip if we're already in a revision creation process
            if (doing_action('wp_creating_autosave') || doing_action('_wp_put_post_revision')) {
                return;
            }

            // Skip if no meaningful sections
            if (!$this->has_meaningful_content($sections)) {
                return;
            }

            // Get last revision within the last minute to prevent duplicates
            $revisions = wp_get_post_revisions($post_id, array(
                'posts_per_page' => 1,
                'date_query' => array(
                    array(
                        'after' => '1 minute ago'
                    )
                )
            ));
            $last_revision = reset($revisions);
            
            // Skip if we just created a revision
            if ($last_revision) {
                $this->logger->log('Skipping revision - recent revision exists', 'debug', array(
                    'post_id' => $post_id,
                    'last_revision_id' => $last_revision->ID,
                    'last_revision_time' => $last_revision->post_date
                ));
                return;
            }

            // Get sections from last revision
            $revision_sections = $last_revision ? 
                get_post_meta($last_revision->ID, TCL_Builder_Meta::META_KEY, true) : null;

            if ($revision_sections) {
                if (is_string($revision_sections)) {
                    $revision_sections = json_decode($revision_sections, true);
                }

                // Only create revision if content has meaningfully changed
                if (!$this->has_meaningful_changes($sections, $revision_sections)) {
                    $this->logger->log('Skipping revision - no meaningful changes', 'debug', array(
                        'post_id' => $post_id
                    ));
                    return;
                }
            }

            // Create revision with sections data already in place
            add_filter('wp_save_post_revision_post_has_changed', '__return_true');
            $revision_id = wp_save_post_revision($post_id);
            remove_filter('wp_save_post_revision_post_has_changed', '__return_true');
            
            if ($revision_id) {
                $this->logger->log('Created revision for builder sections', 'info', array(
                    'post_id' => $post_id,
                    'revision_id' => $revision_id,
                    'section_count' => count($sections)
                ));
            }

        } catch (Exception $e) {
            $this->logger->log('Error creating revision', 'error', array(
                'post_id' => $post_id,
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * Check if sections contain meaningful content
     */
    private function has_meaningful_content($sections) {
        if (!is_array($sections) || empty($sections)) {
            return false;
        }

        foreach ($sections as $section) {
            if ($section['type'] === 'html') {
                $html = isset($section['content']['html']) ? 
                    (base64_decode($section['content']['html'], true) ? 
                        base64_decode($section['content']['html']) : 
                        $section['content']['html']) : '';
                $css = isset($section['content']['css']) ? 
                    (base64_decode($section['content']['css'], true) ? 
                        base64_decode($section['content']['css']) : 
                        $section['content']['css']) : '';
                
                if (trim($html) !== '' || trim($css) !== '') {
                    return true;
                }
            } else {
                if (trim(strval($section['content'])) !== '') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if content has changed meaningfully
     */
    private function has_meaningful_changes($current_sections, $revision_sections) {
        if (!is_array($current_sections) || !is_array($revision_sections)) {
            return true;
        }

        // Compare section counts
        if (count($current_sections) !== count($revision_sections)) {
            return true;
        }

        // Compare essential content
        $current = $this->get_comparable_content($current_sections);
        $revision = $this->get_comparable_content($revision_sections);

        return $current !== $revision;
    }

    /**
     * Get comparable content string
     */
    private function get_comparable_content($sections) {
        $content = array();
        foreach ($sections as $section) {
            if ($section['type'] === 'html') {
                if (isset($section['content']['html'])) {
                    $html = is_string($section['content']['html']) ? 
                        (base64_decode($section['content']['html'], true) ? 
                            base64_decode($section['content']['html']) : 
                            $section['content']['html']) : '';
                    $content[] = trim($html);
                }
                if (isset($section['content']['css'])) {
                    $css = is_string($section['content']['css']) ? 
                        (base64_decode($section['content']['css'], true) ? 
                            base64_decode($section['content']['css']) : 
                            $section['content']['css']) : '';
                    $content[] = trim($css);
                }
            } else {
                $content[] = trim(strval($section['content']));
            }
        }
        return implode('|', array_filter($content));
    }

    /**
     * Save metadata to revision
     */
    public function save_revision_meta($revision_id) {
        try {
            $parent_id = wp_get_post_parent_id($revision_id);
            if (!$parent_id) {
                return;
            }

            $sections = TCL_Builder_Meta::get_sections($parent_id);
            if (!$this->has_meaningful_content($sections)) {
                return;
            }

            $this->logger->log('Saving sections to revision', 'info', array(
                'revision_id' => $revision_id,
                'parent_id' => $parent_id,
                'section_count' => count($sections)
            ));

            // Delete any existing meta first
            delete_metadata('post', $revision_id, TCL_Builder_Meta::META_KEY);
            
            // Prepare sections for storage
            $sections_for_storage = array_map(function($section) {
                if ($section['type'] === 'html' && isset($section['content'])) {
                    $section['content'] = array(
                        'html' => base64_encode($section['content']['html']),
                        'css' => base64_encode($section['content']['css'])
                    );
                }
                return $section;
            }, $sections);
            
            // Add the new meta with proper encoding
            $encoded_sections = wp_json_encode($sections_for_storage);
            if ($encoded_sections === false) {
                throw new Exception('Failed to encode sections for revision');
            }
            
            add_metadata('post', $revision_id, TCL_Builder_Meta::META_KEY, $encoded_sections);

        } catch (Exception $e) {
            $this->logger->log('Error saving revision meta', 'error', array(
                'revision_id' => $revision_id,
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * Restore metadata from revision
     */
    public function restore_revision($post_id, $revision_id) {
        try {
            $sections = get_metadata('post', $revision_id, TCL_Builder_Meta::META_KEY, true);
            
            if (!empty($sections)) {
                $this->logger->log('Restoring sections from revision', 'info', array(
                    'post_id' => $post_id,
                    'revision_id' => $revision_id
                ));
                
                // Decode JSON if stored as string
                if (is_string($sections)) {
                    $sections = json_decode($sections, true);
                }
                
                if (is_array($sections)) {
                    // Decode base64 content for HTML sections
                    $sections = array_map(function($section) {
                        if ($section['type'] === 'html' && isset($section['content'])) {
                            $section['content'] = array(
                                'html' => base64_decode($section['content']['html']),
                                'css' => base64_decode($section['content']['css'])
                            );
                        }
                        return $section;
                    }, $sections);
                    
                    // Update sections with restored content
                    TCL_Builder_Meta::update_sections($post_id, $sections);
                    
                    // Clear any transient caches
                    wp_cache_delete($post_id, 'post_meta');
                    wp_cache_delete('tcl_builder_sections_' . $post_id, 'tcl_builder');
                }
            }
        } catch (Exception $e) {
            $this->logger->log('Error restoring revision', 'error', array(
                'post_id' => $post_id,
                'revision_id' => $revision_id,
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * Add sections to revision comparison UI
     */
    public function revision_ui_diff($return, $compare_from, $compare_to) {
        $from_sections = get_metadata('post', $compare_from->ID, TCL_Builder_Meta::META_KEY, true);
        $to_sections = get_metadata('post', $compare_to->ID, TCL_Builder_Meta::META_KEY, true);
        
        // Skip if no sections in either revision
        if (empty($from_sections) && empty($to_sections)) {
            return $return;
        }
        
        // Decode sections if they're JSON strings
        if (is_string($from_sections)) {
            $from_sections = json_decode($from_sections, true);
        }
        if (is_string($to_sections)) {
            $to_sections = json_decode($to_sections, true);
        }
        
        // Format sections for diff view
        $from_text = $this->format_sections_for_diff($from_sections);
        $to_text = $this->format_sections_for_diff($to_sections);
        
        // Add to diff array
        if ($from_text !== $to_text) {
            $return[] = array(
                'id' => 'tcl-builder-sections',
                'name' => __('Builder Sections', 'tcl-builder'),
                'diff' => wp_text_diff($from_text, $to_text, array(
                    'show_split_view' => true
                ))
            );
        }
        
        return $return;
    }

    /**
     * Format sections for diff view
     */
    private function format_sections_for_diff($sections) {
        if (empty($sections) || !is_array($sections)) {
            return '';
        }

        $output = '';
        foreach ($sections as $index => $section) {
            try {
                // Section header with metadata
                $output .= sprintf(
                    "Section %d: %s (ID: %s)\n",
                    $index + 1,
                    isset($section['title']) ? $section['title'] : 'Untitled',
                    isset($section['id']) ? $section['id'] : 'N/A'
                );
                
                $output .= sprintf("Type: %s\n", $section['type']);
                
                if ($section['type'] === 'html') {
                    // Handle HTML content
                    if (isset($section['content']['html'])) {
                        $html = $section['content']['html'];
                        // Handle base64 encoded content
                        if (base64_decode($html, true)) {
                            $html = base64_decode($html);
                        }
                        // Clean and format HTML for diff
                        $html = $this->format_html_for_diff($html);
                        $output .= "HTML Content:\n" . $html . "\n";
                    }
                    
                    // Handle CSS content
                    if (isset($section['content']['css'])) {
                        $css = $section['content']['css'];
                        // Handle base64 encoded content
                        if (base64_decode($css, true)) {
                            $css = base64_decode($css);
                        }
                        // Clean and format CSS for diff
                        $css = $this->format_css_for_diff($css);
                        $output .= "CSS Content:\n" . $css . "\n";
                    }
                } else {
                    // Format shortcode content
                    $output .= "Shortcode:\n" . $this->format_shortcode_for_diff($section['content']) . "\n";
                }
                
                $output .= "\n";
                
            } catch (Exception $e) {
                $output .= "Error formatting section {$index}: " . $e->getMessage() . "\n\n";
            }
        }
        
        return $output;
    }

    /**
     * Format HTML for diff view
     */
    private function format_html_for_diff($html) {
        // Remove excess whitespace while preserving structure
        $html = preg_replace('/>\s+</', ">\n<", trim($html));
        $html = preg_replace('/\s+/', ' ', $html);
        // Indent nested elements
        $level = 0;
        $tokens = preg_split('/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        $output = '';
        foreach ($tokens as $token) {
            if (preg_match('/<\//', $token)) {
                $level--;
            }
            $output .= str_repeat('  ', max(0, $level)) . $token . "\n";
            if (preg_match('/<[^\/].*[^\/]>/', $token)) {
                $level++;
            }
        }
        return $output;
    }

    /**
     * Format CSS for diff view
     */
    private function format_css_for_diff($css) {
        // Remove comments and excess whitespace
        $css = preg_replace('!/\*.*?\*/!s', '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        // Format rules
        $css = preg_replace('/\s*{\s*/', " {\n  ", $css);
        $css = preg_replace('/;\s*/', ";\n  ", $css);
        $css = preg_replace('/\s*}\s*/', "\n}\n", $css);
        return $css;
    }

    /**
     * Format shortcode for diff view
     */
    private function format_shortcode_for_diff($shortcode) {
        // Extract shortcode name and attributes
        if (preg_match('/\[(\w+)(.*?)\]/', $shortcode, $matches)) {
            $name = $matches[1];
            $attrs = trim($matches[2]);
            
            // Format attributes one per line
            if ($attrs) {
                $attrs = preg_replace('/(\w+="[^"]*")/', "\n  $1", $attrs);
                return "[$name$attrs\n]";
            }
            
            return "[$name]";
        }
        return $shortcode;
    }
}

// Initialize the revisions handler
TCL_Builder_Revisions::get_instance();
