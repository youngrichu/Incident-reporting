jQuery(document).ready(function($) {
    // Status update handling
    $('.hlir-status-form').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const statusSelect = form.find('select[name="incident_status"]');
        const submitButton = form.find('button[type="submit"]');
        
        // Disable form while processing
        statusSelect.prop('disabled', true);
        submitButton.prop('disabled', true);
        
        const formData = new FormData(form[0]);
        formData.append('action', 'hlir_update_status');
        formData.append('security', hlir_admin.nonce);
        
        $.ajax({
            url: hlir_admin.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // Show success message
                    alert('Status updated successfully');
                    // Reload page to reflect changes
                    location.reload();
                } else {
                    alert('Error updating status: ' + response.data.message);
                }
            },
            error: function() {
                alert('An error occurred while updating the status');
            },
            complete: function() {
                // Re-enable form
                statusSelect.prop('disabled', false);
                submitButton.prop('disabled', false);
            }
        });
    });

    // Initialize datepickers if present
    if ($.fn.datepicker) {
        $('.hlir-date-picker').datepicker({
            dateFormat: 'yy-mm-dd'
        });
    }

    // Debug log
    console.log('Admin scripts loaded');
});