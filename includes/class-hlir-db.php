<?php
if (!defined('ABSPATH')) {
    exit;
}

class HLIR_DB {
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Incidents Table
        $table_name = $wpdb->prefix . 'hlir_incidents';
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
        
        dbDelta($sql);
        hlir_debug_log('Creating incidents table with SQL: ' . $sql);

        // Activity Log Table
        $activity_table = $wpdb->prefix . 'hlir_activity_log';
        $sql = "CREATE TABLE IF NOT EXISTS $activity_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            incident_id mediumint(9) NOT NULL,
            type varchar(50) NOT NULL,
            description text NOT NULL,
            user_id bigint(20) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY incident_id (incident_id),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        dbDelta($sql);
        hlir_debug_log('Creating activity log table with SQL: ' . $sql);

        // Notes Table
        $notes_table = $wpdb->prefix . 'hlir_notes';
        $sql = "CREATE TABLE IF NOT EXISTS $notes_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            incident_id mediumint(9) NOT NULL,
            user_id bigint(20) NOT NULL,
            content text NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY incident_id (incident_id),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        dbDelta($sql);
        hlir_debug_log('Creating notes table with SQL: ' . $sql);

        // Verify tables were created
        self::verify_tables();
    }

    private static function verify_tables() {
        global $wpdb;
        $tables = array(
            $wpdb->prefix . 'hlir_incidents',
            $wpdb->prefix . 'hlir_activity_log',
            $wpdb->prefix . 'hlir_notes'
        );

        foreach ($tables as $table) {
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
            hlir_debug_log("Table $table exists: " . ($table_exists ? 'yes' : 'no'));
            
            if (!$table_exists) {
                hlir_debug_log("Failed to create table $table. Last error: " . $wpdb->last_error);
            }
        }
    }

    public static function insert_incident($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hlir_incidents';

        // Verify table exists before insert
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            hlir_debug_log('Incidents table does not exist, attempting to create it');
            self::create_tables();
        }

        hlir_debug_log('Attempting to insert incident with data:');
        hlir_debug_log($data);

        $result = $wpdb->insert(
            $table_name,
            $data,
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

        $incident_id = $wpdb->insert_id;
        
        // Log the incident creation
        self::log_activity($incident_id, 'create', 'Incident reported');
        
        hlir_debug_log('Insert successful. New incident ID: ' . $incident_id);
        return $incident_id;
    }

    public static function get_incidents($args = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hlir_incidents';

        $defaults = array(
            'orderby' => 'submitted_at',
            'order' => 'DESC',
            'limit' => null,
            'offset' => 0,
            'search' => '',
            'status' => '',
            'severity' => '',
            'start_date' => '',
            'end_date' => ''
        );

        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $values = array();

        // Add search condition
        if (!empty($args['search'])) {
            $where[] = "(name LIKE %s OR email LIKE %s OR description LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $values[] = $search_term;
            $values[] = $search_term;
            $values[] = $search_term;
        }

        // Add status filter
        if (!empty($args['status'])) {
            $where[] = "status = %s";
            $values[] = $args['status'];
        }

        // Add severity filter
        if (!empty($args['severity'])) {
            $where[] = "severity = %s";
            $values[] = $args['severity'];
        }

        // Add date range filter
        if (!empty($args['start_date'])) {
            $where[] = "submitted_at >= %s";
            $values[] = $args['start_date'] . ' 00:00:00';
        }
        if (!empty($args['end_date'])) {
            $where[] = "submitted_at <= %s";
            $values[] = $args['end_date'] . ' 23:59:59';
        }

        // Build query
        $query = "SELECT * FROM $table_name WHERE " . implode(' AND ', $where);
        
        // Add ordering
        if (!empty($args['orderby'])) {
            $query .= " ORDER BY " . esc_sql($args['orderby']) . " " . esc_sql($args['order']);
        }
        
        // Add limit and offset
        if ($args['limit'] !== null) {
            $query .= $wpdb->prepare(" LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }

        // Prepare the query with all values
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }

        hlir_debug_log('Fetching incidents with query: ' . $query);
        return $wpdb->get_results($query);
    }

    public static function get_incidents_count($args = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hlir_incidents';

        $where = array('1=1');
        $values = array();

        if (!empty($args['search'])) {
            $where[] = "(name LIKE %s OR email LIKE %s OR description LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $values[] = $search_term;
            $values[] = $search_term;
            $values[] = $search_term;
        }

        if (!empty($args['status'])) {
            $where[] = "status = %s";
            $values[] = $args['status'];
        }

        $query = "SELECT COUNT(*) FROM $table_name WHERE " . implode(' AND ', $where);
        
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }

        return $wpdb->get_var($query);
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
        
        $result = $wpdb->update(
            $table_name,
            array('status' => $status),
            array('id' => $id),
            array('%s'),
            array('%d')
        );

        if ($result !== false) {
            self::log_activity($id, 'status_change', sprintf(
                'Status changed to %s',
                ucwords(str_replace('_', ' ', $status))
            ));
        }

        return $result;
    }

    public static function delete_incident($id) {
        global $wpdb;
        $incident_table = $wpdb->prefix . 'hlir_incidents';
        $activity_table = $wpdb->prefix . 'hlir_activity_log';
        $notes_table = $wpdb->prefix . 'hlir_notes';

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Delete related activities
            $wpdb->delete($activity_table, array('incident_id' => $id), array('%d'));
            
            // Delete related notes
            $wpdb->delete($notes_table, array('incident_id' => $id), array('%d'));
            
            // Delete incident
            $result = $wpdb->delete($incident_table, array('id' => $id), array('%d'));

            if ($result === false) {
                throw new Exception('Failed to delete incident');
            }

            $wpdb->query('COMMIT');
            return true;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            hlir_debug_log('Delete failed: ' . $e->getMessage());
            return false;
        }
    }

    public static function log_activity($incident_id, $type, $description) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hlir_activity_log';

        return $wpdb->insert(
            $table_name,
            array(
                'incident_id' => $incident_id,
                'type' => $type,
                'description' => $description,
                'user_id' => get_current_user_id(),
                'created_at' => current_time('mysql')
            ),
            array(
                '%d', // incident_id
                '%s', // type
                '%s', // description
                '%d', // user_id
                '%s'  // created_at
            )
        );
    }

    public static function get_incident_activities($incident_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hlir_activity_log';

        $query = $wpdb->prepare("
            SELECT a.*, u.display_name as user_name
            FROM $table_name a
            LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
            WHERE incident_id = %d
            ORDER BY created_at DESC
        ", $incident_id);

        return $wpdb->get_results($query);
    }

    public static function add_note($incident_id, $content) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hlir_notes';

        $result = $wpdb->insert(
            $table_name,
            array(
                'incident_id' => $incident_id,
                'user_id' => get_current_user_id(),
                'content' => $content,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array(
                '%d', // incident_id
                '%d', // user_id
                '%s', // content
                '%s', // created_at
                '%s'  // updated_at
            )
        );

        if ($result) {
            self::log_activity($incident_id, 'note_added', 'Note added to incident');
        }

        return $result;
    }

    public static function get_incident_notes($incident_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hlir_notes';

        $query = $wpdb->prepare("
            SELECT n.*, u.display_name as user_name
            FROM $table_name n
            LEFT JOIN {$wpdb->users} u ON n.user_id = u.ID
            WHERE incident_id = %d
            ORDER BY created_at DESC
        ", $incident_id);

        return $wpdb->get_results($query);
    }

    public static function update_note($note_id, $content) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hlir_notes';

        return $wpdb->update(
            $table_name,
            array(
                'content' => $content,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $note_id),
            array('%s', '%s'),
            array('%d')
        );
    }

    public static function delete_note($note_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hlir_notes';

        $note = $wpdb->get_row($wpdb->prepare("SELECT incident_id FROM $table_name WHERE id = %d", $note_id));
        
        if ($note) {
            $result = $wpdb->delete($table_name, array('id' => $note_id), array('%d'));
            if ($result) {
                self::log_activity($note->incident_id, 'note_deleted', 'Note deleted from incident');
            }
            return $result;
        }

        return false;
    }
}