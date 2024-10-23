jQuery(document).ready(function($) {
    // Status update handling
    $('.hlir-status-form').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const statusSelect = form.find('select[name="status"]');
        const submitButton = form.find('button[type="submit"]');
        
        // Disable form elements while processing
        statusSelect.prop('disabled', true);
        submitButton.prop('disabled', true).text('Updating...');
        
        // Debug
        console.log('Updating status...');
        console.log('Form data:', {
            action: 'hlir_update_status',
            nonce: hlir_admin.nonce,
            incident_id: form.find('input[name="incident_id"]').val(),
            status: statusSelect.val()
        });

        // Make AJAX request
        $.ajax({
            url: hlir_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'hlir_update_status',
                nonce: hlir_admin.nonce,
                incident_id: form.find('input[name="incident_id"]').val(),
                status: statusSelect.val()
            },
            success: function(response) {
                console.log('Response:', response);
                if (response.success) {
                    // Show success message and reload page
                    window.location.reload();
                } else {
                    alert('Error updating status: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                alert('An error occurred while updating the status');
            },
            complete: function() {
                // Re-enable form elements
                statusSelect.prop('disabled', false);
                submitButton.prop('disabled', false).text('Update Status');
            }
        });
    });

    // Handle incident deletion
    $('.delete-incident').on('click', function(e) {
        e.preventDefault();
        if (confirm(hlir_admin.confirm_delete)) {
            const button = $(this);
            const incident_id = button.data('id');
            const nonce = button.data('nonce');
            
            $.ajax({
                url: hlir_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'hlir_delete_incident',
                    incident_id: incident_id,
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        window.location.reload();
                    } else {
                        alert('Error deleting incident: ' + response.data);
                    }
                }
            });
        }
    });

    // Notes system
    $('.hlir-add-note-form').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const submitButton = form.find('button[type="submit"]');
        const textarea = form.find('textarea[name="note_content"]');
        
        submitButton.prop('disabled', true);
        
        $.ajax({
            url: hlir_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'hlir_add_note',
                nonce: hlir_admin.nonce,
                incident_id: form.find('input[name="incident_id"]').val(),
                content: textarea.val()
            },
            success: function(response) {
                if (response.success) {
                    textarea.val('');
                    window.location.reload();
                } else {
                    alert('Error adding note: ' + response.data);
                }
            },
            complete: function() {
                submitButton.prop('disabled', false);
            }
        });
    });

    // Export form handling
    $('#hlir-export-form').on('submit', function(e) {
        const form = $(this);
        const submitButton = form.find('button[type="submit"]');
        
        // Show processing message
        submitButton.prop('disabled', true).text('Processing...');
        
        // Form will submit normally - no need to prevent default
        setTimeout(function() {
            submitButton.prop('disabled', false).text('Export Data');
        }, 2000);
    });

    // Initialize datepicker if available
    if ($.fn.datepicker) {
        $('.hlir-date-picker').datepicker({
            dateFormat: 'yy-mm-dd'
        });
    }
});