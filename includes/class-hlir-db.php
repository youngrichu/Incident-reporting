<?php
class HLIR_DB {
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $table_name = $wpdb->prefix . 'hlir_incidents';

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            incident_type varchar(50) NOT NULL,
            description text NOT NULL,
            date date NOT NULL,
            status varchar(20) NOT NULL,
            submitted_at datetime NOT NULL,
            severity varchar(20) NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public static function insert_incident($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hlir_incidents';

        $wpdb->insert($table_name, $data);

        return $wpdb->insert_id;
    }

    public static function get_incidents() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hlir_incidents';

        $incidents = $wpdb->get_results("SELECT * FROM $table_name ORDER BY submitted_at DESC");

        return $incidents;
    }

    public static function get_incident_by_id($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hlir_incidents';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
    }

    public static function update_incident_status($id, $status) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hlir_incidents';
        $wpdb->update($table_name, array('status' => $status), array('id' => $id));
    }
}
