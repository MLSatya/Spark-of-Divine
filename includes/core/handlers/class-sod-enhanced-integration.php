<?php
/**
 * Enhanced Booking Integration for Existing SOD System
 * 
 * This file adds enhanced time slot functionality to your existing system
 * without breaking current functionality.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SOD_Enhanced_Integration {
    private static $instance = null;
    private $wpdb;
    private $bookings_table;

    public static function getInstance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // Set bookings table with fallback
        $this->bookings_table = $wpdb->prefix . 'sod_bookings';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->bookings_table}'") !== $this->bookings_table) {
            $this->bookings_table = 'wp_3be9vb_sod_bookings'; // Your actual table
        }
        
        $this->init_hooks();
        error_log("SOD Enhanced Integration initialized");
    }

    /**
     * Initialize hooks to enhance existing system
     */
    private function init_hooks() {
        // Add enhanced AJAX endpoint while keeping existing one
        add_action('wp_ajax_sod_get_available_timeslots_enhanced', array($this, 'ajax_get_enhanced_timeslots'));
        add_action('wp_ajax_nopriv_sod_get_available_timeslots_enhanced', array($this, 'ajax_get_enhanced_timeslots'));
        
        // Hook into existing booking validation to enhance it
        add_filter('sod_validate_booking_data', array($this, 'enhance_booking_validation'), 10, 5);
        
        // Enhance existing booking handler methods
        add_action('init', array($this, 'enhance_existing_handlers'), 20);
        
        // Enqueue enhanced scripts alongside existing ones
        add_action('wp_enqueue_scripts', array($this, 'enqueue_enhanced_scripts'), 20);
    }

    /**
     * Enhanced AJAX handler for timeslots with better conflict checking
     */
    public function ajax_get_enhanced_timeslots() {
        try {
            // Check nonce (compatible with your existing system)
            $nonce_verified = false;
            
            if (isset($_POST['nonce'])) {
                if (wp_verify_nonce($_POST['nonce'], 'sod_booking_admin_nonce')) {
                    $nonce_verified = true;
                } elseif (wp_verify_nonce($_POST['nonce'], 'sod_booking_nonce')) {
                    $nonce_verified = true;
                } elseif (isset($GLOBALS['sodBooking']) && wp_verify_nonce($_POST['nonce'], $GLOBALS['sodBooking']['nonce'])) {
                    $nonce_verified = true;
                }
            }
            
            if (!$nonce_verified) {
                throw new Exception(__('Security check failed', 'spark-of-divine-scheduler'));
            }

            $staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;
            $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
            $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
            $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 60;
            
            if (!$staff_id || !$date) {
                throw new Exception(__('Missing required parameters', 'spark-of-divine-scheduler'));
            }

            $timeslots = $this->get_enhanced_available_timeslots($product_id, $staff_id, $date, $duration);

            wp_send_json_success(array(
                'timeslots' => $timeslots,
                'debug' => array(
                    'staff_id' => $staff_id,
                    'date' => $date,
                    'product_id' => $product_id,
                    'duration' => $duration,
                    'total_slots' => count($timeslots),
                    'method' => 'enhanced'
                )
            ));
        } catch (Exception $e) {
            error_log("Enhanced timeslot error: " . $e->getMessage());
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Get enhanced available timeslots with proper conflict checking
     */
    private function get_enhanced_available_timeslots($product_id, $staff_id, $date, $duration = 60) {
        $timeslots = [];

        try {
            // Get staff availability for this date
            $availability = $this->get_staff_availability($staff_id, $product_id, $date);
            
            if (!$availability) {
                error_log("No availability found for staff $staff_id with product $product_id on date $date");
                return $timeslots;
            }

            // Parse availability times
            $start_time = new DateTime("$date {$availability->start_time}");
            $end_time = new DateTime("$date {$availability->end_time}");
            
            // Use 15-minute intervals for slot generation
            $interval = new DateInterval('PT15M');
            
            // Get all existing bookings for this staff on this date
            $existing_bookings = $this->get_existing_bookings($staff_id, $date);
            
            error_log("Checking timeslots for staff $staff_id on $date with duration $duration minutes");
            error_log("Staff hours: {$availability->start_time} to {$availability->end_time}");
            error_log("Found " . count($existing_bookings) . " existing bookings");

            // Generate potential time slots
            $current_time = clone $start_time;
            
            while ($current_time < $end_time) {
                // Calculate end time for this potential slot
                $slot_end_time = clone $current_time;
                $slot_end_time->add(new DateInterval('PT' . $duration . 'M'));
                
                // Skip if this slot would end after staff availability ends
                if ($slot_end_time > $end_time) {
                    $current_time->add($interval);
                    continue;
                }
                
                // Check for conflicts with existing bookings
                if (!$this->has_booking_conflict($current_time, $slot_end_time, $existing_bookings)) {
                    $time_str = $current_time->format('H:i:s');
                    $formatted_time = date_i18n(get_option('time_format'), $current_time->getTimestamp());
                    $formatted_end_time = date_i18n(get_option('time_format'), $slot_end_time->getTimestamp());
                    
                    $timeslots[] = [
                        'time' => $time_str,
                        'formatted' => $formatted_time . ' - ' . $formatted_end_time,
                        'start_formatted' => $formatted_time,
                        'end_formatted' => $formatted_end_time,
                        'duration' => $duration
                    ];
                }
                
                // Move to next potential slot
                $current_time->add($interval);
            }
            
            error_log("Found " . count($timeslots) . " available timeslots");
            
        } catch (Exception $e) {
            error_log("Error getting enhanced timeslots: " . $e->getMessage());
        }

        return $timeslots;
    }

    /**
     * Get staff availability (compatible with your system)
     */
    private function get_staff_availability($staff_id, $product_id, $date) {
        // First try specific availability for this exact date
        $specific = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}sod_staff_availability 
             WHERE staff_id = %d AND product_id = %d AND date = %s 
             ORDER BY start_time ASC LIMIT 1",
            $staff_id, $product_id, $date
        ));
        
        if ($specific) {
            return $specific;
        }
        
        // Check for recurring availability
        $day_of_week = date('l', strtotime($date));
        
        $recurring = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}sod_staff_availability 
             WHERE staff_id = %d AND product_id = %d AND day_of_week = %s 
             AND recurring_type IS NOT NULL 
             AND (recurring_end_date IS NULL OR recurring_end_date >= %s)
             ORDER BY start_time ASC LIMIT 1",
            $staff_id, $product_id, $day_of_week, $date
        ));
        
        if ($recurring) {
            return $recurring;
        }
        
        // Check for general staff availability
        $general = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}sod_staff_availability 
             WHERE staff_id = %d AND (product_id = 0 OR product_id IS NULL) AND day_of_week = %s 
             AND recurring_type IS NOT NULL 
             AND (recurring_end_date IS NULL OR recurring_end_date >= %s)
             ORDER BY start_time ASC LIMIT 1",
            $staff_id, $day_of_week, $date
        ));
        
        if ($general) {
            return $general;
        }
        
        // Fall back to staff meta settings (compatible with your system)
        return $this->get_staff_meta_availability($staff_id, $date);
    }

    /**
     * Get staff availability from post meta (your existing approach)
     */
    private function get_staff_meta_availability($staff_id, $date) {
        $day_of_week = strtolower(date('l', strtotime($date)));
        
        // Check if staff works this day
        $works_day = get_post_meta($staff_id, "sod_works_{$day_of_week}", true);
        if ($works_day === 'no' || $works_day === false) {
            return null;
        }
        
        // Get times for this day (compatible with your meta structure)
        $start_time = get_post_meta($staff_id, "sod_start_time_{$day_of_week}", true);
        $end_time = get_post_meta($staff_id, "sod_end_time_{$day_of_week}", true);
        
        // Fall back to default times
        if (empty($start_time)) {
            $start_time = get_post_meta($staff_id, 'sod_default_start_time', true) ?: '09:00:00';
        }
        if (empty($end_time)) {
            $end_time = get_post_meta($staff_id, 'sod_default_end_time', true) ?: '17:00:00';
        }
        
        // Create availability object
        $availability = new stdClass();
        $availability->staff_id = $staff_id;
        $availability->start_time = $start_time;
        $availability->end_time = $end_time;
        $availability->date = $date;
        
        return $availability;
    }

    /**
     * Get existing bookings (using your table structure)
     */
    private function get_existing_bookings($staff_id, $date) {
        try {
            $query = $this->wpdb->prepare(
                "SELECT booking_id, 
                        CONCAT(date, ' ', start_time) as booking_start,
                        CONCAT(date, ' ', end_time) as booking_end,
                        start_time, 
                        end_time, 
                        duration,
                        status
                 FROM {$this->bookings_table}
                 WHERE staff_id = %d 
                 AND date = %s
                 AND status NOT IN ('cancelled', 'no-show', 'failed')
                 ORDER BY start_time ASC",
                $staff_id,
                $date
            );
            
            $results = $this->wpdb->get_results($query);
            
            // Convert to DateTime objects for easier comparison
            $bookings = array();
            if ($results) {
                foreach ($results as $booking) {
                    $bookings[] = (object) [
                        'booking_id' => $booking->booking_id,
                        'start' => new DateTime($booking->booking_start),
                        'end' => new DateTime($booking->booking_end),
                        'status' => $booking->status
                    ];
                }
            }
            
            return $bookings;
            
        } catch (Exception $e) {
            error_log("Error getting existing bookings: " . $e->getMessage());
            return array();
        }
    }

    /**
     * Check for booking conflicts
     */
    private function has_booking_conflict($slot_start, $slot_end, $existing_bookings) {
        foreach ($existing_bookings as $booking) {
            // Check for any overlap
            if ($slot_start < $booking->end && $slot_end > $booking->start) {
                error_log("Conflict detected: Proposed slot {$slot_start->format('H:i')}-{$slot_end->format('H:i')} conflicts with booking {$booking->booking_id}");
                return true;
            }
        }
        
        return false;
    }

    /**
     * Enhance existing booking validation
     */
    public function enhance_booking_validation($is_valid, $booking_data, $staff_id, $date, $time) {
        // If already invalid, return as-is
        if (!$is_valid) {
            return $is_valid;
        }
        
        try {
            $duration = isset($booking_data['duration']) ? intval($booking_data['duration']) : 60;
            $product_id = isset($booking_data['product_id']) ? intval($booking_data['product_id']) : 0;
            
            // Create DateTime objects for the proposed booking
            $booking_start = new DateTime("$date $time");
            $booking_end = clone $booking_start;
            $booking_end->add(new DateInterval('PT' . $duration . 'M'));
            
            // Get staff availability
            $availability = $this->get_staff_availability($staff_id, $product_id, $date);
            
            if (!$availability) {
                return false;
            }
            
            // Check if booking is within availability hours
            $avail_start = new DateTime("$date {$availability->start_time}");
            $avail_end = new DateTime("$date {$availability->end_time}");
            
            if ($booking_start < $avail_start || $booking_end > $avail_end) {
                return false;
            }
            
            // Check for conflicts with existing bookings
            $existing_bookings = $this->get_existing_bookings($staff_id, $date);
            
            if ($this->has_booking_conflict($booking_start, $booking_end, $existing_bookings)) {
                return false;
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Error in enhanced booking validation: " . $e->getMessage());
            return $is_valid; // Return original validation result on error
        }
    }

    /**
     * Enhance existing handlers with hooks
     */
    public function enhance_existing_handlers() {
        // Hook into existing booking handler if it exists
        if (class_exists('SOD_Booking_Handler')) {
            add_filter('sod_booking_get_timeslots', array($this, 'filter_existing_timeslots'), 10, 4);
        }
        
        // Hook into schedule handler if it exists
        if (class_exists('SOD_Schedule_Handler')) {
            add_filter('sod_schedule_timeslots', array($this, 'filter_schedule_timeslots'), 10, 4);
        }
    }

    /**
     * Filter existing timeslots to use enhanced version when appropriate
     */
    public function filter_existing_timeslots($timeslots, $staff_id, $date, $duration) {
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        
        // Use enhanced method
        $enhanced_slots = $this->get_enhanced_available_timeslots($product_id, $staff_id, $date, $duration);
        
        return !empty($enhanced_slots) ? $enhanced_slots : $timeslots;
    }

    /**
     * Filter schedule timeslots
     */
    public function filter_schedule_timeslots($timeslots, $staff_id, $date, $product_id) {
        $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 60;
        
        $enhanced_slots = $this->get_enhanced_available_timeslots($product_id, $staff_id, $date, $duration);
        
        return !empty($enhanced_slots) ? $enhanced_slots : $timeslots;
    }

    /**
     * Enqueue enhanced scripts alongside existing ones
     */
    public function enqueue_enhanced_scripts() {
        // Only load on pages that need it
        if (!$this->should_load_enhanced_scripts()) {
            return;
        }

        wp_enqueue_script(
            'sod-enhanced-integration',
            plugin_dir_url(__FILE__) . 'assets/js/sod-enhanced-integration.js',
            array('jquery', 'sod-booking-form'), // Depend on your existing script
            '1.0.0',
            true
        );

        // Localize with enhanced variables
        wp_localize_script('sod-enhanced-integration', 'sodEnhanced', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sod_booking_nonce'),
            'defaultDuration' => 60,
            'debugMode' => defined('WP_DEBUG') && WP_DEBUG,
            'strings' => array(
                'loadingSlots' => __('Loading available time slots...', 'spark-of-divine-scheduler'),
                'noSlotsAvailable' => __('No time slots available for selected duration', 'spark-of-divine-scheduler'),
                'selectAllFields' => __('Please complete all required fields', 'spark-of-divine-scheduler'),
                'bookingError' => __('Error processing booking', 'spark-of-divine-scheduler')
            )
        ));
    }

    /**
     * Check if we should load enhanced scripts
     */
    private function should_load_enhanced_scripts() {
        global $post;
        
        // Load on pages with booking forms
        if ($post && (
            has_shortcode($post->post_content, 'sod_booking_form') ||
            has_shortcode($post->post_content, 'sod_schedule') ||
            strpos($post->post_content, 'sod-booking') !== false ||
            strpos($post->post_content, 'booking-form') !== false
        )) {
            return true;
        }
        
        // Load on specific page templates
        if (is_page()) {
            $template = get_page_template_slug();
            if (strpos($template, 'booking') !== false || 
                strpos($template, 'schedule') !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Debug method to check integration status
     */
    public function get_integration_status() {
        return array(
            'bookings_table' => $this->bookings_table,
            'table_exists' => $this->wpdb->get_var("SHOW TABLES LIKE '{$this->bookings_table}'") !== null,
            'original_handler_exists' => class_exists('SOD_Booking_Handler'),
            'schedule_handler_exists' => class_exists('SOD_Schedule_Handler'),
            'hooks_registered' => has_action('wp_ajax_sod_get_available_timeslots_enhanced'),
            'enhanced_endpoint_active' => true
        );
    }
}

// Initialize the enhanced integration
add_action('plugins_loaded', function() {
    SOD_Enhanced_Integration::getInstance();
}, 15);

/**
 * Helper function to get enhanced integration instance
 */
function sod_get_enhanced_integration() {
    return SOD_Enhanced_Integration::getInstance();
}

/**
 * Patch for existing SOD_Booking_Handler if it exists
 * Add this method to your existing SOD_Booking_Handler class
 */
if (class_exists('SOD_Booking_Handler')) {
    add_action('init', function() {
        $booking_handler = SOD_Booking_Handler::getInstance();
        
        // Add enhanced timeslots method to existing handler
        if (method_exists($booking_handler, 'add_enhanced_timeslots_method')) {
            $booking_handler->add_enhanced_timeslots_method();
        } else {
            // If the method doesn't exist, we'll use the hook system instead
            add_filter('sod_booking_timeslots_result', function($timeslots, $staff_id, $date, $duration) {
                $enhanced_integration = SOD_Enhanced_Integration::getInstance();
                $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
                
                $enhanced_slots = $enhanced_integration->get_enhanced_available_timeslots($product_id, $staff_id, $date, $duration);
                
                return !empty($enhanced_slots) ? $enhanced_slots : $timeslots;
            }, 10, 4);
        }
    }, 25);
}