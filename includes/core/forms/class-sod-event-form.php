<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class SOD_Event_Form
 *
 * This class renders the events form and registers AJAX handlers
 * for fetching event data, event staff, and event availability,
 * and for handling event form submissions.
 *
 * @package SparkOfDivineScheduler
 * @since 2.0
 */
class SOD_Event_Form {

    public function __construct() {
        // Register shortcode to render the event form.
        add_shortcode( 'sod_event_form', array( $this, 'render_event_form' ) );

        // Enqueue necessary scripts and styles.
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        // Handle the AJAX request for event submission.
        add_action( 'wp_ajax_sod_submit_event', array( $this, 'handle_event_submission' ) );
        add_action( 'wp_ajax_nopriv_sod_submit_event', array( $this, 'handle_event_submission' ) );

        // Register AJAX handlers for fetching events, staff, and event availability.
        add_action( 'wp_ajax_sod_get_events', array( $this, 'get_events' ) );
        add_action( 'wp_ajax_nopriv_sod_get_events', array( $this, 'get_events' ) );

        add_action( 'wp_ajax_sod_get_event_staff', array( $this, 'get_event_staff' ) );
        add_action( 'wp_ajax_nopriv_sod_get_event_staff', array( $this, 'get_event_staff' ) );

        add_action( 'wp_ajax_sod_get_event_availability', array( $this, 'get_event_availability' ) );
        add_action( 'wp_ajax_nopriv_sod_get_event_availability', array( $this, 'get_event_availability' ) );
    }

    /**
     * Enqueue CSS/JS for the event form.
     */
    public function enqueue_scripts() {
        // Determine the plugin's root directory and URL.
        $plugin_root = plugin_dir_path( dirname( __FILE__, 2 ) ); // Up two levels.
        $plugin_url  = plugin_dir_url( dirname( __FILE__, 2 ) );

        $css_path = $plugin_root . 'assets/css/sod-event-form.css';
        $css_url  = $plugin_url . 'assets/css/sod-event-form.css';

        $js_path  = $plugin_root . 'assets/js/sod-event-form.js';
        $js_url   = $plugin_url . 'assets/js/sod-event-form.js';

        // Enqueue CSS if it exists.
        if ( file_exists( $css_path ) ) {
            wp_enqueue_style(
                'sod-event-form',
                $css_url,
                array(),
                filemtime( $css_path )
            );
        }

        // Enqueue JS if it exists.
        if ( file_exists( $js_path ) ) {
            wp_enqueue_script(
                'sod-event-form',
                $js_url,
                array( 'jquery' ),
                filemtime( $js_path ),
                true
            );

            // Localize settings for the event form.
            wp_localize_script( 'sod-event-form', 'sodEvent', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'sod_event_nonce' ),
                'texts'    => array(
                    'selectEvent'       => __( 'Select Event', 'spark-of-divine-scheduler' ),
                    'selectStaff'       => __( 'Select Staff', 'spark-of-divine-scheduler' ),
                    'selectAvailability'=> __( 'Select Availability', 'spark-of-divine-scheduler' ),
                    'pleaseSelect'      => __( 'Please Select', 'spark-of-divine-scheduler' ),
                )
            ));
        }
    }

    /**
     * Render the event form.
     *
     * @return string The HTML output for the form.
     */
    public function render_event_form() {
        ob_start();
        ?>
        <button id="openEventModal"><?php _e( 'Create Event', 'spark-of-divine-scheduler' ); ?></button>
        <div id="eventModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h3><?php _e( 'Event Booking', 'spark-of-divine-scheduler' ); ?></h3>
                <form id="sod-event-form">
                    <div class="form-group">
                        <label for="event"><?php _e( 'Event:', 'spark-of-divine-scheduler' ); ?></label>
                        <select id="event" name="event" required>
                            <option value=""><?php _e( 'Select Event', 'spark-of-divine-scheduler' ); ?></option>
                            <!-- Options will be populated via AJAX from the 'sod_event' post type -->
                        </select>
                        <div class="error-message"></div>
                    </div>
                    <div class="form-group">
                        <label for="event_staff"><?php _e( 'Staff:', 'spark-of-divine-scheduler' ); ?></label>
                        <select id="event_staff" name="event_staff" required>
                            <option value=""><?php _e( 'Select Staff', 'spark-of-divine-scheduler' ); ?></option>
                            <!-- Options will be populated via AJAX from the 'sod_staff' post type -->
                        </select>
                        <div class="error-message"></div>
                    </div>
                    <div class="form-group">
                        <label for="availability"><?php _e( 'Availability:', 'spark-of-divine-scheduler' ); ?></label>
                        <select id="availability" name="availability" required>
                            <option value=""><?php _e( 'Select Availability', 'spark-of-divine-scheduler' ); ?></option>
                            <!-- Options will be populated via AJAX from a dedicated event availability source -->
                        </select>
                        <div class="error-message"></div>
                    </div>
                    <button type="submit" id="submitEvent"><?php _e( 'Submit Event', 'spark-of-divine-scheduler' ); ?></button>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle the event form submission via AJAX.
     */
    public function handle_event_submission() {
        check_ajax_referer( 'sod_event_nonce', 'nonce' );

        $event_id     = isset( $_POST['event'] ) ? intval( $_POST['event'] ) : 0;
        $staff_id     = isset( $_POST['event_staff'] ) ? intval( $_POST['event_staff'] ) : 0;
        $availability = isset( $_POST['availability'] ) ? sanitize_text_field( $_POST['availability'] ) : '';

        if ( ! $event_id || ! $staff_id || ! $availability ) {
            wp_send_json_error( array( 'message' => __( 'Missing required fields.', 'spark-of-divine-scheduler' ) ) );
        }

        // Process the event booking.
        // This is where you would call your event handler to create or update an event.
        // For example:
        $result = SOD_Event_Handler::get_instance()->create_event( array(
            'eventId'      => $event_id,
            'staffId'      => $staff_id,
            'availability' => $availability,
        ) );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        } else {
            wp_send_json_success( array( 'message' => __( 'Event successfully created!', 'spark-of-divine-scheduler' ) ) );
        }
    }

    /**
     * AJAX handler to fetch events from the 'sod_event' post type.
     */
    public function get_events() {
        check_ajax_referer( 'sod_event_nonce', 'nonce' );

        $args = array(
            'post_type'      => 'sod_event',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        );

        $query = new WP_Query( $args );
        $data  = array();

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $data[] = array(
                    'id'    => get_the_ID(),
                    'title' => get_the_title(),
                );
            }
            wp_reset_postdata();
        }

        wp_send_json_success( $data );
    }

    /**
     * AJAX handler to fetch staff for events.
     */
    public function get_event_staff() {
        check_ajax_referer( 'sod_event_nonce', 'nonce' );

        $args = array(
            'post_type'      => 'sod_staff',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        );

        $query = new WP_Query( $args );
        $data  = array();

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $data[] = array(
                    'id'    => get_the_ID(),
                    'title' => get_the_title(),
                );
            }
            wp_reset_postdata();
        }

        wp_send_json_success( $data );
    }

    /**
     * AJAX handler to fetch event availability.
     *
     * This method should pull data from a dedicated events availability source
     * (e.g. a custom table or post meta on the 'sod_event' post type) that is distinct
     * from staff availability.
     */
    public function get_event_availability() {
        check_ajax_referer( 'sod_event_nonce', 'nonce' );

        global $wpdb;
        $table = $wpdb->prefix . 'sod_event_availability';

        // Example: Retrieve all availability slots. You might filter by date, event, or staff.
        $availability = $wpdb->get_results( "SELECT * FROM $table ORDER BY slot_start ASC", ARRAY_A );

        if ( $wpdb->last_error ) {
            wp_send_json_error( array( 'message' => $wpdb->last_error ) );
        }

        wp_send_json_success( $availability );
    }
}

new SOD_Event_Form();