<?php
class HLIR_Admin {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'handle_status_update'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    public function enqueue_admin_scripts($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'hlir') !== false) {
            wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), HLIR_VERSION, true);
            wp_enqueue_style('hlir-admin-style', HLIR_PLUGIN_URL . 'assets/css/admin-style.css', array(), HLIR_VERSION);
            wp_enqueue_script('hlir-admin-script', HLIR_PLUGIN_URL . 'assets/js/admin-script.js', array('jquery', 'chart-js'), HLIR_VERSION, true);
        }
    }

    public function add_admin_menu() {
        add_menu_page(
            'Incident Reports',
            'Incident Reports',
            'manage_options',
            'hlir-incidents',
            array($this, 'incidents_page'),
            'dashicons-shield',
            30
        );

        add_submenu_page(
            'hlir-incidents',
            'Analytics',
            'Analytics',
            'manage_options',
            'hlir-analytics',
            array($this, 'analytics_page')
        );

        add_submenu_page(
            'hlir-incidents',
            'Settings',
            'Settings',
            'manage_options',
            'hlir-settings',
            array($this, 'settings_page')
        );
    }

    public function incidents_page() {
        // Check if viewing specific incident
        if (isset($_GET['incident_id'])) {
            $this->display_incident_details($_GET['incident_id']);
            return;
        }

        $incidents = HLIR_DB::get_incidents();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Incident Reports</h1>
            <hr class="wp-header-end">

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Reporter</th>
                        <th>Type</th>
                        <th>Severity</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($incidents as $incident) : ?>
                        <tr>
                            <td>#<?php echo esc_html($incident->id); ?></td>
                            <td><?php echo esc_html($incident->name); ?></td>
                            <td><?php echo esc_html(ucwords(str_replace('_', ' ', $incident->incident_type))); ?></td>
                            <td>
                                <span class="severity-badge severity-<?php echo esc_attr($incident->severity); ?>">
                                    <?php echo esc_html(ucfirst($incident->severity)); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr($incident->status); ?>">
                                    <?php echo esc_html(ucwords(str_replace('_', ' ', $incident->status))); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html(date('M j, Y H:i', strtotime($incident->submitted_at))); ?></td>
                            <td>
                                <a href="?page=hlir-incidents&incident_id=<?php echo esc_attr($incident->id); ?>" 
                                   class="button button-small">View Details</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function display_incident_details($incident_id) {
        $incident = HLIR_DB::get_incident_by_id($incident_id);
        if (!$incident) {
            wp_die('Incident not found');
        }
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                Incident #<?php echo esc_html($incident_id); ?>
                <a href="?page=hlir-incidents" class="page-title-action">← Back to List</a>
            </h1>
            <hr class="wp-header-end">

            <div class="hlir-incident-details">
                <div class="hlir-incident-main">
                    <div class="hlir-incident-header">
                        <div class="hlir-incident-meta">
                            <span class="severity-badge severity-<?php echo esc_attr($incident->severity); ?>">
                                <?php echo esc_html(ucfirst($incident->severity)); ?>
                            </span>
                            <span class="status-badge status-<?php echo esc_attr($incident->status); ?>">
                                <?php echo esc_html(ucwords(str_replace('_', ' ', $incident->status))); ?>
                            </span>
                            <span class="hlir-incident-date">
                                <?php echo esc_html(date('M j, Y H:i', strtotime($incident->submitted_at))); ?>
                            </span>
                        </div>
                        
                        <form method="post" class="hlir-status-form">
                            <input type="hidden" name="incident_id" value="<?php echo esc_attr($incident_id); ?>">
                            <select name="incident_status" class="hlir-status-select">
                                <option value="new" <?php selected($incident->status, 'new'); ?>>New</option>
                                <option value="in_progress" <?php selected($incident->status, 'in_progress'); ?>>In Progress</option>
                                <option value="resolved" <?php selected($incident->status, 'resolved'); ?>>Resolved</option>
                                <option value="closed" <?php selected($incident->status, 'closed'); ?>>Closed</option>
                            </select>
                            <button type="submit" class="button button-primary">Update Status</button>
                        </form>
                    </div>

                    <div class="hlir-incident-content">
                        <h2>Incident Details</h2>
                        <table class="form-table">
                            <tr>
                                <th>Reporter Name:</th>
                                <td><?php echo esc_html($incident->name); ?></td>
                            </tr>
                            <tr>
                                <th>Email:</th>
                                <td><?php echo esc_html($incident->email); ?></td>
                            </tr>
                            <tr>
                                <th>Incident Type:</th>
                                <td><?php echo esc_html(ucwords(str_replace('_', ' ', $incident->incident_type))); ?></td>
                            </tr>
                            <tr>
                                <th>Description:</th>
                                <td><?php echo nl2br(esc_html($incident->description)); ?></td>
                            </tr>
                            <tr>
                                <th>Incident Date:</th>
                                <td><?php echo esc_html(date('M j, Y H:i', strtotime($incident->date))); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function analytics_page() {
        $stats = HLIR_Analytics::get_incident_stats();
        $trends = HLIR_Analytics::get_trends();
        include(HLIR_PLUGIN_DIR . 'templates/admin/analytics.php');
    }

    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>Incident Reporting Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('hlir_settings');
                do_settings_sections('hlir_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function register_settings() {
        register_setting('hlir_settings', 'hlir_settings', array($this, 'sanitize_settings'));

        add_settings_section(
            'hlir_general_settings',
            'General Settings',
            array($this, 'general_settings_callback'),
            'hlir_settings'
        );

        // Notification Email
        add_settings_field(
            'notification_email',
            'Notification Email',
            array($this, 'notification_email_callback'),
            'hlir_settings',
            'hlir_general_settings'
        );

        // Form Title
        add_settings_field(
            'form_title',
            'Form Title',
            array($this, 'form_title_callback'),
            'hlir_settings',
            'hlir_general_settings'
        );

        // Success Message
        add_settings_field(
            'success_message',
            'Success Message',
            array($this, 'success_message_callback'),
            'hlir_settings',
            'hlir_general_settings'
        );

        // Auto-delete old incidents
        add_settings_field(
            'auto_delete_days',
            'Auto-delete Old Incidents',
            array($this, 'auto_delete_callback'),
            'hlir_settings',
            'hlir_general_settings'
        );

        // Email Template
        add_settings_field(
            'email_template',
            'Email Template',
            array($this, 'email_template_callback'),
            'hlir_settings',
            'hlir_general_settings'
        );
    }

    public function general_settings_callback() {
        echo '<p>Configure the general settings for the incident reporting system.</p>';
    }

    public function notification_email_callback() {
        $settings = get_option('hlir_settings');
        ?>
        <input 
            type="email" 
            name="hlir_settings[notification_email]" 
            value="<?php echo esc_attr($settings['notification_email']); ?>"
            class="regular-text"
        >
        <p class="description">Email address where incident notifications will be sent.</p>
        <?php
    }

    public function form_title_callback() {
        $settings = get_option('hlir_settings');
        ?>
        <input 
            type="text" 
            name="hlir_settings[form_title]" 
            value="<?php echo esc_attr($settings['form_title']); ?>"
            class="regular-text"
        >
        <p class="description">Title displayed above the incident report form.</p>
        <?php
    }

    public function success_message_callback() {
        $settings = get_option('hlir_settings');
        ?>
        <textarea 
            name="hlir_settings[success_message]" 
            class="large-text" 
            rows="3"
        ><?php echo esc_textarea($settings['success_message']); ?></textarea>
        <p class="description">Message shown after successful submission of an incident report.</p>
        <?php
    }

    public function auto_delete_callback() {
        $settings = get_option('hlir_settings');
        $days = isset($settings['auto_delete_days']) ? $settings['auto_delete_days'] : '0';
        ?>
        <select name="hlir_settings[auto_delete_days]">
            <option value="0" <?php selected($days, '0'); ?>>Never</option>
            <option value="30" <?php selected($days, '30'); ?>>30 Days</option>
            <option value="60" <?php selected($days, '60'); ?>>60 Days</option>
            <option value="90" <?php selected($days, '90'); ?>>90 Days</option>
            <option value="180" <?php selected($days, '180'); ?>>180 Days</option>
            <option value="365" <?php selected($days, '365'); ?>>1 Year</option>
        </select>
        <p class="description">Automatically delete closed incidents after specified period.</p>
        <?php
    }

    public function email_template_callback() {
        $settings = get_option('hlir_settings');
        $template = isset($settings['email_template']) ? $settings['email_template'] : '';
        ?>
        <textarea 
            name="hlir_settings[email_template]" 
            class="large-text code" 
            rows="10"
        ><?php echo esc_textarea($template); ?></textarea>
        <p class="description">
            HTML template for notification emails. Available variables: {incident_id}, {name}, {email}, 
            {type}, {severity}, {date}, {description}, {status}
        </p>
        <?php
    }

    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Notification Email
        $sanitized['notification_email'] = sanitize_email($input['notification_email']);
        
        // Form Title
        $sanitized['form_title'] = sanitize_text_field($input['form_title']);
        
        // Success Message
        $sanitized['success_message'] = wp_kses_post($input['success_message']);
        
        // Auto-delete Days
        $sanitized['auto_delete_days'] = absint($input['auto_delete_days']);
        
        // Email Template
        $sanitized['email_template'] = wp_kses_post($input['email_template']);
        
        return $sanitized;
    }

    public function handle_status_update() {
        if (!isset($_POST['incident_id']) || !isset($_POST['incident_status'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $incident_id = absint($_POST['incident_id']);
        $status = sanitize_text_field($_POST['incident_status']);

        // Validate status
        $valid_statuses = array('new', 'in_progress', 'resolved', 'closed');
        if (!in_array($status, $valid_statuses)) {
            wp_die('Invalid status');
        }

        // Update status
        HLIR_DB::update_incident_status($incident_id, $status);

        // Redirect back with success message
        add_settings_error(
            'hlir_messages',
            'hlir_status_updated',
            'Incident status updated successfully.',
            'success'
        );

        // Get the current URL without query parameters
        $redirect_url = remove_query_arg(array('incident_id'), wp_get_referer());
        wp_redirect($redirect_url);
        exit;
    }

    public function get_status_badge($status) {
        $status_classes = array(
            'new' => 'status-new',
            'in_progress' => 'status-progress',
            'resolved' => 'status-resolved',
            'closed' => 'status-closed'
        );

        $class = isset($status_classes[$status]) ? $status_classes[$status] : 'status-default';
        return sprintf(
            '<span class="status-badge %s">%s</span>',
            esc_attr($class),
            esc_html(ucwords(str_replace('_', ' ', $status)))
        );
    }

    public function get_severity_badge($severity) {
        $severity_classes = array(
            'low' => 'severity-low',
            'medium' => 'severity-medium',
            'high' => 'severity-high',
            'critical' => 'severity-critical'
        );

        $class = isset($severity_classes[$severity]) ? $severity_classes[$severity] : 'severity-default';
        return sprintf(
            '<span class="severity-badge %s">%s</span>',
            esc_attr($class),
            esc_html(ucfirst($severity))
        );
    }

    private function schedule_cleanup_tasks() {
        if (!wp_next_scheduled('hlir_cleanup_old_incidents')) {
            wp_schedule_event(time(), 'daily', 'hlir_cleanup_old_incidents');
        }
    }

    public function cleanup_old_incidents() {
        $settings = get_option('hlir_settings');
        $days = isset($settings['auto_delete_days']) ? absint($settings['auto_delete_days']) : 0;
        
        if ($days > 0) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'hlir_incidents';
            
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $table_name 
                WHERE status = 'closed' 
                AND submitted_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            ));
        }
    }

}