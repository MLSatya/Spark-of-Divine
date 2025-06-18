<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Staff Dashboard Manager
 * 
 * Handles staff dashboard functionality with enhanced WooCommerce integration, including:
 * - Login redirects
 * - Dashboard pages
 * - Revenue tracking
 * - Booking management
 * - Availability management
 */
class SOD_Staff_Dashboard {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Handle login redirects for staff members
        add_action('wp_login', array($this, 'redirect_staff_on_login'), 10, 2);
        
        // Add custom endpoints to My Account page
        add_action('init', array($this, 'add_staff_endpoints'));
        add_filter('woocommerce_account_menu_items', array($this, 'add_staff_dashboard_menu_items'));
        add_filter('woocommerce_get_endpoint_url', array($this, 'staff_dashboard_endpoint_urls'), 10, 4);
        
        // Register content for custom endpoints
        add_action('woocommerce_account_staff-dashboard_endpoint', array($this, 'staff_dashboard_content'));
        add_action('woocommerce_account_staff-bookings_endpoint', array($this, 'staff_bookings_content'));
        add_action('woocommerce_account_staff-revenue_endpoint', array($this, 'staff_revenue_content'));
        add_action('woocommerce_account_staff-purchases_endpoint', array($this, 'staff_purchases_content'));
        add_action('woocommerce_account_staff-availability_endpoint', array($this, 'staff_availability_content'));
        
        // AJAX handlers for staff actions
        add_action('wp_ajax_sod_staff_confirm_booking', array($this, 'ajax_confirm_booking'));
        add_action('wp_ajax_sod_staff_reschedule_booking', array($this, 'ajax_reschedule_booking'));
        add_action('wp_ajax_sod_staff_cancel_booking', array($this, 'ajax_cancel_booking'));
        add_action('wp_ajax_sod_staff_update_availability', array($this, 'ajax_update_availability'));
        
        // Register shortcode for staff dashboard outside My Account
        add_shortcode('sod_staff_dashboard', array($this, 'render_staff_dashboard_shortcode'));
        
        // Setup custom rewrite rules for staff-schedule URL
        /*add_action('init', array($this, 'add_staff_schedule_rewrite_rules'));*/
        add_action('template_redirect', array($this, 'handle_staff_schedule_page'));
        
        // Sync WooCommerce order status with booking status
        add_action('woocommerce_order_status_changed', array($this, 'sync_order_status_with_booking'), 10, 4);
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Add custom rewrite rules for staff schedule page
     
    public function add_staff_schedule_rewrite_rules() {
        add_rewrite_rule(
            'staff-schedule/?$',
            'index.php?staff_schedule=dashboard',
            'top'
        );
        
        add_rewrite_rule(
            'staff-schedule/bookings/?$',
            'index.php?staff_schedule=bookings',
            'top'
        );
        
        add_rewrite_rule(
            'staff-schedule/revenue/?$',
            'index.php?staff_schedule=revenue',
            'top'
        );
        
        add_rewrite_rule(
            'staff-schedule/availability/?$',
            'index.php?staff_schedule=availability',
            'top'
        );
        
        add_rewrite_tag('%staff_schedule%', '([^&]+)');
    }
    */
    /**
     * Handle custom staff schedule page
     */
    public function handle_staff_schedule_page() {
        global $wp_query;
        
        if (!isset($wp_query->query_vars['staff_schedule'])) {
            return;
        }
        
        // Check if user is logged in and is staff
        if (!is_user_logged_in() || !$this->is_staff_user()) {
            auth_redirect();
            exit;
        }
        
        $section = $wp_query->query_vars['staff_schedule'];
        
        // Load appropriate template
        switch ($section) {
            case 'dashboard':
                $this->load_staff_dashboard_template();
                break;
            case 'bookings':
                $this->load_staff_bookings_template();
                break;
            case 'revenue':
                $this->load_staff_revenue_template();
                break;
            case 'availability':
                $this->load_staff_availability_template();
                break;
            default:
                $this->load_staff_dashboard_template();
                break;
        }
        
        exit;
    }
    
    /**
     * Load staff dashboard template
     */
    private function load_staff_dashboard_template() {
        // Get necessary data
        $staff_id = $this->get_staff_id_for_user(get_current_user_id());
        $upcoming_bookings = $this->get_upcoming_bookings($staff_id, 5);
        $revenue = $this->get_revenue_summary($staff_id);
        $pending_bookings = $this->get_pending_bookings($staff_id);
        
        // Set page title
        add_filter('the_title', function($title) {
            return __('Staff Dashboard', 'spark-of-divine-scheduler');
        });
        
        // Load template
        get_header();
        echo '<div class="sod-standalone-dashboard container">';
        include SOD_PLUGIN_PATH . 'templates/staff/dashboard.php';
        echo '</div>';
        get_footer();
    }
    
    /**
     * Load staff bookings template
     */
    private function load_staff_bookings_template() {
        $staff_id = $this->get_staff_id_for_user(get_current_user_id());
        
        // Process booking actions if submitted
        if (isset($_POST['sod_booking_action'])) {
            $this->process_booking_action();
        }
        
        // Get filter values
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'upcoming';
        $date_filter = isset($_GET['date_range']) ? sanitize_text_field($_GET['date_range']) : '30days';
        
        // Get bookings based on filters
        $bookings = $this->get_filtered_bookings($staff_id, $status_filter, $date_filter);
        
        // Set page title
        add_filter('the_title', function($title) {
            return __('My Bookings', 'spark-of-divine-scheduler');
        });
        
        // Load template
        get_header();
        echo '<div class="sod-standalone-dashboard container">';
        include SOD_PLUGIN_PATH . 'templates/staff/bookings.php';
        echo '</div>';
        get_footer();
    }
    
    /**
     * Load staff revenue template
     */
    private function load_staff_revenue_template() {
        $staff_id = $this->get_staff_id_for_user(get_current_user_id());
        
        // Get date range filter
        $date_range = isset($_GET['date_range']) ? sanitize_text_field($_GET['date_range']) : 'month';
        
        // Calculate date ranges
        $dates = $this->get_date_range_for_filter($date_range);
        
        // Get revenue data
        $revenue_data = $this->get_detailed_revenue($staff_id, $dates['start'], $dates['end']);
        
        // Set page title
        add_filter('the_title', function($title) {
            return __('My Revenue', 'spark-of-divine-scheduler');
        });
        
        // Load template
        get_header();
        echo '<div class="sod-standalone-dashboard container">';
        include SOD_PLUGIN_PATH . 'templates/staff/revenue.php';
        echo '</div>';
        get_footer();
    }
    
    /**
     * Load staff availability template
     */
    private function load_staff_availability_template() {
        $staff_id = $this->get_staff_id_for_user(get_current_user_id());
        
        // Get all services/products this staff provides
        $products = $this->get_staff_products($staff_id);
        
        // Get existing availability slots
        $availability = $this->get_staff_availability($staff_id);
        
        // Set page title
        add_filter('the_title', function($title) {
            return __('My Availability', 'spark-of-divine-scheduler');
        });
        
        // Load template
        get_header();
        echo '<div class="sod-standalone-dashboard container">';
        include SOD_PLUGIN_PATH . 'templates/staff/availability.php';
        echo '</div>';
        get_footer();
    }
    
    /**
     * Shortcode to render staff dashboard
     */
    public function render_staff_dashboard_shortcode($atts) {
        // Check if user is logged in and is staff
        if (!is_user_logged_in() || !$this->is_staff_user()) {
            return sprintf(
                '<p>%s <a href="%s">%s</a></p>',
                __('You must be logged in as a staff member to view this dashboard.', 'spark-of-divine-scheduler'),
                wp_login_url(get_permalink()),
                __('Log in', 'spark-of-divine-scheduler')
            );
        }
        
        $atts = shortcode_atts(array(
            'view' => 'dashboard',
        ), $atts);
        
        // Get necessary data based on view
        $staff_id = $this->get_staff_id_for_user(get_current_user_id());
        
        ob_start();
        
        switch ($atts['view']) {
            case 'bookings':
                $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'upcoming';
                $date_filter = isset($_GET['date_range']) ? sanitize_text_field($_GET['date_range']) : '30days';
                $bookings = $this->get_filtered_bookings($staff_id, $status_filter, $date_filter);
                include SOD_PLUGIN_PATH . 'templates/staff/bookings.php';
                break;
                
            case 'revenue':
                $date_range = isset($_GET['date_range']) ? sanitize_text_field($_GET['date_range']) : 'month';
                $dates = $this->get_date_range_for_filter($date_range);
                $revenue_data = $this->get_detailed_revenue($staff_id, $dates['start'], $dates['end']);
                include SOD_PLUGIN_PATH . 'templates/staff/revenue.php';
                break;
                
            case 'availability':
                $products = $this->get_staff_products($staff_id);
                $availability = $this->get_staff_availability($staff_id);
                include SOD_PLUGIN_PATH . 'templates/staff/availability.php';
                break;
                
            case 'dashboard':
            default:
                $upcoming_bookings = $this->get_upcoming_bookings($staff_id, 5);
                $revenue = $this->get_revenue_summary($staff_id);
                $pending_bookings = $this->get_pending_bookings($staff_id);
                include SOD_PLUGIN_PATH . 'templates/staff/dashboard.php';
                break;
        }
        
        return ob_get_clean();
    }
    
    /**
     * Redirect staff members to their dashboard after login
     */
    public function redirect_staff_on_login($user_login, $user) {
        // Check if user is staff
        $staff_id = $this->get_staff_id_for_user($user->ID);
        
        if ($staff_id) {
            // Redirect to staff dashboard
            wp_redirect(wc_get_account_endpoint_url('staff-dashboard'));
            exit;
        }
    }
    
    /**
     * Add custom endpoints for staff
     */
    public function add_staff_endpoints() {
        add_rewrite_endpoint('staff-dashboard', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('staff-bookings', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('staff-revenue', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('staff-purchases', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('staff-availability', EP_ROOT | EP_PAGES);
        
        // Flush rewrite rules only when needed
        if (get_option('sod_flush_rewrite_rules', 'no') === 'yes') {
            flush_rewrite_rules();
            update_option('sod_flush_rewrite_rules', 'no');
        }
    }
    
    /**
     * Add menu items to My Account page for staff
     */
    public function add_staff_dashboard_menu_items($items) {
        // Only modify for staff users
        if (!$this->is_staff_user()) {
            return $items;
        }
        
        // Add staff menu items after dashboard
        $new_items = array();
        
        foreach ($items as $key => $value) {
            $new_items[$key] = $value;
            
            if ($key === 'dashboard') {
                $new_items['staff-dashboard'] = __('Staff Dashboard', 'spark-of-divine-scheduler');
                $new_items['staff-bookings'] = __('My Bookings', 'spark-of-divine-scheduler');
                $new_items['staff-revenue'] = __('My Revenue', 'spark-of-divine-scheduler');
                $new_items['staff-availability'] = __('My Availability', 'spark-of-divine-scheduler');
                $new_items['staff-purchases'] = __('My Purchases', 'spark-of-divine-scheduler');
            }
        }
        
        return $new_items;
    }
    
    /**
     * Generate endpoint URLs for staff dashboard
     */
    public function staff_dashboard_endpoint_urls($url, $endpoint, $value, $permalink) {
        if ($endpoint === 'staff-dashboard') {
            return wc_get_page_permalink('myaccount') . 'staff-dashboard/';
        }
        if ($endpoint === 'staff-bookings') {
            return wc_get_page_permalink('myaccount') . 'staff-bookings/';
        }
        if ($endpoint === 'staff-revenue') {
            return wc_get_page_permalink('myaccount') . 'staff-revenue/';
        }
        if ($endpoint === 'staff-purchases') {
            return wc_get_page_permalink('myaccount') . 'staff-purchases/';
        }
        if ($endpoint === 'staff-availability') {
            return wc_get_page_permalink('myaccount') . 'staff-availability/';
        }
        
        return $url;
    }
    
    /**
     * Staff dashboard main content
     */
    public function staff_dashboard_content() {
        $staff_id = $this->get_staff_id_for_user(get_current_user_id());
        
        if (!$staff_id) {
            echo '<p>' . __('Staff account not found. Please contact an administrator.', 'spark-of-divine-scheduler') . '</p>';
            return;
        }
        
        // Get upcoming bookings (next 5)
        $upcoming_bookings = $this->get_upcoming_bookings($staff_id, 5);
        
        // Get revenue overview
        $revenue = $this->get_revenue_summary($staff_id);
        
        // Get pending action items
        $pending_bookings = $this->get_pending_bookings($staff_id);
        
        include SOD_PLUGIN_PATH . 'templates/staff/dashboard.php';
    }
    
    /**
     * Staff bookings content
     */
    public function staff_bookings_content() {
        $staff_id = $this->get_staff_id_for_user(get_current_user_id());
        
        if (!$staff_id) {
            echo '<p>' . __('Staff account not found. Please contact an administrator.', 'spark-of-divine-scheduler') . '</p>';
            return;
        }
        
        // Process booking actions if submitted
        if (isset($_POST['sod_booking_action'])) {
            $this->process_booking_action();
        }
        
        // Get filter values
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'upcoming';
        $date_filter = isset($_GET['date_range']) ? sanitize_text_field($_GET['date_range']) : '30days';
        
        // Get bookings based on filters
        $bookings = $this->get_filtered_bookings($staff_id, $status_filter, $date_filter);
        
        include SOD_PLUGIN_PATH . 'templates/staff/bookings.php';
    }
    
    /**
     * Staff revenue content
     */
    public function staff_revenue_content() {
        $staff_id = $this->get_staff_id_for_user(get_current_user_id());
        
        if (!$staff_id) {
            echo '<p>' . __('Staff account not found. Please contact an administrator.', 'spark-of-divine-scheduler') . '</p>';
            return;
        }
        
        // Get date range filter
        $date_range = isset($_GET['date_range']) ? sanitize_text_field($_GET['date_range']) : 'month';
        
        // Calculate date ranges
        $dates = $this->get_date_range_for_filter($date_range);
        
        // Get revenue data
        $revenue_data = $this->get_detailed_revenue($staff_id, $dates['start'], $dates['end']);
        
        include SOD_PLUGIN_PATH . 'templates/staff/revenue.php';
    }
    
    /**
     * Staff purchases content
     */
    public function staff_purchases_content() {
        $user_id = get_current_user_id();
        
        // Get orders for this user
        $orders = wc_get_orders(array(
            'customer_id' => $user_id,
            'limit' => 50,
            'orderby' => 'date',
            'order' => 'DESC',
        ));
        
        include SOD_PLUGIN_PATH . 'templates/staff/purchases.php';
    }
    
    /**
     * Staff availability content
     */
    public function staff_availability_content() {
        $staff_id = $this->get_staff_id_for_user(get_current_user_id());
        
        if (!$staff_id) {
            echo '<p>' . __('Staff account not found. Please contact an administrator.', 'spark-of-divine-scheduler') . '</p>';
            return;
        }
        
        // Get all services/products this staff provides
        $products = $this->get_staff_products($staff_id);
        
        // Get existing availability slots
        $availability = $this->get_staff_availability($staff_id);
        
        include SOD_PLUGIN_PATH . 'templates/staff/availability.php';
    }

    /**
     * AJAX handler for booking confirmation
     */
    public function ajax_confirm_booking() {
        // Verify nonce
        if (!check_ajax_referer('sod_staff_action', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'spark-of-divine-scheduler')));
            return;
        }
        
        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
        $staff_id = $this->get_staff_id_for_user(get_current_user_id());
        
        if (!$booking_id || !$staff_id) {
            wp_send_json_error(array('message' => __('Invalid booking or staff ID', 'spark-of-divine-scheduler')));
            return;
        }
        
        // Verify this booking belongs to this staff member
        global $wpdb;
        $booking_staff_id = $wpdb->get_var($wpdb->prepare(
            "SELECT staff_id FROM {$wpdb->prefix}sod_bookings WHERE booking_id = %d", 
            $booking_id
        ));
        
        if ($booking_staff_id != $staff_id) {
            wp_send_json_error(array('message' => __('This booking does not belong to you', 'spark-of-divine-scheduler')));
            return;
        }
        
        // Get order ID from booking
        $order_id = $this->get_order_id_from_booking($booking_id);
        
        // Update booking status
        $updated = $wpdb->update(
            "{$wpdb->prefix}sod_bookings",
            array('status' => 'confirmed'),
            array('booking_id' => $booking_id),
            array('%s'),
            array('%d')
        );
        
        // Update order status if applicable
        if ($order_id) {
            $order = wc_get_order($order_id);
            if ($order && !$order->has_status('completed')) {
                // Update to processing status
                $order->update_status('processing', __('Booking confirmed by staff', 'spark-of-divine-scheduler'));
            }
        }
        
        // Also update in post meta
        update_post_meta($booking_id, 'sod_booking_status', 'confirmed');
        
        // Send confirmation emails
        $this->send_booking_confirmation_emails($booking_id);
        
        wp_send_json_success(array(
            'message' => __('Booking confirmed successfully', 'spark-of-divine-scheduler')
        ));
    }
    
    /**
     * AJAX handler for booking reschedule
     */
    public function ajax_reschedule_booking() {
        // Verify nonce
        if (!check_ajax_referer('sod_staff_action', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'spark-of-divine-scheduler')));
            return;
        }
        
        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
        $new_date = isset($_POST['new_date']) ? sanitize_text_field($_POST['new_date']) : '';
        $new_time = isset($_POST['new_time']) ? sanitize_text_field($_POST['new_time']) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
        
        if (!$booking_id || !$new_date || !$new_time) {
            wp_send_json_error(array('message' => __('Missing required parameters', 'spark-of-divine-scheduler')));
            return;
        }
        
        $staff_id = $this->get_staff_id_for_user(get_current_user_id());
        
        // Verify this booking belongs to this staff member
        global $wpdb;
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sod_bookings WHERE booking_id = %d", 
            $booking_id
        ));
        
        if (!$booking || $booking->staff_id != $staff_id) {
            wp_send_json_error(array('message' => __('This booking does not belong to you', 'spark-of-divine-scheduler')));
            return;
        }
        
        // Calculate duration from original booking
        $original_duration = strtotime($booking->end_time) - strtotime($booking->start_time);
        
        // Create new start/end times
        $new_start_datetime = $new_date . ' ' . $new_time . ':00';
        $new_end_datetime = date('Y-m-d H:i:s', strtotime($new_start_datetime) + $original_duration);
        
        // Update booking with new times
        $updated = $wpdb->update(
            "{$wpdb->prefix}sod_bookings",
            array(
                'start_time' => $new_start_datetime,
                'end_time' => $new_end_datetime,
                'status' => 'rescheduled'
            ),
            array('booking_id' => $booking_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
        
        // Also update in post meta
        update_post_meta($booking_id, 'sod_booking_date', $new_date);
        update_post_meta($booking_id, 'sod_booking_time', $new_time);
        update_post_meta($booking_id, 'sod_booking_status', 'rescheduled');
        update_post_meta($booking_id, 'sod_reschedule_message', $message);
        
        // Add a note to the WooCommerce order if applicable
        $order_id = $this->get_order_id_from_booking($booking_id);
        if ($order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $note = sprintf(
                    __('Booking rescheduled to %s at %s by staff member. %s', 'spark-of-divine-scheduler'),
                    date_i18n(get_option('date_format'), strtotime($new_date)),
                    date_i18n(get_option('time_format'), strtotime($new_time)),
                    $message ? "Note: $message" : ""
                );
                $order->add_order_note($note);
            }
        }
        
        // Send reschedule emails
        $this->send_booking_reschedule_emails($booking_id, $new_start_datetime, $message);
        
        wp_send_json_success(array(
            'message' => __('Booking rescheduled successfully', 'spark-of-divine-scheduler')
        ));
    }
    
    /**
     * AJAX handler for booking cancellation
     */
    public function ajax_cancel_booking() {
        // Verify nonce
        if (!check_ajax_referer('sod_staff_action', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'spark-of-divine-scheduler')));
            return;
        }
        
        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
        $reason = isset($_POST['reason']) ? sanitize_textarea_field($_POST['reason']) : '';
        $staff_id = $this->get_staff_id_for_user(get_current_user_id());
        
        if (!$booking_id || !$staff_id) {
            wp_send_json_error(array('message' => __('Invalid booking or staff ID', 'spark-of-divine-scheduler')));
            return;
        }
        
        // Verify this booking belongs to this staff member
        global $wpdb;
        $booking_staff_id = $wpdb->get_var($wpdb->prepare(
            "SELECT staff_id FROM {$wpdb->prefix}sod_bookings WHERE booking_id = %d", 
            $booking_id
        ));
        
        if ($booking_staff_id != $staff_id) {
            wp_send_json_error(array('message' => __('This booking does not belong to you', 'spark-of-divine-scheduler')));
            return;
        }
        
        // Update booking status
        $updated = $wpdb->update(
            "{$wpdb->prefix}sod_bookings",
            array('status' => 'cancelled'),
            array('booking_id' => $booking_id),
            array('%s'),
            array('%d')
        );
        
        // Also update in post meta
        update_post_meta($booking_id, 'sod_booking_status', 'cancelled');
        update_post_meta($booking_id, 'sod_cancellation_reason', $reason);
        
        // Update WooCommerce order if applicable
        $order_id = $this->get_order_id_from_booking($booking_id);
        if ($order_id) {
            $order = wc_get_order($order_id);
            if ($order && !$order->has_status('cancelled')) {
                // Add note to order
                $order->add_order_note(
                    sprintf(__('Booking cancelled by staff. Reason: %s', 'spark-of-divine-scheduler'), $reason)
                );
                
                // Cancel the order
                $order->update_status('cancelled', __('Booking cancelled by staff', 'spark-of-divine-scheduler'));
            }
        }
        
        // Send cancellation emails
        $this->send_booking_cancellation_emails($booking_id, $reason);
        
        wp_send_json_success(array(
            'message' => __('Booking cancelled successfully', 'spark-of-divine-scheduler')
        ));
    }
    
    /**
     * AJAX handler for updating staff availability
     */
    public function ajax_update_availability() {
        // Verify nonce
        if (!check_ajax_referer('sod_staff_action', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'spark-of-divine-scheduler')));
            return;
        }
        
        $staff_id = $this->get_staff_id_for_user(get_current_user_id());
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $availability_type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $slot_id = isset($_POST['slot_id']) ? intval($_POST['slot_id']) : 0;
        
        if (!$staff_id || !$product_id) {
            wp_send_json_error(array('message' => __('Invalid staff or product ID', 'spark-of-divine-scheduler')));
            return;
        }
        
        // Handle different availability actions
        switch ($availability_type) {
            case 'add':
                $result = $this->add_staff_availability_slot($_POST);
                break;
                
            case 'update':
                $result = $this->update_staff_availability_slot($_POST);
                break;
                
            case 'delete':
                $result = $this->delete_staff_availability_slot($slot_id);
                break;
                
            default:
                wp_send_json_error(array('message' => __('Invalid availability action', 'spark-of-divine-scheduler')));
                return;
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        } else {
            wp_send_json_success(array(
                'message' => __('Availability updated successfully', 'spark-of-divine-scheduler'),
                'slot_id' => $result
            ));
        }
    }
    
    /**
     * Add a new availability slot for staff
     */
    private function add_staff_availability_slot($data) {
        global $wpdb;
        
        $staff_id = $this->get_staff_id_for_user(get_current_user_id());
        $product_id = isset($data['product_id']) ? intval($data['product_id']) : 0;
        $day_of_week = isset($data['day_of_week']) ? sanitize_text_field($data['day_of_week']) : '';
        $specific_date = isset($data['specific_date']) ? sanitize_text_field($data['specific_date']) : '';
        $start_time = isset($data['start_time']) ? sanitize_text_field($data['start_time']) : '';
        $end_time = isset($data['end_time']) ? sanitize_text_field($data['end_time']) : '';
        $recurring_type = isset($data['recurring_type']) ? sanitize_text_field($data['recurring_type']) : '';
        $recurring_end_date = isset($data['recurring_end_date']) ? sanitize_text_field($data['recurring_end_date']) : null;
        
        // Validate required fields
        if (!$product_id || (!$day_of_week && !$specific_date) || !$start_time || !$end_time) {
            return new WP_Error('missing_data', __('Missing required availability data', 'spark-of-divine-scheduler'));
        }
        
        // Insert into database
        $insert_data = array(
            'staff_id' => $staff_id,
            'product_id' => $product_id,
            'start_time' => $start_time,
            'end_time' => $end_time,
        );
        
        $insert_format = array('%d', '%d', '%s', '%s');
        
        // Add either day of week or specific date
        if ($day_of_week) {
            $insert_data['day_of_week'] = $day_of_week;
            $insert_format[] = '%s';
            
            if ($recurring_type) {
                $insert_data['recurring_type'] = $recurring_type;
                $insert_format[] = '%s';
            }
            
            if ($recurring_end_date) {
                $insert_data['recurring_end_date'] = $recurring_end_date;
                $insert_format[] = '%s';
            }
        } else {
            $insert_data['date'] = $specific_date;
            $insert_format[] = '%s';
        }
        
        $result = $wpdb->insert(
            "{$wpdb->prefix}sod_staff_availability",
            $insert_data,
            $insert_format
        );
        
        if (!$result) {
            return new WP_Error('db_error', __('Failed to add availability slot', 'spark-of-divine-scheduler'));
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update an existing availability slot
     */
    private function update_staff_availability_slot($data) {
        global $wpdb;
        
        $staff_id = $this->get_staff_id_for_user(get_current_user_id());
        $slot_id = isset($data['slot_id']) ? intval($data['slot_id']) : 0;
        $start_time = isset($data['start_time']) ? sanitize_text_field($data['start_time']) : '';
        $end_time = isset($data['end_time']) ? sanitize_text_field($data['end_time']) : '';
        $recurring_end_date = isset($data['recurring_end_date']) ? sanitize_text_field($data['recurring_end_date']) : null;
        
        // Validate required fields
        if (!$slot_id || !$start_time || !$end_time) {
            return new WP_Error('missing_data', __('Missing required availability data', 'spark-of-divine-scheduler'));
        }
        
        // Verify this slot belongs to this staff member
        $slot_staff_id = $wpdb->get_var($wpdb->prepare(
            "SELECT staff_id FROM {$wpdb->prefix}sod_staff_availability WHERE id = %d",
            $slot_id
        ));
        
        if ($slot_staff_id != $staff_id) {
            return new WP_Error('unauthorized', __('You do not have permission to update this availability slot', 'spark-of-divine-scheduler'));
        }
        
        // Update database
        $update_data = array(
            'start_time' => $start_time,
            'end_time' => $end_time,
        );
        
        $update_format = array('%s', '%s');
        
        if ($recurring_end_date !== null) {
            $update_data['recurring_end_date'] = $recurring_end_date;
            $update_format[] = '%s';
        }
        
        $result = $wpdb->update(
            "{$wpdb->prefix}sod_staff_availability",
            $update_data,
            array('id' => $slot_id),
            $update_format,
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to update availability slot', 'spark-of-divine-scheduler'));
        }
        
        return $slot_id;
    }
    
    /**
     * Delete an availability slot
     */
    private function delete_staff_availability_slot($slot_id) {
        global $wpdb;
        
        $staff_id = $this->get_staff_id_for_user(get_current_user_id());
        
        // Verify this slot belongs to this staff member
        $slot_staff_id = $wpdb->get_var($wpdb->prepare(
            "SELECT staff_id FROM {$wpdb->prefix}sod_staff_availability WHERE id = %d",
            $slot_id
        ));
        
        if ($slot_staff_id != $staff_id) {
            return new WP_Error('unauthorized', __('You do not have permission to delete this availability slot', 'spark-of-divine-scheduler'));
        }
        
        // Delete the slot
        $result = $wpdb->delete(
            "{$wpdb->prefix}sod_staff_availability",
            array('id' => $slot_id),
            array('%d')
        );
        
        if (!$result) {
            return new WP_Error('db_error', __('Failed to delete availability slot', 'spark-of-divine-scheduler'));
        }
        
        return $slot_id;
    }
    
    /**
     * Sync WooCommerce order status with booking status
     */
    public function sync_order_status_with_booking($order_id, $from_status, $to_status, $order) {
        // Get booking_id from order meta
        $booking_id = $order->get_meta('_sod_booking_id');
        
        if (!$booking_id) {
            return;
        }
        
        global $wpdb;
        
        // Update booking status based on order status
        switch ($to_status) {
            case 'completed':
                $wpdb->update(
                    "{$wpdb->prefix}sod_bookings",
                    array('status' => 'completed'),
                    array('booking_id' => $booking_id),
                    array('%s'),
                    array('%d')
                );
                update_post_meta($booking_id, 'sod_booking_status', 'completed');
                break;
                
            case 'cancelled':
                $wpdb->update(
                    "{$wpdb->prefix}sod_bookings",
                    array('status' => 'cancelled'),
                    array('booking_id' => $booking_id),
                    array('%s'),
                    array('%d')
                );
                update_post_meta($booking_id, 'sod_booking_status', 'cancelled');
                break;
                
            case 'processing':
                $wpdb->update(
                    "{$wpdb->prefix}sod_bookings",
                    array('status' => 'confirmed'),
                    array('booking_id' => $booking_id),
                    array('%s'),
                    array('%d')
                );
                update_post_meta($booking_id, 'sod_booking_status', 'confirmed');
                break;
                
            case 'on-hold':
                $wpdb->update(
                    "{$wpdb->prefix}sod_bookings",
                    array('status' => 'deposit_paid'),
                    array('booking_id' => $booking_id),
                    array('%s'),
                    array('%d')
                );
                update_post_meta($booking_id, 'sod_booking_status', 'deposit_paid');
                break;
        }
    }
    
    /**
     * Get staff ID for a user
     */
    private function get_staff_id_for_user($user_id) {
        if (!$user_id) return false;
        
        global $wpdb;
        $staff_id = $wpdb->get_var($wpdb->prepare(
            "SELECT staff_id FROM {$wpdb->prefix}sod_staff WHERE user_id = %d",
            $user_id
        ));
        
        if ($staff_id) {
            return $staff_id;
        }
        
        // Try post meta as fallback
        $args = array(
            'post_type' => 'sod_staff',
            'meta_key' => 'sod_staff_user_id',
            'meta_value' => $user_id,
            'posts_per_page' => 1
        );
        
        $staff_posts = get_posts($args);
        
        if (!empty($staff_posts)) {
            return $staff_posts[0]->ID;
        }
        
        return false;
    }
    
    /**
     * Check if current user is staff
     */
    private function is_staff_user() {
        $user_id = get_current_user_id();
        if (!$user_id) return false;
        
        // Check with our get_staff_id_for_user method
        $staff_id = $this->get_staff_id_for_user($user_id);
        if ($staff_id) return true;
        
        // Alternatively check for a role-based approach
        $user = get_userdata($user_id);
        return $user && (in_array('staff', $user->roles) || in_array('administrator', $user->roles));
    }
    
    /**
     * Get upcoming bookings for a staff member
     */
    private function get_upcoming_bookings($staff_id, $limit = 5) {
        global $wpdb;
        
        // Updated query to prioritize product_id and use it in joins
        return $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, 
                c.meta_value as customer_name,
                p.meta_value as customer_phone,
                e.meta_value as customer_email,
                d.meta_value as booking_date,
                t.meta_value as booking_time,
                COALESCE(prd.post_title, s.post_title) as product_name
             FROM {$wpdb->prefix}sod_bookings b
             LEFT JOIN {$wpdb->postmeta} c ON b.booking_id = c.post_id AND c.meta_key = 'sod_booking_customer_name'
             LEFT JOIN {$wpdb->postmeta} p ON b.booking_id = p.post_id AND p.meta_key = 'sod_booking_customer_phone'
             LEFT JOIN {$wpdb->postmeta} e ON b.booking_id = e.post_id AND e.meta_key = 'sod_booking_customer_email'
             LEFT JOIN {$wpdb->postmeta} d ON b.booking_id = d.post_id AND d.meta_key = 'sod_booking_date'
             LEFT JOIN {$wpdb->postmeta} t ON b.booking_id = t.post_id AND t.meta_key = 'sod_booking_time'
             LEFT JOIN {$wpdb->posts} prd ON b.product_id = prd.ID
             LEFT JOIN {$wpdb->posts} s ON b.service_id = s.ID
             WHERE b.staff_id = %d
             AND b.status IN ('pending', 'confirmed', 'deposit_paid')
             AND start_time >= %s
             ORDER BY start_time ASC
             LIMIT %d",
            $staff_id,
            current_time('mysql'),
            $limit
        ));
    }
    
    /**
     * Get pending bookings that need staff action
     */
    private function get_pending_bookings($staff_id) {
        global $wpdb;
        
        // Updated query to prioritize product_id
        return $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, 
                c.meta_value as customer_name,
                p.meta_value as customer_phone,
                e.meta_value as customer_email,
                d.meta_value as booking_date,
                t.meta_value as booking_time,
                COALESCE(prd.post_title, s.post_title) as product_name
             FROM {$wpdb->prefix}sod_bookings b
             LEFT JOIN {$wpdb->postmeta} c ON b.booking_id = c.post_id AND c.meta_key = 'sod_booking_customer_name'
             LEFT JOIN {$wpdb->postmeta} p ON b.booking_id = p.post_id AND p.meta_key = 'sod_booking_customer_phone'
             LEFT JOIN {$wpdb->postmeta} e ON b.booking_id = e.post_id AND e.meta_key = 'sod_booking_customer_email'
             LEFT JOIN {$wpdb->postmeta} d ON b.booking_id = d.post_id AND d.meta_key = 'sod_booking_date'
             LEFT JOIN {$wpdb->postmeta} t ON b.booking_id = t.post_id AND t.meta_key = 'sod_booking_time'
             LEFT JOIN {$wpdb->posts} prd ON b.product_id = prd.ID
             LEFT JOIN {$wpdb->posts} s ON b.service_id = s.ID
             WHERE b.staff_id = %d
             AND b.status IN ('pending', 'deposit_paid')
             ORDER BY start_time ASC",
            $staff_id
        ));
    }
    
    /**
     * Get filtered bookings based on status and date range
     */
    private function get_filtered_bookings($staff_id, $status_filter, $date_filter) {
        global $wpdb;
        
        // Determine status conditions
        $status_conditions = '';
        if ($status_filter === 'upcoming') {
            $status_conditions = "AND b.status IN ('pending', 'confirmed', 'deposit_paid') AND b.start_time >= '" . current_time('mysql') . "'";
        } elseif ($status_filter === 'past') {
            $status_conditions = "AND (b.status = 'completed' OR b.start_time < '" . current_time('mysql') . "')";
        } elseif ($status_filter === 'cancelled') {
            $status_conditions = "AND b.status IN ('cancelled', 'no-show')";
        }
        
        // Determine date range
        $date_range = $this->get_date_range_for_filter($date_filter);
        $date_conditions = '';
        if ($date_range) {
            $date_conditions = "AND b.start_time BETWEEN '" . $date_range['start'] . "' AND '" . $date_range['end'] . "'";
        }
        
        // Updated query to prioritize product_id
        $query = $wpdb->prepare(
            "SELECT b.*, 
                c.meta_value as customer_name,
                p.meta_value as customer_phone,
                e.meta_value as customer_email,
                d.meta_value as booking_date,
                t.meta_value as booking_time,
                COALESCE(prd.post_title, s.post_title) as product_name,
                o.ID as order_id
             FROM {$wpdb->prefix}sod_bookings b
             LEFT JOIN {$wpdb->postmeta} c ON b.booking_id = c.post_id AND c.meta_key = 'sod_booking_customer_name'
             LEFT JOIN {$wpdb->postmeta} p ON b.booking_id = p.post_id AND p.meta_key = 'sod_booking_customer_phone'
             LEFT JOIN {$wpdb->postmeta} e ON b.booking_id = e.post_id AND e.meta_key = 'sod_booking_customer_email'
             LEFT JOIN {$wpdb->postmeta} d ON b.booking_id = d.post_id AND d.meta_key = 'sod_booking_date'
             LEFT JOIN {$wpdb->postmeta} t ON b.booking_id = t.post_id AND t.meta_key = 'sod_booking_time'
             LEFT JOIN {$wpdb->posts} prd ON b.product_id = prd.ID
             LEFT JOIN {$wpdb->posts} s ON b.service_id = s.ID
             LEFT JOIN {$wpdb->postmeta} om ON b.booking_id = om.meta_value AND om.meta_key = '_sod_booking_id'
             LEFT JOIN {$wpdb->posts} o ON om.post_id = o.ID AND o.post_type = 'shop_order'
             WHERE b.staff_id = %d
             $status_conditions
             $date_conditions
             ORDER BY b.start_time DESC",
            $staff_id
        );
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get date range for a filter value
     */
    private function get_date_range_for_filter($filter) {
        $now = current_time('mysql');
        $result = ['start' => '', 'end' => ''];
        
        switch ($filter) {
            case '7days':
                $result['start'] = date('Y-m-d 00:00:00', strtotime('-7 days'));
                $result['end'] = date('Y-m-d 23:59:59', strtotime('+7 days'));
                break;
            case '30days':
                $result['start'] = date('Y-m-d 00:00:00', strtotime('-30 days'));
                $result['end'] = date('Y-m-d 23:59:59', strtotime('+30 days'));
                break;
            case 'month':
                $result['start'] = date('Y-m-01 00:00:00');
                $result['end'] = date('Y-m-t 23:59:59');
                break;
            case 'lastmonth':
                $result['start'] = date('Y-m-01 00:00:00', strtotime('first day of last month'));
                $result['end'] = date('Y-m-t 23:59:59', strtotime('last day of last month'));
                break;
            case 'year':
                $result['start'] = date('Y-01-01 00:00:00');
                $result['end'] = date('Y-12-31 23:59:59');
                break;
            default:
                $result['start'] = date('Y-m-d 00:00:00', strtotime('-30 days'));
                $result['end'] = date('Y-m-d 23:59:59', strtotime('+180 days'));
                break;
        }
        
        return $result;
    }
    
    /**
     * Get revenue summary for a staff member
     */
    private function get_revenue_summary($staff_id) {
        global $wpdb;
        
        // Current month
        $month_start = date('Y-m-01 00:00:00');
        $month_end = date('Y-m-t 23:59:59');
        
        // Get orders that have a staff_id meta matching this staff member
        $month_revenue = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(meta_total.meta_value) 
             FROM {$wpdb->posts} orders
             JOIN {$wpdb->postmeta} meta_staff ON orders.ID = meta_staff.post_id AND meta_staff.meta_key = '_sod_staff_id' AND meta_staff.meta_value = %d
             JOIN {$wpdb->postmeta} meta_total ON orders.ID = meta_total.post_id AND meta_total.meta_key = '_order_total'
             WHERE orders.post_type = 'shop_order'
             AND orders.post_status IN ('wc-completed', 'wc-processing')
             AND orders.post_date BETWEEN %s AND %s",
            $staff_id, $month_start, $month_end
        ));
        
        // Previous month
        $prev_month_start = date('Y-m-01 00:00:00', strtotime('first day of last month'));
        $prev_month_end = date('Y-m-t 23:59:59', strtotime('last day of last month'));
        
        $prev_month_revenue = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(meta_total.meta_value) 
             FROM {$wpdb->posts} orders
             JOIN {$wpdb->postmeta} meta_staff ON orders.ID = meta_staff.post_id AND meta_staff.meta_key = '_sod_staff_id' AND meta_staff.meta_value = %d
             JOIN {$wpdb->postmeta} meta_total ON orders.ID = meta_total.post_id AND meta_total.meta_key = '_order_total'
             WHERE orders.post_type = 'shop_order'
             AND orders.post_status IN ('wc-completed', 'wc-processing')
             AND orders.post_date BETWEEN %s AND %s",
            $staff_id, $prev_month_start, $prev_month_end
        ));
        
        // Year to date
        $year_start = date('Y-01-01 00:00:00');
        $year_end = date('Y-12-31 23:59:59');
        
        $year_revenue = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(meta_total.meta_value) 
             FROM {$wpdb->posts} orders
             JOIN {$wpdb->postmeta} meta_staff ON orders.ID = meta_staff.post_id AND meta_staff.meta_key = '_sod_staff_id' AND meta_staff.meta_value = %d
             JOIN {$wpdb->postmeta} meta_total ON orders.ID = meta_total.post_id AND meta_total.meta_key = '_order_total'
             WHERE orders.post_type = 'shop_order'
             AND orders.post_status IN ('wc-completed', 'wc-processing')
             AND orders.post_date BETWEEN %s AND %s",
            $staff_id, $year_start, $year_end
        ));
        
        // Calculate staff portion (65%)
        return [
            'current_month' => [
                'gross' => floatval($month_revenue),
                'service_fee' => floatval($month_revenue) * 0.35,
                'staff_portion' => floatval($month_revenue) * 0.65
            ],
            'previous_month' => [
                'gross' => floatval($prev_month_revenue),
                'service_fee' => floatval($prev_month_revenue) * 0.35,
                'staff_portion' => floatval($prev_month_revenue) * 0.65
            ],
            'year_to_date' => [
                'gross' => floatval($year_revenue),
                'service_fee' => floatval($year_revenue) * 0.35,
                'staff_portion' => floatval($year_revenue) * 0.65
            ]
        ];
    }
    
    /**
     * Get detailed revenue for a staff member in a date range
     */
    private function get_detailed_revenue($staff_id, $start_date, $end_date) {
        global $wpdb;
        
        // Get orders in the specified date range
        $orders = $wpdb->get_results($wpdb->prepare(
            "SELECT orders.ID, orders.post_date, meta_total.meta_value as total
             FROM {$wpdb->posts} orders
             JOIN {$wpdb->postmeta} meta_staff ON orders.ID = meta_staff.post_id AND meta_staff.meta_key = '_sod_staff_id' AND meta_staff.meta_value = %d
             JOIN {$wpdb->postmeta} meta_total ON orders.ID = meta_total.post_id AND meta_total.meta_key = '_order_total'
             WHERE orders.post_type = 'shop_order'
             AND orders.post_status IN ('wc-completed', 'wc-processing')
             AND orders.post_date BETWEEN %s AND %s
             ORDER BY orders.post_date DESC",
            $staff_id, $start_date, $end_date
        ));
        
        $result = [
            'orders' => [],
            'total_gross' => 0,
            'total_fee' => 0,
            'total_staff' => 0
        ];
        
        foreach ($orders as $order) {
            $order_obj = wc_get_order($order->ID);
            if (!$order_obj) continue;
            
            $gross = floatval($order->total);
            $fee = $gross * 0.35;
            $staff_portion = $gross * 0.65;
            
            $result['total_gross'] += $gross;
            $result['total_fee'] += $fee;
            $result['total_staff'] += $staff_portion;
            
            // Get booked service information
            $booking_id = $order_obj->get_meta('_sod_booking_id');
            $service_info = '';
            
            if ($booking_id) {
                $product_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT product_id FROM {$wpdb->prefix}sod_bookings WHERE booking_id = %d",
                    $booking_id
                ));
                
                if ($product_id) {
                    $product = wc_get_product($product_id);
                    if ($product) {
                        $service_info = $product->get_name();
                    }
                }
            }
            
            $result['orders'][] = [
                'order_id' => $order->ID,
                'date' => $order->post_date,
                'customer' => $order_obj->get_billing_first_name() . ' ' . $order_obj->get_billing_last_name(),
                'service' => $service_info,
                'gross' => $gross,
                'fee' => $fee,
                'staff_portion' => $staff_portion
            ];
        }
        
        return $result;
    }
    
    /**
     * Get products that this staff member can provide
     */
    private function get_staff_products($staff_id) {
        // Query products that have this staff member assigned
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_sod_staff_id',
                    'value' => $staff_id,
                    'compare' => '='
                )
            )
        );
        
        $staff_specific_products = get_posts($args);
        
        // Also get products without specific staff assignment (general products)
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'slug',
                    'terms' => array('services', 'classes', 'events'),
                    'operator' => 'IN'
                )
            ),
            'meta_query' => array(
                array(
                    'key' => '_sod_staff_id',
                    'compare' => 'NOT EXISTS'
                )
            )
        );
        
        $general_products = get_posts($args);
        
        // Combine and return all products
        return array_merge($staff_specific_products, $general_products);
    }
    
    /**
     * Get staff availability slots
     */
    private function get_staff_availability($staff_id) {
        global $wpdb;
        
        // Get recurring availability
        $recurring = $wpdb->get_results($wpdb->prepare(
            "SELECT sa.*, p.post_title as product_name 
             FROM {$wpdb->prefix}sod_staff_availability sa
             LEFT JOIN {$wpdb->posts} p ON sa.product_id = p.ID
             WHERE sa.staff_id = %d
             AND sa.day_of_week IS NOT NULL
             ORDER BY FIELD(sa.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), sa.start_time",
            $staff_id
        ));
        
        // Get specific date availability
        $specific_dates = $wpdb->get_results($wpdb->prepare(
            "SELECT sa.*, p.post_title as product_name 
             FROM {$wpdb->prefix}sod_staff_availability sa
             LEFT JOIN {$wpdb->posts} p ON sa.product_id = p.ID
             WHERE sa.staff_id = %d
             AND sa.date IS NOT NULL
             ORDER BY sa.date, sa.start_time",
            $staff_id
        ));
        
        return [
            'recurring' => $recurring,
            'specific_dates' => $specific_dates
        ];
    }
    
    /**
     * Process booking action form submission
     */
    private function process_booking_action() {
        if (!isset($_POST['sod_booking_action_nonce']) || 
            !wp_verify_nonce($_POST['sod_booking_action_nonce'], 'sod_booking_action')) {
            return;
        }
        
        $action = sanitize_text_field($_POST['sod_booking_action']);
        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
        
        if (!$booking_id) return;
        
        $staff_id = $this->get_staff_id_for_user(get_current_user_id());
        
        // Verify this booking belongs to this staff member
        global $wpdb;
        $booking_staff_id = $wpdb->get_var($wpdb->prepare(
            "SELECT staff_id FROM {$wpdb->prefix}sod_bookings WHERE booking_id = %d", 
            $booking_id
        ));
        
        if ($booking_staff_id != $staff_id) {
            wc_add_notice(__('You do not have permission to modify this booking.', 'spark-of-divine-scheduler'), 'error');
            return;
        }
        
        switch ($action) {
            case 'confirm':
                $this->confirm_booking($booking_id);
                break;
                
            case 'reschedule':
                $new_date = isset($_POST['new_date']) ? sanitize_text_field($_POST['new_date']) : '';
                $new_time = isset($_POST['new_time']) ? sanitize_text_field($_POST['new_time']) : '';
                $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
                
                if (!$new_date || !$new_time) {
                    wc_add_notice(__('New date and time are required for rescheduling.', 'spark-of-divine-scheduler'), 'error');
                    return;
                }
                
                $this->reschedule_booking($booking_id, $new_date, $new_time, $message);
                break;
                
            case 'cancel':
                $reason = isset($_POST['reason']) ? sanitize_textarea_field($_POST['reason']) : '';
                $this->cancel_booking($booking_id, $reason);
                break;
        }
    }
    
    /**
     * Confirm a booking
     */
    private function confirm_booking($booking_id) {
        global $wpdb;
        
        // Update booking status
        $updated = $wpdb->update(
            "{$wpdb->prefix}sod_bookings",
            array('status' => 'confirmed'),
            array('booking_id' => $booking_id),
            array('%s'),
            array('%d')
        );
        
        // Also update in post meta
        update_post_meta($booking_id, 'sod_booking_status', 'confirmed');
        
        // Update related order
        $order_id = $this->get_order_id_from_booking($booking_id);
        if ($order_id) {
            $order = wc_get_order($order_id);
            if ($order && !$order->has_status('completed')) {
                // Add note to order
                $order->add_order_note(__('Booking confirmed by staff member', 'spark-of-divine-scheduler'));
                
                // Update order status if not already completed or processing
                if (!$order->has_status(array('completed', 'processing'))) {
                    $order->update_status('processing');
                }
            }
        }
        
        // Send confirmation emails
        $this->send_booking_confirmation_emails($booking_id);
        
        wc_add_notice(__('Booking confirmed successfully.', 'spark-of-divine-scheduler'), 'success');
    }
    
    /**
     * Reschedule a booking
     */
    private function reschedule_booking($booking_id, $new_date, $new_time, $message = '') {
        global $wpdb;
        
        // Get original booking details
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sod_bookings WHERE booking_id = %d", 
            $booking_id
        ));
        
        if (!$booking) {
            wc_add_notice(__('Booking not found.', 'spark-of-divine-scheduler'), 'error');
            return;
        }
        
        // Calculate duration from original booking
        $original_duration = strtotime($booking->end_time) - strtotime($booking->start_time);
        
        // Create new start/end times
        $new_start_datetime = $new_date . ' ' . $new_time . ':00';
        $new_end_datetime = date('Y-m-d H:i:s', strtotime($new_start_datetime) + $original_duration);
        
        // Update booking with new times
        $updated = $wpdb->update(
            "{$wpdb->prefix}sod_bookings",
            array(
                'start_time' => $new_start_datetime,
                'end_time' => $new_end_datetime,
                'status' => 'rescheduled'
            ),
            array('booking_id' => $booking_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
        
        // Also update in post meta
        update_post_meta($booking_id, 'sod_booking_date', $new_date);
        update_post_meta($booking_id, 'sod_booking_time', $new_time);
        update_post_meta($booking_id, 'sod_booking_status', 'rescheduled');
        update_post_meta($booking_id, 'sod_reschedule_message', $message);
        
        // Update related order
        $order_id = $this->get_order_id_from_booking($booking_id);
        if ($order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                // Add note to order
                $note = sprintf(
                    __('Booking rescheduled to %s at %s by staff member. %s', 'spark-of-divine-scheduler'),
                    date_i18n(get_option('date_format'), strtotime($new_date)),
                    date_i18n(get_option('time_format'), strtotime($new_time)),
                    $message ? "Note: $message" : ""
                );
                $order->add_order_note($note);
            }
        }
        
        // Send reschedule emails
        $this->send_booking_reschedule_emails($booking_id, $new_start_datetime, $message);
        
        wc_add_notice(__('Booking rescheduled successfully.', 'spark-of-divine-scheduler'), 'success');
    }
    
    /**
     * Cancel a booking
     */
    private function cancel_booking($booking_id, $reason = '') {
        global $wpdb;
        
        // Update booking status
        $updated = $wpdb->update(
            "{$wpdb->prefix}sod_bookings",
            array('status' => 'cancelled'),
            array('booking_id' => $booking_id),
            array('%s'),
            array('%d')
        );
        
        // Also update in post meta
        update_post_meta($booking_id, 'sod_booking_status', 'cancelled');
        update_post_meta($booking_id, 'sod_cancellation_reason', $reason);
        
        // Update related order
        $order_id = $this->get_order_id_from_booking($booking_id);
        if ($order_id) {
            $order = wc_get_order($order_id);
            if ($order && !$order->has_status('cancelled')) {
                // Add note to order
                $note = __('Booking cancelled by staff member.', 'spark-of-divine-scheduler');
                if ($reason) {
                    $note .= ' ' . sprintf(__('Reason: %s', 'spark-of-divine-scheduler'), $reason);
                }
                $order->add_order_note($note);
                
                // Update order status
                $order->update_status('cancelled');
            }
        }
        
        // Send cancellation emails
        $this->send_booking_cancellation_emails($booking_id, $reason);
        
        wc_add_notice(__('Booking cancelled successfully.', 'spark-of-divine-scheduler'), 'success');
    }
    
    /**
     * Get order ID from booking ID
     */
    private function get_order_id_from_booking($booking_id) {
        global $wpdb;
        
        // First try to find an order with _sod_booking_id meta
        $order_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
             WHERE meta_key = '_sod_booking_id' AND meta_value = %d",
            $booking_id
        ));
        
        if ($order_id) {
            return $order_id;
        }
        
        // Try alternate relationship - booking might have order_id meta
        $order_id = get_post_meta($booking_id, 'order_id', true);
        if ($order_id) {
            return $order_id;
        }
        
        // Check if booking_id is stored in a different format
        $order_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
             WHERE meta_key = '_sod_booking_id' AND meta_value LIKE %s",
            '%"id":' . $booking_id . ',%'
        ));
        
        return $order_id;
    }
    
    /**
     * Send booking confirmation emails
     */
    private function send_booking_confirmation_emails($booking_id) {
        // Get booking details
        global $wpdb;
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT b.*, 
                c.meta_value as customer_name,
                p.meta_value as customer_phone,
                e.meta_value as customer_email,
                COALESCE(prd.post_title, s.post_title) as service_name,
                u.display_name as staff_name,
                u.user_email as staff_email
             FROM {$wpdb->prefix}sod_bookings b
             LEFT JOIN {$wpdb->postmeta} c ON b.booking_id = c.post_id AND c.meta_key = 'sod_booking_customer_name'
             LEFT JOIN {$wpdb->postmeta} p ON b.booking_id = p.post_id AND p.meta_key = 'sod_booking_customer_phone'
             LEFT JOIN {$wpdb->postmeta} e ON b.booking_id = e.post_id AND e.meta_key = 'sod_booking_customer_email'
             LEFT JOIN {$wpdb->posts} prd ON b.product_id = prd.ID
             LEFT JOIN {$wpdb->posts} s ON b.service_id = s.ID
             LEFT JOIN {$wpdb->prefix}sod_staff staff ON b.staff_id = staff.staff_id
             LEFT JOIN {$wpdb->users} u ON staff.user_id = u.ID
             WHERE b.booking_id = %d",
            $booking_id
        ));
        
        if (!$booking) return;
        
        // Format dates for display
        $date_formatted = date_i18n(get_option('date_format'), strtotime($booking->start_time));
        $time_formatted = date_i18n(get_option('time_format'), strtotime($booking->start_time));
        
        // First check if we can use WooCommerce email templates
        if (class_exists('WC_Email') && class_exists('SOD_Booking_Email')) {
            $mailer = WC()->mailer();
            $emails = $mailer->get_emails();
            
            // Find our custom booking email class if it exists
            foreach ($emails as $email) {
                if ($email instanceof SOD_Booking_Email && $email->id === 'customer_booking_confirmed') {
                    // Trigger the email
                    $email->trigger($booking_id);
                    return;
                }
            }
        }
        
        // Fallback to manual email sending
        $customer_email = $booking->customer_email;
        if ($customer_email) {
            $subject = sprintf(__('Your booking with %s has been confirmed', 'spark-of-divine-scheduler'), get_bloginfo('name'));
            
            $message = sprintf(
                __("Hello %s,\n\nYour booking has been confirmed:\n\nService: %s\nStaff: %s\nDate: %s\nTime: %s\n\nThank you for booking with us!\n\n%s", 'spark-of-divine-scheduler'),
                $booking->customer_name,
                $booking->service_name,
                $booking->staff_name,
                $date_formatted,
                $time_formatted,
                get_bloginfo('name')
            );
            
            // Send email to customer
            wp_mail($customer_email, $subject, $message, 'Content-Type: text/plain; charset=UTF-8');
        }
        
        // Email to admin
        $admin_email = get_option('admin_email');
        $subject = sprintf(__('Booking #%d has been confirmed', 'spark-of-divine-scheduler'), $booking_id);
        
        $message = sprintf(
            __("A booking has been confirmed:\n\nBooking ID: %d\nCustomer: %s\nPhone: %s\nEmail: %s\nService: %s\nStaff: %s\nDate: %s\nTime: %s", 'spark-of-divine-scheduler'),
            $booking_id,
            $booking->customer_name,
            $booking->customer_phone,
            $booking->customer_email,
            $booking->service_name,
            $booking->staff_name,
            $date_formatted,
            $time_formatted
        );
        
        // Send email to admin
        wp_mail($admin_email, $subject, $message, 'Content-Type: text/plain; charset=UTF-8');
    }
    
    /**
     * Send booking reschedule emails
     */
    private function send_booking_reschedule_emails($booking_id, $new_start_time, $message = '') {
        global $wpdb;
        
        // Get booking details
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT b.*, 
                c.meta_value as customer_name,
                p.meta_value as customer_phone,
                e.meta_value as customer_email,
                COALESCE(prd.post_title, s.post_title) as service_name,
                u.display_name as staff_name
             FROM {$wpdb->prefix}sod_bookings b
             LEFT JOIN {$wpdb->postmeta} c ON b.booking_id = c.post_id AND c.meta_key = 'sod_booking_customer_name'
             LEFT JOIN {$wpdb->postmeta} p ON b.booking_id = p.post_id AND p.meta_key = 'sod_booking_customer_phone'
             LEFT JOIN {$wpdb->postmeta} e ON b.booking_id = e.post_id AND e.meta_key = 'sod_booking_customer_email'
             LEFT JOIN {$wpdb->posts} prd ON b.product_id = prd.ID
             LEFT JOIN {$wpdb->posts} s ON b.service_id = s.ID 
             LEFT JOIN {$wpdb->prefix}sod_staff staff ON b.staff_id = staff.staff_id
             LEFT JOIN {$wpdb->users} u ON staff.user_id = u.ID
             WHERE b.booking_id = %d",
            $booking_id
        ));
        
        if (!$booking) return;
        
        // Format dates for display
        $date_formatted = date_i18n(get_option('date_format'), strtotime($new_start_time));
        $time_formatted = date_i18n(get_option('time_format'), strtotime($new_start_time));
        
        // Check if WooCommerce email templates exist
        if (class_exists('WC_Email') && class_exists('SOD_Booking_Email')) {
            $mailer = WC()->mailer();
            $emails = $mailer->get_emails();
            
            foreach ($emails as $email) {
                if ($email instanceof SOD_Booking_Email && $email->id === 'customer_booking_updated') {
                    // Trigger the email
                    $email->trigger($booking_id);
                    return;
                }
            }
        }
        
        // Fallback to manual email
        $customer_email = $booking->customer_email;
        if ($customer_email) {
            $subject = sprintf(__('Your booking with %s has been rescheduled', 'spark-of-divine-scheduler'), get_bloginfo('name'));
            
            $message_text = sprintf(
                __("Hello %s,\n\nYour booking has been rescheduled:\n\nService: %s\nStaff: %s\nNew Date: %s\nNew Time: %s\n\n", 'spark-of-divine-scheduler'),
                $booking->customer_name,
                $booking->service_name,
                $booking->staff_name,
                $date_formatted,
                $time_formatted
            );
            
            if ($message) {
                $message_text .= sprintf(__("Message from staff: %s\n\n", 'spark-of-divine-scheduler'), $message);
            }
            
            $message_text .= sprintf(__("Thank you for your understanding.\n\n%s", 'spark-of-divine-scheduler'), get_bloginfo('name'));
            
            // Send email to customer
            wp_mail($customer_email, $subject, $message_text, 'Content-Type: text/plain; charset=UTF-8');
        }
        
        // Email to admin
        $admin_email = get_option('admin_email');
        $subject = sprintf(__('Booking #%d has been rescheduled', 'spark-of-divine-scheduler'), $booking_id);
        
        $message_text = sprintf(
            __("A booking has been rescheduled:\n\nBooking ID: %d\nCustomer: %s\nPhone: %s\nEmail: %s\nService: %s\nStaff: %s\nNew Date: %s\nNew Time: %s\n\n", 'spark-of-divine-scheduler'),
            $booking_id,
            $booking->customer_name,
            $booking->customer_phone,
            $booking->customer_email,
            $booking->service_name,
            $booking->staff_name,
            $date_formatted,
            $time_formatted
        );
        
        if ($message) {
            $message_text .= sprintf(__("Message from staff: %s", 'spark-of-divine-scheduler'), $message);
        }
        
        // Send email to admin
        wp_mail($admin_email, $subject, $message_text, 'Content-Type: text/plain; charset=UTF-8');
    }
    
    /**
     * Send booking cancellation emails
     */
    private function send_booking_cancellation_emails($booking_id, $reason = '') {
        global $wpdb;
        
        // Get booking details
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT b.*, 
                c.meta_value as customer_name,
                p.meta_value as customer_phone,
                e.meta_value as customer_email,
                COALESCE(prd.post_title, s.post_title) as service_name,
                u.display_name as staff_name
             FROM {$wpdb->prefix}sod_bookings b
             LEFT JOIN {$wpdb->postmeta} c ON b.booking_id = c.post_id AND c.meta_key = 'sod_booking_customer_name'
             LEFT JOIN {$wpdb->postmeta} p ON b.booking_id = p.post_id AND p.meta_key = 'sod_booking_customer_phone'
             LEFT JOIN {$wpdb->postmeta} e ON b.booking_id = e.post_id AND e.meta_key = 'sod_booking_customer_email'
             LEFT JOIN {$wpdb->posts} prd ON b.product_id = prd.ID
             LEFT JOIN {$wpdb->posts} s ON b.service_id = s.ID
             LEFT JOIN {$wpdb->prefix}sod_staff staff ON b.staff_id = staff.staff_id
             LEFT JOIN {$wpdb->users} u ON staff.user_id = u.ID
             WHERE b.booking_id = %d",
            $booking_id
        ));
        
        if (!$booking) return;
        
        // Format dates for display
        $date_formatted = date_i18n(get_option('date_format'), strtotime($booking->start_time));
        $time_formatted = date_i18n(get_option('time_format'), strtotime($booking->start_time));
        
        // Check if WooCommerce email templates exist
        if (class_exists('WC_Email') && class_exists('SOD_Booking_Email')) {
            $mailer = WC()->mailer();
            $emails = $mailer->get_emails();
            
            foreach ($emails as $email) {
                if ($email instanceof SOD_Booking_Email && $email->id === 'customer_booking_canceled') {
                    // Trigger the email
                    $email->trigger($booking_id);
                    return;
                }
            }
        }
        
        // Fallback to manual email
        $customer_email = $booking->customer_email;
        if ($customer_email) {
            $subject = sprintf(__('Your booking with %s has been cancelled', 'spark-of-divine-scheduler'), get_bloginfo('name'));
            
            $message_text = sprintf(
                __("Hello %s,\n\nYour booking has been cancelled:\n\nService: %s\nStaff: %s\nDate: %s\nTime: %s\n\n", 'spark-of-divine-scheduler'),
                $booking->customer_name,
                $booking->service_name,
                $booking->staff_name,
                $date_formatted,
                $time_formatted
            );
            
            if ($reason) {
                $message_text .= sprintf(__("Reason: %s\n\n", 'spark-of-divine-scheduler'), $reason);
            }
            
            $message_text .= sprintf(__("We apologize for any inconvenience. Please contact us if you would like to rebook.\n\n%s", 'spark-of-divine-scheduler'), get_bloginfo('name'));
            
            // Send email to customer
            wp_mail($customer_email, $subject, $message_text, 'Content-Type: text/plain; charset=UTF-8');
        }
        
        // Email to admin
        $admin_email = get_option('admin_email');
        $subject = sprintf(__('Booking #%d has been cancelled', 'spark-of-divine-scheduler'), $booking_id);
        
        $message_text = sprintf(
            __("A booking has been cancelled:\n\nBooking ID: %d\nCustomer: %s\nPhone: %s\nEmail: %s\nService: %s\nStaff: %s\nDate: %s\nTime: %s\n\n", 'spark-of-divine-scheduler'),
            $booking_id,
            $booking->customer_name,
            $booking->customer_phone,
            $booking->customer_email,
            $booking->service_name,
            $booking->staff_name,
            $date_formatted,
            $time_formatted
        );
        
        if ($reason) {
            $message_text .= sprintf(__("Reason: %s", 'spark-of-divine-scheduler'), $reason);
        }
        
        // Send email to admin
        wp_mail($admin_email, $subject, $message_text, 'Content-Type: text/plain; charset=UTF-8');
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        // Only enqueue on relevant pages
        if (!is_account_page() && !$this->is_staff_schedule_page()) return;
        
        wp_enqueue_style(
            'sod-staff-dashboard-style', 
            SOD_PLUGIN_URL . 'assets/css/staff-dashboard.css', 
            [], 
            SOD_VERSION
        );
        
        wp_enqueue_script(
            'sod-staff-dashboard-script',
            SOD_PLUGIN_URL . 'assets/js/staff-dashboard.js',
            ['jquery'],
            SOD_VERSION,
            true
        );
        
        wp_localize_script('sod-staff-dashboard-script', 'sod_staff_dashboard', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sod_staff_action'),
            'i18n' => [
                'confirm_booking' => __('Are you sure you want to confirm this booking?', 'spark-of-divine-scheduler'),
                'cancel_booking' => __('Are you sure you want to cancel this booking?', 'spark-of-divine-scheduler'),
                'reschedule_booking' => __('Please select a new date and time', 'spark-of-divine-scheduler'),
                'errorMessage' => __('An error occurred. Please try again.', 'spark-of-divine-scheduler')
            ]
        ]);
        
        // Enqueue datepicker if we're on availability page
        if (is_account_page() && isset($_GET['section']) && $_GET['section'] == 'availability' || 
            isset($GLOBALS['wp_query']->query_vars['staff_schedule']) && 
            $GLOBALS['wp_query']->query_vars['staff_schedule'] == 'availability') {
            
            wp_enqueue_script('jquery-ui-datepicker');
            wp_enqueue_style(
                'jquery-ui-style',
                '//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css',
                [],
                '1.12.1'
            );
        }
    }
    
    /**
     * Check if current page is staff schedule page
     */
    private function is_staff_schedule_page() {
        global $wp_query;
        return isset($wp_query->query_vars['staff_schedule']);
    }
}

// Initialize the class
$GLOBALS['sod_staff_dashboard'] = new SOD_Staff_Dashboard();