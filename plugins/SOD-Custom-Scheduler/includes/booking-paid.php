<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SOD_Booking_Paid_Email')) :

class SOD_Booking_Paid_Email {
    private static $instance;

    public static function init() {
        add_action('woocommerce_loaded', array(__CLASS__, 'init_email'));
    }

    public static function init_email() {
        if (class_exists('WC_Email')) {
            self::$instance = new self();
        }
    }

    public function __construct() {
        if (!class_exists('WC_Email')) {
            return;
        }

        $this->id = 'sod_booking_paid';
        $this->title = __('Booking Paid', 'spark-divine-service');
        $this->description = __('This email is sent to the customer when a booking is paid.', 'spark-divine-service');
        $this->template_html = 'emails/customer-booking-paid.php';
        $this->template_plain = 'emails/plain/customer-booking-paid.php';
        $this->template_base = plugin_dir_path(__FILE__) . 'templates/';

        WC_Email::__construct();
    }

    public function trigger($booking_id, $order_id) {
        $this->object = get_post($booking_id);
        $this->order = wc_get_order($order_id);

        if (!$this->is_enabled() || !$this->object || !$this->order) {
            return;
        }

        $this->recipient = $this->order->get_billing_email();

        $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
    }

    public function get_content_html() {
        return wc_get_template_html($this->template_html, array(
            'booking' => $this->object,
            'order' => $this->order,
            'email_heading' => $this->get_heading(),
            'sent_to_admin' => false,
            'plain_text' => false,
            'email' => $this,
        ), '', $this->template_base);
    }

    public function get_content_plain() {
        return wc_get_template_html($this->template_plain, array(
            'booking' => $this->object,
            'order' => $this->order,
            'email_heading' => $this->get_heading(),
            'sent_to_admin' => false,
            'plain_text' => true,
            'email' => $this,
        ), '', $this->template_base);
    }

    public static function get_instance() {
        return self::$instance;
    }
}

endif;

SOD_Booking_Paid_Email::init();

return 'SOD_Booking_Paid_Email';