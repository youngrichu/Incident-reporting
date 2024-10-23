<?php
/**
 * Plugin Name: Hidden Leaf Incident Reporting
 * Plugin URI: https://hiddenleaf.org
 * Description: A plugin for reporting and managing security incidents
 * Version: 1.1
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
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function activate() {
        hlir_debug_log('Plugin activation started');
        
        // Create database tables
        HLIR_DB::create_tables();
        
        // Set default options if they don't exist
        if (!get_option('hlir_settings')) {
            $default_options = array(
                'notification_email' => get_option('admin_email'),
                'form_title' => 'Report a Security Incident',
                'success_message' => 'Thank you for reporting the incident. We will investigate shortly.',
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
        ob_start();
        HLIR_Form::render();
        return ob_get_clean();
    }

    public function handle_form_submission() {
        check_ajax_referer('hlir_incident_report', 'security');
        
        $result = HLIR_Form::process_form();
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
        } else {
            wp_send_json_success(array(
                'message' => get_option('hlir_settings')['success_message'],
                'incident_id' => $result
            ));
        }
    }
}

// Initialize the plugin
function hlir_init() {
    return Hidden_Leaf_Incident_Reporting::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'hlir_init');