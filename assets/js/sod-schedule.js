/**
 * SOD Schedule JavaScript (Fixed)
 * 
 * Handles frontend interactions for the schedule system including:
 * - Filter form submissions
 * - AJAX loading of schedule content
 * - Dynamic timeslot loading
 * - Booking form submissions
 * 
 * This should be saved as: assets/js/sod-schedule.js
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Check if required data is available
    if (typeof sodSchedule === 'undefined') {
        console.log('SOD Schedule: sodSchedule object not found, using fallback configuration');
        window.sodSchedule = {
            ajax_url: ajaxurl || '/wp-admin/admin-ajax.php',
            nonce: '',
            base_url: window.location.origin + '/',
            strings: {
                loading: 'Loading...',
                error: 'Error occurred',
                noResults: 'No results found'
            }
        };
    }
    
    // Initialize the schedule system
    var SODSchedule = {
        
        init: function() {
            this.bindEvents();
            console.log('SOD Schedule system initialized');
        },
        
        bindEvents: function() {
            // Handle view selector changes
            $(document).on('change', '#view-select', this.handleViewChange);
            
            // Handle filter form submissions
            $(document).on('submit', '.filter-form', this.handleFilterSubmission);
            
            // Handle clear filters button
            $(document).on('click', '.filter-clear', this.handleClearFilters);
            
            // Handle attribute selection for dynamic timeslots
            $(document).on('change', '.attribute-select', this.handleAttributeChange);
            
            // Handle booking form submission
            $(document).on('submit', '.booking-form', this.handleBookingSubmission);
            
            // Handle navigation clicks with AJAX (optional enhancement)
            $(document).on('click', '.nav-button', this.handleNavigationClick);
        },
        
        handleViewChange: function(e) {
            var newView = $(this).val();
            var currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('view', newView);
            window.location.href = currentUrl.toString();
        },
        
        handleFilterSubmission: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var formData = $form.serialize();
            var currentUrl = new URL(window.location.href);
            
            // Update URL parameters
            var params = new URLSearchParams(formData);
            for (let [key, value] of params) {
                if (value && value !== '0') {
                    currentUrl.searchParams.set(key, value);
                } else {
                    currentUrl.searchParams.delete(key);
                }
            }
            
            // Show loading state
            SODSchedule.showLoadingState();
            
            // Redirect to new URL (or use AJAX for smoother experience)
            window.location.href = currentUrl.toString();
        },
        
        handleClearFilters: function(e) {
            e.preventDefault();
            
            var href = $(this).attr('href');
            if (href) {
                SODSchedule.showLoadingState();
                window.location.href = href;
            }
        },
        
        handleNavigationClick: function(e) {
            // Optional: Handle navigation with AJAX for smoother experience
            // For now, let the default behavior work (page reload)
            SODSchedule.showLoadingState();
        },
        
        handleAttributeChange: function(e) {
            var $select = $(this);
            var $form = $select.closest('.booking-form');
            var $timeslotSelect = $form.find('select[name="timeslot"]');
            var attributeData = $select.val();
            
            if (!attributeData) {
                $timeslotSelect.html('<option value="">' + (sodSchedule.strings.select_time || 'Select a time') + '</option>');
                return;
            }
            
            try {
                var attribute = JSON.parse(attributeData);
                var duration = $select.find(':selected').data('duration') || 60;
                var productId = $form.find('input[name="product_id"]').val();
                var staffId = $form.find('input[name="staff_id"]').val();
                var date = $form.find('input[name="date"]').val();
                
                // Show loading state
                $timeslotSelect.html('<option value="">' + (sodSchedule.strings.loading || 'Loading...') + '</option>')
                              .prop('disabled', true);
                
                // Fetch available timeslots
                $.ajax({
                    url: sodSchedule.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'sod_get_available_timeslots',
                        nonce: sodSchedule.nonce,
                        product_id: productId,
                        staff_id: staffId,
                        date: date,
                        duration: duration
                    },
                    success: function(response) {
                        console.log('Timeslots response:', response);
                        
                        if (response.success && response.data.timeslots) {
                            var options = '<option value="">' + (sodSchedule.strings.select_time || 'Select a time') + '</option>';
                            $.each(response.data.timeslots, function(index, slot) {
                                options += '<option value="' + slot.time + '">' + slot.timeRange + '</option>';
                            });
                            $timeslotSelect.html(options).prop('disabled', false);
                        } else {
                            $timeslotSelect.html('<option value="">No times available</option>')
                                          .prop('disabled', false);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error loading timeslots:', error);
                        $timeslotSelect.html('<option value="">Error loading times</option>')
                                      .prop('disabled', false);
                    }
                });
                
            } catch (e) {
                console.error('Error parsing attribute data:', e);
                $timeslotSelect.html('<option value="">Error parsing data</option>')
                              .prop('disabled', false);
            }
        },
        
        handleBookingSubmission: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var originalText = $button.text();
            
            // Validate required fields
            var isValid = true;
            $form.find('[required]').each(function() {
                if (!$(this).val()) {
                    isValid = false;
                    $(this).addClass('error');
                } else {
                    $(this).removeClass('error');
                }
            });
            
            if (!isValid) {
                alert('Please fill in all required fields');
                return;
            }
            
            // Show loading state
            $button.text('Booking...').prop('disabled', true);
            
            $.ajax({
                url: $form.attr('action'),
                type: 'POST',
                data: $form.serialize(),
                success: function(response) {
                    console.log('Booking response:', response);
                    
                    if (response.success) {
                        alert(response.data.message || 'Booking created successfully!');
                        if (response.data.redirect) {
                            window.location.href = response.data.redirect;
                        } else {
                            location.reload();
                        }
                    } else {
                        alert(response.data.message || 'Booking failed. Please try again.');
                        $button.text(originalText).prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Booking submission error:', error);
                    alert('An error occurred. Please try again.');
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },
        
        showLoadingState: function() {
            var $container = $('.sod-schedule-container');
            if ($container.length) {
                $container.addClass('loading');
                
                // Remove loading state after a timeout (fallback)
                setTimeout(function() {
                    $container.removeClass('loading');
                }, 30000); // 30 seconds timeout
            }
        },
        
        hideLoadingState: function() {
            $('.sod-schedule-container').removeClass('loading');
        },
        
        // AJAX schedule loading (for future enhancement)
        loadScheduleAjax: function(filters) {
            var $container = $('.sod-schedule-container');
            
            this.showLoadingState();
            
            $.ajax({
                url: sodSchedule.ajax_url,
                type: 'GET',
                data: $.extend({
                    action: 'sod_load_schedule_ajax',
                    nonce: sodSchedule.filter_nonce
                }, filters),
                success: function(response) {
                    if (response.success && response.data.html) {
                        // Update the schedule content
                        var $newContent = $(response.data.html);
                        $container.find('.calendar-grid, .day-view-list').replaceWith($newContent.find('.calendar-grid, .day-view-list'));
                        
                        // Update browser history
                        var newUrl = new URL(window.location.href);
                        $.each(response.data.filters, function(key, value) {
                            if (value && value !== '0') {
                                newUrl.searchParams.set(key, value);
                            } else {
                                newUrl.searchParams.delete(key);
                            }
                        });
                        
                        if (history.pushState) {
                            history.pushState(null, null, newUrl.toString());
                        }
                    } else {
                        console.error('Failed to load schedule:', response);
                        alert(sodSchedule.strings.error || 'Error loading schedule');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                    alert(sodSchedule.strings.error || 'Error loading schedule');
                },
                complete: function() {
                    SODSchedule.hideLoadingState();
                }
            });
        }
    };
    
    // Initialize the system
    SODSchedule.init();
    
    // Make SODSchedule globally available for debugging
    window.SODSchedule = SODSchedule;
    
    // Handle responsive calendar view switching
    function handleResponsiveCalendar() {
        var $desktopView = $('.calendar-wrapper.desktop-view');
        var $mobileView = $('.calendar-wrapper.mobile-view');
        
        if ($(window).width() <= 768) {
            $desktopView.hide();
            $mobileView.show();
        } else {
            $desktopView.show();
            $mobileView.hide();
        }
    }
    
    // Initial responsive check
    handleResponsiveCalendar();
    
    // Handle window resize
    $(window).on('resize', handleResponsiveCalendar);
    
    // Debug logging
    console.log('SOD Schedule JavaScript loaded successfully');
    console.log('Available data:', sodSchedule);
});