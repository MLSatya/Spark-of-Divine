/**
 * Staff Dashboard JavaScript
 */
(function($) {
    'use strict';
    
    // Variables
    var activeModal = null;
    var activeBookingId = null;
    
    // Initialize everything when document is ready
    $(document).ready(function() {
        initBookingActions();
        initModals();
        initProductAvailability();
    });
    
    /**
     * Initialize booking action buttons
     */
    function initBookingActions() {
        // Confirm booking
        $('.sod-confirm-booking').on('click', function(e) {
            e.preventDefault();
            activeBookingId = $(this).data('booking-id');
            openModal('sod-confirm-booking-modal');
        });
        
        // Reschedule booking
        $('.sod-reschedule-booking').on('click', function(e) {
            e.preventDefault();
            activeBookingId = $(this).data('booking-id');
            openModal('sod-reschedule-booking-modal');
        });
        
        // Cancel booking
        $('.sod-cancel-booking').on('click', function(e) {
            e.preventDefault();
            activeBookingId = $(this).data('booking-id');
            openModal('sod-cancel-booking-modal');
        });
        
        // Filter form change handlers
        $('#booking_status_filter, #booking_date_filter').on('change', function() {
            $('#booking-filters-form').submit();
        });
        
        // Revenue date filter
        $('#revenue_date_filter').on('change', function() {
            $('#revenue-filters-form').submit();
        });
    }
    
    /**
     * Initialize modal interactions
     */
    function initModals() {
        // Close button
        $('.sod-modal-close, .sod-btn-cancel').on('click', function() {
            closeModal();
        });
        
        // Close when clicking outside modal
        $('.sod-modal').on('click', function(e) {
            if ($(e.target).hasClass('sod-modal')) {
                closeModal();
            }
        });
        
        // ESC key to close
        $(document).keydown(function(e) {
            if (e.key === 'Escape' && activeModal) {
                closeModal();
            }
        });
        
        // Confirm booking action
        $('#sod-confirm-booking-modal .sod-btn-confirm').on('click', function() {
            confirmBooking();
        });
        
        // Reschedule booking action
        $('#sod-reschedule-booking-modal .sod-btn-confirm').on('click', function() {
            rescheduleBooking();
        });
        
        // Cancel booking action
        $('#sod-cancel-booking-modal .sod-btn-confirm').on('click', function() {
            cancelBooking();
        });
    }
    
    /**
     * Initialize product availability actions
     */
    function initProductAvailability() {
        // Handle availability type toggle
        $('#availability_type').on('change', function() {
            toggleAvailabilityFields();
        });
        
        // Initialize datepickers if jQuery UI is available
        if ($.datepicker) {
            $('.datepicker').datepicker({
                dateFormat: 'yy-mm-dd',
                minDate: 0,
                changeMonth: true,
                changeYear: true
            });
        }
        
        // Add availability form submission
        $('#add-availability-form').on('submit', function(e) {
            e.preventDefault();
            addAvailability();
        });
        
        // Edit availability button
        $('.edit-availability').on('click', function() {
            var slotId = $(this).data('slot-id');
            openEditAvailabilityModal(slotId);
        });
        
        // Delete availability button
        $('.delete-availability').on('click', function() {
            var slotId = $(this).data('slot-id');
            openDeleteAvailabilityModal(slotId);
        });
    }
    
    /**
     * Toggle availability fields based on type
     */
    function toggleAvailabilityFields() {
        var type = $('#availability_type').val();
        
        if (type === 'recurring') {
            $('.recurring-fields').show();
            $('.specific-fields').hide();
        } else {
            $('.recurring-fields').hide();
            $('.specific-fields').show();
        }
    }
    
    /**
     * Open a modal by ID
     */
    function openModal(modalId) {
        activeModal = modalId;
        
        // Show modal container and the specific modal
        $('.sod-modal-container').addClass('active');
        $('#' + modalId).addClass('active');
        
        // Focus first input if present
        $('#' + modalId).find('input:first').focus();
    }
    
    /**
     * Close the active modal
     */
    function closeModal() {
        if (!activeModal) return;
        
        // Hide modals
        $('.sod-modal-container').removeClass('active');
        $('#' + activeModal).removeClass('active');
        
        // Clear inputs
        $('#' + activeModal).find('input, textarea').val('');
        
        // Reset variables
        activeModal = null;
    }
    
    /**
     * Open edit availability modal
     */
    function openEditAvailabilityModal(slotId) {
        // Set the active slot ID
        $('#edit_slot_id').val(slotId);
        
        // Get slot data from the data attributes
        var $slot = $('[data-slot-id="' + slotId + '"]');
        $('#edit_start_time').val($slot.data('start-time'));
        $('#edit_end_time').val($slot.data('end-time'));
        
        // Check if it's a recurring slot
        if ($slot.data('recurring')) {
            $('#edit_end_date').val($slot.data('end-date')).closest('.sod-form-row').show();
        } else {
            $('#edit_end_date').val('').closest('.sod-form-row').hide();
        }
        
        // Open the modal
        openModal('edit-availability-modal');
    }
    
    /**
     * Open delete availability modal
     */
    function openDeleteAvailabilityModal(slotId) {
        $('#delete_slot_id').val(slotId);
        openModal('delete-availability-modal');
    }
    
    /**
     * Add new availability slot
     */
    function addAvailability() {
        var type = $('#availability_type').val();
        var data = {
            action: 'sod_staff_update_availability',
            nonce: sodStaffDashboard.nonce,
            type: 'add',
            product_id: $('#product_id').val(),
            start_time: $('#start_time').val(),
            end_time: $('#end_time').val()
        };
        
        if (type === 'recurring') {
            data.day_of_week = $('#day_of_week').val();
            data.recurring_type = $('#recurring_type').val();
            data.recurring_end_date = $('#recurring_end_date').val();
        } else {
            data.specific_date = $('#specific_date').val();
        }
        
        // Validate inputs
        if (!data.product_id || !data.start_time || !data.end_time) {
            alert(sodStaffDashboard.i18n.requiredFields || 'Please fill all required fields');
            return;
        }
        
        if (type === 'recurring' && !data.day_of_week) {
            alert(sodStaffDashboard.i18n.selectDay || 'Please select a day of the week');
            return;
        }
        
        if (type === 'specific' && !data.specific_date) {
            alert(sodStaffDashboard.i18n.selectDate || 'Please select a date');
            return;
        }
        
        // Show loading state
        $('#add-availability-btn').prop('disabled', true);
        
        $.ajax({
            url: sodStaffDashboard.ajaxUrl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    // Reload page to show new availability
                    location.reload();
                } else {
                    alert(response.data.message || sodStaffDashboard.i18n.errorMessage);
                    $('#add-availability-btn').prop('disabled', false);
                }
            },
            error: function() {
                alert(sodStaffDashboard.i18n.errorMessage);
                $('#add-availability-btn').prop('disabled', false);
            }
        });
    }
    
    /**
     * Update availability slot
     */
    function updateAvailability() {
        var slotId = $('#edit_slot_id').val();
        var data = {
            action: 'sod_staff_update_availability',
            nonce: sodStaffDashboard.nonce,
            type: 'update',
            slot_id: slotId,
            start_time: $('#edit_start_time').val(),
            end_time: $('#edit_end_time').val(),
            recurring_end_date: $('#edit_end_date').val()
        };
        
        // Validate inputs
        if (!data.start_time || !data.end_time) {
            alert(sodStaffDashboard.i18n.requiredFields || 'Please fill all required fields');
            return;
        }
        
        // Show loading state
        $('#edit-availability-modal .sod-btn-confirm').prop('disabled', true);
        
        $.ajax({
            url: sodStaffDashboard.ajaxUrl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    // Reload page to show updated availability
                    location.reload();
                } else {
                    alert(response.data.message || sodStaffDashboard.i18n.errorMessage);
                    $('#edit-availability-modal .sod-btn-confirm').prop('disabled', false);
                }
            },
            error: function() {
                alert(sodStaffDashboard.i18n.errorMessage);
                $('#edit-availability-modal .sod-btn-confirm').prop('disabled', false);
            }
        });
    }
    
    /**
     * Delete availability slot
     */
    function deleteAvailability() {
        var slotId = $('#delete_slot_id').val();
        
        // Show loading state
        $('#delete-availability-modal .sod-btn-confirm').prop('disabled', true);
        
        $.ajax({
            url: sodStaffDashboard.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sod_staff_update_availability',
                nonce: sodStaffDashboard.nonce,
                type: 'delete',
                slot_id: slotId
            },
            success: function(response) {
                if (response.success) {
                    // Reload page to show updated availability
                    location.reload();
                } else {
                    alert(response.data.message || sodStaffDashboard.i18n.errorMessage);
                    $('#delete-availability-modal .sod-btn-confirm').prop('disabled', false);
                }
            },
            error: function() {
                alert(sodStaffDashboard.i18n.errorMessage);
                $('#delete-availability-modal .sod-btn-confirm').prop('disabled', false);
            }
        });
    }
    
    /**
     * Confirm booking via AJAX
     */
    function confirmBooking() {
        if (!activeBookingId) return;
        
        $.ajax({
            url: sodStaffDashboard.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sod_staff_confirm_booking',
                booking_id: activeBookingId,
                nonce: sodStaffDashboard.nonce
            },
            beforeSend: function() {
                // Show loading state
                $('#sod-confirm-booking-modal .sod-btn-confirm').prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    // Reload page to show updated status
                    location.reload();
                } else {
                    alert(response.data.message || sodStaffDashboard.i18n.errorMessage);
                    closeModal();
                }
            },
            error: function() {
                alert(sodStaffDashboard.i18n.errorMessage);
                closeModal();
            }
        });
    }
    
    /**
     * Reschedule booking via AJAX
     */
    function rescheduleBooking() {
        if (!activeBookingId) return;
        
        var newDate = $('#sod-new-date').val();
        var newTime = $('#sod-new-time').val();
        var message = $('#sod-reschedule-message').val();
        
        if (!newDate || !newTime) {
            alert(sodStaffDashboard.i18n.rescheduleBooking);
            return;
        }
        
        $.ajax({
            url: sodStaffDashboard.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sod_staff_reschedule_booking',
                booking_id: activeBookingId,
                new_date: newDate,
                new_time: newTime,
                message: message,
                nonce: sodStaffDashboard.nonce
            },
            beforeSend: function() {
                // Show loading state
                $('#sod-reschedule-booking-modal .sod-btn-confirm').prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    // Reload page to show updated status
                    location.reload();
                } else {
                    alert(response.data.message || sodStaffDashboard.i18n.errorMessage);
                    closeModal();
                }
            },
            error: function() {
                alert(sodStaffDashboard.i18n.errorMessage);
                closeModal();
            }
        });
    }
    
    /**
     * Cancel booking via AJAX
     */
    function cancelBooking() {
        if (!activeBookingId) return;
        
        var reason = $('#sod-cancel-reason').val();
        
        $.ajax({
            url: sodStaffDashboard.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sod_staff_cancel_booking',
                booking_id: activeBookingId,
                reason: reason,
                nonce: sodStaffDashboard.nonce
            },
            beforeSend: function() {
                // Show loading state
                $('#sod-cancel-booking-modal .sod-btn-confirm').prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    // Reload page to show updated status
                    location.reload();
                } else {
                    alert(response.data.message || sodStaffDashboard.i18n.errorMessage);
                    closeModal();
                }
            },
            error: function() {
                alert(sodStaffDashboard.i18n.errorMessage);
                closeModal();
            }
        });
    }
    
    /**
     * Format time string for display (HH:MM)
     */
    function formatTime(timeString) {
        if (!timeString) return '';
        
        // Handle various time formats
        var timeRegex = /(\d{1,2}):(\d{2})(?::(\d{2}))?/;
        var match = timeString.match(timeRegex);
        
        if (!match) return timeString;
        
        var hours = match[1].padStart(2, '0');
        var minutes = match[2];
        
        return hours + ':' + minutes;
    }
    
    /**
     * Format date string (YYYY-MM-DD)
     */
    function formatDate(dateString) {
        if (!dateString) return '';
        
        var date = new Date(dateString);
        if (isNaN(date.getTime())) return dateString;
        
        return date.getFullYear() + '-' + 
               padZero(date.getMonth() + 1) + '-' + 
               padZero(date.getDate());
    }
    
    /**
     * Pad a number with leading zero if needed
     */
    function padZero(num) {
        return num < 10 ? '0' + num : num;
    }
    
    // Initialize edit/delete availability modals if they exist
    if ($('#edit-availability-modal').length) {
        $('#edit-availability-modal .sod-btn-confirm').on('click', function() {
            updateAvailability();
        });
    }
    
    if ($('#delete-availability-modal').length) {
        $('#delete-availability-modal .sod-btn-confirm').on('click', function() {
            deleteAvailability();
        });
    }
    
})(jQuery);