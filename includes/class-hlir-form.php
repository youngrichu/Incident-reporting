<?php
class HLIR_Form {
    public static function render() {
        $settings = get_option('hlir_settings');
        ?>
        <div class="hlir-form-container">
            <h2><?php echo esc_html($settings['form_title']); ?></h2>
            <?php if ($message = get_transient('hlir_success_message')): ?>
                <div class="toast" id="toast"><?php echo esc_html($message); ?></div>
                <?php delete_transient('hlir_success_message'); ?>
            <?php endif; ?>
            <form id="hlir-incident-form" method="post">
                <?php wp_nonce_field('hlir_incident_report', 'hlir_nonce'); ?>
                <div class="form-group">
                    <label for="hlir-name">Your Name:</label>
                    <input type="text" id="hlir-name" name="hlir_name" required>
                </div>
                <div class="form-group">
                    <label for="hlir-email">Your Email:</label>
                    <input type="email" id="hlir-email" name="hlir_email" required>
                </div>
                <div class="form-group">
                    <label for="hlir-incident-type">Incident Type:</label>
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
                    <label for="hlir-description">Incident Description:</label>
                    <textarea id="hlir-description" name="hlir_description" required></textarea>
                </div>
                <div class="form-group">
                    <label for="hlir-date">Date of Incident:</label>
                    <input type="date" id="hlir-date" name="hlir_date" value="<?php echo date('Y-m-d'); ?>" required>
                    <input type="time" id="hlir-time" name="hlir_time" value="<?php echo date('H:i'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="hlir-severity">Severity:</label>
                    <select id="hlir-severity" name="hlir_severity" required>
                        <option value="">Select Severity</option>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
                <div class="form-group">
                    <input type="submit" value="Submit Report">
                </div>
            </form>
        </div>
        <script>
            // Toast notification logic
            const toast = document.getElementById('toast');
            if (toast) {
                setTimeout(() => {
                    toast.style.opacity = '0';
                    setTimeout(() => {
                        toast.remove();
                    }, 500);
                }, 3000); // Toast disappears after 3 seconds
            }
        </script>
        <style>
            .toast {
                background-color: #4CAF50;
                color: white;
                padding: 15px;
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 1000;
                border-radius: 5px;
                opacity: 1;
                transition: opacity 0.5s ease;
            }
        </style>
        <?php
    }

    public static function process_form() {
        if (!isset($_POST['hlir_nonce']) || !wp_verify_nonce($_POST['hlir_nonce'], 'hlir_incident_report')) {
            wp_die('Invalid nonce. Please try again.');
        }

        // Capture date and time
        $incident_date = sanitize_text_field($_POST['hlir_date']);
        $incident_time = sanitize_text_field($_POST['hlir_time']);
        $incident_datetime = $incident_date . ' ' . $incident_time; // Combine date and time

        $incident_data = array(
            'name' => sanitize_text_field($_POST['hlir_name']),
            'email' => sanitize_email($_POST['hlir_email']),
            'incident_type' => sanitize_text_field($_POST['hlir_incident_type']),
            'description' => sanitize_textarea_field($_POST['hlir_description']),
            'date' => $incident_datetime, // Save combined date and time
            'status' => 'new',
            'submitted_at' => current_time('mysql'),
            'severity' => sanitize_text_field($_POST['hlir_severity']),
        );

        $incident_id = HLIR_DB::insert_incident($incident_data);

        if ($incident_id) {
            // Send notification email
            self::send_notification($incident_data);
            
            // Set a success message in a transient
            set_transient('hlir_success_message', get_option('hlir_settings')['success_message'], 30); // 30 seconds
            // Redirect to the same page to avoid showing the message on refresh
            wp_redirect($_SERVER['REQUEST_URI']);
            exit;
        } else {
            wp_die('An error occurred while submitting the report. Please try again.');
        }
    }

    private static function send_notification($incident_data) {
        $settings = get_option('hlir_settings');
        $to = $settings['notification_email']; // Get the configured email
        $subject = 'New Security Incident Reported';
        $message = "A new security incident has been reported:\n\n";
        $message .= "Name: {$incident_data['name']}\n";
        $message .= "Email: {$incident_data['email']}\n";
        $message .= "Incident Type: {$incident_data['incident_type']}\n";
        $message .= "Description: {$incident_data['description']}\n";
        $message .= "Date: {$incident_data['date']}\n";

        wp_mail($to, $subject, $message); // Send the email
    }
}
