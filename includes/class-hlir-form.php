<?php
if (!defined('ABSPATH')) {
    exit;
}

class HLIR_Form {
    private static $instance = null;
    private static $allowed_file_types = array('jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx');
    private static $max_file_size = 5242880; // 5MB in bytes

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Initialize if needed
    }

    public static function init() {
        // Load form-specific scripts and styles
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_form_assets'));
    }

    public static function enqueue_form_assets() {
        if (!wp_script_is('hlir-form-script', 'enqueued')) {
            wp_enqueue_script('jquery');
            wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array(), HLIR_VERSION, true);
            wp_enqueue_script('hlir-form-script', HLIR_PLUGIN_URL . 'assets/js/form-script.js', array('jquery', 'sweetalert2'), HLIR_VERSION, true);
            wp_localize_script('hlir-form-script', 'hlir_settings', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('hlir_form_nonce'),
                'max_file_size' => self::$max_file_size,
                'allowed_types' => self::$allowed_file_types
            ));
        }

        if (!wp_style_is('hlir-form-style', 'enqueued')) {
            wp_enqueue_style('hlir-form-style', HLIR_PLUGIN_URL . 'assets/css/form-style.css', array(), HLIR_VERSION);
        }

        // Load reCAPTCHA if enabled
        $settings = get_option('hlir_settings');
        if (!empty($settings['recaptcha_site_key'])) {
            wp_enqueue_script('google-recaptcha', 'https://www.google.com/recaptcha/api.js', array(), null, true);
        }
    }

    public static function render() {
        // Ensure scripts and styles are enqueued
        self::enqueue_form_assets();

        // Get plugin settings
        $settings = get_option('hlir_settings', array(
            'form_title' => 'Report a Security Incident'
        ));

        // Generate anti-spam tokens
        $timestamp = time();
        $honeypot_hash = wp_hash($timestamp . get_current_user_id());

        // Start output buffering
        ob_start();
        ?>
        <div class="hlir-form-container">
            <h2><?php echo esc_html($settings['form_title']); ?></h2>
            
            <form id="hlir-incident-form" method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('hlir_incident_report', 'hlir_nonce'); ?>
                
                <!-- Anti-spam fields -->
                <input type="hidden" name="hlir_timestamp" value="<?php echo esc_attr($timestamp); ?>">
                <input type="hidden" name="hlir_hash" value="<?php echo esc_attr($honeypot_hash); ?>">
                <div style="display:none !important;">
                    <label for="hlir_website">Website</label>
                    <input type="text" name="hlir_website" id="hlir_website" tabindex="-1" autocomplete="off">
                </div>

                <div class="form-group">
                    <label for="hlir-name">Your Name <span class="required">*</span></label>
                    <input type="text" id="hlir-name" name="hlir_name" required>
                </div>
                
                <div class="form-group">
                    <label for="hlir-email">Your Email <span class="required">*</span></label>
                    <input type="email" id="hlir-email" name="hlir_email" required>
                </div>
                
                <div class="form-group">
                    <label for="hlir-incident-type">Incident Type <span class="required">*</span></label>
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
                    <label for="hlir-description">Incident Description <span class="required">*</span></label>
                    <textarea id="hlir-description" name="hlir_description" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="hlir-actions-taken">Actions Already Taken</label>
                    <textarea id="hlir-actions-taken" name="hlir_actions_taken"></textarea>
                </div>

                <div class="form-group datetime-group">
                    <div>
                        <label for="hlir-date">Date of Incident <span class="required">*</span></label>
                        <input type="date" id="hlir-date" name="hlir_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div>
                        <label for="hlir-time">Time of Incident <span class="required">*</span></label>
                        <input type="time" id="hlir-time" name="hlir_time" value="<?php echo date('H:i'); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="hlir-severity">Severity <span class="required">*</span></label>
                    <select id="hlir-severity" name="hlir_severity" required>
                        <option value="">Select Severity</option>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="hlir-attachments">Supporting Documents</label>
                    <input type="file" id="hlir-attachments" name="hlir_attachments[]" multiple 
                           accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                    <p class="description">
                        Accepted file types: Images (JPG, PNG), PDF, Word documents.<br>
                        Maximum file size: 5MB per file.
                    </p>
                </div>

                <?php if (!empty($settings['recaptcha_site_key'])): ?>
                <div class="form-group">
                    <div class="g-recaptcha" data-sitekey="<?php echo esc_attr($settings['recaptcha_site_key']); ?>"></div>
                </div>
                <?php endif; ?>

                <div class="form-group">
                <button type="submit" class="hlir-submit-btn custom-link btn border-width-0 btn-color-671969 btn-round btn-flat btn-icon-left">Submit Report</button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function process_form() {
        try {
            // Verify nonce
            if (!isset($_POST['hlir_nonce']) || !wp_verify_nonce($_POST['hlir_nonce'], 'hlir_incident_report')) {
                throw new Exception('Security check failed.');
            }

            // Verify anti-spam measures
            $spam_check = self::verify_submission();
            if (is_wp_error($spam_check)) {
                throw new Exception($spam_check->get_error_message());
            }

            // Validate required fields
            $required_fields = array(
                'hlir_name' => 'Name',
                'hlir_email' => 'Email',
                'hlir_incident_type' => 'Incident Type',
                'hlir_description' => 'Description',
                'hlir_date' => 'Date',
                'hlir_time' => 'Time',
                'hlir_severity' => 'Severity'
            );

            foreach ($required_fields as $field => $label) {
                if (empty($_POST[$field])) {
                    throw new Exception($label . ' is required.');
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

            // Prepare incident data
            $incident_data = array(
                'name' => sanitize_text_field($_POST['hlir_name']),
                'email' => sanitize_email($_POST['hlir_email']),
                'incident_type' => sanitize_text_field($_POST['hlir_incident_type']),
                'description' => sanitize_textarea_field($_POST['hlir_description']),
                'actions_taken' => sanitize_textarea_field($_POST['hlir_actions_taken']),
                'date' => $incident_datetime,
                'status' => 'new',
                'submitted_at' => current_time('mysql'),
                'severity' => sanitize_text_field($_POST['hlir_severity'])
            );

            // Insert the incident
            $incident_id = HLIR_DB::insert_incident($incident_data);

            if (!$incident_id) {
                throw new Exception('Failed to save incident report.');
            }

            // Handle file uploads
            if (!empty($_FILES['hlir_attachments']['name'][0])) {
                $upload_results = self::process_file_uploads($incident_id);
                if (is_wp_error($upload_results)) {
                    throw new Exception($upload_results->get_error_message());
                }
            }

            // Send notification email
            self::send_notification($incident_data, $incident_id, $_POST['hlir_email']); // Pass reporter's email

            return $incident_id;

        } catch (Exception $e) {
            return new WP_Error('form_error', $e->getMessage());
        }
    }

    private static function verify_submission() {
        // Initialize default response
        $is_valid = true;
        $error_message = '';

        try {
            // Check honeypot
            if (!empty($_POST['hlir_website'])) {
                $is_valid = false;
                $error_message = 'Spam detection triggered.';
            }

            // Verify timestamp exists
            if (!isset($_POST['hlir_timestamp'])) {
                $is_valid = false;
                $error_message = 'Missing submission timestamp.';
            }

            // Verify hash exists
            if (!isset($_POST['hlir_hash'])) {
                $is_valid = false;
                $error_message = 'Missing submission verification.';
            }

            // If basic checks pass, verify timestamp and hash
            if ($is_valid) {
                $timestamp = intval($_POST['hlir_timestamp']);
                $hash = sanitize_text_field($_POST['hlir_hash']);
                $expected_hash = wp_hash($timestamp . get_current_user_id());

                // Verify submission isn't too old (30 minutes max)
                if (time() - $timestamp > 1800) {
                    $is_valid = false;
                    $error_message = 'Form submission expired. Please refresh the page and try again.';
                }

                // Verify submission isn't too quick (2 seconds min)
                if (time() - $timestamp < 2) {
                    $is_valid = false;
                    $error_message = 'Please take time to fill out the form properly.';
                }

                // Verify hash matches
                if ($hash !== $expected_hash) {
                    $is_valid = false;
                    $error_message = 'Invalid submission verification.';
                }
            }

            // Verify reCAPTCHA if enabled
            $settings = get_option('hlir_settings');
            if (!empty($settings['recaptcha_secret_key'])) {
                if (empty($_POST['g-recaptcha-response'])) {
                    $is_valid = false;
                    $error_message = 'Please complete the reCAPTCHA verification.';
                } else {
                    $verify = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', array(
                        'body' => array(
                            'secret' => $settings['recaptcha_secret_key'],
                            'response' => $_POST['g-recaptcha-response']
                        )
                    ));

                    if (is_wp_error($verify) || empty($verify['body'])) {
                        $is_valid = false;
                        $error_message = 'Failed to verify reCAPTCHA response.';
                    } else {
                        $verify_response = json_decode($verify['body'], true);
                        if (!$verify_response['success']) {
                            $is_valid = false;
                            $error_message = 'reCAPTCHA verification failed.';
                        }
                    }
                }
            }

            return $is_valid ? true : new WP_Error('validation_failed', $error_message);

        } catch (Exception $e) {
            hlir_debug_log('Spam verification error: ' . $e->getMessage());
            return new WP_Error('validation_failed', 'An error occurred while processing your submission.');
        }
    }

    private static function process_file_uploads($incident_id) {
        if (empty($_FILES['hlir_attachments']['name'][0])) {
            return true;
        }

        $files = $_FILES['hlir_attachments'];
        $uploaded_files = array();

        // Process each file
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            // Verify file size
            if ($files['size'][$i] > self::$max_file_size) {
                return new WP_Error(
                    'file_too_large',
                    sprintf('File "%s" exceeds the maximum allowed size of %s.',
                        $files['name'][$i],
                        size_format(self::$max_file_size)
                    )
                );
            }

            // Verify file type
            $file_extension = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            if (!in_array($file_extension, self::$allowed_file_types)) {
                return new WP_Error(
                    'invalid_file_type',
                    sprintf('File type "%s" is not allowed.', $file_extension)
                );
            }

            $file_data = array(
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'size' => $files['size'][$i]
            );

            $result = HLIR_DB::add_attachment($incident_id, $file_data);
            if ($result) {
                $uploaded_files[] = $files['name'][$i];
            }
        }

        return $uploaded_files;
    }

    private static function send_notification($incident_data, $incident_id, $reporter_email) {
        $settings = get_option('hlir_settings');
        $to = isset($settings['notification_email']) ? $settings['notification_email'] : get_option('admin_email');
        
        // Add reporter's email to the recipients
        $recipients = array_filter([$to, $reporter_email]); // Filter out any empty values
        $subject = sprintf(
            '[%s] New %s Priority Security Incident Reported (#%d)',
            get_bloginfo('name'),
            ucfirst($incident_data['severity']),
            $incident_id
        );

        $message = '<html><body>';
        $message .= '<h2 style="color: #2c3e50;">New Security Incident Report</h2>';
        $message .= sprintf('<p>A new security incident has been reported. Reference ID: #%d</p>', $incident_id);
        
        $message .= '<table style="border-collapse: collapse; width: 100%; max-width: 600px;">';
        $message .= '<tr><th style="text-align: left; padding: 8px; background-color: #f2f2f2; border: 1px solid #ddd;">Field</th>';
        $message .= '<th style="text-align: left; padding: 8px; background-color: #f2f2f2; border: 1px solid #ddd;">Value</th></tr>';

        $fields = array(
            'Name' => $incident_data['name'],
            'Email' => $incident_data['email'],
            'Incident Type' => ucfirst(str_replace('_', ' ', $incident_data['incident_type'])),
            'Severity' => ucfirst($incident_data['severity']),
            'Date' => $incident_data['date'],
            'Description' => nl2br($incident_data['description']),
            'Actions Taken' => !empty($incident_data['actions_taken']) ? nl2br($incident_data['actions_taken']) : 'None reported'
        );

        foreach ($fields as $label => $value) {
            $message .= sprintf(
                '<tr><td style="border: 1px solid #ddd; padding: 8px;"><strong>%s</strong></td>
                <td style="border: 1px solid #ddd; padding: 8px;">%s</td></tr>',
                esc_html($label),
                wp_kses_post($value)
            );
        }

        // Add attachments information if any
        $attachments = HLIR_DB::get_incident_attachments($incident_id);
        if (!empty($attachments)) {
            $message .= '<tr><td style="border: 1px solid #ddd; padding: 8px;"><strong>Attachments</strong></td>';
            $message .= '<td style="border: 1px solid #ddd; padding: 8px;">';
            foreach ($attachments as $attachment) {
                $message .= sprintf(
                    '• %s (%s)<br>',
                    esc_html($attachment->file_name),
                    size_format($attachment->file_size, 2)
                );
            }
            $message .= '</td></tr>';
        }

        $message .= '</table>';

        // Add action links
        $admin_url = admin_url('admin.php?page=hlir-incidents&incident_id=' . $incident_id);
        $message .= sprintf(
            '<p style="margin-top: 20px;">
                <a href="%s" style="background-color: #2c3e50; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 4px;">
                    View Incident Details
                </a>
            </p>',
            esc_url($admin_url)
        );

        $message .= '<p style="color: #666; font-size: 0.9em; margin-top: 20px;">
            This is an automated notification. Please do not reply to this email.
            </p>';
        $message .= '</body></html>';

        // Send email to all recipients
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            sprintf('From: %s <%s>', get_bloginfo('name'), get_option('admin_email'))
        );

        return wp_mail($recipients, $subject, $message, $headers);
    }

    public static function display_incident_attachments($incident_id) {
        $attachments = HLIR_DB::get_incident_attachments($incident_id);
        if (empty($attachments)) {
            echo '<p>No attachments found.</p>';
            return;
        }
        ?>
        <div class="hlir-attachments-section">
            <h3>Supporting Documents</h3>
            <div class="hlir-attachments-list">
                <?php foreach ($attachments as $attachment): ?>
                    <div class="hlir-attachment-item">
                        <div class="attachment-icon">
                            <?php echo self::get_file_icon($attachment->file_type); ?>
                        </div>
                        <div class="attachment-details">
                            <span class="attachment-name"><?php echo esc_html($attachment->file_name); ?></span>
                            <span class="attachment-meta">
                                <?php 
                                $file_size = size_format($attachment->file_size, 2);
                                $upload_date = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), 
                                    strtotime($attachment->uploaded_at));
                                echo esc_html("$file_size • Uploaded on $upload_date");
                                ?>
                            </span>
                        </div>
                        <div class="attachment-actions">
                            <a href="<?php echo esc_url(wp_nonce_url(
                                admin_url('admin-post.php?action=hlir_download_attachment&id=' . $attachment->id),
                                'download_attachment_' . $attachment->id
                            )); ?>" 
                               class="button button-secondary">
                                <span class="dashicons dashicons-download"></span> Download
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    private static function get_file_icon($mime_type) {
        switch ($mime_type) {
            case 'image/jpeg':
            case 'image/png':
                return '<span class="dashicons dashicons-format-image"></span>';
            case 'application/pdf':
                return '<span class="dashicons dashicons-pdf"></span>';
            case 'application/msword':
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
                return '<span class="dashicons dashicons-media-document"></span>';
            default:
                return '<span class="dashicons dashicons-media-default"></span>';
        }
    }

    public static function display_form_errors($errors) {
        if (!is_wp_error($errors)) {
            return;
        }
        ?>
        <div class="hlir-form-errors">
            <ul>
                <?php foreach ($errors->get_error_messages() as $error): ?>
                    <li><?php echo esc_html($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }

    // Utility Methods
    private static function get_allowed_file_types() {
        $settings = get_option('hlir_settings');
        return !empty($settings['allowed_file_types']) ? $settings['allowed_file_types'] : self::$allowed_file_types;
    }

    private static function get_max_file_size() {
        $settings = get_option('hlir_settings');
        $configured_size = !empty($settings['max_file_size']) ? intval($settings['max_file_size']) : 5;
        return min($configured_size * 1024 * 1024, wp_max_upload_size());
    }

    private static function is_valid_file_type($file_name) {
        $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        return in_array($extension, self::get_allowed_file_types());
    }

    private static function format_file_size($bytes) {
        $units = array('B', 'KB', 'MB', 'GB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    // Prevent cloning of the instance
    private function __clone() {}

    // Prevent unserializing of the instance
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// Initialize the form handler
add_action('init', array('HLIR_Form', 'init'));
