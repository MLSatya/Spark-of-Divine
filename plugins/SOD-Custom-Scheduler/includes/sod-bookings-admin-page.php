<?php
if (!defined('ABSPATH')) {
    exit;
}

class SOD_Bookings_Admin_Page {
    private $db_access;

    public function __construct($db_access) {
        $this->db_access = $db_access;

        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_get_booking_details', array($this, 'get_booking_details'));
        add_action('wp_ajax_update_booking', array($this, 'update_booking'));
    }

    public function add_admin_menu() {
        add_menu_page(
            'Manage Bookings',
            'Bookings',
            'manage_options',
            'sod-bookings',
            array($this, 'display_bookings_page'),
            'dashicons-calendar-alt',
            6
        );
    }

    public function enqueue_admin_scripts($hook) {
        if ('toplevel_page_sod-bookings' !== $hook) {
            return;
        }
        wp_enqueue_script('sod-admin-script', plugin_dir_url(__FILE__) . 'js/admin-bookings.js', array('jquery'), '1.0', true);
        wp_localize_script('sod-admin-script', 'sodBookings', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sod_admin_bookings')
        ));
    }

    public function display_bookings_page() {
        $bookings = $this->db_access->getAllBookings(); // Fetch bookings using the db_access class
        ?>
        <div class="wrap">
            <h1>Manage Bookings</h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Service</th>
                        <th>Staff</th>
                        <th>Customer</th>
                        <th>Date & Time</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking) : ?>
                        <tr>
                            <td><?php echo esc_html($booking->booking_id); ?></td>
                            <td><?php echo esc_html($this->db_access->getService($booking->service_id)->name); ?></td>
                            <td><?php echo esc_html($this->db_access->getStaff($booking->staff_id)->name); ?></td>
                            <td><?php echo esc_html(get_userdata($booking->user_id)->display_name); ?></td>
                            <td><?php echo esc_html(date('Y-m-d H:i', strtotime($booking->date . ' ' . $booking->time))); ?></td>
                            <td><?php echo esc_html($booking->duration); ?> minutes</td>
                            <td><?php echo esc_html($booking->status); ?></td>
                            <td>
                                <button class="button edit-booking" data-id="<?php echo esc_attr($booking->booking_id); ?>">Edit</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    // AJAX handler for getting booking details
    public function get_booking_details() {
        check_ajax_referer('sod_admin_bookings', 'nonce');

        $booking_id = intval($_POST['booking_id']);
        $booking = $this->db_access->getBooking($booking_id); // Fetch booking using db_access

        if (!$booking) {
            wp_send_json_error(array('message' => 'Booking not found'));
        }

        $booking_data = array(
            'id' => $booking->booking_id,
            'service_id' => $booking->service_id,
            'staff_id' => $booking->staff_id,
            'date' => $booking->date,
            'time' => $booking->time,
            'duration' => $booking->duration,
            'status' => $booking->status,
            'user_id' => $booking->user_id
        );

        wp_send_json_success($booking_data);
    }

    // AJAX handler for updating booking
    public function update_booking() {
        check_ajax_referer('sod_admin_bookings', 'nonce');

        $booking_id = intval($_POST['booking_id']);
        $booking_data = $_POST['booking_data'];

        // Validate and sanitize input data
        $sanitized_data = array(
            'service_id' => intval($booking_data['service_id']),
            'staff_id' => intval($booking_data['staff_id']),
            'date' => sanitize_text_field($booking_data['date']),
            'time' => sanitize_text_field($booking_data['time']),
            'duration' => intval($booking_data['duration']),
            'status' => sanitize_text_field($booking_data['status'])
        );

        // Update booking
        $updated = $this->db_access->updateBooking(
            $booking_id,
            $sanitized_data['status'],
            null, // You can pass null or empty string if not updating payment_method
            $sanitized_data
        );

        if ($updated) {
            do_action('spark_divine_booking_updated', $booking_id);
            wp_send_json_success(array('message' => 'Booking updated successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to update booking'));
        }
    }
}

// Instantiate the admin page with the database access class
new SOD_Bookings_Admin_Page(new SOD_DB_Access($GLOBALS['wpdb']));
?>