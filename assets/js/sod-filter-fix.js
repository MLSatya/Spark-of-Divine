/**
 * SOD Filter System Fix
 * Comprehensive fix for filter functionality
 * 
 * @package SOD
 * @version 3.1
 */

(function($) {
    'use strict';

    console.log('[SOD Filter Fix] Loading filter system fixes...');

    // Main filter handler
    const SODFilterFix = {
        
        // Initialize the filter system
        init: function() {
            console.log('[SOD Filter Fix] Initializing filter system');
            
            this.bindFilterEvents();
            this.fixFormSubmission();
            this.monitorUrlChanges();
            
            // Check initial state
            this.checkFilterState();
        },
        
        // Bind filter change events
        bindFilterEvents: function() {
            console.log('[SOD Filter Fix] Binding filter events');
            
            // Handle all filter changes
            $(document).on('change', '.sod-filter-select, .sod-filter-input, select[name="view"], select[name="product"], select[name="staff"], select[name="category"], input[name="date"]', function(e) {
                console.log('[SOD Filter Fix] Filter changed:', {
                    name: $(this).attr('name'),
                    value: $(this).val()
                });
                
                // Get the form
                const $form = $(this).closest('form');
                
                // If AJAX is available, use it
                if (typeof sodSchedule !== 'undefined' && sodSchedule.ajax_url) {
                    e.preventDefault();
                    SODFilterFix.applyFiltersAjax($form);
                }
                // Otherwise, let form submit normally
            });
            
            // Handle filter form submission
            $(document).on('submit', '.sod-filter-form, .sod-schedule-filters form', function(e) {
                console.log('[SOD Filter Fix] Filter form submitted');
                
                // If AJAX is available, use it
                if (typeof sodSchedule !== 'undefined' && sodSchedule.ajax_url) {
                    e.preventDefault();
                    SODFilterFix.applyFiltersAjax($(this));
                    return false;
                }
                
                // Otherwise, ensure form submits to correct URL
                const $form = $(this);
                if (!$form.attr('action') || $form.attr('action') === '#') {
                    $form.attr('action', window.location.pathname);
                }
            });
            
            // Handle filter submit button
            $(document).on('click', '.sod-filter-submit, .filter-submit', function(e) {
                console.log('[SOD Filter Fix] Filter submit button clicked');
                
                const $form = $(this).closest('form');
                if ($form.length) {
                    $form.trigger('submit');
                }
            });
            
            // Handle filter reset
            $(document).on('click', '.sod-filter-reset, .filter-clear', function(e) {
                console.log('[SOD Filter Fix] Filter reset clicked');
                e.preventDefault();
                
                // Reset to base URL
                window.location.href = window.location.pathname;
            });
        },
        
        // Apply filters using AJAX
        applyFiltersAjax: function($form) {
            console.log('[SOD Filter Fix] Applying filters via AJAX');
            
            // Show loading state
            const $container = $('.sod-schedule-container');
            $container.addClass('loading');
            
            // Add loading overlay if not exists
            if (!$container.find('.sod-loading-overlay').length) {
                $container.append('<div class="sod-loading-overlay"><div class="spinner"></div></div>');
            }
            
            // Collect filter data
            const filterData = {
                action: 'sod_filter_schedule',
                nonce: sodSchedule.filter_nonce || sodSchedule.nonce,
                filters: {}
            };
            
            // Get all filter values
            $form.find('select, input').each(function() {
                const name = $(this).attr('name');
                const value = $(this).val();
                if (name && value) {
                    filterData.filters[name] = value;
                }
            });
            
            console.log('[SOD Filter Fix] Filter data:', filterData);
            
            // Make AJAX request
            $.ajax({
                url: sodSchedule.ajax_url,
                type: 'POST',
                data: filterData,
                success: function(response) {
                    console.log('[SOD Filter Fix] AJAX response received:', response);
                    
                    if (response.success && response.data.html) {
                        // Update the schedule content
                        $container.html(response.data.html);
                        
                        // Update URL without reloading
                        SODFilterFix.updateUrl(filterData.filters);
                        
                        // Trigger custom event
                        $(document).trigger('sod_filters_applied', [filterData.filters]);
                    } else {
                        console.error('[SOD Filter Fix] Invalid response:', response);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[SOD Filter Fix] AJAX error:', error);
                    
                    // Fallback to form submission
                    $form.off('submit.sodfilter');
                    $form.submit();
                },
                complete: function() {
                    // Remove loading state
                    $container.removeClass('loading');
                    $('.sod-loading-overlay').remove();
                }
            });
        },
        
        // Update URL with filter parameters
        updateUrl: function(filters) {
            const url = new URL(window.location);
            
            // Clear existing parameters
            ['view', 'date', 'product', 'service', 'staff', 'category'].forEach(param => {
                url.searchParams.delete(param);
            });
            
            // Add new parameters
            Object.keys(filters).forEach(key => {
                if (filters[key] && filters[key] !== '0' && filters[key] !== '') {
                    url.searchParams.set(key, filters[key]);
                }
            });
            
            // Update URL without reload
            window.history.pushState({filters: filters}, '', url.toString());
        },
        
        // Fix form submission issues
        fixFormSubmission: function() {
            // Ensure forms have proper action
            $('.sod-filter-form, .sod-schedule-filters form').each(function() {
                const $form = $(this);
                if (!$form.attr('action') || $form.attr('action') === '#') {
                    $form.attr('action', window.location.pathname);
                }
                if (!$form.attr('method')) {
                    $form.attr('method', 'get');
                }
            });
        },
        
        // Monitor URL changes
        monitorUrlChanges: function() {
            window.addEventListener('popstate', function(event) {
                console.log('[SOD Filter Fix] URL changed via browser navigation');
                
                if (event.state && event.state.filters) {
                    // Reload with new filters
                    location.reload();
                }
            });
        },
        
        // Check current filter state
        checkFilterState: function() {
            const urlParams = new URLSearchParams(window.location.search);
            const filters = {};
            
            ['view', 'date', 'product', 'service', 'staff', 'category'].forEach(param => {
                if (urlParams.has(param)) {
                    filters[param] = urlParams.get(param);
                }
            });
            
            console.log('[SOD Filter Fix] Current filter state:', filters);
            
            // Set form values to match URL
            Object.keys(filters).forEach(key => {
                const $field = $(`select[name="${key}"], input[name="${key}"]`);
                if ($field.length && $field.val() !== filters[key]) {
                    $field.val(filters[key]);
                }
            });
        }
    };

    // Alternative implementation if AJAX is not available
    const SODFilterFallback = {
        init: function() {
            console.log('[SOD Filter Fix] Initializing fallback filter system');
            
            // Make filters submit on change
            $(document).on('change', '.sod-filter-select, select[name="view"], select[name="product"], select[name="staff"], select[name="category"]', function() {
                const $form = $(this).closest('form');
                if ($form.length) {
                    console.log('[SOD Filter Fix] Auto-submitting form on filter change');
                    $form.submit();
                }
            });
            
            // Fix date input
            $(document).on('change', 'input[name="date"]', function() {
                const $form = $(this).closest('form');
                if ($form.length) {
                    $form.submit();
                }
            });
        }
    };

    // Initialize when ready
    $(document).ready(function() {
        // Wait a bit for other scripts to load
        setTimeout(function() {
            if (typeof sodSchedule !== 'undefined' && sodSchedule.ajax_url) {
                SODFilterFix.init();
            } else {
                SODFilterFallback.init();
            }
        }, 100);
    });

})(jQuery);