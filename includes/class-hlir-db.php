<?php
if (!defined('ABSPATH')) {
    exit;
}

class HLIR_DB {
    // Database table creation
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
            actions_taken text DEFAULT NULL,
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

        // Attachments Table
        $attachments_table = $wpdb->prefix . 'hlir_attachments';
        $sql = "CREATE TABLE IF NOT EXISTS $attachments_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            incident_id mediumint(9) NOT NULL,
            file_name varchar(255) NOT NULL,
            file_path varchar(255) NOT NULL,
            file_type varchar(100) NOT NULL,
            file_size int(11) NOT NULL,
            uploaded_at datetime NOT NULL,
            uploaded_by bigint(20) NOT NULL,
            PRIMARY KEY  (id),
            KEY incident_id (incident_id)
        ) $charset_collate;";
        
        dbDelta($sql);

        // Verify tables were created
        self::verify_tables();
    }

    private static function verify_tables() {
        global $wpdb;
        $tables = array(
            $wpdb->prefix . 'hlir_incidents',
            $wpdb->prefix . 'hlir_activity_log',
            $wpdb->prefix . 'hlir_notes',
            $wpdb->prefix . 'hlir_attachments'
        );

        foreach ($tables as $table) {
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
            hlir_debug_log("Table $table exists: " . ($table_exists ? 'yes' : 'no'));
            
            if (!$table_exists) {
                hlir_debug_log("Failed to create table $table. Last error: " . $wpdb->last_error);
            }
        }
    }

    // Incident Management Methods
    public static function insert_incident($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hlir_incidents';

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
                '%s', // actions_taken
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
        self::log_activity($incident_id, 'create', 'Incident reported');
        
        return $incident_id;
    }

    public static function get_incident_by_id($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hlir_incidents';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $id
        ));
    }

    public static function update_incident($id, $data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hlir_incidents';
        
        $result = $wpdb->update(
            $table_name,
            $data,
            array('id' => $id),
            array(
                '%s', // Format for each field in $data
            ),
            array('%d') // Format for ID
        );

        if ($result !== false) {
            self::log_activity($id, 'update', 'Incident updated');
        }

        return $result;
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
        $attachments_table = $wpdb->prefix . 'hlir_attachments';

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Get and delete attachments first
            $attachments = self::get_incident_attachments($id);
            foreach ($attachments as $attachment) {
                self::delete_attachment($attachment->id);
            }

            // Delete related records
            $wpdb->delete($activity_table, array('incident_id' => $id), array('%d'));
            $wpdb->delete($notes_table, array('incident_id' => $id), array('%d'));
            $wpdb->delete($attachments_table, array('incident_id' => $id), array('%d'));
            
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

    // Attachment Management Methods
    public static function add_attachment($incident_id, $file_data) {
        global $wpdb;
        $uploads_dir = wp_upload_dir();
        $hlir_upload_dir = $uploads_dir['basedir'] . '/hlir-attachments';
        
        // Create upload directory if it doesn't exist
        if (!file_exists($hlir_upload_dir)) {
            wp_mkdir_p($hlir_upload_dir);
            // Create .htaccess to protect uploads
            file_put_contents($hlir_upload_dir . '/.htaccess', 'deny from all');
        }

        $file_name = sanitize_file_name($file_data['name']);
        $file_path = $hlir_upload_dir . '/' . time() . '_' . $file_name;

        if (move_uploaded_file($file_data['tmp_name'], $file_path)) {
            return $wpdb->insert(
                $wpdb->prefix . 'hlir_attachments',
                array(
                    'incident_id' => $incident_id,
                    'file_name' => $file_name,
                    'file_path' => str_replace($uploads_dir['basedir'], '', $file_path),
                    'file_type' => $file_data['type'],
                    'file_size' => $file_data['size'],
                    'uploaded_at' => current_time('mysql'),
                    'uploaded_by' => get_current_user_id()
                ),
                array('%d', '%s', '%s', '%s', '%d', '%s', '%d')
            );
        }
        return false;
    }

    public static function get_incident_attachments($incident_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hlir_attachments WHERE incident_id = %d ORDER BY uploaded_at DESC",
            $incident_id
        ));
    }

    public static function delete_attachment($attachment_id) {
        global $wpdb;
        $attachment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hlir_attachments WHERE id = %d",
            $attachment_id
        ));

        if ($attachment) {
            $uploads_dir = wp_upload_dir();
            $file_path = $uploads_dir['basedir'] . $attachment->file_path;
            
            if (file_exists($file_path)) {
                unlink($file_path);
            }

            return $wpdb->delete(
                $wpdb->prefix . 'hlir_attachments',
                array('id' => $attachment_id),
                array('%d')
            );
        }
        return false;
    }

    // Activity Log Methods
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

        return $wpdb->get_results($wpdb->prepare("
            SELECT a.*, u.display_name as user_name
            FROM $table_name a
            LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
            WHERE incident_id = %d
            ORDER BY created_at DESC
        ", $incident_id));
    }

    // Notes Management Methods
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

        return $wpdb->get_results($wpdb->prepare("
            SELECT n.*, u.display_name as user_name
            FROM $table_name n
            LEFT JOIN {$wpdb->users} u ON n.user_id = u.ID
            WHERE incident_id = %d
            ORDER BY created_at DESC
        ", $incident_id));
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

        $note = $wpdb->get_row($wpdb->prepare(
            "SELECT incident_id FROM $table_name WHERE id = %d", 
            $note_id
        ));
        
        if ($note) {
            $result = $wpdb->delete($table_name, array('id' => $note_id), array('%d'));
            if ($result) {
                self::log_activity($note->incident_id, 'note_deleted', 'Note deleted from incident');
            }
            return $result;
        }

        return false;
    }

    // Query Methods
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

        if (!empty($args['search'])) {
            $where[] = "(name LIKE %s OR email LIKE %s OR description LIKE %s OR actions_taken LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $values = array_merge($values, array($search_term, $search_term, $search_term, $search_term));
        }

        if (!empty($args['status'])) {
            $where[] = "status = %s";
            $values[] = $args['status'];
        }

        if (!empty($args['severity'])) {
            $where[] = "severity = %s";
            $values[] = $args['severity'];
        }

        if (!empty($args['start_date'])) {
            $where[] = "submitted_at >= %s";
            $values[] = $args['start_date'] . ' 00:00:00';
        }

        if (!empty($args['end_date'])) {
            $where[] = "submitted_at <= %s";
            $values[] = $args['end_date'] . ' 23:59:59';
        }

        $query = "SELECT * FROM $table_name WHERE " . implode(' AND ', $where);
        
        if (!empty($args['orderby'])) {
            $query .= " ORDER BY " . esc_sql($args['orderby']) . " " . esc_sql($args['order']);
        }
        
        if ($args['limit'] !== null) {
            $query .= $wpdb->prepare(" LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }

        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }

        return $wpdb->get_results($query);
    }

    public static function get_incidents_count($args = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hlir_incidents';

        $where = array('1=1');
        $values = array();

        if (!empty($args['search'])) {
            $where[] = "(name LIKE %s OR email LIKE %s OR description LIKE %s OR actions_taken LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $values = array_merge($values, array($search_term, $search_term, $search_term, $search_term));
        }

        if (!empty($args['status'])) {
            $where[] = "status = %s";
            $values[] = $args['status'];
        }

        if (!empty($args['severity'])) {
            $where[] = "severity = %s";
            $values[] = $args['severity'];
        }

        $query = "SELECT COUNT(*) FROM $table_name WHERE " . implode(' AND ', $where);
        
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }

        return $wpdb->get_var($query);
    }

    // Statistics and Analytics Methods
    public static function get_incident_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hlir_incidents';

        // Verify table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            hlir_debug_log('Analytics: Table does not exist');
            return array(
                'total_incidents' => 0,
                'types' => array('labels' => array(), 'data' => array()),
                'severity' => array('labels' => array(), 'data' => array()),
                'status' => array('labels' => array(), 'data' => array()),
                'timeline' => array('labels' => array(), 'data' => array())
            );
        }

        try {
            // Get total incidents
            $total_incidents = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            
            // Get open incidents count
            $open_incidents = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE status IN (%s, %s)",
                'new',
                'in_progress'
            ));

            // Get critical incidents count
            $critical_incidents = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE severity = %s",
                'critical'
            ));

            // Get incidents by type
            $incident_types = $wpdb->get_results("
                SELECT incident_type, COUNT(*) as count 
                FROM $table_name 
                GROUP BY incident_type 
                ORDER BY count DESC
            ");

            // Get incidents by severity
            $severity_stats = $wpdb->get_results("
                SELECT severity, COUNT(*) as count 
                FROM $table_name 
                GROUP BY severity 
                ORDER BY FIELD(severity, 'critical', 'high', 'medium', 'low')
            ");

            // Get incidents by status
            $status_stats = $wpdb->get_results("
                SELECT status, COUNT(*) as count 
                FROM $table_name 
                GROUP BY status 
                ORDER BY count DESC
            ");

            // Get incidents over time (last 30 days)
            $time_stats = $wpdb->get_results("
                SELECT 
                    DATE(submitted_at) as date, 
                    COUNT(*) as count
                FROM $table_name 
                WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DATE(submitted_at) 
                ORDER BY date ASC
            ");

            // Prepare data for charts
            return array(
                'total_incidents' => (int)$total_incidents,
                'open_incidents' => (int)$open_incidents,
                'critical_incidents' => (int)$critical_incidents,
                'types' => array(
                    'labels' => array_map(function($item) {
                        return ucwords(str_replace('_', ' ', $item->incident_type));
                    }, $incident_types),
                    'data' => array_map(function($item) {
                        return (int)$item->count;
                    }, $incident_types)
                ),
                'severity' => array(
                    'labels' => array_map(function($item) {
                        return ucfirst($item->severity);
                    }, $severity_stats),
                    'data' => array_map(function($item) {
                        return (int)$item->count;
                    }, $severity_stats),
                    'colors' => array(
                        'critical' => '#9C27B0',
                        'high' => '#f44336',
                        'medium' => '#FF9800',
                        'low' => '#4CAF50'
                    )
                ),
                'status' => array(
                    'labels' => array_map(function($item) {
                        return ucwords(str_replace('_', ' ', $item->status));
                    }, $status_stats),
                    'data' => array_map(function($item) {
                        return (int)$item->count;
                    }, $status_stats),
                    'colors' => array(
                        'new' => '#2196F3',
                        'in_progress' => '#FF9800',
                        'resolved' => '#4CAF50',
                        'closed' => '#9E9E9E'
                    )
                ),
                'timeline' => array(
                    'labels' => array_map(function($item) {
                        return date('M j', strtotime($item->date));
                    }, $time_stats),
                    'data' => array_map(function($item) {
                        return (int)$item->count;
                    }, $time_stats)
                )
            );

        } catch (Exception $e) {
            hlir_debug_log('Error in analytics: ' . $e->getMessage());
            return array(
                'total_incidents' => 0,
                'types' => array('labels' => array(), 'data' => array()),
                'severity' => array('labels' => array(), 'data' => array()),
                'status' => array('labels' => array(), 'data' => array()),
                'timeline' => array('labels' => array(), 'data' => array())
            );
        }
    }

    public static function get_trends() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hlir_incidents';

        $this_month = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM $table_name 
            WHERE MONTH(submitted_at) = MONTH(CURRENT_DATE())
            AND YEAR(submitted_at) = YEAR(CURRENT_DATE())
        ");

        $last_month = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM $table_name 
            WHERE MONTH(submitted_at) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
            AND YEAR(submitted_at) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
        ");

        $percent_change = 0;
        if ($last_month > 0) {
            $percent_change = (($this_month - $last_month) / $last_month) * 100;
        }

        return array(
            'this_month' => (int)$this_month,
            'last_month' => (int)$last_month,
            'percent_change' => round($percent_change, 1)
        );
    }

    public static function get_recent_incidents($limit = 5) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hlir_incidents';

        return $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM $table_name
            ORDER BY submitted_at DESC
            LIMIT %d
        ", $limit));
    }

    // Utility Methods
    public static function sanitize_incident_data($data) {
        return array(
            'name' => sanitize_text_field($data['name'] ?? ''),
            'email' => sanitize_email($data['email'] ?? ''),
            'incident_type' => sanitize_text_field($data['incident_type'] ?? ''),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'actions_taken' => sanitize_textarea_field($data['actions_taken'] ?? ''),
            'date' => sanitize_text_field($data['date'] ?? ''),
            'status' => sanitize_text_field($data['status'] ?? ''),
            'severity' => sanitize_text_field($data['severity'] ?? '')
        );
    }

    public static function validate_incident_data($data) {
        $errors = new WP_Error();

        if (empty($data['name'])) {
            $errors->add('empty_name', 'Name is required.');
        }

        if (empty($data['email']) || !is_email($data['email'])) {
            $errors->add('invalid_email', 'Valid email is required.');
        }

        if (empty($data['incident_type'])) {
            $errors->add('empty_type', 'Incident type is required.');
        }

        if (empty($data['description'])) {
            $errors->add('empty_description', 'Description is required.');
        }

        if (empty($data['severity'])) {
            $errors->add('empty_severity', 'Severity level is required.');
        }

        return $errors;
    }
}
