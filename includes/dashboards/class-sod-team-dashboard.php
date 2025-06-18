<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Team Dashboard Manager
 * 
 * Handles team dashboard functionality for Spark of Divine staff team, including:
 * - Managing schedules for all staff members
 * - Managing customers
 * - Viewing and editing bookings
 * - WooCommerce integration for team purchases
 */
class SOD_Team_Dashboard {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Register role upon plugin initialization
        add_action('init', array($this, 'register_team_role'));
        
        // Add custom endpoints for team dashboard pages
        add_action('init', array($this, 'add_team_endpoints'));
        
        // Add rewrite rules for team dashboard pages
        /*add_action('init', array($this, 'add_team_rewrite_rules'));*/
        add_action('template_redirect', array($this, 'handle_team_dashboard_page'));
        
        // Register shortcode
        add_shortcode('sod_team_dashboard', array($this, 'team_dashboard_shortcode'));
        
        // AJAX handlers for team actions
        add_action('wp_ajax_sod_team_update_staff_schedule', array($this, 'ajax_update_staff_schedule'));
        add_action('wp_ajax_sod_team_update_customer', array($this, 'ajax_update_customer'));
        add_action('wp_ajax_sod_team_manage_booking', array($this, 'ajax_manage_booking'));
        add_action('wp_ajax_sod_team_get_staff_details', array($this, 'ajax_get_staff_details'));
        add_action('wp_ajax_sod_team_get_customer_details', array($this, 'ajax_get_customer_details'));
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Register spark-team role
     */
    public function register_team_role() {
        // Check if the role already exists
        if (!get_role('spark-team')) {
            // Create the spark-team role with specific capabilities
            add_role(
                'spark-team',
                'Spark Team',
                array(
                    'read' => true,
                    'edit_posts' => false,
                    'delete_posts' => false,
                    'publish_posts' => false,
                    'upload_files' => true,
                    'manage_sod_bookings' => true,
                    'manage_sod_staff' => true,
                    'manage_sod_customers' => true,
                )
            );
            
            // Log creation of the role
            if (function_exists('sod_debug_log')) {
                sod_debug_log('Created spark-team role', 'Team Dashboard');
            }
        }
    }
    
    /**
     * Add team dashboard endpoints
     */
    public function add_team_endpoints() {
        // These will be used for rewrite rules
        add_rewrite_tag('%team_dashboard%', '([^&]+)');
        add_rewrite_tag('%staff_id%', '([0-9]+)');
        add_rewrite_tag('%customer_id%', '([0-9]+)');
        add_rewrite_tag('%booking_id%', '([0-9]+)');
    }
    
    /**
     * Add rewrite rules for team dashboard pages
    public function add_team_rewrite_rules() {
        // Main dashboard page
        add_rewrite_rule(
            'team-dashboard/?$',
            'index.php?team_dashboard=overview',
            'top'
        );
        
        // Staff management
        add_rewrite_rule(
            'team-dashboard/staff/?$',
            'index.php?team_dashboard=staff',
            'top'
        );
        
        // Individual staff schedule management
        add_rewrite_rule(
            'team-dashboard/staff/([0-9]+)/?$',
            'index.php?team_dashboard=staff_schedule&staff_id=$matches[1]',
            'top'
        );
        
        // Customer management
        add_rewrite_rule(
            'team-dashboard/customers/?$',
            'index.php?team_dashboard=customers',
            'top'
        );
        
        // Individual customer management
        add_rewrite_rule(
            'team-dashboard/customers/([0-9]+)/?$',
            'index.php?team_dashboard=customer_details&customer_id=$matches[1]',
            'top'
        );
        
        // Bookings management
        add_rewrite_rule(
            'team-dashboard/bookings/?$',
            'index.php?team_dashboard=bookings',
            'top'
        );
        
        // Individual booking management
        add_rewrite_rule(
            'team-dashboard/bookings/([0-9]+)/?$',
            'index.php?team_dashboard=booking_details&booking_id=$matches[1]',
            'top'
        );
        
        // Product purchases
        add_rewrite_rule(
            'team-dashboard/purchases/?$',
            'index.php?team_dashboard=purchases',
            'top'
        );
        
        // Flush rewrite rules if needed
        if (get_option('sod_flush_team_rewrite_rules', 'no') === 'yes') {
            flush_rewrite_rules();
            update_option('sod_flush_team_rewrite_rules', 'no');
        }
    }
    */
    
    /**
     * Handle team dashboard page templates
     */
    public function handle_team_dashboard_page() {
        global $wp_query;
        
        if (!isset($wp_query->query_vars['team_dashboard'])) {
            return;
        }
        
        // Check if user is logged in and has appropriate role
        if (!$this->can_access_team_dashboard()) {
            auth_redirect();
            exit;
        }
        
        $section = $wp_query->query_vars['team_dashboard'];
        
        // Parse additional parameters if available
        $staff_id = isset($wp_query->query_vars['staff_id']) ? intval($wp_query->query_vars['staff_id']) : 0;
        $customer_id = isset($wp_query->query_vars['customer_id']) ? intval($wp_query->query_vars['customer_id']) : 0;
        $booking_id = isset($wp_query->query_vars['booking_id']) ? intval($wp_query->query_vars['booking_id']) : 0;
        
        // Load the appropriate template
        switch ($section) {
            case 'overview':
                $this->load_team_dashboard_template();
                break;
                
            case 'staff':
                $this->load_staff_management_template();
                break;
                
            case 'staff_schedule':
                $this->load_staff_schedule_template($staff_id);
                break;
                
            case 'customers':
                $this->load_customer_management_template();
                break;
                
            case 'customer_details':
                $this->load_customer_details_template($customer_id);
                break;
                
            case 'bookings':
                $this->load_bookings_management_template();
                break;
                
            case 'booking_details':
                $this->load_booking_details_template($booking_id);
                break;
                
            case 'purchases':
                $this->load_purchases_template();
                break;
                
            default:
                $this->load_team_dashboard_template();
                break;
        }
        
        exit;
    }
    
    /**
     * Check if current user can access team dashboard
     */
    private function can_access_team_dashboard() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        return in_array('spark-team', (array) $user->roles) || 
               in_array('administrator', (array) $user->roles) ||
               current_user_can('manage_options');
    }
    
    /**
     * Load team dashboard template
     */
    private function load_team_dashboard_template() {
        // Get dashboard data
        $upcoming_bookings = $this->get_upcoming_bookings(5);
        $recent_customers = $this->get_recent_customers(5);
        $pending_bookings = $this->get_pending_bookings();
        
        // Set page title
        add_filter('the_title', function($title) {
            return __('Team Dashboard', 'spark-of-divine-scheduler');
        });
        
        // Load template
        get_header();
        echo '<div class="sod-team-dashboard">';
        include $this->get_template_path('team-dashboard.php');
        echo '</div>';
        get_footer();
    }
    
    /**
     * Load staff management template
     */
    private function load_staff_management_template() {
        // Get all staff
        $staff_members = $this->get_all_staff();
        
        // Set page title
        add_filter('the_title', function($title) {
            return __('Staff Management', 'spark-of-divine-scheduler');
        });
        
        // Load template
        get_header();
        echo '<div class="sod-team-dashboard">';
        include $this->get_template_path('staff-management.php');
        echo '</div>';
        get_footer();
    }
    
    /**
     * Load staff schedule template
     */
    private function load_staff_schedule_template($staff_id) {
        if (!$staff_id) {
            wp_redirect(home_url('/team-dashboard/staff/'));
            exit;
        }
        
        // Get staff member details
        $staff_member = $this->get_staff_member($staff_id);
        if (!$staff_member) {
            wp_redirect(home_url('/team-dashboard/staff/'));
            exit;
        }
        
        // Get staff schedule
        $schedule_date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : date('Y-m-d');
        $staff_schedule = $this->get_staff_schedule($staff_id, $schedule_date);
        
        // Get all products/services
        $products = $this->get_all_products();
        
        // Set page title
        add_filter('the_title', function($title) use ($staff_member) {
            return sprintf(__('Schedule for %s', 'spark-of-divine-scheduler'), $staff_member->display_name);
        });
        
        // Load template
        get_header();
        echo '<div class="sod-team-dashboard">';
        include $this->get_template_path('staff-schedule.php');
        echo '</div>';
        get_footer();
    }
    
    /**
     * Load customer management template
     */
    private function load_customer_management_template() {
        // Get filters
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        $page = isset($_GET['cpage']) ? intval($_GET['cpage']) : 1;
        
        // Get customers
        $customers = $this->get_filtered_customers($search, $page);
        $total_customers = $this->get_total_customers($search);
        $per_page = 20;
        $total_pages = ceil($total_customers / $per_page);
        
        // Set page title
        add_filter('the_title', function($title) {
            return __('Customer Management', 'spark-of-divine-scheduler');
        });
        
        // Load template
        get_header();
        echo '<div class="sod-team-dashboard">';
        include $this->get_template_path('customer-management.php');
        echo '</div>';
        get_footer();
    }
    
    /**
     * Load customer details template
     */
    private function load_customer_details_template($customer_id) {
        if (!$customer_id) {
            wp_redirect(home_url('/team-dashboard/customers/'));
            exit;
        }
        
        // Get customer details
        $customer = $this->get_customer($customer_id);
        if (!$customer) {
            wp_redirect(home_url('/team-dashboard/customers/'));
            exit;
        }
        
        // Get customer bookings
        $customer_bookings = $this->get_customer_bookings($customer_id);
        
        // Set page title
        add_filter('the_title', function($title) use ($customer) {
            return sprintf(__('Customer Details: %s', 'spark-of-divine-scheduler'), $customer->name);
        });
        
        // Load template
        get_header();
        echo '<div class="sod-team-dashboard">';
        include $this->get_template_path('customer-details.php');
        echo '</div>';
        get_footer();
    }
    
    /**
     * Load bookings management template
     */
    private function load_bookings_management_template() {
        // Get filters
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'upcoming';
        $date_filter = isset($_GET['date_range']) ? sanitize_text_field($_GET['date_range']) : '30days';
        $staff_filter = isset($_GET['staff']) ? intval($_GET['staff']) : 0;
        $service_filter = isset($_GET['service']) ? intval($_GET['service']) : 0;
        
        // Get all staff for filter dropdown
        $all_staff = $this->get_all_staff();
        
        // Get all services/products for filter dropdown
        $all_services = $this->get_all_products();
        
        // Get bookings based on filters
        $bookings = $this->get_filtered_bookings($status_filter, $date_filter, $staff_filter, $service_filter);
        
        // Set page title
        add_filter('the_title', function($title) {
            return __('Bookings Management', 'spark-of-divine-scheduler');
        });
        
        // Load template
        get_header();
        echo '<div class="sod-team-dashboard">';
        include $this->get_template_path('bookings-management.php');
        echo '</div>';
        get_footer();
    }
    
    /**
     * Load booking details template
     */
    private function load_booking_details_template($booking_id) {
        if (!$booking_id) {
            wp_redirect(home_url('/team-dashboard/bookings/'));
            exit;
        }
        
        // Get booking details
        $booking = $this->get_booking($booking_id);
        if (!$booking) {
            wp_redirect(home_url('/team-dashboard/bookings/'));
            exit;
        }
        
        // Get related customer
        $customer = $this->get_customer($booking->customer_id);
        
        // Get related staff
        $staff = $this->get_staff_member($booking->staff_id);
        
        // Get related product/service
        $product = wc_get_product($booking->product_id);
        
        // Get related order if any
        $order_id = $this->get_order_id_from_booking($booking_id);
        $order = $order_id ? wc_get_order($order_id) : null;
        
        // Set page title
        add_filter('the_title', function($title) use ($booking, $customer, $staff) {
            $customer_name = $customer ? $customer->name : __('Unknown', 'spark-of-divine-scheduler');
            $staff_name = $staff ? $staff->display_name : __('Unknown', 'spark-of-divine-scheduler');
            return sprintf(__('Booking #%d: %s with %s', 'spark-of-divine-scheduler'), $booking->booking_id, $customer_name, $staff_name);
        });
        
        // Load template
        get_header();
        echo '<div class="sod-team-dashboard">';
        include $this->get_template_path('booking-details.php');
        echo '</div>';
        get_footer();
    }
    
    /**
     * Load purchases template
     */
    private function load_purchases_template() {
        // Get WooCommerce products for team purchases
        $products = wc_get_products(array(
            'status' => 'publish',
            'limit' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ));
        
        // Set page title
        add_filter('the_title', function($title) {
            return __('Product Purchases', 'spark-of-divine-scheduler');
        });
        
        // Load template
        get_header();
        echo '<div class="sod-team-dashboard">';
        include $this->get_template_path('team-purchases.php');
        echo '</div>';
        get_footer();
    }
    
    /**
     * Team dashboard shortcode
     */
    public function team_dashboard_shortcode($atts) {
        // Parse attributes
        $atts = shortcode_atts(array(
            'view' => 'overview',
            'staff_id' => 0,
            'customer_id' => 0,
            'booking_id' => 0,
        ), $atts);
        
        // Check if user has access
        if (!$this->can_access_team_dashboard()) {
            return sprintf(
                '<p>%s <a href="%s">%s</a></p>',
                __('You must be logged in as a team member to view this dashboard.', 'spark-of-divine-scheduler'),
                wp_login_url(get_permalink()),
                __('Log in', 'spark-of-divine-scheduler')
            );
        }
        
        // Start output buffering
        ob_start();
        
        // Render appropriate view
        switch ($atts['view']) {
            case 'staff':
                $staff_members = $this->get_all_staff();
                include $this->get_template_path('staff-management.php');
                break;
                
            case 'staff_schedule':
                $staff_id = intval($atts['staff_id']);
                if ($staff_id > 0) {
                    $staff_member = $this->get_staff_member($staff_id);
                    $schedule_date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : date('Y-m-d');
                    $staff_schedule = $this->get_staff_schedule($staff_id, $schedule_date);
                    $products = $this->get_all_products();
                    include $this->get_template_path('staff-schedule.php');
                } else {
                    echo '<p>' . __('Invalid staff ID provided.', 'spark-of-divine-scheduler') . '</p>';
                }
                break;
                
            case 'customers':
                $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
                $page = isset($_GET['cpage']) ? intval($_GET['cpage']) : 1;
                $customers = $this->get_filtered_customers($search, $page);
                $total_customers = $this->get_total_customers($search);
                $per_page = 20;
                $total_pages = ceil($total_customers / $per_page);
                include $this->get_template_path('customer-management.php');
                break;
                
            case 'customer_details':
                $customer_id = intval($atts['customer_id']);
                if ($customer_id > 0) {
                    $customer = $this->get_customer($customer_id);
                    $customer_bookings = $this->get_customer_bookings($customer_id);
                    include $this->get_template_path('customer-details.php');
                } else {
                    echo '<p>' . __('Invalid customer ID provided.', 'spark-of-divine-scheduler') . '</p>';
                }
                break;
                
            case 'bookings':
                $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'upcoming';
                $date_filter = isset($_GET['date_range']) ? sanitize_text_field($_GET['date_range']) : '30days';
                $staff_filter = isset($_GET['staff']) ? intval($_GET['staff']) : 0;
                $service_filter = isset($_GET['service']) ? intval($_GET['service']) : 0;
                $all_staff = $this->get_all_staff();
                $all_services = $this->get_all_products();
                $bookings = $this->get_filtered_bookings($status_filter, $date_filter, $staff_filter, $service_filter);
                include $this->get_template_path('bookings-management.php');
                break;
                
            case 'booking_details':
                $booking_id = intval($atts['booking_id']);
                if ($booking_id > 0) {
                    $booking = $this->get_booking($booking_id);
                    $customer = $this->get_customer($booking->customer_id);
                    $staff = $this->get_staff_member($booking->staff_id);
                    $product = wc_get_product($booking->product_id);
                    $order_id = $this->get_order_id_from_booking($booking_id);
                    $order = $order_id ? wc_get_order($order_id) : null;
                    include $this->get_template_path('booking-details.php');
                } else {
                    echo '<p>' . __('Invalid booking ID provided.', 'spark-of-divine-scheduler') . '</p>';
                }
                break;
                
            case 'purchases':
                $products = wc_get_products(array(
                    'status' => 'publish',
                    'limit' => -1,
                    'orderby' => 'title',
                    'order' => 'ASC',
                ));
                include $this->get_template_path('team-purchases.php');
                break;
                
            case 'overview':
            default:
                $upcoming_bookings = $this->get_upcoming_bookings(5);
                $recent_customers = $this->get_recent_customers(5);
                $pending_bookings = $this->get_pending_bookings();
                include $this->get_template_path('team-dashboard.php');
                break;
        }
        
        return ob_get_clean();
    }
    
    /**
     * Get template path
     */
    private function get_template_path($template_name) {
        // First check in theme for template override
        $theme_template = get_stylesheet_directory() . '/sod-templates/team-dashboard/' . $template_name;
        
        if (file_exists($theme_template)) {
            return $theme_template;
        }
        
        // Fallback to plugin template
        return SOD_PLUGIN_PATH . 'templates/team-dashboard/' . $template_name;
    }
    
    /**
     * AJAX handler for updating staff schedule
     */
    public function ajax_update_staff_schedule() {
        // Verify nonce
        if (!check_ajax_referer('sod_team_action', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'spark-of-divine-scheduler')));
            return;
        }
        
        if (!$this->can_access_team_dashboard()) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'spark-of-divine-scheduler')));
            return;
        }
        
        $staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;
        $action = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';
        
        if (!$staff_id || !$action) {
            wp_send_json_error(array('message' => __('Missing required parameters', 'spark-of-divine-scheduler')));
            return;
        }
        
        switch ($action) {
            case 'add_slot':
                $result = $this->add_staff_availability_slot($_POST);
                break;
                
            case 'update_slot':
                $result = $this->update_staff_availability_slot($_POST);
                break;
                
            case 'delete_slot':
                $slot_id = isset($_POST['slot_id']) ? intval($_POST['slot_id']) : 0;
                $result = $this->delete_staff_availability_slot($staff_id, $slot_id);
                break;
                
            default:
                wp_send_json_error(array('message' => __('Invalid action type', 'spark-of-divine-scheduler')));
                return;
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        } else {
            wp_send_json_success(array(
                'message' => __('Staff schedule updated successfully', 'spark-of-divine-scheduler'),
                'slot_id' => $result
            ));
        }
    }
    
    /**
     * AJAX handler for updating customer
     */
    public function ajax_update_customer() {
        // Verify nonce
        if (!check_ajax_referer('sod_team_action', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'spark-of-divine-scheduler')));
            return;
        }
        
        if (!$this->can_access_team_dashboard()) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'spark-of-divine-scheduler')));
            return;
        }
        
        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        
        if (!$customer_id) {
            wp_send_json_error(array('message' => __('Missing customer ID', 'spark-of-divine-scheduler')));
            return;
        }
        
        // Get customer data from POST
        $customer_data = array(
            'name' => isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '',
            'email' => isset($_POST['email']) ? sanitize_email($_POST['email']) : '',
            'phone' => isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '',
            'emergency_contact_name' => isset($_POST['emergency_contact_name']) ? sanitize_text_field($_POST['emergency_contact_name']) : '',
            'emergency_contact_phone' => isset($_POST['emergency_contact_phone']) ? sanitize_text_field($_POST['emergency_contact_phone']) : '',
            'notes' => isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '',
        );
        
        // Update customer in database
        $result = $this->update_customer($customer_id, $customer_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        } else {
            wp_send_json_success(array(
                'message' => __('Customer updated successfully', 'spark-of-divine-scheduler')
            ));
        }
    }
    
    /**
     * AJAX handler for managing booking
     */
    public function ajax_manage_booking() {
        // Verify nonce
        if (!check_ajax_referer('sod_team_action', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'spark-of-divine-scheduler')));
            return;
        }
        
        if (!$this->can_access_team_dashboard()) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'spark-of-divine-scheduler')));
            return;
        }
        
        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
        $action = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';
        
        if (!$booking_id || !$action) {
            wp_send_json_error(array('message' => __('Missing required parameters', 'spark-of-divine-scheduler')));
            return;
        }
        
        switch ($action) {
            case 'confirm':
                $result = $this->confirm_booking($booking_id);
                break;
                
            case 'reschedule':
                $new_date = isset($_POST['new_date']) ? sanitize_text_field($_POST['new_date']) : '';
                $new_time = isset($_POST['new_time']) ? sanitize_text_field($_POST['new_time']) : '';
                $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
                
                if (!$new_date || !$new_time) {
                    wp_send_json_error(array('message' => __('New date and time are required for rescheduling', 'spark-of-divine-scheduler')));
                    return;
                }
                
                $result = $this->reschedule_booking($booking_id, $new_date, $new_time, $message);
                break;
                
            case 'cancel':
                $reason = isset($_POST['reason']) ? sanitize_textarea_field($_POST['reason']) : '';
                $result = $this->cancel_booking($booking_id, $reason);
                break;
                
            case 'complete':
                $result = $this->complete_booking($booking_id);
                break;
                
            default:
                wp_send_json_error(array('message' => __('Invalid action type', 'spark-of-divine-scheduler')));
                return;
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        } else {
            wp_send_json_success(array(
                'message' => __('Booking updated successfully', 'spark-of-divine-scheduler')
            ));
        }
    }
    
    /**
     * AJAX handler for getting staff details
     */
    public function ajax_get_staff_details() {
        // Verify nonce
        if (!check_ajax_referer('sod_team_action', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'spark-of-divine-scheduler')));
            return;
        }
        
        if (!$this->can_access_team_dashboard()) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'spark-of-divine-scheduler')));
            return;
        }
        
        $staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;
        
        if (!$staff_id) {
            wp_send_json_error(array('message' => __('Missing staff ID', 'spark-of-divine-scheduler')));
            return;
        }
        
        // Get staff details
        $staff = $this->get_staff_member($staff_id);
        
        if (!$staff) {
            wp_send_json_error(array('message' => __('Staff member not found', 'spark-of-divine-scheduler')));
            return;
        }
        
        wp_send_json_success(array(
            'staff' => $staff
        ));
    }
    
    /**
     * AJAX handler for getting customer details
     */
    public function ajax_get_customer_details() {
        // Verify nonce
        if (!check_ajax_referer('sod_team_action', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'spark-of-divine-scheduler')));
            return;
        }
        
        if (!$this->can_access_team_dashboard()) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'spark-of-divine-scheduler')));
            return;
        }
        
        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        
        if (!$customer_id) {
            wp_send_json_error(array('message' => __('Missing customer ID', 'spark-of-divine-scheduler')));
            return;
        }
        
        // Get customer details
        $customer = $this->get_customer($customer_id);
        
        if (!$customer) {
            wp_send_json_error(array('message' => __('Customer not found', 'spark-of-divine-scheduler')));
            return;
        }
        
        wp_send_json_success(array(
            'customer' => $customer
        ));
    }
    
    /**
     * Add staff availability slot
     */
    private function add_staff_availability_slot($data) {
        global $wpdb;
        
        $staff_id = isset($data['staff_id']) ? intval($data['staff_id']) : 0;
        $product_id = isset($data['product_id']) ? intval($data['product_id']) : 0;
        $type = isset($data['availability_type']) ? sanitize_text_field($data['availability_type']) : '';
        $start_time = isset($data['start_time']) ? sanitize_text_field($data['start_time']) : '';
        $end_time = isset($data['end_time']) ? sanitize_text_field($data['end_time']) : '';
        
        // Validate required fields
        if (!$staff_id || !$product_id || !$type || !$start_time || !$end_time) {
            return new WP_Error('missing_data', __('Missing required availability data', 'spark-of-divine-scheduler'));
        }
        
        // Prepare data for insert
        $insert_data = array(
            'staff_id' => $staff_id,
            'product_id' => $product_id,
            'start_time' => $start_time,
            'end_time' => $end_time,
        );
        
        $format = array('%d', '%d', '%s', '%s');
        
        // Handle based on type
        if ($type === 'recurring') {
            $day_of_week = isset($data['day_of_week']) ? sanitize_text_field($data['day_of_week']) : '';
            $recurring_type = isset($data['recurring_type']) ? sanitize_text_field($data['recurring_type']) : 'weekly';
            $recurring_end_date = isset($data['recurring_end_date']) ? sanitize_text_field($data['recurring_end_date']) : null;
            
            if (!$day_of_week) {
                return new WP_Error('missing_data', __('Day of week is required for recurring availability', 'spark-of-divine-scheduler'));
            }
            
            $insert_data['day_of_week'] = $day_of_week;
            $insert_data['recurring_type'] = $recurring_type;
            
            if ($recurring_end_date) {
                $insert_data['recurring_end_date'] = $recurring_end_date;
                $format[] = '%s';
            }
            
            $format[] = '%s';
            $format[] = '%s';
        } else {
            $specific_date = isset($data['specific_date']) ? sanitize_text_field($data['specific_date']) : '';
            
            if (!$specific_date) {
                return new WP_Error('missing_data', __('Date is required for specific availability', 'spark-of-divine-scheduler'));
            }
            
            $insert_data['date'] = $specific_date;
            $format[] = '%s';
        }
        
        // Insert into database
        $result = $wpdb->insert(
            "{$wpdb->prefix}sod_staff_availability",
            $insert_data,
            $format
        );
        
        if (!$result) {
            return new WP_Error('db_error', __('Failed to add availability slot', 'spark-of-divine-scheduler'));
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update staff availability slot
     */
    private function update_staff_availability_slot($data) {
        global $wpdb;
        
        $staff_id = isset($data['staff_id']) ? intval($data['staff_id']) : 0;
        $slot_id = isset($data['slot_id']) ? intval($data['slot_id']) : 0;
        $start_time = isset($data['start_time']) ? sanitize_text_field($data['start_time']) : '';
        $end_time = isset($data['end_time']) ? sanitize_text_field($data['end_time']) : '';
        $recurring_end_date = isset($data['recurring_end_date']) ? sanitize_text_field($data['recurring_end_date']) : null;
        
        // Validate required fields
        if (!$staff_id || !$slot_id || !$start_time || !$end_time) {
            return new WP_Error('missing_data', __('Missing required availability data', 'spark-of-divine-scheduler'));
        }
        
        // Verify this slot belongs to this staff member
        $slot_staff_id = $wpdb->get_var($wpdb->prepare(
            "SELECT staff_id FROM {$wpdb->prefix}sod_staff_availability WHERE id = %d",
            $slot_id
        ));
        
        if ($slot_staff_id != $staff_id) {
            return new WP_Error('invalid_slot', __('This availability slot does not belong to the specified staff member', 'spark-of-divine-scheduler'));
        }
        
        // Update slot
        $update_data = array(
            'start_time' => $start_time,
            'end_time' => $end_time,
        );
        
        $update_format = array('%s', '%s');
        
        if ($recurring_end_date !== null) {
            if ($recurring_end_date === '') {
                $update_data['recurring_end_date'] = null;
                $update_format[] = 'NULL';
            } else {
                $update_data['recurring_end_date'] = $recurring_end_date;
                $update_format[] = '%s';
            }
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
     * Delete staff availability slot
     */
    private function delete_staff_availability_slot($staff_id, $slot_id) {
        global $wpdb;
        
        // Validate required fields
        if (!$staff_id || !$slot_id) {
            return new WP_Error('missing_data', __('Missing required availability data', 'spark-of-divine-scheduler'));
        }
        
        // Verify this slot belongs to this staff member
        $slot_staff_id = $wpdb->get_var($wpdb->prepare(
            "SELECT staff_id FROM {$wpdb->prefix}sod_staff_availability WHERE id = %d",
            $slot_id
        ));
        
        if ($slot_staff_id != $staff_id) {
            return new WP_Error('invalid_slot', __('This availability slot does not belong to the specified staff member', 'spark-of-divine-scheduler'));
        }
        
        // Delete slot
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
     * Update customer
     */
    private function update_customer($customer_id, $customer_data) {
        global $wpdb;
        
        // Validate required fields
        if (!$customer_id || empty($customer_data['name']) || empty($customer_data['email'])) {
            return new WP_Error('missing_data', __('Missing required customer data', 'spark-of-divine-scheduler'));
        }
        
        // Check if customer exists
        $customer = $this->get_customer($customer_id);
        if (!$customer) {
            return new WP_Error('invalid_customer', __('Customer not found', 'spark-of-divine-scheduler'));
        }
        
        // Update customer in custom table
        $result = $wpdb->update(
            "{$wpdb->prefix}sod_customers",
            array(
                'name' => $customer_data['name'],
                'email' => $customer_data['email'],
                'phone' => $customer_data['phone'],
                'emergency_contact_name' => $customer_data['emergency_contact_name'],
                'emergency_contact_phone' => $customer_data['emergency_contact_phone'],
                'notes' => $customer_data['notes'],
            ),
            array('customer_id' => $customer_id),
            array('%s', '%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to update customer in database', 'spark-of-divine-scheduler'));
        }
        
        // Update customer post if it exists
        $customer_post = get_post($customer_id);
        if ($customer_post && $customer_post->post_type === 'sod_customer') {
            wp_update_post(array(
                'ID' => $customer_id,
                'post_title' => $customer_data['name'],
            ));
            
            update_post_meta($customer_id, 'sod_customer_email', $customer_data['email']);
            update_post_meta($customer_id, 'sod_customer_phone', $customer_data['phone']);
            update_post_meta($customer_id, 'sod_customer_emergency_contact_name', $customer_data['emergency_contact_name']);
            update_post_meta($customer_id, 'sod_customer_emergency_contact_phone', $customer_data['emergency_contact_phone']);
            update_post_meta($customer_id, 'sod_customer_notes', $customer_data['notes']);
        }
        
        // Update user if linked to this customer
        if ($customer->user_id) {
            $name_parts = explode(' ', $customer_data['name'], 2);
            $first_name = $name_parts[0];
            $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
            
            wp_update_user(array(
                'ID' => $customer->user_id,
                'user_email' => $customer_data['email'],
                'first_name' => $first_name,
                'last_name' => $last_name,
                'display_name' => $customer_data['name'],
            ));
            
            update_user_meta($customer->user_id, 'billing_phone', $customer_data['phone']);
            update_user_meta($customer->user_id, 'emergency_contact_name', $customer_data['emergency_contact_name']);
            update_user_meta($customer->user_id, 'emergency_contact_phone', $customer_data['emergency_contact_phone']);
        }
        
        return true;
    }
    
    /**
     * Confirm booking
     */
    private function confirm_booking($booking_id) {
        global $wpdb;
        
        // Get booking
        $booking = $this->get_booking($booking_id);
        if (!$booking) {
            return new WP_Error('invalid_booking', __('Booking not found', 'spark-of-divine-scheduler'));
        }
        
        // Update booking status
        $updated = $wpdb->update(
            "{$wpdb->prefix}sod_bookings",
            array('status' => 'confirmed'),
            array('booking_id' => $booking_id),
            array('%s'),
            array('%d')
        );
        
        if ($updated === false) {
            return new WP_Error('db_error', __('Failed to update booking status', 'spark-of-divine-scheduler'));
        }
        
        // Update booking post meta
        $booking_post = get_post($booking_id);
        if ($booking_post && $booking_post->post_type === 'sod_booking') {
            update_post_meta($booking_id, 'sod_booking_status', 'confirmed');
        }
        
        // Update WooCommerce order if linked
        $order_id = $this->get_order_id_from_booking($booking_id);
        if ($order_id) {
            $order = wc_get_order($order_id);
            if ($order && !$order->has_status('completed')) {
                // Add note to order
                $order->add_order_note(__('Booking confirmed by team member', 'spark-of-divine-scheduler'));
                
                // Update order status if not already completed or processing
                if (!$order->has_status(array('completed', 'processing'))) {
                    $order->update_status('processing');
                }
            }
        }
        
        // Send confirmation emails
        $this->send_booking_confirmation_emails($booking_id);
        
        return true;
    }
    
    /**
     * Reschedule booking
     */
    private function reschedule_booking($booking_id, $new_date, $new_time, $message = '') {
        global $wpdb;
        
        // Get booking
        $booking = $this->get_booking($booking_id);
        if (!$booking) {
            return new WP_Error('invalid_booking', __('Booking not found', 'spark-of-divine-scheduler'));
        }
        
        // Calculate duration from original booking
        $original_duration = strtotime($booking->end_time) - strtotime($booking->start_time);
        
        // Create new start/end times
        $new_start_datetime = $new_date . ' ' . $new_time . ':00';
        $new_end_datetime = date('Y-m-d H:i:s', strtotime($new_start_datetime) + $original_duration);
        
        // Update booking
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
        
        if ($updated === false) {
            return new WP_Error('db_error', __('Failed to update booking', 'spark-of-divine-scheduler'));
        }
        
        // Update booking post meta
        $booking_post = get_post($booking_id);
        if ($booking_post && $booking_post->post_type === 'sod_booking') {
            update_post_meta($booking_id, 'sod_booking_date', $new_date);
            update_post_meta($booking_id, 'sod_booking_time', $new_time);
            update_post_meta($booking_id, 'sod_booking_status', 'rescheduled');
            if ($message) {
                update_post_meta($booking_id, 'sod_reschedule_message', $message);
            }
        }
        
        // Update WooCommerce order if linked
        $order_id = $this->get_order_id_from_booking($booking_id);
        if ($order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                // Add note to order
                $note = sprintf(
                    __('Booking rescheduled to %s at %s by team member.', 'spark-of-divine-scheduler'),
                    date_i18n(get_option('date_format'), strtotime($new_date)),
                    date_i18n(get_option('time_format'), strtotime($new_time))
                );
                
                if ($message) {
                    $note .= ' ' . sprintf(__('Note: %s', 'spark-of-divine-scheduler'), $message);
                }
                
                $order->add_order_note($note);
            }
        }
        
        // Send reschedule emails
        $this->send_booking_reschedule_emails($booking_id, $new_start_datetime, $message);
        
        return true;
    }
    
    /**
     * Cancel booking
     */
    private function cancel_booking($booking_id, $reason = '') {
        global $wpdb;
        
        // Get booking
        $booking = $this->get_booking($booking_id);
        if (!$booking) {
            return new WP_Error('invalid_booking', __('Booking not found', 'spark-of-divine-scheduler'));
        }
        
        // Update booking status
        $updated = $wpdb->update(
            "{$wpdb->prefix}sod_bookings",
            array('status' => 'cancelled'),
            array('booking_id' => $booking_id),
            array('%s'),
            array('%d')
        );
        
        if ($updated === false) {
            return new WP_Error('db_error', __('Failed to update booking status', 'spark-of-divine-scheduler'));
        }
        
        // Update booking post meta
        $booking_post = get_post($booking_id);
        if ($booking_post && $booking_post->post_type === 'sod_booking') {
            update_post_meta($booking_id, 'sod_booking_status', 'cancelled');
            if ($reason) {
                update_post_meta($booking_id, 'sod_cancellation_reason', $reason);
            }
        }
        
        // Update WooCommerce order if linked
        $order_id = $this->get_order_id_from_booking($booking_id);
        if ($order_id) {
            $order = wc_get_order($order_id);
            if ($order && !$order->has_status('cancelled')) {
                // Add note to order
                $note = __('Booking cancelled by team member.', 'spark-of-divine-scheduler');
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
        
        return true;
    }
    
    /**
     * Complete booking
     */
    private function complete_booking($booking_id) {
        global $wpdb;
        
        // Get booking
        $booking = $this->get_booking($booking_id);
        if (!$booking) {
            return new WP_Error('invalid_booking', __('Booking not found', 'spark-of-divine-scheduler'));
        }
        
        // Update booking status
        $updated = $wpdb->update(
            "{$wpdb->prefix}sod_bookings",
            array('status' => 'completed'),
            array('booking_id' => $booking_id),
            array('%s'),
            array('%d')
        );
        
        if ($updated === false) {
            return new WP_Error('db_error', __('Failed to update booking status', 'spark-of-divine-scheduler'));
        }
        
        // Update booking post meta
        $booking_post = get_post($booking_id);
        if ($booking_post && $booking_post->post_type === 'sod_booking') {
            update_post_meta($booking_id, 'sod_booking_status', 'completed');
        }
        
        // Update WooCommerce order if linked
        $order_id = $this->get_order_id_from_booking($booking_id);
        if ($order_id) {
            $order = wc_get_order($order_id);
            if ($order && !$order->has_status('completed')) {
                // Add note to order
                $order->add_order_note(__('Booking marked as completed by team member', 'spark-of-divine-scheduler'));
                
                // Update order status
                $order->update_status('completed');
            }
        }
        
        return true;
    }
    
    /**
     * Send booking confirmation emails
     */
    private function send_booking_confirmation_emails($booking_id) {
        global $wpdb;
        
        // Get booking details
        $booking = $this->get_booking($booking_id);
        if (!$booking) {
            return false;
        }
        
        // Get customer
        $customer = $this->get_customer($booking->customer_id);
        if (!$customer) {
            return false;
        }
        
        // Get staff
        $staff = $this->get_staff_member($booking->staff_id);
        $staff_name = $staff ? $staff->display_name : __('Unknown', 'spark-of-divine-scheduler');
        
        // Get product
        $product = wc_get_product($booking->product_id);
        $product_name = $product ? $product->get_name() : __('Unknown Service', 'spark-of-divine-scheduler');
        
        // Format dates for display
        $date_formatted = date_i18n(get_option('date_format'), strtotime($booking->start_time));
        $time_formatted = date_i18n(get_option('time_format'), strtotime($booking->start_time));
        
        // First check if we can use WooCommerce email templates
        if (class_exists('WC_Email') && function_exists('sod_trigger_booking_email')) {
            sod_trigger_booking_email('customer_booking_confirmed', $booking_id);
            return true;
        }
        
        // Fallback to manual email sending
        $customer_email = $customer->email;
        if ($customer_email) {
            $subject = sprintf(__('Your booking with %s has been confirmed', 'spark-of-divine-scheduler'), get_bloginfo('name'));
            
            $message = sprintf(
                __("Hello %s,\n\nYour booking has been confirmed:\n\nService: %s\nStaff: %s\nDate: %s\nTime: %s\n\nThank you for booking with us!\n\n%s", 'spark-of-divine-scheduler'),
                $customer->name,
                $product_name,
                $staff_name,
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
            $customer->name,
            $customer->phone,
            $customer->email,
            $product_name,
            $staff_name,
            $date_formatted,
            $time_formatted
        );
        
        // Send email to admin
        wp_mail($admin_email, $subject, $message, 'Content-Type: text/plain; charset=UTF-8');
        
        return true;
    }
    
    /**
     * Send booking reschedule emails
     */
    private function send_booking_reschedule_emails($booking_id, $new_start_time, $message = '') {
        // Implementation similar to send_booking_confirmation_emails
        // but with rescheduling information
        // Check if sod_trigger_booking_email function exists first
        if (function_exists('sod_trigger_booking_email')) {
            sod_trigger_booking_email('customer_booking_updated', $booking_id);
        }
        
        return true;
    }
    
    /**
     * Send booking cancellation emails
     */
    private function send_booking_cancellation_emails($booking_id, $reason = '') {
        // Implementation similar to send_booking_confirmation_emails
        // but with cancellation information
        // Check if sod_trigger_booking_email function exists first
        if (function_exists('sod_trigger_booking_email')) {
            sod_trigger_booking_email('customer_booking_canceled', $booking_id);
        }
        
        return true;
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
     * Get upcoming bookings
     */
    private function get_upcoming_bookings($limit = 5) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, 
                c.name as customer_name,
                c.email as customer_email,
                c.phone as customer_phone,
                u.display_name as staff_name
             FROM {$wpdb->prefix}sod_bookings b
             LEFT JOIN {$wpdb->prefix}sod_customers c ON b.customer_id = c.customer_id
             LEFT JOIN {$wpdb->prefix}sod_staff s ON b.staff_id = s.staff_id
             LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
             WHERE b.status IN ('pending', 'confirmed', 'deposit_paid')
             AND b.start_time >= %s
             ORDER BY b.start_time ASC
             LIMIT %d",
            current_time('mysql'),
            $limit
        ));
    }
    
    /**
     * Get pending bookings
     */
    private function get_pending_bookings() {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, 
                c.name as customer_name,
                c.email as customer_email,
                c.phone as customer_phone,
                u.display_name as staff_name
             FROM {$wpdb->prefix}sod_bookings b
             LEFT JOIN {$wpdb->prefix}sod_customers c ON b.customer_id = c.customer_id
             LEFT JOIN {$wpdb->prefix}sod_staff s ON b.staff_id = s.staff_id
             LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
             WHERE b.status IN ('pending', 'deposit_paid')
             ORDER BY b.start_time ASC"
        ));
    }
    
    /**
     * Get recent customers
     */
    private function get_recent_customers($limit = 5) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.*
             FROM {$wpdb->prefix}sod_customers c
             ORDER BY c.created_at DESC
             LIMIT %d",
            $limit
        ));
    }
    
    /**
     * Get filtered bookings - continued from previous code
     */
    private function get_filtered_bookings($status_filter = 'upcoming', $date_filter = '30days', $staff_filter = 0, $service_filter = 0) {
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
        
        // Staff filter
        $staff_conditions = '';
        if ($staff_filter > 0) {
            $staff_conditions = "AND b.staff_id = " . $staff_filter;
        }
        
        // Service filter
        $service_conditions = '';
        if ($service_filter > 0) {
            $service_conditions = "AND (b.product_id = " . $service_filter . " OR b.service_id = " . $service_filter . ")";
        }
        
        // Build and execute query
        $query = $wpdb->prepare(
            "SELECT b.*, 
                c.name as customer_name,
                c.email as customer_email,
                c.phone as customer_phone,
                u.display_name as staff_name,
                COALESCE(p1.post_title, p2.post_title, 'Unknown') as service_name,
                o.ID as order_id
             FROM {$wpdb->prefix}sod_bookings b
             LEFT JOIN {$wpdb->prefix}sod_customers c ON b.customer_id = c.customer_id
             LEFT JOIN {$wpdb->prefix}sod_staff s ON b.staff_id = s.staff_id
             LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
             LEFT JOIN {$wpdb->posts} p1 ON b.product_id = p1.ID
             LEFT JOIN {$wpdb->posts} p2 ON b.service_id = p2.ID
             LEFT JOIN {$wpdb->postmeta} om ON b.booking_id = om.meta_value AND om.meta_key = '_sod_booking_id'
             LEFT JOIN {$wpdb->posts} o ON om.post_id = o.ID AND o.post_type = 'shop_order'
             WHERE 1=1
             $status_conditions
             $date_conditions
             $staff_conditions
             $service_conditions
             ORDER BY b.start_time DESC"
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
            case 'all':
                $result['start'] = date('Y-m-d 00:00:00', strtotime('-10 years'));
                $result['end'] = date('Y-m-d 23:59:59', strtotime('+10 years'));
                break;
            default:
                $result['start'] = date('Y-m-d 00:00:00', strtotime('-30 days'));
                $result['end'] = date('Y-m-d 23:59:59', strtotime('+180 days'));
                break;
        }
        
        return $result;
    }
    
    /**
     * Get all staff members
     */
    private function get_all_staff() {
        global $wpdb;
        
        // Try to get staff from custom table first
        $staff = $wpdb->get_results(
            "SELECT s.staff_id, s.user_id, u.display_name, u.user_email,
                GROUP_CONCAT(DISTINCT tm.meta_value SEPARATOR ', ') as specialties
             FROM {$wpdb->prefix}sod_staff s
             LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
             LEFT JOIN {$wpdb->posts} p ON p.ID = s.staff_id
             LEFT JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
             LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'staff_specialty'
             LEFT JOIN {$wpdb->termmeta} tm ON tt.term_id = tm.term_id AND tm.meta_key = 'name'
             GROUP BY s.staff_id, s.user_id, u.display_name, u.user_email
             ORDER BY u.display_name"
        );
        
        // If no results or table doesn't exist, try posts approach
        if (empty($staff) || $wpdb->last_error) {
            $args = array(
                'post_type' => 'sod_staff',
                'posts_per_page' => -1,
                'orderby' => 'title',
                'order' => 'ASC',
            );
            
            $staff_posts = get_posts($args);
            $staff = array();
            
            foreach ($staff_posts as $post) {
                $user_id = get_post_meta($post->ID, 'sod_staff_user_id', true);
                $user = get_userdata($user_id);
                
                if ($user) {
                    $specialties = wp_get_post_terms($post->ID, 'staff_specialty', array('fields' => 'names'));
                    $specialties_str = implode(', ', $specialties);
                    
                    $staff[] = (object) array(
                        'staff_id' => $post->ID,
                        'user_id' => $user_id,
                        'display_name' => $user->display_name,
                        'user_email' => $user->user_email,
                        'specialties' => $specialties_str
                    );
                }
            }
        }
        
        return $staff;
    }
    
    /**
     * Get staff member by ID
     */
    private function get_staff_member($staff_id) {
        global $wpdb;
        
        // Try to get from custom table first
        $staff = $wpdb->get_row($wpdb->prepare(
            "SELECT s.*, u.display_name, u.user_email 
             FROM {$wpdb->prefix}sod_staff s
             LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
             WHERE s.staff_id = %d",
            $staff_id
        ));
        
        // If no result or table doesn't exist, try post approach
        if (!$staff || $wpdb->last_error) {
            $post = get_post($staff_id);
            
            if ($post && $post->post_type === 'sod_staff') {
                $user_id = get_post_meta($post->ID, 'sod_staff_user_id', true);
                $user = get_userdata($user_id);
                
                if ($user) {
                    $staff = (object) array(
                        'staff_id' => $post->ID,
                        'user_id' => $user_id,
                        'display_name' => $user->display_name,
                        'user_email' => $user->user_email
                    );
                    
                    // Add additional meta fields if available
                    $meta_fields = array(
                        'bio' => 'sod_staff_bio',
                        'phone' => 'sod_staff_phone',
                        'specialty' => 'sod_staff_specialty'
                    );
                    
                    foreach ($meta_fields as $property => $meta_key) {
                        $staff->$property = get_post_meta($post->ID, $meta_key, true);
                    }
                }
            }
        }
        
        return $staff;
    }
    
    /**
     * Get staff schedule
     */
    private function get_staff_schedule($staff_id, $date) {
        global $wpdb;
        
        // Get bookings for this date
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, 
                c.name as customer_name,
                c.email as customer_email,
                c.phone as customer_phone,
                COALESCE(p1.post_title, p2.post_title, 'Unknown') as service_name
             FROM {$wpdb->prefix}sod_bookings b
             LEFT JOIN {$wpdb->prefix}sod_customers c ON b.customer_id = c.customer_id
             LEFT JOIN {$wpdb->posts} p1 ON b.product_id = p1.ID
             LEFT JOIN {$wpdb->posts} p2 ON b.service_id = p2.ID
             WHERE b.staff_id = %d
             AND DATE(b.start_time) = %s
             ORDER BY b.start_time ASC",
            $staff_id, $date
        ));
        
        // Get availability slots for this date
        $day_of_week = date('l', strtotime($date));
        
        $availability = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, p.post_title as product_name
             FROM {$wpdb->prefix}sod_staff_availability a
             LEFT JOIN {$wpdb->posts} p ON a.product_id = p.ID
             WHERE a.staff_id = %d
             AND (
                 (a.day_of_week = %s AND (a.recurring_end_date IS NULL OR a.recurring_end_date >= %s))
                 OR a.date = %s
             )
             ORDER BY a.start_time ASC",
            $staff_id, $day_of_week, $date, $date
        ));
        
        return array(
            'bookings' => $bookings,
            'availability' => $availability
        );
    }
    
    /**
     * Get all products/services
     */
    private function get_all_products() {
        return wc_get_products(array(
            'status' => 'publish',
            'limit' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'category' => array('services', 'classes', 'events')
        ));
    }
    
    /**
     * Get filtered customers
     */
    private function get_filtered_customers($search = '', $page = 1, $per_page = 20) {
        global $wpdb;
        
        $search_condition = '';
        if (!empty($search)) {
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $search_condition = $wpdb->prepare(
                "WHERE (name LIKE %s OR email LIKE %s OR phone LIKE %s)",
                $search_term, $search_term, $search_term
            );
        }
        
        $limit = '';
        if ($per_page > 0) {
            $offset = ($page - 1) * $per_page;
            $limit = $wpdb->prepare("LIMIT %d, %d", $offset, $per_page);
        }
        
        $customers = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}sod_customers
             $search_condition
             ORDER BY name ASC
             $limit"
        );
        
        // If no results or table doesn't exist, try posts approach
        if ((empty($customers) || $wpdb->last_error) && post_type_exists('sod_customer')) {
            $args = array(
                'post_type' => 'sod_customer',
                'posts_per_page' => $per_page,
                'paged' => $page,
                'orderby' => 'title',
                'order' => 'ASC',
            );
            
            if (!empty($search)) {
                $args['s'] = $search;
            }
            
            $customer_posts = get_posts($args);
            $customers = array();
            
            foreach ($customer_posts as $post) {
                $customers[] = (object) array(
                    'customer_id' => $post->ID,
                    'name' => $post->post_title,
                    'email' => get_post_meta($post->ID, 'sod_customer_email', true),
                    'phone' => get_post_meta($post->ID, 'sod_customer_phone', true),
                    'emergency_contact_name' => get_post_meta($post->ID, 'sod_customer_emergency_contact_name', true),
                    'emergency_contact_phone' => get_post_meta($post->ID, 'sod_customer_emergency_contact_phone', true),
                    'notes' => get_post_meta($post->ID, 'sod_customer_notes', true),
                    'user_id' => get_post_meta($post->ID, 'sod_customer_user_id', true),
                    'created_at' => $post->post_date
                );
            }
        }
        
        return $customers;
    }
    
    /**
     * Get total customers count
     */
    private function get_total_customers($search = '') {
        global $wpdb;
        
        $search_condition = '';
        if (!empty($search)) {
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $search_condition = $wpdb->prepare(
                "WHERE (name LIKE %s OR email LIKE %s OR phone LIKE %s)",
                $search_term, $search_term, $search_term
            );
        }
        
        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sod_customers $search_condition"
        );
        
        // If error or table doesn't exist, try posts approach
        if ($wpdb->last_error && post_type_exists('sod_customer')) {
            $args = array(
                'post_type' => 'sod_customer',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'no_found_rows' => false,
            );
            
            if (!empty($search)) {
                $args['s'] = $search;
            }
            
            $customer_query = new WP_Query($args);
            $count = $customer_query->found_posts;
        }
        
        return intval($count);
    }
    
    /**
     * Get customer by ID
     */
    private function get_customer($customer_id) {
        global $wpdb;
        
        // Try to get from custom table first
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sod_customers WHERE customer_id = %d",
            $customer_id
        ));
        
        // If no result or table doesn't exist, try post approach
        if (!$customer || $wpdb->last_error) {
            $post = get_post($customer_id);
            
            if ($post && $post->post_type === 'sod_customer') {
                $customer = (object) array(
                    'customer_id' => $post->ID,
                    'name' => $post->post_title,
                    'email' => get_post_meta($post->ID, 'sod_customer_email', true),
                    'phone' => get_post_meta($post->ID, 'sod_customer_phone', true),
                    'emergency_contact_name' => get_post_meta($post->ID, 'sod_customer_emergency_contact_name', true),
                    'emergency_contact_phone' => get_post_meta($post->ID, 'sod_customer_emergency_contact_phone', true),
                    'notes' => get_post_meta($post->ID, 'sod_customer_notes', true),
                    'user_id' => get_post_meta($post->ID, 'sod_customer_user_id', true),
                    'created_at' => $post->post_date
                );
            }
        }
        
        return $customer;
    }
    
    /**
     * Get customer bookings
     */
    private function get_customer_bookings($customer_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, 
                u.display_name as staff_name,
                COALESCE(p1.post_title, p2.post_title, 'Unknown') as service_name
             FROM {$wpdb->prefix}sod_bookings b
             LEFT JOIN {$wpdb->prefix}sod_staff s ON b.staff_id = s.staff_id
             LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
             LEFT JOIN {$wpdb->posts} p1 ON b.product_id = p1.ID
             LEFT JOIN {$wpdb->posts} p2 ON b.service_id = p2.ID
             WHERE b.customer_id = %d
             ORDER BY b.start_time DESC",
            $customer_id
        ));
    }
    
    /**
     * Get booking by ID
     */
    private function get_booking($booking_id) {
        global $wpdb;
        
        // Get booking from custom table
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT b.*
             FROM {$wpdb->prefix}sod_bookings b
             WHERE b.booking_id = %d",
            $booking_id
        ));
        
        // If no result from custom table, try post meta as fallback
        if (!$booking || $wpdb->last_error) {
            $post = get_post($booking_id);
            
            if ($post && $post->post_type === 'sod_booking') {
                $booking = new stdClass();
                $booking->booking_id = $post->ID;
                $booking->customer_id = get_post_meta($post->ID, 'sod_booking_customer_id', true);
                $booking->staff_id = get_post_meta($post->ID, 'sod_booking_staff_id', true);
                $booking->service_id = get_post_meta($post->ID, 'sod_booking_service_id', true);
                $booking->product_id = get_post_meta($post->ID, 'sod_booking_product_id', true);
                
                $date = get_post_meta($post->ID, 'sod_booking_date', true);
                $time = get_post_meta($post->ID, 'sod_booking_time', true);
                $duration = get_post_meta($post->ID, 'sod_booking_duration', true);
                
                if ($date && $time) {
                    $booking->start_time = $date . ' ' . $time . ':00';
                    
                    if ($duration) {
                        $end_time = strtotime($booking->start_time) + ($duration * 60);
                        $booking->end_time = date('Y-m-d H:i:s', $end_time);
                    } else {
                        // Default to 1 hour if no duration specified
                        $booking->end_time = date('Y-m-d H:i:s', strtotime($booking->start_time) + 3600);
                    }
                }
                
                $booking->status = get_post_meta($post->ID, 'sod_booking_status', true);
                $booking->notes = get_post_meta($post->ID, 'sod_booking_notes', true);
            }
        }
        
        return $booking;
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        // Check if we're on a team dashboard page
        if (!is_page('team-dashboard') && !$this->is_team_dashboard_url() && !$this->has_team_dashboard_shortcode()) {
            return;
        }
        
        // Enqueue team dashboard CSS
        wp_enqueue_style(
            'sod-team-dashboard',
            SOD_PLUGIN_URL . 'assets/css/team-dashboard.css',
            [],
            SOD_VERSION
        );
        
        // Enqueue team dashboard JS
        wp_enqueue_script(
            'sod-team-dashboard',
            SOD_PLUGIN_URL . 'assets/js/team-dashboard.js',
            ['jquery'],
            SOD_VERSION,
            true
        );
        
        // Add jQuery UI datepicker if needed
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style(
            'jquery-ui-style',
            '//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css',
            [],
            '1.12.1'
        );
        
        // Localize the script with data
        wp_localize_script('sod-team-dashboard', 'sodTeamDashboard', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sod_team_action'),
            'confirmCancel' => __('Are you sure you want to cancel this booking?', 'spark-of-divine-scheduler'),
            'confirmComplete' => __('Are you sure you want to mark this booking as completed?', 'spark-of-divine-scheduler'),
            'confirmReschedule' => __('Are you sure you want to reschedule this booking?', 'spark-of-divine-scheduler'),
            'processingText' => __('Processing...', 'spark-of-divine-scheduler')
        ]);
    }
    
    /**
     * Check if current URL is a team dashboard page
     */
    private function is_team_dashboard_url() {
        $current_url = $_SERVER['REQUEST_URI'];
        return strpos($current_url, '/team-dashboard') !== false;
    }
    
    /**
     * Check if current page contains team dashboard shortcode
     */
    private function has_team_dashboard_shortcode() {
        global $post;
        
        if (!is_a($post, 'WP_Post')) {
            return false;
        }
        
        return has_shortcode($post->post_content, 'sod_team_dashboard');
    }
}