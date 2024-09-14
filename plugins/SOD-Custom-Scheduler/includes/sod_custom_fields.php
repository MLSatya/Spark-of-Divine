<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class SOD_Custom_Fields {
    public function __construct() {
        // Register meta boxes
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        // Save meta box data
        add_action('save_post', array($this, 'save_meta_box_data'));
    }

    // Method to add meta boxes for custom post types
    public function add_meta_boxes() {
        // Booking Meta Box
        add_meta_box(
            'sod_booking_details',
            __('Booking Details', 'textdomain'),
            array($this, 'booking_details_callback'),
            'sod_booking',
            'normal',
            'default'
        );

        // Staff Meta Box
        add_meta_box(
            'sod_staff_details',
            __('Staff Details', 'textdomain'),
            array($this, 'staff_details_callback'),
            'sod_staff',
            'normal',
            'default'
        );

        // Service Meta Box
        add_meta_box(
            'sod_service_details',
            __('Service Details', 'textdomain'),
            array($this, 'service_details_callback'),
            'sod_service',
            'normal',
            'default'
        );

        // Customer Meta Box
        add_meta_box(
            'sod_customer_details',
            __('Customer Details', 'textdomain'),
            array($this, 'customer_details_callback'),
            'sod_customer',
            'normal',
            'default'
        );
    }

    // Callback to display fields in the booking meta box
    public function booking_details_callback($post) {
        // Add nonce for security
        wp_nonce_field('sod_booking_nonce_action', 'sod_booking_nonce');

        // Retrieve current meta data
        $booking_date = get_post_meta($post->ID, 'sod_booking_date', true);
        $booking_time = get_post_meta($post->ID, 'sod_booking_time', true);
        $duration = get_post_meta($post->ID, 'sod_booking_duration', true);
        $payment_method = get_post_meta($post->ID, 'sod_booking_payment_method', true);

        // Render fields
        echo '<label for="sod_booking_date">' . __('Date:', 'textdomain') . '</label>';
        echo '<input type="date" id="sod_booking_date" name="sod_booking_date" value="' . esc_attr($booking_date) . '" /><br>';

        echo '<label for="sod_booking_time">' . __('Time:', 'textdomain') . '</label>';
        echo '<input type="time" id="sod_booking_time" name="sod_booking_time" value="' . esc_attr($booking_time) . '" /><br>';

        echo '<label for="sod_booking_duration">' . __('Duration (minutes):', 'textdomain') . '</label>';
        echo '<input type="number" id="sod_booking_duration" name="sod_booking_duration" value="' . esc_attr($duration) . '" /><br>';

        echo '<label for="sod_booking_payment_method">' . __('Payment Method:', 'textdomain') . '</label>';
        echo '<select id="sod_booking_payment_method" name="sod_booking_payment_method">
                <option value="digital" ' . selected($payment_method, 'digital', false) . '>Digital</option>
                <option value="cash" ' . selected($payment_method, 'cash', false) . '>Cash</option>
              </select><br>';
    }

    // Callback to display fields in the staff meta box
    public function staff_details_callback($post) {
        wp_nonce_field('sod_staff_nonce_action', 'sod_staff_nonce');

        // Retrieve current meta data
        $availability = get_post_meta($post->ID, 'sod_staff_availability', true);
        $accepts_cash = get_post_meta($post->ID, 'sod_staff_accepts_cash', true);

        // Render fields
        echo '<label for="sod_staff_availability">' . __('Availability:', 'textdomain') . '</label>';
        echo '<textarea id="sod_staff_availability" name="sod_staff_availability">' . esc_textarea($availability) . '</textarea><br>';

        echo '<label for="sod_staff_accepts_cash">' . __('Accepts Cash Payments:', 'textdomain') . '</label>';
        echo '<input type="checkbox" id="sod_staff_accepts_cash" name="sod_staff_accepts_cash" ' . checked($accepts_cash, 'yes', false) . ' value="yes" /><br>';
    }

    // Callback to display fields in the service meta box
    public function service_details_callback($post) {
        wp_nonce_field('sod_service_nonce_action', 'sod_service_nonce');

        // Retrieve current meta data
        $price = get_post_meta($post->ID, 'sod_service_price', true);

        // Render fields
        echo '<label for="sod_service_price">' . __('Price:', 'textdomain') . '</label>';
        echo '<input type="number" step="0.01" id="sod_service_price" name="sod_service_price" value="' . esc_attr($price) . '" /><br>';
    }

    // Callback to display fields in the customer meta box
    public function customer_details_callback($post) {
        wp_nonce_field('sod_customer_nonce_action', 'sod_customer_nonce');

        // Retrieve current meta data
        $phone = get_post_meta($post->ID, 'sod_customer_phone', true);

        // Render fields
        echo '<label for="sod_customer_phone">' . __('Phone:', 'textdomain') . '</label>';
        echo '<input type="tel" id="sod_customer_phone" name="sod_customer_phone" value="' . esc_attr($phone) . '" /><br>';
    }

    // Method to save meta box data
    public function save_meta_box_data($post_id) {
        // Check nonces for security
        if (isset($_POST['sod_booking_nonce']) && wp_verify_nonce($_POST['sod_booking_nonce'], 'sod_booking_nonce_action')) {
            $this->save_booking_meta($post_id);
        }

        if (isset($_POST['sod_staff_nonce']) && wp_verify_nonce($_POST['sod_staff_nonce'], 'sod_staff_nonce_action')) {
            $this->save_staff_meta($post_id);
        }

        if (isset($_POST['sod_service_nonce']) && wp_verify_nonce($_POST['sod_service_nonce'], 'sod_service_nonce_action')) {
            $this->save_service_meta($post_id);
        }

        if (isset($_POST['sod_customer_nonce']) && wp_verify_nonce($_POST['sod_customer_nonce'], 'sod_customer_nonce_action')) {
            $this->save_customer_meta($post_id);
        }
    }

    // Method to save booking meta
    private function save_booking_meta($post_id) {
        if (isset($_POST['sod_booking_date'])) {
            update_post_meta($post_id, 'sod_booking_date', sanitize_text_field($_POST['sod_booking_date']));
        }
        if (isset($_POST['sod_booking_time'])) {
            update_post_meta($post_id, 'sod_booking_time', sanitize_text_field($_POST['sod_booking_time']));
        }
        if (isset($_POST['sod_booking_duration'])) {
            update_post_meta($post_id, 'sod_booking_duration', sanitize_text_field($_POST['sod_booking_duration']));
        }
        if (isset($_POST['sod_booking_payment_method'])) {
            update_post_meta($post_id, 'sod_booking_payment_method', sanitize_text_field($_POST['sod_booking_payment_method']));
        }
    }

    // Method to save staff meta
    private function save_staff_meta($post_id) {
        if (isset($_POST['sod_staff_availability'])) {
            update_post_meta($post_id, 'sod_staff_availability', sanitize_textarea_field($_POST['sod_staff_availability']));
        }
        $accepts_cash = isset($_POST['sod_staff_accepts_cash']) ? 'yes' : 'no';
        update_post_meta($post_id, 'sod_staff_accepts_cash', $accepts_cash);
    }

    // Method to save service meta
    private function save_service_meta($post_id) {
        if (isset($_POST['sod_service_price'])) {
            update_post_meta($post_id, 'sod_service_price', sanitize_text_field($_POST['sod_service_price']));
        }
    }

    // Method to save customer meta
    private function save_customer_meta($post_id) {
        if (isset($_POST['sod_customer_phone'])) {
            update_post_meta($post_id, 'sod_customer_phone', sanitize_text_field($_POST['sod_customer_phone']));
        }
    }
}
    