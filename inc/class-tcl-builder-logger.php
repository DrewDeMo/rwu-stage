<?php
/**
 * TCL Builder Logger
 * 
 * Handles logging functionality for the TCL Builder plugin
 */

defined('ABSPATH') || exit;

class TCL_Builder_Logger {
    private static $instance = null;
    private $log_directory;
    private $log_file;
    private $max_file_size = 5242880; // 5MB
    
    private function __construct() {
        $upload_dir = wp_upload_dir();
        $this->log_directory = $upload_dir['basedir'] . '/tcl-builder-logs';
        $this->log_file = $this->log_directory . '/debug.log';
        
        $this->ensure_log_directory();
        $this->write_initial_log();
        
        // Register AJAX handlers
        add_action('wp_ajax_tcl_builder_get_logs', array($this, 'ajax_get_logs'));
        add_action('wp_ajax_tcl_builder_clear_logs', array($this, 'ajax_clear_logs'));
    }

    /**
     * Get log directory path
     */
    public function get_log_file_path() {
        return $this->log_file;
    }

    /**
     * Ensure log directory exists with proper permissions
     */
    private function ensure_log_directory() {
        // Create log directory if it doesn't exist
        if (!file_exists($this->log_directory)) {
            wp_mkdir_p($this->log_directory);
            // Set directory permissions to 755
            chmod($this->log_directory, 0755);
            
            // Create .htaccess to prevent direct access
            $htaccess_content = "Order Deny,Allow\nDeny from all";
            file_put_contents($this->log_directory . '/.htaccess', $htaccess_content);
        }
        
        // Create log file if it doesn't exist
        if (!file_exists($this->log_file)) {
            touch($this->log_file);
            chmod($this->log_file, 0644);
        }
    }

    /**
     * Write initial log entry
     */
    private function write_initial_log() {
        $timestamp = current_time('mysql');
        $initial_log = sprintf("[%s] INFO: TCL Builder logging initialized\n", $timestamp);
        error_log($initial_log, 3, $this->log_file);
        
        // Add a test log entry
        $this->log('Test log entry - if you see this, logging is working!', 'info', array(
            'log_directory' => $this->log_directory,
            'log_file' => $this->log_file
        ));
    }
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Log a message with severity level
     */
    public function log($message, $level = 'info', $context = array()) {
        if (!is_string($message)) {
            $message = print_r($message, true);
        }
        
        $timestamp = current_time('mysql');
        $context_str = !empty($context) ? ' ' . json_encode($context) : '';
        $log_entry = sprintf("[%s] %s: %s%s\n", $timestamp, strtoupper($level), $message, $context_str);
        
        // Rotate log if too large
        if (file_exists($this->log_file) && filesize($this->log_file) > $this->max_file_size) {
            $this->rotate_logs();
        }
        
        error_log($log_entry, 3, $this->log_file);
    }
    
    /**
     * Rotate log files
     */
    private function rotate_logs() {
        $backup_file = $this->log_directory . '/debug.' . date('Y-m-d-H-i-s') . '.log';
        rename($this->log_file, $backup_file);
        touch($this->log_file);
        chmod($this->log_file, 0644);
    }
    
    /**
     * Get log contents
     */
    public function get_logs($lines = 100) {
        if (!file_exists($this->log_file)) {
            return array();
        }
        
        $logs = array_filter(array_map('trim', file($this->log_file)));
        $logs = array_reverse($logs); // Most recent first
        return array_slice($logs, 0, $lines);
    }
    
    /**
     * Clear all logs
     */
    public function clear_logs() {
        if (file_exists($this->log_file)) {
            file_put_contents($this->log_file, '');
            return true;
        }
        return false;
    }
    
    /**
     * AJAX handler for getting logs
     */
    public function ajax_get_logs() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        check_ajax_referer('tcl_builder_debug', 'nonce');
        
        $logs = $this->get_logs();
        wp_send_json_success($logs);
    }
    
    /**
     * AJAX handler for clearing logs
     */
    public function ajax_clear_logs() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        check_ajax_referer('tcl_builder_debug', 'nonce');
        
        $success = $this->clear_logs();
        if ($success) {
            wp_send_json_success('Logs cleared');
        } else {
            wp_send_json_error('Failed to clear logs');
        }
    }
    
    /**
     * Add debug panel to admin footer
     */
    public function add_debug_panel() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        ?>
        <div id="tcl-builder-debug-panel" class="tcl-builder-debug-panel">
            <div class="debug-panel-header">
                <h3>TCL Builder Debug Log</h3>
                <div class="debug-panel-actions">
                    <button class="button" id="tcl-builder-refresh-logs">Refresh</button>
                    <button class="button" id="tcl-builder-clear-logs">Clear Logs</button>
                    <button class="button" id="tcl-builder-toggle-panel">Toggle Panel</button>
                </div>
            </div>
            <div class="debug-panel-content">
                <pre id="tcl-builder-log-content"></pre>
            </div>
        </div>
        <?php
        
        // Add styles
        add_action('admin_footer', array($this, 'add_debug_panel_styles'));
        // Add scripts
        add_action('admin_footer', array($this, 'add_debug_panel_scripts'));
    }
    
    /**
     * Add debug panel styles
     */
    public function add_debug_panel_styles() {
        ?>
        <style>
            .tcl-builder-debug-panel {
                position: fixed;
                bottom: 0;
                right: 0;
                width: 400px;
                background: #fff;
                border: 1px solid #ccc;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
                z-index: 999999;
                display: none;
            }
            
            .debug-panel-header {
                padding: 10px;
                border-bottom: 1px solid #ccc;
                background: #f5f5f5;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .debug-panel-header h3 {
                margin: 0;
                font-size: 14px;
            }
            
            .debug-panel-actions {
                display: flex;
                gap: 5px;
            }
            
            .debug-panel-content {
                height: 300px;
                overflow-y: auto;
                padding: 10px;
                background: #f9f9f9;
            }
            
            #tcl-builder-log-content {
                margin: 0;
                font-family: monospace;
                font-size: 12px;
                white-space: pre-wrap;
            }
        </style>
        <?php
    }
    
    /**
     * Add debug panel scripts
     */
    public function add_debug_panel_scripts() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            const panel = $('#tcl-builder-debug-panel');
            const content = $('#tcl-builder-log-content');
            
            // Show panel initially
            panel.show();
            
            // Load initial logs
            refreshLogs();
            
            // Refresh logs
            $('#tcl-builder-refresh-logs').on('click', refreshLogs);
            
            // Clear logs
            $('#tcl-builder-clear-logs').on('click', clearLogs);
            
            // Toggle panel
            $('#tcl-builder-toggle-panel').on('click', function() {
                panel.find('.debug-panel-content').toggle();
            });
            
            function refreshLogs() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'tcl_builder_get_logs',
                        nonce: '<?php echo wp_create_nonce("tcl_builder_debug"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            content.html(response.data.join('\n'));
                        }
                    }
                });
            }
            
            function clearLogs() {
                if (!confirm('Are you sure you want to clear all logs?')) {
                    return;
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'tcl_builder_clear_logs',
                        nonce: '<?php echo wp_create_nonce("tcl_builder_debug"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            content.html('');
                        }
                    }
                });
            }
        });
        </script>
        <?php
    }
}

// Initialize logger
TCL_Builder_Logger::get_instance();
