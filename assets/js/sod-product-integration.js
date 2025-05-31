/**
 * Spark of Divine - Product Integration JavaScript
 * 
 * Handles client-side functionality for the service product integration,
 * including management of product attributes and variations.
 */

jQuery(document).ready(function($) {
    'use strict';

    // Initialize the product integration functionality
    const SodProductIntegration = {
        init: function() {
            this.bindEvents();
            this.setupTabs();
        },

        bindEvents: function() {
            // Add attribute button click
            $('.add-attribute').on('click', this.handleAddAttribute);

            // Remove attribute button click (delegated)
            $(document).on('click', '.remove-attribute', this.handleRemoveAttribute);

            // Change event for service type selector
            $('#sod_service_type').on('change', this.handleServiceTypeChange);
        },

        setupTabs: function() {
            // Add SOD tab to WooCommerce product data tabs if it doesn't exist
            if ($('.product_data_tabs .sod_options').length === 0) {
                var $tab = $('<li class="sod_options product_options"><a href="#sod_service_options"><span>Spark of Divine</span></a></li>');
                $('.product_data_tabs').append($tab);
            }
        },

        handleAddAttribute: function(e) {
            e.preventDefault();
            var attributeType = $(this).data('type');
            
            $.ajax({
                url: sodProductIntegration.ajax_url,
                type: 'POST',
                data: {
                    action: 'sod_add_product_attribute',
                    type: attributeType,
                    nonce: sodProductIntegration.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#service-attributes-list').append(response.data.html);
                    } else {
                        alert(response.data.message || 'Error adding attribute');
                    }
                },
                error: function() {
                    alert('Server error. Please try again.');
                }
            });
        },

        handleRemoveAttribute: function(e) {
            e.preventDefault();
            var $row = $(this).closest('.attribute-row');
            var key = $row.data('key');
            var type = $row.data('type');
            
            $.ajax({
                url: sodProductIntegration.ajax_url,
                type: 'POST',
                data: {
                    action: 'sod_remove_product_attribute',
                    key: key,
                    type: type,
                    nonce: sodProductIntegration.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data.message || 'Error removing attribute');
                    }
                },
                error: function() {
                    alert('Server error. Please try again.');
                }
            });
        },

        handleServiceTypeChange: function() {
            var serviceType = $(this).val();
            
            // Adjust UI based on service type
            switch (serviceType) {
                case 'service':
                    $('.attribute-type-controls .add-attribute[data-type="duration"]').show();
                    $('.attribute-type-controls .add-attribute[data-type="passes"]').show();
                    $('.attribute-type-controls .add-attribute[data-type="package"]').show();
                    break;
                    
                case 'event':
                    $('.attribute-type-controls .add-attribute[data-type="duration"]').hide();
                    $('.attribute-type-controls .add-attribute[data-type="passes"]').show();
                    $('.attribute-type-controls .add-attribute[data-type="package"]').show();
                    break;
                    
                case 'class':
                    $('.attribute-type-controls .add-attribute[data-type="duration"]').hide();
                    $('.attribute-type-controls .add-attribute[data-type="passes"]').show();
                    $('.attribute-type-controls .add-attribute[data-type="package"]').show();
                    break;
            }
        }
    };

    // Initialize the product integration
    SodProductIntegration.init();

    // Optional: Add Preview UI for variations
    if ($('#service-attributes-list').length > 0) {
        // Create variation preview container
        var $previewSection = $('<div class="variation-preview-section">' +
                               '<h4>Variation Preview</h4>' +
                               '<div class="variation-preview-container"></div>' +
                               '</div>');
        
        $('.service-attributes-section').after($previewSection);
        
        // Update preview when attributes change
        $(document).on('change', '.attribute-row input', function() {
            updateVariationPreview();
        });
        
        function updateVariationPreview() {
            var $preview = $('.variation-preview-container');
            $preview.empty();
            
            // Collect attribute types and values
            var attributeTypes = {};
            
            $('.attribute-row').each(function() {
                var type = $(this).data('type');
                var value, price;
                
                if (type === 'package') {
                    value = $(this).find('input[name^="sod_attribute"][name$="[value]"]').val();
                } else {
                    value = $(this).find('input[name^="sod_attribute"][name$="[value]"]').val() + 
                            (type === 'duration' ? ' mins' : (type === 'passes' ? (parseInt($(this).find('input[name^="sod_attribute"][name$="[value]"]').val()) > 1 ? ' passes' : ' pass') : ''));
                }
                
                price = parseFloat($(this).find('input[name^="sod_attribute"][name$="[price]"]').val()) || 0;
                
                if (!attributeTypes[type]) {
                    attributeTypes[type] = [];
                }
                
                attributeTypes[type].push({
                    value: value,
                    price: price
                });
            });
            
            // If we have multiple attribute types, show potential combinations
            var typeKeys = Object.keys(attributeTypes);
            
            if (typeKeys.length === 0) {
                $preview.append('<p>No variations will be created.</p>');
                return;
            }
            
            if (typeKeys.length === 1) {
                // Single attribute type - simple variations
                var type = typeKeys[0];
                $preview.append('<p>Creating ' + attributeTypes[type].length + ' variations:</p>');
                
                var $list = $('<ul class="variation-list"></ul>');
                
                attributeTypes[type].forEach(function(attr) {
                    $list.append('<li>' + capitalizeFirstLetter(type) + ': ' + attr.value + ' - $' + attr.price.toFixed(2) + '</li>');
                });
                
                $preview.append($list);
            } else {
                // Multiple attribute types - show potential combinations
                $preview.append('<p>Creating combinations of: ' + typeKeys.map(capitalizeFirstLetter).join(', ') + '</p>');
                
                var combinations = [];
                var count = 1;
                
                typeKeys.forEach(function(type) {
                    count *= attributeTypes[type].length;
                });
                
                $preview.append('<p>Total potential variations: ' + count + '</p>');
                
                // If not too many combinations, show some examples
                if (count <= 10) {
                    combinations = generateCombinations(attributeTypes, typeKeys);
                    
                    var $list = $('<ul class="variation-list"></ul>');
                    
                    combinations.forEach(function(combo) {
                        var description = [];
                        var totalPrice = 0;
                        
                        Object.keys(combo).forEach(function(type) {
                            description.push(capitalizeFirstLetter(type) + ': ' + combo[type].value);
                            totalPrice += combo[type].price;
                        });
                        
                        $list.append('<li>' + description.join(', ') + ' - $' + totalPrice.toFixed(2) + '</li>');
                    });
                    
                    $preview.append($list);
                }
            }
        }
        
        function capitalizeFirstLetter(string) {
            return string.charAt(0).toUpperCase() + string.slice(1);
        }
        
        function generateCombinations(attributeTypes, typeKeys, currentKey = 0, currentCombo = {}) {
            if (currentKey >= typeKeys.length) {
                return [currentCombo];
            }
            
            var combinations = [];
            var currentType = typeKeys[currentKey];
            
            attributeTypes[currentType].forEach(function(attr) {
                var newCombo = Object.assign({}, currentCombo);
                newCombo[currentType] = attr;
                
                var newCombinations = generateCombinations(
                    attributeTypes,
                    typeKeys,
                    currentKey + 1,
                    newCombo
                );
                
                combinations = combinations.concat(newCombinations);
            });
            
            return combinations;
        }
        
        // Initial preview update
        updateVariationPreview();
    }
});