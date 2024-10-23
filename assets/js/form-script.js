jQuery(document).ready(function($) {
    if ($('#hlir-incident-form').length > 0) {
        console.log('Form found and script initialized');

        $('#hlir-incident-form').on('submit', function(e) {
            e.preventDefault();
            console.log('Form submitted');
            
            const form = $(this);
            const submitButton = form.find('.hlir-submit-btn');
            
            // Show loading state
            Swal.fire({
                title: 'Submitting Report',
                text: 'Please wait...',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Collect form data
            const formData = new FormData(form[0]);
            formData.append('action', 'hlir_submit_incident');
            formData.append('security', hlir_ajax.nonce);
            
            // Debug: Log form data
            console.log('Form data:', Object.fromEntries(formData));
            console.log('AJAX URL:', hlir_ajax.ajax_url);
            
            // Submit form via AJAX
            $.ajax({
                url: hlir_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    console.log('AJAX Response:', response);
                    
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: response.data.message,
                            confirmButtonColor: '#3182ce'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                form[0].reset();
                                $('#hlir-severity').css('border-left-color', '');
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.data.message || 'An error occurred while submitting the form.',
                            confirmButtonColor: '#f56565'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', {xhr, status, error});
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An unexpected error occurred. Please try again.',
                        confirmButtonColor: '#f56565'
                    });
                }
            });
        });

        // Add severity color indicators
        $('#hlir-severity').on('change', function() {
            const severity = $(this).val();
            const colors = {
                'low': '#4CAF50',
                'medium': '#FF9800',
                'high': '#f44336',
                'critical': '#9C27B0'
            };
            $(this).css('border-left-color', colors[severity] || '#e2e8f0');
        });
    } else {
        console.log('Form not found on page');
    }
});