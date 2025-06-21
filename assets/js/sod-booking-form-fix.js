jQuery(document).ready(function($) {
    console.log('Booking form fix loaded');
    
    // Handle form submission for booking-form class
    $(document).on('submit', '.booking-form', function(e) {
        e.preventDefault();
        console.log('Booking form submitted!');
        
        var $form = $(this);
        var formData = new FormData(this);
        
        // Log what we're sending
        console.log('Form data:');
        for (var pair of formData.entries()) {
            console.log(pair[0] + ': ' + pair[1]);
        }
        
        // Submit via AJAX
        $.ajax({
            url: $form.attr('action'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('Booking response:', response);
                
                if (response.success) {
                    if (response.data && response.data.cart_url) {
                        window.location.href = response.data.cart_url;
                    } else if (response.data && response.data.redirect) {
                        window.location.href = response.data.redirect;
                    } else {
                        // Fallback to cart page
                        window.location.href = '/cart/';
                    }
                } else {
                    alert(response.data && response.data.message ? response.data.message : 'Booking failed');
                }
            },
            error: function(xhr, status, error) {
                console.error('Booking error:', status, error);
                alert('Error processing booking. Please try again.');
            }
        });
        
        return false;
    });
});

// Update the success handler to check for guest contact requirement
jQuery(document).ready(function($) {
    // Override the existing success handler
    var originalSuccess = window.handleBookingSuccess;
    
    window.handleBookingSuccess = function(response) {
        console.log('Booking success response:', response);
        
        if (response.success && response.data) {
            // Check if guest needs to provide contact info
            if (response.data.needs_contact_info) {
                console.log('Guest user - redirecting to cart for contact info');
            }
            
            // Always redirect to the URL provided
            if (response.data.redirect || response.data.cart_url) {
                var redirectUrl = response.data.redirect || response.data.cart_url;
                console.log('Redirecting to:', redirectUrl);
                window.location.href = redirectUrl;
            }
        }
        
        // Call original if it exists
        if (originalSuccess) {
            originalSuccess(response);
        }
    };
});
