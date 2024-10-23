<?php
if (!defined('ABSPATH')) {
    exit;
}

// Delete plugin options
delete_option('hlir_settings');

// Drop the incidents table
global $wpdb;
$table_name = $wpdb->prefix . 'hlir_incidents';
$wpdb->query("DROP TABLE IF EXISTS $table_name");
