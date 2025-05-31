<?php
/**
 * SOD Cart Handler Class
 *
 * Manages the cart contact form functionality and integration.
 *
 * @package Spark_Of_Divine_Scheduler
 * @subpackage Handlers
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * SOD_Cart_Handler class
 */
class SOD_Cart_Handler {

    /**
     * Initialize the class
     */
    public function __construct() {
        // Register AJAX handlers
        add_action( 'wp_ajax_sod_save_cart_contact_info', array( $this, 'save_cart_contact_info' ) );
        add_action( 'wp_ajax_nopriv_sod_save_cart_contact_info', array( $this, 'save_cart_contact_info' ) );
        
        // Enqueue scripts and styles
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        
        // Pre-populate checkout fields
        add_filter( 'woocommerce_checkout_fields', array( $this, 'checkout_fields_from_session' ) );
        
        // Validate contact info before checkout
        add_action( 'woocommerce_before_checkout_form', array( $this, 'validate_checkout_contact_fields' ), 10 );
    }

    /**
     * Get the nonce key for AJAX requests
     *
     * @return string Nonce key
     */
    private function get_ajax_nonce() {
        return 'sod_contact_fields_nonce';
    }

    /**
     * AJAX handler for saving contact information
     */
    public function save_cart_contact_info() {
    check_ajax_referer('sod_cart_contact_nonce', 'nonce');

    if (!isset($_POST['first_name']) || !isset($_POST['last_name']) || !isset($_POST['email']) || !isset($_POST['phone'])) {
        wp_send_json_error(['message' => 'Missing contact information']);
        return;
    }

    $first_name = sanitize_text_field($_POST['first_name']);
    $last_name = sanitize_text_field($_POST['last_name']);
    $email = sanitize_email($_POST['email']);
    $phone = sanitize_text_field($_POST['phone']);
    
    // Convert opt_out checkbox to promotion_status value
    // If opt_out is checked, promotion_status = -1 (opted out)
    // If opt_out is not checked, promotion_status = 1 (opted in)
    // Default is 0 (neutral/not specified)
    $opt_out = isset($_POST['opt_out']) ? (bool)$_POST['opt_out'] : false;
    $promotion_status = $opt_out ? -1 : 1; // Convert to +1/-1

    if (empty($first_name) || empty($last_name) || !is_email($email) || empty($phone)) {
        wp_send_json_error(['message' => 'Invalid contact details']);
        return;
    }
    
    // Log the data being received
    error_log('SOD_Elementor_Integration: Processing contact info - ' . 
        "Name: $first_name $last_name, Email: $email, Phone: $phone, " . 
        "Promotion Status: " . $promotion_status);

    // Check for existing sod_customer by email
    $customer_query = new WP_Query([
        'post_type' => 'sod_customer',
        'meta_query' => [
            [
                'key' => 'sod_customer_email',
                'value' => $email,
                'compare' => '='
            ]
        ],
        'posts_per_page' => 1
    ]);

    if ($customer_query->have_posts()) {
        $customer_query->the_post();
        $customer_id = get_the_ID();
        wp_update_post([
            'ID' => $customer_id,
            'post_title' => "$first_name $last_name",
        ]);
        update_post_meta($customer_id, 'sod_customer_first_name', $first_name);
        update_post_meta($customer_id, 'sod_customer_last_name', $last_name);
        update_post_meta($customer_id, 'sod_customer_email', $email);
        update_post_meta($customer_id, 'sod_customer_phone', $phone);
        update_post_meta($customer_id, 'sod_customer_promotion_status', $promotion_status);
        
        error_log("SOD_Elementor_Integration: Updated existing sod_customer ID $customer_id");
    } else {
        // Create new sod_customer post
        $customer_id = wp_insert_post([
            'post_type' => 'sod_customer',
            'post_title' => "$first_name $last_name",
            'post_status' => 'publish',
        ]);
        
        if (is_wp_error($customer_id)) {
            wp_send_json_error(['message' => 'Failed to create customer record']);
            return;
        }
        
        update_post_meta($customer_id, 'sod_customer_first_name', $first_name);
        update_post_meta($customer_id, 'sod_customer_last_name', $last_name);
        update_post_meta($customer_id, 'sod_customer_email', $email);
        update_post_meta($customer_id, 'sod_customer_phone', $phone);
        update_post_meta($customer_id, 'sod_customer_promotion_status', $promotion_status);
        
        error_log("SOD_Elementor_Integration: Created new sod_customer ID $customer_id");
    }
    wp_reset_postdata();

    // Also save to wp_sod_customers table if the function exists
    if (function_exists('WC') && class_exists('SOD_DB_Access')) {
        global $sod_db_access;
        
        if ($sod_db_access) {
            $custom_customer_data = [
                'name' => "$first_name $last_name",
                'email' => $email,
                'phone' => $phone,
                'promotion_status' => $promotion_status, // Use the +1/-1/0 value
                'user_id' => null // This will be filled in if they register
            ];
            
            // Attempt to update or insert into the custom table
            try {
                $custom_customer_id = $sod_db_access->createOrUpdateCustomer($custom_customer_data);
                error_log("SOD_Elementor_Integration: Saved to custom table with ID $custom_customer_id");
            } catch (Exception $e) {
                error_log("SOD_Elementor_Integration: Error saving to custom table: " . $e->getMessage());
            }
        }
    }

    // Store in session for checkout
    if (function_exists('WC') && WC()->session) {
        WC()->session->set('sod_cart_first_name', $first_name);
        WC()->session->set('sod_cart_last_name', $last_name);
        WC()->session->set('sod_cart_email', $email);
        WC()->session->set('sod_cart_phone', $phone);
        WC()->session->set('sod_cart_promotion_status', $promotion_status);
        WC()->session->set('sod_cart_source_page', 'cart'); // Track that they came from cart
        
        // Set customer details in WooCommerce
        WC()->customer->set_billing_first_name($first_name);
        WC()->customer->set_billing_last_name($last_name);
        WC()->customer->set_billing_email($email);
        WC()->customer->set_billing_phone($phone);
        WC()->customer->save();
        
        wp_send_json_success([
            'message' => 'Contact info saved',
            'redirect' => wc_get_checkout_url()
        ]);
    } else {
        wp_send_json_error(['message' => 'WooCommerce session not available']);
    }
}

    /**
     * Enqueue scripts and styles for cart and checkout
     */
    public function enqueue_scripts() {
        // Only on cart and checkout pages
        if ( is_cart() || is_checkout() ) {
            // Get plugin/theme directory paths
            $theme_dir = get_stylesheet_directory();
            $plugin_dir = plugin_dir_path( dirname( dirname( __DIR__ ) ) );
            
            // Check if files exist in theme directory first (for theme overrides)
            $css_path = file_exists( $theme_dir . '/css/sod-cart-checkout.css' ) 
                ? get_stylesheet_directory_uri() . '/css/sod-cart-checkout.css'
                : plugin_dir_url( dirname( dirname( __DIR__ ) ) ) . 'assets/css/sod-cart-checkout.css';
            
            $js_path = file_exists( $theme_dir . '/js/sod-cart-checkout.js' )
                ? get_stylesheet_directory_uri() . '/js/sod-cart-checkout.js'
                : plugin_dir_url( dirname( dirname( __DIR__ ) ) ) . 'assets/js/sod-cart-checkout.js';
            
            // Determine file version for cache busting
            $css_version = file_exists( $theme_dir . '/css/sod-cart-checkout.css' )
                ? filemtime( $theme_dir . '/css/sod-cart-checkout.css' )
                : ( file_exists( $plugin_dir . 'assets/css/sod-cart-checkout.css' )
                    ? filemtime( $plugin_dir . 'assets/css/sod-cart-checkout.css' )
                    : '1.0.0' );
            
            $js_version = file_exists( $theme_dir . '/js/sod-cart-checkout.js' )
                ? filemtime( $theme_dir . '/js/sod-cart-checkout.js' )
                : ( file_exists( $plugin_dir . 'assets/js/sod-cart-checkout.js' )
                    ? filemtime( $plugin_dir . 'assets/js/sod-cart-checkout.js' )
                    : '1.0.0' );
            
            // Enqueue CSS
            wp_enqueue_style(
                'sod-cart-checkout-styles',
                $css_path,
                array(),
                $css_version
            );
            
            // Enqueue JS
            wp_enqueue_script(
                'sod-cart-checkout-script',
                $js_path,
                array( 'jquery' ),
                $js_version,
                true
            );
            
            // Pass data to script
            wp_localize_script(
                'sod-cart-checkout-script',
                'sod_contact_fields',
                array(
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'nonce' => wp_create_nonce( $this->get_ajax_nonce() ),
                    'is_user_logged_in' => is_user_logged_in(),
                    'checkout_url' => wc_get_checkout_url()
                )
            );
        }
    }

    /**
     * Validate contact fields before checkout
     */
    public function validate_checkout_contact_fields() {
        // Only for non-logged in users
        if ( is_user_logged_in() ) {
            return;
        }
        
        // Check if we have the contact information in the session
        if ( function_exists( 'WC' ) && WC()->session ) {
            $email = WC()->session->get( 'sod_cart_email', '' );
            
            if ( empty( $email ) ) {
                // Prevent checkout if no contact info
                wc_add_notice( 'Please provide your contact information before proceeding to checkout.', 'error' );
                
                // Redirect back to cart
                wp_safe_redirect( wc_get_cart_url() );
                exit;
            }
        }
    }

    /**
     * Pre-populate checkout fields from saved contact info
     *
     * @param array $fields WooCommerce checkout fields
     * @return array Modified checkout fields
     */
    public function checkout_fields_from_session( $fields ) {
        // Skip for logged in users
        if ( is_user_logged_in() ) {
            return $fields;
        }
        
        // Get data from session
        if ( function_exists( 'WC' ) && WC()->session ) {
            $first_name = WC()->session->get( 'sod_cart_first_name', '' );
            $last_name = WC()->session->get( 'sod_cart_last_name', '' );
            $email = WC()->session->get( 'sod_cart_email', '' );
            $phone = WC()->session->get( 'sod_cart_phone', '' );
            
            // Pre-populate checkout fields
            if ( ! empty( $email ) ) {
                // Billing fields
                if ( isset( $fields['billing'] ) ) {
                    if ( isset( $fields['billing']['billing_first_name'] ) ) {
                        $fields['billing']['billing_first_name']['default'] = $first_name;
                    }
                    
                    if ( isset( $fields['billing']['billing_last_name'] ) ) {
                        $fields['billing']['billing_last_name']['default'] = $last_name;
                    }
                    
                    if ( isset( $fields['billing']['billing_email'] ) ) {
                        $fields['billing']['billing_email']['default'] = $email;
                    }
                    
                    if ( isset( $fields['billing']['billing_phone'] ) ) {
                        $fields['billing']['billing_phone']['default'] = $phone;
                    }
                }
                
                // Shipping fields (use same name)
                if ( isset( $fields['shipping'] ) ) {
                    if ( isset( $fields['shipping']['shipping_first_name'] ) ) {
                        $fields['shipping']['shipping_first_name']['default'] = $first_name;
                    }
                    
                    if ( isset( $fields['shipping']['shipping_last_name'] ) ) {
                        $fields['shipping']['shipping_last_name']['default'] = $last_name;
                    }
                }
            }
        }
        
        return $fields;
    }

    /**
     * Get contact field values from session
     *
     * @return array Contact field values
     */
    public function get_contact_fields_from_session() {
        $data = array(
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'phone' => ''
        );
        
        if ( function_exists( 'WC' ) && WC()->session ) {
            $data['first_name'] = WC()->session->get( 'sod_cart_first_name', '' );
            $data['last_name'] = WC()->session->get( 'sod_cart_last_name', '' );
            $data['email'] = WC()->session->get( 'sod_cart_email', '' );
            $data['phone'] = WC()->session->get( 'sod_cart_phone', '' );
        }
        
        return $data;
    }
}

// Initialize the class
$sod_cart_handler = new SOD_Cart_Handler();