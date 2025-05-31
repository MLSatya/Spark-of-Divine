jQuery(document).ready(function($) {
    // ====================================
    // Utility Functions
    // ====================================
    function getProductOptions() {
        let options = '<option value="">Select a Product</option>';
        if (sodAvailability.products) {
            sodAvailability.products.forEach(function(product) {
                options += `<option value="${product.id}">${product.title}</option>`;
            });
        }
        return options;
    }

    function isValidTimeRange(startTime, endTime) {
        if (!startTime || !endTime) return false;
        var start = new Date(`1970-01-01T${startTime}`);
        var end = new Date(`1970-01-01T${endTime}`);
        return start < end;
    }

    function getDefaultEndDate() {
        var endDate = new Date();
        endDate.setMonth(endDate.getMonth() + 3); // Default to 3 months
        return endDate.toISOString().split('T')[0];
    }

    // ====================================
    // Template Functions
    // ====================================
    function getAvailabilitySlotTemplate(slotIndex = $('.availability-slot').length) {
        return `
            <div class="availability-slot" data-slot-index="${slotIndex}">
                <label>Product:</label>
                <select name="availability_product[${slotIndex}][]" multiple class="availability-product" required>
                    ${getProductOptions()}
                </select>

                <label>Schedule Type:</label>
                <select name="schedule_type[${slotIndex}]" class="schedule-type">
                    <option value="one_time">One-time</option>
                    <option value="weekly">Weekly</option>
                    <option value="biweekly">Biweekly</option>
                    <option value="monthly">Monthly</option>
                </select>

                <div class="one-time-fields">
                    <label>Specific Date:</label>
                    <input type="date" name="availability_date[${slotIndex}]" />
                </div>

                <div class="recurring-fields" style="display: none;">
                    <label>Day of the Week:</label>
                    <select name="availability_day[${slotIndex}]">
                        <option value="">Select Day of the Week</option>
                        <option value="Monday">Monday</option>
                        <option value="Tuesday">Tuesday</option>
                        <option value="Wednesday">Wednesday</option>
                        <option value="Thursday">Thursday</option>
                        <option value="Friday">Friday</option>
                        <option value="Saturday">Saturday</option>
                        <option value="Sunday">Sunday</option>
                    </select>

                    <label>End Date:</label>
                    <input type="date" name="recurring_end_date[${slotIndex}]" class="recurring-end-date" />

                    <div class="biweekly-options" style="display: none;">
                        <label>Biweekly Pattern:</label>
                        <select name="biweekly_pattern[${slotIndex}]">
                            <option value="1st_3rd">1st & 3rd</option>
                            <option value="2nd_4th">2nd & 4th</option>
                        </select>
                        <label>
                            <input type="checkbox" name="skip_5th_week[${slotIndex}]" value="1" />
                            Skip 5th Week
                        </label>
                    </div>
                    
                    <div class="monthly-options" style="display: none;">
                        <label>Monthly Occurrence:</label>
                        <select name="monthly_occurrence[${slotIndex}]" class="monthly-occurrence">
                            <option value="">Select Occurrence</option>
                            <option value="1st">1st</option>
                            <option value="2nd">2nd</option>
                            <option value="3rd">3rd</option>
                            <option value="4th">4th</option>
                            <option value="last">Last</option>
                        </select>
                        <label>Monthly Day:</label>
                        <select name="monthly_day[${slotIndex}]" class="monthly-day">
                            <option value="">Select Day</option>
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                            <option value="Saturday">Saturday</option>
                            <option value="Sunday">Sunday</option>
                        </select>
                        <p class="description">Select the occurrence and day for monthly recurring slots.</p>
                    </div>
                </div>

                <label>Start Time:</label>
                <input type="time" name="availability_start[${slotIndex}]" required />
                
                <label>End Time:</label>
                <input type="time" name="availability_end[${slotIndex}]" required />

                <label>Buffer Between Sessions:</label>
                <select name="buffer_time[${slotIndex}]">
                    <option value="0">None</option>
                    <option value="15">15 mins</option>
                    <option value="30">30 mins</option>
                </select>

                <label>
                    <input type="checkbox" name="appointment_only[${slotIndex}]" value="1" />
                    By Appointment Only
                </label>

                <button type="button" class="remove-availability-slot">Remove</button>
            </div>
        `;
    }

    // ====================================
    // Event Handlers
    // ====================================
    
    // Add new slot
    $(document).on('click', '#add-availability-slot', function(e) {
        console.log('Add Slot button clicked');
        e.preventDefault();
        
        // Get the template HTML
        var template = $('#availability-slot-template').html();
        
        // Generate a unique ID for the new slot
        var uniqueId = 'new_' + Date.now();
        
        // Replace all occurrences of [new] with the unique ID
        var newSlotHtml = template.replace(/\[new\]/g, '[' + uniqueId + ']')
                                .replace(/data-slot-index="new"/g, 'data-slot-index="' + uniqueId + '"');
        
        // Append the new slot to the container
        $('#availability-slots').append(newSlotHtml);
        
        // Initialize the new slot
        var $newSlot = $('#availability-slots .availability-slot').last();
        $newSlot.find('.schedule-type').val('one_time').trigger('change');
        
        console.log('New slot appended with ID: ' + uniqueId);
    });

    // Handle schedule type changes
    $(document).on('change', '.schedule-type', function() {
        var $slot = $(this).closest('.availability-slot');
        var scheduleType = $(this).val();
        
        // Hide all schedule type specific fields first
        $slot.find('.one-time-fields').hide();
        $slot.find('.recurring-fields').hide();
        $slot.find('.biweekly-options').hide();
        $slot.find('.monthly-options').hide();
        
        // Show fields based on selected schedule type
        if (scheduleType === 'one_time') {
            $slot.find('.one-time-fields').show();
        } else {
            $slot.find('.recurring-fields').show();
            
            // Show specific options based on schedule type
            if (scheduleType === 'biweekly') {
                $slot.find('.biweekly-options').show();
            } else if (scheduleType === 'monthly') {
                $slot.find('.monthly-options').show();
            }
            
            // Set default end date
            var defaultEndDate = getDefaultEndDate();
            if (!$slot.find('.recurring-end-date').val()) {
                $slot.find('.recurring-end-date').val(defaultEndDate);
            }
        }
    });

    // Remove slot
    $(document).on('click', '.remove-availability-slot', function(e) {
        e.preventDefault();
        var $slot = $(this).closest('.availability-slot');
        var availabilityId = $slot.data('availability-id');

        if (availabilityId) {
            if (confirm('Are you sure you want to delete this availability slot?')) {
                deleteAvailabilitySlot(availabilityId, $slot);
            }
        } else if ($('.availability-slot').length > 1) {
            $slot.remove();
            console.log('New slot removed from form');
        }
    });

    // Load staff availability
    $('#admin-selected-staff').on('change', function() {
        let rawStaffId = $(this).val(); // what the <option value=""> is
        console.log('Selected staff dropdown value:', rawStaffId);

        // Make sure staffId is a valid integer
        let staffId = parseInt(rawStaffId, 10) || 0; 
        console.log('Parsed staffId as integer:', staffId);

        if (staffId <= 0) {
            console.log('No valid staff ID selected');
            $('#availability-slots').empty();
            return;
        }
        loadStaffAvailability(staffId);
    });

    // Load staff availability button
    $('#load-staff-availability').on('click', function() {
        let staffId = parseInt($('#admin-selected-staff').val(), 10) || 0;
        if (staffId > 0) {
            loadStaffAvailability(staffId);
        } else {
            alert('Please select a staff member first.');
        }
    });

    // Toggle bulk delete options
    $('#toggle-bulk-delete').on('click', function() {
        $('#bulk-delete-section').toggle();
    });

    // Change filter type in bulk delete form
    $('#bulk-delete-filter-type').on('change', function() {
        let filterType = $(this).val();
        $('.product-filter, .month-filter, .range-filter').hide();
        
        if (filterType === 'product') {
            $('.product-filter').show();
        } else if (filterType === 'month') {
            $('.month-filter').show();
        } else if (filterType === 'range') {
            $('.range-filter').show();
        }
    });

    // Handle form submission
    $('#sod-staff-availability-form').on('submit', handleFormSubmit);

    // Edit existing slot
    $(document).on('click', '.edit-availability', function(e) {
        e.preventDefault();
        console.log('Edit button clicked');
        
        const $button = $(this);
        const data = $button.data();
        const slotId = Date.now();
        
        const $newSlot = $(getAvailabilitySlotTemplate(slotId));
        
        // Update to use product-id instead of service-id
        const productIds = data.productId ? (Array.isArray(data.productId) ? data.productId : [data.productId]) : [];
        $newSlot.find('.availability-product').val(productIds);
        
        // Determine schedule type
        const scheduleType = 
            data.recurringType === 'biweekly' ? 'biweekly' :
            data.recurringType === 'monthly' ? 'monthly' :
            data.day && data.recurringType ? 'weekly' : 'one_time';
        
        $newSlot.find('.schedule-type').val(scheduleType);
        $newSlot.find('input[name="availability_date[' + slotId + ']"]').val(data.date);
        $newSlot.find('select[name="availability_day[' + slotId + ']"]').val(data.day);
        $newSlot.find('.recurring-end-date').val(data.recurringEndDate);
        $newSlot.find('select[name="biweekly_pattern[' + slotId + ']"]').val(data.biweeklyPattern || '1st_3rd');
        $newSlot.find('input[name="skip_5th_week[' + slotId + ']"]').prop('checked', data.skip5thWeek === '1');
        $newSlot.find('select[name="monthly_occurrence[' + slotId + ']"]').val(data.monthlyOccurrence || '');
        $newSlot.find('select[name="monthly_day[' + slotId + ']"]').val(data.monthlyDay || '');
        $newSlot.find('select[name="buffer_time[' + slotId + ']"]').val(data.bufferTime || '0');
        $newSlot.find('input[name="availability_start[' + slotId + ']"]').val(data.start);
        $newSlot.find('input[name="availability_end[' + slotId + ']"]').val(data.end);
        $newSlot.find('input[name="appointment_only[' + slotId + ']"]').prop('checked', data.appointmentOnly === '1');
        
        if (data.availabilityId) {
            $newSlot.append(`
                <div class="update-scope" style="margin-top: 10px;">
                    <label>Update Scope:</label><br>
                    <label><input type="radio" name="update_scope[${slotId}]" value="single" checked /> This day only</label>
                    <label><input type="radio" name="update_scope[${slotId}]" value="all" /> All future slots</label>
                    <input type="hidden" name="availability_ids[${slotId}]" value="${data.availabilityId}" />
                </div>
            `);
        }
        
        $button.closest('tr').replaceWith($newSlot);
        updateSlotFields();
    });

    // Delete existing slot
    $(document).on('click', '.delete-availability', function(e) {
        e.preventDefault();
        console.log('Delete button clicked');
        
        const availabilityId = $(this).data('availability-id');
        if (!availabilityId) {
            console.error('No availability ID found');
            return;
        }

        if (confirm('Are you sure you want to delete this availability slot?')) {
            $.ajax({
                url: sodAvailability.ajax_url,
                type: 'POST',
                data: {
                    action: 'delete_availability_slot',
                    availability_id: availabilityId,
                    nonce: sodAvailability.nonce
                },
                success: function(response) {
                    console.log('Delete response:', response);
                    if (response.success) {
                        const staffId = $('#admin-selected-staff').val();
                        // parse staffId to integer for consistency
                        loadStaffAvailability(parseInt(staffId, 10) || 0);
                    } else {
                        alert(response.data || 'Error deleting availability slot');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Delete error:', error);
                    alert('Failed to delete availability slot. Please try again.');
                }
            });
        }
    });
    
    // Handle bulk delete form submission
    $('#sod-bulk-delete-form').on('submit', function(e) {
        e.preventDefault();
        console.log('Bulk delete form submitted');

        // Create formData
        var formData = new FormData(this);

        // Ensure staff_post_id is always the currently selected staff
        var currentStaffId = $('#admin-selected-staff').val();
        console.log('Using current staff ID for bulk delete:', currentStaffId);
        formData.set('staff_post_id', currentStaffId);

        // Add action and nonce
        formData.append('action', 'bulk_delete_availability_slots');

        // Show spinner and hide any previous results
        $(this).find('.spinner').addClass('is-active');
        $('#bulk-delete-results').hide();

        $.ajax({
            url: sodAvailability.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('Bulk delete response:', response);
                $('#sod-bulk-delete-form .spinner').removeClass('is-active');

                if (response.success) {
                    $('#bulk-delete-results').html('<p class="success">' + response.data.message + '</p>').show();
                    // Reload the availability slots to reflect the deletions
                    loadStaffAvailability(currentStaffId);
                } else {
                    $('#bulk-delete-results').html('<p class="error">' + (response.data.message || 'Error performing bulk delete') + '</p>').show();
                }
            },
            error: function(xhr, status, error) {
                $('#sod-bulk-delete-form .spinner').removeClass('is-active');
                console.error('Bulk delete error:', error);
                $('#bulk-delete-results').html('<p class="error">Failed to perform bulk delete. Please try again.</p>').show();
            }
        });
    });

    // Handle select all checkbox
    $(document).on('change', '#select-all-slots', function() {
        $('.select-slot').prop('checked', $(this).prop('checked'));
        updateBulkActionButtonState();
    });

    // Handle individual slot checkbox
    $(document).on('change', '.select-slot', function() {
        updateBulkActionButtonState();
        
        // Update "select all" checkbox
        var allChecked = $('.select-slot:checked').length === $('.select-slot').length;
        $('#select-all-slots').prop('checked', allChecked);
    });

    // Handle bulk action button
    $(document).on('click', '#do-bulk-action', function() {
        var action = $('#bulk-action-selector').val();
        var selectedSlots = $('.select-slot:checked');
        
        if (!action) {
            alert('Please select an action');
            return;
        }
        
        if (selectedSlots.length === 0) {
            alert('Please select at least one slot');
            return;
        }
        
        if (action === 'delete') {
            if (confirm('Are you sure you want to delete the selected slots? This action cannot be undone.')) {
                bulkDeleteSlots(selectedSlots);
            }
        }
    });

    // ====================================
    // AJAX Functions
    // ====================================
    
    function loadStaffAvailability(staffId) {
        console.log('Loading availability for staff ID:', staffId);
        if (!staffId) {
            console.error('No staff ID provided');
            return;
        }
        // Update both form's staff IDs
        $('#sod-staff-availability-form input[name="staff_post_id"]').val(staffId);
        $('#sod-bulk-delete-form input[name="staff_post_id"]').val(staffId);

        // Log confirmation that both forms were updated
        console.log('Updated main form staff_post_id:', $('#sod-staff-availability-form input[name="staff_post_id"]').val());
        console.log('Updated bulk delete form staff_post_id:', $('#sod-bulk-delete-form input[name="staff_post_id"]').val());

        // Update staff name display in the bulk delete form
        if (typeof sodAvailability.staff_names !== 'undefined' && sodAvailability.staff_names[staffId]) {
            $('.staff-name').text(sodAvailability.staff_names[staffId]);
        }

        $.ajax({
            url: sodAvailability.ajax_url,
            type: 'POST',
            data: {
                action: 'load_staff_availability',
                staff_id: staffId,
                nonce: sodAvailability.nonce
            },
            beforeSend: function() {
                $('#availability-slots').html('<p>Loading...</p>');
            },
            success: function(response) {
                console.log('Load availability response received');
                if (response.success) {
                    $('#availability-slots').html(response.data);
                    updateSlotFields();
                    // After loading availability, also reload the products for bulk delete
                    reloadBulkDeleteProducts(staffId);
                } else {
                    $('#availability-slots').html('<p class="error">Error loading availability data.</p>');
                    console.error('Failed to load availability:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                $('#availability-slots').html('<p class="error">Error loading availability data.</p>');
            }
        });
    }
    
    function reloadBulkDeleteProducts(staffId) {
        console.log('Reloading bulk delete products for staff ID:', staffId);

        // Make sure we're using the correct nonce
        var nonce = $('#sod_availability_nonce').val();
        console.log('Using nonce:', nonce);

        $.ajax({
            url: sodAvailability.ajax_url,
            type: 'POST',
            data: {
                action: 'get_staff_products',
                staff_id: staffId,
                nonce: nonce
            },
            success: function(response) {
                console.log('Get products response:', response);
                if (response.success) {
                    var $productSelect = $('#bulk-delete-product');
                    $productSelect.empty();
                    $productSelect.append('<option value="">Select Product</option>');

                    if (response.data.products && Object.keys(response.data.products).length > 0) {
                        $.each(response.data.products, function(id, name) {
                            $productSelect.append('<option value="' + id + '">' + name + '</option>');
                        });
                    } else {
                        $productSelect.append('<option value="">No products available for this staff</option>');
                    }
                } else {
                    console.error('Failed to load products:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error loading products. Status:', status);
                console.error('Error:', error);
                console.error('Response:', xhr.responseText);
            }
        });
    }
    
    function deleteAvailabilitySlot(availabilityId, $slot) {
        $.ajax({
            url: sodAvailability.ajax_url,
            type: 'POST',
            data: {
                action: 'delete_availability_slot',
                availability_id: availabilityId,
                nonce: sodAvailability.nonce
            },
            success: function(response) {
                if (response.success) {
                    $slot.remove();
                    alert('Availability slot deleted successfully');
                } else {
                    alert(response.data || 'Error deleting slot');
                }
            },
            error: function() {
                alert('An error occurred while deleting the slot.');
            }
        });
    }

    // Function to update button state
    function updateBulkActionButtonState() {
        var hasSelection = $('.select-slot:checked').length > 0;
        $('#do-bulk-action').prop('disabled', !hasSelection);
    }

    // Function to handle bulk delete action
    function bulkDeleteSlots(selectedSlots) {
        // Get all selected slot IDs
        var slotIds = $.map(selectedSlots, function(checkbox) {
            return $(checkbox).val();
        });
        
        // Show spinner
        $('.bulk-delete-spinner').addClass('is-active');
        $('#bulk-action-result').addClass('hidden');
        
        // Get current staff ID
        var staffId = $('#admin-selected-staff').val();
        
        // Send AJAX request
        $.ajax({
            url: sodAvailability.ajax_url,
            type: 'POST',
            data: {
                action: 'bulk_delete_slots',
                nonce: sodAvailability.nonce,
                staff_id: staffId,
                slot_ids: slotIds
            },
            success: function(response) {
                $('.bulk-delete-spinner').removeClass('is-active');
                
                if (response.success) {
                    // Show success message
                    $('#bulk-action-result')
                        .removeClass('hidden error')
                        .addClass('success')
                        .html(response.data.message);
                    
                    // Reload the availability list
                    loadStaffAvailability(staffId);
                    
                    // Reset select all checkbox
                    $('#select-all-slots').prop('checked', false);
                } else {
                    // Show error message
                    $('#bulk-action-result')
                        .removeClass('hidden success')
                        .addClass('error')
                        .html(response.data.message || 'Failed to delete slots');
                }
            },
            error: function() {
                $('.bulk-delete-spinner').removeClass('is-active');
                
                // Show error message
                $('#bulk-action-result')
                    .removeClass('hidden success')
                    .addClass('error')
                    .html('An error occurred. Please try again.');
            }
        });
    }

    function handleFormSubmit(e) {
        e.preventDefault();
        console.log('Form submitted');

        if (!validateForm()) return;

        var formData = new FormData(this);

        // ALWAYS use the currently selected staff ID
        var currentStaffId = $('#admin-selected-staff').val();
        console.log('Using current staff ID for submission:', currentStaffId);
        formData.set('staff_post_id', currentStaffId); // .set() overwrites if it exists

        formData.append('action', 'save_staff_availability');
        formData.append('sod_availability_nonce', $('#sod_availability_nonce').val());

        $('.availability-slot').each(function() {
            processSlotData($(this), formData);
        });

        // Debug log all form data
        console.log('----FORM DATA----');
        for (var pair of formData.entries()) {
            console.log(pair[0] + ': ' + pair[1]);
        }
        console.log('----------------');

        submitForm(formData);
    }

    // ====================================
    // Validation Functions
    // ====================================
    
    function validateForm() {
        var errors = [];
        $('.availability-slot').each(function(index) {
            var slotErrors = validateSlot($(this), index + 1);
            errors = errors.concat(slotErrors);
        });

        if (errors.length > 0) {
            alert('Please correct the following errors:\n\n' + errors.join('\n'));
            return false;
        }
        return true;
    }

    function validateSlot($slot, index) {
        var errors = [];
        var slotIndex = $slot.data('slot-index') || $slot.data('slot-key') || index - 1;
        
        // Updated to use availability_product instead of availability_service
        var product = $slot.find('select[name^="availability_product[' + slotIndex + ']"]').val();
        var scheduleType = $slot.find('select[name^="schedule_type[' + slotIndex + ']"]').val();
        var startTime = $slot.find('input[name^="availability_start[' + slotIndex + ']"]').val();
        var endTime = $slot.find('input[name^="availability_end[' + slotIndex + ']"]').val();

        // Check if product is selected - works for multiple selection
        if (!product || product.length === 0) {
            errors.push(`Slot ${index}: Product is required`);
        }
        
        if (!startTime) {
            errors.push(`Slot ${index}: Start Time is required`);
        }
        
        if (!endTime) {
            errors.push(`Slot ${index}: End Time is required`);
        }
        
        if (startTime && endTime && !isValidTimeRange(startTime, endTime)) {
            errors.push(`Slot ${index}: End Time must be after Start Time`);
        }

        if (scheduleType === 'one_time') {
            var date = $slot.find('input[name^="availability_date[' + slotIndex + ']"]').val();
            if (!date) {
                errors.push(`Slot ${index}: Date is required for one-time slots`);
            }
        } else if (scheduleType === 'weekly' || scheduleType === 'biweekly' || scheduleType === 'monthly') {
            var day = $slot.find('select[name^="availability_day[' + slotIndex + ']"]').val();
            var endDate = $slot.find('input[name^="recurring_end_date[' + slotIndex + ']"]').val();
            
            if (!day) {
                errors.push(`Slot ${index}: Day of the Week is required for recurring slots`);
            }
            
            if (!endDate) {
                errors.push(`Slot ${index}: End Date is required for recurring slots`);
            }
            
            // Schedule type specific validation
            if (scheduleType === 'biweekly') {
                var biweeklyPattern = $slot.find('select[name^="biweekly_pattern[' + slotIndex + ']"]').val();
                if (!biweeklyPattern) {
                    errors.push(`Slot ${index}: Biweekly Pattern is required for biweekly slots`);
                }
            } else if (scheduleType === 'monthly') {
                var monthlyOccurrence = $slot.find('select[name^="monthly_occurrence[' + slotIndex + ']"]').val();
                var monthlyDay = $slot.find('select[name^="monthly_day[' + slotIndex + ']"]').val();
                if (!monthlyOccurrence) {
                    errors.push(`Slot ${index}: Monthly Occurrence is required for monthly slots`);
                }
                if (!monthlyDay) {
                    errors.push(`Slot ${index}: Monthly Day is required for monthly slots`);
                }
            }
        }

        return errors;
    }

    // ====================================
    // Helper Functions
    // ====================================
    
    function processSlotData($slot, formData) {
        var slotIndex = $slot.data('slot-index') || $slot.data('slot-key');
        var scheduleType = $slot.find('select[name^="schedule_type[' + slotIndex + ']"]').val();
        var checkbox = $slot.find('input[name^="appointment_only[' + slotIndex + ']"]');
        var skipFifthWeek = $slot.find('input[name^="skip_5th_week[' + slotIndex + ']"]');
        var availabilityId = $slot.data('availability-id');
        
        // If we are editing an existing slot, store its ID
        if (availabilityId && !formData.has('availability_ids[' + slotIndex + ']')) {
            formData.append('availability_ids[' + slotIndex + ']', availabilityId);
        }
        
        // For the "appointment_only" checkbox
        if (!formData.has('appointment_only[' + slotIndex + ']')) {
            formData.append('appointment_only[' + slotIndex + ']', checkbox.is(':checked') ? '1' : '0');
        }
        
        // Ensure skip 5th week is properly set for biweekly slots
        if (scheduleType === 'biweekly' && !formData.has('skip_5th_week[' + slotIndex + ']')) {
            formData.append('skip_5th_week[' + slotIndex + ']', skipFifthWeek.is(':checked') ? '1' : '0');
        }
    }

    function updateSlotFields() {
        $('.availability-slot').each(function() {
            var $slot = $(this);
            var scheduleType = $slot.find('.schedule-type').val();
            
            // Hide all schedule-specific fields
            $slot.find('.one-time-fields').hide();
            $slot.find('.recurring-fields').hide();
            $slot.find('.biweekly-options').hide();
            $slot.find('.monthly-options').hide();
            
            // Show appropriate fields based on schedule type
            if (scheduleType === 'one_time') {
                $slot.find('.one-time-fields').show();
            } else {
                $slot.find('.recurring-fields').show();
                
                if (scheduleType === 'biweekly') {
                    $slot.find('.biweekly-options').show();
                } else if (scheduleType === 'monthly') {
                    $slot.find('.monthly-options').show();
                }
            }
        });
    }

    function submitForm(formData) {
        $.ajax({
            url: sodAvailability.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('AJAX response:', response);
                if (response.success) {
                    alert(response.data);
                    const staffId = $('#admin-selected-staff').val();
                    if (staffId) loadStaffAvailability(parseInt(staffId, 10) || 0);
                } else {
                    handleFormError(response);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                alert('An error occurred while saving availability. Please try again.');
            }
        });
    }

    function handleFormError(response) {
        var errorMessage = 'Error saving availability';
        if (response.data && typeof response.data === 'object' && response.data.errors) {
            errorMessage += ':\n\n' + response.data.errors.join('\n');
        } else if (response.data) {
            errorMessage = response.data;
        }
        alert(errorMessage);
    }

    // Initial setup
    updateSlotFields();

    // Initialize on load
    $(function() {
        // Show the bulk delete toggle button only for administrators
        if ($('#toggle-bulk-delete').length) {
            $('#toggle-bulk-delete').show();
        }
        
        // Automatically load availability if a staff member is pre-selected
        let staffId = parseInt($('#admin-selected-staff').val(), 10) || 0;
        if (staffId > 0) {
            loadStaffAvailability(staffId);
        }
    });
});