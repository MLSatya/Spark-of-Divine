<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SOD_Booking_Canceled_Email')) :

class SOD_Booking_Canceled_Email {
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

        $this->id = 'sod_booking_canceled';
        $this->title = __('Booking Canceled', 'spark-divine-service');
        $this->description = __('This email is sent to the customer when a booking is canceled.', 'spark-divine-service');
        $this->template_html = 'emails/customer-booking-canceled.php';
        $this->template_plain = 'emails/plain/customer-booking-canceled.php';
        $this->template_base = plugin_dir_path(__FILE__) . 'templates/';

        WC_Email::__construct();
    }

    public function trigger($booking_id) {
        $this->object = get_post($booking_id);

        if (!$this->is_enabled() || !$this->object) {
            return;
        }

        $this->recipient = get_post_meta($booking_id, 'client_email', true);

        $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
    }

    public function get_content_html() {
        return wc_get_template_html($this->template_html, array(
            'booking' => $this->object,
            'email_heading' => $this->get_heading(),
            'sent_to_admin' => false,
            'plain_text' => false,
            'email' => $this,
        ), '', $this->template_base);
    }

    public function get_content_plain() {
        return wc_get_template_html($this->template_plain, array(
            'booking' => $this->object,
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

SOD_Booking_Canceled_Email::init();

return 'SOD_Booking_Canceled_Email';