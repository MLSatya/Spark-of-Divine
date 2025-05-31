<?php
/**
 * Booking Emails Handler
 *
 * @package Spark_Of_Divine_Scheduler
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Class SOD_Emails_Handler
 * 
 * Handles registration and triggering of custom booking emails with WooCommerce
 */
class SOD_Emails_Handler {

    /**
     * Singleton instance
     *
     * @var SOD_Emails_Handler
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return SOD_Emails_Handler
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        // Register email classes with WooCommerce
        add_filter('woocommerce_email_classes', array($this, 'register_emails'));
        
        // Add email actions to triggers
        add_action('init', array($this, 'register_email_actions'));
        
        // Schedule reminder cron job on first run
        add_action('wp', array($this, 'schedule_reminder_cron'));
        
        // Hook the reminder sending action
        add_action('sod_send_booking_reminders', array($this, 'send_reminder_emails'));
        
        error_log('SOD: Booking emails initialized');
    }

    /**
     * Register our custom email classes with WooCommerce
     */
    public function register_emails($email_classes) {
        if (!class_exists('SOD_Booking_Email')) {
            require_once SOD_PLUGIN_PATH . 'includes/emails/class-sod-booking-email.php';
        }

        $email_types = [
            'created' => 'SOD_Booking_Created_Email',
            'confirmed' => 'SOD_Booking_Confirmed_Email',
            'updated' => 'SOD_Booking_Updated_Email',
            'canceled' => 'SOD_Booking_Canceled_Email',
            'paid' => 'SOD_Booking_Paid_Email',
            'reminder' => 'SOD_Booking_Reminder_Email'
        ];

        foreach ($email_types as $type => $class_name) {
            $file_name = 'class-sod-booking-' . $type . '-email.php';
            $file_path = SOD_PLUGIN_PATH . 'includes/emails/' . $file_name;
            
            if (!class_exists($class_name) && file_exists($file_path)) {
                require_once $file_path;
            } elseif (!class_exists($class_name)) {
                $this->define_email_class($type, $class_name);
            }
            
            $email_classes[$class_name] = new $class_name();
        }
        
        return $email_classes;
    }

    /**
     * Define email class inline as a fallback
     */
    private function define_email_class($type, $class_name) {
        $definitions = [
            'created' => [
                'id' => 'sod_booking_created',
                'title' => __('Booking Created', 'spark-of-divine-scheduler'),
                'description' => __('Sent to customers when a booking is created.', 'spark-of-divine-scheduler'),
                'template_html' => 'emails/customer-booking-created.php',
                'template_plain' => 'emails/plain/customer-booking-created.php',
                'subject' => __('Your Booking Has Been Created', 'spark-of-divine-scheduler'),
                'heading' => __('Booking Created', 'spark-of-divine-scheduler')
            ],
            'confirmed' => [
                'id' => 'sod_booking_confirmed',
                'title' => __('Booking Confirmed', 'spark-of-divine-scheduler'),
                'description' => __('Sent to customers when a booking is confirmed.', 'spark-of-divine-scheduler'),
                'template_html' => 'emails/customer-booking-confirmed.php',
                'template_plain' => 'emails/plain/customer-booking-confirmed.php',
                'subject' => __('Your Booking Is Confirmed', 'spark-of-divine-scheduler'),
                'heading' => __('Booking Confirmed', 'spark-of-divine-scheduler')
            ],
            'updated' => [
                'id' => 'sod_booking_updated',
                'title' => __('Booking Updated', 'spark-of-divine-scheduler'),
                'description' => __('Sent to customers when a booking is updated.', 'spark-of-divine-scheduler'),
                'template_html' => 'emails/customer-booking-updated.php',
                'template_plain' => 'emails/plain/customer-booking-updated.php',
                'subject' => __('Your Booking Has Been Updated', 'spark-of-divine-scheduler'),
                'heading' => __('Booking Updated', 'spark-of-divine-scheduler')
            ],
            'canceled' => [
                'id' => 'sod_booking_canceled',
                'title' => __('Booking Canceled', 'spark-of-divine-scheduler'),
                'description' => __('Sent to customers when a booking is canceled.', 'spark-of-divine-scheduler'),
                'template_html' => 'emails/customer-booking-canceled.php',
                'template_plain' => 'emails/plain/customer-booking-canceled.php',
                'subject' => __('Your Booking Has Been Canceled', 'spark-of-divine-scheduler'),
                'heading' => __('Booking Canceled', 'spark-of-divine-scheduler')
            ],
            'paid' => [
                'id' => 'sod_booking_paid',
                'title' => __('Booking Paid', 'spark-of-divine-scheduler'),
                'description' => __('Sent to customers when a booking payment is received.', 'spark-of-divine-scheduler'),
                'template_html' => 'emails/customer-booking-paid.php',
                'template_plain' => 'emails/plain/customer-booking-paid.php',
                'subject' => __('Payment Received for Your Booking', 'spark-of-divine-scheduler'),
                'heading' => __('Booking Payment Confirmed', 'spark-of-divine-scheduler')
            ],
            'reminder' => [
                'id' => 'sod_booking_reminder',
                'title' => __('Booking Reminder', 'spark-of-divine-scheduler'),
                'description' => __('Sent to customers as a reminder before their booking.', 'spark-of-divine-scheduler'),
                'template_html' => 'emails/customer-booking-reminder.php',
                'template_plain' => 'emails/plain/customer-booking-reminder.php',
                'subject' => __('Reminder: Your Upcoming Booking', 'spark-of-divine-scheduler'),
                'heading' => __('Booking Reminder', 'spark-of-divine-scheduler')
            ]
        ];

        if (isset($definitions[$type])) {
            eval("class $class_name extends SOD_Booking_Email {
                public function __construct() {
                    \$this->id = '{$definitions[$type]['id']}';
                    \$this->title = '{$definitions[$type]['title']}';
                    \$this->description = '{$definitions[$type]['description']}';
                    \$this->template_html = '{$definitions[$type]['template_html']}';
                    \$this->template_plain = '{$definitions[$type]['template_plain']}';
                    \$this->subject = '{$definitions[$type]['subject']}';
                    \$this->heading = '{$definitions[$type]['heading']}';
                    parent::__construct();
                }
            }");
        }
    }

    /**
     * Register email notification triggers
     */
    public function register_email_actions() {
        add_action('sod_booking_status_created', array($this, 'trigger_created_email'), 10, 1);
        add_action('sod_booking_status_confirmed', array($this, 'trigger_confirmed_email'), 10, 1);
        add_action('sod_booking_status_updated', array($this, 'trigger_updated_email'), 10, 1);
        add_action('sod_booking_status_canceled', array($this, 'trigger_canceled_email'), 10, 1);
        add_action('sod_booking_status_paid', array($this, 'trigger_paid_email'), 10, 1);
    }

    /**
     * Trigger email using WooCommerce email system
     */
    private function trigger_email($email_id, $booking_id) {
        if (!$booking_id) {
            error_log("SOD: No booking ID provided for email trigger: $email_id");
            return;
        }

        $email = wc_get_email($email_id);
        if ($email && $email->is_enabled()) {
            $email->trigger($booking_id);
            error_log("SOD: Triggered $email_id email for booking $booking_id");
        } else {
            error_log("SOD: Email $email_id is not enabled or not found for booking $booking_id");
        }
    }

    /**
     * Trigger created email
     */
    public function trigger_created_email($booking_id) {
        $this->trigger_email('sod_booking_created', $booking_id);
    }

    /**
     * Trigger confirmed email
     */
    public function trigger_confirmed_email($booking_id) {
        $this->trigger_email('sod_booking_confirmed', $booking_id);
    }

    /**
     * Trigger updated email
     */
    public function trigger_updated_email($booking_id) {
        $this->trigger_email('sod_booking_updated', $booking_id);
    }

    /**
     * Trigger canceled email
     */
    public function trigger_canceled_email($booking_id) {
        $this->trigger_email('sod_booking_canceled', $booking_id);
    }

    /**
     * Trigger paid email
     */
    public function trigger_paid_email($booking_id) {
        $this->trigger_email('sod_booking_paid', $booking_id);
    }

    /**
     * Schedule reminder cron job
     */
    public function schedule_reminder_cron() {
        if (!wp_next_scheduled('sod_send_booking_reminders')) {
            wp_schedule_event(time(), 'hourly', 'sod_send_booking_reminders');
            error_log('SOD: Scheduled booking reminder cron job');
        }
    }

    /**
     * Send reminder emails
     */
    public function send_reminder_emails() {
        global $wpdb;

        // Define the reminder window (24-48 hours from now)
        $now = current_time('timestamp');
        $start_window = date('Y-m-d H:i:s', strtotime('+24 hours', $now));
        $end_window = date('Y-m-d H:i:s', strtotime('+48 hours', $now));

        // Query bookings in the reminder window with confirmed/paid status
        $bookings = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.ID, pm.meta_value AS booking_datetime
                FROM $wpdb->posts p
                INNER JOIN $wpdb->postmeta pm ON p.ID = pm.post_id AND pm.meta_key = 'booking_date'
                INNER JOIN $wpdb->postmeta pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = 'booking_status'
                LEFT JOIN $wpdb->postmeta pm_reminder ON p.ID = pm_reminder.post_id AND pm_reminder.meta_key = 'reminder_sent'
                WHERE p.post_type = 'sod_booking'
                AND pm_status.meta_value IN ('confirmed', 'paid')
                AND CONCAT(pm.meta_value, ' ', COALESCE((SELECT meta_value FROM $wpdb->postmeta WHERE post_id = p.ID AND meta_key = 'booking_time'), '')) BETWEEN %s AND %s
                AND pm_reminder.meta_value IS NULL",
                $start_window,
                $end_window
            )
        );

        if (!empty($bookings)) {
            foreach ($bookings as $booking) {
                $this->trigger_email('sod_booking_reminder', $booking->ID);
                // Mark as reminded to prevent duplicates
                update_post_meta($booking->ID, 'reminder_sent', current_time('mysql'));
                error_log("SOD: Reminder sent for booking {$booking->ID} scheduled at {$booking->booking_datetime}");
            }
        } else {
            error_log('SOD: No bookings found for reminder window');
        }
    }

    /**
     * Clear scheduled cron on deactivation
     */
    public static function deactivate() {
        wp_clear_scheduled_hook('sod_send_booking_reminders');
        error_log('SOD: Cleared booking reminder cron job');
    }
}