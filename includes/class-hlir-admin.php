<?php
class HLIR_Admin {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'handle_status_update')); // Add this line
    }

    public function add_admin_menu() {
        add_menu_page(
            'Incident Reports',
            'Incident Reports',
            'manage_options', // Ensure this matches the capability needed
            'hlir-incidents',
            array($this, 'incidents_page'),
            'dashicons-shield',
            30
        );

        // Remove the Incident Details submenu
        /*
        add_submenu_page(
            'hlir-incidents',
            'Incident Details',
            'Incident Details',
            'manage_options', // Ensure this matches the capability needed
            'hlir-incident-details',
            array($this, 'display_incident_details') // Ensure this method is called
        );
        */

        add_submenu_page(
            'hlir-incidents',
            'Settings',
            'Settings',
            'manage_options',
            'hlir-settings',
            array($this, 'settings_page')
        );

        add_submenu_page(
            'hlir-incidents',
            'Analytics',
            'Analytics',
            'manage_options',
            'hlir-analytics',
            array($this, 'analytics_page')
        );
    }

    public function incidents_page() {
        $incidents = HLIR_DB::get_incidents();
        ?>
        <div class="wrap">
            <h1>Incident Reports</h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th> <!-- Title column -->
                        <th>Name</th>
                        <th>Incident Type</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($incidents as $incident) : ?>
                        <tr>
                            <td><a href="<?php echo admin_url('admin.php?page=hlir-incidents&incident_id=' . $incident->id); ?>"><?php echo esc_html($incident->id); ?></a></td>
                            <td><a href="<?php echo admin_url('admin.php?page=hlir-incidents&incident_id=' . $incident->id); ?>"><?php echo esc_html($incident->name . ' - ' . $incident->incident_type); ?></a></td> <!-- Clickable title -->
                            <td><?php echo esc_html($incident->name); ?></td>
                            <td><?php echo esc_html($incident->incident_type); ?></td>
                            <td><?php echo esc_html($incident->date); ?></td>
                            <td><?php echo esc_html($incident->status); ?></td>
                            <td><?php echo esc_html(wp_trim_words($incident->description, 10)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php
            // Check if an incident ID is set in the URL
            if (isset($_GET['incident_id'])) {
                $this->display_incident_details(); // Call the method to display details
            }
            ?>
        </div>
        <?php
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
        register_setting('hlir_settings', 'hlir_settings');

        add_settings_section(
            'hlir_general_settings',
            'General Settings',
            array($this, 'general_settings_callback'),
            'hlir_settings'
        );

        add_settings_field(
            'notification_email',
            'Notification Email',
            array($this, 'notification_email_callback'),
            'hlir_settings',
            'hlir_general_settings'
        );

        add_settings_field(
            'form_title',
            'Form Title',
            array($this, 'form_title_callback'),
            'hlir_settings',
            'hlir_general_settings'
        );

        add_settings_field(
            'success_message',
            'Success Message',
            array($this, 'success_message_callback'),
            'hlir_settings',
            'hlir_general_settings'
        );
    }

    public function general_settings_callback() {
        echo '<p>Configure the general settings for the incident reporting system.</p>';
    }

    public function notification_email_callback() {
        $settings = get_option('hlir_settings');
        echo '<input type="email" name="hlir_settings[notification_email]" value="' . esc_attr($settings['notification_email']) . '">';
    }

    public function form_title_callback() {
        $settings = get_option('hlir_settings');
        echo '<input type="text" name="hlir_settings[form_title]" value="' . esc_attr($settings['form_title']) . '">';
    }

    public function success_message_callback() {
        $settings = get_option('hlir_settings');
        echo '<textarea name="hlir_settings[success_message]">' . esc_textarea($settings['success_message']) . '</textarea>';
    }

    public function analytics_page() {
        $stats = HLIR_Analytics::get_incident_stats();
        ?>
        <div class="wrap">
            <h1>Incident Analytics</h1>
            <canvas id="incidentChart" width="400" height="200"></canvas>
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
                const ctx = document.getElementById('incidentChart').getContext('2d');
                const incidentChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($stats['labels']); ?>,
                        datasets: [{
                            label: 'Number of Incidents',
                            data: <?php echo json_encode($stats['data']); ?>,
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            </script>
        </div>
        <?php
    }

    public function display_incident_details() {
        if (isset($_GET['incident_id'])) {
            $incident_id = intval($_GET['incident_id']);
            $incident = HLIR_DB::get_incident_by_id($incident_id);
            ?>
            <div class="wrap">
                <h1>Incident Details</h1>
                <p><strong>Name:</strong> <?php echo esc_html($incident->name); ?></p>
                <p><strong>Email:</strong> <?php echo esc_html($incident->email); ?></p>
                <p><strong>Incident Type:</strong> <?php echo esc_html($incident->incident_type); ?></p>
                <p><strong>Description:</strong> <?php echo esc_html($incident->description); ?></p>
                <p><strong>Date:</strong> <?php echo esc_html($incident->date); ?></p>
                <p><strong>Status:</strong> <?php echo esc_html($incident->status); ?></p>

                <form method="post" action="">
                    <input type="hidden" name="incident_id" value="<?php echo esc_attr($incident_id); ?>">
                    <label for="incident-status">Change Status:</label>
                    <select name="incident_status" id="incident-status">
                        <option value="new" <?php selected($incident->status, 'new'); ?>>New</option>
                        <option value="in_progress" <?php selected($incident->status, 'in_progress'); ?>>In Progress</option>
                        <option value="resolved" <?php selected($incident->status, 'resolved'); ?>>Resolved</option>
                        <option value="closed" <?php selected($incident->status, 'closed'); ?>>Closed</option>
                    </select>
                    <input type="submit" value="Update Status" class="button button-primary">
                </form>
            </div>
            <?php
        } else {
            wp_die('No incident ID provided.');
        }
    }

    public function handle_status_update() {
        if (isset($_POST['incident_id']) && isset($_POST['incident_status'])) {
            $incident_id = intval($_POST['incident_id']);
            $status = sanitize_text_field($_POST['incident_status']);
            HLIR_DB::update_incident_status($incident_id, $status);
            // Redirect back to the incidents page with a success message
            set_transient('hlir_status_update_message', 'Incident status updated successfully.', 30);
            wp_redirect(admin_url('admin.php?page=hlir-incidents'));
            exit;
        }
    }
}
