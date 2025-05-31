<?php
if (!defined('ABSPATH')) {
    exit;
}

class SOD_Scheduler {
    private $error_log_file;
    private $db_access;
    
    public function __construct($db_access) {
        $this->db_access = $db_access;
        $this->error_log_file = WP_CONTENT_DIR . '/sod-error.log';
        if (!$this->db_access) {
            $this->log_error('SOD_DB_Access instance is missing.');
            return;
        }
        $this->init();
    }
    
    private function init() {
        $this->setup_hooks();
        $this->setup_roles();
    }
   
   private function init_core_components() {
        try {
            // Remove all duplicate initializations since they're handled in the main plugin file
            $this->log_error('Core component initialization handled by main plugin file');
        } catch (Exception $e) {
            $this->log_error('Error in init_core_components: ' . $e->getMessage());
        }
    }

    private function setup_hooks() {
        // Remove the init_core_components call
        add_action('wp_enqueue_scripts', array($this, 'ensure_jquery_loaded'));

        // Register REST endpoint for booking slots
        add_action('rest_api_init', array($this, 'register_book_slot_endpoint'));

        // Initialize passes handler (still relevant)
        add_action('init', array($this, 'init_passes_handler'), 20);

        // Plugin activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'plugin_activation'));
        register_deactivation_hook(__FILE__, array($this, 'plugin_deactivation'));

        //Link practitioners to posts
        add_action('user_register', [$this, 'link_staff_to_post']);
        add_action('profile_update', [$this, 'link_staff_to_post']);
    }
    
    // Removed init_duration_handler since duration handling is gone
    // Duration functionality is now handled by SOD_Service_Product_Integration
    
    public function init_passes_handler() {
        try {
            SOD_Passes_Handler::getInstance();
        } catch (Exception $e) {
            $this->log_error('Failed to initialize Passes Handler: ' . $e->getMessage());
        }
    }
    
    private function setup_roles() {
        // Define sod_staff role (practitioners)
        if (!get_role('sod_staff')) {
            add_role('sod_staff', __('Practitioner', 'spark-of-divine-scheduler'), [
                'read' => true,
                'read_sod_booking' => true, 
            ]);
        }

        // Enhance shop_manager capabilities
        $shop_manager_role = get_role('shop_manager');
        if ($shop_manager_role) {
            $shop_manager_caps = [
                'edit_sod_booking' => true,
                'edit_sod_bookings' => true,
                'publish_sod_bookings' => true,
                'delete_sod_booking' => true,
                'read_sod_booking' => true,
                'edit_others_sod_bookings' => true,
                'delete_others_sod_bookings' => true,
                'read_private_sod_bookings' => true,
            ];
            foreach ($shop_manager_caps as $cap => $grant) {
                $shop_manager_role->add_cap($cap, $grant);
            }
        }

        // Admin capabilities (unchanged)
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_caps = [
                'edit_sod_booking', 'read_sod_booking', 'delete_sod_booking',
                'edit_sod_bookings', 'edit_others_sod_bookings', 'publish_sod_bookings',
                'read_private_sod_bookings', 'delete_sod_bookings',
                'delete_private_sod_bookings', 'delete_published_sod_bookings',
                'delete_others_sod_bookings', 'edit_private_sod_bookings',
                'edit_published_sod_bookings',
                'edit_sod_staff', 'read_sod_staff', 'delete_sod_staff',
                'edit_sod_staffs', 'edit_others_sod_staffs', 'publish_sod_staffs',
                'read_private_sod_staffs', 'delete_sod_staffs',
                'delete_private_sod_staffs', 'delete_published_sod_staffs',
                'delete_others_sod_staffs', 'edit_private_sod_staffs',
                'edit_published_sod_staffs',
            ];
            foreach ($admin_caps as $cap) {
                $admin_role->add_cap($cap);
            }
        }
}
    
    private function update_staff_capabilities() {
        $staff_role = get_role('staff');
        if ($staff_role) {
            $staff_caps = array(
                'edit_sod_staff', 'read_sod_staff', 'delete_sod_staff',
                'edit_sod_staffs', 'edit_others_sod_staffs', 'publish_sod_staffs',
                'read_private_sod_staffs'
            );
            foreach ($staff_caps as $cap) {
                $staff_role->add_cap($cap);
            }
        }
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_caps = array(
                'edit_sod_staff', 'read_sod_staff', 'delete_sod_staff',
                'edit_sod_staffs', 'edit_others_sod_staffs', 'publish_sod_staffs',
                'read_private_sod_staffs', 'delete_sod_staffs',
                'delete_private_sod_staffs', 'delete_published_sod_staffs',
                'delete_others_sod_staffs', 'edit_private_sod_staffs',
                'edit_published_sod_staffs',
            );
            foreach ($admin_caps as $cap) {
                $admin_role->add_cap($cap);
            }
        }
    }
    
    public function link_staff_to_post($user_id) {
    $user = get_userdata($user_id);
    if (in_array('sod_staff', (array)$user->roles)) {
        $staff_post = get_posts([
            'post_type' => 'sod_staff',
            'meta_query' => [['key' => 'sod_staff_user_id', 'value' => $user_id]],
            'posts_per_page' => 1
        ]);
        if (empty($staff_post)) {
            $staff_id = wp_insert_post([
                'post_type' => 'sod_staff',
                'post_title' => $user->display_name,
                'post_status' => 'publish'
            ]);
            update_post_meta($staff_id, 'sod_staff_user_id', $user_id);
            update_user_meta($user_id, 'sod_staff_id', $staff_id);
        } else {
            update_user_meta($user_id, 'sod_staff_id', $staff_post[0]->ID);
        }
    }
}
    
    public function ensure_jquery_loaded() {
        if (!wp_script_is('jquery', 'enqueued')) {
            wp_enqueue_script('jquery');
        }
    }
    
    public function get_booking_slots($request) {
    global $wpdb;
    $start_date = $request->get_param('start') ?: date('Y-m-d');
    $end_date = $request->get_param('end') ?: date('Y-m-d', strtotime('+7 days'));
    $service_filter = $request->get_param('service_id') ?: 0;
    $staff_filter = $request->get_param('staff_id') ?: 0;
    $category_filter = $request->get_param('category_id') ?: 0;
    
    error_log("Fetching booking slots from $start_date to $end_date");
    
    // Build the base query
    $query = "SELECT sa.*, 
              s.name AS service_name, 
              s.description AS service_description,
              u.display_name AS staff_name,
              s.price AS service_price,
              sa.appointment_only,
              st.user_id AS staff_user_id
            FROM {$wpdb->prefix}sod_staff_availability sa
            INNER JOIN {$wpdb->prefix}sod_services s ON sa.service_id = s.service_id
            LEFT JOIN {$wpdb->prefix}sod_staff st ON sa.staff_id = st.staff_id
            LEFT JOIN {$wpdb->prefix}users u ON st.user_id = u.ID";
    
    // Set up filters
    $where_conditions = [];
    $parameters = [];
    
    // Date filter for one-time slots
    $where_conditions[] = "(sa.date IS NOT NULL AND sa.date BETWEEN %s AND %s)";
    $parameters[] = $start_date;
    $parameters[] = $end_date;
    
    // Recurring slots filter
    $where_conditions[] = "(sa.recurring_type IS NOT NULL AND sa.day_of_week IS NOT NULL AND (sa.recurring_end_date IS NULL OR sa.recurring_end_date >= %s))";
    $parameters[] = $start_date;
    
    // Filter by service if specified
    if ($service_filter) {
        $where_conditions[] = "sa.service_id = %d";
        $parameters[] = $service_filter;
    }
    
    // Filter by staff if specified
    if ($staff_filter) {
        $where_conditions[] = "sa.staff_id = %d";
        $parameters[] = $staff_filter;
    }
    
    // Apply the WHERE clause
    $query .= " WHERE " . implode(" OR ", $where_conditions);
    
    // Execute the query with all parameters
    $slots = $wpdb->get_results($wpdb->prepare($query, $parameters));
    
    error_log("Found " . count($slots) . " slots (both one-time and recurring)");
    
    return rest_ensure_response($slots);
}
    
    private function format_slot_as_event($slot, $date) {
        try {
            $timezone = new DateTimeZone('America/New_York');
            $start_dt = new DateTime($date . ' ' . $slot->start_time, $timezone);
            $end_dt = new DateTime($date . ' ' . $slot->end_time, $timezone);
            $title = sprintf('%s with %s', $slot->service_name ?: 'Service', $slot->staff_name ?: 'Staff');
            $event_id = $slot->availability_id . '-' . $start_dt->format('YmdHi');
            
            // Get product price via SOD_Service_Product_Integration
            $product_integration = $GLOBALS['sod_service_product_integration'];
            $product_id = $product_integration->get_product_id_by_service($slot->service_id);
            $price = $product_id ? wc_get_product($product_id)->get_price() : $slot->service_price;
            
            return array(
                'id' => $event_id,
                'title' => $title,
                'start' => $start_dt->format(DateTime::ATOM),
                'end' => $end_dt->format(DateTime::ATOM),
                'isBooked' => false,
                'raw' => array(
                    'staff_id' => $slot->staff_id,
                    'service_id' => $slot->service_id,
                    'price' => number_format((float)$price, 2),
                    'appointment_only' => (bool)$slot->appointment_only
                )
            );
        } catch (Exception $e) {
            $this->log_error("Error formatting slot ID {$slot->availability_id}: " . $e->getMessage());
            return null;
        }
    }
    
    // Removed sod_ensure_service_durations and related methods
    // Duration management is now handled by SOD_Service_Product_Integration
    
    public function register_book_slot_endpoint() {
        register_rest_route('sod/v1', '/book-slot', array(
            'methods' => 'POST',
            'callback' => array($this, 'book_slot'),
            'permission_callback' => function() {
                return true; // Adjust permissions as needed
            }
        ));
    }
    
    public function book_slot($request) {
        $params = $request->get_json_params();
        $slot_id = isset($params['slot_id']) ? intval($params['slot_id']) : 0;
        if (!$slot_id) {
            return new WP_Error('no_slot', 'No slot provided', array('status' => 400));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'sod_staff_availability';
        $slot = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE availability_id = %d", $slot_id));
        if (!$slot) {
            return new WP_Error('invalid_slot', 'Slot not found', array('status' => 404));
        }
        
        if (!function_exists('WC')) {
            return new WP_Error('wc_inactive', 'WooCommerce is not active', array('status' => 500));
        }
        
        $product_integration = $GLOBALS['sod_service_product_integration'];
        $product_id = $product_integration->get_product_id_by_service($slot->service_id);
        if (!$product_id) {
            return new WP_Error('no_product', 'No product found for this service', array('status' => 500));
        }
        
        WC()->cart->empty_cart();
        $added = WC()->cart->add_to_cart($product_id, 1);
        if (!$added) {
            return new WP_Error('add_failed', 'Failed to add product to cart', array('status' => 500));
        }
        
        $checkout_url = wc_get_checkout_url();
        return rest_ensure_response(['success' => true, 'checkout_url' => $checkout_url]);
    }
    
    private function log_error($message) {
        error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, $this->error_log_file);
    }
    
    public function display_admin_error() {
        $class = 'notice notice-error';
        $message = __('There was an error initializing the Spark of Divine Service Scheduler plugin. Please check the error log.', 'spark-of-divine-scheduler');
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
    }
    
    public function plugin_activation() {
        $this->setup_roles();
        flush_rewrite_rules();
    }
    
    public function plugin_deactivation() {
        flush_rewrite_rules();
    }
}