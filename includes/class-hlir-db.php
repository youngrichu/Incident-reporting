<?php
if (!defined('ABSPATH')) {
    exit;
}

class HLIR_DB {
    public static function create_tables() {
        global $wpdb;
        
        hlir_debug_log('Starting database table creation');
        
        $table_name = $wpdb->prefix . 'hlir_incidents';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if (!$table_exists) {
            hlir_debug_log('Table does not exist, creating it now');
            
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                name varchar(100) NOT NULL,
                email varchar(100) NOT NULL,
                incident_type varchar(50) NOT NULL,
                description text NOT NULL,
                date varchar(50) NOT NULL,
                status varchar(20) NOT NULL,
                submitted_at datetime NOT NULL,
                severity varchar(20) NOT NULL,
                PRIMARY KEY  (id)
            ) $charset_collate;";
            
            hlir_debug_log('Creating table with SQL:');
            hlir_debug_log($sql);
            
            // Use direct query instead of dbDelta for more reliable creation
            $result = $wpdb->query($sql);
            
            if ($result === false) {
                hlir_debug_log('Error creating table: ' . $wpdb->last_error);
            } else {
                hlir_debug_log('Table created successfully');
            }
            
            // Verify table was created
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            hlir_debug_log('Table exists after creation: ' . ($table_exists ? 'yes' : 'no'));
        } else {
            hlir_debug_log('Table already exists');
        }
    }

    public static function insert_incident($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hlir_incidents';

        // Verify table exists before insert
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            hlir_debug_log('Table does not exist, attempting to create it');
            self::create_tables();
            
            // Verify again
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
                hlir_debug_log('Failed to create table');
                return false;
            }
        }

        hlir_debug_log('Attempting to insert incident with data:');
        hlir_debug_log($data);

        $result = $wpdb->insert(
            $table_name,
            array(
                'name' => $data['name'],
                'email' => $data['email'],
                'incident_type' => $data['incident_type'],
                'description' => $data['description'],
                'date' => $data['date'],
                'status' => $data['status'],
                'submitted_at' => $data['submitted_at'],
                'severity' => $data['severity']
            ),
            array(
                '%s', // name
                '%s', // email
                '%s', // incident_type
                '%s', // description
                '%s', // date
                '%s', // status
                '%s', // submitted_at
                '%s'  // severity
            )
        );

        if ($result === false) {
            hlir_debug_log('Insert failed. Database error: ' . $wpdb->last_error);
            return false;
        }

        $insert_id = $wpdb->insert_id;
        hlir_debug_log('Insert successful. New incident ID: ' . $insert_id);
        
        return $insert_id;
    }

    public static function get_incidents($args = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hlir_incidents';

        $defaults = array(
            'orderby' => 'submitted_at',
            'order' => 'DESC',
            'limit' => null,
            'offset' => 0
        );

        $args = wp_parse_args($args, $defaults);
        
        // Ensure table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            hlir_debug_log('Table does not exist when trying to get incidents');
            return array();
        }
        
        $query = "SELECT * FROM $table_name";
        
        if (!empty($args['orderby'])) {
            $query .= " ORDER BY " . esc_sql($args['orderby']) . " " . esc_sql($args['order']);
        }
        
        if ($args['limit'] !== null) {
            $query .= $wpdb->prepare(" LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }

        hlir_debug_log('Fetching incidents with query:');
        hlir_debug_log($query);

        $results = $wpdb->get_results($query);
        hlir_debug_log('Found ' . count($results) . ' incidents');
        
        return $results;
    }

    public static function get_incident_by_id($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hlir_incidents';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $id
        ));
    }

    public static function update_incident_status($id, $status) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hlir_incidents';
        
        return $wpdb->update(
            $table_name,
            array('status' => $status),
            array('id' => $id),
            array('%s'),
            array('%d')
        );
    }

    public static function delete_incident($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hlir_incidents';
        
        return $wpdb->delete(
            $table_name,
            array('id' => $id),
            array('%d')
        );
    }

    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'hlir_incidents';
    }
}