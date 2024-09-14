<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class SOD_Booking_Emails {
    public function __construct() {
        add_action('woocommerce_init', array($this, 'init_emails'));
        add_action('init', array($this, 'schedule_email_reminder')); // Schedule email reminder on plugin load
    }

    // Initialize email classes and hooks
    public function init_emails() {
        add_filter('woocommerce_email_classes', array($this, 'add_booking_email_classes'));
        add_action('spark_divine_booking_created', array($this, 'trigger_booking_created_email'), 10, 2);
        add_action('spark_divine_booking_confirmed', array($this, 'trigger_booking_confirmed_email'), 10, 2);
        add_action('spark_divine_booking_updated', array($this, 'trigger_booking_updated_email'), 10, 1);
        add_action('spark_divine_booking_canceled', array($this, 'trigger_booking_canceled_email'), 10, 1);
    }

    // Add email classes to WooCommerce email handler
    public function add_booking_email_classes($email_classes) {
        $email_classes['SOD_Booking_Created_Email'] = include 'emails/sod-booking-created-email.php';
        $email_classes['SOD_Booking_Confirmed_Email'] = include 'emails/sod-booking-confirmed-email.php';
        $email_classes['SOD_Booking_Updated_Email'] = include 'emails/sod-booking-updated-email.php';
        $email_classes['SOD_Booking_Canceled_Email'] = include 'emails/sod-booking-canceled-email.php';
        
        return $email_classes;
    }

    // Schedule email reminder for bookings
    public function schedule_email_reminder() {
        if (!wp_next_scheduled('sod_send_email_reminder')) {
            wp_schedule_event(time(), 'hourly', 'sod_send_email_reminder');
        }
        add_action('sod_send_email_reminder', array($this, 'check_and_send_email_reminder'));
    }

    // Check and send email reminder
    public function check_and_send_email_reminder() {
        $args = array(
            'post_type' => 'sod_booking',
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => 'start_time',
                    'value' => date('Y-m-d H:i:s', strtotime('+24 hours')),
                    'compare' => '<=',
                    'type' => 'DATETIME',
                ),
                array(
                    'key' => 'status',
                    'value' => 'confirmed',
                ),
            ),
        );

        $bookings = get_posts($args);
        
        foreach ($bookings as $booking) {
            $this->trigger_booking_confirmed_email($booking->ID);
        }
    }

    // Trigger the "Booking Created" email
    public function trigger_booking_created_email($booking_id, $order_id = null) {
        $email = WC()->mailer()->get_emails()['SOD_Booking_Created_Email'];
        if ($email) {
            $email->trigger($booking_id, $order_id);
        }
    }

    // Trigger the "Booking Confirmed" email
    public function trigger_booking_confirmed_email($booking_id, $order_id = null) {
        $email = WC()->mailer()->get_emails()['SOD_Booking_Confirmed_Email'];
        if ($email) {
            $email->trigger($booking_id, $order_id);
        }
    }

    // Trigger the "Booking Updated" email
    public function trigger_booking_updated_email($booking_id) {
        $email = WC()->mailer()->get_emails()['SOD_Booking_Updated_Email'];
        if ($email) {
            $email->trigger($booking_id);
        }
    }

    // Trigger the "Booking Canceled" email
    public function trigger_booking_canceled_email($booking_id) {
        $email = WC()->mailer()->get_emails()['SOD_Booking_Canceled_Email'];
        if ($email) {
            $email->trigger($booking_id);
        }
    }
}

new SOD_Booking_Emails();