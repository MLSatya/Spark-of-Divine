<?php
// File: includes/class-sod-booking-rest-api.php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class SOD_Booking_Rest_API {
    private static $instance;

    // Singleton pattern to ensure only one instance is used
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Initialize REST API routes
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    // Register REST API routes
    public function register_routes() {
        register_rest_route('spark-divine/v1', '/events', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_events'),
            'permission_callback' => '__return_true', // Open access, modify if needed
        ));

        register_rest_route('spark-divine/v1', '/create-booking', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_booking'),
            'permission_callback' => function() {
                return is_user_logged_in(); // Only allow logged-in users to create bookings
            },
        ));
    }

    // Handle the GET request to fetch available events (timeslots)
    public function get_events($request) {
        $staff_id = $request->get_param('staff');
        $service_id = $request->get_param('service');
        $category_id = $request->get_param('category');
        $start = $request->get_param('start');
        $end = $request->get_param('end');

        $args = array(
            'post_type' => 'staff',
            'posts_per_page' => -1,
        );

        if ($staff_id) {
            $args['include'] = array($staff_id);
        }

        $staff_query = new WP_Query($args);
        $events = array();

        if ($staff_query->have_posts()) {
            while ($staff_query->have_posts()) {
                $staff_query->the_post();
                $current_staff_id = get_the_ID();
                $availability = get_field('availability', $current_staff_id);
                $linked_services = get_field('linked_services', $current_staff_id);
                $accepts_payments = get_field('accepts_payments', $current_staff_id);

                if ($availability && $linked_services) {
                    foreach ($linked_services as $service) {
                        if (!$service_id || $service_id == $service->ID) {
                            $service_category = wp_get_post_terms($service->ID, 'service_category', array('fields' => 'ids'));
                            if (!$category_id || in_array($category_id, $service_category)) {
                                $cost = get_field('cost', $service->ID);
                                $max_duration = get_field('duration', $service->ID);

                                foreach ($availability as $slot) {
                                    $day_of_week = date('N', strtotime($slot['day_of_the_week']));
                                    $start_time = $slot['start_time'];
                                    $end_time = $slot['end_time'];

                                    $current_date = new DateTime($start);
                                    $end_date = new DateTime($end);

                                    while ($current_date <= $end_date) {
                                        if ($current_date->format('N') == $day_of_week) {
                                            $event_start = $current_date->format('Y-m-d') . ' ' . $start_time;
                                            $event_end = $current_date->format('Y-m-d') . ' ' . $end_time;

                                            $events[] = array(
                                                'title' => get_the_title($current_staff_id) . ' - ' . $service->post_title,
                                                'start' => $event_start,
                                                'end' => $event_end,
                                                'staffId' => $current_staff_id,
                                                'serviceId' => $service->ID,
                                                'extendedProps' => array(
                                                    'staffName' => get_the_title($current_staff_id),
                                                    'serviceName' => $service->post_title,
                                                    'cost' => $cost,
                                                    'maxDuration' => $max_duration,
                                                    'acceptsPayments' => $accepts_payments,
                                                ),
                                            );
                                        }
                                        $current_date->modify('+1 day');
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        wp_reset_postdata();

        return new WP_REST_Response($events, 200);
    }

    // Handle the POST request to create a booking
    public function create_booking($request) {
        $params = $request->get_params();
        
        // Validate parameters
        if (!isset($params['serviceId'], $params['staffId'], $params['start'], $params['duration'])) {
            return new WP_Error('missing_params', 'Missing required parameters', array('status' => 400));
        }

        // Call the booking creation method from SOD_Booking_Handler
        $result = SOD_Booking_Handler::get_instance()->create_booking($params);

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response($result, 200);
    }
}

// Initialize the REST API class
SOD_Booking_Rest_API::get_instance();