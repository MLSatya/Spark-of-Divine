<?php
/**
 * Customer booking paid email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/customer-booking-paid.php.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package Spark_Of_Divine_Scheduler
 * @version 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action('woocommerce_email_header', $email_heading, $email);
?>

<p><?php printf(__('Hi %s,', 'spark-of-divine-scheduler'), $booking_data['client_name']); ?></p>
<p><?php _e('We have received your payment for the following booking.', 'spark-of-divine-scheduler'); ?></p>

<h2><?php _e('Booking details', 'spark-of-divine-scheduler'); ?></h2>

<ul>
    <li><?php printf(__('Service: %s', 'spark-of-divine-scheduler'), $booking_data['service_name']); ?></li>
    <li><?php printf(__('Date: %s', 'spark-of-divine-scheduler'), $booking_data['date']); ?></li>
    <li><?php printf(__('Time: %s', 'spark-of-divine-scheduler'), $booking_data['time']); ?></li>
    <li><?php printf(__('Staff: %s', 'spark-of-divine-scheduler'), $booking_data['staff_name']); ?></li>
    <?php if (!empty($booking_data['price'])) : ?>
    <li><?php printf(__('Price: %s', 'spark-of-divine-scheduler'), wc_price($booking_data['price'])); ?></li>
    <?php endif; ?>
</ul>

<p><?php _e('Thank you for your payment. We look forward to seeing you!', 'spark-of-divine-scheduler'); ?></p>

<?php
/**
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email);