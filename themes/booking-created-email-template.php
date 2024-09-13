<?php
/*
Template Name: Booking Emails
*/

if (!defined('ABSPATH')) {
    exit;
}

do_action('woocommerce_email_header', $email_heading, $email);
?>

<p><?php printf(__('Hello %s,', 'spark-divine-service'), $booking->client_name); ?></p>
<p><?php _e('Your booking has been created successfully. Here are the details:', 'spark-divine-service'); ?></p>

<ul>
    <li><?php printf(__('Service: %s', 'spark-divine-service'), get_the_title($booking->service_id)); ?></li>
    <li><?php printf(__('Staff: %s', 'spark-divine-service'), get_the_title($booking->staff_id)); ?></li>
    <li><?php printf(__('Date: %s', 'spark-divine-service'), date_i18n(get_option('date_format'), strtotime($booking->service_date))); ?></li>
    <li><?php printf(__('Time: %s', 'spark-divine-service'), date_i18n(get_option('time_format'), strtotime($booking->service_time))); ?></li>
    <li><?php printf(__('Duration: %d minutes', 'spark-divine-service'), $booking->service_duration); ?></li>
</ul>

<?php if ($order) : ?>
    <p><?php _e('To complete your booking, please pay for your order:', 'spark-divine-service'); ?></p>
    <p><a href="<?php echo esc_url($order->get_checkout_payment_url()); ?>"><?php _e('Pay for your booking', 'spark-divine-service'); ?></a></p>
<?php endif; ?>

<p><?php _e('Thank you for choosing our services!', 'spark-divine-service'); ?></p>

<?php
do_action('woocommerce_email_footer', $email);
?>