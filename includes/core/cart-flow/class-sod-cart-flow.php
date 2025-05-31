<?php
/**
 * Cart Flow Integration
 * @package Spark_Of_Divine_Scheduler
 */
if (!defined('ABSPATH')) {
    exit;
}

class SOD_Cart_Flow {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
            error_log('SOD_Cart_Flow: Singleton instance created');
        }
        return self::$instance;
    }

    private function __construct() {
        error_log('SOD: Initializing SOD_Cart_Flow');
        add_action('template_redirect', [$this, 'setup_cart_flow']);
        add_action('wp_ajax_sod_save_cart_contact_info', [$this, 'save_cart_contact_info']);
        add_action('wp_ajax_nopriv_sod_save_cart_contact_info', [$this, 'save_cart_contact_info']);
    }

    public function setup_cart_flow() {
        if (is_checkout() && !is_user_logged_in() && WC()->session) {
            $email = WC()->session->get('sod_cart_email');
            $phone = WC()->session->get('sod_cart_phone');
            if (empty($email) || empty($phone)) {
                wc_add_notice(__('Please provide your contact details before proceeding to checkout.', 'spark-of-divine-scheduler'), 'error');
                wp_redirect(wc_get_cart_url());
                exit;
            }
        }
    }

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

        if (empty($first_name) || empty($last_name) || !is_email($email) || empty($phone)) {
            wp_send_json_error(['message' => 'Invalid contact details']);
            return;
        }

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
            error_log("SOD_Cart_Flow: Updated existing sod_customer ID $customer_id");
        } else {
            $customer_id = wp_insert_post([
                'post_type' => 'sod_customer',
                'post_title' => "$first_name $last_name",
                'post_status' => 'publish',
            ]);
            if (is_wp_error($customer_id)) {
                wp_send_json_error(['message' => 'Failed to create customer']);
                return;
            }
            update_post_meta($customer_id, 'sod_customer_first_name', $first_name);
            update_post_meta($customer_id, 'sod_customer_last_name', $last_name);
            update_post_meta($customer_id, 'sod_customer_email', $email);
            update_post_meta($customer_id, 'sod_customer_phone', $phone);
            // No user ID for guests
            error_log("SOD_Cart_Flow: Created new sod_customer ID $customer_id");
        }
        wp_reset_postdata();

        // Store in session for checkout
        if (WC()->session) {
            WC()->session->set('sod_cart_first_name', $first_name);
            WC()->session->set('sod_cart_last_name', $last_name);
            WC()->session->set('sod_cart_email', $email);
            WC()->session->set('sod_cart_phone', $phone);
            wp_send_json_success([
                'message' => 'Contact info saved',
                'redirect' => wc_get_checkout_url()
            ]);
        } else {
            wp_send_json_error(['message' => 'WooCommerce session not available']);
        }
    }
}