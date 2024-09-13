<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class SOD_Booking_Emails {
    public function __construct() {
        add_action('woocommerce_init', array($this, 'init_emails'));
    }

    public function init_emails() {
        add_filter('woocommerce_email_classes', array($this, 'add_booking_email_classes'));
        add_action('spark_divine_booking_created', array($this, 'trigger_booking_created_email'), 10, 2);
        
        // Placeholder actions for future email types
        // Uncomment and implement these as you create the corresponding classes
        // add_action('spark_divine_booking_confirmed', array($this, 'trigger_booking_confirmed_email'), 10, 2);
        // add_action('spark_divine_booking_updated', array($this, 'trigger_booking_updated_email'), 10, 1);
        // add_action('spark_divine_booking_canceled', array($this, 'trigger_booking_canceled_email'), 10, 1);
        // add_action('woocommerce_order_status_completed', array($this, 'trigger_booking_paid_email'), 10, 1);
    }

    public function add_booking_email_classes($email_classes) {
        $email_classes['SOD_Booking_Created_Email'] = include 'booking-created.php';
        
        // Placeholder inclusions for future email classes
        // Uncomment these as you create the corresponding files
        // $email_classes['SOD_Booking_Confirmed_Email'] = include 'booking-confirmed.php';
        // $email_classes['SOD_Booking_Updated_Email'] = include 'booking-updated.php';
        // $email_classes['SOD_Booking_Canceled_Email'] = include 'booking-canceled.php';
        // $email_classes['SOD_Booking_Paid_Email'] = include 'booking-paid.php';
        
        return $email_classes;
    }

    public function trigger_booking_created_email($booking_id, $order_id = null) {
        $email = WC()->mailer()->get_emails()['SOD_Booking_Created_Email'];
        if ($email) {
            $email->trigger($booking_id, $order_id);
        }
    }

    // Placeholder methods for future email triggers
    // Implement these as you create the corresponding email classes
    /*
    public function trigger_booking_confirmed_email($booking_id, $order_id) {
        // Implementation
    }

    public function trigger_booking_updated_email($booking_id) {
        // Implementation
    }

    public function trigger_booking_canceled_email($booking_id) {
        // Implementation
    }

    public function trigger_booking_paid_email($order_id) {
        // Implementation
    }
    */
}

new SOD_Booking_Emails();