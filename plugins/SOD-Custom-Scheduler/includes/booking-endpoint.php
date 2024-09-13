<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

add_action('rest_api_init', function () {
    register_rest_route('spark-divine/v1', '/create-booking', array(
        'methods' => 'POST',
        'callback' => 'spark_divine_handle_booking_submission',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ));
});

function spark_divine_handle_booking_submission($request) {
    $params = $request->get_params();
    
    // Validate parameters
    if (!isset($params['serviceId'], $params['staffId'], $params['start'], $params['duration'])) {
        return new WP_Error('missing_params', 'Missing required parameters', array('status' => 400));
    }

    // Call the booking creation function from booking-handler.php
    $result = spark_divine_create_booking($params);

    if (is_wp_error($result)) {
        return $result;
    }

    return new WP_REST_Response($result, 200);
}