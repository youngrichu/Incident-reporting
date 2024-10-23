<?php
class HLIR_Analytics {
    public static function get_incident_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'hlir_incidents';

        // Get total incidents
        $total_incidents = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

        // Get incidents by type
        $incident_types = $wpdb->get_results("SELECT incident_type, COUNT(*) as count FROM $table_name GROUP BY incident_type");

        // Prepare data for chart
        $labels = [];
        $data = [];
        foreach ($incident_types as $incident) {
            $labels[] = $incident->incident_type;
            $data[] = (int) $incident->count;
        }

        return [
            'total_incidents' => $total_incidents,
            'labels' => $labels,
            'data' => $data,
        ];
    }
}
