<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class SOD_Registration_Form {
    private $db_access;

    public function __construct($db_access) {
        $this->db_access = $db_access;

        // Hook into WooCommerce registration
        add_action('woocommerce_register_form_start', array($this, 'renderRegistrationFields'));
        add_filter('woocommerce_registration_errors', array($this, 'validateRegistrationFields'), 10, 3);
        add_action('woocommerce_created_customer', array($this, 'saveRegistrationFields'));
    }

    // Render custom registration fields
    public function renderRegistrationFields() {
        ?>
        <p class="form-row form-row-wide">
            <label for="client_phone"><?php _e('Your Phone Number', 'spark-divine-service'); ?> <span class="required">*</span></label>
            <input type="tel" class="input-text" name="client_phone" id="client_phone" value="<?php echo isset($_POST['client_phone']) ? esc_attr($_POST['client_phone']) : ''; ?>" />
        </p>
        <p class="form-row form-row-wide">
            <label for="emergency_contact_name"><?php _e('Emergency Contact Name', 'spark-divine-service'); ?> <span class="required">*</span></label>
            <input type="text" class="input-text" name="emergency_contact_name" id="emergency_contact_name" value="<?php echo isset($_POST['emergency_contact_name']) ? esc_attr($_POST['emergency_contact_name']) : ''; ?>" />
        </p>
        <p class="form-row form-row-wide">
            <label for="emergency_contact_phone"><?php _e('Emergency Contact Phone', 'spark-divine-service'); ?> <span class="required">*</span></label>
            <input type="tel" class="input-text" name="emergency_contact_phone" id="emergency_contact_phone" value="<?php echo isset($_POST['emergency_contact_phone']) ? esc_attr($_POST['emergency_contact_phone']) : ''; ?>" />
        </p>
        <p class="form-row form-row-wide">
            <label><?php _e('Are you signing up a child or other dependent?', 'spark-divine-service'); ?></label><br>
            <label><input type="radio" name="signing_dependent" value="yes" <?php checked(isset($_POST['signing_dependent']) && $_POST['signing_dependent'] === 'yes'); ?> /> Yes</label>
            <label><input type="radio" name="signing_dependent" value="no" <?php checked(!isset($_POST['signing_dependent']) || $_POST['signing_dependent'] === 'no'); ?> /> No</label>
        </p>
        <div id="dependent-details" style="display: none;">
            <p class="form-row form-row-wide">
                <label for="dependent_name"><?php _e("Dependent's Name", 'spark-divine-service'); ?></label>
                <input type="text" class="input-text" name="dependent_name" id="dependent_name" value="<?php echo isset($_POST['dependent_name']) ? esc_attr($_POST['dependent_name']) : ''; ?>" />
            </p>
            <p class="form-row form-row-wide">
                <label for="dependent_dob"><?php _e("Dependent's Date of Birth", 'spark-divine-service'); ?></label>
                <input type="date" class="input-text" name="dependent_dob" id="dependent_dob" value="<?php echo isset($_POST['dependent_dob']) ? esc_attr($_POST['dependent_dob']) : ''; ?>" />
            </p>
        </div>
        <p class="form-row form-row-wide">
            <label for="terms_conditions">
                <input type="checkbox" name="terms_conditions" id="terms_conditions" <?php checked(isset($_POST['terms_conditions'])); ?> required />
                <?php _e('I agree to the', 'spark-divine-service'); ?> <a href="/terms-of-service" target="_blank"><?php _e('Terms of Service', 'spark-divine-service'); ?></a> <?php _e('and', 'spark-divine-service'); ?> <a href="/privacy-policy" target="_blank"><?php _e('Privacy Policy', 'spark-divine-service'); ?></a>.
            </label>
        </p>
        <?php
    }

    // Validate custom registration fields
    public function validateRegistrationFields($errors, $username, $email) {
        if (empty($_POST['client_phone'])) {
            $errors->add('client_phone_error', __('Please enter your phone number.', 'spark-divine-service'));
        }
        if (empty($_POST['emergency_contact_name'])) {
            $errors->add('emergency_contact_name_error', __('Please enter your emergency contact name.', 'spark-divine-service'));
        }
        if (empty($_POST['emergency_contact_phone'])) {
            $errors->add('emergency_contact_phone_error', __('Please enter your emergency contact phone.', 'spark-divine-service'));
        }
        if (!isset($_POST['terms_conditions'])) {
            $errors->add('terms_conditions_error', __('You must accept the Terms of Service and Privacy Policy.', 'spark-divine-service'));
        }
        if (isset($_POST['signing_dependent']) && $_POST['signing_dependent'] === 'yes') {
            if (empty($_POST['dependent_name'])) {
                $errors->add('dependent_name_error', __("Please enter your dependent's name.", 'spark-divine-service'));
            }
            if (empty($_POST['dependent_dob'])) {
                $errors->add('dependent_dob_error', __("Please enter your dependent's date of birth.", 'spark-divine-service'));
            }
        }
        return $errors;
    }

    // Save custom registration fields
    public function saveRegistrationFields($customer_id) {
        // Gather the data
        $client_phone = isset($_POST['client_phone']) ? sanitize_text_field($_POST['client_phone']) : '';
        $emergency_contact_name = isset($_POST['emergency_contact_name']) ? sanitize_text_field($_POST['emergency_contact_name']) : '';
        $emergency_contact_phone = isset($_POST['emergency_contact_phone']) ? sanitize_text_field($_POST['emergency_contact_phone']) : '';
        $signing_dependent = isset($_POST['signing_dependent']) ? sanitize_text_field($_POST['signing_dependent']) : '';
        $dependent_name = isset($_POST['dependent_name']) ? sanitize_text_field($_POST['dependent_name']) : '';
        $dependent_dob = isset($_POST['dependent_dob']) ? sanitize_text_field($_POST['dependent_dob']) : '';

        // Save the data to custom table using the SOD_DB_Access class
        $this->db_access->createCustomer($customer_id, $client_phone, $emergency_contact_name, $emergency_contact_phone, $signing_dependent, $dependent_name, $dependent_dob);
    }
}