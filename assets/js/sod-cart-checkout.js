jQuery(function($) {
    // This logic only applies to guest users on the cart page
    if ($('body').hasClass('woocommerce-cart') && !$('body').hasClass('logged-in')) {

        const $checkoutButton = $('.wc-proceed-to-checkout .checkout-button');

        // Ensure the button exists before trying to add a handler
        if ($checkoutButton.length > 0) {
            
            // Hijack the original click event
            $checkoutButton.on('click', function(e) {
                // Prevent the button from going to the checkout page immediately
                e.preventDefault();

                const $form = $('.sod-cart-contact-info');
                const $status = $form.find('.sod-contact-status'); // Optional status message div
                const checkoutUrl = $(this).attr('href'); // Get the checkout page URL from the button

                // Visually disable the button while we work
                $checkoutButton.addClass('disabled').css('opacity', '0.5');
                if ($status.length) {
                    $status.html('Saving...').removeClass('error');
                }

                // Gather the data from the form fields
                const contactData = {
                    action: 'sod_save_cart_contact_info',
                    nonce: $form.find('#sod_contact_nonce').val() || sod_contact_fields.nonce,
                    first_name: $form.find('input[name="sod_cart_first_name"]').val(),
                    last_name: $form.find('input[name="sod_cart_last_name"]').val(),
                    email: $form.find('input[name="sod_cart_email"]').val(),
                    phone: $form.find('input[name="sod_cart_phone"]').val(),
                    opt_out: $form.find('input[name="sod_cart_opt_out"]').is(':checked')
                };

                // Send the data to your existing PHP handler
                $.post(sod_contact_fields.ajax_url, contactData)
                    .done(function(response) {
                        if (response.success) {
                            // If the data was saved successfully, now we can go to the checkout page
                            window.location.href = checkoutUrl;
                        } else {
                            // If there was an error, display it and re-enable the button
                            if ($status.length) {
                                $status.html(response.data.message).addClass('error');
                            } else {
                                alert(response.data.message);
                            }
                            $checkoutButton.removeClass('disabled').css('opacity', '1');
                        }
                    })
                    .fail(function() {
                        // Handle server errors
                        if ($status.length) {
                            $status.html('A server error occurred. Please try again.').addClass('error');
                        } else {
                            alert('A server error occurred. Please try again.');
                        }
                        $checkoutButton.removeClass('disabled').css('opacity', '1');
                    });
            });
        }
    }
});
