<?php
// File: includes/class-sod-booking-handler.php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class SOD_Booking_Handler {
    private static $instance;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Register AJAX handlers for service categories
        add_action('wp_ajax_get_service_categories', array('SOD_Booking_Handler', 'get_service_categories'));
        add_action('wp_ajax_nopriv_get_service_categories', array('SOD_Booking_Handler', 'get_service_categories'));
    }

    // Method to create a booking
    public function create_booking($params) {
        // Check availability
        if (!$this->check_availability($params['staff_id'], $params['service_id'], $params['start_time'], $params['duration'])) {
            return new WP_Error('unavailable', 'This time slot is no longer available', array('status' => 409));
        }

        // Create the booking post
        $booking_id = wp_insert_post(array(
            'post_type' => 'sod_booking',
            'post_status' => 'publish',
            'post_title' => 'Booking for ' . get_the_title($params['service_id']) . ' with ' . get_the_title($params['staff_id']),
            'meta_input' => array(
                'service_id' => $params['service_id'],
                'staff_id' => $params['staff_id'],
                'start_time' => $params['start_time'],
                'duration' => $params['duration'],
                'customer_id' => get_current_user_id(),
                'status' => 'pending'
            )
        ));

        if (is_wp_error($booking_id)) {
            return new WP_Error('booking_failed', 'Failed to create booking', array('status' => 500));
        }

        // Determine if payment is required
        $collect_online = get_post_meta($params['staff_id'], 'collect_payment_online', true);
        if ($collect_online) {
            // Create WooCommerce order for digital payment
            $order_id = $this->create_woocommerce_order($booking_id, $params);
            if (is_wp_error($order_id)) {
                wp_delete_post($booking_id, true); // Rollback booking
                return $order_id;
            }
            update_post_meta($booking_id, 'order_id', $order_id);

            // Trigger booking created email
            $this->send_booking_created_email($booking_id, $order_id);
        } else {
            // Handle cash payment
            $this->send_booking_confirmed_email($booking_id);
        }

        return array(
            'booking_id' => $booking_id,
            'order_id' => isset($order_id) ? $order_id : null,
            'requires_payment' => $collect_online
        );
    }

    // Method to check staff availability
    private function check_availability($staff_id, $service_id, $start_time, $duration) {
        global $wpdb;
        $staff_availability_table = $wpdb->prefix . 'sod_staff_availability';
        $bookings_table = $wpdb->prefix . 'sod_bookings';

        // Check staff availability
        $day_of_week = date('N', strtotime($start_time));
        $time = date('H:i:s', strtotime($start_time));
        
        // Query to check staff's availability
        $availability = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $staff_availability_table 
            WHERE staff_id = %d AND service_id = %d AND day_of_week = %d 
            AND start_time <= %s AND end_time >= %s",
            $staff_id, $service_id, $day_of_week, $time, $time
        ));

        if (!$availability) {
            return false; // Time slot is outside of staff's availability
        }

        // Check for conflicting bookings
        $end_time = date('Y-m-d H:i:s', strtotime($start_time) + $duration * 60);
        $conflicting_bookings = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $bookings_table 
            WHERE staff_id = %d 
            AND ((start_time <= %s AND DATE_ADD(start_time, INTERVAL duration MINUTE) > %s)
            OR (start_time < %s AND DATE_ADD(start_time, INTERVAL duration MINUTE) >= %s))",
            $staff_id, $time, $time, $end_time, $end_time
        ));

        return $conflicting_bookings == 0;
    }

    // Method to get service categories
    public static function get_service_categories() {
        // Check nonce for security
        check_ajax_referer('sod_booking_nonce', 'nonce');

        $categories = get_terms(array(
            'taxonomy' => 'service_category',
            'hide_empty' => false,
        ));

        if (!empty($categories) && !is_wp_error($categories)) {
            $category_list = array();

            foreach ($categories as $category) {
                $category_list[] = array(
                    'id' => $category->term_id,
                    'name' => $category->name,
                );
            }

            wp_send_json_success($category_list);
        } else {
            wp_send_json_error(array('message' => 'No categories found.'));
        }
    }

    // Method to get services by category
    public static function get_services() {
        check_ajax_referer('sod_booking_nonce', 'nonce');

        $category_id = isset($_POST['category_id']) ? absint($_POST['category_id']) : 0;
        if (!$category_id) {
            wp_send_json_error(array('message' => 'Invalid category ID.'));
        }

        $services = get_posts(array(
            'post_type' => 'service',
            'tax_query' => array(
                array(
                    'taxonomy' => 'service_category',
                    'field' => 'term_id',
                    'terms' => $category_id,
                ),
            ),
            'posts_per_page' => -1,
        ));

        if (!empty($services)) {
            $service_list = array();

            foreach ($services as $service) {
                $service_list[] = array(
                    'id' => $service->ID,
                    'name' => $service->post_title,
                );
            }

            wp_send_json_success($service_list);
        } else {
            wp_send_json_error(array('message' => 'No services found.'));
        }
    }

    // Method to create a WooCommerce order
    private function create_woocommerce_order($booking_id, $params) {
        // WooCommerce order creation logic
        $service = get_post($params['service_id']);
        $staff = get_post($params['staff_id']);
        $cost = get_post_meta($params['service_id'], 'cost', true) * ($params['duration'] / 15);

        $order = wc_create_order();

        // Add the service as a line item
        $order->add_product(wc_get_product($params['service_id']), 1, [
            'subtotal' => $cost,
            'total' => $cost,
            'name' => $service->post_title . ' with ' . $staff->post_title,
        ]);

        $order->set_created_via('Spark Divine Scheduler');
        $order->set_customer_id(get_current_user_id());
        $order->calculate_totals();
        $order->save();

        // Add booking details to order meta
        update_post_meta($order->get_id(), '_booking_id', $booking_id);
        update_post_meta($order->get_id(), '_service_date', date('Y-m-d', strtotime($params['start_time'])));
        update_post_meta($order->get_id(), '_service_time', date('H:i:s', strtotime($params['start_time'])));
        update_post_meta($order->get_id(), '_service_duration', $params['duration']);

        return $order->get_id();
    }

    // Method to update a booking
    public function update_booking($booking_id, $params) {
        // Update the booking post
        $update_status = wp_update_post(array(
            'ID' => $booking_id,
            'post_title' => 'Updated Booking for ' . get_the_title($params['service_id']) . ' with ' . get_the_title($params['staff_id']),
            'meta_input' => array(
                'service_id' => $params['service_id'],
                'staff_id' => $params['staff_id'],
                'start_time' => $params['start_time'],
                'duration' => $params['duration'],
                'status' => $params['status'],
            )
        ));

        if (is_wp_error($update_status)) {
            return new WP_Error('update_failed', 'Failed to update booking', array('status' => 500));
        }

        // Update the database table if applicable
        global $wpdb;
        $bookings_table = $wpdb->prefix . 'sod_bookings';
        $wpdb->update(
            $bookings_table,
            array(
                'service_id' => $params['service_id'],
                'staff_id' => $params['staff_id'],
                'start_time' => $params['start_time'],
                'duration' => $params['duration'],
                'status' => $params['status']
            ),
            array('id' => $booking_id),
            array('%d', '%d', '%s', '%d', '%s'),
            array('%d')
        );

        // Send confirmation email if the booking is updated
        $this->send_booking_updated_email($booking_id);

        return true;
    }

    // Method to send booking created email
    private function send_booking_created_email($booking_id, $order_id) {
        if (class_exists('SOD_Booking_Created_Email')) {
            $email = SOD_Booking_Created_Email::get_instance();
            $email->trigger($booking_id, $order_id);
        }
    }

    // Method to send booking confirmed email
    private function send_booking_confirmed_email($booking_id) {
        if (class_exists('SOD_Booking_Confirmed_Email')) {
            $email = SOD_Booking_Confirmed_Email::get_instance();
            $email->trigger($booking_id);
        }
    }

    // Method to send booking updated email
    private function send_booking_updated_email($booking_id) {
        if (class_exists('SOD_Booking_Updated_Email')) {
            $email = SOD_Booking_Updated_Email::get_instance();
            $email->trigger($booking_id);
        }
    }

    // Method to send booking canceled email
    private function send_booking_canceled_email($booking_id) {
        if (class_exists('SOD_Booking_Canceled_Email')) {
            $email = SOD_Booking_Canceled_Email::get_instance();
            $email->trigger($booking_id);
        }
    }

    // Method to send booking paid email
    private function send_booking_paid_email($booking_id, $order_id) {
        if (class_exists('SOD_Booking_Paid_Email')) {
            $email = SOD_Booking_Paid_Email::get_instance();
            $email->trigger($booking_id, $order_id);
        }
    }

    // Method to mark booking as paid
    public function mark_booking_as_paid($booking_id) {
        // Update the booking status to paid
        update_post_meta($booking_id, '_sod_booking_paid', '1');

        // Get associated order ID if exists
        $order_id = get_post_meta($booking_id, 'order_id', true);

        if ($order_id) {
            // Mark the WooCommerce order as completed
            $order = wc_get_order($order_id);
            if ($order && $order->get_status() !== 'completed') {
                $order->update_status('completed', __('Marked as paid in the shop by staff', 'textdomain'));
            }
        } 

        // Trigger the 'Booking Paid' email
        $this->send_booking_paid_email($booking_id, $order_id);
    }
}

// Initialize the booking handler
SOD_Booking_Handler::get_instance();