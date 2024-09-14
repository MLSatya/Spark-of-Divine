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
        
        // Hooks to trigger email notifications
        add_action('save_post_sod_booking', array($this, 'handle_booking_status_change'), 10, 3);
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
        $service_id = get_post_meta($post->ID, 'service_id', true);
        $staff_id = get_post_meta($post->ID, 'staff_id', true);
        $start_time = get_post_meta($post->ID, 'start_time', true);
        $duration = get_post_meta($post->ID, 'duration', true);
        $status = get_post_meta($post->ID, 'status', true);

        // Render fields
        echo '<label for="sod_service_id">Service:</label>';
        echo '<select id="sod_service_id" name="sod_service_id">';
        $this->render_services_dropdown($service_id);
        echo '</select><br>';

        echo '<label for="sod_staff_id">Staff:</label>';
        echo '<select id="sod_staff_id" name="sod_staff_id">';
        $this->render_staff_dropdown($staff_id);
        echo '</select><br>';

        echo '<label for="sod_start_time">Start Time:</label>';
        echo '<input type="datetime-local" id="sod_start_time" name="sod_start_time" value="' . esc_attr($start_time) . '"/><br>';

        echo '<label for="sod_duration">Duration (minutes):</label>';
        echo '<input type="number" id="sod_duration" name="sod_duration" value="' . esc_attr($duration) . '"/><br>';

        echo '<label for="sod_status">Status:</label>';
        echo '<select id="sod_status" name="sod_status">
                <option value="pending" ' . selected($status, 'pending', false) . '>Pending</option>
                <option value="confirmed" ' . selected($status, 'confirmed', false) . '>Confirmed</option>
                <option value="canceled" ' . selected($status, 'canceled', false) . '>Canceled</option>
              </select>';
    }

    // Render dropdown options for services
    private function render_services_dropdown($selected_service) {
        $services = get_posts(array('post_type' => 'sod_service', 'posts_per_page' => -1));
        foreach ($services as $service) {
            echo '<option value="' . $service->ID . '" ' . selected($selected_service, $service->ID, false) . '>' . $service->post_title . '</option>';
        }
    }

    // Render dropdown options for staff
    private function render_staff_dropdown($selected_staff) {
        $staff_members = get_posts(array('post_type' => 'sod_staff', 'posts_per_page' => -1));
        foreach ($staff_members as $staff) {
            echo '<option value="' . $staff->ID . '" ' . selected($selected_staff, $staff->ID, false) . '>' . $staff->post_title . '</option>';
        }
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
        global $wpdb;

        // Prepare data
        $booking_data = [
            'service_id' => isset($_POST['sod_service_id']) ? sanitize_text_field($_POST['sod_service_id']) : '',
            'staff_id' => isset($_POST['sod_staff_id']) ? sanitize_text_field($_POST['sod_staff_id']) : '',
            'start_time' => isset($_POST['sod_start_time']) ? sanitize_text_field($_POST['sod_start_time']) : '',
            'duration' => isset($_POST['sod_duration']) ? intval($_POST['sod_duration']) : 0,
            'status' => isset($_POST['sod_status']) ? sanitize_text_field($_POST['sod_status']) : 'pending'
        ];

        // Update post meta
        foreach ($booking_data as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }

        // Update or insert into a custom table
        $existing_booking = $wpdb->get_var($wpdb->prepare("SELECT booking_id FROM {$wpdb->prefix}sod_bookings WHERE booking_id = %d", $post_id));

        if ($existing_booking) {
            // Update booking in the custom table
            $wpdb->update("{$wpdb->prefix}sod_bookings", $booking_data, ['booking_id' => $post_id], ['%s', '%s', '%s', '%d', '%s'], ['%d']);
        } else {
            // Insert new booking
            $booking_data['booking_id'] = $post_id;
            $wpdb->insert("{$wpdb->prefix}sod_bookings", $booking_data, ['%d', '%s', '%s', '%s', '%d', '%s']);
        }
    }

    // Method to handle booking status change and trigger emails
    public function handle_booking_status_change($post_id, $post, $update) {
        if ($post->post_type != 'sod_booking') {
            return;
        }

        // Get the current status
        $status = get_post_meta($post_id, 'status', true);

        // Trigger appropriate emails based on status
        switch ($status) {
            case 'confirmed':
                do_action('spark_divine_booking_confirmed', $post_id);
                break;
            case 'canceled':
                do_action('spark_divine_booking_canceled', $post_id);
                break;
            // Add more cases as needed
        }

        // Schedule reminder email 24 hours before the booking
        $this->schedule_reminder_email($post_id);
    }

    // Schedule a reminder email 24 hours before the booking
    private function schedule_reminder_email($post_id) {
        $start_time = get_post_meta($post_id, 'start_time', true);
        $timestamp = strtotime($start_time) - 86400; // 24 hours before

        if ($timestamp > time()) {
            wp_schedule_single_event($timestamp, 'sod_send_reminder_email', array($post_id));
        }
    }

    // Method to save staff meta
    private function save_staff_meta($post_id) {
        global $wpdb;

        // Delete existing availability entries for this staff member in the custom table
        $wpdb->delete($wpdb->prefix . 'sod_staff_availability', ['staff_id' => $post_id], ['%d']);

        // Save new availability slots into the custom table
        if (isset($_POST['availability_day']) && is_array($_POST['availability_day'])) {
            $days = $_POST['availability_day'];
            $start_times = $_POST['availability_start'];
            $end_times = $_POST['availability_end'];
            
            foreach ($days as $index => $day) {
                // Insert new availability slots into the custom table
                $wpdb->insert($wpdb->prefix . 'sod_staff_availability', [
                    'staff_id' => $post_id,
                    'day_of_week' => sanitize_text_field($day),
                    'start_time' => sanitize_text_field($start_times[$index]),
                    'end_time' => sanitize_text_field($end_times[$index])
                ], [
                    '%d', '%s', '%s', '%s'
                ]);
            }
        }

        // Save accepts_cash in wp_postmeta as this data is simple and not part of the custom table
        $accepts_cash = isset($_POST['sod_staff_accepts_cash']) ? 'yes' : 'no';
        update_post_meta($post_id, 'sod_staff_accepts_cash', $accepts_cash);
    }

    // Method to save service meta
    private function save_service_meta($post_id) {
        global $wpdb;

        // Prepare data
        $price = isset($_POST['sod_service_price']) ? sanitize_text_field($_POST['sod_service_price']) : '';

        // Update or insert into the custom table
        $existing_service = $wpdb->get_var($wpdb->prepare("SELECT service_id FROM {$wpdb->prefix}sod_services WHERE service_id = %d", $post_id));

        if ($existing_service) {
            // Update the service in the custom table
            $wpdb->update("{$wpdb->prefix}sod_services", ['price' => $price], ['service_id' => $post_id], ['%s'], ['%d']);
        } else {
            // Insert new service
            $wpdb->insert("{$wpdb->prefix}sod_services", ['service_id' => $post_id, 'price' => $price], ['%d', '%s']);
        }
    }

    // Method to save customer meta
    private function save_customer_meta($post_id) {
        global $wpdb;

        // Prepare data
        $phone = isset($_POST['sod_customer_phone']) ? sanitize_text_field($_POST['sod_customer_phone']) : '';

        // Check if customer exists in the custom table
        $existing_customer = $wpdb->get_var($wpdb->prepare("SELECT customer_id FROM {$wpdb->prefix}sod_customers WHERE customer_id = %d", $post_id));

        if ($existing_customer) {
            // Update customer in the custom table
            $wpdb->update("{$wpdb->prefix}sod_customers", ['phone' => $phone], ['customer_id' => $post_id], ['%s'], ['%d']);
        } else {
            // Insert new customer
            $wpdb->insert("{$wpdb->prefix}sod_customers", ['customer_id' => $post_id, 'phone' => $phone], ['%d', '%s']);
        }
    }   
}

// Initialize custom fields
new SOD_Custom_Fields();