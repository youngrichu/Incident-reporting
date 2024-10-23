<?php
/**
 * Plugin Name: Hidden Leaf Incident Reporting
 * Plugin URI: https://hiddenleaf.org
 * Description: A plugin for reporting and managing security incidents
 * Version: 1.0
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

// Include necessary files
require_once HLIR_PLUGIN_DIR . 'includes/class-hlir-form.php';
require_once HLIR_PLUGIN_DIR . 'includes/class-hlir-admin.php';
require_once HLIR_PLUGIN_DIR . 'includes/class-hlir-db.php';
require_once HLIR_PLUGIN_DIR . 'includes/class-hlir-analytics.php';

class Hidden_Leaf_Incident_Reporting {
    public function __construct() {
        // Initialize the plugin
        add_action('init', array($this, 'init'));
        add_shortcode('incident_report_form', array($this, 'render_form'));
        add_action('wp_ajax_nopriv_log_incident', array($this, 'log_incident'));
        add_action('wp_ajax_log_incident', array($this, 'log_incident'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        add_action('wp', function() {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hlir_nonce'])) {
                HLIR_Form::process_form(); // Call the process_form method
            }
            // Render the form regardless of the request method
            echo HLIR_Form::render();
        });
    }

    public function init() {
        // Initialize form handling
        new HLIR_Form();

        // Initialize admin pages
        if (is_admin()) {
            new HLIR_Admin();
            add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        }
    }

    public function activate() {
        HLIR_DB::create_tables();
        $default_options = array(
            'notification_email' => get_option('admin_email'),
            'form_title' => 'Report a Security Incident',
            'success_message' => 'Thank you for reporting the incident. We will investigate shortly.',
        );
        update_option('hlir_settings', $default_options);
    }

    public function deactivate() {
        // Cleanup tasks if needed
    }

    public function render_form() {
        ob_start();
        HLIR_Form::render();
        return ob_get_clean();
    }

    public function log_incident() {
        $this->capture_incident_data();
        wp_send_json_success('Incident logged successfully.');
    }

    private function capture_incident_data() {
        // Logic to capture and log incident data
    }

    public function get_incident_stats() {
        // Logic to gather and return analytics data
    }

    public function enqueue_scripts() {
        wp_enqueue_style('hlir-form-style', HLIR_PLUGIN_URL . 'assets/css/form-style.css');
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), null, true);
    }
}

// Initialize the plugin
new Hidden_Leaf_Incident_Reporting();
