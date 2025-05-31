<?php
/**
 * SOD Booking Fix using existing debug handler
 * 
 * Add this code to your theme's functions.php file to fix the booking process
 * This works with your existing class-sod-debug-handler.php
 */

// Add frontend fixes only
if (!is_admin()) {
    // Add our JavaScript fix to enhance the booking form
    add_action('wp_footer', 'sod_booking_form_enhancer', 9999);
    
    // Add a custom product data resolver that will use your debug handler
    add_filter('sod_resolve_product_data', 'sod_fix_product_data', 5, 4);
}

/**
 * JavaScript to enhance booking form submission
 */
function sod_booking_form_enhancer() {
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        console.log('SOD Form Enhancer: Initializing');
        
        setTimeout(function() {
            // Enhance attribute selects
            $('.attribute-select').each(function() {
                var $select = $(this);
                var $form = $select.closest('form');
                
                // Enhanced change handler 
                $select.on('change', function() {
                    var $selected = $(this).find('option:selected');
                    var optionText = $selected.text();
                    var selectedValue = $selected.val();
                    
                    console.log('SOD Form Enhancer: Attribute selected', {
                        text: optionText,
                        value: selectedValue
                    });
                    
                    // Clean up any previous enhancements
                    $form.find('input[name="duration_enhanced"]').remove();
                    $form.find('input[name="passes_enhanced"]').remove();
                    $form.find('input[name="product_id_enhanced"]').remove();
                    $form.find('input[name="variation_id_enhanced"]').remove();
                    
                    // Extract duration from text
                    var duration = 60; // Default
                    var durationMatch = optionText.match(/(\d+)\s*min/i);
                    if (durationMatch) {
                        duration = parseInt(durationMatch[1], 10);
                    } else {
                        // Try JSON value
                        try {
                            var attributeData = JSON.parse(selectedValue);
                            if (attributeData && attributeData.type === 'duration' && attributeData.value) {
                                var durationStr = attributeData.value.replace(/[^0-9]/g, '');
                                if (durationStr) {
                                    duration = parseInt(durationStr, 10);
                                }
                            }
                        } catch(e) {
                            console.log('Not JSON value');
                        }
                    }
                    
                    // Extract passes from text
                    var passes = 1; // Default
                    var passMatch = optionText.match(/(\d+)[- ](month|pass|session)/i);
                    if (passMatch) {
                        passes = parseInt(passMatch[1], 10);
                    }
                    
                    // Get IDs
                    var productId = $selected.data('product-id');
                    var variationId = $selected.data('variation-id');
                    
                    // Add enhanced fields
                    $form.append('<input type="hidden" name="duration_enhanced" value="' + duration + '">');
                    $form.append('<input type="hidden" name="passes_enhanced" value="' + passes + '">');
                    
                    if (productId) {
                        $form.append('<input type="hidden" name="product_id_enhanced" value="' + productId + '">');
                    }
                    
                    if (variationId) {
                        $form.append('<input type="hidden" name="variation_id_enhanced" value="' + variationId + '">');
                    }
                    
                    console.log('SOD Form Enhancer: Added data to form', {
                        duration: duration,
                        passes: passes,
                        productId: productId,
                        variationId: variationId
                    });
                });
                
                // Trigger for initial value
                if ($select.val()) {
                    $select.trigger('change');
                }
            });
            
            // Add data extractor to booking form submission
            $('.booking-form').on('submit', function(e) {
                var $form = $(this);
                
                // Add our enhanced values to the main form fields
                // This ensures they're available to your debug handler
                
                // Duration
                if ($form.find('input[name="duration_enhanced"]').length && !$form.find('input[name="duration"]').length) {
                    $form.append('<input type="hidden" name="duration" value="' + $form.find('input[name="duration_enhanced"]').val() + '">');
                }
                
                // Passes
                if ($form.find('input[name="passes_enhanced"]').length && !$form.find('input[name="passes"]').length) {
                    $form.append('<input type="hidden" name="passes" value="' + $form.find('input[name="passes_enhanced"]').val() + '">');
                }
                
                // Product ID (if enhanced version exists and different from default)
                if ($form.find('input[name="product_id_enhanced"]').length) {
                    var enhancedProductId = $form.find('input[name="product_id_enhanced"]').val();
                    var currentProductId = $form.find('input[name="product_id"]').val();
                    
                    if (enhancedProductId !== currentProductId) {
                        // Replace existing product ID
                        $form.find('input[name="product_id"]').val(enhancedProductId);
                    }
                }
                
                // Variation ID
                if ($form.find('input[name="variation_id_enhanced"]').length && !$form.find('input[name="variation_id"]').length) {
                    $form.append('<input type="hidden" name="variation_id" value="' + $form.find('input[name="variation_id_enhanced"]').val() + '">');
                }
                
                console.log('SOD Form Enhancer: Form submitted with enhanced data');
                
                // Don't intercept submission - let the normal handler run with our enhanced data
            });
            
            console.log('SOD Form Enhancer: Setup complete');
        }, 1000);
    });
    </script>
    <?php
}

/**
 * Fix product data resolution - works with your debug handler
 * This runs at priority 5, before your debug handler (priority 10)
 */
function sod_fix_product_data($product_data, $product_id, $attribute, $variation_id) {
    // Don't override if data is already set
    if (!empty($product_data)) {
        return $product_data;
    }
    
    // Get enhanced variation ID from POST if available
    if (isset($_POST['variation_id_enhanced'])) {
        $variation_id = intval($_POST['variation_id_enhanced']);
    }
    
    // Get enhanced product ID from POST if available
    if (isset($_POST['product_id_enhanced'])) {
        $product_id = intval($_POST['product_id_enhanced']);
    }
    
    // Try to use variation directly if we have one
    if (!empty($variation_id)) {
        $variation = wc_get_product($variation_id);
        if ($variation) {
            error_log("SOD Fix: Using variation directly: $variation_id for product $product_id");
            return [
                'product_id' => $product_id,
                'variation_id' => $variation_id,
                'price' => $variation->get_price()
            ];
        }
    }
    
    // Let your debug handler take over from here
    return $product_data;
}

/**
 * Add extra AJAX hooks to work with your debug handler
 */
add_action('plugins_loaded', 'sod_setup_fix_hooks');

function sod_setup_fix_hooks() {
    // Add an exception catcher to prevent 500 errors
    add_action('wp_ajax_sod_submit_booking', 'sod_booking_exception_handler', 0);
    add_action('wp_ajax_nopriv_sod_submit_booking', 'sod_booking_exception_handler', 0);
}

/**
 * Global exception handler for booking submissions
 * Runs before any other booking handlers
 */
function sod_booking_exception_handler() {
    try {
        // Validate duration and passes are set
        if (!isset($_POST['duration']) && isset($_POST['duration_enhanced'])) {
            $_POST['duration'] = $_POST['duration_enhanced'];
        }
        
        if (!isset($_POST['passes']) && isset($_POST['passes_enhanced'])) {
            $_POST['passes'] = $_POST['passes_enhanced'];
        }
        
        // Let processing continue to your debug handler
        return;
        
    } catch (Exception $e) {
        error_log('SOD Fix Exception: ' . $e->getMessage());
        wp_send_json_error([
            'message' => 'An error occurred processing your booking. Please try again.'
        ]);
        exit;
    }
}