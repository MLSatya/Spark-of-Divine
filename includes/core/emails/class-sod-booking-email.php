<?php
/**
 * SOD Booking Email Base Class
 *
 * @package Spark_Of_Divine_Scheduler
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists('SOD_Booking_Email')) :

/**
 * Base class for all booking email notifications
 */
abstract class SOD_Booking_Email extends WC_Email {
    /**
     * Constructor
     */
    public function __construct() {
        // Set defaults
        $this->customer_email = true;
        
        // Call parent constructor only after all properties are set
        parent::__construct();
        
        // Set default template base path
        // This allows theme overrides while falling back to plugin templates
        $this->template_base = SOD_PLUGIN_PATH . 'templates/';
    }
    
    /**
     * Trigger email based on booking ID
     */
    public function trigger($booking_id) {
        if (!$booking_id) {
            return;
        }

        $this->object = get_post($booking_id);
        if (!$this->object || 'sod_booking' !== $this->object->post_type) {
            error_log("SOD Email: Invalid booking ID {$booking_id}");
            return;
        }

        // Get recipient email from booking post meta
        $this->recipient = get_post_meta($booking_id, 'client_email', true);

        if (!$this->is_enabled() || !$this->recipient) {
            error_log("SOD Email: Email not enabled or recipient missing for booking {$booking_id}");
            return;
        }

        // Set subject and heading
        $this->subject = $this->get_subject();
        $this->heading = $this->get_heading();

        // Get booking details for email context
        $this->booking_data = $this->get_booking_data($booking_id);

        // Send the email
        $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        
        error_log("SOD Email: Sent {$this->id} email for booking {$booking_id} to {$this->recipient}");
    }

    /**
     * Get booking data for email context
     */
    protected function get_booking_data($booking_id) {
        return array(
            'id' => $booking_id,
            'date' => get_post_meta($booking_id, 'booking_date', true),
            'time' => get_post_meta($booking_id, 'booking_time', true),
            'service_name' => get_post_meta($booking_id, 'service_name', true),
            'staff_name' => get_post_meta($booking_id, 'staff_name', true),
            'client_name' => get_post_meta($booking_id, 'client_name', true),
            'client_email' => get_post_meta($booking_id, 'client_email', true),
            'client_phone' => get_post_meta($booking_id, 'client_phone', true),
            'status' => get_post_meta($booking_id, 'booking_status', true),
            'notes' => get_post_meta($booking_id, 'booking_notes', true),
            'price' => get_post_meta($booking_id, 'booking_price', true)
        );
    }

    /**
     * Get content HTML
     */
    public function get_content_html() {
        return wc_get_template_html($this->template_html, array(
            'booking' => $this->object,
            'booking_data' => $this->booking_data,
            'email_heading' => $this->get_heading(),
            'sent_to_admin' => false,
            'plain_text' => false,
            'email' => $this,
        ), '', $this->template_base);
    }

    /**
     * Get content plain
     */
    public function get_content_plain() {
        return wc_get_template_html($this->template_plain, array(
            'booking' => $this->object,
            'booking_data' => $this->booking_data,
            'email_heading' => $this->get_heading(),
            'sent_to_admin' => false,
            'plain_text' => true,
            'email' => $this,
        ), '', $this->template_base);
    }
}

endif;