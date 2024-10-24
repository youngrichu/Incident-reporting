// assets/js/form-script.js

jQuery(document).ready(function($) {
    if ($('#hlir-incident-form').length > 0) {
        console.log('Incident report form initialized');

        // Disable autocomplete on honeypot field
        $('#hlir_website').attr('autocomplete', 'off');
        
        // Clear honeypot field on load
        $('#hlir_website').val('');

        // Prevent paste into honeypot
        $('#hlir_website').on('paste', function(e) {
            e.preventDefault();
            return false;
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

        // File upload validation
        $('#hlir-attachments').on('change', function(e) {
            const files = e.target.files;
            let hasError = false;
            let errorMessage = '';
            const maxSize = parseInt(hlir_settings.max_file_size);
            const allowedTypes = hlir_settings.allowed_types;

            // Reset file input if needed
            const resetFileInput = () => {
                $(this).val('');
                // For IE/Edge
                if (this.value) {
                    try {
                        this.value = ''; // For IE11, latest Chrome/Firefox/Opera
                    } catch(ex) { }
                    if (this.value) { // For IE9/10
                        var form = document.createElement('form'),
                            ref = this.nextSibling,
                            p = this.parentNode;
                        form.appendChild(this);
                        form.reset();
                        p.insertBefore(this,ref);
                    }
                }
            };

            // Check each file
            Array.from(files).forEach(file => {
                const extension = file.name.split('.').pop().toLowerCase();
                
                // Check file size
                if (file.size > maxSize) {
                    hasError = true;
                    errorMessage = `File "${file.name}" exceeds the maximum size limit of ${(maxSize / (1024 * 1024)).toFixed(1)}MB`;
                }
                
                // Check file type
                if (!allowedTypes.includes(extension)) {
                    hasError = true;
                    errorMessage = `File type "${extension}" is not allowed`;
                }
            });

            if (hasError) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid File',
                    text: errorMessage,
                    confirmButtonColor: '#3085d6'
                });
                resetFileInput();
                return false;
            }
        });

        // Form submission
        $('#hlir-incident-form').on('submit', function(e) {
            e.preventDefault();
            
            const form = $(this);
            const submitButton = form.find('.hlir-submit-btn');
            
            // Show loading state
            Swal.fire({
                title: 'Submitting Report',
                html: `
                    <div class="upload-progress">
                        <div class="progress-bar"></div>
                        <div class="progress-text">Processing your submission...</div>
                    </div>
                `,
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Prepare form data
            const formData = new FormData(form[0]);
            formData.append('action', 'hlir_submit_incident');
            formData.append('security', hlir_ajax.nonce);
            
            // Submit form via AJAX
            $.ajax({
                url: hlir_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            const percent = Math.round((e.loaded / e.total) * 100);
                            $('.progress-bar').css('width', percent + '%');
                            $('.progress-text').text(`Uploading... ${percent}%`);
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    console.log('Form submission response:', response);
                    
                    if (response.success) {
                        const incidentId = response.data.incident_id || '';
                        const referenceText = incidentId ? `Reference ID: #${incidentId}` : '';
                        
                        Swal.fire({
                            icon: 'success',
                            title: 'Incident Reported Successfully',
                            html: `
                                <div class="hlir-success-message">
                                    <p>${response.data.message}</p>
                                    ${referenceText ? `<p class="reference-id">${referenceText}</p>` : ''}
                                    <p class="next-steps">Our team will review your report and take appropriate action.</p>
                                </div>
                            `,
                            confirmButtonText: 'Done',
                            confirmButtonColor: '#3182ce',
                            showCancelButton: true,
                            cancelButtonText: 'Report Another Incident',
                            cancelButtonColor: '#718096',
                            allowOutsideClick: false
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = window.location.pathname;
                            } else {
                                form[0].reset();
                                $('#hlir-severity').css('border-left-color', '');
                                if (typeof grecaptcha !== 'undefined') {
                                    grecaptcha.reset();
                                }
                                $('html, body').animate({
                                    scrollTop: form.offset().top - 50
                                }, 500);
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Submission Error',
                            text: response.data.message || 'An error occurred while submitting the form. Please try again.',
                            confirmButtonColor: '#f56565'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Form submission error:', {xhr, status, error});
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Connection Error',
                        text: 'Unable to submit your report due to a connection error. Please check your internet connection and try again.',
                        confirmButtonColor: '#f56565'
                    });
                },
                complete: function() {
                    submitButton.prop('disabled', false);
                }
            });
        });
    }
});