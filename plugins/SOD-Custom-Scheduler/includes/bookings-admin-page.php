<?php
if (!defined('ABSPATH')) {
    exit;
}
class SOD_Bookings_Admin_Page {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_get_booking_details', 'sod_get_booking_details');
        add_action('wp_ajax_update_booking', 'sod_update_booking');
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
        $bookings = $this->get_bookings();
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
                            <td><?php echo esc_html($booking->ID); ?></td>
                            <td><?php echo esc_html(get_the_title($booking->service_id)); ?></td>
                            <td><?php echo esc_html(get_the_title($booking->staff_id)); ?></td>
                            <td><?php echo esc_html(get_userdata($booking->customer_id)->display_name); ?></td>
                            <td><?php echo esc_html(date('Y-m-d H:i', strtotime($booking->start_time))); ?></td>
                            <td><?php echo esc_html($booking->duration); ?> minutes</td>
                            <td><?php echo esc_html($booking->status); ?></td>
                            <td>
                                <button class="button edit-booking" data-id="<?php echo esc_attr($booking->ID); ?>">Edit</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function get_bookings() {
        $args = array(
            'post_type' => 'booking',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'status',
                    'value' => array('pending', 'confirmed', 'completed'),
                    'compare' => 'IN'
                )
            )
        );
        $bookings = get_posts($args);
        return array_map(function($post) {
            $post->service_id = get_post_meta($post->ID, 'service_id', true);
            $post->staff_id = get_post_meta($post->ID, 'staff_id', true);
            $post->start_time = get_post_meta($post->ID, 'start_time', true);
            $post->duration = get_post_meta($post->ID, 'duration', true);
            $post->customer_id = get_post_meta($post->ID, 'customer_id', true);
            $post->status = get_post_meta($post->ID, 'status', true);
            return $post;
        }, $bookings);
    }
}
function sod_get_booking_details() {
    check_ajax_referer('sod_admin_bookings', 'nonce');
    
    $booking_id = intval($_POST['booking_id']);
    $booking = get_post($booking_id);
    
    if (!$booking || $booking->post_type !== 'booking') {
        wp_send_json_error(array('message' => 'Booking not found'));
    }
    
    $booking_data = array(
        'id' => $booking->ID,
        'service_id' => get_post_meta($booking->ID, 'service_id', true),
        'staff_id' => get_post_meta($booking->ID, 'staff_id', true),
        'start_time' => get_post_meta($booking->ID, 'start_time', true),
        'duration' => get_post_meta($booking->ID, 'duration', true),
        'customer_id' => get_post_meta($booking->ID, 'customer_id', true),
        'status' => get_post_meta($booking->ID, 'status', true)
    );
    
    wp_send_json_success($booking_data);
}

function sod_update_booking() {
    check_ajax_referer('sod_admin_bookings', 'nonce');
    
    $booking_id = intval($_POST['booking_id']);
    $booking_data = $_POST['booking_data'];
    
    // Validate and sanitize input data
    $sanitized_data = array(
        'service_id' => intval($booking_data['service_id']),
        'staff_id' => intval($booking_data['staff_id']),
        'start_time' => sanitize_text_field($booking_data['start_time']),
        'duration' => intval($booking_data['duration']),
        'status' => sanitize_text_field($booking_data['status'])
    );
    
    // Update booking
    foreach ($sanitized_data as $key => $value) {
        update_post_meta($booking_id, $key, $value);
    }
    
    // Trigger booking updated action
    do_action('spark_divine_booking_updated', $booking_id);
    
    wp_send_json_success(array('message' => 'Booking updated successfully'));
}

new SOD_Bookings_Admin_Page();
?>