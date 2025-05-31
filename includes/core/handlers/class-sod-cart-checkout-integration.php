<?php
/**
 * Cart and Checkout Integration
 *
 * @package Spark_Of_Divine_Scheduler
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Class SOD_Cart_Checkout_Integration
 *
 * Handles integration with WooCommerce cart and checkout, including custom fields,
 * form submissions, and account creation.
 */
class SOD_Cart_Checkout_Integration {
    /**
     * Singleton instance
     *
     * @var SOD_Cart_Checkout_Integration
     */
    private static $instance = null;
    
    /**
     * Database access object
     *
     * @var object
     */
    private $db_access;
    
    /**
     * Flag to prevent hook registration multiple times
     *
     * @var boolean
     */
    private static $hooks_registered = false;

    /**
     * Get singleton instance
     *
     * @param object $db_access Database access object
     * @return SOD_Cart_Checkout_Integration
     */
    public static function get_instance($db_access = null) {
        if (null === self::$instance) {
            self::$instance = new self($db_access);
            
            // Only log the first time the instance is created
            error_log('SOD_Cart_Checkout_Integration: Singleton instance created');
        }
        return self::$instance;
    }

    /**
     * Constructor
     *
     * @param object $db_access Database access object
     */
    protected function __construct($db_access) {
        $this->db_access = $db_access;

        // Only register hooks once
        if (!self::$hooks_registered) {
            // Enqueue assets
            add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
            
            // Cart integration
            add_action('woocommerce_before_cart', array($this, 'add_contact_info_to_cart'));
            add_action('wp_footer', array($this, 'add_return_to_shop_button'));
            add_action('wp_ajax_sod_save_cart_contact_info', array($this, 'save_cart_contact_info'));
            add_action('wp_ajax_nopriv_sod_save_cart_contact_info', array($this, 'save_cart_contact_info'));
            
            // Checkout integration
            add_action('wp_footer', array($this, 'add_checkout_fields'));
            add_action('woocommerce_before_checkout_form', array($this, 'add_session_data_field'));
            add_action('woocommerce_checkout_process', array($this, 'process_checkout_fields'));
            add_action('woocommerce_checkout_order_created', array($this, 'create_user_from_checkout'));
            add_action('wp_ajax_sod_save_checkout_data', array($this, 'save_checkout_data'));
            add_action('wp_ajax_nopriv_sod_save_checkout_data', array($this, 'save_checkout_data'));
            
            self::$hooks_registered = true;
            error_log('SOD_Cart_Checkout_Integration: Hooks registered');
        }
    }

    /**
     * Prevent cloning for singleton pattern
     */
    private function __clone() {}

    /**
     * Enqueue CSS and JavaScript assets
     */
    public function enqueue_assets() {
        // Register and enqueue styles
        wp_register_style(
            'sod-cart-checkout-styles',
            SOD_PLUGIN_URL . 'assets/css/sod-cart-checkout.css',
            array(),
            '1.0.0'
        );
        wp_enqueue_style('sod-cart-checkout-styles');
        
        // Register and enqueue scripts
        wp_register_script(
            'sod-cart-checkout-script',
            SOD_PLUGIN_URL . 'assets/js/sod-cart-checkout.js',
            array('jquery'),
            '1.0.0',
            true
        );
        
        // Localize script with data
        wp_localize_script('sod-cart-checkout-script', 'sodCartCheckout', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'cart_nonce' => wp_create_nonce('sod_cart_contact_nonce'),
            'checkout_nonce' => wp_create_nonce('sod_checkout_nonce'),
            'is_user_logged_in' => is_user_logged_in() ? '1' : '0',
            'debug_mode' => defined('WP_DEBUG') && WP_DEBUG ? '1' : '0',
            'shop_url' => get_permalink(wc_get_page_id('shop')) ? get_permalink(wc_get_page_id('shop')) : home_url('/schedule/'),
        ));
        
        wp_enqueue_script('sod-cart-checkout-script');
    }

    /**
     * Add contact information fields to the cart page for guest users
     */
    public function add_contact_info_to_cart() {
        // Skip if user is logged in
        if (is_user_logged_in()) {
            return;
        }
        
        // Get cart template
        include SOD_PLUGIN_PATH . 'templates/cart/cart-fields.php';
    }

    /**
     * Add "Return to Shop" button to the cart
     */
    public function add_return_to_shop_button() {
        // Only add on cart page
        if (!is_cart()) {
            return;
        }
        
        // Get shop page URL
        $shop_page_url = get_permalink(wc_get_page_id('shop'));
        if (!$shop_page_url) {
            $shop_page_url = home_url('/schedule/');
        }
        
        // Include template
        include SOD_PLUGIN_PATH . 'templates/cart/return-to-shop.php';
    }

    /**
     * Save cart contact information via AJAX
     */
    public function save_cart_contact_info() {
        check_ajax_referer('sod_cart_contact_nonce', 'nonce');

        if (isset($_POST['email']) && isset($_POST['phone'])) {
            $email = sanitize_email($_POST['email']);
            $phone = sanitize_text_field($_POST['phone']);
            WC()->session->set('sod_cart_email', $email);
            WC()->session->set('sod_cart_phone', $phone);
            wp_send_json_success(['message' => 'Contact info saved']);
        } else {
            wp_send_json_error(['message' => 'Missing contact information']);
        }
    }

    /**
     * Add custom checkout fields for guest users
     */
    public function add_checkout_fields() {
        // Only add on checkout page
        if (!is_checkout()) {
            return;
        }
        
        // Skip if user is logged in
        if (is_user_logged_in()) {
            return;
        }
        
        // Include template
        include SOD_PLUGIN_PATH . 'templates/checkout/checkout-fields.php';
    }

    /**
     * Add hidden field to checkout to capture session storage data
     */
    public function add_session_data_field() {
        // Skip if user is logged in
        if (is_user_logged_in()) {
            return;
        }
        
        echo '<input type="hidden" id="sod_session_data" name="sod_session_data" />';
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Get data from session storage if available
                const sessionData = sessionStorage.getItem('sod_checkout_data');
                if (sessionData) {
                    $('#sod_session_data').val(sessionData);
                }
            });
        </script>
        <?php
    }

    /**
     * Process checkout fields to save data
     */
    public function process_checkout_fields() {
        // Check if our fields were submitted
        if (isset($_POST['sod_email']) && isset($_POST['sod_phone'])) {
            $checkout_data = [
                'email' => sanitize_email($_POST['sod_email']),
                'phone' => sanitize_text_field($_POST['sod_phone']),
                'register' => isset($_POST['sod_register']) ? true : false,
            ];
            
            // Add registration data if needed
            if ($checkout_data['register']) {
                $checkout_data['username'] = isset($_POST['sod_username']) ? sanitize_user($_POST['sod_username']) : '';
                $checkout_data['first_name'] = isset($_POST['sod_first_name']) ? sanitize_text_field($_POST['sod_first_name']) : '';
                $checkout_data['last_name'] = isset($_POST['sod_last_name']) ? sanitize_text_field($_POST['sod_last_name']) : '';
                $checkout_data['emergency_contact_name'] = isset($_POST['sod_emergency_contact_name']) ? sanitize_text_field($_POST['sod_emergency_contact_name']) : '';
                $checkout_data['emergency_contact_phone'] = isset($_POST['sod_emergency_contact_phone']) ? sanitize_text_field($_POST['sod_emergency_contact_phone']) : '';
            }
            
            // Store in session
            WC()->session->set('sod_checkout_integration_data', $checkout_data);
            
            error_log('SOD Checkout: Field data stored from direct form submission: ' . print_r($checkout_data, true));
        } else {
            // Try to get data from session storage via a hidden field
            if (isset($_POST['sod_session_data'])) {
                $session_data = json_decode(stripslashes($_POST['sod_session_data']), true);
                if ($session_data && is_array($session_data)) {
                    WC()->session->set('sod_checkout_integration_data', $session_data);
                    error_log('SOD Checkout: Field data stored from session data: ' . print_r($session_data, true));
                }
            }
        }
    }

    /**
     * Create user account from checkout data
     *
     * @param WC_Order $order The order object
     */
    public function create_user_from_checkout($order) {
        // Skip if user is logged in
        if (is_user_logged_in() || !$order) {
            return;
        }
        
        // Get data from our session
        $data = WC()->session->get('sod_checkout_integration_data');
        if (!$data || !isset($data['register']) || !$data['register']) {
            return;
        }
        
        // Get email from the order if not in our data
        $email = isset($data['email']) ? $data['email'] : $order->get_billing_email();
        if (empty($email)) {
            error_log('SOD Checkout: Cannot create user - no email address found');
            return;
        }
        
        // If email already exists as a user, don't try to create a new account
        if (email_exists($email)) {
            error_log('SOD Checkout: User with email ' . $email . ' already exists');
            return;
        }
        
        // Use the provided username or create one from name/email if not provided
        $username = !empty($data['username']) ? $data['username'] : '';
        if (empty($username)) {
            $first_name = !empty($data['first_name']) ? $data['first_name'] : $order->get_billing_first_name();
            $last_name = !empty($data['last_name']) ? $data['last_name'] : $order->get_billing_last_name();
            
            if (!empty($first_name) && !empty($last_name)) {
                $username = sanitize_user($first_name . '.' . $last_name . '.' . time());
            } else {
                $username = sanitize_user('customer.' . time());
            }
        }
        
        // Check if username exists and append timestamp if needed
        if (username_exists($username)) {
            $username = $username . '.' . time();
        }
        
        // Generate a random password
        $password = wp_generate_password();
        
        // Create the user
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            error_log('SOD Checkout: Failed to create user: ' . $user_id->get_error_message());
            return;
        }
        
        // Update user data
        wp_update_user([
            'ID' => $user_id,
            'first_name' => !empty($data['first_name']) ? $data['first_name'] : $order->get_billing_first_name(),
            'last_name' => !empty($data['last_name']) ? $data['last_name'] : $order->get_billing_last_name(),
            'display_name' => !empty($data['first_name']) ? $data['first_name'] : $username,
        ]);
        
        // Add custom user meta
        update_user_meta($user_id, 'billing_phone', !empty($data['phone']) ? $data['phone'] : $order->get_billing_phone());
        update_user_meta($user_id, 'emergency_contact_name', !empty($data['emergency_contact_name']) ? $data['emergency_contact_name'] : '');
        update_user_meta($user_id, 'emergency_contact_phone', !empty($data['emergency_contact_phone']) ? $data['emergency_contact_phone'] : '');
        
        // Connect user to order
        $order->set_customer_id($user_id);
        $order->save();
        
        // Create customer in custom table
        $this->create_or_update_customer($user_id, $email, $data['phone'], $data);
        
        // Send welcome email
        wp_send_new_user_notifications($user_id, 'user');
        
        error_log('SOD Checkout: Created user with ID ' . $user_id . ' and connected to order ' . $order->get_id());
    }

    /**
     * Save checkout data from AJAX request
     */
    public function save_checkout_data() {
        check_ajax_referer('sod_checkout_nonce', 'nonce');
        
        if (isset($_POST['data'])) {
            $data = $_POST['data'];
            WC()->session->set('sod_checkout_integration_data', $data);
            wp_send_json_success(['message' => 'Data saved successfully']);
        } else {
            wp_send_json_error(['message' => 'No data provided']);
        }
    }

    /**
     * Create or update a customer record
     *
     * @param int $user_id WordPress user ID
     * @param string $email Customer email
     * @param string $phone Customer phone
     * @param array $extension_data Additional customer data
     * @return int Customer ID or 0 on failure
     */
    private function create_or_update_customer($user_id, $email, $phone, $extension_data) {
        global $wpdb;

        // If no database access object is available, return early
        if (!$this->db_access || !method_exists($this->db_access, 'update_custom_booking_table')) {
            error_log('SOD_Cart_Checkout_Integration: DB access object not available or missing required method');
            return 0;
        }

        $table_name = $wpdb->prefix . 'sod_customers';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
            $table_name = 'wp_3be9vb_sod_customers';
        }

        $existing_customer_id = $wpdb->get_var($wpdb->prepare(
            "SELECT customer_id FROM $table_name WHERE email = %s",
            $email
        ));

        $customer_data = [
            'post_type' => 'sod_customer',
            'post_title' => isset($extension_data['first_name']) && isset($extension_data['last_name']) 
                ? sanitize_text_field($extension_data['first_name'] . ' ' . $extension_data['last_name']) 
                : sanitize_email($email),
            'post_status' => 'publish',
            'meta_input' => [
                'sod_customer_email' => $email,
                'sod_customer_phone' => $phone,
            ]
        ];

        if ($user_id) {
            $customer_data['post_author'] = $user_id;
            $customer_data['meta_input']['sod_customer_user_id'] = $user_id;
        }

        if (isset($extension_data['emergency_contact_name'])) {
            $customer_data['meta_input']['sod_customer_emergency_contact_name'] = sanitize_text_field($extension_data['emergency_contact_name']);
        }
        if (isset($extension_data['emergency_contact_phone'])) {
            $customer_data['meta_input']['sod_customer_emergency_contact_phone'] = sanitize_text_field($extension_data['emergency_contact_phone']);
        }
        if (isset($extension_data['signing_dependent'])) {
            $customer_data['meta_input']['sod_customer_signing_dependent'] = (int)$extension_data['signing_dependent'];
        }
        if (isset($extension_data['dependent_name'])) {
            $customer_data['meta_input']['sod_customer_dependent_name'] = sanitize_text_field($extension_data['dependent_name']);
        }
        if (isset($extension_data['dependent_dob'])) {
            $customer_data['meta_input']['sod_customer_dependent_dob'] = sanitize_text_field($extension_data['dependent_dob']);
        }

        if ($existing_customer_id) {
            $customer_data['ID'] = $existing_customer_id;
            $customer_id = wp_update_post($customer_data);
        } else {
            $customer_id = wp_insert_post($customer_data);
        }

        if (is_wp_error($customer_id)) {
            error_log('Failed to create/update sod_customer: ' . $customer_id->get_error_message());
            return 0;
        }

        $table_data = [
            'user_id' => $user_id ? $user_id : null,
            'name' => $customer_data['post_title'],
            'email' => $email,
            'phone' => $phone,
            'emergency_contact_name' => isset($extension_data['emergency_contact_name']) ? sanitize_text_field($extension_data['emergency_contact_name']) : null,
            'emergency_contact_phone' => isset($extension_data['emergency_contact_phone']) ? sanitize_text_field($extension_data['emergency_contact_phone']) : null,
            'signing_dependent' => isset($extension_data['signing_dependent']) ? (int)$extension_data['signing_dependent'] : 0,
            'dependent_name' => isset($extension_data['dependent_name']) ? sanitize_text_field($extension_data['dependent_name']) : null,
            'dependent_dob' => isset($extension_data['dependent_dob']) ? sanitize_text_field($extension_data['dependent_dob']) : null,
        ];

        if ($existing_customer_id) {
            $wpdb->update(
                $table_name,
                $table_data,
                ['customer_id' => $existing_customer_id],
                ['%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s'],
                ['%d']
            );
        } else {
            $table_data['customer_id'] = $customer_id;
            $wpdb->insert(
                $table_name,
                $table_data,
                ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
            );
        }

        if ($user_id) {
            update_user_meta($user_id, 'sod_customer_id', $customer_id);
        }

        return $customer_id;
    }
}