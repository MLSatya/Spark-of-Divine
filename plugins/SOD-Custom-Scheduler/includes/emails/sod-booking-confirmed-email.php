<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SOD_Booking_Confirmed_Email')) :

class SOD_Booking_Confirmed_Email extends WC_Email {
    private static $instance;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->id = 'sod_booking_confirmed';
        $this->title = __('Booking Confirmed', 'spark-divine-service');
        $this->description = __('This email is sent to the customer when a booking is confirmed.', 'spark-divine-service');
        $this->template_html = 'emails/customer-booking-confirmed.php';
        $this->template_plain = 'emails/plain/customer-booking-confirmed.php';
        $this->template_base = plugin_dir_path(__FILE__) . 'templates/';

        // Call parent constructor
        parent::__construct();

        // Trigger this email
        add_action('spark_divine_booking_confirmed', array($this, 'trigger'), 10, 2);
    }

    public function trigger($booking_id, $order_id = null) {
        if (!class_exists('WC_Email')) {
            return;
        }

        if (!$booking_id) {
            return;
        }

        $this->object = get_post($booking_id);
        $this->order = $order_id ? wc_get_order($order_id) : null;

        // Get recipient email from booking post meta
        $this->recipient = get_post_meta($booking_id, 'client_email', true);

        if (!$this->is_enabled() || !$this->recipient) {
            return;
        }

        // Set subject and heading
        $this->subject = $this->get_subject();
        $this->heading = $this->get_heading();

        // Send the email
        $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
    }

    public function get_content_html() {
        return wc_get_template_html($this->template_html, array(
            'booking'       => $this->object,
            'order'         => $this->order,
            'email_heading' => $this->get_heading(),
            'sent_to_admin' => false,
            'plain_text'    => false,
            'email'         => $this,
        ), '', $this->template_base);
    }

    public function get_content_plain() {
        return wc_get_template_html($this->template_plain, array(
            'booking'       => $this->object,
            'order'         => $this->order,
            'email_heading' => $this->get_heading(),
            'sent_to_admin' => false,
            'plain_text'    => true,
            'email'         => $this,
        ), '', $this->template_base);
    }

    public function get_subject() {
        return sprintf(__('Your booking #%s has been confirmed', 'spark-divine-service'), $this->object->ID);
    }

    public function get_heading() {
        return sprintf(__('Booking Confirmed - #%s', 'spark-divine-service'), $this->object->ID);
    }

    public function get_headers() {
        return "Content-Type: text/html\r\n";
    }

    public function get_attachments() {
        return array();
    }
}

endif;

return SOD_Booking_Confirmed_Email::get_instance();