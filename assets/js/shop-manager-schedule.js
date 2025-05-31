// JS FILE: assets/js/shop-manager-schedule

jQuery(document).ready(function($) {
    // Toggle booking forms
    window.sodToggleBookingForm = function(formId) {
        const form = document.getElementById(`booking-form-${formId}`);
        if (form) {
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
    };
    
    // Handle confirmation and cancellation
    $('.confirm-form').submit(function(e) {
        if (!confirm(sodShopManagerSchedule.confirmConfirm)) {
            e.preventDefault();
        }
    });
    
    $('.cancel-form').submit(function(e) {
        if (!confirm(sodShopManagerSchedule.confirmCancel)) {
            e.preventDefault();
        }
    });
    
    // Form submission handling with AJAX
    $('.sod-booking-action-form').submit(function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $submitBtn = $form.find('button[type="submit"]');
        const originalText = $submitBtn.text();
        
        $submitBtn.text(sodShopManagerSchedule.processingText).prop('disabled', true);
        
        $.ajax({
            url: sodShopManagerSchedule.ajax_url,
            type: 'POST',
            data: $form.serialize() + '&security=' + sodShopManagerSchedule.nonce,
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    if (response.data.reload) {
                        window.location.reload();
                    }
                } else {
                    alert(response.data.message || 'An error occurred. Please try again.');
                }
            },
            error: function() {
                alert('Connection error. Please try again.');
            },
            complete: function() {
                $submitBtn.text(originalText).prop('disabled', false);
            }
        });
    });
});