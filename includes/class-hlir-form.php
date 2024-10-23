<?php
if (!defined('ABSPATH')) {
    exit;
}

class HLIR_Form {
    public static function render() {
        $settings = get_option('hlir_settings', array(
            'form_title' => 'Report a Security Incident'
        ));
        ?>
        <div class="hlir-form-container">
            <h2><?php echo esc_html($settings['form_title']); ?></h2>
            <form id="hlir-incident-form" method="post">
                <?php wp_nonce_field('hlir_incident_report', 'hlir_nonce'); ?>
                
                <div class="form-group">
                    <label for="hlir-name">Your Name</label>
                    <input type="text" id="hlir-name" name="hlir_name" required>
                </div>
                
                <div class="form-group">
                    <label for="hlir-email">Your Email</label>
                    <input type="email" id="hlir-email" name="hlir_email" required>
                </div>
                
                <div class="form-group">
                    <label for="hlir-incident-type">Incident Type</label>
                    <select id="hlir-incident-type" name="hlir_incident_type" required>
                        <option value="">Select Type</option>
                        <option value="phishing">Phishing</option>
                        <option value="data_breach">Data Breach</option>
                        <option value="unauthorized_access">Unauthorized Access</option>
                        <option value="malware">Malware</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="hlir-description">Incident Description</label>
                    <textarea id="hlir-description" name="hlir_description" required></textarea>
                </div>
                
                <div class="form-group datetime-group">
                    <div>
                        <label for="hlir-date">Date of Incident</label>
                        <input type="date" id="hlir-date" name="hlir_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div>
                        <label for="hlir-time">Time of Incident</label>
                        <input type="time" id="hlir-time" name="hlir_time" value="<?php echo date('H:i'); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="hlir-severity">Severity</label>
                    <select id="hlir-severity" name="hlir_severity" required>
                        <option value="">Select Severity</option>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="hlir-submit-btn custom-link btn border-width-0 btn-color-671969 btn-round btn-flat btn-icon-left">Submit Report</button>
                </div>
            </form>
        </div>
        <?php
    }

    public static function process_form() {
        hlir_debug_log('Starting form processing');
        hlir_debug_log('POST data:');
        hlir_debug_log($_POST);

        try {
            // Check nonce - already handled in main class
            
            // Validate required fields
            $required_fields = ['hlir_name', 'hlir_email', 'hlir_incident_type', 'hlir_description', 'hlir_date', 'hlir_time', 'hlir_severity'];
            foreach ($required_fields as $field) {
                if (empty($_POST[$field])) {
                    throw new Exception("Missing required field: {$field}");
                }
            }

            // Validate email
            if (!is_email($_POST['hlir_email'])) {
                throw new Exception('Please enter a valid email address.');
            }

            // Format date and time
            $incident_date = sanitize_text_field($_POST['hlir_date']);
            $incident_time = sanitize_text_field($_POST['hlir_time']);
            $incident_datetime = $incident_date . ' ' . $incident_time;

            hlir_debug_log('Formatted datetime: ' . $incident_datetime);

            // Prepare incident data
            $incident_data = array(
                'name' => sanitize_text_field($_POST['hlir_name']),
                'email' => sanitize_email($_POST['hlir_email']),
                'incident_type' => sanitize_text_field($_POST['hlir_incident_type']),
                'description' => sanitize_textarea_field($_POST['hlir_description']),
                'date' => $incident_datetime,
                'status' => 'new',
                'submitted_at' => current_time('mysql'),
                'severity' => sanitize_text_field($_POST['hlir_severity'])
            );

            hlir_debug_log('Prepared incident data:');
            hlir_debug_log($incident_data);

            // Insert the incident
            $incident_id = HLIR_DB::insert_incident($incident_data);

            if (!$incident_id) {
                throw new Exception('Database insertion failed. Please check the debug log for details.');
            }

            // Send notification email
            self::send_notification($incident_data);

            return $incident_id;

        } catch (Exception $e) {
            hlir_debug_log('Error in process_form: ' . $e->getMessage());
            return new WP_Error('form_error', $e->getMessage());
        }
    }

    private static function send_notification($incident_data) {
        $settings = get_option('hlir_settings');
        $to = isset($settings['notification_email']) ? $settings['notification_email'] : get_option('admin_email');
        $subject = sprintf('New %s Priority Security Incident Reported', ucfirst($incident_data['severity']));
        
        // Create HTML email template
        $message = '<html><body>';
        $message .= '<h2 style="color: #2c3e50;">New Security Incident Report</h2>';
        $message .= '<table style="border-collapse: collapse; width: 100%; max-width: 600px;">';
        $message .= '<tr><th style="text-align: left; padding: 8px; background-color: #f2f2f2; border: 1px solid #ddd;">Field</th>';
        $message .= '<th style="text-align: left; padding: 8px; background-color: #f2f2f2; border: 1px solid #ddd;">Value</th></tr>';
        
        // Add incident details to the table
        $fields = array(
            'Name' => $incident_data['name'],
            'Email' => $incident_data['email'],
            'Incident Type' => ucfirst(str_replace('_', ' ', $incident_data['incident_type'])),
            'Severity' => ucfirst($incident_data['severity']),
            'Date' => $incident_data['date'],
            'Description' => nl2br($incident_data['description'])
        );

        foreach ($fields as $label => $value) {
            $message .= sprintf(
                '<tr><td style="border: 1px solid #ddd; padding: 8px;"><strong>%s</strong></td>
                <td style="border: 1px solid #ddd; padding: 8px;">%s</td></tr>',
                esc_html($label),
                wp_kses_post($value)
            );
        }

        $message .= '</table>';
        $message .= '<p style="color: #666;">This is an automated notification. Please do not reply to this email.</p>';
        $message .= '</body></html>';

        // Headers for HTML email
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Hidden Leaf Security <' . get_option('admin_email') . '>'
        );
        
        return wp_mail($to, $subject, $message, $headers);
    }
}