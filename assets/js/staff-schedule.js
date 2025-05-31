// Staff Schedule
(function($) {
    'use strict';
    
    $(document).ready(function() {
        console.log('Staff Schedule JS loaded');
        
        // Auto-submit form when filters change
        $('.sod-schedule-filters select').on('change', function() {
            $(this).closest('form').submit();
        });
        
        // Global function for toggling reschedule form
        window.sodToggleRescheduleForm = function(bookingId) {
            const form = document.getElementById('reschedule-form-' + bookingId);
            if (form) {
                form.style.display = form.style.display === 'none' ? 'block' : 'none';
            }
        };
        
        // Bind click events to reschedule buttons
        $('.reschedule-btn').on('click', function() {
            const bookingId = $(this).data('booking-id');
            sodToggleRescheduleForm(bookingId);
        });
        
        // Bind click events to cancel buttons in reschedule forms
        $('.sod-reschedule-form .cancel-btn').on('click', function() {
            const bookingId = $(this).data('booking-id');
            sodToggleRescheduleForm(bookingId);
        });
        
        // Confirmation for cancel actions
        $('.cancel-form').on('submit', function(e) {
            if (!confirm(sodStaffSchedule.confirmCancel || 'Are you sure you want to cancel this booking?')) {
                e.preventDefault();
            }
        });
        
        // Add loading indicator for form submissions
        $('.sod-booking-action-form').on('submit', function() {
            $(this).find('button[type="submit"]').prop('disabled', true).text(sodStaffSchedule.processingText || 'Processing...');
        });
    });
    
})(jQuery);