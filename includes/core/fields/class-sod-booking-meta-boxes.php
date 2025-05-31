<?php
if (!defined('ABSPATH')) {
    exit;
}

class SOD_Booking_Meta_Boxes {
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_booking_meta_boxes'));
        add_action('save_post_sod_booking', array($this, 'save_booking_meta_boxes'));
        add_action('save_post_sod_booking', array($this, 'handle_booking_status_change'), 10, 3);
        
        // Add admin scripts for calendar/timeslot functionality
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Enqueue necessary scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'post.php' && $hook !== 'post-new.php') return;
        
        $screen = get_current_screen();
        if ($screen->post_type !== 'sod_booking') return;

        // Enqueue jQuery UI Datepicker
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui-css', '//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
        
        // Custom script for timeslot handling
        wp_enqueue_script(
            'sod-booking-admin',
            plugins_url('/js/sod-booking-admin.js', __FILE__),
            array('jquery', 'jquery-ui-datepicker'),
            '1.0.0',
            true
        );

        wp_localize_script('sod-booking-admin', 'sodBookingAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sod_booking_admin_nonce'),
        ));
    }

    public function add_booking_meta_boxes() {
        add_meta_box(
            'sod_booking_details',
            __('Booking Details', 'spark-of-divine-scheduler'),
            array($this, 'render_booking_details'),
            'sod_booking',
            'normal',
            'default'
        );
    }

    public function render_booking_details($post) {
        $booking_data = $this->get_booking_meta($post->ID);
        wp_nonce_field('sod_booking_meta_box', 'sod_booking_meta_box_nonce');
        ?>
        <div class="sod-booking-meta">
            <p>
                <label for="sod_service_id"><?php _e('Service:', 'spark-of-divine-scheduler'); ?></label>
                <select name="sod_service_id" id="sod_service_id" class="widefat">
                    <option value=""><?php _e('Select a service', 'spark-of-divine-scheduler'); ?></option>
                    <?php 
                    $services = get_posts(array(
                        'post_type' => ['sod_service', 'sod_event', 'sod_class'],
                        'posts_per_page' => -1,
                        'orderby' => 'title',
                        'order' => 'ASC'
                    ));
                    foreach ($services as $service) : ?>
                        <option value="<?php echo esc_attr($service->ID); ?>" <?php selected($booking_data['service_id'], $service->ID); ?>>
                            <?php echo esc_html($service->post_title); ?> (<?php echo esc_html($service->post_type); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
            
            <p>
                <label for="sod_staff_id"><?php _e('Staff:', 'spark-of-divine-scheduler'); ?></label>
                <select name="sod_staff_id" id="sod_staff_id" class="widefat">
                    <option value=""><?php _e('Select a staff member', 'spark-of-divine-scheduler'); ?></option>
                    <?php 
                    $staff = get_posts(array(
                        'post_type' => 'sod_staff',
                        'posts_per_page' => -1,
                        'orderby' => 'title',
                        'order' => 'ASC'
                    ));
                    foreach ($staff as $member) : ?>
                        <option value="<?php echo esc_attr($member->ID); ?>" <?php selected($booking_data['staff_id'], $member->ID); ?>>
                            <?php echo esc_html($member->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p>
                <label><?php _e('Booking Schedule:', 'spark-of-divine-scheduler'); ?></label>
                <div class="sod-schedule-fields">
                    <input type="text" 
                           name="sod_booking_date" 
                           id="sod_booking_date" 
                           class="sod-datepicker" 
                           value="<?php echo esc_attr($booking_data['date']); ?>" 
                           placeholder="Select Date" />
                    
                    <select name="sod_timeslot" id="sod_timeslot" class="sod-timeslot-select">
                        <option value=""><?php _e('Select Timeslot', 'spark-of-divine-scheduler'); ?></option>
                        <?php 
                        if ($booking_data['start_time']) {
                            $selected_time = date('H:i', strtotime($booking_data['start_time']));
                            echo '<option value="' . esc_attr($selected_time) . '" selected>' . 
                                 esc_html(date_i18n(get_option('time_format'), strtotime($booking_data['start_time']))) . 
                                 '</option>';
                        }
                        ?>
                    </select>
                    
                    <input type="number" 
                           name="sod_duration" 
                           id="sod_duration" 
                           value="<?php echo esc_attr($booking_data['duration']); ?>" 
                           min="15" 
                           step="15" 
                           style="width: 80px;" /> 
                    <span><?php _e('minutes', 'spark-of-divine-scheduler'); ?></span>
                </div>
            </p>

            <p>
                <label for="sod_status"><?php _e('Status:', 'spark-of-divine-scheduler'); ?></label>
                <select name="sod_status" id="sod_status" class="widefat">
                    <?php 
                    $statuses = array(
                        'pending' => __('Pending', 'spark-of-divine-scheduler'),
                        'deposit_paid' => __('Deposit Paid', 'spark-of-divine-scheduler'),
                        'confirmed' => __('Confirmed', 'spark-of-divine-scheduler'),
                        'completed' => __('Completed', 'spark-of-divine-scheduler'),
                        'cancelled' => __('Cancelled', 'spark-of-divine-scheduler'),
                        'no-show' => __('No Show', 'spark-of-divine-scheduler')
                    );
                    foreach ($statuses as $value => $label) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($booking_data['status'], $value); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p>
                <label for="sod_customer_id"><?php _e('Customer:', 'spark-of-divine-scheduler'); ?></label>
                <select name="sod_customer_id" id="sod_customer_id" class="widefat">
                    <option value=""><?php _e('Select a customer', 'spark-of-divine-scheduler'); ?></option>
                    <?php 
                    $users = get_users(array('role__in' => array('customer', 'subscriber')));
                    foreach ($users as $user) : ?>
                        <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($booking_data['customer_id'], $user->ID); ?>>
                            <?php echo esc_html($user->display_name) . ' (' . $user->user_email . ')'; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p>
                <label for="sod_booking_notes"><?php _e('Notes:', 'spark-of-divine-scheduler'); ?></label>
                <textarea name="sod_booking_notes" id="sod_booking_notes" class="widefat" rows="3"><?php echo esc_textarea($booking_data['notes']); ?></textarea>
            </p>

            <?php if ($booking_data['day_schedule']) : ?>
            <p>
                <label><?php _e('Day Schedule:', 'spark-of-divine-scheduler'); ?></label>
                <div class="sod-day-schedule"><?php echo esc_html($booking_data['day_schedule']); ?></div>
            </p>
            <?php endif; ?>
        </div>
        <?php
    }

    private function get_booking_meta($post_id) {
        $start_time = get_post_meta($post_id, 'sod_start_time', true);
        return array(
            'service_id' => get_post_meta($post_id, 'sod_service_id', true),
            'staff_id' => get_post_meta($post_id, 'sod_staff_id', true),
            'start_time' => $start_time,
            'date' => $start_time ? date('Y-m-d', strtotime($start_time)) : '',
            'duration' => get_post_meta($post_id, 'sod_duration', true) ?: 60,
            'status' => get_post_meta($post_id, 'sod_status', true) ?: 'pending',
            'customer_id' => get_post_meta($post_id, 'sod_customer_id', true),
            'notes' => get_post_meta($post_id, 'sod_booking_notes', true),
            'day_schedule' => get_post_meta($post_id, 'sod_day_schedule', true)
        );
    }

    public function save_booking_meta_boxes($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;
        if (!isset($_POST['sod_booking_meta_box_nonce']) || 
            !wp_verify_nonce($_POST['sod_booking_meta_box_nonce'], 'sod_booking_meta_box')) return;

        $old_status = get_post_meta($post_id, 'sod_status', true);
        $old_start_time = get_post_meta($post_id, 'sod_start_time', true);

        $service_id = isset($_POST['sod_service_id']) ? intval($_POST['sod_service_id']) : 0;
        $staff_id = isset($_POST['sod_staff_id']) ? intval($_POST['sod_staff_id']) : 0;
        $date = isset($_POST['sod_booking_date']) ? sanitize_text_field($_POST['sod_booking_date']) : '';
        $timeslot = isset($_POST['sod_timeslot']) ? sanitize_text_field($_POST['sod_timeslot']) : '';
        $duration = isset($_POST['sod_duration']) ? intval($_POST['sod_duration']) : 60;
        $status = isset($_POST['sod_status']) ? sanitize_text_field($_POST['sod_status']) : 'pending';
        $customer_id = isset($_POST['sod_customer_id']) ? intval($_POST['sod_customer_id']) : 0;
        $notes = isset($_POST['sod_booking_notes']) ? sanitize_textarea_field($_POST['sod_booking_notes']) : '';

        // Combine date and timeslot into start_time
        $start_time = $date && $timeslot ? "$date $timeslot" : ($date ? $date : '');

        // Validate timeslot change
        if ($start_time && $start_time !== $old_start_time && $service_id && $staff_id) {
            $validator = SOD_Booking_Validator::getInstance();
            $validation = $validator->validate_booking_request(null, $service_id, $staff_id, $start_time);
            if (!$validation['valid']) {
                wp_die(__('Invalid timeslot: ') . $validation['message']);
            }
        }

        update_post_meta($post_id, 'sod_service_id', $service_id);
        update_post_meta($post_id, 'sod_staff_id', $staff_id);
        update_post_meta($post_id, 'sod_start_time', $start_time);
        update_post_meta($post_id, 'sod_duration', $duration);
        update_post_meta($post_id, 'sod_status', $status);
        update_post_meta($post_id, 'sod_customer_id', $customer_id);
        update_post_meta($post_id, 'sod_booking_notes', $notes);

        // Update end_time
        if ($start_time) {
            $start_datetime = new DateTime($start_time);
            $end_datetime = clone $start_datetime;
            $end_datetime->modify("+{$duration} minutes");
            update_post_meta($post_id, 'sod_end_time', $end_datetime->format('Y-m-d H:i:s'));
        }

        // Update post title
        $service_title = $service_id ? get_the_title($service_id) : 'Service';
        $staff_title = $staff_id ? get_the_title($staff_id) : 'Staff';
        $date_str = $start_time ? date_i18n('M j, Y \a\t g:i A', strtotime($start_time)) : 'TBD';
        
        wp_update_post(array(
            'ID' => $post_id,
            'post_title' => sprintf(
                __('Booking for %s with %s on %s', 'spark-of-divine-scheduler'),
                $service_title,
                $staff_title,
                $date_str
            )
        ));
    }

    public function handle_booking_status_change($post_id, $post, $update) {
        if ($post->post_type !== 'sod_booking') return;

        $new_status = get_post_meta($post_id, 'sod_status', true);
        $old_status = get_post_meta($post_id, '_previous_status', true);
        $start_time = get_post_meta($post_id, 'sod_start_time', true);
        $old_start_time = get_post_meta($post_id, '_previous_start_time', true);

        // Store current values as previous for next save
        update_post_meta($post_id, '_previous_status', $new_status);
        update_post_meta($post_id, '_previous_start_time', $start_time);

        $changed = false;
        $change_type = '';

        if ($update && $old_status && $new_status !== $old_status) {
            $changed = true;
            $change_type = 'status';
        }
        if ($update && $old_start_time && $start_time !== $old_start_time) {
            $changed = true;
            $change_type = $change_type ? 'both' : 'timeslot';
        }

        if ($changed) {
            $this->send_notification_emails($post_id, $change_type, $old_status, $new_status, $old_start_time, $start_time);
            
            switch ($new_status) {
                case 'confirmed':
                    do_action('spark_divine_booking_confirmed', $post_id);
                    break;
                case 'cancelled':
                    do_action('spark_divine_booking_canceled', $post_id);
                    break;
                case 'deposit_paid':
                case 'completed':
                    do_action('spark_divine_booking_paid', $post_id);
                    break;
            }
        }
    }

    private function send_notification_emails($booking_id, $change_type, $old_status, $new_status, $old_start_time, $new_start_time) {
        $service_id = get_post_meta($booking_id, 'sod_service_id', true);
        $staff_id = get_post_meta($booking_id, 'sod_staff_id', true);
        $customer_id = get_post_meta($booking_id, 'sod_customer_id', true);
        $duration = get_post_meta($booking_id, 'sod_duration', true);

        $service_title = get_the_title($service_id);
        $staff_title = get_the_title($staff_id);
        $staff_email = ($staff_user_id = get_post_meta($staff_id, 'sod_user_id', true)) ? 
            get_userdata($staff_user_id)->user_email : '';
        $customer_email = $customer_id ? get_userdata($customer_id)->user_email : '';
        $admin_email = get_option('admin_email');

        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        $date_format = get_option('date_format');
        $time_format = get_option('time_format');

        $subject_prefix = __('Booking Update: %s with %s', 'spark-of-divine-scheduler');
        $subject = sprintf($subject_prefix, $service_title, $staff_title);

        // Prepare message based on change type
        $message_template = '';
        $customer_message_template = '';

        switch ($change_type) {
            case 'status':
                $message_template = __("Booking status changed from %s to %s.\n\nService: %s\nStaff: %s\nDate: %s\nTime: %s\nDuration: %s minutes\n\nView booking: %s", 'spark-of-divine-scheduler');
                $customer_message_template = __("Your booking status has changed from %s to %s.\n\nService: %s\nStaff: %s\nDate: %s\nTime: %s\nDuration: %s minutes", 'spark-of-divine-scheduler');
                break;
            case 'timeslot':
                $message_template = __("Booking timeslot changed from %s to %s.\n\nService: %s\nStaff: %s\nDate: %s\nDuration: %s minutes\n\nView booking: %s", 'spark-of-divine-scheduler');
                $customer_message_template = __("Your booking time has changed from %s to %s.\n\nService: %s\nStaff: %s\nDate: %s\nDuration: %s minutes", 'spark-of-divine-scheduler');
                break;
            case 'both':
                $message_template = __("Booking updated - Status changed from %s to %s and timeslot from %s to %s.\n\nService: %s\nStaff: %s\nDate: %s\nDuration: %s minutes\n\nView booking: %s", 'spark-of-divine-scheduler');
                $customer_message_template = __("Your booking has been updated - Status changed from %s to %s and time from %s to %s.\n\nService: %s\nStaff: %s\nDate: %s\nDuration: %s minutes", 'spark-of-divine-scheduler');
                break;
        }

        $formatted_date = $new_start_time ? date_i18n($date_format, strtotime($new_start_time)) : '';
        $formatted_time = $new_start_time ? date_i18n($time_format, strtotime($new_start_time)) : '';
        $old_formatted_time = $old_start_time ? date_i18n($time_format, strtotime($old_start_time)) : '';

        // Admin/Staff message
        $body = sprintf(
            $message_template,
            $old_status ?: 'N/A',
            $new_status,
            $old_formatted_time,
            $formatted_time,
            $service_title,
            $staff_title,
            $formatted_date,
            $duration,
            admin_url('post.php?post=' . $booking_id . '&action=edit')
        );

        // Customer message
        $customer_body = sprintf(
            $customer_message_template,
            $old_status ?: 'N/A',
            $new_status,
            $old_formatted_time,
            $formatted_time,
            $service_title,
            $staff_title,
            $formatted_date,
            $duration
        );

        // Send emails
        wp_mail($admin_email, $subject, $body, $headers);
        if ($staff_email) {
            wp_mail($staff_email, $subject, $body, $headers);
        }
        if ($customer_email) {
            wp_mail($customer_email, $subject, $customer_body, $headers);
        }

        error_log("Sent booking update emails for booking $booking_id - Change type: $change_type");
    }
}

new SOD_Booking_Meta_Boxes();