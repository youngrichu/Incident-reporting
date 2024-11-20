<?php
if (!defined('ABSPATH')) {
    exit;
}

class HLIR_Admin {
    private $per_page = 20;
    private $current_tab = '';

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_hlir_update_status', array($this, 'ajax_update_status'));
        add_action('wp_ajax_hlir_bulk_action', array($this, 'handle_bulk_action'));
        add_action('wp_ajax_hlir_export_incidents', array($this, 'export_incidents'));
        add_action('wp_ajax_hlir_add_note', array($this, 'handle_add_note')); // Add this
        add_action('wp_ajax_hlir_delete_note', array($this, 'handle_delete_note')); // Add this
        add_action('wp_ajax_hlir_delete_incident', array($this, 'handle_incident_deletion'));
    
        // Handle form submissions
        add_action('admin_post_hlir_update_status', array($this, 'handle_status_update'));
        add_action('admin_post_hlir_delete_incident', array($this, 'handle_incident_deletion'));
        add_action('admin_post_hlir_export', array($this, 'handle_export')); // Add this
    
        // Screen options
        add_filter('set-screen-option', array($this, 'set_screen_option'), 10, 3);
        add_action('admin_head', array($this, 'add_screen_options'));
    }

    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'hlir') !== false) {
            wp_enqueue_style('hlir-admin-style', HLIR_PLUGIN_URL . 'assets/css/admin-style.css', array(), HLIR_VERSION);
            wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), HLIR_VERSION, true);
            wp_enqueue_script('hlir-admin-script', HLIR_PLUGIN_URL . 'assets/js/admin-script.js', array('jquery', 'chart-js'), HLIR_VERSION, true);
            
            // Add datepicker if needed
            wp_enqueue_script('jquery-ui-datepicker');
            wp_enqueue_style('jquery-ui-style', '//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');

            wp_localize_script('hlir-admin-script', 'hlir_admin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('hlir_admin_nonce'),
                'confirm_delete' => __('Are you sure you want to delete this incident?', 'hlir'),
                'confirm_bulk' => __('Are you sure you want to perform this action?', 'hlir')
            ));
        }
    }

    public function add_admin_menu() {
        $main_page = add_menu_page(
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
            'All Incidents',
            'All Incidents',
            'manage_options',
            'hlir-incidents'
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

        add_submenu_page(
            'hlir-incidents',
            'Export',
            'Export',
            'manage_options',
            'hlir-export',
            array($this, 'export_page')
        );

        // Add screen options
        add_action("load-$main_page", array($this, 'add_screen_options'));
    }

    public function add_screen_options() {
        $option = 'per_page';
        $args = array(
            'label' => 'Incidents per page',
            'default' => 20,
            'option' => 'incidents_per_page'
        );
        add_screen_option($option, $args);
    }

    public function set_screen_option($status, $option, $value) {
        if ('incidents_per_page' === $option) {
            return $value;
        }
        return $status;
    }

    public function incidents_page() {
        // Handle bulk actions
        $this->handle_bulk_actions();

        // Get current view/filter
        $view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'all';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        // Get pagination parameters
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = get_user_meta(get_current_user_id(), 'incidents_per_page', true);
        if (empty($per_page)) {
            $per_page = $this->per_page;
        }

        // Get filtered incidents
        $args = array(
            'limit' => $per_page,
            'offset' => ($page - 1) * $per_page,
            'view' => $view,
            'search' => $search
        );

        if (isset($_GET['incident_id'])) {
            $this->display_incident_details(intval($_GET['incident_id']));
            return;
        }

        $incidents = HLIR_DB::get_incidents($args);
        $total_items = HLIR_DB::get_incidents_count($args);
        $total_pages = ceil($total_items / $per_page);

        // Display the page
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Incident Reports</h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=hlir-export')); ?>" class="page-title-action">Export</a>
            <hr class="wp-header-end">

            <?php $this->display_notices(); ?>

            <!-- Filters -->
            <div class="tablenav top">
                <div class="alignleft actions">
                    <form method="get" action="">
                        <input type="hidden" name="page" value="hlir-incidents">
                        <?php
                        $this->render_bulk_actions_dropdown();
                        $this->render_filter_dropdowns();
                        submit_button(__('Apply', 'hlir'), 'action', '', false);
                        ?>
                    </form>
                </div>

                <!-- Search Box -->
                <form method="get" action="">
                    <input type="hidden" name="page" value="hlir-incidents">
                    <p class="search-box">
                        <label class="screen-reader-text" for="incident-search-input">Search Incidents:</label>
                        <input type="search" id="incident-search-input" name="s" value="<?php echo esc_attr($search); ?>">
                        <input type="submit" id="search-submit" class="button" value="Search Incidents">
                    </p>
                </form>

                <!-- Pagination -->
                <?php $this->render_pagination($total_items, $total_pages, $page); ?>
            </div>

            <form method="post" id="incidents-filter">
                <?php wp_nonce_field('bulk-incidents'); ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <?php $this->render_table_headers(); ?>
                    </thead>
                    <tbody>
                        <?php
                        if ($incidents) {
                            foreach ($incidents as $incident) {
                                $this->render_incident_row($incident);
                            }
                        } else {
                            echo '<tr><td colspan="8">No incidents found.</td></tr>';
                        }
                        ?>
                    </tbody>
                    <tfoot>
                        <?php $this->render_table_headers(); ?>
                    </tfoot>
                </table>
            </form>

            <!-- Bottom Pagination -->
            <div class="tablenav bottom">
                <?php $this->render_pagination($total_items, $total_pages, $page); ?>
            </div>
        </div>
        <?php
    }

    private function render_bulk_actions_dropdown() {
        ?>
        <select name="action" id="bulk-action-selector-top">
            <option value="-1">Bulk Actions</option>
            <option value="delete">Delete</option>
            <option value="mark_resolved">Mark as Resolved</option>
            <option value="mark_closed">Mark as Closed</option>
        </select>
        <?php
    }

    private function render_filter_dropdowns() {
        $severity_options = array(
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'critical' => 'Critical'
        );

        $status_options = array(
            'new' => 'New',
            'in_progress' => 'In Progress',
            'resolved' => 'Resolved',
            'closed' => 'Closed'
        );

        $selected_severity = isset($_GET['severity']) ? sanitize_text_field($_GET['severity']) : '';
        $selected_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        ?>
        <select name="severity">
            <option value="">All Severities</option>
            <?php
            foreach ($severity_options as $value => $label) {
                printf(
                    '<option value="%s" %s>%s</option>',
                    esc_attr($value),
                    selected($value, $selected_severity, false),
                    esc_html($label)
                );
            }
            ?>
        </select>

        <select name="status">
            <option value="">All Statuses</option>
            <?php
            foreach ($status_options as $value => $label) {
                printf(
                    '<option value="%s" %s>%s</option>',
                    esc_attr($value),
                    selected($value, $selected_status, false),
                    esc_html($label)
                );
            }
            ?>
        </select>
        <?php
    }

    private function render_table_headers() {
        ?>
        <tr>
            <td class="manage-column column-cb check-column">
                <input type="checkbox" id="cb-select-all-1">
            </td>
            <th scope="col" class="manage-column column-id">ID</th>
            <th scope="col" class="manage-column column-reporter">Reporter</th>
            <th scope="col" class="manage-column column-type">Type</th>
            <th scope="col" class="manage-column column-severity">Severity</th>
            <th scope="col" class="manage-column column-status">Status</th>
            <th scope="col" class="manage-column column-date">Date</th>
            <th scope="col" class="manage-column column-actions">Actions</th>
        </tr>
        <?php
    }

    private function render_incident_row($incident) {
        ?>
        <tr>
            <th scope="row" class="check-column">
                <input type="checkbox" name="incidents[]" value="<?php echo esc_attr($incident->id); ?>">
            </th>
            <td>
                #<?php echo esc_html($incident->id); ?>
            </td>
            <td>
                <strong>
                    <a href="?page=hlir-incidents&incident_id=<?php echo esc_attr($incident->id); ?>">
                        <?php echo esc_html($incident->name); ?>
                    </a>
                </strong>
                <br>
                <small><?php echo esc_html($incident->email); ?></small>
            </td>
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
            <td>
                <?php echo esc_html(date('M j, Y', strtotime($incident->submitted_at))); ?><br>
                <small><?php echo esc_html(date('H:i', strtotime($incident->submitted_at))); ?></small>
            </td>
            <td class="actions">
                <a href="?page=hlir-incidents&incident_id=<?php echo esc_attr($incident->id); ?>" 
                   class="button button-small">
                    View Details
                </a>
                <!-- Remove or comment out the delete button -->
                <!--
                <button type="button" 
                        class="button button-small delete-incident" 
                        data-id="<?php echo esc_attr($incident->id); ?>"
                        data-nonce="<?php echo wp_create_nonce('delete_incident_' . $incident->id); ?>">
                    Delete
                </button>
                -->
            </td>
        </tr>
        <?php
    }

    private function render_pagination($total_items, $total_pages, $current_page) {
        $page_links = paginate_links(array(
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'prev_text' => __('&laquo;'),
            'next_text' => __('&raquo;'),
            'total' => $total_pages,
            'current' => $current_page
        ));

        if ($page_links) {
            echo '<div class="tablenav-pages">';
            echo '<span class="displaying-num">' . sprintf(_n('%s item', '%s items', $total_items), number_format_i18n($total_items)) . '</span>';
            echo $page_links;
            echo '</div>';
        }
    }

    private function get_file_icon($mime_type) {
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

    public function display_incident_details($incident_id) {
        $incident = HLIR_DB::get_incident_by_id($incident_id);
        if (!$incident) {
            wp_die('Incident not found');
        }

        ?>
        <div class="wrap">
            <h1>
                Incident #<?php echo esc_html($incident_id); ?>
                <a href="?page=hlir-incidents" class="page-title-action">← Back to List</a>
            </h1>

            <?php $this->display_notices(); ?>

            <div class="hlir-incident-details">
                <div class="hlir-incident-header">
                    <div class="hlir-incident-meta">
                        <span class="severity-badge severity-<?php echo esc_attr($incident->severity); ?>">
                            <?php echo esc_html(ucfirst($incident->severity)); ?>
                        </span>
                        <span class="status-badge status-<?php echo esc_attr($incident->status); ?>">
                            <?php echo esc_html(ucwords(str_replace('_', ' ', $incident->status))); ?>
                        </span>
                        <span class="hlir-incident-date">
                            Submitted: <?php echo esc_html(date('M j, Y H:i', strtotime($incident->submitted_at))); ?>
                        </span>
                    </div>
                </div>

                <div class="hlir-incident-content">
                    <!-- Incident Details Section -->
                    <div class="hlir-incident-section">
                        <h2>Incident Details</h2>
                        <table class="form-table">
                            <tr>
                                <th>Type:</th>
                                <td><?php echo esc_html(ucwords(str_replace('_', ' ', $incident->incident_type))); ?></td>
                            </tr>
                            <tr>
                                <th>Description:</th>
                                <td><?php echo nl2br(esc_html($incident->description)); ?></td>
                            </tr>
                            <tr>
                                <th>Severity:</th>
                                <td>
                                    <span class="severity-badge severity-<?php echo esc_attr($incident->severity); ?>">
                                        <?php echo esc_html(ucfirst($incident->severity)); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr($incident->status); ?>">
                                        <?php echo esc_html(ucwords(str_replace('_', ' ', $incident->status))); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Actions Already Taken:</th>
                                <td><?php echo nl2br(esc_html($incident->actions_taken)); ?></td>
                            </tr>
                        </table>
                    </div>

                    <!-- Update Status Section -->
                    <div class="hlir-incident-section">
                        <h2>Update Status</h2>
                        <form method="post" class="hlir-status-form">
                            <?php wp_nonce_field('hlir_update_status', 'hlir_status_nonce'); ?>
                            <input type="hidden" name="incident_id" value="<?php echo esc_attr($incident_id); ?>">
                            <select name="status" class="hlir-status-select">
                                <option value="new" <?php selected($incident->status, 'new'); ?>>New</option>
                                <option value="in_progress" <?php selected($incident->status, 'in_progress'); ?>>In Progress</option>
                                <option value="resolved" <?php selected($incident->status, 'resolved'); ?>>Resolved</option>
                                <option value="closed" <?php selected($incident->status, 'closed'); ?>>Closed</option>
                            </select>
                            <button type="submit" class="button button-primary">Update Status</button>
                        </form>
                    </div>

                    <!-- Attachments Section -->
                    <div class="hlir-incident-section">
                        <h2>Attachments</h2>
                        <?php
                        $attachments = HLIR_DB::get_incident_attachments($incident_id);
                        if (empty($attachments)) {
                            echo '<p>No attachments found.</p>';
                        } else {
                            ?>
                            <div class="hlir-attachments-list">
                                <?php foreach ($attachments as $attachment): ?>
                                    <div class="hlir-attachment-item">
                                        <div class="attachment-icon">
                                            <?php echo $this->get_file_icon($attachment->file_type); ?>
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
                                            )); ?>" class="button button-secondary">
                                                <span class="dashicons dashicons-download"></span> Download
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php
                        }
                        ?>
                    </div>

                    <!-- Activity Log Section -->
                    <div class="hlir-incident-section">
                        <h2>Activity Log</h2>
                        <?php $this->display_activity_log($incident_id); ?>
                    </div>

                    <!-- Notes Section -->
                    <div class="hlir-incident-section">
                        <h2>Notes</h2>
                        <?php $this->display_notes($incident_id); ?>
                    </div>

                    <div class="hlir-add-note-section">
                        <h3>Add a Note</h3>
                        <form class="hlir-add-note-form">
                            <input type="hidden" name="incident_id" value="<?php echo esc_attr($incident->id); ?>">
                            <textarea name="note_content" required placeholder="Enter your note here..."></textarea>
                            <button type="submit" class="button button-primary">Add Note</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    

    private function display_activity_log($incident_id) {
        $activities = HLIR_DB::get_incident_activities($incident_id);
        if (empty($activities)) {
            echo '<p>No activity recorded yet.</p>';
            return;
        }

        echo '<ul class="hlir-activity-log">';
        foreach ($activities as $activity) {
            printf(
                '<li class="activity-%s">
                    <span class="activity-time">%s</span>
                    <span class="activity-description">%s</span>
                    <span class="activity-user">by %s</span>
                </li>',
                esc_attr($activity->type),
                esc_html(date('M j, Y H:i', strtotime($activity->created_at))),
                esc_html($activity->description),
                esc_html($activity->user_name)
            );
        }
        echo '</ul>';
    }

    private function display_notes($incident_id) {
        $notes = HLIR_DB::get_incident_notes($incident_id);
        if (empty($notes)) {
            echo '<p>No notes added yet.</p>';
            return;
        }

        echo '<ul class="hlir-notes-list">';
        foreach ($notes as $note) {
            printf(
                '<li class="note-item">
                    <div class="note-header">
                        <span class="note-author">%s</span>
                        <span class="note-date">%s</span>
                    </div>
                    <div class="note-content">%s</div>
                </li>',
                esc_html($note->user_name),
                esc_html(date('M j, Y H:i', strtotime($note->created_at))),
                nl2br(esc_html($note->content))
            );
        }
        echo '</ul>';
    }

    public function analytics_page() {
        require_once HLIR_PLUGIN_DIR . 'templates/admin/analytics.php';
    }
    public function handle_bulk_actions() {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'bulk-incidents')) {
            return;
        }

        if (!isset($_POST['action']) || $_POST['action'] === '-1') {
            return;
        }

        if (empty($_POST['incidents'])) {
            return;
        }

        $action = sanitize_text_field($_POST['action']);
        $incident_ids = array_map('intval', $_POST['incidents']);

        switch ($action) {
            case 'delete':
                foreach ($incident_ids as $id) {
                    HLIR_DB::delete_incident($id);
                }
                $message = sprintf(_n('%s incident deleted.', '%s incidents deleted.', count($incident_ids)), count($incident_ids));
                break;

            case 'mark_resolved':
                foreach ($incident_ids as $id) {
                    HLIR_DB::update_incident_status($id, 'resolved');
                }
                $message = sprintf(_n('%s incident marked as resolved.', '%s incidents marked as resolved.', count($incident_ids)), count($incident_ids));
                break;

            case 'mark_closed':
                foreach ($incident_ids as $id) {
                    HLIR_DB::update_incident_status($id, 'closed');
                }
                $message = sprintf(_n('%s incident marked as closed.', '%s incidents marked as closed.', count($incident_ids)), count($incident_ids));
                break;
        }

        if (isset($message)) {
            add_settings_error('hlir_messages', 'hlir_bulk_action', $message, 'updated');
        }
    }

    public function ajax_update_status() {
        check_ajax_referer('hlir_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $incident_id = isset($_POST['incident_id']) ? intval($_POST['incident_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

        if (!$incident_id || !$status) {
            wp_send_json_error('Missing required fields');
        }

        $result = HLIR_DB::update_incident_status($incident_id, $status);

        if ($result !== false) {
            // Log the status change
            HLIR_DB::log_activity($incident_id, 'status_change', sprintf(
                'Status changed to %s',
                ucwords(str_replace('_', ' ', $status))
            ));

            wp_send_json_success('Status updated successfully');
        } else {
            wp_send_json_error('Failed to update status');
        }
    }

    public function handle_status_update() {
        check_admin_referer('hlir_update_status', 'hlir_status_nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $incident_id = isset($_POST['incident_id']) ? intval($_POST['incident_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

        if (!$incident_id || !$status) {
            wp_die('Missing required fields');
        }

        $result = HLIR_DB::update_incident_status($incident_id, $status);

        if ($result !== false) {
            // Log the status change
            HLIR_DB::log_activity($incident_id, 'status_change', sprintf(
                'Status changed to %s',
                ucwords(str_replace('_', ' ', $status))
            ));

            wp_redirect(add_query_arg(array(
                'page' => 'hlir-incidents',
                'incident_id' => $incident_id,
                'status_updated' => 1
            ), admin_url('admin.php')));
            exit;
        } else {
            wp_die('Failed to update status');
        }
    }

    public function export_incidents() {
        check_admin_referer('hlir_export_incidents', 'export_nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $format = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : 'csv';
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

        $args = array(
            'start_date' => $start_date,
            'end_date' => $end_date,
            'status' => $status
        );

        $incidents = HLIR_DB::get_incidents($args);

        if ($format === 'json') {
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="incidents-export.json"');
            echo json_encode($incidents);
        } else {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="incidents-export.csv"');
            
            $output = fopen('php://output', 'w');
            
            // Add CSV headers
            fputcsv($output, array(
                'ID',
                'Reporter Name',
                'Email',
                'Incident Type',
                'Severity',
                'Status',
                'Description',
                'Date',
                'Submitted At'
            ));

            // Add data rows
            foreach ($incidents as $incident) {
                fputcsv($output, array(
                    $incident->id,
                    $incident->name,
                    $incident->email,
                    $incident->incident_type,
                    $incident->severity,
                    $incident->status,
                    $incident->description,
                    $incident->date,
                    $incident->submitted_at
                ));
            }

            fclose($output);
        }
        exit;
    }

    private function display_notices() {
        settings_errors('hlir_messages');
    }
    public function handle_add_note() {
        check_ajax_referer('hlir_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $incident_id = isset($_POST['incident_id']) ? intval($_POST['incident_id']) : 0;
        $content = isset($_POST['content']) ? sanitize_textarea_field($_POST['content']) : '';

        if (!$incident_id || !$content) {
            wp_send_json_error('Missing required fields');
        }

        $result = HLIR_DB::add_note($incident_id, $content);

        if ($result) {
            wp_send_json_success('Note added successfully');
        } else {
            wp_send_json_error('Failed to add note');
        }
    }
    public function handle_delete_note() {
        check_ajax_referer('hlir_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $note_id = isset($_POST['note_id']) ? intval($_POST['note_id']) : 0;

        if (!$note_id) {
            wp_send_json_error('Missing note ID');
        }

        $result = HLIR_DB::delete_note($note_id);

        if ($result) {
            wp_send_json_success('Note deleted successfully');
        } else {
            wp_send_json_error('Failed to delete note');
        }
    }
    public function handle_export() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('hlir_export', 'export_nonce');

        $format = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : 'csv';
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

        $args = array(
            'start_date' => $start_date,
            'end_date' => $end_date,
            'status' => $status
        );

        $incidents = HLIR_DB::get_incidents($args);

        if ($format === 'json') {
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="incidents-export.json"');
            echo json_encode($incidents);
            exit;
        }

        // Default to CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="incidents-export.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add CSV headers
        fputcsv($output, array(
            'ID',
            'Reporter Name',
            'Email',
            'Incident Type',
            'Severity',
            'Status',
            'Description',
            'Date',
            'Submitted At'
        ));

        // Add data rows
        foreach ($incidents as $incident) {
            fputcsv($output, array(
                $incident->id,
                $incident->name,
                $incident->email,
                $incident->incident_type,
                $incident->severity,
                $incident->status,
                $incident->description,
                $incident->date,
                $incident->submitted_at
            ));
        }

        fclose($output);
        exit;
    }

    public function export_page() {
        ?>
        <div class="wrap">
            <h1>Export Incident Reports</h1>
            
            <div class="card">
                <h2>Export Options</h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="hlir-export-form">
                    <?php wp_nonce_field('hlir_export', 'export_nonce'); ?>
                    <input type="hidden" name="action" value="hlir_export">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Date Range</th>
                            <td>
                                <input type="date" name="start_date" class="hlir-date-picker">
                                to
                                <input type="date" name="end_date" class="hlir-date-picker">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Status</th>
                            <td>
                                <select name="status">
                                    <option value="">All Statuses</option>
                                    <option value="new">New</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="resolved">Resolved</option>
                                    <option value="closed">Closed</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Format</th>
                            <td>
                                <select name="format">
                                    <option value="csv">CSV</option>
                                    <option value="json">JSON</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary">Export Data</button>
                    </p>
                </form>
            </div>
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

    public function general_settings_callback() {
        echo '<p>Configure the general settings for the incident reporting system.</p>';
    }

    public function notification_email_callback() {
        $settings = get_option('hlir_settings');
        if (is_array($settings)) {
            // Access settings safely
            $notification_email = $settings['notification_email'];
        } else {
            $notification_email = ''; // Default value or handle the error
        }
        ?>
        <input type="email" name="hlir_settings[notification_email]" 
               value="<?php echo esc_attr($notification_email); ?>" 
               class="regular-text">
        <p class="description">Email address where incident notifications will be sent.</p>
        <?php
    }

    public function form_title_callback() {
        $settings = get_option('hlir_settings');
        if (is_array($settings)) {
            $form_title = $settings['form_title'];
        } else {
            $form_title = 'Default Form Title'; // Default value or handle the error
        }
        ?>
        <input type="text" name="hlir_settings[form_title]" 
               value="<?php echo esc_attr($form_title); ?>" 
               class="regular-text">
        <p class="description">Title displayed above the incident report form.</p>
        <?php
    }

    public function success_message_callback() {
        $settings = get_option('hlir_settings');
        if (is_array($settings)) {
            $success_message = $settings['success_message'];
        } else {
            $success_message = 'Thank you for reporting the incident.'; // Default value or handle the error
        }
        ?>
        <textarea name="hlir_settings[success_message]" class="large-text" rows="3"><?php 
            echo esc_textarea($success_message); 
        ?></textarea>
        <p class="description">Message shown after successful submission of an incident report.</p>
        <?php
    }

    public function handle_incident_deletion() {
        check_ajax_referer('delete_incident_' . $_POST['incident_id'], 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
        }
    
        $incident_id = isset($_POST['incident_id']) ? intval($_POST['incident_id']) : 0;
        if (!$incident_id) {
            wp_send_json_error('Invalid incident ID');
        }
    
        $result = HLIR_DB::delete_incident($incident_id);
    
        if ($result) {
            wp_send_json_success('Incident deleted successfully');
        } else {
            wp_send_json_error('Failed to delete incident');
        }
    }

    public function sanitize_settings($input) {
        $sanitized = array();
        
        if (isset($input['notification_email'])) {
            $sanitized['notification_email'] = sanitize_email($input['notification_email']);
        }
        
        if (isset($input['form_title'])) {
            $sanitized['form_title'] = sanitize_text_field($input['form_title']);
        }
        
        if (isset($input['success_message'])) {
            $sanitized['success_message'] = wp_kses_post($input['success_message']);
        }
        
        return $sanitized;
    }
}

