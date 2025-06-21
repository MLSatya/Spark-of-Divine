<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SOD_Booking_Handler
 * 
 * Handles the core booking workflow for the Spark of Divine Scheduler,
 * integrating with WooCommerce and delegating specific logic to specialized handlers.
 * 
 * Refactored: Unified booking handling into a single internal method `process_booking_request`
 */
class SOD_Booking_Handler {
    private static $instance;
    private $wpdb;
    private $bookings_table;
    private $debug_mode = false;

    /**
     * Get singleton instance
     * 
     * @return SOD_Booking_Handler
     */
    public static function getInstance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // Set debug mode based on WordPress constant
        $this->debug_mode = defined('WP_DEBUG') && WP_DEBUG;
        
        // Make available in globals
        $GLOBALS['sod_booking_handler'] = $this;
        
        // Set bookings table with fallback
        $this->bookings_table = $wpdb->prefix . 'sod_bookings';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->bookings_table}'") !== $this->bookings_table) {
            $this->bookings_table = 'wp_3be9vb_sod_bookings'; // Fallback
        }
        
        // AJAX handlers using anonymous functions for consistent processing
        add_action('wp_ajax_sod_submit_booking', function() {
            check_ajax_referer('sod_booking_nonce', 'nonce');
            $this->process_booking_request($_POST);
        });
        add_action('wp_ajax_nopriv_sod_submit_booking', function() {
            check_ajax_referer('sod_booking_nonce', 'nonce');
            $this->process_booking_request($_POST);
        });
        add_action('wp_ajax_sod_process_booking', function() {
            check_ajax_referer('sod_booking_nonce', 'nonce');
            $this->process_booking_request($_POST);
        });
        
        add_action('wp_ajax_sod_get_available_timeslots', array($this, 'handle_get_available_timeslots'));
        add_action('wp_ajax_nopriv_sod_get_available_timeslots', array($this, 'handle_get_available_timeslots'));
        
        // WooCommerce integration
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'add_booking_data_to_order_item'), 10, 4);
        add_action('woocommerce_payment_complete', array($this, 'process_completed_payment'));
        add_action('woocommerce_order_status_completed', array($this, 'handle_completed_order'));
        add_action('woocommerce_checkout_order_processed', array($this, 'handle_checkout_data'), 10, 2);

        // Custom actions
        add_action('sod_before_finalize_booking', array($this, 'finalize_booking_extensions'), 10, 3);
        
        // Add filter for product data resolution
        add_filter('sod_resolve_product_data', array($this, 'resolve_product_data'), 10, 4);
        
        $this->log("SOD Booking Handler initialized");
    }

    /**
     * Log message to error log if debug mode is enabled
     * 
     * @param string $message Message to log
     * @param string $type Type of message (info, error, warning)
     */
    private function log($message, $type = 'info') {
        if ($this->debug_mode) {
            error_log("[SOD Booking Handler] [$type] $message");
        }
    }

    /**
     * Create a booking programmatically
     * 
     * @param array $args Booking arguments
     * @return int|WP_Error Booking ID or WP_Error
     */
    public function create_booking($args) {
        $args['nonce'] = wp_create_nonce('sod_booking_nonce');
        return $this->process_booking_request($args, true);
    }

    /**
     * Handle booking creation via unified processing method
     * 
     * @param array $args Request arguments
     * @param bool $internal Whether this is an internal call
     * @return int|WP_Error|void Booking ID, WP_Error, or JSON response
     */
    private function process_booking_request($args, $internal = false) {
        try {
            // Get and sanitize input parameters
            $product_id   = isset($args['product_id']) ? intval($args['product_id']) : 0;
            $variation_id = isset($args['variation_id']) ? intval($args['variation_id']) : 0;
            $duration     = isset($args['duration']) ? intval($args['duration']) : 0;
            $staff_id     = isset($args['staff_id']) ? intval($args['staff_id']) : 0;
            $booking_date = sanitize_text_field($args['booking_date'] ?? $args['date'] ?? '');
            $timeslot     = sanitize_text_field($args['timeslot'] ?? '');
            $attribute    = isset($args['attribute']) ? sanitize_text_field($args['attribute']) : '';

            $this->log("Booking attempt: product=$product_id, staff=$staff_id, start=$timeslot, date=$booking_date, attribute=$attribute, variation=$variation_id, duration=$duration");

            if (!$product_id || !$staff_id || !$booking_date || !$timeslot) {
                throw new Exception(__('Missing required fields.', 'spark-of-divine-scheduler'));
            }

            // Handle date and time formats
            $datetime = "$booking_date $timeslot";
            if (strpos($timeslot, ' ') !== false) {
                $datetime = $timeslot; // timeslot already includes date
            }
            
            // Convert attribute to array format if needed
            $attribute_data = $this->parse_attribute_data($attribute);
            
            // If no duration specified, try to extract from attribute
            if (!$duration && isset($attribute_data['type']) && $attribute_data['type'] === 'duration') {
                // Extract numeric part from strings like "60-min"
                $duration_str = preg_replace('/[^0-9]/', '', $attribute_data['value']);
                if ($duration_str) {
                    $duration = intval($duration_str);
                    $this->log("Extracted duration $duration from attribute");
                }
            }
            
            if (!$duration) {
                $duration = 60; // Default duration
                $this->log("Using default duration: 60 minutes");
            }

            // Validation
            if (!class_exists('SOD_Booking_Validator')) {
                throw new Exception(__('Booking validator not available', 'spark-of-divine-scheduler'));
            }
            
            $validator = SOD_Booking_Validator::getInstance();
            
            // Run validation with duration
            $validation = $validator->validate_booking_request(
                null, 
                $product_id, 
                $staff_id, 
                $datetime, 
                $attribute_data,
                $duration
            );

            if (!$validation['valid']) {
                throw new Exception($validation['message']);
            }

            // Begin database transaction
            $this->wpdb->query('START TRANSACTION');

            // Set full datetime if needed
            $start_time = $datetime;

            // Create booking post
            $booking_id = $this->create_booking_post($product_id, $staff_id, $start_time, $attribute_data, $duration);
            if (!$booking_id) {
                throw new Exception(__('Failed to create booking', 'spark-of-divine-scheduler'));
            }

            // Create booking database entry
            $this->create_booking_db_entry($booking_id, $product_id, $staff_id, $start_time, $duration);

            // Resolve product data for cart
            $product_data = apply_filters('sod_resolve_product_data', null, $product_id, $attribute_data, $variation_id);
            if (!$product_data) {
                // Fallback to our own method if filter doesn't provide data
                $product_data = $this->resolve_product_data(null, $product_id, $attribute_data, $variation_id);
                
                if (!$product_data) {
                    throw new Exception(__('No product found for this booking.', 'spark-of-divine-scheduler'));
                }
            }

            // Add to WooCommerce cart
            $cart_item_key = $this->add_to_woocommerce_cart($product_data, $booking_id);
            if (!$cart_item_key) {
                throw new Exception(__('Failed to add to cart', 'spark-of-divine-scheduler'));
            }

            // Set flag that this booking needs payment before sending emails
            update_post_meta($booking_id, 'sod_needs_payment', 'yes');

            // Allow extensions to process booking
            do_action('sod_before_finalize_booking', $booking_id, $product_id, get_current_user_id());
            
            // Commit transaction
            $this->wpdb->query('COMMIT');

            // Response: Booking added to cart, payment required
            $response = [
                'requires_payment' => true,
                'booking_id' => $booking_id,
                'cart_url' => wc_get_cart_url(),
                'redirect' => wc_get_cart_url(),
                'redirect' => wc_get_cart_url(),
                'message' => __('Booking added to cart! Payment is required—continue shopping or proceed to checkout.', 'spark-of-divine-scheduler')
            ];

            if ($internal) {
                return $booking_id;
            }
            wp_send_json_success($response);

        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
            $error_message = $e->getMessage();
            $this->log("Booking error: $error_message", 'error');
            if ($internal) {
                return new WP_Error('booking_error', $error_message);
            }
            wp_send_json_error(['message' => $error_message]);
        }
    }

    /**
     * Handle AJAX request to get available timeslots
     */
    public function handle_get_available_timeslots() {
        try {
            // Check the nonce with fallback support for different nonce names
            $nonce_verified = false;
            
            if (isset($_POST['nonce'])) {
                if (wp_verify_nonce($_POST['nonce'], 'sod_booking_admin_nonce')) {
                    $nonce_verified = true;
                } elseif (wp_verify_nonce($_POST['nonce'], 'sod_booking_nonce')) {
                    $nonce_verified = true;
                }
            }
            
            if (!$nonce_verified) {
                throw new Exception(__('Security check failed', 'spark-of-divine-scheduler'));
            }

            $staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;
            $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
            $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
            $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 0;
            
            // If no duration specified, try to get from attribute
            if (!$duration && isset($_POST['attribute'])) {
                $attribute = sanitize_text_field($_POST['attribute']);
                $attribute_data = $this->parse_attribute_data($attribute);
                
                if (isset($attribute_data['type']) && $attribute_data['type'] === 'duration') {
                    // Extract numeric part from strings like "60-min"
                    $duration_str = preg_replace('/[^0-9]/', '', $attribute_data['value']);
                    if ($duration_str) {
                        $duration = intval($duration_str);
                        $this->log("Extracted duration $duration from attribute for timeslot lookup");
                    }
                }
            }
            
            // Default duration if not specified
            if (!$duration) {
                $duration = 60;
                $this->log("Using default duration: 60 minutes for timeslot lookup");
            }

            if (!$staff_id || !$date) {
                throw new Exception(__('Missing required parameters', 'spark-of-divine-scheduler'));
            }

            $timeslots = $this->get_available_timeslots($product_id, $staff_id, $date, $duration);

            wp_send_json_success(array(
                'timeslots' => $timeslots,
                'debug' => array(
                    'staff_id' => $staff_id,
                    'date' => $date,
                    'product_id' => $product_id,
                    'duration' => $duration
                )
            ));
        } catch (Exception $e) {
            $this->log("Timeslot error: " . $e->getMessage(), 'error');
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Get available timeslots for a given product, staff, and date
     * 
     * @param int $product_id Product ID
     * @param int $staff_id Staff ID
     * @param string $date Date in Y-m-d format
     * @param int $duration Duration in minutes
     * @return array Array of available timeslots
     */
    private function get_available_timeslots($product_id, $staff_id, $date, $duration = 60) {
        $timeslots = [];

        try {
            // Get staff availability times for the specific day of week
            $day_of_week = strtolower(date('l', strtotime($date)));
            $staff_start_time = get_post_meta($staff_id, "sod_start_time_{$day_of_week}", true) ?: 
                               get_post_meta($staff_id, 'sod_default_start_time', true) ?: '09:00:00';
            $staff_end_time = get_post_meta($staff_id, "sod_end_time_{$day_of_week}", true) ?: 
                             get_post_meta($staff_id, 'sod_default_end_time', true) ?: '17:00:00';

            $start_time = new DateTime("$date $staff_start_time");
            $end_time = new DateTime("$date $staff_end_time");
            $interval = new DateInterval('PT15M'); // 15 minute intervals

            // Create validator and load existing bookings
            $validator = SOD_Booking_Validator::getInstance();
            $booked_slots = $this->get_booked_slots($staff_id, $date);
            
            $this->log("Checking timeslots for staff $staff_id on $date with duration $duration minutes");
            $this->log("Staff hours: $staff_start_time to $staff_end_time");
            $this->log("Found " . count($booked_slots) . " existing bookings");

            // Check each timeslot
            $potential_start = clone $start_time;
            while ($potential_start < $end_time) {
                $time_str = $potential_start->format('H:i:s');
                $datetime_str = "$date $time_str";
                
                // Calculate end time for this potential slot
                $potential_end = clone $potential_start;
                $potential_end->add(new DateInterval('PT' . $duration . 'M'));
                
                // Skip if this slot ends after staff hours
                if ($potential_end > $end_time) {
                    $potential_start->add($interval);
                    continue;
                }
                
                // Check for conflicts with existing bookings
                $has_conflict = false;
                foreach ($booked_slots as $booked) {
                    $booked_start = new DateTime("$date {$booked['start_time']}");
                    $booked_end = new DateTime("$date {$booked['end_time']}");
                    
                    // If there's any overlap, we have a conflict
                    if ($potential_start < $booked_end && $potential_end > $booked_start) {
                        $has_conflict = true;
                        break;
                    }
                }
                
                if (!$has_conflict) {
                    // Additional validation using the validator
                    $attribute_data = array('type' => 'duration', 'value' => $duration . '-min');
                    $validation = $validator->validate_booking_request(
                        null, 
                        $product_id, 
                        $staff_id, 
                        $datetime_str, 
                        $attribute_data,
                        $duration
                    );
                    
                    if ($validation['valid']) {
                        $timeslots[] = [
                            'time' => $time_str,
                            'formatted' => date_i18n(get_option('time_format'), $potential_start->getTimestamp()) . 
                                         ' - ' . 
                                         date_i18n(get_option('time_format'), $potential_end->getTimestamp())
                        ];
                    }
                }

                $potential_start->add($interval);
            }
            
            $this->log("Found " . count($timeslots) . " available timeslots");
            
        } catch (Exception $e) {
            $this->log("Error getting available timeslots: " . $e->getMessage(), 'error');
        }

        return $timeslots;
    }
    
    /**
     * Get booked slots for a staff member on a specific date
     * 
     * @param int $staff_id Staff ID
     * @param string $date Date in Y-m-d format
     * @return array Array of booked slots
     */
    private function get_booked_slots($staff_id, $date) {
        try {
            $query = $this->wpdb->prepare(
                "SELECT TIME_FORMAT(start_time, '%%H:%%i:%%s') as start_time, 
                        TIME_FORMAT(end_time, '%%H:%%i:%%s') as end_time
                 FROM {$this->bookings_table}
                 WHERE staff_id = %d 
                 AND date = %s
                 AND status NOT IN ('cancelled', 'no-show')",
                $staff_id,
                $date
            );
            
            $results = $this->wpdb->get_results($query, ARRAY_A);
            
            if ($this->wpdb->last_error) {
                $this->log("Database error getting booked slots: " . $this->wpdb->last_error, 'error');
                return array();
            }
            
            return $results ?: array();
            
        } catch (Exception $e) {
            $this->log("Error getting booked slots: " . $e->getMessage(), 'error');
            return array();
        }
    }

    /**
     * Resolves product data for a booking
     * 
     * @param array|null $product_data Existing product data
     * @param int $product_id Product ID
     * @param array $attribute Attribute data
     * @param int $variation_id Variation ID
     * @return array|null Product data array or null
     */
    public function resolve_product_data($product_data, $product_id, $attribute, $variation_id = 0) {
        // If product data already exists, return it
        if ($product_data && isset($product_data['product_id']) && $product_data['product_id'] > 0) {
            return $product_data;
        }

        // 1. Try using WooCommerce Integration if available
        if (isset($GLOBALS['sod_woocommerce_integration'])) {
            $wc_integration = $GLOBALS['sod_woocommerce_integration'];
            $staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;
            
            if (method_exists($wc_integration, 'enhance_product_lookup')) {
                $enhanced_data = $wc_integration->enhance_product_lookup(null, $product_id, $attribute);
                if ($enhanced_data) {
                    $this->log("Using product data from WooCommerce Integration");
                    return $enhanced_data;
                }
            }
        }

        // 2. Check for attribute-specific product data
        $attribute_product_data = $this->get_product_data_from_attribute($product_id, $attribute);
        if ($attribute_product_data) {
            $this->log("Using product data from attribute-specific lookup");
            return $attribute_product_data;
        }
        
        // 3. Default fallback - use the product_id directly with specified variation
        $product = wc_get_product($product_id);
        if ($product) {
            $price = $product->get_price();
            
            // If a variation ID is provided, use its price
            if ($variation_id) {
                $variation = wc_get_product($variation_id);
                if ($variation) {
                    $price = $variation->get_price();
                }
            }
            
            // For variable products, try to find matching variation if no variation_id provided
            if ($product->is_type('variable') && !$variation_id) {
                foreach ($product->get_available_variations() as $variation_data) {
                    $variation_id = $variation_data['variation_id'];
                    $variation_obj = wc_get_product($variation_id);
                    $attributes = $variation_obj->get_attributes();
                    
                    // Check if variation matches the attribute type/value
                    $found_match = false;
                    if (is_array($attribute) && isset($attribute['type'], $attribute['value'])) {
                        foreach ($attributes as $key => $value) {
                            if (strpos($key, $attribute['type']) !== false && 
                                strpos($value, $attribute['value']) !== false) {
                                $found_match = true;
                                $price = $variation_obj->get_price();
                                break;
                            }
                        }
                    }
                    
                    if ($found_match) {
                        $this->log("Found matching variation $variation_id for product $product_id");
                        break;
                    }
                }
            }
            
            $this->log("Using direct product data from WooCommerce product $product_id");
            return [
                'product_id' => $product_id,
                'variation_id' => $variation_id ?: 0,
                'price' => $price
            ];
        }

        $this->log("Failed to find any product data for product_id: $product_id", 'error');
        return null;
    }

    /**
     * Create booking post
     * 
     * @param int $product_id Product ID
     * @param int $staff_id Staff ID 
     * @param string $start_time Start time (Y-m-d H:i:s)
     * @param array $attribute_data Attribute data
     * @param int $duration Duration in minutes
     * @return int|false Booking post ID or false on failure
     */
    private function create_booking_post($product_id, $staff_id, $start_time, $attribute_data, $duration) {
        try {
            // Get product and staff info
            $product = wc_get_product($product_id);
            $item_title = $product ? $product->get_title() : "Product #$product_id";
            $staff_title = get_the_title($staff_id);

            // Calculate start and end times
            $start_datetime = new DateTime($start_time);
            $end_datetime = clone $start_datetime;
            $end_datetime->modify("+{$duration} minutes");
            $end_time = $end_datetime->format('Y-m-d H:i:s');

            // Get same-day bookings for this staff
            $booking_date = $start_datetime->format('Y-m-d');
            $same_day_bookings = $this->get_same_day_bookings($staff_id, $booking_date);

            // Create day schedule text
            $day_schedule = '';
            if (!empty($same_day_bookings)) {
                $day_schedule = sprintf(
                    __('Other bookings on %s: ', 'spark-of-divine-scheduler'),
                    date_i18n(get_option('date_format'), strtotime($booking_date))
                );

                foreach ($same_day_bookings as $existing) {
                    $existing_product = wc_get_product($existing->product_id);
                    $product_name = $existing_product ? $existing_product->get_title() : "Product #{$existing->product_id}";
                    $day_schedule .= sprintf(
                        '%s (%s), ',
                        date_i18n(get_option('time_format'), strtotime($existing->start_time)),
                        $product_name
                    );
                }
                $day_schedule = rtrim($day_schedule, ', ');
            }

            // Prepare post meta
            $meta_input = [
                'sod_product_id' => $product_id,
                'sod_staff_id' => $staff_id,
                'sod_start_time' => $start_time,
                'sod_end_time' => $end_time,
                'sod_duration' => $duration,
                'sod_customer_id' => get_current_user_id() ?: 1,
                'sod_status' => 'pending',
                'sod_payment_method' => 'partial',
                'sod_item_type' => 'product',
                'sod_day_schedule' => $day_schedule
            ];

            // Add attribute data
            if (isset($attribute_data['type'], $attribute_data['value'])) {
                $meta_input['sod_attribute_type'] = $attribute_data['type'];
                $meta_input['sod_attribute_value'] = $attribute_data['value'];
            }

            // Create booking post
            $booking_data = [
                'post_type' => 'sod_booking',
                'post_status' => 'publish',
                'post_title' => sprintf(__('Booking for %s with %s', 'spark-of-divine-scheduler'), $item_title, $staff_title),
                'meta_input' => $meta_input
            ];

            $post_id = wp_insert_post($booking_data);

            if (is_wp_error($post_id)) {
                throw new Exception("Error creating booking post: " . $post_id->get_error_message());
            }

            // Fire created action but email will be sent after payment
            do_action('sod_booking_status_created', $post_id);
            
            // Update other bookings' schedules to show this one
            $this->update_other_bookings_schedules($post_id, $staff_id, $booking_date, $start_datetime->format('H:i:s'), $item_title);

            $this->log("Created booking post with ID: $post_id");
            return $post_id;
        } catch (Exception $e) {
            $this->log("Exception in create_booking_post: " . $e->getMessage(), 'error');
            return 0;
        }
    }

    /**
     * Create or update booking entry in custom table
     * 
     * @param int $booking_id Booking post ID
     * @param int $product_id Product ID
     * @param int $staff_id Staff ID
     * @param string $start_time Start time (Y-m-d H:i:s)
     * @param int $duration Duration in minutes
     * @return bool Success indicator
     */
    private function create_booking_db_entry($booking_id, $product_id, $staff_id, $start_time, $duration) {
        try {
            $start_datetime = new DateTime($start_time);
            $date = $start_datetime->format('Y-m-d');
            $time = $start_datetime->format('H:i:s');
    
            $end_datetime = clone $start_datetime;
            $end_datetime->modify("+{$duration} minutes");
            $end_time = $end_datetime->format('H:i:s');
    
            $customer_id = get_current_user_id() ?: 1;
    
            // Check if booking already exists in custom table
            $existing = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT booking_id FROM {$this->bookings_table} WHERE booking_id = %d",
                $booking_id
            ));
    
            $data = [
                'customer_id' => $customer_id,
                'product_id' => $product_id,
                'staff_id' => $staff_id,
                'date' => $date,
                'start_time' => $time,
                'end_time' => $end_time,
                'duration' => $duration,
                'status' => 'pending',
                'payment_method' => 'partial'
            ];
    
            if ($existing) {
                $result = $this->wpdb->update(
                    $this->bookings_table,
                    $data,
                    ['booking_id' => $booking_id]
                );
            } else {
                $data['booking_id'] = $booking_id;
                $result = $this->wpdb->insert(
                    $this->bookings_table,
                    $data
                );
            }
            
            if ($result === false) {
                $this->log("Database error in create_booking_db_entry: " . $this->wpdb->last_error, 'error');
                return false;
            }
    
            $this->log("Updated custom booking table for booking ID: $booking_id");
            return true;
        } catch (Exception $e) {
            $this->log("Exception in create_booking_db_entry: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Add booking to WooCommerce cart
     * 
     * @param array $product_data Product data 
     * @param int $booking_id Booking ID
     * @return string|false Cart item key or false on failure
     */
    private function add_to_woocommerce_cart($product_data, $booking_id) {
        try {
            $product_id = $product_data['product_id'] ?? 0;
            $variation_id = $product_data['variation_id'] ?? 0;
            $price = $product_data['price'] ?? 0;

            $product = wc_get_product($product_id);
            if (!$product) {
                throw new Exception("Product not found: $product_id");
            }

            $full_price = floatval($price ?: $product->get_price());
            $deposit_price = $full_price * 0.35; // 35% deposit

            $cart_item_data = [
                'booking_id' => $booking_id,
                'full_price' => $full_price,
                'deposit_price' => $deposit_price,
                'unique_key' => md5(uniqid(rand(), true)) // Prevent item merging
            ];

            if ($variation_id) {
                $cart_item_data['variation_id'] = $variation_id;
            }

            // Set deposit price for this cart item
            add_filter('woocommerce_product_get_price', array($this, 'set_deposit_price'), 10, 2);
            $cart_item_key = WC()->cart->add_to_cart($product_id, 1, $variation_id, [], $cart_item_data);
            remove_filter('woocommerce_product_get_price', array($this, 'set_deposit_price'), 10);

            if (!$cart_item_key) {
                throw new Exception("Failed to add to cart");
            }

            $this->log("Added booking $booking_id to cart with key: $cart_item_key");
            return $cart_item_key;
        } catch (Exception $e) {
            $this->log("Exception in add_to_woocommerce_cart: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Set deposit price for cart item
     * 
     * @param float $price Original price
     * @param WC_Product $product WooCommerce product
     * @return float Modified price
     */
    public function set_deposit_price($price, $product) {
        $cart = WC()->cart->get_cart();
        foreach ($cart as $item) {
            if (isset($item['deposit_price'])) {
                return $item['deposit_price'];
            }
        }
        return $price;
    }

    /**
     * Add booking data to order item
     */
    public function add_booking_data_to_order_item($item, $cart_item_key, $values, $order) {
        if (isset($values['booking_id'])) {
            $booking_id = $values['booking_id'];
            
            // Add hidden booking ID
            $item->add_meta_data('_booking_id', $booking_id);
            
            // Add pricing info
            if (isset($values['full_price'])) {
                $item->add_meta_data('full_price', $values['full_price']);
            }
            
            if (isset($values['deposit_price'])) {
                $item->add_meta_data('deposit_price', $values['deposit_price']);
            }

            // Get booking details
            $product_id = get_post_meta($booking_id, 'sod_product_id', true);
            $staff_id = get_post_meta($booking_id, 'sod_staff_id', true);
            $start_time = get_post_meta($booking_id, 'sod_start_time', true);
            $duration = get_post_meta($booking_id, 'sod_duration', true);

            // Add visible meta data
            $product = wc_get_product($product_id);
            $product_name = $product ? $product->get_title() : "Product #$product_id";
            
            $item->add_meta_data('Product', $product_name);
            
            if ($staff_id) {
                $item->add_meta_data('Staff', get_the_title($staff_id));
            }
            
            if ($start_time) {
                $item->add_meta_data('Date', date_i18n(get_option('date_format'), strtotime($start_time)));
                $item->add_meta_data('Time', date_i18n(get_option('time_format'), strtotime($start_time)));
            }
            
            if ($duration) {
                $item->add_meta_data('Duration', $duration . ' minutes');
            }

            // Update booking meta
            update_post_meta($booking_id, 'sod_order_item_id', $item->get_id());
            
            // Calculate and update remaining balance
            if (isset($values['full_price']) && isset($values['deposit_price'])) {
                $remaining = $values['full_price'] - $values['deposit_price'];
                update_post_meta($booking_id, 'sod_remaining_balance', $remaining);
            }
            
            // Update custom booking table
            $this->update_custom_booking_table($booking_id, 'order_id', $order->get_id());
            
            $this->log("Added booking data to order item for booking ID: $booking_id, Order ID: {$order->get_id()}");
        }
    }

    /**
     * Process completed payment
     * 
     * @param int $order_id Order ID
     */
    public function process_completed_payment($order_id) {
        $this->handle_completed_order($order_id);
    }

    /**
     * Handle completed order
     * 
     * @param int $order_id Order ID
     */
    public function handle_completed_order($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            $this->log("Failed to retrieve order with ID $order_id", 'error');
            return;
        }
        
        $booking_ids = [];
        
        foreach ($order->get_items() as $item) {
            $booking_id = $item->get_meta('_booking_id');
            if ($booking_id) {
                $booking_ids[] = $booking_id;
                
                // Process booking
                $this->confirm_booking($booking_id);
                
                // Update payment status
                update_post_meta($booking_id, 'sod_needs_payment', 'no');
                update_post_meta($booking_id, 'sod_payment_method', 'paid');
                update_post_meta($booking_id, 'sod_payment_status', 'completed');
                update_post_meta($booking_id, 'sod_order_id', $order_id);
                
                $this->update_custom_booking_table($booking_id, 'payment_method', 'paid');
                $this->update_custom_booking_table($booking_id, 'order_id', $order_id);
                
                $this->log("Updated payment status for booking ID: $booking_id");
            }
        }
        
        if (!empty($booking_ids)) {
            do_action('sod_bookings_payment_completed', $booking_ids, $order_id);
            $this->log("Completed payment for " . count($booking_ids) . " bookings in order $order_id");
        }
    }

    /**
     * Update custom booking table with a field/value
     * 
     * @param int $booking_id Booking ID
     * @param string $field Field name
     * @param mixed $value Field value
     * @return bool Success or failure
     */
    public function update_custom_booking_table($booking_id, $field, $value) {
        $result = $this->wpdb->update(
            $this->bookings_table,
            [$field => $value],
            ['booking_id' => $booking_id],
            ['%s'],
            ['%d']
        );
        
        $this->log("Updated booking $booking_id in custom table: $field = $value");
        return $result !== false;
    }

    /**
     * Parse attribute data to standardized format
     * 
     * @param mixed $attribute Attribute data (string or array)
     * @return array Parsed attribute data with type and value
     */
    private function parse_attribute_data($attribute) {
        // If already an array with correct structure, return it
        if (is_array($attribute) && isset($attribute['type'], $attribute['value'])) {
            return $attribute;
        }

        // Try to decode JSON string
        if (is_string($attribute)) {
            $decoded = json_decode($attribute, true);
            if (is_array($decoded) && isset($decoded['type'], $decoded['value'])) {
                return $decoded;
            }
        }

        // Default fallback
        return ['type' => 'duration', 'value' => '60-min'];
    }

    /**
     * Get product data based on attribute
     * 
     * @param int $product_id Product ID
     * @param mixed $attribute Attribute data
     * @return array|false Product data or false if not found
     */
    private function get_product_data_from_attribute($product_id, $attribute) {
        $attribute_data = $this->parse_attribute_data($attribute);
        
        // Check custom attributes table first
        $table = $this->wpdb->prefix . 'sod_product_attributes';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            $table = defined('SOD_PRODUCT_ATTRIBUTES_TABLE') ? SOD_PRODUCT_ATTRIBUTES_TABLE : 'wp_3be9vb_sod_product_attributes';
        }

        $result = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT product_id, variation_id, price FROM $table 
             WHERE product_id = %d AND attribute_type = %s AND value = %s LIMIT 1",
            $product_id, $attribute_data['type'], $attribute_data['value']
        ));

        if ($result) {
            return [
                'product_id' => $result->product_id,
                'variation_id' => $result->variation_id,
                'price' => $result->price
            ];
        }
        
        // Check if the product is a variable product with matching variations
        $product = wc_get_product($product_id);
        if ($product && $product->is_type('variable')) {
            foreach ($product->get_available_variations() as $variation) {
                $variation_obj = wc_get_product($variation['variation_id']);
                $variation_attributes = $variation_obj->get_attributes();
                
                // Look for matching attribute
                $found_match = false;
                foreach ($variation_attributes as $key => $value) {
                    if (strpos($key, $attribute_data['type']) !== false && 
                        strpos($value, $attribute_data['value']) !== false) {
                        $found_match = true;
                        break;
                    }
                }
                
                if ($found_match) {
                    return [
                        'product_id' => $product_id,
                        'variation_id' => $variation['variation_id'],
                        'price' => $variation_obj->get_price()
                    ];
                }
            }
        }
        
        // No matching product found
        return false;
    }

    /**
     * Get same day bookings
     * 
     * @param int $staff_id Staff ID
     * @param string $date Date (Y-m-d)
     * @return array Array of booking objects
     */
    private function get_same_day_bookings($staff_id, $date) {
        $bookings = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT booking_id, start_time, end_time, product_id, status 
             FROM {$this->bookings_table} 
             WHERE date = %s AND staff_id = %d
             AND status NOT IN ('cancelled', 'no-show')
             ORDER BY start_time ASC",
            $date,
            $staff_id
        ));

        return $bookings;
    }

    /**
     * Update other bookings' schedules when a new booking is created
     * 
     * @param int $new_booking_id New booking ID
     * @param int $staff_id Staff ID
     * @param string $date Date (Y-m-d)
     * @param string $time Time (H:i:s)
     * @param string $product_title Product title
     */
    private function update_other_bookings_schedules($new_booking_id, $staff_id, $date, $time, $product_title) {
        $other_booking_ids = $this->wpdb->get_col($this->wpdb->prepare(
            "SELECT booking_id FROM {$this->bookings_table} 
             WHERE date = %s AND staff_id = %d AND booking_id != %d
             AND status NOT IN ('cancelled', 'no-show')",
            $date,
            $staff_id,
            $new_booking_id
        ));

        if (empty($other_booking_ids)) {
            return;
        }

        // Get all bookings for this day/staff
        $all_bookings = $this->get_same_day_bookings($staff_id, $date);

        // Create schedule text
        $day_schedule = sprintf(
            __('Other bookings on %s: ', 'spark-of-divine-scheduler'),
            date_i18n(get_option('date_format'), strtotime($date))
        );

        foreach ($all_bookings as $booking) {
            $product = wc_get_product($booking->product_id);
            $product_name = $product ? $product->get_title() : "Product #{$booking->product_id}";
            $day_schedule .= sprintf(
                '%s (%s), ',
                date_i18n(get_option('time_format'), strtotime($booking->start_time)),
                $product_name
            );
        }
        $day_schedule = rtrim($day_schedule, ', ');

        // Update schedule on all other bookings
        foreach ($other_booking_ids as $booking_id) {
            update_post_meta($booking_id, 'sod_day_schedule', $day_schedule);
            $this->log("Updated day schedule for booking $booking_id");
        }
    }

    /**
     * Handle checkout data from WooCommerce
     * 
     * @param int $order_id Order ID
     * @param array $posted_data Posted checkout data
     */
    public function handle_checkout_data($order_id, $posted_data) {
        $order = wc_get_order($order_id);
        if (!$order) {
            $this->log("Failed to retrieve order with ID $order_id", 'error');
            return;
        }

        // Get checkout extension data
        $extension_data = $order->get_meta('sod-checkout-integration', true);
        
        // Fall back to session data if needed
        $phone = '';
        $email = '';
        $register = false;

        if ($extension_data) {
            $phone = isset($extension_data['phone']) ? sanitize_text_field($extension_data['phone']) : '';
            $email = isset($extension_data['email']) ? sanitize_email($extension_data['email']) : '';
            $register = isset($extension_data['register']) ? (bool)$extension_data['register'] : false;
        } else {
            $this->log("No extension data found for order $order_id, using session data");
            $phone = WC()->session->get('sod_cart_phone', '');
            $email = WC()->session->get('sod_cart_email', '');
        }

        // Exit if we don't have required data
        if (empty($phone) || empty($email)) {
            $this->log("Missing required contact info for order $order_id", 'warning');
            return;
        }

        // Check if user needs to be created
        $user_id = $order->get_user_id();
        
        if ($register && !$user_id) {
            $user_id = $this->create_user_from_checkout_data($order, $extension_data, $email, $phone);
        }

        // Create or update customer record
        $customer_id = $this->create_or_update_customer($user_id, $email, $phone, $extension_data);
        
        // Associate customer with bookings
        if ($customer_id) {
            foreach ($order->get_items() as $item) {
                $booking_id = $item->get_meta('_booking_id');
                if ($booking_id) {
                    update_post_meta($booking_id, 'sod_customer_id', $customer_id);
                    $this->update_custom_booking_table($booking_id, 'customer_id', $customer_id);
                    $this->log("Linked customer ID $customer_id to booking ID $booking_id");
                }
            }
        }

        // Save extension data back to order
        $order->update_meta_data('sod-checkout-integration', $extension_data);
        $order->save();
    }

    /**
     * Create a WordPress user from checkout data
     * 
     * @param WC_Order $order The order
     * @param array $extension_data Extension data
     * @param string $email User email
     * @param string $phone User phone
     * @return int|false User ID or false on failure
     */
    private function create_user_from_checkout_data($order, $extension_data, $email, $phone) {
        // Basic user info
        $first_name = isset($extension_data['first_name']) ? sanitize_text_field($extension_data['first_name']) : '';
        $last_name = isset($extension_data['last_name']) ? sanitize_text_field($extension_data['last_name']) : '';
        
        // Create username
        $username = sanitize_user($first_name . '.' . $last_name . '.' . time());
        $password = wp_generate_password();

        // Create the user
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            $this->log("Failed to create user: " . $user_id->get_error_message(), 'error');
            return false;
        }
        
        // Update user data
        wp_update_user([
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'nickname' => isset($extension_data['nickname']) ? sanitize_text_field($extension_data['nickname']) : $username,
        ]);

        // Add user meta
        update_user_meta($user_id, 'billing_phone', $phone);
        
        // Add additional meta if provided
        $meta_fields = [
            'emergency_contact_name',
            'emergency_contact_phone',
            'signing_dependent',
            'dependent_name',
            'dependent_dob'
        ];
        
        foreach ($meta_fields as $field) {
            if (isset($extension_data[$field])) {
                update_user_meta($user_id, $field, sanitize_text_field($extension_data[$field]));
            }
        }

        // Link user to order
        $order->set_customer_id($user_id);
        $order->save();
        
        // Send welcome email
        wp_send_new_user_notifications($user_id, 'user');
        
        $this->log("Created user with ID $user_id and linked to order {$order->get_id()}");
        return $user_id;
    }

    /**
     * Create or update a customer record
     * 
     * @param int $user_id WordPress user ID
     * @param string $email Customer email
     * @param string $phone Customer phone
     * @param array $extension_data Additional data
     * @return int|false Customer ID or false on failure
     */
    private function create_or_update_customer($user_id, $email, $phone, $extension_data) {
        global $wpdb;

        // Find customer table
        $table_name = $wpdb->prefix . 'sod_customers';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
            $table_name = 'wp_3be9vb_sod_customers';
        }

        // Check if customer already exists
        $existing_customer_id = $wpdb->get_var($wpdb->prepare(
            "SELECT customer_id FROM $table_name WHERE email = %s",
            $email
        ));

        // Build customer post data
        $customer_name = (isset($extension_data['first_name']) ? sanitize_text_field($extension_data['first_name']) : '') . ' ' .
                        (isset($extension_data['last_name']) ? sanitize_text_field($extension_data['last_name']) : '');
        
        if (empty(trim($customer_name))) {
            $customer_name = sanitize_email($email);
        }
        
        $customer_data = [
            'post_type' => 'sod_customer',
            'post_title' => $customer_name,
            'post_status' => 'publish',
            'meta_input' => [
                'sod_customer_email' => $email,
                'sod_customer_phone' => $phone,
            ]
        ];

        // Add user ID if available
        if ($user_id) {
            $customer_data['post_author'] = $user_id;
            $customer_data['meta_input']['sod_customer_user_id'] = $user_id;
        }

        // Add additional meta if provided
        $meta_fields = [
            'emergency_contact_name' => 'sod_customer_emergency_contact_name',
            'emergency_contact_phone' => 'sod_customer_emergency_contact_phone',
            'signing_dependent' => 'sod_customer_signing_dependent',
            'dependent_name' => 'sod_customer_dependent_name',
            'dependent_dob' => 'sod_customer_dependent_dob'
        ];
        
        foreach ($meta_fields as $field => $meta_key) {
            if (isset($extension_data[$field])) {
                $customer_data['meta_input'][$meta_key] = sanitize_text_field($extension_data[$field]);
            }
        }

        // Create or update post
        if ($existing_customer_id) {
            $customer_data['ID'] = $existing_customer_id;
            $customer_id = wp_update_post($customer_data);
        } else {
            $customer_id = wp_insert_post($customer_data);
        }

        if (is_wp_error($customer_id)) {
            $this->log('Failed to create/update sod_customer: ' . $customer_id->get_error_message(), 'error');
            return 0;
        }

        // Prepare data for custom table
        $table_data = [
            'user_id' => $user_id ? $user_id : null,
            'name' => $customer_data['post_title'],
            'email' => $email,
            'phone' => $phone,
        ];
        
        // Add additional fields
        foreach ($meta_fields as $field => $meta_key) {
            $table_field = str_replace('sod_customer_', '', $meta_key);
            $table_data[$table_field] = isset($extension_data[$field]) ? sanitize_text_field($extension_data[$field]) : null;
        }

        // Update or insert into custom table
        if ($existing_customer_id) {
            $wpdb->update(
                $table_name,
                $table_data,
                ['customer_id' => $existing_customer_id]
            );
        } else {
            $table_data['customer_id'] = $customer_id;
            $wpdb->insert($table_name, $table_data);
        }

        // Link user to customer
        if ($user_id) {
            update_user_meta($user_id, 'sod_customer_id', $customer_id);
        }

        $this->log("Created/updated customer with ID $customer_id");
        return $customer_id;
    }

    /**
     * Confirm a booking
     * 
     * @param int $booking_id Booking ID
     */
    public function confirm_booking($booking_id) {
        update_post_meta($booking_id, 'sod_status', 'confirmed');
        $this->update_custom_booking_table($booking_id, 'status', 'confirmed');
        do_action('sod_booking_status_confirmed', $booking_id);
        $this->log("Booking $booking_id confirmed");
    }

    /**
     * Cancel a booking
     * 
     * @param int $booking_id Booking ID
     */
    public function cancel_booking($booking_id) {
        update_post_meta($booking_id, 'sod_status', 'canceled');
        $this->update_custom_booking_table($booking_id, 'status', 'canceled');
        do_action('sod_booking_status_canceled', $booking_id);
        $this->log("Booking $booking_id canceled");
    }

    /**
     * Update a booking
     * 
     * @param int $booking_id Booking ID
     * @param array $data Data to update
     */
    public function update_booking($booking_id, $data) {
        foreach ($data as $key => $value) {
            update_post_meta($booking_id, $key, $value);
            $this->update_custom_booking_table($booking_id, str_replace('sod_', '', $key), $value);
        }
        do_action('sod_booking_status_updated', $booking_id);
        $this->log("Booking $booking_id updated");
    }

    /**
     * Check if product uses passes or packages
     * 
     * @param int $product_id Product ID
     * @return bool Whether this product uses passes or packages
     */
    public function is_pass_or_package_product($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
            $this->log("Product $product_id not found", 'warning');
            return false;
        }
        
        // Try to determine by product title (legacy approach)
        $product_title = $product->get_title();
        if ($product_title && stripos($product_title, 'yoga all levels') !== false) {
            $this->log("Product is Yoga All Levels - pass/package based");
            return true;
        }

        // Check product attributes
        $attributes = $this->get_product_attributes($product_id);
        $has_passes = false;
        $has_durations = false;

        foreach ($attributes as $attr) {
            if ($attr->attribute_type === 'passes') {
                $has_passes = true;
            } elseif ($attr->attribute_type === 'duration') {
                $has_durations = true;
            }
        }

        // A product that has passes but no duration options is a pass/package product
        $is_pass_product = $has_passes && !$has_durations;
        
        // Allow other code to modify this determination
        $result = apply_filters('sod_is_pass_or_package_product', $is_pass_product, $product_id);
        
        $this->log("Product $product_id is " . ($result ? "" : "not ") . "a pass/package product");
        return $result;
    }
    
    /**
     * Get product attributes
     * 
     * @param int $product_id Product ID
     * @return array Product attributes
     */
    private function get_product_attributes($product_id) {
        $table = $this->wpdb->prefix . 'sod_product_attributes';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            $table = defined('SOD_PRODUCT_ATTRIBUTES_TABLE') ? SOD_PRODUCT_ATTRIBUTES_TABLE : 'wp_3be9vb_sod_product_attributes';
        }
        
        $attributes = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM $table WHERE product_id = %d",
            $product_id
        ));
        
        return $attributes ?: [];
    }
    
    /**
     * Extension hook for booking finalization
     * 
     * @param int $booking_id Booking ID
     * @param int $product_id Product ID
     * @param int $user_id User ID
     */
    public function finalize_booking_extensions($booking_id, $product_id, $user_id) {
        // Hook for extensions to process
        do_action('sod_booking_finalize_extensions', $booking_id, $product_id, $user_id);
    }
}

// Initialize singleton instance
SOD_Booking_Handler::getInstance();
// Temporary debug function
add_action('wp_ajax_sod_debug_booking', function() {
    error_log('Debug: Booking AJAX called');
    error_log('POST data: ' . print_r($_POST, true));
    
        // Check if user is guest and needs contact info
        $needs_contact_info = false;
        $redirect_url = wc_get_cart_url();
        
        if (!is_user_logged_in() && function_exists('WC') && WC()->session) {
            // Check if contact info is already in session
            $has_contact = WC()->session->get('sod_cart_first_name') && 
                          WC()->session->get('sod_cart_last_name') && 
                          WC()->session->get('sod_cart_email') && 
                          WC()->session->get('sod_cart_phone');
            
            if (!$has_contact) {
                $needs_contact_info = true;
                // For now, redirect to cart where contact fields should appear
                $redirect_url = wc_get_cart_url();
            }
        }
        
        wp_send_json_success([
            'message' => __('Booking confirmed! Redirecting...', 'spark-of-divine-scheduler'),
            'booking_id' => $booking_id,
            'requires_payment' => true,
            'needs_contact_info' => $needs_contact_info,
            'cart_url' => $redirect_url,
            'redirect' => $redirect_url
        ]);
});
add_action('wp_ajax_nopriv_sod_debug_booking', 'sod_debug_booking');
