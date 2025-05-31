<?php
/**
 * Modified SOD_Packages_Handler Class to work with existing database tables
 */
class SOD_Packages_Handler {
    private static $instance;
    private $wpdb;
    private $error_log_file;

    /**
     * Get the singleton instance
     */
    public static function getInstance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor
     */
    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->error_log_file = WP_CONTENT_DIR . '/sod-error.log';
        // $this->log_message('Packages Handler initialized');

        // Register action hooks
        add_action('sod_check_packages', array($this, 'check_and_process_packages'), 10, 3);
        add_action('woocommerce_order_status_completed', array($this, 'process_package_purchase'), 10, 1);
        
        // Add AJAX endpoint for checking available packages
        add_action('wp_ajax_sod_check_available_packages', array($this, 'ajax_check_available_packages'));
        add_action('wp_ajax_nopriv_sod_check_available_packages', array($this, 'ajax_check_available_packages'));
        
        // Add hook to modify checkout process for packages
        add_filter('woocommerce_checkout_cart_item_quantity', array($this, 'modify_cart_item_for_packages'), 10, 3);
    }

    /**
     * Check and process packages for a booking
     * Called from the booking handler via 'sod_check_packages' action
     */
    public function check_and_process_packages($booking_id, $service_id, $user_id) {
        // $this->log_message("Checking packages for booking $booking_id, service $service_id, user $user_id");
        
        // Skip if no user is logged in
        if (!$user_id) {
            // $this->log_message("No user ID provided, skipping packages check");
            return false;
        }
        
        // Get the attribute from the booking
        $attribute_type = get_post_meta($booking_id, 'attribute_type', true);
        $attribute_value = get_post_meta($booking_id, 'attribute_value', true);
        
        // $this->log_message("Booking has attribute: $attribute_type = $attribute_value");
        
        // Skip if this is not a package attribute
        if ($attribute_type !== 'package') {
            // $this->log_message("Attribute type is not 'package', skipping");
            return false;
        }
        
        // Check if user has an active package that can be used for this service
        $active_package = $this->get_active_package($user_id, $service_id, $attribute_value);
        // $this->log_message("User active package check: " . ($active_package ? "Found: {$active_package->package_id}" : "Not found"));
        
        if ($active_package) {
            // Mark booking as using a package
            update_post_meta($booking_id, 'using_package', 'yes');
            update_post_meta($booking_id, 'package_id', $active_package->package_id);
            update_post_meta($booking_id, 'payment_method', 'package');
            
            // Record package usage
            $this->use_package($active_package->package_id, $booking_id, $service_id);
            
            // Mark booking as paid
            update_post_meta($booking_id, 'status', 'confirmed');
            
            $this->log_message("Package {$active_package->package_id} used for booking $booking_id");
            do_action('spark_divine_package_used', $booking_id, $user_id, $service_id, $active_package->package_id);
            
            return true;
        } else {
            // $this->log_message("No active package found for user $user_id and service $service_id");
            return false;
        }
    }

    /**
     * Get active package for a user and service
     */
    public function get_active_package($user_id, $service_id, $package_type = null) {
        // Build query conditions
        $conditions = [
            "user_id = %d",
            "status = 'active'",
            "(expiration_date IS NULL OR expiration_date >= CURDATE())"
        ];
        
        $params = [$user_id];
        
        // First try to find packages specifically for this service type
        if (!empty($package_type)) {
            $conditions[] = "package_name LIKE %s";
            $params[] = '%' . $this->wpdb->esc_like($package_type) . '%';
        }
        
        // Get packages for this user with matching conditions
        $query = "SELECT * FROM {$this->wpdb->prefix}sod_packages 
                 WHERE " . implode(' AND ', $conditions) . " 
                 ORDER BY created_at DESC";
        
        $packages = $this->wpdb->get_results($this->wpdb->prepare($query, $params));
        
        // Check each package to see if it covers this service
        foreach ($packages as $package) {
            if ($this->package_covers_service($package, $service_id)) {
                return $package;
            }
        }
        
        return null;
    }
    
    /**
     * Check if a package covers a specific service
     */
    private function package_covers_service($package, $service_id) {
        // If service_ids is empty, it covers all services
        if (empty($package->service_ids)) {
            return true;
        }
        
        // Parse service IDs list
        $service_ids = maybe_unserialize($package->service_ids);
        
        // If it's a string, try to parse as comma-separated list
        if (is_string($service_ids)) {
            $service_ids = array_map('trim', explode(',', $service_ids));
        }
        
        // If it's not an array at this point, assume it covers all services
        if (!is_array($service_ids)) {
            return true;
        }
        
        // Check if the service ID is in the list
        return in_array($service_id, $service_ids);
    }

    /**
     * Record package usage
     */
    private function use_package($package_id, $booking_id, $service_id) {
        // Record usage in the package_usage table
        $this->wpdb->insert(
            "{$this->wpdb->prefix}sod_package_usage",
            [
                'package_id' => $package_id,
                'booking_id' => $booking_id,
                'service_id' => $service_id,
                'usage_date' => current_time('mysql')
            ],
            ['%d', '%d', '%d', '%s']
        );
        
        $this->log_message("Recorded usage of package $package_id for booking $booking_id, service $service_id");
        return true;
    }

    /**
     * Process package purchase when an order is completed
     */
    public function process_package_purchase($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        $user_id = $order->get_user_id();
        if (!$user_id) return;
        
        // $this->log_message("Processing package purchase for order $order_id, user $user_id");
        
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            
            // Check if this is a package product
            if ($this->is_package_product($product_id, $variation_id)) {
                $package_data = $this->get_package_data($product_id, $variation_id);
                
                if ($package_data) {
                    $this->add_package_to_user(
                        $user_id,
                        $order_id,
                        $package_data['name'],
                        $package_data['description'],
                        $package_data['service_ids'],
                        $package_data['price'],
                        $package_data['validity_days']
                    );
                    
                    $this->log_message("Added package {$package_data['name']} to user $user_id");
                }
            }
        }
    }

    /**
     * Check if a product is a package product
     */
    private function is_package_product($product_id, $variation_id) {
        global $wpdb;
        
        // First check if it's a variation with package attribute
        if ($variation_id) {
            $table = $wpdb->prefix . 'sod_service_attributes';
            $result = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE variation_id = %d AND attribute_type = 'package'",
                $variation_id
            ));
            
            if ($result > 0) return true;
        }
        
        // Then check if it's a direct package product
        $service_id = get_post_meta($product_id, '_sod_service_id', true);
        if (!$service_id) return false;
        
        $package_meta = get_post_meta($product_id, '_sod_package_product', true);
        return !empty($package_meta);
    }

    /**
     * Get package data from a product
     */
    private function get_package_data($product_id, $variation_id) {
        global $wpdb;
        $product = wc_get_product($product_id);
        
        // First try to get from variation
        if ($variation_id) {
            $table = $wpdb->prefix . 'sod_service_attributes';
            $attr = $wpdb->get_row($wpdb->prepare(
                "SELECT service_id, value FROM $table WHERE variation_id = %d AND attribute_type = 'package'",
                $variation_id
            ));
            
            if ($attr) {
                return [
                    'name' => $attr->value,
                    'description' => $product ? $product->get_description() : '',
                    'service_ids' => [$attr->service_id],
                    'price' => $product ? $product->get_price() : 0,
                    'validity_days' => $this->get_package_validity($attr->value)
                ];
            }
        }
        
        // Then try from product meta
        $service_id = get_post_meta($product_id, '_sod_service_id', true);
        $package_meta = get_post_meta($product_id, '_sod_package_product', true);
        
        if ($service_id && $package_meta) {
            $package_data = maybe_unserialize($package_meta);
            
            return [
                'name' => isset($package_data['name']) ? $package_data['name'] : $product->get_name(),
                'description' => isset($package_data['description']) ? $package_data['description'] : $product->get_description(),
                'service_ids' => isset($package_data['services']) ? $package_data['services'] : [$service_id],
                'price' => $product ? $product->get_price() : 0,
                'validity_days' => isset($package_data['validity']) ? (int) $package_data['validity'] : 30
            ];
        }
        
        // Last resort, create from product data
        if ($product) {
            return [
                'name' => $product->get_name(),
                'description' => $product->get_description(),
                'service_ids' => $service_id ? [$service_id] : [],
                'price' => $product->get_price(),
                'validity_days' => 30 // Default validity
            ];
        }
        
        return null;
    }

    /**
     * Get validity days for a package type
     */
    private function get_package_validity($package_type) {
        $package_type = strtolower($package_type);
        
        if (strpos($package_type, 'week') !== false) return 7;
        if (strpos($package_type, 'month') !== false) return 30;
        if (strpos($package_type, 'quarterly') !== false || strpos($package_type, '3 month') !== false) return 90;
        if (strpos($package_type, 'biannual') !== false || strpos($package_type, '6 month') !== false) return 180;
        if (strpos($package_type, 'annual') !== false || strpos($package_type, 'year') !== false) return 365;
        
        // Default to monthly
        return 30;
    }

    /**
     * Add package to a user
     */
    public function add_package_to_user($user_id, $order_id, $name, $description, $service_ids, $price, $validity_days = 30) {
        $expiration_date = date('Y-m-d', strtotime("+$validity_days days"));
        
        // Prepare service_ids for storage
        $service_ids_str = is_array($service_ids) ? implode(',', $service_ids) : $service_ids;
        
        $this->wpdb->insert(
            "{$this->wpdb->prefix}sod_packages",
            [
                'user_id' => $user_id,
                'order_id' => $order_id,
                'package_name' => $name,
                'description' => $description,
                'service_ids' => $service_ids_str,
                'price' => $price,
                'expiration_date' => $expiration_date,
                'status' => 'active',
                'created_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s', '%s', '%s', '%f', '%s', '%s', '%s']
        );
        
        $package_id = $this->wpdb->insert_id;
        do_action('spark_divine_package_added', $user_id, $package_id, $name, $expiration_date);
    }

    /**
     * AJAX endpoint to check available packages
     */
    public function ajax_check_available_packages() {
        check_ajax_referer('sod_booking_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $service_id = isset($_POST['service']) ? intval($_POST['service']) : 0;
        $package_type = isset($_POST['package']) ? sanitize_text_field($_POST['package']) : '';
        
        if (!$user_id) {
            wp_send_json_error(['message' => __('You must be logged in to use packages', 'spark-of-divine-scheduler')]);
            return;
        }
        
        $active_package = $this->get_active_package($user_id, $service_id, $package_type);
        $can_use_package = ($active_package !== null);
        
        wp_send_json_success([
            'canUsePackage' => $can_use_package,
            'packageInfo' => $active_package ? [
                'id' => $active_package->package_id,
                'name' => $active_package->package_name,
                'expiryDate' => $active_package->expiration_date
            ] : null,
            'message' => $can_use_package ? 
                sprintf(__('Active %s package available (expires %s)', 'spark-of-divine-scheduler'), 
                    $active_package->package_name, 
                    date_i18n(get_option('date_format'), strtotime($active_package->expiration_date))
                ) :
                __('No active package available', 'spark-of-divine-scheduler')
        ]);
    }

    /**
     * Modify cart item display for packages
     */
    public function modify_cart_item_for_packages($html, $cart_item, $cart_item_key) {
        $product_id = $cart_item['product_id'];
        $variation_id = isset($cart_item['variation_id']) ? $cart_item['variation_id'] : 0;
        
        if ($this->is_package_product($product_id, $variation_id)) {
            $package_data = $this->get_package_data($product_id, $variation_id);
            
            if ($package_data) {
                $service_names = [];
                if (is_array($package_data['service_ids'])) {
                    foreach ($package_data['service_ids'] as $sid) {
                        $service_names[] = get_the_title($sid);
                    }
                } elseif (!empty($package_data['service_ids'])) {
                    $service_names[] = get_the_title($package_data['service_ids']);
                } else {
                    $service_names[] = __('All Services', 'spark-of-divine-scheduler');
                }
                
                $service_list = implode(', ', $service_names);
                
                $html .= sprintf(
                    '<p class="package-info">%s</p>',
                    sprintf(
                        __('%s package for %s (valid for %d days)', 'spark-of-divine-scheduler'),
                        $package_data['name'],
                        $service_list,
                        $package_data['validity_days']
                    )
                );
            }
        }
        
        return $html;
    }

    /**
     * Get all packages for a user
     */
    public function get_user_packages($user_id) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT p.*, COUNT(pu.usage_id) as usage_count 
             FROM {$this->wpdb->prefix}sod_packages p
             LEFT JOIN {$this->wpdb->prefix}sod_package_usage pu ON p.package_id = pu.package_id
             WHERE p.user_id = %d
             GROUP BY p.package_id
             ORDER BY p.expiration_date DESC",
            $user_id
        ));
    }

    /**
     * Get active packages count for a user
     */
    public function get_active_packages_count($user_id) {
        return $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}sod_packages 
             WHERE user_id = %d 
             AND status = 'active' 
             AND (expiration_date IS NULL OR expiration_date >= CURDATE())",
            $user_id
        ));
    }

    /**
     * Log a message to the error log
     */
    private function log_message($message) {
        error_log(date('[Y-m-d H:i:s] ') . "[SOD Packages] " . $message . "\n", 3, $this->error_log_file);
    }
}

// Initialize the handler
SOD_Packages_Handler::getInstance();