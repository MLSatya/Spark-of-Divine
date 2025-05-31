jQuery(document).ready(function($) {
    // Toggle bulk delete section
    $('#toggle-bulk-delete').on('click', function() {
        $('#bulk-delete-section').slideToggle();
    });

    // Show/hide filter options based on filter type
    $('#bulk-delete-filter-type').on('change', function() {
        var type = $(this).val();
        $('.service-filter, .month-filter, .range-filter').hide();
        if (type === 'service') {
            $('.service-filter').show();
        } else if (type === 'month') {
            $('.month-filter').show();
        } else if (type === 'range') {
            $('.range-filter').show();
        }
    });
    
    // Handle form submission for bulk delete
    $('#sod-bulk-delete-form').on('submit', function(e) {
        e.preventDefault();
        
        if (!confirm('WARNING: This will permanently delete ALL matching availability slots for this staff member. This action CANNOT be undone. Are you sure you want to continue?')) {
            return false;
        }
        
        var $form = $(this);
        var $submitButton = $('#bulk-delete-submit');
        var $spinner = $form.find('.spinner');
        var $results = $('#bulk-delete-results');
        
        // Get the staff ID - ensure this is correctly set in the form
        var staffId = $form.find('input[name="staff_id"]').val();
        console.log('Submitting bulk delete for staff ID: ' + staffId);
        
        if (!staffId) {
            $results
                .html('<p><strong>Error:</strong> No staff member selected.</p>')
                .removeClass('results-success')
                .addClass('results-error')
                .show();
            return;
        }
        
        // Disable submit button and show spinner
        $submitButton.prop('disabled', true);
        $spinner.addClass('is-active');
        $results.hide();
        
        // Collect form data
        var formData = new FormData(this);
        formData.append('action', 'bulk_delete_availability_slots');
        formData.append('sod_availability_nonce', sodAvailability.nonce);
        
        // Log form data for debugging
        console.log('Form Data:', Object.fromEntries(formData));
        
        // Validate form data before sending
        var filterType = formData.get('filter_type');
        var serviceId = formData.get('service_id');
        var month = formData.get('month');
        var startDate = formData.get('start_date');
        var endDate = formData.get('end_date');
        
        if (!filterType) {
            $results
                .html('<p><strong>Error:</strong> Please select a filter type.</p>')
                .removeClass('results-success')
                .addClass('results-error')
                .show();
            $submitButton.prop('disabled', false);
            $spinner.removeClass('is-active');
            return;
        }
        
        if (filterType === 'service' && !serviceId) {
            $results
                .html('<p><strong>Error:</strong> Please select a service.</p>')
                .removeClass('results-success')
                .addClass('results-error')
                .show();
            $submitButton.prop('disabled', false);
            $spinner.removeClass('is-active');
            return;
        }
        
        if (filterType === 'month' && (!month || !/^\d{4}-\d{2}$/.test(month))) {
            $results
                .html('<p><strong>Error:</strong> Please enter a valid month (YYYY-MM).</p>')
                .removeClass('results-success')
                .addClass('results-error')
                .show();
            $submitButton.prop('disabled', false);
            $spinner.removeClass('is-active');
            return;
        }
        
        if (filterType === 'range' && (!startDate || !endDate)) {
            $results
                .html('<p><strong>Error:</strong> Please specify a date range.</p>')
                .removeClass('results-success')
                .addClass('results-error')
                .show();
            $submitButton.prop('disabled', false);
            $spinner.removeClass('is-active');
            return;
        }
        
        if (filterType === 'range' && startDate && endDate && new Date(startDate) > new Date(endDate)) {
            $results
                .html('<p><strong>Error:</strong> Start date cannot be after end date.</p>')
                .removeClass('results-success')
                .addClass('results-error')
                .show();
            $submitButton.prop('disabled', false);
            $spinner.removeClass('is-active');
            return;
        }
        
        // Send AJAX request
        $.ajax({
            url: sodAvailability.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $submitButton.prop('disabled', false);
                $spinner.removeClass('is-active');

                if (response.success) {
                    $results
                        .html('<p><strong>' + response.data.message + '</strong></p>')
                        .removeClass('results-error')
                        .addClass('results-success')
                        .show();

                    // Reload availability after successful deletion
                    if (typeof loadStaffAvailability === 'function') {
                        loadStaffAvailability(staffId);
                    }
                } else {
                    $results
                        .html('<p><strong>Error:</strong> ' + (response.data.message || 'Failed to delete slots.') + '</p>')
                        .removeClass('results-success')
                        .addClass('results-error')
                        .show();
                }
            },
            error: function(xhr, status, error) {
                $submitButton.prop('disabled', false);
                $spinner.removeClass('is-active');
                
                $results
                    .html('<p><strong>Error:</strong> An unexpected error occurred. Please try again.</p>')
                    .removeClass('results-success')
                    .addClass('results-error')
                    .show();
                console.error('AJAX Error:', status, error, xhr.responseText);
            }
        });
    });

    // When admin selects a different staff member, update staff ID in bulk delete form
    $('#admin-selected-staff').on('change', function() {
        var selectedStaffId = $(this).val();
        $('#sod-bulk-delete-form input[name="staff_id"]').val(selectedStaffId);
        console.log('Updated bulk delete form staff ID to: ' + selectedStaffId);
    });

    // Initialize the staff ID in the bulk delete form on page load
    var initialStaffId = $('#admin-selected-staff').val();
    if (initialStaffId) {
        $('#sod-bulk-delete-form input[name="staff_id"]').val(initialStaffId);
        console.log('Set initial bulk delete form staff ID to: ' + initialStaffId);
    }
});