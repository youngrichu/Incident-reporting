<?php
if (!defined('ABSPATH')) {
    exit;
}

class HLIR_Analytics {
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
}