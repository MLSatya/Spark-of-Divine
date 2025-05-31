<?php
namespace Elementor;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class SOD_Staff_Frontend_Schedule_Widget extends Widget_Base {

    public function get_name() { return 'sod_staff_schedule'; }
    public function get_title() { return __('Staff Schedule', 'spark-of-divine-scheduler'); }
    public function get_icon() { return 'eicon-calendar'; }
    public function get_categories() { return ['spark-of-divine']; }
    public function get_script_depends() { return ['sod-staff-schedule-script']; }
    public function get_style_depends() { return ['sod-staff-schedule-style']; }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            ['label' => __('Content Settings', 'spark-of-divine-scheduler')]
        );

        $this->add_control(
            'days_to_show',
            [
                'label' => __('Days to Show', 'spark-of-divine-scheduler'),
                'type' => Controls_Manager::NUMBER,
                'default' => 7,
                'min' => 1,
                'max' => 30,
                'step' => 1,
            ]
        );

        $this->add_control(
            'title',
            [
                'label' => __('Title', 'spark-of-divine-scheduler'),
                'type' => Controls_Manager::TEXT,
                'default' => __('My Schedule', 'spark-of-divine-scheduler'),
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'section_style',
            [
                'label' => __('Style', 'spark-of-divine-scheduler'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'header_background',
            [
                'label' => __('Header Background', 'spark-of-divine-scheduler'),
                'type' => Controls_Manager::COLOR,
                'default' => '#f8f9fa',
            ]
        );

        $this->add_control(
            'row_background',
            [
                'label' => __('Row Background', 'spark-of-divine-scheduler'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
            ]
        );

        $this->add_control(
            'button_color',
            [
                'label' => __('Button Color', 'spark-of-divine-scheduler'),
                'type' => Controls_Manager::COLOR,
                'default' => '#0073aa',
            ]
        );

        $this->end_controls_section();
    }

    private function get_current_staff_id() {
        $user_id = get_current_user_id();
        error_log("Checking staff ID for user ID: " . $user_id);

        // If user is an administrator without the staff role, they shouldn't be treated as staff
        if (!$user_id) {
            error_log("No user ID found");
            return false;
        }

        // Let's check if the user has a staff record regardless of role
        global $wpdb;
        $staff_id = $wpdb->get_var($wpdb->prepare(
            "SELECT staff_id FROM {$wpdb->prefix}sod_staff WHERE user_id = %d",
            $user_id
        ));

        error_log("Database lookup for staff_id: " . ($staff_id ? $staff_id : 'None found'));

        if ($staff_id) {
            // User has a staff record in the database
            return $staff_id;
        } else {
            // No staff record found
            error_log("No staff record found for user ID: " . $user_id);
            return false;
        }
    }

    private function get_staff_availability($staff_id, $days) {
        global $wpdb;
        $start_date = current_time('Y-m-d');
        $end_date = date('Y-m-d', strtotime("+{$days} days"));

        $query = $wpdb->prepare(
            "SELECT sa.*, s.name AS service_name
             FROM {$wpdb->prefix}sod_staff_availability sa
             LEFT JOIN {$wpdb->prefix}sod_services s ON sa.service_id = s.service_id
             WHERE sa.staff_id = %d
             AND (
                 (sa.date BETWEEN %s AND %s)
                 OR (sa.recurring_type IS NOT NULL AND sa.day_of_week IS NOT NULL 
                     AND (sa.recurring_end_date IS NULL OR sa.recurring_end_date >= %s))
             )
             ORDER BY sa.date, sa.start_time ASC",
            $staff_id, $start_date, $end_date, $start_date
        );

        $availability = $wpdb->get_results($query);
        if ($wpdb->last_error) {
            error_log("Availability query error: " . $wpdb->last_error);
            return [];
        }

        $availability_by_date = [];
        $start = new \DateTime($start_date);
        $end = new \DateTime($end_date);
        $day_map = ['Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6, 'Sunday' => 7];

        foreach ($availability as $slot) {
            if ($slot->date) {
                $availability_by_date[$slot->date][] = $slot;
            } elseif ($slot->day_of_week && isset($day_map[$slot->day_of_week])) {
                $current = clone $start;
                while ($current <= $end) {
                    if ((int)$current->format('N') === $day_map[$slot->day_of_week]) {
                        if (!$slot->recurring_end_date || $current->format('Y-m-d') <= $slot->recurring_end_date) {
                            $date_key = $current->format('Y-m-d');
                            $availability_by_date[$date_key][] = $slot;
                        }
                    }
                    $current->modify('+1 day');
                }
            }
        }
        return $availability_by_date;
    }

    private function get_staff_bookings($staff_id, $days) {
        global $wpdb;
        $start_date = current_time('Y-m-d');
        $end_date = date('Y-m-d', strtotime("+{$days} days"));

        $query = $wpdb->prepare(
            "SELECT b.*, p.post_title as booking_title,
               c.meta_value as customer_name,
               phone.meta_value as customer_phone
             FROM {$wpdb->prefix}sod_bookings b
             LEFT JOIN {$wpdb->posts} p ON b.booking_id = p.ID
             LEFT JOIN {$wpdb->postmeta} c ON b.booking_id = c.post_id AND c.meta_key = 'sod_booking_customer_name'
             LEFT JOIN {$wpdb->postmeta} phone ON b.booking_id = phone.post_id AND phone.meta_key = 'sod_booking_customer_phone'
             WHERE b.staff_id = %d 
             AND DATE(b.start_time) BETWEEN %s AND %s
             ORDER BY b.start_time ASC",
            $staff_id, $start_date, $end_date
        );

        $bookings = $wpdb->get_results($query);
        if ($wpdb->last_error) {
            error_log("Booking query error: " . $wpdb->last_error);
            return [];
        }
        return $bookings;
    }

    private function get_all_staff_availability($days) {
        global $wpdb;
        $start_date = current_time('Y-m-d');
        $end_date = date('Y-m-d', strtotime("+{$days} days"));

        $query = $wpdb->prepare(
            "SELECT sa.*, s.name AS service_name, u.display_name AS staff_name
             FROM {$wpdb->prefix}sod_staff_availability sa
             LEFT JOIN {$wpdb->prefix}sod_services s ON sa.service_id = s.service_id
             LEFT JOIN {$wpdb->prefix}sod_staff st ON sa.staff_id = st.staff_id
             LEFT JOIN {$wpdb->users} u ON st.user_id = u.ID
             WHERE (
                 (sa.date BETWEEN %s AND %s)
                 OR (sa.recurring_type IS NOT NULL AND sa.day_of_week IS NOT NULL 
                     AND (sa.recurring_end_date IS NULL OR sa.recurring_end_date >= %s))
             )
             ORDER BY sa.date, sa.start_time ASC",
            $start_date, $end_date, $start_date
        );

        $availability = $wpdb->get_results($query);
        if ($wpdb->last_error) {
            error_log("All staff availability query error: " . $wpdb->last_error);
            return [];
        }

        $availability_by_date = [];
        $start = new \DateTime($start_date);
        $end = new \DateTime($end_date);
        $day_map = ['Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6, 'Sunday' => 7];

        foreach ($availability as $slot) {
            if ($slot->date) {
                $availability_by_date[$slot->date][$slot->staff_id][] = $slot;
            } elseif ($slot->day_of_week && isset($day_map[$slot->day_of_week])) {
                $current = clone $start;
                while ($current <= $end) {
                    if ((int)$current->format('N') === $day_map[$slot->day_of_week]) {
                        if (!$slot->recurring_end_date || $current->format('Y-m-d') <= $slot->recurring_end_date) {
                            $date_key = $current->format('Y-m-d');
                            $availability_by_date[$date_key][$slot->staff_id][] = $slot;
                        }
                    }
                    $current->modify('+1 day');
                }
            }
        }
        return $availability_by_date;
    }

    private function get_all_bookings($days) {
        global $wpdb;
        $start_date = current_time('Y-m-d');
        $end_date = date('Y-m-d', strtotime("+{$days} days"));

        $query = $wpdb->prepare(
            "SELECT b.*, p.post_title as booking_title, u.display_name AS staff_name,
               c.meta_value as customer_name,
               phone.meta_value as customer_phone
             FROM {$wpdb->prefix}sod_bookings b
             LEFT JOIN {$wpdb->posts} p ON b.booking_id = p.ID
             LEFT JOIN {$wpdb->prefix}sod_staff st ON b.staff_id = st.staff_id
             LEFT JOIN {$wpdb->users} u ON st.user_id = u.ID
             LEFT JOIN {$wpdb->postmeta} c ON b.booking_id = c.post_id AND c.meta_key = 'sod_booking_customer_name'
             LEFT JOIN {$wpdb->postmeta} phone ON b.booking_id = phone.post_id AND phone.meta_key = 'sod_booking_customer_phone'
             WHERE DATE(b.start_time) BETWEEN %s AND %s
             ORDER BY b.start_time ASC",
            $start_date, $end_date
        );

        $bookings = $wpdb->get_results($query);
        if ($wpdb->last_error) {
            error_log("All bookings query error: " . $wpdb->last_error);
            return [];
        }
        return $bookings;
    }

    /**
     * Get staff-specific services for filtering
     */
    private function get_staff_services($staff_id) {
        global $wpdb;
        
        // First try to get from availability table
        $services = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT sa.service_id, s.name AS service_name 
             FROM {$wpdb->prefix}sod_staff_availability sa
             LEFT JOIN {$wpdb->prefix}sod_services s ON sa.service_id = s.service_id
             WHERE sa.staff_id = %d",
            $staff_id
        ));
        
        if (empty($services)) {
            // Fallback to getting all services
            $services = $wpdb->get_results(
                "SELECT service_id, name AS service_name 
                 FROM {$wpdb->prefix}sod_services"
            );
        }
        
        return $services;
    }

    private function process_form_submission() {
        if (!isset($_POST['action'])) return;

        $action = sanitize_text_field($_POST['action']);
        switch ($action) {
            case 'confirm_booking':
                $this->confirm_booking();
                break;
            case 'reschedule_booking':
                $this->reschedule_booking();
                break;
            case 'cancel_booking':
                $this->cancel_booking();
                break;
        }
    }

    private function confirm_booking() {
        if (!wp_verify_nonce($_POST['sod_staff_confirm_booking_nonce'], 'sod_staff_confirm_booking')) {
            error_log("Nonce verification failed for confirm_booking");
            return;
        }
        global $wpdb;
        $booking_id = intval($_POST['booking_id']);
        
        // Update status in both tables
        $wpdb->update(
            "{$wpdb->prefix}sod_bookings", 
            ['status' => 'confirmed'], 
            ['booking_id' => $booking_id]
        );
        
        update_post_meta($booking_id, 'sod_booking_status', 'confirmed');
        
        $this->send_booking_confirmation_emails($booking_id);
        
        error_log("Booking $booking_id confirmed");
    }

    private function reschedule_booking() {
        if (!wp_verify_nonce($_POST['sod_staff_reschedule_booking_nonce'], 'sod_staff_reschedule_booking')) {
            error_log("Nonce verification failed for reschedule_booking");
            return;
        }
        global $wpdb;
        $booking_id = intval($_POST['booking_id']);
        $new_date = sanitize_text_field($_POST['new_date']);
        $new_time = sanitize_text_field($_POST['new_time']);
        $new_start = $new_date . ' ' . $new_time . ':00';
        
        // Get duration from post meta or default to 60 minutes
        $duration = get_post_meta($booking_id, 'sod_duration', true) ?: 60;
        $new_end = date('Y-m-d H:i:s', strtotime($new_start . ' + ' . $duration . ' minutes'));
        
        // Update in custom table
        $wpdb->update(
            "{$wpdb->prefix}sod_bookings", 
            [
                'start_time' => $new_start, 
                'end_time' => $new_end,
                'date' => $new_date,
                'status' => 'rescheduled'
            ], 
            ['booking_id' => $booking_id]
        );
        
        // Update meta values
        update_post_meta($booking_id, 'sod_booking_date', $new_date);
        update_post_meta($booking_id, 'sod_booking_start_time', $new_time);
        update_post_meta($booking_id, 'sod_booking_status', 'rescheduled');
        
        // Send notification emails
        $this->send_booking_reschedule_emails($booking_id, $new_start);
        
        error_log("Booking $booking_id rescheduled to $new_start");
    }

    private function cancel_booking() {
        if (!wp_verify_nonce($_POST['sod_staff_cancel_booking_nonce'], 'sod_staff_cancel_booking')) {
            error_log("Nonce verification failed for cancel_booking");
            return;
        }
        global $wpdb;
        $booking_id = intval($_POST['booking_id']);
        
        // Update in both places
        $wpdb->update(
            "{$wpdb->prefix}sod_bookings", 
            ['status' => 'cancelled'], 
            ['booking_id' => $booking_id]
        );
        
        update_post_meta($booking_id, 'sod_booking_status', 'cancelled');
        
        // Send cancellation emails
        $this->send_booking_cancellation_emails($booking_id);
        
        error_log("Booking $booking_id cancelled");
    }
    
    /**
     * Send booking confirmation emails to staff, admin, and customer
     */
    private function send_booking_confirmation_emails($booking_id) {
        // Get booking details
        $booking_meta = $this->get_booking_details($booking_id);
        if (!$booking_meta) return;
        
        $admin_email = get_option('admin_email');
        $staff_email = get_user_meta($booking_meta['staff_user_id'], 'user_email', true);
        $customer_email = $booking_meta['customer_email'];
        
        // Build email content
        $subject = sprintf(__('Booking Confirmed: %s on %s', 'spark-of-divine-scheduler'), 
            $booking_meta['service_name'], 
            date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking_meta['start_time']))
        );
        
        $message = sprintf(
            __("Hello %s,\n\nYour booking has been confirmed:\n\nService: %s\nStaff: %s\nDate: %s\nTime: %s\n\nThank you for booking with us.\n\nSpark of Divine", 'spark-of-divine-scheduler'),
            $booking_meta['customer_name'],
            $booking_meta['service_name'],
            $booking_meta['staff_name'],
            date_i18n(get_option('date_format'), strtotime($booking_meta['start_time'])),
            date_i18n(get_option('time_format'), strtotime($booking_meta['start_time']))
        );
        
        // Send emails
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        
        // To customer
        wp_mail($customer_email, $subject, nl2br($message), $headers);
        
        // To staff
        $staff_subject = sprintf(__('Booking Confirmed with %s on %s', 'spark-of-divine-scheduler'), 
            $booking_meta['customer_name'], 
            date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking_meta['start_time']))
        );
        
        $staff_message = sprintf(
            __("Hello %s,\n\nA booking has been confirmed:\n\nCustomer: %s\nPhone: %s\nEmail: %s\nService: %s\nDate: %s\nTime: %s\n\nSpark of Divine", 'spark-of-divine-scheduler'),
            $booking_meta['staff_name'],
            $booking_meta['customer_name'],
            $booking_meta['customer_phone'],
            $booking_meta['customer_email'],
            $booking_meta['service_name'],
            date_i18n(get_option('date_format'), strtotime($booking_meta['start_time'])),
            date_i18n(get_option('time_format'), strtotime($booking_meta['start_time']))
        );
        
        wp_mail($staff_email, $staff_subject, nl2br($staff_message), $headers);
        
        // Always send to spark admin
        wp_mail('staff@sparkofdivine.com', $staff_subject, nl2br($staff_message), $headers);
    }
    
    /**
     * Send booking reschedule emails
     */
    private function send_booking_reschedule_emails($booking_id, $new_start_time) {
        // Implementation similar to send_booking_confirmation_emails
        // but with rescheduling information
    }
    
    /**
     * Send booking cancellation emails
     */
    private function send_booking_cancellation_emails($booking_id) {
        // Implementation similar to send_booking_confirmation_emails
        // but with cancellation information
    }
    
    /**
     * Get all relevant booking details
     */
    private function get_booking_details($booking_id) {
        global $wpdb;
        
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT b.*, 
                s.name AS service_name,
                st.user_id AS staff_user_id,
                u.display_name AS staff_name
             FROM {$wpdb->prefix}sod_bookings b
             LEFT JOIN {$wpdb->prefix}sod_services s ON b.service_id = s.service_id
             LEFT JOIN {$wpdb->prefix}sod_staff st ON b.staff_id = st.staff_id
             LEFT JOIN {$wpdb->users} u ON st.user_id = u.ID
             WHERE b.booking_id = %d",
            $booking_id
        ));
        
        if (!$booking) return false;
        
        // Get post meta values
        $customer_name = get_post_meta($booking_id, 'sod_booking_customer_name', true);
        $customer_email = get_post_meta($booking_id, 'sod_booking_customer_email', true);
        $customer_phone = get_post_meta($booking_id, 'sod_booking_customer_phone', true);
        
        return [
            'booking_id' => $booking_id,
            'service_id' => $booking->service_id,
            'service_name' => $booking->service_name,
            'staff_id' => $booking->staff_id,
            'staff_name' => $booking->staff_name,
            'staff_user_id' => $booking->staff_user_id,
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'customer_phone' => $customer_phone,
            'start_time' => $booking->start_time,
            'end_time' => $booking->end_time,
            'status' => $booking->status
        ];
    }

    /**
     * Updated render method for SOD_Staff_Frontend_Schedule_Widget to fix conflicts
     */
    protected function render() {
        // Only render for staff users or on staff-schedule page
        if (!is_user_logged_in() || !current_user_can('sod_staff')) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="sod-preview">Staff Schedule Preview</div>';
                return;
            }

            global $post;
            if (!$post || $post->post_name !== 'staff-schedule') {
                return;
            }
        }

        $settings = $this->get_settings_for_display();

        // Clear any existing globals
        unset($GLOBALS['sod_customer_view']);
        unset($GLOBALS['sod_shop_manager_view']);

        // Set necessary globals for staff view only
        $GLOBALS['sod_staff_view'] = true;

        try {
            $staff_id = $this->get_current_staff_id();
            if (!$staff_id) {
                echo '<p>' . __('Staff member not found.', 'spark-of-divine-scheduler') . '</p>';
                return;
            }

            $GLOBALS['sod_staff_id'] = $staff_id;
            $GLOBALS['sod_staff_availability'] = $this->get_staff_availability($staff_id, $settings['days_to_show']);
            $GLOBALS['sod_staff_bookings'] = $this->get_staff_bookings($staff_id, $settings['days_to_show']);
            $GLOBALS['sod_widget_settings'] = $settings;

            // Include template with error handling
            $template = get_stylesheet_directory() . '/staff-schedule.php';
            if (file_exists($template)) {
                include $template;
            } else {
                $plugin_template = SOD_PLUGIN_PATH . 'templates/staff-schedule.php';

                if (file_exists($plugin_template)) {
                    include $plugin_template;
                } else {
                    echo '<p>' . __('Staff schedule template not found.', 'spark-of-divine-scheduler') . '</p>';
                }
            }
        } catch (Exception $e) {
            error_log('SOD_Staff_Frontend_Schedule_Widget error: ' . $e->getMessage());
            echo '<p>' . __('Error loading schedule data. Please try again.', 'spark-of-divine-scheduler') . '</p>';
        }
    }
}