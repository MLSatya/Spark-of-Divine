/**
 * Enhanced SOD Booking Integration
 * 
 * This extends your existing sod-booking-form.js with the enhanced time slot functionality
 * while preserving all your current features.
 */

jQuery(document).ready(function($) {
    console.log('SOD Enhanced Booking Integration loaded');
    
    // Extend your existing functionality with enhanced features
    window.SODBookingEnhanced = {
        
        // Configuration - matches your existing setup
        config: {
            defaultDuration: 60,
            slotInterval: 15,
            debugMode: typeof WP_DEBUG !== 'undefined' && WP_DEBUG,
            ajaxUrl: typeof sodBooking !== 'undefined' ? sodBooking.ajax_url : ajaxurl,
            nonce: typeof sodBooking !== 'undefined' ? sodBooking.nonce : $('input[name="nonce"]').val()
        },
        
        // Current booking state
        state: {
            selectedStaff: null,
            selectedProduct: null,
            selectedDate: null,
            selectedDuration: null,
            selectedTimeSlot: null,
            availableSlots: []
        },
        
        // Initialize enhanced features
        init: function() {
            this.enhanceExistingFunctionality();
            this.bindEnhancedEvents();
            console.log('Enhanced booking features initialized');
        },
        
        // Enhance your existing updateTimeslots function
        enhanceExistingFunctionality: function() {
            var self = this;
            
            // Override the existing updateTimeslots function to use enhanced endpoint
            if (typeof window.updateTimeslots === 'function') {
                window.updateTimeslots = function(duration) {
                    self.updateTimeslotsEnhanced(duration);
                };
            }
            
            // Enhance the existing getDuration function
            if (typeof window.getDuration === 'function') {
                var originalGetDuration = window.getDuration;
                window.getDuration = function() {
                    var duration = originalGetDuration();
                    self.state.selectedDuration = duration;
                    return duration;
                };
            }
        },
        
        // Bind enhanced event handlers
        bindEnhancedEvents: function() {
            var self = this;
            
            // Enhanced duration change handler
            $(document).on('change', '.attribute-select, .duration-select', function() {
                var duration = self.extractDurationFromSelection($(this));
                if (duration && duration !== self.state.selectedDuration) {
                    self.state.selectedDuration = duration;
                    self.handleDurationChange(duration, $(this));
                }
            });
            
            // Enhanced staff selection
            $(document).on('change', '#sod_staff_id', function() {
                var staffId = parseInt($(this).val());
                if (staffId !== self.state.selectedStaff) {
                    self.state.selectedStaff = staffId;
                    self.handleStaffChange(staffId, $(this));
                }
            });
            
            // Enhanced date selection
            $(document).on('change', '#sod_booking_date', function() {
                var date = $(this).val();
                if (date !== self.state.selectedDate) {
                    self.state.selectedDate = date;
                    self.handleDateChange(date, $(this));
                }
            });
            
            // Enhanced time slot selection with conflict checking
            $(document).on('click', '.time-slot, .time-slot-option', function(e) {
                e.preventDefault();
                var timeSlot = $(this).data('time') || $(this).val();
                if (timeSlot && !$(this).hasClass('disabled')) {
                    self.selectTimeSlot(timeSlot, $(this));
                }
            });
        },
        
        // Enhanced timeslot updating with better conflict detection
        updateTimeslotsEnhanced: function(duration) {
            var self = this;
            var staffId = $('#sod_staff_id').val();
            var date = $('#sod_booking_date').val();
            var productId = $('input[name="product_id"]').val() || 0;
            
            if (!staffId || !date) {
                console.log('Missing staff or date for timeslot update');
                return;
            }
            
            duration = duration || this.state.selectedDuration || this.config.defaultDuration;
            
            console.log('Updating timeslots with enhanced method:', {
                staffId: staffId,
                date: date,
                productId: productId,
                duration: duration
            });
            
            // Show loading state
            var $timeslotSelect = $('#sod_timeslot');
            $timeslotSelect.empty().append('<option value="">Loading available times...</option>');
            $timeslotSelect.prop('disabled', true);
            
            // Use enhanced endpoint with fallback to existing
            var actionName = 'sod_get_available_timeslots_enhanced';
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: actionName,
                    staff_id: staffId,
                    date: date,
                    product_id: productId,
                    duration: duration,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    console.log('Enhanced timeslots response:', response);
                    
                    if (response.success && response.data.timeslots) {
                        self.renderEnhancedTimeslots(response.data.timeslots, $timeslotSelect);
                        self.state.availableSlots = response.data.timeslots;
                    } else {
                        // Fallback to original endpoint
                        self.fallbackToOriginalTimeslots(staffId, date, duration, $timeslotSelect);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Enhanced timeslots error, falling back:', error);
                    // Fallback to original endpoint
                    self.fallbackToOriginalTimeslots(staffId, date, duration, $timeslotSelect);
                }
            });
        },
        
        // Fallback to your existing timeslot method
        fallbackToOriginalTimeslots: function(staffId, date, duration, $timeslotSelect) {
            var self = this;
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sod_get_available_timeslots',
                    staff_id: staffId,
                    date: date,
                    duration: duration,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    console.log('Fallback timeslots response:', response);
                    
                    $timeslotSelect.empty().append('<option value="">Select Timeslot</option>');
                    $timeslotSelect.prop('disabled', false);
                    
                    if (response.success && response.data.timeslots && response.data.timeslots.length > 0) {
                        $.each(response.data.timeslots, function(index, timeslot) {
                            $timeslotSelect.append(
                                $('<option>', {
                                    value: timeslot.time,
                                    text: timeslot.formatted || timeslot.time
                                })
                            );
                        });
                        self.state.availableSlots = response.data.timeslots;
                    } else {
                        $timeslotSelect.append('<option value="" disabled>No available timeslots</option>');
                        self.state.availableSlots = [];
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Fallback timeslots error:', error);
                    $timeslotSelect.empty().append('<option value="">Error loading timeslots</option>');
                    $timeslotSelect.prop('disabled', false);
                    self.state.availableSlots = [];
                }
            });
        },
        
        // Render enhanced timeslots with better formatting
        renderEnhancedTimeslots: function(timeslots, $timeslotSelect) {
            $timeslotSelect.empty().append('<option value="">Select Time Slot</option>');
            $timeslotSelect.prop('disabled', false);
            
            if (timeslots && timeslots.length > 0) {
                $.each(timeslots, function(index, slot) {
                    var displayText = slot.formatted || slot.start_formatted || slot.time;
                    if (slot.end_formatted) {
                        displayText = slot.start_formatted + ' - ' + slot.end_formatted;
                    }
                    
                    $timeslotSelect.append(
                        $('<option>', {
                            value: slot.time,
                            text: displayText,
                            'data-duration': slot.duration || null,
                            'data-start': slot.start_formatted || null,
                            'data-end': slot.end_formatted || null
                        })
                    );
                });
                
                console.log('Rendered ' + timeslots.length + ' enhanced timeslots');
            } else {
                $timeslotSelect.append('<option value="" disabled>No available time slots for selected duration</option>');
                console.log('No timeslots available for current selection');
            }
        },
        
        // Extract duration from various selection types
        extractDurationFromSelection: function($element) {
            var duration = null;
            
            // Check for direct duration data attribute
            if ($element.is('option')) {
                duration = $element.data('duration');
            } else {
                var $selected = $element.find('option:selected');
                duration = $selected.data('duration');
            }
            
            // Try to extract from value if not found
            if (!duration) {
                var value = $element.is('option') ? $element.val() : $element.find('option:selected').val();
                
                try {
                    var valueObj = JSON.parse(value);
                    if (valueObj && valueObj.type === 'duration' && valueObj.value) {
                        var durationStr = valueObj.value.replace(/[^0-9]/g, '');
                        duration = parseInt(durationStr);
                    }
                } catch(e) {
                    // Try direct numeric extraction
                    var numericMatch = value.match(/(\d+)\s*(?:min|minute)/i);
                    if (numericMatch) {
                        duration = parseInt(numericMatch[1]);
                    }
                }
            }
            
            return duration || this.config.defaultDuration;
        },
        
        // Handle duration change with enhanced logic
        handleDurationChange: function(duration, $element) {
            console.log('Enhanced duration change:', duration);
            
            // Update any duration displays
            $('.selected-duration-display').text(duration + ' minutes');
            
            // Update hidden duration field
            var $form = $element.closest('form');
            $form.find('input[name="duration"]').remove();
            $form.append('<input type="hidden" name="duration" value="' + duration + '">');
            
            // Clear previous time slot selection
            this.clearTimeSlotSelection();
            
            // Update timeslots if we have all required data
            if (this.state.selectedStaff && this.state.selectedDate) {
                this.updateTimeslotsEnhanced(duration);
            }
        },
        
        // Handle staff change
        handleStaffChange: function(staffId, $element) {
            console.log('Enhanced staff change:', staffId);
            this.clearTimeSlotSelection();
            
            if (this.state.selectedDate && this.state.selectedDuration) {
                this.updateTimeslotsEnhanced(this.state.selectedDuration);
            }
        },
        
        // Handle date change
        handleDateChange: function(date, $element) {
            console.log('Enhanced date change:', date);
            this.clearTimeSlotSelection();
            
            if (this.state.selectedStaff && this.state.selectedDuration) {
                this.updateTimeslotsEnhanced(this.state.selectedDuration);
            }
        },
        
        // Enhanced time slot selection
        selectTimeSlot: function(timeSlot, $element) {
            console.log('Enhanced time slot selection:', timeSlot);
            
            this.state.selectedTimeSlot = timeSlot;
            
            // Update UI
            $('.time-slot, .time-slot-option').removeClass('selected');
            $element.addClass('selected');
            
            // Update form fields
            $('#sod_timeslot').val(timeSlot);
            $('input[name="timeslot"]').val(timeSlot);
            
            // Enable booking button
            this.updateBookingButton(true);
            
            // Show booking summary if container exists
            this.updateBookingSummary();
        },
        
        // Clear time slot selection
        clearTimeSlotSelection: function() {
            this.state.selectedTimeSlot = null;
            $('.time-slot, .time-slot-option').removeClass('selected');
            $('#sod_timeslot').val('');
            $('input[name="timeslot"]').val('');
            this.updateBookingButton(false);
        },
        
        // Update booking button state
        updateBookingButton: function(enabled) {
            var $buttons = $('.book-now, .sod-book-button, .booking-submit, button[type="submit"]');
            
            if (enabled && this.hasCompleteBookingInfo()) {
                $buttons.prop('disabled', false).removeClass('disabled');
            } else {
                $buttons.prop('disabled', true).addClass('disabled');
            }
        },
        
        // Check if we have complete booking information
        hasCompleteBookingInfo: function() {
            return this.state.selectedStaff && 
                   this.state.selectedDate && 
                   this.state.selectedDuration && 
                   this.state.selectedTimeSlot;
        },
        
        // Update booking summary display
        updateBookingSummary: function() {
            var $summary = $('.booking-summary, .sod-booking-summary');
            if (!$summary.length || !this.hasCompleteBookingInfo()) return;
            
            var staffName = $('#sod_staff_id option:selected').text() || 'Selected Staff';
            var productName = $('.attribute-select option:selected').text() || 'Selected Service';
            var dateFormatted = new Date(this.state.selectedDate).toLocaleDateString();
            
            // Find the selected timeslot display text
            var timeFormatted = 'Selected Time';
            var $selectedSlot = $('.time-slot.selected, .time-slot-option.selected');
            if ($selectedSlot.length) {
                timeFormatted = $selectedSlot.text() || $selectedSlot.data('formatted') || this.state.selectedTimeSlot;
            } else {
                timeFormatted = $('#sod_timeslot option:selected').text() || this.state.selectedTimeSlot;
            }
            
            var summaryHtml = 
                '<h4>Booking Summary:</h4>' +
                '<p><strong>Service:</strong> ' + productName + '</p>' +
                '<p><strong>Staff:</strong> ' + staffName + '</p>' +
                '<p><strong>Date:</strong> ' + dateFormatted + '</p>' +
                '<p><strong>Time:</strong> ' + timeFormatted + '</p>' +
                '<p><strong>Duration:</strong> ' + this.state.selectedDuration + ' minutes</p>';
            
            $summary.html(summaryHtml).show();
        },
        
        // Enhanced booking validation
        validateBookingBeforeSubmit: function($form) {
            var errors = [];
            
            // Check required fields
            if (!this.state.selectedStaff || !$('#sod_staff_id').val()) {
                errors.push('Please select a staff member');
            }
            
            if (!this.state.selectedDate || !$('#sod_booking_date').val()) {
                errors.push('Please select a date');
            }
            
            if (!this.state.selectedTimeSlot || !$('#sod_timeslot').val()) {
                errors.push('Please select a time slot');
            }
            
            var $attributeSelect = $('.attribute-select');
            if ($attributeSelect.length && !$attributeSelect.val()) {
                errors.push('Please select a service option');
            }
            
            if (errors.length > 0) {
                this.showValidationErrors(errors);
                return false;
            }
            
            return true;
        },
        
        // Show validation errors
        showValidationErrors: function(errors) {
            var $errorContainer = $('.booking-errors, .sod-booking-errors');
            
            if (!$errorContainer.length) {
                $errorContainer = $('<div class="booking-errors sod-booking-errors"></div>');
                $('.booking-form').prepend($errorContainer);
            }
            
            var errorHtml = '<div class="error-message"><ul>';
            errors.forEach(function(error) {
                errorHtml += '<li>' + error + '</li>';
            });
            errorHtml += '</ul></div>';
            
            $errorContainer.html(errorHtml).show();
            
            // Scroll to errors
            $('html, body').animate({
                scrollTop: $errorContainer.offset().top - 100
            }, 500);
        },
        
        // Debug helper
        getDebugInfo: function() {
            return {
                state: this.state,
                config: this.config,
                availableSlots: this.state.availableSlots.length,
                hasCompleteInfo: this.hasCompleteBookingInfo()
            };
        }
    };
    
    // Initialize enhanced booking system
    if (typeof window.SODBookingEnhanced !== 'undefined') {
        window.SODBookingEnhanced.init();
    }
    
    // Enhance existing form submission validation
    $(document).on('submit', '.booking-form', function(e) {
        if (window.SODBookingEnhanced && !window.SODBookingEnhanced.validateBookingBeforeSubmit($(this))) {
            e.preventDefault();
            return false;
        }
    });
    
    // Auto-initialize state from existing form values
    setTimeout(function() {
        if (window.SODBookingEnhanced) {
            var staffId = $('#sod_staff_id').val();
            var date = $('#sod_booking_date').val();
            var timeslot = $('#sod_timeslot').val();
            
            if (staffId) window.SODBookingEnhanced.state.selectedStaff = parseInt(staffId);
            if (date) window.SODBookingEnhanced.state.selectedDate = date;
            if (timeslot) window.SODBookingEnhanced.state.selectedTimeSlot = timeslot;
            
            // Extract duration from current selection
            var $attributeSelect = $('.attribute-select');
            if ($attributeSelect.length) {
                var duration = window.SODBookingEnhanced.extractDurationFromSelection($attributeSelect);
                window.SODBookingEnhanced.state.selectedDuration = duration;
            }
            
            console.log('Initialized state from existing form:', window.SODBookingEnhanced.state);
        }
    }, 500);
});