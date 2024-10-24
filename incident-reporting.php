<?php
/**
 * Plugin Name: Hidden Leaf Incident Reporting
 * Plugin URI: https://hiddenleaf.org
 * Description: A plugin for reporting and managing security incidents
 * Version: 1.3
 * Author: YolkWorks
 * Author URI: https://yolk.works
 * License: GPL2
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('HLIR_VERSION', '1.0');
define('HLIR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HLIR_PLUGIN_URL', plugin_dir_url(__FILE__));

// Add debugging function
if (!function_exists('hlir_debug_log')) {
    function hlir_debug_log($message) {
        if (WP_DEBUG === true) {
            if (is_array($message) || is_object($message)) {
                error_log(print_r($message, true));
            } else {
                error_log($message);
            }
        }
    }
}

// Include necessary files
require_once HLIR_PLUGIN_DIR . 'includes/class-hlir-db.php';
require_once HLIR_PLUGIN_DIR . 'includes/class-hlir-form.php';
require_once HLIR_PLUGIN_DIR . 'includes/class-hlir-admin.php';
require_once HLIR_PLUGIN_DIR . 'includes/class-hlir-analytics.php';

class Hidden_Leaf_Incident_Reporting {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Register activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Initialize the plugin
        add_action('init', array($this, 'init'));
        
        // Register shortcode
        add_shortcode('incident_report_form', array($this, 'render_form'));
        
        // Add AJAX handlers
        add_action('wp_ajax_hlir_submit_incident', array($this, 'handle_form_submission'));
        add_action('wp_ajax_nopriv_hlir_submit_incident', array($this, 'handle_form_submission'));
        
        // Add attachment handlers
        add_action('admin_post_hlir_download_attachment', array($this, 'handle_attachment_download'));
        // add_action('wp_ajax_hlir_delete_attachment', array($this, 'handle_attachment_deletion'));
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function activate() {
        hlir_debug_log('Plugin activation started');
        
        // Create database tables
        HLIR_DB::create_tables();
        
        // Initialize attachment directory
        $this->initialize_attachment_directory();
        
        // Add capabilities
        $this->add_attachment_capabilities();
        
        // Set default options if they don't exist or are empty
        $existing_settings = get_option('hlir_settings');
        if (empty($existing_settings) || !is_array($existing_settings)) {
            $default_options = array(
                'notification_email' => get_option('admin_email'),
                'form_title' => 'Report a Security Incident',
                'success_message' => 'Thank you for reporting the incident. We will investigate shortly.',
                'max_file_size' => 5, // MB
                'allowed_file_types' => array('jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx')
            );
            update_option('hlir_settings', $default_options);
        }
        
        // Clear the permalinks
        flush_rewrite_rules();
        
        hlir_debug_log('Plugin activation completed');
    }

    public function deactivate() {
        flush_rewrite_rules();
    }

    public function init() {
        if (is_admin()) {
            new HLIR_Admin();
        }
        // Initialize form handler
        HLIR_Form::init();
    }

    public function enqueue_scripts() {
        global $post;
        
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'incident_report_form')) {
            wp_enqueue_script('jquery');
            
            wp_enqueue_script(
                'sweetalert2',
                'https://cdn.jsdelivr.net/npm/sweetalert2@11',
                array(),
                HLIR_VERSION,
                true
            );
            
            wp_enqueue_style(
                'hlir-form-style',
                HLIR_PLUGIN_URL . 'assets/css/form-style.css',
                array(),
                HLIR_VERSION
            );
            
            wp_enqueue_script(
                'hlir-form-script',
                HLIR_PLUGIN_URL . 'assets/js/form-script.js',
                array('jquery', 'sweetalert2'),
                HLIR_VERSION,
                true
            );
            
            wp_localize_script(
                'hlir-form-script',
                'hlir_ajax',
                array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('hlir_incident_report')
                )
            );
        }
    }

    public function render_form($atts) {
        // Check if HLIR_Form class exists
        if (!class_exists('HLIR_Form')) {
            return 'Form handler not found.';
        }

        // Return the form HTML
        return HLIR_Form::render();
    }

    public function handle_form_submission() {
        check_ajax_referer('hlir_incident_report', 'security');
        
        $result = HLIR_Form::process_form();
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
        } else {
            // Get settings with fallback message
            $settings = get_option('hlir_settings', array());
            $success_message = isset($settings['success_message']) && !empty($settings['success_message']) 
                ? $settings['success_message'] 
                : 'Thank you for reporting the incident. We will investigate shortly.';

            wp_send_json_success(array(
                'message' => $success_message,
                'incident_id' => $result
            ));
        }
    }

    /**
     * Initialize attachment directory
     */
    private function initialize_attachment_directory() {
        $uploads_dir = wp_upload_dir();
        $hlir_upload_dir = $uploads_dir['basedir'] . '/hlir-attachments';

        if (!file_exists($hlir_upload_dir)) {
            wp_mkdir_p($hlir_upload_dir);

            // Create .htaccess to protect uploads
            $htaccess_content = "Order Deny,Allow\n";
            $htaccess_content .= "Deny from all\n";
            $htaccess_content .= "<Files ~ \"\\.(jpg|jpeg|png|pdf|doc|docx)$\">\n";
            $htaccess_content .= "    Allow from all\n";
            $htaccess_content .= "</Files>\n";
            
            file_put_contents($hlir_upload_dir . '/.htaccess', $htaccess_content);
            
            // Create index.php to prevent directory listing
            file_put_contents($hlir_upload_dir . '/index.php', '<?php // Silence is golden');
        }
    }

    /**
     * Add attachment management capabilities
     */
    private function add_attachment_capabilities() {
        $role = get_role('administrator');
        $role->add_cap('manage_incident_attachments');
    }

    /**
     * Handle attachment downloads
     */
    public function handle_attachment_download() {
        // Verify user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }

        // Get and validate attachment ID
        $attachment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!$attachment_id) {
            wp_die('Invalid attachment ID');
        }

        // Verify nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'download_attachment_' . $attachment_id)) {
            wp_die('Invalid security token');
        }

        // Get attachment details
        global $wpdb;
        $attachment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hlir_attachments WHERE id = %d",
            $attachment_id
        ));

        if (!$attachment) {
            wp_die('Attachment not found');
        }

        // Get file path
        $uploads_dir = wp_upload_dir();
        $file_path = $uploads_dir['basedir'] . $attachment->file_path;

        // Verify file exists
        if (!file_exists($file_path)) {
            wp_die('File not found');
        }

        // Verify file is within allowed directory
        $allowed_dir = $uploads_dir['basedir'] . '/hlir-attachments';
        if (strpos(realpath($file_path), realpath($allowed_dir)) !== 0) {
            wp_die('Invalid file location');
        }

        // Log the download
        hlir_debug_log(sprintf(
            'Attachment download requested: ID=%d, File=%s, User=%d',
            $attachment_id,
            $attachment->file_name,
            get_current_user_id()
        ));

        // Set headers for download
        header('Content-Type: ' . $attachment->file_type);
        header('Content-Disposition: attachment; filename="' . basename($attachment->file_name) . '"');
        header('Content-Length: ' . $attachment->file_size);
        header('Pragma: public');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Content-Transfer-Encoding: binary');

        // Clear output buffer
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Output file
        readfile($file_path);
        exit;
    }

    /**
     * Handle attachment deletion
     */
    public function handle_attachment_deletion() {
        // Comment out or remove this method
        /*
        // Verify user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
        }

        // Verify nonce
        check_ajax_referer('delete_attachment', 'nonce');

        // Get and validate attachment ID
        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        if (!$attachment_id) {
            wp_send_json_error('Invalid attachment ID');
        }

        // Delete the attachment
        $result = HLIR_DB::delete_attachment($attachment_id);

        if ($result) {
            wp_send_json_success('Attachment deleted successfully');
        } else {
            wp_send_json_error('Failed to delete attachment');
        }
        */
    }
}

// Initialize the plugin
function hlir_init() {
    return Hidden_Leaf_Incident_Reporting::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'hlir_init');

// Function to initialize plugin settings
function hlir_activate() {
    // Default settings
    $default_settings = array(
        'notification_email' => get_option('admin_email'),
        'form_title' => 'Report a Security Incident',
        'success_message' => 'Thank you for reporting the incident. We will investigate shortly.',
        'max_file_size' => 5, // MB
        'allowed_file_types' => array('jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx')
    );

    // Add the option to the database if it doesn't exist
    if (!get_option('hlir_settings')) {
        add_option('hlir_settings', $default_settings);
    }
}

// Register activation hook
register_activation_hook(__FILE__, 'hlir_activate');
