<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SOD_Attribute_Handler
 * 
 * Manages service attributes (durations, passes, packages, custom attributes)
 * and their integration with WooCommerce products.
 */
class SOD_Attribute_Handler {
    private static $instance = null;
    private $wpdb;
    private $attributes_table;
    private $product_integration = null;
    private $error_log_file;

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->attributes_table = $wpdb->prefix . 'sod_service_attributes';
        $this->error_log_file = WP_CONTENT_DIR . '/sod-error.log';
        
        // Note: Don't try to access product_integration here
        // It will be set later via set_product_integration()
        
        $this->log_message("Attribute Handler initialized");

        // Register hooks for attribute management
        add_action('woocommerce_save_product_variation', array($this, 'update_service_attributes'), 10, 2);
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // AJAX handlers for attribute operations
        add_action('wp_ajax_get_service_attributes', array($this, 'ajax_get_service_attributes'));
        add_action('wp_ajax_save_service_attributes', array($this, 'ajax_save_service_attributes'));
        add_action('wp_ajax_delete_attribute_product', array($this, 'ajax_delete_attribute_product'));
        add_action('wp_ajax_create_service_products', array($this, 'ajax_create_service_products'));
    }

    /**
     * Singleton pattern implementation
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Set the product integration after initialization
     */
    public function set_product_integration($product_integration) {
        $this->product_integration = $product_integration;
        $this->log_message("Product integration has been set");
    }
    
    /**
     * Get product integration with fallback to global
     */
    private function get_product_integration() {
        if (!$this->product_integration) {
            if (isset($GLOBALS['sod_service_product_integration'])) {
                $this->product_integration = $GLOBALS['sod_service_product_integration'];
                $this->log_message("Product integration obtained from globals");
            } else {
                $this->log_error("Product integration not available");
                return null;
            }
        }
        return $this->product_integration;
    }

    /**
     * Register the submenu page for WooCommerce product creation
     */
    public function add_admin_menu() {
        add_submenu_page(
            'tools.php',
            'Create WooCommerce Products for Services',
            'Create Service Products',
            'manage_options',
            'create-service-products',
            array($this, 'render_create_service_products_page')
        );
    }

    /**
     * Render the admin page for product creation
     */
    public function render_create_service_products_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Create WooCommerce Products for Services', 'spark-of-divine-scheduler'); ?></h1>
            <p><?php _e('Use this tool to create or update WooCommerce products for all services, classes, and events.', 'spark-of-divine-scheduler'); ?></p>
            
            <form id="create-service-products-form">
                <?php wp_nonce_field('sod_create_service_products', 'sod_nonce'); ?>
                <div class="actions">
                    <button type="button" id="create-service-products" class="button button-primary">
                        <?php _e('Create/Update Products', 'spark-of-divine-scheduler'); ?>
                    </button>
                    <span class="spinner"></span>
                </div>
                <div id="progress-container" style="display:none; margin-top: 20px;">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 0%;"></div>
                    </div>
                    <p class="progress-status"></p>
                </div>
                <div id="result-container" style="display:none; margin-top: 20px;">
                    <h3><?php _e('Results', 'spark-of-divine-scheduler'); ?></h3>
                    <div id="result-content"></div>
                </div>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#create-service-products').on('click', function() {
                var $button = $(this);
                var $spinner = $('.spinner');
                var $progress = $('#progress-container');
                var $progressFill = $('.progress-fill');
                var $progressStatus = $('.progress-status');
                var $result = $('#result-container');
                var $resultContent = $('#result-content');
                
                $button.prop('disabled', true);
                $spinner.addClass('is-active');
                $progress.show();
                $result.hide();
                $progressFill.width('0%');
                $progressStatus.text('<?php _e('Fetching services...', 'spark-of-divine-scheduler'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'create_service_products',
                        nonce: $('#sod_nonce').val()
                    },
                    success: function(response) {
                        $spinner.removeClass('is-active');
                        $progress.hide();
                        $result.show();
                        
                        if (response.success) {
                            $resultContent.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                            $resultContent.append('<p><?php _e('Created/Updated:', 'spark-of-divine-scheduler'); ?> ' + response.data.created + '</p>');
                            $resultContent.append('<p><?php _e('Skipped:', 'spark-of-divine-scheduler'); ?> ' + response.data.skipped + '</p>');
                        } else {
                            $resultContent.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                        }
                        
                        $button.prop('disabled', false);
                    },
                    error: function() {
                        $spinner.removeClass('is-active');
                        $progress.hide();
                        $result.show();
                        $resultContent.html('<div class="notice notice-error"><p><?php _e('An error occurred while processing the request.', 'spark-of-divine-scheduler'); ?></p></div>');
                        $button.prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <style>
        .spinner {
            float: none;
            margin-left: 10px;
            vertical-align: middle;
        }
        .progress-bar {
            height: 20px;
            background-color: #f0f0f0;
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        .progress-fill {
            height: 100%;
            background-color: #0073aa;
            transition: width 0.3s ease;
        }
        </style>
        <?php
    }

    /**
     * AJAX handler for creating service products
     */
    public function ajax_create_service_products() {
        check_ajax_referer('sod_create_service_products', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'spark-of-divine-scheduler')]);
            return;
        }
        
        $product_integration = $this->get_product_integration();
        if (!$product_integration) {
            wp_send_json_error(['message' => __('Service Product Integration not available.', 'spark-of-divine-scheduler')]);
            return;
        }
        
        $post_types = ['sod_service', 'sod_event', 'sod_class'];
        $created = 0;
        $skipped = 0;
        
        foreach ($post_types as $post_type) {
            $items = get_posts(['post_type' => $post_type, 'posts_per_page' => -1, 'fields' => 'ids']);
            foreach ($items as $item_id) {
                $attributes = $this->get_service_attributes($item_id);
                $result = $product_integration->create_or_update_item_product($item_id);
                if ($result) {
                    $created++;
                } else {
                    $skipped++;
                }
            }
        }
        
        wp_send_json_success([
            'message' => __('WooCommerce products have been created or updated.', 'spark-of-divine-scheduler'),
            'created' => $created,
            'skipped' => $skipped
        ]);
    }

    /**
     * Update service attributes when WooCommerce variations are saved
     */
    public function update_service_attributes($variation_id, $i = null) {
        $this->log_message("update_service_attributes called for Variation ID: {$variation_id}");

        $variation = wc_get_product($variation_id);
        if (!$variation || !$variation->is_type('variation')) {
            $this->log_error("Not a valid variation product ID: {$variation_id}");
            return;
        }

        $parent_id = $variation->get_parent_id();
        $this->log_message("Parent product ID: {$parent_id}");

        $service_id = $this->get_linked_item_id($parent_id);
        if (!$service_id) {
            $this->log_error("No service, class, or event ID found for parent product {$parent_id}");
            return;
        }

        $attributes = $variation->get_attributes();
        foreach ($attributes as $attribute_name => $value) {
            $type = str_replace('attribute_', '', $attribute_name);
            $value = sanitize_text_field($value);

            $price = $variation->get_regular_price();
            $passes = $this->get_passes_for_attribute($type, $value, $service_id);
            $this->log_message("Processing attribute {$type} for variation {$variation_id} - Value: {$value}, Price: {$price}, Passes: {$passes}");

            try {
                $this->wpdb->query('START TRANSACTION');

                $exists = $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->attributes_table} 
                     WHERE service_id = %d AND attribute_type = %s AND value = %s AND variation_id = %d",
                    $service_id, $type, $value, $variation_id
                ));

                if ($exists) {
                    $result = $this->wpdb->update(
                        $this->attributes_table,
                        ['price' => floatval($price), 'product_id' => $parent_id],
                        [
                            'service_id' => $service_id,
                            'attribute_type' => $type,
                            'value' => $value,
                            'variation_id' => $variation_id
                        ],
                        ['%f', '%d'],
                        ['%d', '%s', '%s', '%d']
                    );
                    $this->log_message("Updated existing attribute record for variation {$variation_id}");
                } else {
                    $result = $this->wpdb->insert(
                        $this->attributes_table,
                        [
                            'service_id' => $service_id,
                            'attribute_type' => $type,
                            'value' => $value,
                            'price' => floatval($price),
                            'product_id' => $parent_id,
                            'variation_id' => $variation_id
                        ],
                        ['%d', '%s', '%s', '%f', '%d', '%d']
                    );
                    $this->log_message("Inserted new attribute record for variation {$variation_id}");
                }

                if ($result === false) {
                    throw new Exception("Database operation failed: " . $this->wpdb->last_error);
                }

                // If this is a 'passes' attribute, update related passes logic if needed
                if ($type === 'passes') {
                    $this->log_message("Pass attribute detected for service {$service_id}, value: {$value}, passes: {$passes}");
                    // Optional: Integrate with SOD_Passes_Handler if needed
                }

                $this->wpdb->query('COMMIT');
                $this->log_message("Successfully processed attribute update for variation {$variation_id}");

            } catch (Exception $e) {
                $this->wpdb->query('ROLLBACK');
                $this->log_error("Error processing attribute update: " . $e->getMessage());
            }
        }
    }

    /**
     * Get the linked item ID for a WooCommerce product
     */
    private function get_linked_item_id($product_id) {
        $post_types = ['sod_service', 'sod_class', 'sod_event'];
        foreach ($post_types as $post_type) {
            $service_id = get_post_meta($product_id, '_sod_service_id', true);
            if ($service_id && get_post_type($service_id) === $post_type) {
                return $service_id;
            }
        }
        return false;
    }

    /**
     * Get passes for a specific attribute
     */
    private function get_passes_for_attribute($type, $value, $service_id) {
        if ($type !== 'passes') {
            $this->log_message("Attribute type '{$type}' is not 'passes' for service {$service_id}, defaulting to 1 pass");
            return 1; // Default to 1 pass for non-pass attributes
        }

        // Map common pass values to numeric counts
        $pass_map = [
            'single' => 1,
            'four' => 4,
            'five' => 5,
            'eight' => 8,
            '3-month' => null, // Special case: handled by duration or separate logic
            'senior' => 1     // Adjust based on your business logic
        ];

        $value = strtolower($value);
        if (isset($pass_map[$value])) {
            $passes = $pass_map[$value];
            if ($passes === null) {
                $this->log_message("Pass value '{$value}' for service {$service_id} requires custom handling, defaulting to 1");
                return 1; // Fallback for undefined cases like '3-month'
            }
            $this->log_message("Interpreted pass value '{$value}' as {$passes} passes for service {$service_id}");
            return $passes;
        }

        // Attempt to parse numeric value directly
        $numeric_value = (int) preg_replace('/[^0-9]/', '', $value);
        if ($numeric_value > 0) {
            $this->log_message("Parsed numeric pass value '{$value}' as {$numeric_value} passes for service {$service_id}");
            return $numeric_value;
        }

        $this->log_message("Unknown pass value '{$value}' for service {$service_id}, defaulting to 1");
        return 1; // Default fallback
    }

    /**
     * Save service attributes
     */
    public function save_service_attributes($item_id, $attributes) {
        try {
            $this->wpdb->query('START TRANSACTION');
            
            if (empty($item_id) || empty($attributes)) {
                throw new Exception('Invalid item_id or empty attributes array.');
            }

            // Delete existing attributes for this item
            $this->delete_existing_attributes($item_id);

            foreach ($attributes as $attribute) {
                if (!isset($attribute['attribute_type'], $attribute['value'], $attribute['price'])) {
                    $this->log_error("Missing required fields in attribute array for item_id: {$item_id}");
                    continue;
                }

                $type = sanitize_text_field($attribute['attribute_type']);
                $value = sanitize_text_field($attribute['value']);
                $price = floatval($attribute['price']);
                $product_id = isset($attribute['product_id']) ? intval($attribute['product_id']) : 0;
                $variation_id = isset($attribute['variation_id']) ? intval($attribute['variation_id']) : 0;
                $passes = isset($attribute['passes']) ? intval($attribute['passes']) : 1;

                $this->log_message("Inserting attribute for item_id: {$item_id}, type: {$type}, value: {$value}, price: {$price}");

                $result = $this->wpdb->insert(
                    $this->attributes_table,
                    array(
                        'service_id' => $item_id,
                        'attribute_type' => $type,
                        'value' => $value,
                        'price' => $price,
                        'product_id' => $product_id,
                        'variation_id' => $variation_id,
                        'passes' => $passes
                    ),
                    array('%d', '%s', '%s', '%f', '%d', '%d', '%d')
                );

                if ($result === false) {
                    $this->log_error("Failed to insert attribute for item_id {$item_id}. Error: " . $this->wpdb->last_error);
                } else {
                    $this->log_message("Successfully inserted attribute with attribute_id: " . $this->wpdb->insert_id);
                }
            }

            $this->wpdb->query('COMMIT');
            
            // Sync with WooCommerce product if available
            $product_integration = $this->get_product_integration();
            if ($product_integration && isset($attributes[0]['product_id'])) {
                $product_integration->update_product_attributes_and_variations($attributes[0]['product_id']); 
            }
            
            return true;

        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
            $this->log_error("Transaction rolled back. Failed to save attributes for item_id: {$item_id}. Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete existing attributes for an item
     */
    private function delete_existing_attributes($item_id) {
        return $this->wpdb->delete(
            $this->attributes_table,
            array('service_id' => $item_id),
            array('%d')
        );
    }

    /**
     * Get service attributes with product information
     */
    public function get_service_attributes($item_id) {
        $this->log_message("Fetching attributes for service ID: {$item_id}");
        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT attribute_type, value, price, product_id, variation_id
             FROM {$this->attributes_table}
             WHERE service_id = %d 
             ORDER BY attribute_type ASC, value ASC",
            $item_id
        ));

        if ($this->wpdb->last_error) {
            $this->log_error("Error fetching attributes for service {$item_id}: " . $this->wpdb->last_error);
        } else {
            $this->log_message("Fetched " . count($results) . " attributes for service {$item_id}");
        }

        return $results;
    }

    /**
     * AJAX handler for getting service attributes
     */
    public function ajax_get_service_attributes() {
        check_ajax_referer('sod_attribute_nonce', 'nonce');
        
        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
        if (!$item_id) {
            wp_send_json_error(['message' => 'Invalid item ID']);
            return;
        }

        $attributes = $this->get_service_attributes($item_id);
        wp_send_json_success(['attributes' => $attributes]);
    }

    /**
     * AJAX handler for saving service attributes
     */
    public function ajax_save_service_attributes() {
        check_ajax_referer('sod_attribute_nonce', 'nonce');

        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
        $attributes = isset($_POST['attributes']) ? $_POST['attributes'] : [];

        if (!$item_id || empty($attributes)) {
            wp_send_json_error(['message' => 'Invalid data']);
            return;
        }

        $result = $this->save_service_attributes($item_id, $attributes);
        if ($result) {
            wp_send_json_success(['message' => 'Attributes saved successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to save attributes']);
        }
    }

    /**
     * AJAX handler for deleting attribute product
     */
    public function ajax_delete_attribute_product() {
        check_ajax_referer('sod_attribute_nonce', 'nonce');
        
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;

        if (!$product_id || !$item_id) {
            wp_send_json_error(['message' => 'Invalid data']);
            return;
        }

        try {
            $this->wpdb->query('START TRANSACTION');

            // Delete WooCommerce product and its variations
            $product = wc_get_product($product_id);
            if ($product && $product->is_type('variable')) {
                foreach ($product->get_children() as $variation_id) {
                    wp_delete_post($variation_id, true);
                }
            }
            $result = wp_delete_post($product_id, true);
            if (!$result) {
                throw new Exception('Failed to delete product');
            }

            // Delete attribute records linked to this product
            $result = $this->wpdb->delete(
                $this->attributes_table,
                array(
                    'service_id' => $item_id,
                    'product_id' => $product_id
                ),
                array('%d', '%d')
            );

            if ($result === false) {
                throw new Exception($this->wpdb->last_error);
            }

            $this->wpdb->query('COMMIT');
            wp_send_json_success(['message' => 'Attribute product deleted successfully']);

        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
            $this->log_error("Failed to delete attribute product: " . $e->getMessage());
            wp_send_json_error(['message' => 'Failed to delete attribute product: ' . $e->getMessage()]);
        }
    }

    /**
     * Log a message
     */
    private function log_message($message) {
        error_log(date('[Y-m-d H:i:s] ') . "[Attribute Handler] " . $message . "\n", 3, $this->error_log_file);
    }

    /**
     * Log an error message
     */
    private function log_error($message) {
        error_log(date('[Y-m-d H:i:s] ') . "[Attribute Handler ERROR] " . $message . "\n", 3, $this->error_log_file);
    }
}