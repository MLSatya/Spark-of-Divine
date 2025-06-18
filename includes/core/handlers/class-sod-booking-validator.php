<?php
/**
 * SOD Booking Validator
 * 
 * Validates booking requests and handles booking conflicts.
 * Fixed to include the missing validate_booking_request method.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class SOD_Booking_Validator {
    // Use Singleton pattern
    private static $instance = null;
    
    // Private constructor for singleton
    private function __construct() {
        // Private constructor to prevent creating a new instance
    }
    
    /**
     * Get the singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Validate a booking request (compatibility method for booking handler)
     * 
     * @param null $unused Legacy parameter
     * @param int $product_id Product ID
     * @param int $staff_id Staff ID
     * @param string $datetime Date and time
     * @param array $attribute_data Attribute data
     * @param int $duration Duration in minutes
     * @return array Validation result with 'valid' and optionally 'message' keys
     */
    public function validate_booking_request($unused, $product_id, $staff_id, $datetime, $attribute_data, $duration = 60) {
        // Convert parameters to the format expected by validate_booking
        $booking_data = array(
            'product_id' => $product_id,
            'staff_id' => $staff_id,
            'date' => date('Y-m-d', strtotime($datetime)),
            'time' => date('H:i', strtotime($datetime)),
            'duration' => $duration
        );
        
        // Call the existing validation method
        $result = $this->validate_booking($booking_data);
        
        // Convert the result to the expected format
        if ($result === true) {
            return array('valid' => true, 'message' => 'Booking is valid');
        } elseif (is_array($result) && isset($result['valid']) && !$result['valid']) {
            // Extract error message
            $message = isset($result['errors']) && is_array($result['errors']) 
                ? implode(' ', $result['errors']) 
                : 'This time slot is not available.';
            return array('valid' => false, 'message' => $message);
        } else {
            // Unknown result format, assume invalid
            return array('valid' => false, 'message' => 'Validation failed');
        }
    }
    
    /**
     * Validate a booking request
     * 
     * @param array $booking_data Booking data to validate
     * @return boolean|array True if valid, array of errors if invalid
     */
    public function validate_booking($booking_data) {
        // Initialize empty errors array
        $errors = array();
        
        // Check for required fields
        $required_fields = array('staff_id', 'date', 'time');
        foreach ($required_fields as $field) {
            if (empty($booking_data[$field])) {
                $errors[] = sprintf(__('Missing required field: %s', 'spark-of-divine-scheduler'), $field);
            }
        }
        
        // If we have errors, return them
        if (!empty($errors)) {
            return array(
                'valid' => false,
                'errors' => $errors
            );
        }
        
        // Check staff availability
        $staff_id = intval($booking_data['staff_id']);
        $date = sanitize_text_field($booking_data['date']);
        $time = sanitize_text_field($booking_data['time']);
        
        // Calculate duration
        $duration = isset($booking_data['duration']) ? intval($booking_data['duration']) : 60; // Default 60 minutes
        
        // Check if this time slot is available
        $availability = $this->check_availability($staff_id, $date, $time, $duration);
        
        if (!$availability['available']) {
            return array(
                'valid' => false,
                'errors' => array(__('This time slot is not available.', 'spark-of-divine-scheduler'))
            );
        }
        
        // Check for any booking-specific validation
        $product_validation = $this->validate_product_booking($booking_data);
        if (is_array($product_validation) && !$product_validation['valid']) {
            return $product_validation;
        }
        
        // Booking is valid
        return true;
    }
    
    /**
     * Check availability for a staff member at a specific time
     * 
     * @param int $staff_id Staff ID
     * @param string $date Date in Y-m-d format
     * @param string $time Time in H:i format
     * @param int $duration Duration in minutes
     * @return array Availability information
     */
    public function check_availability($staff_id, $date, $time, $duration = 60) {
        global $wpdb;
        
        // Parse date and time
        $datetime = new DateTime("$date $time");
        $end_datetime = clone $datetime;
        $end_datetime->add(new DateInterval("PT{$duration}M"));
        
        // Format for DB query
        $start_time = $datetime->format('Y-m-d H:i:s');
        $end_time = $end_datetime->format('Y-m-d H:i:s');
        
        // Get table name with prefix
        $table_name = $wpdb->prefix . 'sod_bookings';
        
        // Check if the booking table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            // Try fallback table name
            $table_name = 'wp_3be9vb_sod_bookings';
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            
            if (!$table_exists) {
                // If table doesn't exist, assume available (likely during setup)
                return array(
                    'available' => true,
                    'conflicting_bookings' => array()
                );
            }
        }
        
        // Check for conflicting bookings - using date/time columns separately
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE staff_id = %d 
            AND date = %s
            AND (
                (start_time <= %s AND end_time > %s) OR 
                (start_time < %s AND end_time >= %s) OR
                (start_time >= %s AND start_time < %s)
            )
            AND status NOT IN ('cancelled', 'no_show')",
            $staff_id, 
            $date,
            $time, $time,
            date('H:i:s', strtotime($end_time)), date('H:i:s', strtotime($end_time)),
            $time, date('H:i:s', strtotime($end_time))
        );
        
        $conflicting_bookings = $wpdb->get_results($query);
        
        // Check staff schedule
        $schedule_available = $this->check_staff_schedule($staff_id, $datetime, $end_datetime);
        
        return array(
            'available' => (empty($conflicting_bookings) && $schedule_available),
            'conflicting_bookings' => $conflicting_bookings,
            'schedule_available' => $schedule_available
        );
    }
    
    /**
     * Check if a staff member is scheduled to work during the requested time
     * 
     * @param int $staff_id Staff ID
     * @param DateTime $start_datetime Start datetime
     * @param DateTime $end_datetime End datetime
     * @return boolean True if available
     */
    private function check_staff_schedule($staff_id, $datetime, $end_datetime) {
        // Get the day of week
        $day_of_week = strtolower($datetime->format('l'));
        
        // Get staff schedule
        $schedule = get_post_meta($staff_id, '_sod_schedule', true);
        
        // If no schedule, assume available
        if (empty($schedule) || !is_array($schedule)) {
            return true;
        }
        
        // Check if staff works on this day
        if (!isset($schedule[$day_of_week]) || !$schedule[$day_of_week]['enabled']) {
            return false;
        }
        
        // Get start and end hours for the day
        $start_time = $schedule[$day_of_week]['start'];
        $end_time = $schedule[$day_of_week]['end'];
        
        // If no times set, assume available all day
        if (empty($start_time) || empty($end_time)) {
            return true;
        }
        
        // Parse schedule times
        $schedule_start = DateTime::createFromFormat('H:i', $start_time);
        $schedule_end = DateTime::createFromFormat('H:i', $end_time);
        
        // Get just the time portion for comparison
        $booking_time = DateTime::createFromFormat('H:i', $datetime->format('H:i'));
        $booking_end_time = DateTime::createFromFormat('H:i', $end_datetime->format('H:i'));
        
        // Check if booking time is within staff schedule
        if ($booking_time >= $schedule_start && $booking_end_time <= $schedule_end) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Validate product-specific booking requirements
     * 
     * @param array $booking_data Booking data
     * @return boolean|array True if valid, array with errors if invalid
     */
    private function validate_product_booking($booking_data) {
        // Get the product ID from booking data
        $product_id = isset($booking_data['product_id']) ? intval($booking_data['product_id']) : 0;
        
        // If no product, skip validation
        if (empty($product_id)) {
            return true;
        }
        
        // Check if this product allows booking with this staff member
        $staff_id = intval($booking_data['staff_id']);
        $product_staff_id = get_post_meta($product_id, '_sod_staff_id', true);
        
        if (!empty($product_staff_id) && $product_staff_id != $staff_id) {
            return array(
                'valid' => false,
                'errors' => array(__('This product cannot be booked with the selected staff member.', 'spark-of-divine-scheduler'))
            );
        }
        
        // Product is valid for booking
        return true;
    }
}