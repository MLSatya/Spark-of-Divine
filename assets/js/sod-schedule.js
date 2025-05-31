/**
 * SOD Schedule Handler
 * 
 * Handles schedule interactions, mobile view adjustments, and booking forms.
 * Updated with improved title display and event category detection.
 */
jQuery(document).ready(function($) {
    'use strict';

    const sodScheduleHandler = {
        /**
         * Initialize the schedule handler
         */
        init: function() {
            this.setupEventHandlers();
            this.switchMobileView(); // Switch to mobile view if necessary
            this.setupCategoryDetection(); // Set up detection for special categories
            console.log('SOD Schedule Handler initialized');
        },

        /**
         * Set up all event handlers
         */
        setupEventHandlers: function() {
            // View selector handling
            $('#view').on('change', function() {
                $(this).closest('form').submit();
            });

            // Filter form handling
            $('#filter-form select').on('change', function() {
                // Show loading indicator
                $('.sod-schedule-container').addClass('loading');
                
                // Submit the form after a small delay to prevent rapid multiple submissions
                setTimeout(function() {
                    $('#filter-form').submit();
                }, 100);
            });

            // Calendar navigation handling
            $('.calendar-nav a.nav-button').on('click', function(e) {
                e.preventDefault();
                $('.sod-schedule-container').addClass('loading');
                window.location.href = $(this).attr('href');
            });

            // Day tab switching for week view
            $('.view-week .calendar-header .calendar-cell, .mobile-day-section h3').on('click', function(e) {
                if ($(this).find('a.day-link').length) {
                    e.preventDefault();
                    $('.sod-schedule-container').addClass('loading');
                    window.location.href = $(this).find('a.day-link').attr('href');
                }
            });

            // Booking form submission
            $('.booking-form').on('submit', function(e) {
                e.preventDefault();
                const $form = $(this);
                
                // Validate the form first
                if (!sodScheduleHandler.validateBookingForm($form)) {
                    return false;
                }
                
                const formData = new FormData($form[0]);
                sodScheduleHandler.processBookingForm(formData, $form);
            });

            // Attribute select change handler
            $('.attribute-select').on('change', function() {
                const $selected = $(this).find('option:selected');
                const $form = $(this).closest('form');
                
                // Remove any previous dynamic fields
                $form.find('input[name="product_id"][data-dynamic="true"]').remove();
                $form.find('input[name="variation_id"][data-dynamic="true"]').remove();
                $form.find('input[name="duration"]').remove();
                
                // Add new dynamic fields based on selection
                if ($selected.data('duration')) {
                    $form.append('<input type="hidden" name="duration" value="' + $selected.data('duration') + '">');
                }
                if ($selected.data('product-id')) {
                    $form.find('.default-product-id').remove(); // Remove default if attribute overrides
                    $form.append('<input type="hidden" name="product_id" value="' + $selected.data('product-id') + '" data-dynamic="true">');
                }
                if ($selected.data('variation-id')) {
                    $form.append('<input type="hidden" name="variation_id" value="' + $selected.data('variation-id') + '" data-dynamic="true">');
                }
            });
            
            // When any input changes, update the hidden inputs for proper form submission
            $('input, select').on('change keyup', function() {
                const $form = $(this).closest('form');
                const $inputs = $form.find('input, select').not('[type="submit"]');
                
                // Check if all required fields have values
                let allFilled = true;
                $inputs.filter('[required]').each(function() {
                    if (!$(this).val()) {
                        allFilled = false;
                    }
                });
                
                // Enable or disable the submit button
                $form.find('[type="submit"]').prop('disabled', !allFilled);
            });
        },

        /**
         * Validate booking form
         * @param {jQuery} $form - The form to validate
         * @return {boolean} - True if valid, false otherwise
         */
        validateBookingForm: function($form) {
            // Check required selects
            const $requiredSelects = $form.find('select[required]');
            let isValid = true;
            
            $requiredSelects.each(function() {
                if (!$(this).val()) {
                    isValid = false;
                    $(this).addClass('error');
                } else {
                    $(this).removeClass('error');
                }
            });
            
            if (!isValid) {
                alert('Please fill in all required fields.');
                return false;
            }
            
            return true;
        },

        /**
         * Switch to mobile view if on a mobile device
         */
        switchMobileView: function() {
            const checkMobileView = function() {
                if ($(window).width() <= 767 && $('#week-calendar').length > 0) {
                    $('#week-calendar .desktop-view').hide();
                    $('#mobile-week-calendar').show();
                } else if ($(window).width() > 767 && $('#week-calendar').length > 0) {
                    $('#week-calendar .desktop-view').show();
                    $('#mobile-week-calendar').hide();
                }
            };
            
            // Check on init
            checkMobileView();
            
            // Also attach to window resize
            $(window).off('resize.sodSchedule').on('resize.sodSchedule', checkMobileView);
        },

        /**
         * Set up detection for special product categories like events
         */
        setupCategoryDetection: function() {
            // Detect event category products
            $('.schedule-slot, .day-slot-item').each(function() {
                const $slot = $(this);
                let isEvent = false;
                
                // Look for event category in data attribute
                if ($slot.data('category') === 'events' || $slot.attr('data-category') === 'events') {
                    isEvent = true;
                }
                
                // Check for hidden input indicating event category
                if ($slot.find('input[name="event_category"][value="1"]').length > 0) {
                    isEvent = true;
                }
                
                // Look for event category in links
                if ($slot.find('a[href*="product_cat=events"]').length > 0) {
                    isEvent = true;
                }
                
                // Apply event slot class if needed
                if (isEvent && !$slot.hasClass('event-slot')) {
                    $slot.addClass('event-slot');
                }

                // Check for appointment-only flag
                if ($slot.hasClass('appointment-only') || $slot.data('appointment-only') === true || 
                    $slot.attr('data-appointment-only') === 'true' || 
                    $slot.find('input[name="appointment_only"][value="1"]').length > 0) {
                    if (!$slot.hasClass('appointment-only')) {
                        $slot.addClass('appointment-only');
                    }
                }
            });
        },

        /**
         * Process booking form
         * @param {FormData} formData - Form data to submit
         * @param {jQuery} $form - The form element
         */
        processBookingForm: function(formData, $form) {
            console.log('Schedule: Processing booking form');

            // Add submit button state
            const $submitButton = $form.find('button[type="submit"]');
            $submitButton.prop('disabled', true).addClass('loading').text('Processing...');

            // Create response container if needed
            if ($form.siblings('.booking-response').length === 0) {
                $form.after('<div class="booking-response"></div>');
            }
            const $responseContainer = $form.siblings('.booking-response');
            $responseContainer.html('<div class="booking-processing">Processing your booking...</div>');

            // Process the form data
            $.ajax({
                url: typeof sodBooking !== 'undefined' ? sodBooking.ajax_url : ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    console.log('Schedule: Booking response received', response);

                    // Reset button state
                    $submitButton.prop('disabled', false).removeClass('loading').text('BOOK');

                    // Parse response if needed
                    if (typeof response === 'string') {
                        try {
                            response = JSON.parse(response);
                        } catch (e) {
                            console.error('Failed to parse response:', e);
                        }
                    }

                    if (response.success && response.data) {
                        const message = response.data.message || 'Booking created successfully!';
                        $responseContainer.html('<div class="booking-success">' + message + '</div>');

                        if (response.data.requires_payment && response.data.cart_url) {
                            console.log('Schedule: Redirecting to cart', response.data.cart_url);
                            $responseContainer.append('<div class="redirect-message">Redirecting to cart...</div>');
                            setTimeout(function() {
                                window.location.href = response.data.cart_url;
                            }, 1500);
                        } else {
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        }
                    } else {
                        const errorMsg = response.data && response.data.message ? 
                            response.data.message : 'Error processing booking.';
                        $responseContainer.html('<div class="booking-error">' + errorMsg + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Schedule: AJAX error', status, error);

                    // Reset button state
                    $submitButton.prop('disabled', false).removeClass('loading').text('BOOK');

                    // Show error message
                    $responseContainer.html('<div class="booking-error">Server error. Please try again.</div>');
                }
            });
        },
        
        /**
         * Display appropriate form fields based on product type
         * @param {jQuery} $form - The form element
         */
        handleProductType: function($form) {
            // Get product type from data attribute or determine from classes
            let productType = 'standard';
            const $slot = $form.closest('.schedule-slot, .day-slot-item');
            
            if ($slot.hasClass('appointment-only')) {
                productType = 'appointment';
            } else if ($slot.hasClass('class-slot')) {
                productType = 'class';
            } else if ($slot.hasClass('package-slot')) {
                productType = 'package';
            } else if ($slot.hasClass('passes-slot')) {
                productType = 'passes';
            }
            
            // Handle form display based on product type
            switch (productType) {
                case 'appointment':
                    // For appointments, we need both duration and timeslot
                    $form.find('.duration-select, select[name="timeslot"]').show();
                    break;
                    
                case 'passes':
                case 'package':
                    // For passes and packages, we only need the attribute selection
                    $form.find('select[name="timeslot"]').hide();
                    break;
                    
                case 'class':
                    // For classes, simplify the interface
                    const $attributeSelect = $form.find('.attribute-select');
                    if ($attributeSelect.find('option').length === 2) { // Just the placeholder and one real option
                        $attributeSelect.val($attributeSelect.find('option:last-child').val()).trigger('change');
                        $attributeSelect.hide();
                    }
                    $form.find('select[name="timeslot"]').hide();
                    break;
                    
                default:
                    // Default handling - show everything
                    break;
            }
        }
    };

    // Initialize the schedule handler
    sodScheduleHandler.init();
    
    // Process each form on the page to handle product types
    $('.booking-form').each(function() {
        sodScheduleHandler.handleProductType($(this));
    });

    // Expose globally
    window.sodScheduleHandler = sodScheduleHandler;
});