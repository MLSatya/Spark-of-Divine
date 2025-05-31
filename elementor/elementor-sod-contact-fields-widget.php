<?php
/**
 * SOD Contact Fields Elementor Widget
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Register SOD Cart Contact Fields Widget
 */
class SOD_Contact_Fields_Widget extends \Elementor\Widget_Base {

    /**
     * Get widget name
     */
    public function get_name() {
        return 'sod_contact_fields';
    }

    /**
     * Get widget title
     */
    public function get_title() {
        return __('SOD Contact Fields', 'spark-of-divine-scheduler');
    }

    /**
     * Get widget icon
     */
    public function get_icon() {
        return 'eicon-form-horizontal';
    }

    /**
     * Get widget categories
     */
    public function get_categories() {
        return ['general', 'woocommerce-elements'];
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
            'title',
            [
                'label' => __('Title', 'spark-of-divine-scheduler'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Contact Information', 'spark-of-divine-scheduler'),
            ]
        );

        $this->add_control(
            'description',
            [
                'label' => __('Description', 'spark-of-divine-scheduler'),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'default' => __('Please provide your details to proceed to checkout.', 'spark-of-divine-scheduler'),
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
                    '{{WRAPPER}} .sod-cart-contact-info' => 'background-color: {{VALUE}};',
                ],
                'default' => '#f7f7f7',
            ]
        );

        $this->add_control(
            'container_border_color',
            [
                'label' => __('Border Color', 'spark-of-divine-scheduler'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .sod-cart-contact-info' => 'border-color: {{VALUE}};',
                ],
                'default' => '#dddddd',
            ]
        );

        $this->add_control(
            'button_background',
            [
                'label' => __('Button Background', 'spark-of-divine-scheduler'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .sod-save-contact-info' => 'background-color: {{VALUE}};',
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
                    '{{WRAPPER}} .sod-save-contact-info' => 'color: {{VALUE}};',
                ],
                'default' => '#ffffff',
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Render widget output on the frontend
     */
    protected function render_contact_fields() {
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
  }
}