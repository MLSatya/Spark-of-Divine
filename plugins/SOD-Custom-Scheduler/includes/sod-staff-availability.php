<?php 
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class SOD_Staff_Availability_Form {
    public function __construct() {
        add_shortcode('sod_staff_availability_form', array($this, 'render_availability_form'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_save_staff_availability', array($this, 'save_staff_availability'));
    }

    public function enqueue_scripts() {
        wp_enqueue_script('sod-availability-script', plugins_url('assets/js/staff-availability.js', __FILE__), array('jquery'), null, true);
        wp_localize_script('sod-availability-script', 'sodAvailability', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sod_availability_nonce')
        ));
    }

    // Render the availability form
    public function render_availability_form() {
        if (!is_user_logged_in() || !current_user_can('manage_availability')) {
            return __('You do not have permission to view this page.', 'textdomain');
        }

        ob_start();
        ?>
        <form id="sod-staff-availability-form">
            <h3><?php _e('Add Availability', 'textdomain'); ?></h3>
            <div id="availability-slots">
                <div class="availability-slot">
                    <label><?php _e('Day of the Week:', 'textdomain'); ?></label>
                    <select name="availability_day[]">
                        <option value="Monday">Monday</option>
                        <!-- Add other days -->
                    </select>
                    <label><?php _e('Start Time:', 'textdomain'); ?></label>
                    <input type="time" name="availability_start[]" />
                    <label><?php _e('End Time:', 'textdomain'); ?></label>
                    <input type="time" name="availability_end[]" />
                </div>
            </div>
            <button type="button" id="add-availability-slot"><?php _e('Add Slot', 'textdomain'); ?></button>
            <button type="submit"><?php _e('Save Availability', 'textdomain'); ?></button>
            <?php wp_nonce_field('sod_availability_nonce_action', 'sod_availability_nonce'); ?>
        </form>
        <?php
        return ob_get_clean();
    }

    // Handle form submission
    public function save_staff_availability() {
        check_ajax_referer('sod_availability_nonce_action', 'nonce');

        // Ensure user is logged in and is a staff member
        if (!is_user_logged_in() || !current_user_can('manage_availability')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'textdomain'));
        }

        global $wpdb;
        $user_id = get_current_user_id();

        // Process submitted data
        if (isset($_POST['availability_day']) && is_array($_POST['availability_day'])) {
            // Remove existing availability for the staff member
            $wpdb->delete($wpdb->prefix . 'sod_staff_availability', ['staff_id' => $user_id], ['%d']);

            $days = $_POST['availability_day'];
            $start_times = $_POST['availability_start'];
            $end_times = $_POST['availability_end'];

            foreach ($days as $index => $day) {
                $wpdb->insert($wpdb->prefix . 'sod_staff_availability', [
                    'staff_id' => $user_id,
                    'day_of_week' => sanitize_text_field($day),
                    'start_time' => sanitize_text_field($start_times[$index]),
                    'end_time' => sanitize_text_field($end_times[$index])
                ], [
                    '%d', '%s', '%s', '%s'
                ]);
            }
            wp_send_json_success(__('Availability updated successfully.', 'textdomain'));
        }

        wp_send_json_error(__('Failed to update availability.', 'textdomain'));
    }
}

new SOD_Staff_Availability_Form();