<?php
if (!defined('ABSPATH')) {
    exit;
}

class SOD_Registration_Form {
    private $db_access;

    public function __construct($db_access) {
        $this->db_access = $db_access;
        
        add_action('woocommerce_register_form_start', [$this, 'render_registration_fields']);
        add_filter('woocommerce_registration_errors', [$this, 'validate_registration_fields'], 10, 3);
        add_filter('woocommerce_new_customer_data', [$this, 'modify_new_customer_data'], 10, 1);
        add_action('woocommerce_created_customer', [$this, 'save_registration_fields']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function enqueue_scripts() {
        if (is_account_page()) {
            wp_enqueue_script(
                'sod-registration-script',
                SOD_PLUGIN_URL . 'assets/js/sod-registration.js',
                ['jquery'],
                '2.1',
                true
            );
            wp_localize_script('sod-registration-script', 'sodRegistration', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sod_registration_nonce')
            ]);
        }
    }

    public function render_registration_fields() {
        echo "<!-- SOD Custom Fields Start -->";
        ?>
        <?php wp_nonce_field('sod_register_nonce', 'sod_register_nonce_field'); ?>

        <p class="form-row form-row-first">
            <label for="reg_first_name"><?php _e('First Name', 'spark-of-divine-scheduler'); ?> <span class="required">*</span></label>
            <input type="text" class="input-text" name="first_name" id="reg_first_name" value="<?php echo isset($_POST['first_name']) ? esc_attr($_POST['first_name']) : ''; ?>" required />
        </p>

        <p class="form-row form-row-last">
            <label for="reg_last_name"><?php _e('Last Name', 'spark-of-divine-scheduler'); ?> <span class="required">*</span></label>
            <input type="text" class="input-text" name="last_name" id="reg_last_name" value="<?php echo isset($_POST['last_name']) ? esc_attr($_POST['last_name']) : ''; ?>" required />
        </p>

        <p class="form-row form-row-wide">
            <label for="reg_email"><?php _e('Email address', 'spark-of-divine-scheduler'); ?> <span class="required">*</span></label>
            <input type="email" class="input-text" name="email" id="reg_email" value="<?php echo isset($_POST['email']) ? esc_attr($_POST['email']) : ''; ?>" required />
        </p>

        <p class="form-row form-row-wide">
            <label for="client_phone"><?php _e('Your Phone Number', 'spark-of-divine-scheduler'); ?> <span class="required">*</span></label>
            <input type="tel" class="input-text" name="client_phone" id="client_phone" value="<?php echo isset($_POST['client_phone']) ? esc_attr($_POST['client_phone']) : ''; ?>" required />
        </p>

        <p class="form-row form-row-wide">
            <label for="emergency_contact_name"><?php _e('Emergency Contact Name', 'spark-of-divine-scheduler'); ?> <span class="required">*</span></label>
            <input type="text" class="input-text" name="emergency_contact_name" id="emergency_contact_name" value="<?php echo isset($_POST['emergency_contact_name']) ? esc_attr($_POST['emergency_contact_name']) : ''; ?>" required />
        </p>

        <p class="form-row form-row-wide">
            <label for="emergency_contact_phone"><?php _e('Emergency Contact Phone', 'spark-of-divine-scheduler'); ?> <span class="required">*</span></label>
            <input type="tel" class="input-text" name="emergency_contact_phone" id="emergency_contact_phone" value="<?php echo isset($_POST['emergency_contact_phone']) ? esc_attr($_POST['emergency_contact_phone']) : ''; ?>" required />
        </p>

        <p class="form-row form-row-wide">
            <label><?php _e('Are you signing up a child or other dependent?', 'spark-of-divine-scheduler'); ?></label><br>
            <label><input type="radio" name="signing_dependent" value="yes" id="signing-dependent-yes" <?php checked(isset($_POST['signing_dependent']) && $_POST['signing_dependent'] === 'yes'); ?> /> Yes</label>
            <label><input type="radio" name="signing_dependent" value="no" id="signing-dependent-no" <?php checked(!isset($_POST['signing_dependent']) || $_POST['signing_dependent'] === 'no'); ?> /> No</label>
        </p>

        <div id="dependent-details" style="display: none;">
            <p class="form-row form-row-wide">
                <label for="dependent_name"><?php _e("Dependent's Name", 'spark-of-divine-scheduler'); ?></label>
                <input type="text" class="input-text" name="dependent_name" id="dependent_name" value="<?php echo isset($_POST['dependent_name']) ? esc_attr($_POST['dependent_name']) : ''; ?>" />
            </p>
            <p class="form-row form-row-wide">
                <label for="dependent_dob"><?php _e("Dependent's Date of Birth", 'spark-of-divine-scheduler'); ?></label>
                <input type="date" class="input-text" name="dependent_dob" id="dependent_dob" value="<?php echo isset($_POST['dependent_dob']) ? esc_attr($_POST['dependent_dob']) : ''; ?>" />
            </p>
        </div>

        <p class="form-row form-row-wide">
            <label for="terms_conditions">
                <input type="checkbox" name="terms_conditions" id="terms_conditions" <?php checked(isset($_POST['terms_conditions'])); ?> required />
                <?php _e('I agree to the', 'spark-of-divine-scheduler'); ?> <a href="/terms-of-service" target="_blank"><?php _e('Terms of Service', 'spark-of-divine-scheduler'); ?></a> <?php _e('and', 'spark-of-divine-scheduler'); ?> <a href="/privacy-policy" target="_blank"><?php _e('Privacy Policy', 'spark-of-divine-scheduler'); ?></a>.
            </label>
        </p>
        <?php
        echo "<!-- SOD Custom Fields End -->";
    }

    public function validate_registration_fields($errors, $username, $email) {
        if (!isset($_POST['sod_register_nonce_field']) || !wp_verify_nonce($_POST['sod_register_nonce_field'], 'sod_register_nonce')) {
            $errors->add('nonce_error', __('Security check failed. Please try again.', 'spark-of-divine-scheduler'));
        }

        if (empty($_POST['first_name'])) {
            $errors->add('first_name_error', __('Please enter your first name.', 'spark-of-divine-scheduler'));
        }
        if (empty($_POST['last_name'])) {
            $errors->add('last_name_error', __('Please enter your last name.', 'spark-of-divine-scheduler'));
        }
        if (empty($_POST['client_phone'])) {
            $errors->add('client_phone_error', __('Please enter your phone number.', 'spark-of-divine-scheduler'));
        }
        if (empty($_POST['emergency_contact_name'])) {
            $errors->add('emergency_contact_name_error', __('Please enter your emergency contact name.', 'spark-of-divine-scheduler'));
        }
        if (empty($_POST['emergency_contact_phone'])) {
            $errors->add('emergency_contact_phone_error', __('Please enter your emergency contact phone.', 'spark-of-divine-scheduler'));
        }
        if (!isset($_POST['terms_conditions'])) {
            $errors->add('terms_conditions_error', __('You must accept the Terms of Service and Privacy Policy.', 'spark-of-divine-scheduler'));
        }

        if (isset($_POST['signing_dependent']) && $_POST['signing_dependent'] === 'yes') {
            if (empty($_POST['dependent_name'])) {
                $errors->add('dependent_name_error', __("Please enter your dependent's name.", 'spark-of-divine-scheduler'));
            }
            if (empty($_POST['dependent_dob'])) {
                $errors->add('dependent_dob_error', __("Please enter your dependent's date of birth.", 'spark-of-divine-scheduler'));
            }
        }
        return $errors;
    }

    public function modify_new_customer_data($customer_data) {
        if (isset($_POST['username']) && !username_exists($_POST['username'])) {
            $customer_data['user_login'] = sanitize_user($_POST['username']);
        }
        return $customer_data;
    }

    public function save_registration_fields($customer_id) {
        if (!$customer_id) {
            return;
        }

        error_log('SOD_Registration_Form: Preparing to save data for WooCommerce customer ID ' . $customer_id);

        $user_data = get_userdata($customer_id);
        $email = $user_data->user_email;

        // Save to WooCommerce user meta
        $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
        $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';
        $phone = isset($_POST['client_phone']) ? sanitize_text_field($_POST['client_phone']) : '';

        update_user_meta($customer_id, 'first_name', $first_name);
        update_user_meta($customer_id, 'last_name', $last_name);
        update_user_meta($customer_id, 'billing_phone', $phone);
        update_user_meta($customer_id, 'billing_email', $email);

        // Additional fields
        update_user_meta($customer_id, 'emergency_contact_name', isset($_POST['emergency_contact_name']) ? sanitize_text_field($_POST['emergency_contact_name']) : '');
        update_user_meta($customer_id, 'emergency_contact_phone', isset($_POST['emergency_contact_phone']) ? sanitize_text_field($_POST['emergency_contact_phone']) : '');
        update_user_meta($customer_id, 'signing_dependent', isset($_POST['signing_dependent']) && $_POST['signing_dependent'] === 'yes' ? 'yes' : 'no');
        update_user_meta($customer_id, 'dependent_name', isset($_POST['dependent_name']) ? sanitize_text_field($_POST['dependent_name']) : '');
        update_user_meta($customer_id, 'dependent_dob', isset($_POST['dependent_dob']) ? sanitize_text_field($_POST['dependent_dob']) : '');

        // Create/Update sod_customer post
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
            $sod_customer_id = get_the_ID();
            wp_update_post([
                'ID' => $sod_customer_id,
                'post_title' => "$first_name $last_name",
            ]);
            update_post_meta($sod_customer_id, 'sod_customer_first_name', $first_name);
            update_post_meta($sod_customer_id, 'sod_customer_last_name', $last_name);
            update_post_meta($sod_customer_id, 'sod_customer_email', $email);
            update_post_meta($sod_customer_id, 'sod_customer_phone', $phone);
            update_post_meta($sod_customer_id, 'sod_customer_user_id', $customer_id);
            error_log("SOD_Registration_Form: Updated existing sod_customer ID $sod_customer_id for user ID $customer_id");
        } else {
            $sod_customer_id = wp_insert_post([
                'post_type' => 'sod_customer',
                'post_title' => "$first_name $last_name",
                'post_status' => 'publish',
            ]);
            if (!is_wp_error($sod_customer_id)) {
                update_post_meta($sod_customer_id, 'sod_customer_first_name', $first_name);
                update_post_meta($sod_customer_id, 'sod_customer_last_name', $last_name);
                update_post_meta($sod_customer_id, 'sod_customer_email', $email);
                update_post_meta($sod_customer_id, 'sod_customer_phone', $phone);
                update_post_meta($sod_customer_id, 'sod_customer_user_id', $customer_id);
                error_log("SOD_Registration_Form: Created new sod_customer ID $sod_customer_id for user ID $customer_id");
            } else {
                error_log("SOD_Registration_Form: Failed to create sod_customer for user ID $customer_id");
            }
        }
        wp_reset_postdata();

        // Optional: Save to custom table
        $custom_customer_data = [
            'name' => $user_data->user_login,
            'email' => $email,
            'client_phone' => $phone,
            'emergency_contact_name' => isset($_POST['emergency_contact_name']) ? sanitize_text_field($_POST['emergency_contact_name']) : '',
            'emergency_contact_phone' => isset($_POST['emergency_contact_phone']) ? sanitize_text_field($_POST['emergency_contact_phone']) : '',
            'signing_dependent' => isset($_POST['signing_dependent']) && $_POST['signing_dependent'] === 'yes' ? 1 : 0,
            'dependent_name' => isset($_POST['dependent_name']) ? sanitize_text_field($_POST['dependent_name']) : '',
            'dependent_dob' => isset($_POST['dependent_dob']) ? sanitize_text_field($_POST['dependent_dob']) : null,
        ];
        $custom_customer_id = $this->db_access->createCustomer($custom_customer_data);
        if ($custom_customer_id !== false) {
            update_user_meta($customer_id, 'sod_custom_table_customer_id', $custom_customer_id);
            error_log("SOD_Registration_Form: Saved to custom table with ID $custom_customer_id");
        }

        error_log("SOD_Registration_Form: Customer data saved for WooCommerce customer ID $customer_id");
    }
}