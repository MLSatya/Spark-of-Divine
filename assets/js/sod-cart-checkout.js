/**
 * Spark of Divine - Cart & Checkout Integration
 */
jQuery(document).ready(function($) {
    // Fix for contact fields display issues
    $('.sod-cart-contact-info').css('display', 'block');
    
    // Add event handler for all contact field save buttons
    $('.sod-cart-contact-info button').on('click', function(e) {
        e.preventDefault();
        
        // Get form container - navigate up from button to container div
        const container = $(this).closest('.sod-cart-contact-info');
        const containerId = container.attr('id');
        
        // Get form values
        const firstNameInput = $('#' + containerId + ' input[name="sod_cart_first_name"]');
        const lastNameInput = $('#' + containerId + ' input[name="sod_cart_last_name"]');
        const emailInput = $('#' + containerId + ' input[name="sod_cart_email"]');
        const phoneInput = $('#' + containerId + ' input[name="sod_cart_phone"]');
        const statusContainer = $('#' + containerId + ' .sod-contact-status');
        const saveButton = $(this);
        
        // Reset errors
        container.find('.error-message').text('');
        container.find('input').removeClass('has-error');
        
        // Validate inputs
        let isValid = true;
        
        if (!firstNameInput.val().trim()) {
            $('#' + containerId + ' #' + containerId + '-first-name-error').text('First name is required');
            firstNameInput.addClass('has-error');
            isValid = false;
        }
        
        if (!lastNameInput.val().trim()) {
            $('#' + containerId + ' #' + containerId + '-last-name-error').text('Last name is required');
            lastNameInput.addClass('has-error');
            isValid = false;
        }
        
        if (!emailInput.val().trim()) {
            $('#' + containerId + ' #' + containerId + '-email-error').text('Email is required');
            emailInput.addClass('has-error');
            isValid = false;
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.val())) {
            $('#' + containerId + ' #' + containerId + '-email-error').text('Please enter a valid email address');
            emailInput.addClass('has-error');
            isValid = false;
        }
        
        if (!phoneInput.val().trim()) {
            $('#' + containerId + ' #' + containerId + '-phone-error').text('Phone number is required');
            phoneInput.addClass('has-error');
            isValid = false;
        }
        
        if (!isValid) {
            return;
        }
        
        // Show loading state
        saveButton.prop('disabled', true).text('Saving...');
        statusContainer.html('');
        
        // Save via AJAX
        $.ajax({
            url: sod_contact_fields.ajax_url,
            type: 'POST',
            data: {
                action: 'sod_save_cart_contact_info',
                nonce: sod_contact_fields.nonce,
                first_name: firstNameInput.val(),
                last_name: lastNameInput.val(),
                email: emailInput.val(),
                phone: phoneInput.val()
            },
            success: function(response) {
                if (response.success) {
                    // Show success
                    statusContainer.html('<div class="sod-success-message">Contact info saved successfully!</div>');
                    saveButton.text('Saved!');
                    
                    // Add success class
                    container.addClass('saved');
                    
                    // Enable checkout button explicitly
                    $('.checkout-button, .wc-proceed-to-checkout .button').removeClass('disabled');
                    
                    // Optionally redirect to checkout
                    if (response.data && response.data.redirect) {
                        window.location.href = response.data.redirect;
                    }
                } else {
                    // Show error
                    statusContainer.html('<div class="sod-error-message">Error: ' + (response.data ? response.data.message : 'Unknown error') + '</div>');
                    saveButton.prop('disabled', false).text('Save Contact Info');
                }
            },
            error: function() {
                // Show error
                statusContainer.html('<div class="sod-error-message">Error connecting to server. Please try again.</div>');
                saveButton.prop('disabled', false).text('Save Contact Info');
            }
        });
    });

    // Add event listeners for checkout button clicks to validate contact info first
    $(document).on('click', '.checkout-button, .wc-proceed-to-checkout .button', function(e) {
        // Skip if user is logged in
        if (sod_contact_fields.is_user_logged_in) {
            return;
        }
        
        // Get contact fields container
        const container = $('.sod-cart-contact-info');
        
        // If no container, allow checkout
        if (container.length === 0) {
            return;
        }
        
        // Check if saved
        if (!container.hasClass('saved')) {
            e.preventDefault();
            
            // Show error and scroll to contact fields
            container.find('.sod-contact-status').html('<div class="sod-error-message">Please save your contact information before proceeding to checkout.</div>');
            $('html, body').animate({
                scrollTop: container.offset().top - 100
            }, 500);
        }
    });

    // Modify coupon field width
    $('.woocommerce-cart-form .coupon').addClass('sod-coupon-compact');
    
    // Move "Return to Calendar" button to top if not already there
    if ($('.sod-top-actions').length === 0 && $('.sod-return-to-calendar').length > 0) {
        // Create container if it doesn't exist
        $('.sod-custom-cart').prepend('<div class="sod-top-actions"></div>');
        // Move button to top container
        $('.sod-return-to-calendar').appendTo('.sod-top-actions');
    }
    
    // Apply Elementor global colors to buttons
    function applyElementorColors() {
        // Try to get Elementor global colors from CSS variables
        let primaryColor = '';
        let secondaryColor = '';
        
        // Check if Elementor global colors are available
        if (window.getComputedStyle(document.documentElement).getPropertyValue('--e-global-color-primary')) {
            primaryColor = window.getComputedStyle(document.documentElement).getPropertyValue('--e-global-color-primary');
        }
        
        if (window.getComputedStyle(document.documentElement).getPropertyValue('--e-global-color-secondary')) {
            secondaryColor = window.getComputedStyle(document.documentElement).getPropertyValue('--e-global-color-secondary');
        }
        
        // Apply colors if available
        if (primaryColor) {
            $('.sod-return-to-calendar, .checkout-button, .wc-proceed-to-checkout .button, .sod-save-contact-info')
                .css('background-color', primaryColor);
        }
        
        if (secondaryColor) {
            $('.woocommerce-cart-form button[name="update_cart"], .woocommerce-cart-form button[name="apply_coupon"]')
                .css('background-color', secondaryColor);
        }
    }
    
    // Run once on page load
    applyElementorColors();
    
    // Force display of contact fields after a short delay
    // This helps with some themes that might hide them with JS
    setTimeout(function() {
        $('.sod-cart-contact-info').css({
            'display': 'block',
            'visibility': 'visible',
            'opacity': '1'
        });
    }, 500);
});