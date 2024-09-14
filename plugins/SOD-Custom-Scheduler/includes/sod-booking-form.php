<?php
// File: includes/class-sod-booking-form.php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class SOD_Booking_Form {
    public function __construct() {
        // Register shortcode to trigger the modal
        add_shortcode('sod_booking_form', array($this, 'render_booking_button'));

        // Enqueue necessary scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Handle the AJAX request for booking submission
        add_action('wp_ajax_sod_submit_booking', array($this, 'handle_booking_submission'));
        add_action('wp_ajax_nopriv_sod_submit_booking', array($this, 'handle_booking_submission'));

        // Register AJAX handlers for fetching services, staff, and timeslots
        add_action('wp_ajax_get_services', array($this, 'get_services'));
        add_action('wp_ajax_nopriv_get_services', array($this, 'get_services'));

        add_action('wp_ajax_get_staff', array($this, 'get_staff'));
        add_action('wp_ajax_nopriv_get_staff', array($this, 'get_staff'));

        add_action('wp_ajax_get_timeslots', array($this, 'get_timeslots'));
        add_action('wp_ajax_nopriv_get_timeslots', array($this, 'get_timeslots'));
    }

    // Render the button that opens the booking modal
    public function render_booking_button() {
        ob_start();
        ?>
        <button id="openModal">Book Now</button>
        <div id="bookingModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <div id="bookingFormContainer">
                    <!-- Booking form -->
                    <form id="sod-booking-form">
                        <div class="form-group">
                            <label for="category">Category:</label>
                            <select id="category" name="category">
                                <option value="">Select Category</option>
                                <!-- Categories will be populated dynamically -->
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="service">Service:</label>
                            <select id="service" name="service">
                                <option value="">Select Service</option>
                                <!-- Services will be populated here based on the selected category -->
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="staff">Staff:</label>
                            <select id="staff" name="staff">
                                <option value="">Select Staff</option>
                                <!-- Staff members will be populated here based on the selected service -->
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="timeslot">Available Times:</label>
                            <select id="timeslot" name="timeslot">
                                <option value="">Select Time Slot</option>
                                <!-- Available time slots will be populated here based on the selected staff -->
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="duration">Duration:</label>
                            <select id="duration" name="duration">
                                <option value="15">15 Minutes</option>
                                <option value="30">30 Minutes</option>
                                <!-- Additional options as needed -->
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="price">Price:</label>
                            <input type="text" id="price" name="price" readonly>
                        </div>

                        <button type="submit" id="book-now">Book Now</button>
                    </form>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // Enqueue scripts and styles
    public function enqueue_scripts() {
        wp_enqueue_script('sod-booking-form', plugin_dir_url(__FILE__) . 'js/sod-booking-form.js', array('jquery'), '1.0', true);

        // Localize script to pass AJAX URL and nonce
        wp_localize_script('sod-booking-form', 'sodBooking', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sod_booking_nonce'),
        ));
    }

    // Handle the booking submission via AJAX
    public function handle_booking_submission() {
        check_ajax_referer('sod_booking_nonce', 'nonce');

        // Process the booking data
        $service_id = isset($_POST['service_id']) ? sanitize_text_field($_POST['service_id']) : '';
        $staff_id = isset($_POST['staff_id']) ? sanitize_text_field($_POST['staff_id']) : '';
        $timeslot = isset($_POST['timeslot']) ? sanitize_text_field($_POST['timeslot']) : '';
        $duration = isset($_POST['duration']) ? sanitize_text_field($_POST['duration']) : '';

        // Here you would use your booking handler to create the booking
        $result = SOD_Booking_Handler::get_instance()->create_booking(array(
            'serviceId' => $service_id,
            'staffId' => $staff_id,
            'start' => $timeslot,
            'duration' => $duration,
        ));

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        } else {
            wp_send_json_success(array('message' => 'Booking successful!'));
        }
    }

    // Fetch services based on the selected category
    public function get_services() {
        check_ajax_referer('sod_booking_nonce', 'nonce');

        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;

        // Fetch services from the database based on category_id
        global $wpdb;
        $services = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sod_services WHERE category_id = %d",
            $category_id
        ));

        wp_send_json_success($services);
    }

    // Fetch staff based on the selected service
    public function get_staff() {
        check_ajax_referer('sod_booking_nonce', 'nonce');

        $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;

        // Fetch staff from the database based on service_id
        global $wpdb;
        $staff = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sod_staff WHERE service_id = %d",
            $service_id
        ));

        wp_send_json_success($staff);
    }

    // Fetch available timeslots for the selected staff
    public function get_timeslots() {
        check_ajax_referer('sod_booking_nonce', 'nonce');

        $staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;

        // Fetch available timeslots for the staff member
        global $wpdb;
        $timeslots = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sod_staff_availability WHERE staff_id = %d",
            $staff_id
        ));

        wp_send_json_success($timeslots);
    }
}

new SOD_Booking_Form();