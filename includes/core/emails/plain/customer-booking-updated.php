<?php
/**
 * Customer booking updated email (plain text)
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/customer-booking-updated.php.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package Spark_Of_Divine_Scheduler
 * @version 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

echo "= " . $email_heading . " =\n\n";

echo sprintf(__('Hi %s,', 'spark-of-divine-scheduler'), $booking_data['client_name']) . "\n\n";
echo __('Your booking has been updated.', 'spark-of-divine-scheduler') . "\n\n";

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo __('Updated booking details', 'spark-of-divine-scheduler') . "\n\n";

echo sprintf(__('Service: %s', 'spark-of-divine-scheduler'), $booking_data['service_name']) . "\n";
echo sprintf(__('Date: %s', 'spark-of-divine-scheduler'), $booking_data['date']) . "\n";
echo sprintf(__('Time: %s', 'spark-of-divine-scheduler'), $booking_data['time']) . "\n";
echo sprintf(__('Staff: %s', 'spark-of-divine-scheduler'), $booking_data['staff_name']) . "\n\n";

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo __('If you did not request this change or have any questions, please contact us.', 'spark-of-divine-scheduler') . "\n\n";

echo apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text'));