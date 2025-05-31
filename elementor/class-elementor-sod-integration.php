<?php
/**
 * Elementor Integration for SOD
 *
 * @package Spark_Of_Divine_Scheduler
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SOD_Elementor_Integration Class
 */
class SOD_Elementor_Integration {
    /**
     * Instance
     * @var SOD_Elementor_Integration
     */
    private static $instance = null;

    /**
     * Get instance
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
        // Register widgets
        add_action('elementor/widgets/register', [$this, 'register_widgets']);
        
        // Register widget category
        add_action('elementor/elements/categories_registered', [$this, 'register_widget_categories']);
        
        // Register AJAX handler
        add_action('wp_ajax_sod_save_cart_contact_info', [$this, 'save_cart_contact_info']);
        add_action('wp_ajax_nopriv_sod_save_cart_contact_info', [$this, 'save_cart_contact_info']);
        
        // Checkout validation
        add_action('woocommerce_before_checkout_process', [$this, 'validate_contact_info']);
        add_action('template_redirect', [$this, 'redirect_checkout_if_no_contact_info']);
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Register widget categories
     *
     * @param \Elementor\Elements_Manager $elements_manager
     */
    public function register_widget_categories($elements_manager) {
        $elements_manager->add_category(
            'spark-of-divine',
            [
                'title' => __('Spark of Divine', 'spark-of-divine-scheduler'),
                'icon' => 'fa fa-plug',
            ]
        );
    }

    /**
     * Register Elementor widgets
     *
     * @param \Elementor\Widgets_Manager $widgets_manager
     */
    public function register_widgets($widgets_manager) {
        // Include widget files - CORRECTED PATHS
        require_once SOD_PLUGIN_PATH . 'elementor/elementor-sod-contact-fields-widget.php';
        require_once SOD_PLUGIN_PATH . 'elementor/elementor-sod-custom-cart-widget.php';
        
        // Register the widgets
        $widgets_manager->register(new SOD_Contact_Fields_Widget());
        $widgets_manager->register(new SOD_Custom_Cart_Widget());
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets() {
        // Only on cart and checkout pages or Elementor pages
        if (is_cart() || is_checkout() || has_shortcode(get_post()->post_content, 'elementor-template')) {
            // Register and enqueue CSS
            wp_register_style(
                'sod-cart-checkout',
                SOD_PLUGIN_URL . 'assets/css/sod-cart-checkout.css',
                [],
                '2.1'
            );
            wp_enqueue_style('sod-cart-checkout');
            
            // Register and enqueue JS
            wp_register_script(
                'sod-cart-checkout',
                SOD_PLUGIN_URL . 'assets/js/sod-cart-checkout.js',
                ['jquery'],
                '2.1',
                true
            );
            
            // Localize script
            wp_localize_script('sod-cart-checkout', 'sod_contact_fields', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sod_cart_contact_nonce'),
                'is_user_logged_in' => is_user_logged_in()
            ]);
            
            wp_enqueue_script('sod-cart-checkout');
        }
    }

    /**
     * Save contact info via AJAX
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
            error_log("SOD_Elementor_Integration: Updated existing sod_customer ID $customer_id");
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
            error_log("SOD_Elementor_Integration: Created new sod_customer ID $customer_id");
        }
        wp_reset_postdata();

        // Store in session for checkout
        if (function_exists('WC') && WC()->session) {
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

    /**
     * Validate contact info before checkout
     */
    public function validate_contact_info() {
        // Skip for logged-in users
        if (is_user_logged_in()) {
            return;
        }
        
        // Check if contact info is stored in session
        if (function_exists('WC') && WC()->session) {
            $first_name = WC()->session->get('sod_cart_first_name');
            $last_name = WC()->session->get('sod_cart_last_name');
            $email = WC()->session->get('sod_cart_email');
            $phone = WC()->session->get('sod_cart_phone');
            
            if (empty($first_name) || empty($last_name) || empty($email) || empty($phone)) {
                wc_add_notice(__('Please provide your contact details before proceeding to checkout.', 'spark-of-divine-scheduler'), 'error');
                wp_redirect(wc_get_cart_url());
                exit;
            }
        }
    }

    /**
     * Redirect checkout if no contact info
     */
    public function redirect_checkout_if_no_contact_info() {
        // Only on checkout page for guest users
        if (!is_checkout() || is_user_logged_in()) {
            return;
        }
        
        // Check if contact info is stored in session
        if (function_exists('WC') && WC()->session) {
            $first_name = WC()->session->get('sod_cart_first_name');
            $last_name = WC()->session->get('sod_cart_last_name');
            $email = WC()->session->get('sod_cart_email');
            $phone = WC()->session->get('sod_cart_phone');
            
            if (empty($first_name) || empty($last_name) || empty($email) || empty($phone)) {
                wc_add_notice(__('Please provide your contact details before proceeding to checkout.', 'spark-of-divine-scheduler'), 'error');
                wp_safe_redirect(wc_get_cart_url());
                exit;
            }
        }
    }
    
    /**
     * Fix for render method in SOD_Schedule_Widget
     * Replace the render method with this one
     */
    protected function render() {
        // Don't render on shop-manager or staff-schedule pages
        global $post;

        // Debug log
        error_log('SOD_Schedule_Widget render called');

        // Prevent conflicts with other widgets by clearing any existing view globals
        unset($GLOBALS['sod_staff_view']);
        unset($GLOBALS['sod_shop_manager_view']);

        // Only set customer view for this widget
        $GLOBALS['sod_customer_view'] = true;
        $GLOBALS['sod_schedule_view'] = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'week';
        $GLOBALS['sod_schedule_date'] = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : date('Y-m-d');

        // Check if we're on a page where this widget shouldn't render
        if ($post && in_array($post->post_name, ['shop-manager', 'staff-schedule'])) {
            error_log('SOD_Schedule_Widget not rendering on page: ' . $post->post_name);
            return;
        }

        // Include template with error handling
        $template = get_stylesheet_directory() . '/schedule-template.php';

        if (file_exists($template)) {
            error_log('SOD_Schedule_Widget using theme template: ' . $template);
            include $template;
        } else {
            $plugin_template = SOD_PLUGIN_PATH . 'templates/schedule-template.php';

            if (file_exists($plugin_template)) {
                error_log('SOD_Schedule_Widget using plugin template: ' . $plugin_template);
                include $plugin_template;
            } else {
                error_log('SOD_Schedule_Widget - No template found');
                echo '<p>' . __('Schedule template not found. Please contact the administrator.', 'spark-of-divine-scheduler') . '</p>';
            }
        }
    }

}

// Initialize
SOD_Elementor_Integration::get_instance();