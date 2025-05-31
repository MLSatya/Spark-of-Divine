<?php
if (!defined('ABSPATH')) {
    exit;
}

class SOD_Bookings_Admin_Page {
    private $db_access;

    public function __construct($db_access) {
        $this->db_access = $db_access;
        add_action('admin_menu', [$this, 'add_admin_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    public function add_admin_menus() {
        if (!current_user_can('administrator')) {
            return;
        }
        add_menu_page(
            __('Manage Bookings', 'spark-of-divine-scheduler'),
            __('Bookings', 'spark-of-divine-scheduler'),
            'manage_options',
            'sod-bookings',
            [$this, 'display_bookings_page'],
            'dashicons-calendar-alt',
            5
        );
        add_submenu_page(
            'sod-bookings',
            __('All Bookings', 'spark-of-divine-scheduler'),
            __('All Bookings', 'spark-of-divine-scheduler'),
            'manage_options',
            'sod-bookings',
            [$this, 'display_bookings_page']
        );
        add_submenu_page(
            'sod-bookings',
            __('Add New Booking', 'spark-of-divine-scheduler'),
            __('Add New', 'spark-of-divine-scheduler'),
            'manage_options',
            'sod-new-booking',
            [$this, 'display_new_booking_page']
        );
        add_submenu_page(
            'sod-bookings',
            __('Staff Availability', 'spark-of-divine-scheduler'),
            __('Staff Availability', 'spark-of-divine-scheduler'),
            'manage_options',
            'sod-staff-availability',
            [$this, 'display_staff_availability_page']
        );
    }

    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'sod-') === false) {
            return;
        }
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_script('jquery-ui-timepicker', SOD_PLUGIN_URL . 'assets/js/jquery-ui-timepicker-addon.min.js', ['jquery-ui-datepicker'], '1.6.3', true);
        wp_enqueue_style('jquery-ui-style', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
        
        $js_path = SOD_PLUGIN_PATH . 'assets/js/admin-bookings.js';
        if (file_exists($js_path)) {
            wp_enqueue_script('sod-admin-script', SOD_PLUGIN_URL . 'assets/js/admin-bookings.js', ['jquery', 'jquery-ui-datepicker', 'jquery-ui-timepicker'], '1.0', true);
        } else {
            error_log("SOD Admin JS not found at: $js_path");
        }
        
        $css_path = SOD_PLUGIN_PATH . 'assets/css/admin-style.css';
        if (file_exists($css_path)) {
            wp_enqueue_style('sod-admin-style', SOD_PLUGIN_URL . 'assets/css/admin-style.css', [], '1.0');
        } else {
            error_log("SOD Admin CSS not found at: $css_path");
        }

        wp_localize_script('sod-admin-script', 'sodAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sod_admin_nonce'),
            'date_format' => get_option('date_format', 'Y-m-d'),
            'time_format' => get_option('time_format', 'H:i'),
            'texts' => [
                'confirm_delete' => __('Are you sure you want to delete this booking?', 'spark-of-divine-scheduler'),
                'confirm_cancel' => __('Are you sure you want to cancel this booking?', 'spark-of-divine-scheduler'),
                'booking_updated' => __('Booking updated successfully', 'spark-of-divine-scheduler'),
                'booking_created' => __('Booking created successfully', 'spark-of-divine-scheduler'),
                'booking_deleted' => __('Booking deleted successfully', 'spark-of-divine-scheduler'),
                'error_occurred' => __('An error occurred. Please try again.', 'spark-of-divine-scheduler')
            ]
        ]);
    }

    public function display_bookings_page() {
        error_log("Displaying SOD Bookings page");
        $args = [
            'post_type' => 'sod_booking',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'meta_value',
            'meta_key' => 'sod_start_time',
            'order' => 'DESC'
        ];
        $bookings_query = new WP_Query($args);

        $staff_args = [
            'post_type' => 'sod_staff',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ];
        $staff_query = new WP_Query($staff_args);

        $service_args = [
            'post_type' => ['sod_service', 'sod_event'],
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ];
        $service_query = new WP_Query($service_args);

        $statuses = [
            'pending' => __('Pending', 'spark-of-divine-scheduler'),
            'confirmed' => __('Confirmed', 'spark-of-divine-scheduler'),
            'completed' => __('Completed', 'spark-of-divine-scheduler'),
            'cancelled' => __('Cancelled', 'spark-of-divine-scheduler'),
            'no_show' => __('No Show', 'spark-of-divine-scheduler'),
            'deposit_paid' => __('Deposit Paid', 'spark-of-divine-scheduler')
        ];

        $template_path = SOD_PLUGIN_PATH . 'includes/core/admin/templates/bookings-list.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="error"><p>' . esc_html__('Bookings list template not found.', 'spark-of-divine-scheduler') . '</p></div>';
            error_log("Bookings list template not found at: $template_path");
        }
    }

    public function display_new_booking_page() {
        $staff_args = ['post_type' => 'sod_staff', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'];
        $staff_query = new WP_Query($staff_args);
        $service_args = ['post_type' => ['sod_service', 'sod_event'], 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'];
        $service_query = new WP_Query($service_args);
        $durations = ['30' => '30 mins', '60' => '60 mins', '90' => '90 mins', '120' => '120 mins'];
        $statuses = ['pending' => 'Pending', 'confirmed' => 'Confirmed', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'no_show' => 'No Show', 'deposit_paid' => 'Deposit Paid'];
        $users = get_users(['role__in' => ['customer', 'subscriber', 'administrator'], 'orderby' => 'display_name']);
        include SOD_PLUGIN_PATH . 'includes/core/admin/templates/new-booking.php';
    }
    
    public function display_staff_availability_page() {
        $staff_args = array(
            'post_type' => 'sod_staff',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        );
        $staff_query = new WP_Query($staff_args);
        
        include(SOD_PLUGIN_PATH . 'includes/core/admin/templates/admin-staff-availability.php');
    }
    
    public function ajax_get_booking_details() {
        check_ajax_referer('sod_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to do this.', 'spark-of-divine-scheduler')));
        }
        
        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
        
        if (!$booking_id) {
            wp_send_json_error(array('message' => __('Invalid booking ID.', 'spark-of-divine-scheduler')));
        }
        
        $booking = get_post($booking_id);
        
        if (!$booking || $booking->post_type !== 'sod_booking') {
            wp_send_json_error(array('message' => __('Booking not found.', 'spark-of-divine-scheduler')));
        }
        
        $service_id = get_post_meta($booking_id, 'sod_service_id', true);
        $staff_id = get_post_meta($booking_id, 'sod_staff_id', true);
        $customer_id = get_post_meta($booking_id, 'sod_customer_id', true);
        $start_time = get_post_meta($booking_id, 'sod_start_time', true);
        $end_time = get_post_meta($booking_id, 'sod_end_time', true);
        $duration = get_post_meta($booking_id, 'sod_duration', true) ?: 60;
        $status = get_post_meta($booking_id, 'sod_status', true) ?: 'pending';
        $order_id = get_post_meta($booking_id, 'sod_order_id', true) ?: 0;
        $payment_method = get_post_meta($booking_id, 'sod_payment_method', true) ?: '';
        
        $start_datetime = new DateTime($start_time);
        $date = $start_datetime->format('Y-m-d');
        $time = $start_datetime->format('H:i');
        
        $service_title = get_the_title($service_id);
        $staff_title = get_the_title($staff_id);
        
        $customer_name = '';
        if ($customer_id) {
            $customer = get_userdata($customer_id);
            $customer_name = $customer ? $customer->display_name : '';
        }
        
        $booking_data = array(
            'id' => $booking_id,
            'title' => $booking->post_title,
            'service_id' => $service_id,
            'service_title' => $service_title,
            'staff_id' => $staff_id,
            'staff_title' => $staff_title,
            'customer_id' => $customer_id,
            'customer_name' => $customer_name,
            'date' => $date,
            'time' => $time,
            'duration' => $duration,
            'status' => $status,
            'order_id' => $order_id,
            'payment_method' => $payment_method
        );
        
        wp_send_json_success($booking_data);
    }
    
    public function ajax_update_booking() {
        check_ajax_referer('sod_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to do this.', 'spark-of-divine-scheduler')));
        }
        
        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
        
        if (!$booking_id) {
            wp_send_json_error(array('message' => __('Invalid booking ID.', 'spark-of-divine-scheduler')));
        }
        
        $booking = get_post($booking_id);
        
        if (!$booking || $booking->post_type !== 'sod_booking') {
            wp_send_json_error(array('message' => __('Booking not found.', 'spark-of-divine-scheduler')));
        }
        
        $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
        $staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;
        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $time = isset($_POST['time']) ? sanitize_text_field($_POST['time']) : '';
        $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 60;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'pending';
        
        if (!$service_id || !$staff_id || !$date || !$time) {
            wp_send_json_error(array('message' => __('Please fill in all required fields.', 'spark-of-divine-scheduler')));
        }
        
        $start_time = $date . ' ' . $time;
        $start_datetime = new DateTime($start_time);
        
        $end_datetime = clone $start_datetime;
        $end_datetime->modify("+{$duration} minutes");
        $end_time = $end_datetime->format('Y-m-d H:i:s');
        
        if (!$this->is_time_slot_available($staff_id, $start_time, $duration, $booking_id)) {
            wp_send_json_error(array('message' => __('This time slot is already booked. Please choose another time.', 'spark-of-divine-scheduler')));
        }
        
        update_post_meta($booking_id, 'sod_service_id', $service_id);
        update_post_meta($booking_id, 'sod_staff_id', $staff_id);
        update_post_meta($booking_id, 'sod_customer_id', $customer_id);
        update_post_meta($booking_id, 'sod_start_time', $start_time);
        update_post_meta($booking_id, 'sod_end_time', $end_time);
        update_post_meta($booking_id, 'sod_duration', $duration);
        update_post_meta($booking_id, 'sod_status', $status);
        
        $service_title = get_the_title($service_id);
        $staff_title = get_the_title($staff_id);
        
        $post_data = array(
            'ID' => $booking_id,
            'post_title' => sprintf(
                __('Booking for %1$s with %2$s on %3$s at %4$s', 'spark-of-divine-scheduler'),
                $service_title,
                $staff_title,
                date('M j, Y', strtotime($date)),
                date('g:i A', strtotime($time))
            )
        );
        
        wp_update_post($post_data);
        
        do_action('spark_divine_booking_updated', $booking_id);
        
        wp_send_json_success(array(
            'message' => __('Booking updated successfully.', 'spark-of-divine-scheduler'),
            'booking_id' => $booking_id
        ));
    }
    
    public function ajax_create_booking() {
        check_ajax_referer('sod_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to do this.', 'spark-of-divine-scheduler')]);
        }

        $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
        $staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;
        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $time = isset($_POST['time']) ? sanitize_text_field($_POST['time']) : '';
        $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 60;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'pending';

        if (!$service_id || !$staff_id || !$date || !$time) {
            wp_send_json_error(['message' => __('Please fill in all required fields.', 'spark-of-divine-scheduler')]);
        }

        $start_time = $date . ' ' . $time . ':00';
        try {
            $start_datetime = new DateTime($start_time);
            $end_datetime = clone $start_datetime;
            $end_datetime->modify("+{$duration} minutes");
            $end_time = $end_datetime->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            wp_send_json_error(['message' => __('Invalid date or time format.', 'spark-of-divine-scheduler')]);
        }

        if (!$this->is_time_slot_available($staff_id, $start_time, $duration)) {
            wp_send_json_error(['message' => __('This time slot is already booked. Please choose another time.', 'spark-of-divine-scheduler')]);
        }

        $service_title = get_the_title($service_id) ?: 'Unknown Service';
        $staff_title = get_the_title($staff_id) ?: 'Unknown Staff';

        $post_data = [
            'post_type' => 'sod_booking',
            'post_title' => sprintf(
                __('Booking for %1$s with %2$s on %3$s at %4$s', 'spark-of-divine-scheduler'),
                $service_title,
                $staff_title,
                date_i18n('M j, Y', strtotime($start_time)),
                date_i18n('g:i A', strtotime($start_time))
            ),
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
            'meta_input' => [
                'sod_service_id' => $service_id,
                'sod_staff_id' => $staff_id,
                'sod_customer_id' => $customer_id,
                'sod_start_time' => $start_time,
                'sod_end_time' => $end_time,
                'sod_duration' => $duration,
                'sod_status' => $status,
                'sod_payment_method' => 'manual'
            ]
        ];

        $booking_id = wp_insert_post($post_data);

        if (is_wp_error($booking_id)) {
            error_log("Failed to create booking post: " . $booking_id->get_error_message());
            wp_send_json_error(['message' => $booking_id->get_error_message()]);
        }

        if (class_exists('SOD_Booking_Sync')) {
            $sync = SOD_Booking_Sync::getInstance();
            $sync->sync_post_to_database($booking_id, get_post($booking_id), false);
            error_log("Manually triggered sync for booking $booking_id");
        }

        do_action('sod_booking_status_created', $booking_id);

        wp_send_json_success([
            'message' => __('Booking created successfully.', 'spark-of-divine-scheduler'),
            'booking_id' => $booking_id
        ]);
    }
    
    public function ajax_delete_booking() {
        check_ajax_referer('sod_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to do this.', 'spark-of-divine-scheduler')));
        }
        
        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
        
        if (!$booking_id) {
            wp_send_json_error(array('message' => __('Invalid booking ID.', 'spark-of-divine-scheduler')));
        }
        
        $booking = get_post($booking_id);
        
        if (!$booking || $booking->post_type !== 'sod_booking') {
            wp_send_json_error(array('message' => __('Booking not found.', 'spark-of-divine-scheduler')));
        }
        
        $result = wp_delete_post($booking_id, true);
        
        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to delete booking.', 'spark-of-divine-scheduler')));
        }
        
        wp_send_json_success(array(
            'message' => __('Booking deleted successfully.', 'spark-of-divine-scheduler')
        ));
    }
    
    public function ajax_get_staff_availability() {
        check_ajax_referer('sod_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to do this.', 'spark-of-divine-scheduler')));
        }
        
        $staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;
        
        if (!$staff_id) {
            wp_send_json_error(array('message' => __('Invalid staff ID.', 'spark-of-divine-scheduler')));
        }
        
        // Use db_access to get staff availability
        if (!$this->db_access || !method_exists($this->db_access, 'get_staff_availability')) {
            wp_send_json_error(array('message' => __('Database access not available.', 'spark-of-divine-scheduler')));
        }
        
        $availability = $this->db_access->get_staff_availability($staff_id);
        if (!is_array($availability)) {
            $availability = [];
        }
        
        wp_send_json_success(array(
            'availability' => $availability
        ));
    }
    
    public function ajax_update_staff_availability() {
        check_ajax_referer('sod_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to do this.', 'spark-of-divine-scheduler')));
        }
        
        $staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;
        $availability = isset($_POST['availability']) ? $_POST['availability'] : array();
        
        if (!$staff_id) {
            wp_send_json_error(array('message' => __('Invalid staff ID.', 'spark-of-divine-scheduler')));
        }
        
        if (!$this->db_access || !method_exists($this->db_access, 'update_staff_availability')) {
            wp_send_json_error(array('message' => __('Database access not available.', 'spark-of-divine-scheduler')));
        }
        
        // Sanitize availability data
        $sanitized_availability = array();
        foreach ($availability as $slot) {
            $day = isset($slot['day']) ? sanitize_text_field($slot['day']) : '';
            $start = isset($slot['start']) ? sanitize_text_field($slot['start']) : '';
            $end = isset($slot['end']) ? sanitize_text_field($slot['end']) : '';
            $date = isset($slot['date']) ? sanitize_text_field($slot['date']) : '';
            
            if (($day || $date) && $start && $end) {
                $sanitized_availability[] = array(
                    'day' => $day,
                    'date' => $date,
                    'start' => $start,
                    'end' => $end
                );
            }
        }
        
        // Update via db_access
        $result = $this->db_access->update_staff_availability($staff_id, $sanitized_availability);
        
        if ($result === false) {
            wp_send_json_error(array('message' => __('Failed to update staff availability.', 'spark-of-divine-scheduler')));
        }
        
        wp_send_json_success(array(
            'message' => __('Staff availability updated successfully.', 'spark-of-divine-scheduler')
        ));
    }
    
    public function ajax_get_available_time_slots() {
        check_ajax_referer('sod_admin_nonce', 'nonce');
        
        $staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 60;
        
        if (!$staff_id || !$date) {
            wp_send_json_error(array('message' => __('Missing required parameters.', 'spark-of-divine-scheduler')));
        }
        
        $slots = $this->get_available_time_slots($staff_id, $date, $duration);
        
        wp_send_json_success(array(
            'slots' => $slots
        ));
    }
    
    private function get_available_time_slots($staff_id, $date, $duration = 60) {
        if (!$this->db_access || !method_exists($this->db_access, 'get_staff_availability')) {
            return [];
        }
        
        $availability = $this->db_access->get_staff_availability($staff_id);
        if (!is_array($availability)) {
            return [];
        }
        
        $date_obj = new DateTime($date);
        $day_of_week = strtolower($date_obj->format('l'));
        
        $day_slots = [];
        foreach ($availability as $slot) {
            if (($slot['day'] === $day_of_week) || ($slot['date'] === $date)) {
                $day_slots[] = [
                    'start' => $slot['start'],
                    'end' => $slot['end']
                ];
            }
        }
        
        if (empty($day_slots)) {
            return [];
        }
        
        $all_slots = [];
        foreach ($day_slots as $slot) {
            $start_time = strtotime($date . ' ' . $slot['start']);
            $end_time = strtotime($date . ' ' . $slot['end']);
            $end_time = $end_time - ($duration * 60);
            
            $interval = 15 * 60;
            $current_time = $start_time;
            
            while ($current_time <= $end_time) {
                $time_string = date('H:i', $current_time);
                $all_slots[] = $time_string;
                $current_time += $interval;
            }
        }
        
        $available_slots = [];
        foreach ($all_slots as $time) {
            $time_to_check = $date . ' ' . $time;
            if ($this->is_time_slot_available($staff_id, $time_to_check, $duration)) {
                $available_slots[] = $time;
            }
        }
        
        return $available_slots;
    }
    
    private function is_time_slot_available($staff_id, $start_time, $duration = 60, $exclude_booking_id = 0) {
        $start_datetime = new DateTime($start_time);
        $end_datetime = clone $start_datetime;
        $end_datetime->modify("+{$duration} minutes");
        $end_time = $end_datetime->format('Y-m-d H:i:s');
        
        // Check availability via db_access if available
        if ($this->db_access && method_exists($this->db_access, 'is_time_slot_available')) {
            $is_available = $this->db_access->is_time_slot_available($staff_id, $start_time, $end_time, $exclude_booking_id);
            if ($is_available !== null) {
                return $is_available;
            }
        }
        
        // Fallback to WordPress post meta check
        $args = array(
            'post_type' => 'sod_booking',
            'post_status' => 'publish',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'sod_staff_id',
                    'value' => $staff_id,
                    'compare' => '='
                ),
                array(
                    'key' => 'sod_status',
                    'value' => array('cancelled', 'no_show'),
                    'compare' => 'NOT IN'
                ),
                array(
                    'relation' => 'OR',
                    array(
                        'relation' => 'AND',
                        array(
                            'key' => 'sod_start_time',
                            'value' => $start_time,
                            'compare' => '<=',
                            'type' => 'DATETIME'
                        ),
                        array(
                            'key' => 'sod_end_time',
                            'value' => $start_time,
                            'compare' => '>',
                            'type' => 'DATETIME'
                        )
                    ),
                    array(
                        'relation' => 'AND',
                        array(
                            'key' => 'sod_start_time',
                            'value' => $end_time,
                            'compare' => '<',
                            'type' => 'DATETIME'
                        ),
                        array(
                            'key' => 'sod_end_time',
                            'value' => $end_time,
                            'compare' => '>=',
                            'type' => 'DATETIME'
                        )
                    ),
                    array(
                        'relation' => 'AND',
                        array(
                            'key' => 'sod_start_time',
                            'value' => $start_time,
                            'compare' => '>=',
                            'type' => 'DATETIME'
                        ),
                        array(
                            'key' => 'sod_end_time',
                            'value' => $end_time,
                            'compare' => '<=',
                            'type' => 'DATETIME'
                        )
                    )
                )
            ),
            'posts_per_page' => 1,
            'fields' => 'ids'
        );
        
        if ($exclude_booking_id > 0) {
            $args['post__not_in'] = array($exclude_booking_id);
        }
        
        $query = new WP_Query($args);
        
        return $query->found_posts == 0;
    }
}