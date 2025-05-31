/**
 * Spark of Divine Scheduler - Bug Fixes
 * 
 * This file contains fixes for the booking functionality:
 * 1. Fix the datepicker dependency issue
 * 2. Add better error handling for AJAX requests
 * 3. Updated to use sod_process_booking endpoint
 */

jQuery(document).ready(function($) {
    console.log('SOD Scheduler Fixes loaded');
    
    // Fix 1: Check if datepicker is available, if not, load it dynamically
    if (typeof $.fn.datepicker !== 'function') {
        console.log('jQuery UI Datepicker not found - loading dynamically');
        
        const jqueryUILoaded = typeof $.ui !== 'undefined';
        
        if (!jqueryUILoaded) {
            const uiCoreScript = document.createElement('script');
            uiCoreScript.src = 'https://code.jquery.com/ui/1.13.2/jquery-ui.min.js';
            uiCoreScript.integrity = 'sha256-lSjKY0/srUM9BE3dPm+c4fBo1dky2v27Gdjm2uoZaL0=';
            uiCoreScript.crossOrigin = 'anonymous';
            document.head.appendChild(uiCoreScript);
            
            const uiCssLink = document.createElement('link');
            uiCssLink.rel = 'stylesheet';
            uiCssLink.href = 'https://code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css';
            document.head.appendChild(uiCssLink);
            
            uiCoreScript.onload = function() {
                initializeBookingFormHandlers();
            };
        } else {
            $.getScript('https://code.jquery.com/ui/1.13.2/jquery-ui.min.js')
                .done(function() {
                    initializeBookingFormHandlers();
                })
                .fail(function() {
                    console.error('Failed to load jQuery UI Datepicker');
                });
        }
    } else {
        initializeBookingFormHandlers();
    }

    // Fix 2: Improve error handling for AJAX requests
    $(document).ajaxError(function(event, jqXHR, ajaxSettings, thrownError) {
        if (ajaxSettings.url && ajaxSettings.url.includes('admin-ajax.php')) {
            console.error('AJAX Error Details:', {
                status: jqXHR.status,
                statusText: jqXHR.statusText,
                responseText: jqXHR.responseText,
                error: thrownError
            });

            const errorContainer = $('.booking-response');
            if (errorContainer.length === 0) {
                $('.booking-form').after('<div class="booking-response"></div>');
            }

            let errorMessage = 'Server error occurred. Please try again or contact support.';
            try {
                if (jqXHR.responseText) {
                    if (jqXHR.responseText.includes('Fatal error') || 
                        jqXHR.responseText.includes('Parse error') ||
                        jqXHR.responseText.includes('Warning')) {
                        errorMessage = 'Technical error occurred. Please contact support.';
                        const errorMatch = jqXHR.responseText.match(/(Fatal error|Parse error|Warning):[^<]+/);
                        if (errorMatch) {
                            console.error('PHP Error:', errorMatch[0]);
                        }
                    } else {
                        const jsonResponse = JSON.parse(jqXHR.responseText);
                        if (jsonResponse && jsonResponse.data && jsonResponse.data.message) {
                            errorMessage = jsonResponse.data.message;
                        }
                    }
                }
            } catch (e) {
                console.log('Could not parse error response:', e);
            }

            $('.booking-response').html('<div class="booking-error">' + errorMessage + '</div>');
        }
    });

    function initializeBookingFormHandlers() {
        console.log('Initializing booking form handlers');

        // Initialize datepicker
        $('#sod_booking_date').datepicker({
            dateFormat: 'yy-mm-dd',
            minDate: 0,
            onSelect: function(dateText) {
                const duration = getDuration();
                updateTimeslots(duration);
            }
        });

        // Update timeslots when staff changes
        $('#sod_staff_id').on('change', function() {
            if ($('#sod_booking_date').val()) {
                const duration = getDuration();
                updateTimeslots(duration);
            }
        });

        // Handle attribute/duration selection changes
        $('.attribute-select').on('change', function() {
            const $selected = $(this).find('option:selected');
            const $form = $(this).closest('form');

            // Remove any previous dynamic fields
            $form.find('input[name="product_id"][data-dynamic="true"]').remove();
            $form.find('input[name="variation_id"][data-dynamic="true"]').remove();
            $form.find('input[name="duration"]').remove();

            // Capture selection data
            const selectedData = {
                value: $selected.val(),
                productId: $selected.data('product-id'),
                variationId: $selected.data('variation-id'),
                passes: $selected.data('passes'),
                price: $selected.data('price')
            };

            console.log('Attribute selected:', selectedData);

            // Add new dynamic fields based on selection
            let duration = 60; // Default duration

            if ($selected.data('duration')) {
                duration = parseInt($selected.data('duration'));
                $form.append('<input type="hidden" name="duration" value="' + duration + '">');
            } else {
                // Try to extract duration from the value if possible
                try {
                    const valueObj = JSON.parse($selected.val());
                    if (valueObj && valueObj.type === 'duration' && valueObj.value) {
                        const durationStr = valueObj.value.replace(/[^0-9]/g, '');
                        duration = parseInt(durationStr);
                        $form.append('<input type="hidden" name="duration" value="' + duration + '">');
                    }
                } catch(e) {
                    // Not valid JSON, ignore
                }
            }

            if ($selected.data('product-id')) {
                $form.find('.default-product-id').remove(); // Remove default if attribute overrides
                $form.append('<input type="hidden" name="product_id" value="' + $selected.data('product-id') + '" data-dynamic="true">');
            }

            if ($selected.data('variation-id')) {
                $form.append('<input type="hidden" name="variation_id" value="' + $selected.data('variation-id') + '" data-dynamic="true">');
            }

            // Update timeslots based on new duration
            if ($('#sod_booking_date').val() && $('#sod_staff_id').val()) {
                updateTimeslots(duration);
            }
        });

        // Handle booking form submission
        $('.booking-form').on('submit', function(e) {
            e.preventDefault();

            // Clear previous responses
            $('.booking-response').empty();

            // Show loading state
            $('.booking-response').html('<div class="booking-processing">Processing your booking...</div>');

            // Get selected attribute data
            const $selected = $('.attribute-select option:selected');
            const selectedData = {
                value: $selected.val(),
                productId: $selected.data('product-id') || $('input[name="product_id"]').val(),
                variationId: $selected.data('variation-id') || $('input[name="variation_id"]').val(),
                duration: getDuration()
            };

            // Prepare data for AJAX submission
            const formData = new FormData();

            // Add required fields - using the updated action name
            formData.append('action', 'sod_process_booking');
            formData.append('product_id', selectedData.productId);
            formData.append('variation_id', selectedData.variationId);
            formData.append('duration', selectedData.duration);
            formData.append('staff_id', $('#sod_staff_id').val());
            formData.append('booking_date', $('#sod_booking_date').val());
            formData.append('timeslot', $('#sod_timeslot').val());
            formData.append('attribute', selectedData.value);
            formData.append('nonce', typeof sodBooking !== 'undefined' ? sodBooking.nonce : $('input[name="nonce"]').val());

            // Log what we're sending for debugging
            console.log('Submitting booking with data:', {
                action: 'sod_process_booking',
                product_id: selectedData.productId,
                variation_id: selectedData.variationId,
                duration: selectedData.duration,
                staff_id: $('#sod_staff_id').val(),
                booking_date: $('#sod_booking_date').val(),
                timeslot: $('#sod_timeslot').val(),
                attribute: selectedData.value,
                nonce: typeof sodBooking !== 'undefined' ? 'valid-nonce' : $('input[name="nonce"]').val()
            });

            // Submit booking request
            $.ajax({
                url: typeof sodBooking !== 'undefined' ? sodBooking.ajax_url : ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    console.log('Booking response:', response);
                    
                    if (response.success) {
                        $('.booking-response').html('<div class="booking-success">' + response.data.message + '</div>');
                        
                        // If payment is required, redirect to cart or show additional info
                        if (response.data.requires_payment && response.data.cart_url) {
                            setTimeout(function() {
                                window.location.href = response.data.cart_url;
                            }, 1500);
                        }
                    } else {
                        $('.booking-response').html('<div class="booking-error">' + 
                            (response.data && response.data.message ? response.data.message : 'Error processing booking.') + 
                            '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Booking submission error:', status, error);
                    $('.booking-response').html('<div class="booking-error">Unable to process booking. Please try again.</div>');
                }
            });
        });
    }

    // Helper function to get the current selected duration
    function getDuration() {
        // First check for a duration field
        const $durationField = $('input[name="duration"]');
        if ($durationField.length > 0) {
            return parseInt($durationField.val());
        }
        
        // Next, check for a selected attribute that might have duration
        const $selected = $('.attribute-select option:selected');
        if ($selected.length > 0) {
            if ($selected.data('duration')) {
                return parseInt($selected.data('duration'));
            }
            
            // Try to extract duration from the value if possible
            try {
                const valueObj = JSON.parse($selected.val());
                if (valueObj && valueObj.type === 'duration' && valueObj.value) {
                    const durationStr = valueObj.value.replace(/[^0-9]/g, '');
                    return parseInt(durationStr);
                }
            } catch(e) {
                // Not valid JSON, ignore
            }
        }
        
        // Default to 60 minutes
        return 60;
    }
    
    // Function to update available timeslots
    function updateTimeslots(duration) {
        const staffId = $('#sod_staff_id').val();
        const date = $('#sod_booking_date').val();
        
        if (!staffId || !date) return;
        
        // Show loading indicator
        const $timeslotSelect = $('#sod_timeslot');
        $timeslotSelect.empty().append('<option value="">Loading...</option>');
        $timeslotSelect.prop('disabled', true);
        
        $.ajax({
            url: typeof sodBooking !== 'undefined' ? sodBooking.ajax_url : ajaxurl,
            type: 'POST',
            data: {
                action: 'sod_get_available_timeslots',
                staff_id: staffId,
                date: date,
                duration: duration || 60, // Use passed duration or default to 60
                nonce: typeof sodBooking !== 'undefined' ? sodBooking.nonce : $('input[name="nonce"]').val()
            },
            success: function(response) {
                $timeslotSelect.empty();
                $timeslotSelect.append('<option value="">Select Timeslot</option>');
                $timeslotSelect.prop('disabled', false);
                
                if (response.success && response.data.timeslots && response.data.timeslots.length > 0) {
                    $.each(response.data.timeslots, function(index, timeslot) {
                        $timeslotSelect.append(
                            $('<option>', {
                                value: timeslot.time,
                                text: timeslot.formatted
                            })
                        );
                    });
                } else {
                    $timeslotSelect.append('<option value="" disabled>No available timeslots</option>');
                    if (response.data && response.data.message) {
                        console.log('Timeslot error:', response.data.message);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                $timeslotSelect.empty();
                $timeslotSelect.append('<option value="">Select Timeslot</option>');
                $timeslotSelect.append('<option value="" disabled>Error loading timeslots</option>');
                $timeslotSelect.prop('disabled', false);
            }
        });
    }
});