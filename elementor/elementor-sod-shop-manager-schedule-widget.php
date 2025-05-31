<?php
namespace Elementor;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class SOD_Shop_Manager_Schedule_Widget extends Widget_Base {

    public function get_name() { return 'sod_shop_manager_schedule'; }
    public function get_title() { return __('Shop Manager Schedule', 'spark-of-divine-scheduler'); }
    public function get_icon() { return 'eicon-calendar'; }
    public function get_categories() { return ['spark-of-divine']; }
    public function get_script_depends() { return ['sod-shop-manager-schedule-script']; }
    public function get_style_depends() { return ['sod-shop-manager-schedule-style']; }

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
                'default' => __('Shop Manager Schedule', 'spark-of-divine-scheduler'),
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
            error_log("Shop Manager availability query error: " . $wpdb->last_error);
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
            "SELECT b.*, p.post_title as booking_title, u.display_name AS staff_name
             FROM {$wpdb->prefix}sod_bookings b
             LEFT JOIN {$wpdb->posts} p ON b.booking_id = p.ID
             LEFT JOIN {$wpdb->prefix}sod_staff st ON b.staff_id = st.staff_id
             LEFT JOIN {$wpdb->users} u ON st.user_id = u.ID
             WHERE DATE(b.start_time) BETWEEN %s AND %s
             ORDER BY b.start_time ASC",
            $start_date, $end_date
        );

        return $wpdb->get_results($query) ?: [];
    }

    /**
     * Check if current user has permissions to view this widget
     * @return bool
     */
    private function user_has_access() {
        return is_user_logged_in() && (current_user_can('administrator') || current_user_can('sod_shop_manager'));
    }

    /**
     * Updated render method for SOD_Shop_Manager_Schedule_Widget to fix conflicts
     */
    protected function render() {
        // Only render for shop manager users or on shop-manager page
        if (!is_user_logged_in() || !(current_user_can('shop_manager') || current_user_can('administrator'))) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="sod-preview">Shop Manager Schedule Preview</div>';
                return;
            }

            global $post;
            if (!$post || $post->post_name !== 'shop-manager') {
                return;
            }
        }

        $settings = $this->get_settings_for_display();

        // Clear any existing globals 
        unset($GLOBALS['sod_customer_view']);
        unset($GLOBALS['sod_staff_view']);

        // Set necessary globals for shop manager view only
        $GLOBALS['sod_shop_manager_view'] = true;

        try {
            // Get data with error handling
            $GLOBALS['sod_all_staff_availability'] = $this->get_all_staff_availability($settings['days_to_show']);
            $GLOBALS['sod_all_bookings'] = $this->get_all_bookings($settings['days_to_show']);
            $GLOBALS['sod_widget_settings'] = $settings;

            // Include template with error handling
            $template = get_stylesheet_directory() . '/shop-manager-schedule.php';
            if (file_exists($template)) {
                include $template;
            } else {
                $plugin_template = SOD_PLUGIN_PATH . 'templates/shop-manager-schedule.php';

                if (file_exists($plugin_template)) {
                    include $plugin_template;
                } else {
                    echo '<p>' . __('Shop manager schedule template not found.', 'spark-of-divine-scheduler') . '</p>';
                }
            }
        } catch (Exception $e) {
            error_log('SOD_Shop_Manager_Schedule_Widget error: ' . $e->getMessage());
            echo '<p>' . __('Error loading schedule data. Please try again.', 'spark-of-divine-scheduler') . '</p>';
        }
    }
}