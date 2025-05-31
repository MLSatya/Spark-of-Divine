<?php
// File: includes/class-sod-schedule-handler.php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * SOD_Schedule_Handler Class
 * 
 * Handles all schedule-related functionality including calendar displays,
 * time slot generation, and AJAX interactions.
 * 
 * Refactored to work exclusively with product_id instead of service_id.
 */
class SOD_Schedule_Handler {
    private static $instance = null;
    private $wpdb;
    private $plugin_path;
    private $plugin_url;

    /**
     * Get singleton instance
     * 
     * @return SOD_Schedule_Handler
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
        // Use the constants defined in the main plugin file:
        $this->plugin_path = SOD_PLUGIN_PATH;
        $this->plugin_url  = SOD_PLUGIN_URL;

        $this->init_hooks();
        $this->ensure_assets();
        
        error_log("SOD Schedule Handler initialized");
    }

    /**
     * Initialize all hooks
     */
    private function init_hooks() {
        // AJAX handlers
        add_action('wp_ajax_fetch_events', array($this, 'fetch_events'));
        add_action('wp_ajax_nopriv_fetch_events', array($this, 'fetch_events'));
        add_action('wp_ajax_get_filtered_listings', array($this, 'get_filtered_listings'));
        add_action('wp_ajax_nopriv_get_filtered_listings', array($this, 'get_filtered_listings'));
        // Add booking handler
        add_action('wp_ajax_book_appointment', array($this, 'handle_booking'));
        add_action('wp_ajax_nopriv_book_appointment', array($this, 'handle_booking'));
        // Add the verification hook
        add_action('wp_ajax_verify_schedule_data', array($this, 'ajax_verify_schedule_data'));
        add_action('wp_ajax_nopriv_verify_schedule_data', array($this, 'ajax_verify_schedule_data'));
        // Add the event details
        add_action('wp_ajax_fetch_event_details', array($this, 'fetch_event_details'));
        add_action('wp_ajax_nopriv_fetch_event_details', array($this, 'fetch_event_details'));
        // Add available timeslots AJAX handler
        add_action('wp_ajax_sod_get_available_timeslots', array($this, 'handle_get_available_timeslots'));
        add_action('wp_ajax_nopriv_sod_get_available_timeslots', array($this, 'handle_get_available_timeslots'));
        // Testing nonce
        add_action('wp_ajax_test_nonce', function() {
            check_ajax_referer('sod_scheduler_nonce', 'nonce');
            wp_send_json_success(['message' => 'Nonce verified successfully!']);
        });
        add_action('wp_ajax_nopriv_test_nonce', function() {
            check_ajax_referer('sod_scheduler_nonce', 'nonce');
            wp_send_json_success(['message' => 'Nonce verified successfully for non-logged-in users!']);
        });
    }

    /**
     * Ensure assets are available
     */
    private function ensure_assets() {
        // Since our asset folders are shipped with the plugin, we assume they exist.
        $dirs = array(
            'assets',
            'assets/css',
            'assets/js'
        );

        foreach ($dirs as $dir) {
            $full_path = SOD_PLUGIN_PATH . $dir;
            if (!file_exists($full_path)) {
                error_log("Warning: Expected asset directory not found: " . $full_path);
            }
        }
    }
    
    /**
     * AJAX handler: Fetch events for the schedule calendar
     */
    public function fetch_events() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sod_scheduler_nonce')) {
            wp_send_json_error(['message' => 'Nonce verification failed']);
            exit;
        }

        try {
            $start_date = isset($_POST['start']) ? sanitize_text_field($_POST['start']) : date('Y-m-d');
            $end_date = isset($_POST['end']) ? sanitize_text_field($_POST['end']) : date('Y-m-d', strtotime('+1 month'));
            $service_filter = isset($_POST['service']) ? intval($_POST['service']) : 0;
            $staff_filter = isset($_POST['staff']) ? intval($_POST['staff']) : 0;
            $category_filter = isset($_POST['category']) ? intval($_POST['category']) : 0;

            error_log("Fetching events from $start_date to $end_date" . 
                      ($service_filter ? " for service $service_filter" : "") . 
                      ($staff_filter ? " with staff $staff_filter" : "") . 
                      ($category_filter ? " in category $category_filter" : ""));

            // Get raw availability data
            $availabilities = $this->fetch_availability($start_date, $end_date, $service_filter, $staff_filter, $category_filter);
            error_log("Raw availability slots found: " . count($availabilities));
            
            // Process recurring slots
            $processed_slots = $this->process_recurring_slots($availabilities, $start_date, $end_date);
            error_log("Processed slots for current date range: " . count($processed_slots));
            
            // Get existing bookings for this date range
            $bookings = $this->fetch_bookings($start_date, $end_date);
            error_log("Found " . count($bookings) . " existing bookings");
            
            // Format events for calendar
            $events = $this->format_events($processed_slots, $bookings);
            error_log("Formatted " . count($events) . " events for display");
            
            wp_send_json_success(['events' => $events]);
        } catch (Exception $e) {
            error_log('Error in fetch_events: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Failed to fetch events: ' . $e->getMessage()]);
        }
    }

    /**
     * Fetch availability data from database
     * Uses product_id exclusively for all lookups
     */
    private function fetch_availability($start_date, $end_date, $product_filter = 0, $staff_filter = 0, $category_filter = 0) {
        error_log("Fetching availability from database for $start_date to $end_date");

        try {
            // Base query using product_id
            $query = "
                SELECT 
                    sa.availability_id,
                    sa.staff_id,
                    sa.product_id,
                    sa.date,
                    sa.day_of_week,
                    sa.start_time,
                    sa.end_time,
                    sa.appointment_only,
                    sa.recurring_type,
                    sa.recurring_end_date,
                    sa.biweekly_pattern,
                    sa.skip_5th_week,
                    sa.monthly_occurrence,
                    sa.monthly_day,
                    sa.buffer_time,
                    st.user_id AS staff_user_id,
                    st.phone_number,
                    st.accepts_cash,
                    p.post_title AS product_name,
                    p.post_excerpt AS product_description,
                    p.ID as product_id,
                    u.display_name AS staff_name
                FROM {$this->wpdb->prefix}sod_staff_availability sa
                LEFT JOIN {$this->wpdb->prefix}sod_staff st ON sa.staff_id = st.staff_id
                LEFT JOIN {$this->wpdb->posts} p ON sa.product_id = p.ID
                LEFT JOIN {$this->wpdb->users} u ON st.user_id = u.ID
                WHERE 
                    (sa.date IS NOT NULL AND sa.date BETWEEN %s AND %s)
                    OR 
                    (sa.recurring_type IS NOT NULL AND sa.day_of_week IS NOT NULL 
                    AND (sa.recurring_end_date IS NULL OR sa.recurring_end_date >= %s))";

            $params = [$start_date, $end_date, $start_date];
            
            // Add product filter if provided
            if ($product_filter > 0) {
                $query .= " AND sa.product_id = %d";
                $params[] = $product_filter;
            }
            
            // Add staff filter if provided
            if ($staff_filter > 0) {
                $query .= " AND sa.staff_id = %d";
                $params[] = $staff_filter;
            }
            
            // Add category filter if provided
            if ($category_filter > 0) {
                $query .= " AND p.ID IN (
                    SELECT object_id FROM {$this->wpdb->term_relationships} tr
                    JOIN {$this->wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    WHERE tt.taxonomy = 'product_cat' AND tt.term_id = %d
                )";
                $params[] = $category_filter;
            }
            
            // Product status filter
            $query .= " AND p.post_type = 'product' AND p.post_status = 'publish'";
            
            // Prepare and execute query
            $query = $this->wpdb->prepare($query, $params);
            $results = $this->wpdb->get_results($query);

            error_log("Found " . count($results) . " availability slots");
            
            return $results;
        } catch (Exception $e) {
            error_log("Error in fetch_availability: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Fetch existing bookings for this date range
     */
    private function fetch_bookings($start_date, $end_date) {
        $bookings_table = $this->wpdb->prefix . 'sod_bookings';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '{$bookings_table}'") !== $bookings_table) {
            $bookings_table = 'wp_3be9vb_sod_bookings'; // Fallback
        }
        
        $query = $this->wpdb->prepare("
            SELECT 
                b.booking_id,
                b.product_id,
                b.staff_id,
                CONCAT(b.date, ' ', b.start_time) as start_time,
                CONCAT(b.date, ' ', b.end_time) as end_time,
                b.duration
            FROM {$bookings_table} b
            WHERE CONCAT(b.date, ' ', b.start_time) >= %s
              AND CONCAT(b.date, ' ', b.end_time) <= %s
              AND b.status NOT IN ('cancelled', 'no_show')",
            $start_date,
            $end_date
        );

        $results = $this->wpdb->get_results($query);
        return is_array($results) ? $results : []; // Return empty array if no bookings found
    }
    
    /**
     * Check if a time slot is already booked
     */
    private function is_time_slot_booked($staff_id, $start_time, $duration) {
        $end_time = date('Y-m-d H:i:s', strtotime($start_time . " +$duration minutes"));
        
        $bookings_table = $this->wpdb->prefix . 'sod_bookings';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '{$bookings_table}'") !== $bookings_table) {
            $bookings_table = 'wp_3be9vb_sod_bookings'; // Fallback
        }

        $query = $this->wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$bookings_table} 
            WHERE staff_id = %d 
            AND status NOT IN ('cancelled', 'no_show')
            AND (
                (start_time <= %s AND DATE_ADD(start_time, INTERVAL duration MINUTE) > %s)
                OR (start_time < %s AND DATE_ADD(start_time, INTERVAL duration MINUTE) >= %s)
                OR (start_time >= %s AND DATE_ADD(start_time, INTERVAL duration MINUTE) <= %s)
            )",
            $staff_id,
            $start_time,
            $start_time,
            $end_time,
            $end_time,
            $start_time,
            $end_time
        );

        return (bool) $this->wpdb->get_var($query);
    }
    
    /**
     * Process recurring slots to generate specific dates
     */
    private function process_recurring_slots($slots, $start_date, $end_date) {
        $processed_slots = [];
        $start_dt = new DateTime($start_date);
        $end_dt = new DateTime($end_date);
        
        foreach ($slots as $slot) {
            // Skip slots where recurring has ended
            if (!empty($slot->recurring_end_date) && new DateTime($slot->recurring_end_date) < $start_dt) {
                continue;
            }
            
            // Process one-time slots
            if (!empty($slot->date)) {
                $slot_date = new DateTime($slot->date);
                if ($slot_date >= $start_dt && $slot_date <= $end_dt) {
                    $processed_slots[] = $slot;
                }
                continue;
            }
            
            // Process recurring slots
            if (!empty($slot->recurring_type) && !empty($slot->day_of_week)) {
                // Convert day name to number (1=Monday, 7=Sunday)
                $days_map = [
                    'Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 
                    'Friday' => 5, 'Saturday' => 6, 'Sunday' => 7
                ];
                
                $day_num = isset($days_map[$slot->day_of_week]) ? $days_map[$slot->day_of_week] : 0;
                if (!$day_num) continue;
                
                // Find all matching days in the current range
                $current = clone $start_dt;
                while ($current <= $end_dt) {
                    // If this day of the week matches
                    if ((int)$current->format('N') === $day_num) {
                        // Check biweekly pattern if applicable
                        if ($slot->recurring_type === 'biweekly' && !empty($slot->biweekly_pattern)) {
                            $week_number = ceil($current->format('j') / 7);
                            if (($week_number % 2) !== (int)$slot->biweekly_pattern) {
                                $current->modify('+1 day');
                                continue;
                            }
                        }
                        
                        // Skip 5th week occurrences if configured
                        if (!empty($slot->skip_5th_week)) {
                            $week_number = ceil($current->format('j') / 7);
                            if ($week_number >= 5) {
                                $current->modify('+1 day');
                                continue;
                            }
                        }
                        
                        // Create a new slot for this specific date
                        $new_slot = clone $slot;
                        $new_slot->date = $current->format('Y-m-d');
                        $processed_slots[] = $new_slot;
                    }
                    $current->modify('+1 day');
                }
            }
        }
        
        return $processed_slots;
    }
    
    /**
     * Format events for calendar display
     * Uses product_id for all operations
     */
    private function format_events($processed_slots, $bookings = []) {
        error_log("Formatting " . count($processed_slots) . " slots for calendar display");

        $events = [];
        $formatted_count = 0;
        $skipped_count = 0;

        foreach ($processed_slots as $slot) {
            if (empty($slot->date) || empty($slot->start_time) || empty($slot->end_time)) {
                error_log("⚠️ Skipping slot due to missing date/time data: " . json_encode($slot));
                $skipped_count++;
                continue;
            }

            if (empty($slot->product_name) || empty($slot->staff_name)) {
                error_log("⚠️ Skipping slot due to missing product/staff name: " . json_encode($slot));
                $skipped_count++;
                continue;
            }

            $start_datetime = new DateTime($slot->date . ' ' . $slot->start_time);
            $end_datetime_limit = new DateTime($slot->date . ' ' . $slot->end_time);

            // Fetch all durations for this product
            $durations = $this->get_product_durations($slot->product_id);
            if (empty($durations)) {
                error_log("No durations found for product {$slot->product_id}, using default 60 min");
                $durations = [60];
            }

            // Generate slots for each duration
            foreach ($durations as $duration) {
                $current_start = clone $start_datetime;

                while ($current_start < $end_datetime_limit) {
                    $current_end = clone $current_start;
                    $current_end->modify("+{$duration} minutes");

                    if ($current_end > $end_datetime_limit) {
                        break; // Stop if the slot exceeds the availability window
                    }

                    $start_str = $current_start->format('Y-m-d H:i:s');
                    $end_str = $current_end->format('Y-m-d H:i:s');

                    if ($this->is_time_slot_booked($slot->staff_id, $start_str, $duration)) {
                        error_log("Slot already booked: {$start_str} - {$end_str}, staff {$slot->staff_id}");
                        $current_start->modify("+{$duration} minutes");
                        continue;
                    }

                    // Get attribute and pricing info
                    $price = $this->get_price_for_duration($slot->product_id, $duration);
                    $product_attributes = $this->get_product_attributes_for_display($slot->product_id, $duration);

                    $events[] = [
                        'id' => $slot->availability_id . '_' . $duration . '_' . $current_start->getTimestamp(),
                        'title' => sprintf('%s with %s (%d min)', $slot->product_name, $slot->staff_name, $duration),
                        'start' => $start_str,
                        'end' => $end_str,
                        'extendedProps' => [
                            'staffId' => $slot->staff_id,
                            'staffName' => $slot->staff_name,
                            'productId' => $slot->product_id,
                            'productName' => $slot->product_name,
                            'productDescription' => $slot->product_description,
                            'price' => $price,
                            'attributes' => $product_attributes,
                            'appointmentOnly' => isset($slot->appointment_only) ? (bool)$slot->appointment_only : false,
                            'acceptsCash' => isset($slot->accepts_cash) ? (bool)$slot->accepts_cash : false,
                            'staffPhone' => isset($slot->phone_number) ? $slot->phone_number : '',
                            'staffUserId' => isset($slot->staff_user_id) ? $slot->staff_user_id : 0,
                            'duration' => $duration
                        ]
                    ];
                    $formatted_count++;
                    $current_start->modify("+{$duration} minutes");
                }
            }
        }

        error_log("Formatted $formatted_count events for calendar (skipped $skipped_count)");
        return $events;
    }
    
    /**
     * Get attributes for a product organized for display
     */
    private function get_product_attributes_for_display($product_id, $default_duration = 60) {
        $raw_attributes = $this->get_product_attributes($product_id);
        
        $organized = [
            'duration' => [],
            'passes' => [],
            'package' => [],
            'custom' => []
        ];
        
        foreach ($raw_attributes as $attr) {
            if (isset($organized[$attr->attribute_type])) {
                $organized[$attr->attribute_type][] = [
                    'value' => $attr->value,
                    'price' => $attr->price,
                    'product_id' => $attr->product_id ?? $product_id,
                    'variation_id' => $attr->variation_id ?? 0
                ];
            } else {
                $organized['custom'][] = [
                    'type' => $attr->attribute_type,
                    'value' => $attr->value,
                    'price' => $attr->price,
                    'product_id' => $attr->product_id ?? $product_id,
                    'variation_id' => $attr->variation_id ?? 0
                ];
            }
        }
        
        // If no durations found, add the default
        if (empty($organized['duration'])) {
            $organized['duration'][] = [
                'value' => $default_duration . ' min',
                'price' => 0,
                'product_id' => $product_id,
                'variation_id' => 0
            ];
        }
        
        return $organized;
    }
    
    /**
     * Get product durations from the attributes table
     * Uses product_id for lookups
     */
    private function get_product_durations($product_id) {
        global $wpdb;
        
        // Try standard table name first
        $attributes_table = $wpdb->prefix . 'sod_service_attributes';
        
        // Check if the table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$attributes_table}'") !== $attributes_table) {
            // Fall back to hardcoded name
            $attributes_table = 'wp_3be9vb_sod_service_attributes';
            
            // If even that doesn't exist, use default
            if ($wpdb->get_var("SHOW TABLES LIKE '{$attributes_table}'") !== $attributes_table) {
                error_log("No attributes table found for product_id: $product_id. Using default duration.");
                return [60]; // Default 60 minute duration
            }
        }
        
        // Query using product_id
        $attributes = $wpdb->get_results($wpdb->prepare(
            "SELECT value FROM {$attributes_table} WHERE product_id = %d AND attribute_type = 'duration'",
            $product_id
        ));
        
        // If no durations found, use default
        if (empty($attributes)) {
            error_log("No duration attributes found for product_id: $product_id. Using default duration.");
            return [60]; // Default 60 minute duration
        }
        
        // Extract duration values
        $durations = [];
        foreach ($attributes as $attr) {
            $duration = (int) preg_replace('/[^0-9]/', '', $attr->value);
            if ($duration > 0) {
                $durations[] = $duration;
            }
        }
        
        // If no valid durations parsed, use default
        if (empty($durations)) {
            error_log("Failed to parse any valid durations for product_id: $product_id. Using default.");
            return [60]; // Default 60 minute duration
        }
        
        return array_unique($durations);
    }

    /**
     * Get price for a specific duration from the attributes table
     * Uses product_id for lookups
     */
    private function get_price_for_duration($product_id, $duration) {
        global $wpdb;

        // Try standard table name first
        $attributes_table = $wpdb->prefix . 'sod_service_attributes';

        // Check if the table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$attributes_table}'") !== $attributes_table) {
            // Fall back to hardcoded name
            $attributes_table = 'wp_3be9vb_sod_service_attributes';

            // If even that doesn't exist, use default
            if ($wpdb->get_var("SHOW TABLES LIKE '{$attributes_table}'") !== $attributes_table) {
                error_log("No attributes table found for product_id: $product_id. Using default price.");
                return '0.00';
            }
        }

        // Try to find the price for this duration
        try {
            // Query using product_id
            $attributes = $wpdb->get_results($wpdb->prepare(
                "SELECT value, price FROM {$attributes_table} 
                 WHERE product_id = %d AND attribute_type = 'duration'",
                $product_id
            ));

            // Debug
            error_log("Found " . count($attributes) . " duration attributes for product $product_id");

            // Manually find the one with matching duration
            foreach ($attributes as $attr) {
                $attr_duration = (int) preg_replace('/[^0-9]/', '', $attr->value);
                error_log("Checking duration: {$attr->value} ($attr_duration) against $duration");

                if ($attr_duration == $duration) {
                    error_log("Found matching duration: {$attr->value} with price: {$attr->price}");
                    return floatval($attr->price);
                }
            }

            // If no exact match found, use first duration's price as fallback
            if (!empty($attributes)) {
                error_log("No exact match for duration $duration, using first attribute's price: {$attributes[0]->price}");
                return floatval($attributes[0]->price);
            }

        } catch (Exception $e) {
            error_log("Error getting price for product $product_id, duration $duration: " . $e->getMessage());
        }

        // If all else fails, try to get product price from WooCommerce
        try {
            $product = wc_get_product($product_id);
            if ($product) {
                return floatval($product->get_price());
            }
        } catch (Exception $e) {
            error_log("Error getting WooCommerce product price: " . $e->getMessage());
        }

        error_log("No price found for product $product_id, duration $duration. Using 0.00");
        return '0.00';
    }

    /**
     * Get product attributes from the attributes table
     * Uses product_id for lookups
     */
    private function get_product_attributes($product_id) {
        global $wpdb;
        
        // Try standard table name first
        $attributes_table = $wpdb->prefix . 'sod_service_attributes';
        
        // Check if the table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$attributes_table}'") !== $attributes_table) {
            // Fall back to hardcoded name
            $attributes_table = 'wp_3be9vb_sod_service_attributes';
            
            // If even that doesn't exist, return empty array
            if ($wpdb->get_var("SHOW TABLES LIKE '{$attributes_table}'") !== $attributes_table) {
                return [];
            }
        }
        
        // Query using product_id
        $attributes = $wpdb->get_results($wpdb->prepare(
            "SELECT attribute_type, value, price, product_id, variation_id
             FROM {$attributes_table} 
             WHERE product_id = %d
             ORDER BY attribute_type ASC, price ASC",
            $product_id
        ));
        
        return $attributes;
    }

    /**
     * AJAX endpoint for data verification
     */
    public function ajax_verify_schedule_data() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sod_scheduler_nonce')) {
            wp_send_json_error(array('message' => 'Nonce verification failed'));
            exit;
        }

        try {
            global $wpdb;

            // Check staff posts
            $staff = $wpdb->get_results("
                SELECT ID, post_title 
                FROM {$wpdb->posts} 
                WHERE post_type = 'sod_staff'
            ");
            error_log('Staff count: ' . count($staff));

            // Check products
            $products = $wpdb->get_results("
                SELECT ID, post_title 
                FROM {$wpdb->posts} 
                WHERE post_type = 'product'
                AND post_status = 'publish'
            ");
            error_log('Products count: ' . count($products));

            // Check availability
            $availability = $wpdb->get_results("
                SELECT * 
                FROM {$wpdb->prefix}sod_staff_availability 
                WHERE date >= CURDATE()
                OR (recurring_type IS NOT NULL AND recurring_end_date >= CURDATE())
            ");
            error_log('Future availability count: ' . count($availability));

            // Check table structure
            $this->verify_database_structure();

            // Send response
            wp_send_json_success(array(
                'staff_count' => count($staff),
                'products_count' => count($products),
                'availability_count' => count($availability),
                'message' => 'Verification completed successfully'
            ));

        } catch (Exception $e) {
            error_log('Error during verification: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Verification failed: ' . $e->getMessage()));
        }
    }

    /**
     * Verify database structure
     */
    private function verify_database_structure() {
        global $wpdb;
        
        // Check availability table structure
        $availability_table = $wpdb->prefix . 'sod_staff_availability';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$availability_table}'") === $availability_table) {
            $product_id_column = $wpdb->get_results("SHOW COLUMNS FROM {$availability_table} LIKE 'product_id'");
            $service_id_column = $wpdb->get_results("SHOW COLUMNS FROM {$availability_table} LIKE 'service_id'");
            
            if (empty($product_id_column)) {
                error_log("CRITICAL: product_id column missing from availability table!");
                
                // Add product_id column if service_id exists but product_id doesn't
                if (!empty($service_id_column)) {
                    $wpdb->query("ALTER TABLE {$availability_table} ADD COLUMN product_id bigint(20) UNSIGNED NULL AFTER staff_id");
                    $wpdb->query("UPDATE {$availability_table} SET product_id = service_id WHERE product_id IS NULL");
                    error_log("Added product_id column and copied values from service_id");
                }
            }
        }
        
        // Check attributes table structure
        $attributes_table = $wpdb->prefix . 'sod_service_attributes';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$attributes_table}'") === $attributes_table) {
            $product_id_column = $wpdb->get_results("SHOW COLUMNS FROM {$attributes_table} LIKE 'product_id'");
            $service_id_column = $wpdb->get_results("SHOW COLUMNS FROM {$attributes_table} LIKE 'service_id'");
            
            if (empty($product_id_column)) {
                error_log("CRITICAL: product_id column missing from attributes table!");
                
                // Add product_id column if service_id exists but product_id doesn't
                if (!empty($service_id_column)) {
                    $wpdb->query("ALTER TABLE {$attributes_table} ADD COLUMN product_id bigint(20) UNSIGNED NULL AFTER service_id");
                    $wpdb->query("UPDATE {$attributes_table} SET product_id = service_id WHERE product_id IS NULL");
                    error_log("Added product_id column and copied values from service_id");
                }
            }
        }
        
        // Check bookings table structure
        $bookings_table = $wpdb->prefix . 'sod_bookings';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$bookings_table}'") === $bookings_table) {
            $product_id_column = $wpdb->get_results("SHOW COLUMNS FROM {$bookings_table} LIKE 'product_id'");
            $service_id_column = $wpdb->get_results("SHOW COLUMNS FROM {$bookings_table} LIKE 'service_id'");
            
            if (empty($product_id_column)) {
                error_log("CRITICAL: product_id column missing from bookings table!");
                
                // Add product_id column if service_id exists but product_id doesn't
                if (!empty($service_id_column)) {
                    $wpdb->query("ALTER TABLE {$bookings_table} ADD COLUMN product_id bigint(20) UNSIGNED NULL AFTER staff_id");
                    $wpdb->query("UPDATE {$bookings_table} SET product_id = service_id WHERE product_id IS NULL");
                    error_log("Added product_id column and copied values from service_id");
                }
            }
        }
    }

    /**
     * AJAX handler for getting filtered listings
     */
    public function get_filtered_listings() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sod_scheduler_nonce')) {
            wp_send_json_error(array('message' => 'Nonce verification failed'));
            exit;
        }

        try {
            $filters = isset($_POST['filters']) ? $this->validate_filters($_POST['filters']) : array();
            
            // Updated query to get listings with products from WooCommerce
            $query = "SELECT 
                        s.ID as staff_id,
                        s.post_title as staff_name,
                        p.ID as product_id,
                        p.post_title as product_name,
                        p.post_excerpt as product_excerpt,
                        sa.product_id as product_reference,
                        sa.duration,
                        pm.meta_value as price
                    FROM {$this->wpdb->prefix}sod_staff_availability sa
                    JOIN {$this->wpdb->posts} s ON s.ID = sa.staff_id
                    JOIN {$this->wpdb->posts} p ON sa.product_id = p.ID
                    LEFT JOIN {$this->wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_price'
                    WHERE s.post_type = 'sod_staff'
                    AND p.post_type = 'product'
                    AND s.post_status = 'publish'
                    AND p.post_status = 'publish'";

            if (!empty($filters['staff'])) {
                $staff_ids = implode(',', array_map('intval', $filters['staff']));
                $query .= " AND sa.staff_id IN ($staff_ids)";
            }

            if (!empty($filters['product'])) {
                $product_ids = implode(',', array_map('intval', $filters['product']));
                $query .= " AND sa.product_id IN ($product_ids)";
            }

            if (!empty($filters['category'])) {
                $category_ids = implode(',', array_map('intval', $filters['category']));
                $query .= " AND p.ID IN (
                    SELECT object_id FROM {$this->wpdb->term_relationships} tr
                    JOIN {$this->wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    WHERE tt.taxonomy = 'product_cat' AND tt.term_id IN ($category_ids)
                )";
            }

            $query .= " GROUP BY s.ID, p.ID, sa.duration ORDER BY p.post_title ASC";

            $listings = $this->wpdb->get_results($query);
            
            ob_start();
            $this->render_listings($listings);
            $html = ob_get_clean();

            wp_send_json_success(array('html' => $html));

        } catch (Exception $e) {
            error_log('Error loading listings: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Failed to load listings'));
        }
    }
    
    /**
     * Render listings
     */
    private function render_listings($listings) {
        if (empty($listings)) {
            echo '<p>' . __('No listings found.', 'spark-of-divine-scheduler') . '</p>';
            return;
        }

        foreach ($listings as $listing) {
            ?>
            <div class="service-listing">
                <h3><?php echo esc_html($listing->product_name); ?></h3>
                <p class="staff-name"><?php echo esc_html($listing->staff_name); ?></p>
                <p class="duration"><?php printf(__('Duration: %d minutes', 'spark-of-divine-scheduler'), $listing->duration); ?></p>
                <p class="price"><?php printf(__('Price: $%.2f', 'spark-of-divine-scheduler'), $listing->price); ?></p>
                <button class="book-now" 
                        data-staff="<?php echo esc_attr($listing->staff_id); ?>"
                        data-product="<?php echo esc_attr($listing->product_id); ?>">
                    <?php _e('Book Now', 'spark-of-divine-scheduler'); ?>
                </button>
            </div>
            <?php
        }
    }

    /**
     * Validate filters
     */
    private function validate_filters($filters) {
        error_log('Validating filters: ' . print_r($filters, true));
        
        $valid_filters = array(
            'staff' => array(),
            'product' => array(),
            'category' => array()
        );

        if (!is_array($filters)) {
            error_log('Filters is not an array');
            return $valid_filters;
        }

        foreach ($filters as $type => $ids) {
            if (isset($valid_filters[$type])) {
                $valid_filters[$type] = array_map('intval', (array)$ids);
                error_log("Validated {$type} filters: " . print_r($valid_filters[$type], true));
            }
        }

        error_log('Final validated filters: ' . print_r($valid_filters, true));
        return $valid_filters;
    }
    
    /**
     * Handle booking creation via AJAX
     */
    public function handle_booking() {
        check_ajax_referer('sod_schedule_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array(
                'message' => __('Please log in to make a booking.', 'spark-of-divine-scheduler')
            ));
            return;
        }

        // Use product_id instead of service_id
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;
        $start_time = isset($_POST['start_time']) ? sanitize_text_field($_POST['start_time']) : '';
        $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 0;

        if (!$product_id || !$staff_id || !$start_time || !$duration) {
            wp_send_json_error(array(
                'message' => __('Missing required booking information.', 'spark-of-divine-scheduler')
            ));
            return;
        }

        // Create the booking using the booking handler
        $booking_handler = SOD_Booking_Handler::getInstance();
        $result = $booking_handler->create_booking([
            'product_id' => $product_id,
            'staff_id' => $staff_id,
            'start_time' => $start_time,
            'duration' => $duration,
            'user_id' => get_current_user_id()
        ]);

        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
            return;
        }

        wp_send_json_success(array(
            'message' => __('Booking created successfully!', 'spark-of-divine-scheduler'),
            'booking_id' => $result['booking_id']
        ));
    }
    
    /**
     * AJAX handler to get available timeslots
     */
    public function handle_get_available_timeslots() {
        // Check nonce for security
        check_ajax_referer('sod_booking_nonce', 'nonce');

        try {
            $staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;
            $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
            $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
            $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 60;

            if (!$staff_id || !$date || !$product_id) {
                throw new Exception(__('Missing required parameters', 'spark-of-divine-scheduler'));
            }

            // Log what we're doing for debugging
            error_log("Getting available timeslots for product_id: $product_id, staff_id: $staff_id, date: $date, duration: $duration");
            
            // Get available timeslots
            $timeslots = $this->get_available_timeslots($product_id, $staff_id, $date, $duration);

            wp_send_json_success(array('timeslots' => $timeslots));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Get available timeslots for a given product, staff, and date
     */
    private function get_available_timeslots($product_id, $staff_id, $date, $duration = 60) {
        $timeslots = [];

        // Get availability for this date
        $availability = $this->get_staff_availability($staff_id, $product_id, $date);
        if (empty($availability)) {
            error_log("No availability found for staff $staff_id with product $product_id on date $date");
            return $timeslots;
        }

        // Use start/end time from availability
        $start_time = new DateTime("$date {$availability->start_time}");
        $end_time = new DateTime("$date {$availability->end_time}");
        
        // Use custom interval (15 minutes) for time slots
        $interval = new DateInterval('PT15M');
        $current = clone $start_time;

        // Get existing bookings for this date and staff
        $existing_bookings = $this->get_bookings_for_date($staff_id, $date);

        // Generate available time slots
        while ($current < $end_time) {
            $slot_end = clone $current;
            $slot_end->modify("+{$duration} minutes");
            
            // Skip if the slot would end after the availability end time
            if ($slot_end > $end_time) {
                break;
            }
            
            // Check if this slot overlaps with any existing bookings
            $is_available = true;
            foreach ($existing_bookings as $booking) {
                $booking_start = new DateTime("$date {$booking->start_time}");
                $booking_end = new DateTime("$date {$booking->end_time}");
                
                // Check for overlap
                if (($current >= $booking_start && $current < $booking_end) || 
                    ($slot_end > $booking_start && $slot_end <= $booking_end) ||
                    ($current <= $booking_start && $slot_end >= $booking_end)) {
                    $is_available = false;
                    break;
                }
            }
            
            if ($is_available) {
                $time_value = $current->format('H:i:s');
                $formatted_time = date_i18n(get_option('time_format'), $current->getTimestamp());
                
                $timeslots[] = [
                    'time' => $time_value,
                    'formatted' => $formatted_time
                ];
            }
            
            // Move to next slot
            $current->add($interval);
        }

        return $timeslots;
    }

    /**
     * Get staff availability for a specific date
     */
    private function get_staff_availability($staff_id, $product_id, $date) {
        // First try to get a one-time availability for this exact date
        $one_time = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}sod_staff_availability 
             WHERE staff_id = %d AND product_id = %d AND date = %s",
            $staff_id, $product_id, $date
        ));
        
        if ($one_time) {
            return $one_time;
        }
        
        // If no one-time availability, check for recurring
        $day_of_week = date('l', strtotime($date)); // Get day name (Monday, Tuesday, etc.)
        
        $recurring = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}sod_staff_availability 
             WHERE staff_id = %d AND product_id = %d AND day_of_week = %s
             AND (recurring_end_date IS NULL OR recurring_end_date >= %s)",
            $staff_id, $product_id, $day_of_week, $date
        ));
        
        if ($recurring) {
            return $recurring;
        }
        
        // If no product-specific availability, check for general staff availability
        $default = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}sod_staff_availability 
             WHERE staff_id = %d AND product_id = 0 AND day_of_week = %s
             AND (recurring_end_date IS NULL OR recurring_end_date >= %s)",
            $staff_id, $day_of_week, $date
        ));
        
        if ($default) {
            return $default;
        }
        
        // If still no availability, create a default based on staff settings
        $default_start = get_post_meta($staff_id, 'sod_default_start_time', true) ?: '09:00:00';
        $default_end = get_post_meta($staff_id, 'sod_default_end_time', true) ?: '17:00:00';
        
        // Check day-specific overrides
        $day_lower = strtolower($day_of_week);
        $day_start = get_post_meta($staff_id, "sod_start_time_{$day_lower}", true);
        $day_end = get_post_meta($staff_id, "sod_end_time_{$day_lower}", true);
        
        $default_obj = new stdClass();
        $default_obj->start_time = $day_start ?: $default_start;
        $default_obj->end_time = $day_end ?: $default_end;
        
        return $default_obj;
    }

    /**
     * Get bookings for a specific date and staff
     */
    private function get_bookings_for_date($staff_id, $date) {
        $bookings_table = $this->wpdb->prefix . 'sod_bookings';
        if ($this->wpdb->get_var("SHOW TABLES LIKE '{$bookings_table}'") !== $bookings_table) {
            $bookings_table = 'wp_3be9vb_sod_bookings'; // Fallback
        }
        
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$bookings_table} 
             WHERE staff_id = %d AND date = %s 
             AND status NOT IN ('cancelled', 'no_show')",
            $staff_id, $date
        ));
    }
    
    /**
     * Fetch event details via AJAX
     */
    public function fetch_event_details() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sod_scheduler_nonce')) {
            wp_send_json_error(['message' => 'Nonce verification failed']);
            exit;
        }
        
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;
        
        if (!$product_id || !$staff_id) {
            wp_send_json_error(['message' => 'Missing required parameters']);
            exit;
        }
        
        try {
            // Fetch product details
            $product = get_post($product_id);
            
            if (!$product || $product->post_type !== 'product') {
                wp_send_json_error(['message' => 'Product not found']);
                exit;
            }
            
            // Get product attributes and price
            $product_attributes = $this->get_product_attributes($product_id);
            
            // Organize attributes by type
            $organized_attributes = [
                'duration' => [],
                'passes' => [],
                'package' => [],
                'custom' => []
            ];
            
            foreach ($product_attributes as $attr) {
                if (isset($organized_attributes[$attr->attribute_type])) {
                    $organized_attributes[$attr->attribute_type][] = [
                        'type' => $attr->attribute_type,
                        'value' => $attr->value,
                        'price' => floatval($attr->price),
                        'product_id' => $attr->product_id ?? $product_id,
                        'variation_id' => $attr->variation_id ?? 0
                    ];
                } else {
                    $organized_attributes['custom'][] = [
                        'type' => $attr->attribute_type,
                        'value' => $attr->value,
                        'price' => floatval($attr->price),
                        'product_id' => $attr->product_id ?? $product_id,
                        'variation_id' => $attr->variation_id ?? 0
                    ];
                }
            }
            
            // Get product categories
            $categories = [];
            $terms = get_the_terms($product->ID, 'product_cat');
            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $categories[] = [
                        'id' => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug
                    ];
                }
            }
            
            $product_info = [
                'id' => $product->ID,
                'name' => $product->post_title,
                'description' => $product->post_content,
                'short_description' => $product->post_excerpt,
                'price' => get_post_meta($product->ID, '_price', true),
                'categories' => $categories,
                'attributes' => $organized_attributes
            ];
            
            // Get staff info
            $staff_post = get_post($staff_id);
            $staff_info = [
                'id' => $staff_id,
                'name' => $staff_post ? $staff_post->post_title : 'Unknown',
                'bio' => $staff_post ? $staff_post->post_content : '',
                'phone' => get_post_meta($staff_id, 'sod_staff_phone', true),
                'email' => get_post_meta($staff_id, 'sod_staff_email', true),
                'acceptsCash' => (bool)get_post_meta($staff_id, 'sod_staff_accepts_cash', true)
            ];
            
            wp_send_json_success([
                'product' => $product_info,
                'staff' => $staff_info
            ]);
            
        } catch (Exception $e) {
            error_log('Error fetching event details: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Failed to fetch event details: ' . $e->getMessage()]);
        }
    }

    /**
     * Use this to run verification at any time
     */
    public static function run_verification() {
        $instance = self::getInstance();
        return $instance->verify_database_structure();
    }
}

// Initialize the Schedule Handler
SOD_Schedule_Handler::getInstance();