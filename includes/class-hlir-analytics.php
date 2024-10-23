<?php
class HLIR_Analytics {
    public static function get_incident_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hlir_incidents';

        hlir_debug_log('Fetching incident statistics');

        try {
            // Get total incidents
            $total_incidents = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            hlir_debug_log('Total incidents: ' . $total_incidents);

            // Get incidents by type
            $incident_types = $wpdb->get_results("
                SELECT incident_type, COUNT(*) as count 
                FROM $table_name 
                GROUP BY incident_type 
                ORDER BY count DESC
            ");
            hlir_debug_log('Incident types:', $incident_types);

            // Get incidents by severity
            $severity_stats = $wpdb->get_results("
                SELECT severity, COUNT(*) as count 
                FROM $table_name 
                GROUP BY severity 
                ORDER BY FIELD(severity, 'critical', 'high', 'medium', 'low')
            ");
            hlir_debug_log('Severity stats:', $severity_stats);

            // Get incidents by status
            $status_stats = $wpdb->get_results("
                SELECT status, COUNT(*) as count 
                FROM $table_name 
                GROUP BY status 
                ORDER BY count DESC
            ");
            hlir_debug_log('Status stats:', $status_stats);

            // Get incidents over time (last 30 days)
            $time_stats = $wpdb->get_results("
                SELECT 
                    DATE(submitted_at) as date, 
                    COUNT(*) as count,
                    SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical_count,
                    SUM(CASE WHEN severity = 'high' THEN 1 ELSE 0 END) as high_count
                FROM $table_name 
                WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
                GROUP BY DATE(submitted_at) 
                ORDER BY date ASC
            ");
            hlir_debug_log('Time stats:', $time_stats);

            // Prepare data for charts
            $chart_data = array(
                'total_incidents' => (int)$total_incidents,
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

            hlir_debug_log('Chart data prepared:', $chart_data);
            return $chart_data;

        } catch (Exception $e) {
            hlir_debug_log('Error in get_incident_stats: ' . $e->getMessage());
            return array(
                'total_incidents' => 0,
                'types' => array('labels' => array(), 'data' => array()),
                'severity' => array('labels' => array(), 'data' => array()),
                'status' => array('labels' => array(), 'data' => array()),
                'timeline' => array('labels' => array(), 'data' => array())
            );
        }
    }
}