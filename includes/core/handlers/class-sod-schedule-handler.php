<?php
/**
 * SOD_Schedule_Handler Class (Corrected)
 * 
 * Handles all schedule-related functionality for WooCommerce products.
 * No legacy service code, no inline JS/CSS.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SOD_Schedule_Handler {
    private static $instance = null;
    private $wpdb;
    private $plugin_path;
    private $plugin_url;

    public static function getInstance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->plugin_path = SOD_PLUGIN_PATH;
        $this->plugin_url = SOD_PLUGIN_URL;

        $this->init_hooks();
        $this->verify_database_structure();
        
        error_log("SOD Schedule Handler initialized - Product-only version");
    }

    private function init_hooks() {
        // Essential AJAX handlers for the template
        add_action('wp_ajax_sod_get_available_timeslots', array($this, 'handle_get_available_timeslots'));
        add_action('wp_ajax_nopriv_sod_get_available_timeslots', array($this, 'handle_get_available_timeslots'));
        
        // Also add alternative action names that might be used by the JS
        add_action('wp_ajax_get_available_timeslots', array($this, 'handle_get_available_timeslots'));
        add_action('wp_ajax_nopriv_get_available_timeslots', array($this, 'handle_get_available_timeslots'));
        
        // Add the submit booking handler that the template uses
        add_action('wp_ajax_sod_submit_booking', array($this, 'handle_submit_booking'));
        add_action('wp_ajax_nopriv_sod_submit_booking', array($this, 'handle_submit_booking'));
        
        // Add schedule AJAX handler
        add_action('wp_ajax_sod_load_schedule_ajax', array($this, 'handle_schedule_ajax'));
        add_action('wp_ajax_nopriv_sod_load_schedule_ajax', array($this, 'handle_schedule_ajax'));
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Handle AJAX schedule loading
     */
    public function handle_schedule_ajax() {
        // Verify this is an AJAX request
        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            wp_die('Invalid request');
        }

        try {
            // Get filter handler
            $filter_handler = $this->get_filter_handler();

            if (!$filter_handler) {
                wp_send_json_error(['message' => 'Filter handler not available']);
                return;
            }

            // Parse current filters from request
            $filter_handler->parse_filters();
            $filters = $filter_handler->get_filters();

            // Start output buffering to capture the schedule HTML
            ob_start();

            // Set globals for the template
            $GLOBALS['sod_customer_view'] = true;
            $GLOBALS['sod_schedule_view'] = $filters['view'];
            $GLOBALS['sod_schedule_date'] = $filters['date'];
            $GLOBALS['sod_service_filter'] = $filters['product'];
            $GLOBALS['sod_staff_filter'] = $filters['staff'];
            $GLOBALS['sod_category_filter'] = $filters['category'];

            // Load the schedule template
            $template = get_stylesheet_directory() . '/schedule-template.php';
            if (file_exists($template)) {
                include $template;
            } else {
                $plugin_template = SOD_PLUGIN_PATH . 'templates/schedule-template.php';
                if (file_exists($plugin_template)) {
                    include $plugin_template;
                } else {
                    throw new Exception('Schedule template not found');
                }
            }

            // Get the generated HTML
            $html = ob_get_clean();

            // Extract just the content inside the schedule container
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

            $schedule_container = $dom->getElementsByTagName('*');
            $content = '';

            foreach ($schedule_container as $element) {
                if ($element->getAttribute('class') && strpos($element->getAttribute('class'), 'sod-schedule-container') !== false) {
                    $content = $dom->saveHTML($element);
                    break;
                }
            }

            // If we couldn't extract the container, use the full HTML
            if (empty($content)) {
                $content = $html;
            }

            // Return success response
            wp_send_json_success([
                'html' => $content,
                'filters' => $filters,
                'message' => 'Schedule loaded successfully'
            ]);

        } catch (Exception $e) {
            wp_send_json_error([
                'message' => 'Error loading schedule: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get filter handler instance
     */
    private function get_filter_handler() {
        global $sod_filter_handler;
        
        if (isset($sod_filter_handler)) {
            return $sod_filter_handler;
        }
        
        // Try to get from plugin components
        if (class_exists('SOD_Plugin_Initializer')) {
            $plugin = SOD_Plugin_Initializer::get_instance();
            if (isset($plugin->components['filter_handler'])) {
                return $plugin->components['filter_handler'];
            }
        }
        
        // Try direct instantiation if class exists
        if (class_exists('SOD_Schedule_Filter_Handler')) {
            return SOD_Schedule_Filter_Handler::get_instance();
        }
        
        return null;
    }

    /**
     * Enqueue external CSS and JS files - CONSOLIDATED VERSION
     */
    public function enqueue_assets() {
        // Only load on pages with schedule
        if (!$this->should_load_assets()) {
            return;
        }

        // CSS files
        wp_enqueue_style('sod-schedule-style', $this->plugin_url . 'assets/css/sod-schedule-style.css', array(), filemtime($this->plugin_path . 'assets/css/sod-schedule-style.css'));
        wp_enqueue_style('sod-filter', $this->plugin_url . 'assets/css/sod-filter.css', array(), filemtime($this->plugin_path . 'assets/css/sod-filter.css'));
        wp_enqueue_style('sod-booking-form', $this->plugin_url . 'assets/css/sod-booking-form.css', array(), filemtime($this->plugin_path . 'assets/css/sod-booking-form.css'));
        wp_enqueue_style('sod-ui', $this->plugin_url . 'assets/css/sod-ui.css', array(), filemtime($this->plugin_path . 'assets/css/sod-ui.css'));
        
        // jQuery UI
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui-style', '//code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css', array(), '1.13.2');

        // JS files
        wp_enqueue_script('sod-schedule', $this->plugin_url . 'assets/js/sod-schedule.js', array('jquery', 'jquery-ui-datepicker'), filemtime($this->plugin_path . 'assets/js/sod-schedule.js'), true);
        wp_enqueue_script('sod-booking-form', $this->plugin_url . 'assets/js/sod-booking-form.js', array('jquery'), filemtime($this->plugin_path . 'assets/js/sod-booking-form.js'), true);
        wp_enqueue_script('sod-filter-fix', $this->plugin_url . 'assets/js/sod-filter-fix.js', array('jquery'), filemtime($this->plugin_path . 'assets/js/sod-filter-fix.js'), true);

        // Localize scripts
        wp_localize_script('sod-schedule', 'sodSchedule', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sod_booking_nonce'),
            'filter_nonce' => wp_create_nonce('sod_filter_nonce'),
            'base_url' => home_url('/'),
            'currentFilters' => $this->get_current_filters(),
            'strings' => array(
                'select_duration' => __('Select duration', 'spark-of-divine-scheduler'),
                'select_time' => __('Select a time', 'spark-of-divine-scheduler'),
                'loading' => __('Loading...', 'spark-of-divine-scheduler'),
                'error' => __('An error occurred', 'spark-of-divine-scheduler'),
                'booking_success' => __('Booking created successfully!', 'spark-of-divine-scheduler'),
                'booking_error' => __('Failed to create booking. Please try again.', 'spark-of-divine-scheduler')
            )
        ));
        
        wp_localize_script('sod-booking-form', 'sod_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sod-booking-nonce'),
            'plugin_url' => $this->plugin_url
        ));
    }

    /**
     * Get current filters for JavaScript
     */
    private function get_current_filters() {
        return [
            'view' => isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'week',
            'date' => isset($_GET['date']) ? sanitize_text_field($_GET['date']) : date('Y-m-d'),
            'product' => isset($_GET['product']) ? intval($_GET['product']) : 0,
            'service' => isset($_GET['service']) ? intval($_GET['service']) : 0,
            'staff' => isset($_GET['staff']) ? intval($_GET['staff']) : 0,
            'category' => isset($_GET['category']) ? intval($_GET['category']) : 0,
        ];
    }

    /**
     * Check if assets should be loaded
     */
    private function should_load_assets() {
        // Always load on front page (since that's where the schedule is)
        if (is_front_page()) {
            return true;
        }
        
        // Load if any filter parameters are present
        if (isset($_GET['view']) || isset($_GET['date']) || 
            isset($_GET['product']) || isset($_GET['service']) ||
            isset($_GET['staff']) || isset($_GET['category'])) {
            return true;
        }
        
        // Load on schedule page
        if (is_page('schedule')) {
            return true;
        }
        
        // Check for schedule template
        if (is_page_template('schedule-template.php')) {
            return true;
        }
        
        // Check current URL
        $current_url = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($current_url, '/schedule') !== false) {
            return true;
        }
        
        // Check if page contains schedule-related classes or shortcode
        global $post;
        if ($post) {
            if (has_shortcode($post->post_content, 'sod_schedule')) {
                return true;
            }
            if (strpos($post->post_content, 'sod-schedule-container') !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get booking attributes from custom table by product ID
     */
    public function get_product_booking_attributes($product_id) {
        if (!$product_id) {
            return [];
        }
        
        $attributes_table = $this->get_table_name('sod_service_attributes');
        
        $query = "SELECT attribute_type, value, price, product_id, variation_id 
                  FROM {$attributes_table} 
                  WHERE product_id = %d
                  ORDER BY attribute_type, value";
        
        $attributes = $this->wpdb->get_results($this->wpdb->prepare($query, $product_id));
        
        return $attributes ?: [];
    }

    /**
     * AJAX handler to get available timeslots
     */
    public function handle_get_available_timeslots() {
        // Log the request
        error_log("=== SOD Get Available Timeslots Request ===");
        error_log("POST data: " . print_r($_POST, true));
        
        // Check nonce - try multiple possible nonce names
        $nonce_verified = false;
        if (isset($_POST['nonce'])) {
            if (wp_verify_nonce($_POST['nonce'], 'sod_booking_nonce')) {
                $nonce_verified = true;
            } elseif (wp_verify_nonce($_POST['nonce'], 'sod_scheduler_nonce')) {
                $nonce_verified = true;
            } elseif (wp_verify_nonce($_POST['nonce'], 'sod_schedule_nonce')) {
                $nonce_verified = true;
            }
        }
        
        if (!$nonce_verified) {
            error_log("Nonce verification failed");
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }

        try {
            $staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;
            $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
            $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
            $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 60;

            error_log("Parsed parameters - staff_id: $staff_id, date: $date, product_id: $product_id, duration: $duration");

            if (!$staff_id || !$date || !$product_id) {
                throw new Exception(__('Missing required parameters', 'spark-of-divine-scheduler'));
            }

            $timeslots = $this->get_available_timeslots($product_id, $staff_id, $date, $duration);
            
            error_log("Returning " . count($timeslots) . " timeslots");

            wp_send_json_success(array('timeslots' => $timeslots));
        } catch (Exception $e) {
            error_log("Error in handle_get_available_timeslots: " . $e->getMessage());
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Handle booking submission from the schedule
     */
    public function handle_submit_booking() {
        // Check nonce - be more flexible with nonce checking
        $nonce_verified = false;
        if (isset($_POST['nonce'])) {
            if (wp_verify_nonce($_POST['nonce'], 'sod_booking_nonce')) {
                $nonce_verified = true;
            } elseif (wp_verify_nonce($_POST['nonce'], 'sod_scheduler_nonce')) {
                $nonce_verified = true;
            } elseif (wp_verify_nonce($_POST['nonce'], 'sod_schedule_nonce')) {
                $nonce_verified = true;
            }
        }
        
        if (!$nonce_verified) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }

        try {
            // Get form data
            $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
            $staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;
            $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
            $timeslot = isset($_POST['timeslot']) ? sanitize_text_field($_POST['timeslot']) : '';
            $attribute = isset($_POST['attribute']) ? $_POST['attribute'] : '';

            if (!$product_id || !$staff_id || !$date || !$timeslot) {
                throw new Exception(__('Missing required booking information', 'spark-of-divine-scheduler'));
            }

            // Parse attribute data
            $attribute_data = json_decode(stripslashes($attribute), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception(__('Invalid attribute data', 'spark-of-divine-scheduler'));
            }

            // Get duration from attribute
            $duration = 60; // default
            if ($attribute_data['type'] === 'duration') {
                $duration = (int)preg_replace('/[^0-9]/', '', $attribute_data['value']);
            }

            // Check if slot is still available
            if (!$this->is_timeslot_available($staff_id, $date, $timeslot, $duration)) {
                throw new Exception(__('This time slot is no longer available', 'spark-of-divine-scheduler'));
            }

            // Create booking using the booking handler
            if (class_exists('SOD_Booking_Handler')) {
                $booking_handler = SOD_Booking_Handler::getInstance();
                $booking_data = array(
                    'product_id' => $product_id,
                    'staff_id' => $staff_id,
                    'booking_date' => $date,
                    'timeslot' => $timeslot,
                    'duration' => $duration,
                    'attribute' => $attribute
                );
                
                $result = $booking_handler->create_booking($booking_data);
                
                if (is_wp_error($result)) {
                    throw new Exception($result->get_error_message());
                }
                
                wp_send_json_success(array(
                    'message' => __('Booking created successfully!', 'spark-of-divine-scheduler'),
                    'redirect' => wc_get_cart_url()
                ));
            } else {
                throw new Exception(__('Booking handler not available', 'spark-of-divine-scheduler'));
            }

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

        // Use default times if not set
        $start_time_str = !empty($availability->start_time) ? $availability->start_time : '09:00:00';
        $end_time_str = !empty($availability->end_time) ? $availability->end_time : '17:00:00';

        // Create time slots
        try {
            $start_time = new DateTime("$date $start_time_str");
            $end_time = new DateTime("$date $end_time_str");
        } catch (Exception $e) {
            error_log("Error creating DateTime objects: " . $e->getMessage());
            return $timeslots;
        }

        $interval = new DateInterval('PT15M'); // 15-minute intervals
        $current = clone $start_time;

        // Get existing bookings
        $existing_bookings = $this->get_bookings_for_date($staff_id, $date);

        while ($current < $end_time) {
            $slot_end = clone $current;
            $slot_end->modify("+{$duration} minutes");

            // Skip if slot would end after availability
            if ($slot_end > $end_time) {
                break;
            }

            // Check if slot is available
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
                $timeslots[] = [
                    'time' => $current->format('H:i:s'),
                    'formatted' => $current->format('g:i A'),
                    'timeRange' => $current->format('g:i A') . ' - ' . $slot_end->format('g:i A'),
                    'duration' => $duration,
                    'startDateTime' => $current->format('Y-m-d H:i:s'),
                    'endDateTime' => $slot_end->format('Y-m-d H:i:s')
                ];
            }

            $current->add($interval);
        }

        error_log("Generated " . count($timeslots) . " available timeslots");
        return $timeslots;
    }

    /**
     * Check if a specific timeslot is available
     */
    private function is_timeslot_available($staff_id, $date, $timeslot, $duration) {
        $bookings_table = $this->get_table_name('sod_bookings');
        
        $start_time = $timeslot;
        $end_time = date('H:i:s', strtotime("$timeslot +$duration minutes"));
        
        $query = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$bookings_table}
             WHERE staff_id = %d AND date = %s 
             AND status NOT IN ('cancelled', 'no_show')
             AND ((start_time <= %s AND end_time > %s)
                  OR (start_time < %s AND end_time >= %s)
                  OR (start_time >= %s AND end_time <= %s))",
            $staff_id, $date,
            $start_time, $start_time,
            $end_time, $end_time,
            $start_time, $end_time
        );
        
        $count = $this->wpdb->get_var($query);
        return $count == 0;
    }

    /**
     * Get staff availability for a specific date
     */
    private function get_staff_availability($staff_id, $product_id, $date) {
        $availability_table = $this->get_table_name('sod_staff_availability');

        // Check for one-time availability
        $one_time = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$availability_table} 
             WHERE staff_id = %d AND product_id = %d AND date = %s",
            $staff_id, $product_id, $date
        ));

        if ($one_time) {
            return $one_time;
        }

        // Check for recurring availability
        $day_of_week = date('l', strtotime($date));

        $recurring = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$availability_table} 
             WHERE staff_id = %d AND product_id = %d AND day_of_week = %s
             AND (recurring_end_date IS NULL OR recurring_end_date >= %s)
             ORDER BY availability_id DESC LIMIT 1",
            $staff_id, $product_id, $day_of_week, $date
        ));

        if ($recurring) {
            return $recurring;
        }

        // Check for general staff availability
        $default = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$availability_table} 
             WHERE staff_id = %d AND (product_id = 0 OR product_id IS NULL) AND day_of_week = %s
             AND (recurring_end_date IS NULL OR recurring_end_date >= %s)
             ORDER BY availability_id DESC LIMIT 1",
            $staff_id, $day_of_week, $date
        ));

        return $default;
    }

    /**
     * Get bookings for a specific date and staff
     */
    private function get_bookings_for_date($staff_id, $date) {
        $bookings_table = $this->get_table_name('sod_bookings');
        
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$bookings_table} 
             WHERE staff_id = %d AND date = %s 
             AND status NOT IN ('cancelled', 'no_show')",
            $staff_id, $date
        ));
    }

    /**
     * Get table name with fallback
     */
    private function get_table_name($table_suffix) {
        $prefixed = $this->wpdb->prefix . $table_suffix;
        
        // Check if standard table exists
        if ($this->wpdb->get_var("SHOW TABLES LIKE '{$prefixed}'") === $prefixed) {
            return $prefixed;
        }
        
        // Try hardcoded fallback
        $fallback = 'wp_3be9vb_' . $table_suffix;
        if ($this->wpdb->get_var("SHOW TABLES LIKE '{$fallback}'") === $fallback) {
            return $fallback;
        }
        
        // Return prefixed version anyway
        return $prefixed;
    }

    /**
     * Verify database structure has product_id columns
     */
    private function verify_database_structure() {
        $tables_to_check = [
            'sod_staff_availability' => 'product_id',
            'sod_service_attributes' => 'product_id',
            'sod_bookings' => 'product_id'
        ];

        foreach ($tables_to_check as $table_suffix => $column) {
            $table = $this->get_table_name($table_suffix);
            
            if ($this->wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table) {
                $has_column = $this->wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE '{$column}'");
                
                if (!$has_column) {
                    error_log("Warning: {$column} column missing from {$table}");
                    
                    // Check if service_id exists and we can migrate
                    $has_service_id = $this->wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'service_id'");
                    if ($has_service_id) {
                        // Add product_id column
                        $this->wpdb->query("ALTER TABLE {$table} ADD COLUMN {$column} BIGINT(20) UNSIGNED NULL AFTER staff_id");
                        // Copy data from service_id to product_id
                        $this->wpdb->query("UPDATE {$table} SET {$column} = service_id WHERE {$column} IS NULL AND service_id IS NOT NULL");
                        error_log("Added {$column} column to {$table} and migrated data from service_id");
                    }
                }
            }
        }
    }

    /**
     * Public method to get product booking attributes (used by template)
     */
    public function get_product_attributes($product_id) {
        return $this->get_product_booking_attributes($product_id);
    }
}

// Initialize the Schedule Handler
SOD_Schedule_Handler::getInstance();
