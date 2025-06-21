/**
 * SOD Cart Contact Fields Handler
 */
(function($) {
    'use strict';

    var SODCartContact = {
        init: function() {
            this.bindEvents();
            this.checkRequiredFields();
        },

        bindEvents: function() {
            $(document).on('submit', '#sod-contact-form', this.handleSubmit);
            $(document).on('blur', '#sod-contact-form input', this.validateField);
            
            // Block checkout if fields not filled
            $(document).on('click', '.wc-proceed-to-checkout a, .checkout-button', this.checkBeforeCheckout);
        },

        handleSubmit: function(e) {
            e.preventDefault();
            
            var form = $(this);
            var data = {
                action: 'sod_save_cart_contact_info',
                nonce: sodCartContact.nonce,
                first_name: form.find('[name="sod_first_name"]').val(),
                last_name: form.find('[name="sod_last_name"]').val(),
                email: form.find('[name="sod_email"]').val(),
                phone: form.find('[name="sod_phone"]').val()
            };

            form.find('button').prop('disabled', true).text(sodCartContact.strings.saving);

            $.post(sodCartContact.ajax_url, data)
                .done(function(response) {
                    if (response.success) {
                        form.find('.sod-success-message').show();
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        alert(response.data.message || sodCartContact.strings.error);
                    }
                })
                .fail(function() {
                    alert(sodCartContact.strings.error);
                })
                .always(function() {
                    form.find('button').prop('disabled', false).text(sodCartContact.strings.save);
                });
        },

        validateField: function() {
            var field = $(this);
            var value = field.val().trim();
            var type = field.attr('type');
            var isValid = true;

            if (field.prop('required') && !value) {
                isValid = false;
            } else if (type === 'email' && value) {
                isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
            }

            field.toggleClass('error', !isValid);
            return isValid;
        },

        checkRequiredFields: function() {
            var allFilled = true;
            $('#sod-contact-form input[required]').each(function() {
                if (!$(this).val().trim()) {
                    allFilled = false;
                    return false;
                }
            });

            $('.wc-proceed-to-checkout a, .checkout-button').toggleClass('disabled', !allFilled);
        },

        checkBeforeCheckout: function(e) {
            var allFilled = true;
            $('#sod-contact-form input[required]').each(function() {
                if (!$(this).val().trim()) {
                    allFilled = false;
                    $(this).addClass('error');
                }
            });

            if (!allFilled) {
                e.preventDefault();
                alert(sodCartContact.strings.fillRequired);
                $('html, body').animate({
                    scrollTop: $('#sod-contact-form').offset().top - 100
                }, 500);
            }
        }
    };

    $(document).ready(function() {
        if ($('#sod-contact-form').length) {
            SODCartContact.init();
        }
    });

})(jQuery);
