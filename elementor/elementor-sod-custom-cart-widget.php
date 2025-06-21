<?php
/**
 * SOD Custom Cart Elementor Widget (Refactored)
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class SOD_Custom_Cart_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'sod_custom_cart';
    }

    public function get_title() {
        return __('SOD Custom Cart', 'spark-of-divine-scheduler');
    }

    public function get_icon() {
        return 'eicon-cart';
    }

    public function get_categories() {
        return ['general', 'woocommerce-elements', 'spark-of-divine'];
    }

    protected function register_controls() {
        // This function defines the settings in the Elementor editor.
        // It can remain as it was in your original file.
    }

    /**
     * Render widget output on the frontend (COMPLETE REFACTORED LOGIC)
     */
    protected function render() {
        if (!class_exists('WooCommerce') || !WC()->cart) {
            echo '<div class="elementor-alert elementor-alert-warning">' . __('WooCommerce is not active.', 'spark-of-divine-scheduler') . '</div>';
            return;
        }

        if (WC()->cart->is_empty()) {
            wc_get_template('cart/cart-empty.php');
            return;
        }

        echo '<div class="sod-custom-cart-wrapper woocommerce">';

        // --- GUEST CONTACT INFORMATION FORM ---
        if (!is_user_logged_in()) {
            $session = WC()->session;
            $first_name = $session ? $session->get('sod_cart_first_name', '') : '';
            $last_name = $session ? $session->get('sod_cart_last_name', '') : '';
            $email = $session ? $session->get('sod_cart_email', '') : '';
            $phone = $session ? $session->get('sod_cart_phone', '') : '';
            $opt_out = $session ? $session->get('sod_cart_opt_out', false) : false;
            $block_id = 'sod-guest-info-' . uniqid();
            ?>
            <div class="sod-cart-contact-info" id="<?php echo esc_attr($block_id); ?>">
                <h3><?php _e('Guest Checkout', 'spark-of-divine-scheduler'); ?></h3>
                <p class="sod-guest-checkout-notice"><?php _e('In order to proceed, we require your <strong>full name, email, and phone number</strong> so that we can communicate with <strong>you and your provider</strong>.', 'spark-of-divine-scheduler'); ?></p>
                
                <div class="sod-cart-field">
                    <label for="<?php echo esc_attr($block_id); ?>-first-name"><?php _e('First Name', 'spark-of-divine-scheduler'); ?> <span class="required">*</span></label>
                    <input type="text" id="<?php echo esc_attr($block_id); ?>-first-name" name="sod_cart_first_name" value="<?php echo esc_attr($first_name); ?>" required />
                </div>
                <div class="sod-cart-field">
                    <label for="<?php echo esc_attr($block_id); ?>-last-name"><?php _e('Last Name', 'spark-of-divine-scheduler'); ?> <span class="required">*</span></label>
                    <input type="text" id="<?php echo esc_attr($block_id); ?>-last-name" name="sod_cart_last_name" value="<?php echo esc_attr($last_name); ?>" required />
                </div>
                <div class="sod-cart-field">
                    <label for="<?php echo esc_attr($block_id); ?>-email"><?php _e('Email Address', 'spark-of-divine-scheduler'); ?> <span class="required">*</span></label>
                    <input type="email" id="<?php echo esc_attr($block_id); ?>-email" name="sod_cart_email" value="<?php echo esc_attr($email); ?>" required />
                </div>
                <div class="sod-cart-field">
                    <label for="<?php echo esc_attr($block_id); ?>-phone"><?php _e('Phone Number', 'spark-of-divine-scheduler'); ?> <span class="required">*</span></label>
                    <input type="tel" id="<?php echo esc_attr($block_id); ?>-phone" name="sod_cart_phone" value="<?php echo esc_attr($phone); ?>" required />
                </div>
                <div class="sod-cart-field sod-checkbox-field">
                    <input type="checkbox" id="<?php echo esc_attr($block_id); ?>-opt-out" name="sod_cart_opt_out" <?php checked($opt_out); ?> />
                    <label for="<?php echo esc_attr($block_id); ?>-opt-out"><?php _e('Opt out of receiving promotions', 'spark-of-divine-scheduler'); ?></label>
                </div>
                
                <p class="sod-terms-notice"><?php _e('You are <strong>not registering for an account</strong>, but you <strong>authorize Spark of Divine to contact you via phone and email</strong>. You may opt out of receiving future promotions at any time.', 'spark-of-divine-scheduler'); ?></p>

                <div class="sod-contact-status"></div>
                <?php wp_nonce_field('sod_save_contact_nonce', 'sod_contact_nonce'); ?>
            </div>
            <?php
        }

        // --- WOOCOMMERCE CART FORM ---
        // This renders the standard WC cart table, totals, and checkout button
        echo '<div class="woocommerce-cart-form">';
        do_action('woocommerce_before_cart');
        wc_get_template('cart/cart.php');
        do_action('woocommerce_after_cart');
        echo '</div>';
        
        echo '</div>'; // Close .sod-custom-cart-wrapper
    } // End render() function
}
