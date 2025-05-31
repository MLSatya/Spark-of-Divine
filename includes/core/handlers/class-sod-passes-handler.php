<?php
/**
 * Modified SOD_Passes_Handler Class to work with existing database tables
 */
class SOD_Passes_Handler {
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
        // $this->log_message('Passes Handler initialized');

        // Register action hooks
        add_action('sod_check_passes', array($this, 'check_and_process_passes'), 10, 3);
        add_action('woocommerce_order_status_completed', array($this, 'process_passes_purchase'), 10, 1);
        
        // Add AJAX endpoint for checking available passes
        add_action('wp_ajax_sod_check_available_passes', array($this, 'ajax_check_available_passes'));
        add_action('wp_ajax_nopriv_sod_check_available_passes', array($this, 'ajax_check_available_passes'));
    }

    /**
     * Check and process passes for a booking
     * Called from the booking handler via 'sod_check_passes' action
     */
    public function check_and_process_passes($booking_id, $service_id, $user_id) {
        // $this->log_message("Checking passes for booking $booking_id, service $service_id, user $user_id");
        
        // Skip if no user is logged in
        if (!$user_id) {
            // $this->log_message("No user ID provided, skipping passes check");
            return;
        }
        
        // Get the attribute from the booking
        $attribute_type = get_post_meta($booking_id, 'attribute_type', true);
        $attribute_value = get_post_meta($booking_id, 'attribute_value', true);
        
        // $this->log_message("Booking has attribute: $attribute_type = $attribute_value");
        
        // Skip if this is not a passes attribute
        if ($attribute_type !== 'passes') {
            // $this->log_message("Attribute type is not 'passes', skipping");
            return;
        }
        
        // Check if user has available passes
        $available_pass = $this->get_available_pass($user_id, $service_id);
        // $this->log_message("User pass check: " . ($available_pass ? "Found pass ID: {$available_pass->pass_id}" : "No pass found"));
        
        if ($available_pass) {
            // Mark booking as using a pass
            update_post_meta($booking_id, 'using_pass', 'yes');
            update_post_meta($booking_id, 'pass_id', $available_pass->pass_id);
            update_post_meta($booking_id, 'payment_method', 'pass');
            
            // Decrement user's passes count
            $this->use_pass($available_pass->pass_id, $booking_id);
            
            // Mark booking as paid
            update_post_meta($booking_id, 'status', 'confirmed');
            
            $this->log_message("Pass {$available_pass->pass_id} used for booking $booking_id");
            do_action('spark_divine_pass_used', $booking_id, $user_id, $service_id);
            
            return true;
        } else {
            // $this->log_message("No passes available for user $user_id and service $service_id");
            return false;
        }
    }

    /**
     * Get available pass for a user and service
     */
    public function get_available_pass($user_id, $service_id) {
        // First try to get a service-specific pass
        $pass = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}sod_passes 
             WHERE user_id = %d AND service_id = %d AND remaining_passes > 0 
             AND (expiration_date IS NULL OR expiration_date >= CURDATE())
             AND status = 'active'
             ORDER BY expiration_date ASC LIMIT 1",
            $user_id, $service_id
        ));
        
        if ($pass) {
            return $pass;
        }
        
        // If no specific pass, try a general pass (service_id = 0)
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}sod_passes 
             WHERE user_id = %d AND service_id = 0 AND remaining_passes > 0 
             AND (expiration_date IS NULL OR expiration_date >= CURDATE())
             AND status = 'active'
             ORDER BY expiration_date ASC LIMIT 1",
            $user_id
        ));
    }

    /**
     * Count total available passes for a user and service
     */
    public function count_available_passes($user_id, $service_id) {
        // Count service-specific passes
        $specific_passes = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT SUM(remaining_passes) FROM {$this->wpdb->prefix}sod_passes 
             WHERE user_id = %d AND service_id = %d 
             AND (expiration_date IS NULL OR expiration_date >= CURDATE())
             AND status = 'active'",
            $user_id, $service_id
        ));
        
        // Count general passes
        $general_passes = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT SUM(remaining_passes) FROM {$this->wpdb->prefix}sod_passes 
             WHERE user_id = %d AND service_id = 0 
             AND (expiration_date IS NULL OR expiration_date >= CURDATE())
             AND status = 'active'",
            $user_id
        ));
        
        return intval($specific_passes) + intval($general_passes);
    }

    /**
     * Use a pass for a booking
     */
    private function use_pass($pass_id, $booking_id) {
        // Get current pass data
        $pass = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}sod_passes WHERE pass_id = %d",
            $pass_id
        ));
        
        if (!$pass) {
            $this->log_error("Pass ID $pass_id not found");
            return false;
        }
        
        // Decrement remaining passes
        $this->wpdb->update(
            "{$this->wpdb->prefix}sod_passes",
            ['remaining_passes' => max(0, $pass->remaining_passes - 1)],
            ['pass_id' => $pass_id],
            ['%d'],
            ['%d']
        );
        
        // Record usage in pass_usage table
        $this->wpdb->insert(
            "{$this->wpdb->prefix}sod_pass_usage",
            [
                'pass_id' => $pass_id,
                'booking_id' => $booking_id,
                'usage_date' => current_time('mysql')
            ],
            ['%d', '%d', '%s']
        );
        
        $this->log_message("Used pass ID $pass_id for booking $booking_id. Remaining: " . ($pass->remaining_passes - 1));
        return true;
    }

    /**
     * Process passes purchase when an order is completed
     */
    public function process_passes_purchase($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        $user_id = $order->get_user_id();
        if (!$user_id) return;
        
        // $this->log_message("Processing passes purchase for order $order_id, user $user_id");
        
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $quantity = $item->get_quantity();
            
            // Check if this is a passes product
            if ($this->is_passes_product($product_id, $variation_id)) {
                $passes_data = $this->get_passes_data($product_id, $variation_id);
                
                if ($passes_data) {
                    $this->add_passes_to_user(
                        $user_id,
                        $order_id,
                        $passes_data['service_id'],
                        $passes_data['duration'],
                        $passes_data['passes_count'] * $quantity,
                        $passes_data['expiration_days']
                    );
                    
                    $this->log_message("Added {$passes_data['passes_count']} passes for service {$passes_data['service_id']} to user $user_id");
                }
            }
        }
    }

    /**
     * Check if a product is a passes product
     */
    private function is_passes_product($product_id, $variation_id) {
        global $wpdb;
        
        // First check if it's a variation with passes attribute
        if ($variation_id) {
            $table = $wpdb->prefix . 'sod_service_attributes';
            $result = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE variation_id = %d AND attribute_type = 'passes'",
                $variation_id
            ));
            
            if ($result > 0) return true;
        }
        
        // Then check if it's a direct passes product
        $service_id = get_post_meta($product_id, '_sod_service_id', true);
        if (!$service_id) return false;
        
        $passes_meta = get_post_meta($product_id, '_sod_passes_product', true);
        return !empty($passes_meta);
    }

    /**
     * Get passes data from a product
     */
    private function get_passes_data($product_id, $variation_id) {
        global $wpdb;
        
        // First try to get from variation
        if ($variation_id) {
            $table = $wpdb->prefix . 'sod_service_attributes';
            $attr = $wpdb->get_row($wpdb->prepare(
                "SELECT service_id, value FROM $table WHERE variation_id = %d AND attribute_type = 'passes'",
                $variation_id
            ));
            
            if ($attr) {
                return [
                    'service_id' => $attr->service_id,
                    'passes_count' => (int) $attr->value,
                    'duration' => 60, // Default duration
                    'expiration_days' => 180 // Default 6 months validity
                ];
            }
        }
        
        // Then try from product meta
        $service_id = get_post_meta($product_id, '_sod_service_id', true);
        $passes_meta = get_post_meta($product_id, '_sod_passes_product', true);
        
        if ($service_id && $passes_meta) {
            $passes_data = maybe_unserialize($passes_meta);
            
            return [
                'service_id' => $service_id,
                'passes_count' => isset($passes_data['count']) ? (int) $passes_data['count'] : 5,
                'duration' => isset($passes_data['duration']) ? (int) $passes_data['duration'] : 60,
                'expiration_days' => isset($passes_data['expiration']) ? (int) $passes_data['expiration'] : 180
            ];
        }
        
        return null;
    }

    /**
     * Add passes to a user
     */
    public function add_passes_to_user($user_id, $order_id, $service_id, $duration, $passes_count, $expiration_days = 180) {
        $expiration_date = date('Y-m-d', strtotime("+$expiration_days days"));
        
        $this->wpdb->insert(
            "{$this->wpdb->prefix}sod_passes",
            [
                'user_id' => $user_id,
                'order_id' => $order_id,
                'service_id' => $service_id,
                'duration' => $duration,
                'total_passes' => $passes_count,
                'remaining_passes' => $passes_count,
                'expiration_date' => $expiration_date,
                'pass_type' => 'single',
                'status' => 'active',
                'created_at' => current_time('mysql')
            ],
            ['%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s']
        );
        
        $pass_id = $this->wpdb->insert_id;
        do_action('spark_divine_passes_added', $user_id, $service_id, $pass_id, $passes_count, $expiration_date);
    }

    /**
     * AJAX endpoint to check available passes
     */
    public function ajax_check_available_passes() {
        check_ajax_referer('sod_booking_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $service_id = isset($_POST['service']) ? intval($_POST['service']) : 0;
        
        if (!$user_id) {
            wp_send_json_error(['message' => __('You must be logged in to use passes', 'spark-of-divine-scheduler')]);
            return;
        }
        
        $available_passes = $this->count_available_passes($user_id, $service_id);
        $can_use_passes = ($available_passes > 0);
        
        wp_send_json_success([
            'canUsePasses' => $can_use_passes,
            'availablePasses' => $available_passes,
            'message' => $can_use_passes ? 
                sprintf(__('You have %d passes available', 'spark-of-divine-scheduler'), $available_passes) :
                __('No passes available', 'spark-of-divine-scheduler')
        ]);
    }

    /**
     * Log a message to the error log
     */
    private function log_message($message) {
        error_log(date('[Y-m-d H:i:s] ') . "[SOD Passes] " . $message . "\n", 3, $this->error_log_file);
    }

    /**
     * Log an error message to the error log
     */
    private function log_error($message) {
        error_log(date('[Y-m-d H:i:s] ') . "[SOD Passes ERROR] " . $message . "\n", 3, $this->error_log_file);
    }
}