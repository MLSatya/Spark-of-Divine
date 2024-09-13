<?php
// File: includes/booking-handler.php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

function spark_divine_create_booking($params) {
    // Check if the time slot is still available
    if (!spark_divine_check_availability($params['staffId'], $params['serviceId'], $params['start'], $params['duration'])) {
        return new WP_Error('unavailable', 'This time slot is no longer available', array('status' => 409));
    }

    $booking_id = wp_insert_post(array(
        'post_type' => 'booking',
        'post_status' => 'publish',
        'post_title' => 'Booking for ' . get_the_title($params['serviceId']) . ' with ' . get_the_title($params['staffId']),
        'meta_input' => array(
            'service_id' => $params['serviceId'],
            'staff_id' => $params['staffId'],
            'start_time' => $params['start'],
            'duration' => $params['duration'],
            'customer_id' => get_current_user_id(),
            'status' => 'pending'
        )
    ));

    if (is_wp_error($booking_id)) {
        return new WP_Error('booking_failed', 'Failed to create booking', array('status' => 500));
    }

    // If payment is required, create an order
    $collect_online = get_field('collect_payment_online', $params['staffId']);
    if ($collect_online) {
        $order_id = spark_divine_create_woocommerce_order($booking_id, $params);
        if (is_wp_error($order_id)) {
            wp_delete_post($booking_id, true);
            return $order_id;
        }
        update_post_meta($booking_id, 'order_id', $order_id);
        
        // Trigger booking created email
        do_action('spark_divine_booking_created', $booking_id, $order_id);
    } else {
        // If no payment required, booking is automatically confirmed
        do_action('spark_divine_booking_confirmed', $booking_id);
    }

    return array(
        'booking_id' => $booking_id,
        'order_id' => isset($order_id) ? $order_id : null,
        'requires_payment' => $collect_online
    );
}

function spark_divine_check_availability($staff_id, $service_id, $start_time, $duration) {
    global $wpdb;
    $availability_costs_table = $wpdb->prefix . 'divine_availability_costs';
    $bookings_table = $wpdb->prefix . 'divine_service_bookings';

    // Check if the time slot is within staff's availability
    $day_of_week = date('N', strtotime($start_time));
    $time = date('H:i:s', strtotime($start_time));
    
    $availability = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $availability_costs_table 
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
        WHERE staff_member = %d 
        AND ((service_date = %s AND service_time <= %s AND ADDTIME(service_time, SEC_TO_TIME(service_duration * 60)) > %s)
        OR (service_date = %s AND service_time < %s AND ADDTIME(service_time, SEC_TO_TIME(service_duration * 60)) >= %s))",
        $staff_id, date('Y-m-d', strtotime($start_time)), $time, $time,
        date('Y-m-d', strtotime($end_time)), date('H:i:s', strtotime($end_time)), date('H:i:s', strtotime($end_time))
    ));

    return $conflicting_bookings == 0;
}

function spark_divine_create_woocommerce_order($booking_id, $params) {
    $service = get_post($params['serviceId']);
    $staff = get_post($params['staffId']);
    $cost = get_field('cost', $params['serviceId']) * ($params['duration'] / 15);

    $order = wc_create_order();

    // Add the service as a line item
    $order->add_product(wc_get_product($params['serviceId']), 1, [
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
    update_post_meta($order->get_id(), '_service_date', date('Y-m-d', strtotime($params['start'])));
    update_post_meta($order->get_id(), '_service_time', date('H:i:s', strtotime($params['start'])));
    update_post_meta($order->get_id(), '_service_duration', $params['duration']);

    return $order->get_id();
}