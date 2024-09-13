<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SOD_Booking_Created_Email')) :

class SOD_Booking_Created_Email {
    private static $instance;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Don't do anything here, we'll initialize when needed
    }

    public function init() {
        if (!class_exists('WC_Email')) {
            return;
        }

        $this->id = 'sod_booking_created';
        $this->title = __('Booking Created', 'spark-divine-service');
        $this->description = __('This email is sent to the customer when a new booking is created.', 'spark-divine-service');
        $this->template_html = 'emails/customer-booking-created.php';
        $this->template_plain = 'emails/plain/customer-booking-created.php';
        $this->template_base = plugin_dir_path(__FILE__) . 'templates/';
    }

    public function trigger($booking_id, $order_id = null) {
        if (!class_exists('WC_Email')) {
            return;
        }

        $this->init();

        $email = new WC_Email();
        $email->id = $this->id;
        $email->title = $this->title;
        $email->description = $this->description;

        $booking = get_post($booking_id);
        $order = $order_id ? wc_get_order($order_id) : null;

        if (!$booking) {
            return;
        }

        $recipient = get_post_meta($booking_id, 'client_email', true);

        $email->send($recipient, $this->get_subject($booking), $this->get_content($booking, $order), $this->get_headers(), $this->get_attachments());
    }

    public function get_content($booking, $order) {
        ob_start();
        wc_get_template($this->template_html, array(
            'booking' => $booking,
            'order' => $order,
            'email_heading' => $this->get_heading($booking),
            'sent_to_admin' => false,
            'plain_text' => false,
            'email' => $this,
        ), '', $this->template_base);
        return ob_get_clean();
    }

    public function get_subject($booking) {
        return sprintf(__('New booking #%s', 'spark-divine-service'), $booking->ID);
    }

    public function get_heading($booking) {
        return sprintf(__('New booking #%s', 'spark-divine-service'), $booking->ID);
    }

    public function get_headers() {
        return "Content-Type: text/html\r\n";
    }

    public function get_attachments() {
        return array();
    }
}

endif;

return SOD_Booking_Created_Email::get_instance();