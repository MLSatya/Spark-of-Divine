<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SOD_Booking_Updated_Email')) :

class SOD_Booking_Updated_Email {
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

        $this->id = 'sod_booking_updated';
        $this->title = __('Booking Updated', 'spark-divine-service');
        $this->description = __('This email is sent to the customer when a booking is updated.', 'spark-divine-service');
        $this->template_html = 'emails/customer-booking-updated.php';
        $this->template_plain = 'emails/plain/customer-booking-updated.php';
        $this->template_base = plugin_dir_path(__FILE__) . 'templates/';
    }

    public function trigger($booking_id) {
        if (!class_exists('WC_Email')) {
            return;
        }

        $this->init();

        $email = new WC_Email();
        $email->id = $this->id;
        $email->title = $this->title;
        $email->description = $this->description;

        $booking = get_post($booking_id);
        if (!$booking) {
            return;
        }

        $recipient = get_post_meta($booking_id, 'client_email', true);

        $email->send($recipient, $this->get_subject($booking), $this->get_content($booking), $this->get_headers(), $this->get_attachments());
    }

    public function get_content($booking) {
        ob_start();
        wc_get_template($this->template_html, array(
            'booking' => $booking,
            'email_heading' => $this->get_heading($booking),
            'sent_to_admin' => false,
            'plain_text' => false,
            'email' => $this,
        ), '', $this->template_base);
        return ob_get_clean();
    }

    public function get_subject($booking) {
        return sprintf(__('Your booking #%s has been updated', 'spark-divine-service'), $booking->ID);
    }

    public function get_heading($booking) {
        return sprintf(__('Booking Update: #%s', 'spark-divine-service'), $booking->ID);
    }

    public function get_headers() {
        return "Content-Type: text/html\r\n";
    }

    public function get_attachments() {
        return array();
    }
}

endif;

return SOD_Booking_Updated_Email::get_instance();