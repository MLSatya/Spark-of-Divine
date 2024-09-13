<?php
/*
Plugin Name: Spark of Divine Service Scheduler
Description: A custom plugin for scheduling and managing services at the healing center.
Version: 1.0
Author: MLSatya
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class SparkOfDivineServiceScheduler {
    private $availability_costs_table;
    private $bookings_table;
    private $error_log_file;

   public function __construct() {
    global $wpdb;
    $this->availability_costs_table = $wpdb->prefix . 'sod_availability_costs';
    $this->bookings_table = $wpdb->prefix . 'sod_service_bookings';
    $this->error_log_file = WP_CONTENT_DIR . '/sod-error.log';

    register_activation_hook(__FILE__, array($this, 'activate_plugin'));

          try {
        $this->include_files();
        $this->setup_hooks();
    } catch (Exception $e) {
        $this->log_error('Plugin initialization failed: ' . $e->getMessage());
        add_action('admin_notices', array($this, 'display_admin_error'));
    }
   }

    private function include_files() {
        $required_files = array(
            'includes/booking-handler.php',
            'includes/booking-endpoint.php',
            'includes/events-api.php',
            'includes/booking-emails.php',
            'includes/bookings-admin-page.php',
        );

        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            $required_files = array_merge($required_files, array(
                'includes/booking-created.php',
                'includes/booking-confirmed.php',
                'includes/booking-updated.php',
                'includes/booking-canceled.php',
                'includes/booking-paid.php',
            ));
        }

        foreach ($required_files as $file) {
            $file_path = plugin_dir_path(__FILE__) . $file;
            if (!file_exists($file_path)) {
                throw new Exception("Required file not found: $file");
            }
            require_once $file_path;
        }
    }

    private function setup_hooks() {
        add_action('init', array($this, 'initialize_components'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('acf/save_post', array($this, 'update_availability_costs'), 20);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('woocommerce_register_form_start', array($this, 'spark_divine_service_additional_registration_fields'));
        add_filter('woocommerce_registration_errors', array($this, 'spark_divine_service_validate_registration_fields'), 10, 3);
        add_action('woocommerce_created_customer', array($this, 'spark_divine_service_save_registration_fields'));
    }

    public function activate_plugin() {
        try {
            $this->create_custom_table();
            $this->create_booking_table();
            flush_rewrite_rules();
        } catch (Exception $e) {
            $this->log_error('Plugin activation failed: ' . $e->getMessage());
            wp_die('Error activating plugin. Please check the error log.');
        }
    }

    public function initialize_components() {
        new SOD_Booking_Emails();
        new SOD_Bookings_Admin_Page();
    }

    public function enqueue_scripts() {
        wp_enqueue_style('spark-divine-scheduler-style', plugins_url('assets/css/custom-style.css', __FILE__));
        wp_enqueue_script('spark-divine-scheduler-script', plugins_url('assets/js/custom-script.js', __FILE__), array('jquery'), null, true);
    }

   public function spark_divine_service_additional_registration_fields() {
        ?>
        <p class="form-row form-row-wide">
            <label for="client_phone"><?php _e( 'Your Phone Number', 'spark-divine-service' ); ?> <span class="required">*</span></label>
            <input type="tel" class="input-text" name="client_phone" id="client_phone" value="<?php echo isset( $_POST['client_phone'] ) ? esc_attr( $_POST['client_phone'] ) : ''; ?>" />
        </p>
        <p class="form-row form-row-wide">
            <label for="emergency_contact_name"><?php _e( 'Emergency Contact Name', 'spark-divine-service' ); ?> <span class="required">*</span></label>
            <input type="text" class="input-text" name="emergency_contact_name" id="emergency_contact_name" value="<?php echo isset( $_POST['emergency_contact_name'] ) ? esc_attr( $_POST['emergency_contact_name'] ) : ''; ?>" />
        </p>
        <p class="form-row form-row-wide">
            <label for="emergency_contact_phone"><?php _e( 'Emergency Contact Phone', 'spark-divine-service' ); ?> <span class="required">*</span></label>
            <input type="tel" class="input-text" name="emergency_contact_phone" id="emergency_contact_phone" value="<?php echo isset( $_POST['emergency_contact_phone'] ) ? esc_attr( $_POST['emergency_contact_phone'] ) : ''; ?>" />
        </p>
        <p class="form-row form-row-wide">
            <label><?php _e( 'Are you signing up a child or other dependent?', 'spark-divine-service' ); ?></label><br>
            <label><input type="radio" name="signing_dependent" value="yes" <?php checked( isset( $_POST['signing_dependent'] ) && $_POST['signing_dependent'] === 'yes' ); ?> /> Yes</label>
            <label><input type="radio" name="signing_dependent" value="no" <?php checked( !isset( $_POST['signing_dependent'] ) || $_POST['signing_dependent'] === 'no' ); ?> /> No</label>
        </p>
        <div id="dependent-details" style="display: none;">
            <p class="form-row form-row-wide">
                <label for="dependent_name"><?php _e( "Dependent's Name", 'spark-divine-service' ); ?></label>
                <input type="text" class="input-text" name="dependent_name" id="dependent_name" value="<?php echo isset( $_POST['dependent_name'] ) ? esc_attr( $_POST['dependent_name'] ) : ''; ?>" />
            </p>
            <p class="form-row form-row-wide">
                <label for="dependent_dob"><?php _e( "Dependent's Date of Birth", 'spark-divine-service' ); ?></label>
                <input type="date" class="input-text" name="dependent_dob" id="dependent_dob" value="<?php echo isset( $_POST['dependent_dob'] ) ? esc_attr( $_POST['dependent_dob'] ) : ''; ?>" />
            </p>
        </div>
        <p class="form-row form-row-wide">
            <label for="terms_conditions">
                <input type="checkbox" name="terms_conditions" id="terms_conditions" <?php checked( isset( $_POST['terms_conditions'] ) ); ?> required />
                <?php _e( 'I agree to the', 'spark-divine-service' ); ?> <a href="/terms-of-service" target="_blank"><?php _e( 'Terms of Service', 'spark-divine-service' ); ?></a> <?php _e( 'and', 'spark-divine-service' ); ?> <a href="/privacy-policy" target="_blank"><?php _e( 'Privacy Policy', 'spark-divine-service' ); ?></a>.
            </label>
        </p>
        <?php
    }

    public function spark_divine_service_validate_registration_fields( $errors, $username, $email ) {
        if ( empty( $_POST['client_phone'] ) ) {
            $errors->add( 'client_phone_error', __( 'Please enter your phone number.', 'spark-divine-service' ) );
        }
        if ( empty( $_POST['emergency_contact_name'] ) ) {
            $errors->add( 'emergency_contact_name_error', __( 'Please enter your emergency contact name.', 'spark-divine-service' ) );
        }
        if ( empty( $_POST['emergency_contact_phone'] ) ) {
            $errors->add( 'emergency_contact_phone_error', __( 'Please enter your emergency contact phone.', 'spark-divine-service' ) );
        }
        if ( !isset( $_POST['terms_conditions'] ) ) {
            $errors->add( 'terms_conditions_error', __( 'You must accept the Terms of Service and Privacy Policy.', 'spark-divine-service' ) );
        }
        if ( isset( $_POST['signing_dependent'] ) && $_POST['signing_dependent'] === 'yes' ) {
            if ( empty( $_POST['dependent_name'] ) ) {
                $errors->add( 'dependent_name_error', __( "Please enter your dependent's name.", 'spark-divine-service' ) );
            }
            if ( empty( $_POST['dependent_dob'] ) ) {
                $errors->add( 'dependent_dob_error', __( "Please enter your dependent's date of birth.", 'spark-divine-service' ) );
            }
        }
        return $errors;
    }

    public function spark_divine_service_save_registration_fields( $customer_id ) {
        if ( isset( $_POST['client_phone'] ) ) {
            update_user_meta( $customer_id, 'client_phone', sanitize_text_field( $_POST['client_phone'] ) );
        }
        if ( isset( $_POST['emergency_contact_name'] ) ) {
            update_user_meta( $customer_id, 'emergency_contact_name', sanitize_text_field( $_POST['emergency_contact_name'] ) );
        }
        if ( isset( $_POST['emergency_contact_phone'] ) ) {
            update_user_meta( $customer_id, 'emergency_contact_phone', sanitize_text_field( $_POST['emergency_contact_phone'] ) );
        }
        if ( isset( $_POST['signing_dependent'] ) ) {
            update_user_meta( $customer_id, 'signing_dependent', sanitize_text_field( $_POST['signing_dependent'] ) );
        }
        if ( isset( $_POST['dependent_name'] ) ) {
            update_user_meta( $customer_id, 'dependent_name', sanitize_text_field( $_POST['dependent_name'] ) );
        }
        if ( isset( $_POST['dependent_dob'] ) ) {
            update_user_meta( $customer_id, 'dependent_dob', sanitize_text_field( $_POST['dependent_dob'] ) );
        }
    }
    
    public function register_rest_routes() {
        register_rest_route('spark-divine/v1', '/events', array(
            'methods' => 'GET',
            'callback' => 'spark_divine_get_events',
            'permission_callback' => '__return_true',
        ));
    }

    public function update_availability_costs($post_id) {
    if (get_post_type($post_id) != 'staff') {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'sod_availability_costs';

    // Delete existing entries for this staff member
    $wpdb->delete($table_name, array('staff_id' => $post_id));

    $availability = get_field('availability', $post_id);
    $linked_services = get_field('linked_services', $post_id);

    if (!$availability || !$linked_services) {
        return;
    }

    foreach ($linked_services as $service) {
        $cost = get_field('cost', $service->ID);
        
        foreach ($availability as $slot) {
            $wpdb->insert(
                $table_name,
                array(
                    'staff_id' => $post_id,
                    'service_id' => $service->ID,
                    'day_of_week' => date('N', strtotime($slot['day_of_the_week'])),
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                    'cost_per_15_min' => $cost
                ),
                array('%d', '%d', '%d', '%s', '%s', '%f')
            );
        }
    }
    }

    private function log_error($message) {
        error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, $this->error_log_file);
    }

    public function display_admin_error() {
        $class = 'notice notice-error';
        $message = __('There was an error initializing the Spark of Divine Service Scheduler plugin. Please check the error log.', 'spark-divine-service');
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
    }
}

try {
    new SparkOfDivineServiceScheduler();
} catch (Exception $e) {
    error_log('SparkOfDivineServiceScheduler initialization failed: ' . $e->getMessage());
    add_action('admin_notices', function() {
        $class = 'notice notice-error';
        $message = __('Failed to initialize Spark of Divine Service Scheduler plugin. Please check the error log.', 'spark-divine-service');
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
    });
}
?>