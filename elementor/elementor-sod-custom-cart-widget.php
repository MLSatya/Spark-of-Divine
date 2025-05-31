<?php
/**
 * SOD Custom Cart Elementor Widget
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Register SOD Custom Cart Widget
 */
class SOD_Custom_Cart_Widget extends \Elementor\Widget_Base {

    /**
     * Get widget name
     */
    public function get_name() {
        return 'sod_custom_cart';
    }

    /**
     * Get widget title
     */
    public function get_title() {
        return __('SOD Custom Cart', 'spark-of-divine-scheduler');
    }

    /**
     * Get widget icon
     */
    public function get_icon() {
        return 'eicon-cart';
    }

    /**
     * Get widget categories
     */
    public function get_categories() {
        return ['general', 'woocommerce-elements', 'spark-of-divine'];
    }

    /**
     * Register widget controls
     */
    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Content', 'spark-of-divine-scheduler'),
            ]
        );

        $this->add_control(
            'show_cart_items',
            [
                'label' => __('Show Cart Items', 'spark-of-divine-scheduler'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'spark-of-divine-scheduler'),
                'label_off' => __('No', 'spark-of-divine-scheduler'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_totals',
            [
                'label' => __('Show Totals', 'spark-of-divine-scheduler'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'spark-of-divine-scheduler'),
                'label_off' => __('No', 'spark-of-divine-scheduler'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_coupon',
            [
                'label' => __('Show Coupon Form', 'spark-of-divine-scheduler'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'spark-of-divine-scheduler'),
                'label_off' => __('No', 'spark-of-divine-scheduler'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'step_title',
            [
                'label' => __('Step Title', 'spark-of-divine-scheduler'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Cart', 'spark-of-divine-scheduler'),
            ]
        );

        $this->add_control(
            'next_step_title',
            [
                'label' => __('Next Step Title', 'spark-of-divine-scheduler'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Checkout', 'spark-of-divine-scheduler'),
            ]
        );

        $this->add_control(
            'return_url',
            [
                'label' => __('Return URL', 'spark-of-divine-scheduler'),
                'type' => \Elementor\Controls_Manager::URL,
                'placeholder' => __('https://your-link.com', 'spark-of-divine-scheduler'),
                'default' => [
                    'url' => home_url('/services/'),
                    'is_external' => false,
                    'nofollow' => false,
                ],
            ]
        );

        $this->add_control(
            'return_text',
            [
                'label' => __('Return Text', 'spark-of-divine-scheduler'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Return to Spark Calendar', 'spark-of-divine-scheduler'),
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style',
            [
                'label' => __('Style', 'spark-of-divine-scheduler'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'container_background',
            [
                'label' => __('Background Color', 'spark-of-divine-scheduler'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .sod-custom-cart' => 'background-color: {{VALUE}};',
                ],
                'default' => '#ffffff',
            ]
        );

        $this->add_control(
            'container_padding',
            [
                'label' => __('Padding', 'spark-of-divine-scheduler'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .sod-custom-cart' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'default' => [
                    'top' => '20',
                    'right' => '20',
                    'bottom' => '20',
                    'left' => '20',
                    'unit' => 'px',
                    'isLinked' => true,
                ],
            ]
        );

        $this->add_control(
            'button_background',
            [
                'label' => __('Button Background', 'spark-of-divine-scheduler'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .checkout-button, {{WRAPPER}} .wc-proceed-to-checkout .button' => 'background-color: {{VALUE}};',
                ],
                'default' => '#4CAF50',
            ]
        );

        $this->add_control(
            'button_text_color',
            [
                'label' => __('Button Text Color', 'spark-of-divine-scheduler'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .checkout-button, {{WRAPPER}} .wc-proceed-to-checkout .button' => 'color: {{VALUE}};',
                ],
                'default' => '#ffffff',
            ]
        );

        $this->add_control(
            'progress_active_color',
            [
                'label' => __('Progress Active Color', 'spark-of-divine-scheduler'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .sod-progress-steps li.active .step' => 'background-color: {{VALUE}};',
                    '{{WRAPPER}} .sod-progress-steps li.active .label' => 'color: {{VALUE}};',
                ],
                'default' => '#4CAF50',
            ]);

        $this->end_controls_section();
    }

    /**
     * Render widget output on the frontend
     */
    protected function render() {
        $settings = $this->get_settings_for_display();

        // Only proceed if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            echo '<div class="elementor-alert elementor-alert-warning">';
            echo __('WooCommerce is not active. Please activate it to show the cart.', 'spark-of-divine-scheduler');
            echo '</div>';
            return;
        }

        // Check if cart is empty
        if (WC()->cart->is_empty()) {
            echo '<div class="woocommerce-notices-wrapper">';
            echo '<p class="cart-empty woocommerce-info">' . __('Your cart is currently empty.', 'woocommerce') . '</p>';
            echo '</div>';

            echo '<p class="return-to-shop">';
            echo '<a class="button wc-backward" href="' . esc_url(apply_filters('woocommerce_return_to_shop_redirect', wc_get_page_permalink('shop'))) . '">';
            echo esc_html(apply_filters('woocommerce_return_to_shop_text', __('Return to shop', 'woocommerce')));
            echo '</a>';
            echo '</p>';
            return;
        }

        echo '<div class="sod-custom-cart">';

        // Return link at the top
        echo '<div class="sod-top-actions">';
        if (!empty($settings['return_url']['url'])) {
            echo '<a href="' . esc_url($settings['return_url']['url']) . '" class="sod-return-to-calendar">';
            echo '&larr; ' . esc_html($settings['return_text'] ?? 'Back to Spark Calendar');
            echo '</a>';
        }
        echo '</div>';

        // Start the two-column layout
        echo '<div class="sod-two-column-layout">';

        // Column 1: Contact Fields (for non-logged in users)
        echo '<div class="sod-column-left">';

        if (!is_user_logged_in()) {
            // Get saved values from WC session if available
            $first_name = '';
            $last_name = '';
            $email = '';
            $phone = '';
            $opt_out = false;

            if (function_exists('WC') && WC()->session) {
                $first_name = WC()->session->get('sod_cart_first_name', '');
                $last_name = WC()->session->get('sod_cart_last_name', '');
                $email = WC()->session->get('sod_cart_email', '');
                $phone = WC()->session->get('sod_cart_phone', '');
                $opt_out = WC()->session->get('sod_cart_opt_out', false);
            }

            // Create a unique ID for this instance
            $block_id = 'sod-cart-contact-info-' . uniqid();
            ?>
            <div class="sod-cart-contact-info elementor-widget-container" id="<?php echo esc_attr($block_id); ?>">
                <!-- Guest checkout notice above fields -->
                <p class="sod-guest-checkout-notice">
                    <strong><?php _e('GUEST CHECKOUT', 'spark-of-divine-scheduler'); ?></strong><br>
                    <?php _e('In order to proceed, we require your <strong>full name, email, and phone number</strong> so that we can communicate with <strong>you and your provider</strong>.', 'spark-of-divine-scheduler'); ?>
                </p>

                <div class="sod-cart-field">
                    <label for="<?php echo esc_attr($block_id); ?>-first-name"><?php _e('First Name', 'spark-of-divine-scheduler'); ?> <span class="required">*</span></label>
                    <input type="text" id="<?php echo esc_attr($block_id); ?>-first-name" name="sod_cart_first_name" value="<?php echo esc_attr($first_name); ?>" required />
                    <span class="error-message" id="<?php echo esc_attr($block_id); ?>-first-name-error"></span>
                </div>

                <div class="sod-cart-field">
                    <label for="<?php echo esc_attr($block_id); ?>-last-name"><?php _e('Last Name', 'spark-of-divine-scheduler'); ?> <span class="required">*</span></label>
                    <input type="text" id="<?php echo esc_attr($block_id); ?>-last-name" name="sod_cart_last_name" value="<?php echo esc_attr($last_name); ?>" required />
                    <span class="error-message" id="<?php echo esc_attr($block_id); ?>-last-name-error"></span>
                </div>

                <div class="sod-cart-field">
                    <label for="<?php echo esc_attr($block_id); ?>-email"><?php _e('Email Address', 'spark-of-divine-scheduler'); ?> <span class="required">*</span></label>
                    <input type="email" id="<?php echo esc_attr($block_id); ?>-email" name="sod_cart_email" value="<?php echo esc_attr($email); ?>" required />
                    <span class="error-message" id="<?php echo esc_attr($block_id); ?>-email-error"></span>
                </div>

                <div class="sod-cart-field">
                    <label for="<?php echo esc_attr($block_id); ?>-phone"><?php _e('Phone Number', 'spark-of-divine-scheduler'); ?> <span class="required">*</span></label>
                    <input type="tel" id="<?php echo esc_attr($block_id); ?>-phone" name="sod_cart_phone" value="<?php echo esc_attr($phone); ?>" required />
                    <span class="error-message" id="<?php echo esc_attr($block_id); ?>-phone-error"></span>
                </div>

                <div class="sod-cart-field sod-checkbox-field">
                    <label>
                        <input type="checkbox" id="<?php echo esc_attr($block_id); ?>-opt-out" name="sod_cart_opt_out" <?php checked($opt_out); ?> />
                        <?php _e('Opt out of receiving promotions', 'spark-of-divine-scheduler'); ?>
                    </label>
                </div>

                <p class="sod-terms-notice">
                    <?php _e('You are <strong>not registering for an account</strong>, but you <strong>authorize Spark of Divine to contact you via phone and email</strong>. You may opt out of receiving future promotions at any time.', 'spark-of-divine-scheduler'); ?>
                </p>

                <div class="sod-register-link">
                    <?php _e('Want to create an account?', 'spark-of-divine-scheduler'); ?> 
                    <a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>"><?php _e('Register here', 'spark-of-divine-scheduler'); ?></a>
                </div>

                <div class="sod-contact-status"></div>

                <button class="sod-save-contact-info"><?php _e('Continue to Checkout', 'spark-of-divine-scheduler'); ?></button>
            </div>
            <?php
        } else {
            // For logged-in users, show their information
            echo '<div class="sod-user-info">';
            echo '<h3>' . __('Your Information', 'spark-of-divine-scheduler') . '</h3>';
            $user = wp_get_current_user();
            echo '<p><strong>' . __('Name:', 'spark-of-divine-scheduler') . '</strong> ' . esc_html($user->first_name . ' ' . $user->last_name) . '</p>';
            echo '<p><strong>' . __('Email:', 'spark-of-divine-scheduler') . '</strong> ' . esc_html($user->user_email) . '</p>';
            echo '</div>';
        }

        echo '</div>'; // End left column

        // Column 2: Cart Items
        echo '<div class="sod-column-right">';

        // Cart title
        echo '<h3 class="sod-cart-title">' . __('Your Cart', 'spark-of-divine-scheduler') . '</h3>';

        // Start cart form
        echo '<div class="woocommerce">';
        echo '<form class="woocommerce-cart-form" action="' . esc_url(wc_get_cart_url()) . '" method="post">';

        // Show cart items
        if ('yes' === $settings['show_cart_items']) {
            echo '<table class="shop_table shop_table_responsive cart woocommerce-cart-form__contents" cellspacing="0">';
            echo '<thead>';
            echo '<tr>';
            echo '<th class="product-remove">&nbsp;</th>';
            echo '<th class="product-thumbnail">&nbsp;</th>';
            echo '<th class="product-name">' . __('Product', 'woocommerce') . '</th>';
            echo '<th class="product-price">' . __('Price', 'woocommerce') . '</th>';
            echo '<th class="product-quantity">' . __('Qty', 'woocommerce') . '</th>';
            echo '<th class="product-subtotal">' . __('Subtotal', 'woocommerce') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
                $product_id = apply_filters('woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key);

                if ($_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters('woocommerce_cart_item_visible', true, $cart_item, $cart_item_key)) {
                    $product_permalink = apply_filters('woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink($cart_item) : '', $cart_item, $cart_item_key);

                    echo '<tr class="woocommerce-cart-form__cart-item ' . esc_attr(apply_filters('woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key)) . '">';

                    // Remove link
                    echo '<td class="product-remove">';
                    echo apply_filters('woocommerce_cart_item_remove_link', sprintf(
                        '<a href="%s" class="remove" aria-label="%s" data-product_id="%s" data-product_sku="%s">&times;</a>',
                        esc_url(wc_get_cart_remove_url($cart_item_key)),
                        esc_html__('Remove this item', 'woocommerce'),
                        esc_attr($product_id),
                        esc_attr($_product->get_sku())
                    ), $cart_item_key);
                    echo '</td>';

                    // Thumbnail
                    echo '<td class="product-thumbnail">';
                    echo apply_filters('woocommerce_cart_item_thumbnail', $_product->get_image(), $cart_item, $cart_item_key);
                    echo '</td>';

                    // Product name
                    echo '<td class="product-name" data-title="' . esc_attr__('Product', 'woocommerce') . '">';
                    echo wp_kses_post(apply_filters('woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key));
                    echo '</td>';

                    // Price
                    echo '<td class="product-price" data-title="' . esc_attr__('Price', 'woocommerce') . '">';
                    echo apply_filters('woocommerce_cart_item_price', WC()->cart->get_product_price($_product), $cart_item, $cart_item_key);
                    echo '</td>';

                    // Quantity
                    echo '<td class="product-quantity" data-title="' . esc_attr__('Quantity', 'woocommerce') . '">';
                    echo woocommerce_quantity_input(
                        array(
                            'input_name'   => "cart[{$cart_item_key}][qty]",
                            'input_value'  => $cart_item['quantity'],
                            'max_value'    => $_product->get_max_purchase_quantity(),
                            'min_value'    => '0',
                        ),
                        $_product,
                        false
                    );
                    echo '</td>';

                    // Subtotal
                    echo '<td class="product-subtotal" data-title="' . esc_attr__('Subtotal', 'woocommerce') . '">';
                    echo apply_filters('woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal($_product, $cart_item['quantity']), $cart_item, $cart_item_key);
                    echo '</td>';

                    echo '</tr>';
                }
            }

            echo '</tbody>';
            echo '</table>';

            // Compact coupon form and update cart button
            echo '<div class="actions">';

            if (wc_coupons_enabled() && 'yes' === $settings['show_coupon']) {
                echo '<div class="coupon sod-coupon-compact">';
                echo '<label for="coupon_code">' . esc_html__('Coupon:', 'woocommerce') . '</label>';
                echo '<input type="text" name="coupon_code" class="input-text" id="coupon_code" value="" placeholder="' . esc_attr__('Coupon code', 'woocommerce') . '" />';
                echo '<button type="submit" class="button" name="apply_coupon" value="' . esc_attr__('Apply coupon', 'woocommerce') . '">' . esc_html__('Apply', 'woocommerce') . '</button>';
                echo '</div>';
            }

            echo '<button type="submit" class="button" name="update_cart" value="' . esc_attr__('Update cart', 'woocommerce') . '">' . esc_html__('Update cart', 'woocommerce') . '</button>';

            echo wp_nonce_field('woocommerce-cart', 'woocommerce-cart-nonce', true, false);

            echo '</div>'; // close .actions
        }

        echo '</form>';

        // Cart totals
        if ('yes' === $settings['show_totals']) {
            echo '<div class="cart-collaterals">';
            echo '<div class="cart_totals">';

            echo '<h2>' . __('Cart totals', 'woocommerce') . '</h2>';

            echo '<table cellspacing="0" class="shop_table shop_table_responsive">';

            // Subtotal
            echo '<tr class="cart-subtotal">';
            echo '<th>' . __('Subtotal', 'woocommerce') . '</th>';
            echo '<td data-title="' . esc_attr__('Subtotal', 'woocommerce') . '">' . WC()->cart->get_cart_subtotal() . '</td>';
            echo '</tr>';

            // Coupons
            $coupons = WC()->cart->get_coupons();
            if (!empty($coupons)) {
                foreach ($coupons as $code => $coupon) {
                    echo '<tr class="cart-discount coupon-' . esc_attr(sanitize_title($code)) . '">';
                    echo '<th>' . esc_html(wc_cart_totals_coupon_label($coupon, false)) . '</th>';
                    echo '<td data-title="' . esc_attr(wc_cart_totals_coupon_label($coupon, false)) . '">' . wc_cart_totals_coupon_html($coupon) . '</td>';
                    echo '</tr>';
                }
            }

            // Shipping
            if (WC()->cart->needs_shipping() && WC()->cart->show_shipping()) {
                echo '<tr class="woocommerce-shipping-totals shipping">';
                echo '<th>' . __('Shipping', 'woocommerce') . '</th>';
                echo '<td data-title="' . esc_attr__('Shipping', 'woocommerce') . '">';
                wc_cart_totals_shipping_html();
                echo '</td>';
                echo '</tr>';
            }

            // Total
            echo '<tr class="order-total">';
            echo '<th>' . __('Total', 'woocommerce') . '</th>';
            echo '<td data-title="' . esc_attr__('Total', 'woocommerce') . '">' . WC()->cart->get_total() . '</td>';
            echo '</tr>';

            echo '</table>';

            // Only show checkout button for logged-in users or if contact info is already saved
            if (is_user_logged_in() || (WC()->session && WC()->session->get('sod_cart_email'))) {
                echo '<div class="wc-proceed-to-checkout">';
                echo '<a href="' . esc_url(wc_get_checkout_url()) . '" class="checkout-button button alt wc-forward">';
                echo esc_html__('Proceed to checkout', 'woocommerce');
                echo '</a>';
                echo '</div>';
            }

            echo '</div>'; // Close .cart_totals
            echo '</div>'; // Close .cart-collaterals
        }

        echo '</div>'; // Close .woocommerce

        echo '</div>'; // End right column

        echo '</div>'; // End two-column layout

        echo '</div>'; // Close .sod-custom-cart
    }
}