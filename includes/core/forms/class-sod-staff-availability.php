<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Staff Availability Form Handler
 * 
 * Manages the staff availability scheduling system with support for WooCommerce products.
 */
class SOD_Staff_Availability_Form {
    /**
     * Constructor: Set up shortcodes and actions
     */
    public function __construct() {
        add_shortcode('sod_staff_availability_form', array($this, 'render_availability_form'));
        
        // AJAX actions
        add_action('wp_ajax_save_staff_availability', array($this, 'save_staff_availability'));
        add_action('wp_ajax_nopriv_save_staff_availability', array($this, 'save_staff_availability'));
        add_action('wp_ajax_load_staff_availability', array($this, 'load_staff_availability'));
        add_action('wp_ajax_nopriv_load_staff_availability', array($this, 'load_staff_availability'));
        add_action('wp_ajax_delete_availability_slot', array($this, 'delete_availability_slot'));
        add_action('wp_ajax_nopriv_delete_availability_slot', array($this, 'delete_availability_slot'));
        add_action('wp_ajax_bulk_delete_slots', array($this, 'bulk_delete_slots'));
        add_action('wp_ajax_nopriv_bulk_delete_slots', array($this, 'bulk_delete_slots'));
        add_action('wp_ajax_get_staff_products', array($this, 'get_staff_products'));
        add_action('wp_ajax_nopriv_get_staff_products', array($this, 'get_staff_products'));
        
        // Hooks
        add_action('save_post_sod_staff', array($this, 'sync_staff_with_custom_table'), 10, 3);
        
        // Check and update database schema if needed
        $this->check_and_update_database_schema();
    }

    /**
     * Renders the staff availability form
     * 
     * @return string HTML output of the form
     */
    public function render_availability_form() {
        $current_user = wp_get_current_user();
        $staff_id = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : 0;
        $auth_token = isset($_GET['staff_token']) ? sanitize_text_field($_GET['staff_token']) : '';

        // Determine user_id
        if (is_user_logged_in() && (current_user_can('administrator') || current_user_can('manage_availability') || in_array('staff', $current_user->roles))) {
            $user_id = $current_user->ID;
        } elseif ($staff_id && $auth_token) {
            $stored_token = get_user_meta($staff_id, 'staff_auth_token', true);
            if ($auth_token === $stored_token) {
                $user_id = $staff_id;
                delete_user_meta($staff_id, 'staff_auth_token');
                wp_set_auth_cookie($staff_id);
            } else {
                return __('Invalid authentication token. Please log in.', 'spark-of-divine-scheduler');
            }
        } else {
            $login_url = wp_login_url(add_query_arg(array(), wp_unslash($_SERVER['REQUEST_URI'])));
            return sprintf(__('Please <a href="%s">log in</a> to view this page.', 'spark-of-divine-scheduler'), esc_url($login_url));
        }

        // Get the staff ID to use throughout
        $selected_staff_id = isset($_POST['admin_selected_staff']) ? intval($_POST['admin_selected_staff']) : 0;
        $staff_post_id = $this->get_valid_staff_id($user_id, $selected_staff_id);
        if (!$staff_post_id) {
            return __('Failed to determine a valid staff ID. Please contact an administrator.', 'spark-of-divine-scheduler');
        }

        ob_start();
        ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?php _e('Manage Availability', 'spark-of-divine-scheduler'); ?></h3>
            </div>
            <div class="card-content">
                <?php if (current_user_can('administrator')) : ?>
                    <div class="admin-controls">
                        <div class="staff-select-container">
                            <label for="admin-selected-staff"><?php _e('Select Staff Member:', 'spark-of-divine-scheduler'); ?></label>
                            <select name="admin_selected_staff" id="admin-selected-staff">
                                <?php
                                $staff_posts = get_posts(array('post_type' => 'sod_staff', 'posts_per_page' => -1, 'post_status' => 'publish'));
                                foreach ($staff_posts as $staff_post) {
                                    $selected = ($staff_post->ID == $staff_post_id) ? 'selected="selected"' : '';
                                    echo '<option value="' . esc_attr($staff_post->ID) . '" ' . $selected . '>' . esc_html($staff_post->post_title) . '</option>';
                                }
                                ?>
                            </select>
                            <button type="button" id="load-staff-availability" class="button"><?php _e('Load Availability', 'spark-of-divine-scheduler'); ?></button>
                        </div>
                        <div class="admin-actions">
                            <button type="button" id="toggle-bulk-delete" class="button button-secondary"><?php _e('Bulk Delete Options', 'spark-of-divine-scheduler'); ?></button>
                        </div>
                    </div>

                    <!-- Bulk Delete Section -->
                    <div id="bulk-delete-section" class="sod-bulk-delete-container" style="display: none;">
                        <h4><?php _e('Bulk Delete Availability for Selected Staff', 'spark-of-divine-scheduler'); ?></h4>
                        <p class="warning"><?php _e('Warning: This will permanently delete ALL matching availability slots for the chosen staff member. This action cannot be undone.', 'spark-of-divine-scheduler'); ?></p>

                        <form id="sod-bulk-delete-form" method="post">
                            <input type="hidden" name="staff_post_id" value="<?php echo esc_attr($staff_post_id); ?>">

                            <div class="form-row">
                                <label><?php _e('Staff Member:', 'spark-of-divine-scheduler'); ?></label>
                                <span><?php echo esc_html(get_the_title($staff_post_id)); ?></span>
                            </div>

                            <div class="form-row">
                                <label for="bulk-delete-filter-type"><?php _e('Delete By:', 'spark-of-divine-scheduler'); ?></label>
                                <select id="bulk-delete-filter-type" name="filter_type" required>
                                    <option value=""><?php _e('Select Filter', 'spark-of-divine-scheduler'); ?></option>
                                    <option value="product"><?php _e('Product', 'spark-of-divine-scheduler'); ?></option>
                                    <option value="month"><?php _e('Month', 'spark-of-divine-scheduler'); ?></option>
                                    <option value="range"><?php _e('Date Range', 'spark-of-divine-scheduler'); ?></option>
                                </select>
                            </div>

                            <div class="form-row product-filter" style="display: none;">
                                <label for="bulk-delete-product"><?php _e('Product:', 'spark-of-divine-scheduler'); ?></label>
                                <select id="bulk-delete-product" name="product_id">
                                    <option value=""><?php _e('Select Product', 'spark-of-divine-scheduler'); ?></option>
                                    <?php 
                                    $staff_products = $this->get_staff_products_from_availability($staff_post_id);
                                    if (!empty($staff_products)) {
                                        foreach ($staff_products as $product_id => $product_name) {
                                            ?>
                                            <option value="<?php echo esc_attr($product_id); ?>">
                                                <?php echo esc_html($product_name); ?>
                                            </option>
                                            <?php
                                        }
                                    } else {
                                        echo '<option value="">' . __('No products available for this staff', 'spark-of-divine-scheduler') . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-row month-filter" style="display: none;">
                                <label for="bulk-delete-month"><?php _e('Month and Year:', 'spark-of-divine-scheduler'); ?></label>
                                <input type="month" id="bulk-delete-month" name="month" placeholder="<?php _e('YYYY-MM', 'spark-of-divine-scheduler'); ?>">
                            </div>

                            <div class="form-row range-filter" style="display: none;">
                                <label><?php _e('Date Range:', 'spark-of-divine-scheduler'); ?></label>
                                <div class="date-inputs">
                                    <input type="date" id="bulk-delete-start-date" name="start_date" 
                                           placeholder="<?php _e('Start Date', 'spark-of-divine-scheduler'); ?>" />
                                    <span>to</span>
                                    <input type="date" id="bulk-delete-end-date" name="end_date" 
                                           placeholder="<?php _e('End Date', 'spark-of-divine-scheduler'); ?>" />
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" id="bulk-delete-submit" class="button button-primary">
                                    <?php _e('Delete Matching Slots', 'spark-of-divine-scheduler'); ?>
                                </button>
                                <span class="spinner"></span>
                            </div>

                            <div id="bulk-delete-results" class="results-container" style="display: none;"></div>

                            <?php wp_nonce_field('sod_availability_nonce_action', 'sod_availability_nonce'); ?>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Availability Form -->
                <form id="sod-staff-availability-form" method="post">
                    <input type="hidden" name="staff_post_id" value="<?php echo esc_attr($staff_post_id); ?>">
                    <div id="availability-slots">
                        <?php $this->render_existing_availability_slots($staff_post_id); ?>
                    </div>
                    <div class="form-actions">
                        <button type="button" id="add-availability-slot" class="button"><?php _e('Add Slot', 'spark-of-divine-scheduler'); ?></button>
                        <button type="submit" class="button button-primary"><?php _e('Save Availability', 'spark-of-divine-scheduler'); ?></button>
                    </div>
                    <?php wp_nonce_field('sod_availability_nonce_action', 'sod_availability_nonce'); ?>
                </form>
            </div>
        </div>

        <!-- Hidden Template for New Availability Slots -->
        <script type="text/template" id="availability-slot-template">
            <div class="availability-slot" data-slot-index="new">
                <div class="schedule-type-selector">
                    <label><?php _e('Schedule Type:', 'spark-of-divine-scheduler'); ?></label>
                    <select name="schedule_type[new]" class="schedule-type">
                        <option value="one_time"><?php _e('One-time', 'spark-of-divine-scheduler'); ?></option>
                        <option value="weekly"><?php _e('Weekly', 'spark-of-divine-scheduler'); ?></option>
                        <option value="biweekly"><?php _e('Biweekly', 'spark-of-divine-scheduler'); ?></option>
                        <option value="monthly"><?php _e('Monthly', 'spark-of-divine-scheduler'); ?></option>
                    </select>
                </div>

                <div class="one-time-fields">
                    <label><?php _e('Specific Date:', 'spark-of-divine-scheduler'); ?></label>
                    <input type="date" name="availability_date[new]" />
                </div>

                <div class="recurring-fields" style="display: none;">
                    <label><?php _e('Day of the Week:', 'spark-of-divine-scheduler'); ?></label>
                    <select name="availability_day[new]" class="day-of-week-selector">
                        <option value=""><?php _e('Select Day', 'spark-of-divine-scheduler'); ?></option>
                        <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day): ?>
                            <option value="<?php echo $day; ?>"><?php _e($day, 'spark-of-divine-scheduler'); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label><?php _e('Start Date:', 'spark-of-divine-scheduler'); ?></label>
                    <input type="date" name="recurring_start_date[new]" value="<?php echo date('Y-m-d'); ?>" />

                    <label><?php _e('End Date:', 'spark-of-divine-scheduler'); ?></label>
                    <input type="date" name="recurring_end_date[new]" value="<?php echo date('Y-m-d', strtotime('+3 months')); ?>" />

                    <div class="monthly-options" style="display: none;">
                        <label><?php _e('Monthly Occurrence:', 'spark-of-divine-scheduler'); ?></label>
                        <select name="monthly_occurrence[new]" class="monthly-occurrence">
                            <option value=""><?php _e('Select Occurrence', 'spark-of-divine-scheduler'); ?></option>
                            <option value="1st"><?php _e('1st', 'spark-of-divine-scheduler'); ?></option>
                            <option value="2nd"><?php _e('2nd', 'spark-of-divine-scheduler'); ?></option>
                            <option value="3rd"><?php _e('3rd', 'spark-of-divine-scheduler'); ?></option>
                            <option value="4th"><?php _e('4th', 'spark-of-divine-scheduler'); ?></option>
                            <option value="last"><?php _e('Last', 'spark-of-divine-scheduler'); ?></option>
                        </select>
                        <select name="monthly_day[new]" class="monthly-day">
                            <option value=""><?php _e('Select Day', 'spark-of-divine-scheduler'); ?></option>
                            <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day): ?>
                                <option value="<?php echo $day; ?>"><?php _e($day, 'spark-of-divine-scheduler'); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e('Select the occurrence and day for monthly recurring slots.', 'spark-of-divine-scheduler'); ?></p>
                    </div>

                    <div class="biweekly-options" style="display: none;">
                        <label><?php _e('Biweekly Pattern:', 'spark-of-divine-scheduler'); ?></label>
                        <select name="biweekly_pattern[new]">
                            <option value="1st_3rd"><?php _e('1st & 3rd', 'spark-of-divine-scheduler'); ?></option>
                            <option value="2nd_4th"><?php _e('2nd & 4th', 'spark-of-divine-scheduler'); ?></option>
                        </select>
                        <label>
                            <input type="checkbox" name="skip_5th_week[new]" value="1" />
                            <?php _e('Skip 5th Week', 'spark-of-divine-scheduler'); ?>
                        </label>
                    </div>
                </div>

                <label><?php _e('Products:', 'spark-of-divine-scheduler'); ?></label>
                <select name="availability_product[new][]" multiple class="availability-product">
                    <?php 
                    // Get all WooCommerce products
                    $args = array(
                        'post_type' => 'product',
                        'posts_per_page' => -1,
                        'post_status' => 'publish',
                    );
                    $products = get_posts($args);
                    
                    foreach ($products as $product): 
                    ?>
                        <option value="<?php echo esc_attr($product->ID); ?>"><?php echo esc_html($product->post_title); ?></option>
                    <?php endforeach; ?>
                </select>

                <label><?php _e('Start Time:', 'spark-of-divine-scheduler'); ?></label>
                <input type="time" name="availability_start[new]" />

                <label><?php _e('End Time:', 'spark-of-divine-scheduler'); ?></label>
                <input type="time" name="availability_end[new]" />

                <label><?php _e('Buffer Between Sessions:', 'spark-of-divine-scheduler'); ?></label>
                <select name="buffer_time[new]">
                    <option value="0"><?php _e('None', 'spark-of-divine-scheduler'); ?></option>
                    <option value="15"><?php _e('15 mins', 'spark-of-divine-scheduler'); ?></option>
                    <option value="30"><?php _e('30 mins', 'spark-of-divine-scheduler'); ?></option>
                </select>

                <label>
                    <input type="checkbox" name="appointment_only[new]" value="1" />
                    <?php _e('By Appointment Only', 'spark-of-divine-scheduler'); ?>
                </label>

                <button type="button" class="remove-availability-slot"><?php _e('Remove', 'spark-of-divine-scheduler'); ?></button>
            </div>
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get staff post ID by user ID
     * 
     * @param int $user_id User ID
     * @return int|false Staff post ID or false if not found
     */
    private function get_staff_post_id_by_user($user_id) {
        global $wpdb;
        $staff_id = $wpdb->get_var($wpdb->prepare(
            "SELECT staff_id FROM {$wpdb->prefix}sod_staff WHERE user_id = %d",
            $user_id
        ));

        if ($staff_id) {
            return $staff_id;
        }

        // If not found in the custom table, try to find it in post meta
        $args = array(
            'post_type' => 'sod_staff',
            'meta_key' => 'sod_staff_user_id',
            'meta_value' => $user_id,
            'posts_per_page' => 1
        );
        $staff_posts = get_posts($args);

        if (!empty($staff_posts)) {
            return $staff_posts[0]->ID;
        }

        return false;
    }

    /**
     * Render existing availability slots for a staff member
     * 
     * @param int $staff_post_id Staff post ID
     */
    private function render_existing_availability_slots($staff_post_id) {
        global $wpdb;
        $availability_slots = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sod_staff_availability WHERE staff_id = %d",
            $staff_post_id
        ));
        
        // Get all WooCommerce products
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        );
        $products = get_posts($args);

        $grouped_slots = [];
        foreach ($availability_slots as $slot) {
            $key = md5("{$slot->date}_{$slot->day_of_week}_{$slot->start_time}_{$slot->end_time}");
            if (!isset($grouped_slots[$key])) {
                $grouped_slots[$key] = (object) [
                    'availability_id' => $slot->availability_id, 
                    'date' => $slot->date,
                    'day_of_week' => $slot->day_of_week,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'appointment_only' => $slot->appointment_only,
                    'recurring_type' => $slot->recurring_type,
                    'recurring_end_date' => $slot->recurring_end_date,
                    'biweekly_pattern' => $slot->biweekly_pattern,
                    'skip_5th_week' => $slot->skip_5th_week,
                    'buffer_time' => $slot->buffer_time,
                    'monthly_day' => $slot->monthly_day ?? null, 
                    'monthly_occurrence' => $slot->monthly_occurrence ?? null, 
                    'product_ids' => []
                ];
            }
            
            // Use product_id with fallback to service_id for backward compatibility
            $product_id = (!empty($slot->product_id)) ? $slot->product_id : 
                         ((!empty($slot->service_id)) ? $slot->service_id : null);
                         
            if ($product_id) {
                $grouped_slots[$key]->product_ids[] = $product_id;
            }
        }

        foreach ($grouped_slots as $key => $slot) {
            $slot_id = md5(uniqid()); 
            ?>
            <div class="availability-slot" data-slot-key="<?php echo esc_attr($key); ?>">
                <div class="schedule-type-selector">
                    <label><?php _e('Schedule Type:', 'spark-of-divine-scheduler'); ?></label>
                    <select name="schedule_type[<?php echo $slot_id; ?>]" class="schedule-type">
                        <option value="one_time" <?php selected($slot->recurring_type, ''); ?>><?php _e('One-time', 'spark-of-divine-scheduler'); ?></option>
                        <option value="weekly" <?php selected($slot->recurring_type, 'weekly'); ?>><?php _e('Weekly', 'spark-of-divine-scheduler'); ?></option>
                        <option value="biweekly" <?php selected($slot->recurring_type, 'biweekly'); ?>><?php _e('Biweekly', 'spark-of-divine-scheduler'); ?></option>
                        <option value="monthly" <?php selected($slot->recurring_type, 'monthly'); ?>><?php _e('Monthly', 'spark-of-divine-scheduler'); ?></option>
                    </select>
                </div>

                <div class="one-time-fields" style="display: <?php echo $slot->recurring_type ? 'none' : 'block'; ?>;">
                    <label><?php _e('Specific Date:', 'spark-of-divine-scheduler'); ?></label>
                    <input type="date" name="availability_date[<?php echo $slot_id; ?>]" value="<?php echo esc_attr($slot->date); ?>" />
                </div>

                <div class="recurring-fields" style="display: <?php echo $slot->recurring_type ? 'block' : 'none'; ?>;">
                    <label><?php _e('Day of the Week:', 'spark-of-divine-scheduler'); ?></label>
                    <select name="availability_day[<?php echo $slot_id; ?>]" class="day-of-week-selector">
                        <option value=""><?php _e('Select Day', 'spark-of-divine-scheduler'); ?></option>
                        <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day): ?>
                            <option value="<?php echo $day; ?>" <?php selected($slot->day_of_week, $day); ?>><?php _e($day, 'spark-of-divine-scheduler'); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <div class="monthly-options" style="display: <?php echo $slot->recurring_type === 'monthly' ? 'block' : 'none'; ?>;">
                        <label><?php _e('Monthly Occurrence:', 'spark-of-divine-scheduler'); ?></label>
                        <select name="monthly_occurrence[<?php echo $slot_id; ?>]" class="monthly-occurrence">
                            <option value=""><?php _e('Select Occurrence', 'spark-of-divine-scheduler'); ?></option>
                            <?php foreach (['1st', '2nd', '3rd', '4th', 'last'] as $occurrence): ?>
                                <option value="<?php echo $occurrence; ?>" <?php selected($slot->monthly_occurrence, $occurrence); ?>><?php _e($occurrence, 'spark-of-divine-scheduler'); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label><?php _e('Monthly Day:', 'spark-of-divine-scheduler'); ?></label>
                        <select name="monthly_day[<?php echo $slot_id; ?>]" class="monthly-day">
                            <option value=""><?php _e('Select Day', 'spark-of-divine-scheduler'); ?></option>
                            <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day): ?>
                                <option value="<?php echo $day; ?>" <?php selected($slot->monthly_day, $day); ?>><?php _e($day, 'spark-of-divine-scheduler'); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e('Select the occurrence and day for monthly recurring slots.', 'spark-of-divine-scheduler'); ?></p>
                    </div>

                    <label><?php _e('End Date:', 'spark-of-divine-scheduler'); ?></label>
                    <input type="date" name="recurring_end_date[<?php echo $slot_id; ?>]" value="<?php echo esc_attr($slot->recurring_end_date); ?>" />

                    <div class="biweekly-options" style="display: <?php echo $slot->recurring_type === 'biweekly' ? 'block' : 'none'; ?>;">
                        <label><?php _e('Biweekly Pattern:', 'spark-of-divine-scheduler'); ?></label>
                        <select name="biweekly_pattern[<?php echo $slot_id; ?>]">
                            <option value="1st_3rd" <?php selected($slot->biweekly_pattern, '1st_3rd'); ?>><?php _e('1st & 3rd', 'spark-of-divine-scheduler'); ?></option>
                            <option value="2nd_4th" <?php selected($slot->biweekly_pattern, '2nd_4th'); ?>><?php _e('2nd & 4th', 'spark-of-divine-scheduler'); ?></option>
                        </select>
                        <label>
                            <input type="checkbox" name="skip_5th_week[<?php echo $slot_id; ?>]" value="1" <?php checked($slot->skip_5th_week, 1); ?> />
                            <?php _e('Skip 5th Week', 'spark-of-divine-scheduler'); ?>
                        </label>
                    </div>
                </div>

                <label><?php _e('Products:', 'spark-of-divine-scheduler'); ?></label>
                <select name="availability_product[<?php echo $slot_id; ?>][]" multiple class="availability-product">
                    <?php foreach ($products as $product): ?>
                        <option value="<?php echo esc_attr($product->ID); ?>" <?php echo in_array($product->ID, $slot->product_ids) ? 'selected' : ''; ?>>
                            <?php echo esc_html($product->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label><?php _e('Start Time:', 'spark-of-divine-scheduler'); ?></label>
                <input type="time" name="availability_start[<?php echo $slot_id; ?>]" value="<?php echo esc_attr($slot->start_time); ?>" />

                <label><?php _e('End Time:', 'spark-of-divine-scheduler'); ?></label>
                <input type="time" name="availability_end[<?php echo $slot_id; ?>]" value="<?php echo esc_attr($slot->end_time); ?>" />

                <label><?php _e('Buffer Between Sessions:', 'spark-of-divine-scheduler'); ?></label>
                <select name="buffer_time[<?php echo $slot_id; ?>]">
                    <option value="0" <?php selected($slot->buffer_time, 0); ?>><?php _e('None', 'spark-of-divine-scheduler'); ?></option>
                    <option value="15" <?php selected($slot->buffer_time, 15); ?>><?php _e('15 mins', 'spark-of-divine-scheduler'); ?></option>
                    <option value="30" <?php selected($slot->buffer_time, 30); ?>><?php _e('30 mins', 'spark-of-divine-scheduler'); ?></option>
                </select>

                <label>
                    <input type="checkbox" name="appointment_only[<?php echo $slot_id; ?>]" value="1" <?php checked($slot->appointment_only, 1); ?> />
                    <?php _e('By Appointment Only', 'spark-of-divine-scheduler'); ?>
                </label>

                <?php if ($slot->recurring_type && $slot->availability_id): ?>
                    <div class="update-scope" style="margin-top: 10px;">
                        <label>Update Scope:</label><br>
                        <label><input type="radio" name="update_scope[<?php echo $slot_id; ?>]" value="single" checked /> This day only</label>
                        <label><input type="radio" name="update_scope[<?php echo $slot_id; ?>]" value="all" /> All future slots</label>
                        <input type="hidden" name="availability_ids[<?php echo $slot_id; ?>]" value="<?php echo esc_attr($slot->availability_id); ?>" />
                    </div>
                <?php endif; ?>

                <button type="button" class="remove-availability-slot"><?php _e('Remove', 'spark-of-divine-scheduler'); ?></button>
            </div>
            <?php
        }
    }

    /**
     * AJAX handler to load staff availability
     */
    public function load_staff_availability() {
        check_ajax_referer('sod_availability_nonce_action', 'nonce');

        $staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;
        error_log("Loading availability for staff ID: $staff_id");

        if (!$staff_id) {
            wp_send_json_error(__('Invalid staff ID', 'spark-of-divine-scheduler'));
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'sod_staff_availability';

        // Get count of availability records
        $direct_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE staff_id = %d",
            $staff_id
        ));
        error_log("Direct count shows $direct_count availability records for staff $staff_id");

        // Query all availability slots with product info
        $availability = $wpdb->get_results($wpdb->prepare(
            "SELECT sa.*, p.post_title AS product_name
             FROM $table_name AS sa
             LEFT JOIN {$wpdb->posts} AS p ON sa.product_id = p.ID
             WHERE sa.staff_id = %d 
             ORDER BY CASE 
                 WHEN sa.day_of_week IS NULL OR sa.day_of_week = '' 
                 THEN sa.date 
                 ELSE FIELD(sa.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')
             END, sa.start_time",
            $staff_id
        ));

        error_log("Main query returned " . count($availability) . " rows");

        // Render the availability list
        ob_start();
        ?>
        <div class="current-availability-list">
            <h4><?php _e('Current Availability Schedule', 'spark-of-divine-scheduler'); ?></h4>

            <?php if (empty($availability)): ?>
                <p class="no-availability"><?php _e('No availability slots found.', 'spark-of-divine-scheduler'); ?></p>
            <?php else: ?>
                <table class="availability-table widefat">
                    <thead>
                        <tr>
                            <th class="select-column" width="30">
                                <input type="checkbox" id="select-all-slots" title="Select All">
                            </th>
                            <th><?php _e('Product', 'spark-of-divine-scheduler'); ?></th>
                            <th><?php _e('Date/Day', 'spark-of-divine-scheduler'); ?></th>
                            <th><?php _e('Time', 'spark-of-divine-scheduler'); ?></th>
                            <th><?php _e('Schedule Type', 'spark-of-divine-scheduler'); ?></th>
                            <th><?php _e('Recurring End', 'spark-of-divine-scheduler'); ?></th>
                            <th><?php _e('Booking Type', 'spark-of-divine-scheduler'); ?></th>
                            <th class="actions-column"><?php _e('Actions', 'spark-of-divine-scheduler'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($availability as $slot): 
                            // Prefer product_id but fall back to service_id for backward compatibility
                            $product_id = !empty($slot->product_id) ? $slot->product_id : $slot->service_id;
                            
                            // If no product_name from the join, try to get it directly
                            $product_name = $slot->product_name;
                            if (empty($product_name) && $product_id) {
                                $product_name = get_the_title($product_id);
                            }
                        ?>
                            <tr class="availability-row">
                                <td class="select-column">
                                    <input type="checkbox" class="select-slot" name="selected_slots[]" value="<?php echo esc_attr($slot->availability_id); ?>">
                                </td>
                                <td class="product-name"><?php echo esc_html($product_name ?: "Product #{$product_id}"); ?></td>
                                <td class="date-day">
                                    <?php 
                                    if (!empty($slot->date)) {
                                        echo esc_html(date('M j, Y', strtotime($slot->date)));
                                    } else {
                                        echo esc_html($slot->day_of_week);
                                        if ($slot->recurring_type === 'monthly' && !empty($slot->monthly_occurrence) && !empty($slot->monthly_day)) {
                                            echo ' (' . esc_html($slot->monthly_occurrence . ' ' . $slot->monthly_day) . ')';
                                        }
                                    }
                                    ?>
                                </td>
                                <td class="time-range">
                                    <?php echo esc_html(date('g:i A', strtotime($slot->start_time)) . ' - ' . date('g:i A', strtotime($slot->end_time))); ?>
                                </td>
                                <td class="schedule-type">
                                    <?php 
                                    if ($slot->recurring_type === 'weekly') {
                                        echo __('Weekly', 'spark-of-divine-scheduler');
                                    } elseif ($slot->recurring_type === 'biweekly') {
                                        echo __('Biweekly', 'spark-of-divine-scheduler') . ' (' . ($slot->biweekly_pattern === '1st_3rd' ? '1st & 3rd' : '2nd & 4th') . ')';
                                    } elseif ($slot->recurring_type === 'monthly') {
                                        echo __('Monthly', 'spark-of-divine-scheduler');
                                    } else {
                                        echo __('One-time', 'spark-of-divine-scheduler');
                                    }
                                    ?>
                                </td>
                                <td class="recurring-end">
                                    <?php echo !empty($slot->recurring_end_date) ? esc_html(date('M j, Y', strtotime($slot->recurring_end_date))) : '—'; ?>
                                </td>
                                <td class="booking-type">
                                    <?php echo $slot->appointment_only ? __('By Appointment', 'spark-of-divine-scheduler') : __('Regular', 'spark-of-divine-scheduler'); ?>
                                </td>
                                <td class="actions">
                                    <button type="button" class="edit-availability button-link"
                                            data-availability-id="<?php echo esc_attr($slot->availability_id); ?>"
                                            data-product-id="<?php echo esc_attr($product_id); ?>"
                                            data-start="<?php echo esc_attr($slot->start_time); ?>"
                                            data-end="<?php echo esc_attr($slot->end_time); ?>"
                                            data-date="<?php echo esc_attr($slot->date); ?>"
                                            data-day="<?php echo esc_attr($slot->day_of_week); ?>"
                                            data-recurring-type="<?php echo esc_attr($slot->recurring_type); ?>"
                                            data-recurring-end-date="<?php echo esc_attr($slot->recurring_end_date); ?>"
                                            data-appointment-only="<?php echo esc_attr($slot->appointment_only); ?>"
                                            data-buffer-time="<?php echo esc_attr($slot->buffer_time); ?>"
                                            data-biweekly-pattern="<?php echo esc_attr($slot->biweekly_pattern); ?>"
                                            data-skip-5th-week="<?php echo esc_attr($slot->skip_5th_week); ?>"
                                            data-monthly-occurrence="<?php echo esc_attr($slot->monthly_occurrence); ?>"
                                            data-monthly-day="<?php echo esc_attr($slot->monthly_day); ?>">
                                        <?php _e('Edit', 'spark-of-divine-scheduler'); ?>
                                    </button>
                                    <button type="button" class="delete-availability button-link-delete"
                                            data-availability-id="<?php echo esc_attr($slot->availability_id); ?>">
                                        <?php _e('Delete', 'spark-of-divine-scheduler'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Bulk Actions Section -->
                <div class="bulk-actions">
                    <select name="bulk_action" id="bulk-action-selector">
                        <option value=""><?php _e('Bulk Actions', 'spark-of-divine-scheduler'); ?></option>
                        <option value="delete"><?php _e('Delete', 'spark-of-divine-scheduler'); ?></option>
                    </select>
                    <button type="button" id="do-bulk-action" class="button" disabled><?php _e('Apply', 'spark-of-divine-scheduler'); ?></button>
                    <span class="spinner bulk-delete-spinner"></span>
                    <div id="bulk-action-result" class="hidden"></div>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        .select-column {
            width: 30px;
            text-align: center;
        }
        
        .select-slot {
            margin: 0 auto;
            display: block;
        }
        
        .bulk-actions {
            margin-top: 15px;
            padding: 10px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        #bulk-action-selector {
            padding: 6px 10px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        .bulk-delete-spinner {
            float: none;
            margin: 0;
            visibility: hidden;
        }
        
        .bulk-delete-spinner.is-active {
            visibility: visible;
        }
        
        #bulk-action-result {
            margin-left: 10px;
            padding: 5px 10px;
            border-radius: 4px;
        }
        
        #bulk-action-result.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        #bulk-action-result.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .hidden {
            display: none;
        }
        
        /* Disable buttons when not applicable */
        #do-bulk-action:disabled {
            cursor: not-allowed;
            opacity: 0.6;
        }
        </style>
        <?php
        $html = ob_get_clean();
        wp_send_json_success($html);
    }

    /**
     * Saves staff availability slots via AJAX
     */
    public function save_staff_availability() {
        error_log("====== SAVE STAFF AVAILABILITY STARTED ======");
        error_log("POST data: " . print_r($_POST, true));

        // Verify nonce for security
        if (!check_ajax_referer('sod_availability_nonce_action', 'sod_availability_nonce', false)) {
            error_log("Nonce verification failed");
            wp_send_json_error(array('message' => __('Nonce verification failed.', 'spark-of-divine-scheduler')));
            return;
        }

        // Restrict access: Only administrators are allowed to update availability.
        if (!current_user_can('administrator')) {
            wp_send_json_error(array('message' => __('You do not have permission to update availability.', 'spark-of-divine-scheduler')));
            return;
        }

        // Determine staff ID
        if (isset($_POST['admin_selected_staff']) && intval($_POST['admin_selected_staff']) > 0) {
            $staff_id = intval($_POST['admin_selected_staff']);
            error_log("Admin selected staff ID from dropdown: $staff_id");
        } elseif (isset($_POST['staff_post_id']) && intval($_POST['staff_post_id']) > 0) {
            $staff_id = intval($_POST['staff_post_id']);
            error_log("Using staff_post_id from form: $staff_id");
        } else {
            wp_send_json_error('Staff ID is required.');
            return;
        }

        error_log("Processing staff ID: " . $staff_id);

        global $wpdb;
        $insert_count = 0;
        $errors = array();

        // Start database transaction
        $wpdb->query('START TRANSACTION');

        try {
            if (!isset($_POST['schedule_type']) || !is_array($_POST['schedule_type'])) {
                throw new Exception('Missing schedule type data');
            }

            $schedule_types = $_POST['schedule_type'];
            error_log("Processing " . count($schedule_types) . " slots");

            foreach ($schedule_types as $index => $schedule_type) {
                error_log("Processing slot index: " . $index . ", type: " . $schedule_type);

                // Check for required fields
                if (!isset($_POST['availability_product'][$index]) || !isset($_POST['availability_start'][$index]) || !isset($_POST['availability_end'][$index])) {
                    error_log("Missing required data for index: " . $index);
                    $errors[] = "Slot $index: Missing required data";
                    continue;
                }

                $product_ids = $_POST['availability_product'][$index];
                $start_time = sanitize_text_field($_POST['availability_start'][$index]);
                $end_time = sanitize_text_field($_POST['availability_end'][$index]);
                $buffer_time = isset($_POST['buffer_time'][$index]) ? intval($_POST['buffer_time'][$index]) : 0;
                $is_appointment_only = isset($_POST['appointment_only'][$index]) && $_POST['appointment_only'][$index] == '1' ? 1 : 0;

                if (empty($product_ids) || !is_array($product_ids) || empty($start_time) || empty($end_time) || strtotime($start_time) >= strtotime($end_time)) {
                    error_log("Invalid slot data for index: " . $index);
                    $errors[] = "Slot $index: Invalid or incomplete slot data";
                    continue;
                }

                $is_edit = !empty($_POST['availability_ids'][$index]);
                $update_scope = isset($_POST['update_scope'][$index]) ? $_POST['update_scope'][$index] : 'single';

                if ($schedule_type === 'one_time') {
                    $date = isset($_POST['availability_date'][$index]) ? sanitize_text_field($_POST['availability_date'][$index]) : '';
                    if (empty($date)) {
                        $errors[] = "Slot $index: Date is required for one-time slot";
                        continue;
                    }
                    
                    foreach ($product_ids as $product_id) {
                        $result = $this->insert_availability_slot(
                            $staff_id, $date, null, $start_time, $end_time, intval($product_id), $is_appointment_only, null, null, $buffer_time
                        );
                        if ($result !== false) $insert_count++;
                        else $errors[] = "Slot $index: Failed to insert one-time slot for $date, product $product_id";
                    }
                } elseif (in_array($schedule_type, ['weekly', 'biweekly', 'monthly'])) {
                    $day = isset($_POST['availability_day'][$index]) ? sanitize_text_field($_POST['availability_day'][$index]) : '';
                    $end_date = isset($_POST['recurring_end_date'][$index]) ? sanitize_text_field($_POST['recurring_end_date'][$index]) : '';
                    
                    if (empty($day) || empty($end_date)) {
                        $errors[] = "Slot $index: Day and end date required for recurring slot";
                        continue;
                    }

                    $start_date = date('Y-m-d');
                    $biweekly_pattern = ($schedule_type === 'biweekly' && isset($_POST['biweekly_pattern'][$index])) ? sanitize_text_field($_POST['biweekly_pattern'][$index]) : null;
                    $skip_5th = ($schedule_type === 'biweekly' && isset($_POST['skip_5th_week'][$index])) ? intval($_POST['skip_5th_week'][$index]) : null;
                    
                    // Handle monthly specific fields
                    $monthly_day = null;
                    $monthly_occurrence = null;
                    if ($schedule_type === 'monthly') {
                        $monthly_day = isset($_POST['monthly_day'][$index]) ? sanitize_text_field($_POST['monthly_day'][$index]) : null;
                        $monthly_occurrence = isset($_POST['monthly_occurrence'][$index]) ? sanitize_text_field($_POST['monthly_occurrence'][$index]) : null;
                        
                        if (empty($monthly_day) || empty($monthly_occurrence)) {
                            $errors[] = "Slot $index: Occurrence and day required for monthly slot";
                            continue;
                        }
                        
                        error_log("Monthly slot: Day=$monthly_day, Occurrence=$monthly_occurrence");
                    }

                    if ($is_edit && $update_scope === 'all') {
                        $slot_id = intval($_POST['availability_ids'][$index]);
                        if ($slot_id) {
                            $current_date = date('Y-m-d');
                            $table_name = $wpdb->prefix . 'sod_staff_availability';
                            
                            // Create the additional fields portion dynamically based on schedule type
                            $additional_fields = '';
                            $additional_params = array();
                            
                            if ($schedule_type === 'monthly') {
                                $additional_fields = ", monthly_day = %s, monthly_occurrence = %s";
                                $additional_params = array($monthly_day, $monthly_occurrence);
                            } elseif ($schedule_type === 'biweekly') {
                                $additional_fields = ", biweekly_pattern = %s, skip_5th_week = %d";
                                $additional_params = array($biweekly_pattern, $skip_5th);
                            }

                            // Build and execute the update query
                            $query_params = array_merge(
                                array($end_date, $buffer_time, $is_appointment_only, $schedule_type),
                                $additional_params,
                                array($staff_id, $day, $start_time, $end_time, $current_date)
                            );
                            
                            $query = $wpdb->prepare(
                                "UPDATE $table_name SET recurring_end_date = %s, buffer_time = %d, appointment_only = %d, recurring_type = %s" 
                                . $additional_fields . 
                                " WHERE staff_id = %d AND day_of_week = %s AND start_time = %s AND end_time = %s AND (date IS NULL OR date >= %s)",
                                $query_params
                            );
                            
                            $updated = $wpdb->query($query);
                            $insert_count += $updated ? $updated : 0;

                            // Get existing products for these slots
                            $existing_products = $wpdb->get_col($wpdb->prepare(
                                "SELECT DISTINCT product_id FROM $table_name 
                                 WHERE staff_id = %d AND day_of_week = %s AND start_time = %s AND end_time = %s AND (date IS NULL OR date >= %s)",
                                $staff_id, $day, $start_time, $end_time, $current_date
                            ));

                            // Create a temporary slot object for generate_recurring_slots
                            $temp_slot = (object)array(
                                'day_of_week' => $day,
                                'recurring_type' => $schedule_type,
                                'recurring_end_date' => $end_date,
                                'biweekly_pattern' => $biweekly_pattern,
                                'skip_5th_week' => $skip_5th,
                                'monthly_day' => $monthly_day,
                                'monthly_occurrence' => $monthly_occurrence
                            );
                            
                            $slots = $this->generate_recurring_slots($start_date, $end_date, $temp_slot);

                            // Add any new products
                            $new_products = array_diff($product_ids, $existing_products);
                            foreach ($new_products as $product_id) {
                                foreach ($slots as $date) {
                                    $result = $this->insert_availability_slot(
                                        $staff_id, $date, $day, $start_time, $end_time, intval($product_id), $is_appointment_only, 
                                        $schedule_type, $end_date, $buffer_time, $biweekly_pattern, $skip_5th, $monthly_day, $monthly_occurrence
                                    );
                                    if ($result !== false) $insert_count++;
                                }
                            }

                            // Delete products that are no longer selected
                            $products_to_remove = array_diff($existing_products, $product_ids);
                            if (!empty($products_to_remove)) {
                                $product_list = implode(',', array_map('intval', $products_to_remove));
                                // Delete rows where product_id matches
                                $delete_query = $wpdb->prepare(
                                    "DELETE FROM $table_name 
                                     WHERE staff_id = %d AND day_of_week = %s AND start_time = %s AND end_time = %s 
                                     AND (date IS NULL OR date >= %s) 
                                     AND product_id IN ($product_list)",
                                    $staff_id, $day, $start_time, $end_time, $current_date
                                );
                                $wpdb->query($delete_query);
                            }
                        }
                    } else {
                        // Handle single edit or new slot creation
                        $dates_to_process = [];
                        
                        if ($is_edit && $update_scope === 'single') {
                            // For single update, just use the specified date
                            $dates_to_process = array(sanitize_text_field($_POST['availability_date'][$index]));
                        } else {
                            // For new slots, generate all dates
                            $temp_slot = (object)array(
                                'day_of_week' => $day,
                                'recurring_type' => $schedule_type,
                                'recurring_end_date' => $end_date,
                                'biweekly_pattern' => $biweekly_pattern,
                                'skip_5th_week' => $skip_5th,
                                'monthly_day' => $monthly_day,
                                'monthly_occurrence' => $monthly_occurrence
                            );
                            
                            $dates_to_process = $this->generate_recurring_slots($start_date, $end_date, $temp_slot);
                        }

                        if (empty($dates_to_process)) {
                            $errors[] = "Slot $index: No valid dates generated for recurring slot";
                            continue;
                        }

                        foreach ($dates_to_process as $slot_date) {
                            foreach ($product_ids as $product_id) {
                                $result = $this->insert_availability_slot(
                                    $staff_id, $slot_date, $day, $start_time, $end_time, intval($product_id), $is_appointment_only, 
                                    $schedule_type, $end_date, $buffer_time, $biweekly_pattern, $skip_5th, $monthly_day, $monthly_occurrence
                                );
                                if ($result !== false) $insert_count++;
                                else $errors[] = "Slot $index: Failed to insert recurring slot for $slot_date, product $product_id";
                            }
                        }
                    }
                }
            }

            if (!empty($errors)) {
                $wpdb->query('ROLLBACK');
                wp_send_json_error(array('message' => __('Some slots could not be saved.', 'spark-of-divine-scheduler'), 'errors' => $errors));
                return;
            }

            $wpdb->query('COMMIT');
            wp_send_json_success(sprintf(__('%d availability slots added/updated successfully.', 'spark-of-divine-scheduler'), $insert_count));
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log("Exception in save_staff_availability: " . $e->getMessage());
            wp_send_json_error(__('Failed to save availability. Please try again.', 'spark-of-divine-scheduler'));
        }
    }

    /**
     * Insert or update an availability slot
     */
    private function insert_availability_slot($staff_id, $date, $day, $start_time, $end_time, $product_id, $appointment_only, $recurring_type = null, $recurring_end_date = null, $buffer_time = 0, $biweekly_pattern = null, $skip_5th_week = null, $monthly_day = null, $monthly_occurrence = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sod_staff_availability';

        // Check if the hardcoded table name should be used
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            $table_name = 'wp_3be9vb_sod_staff_availability';
        }

        // Log the exact table name being used
        error_log("Using table name for insert: $table_name");

        // Safety check to ensure staff_id is not null
        if (!$staff_id) {
            error_log("ERROR: Staff ID is null or empty in insert_availability_slot");
            return false;
        }

        error_log(sprintf(
            "Inserting slot - Staff: %d, Product: %d, Date: %s, Day: %s, Start: %s, End: %s, Appointment Only: %s, Buffer: %d",
            $staff_id, $product_id, $date, $day, $start_time, $end_time, $appointment_only, $buffer_time
        ));

        // Check if a slot already exists with the same key data
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT availability_id FROM $table_name 
             WHERE staff_id = %d 
             AND date = %s 
             AND day_of_week = %s 
             AND start_time = %s 
             AND end_time = %s 
             AND product_id = %d",
            $staff_id, $date, $day, $start_time, $end_time, $product_id
        ));

        error_log("Check for existing slot query: " . $wpdb->last_query);
        error_log("Check result: " . ($existing ? "Found existing ID: $existing" : "No existing slot found"));

        // Build data array with all needed fields
        $data = array(
            'staff_id' => $staff_id,
            'product_id' => $product_id,
            'date' => $date,
            'day_of_week' => $day,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'appointment_only' => $appointment_only,
            'recurring_type' => $recurring_type,
            'recurring_end_date' => $recurring_end_date,
            'buffer_time' => $buffer_time,
            'biweekly_pattern' => $biweekly_pattern,
            'skip_5th_week' => $skip_5th_week,
            'monthly_day' => $monthly_day,
            'monthly_occurrence' => $monthly_occurrence
        );

        // For backward compatibility, also set service_id equal to product_id
        // This is not necessary for the constraint anymore, but keeps data consistent
        $data['service_id'] = $product_id;

        error_log("Data to insert/update: " . print_r($data, true));

        // Format parameters based on fields
        $format = array('%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%d');

        if ($existing) {
            $result = $wpdb->update(
                $table_name,
                $data,
                array('availability_id' => $existing),
                $format,
                array('%d')
            );
            error_log("Update query: " . $wpdb->last_query);
            error_log("Update result: " . ($result !== false ? "Success, rows affected: $result" : "Failed: " . $wpdb->last_error));
        } else {
            $result = $wpdb->insert(
                $table_name,
                $data,
                $format
            );
            error_log("Insert query: " . $wpdb->last_query);
            error_log("Insert result: " . ($result !== false ? "Success, new ID: " . $wpdb->insert_id : "Failed: " . $wpdb->last_error));
        }

        if ($result === false) {
            error_log("Failed to insert/update slot: " . $wpdb->last_error);
            return false;
        }
        return $result;
    }

    /**
     * Get products associated with a staff member's availability
     */
    private function get_staff_products_from_availability($staff_id) {
        global $wpdb;
        $products = array();
        
        // Primary query using product_id
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT 
                sa.product_id, 
                p.post_title AS product_name 
             FROM {$wpdb->prefix}sod_staff_availability AS sa
             JOIN {$wpdb->posts} AS p ON sa.product_id = p.ID
             WHERE sa.staff_id = %d AND p.post_type = 'product' AND p.post_status = 'publish'",
            $staff_id
        ));

        if (!empty($results)) {
            foreach ($results as $row) {
                $products[$row->product_id] = $row->product_name;
            }
        }
        
        // Fallback query using service_id for backward compatibility
        if (empty($products)) {
            $service_results = $wpdb->get_results($wpdb->prepare(
                "SELECT DISTINCT sa.service_id, p.post_title AS service_name 
                 FROM {$wpdb->prefix}sod_staff_availability AS sa
                 JOIN {$wpdb->posts} AS p ON sa.service_id = p.ID
                 WHERE sa.staff_id = %d AND p.post_type = 'product' AND p.post_status = 'publish'",
                $staff_id
            ));

            if (!empty($service_results)) {
                foreach ($service_results as $row) {
                    $products[$row->service_id] = $row->service_name;
                }
            }
        }
        
        return $products;
    }
    
    /**
     * AJAX handler to get products for a staff member
     */
    public function get_staff_products() {
        // Check nonce for security
        check_ajax_referer('sod_availability_nonce_action', 'nonce');

        // Get and validate staff ID
        $staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;

        // Log the incoming request for debugging
        error_log("get_staff_products called with staff_id: $staff_id");

        if (!$staff_id) {
            wp_send_json_error('Invalid staff ID');
            return;
        }

        // Get products using the existing method
        $products = $this->get_staff_products_from_availability($staff_id);

        wp_send_json_success(array('products' => $products));
    }

    /**
     * Add product_id column if needed
     */
    private function check_and_update_database_schema() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sod_staff_availability';

        // Check if the table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            // Try the hardcoded name if not found
            $table_name = 'wp_3be9vb_sod_staff_availability'; 

            if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
                error_log("Staff availability table not found");
                return false;
            }
        }

        // Check if product_id column exists
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'product_id'");

        if (empty($columns)) {
            error_log("Adding product_id column to {$table_name} table");

            // Add product_id column if it doesn't exist
            $result = $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN product_id BIGINT(20) NULL");

            if ($result === false) {
                error_log("Failed to add product_id column: " . $wpdb->last_error);
                return false;
            }

            error_log("Successfully added product_id column to {$table_name}");

            // Optional: Add index for performance
            $wpdb->query("ALTER TABLE {$table_name} ADD INDEX idx_product_id (product_id)");

            // Migrate data from service_id to product_id
            $wpdb->query("UPDATE {$table_name} SET product_id = service_id WHERE product_id IS NULL AND service_id IS NOT NULL");

            return true;
        }

        error_log("product_id column already exists in {$table_name}");
        return true;
    }

    /**
     * AJAX handler to delete an availability slot
     */
    public function delete_availability_slot() {
        check_ajax_referer('sod_availability_nonce_action', 'nonce');

        $staff_id = isset($_POST['staff_post_id']) ? intval($_POST['staff_post_id']) : 0;
        $availability_id = isset($_POST['availability_id']) ? intval($_POST['availability_id']) : 0;

        if (!$staff_id || !$availability_id) {
            wp_send_json_error(__('Invalid staff or availability ID', 'spark-of-divine-scheduler'));
            return;
        }

        global $wpdb;

        // First, verify the slot exists and belongs to the specified staff
        $slot = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sod_staff_availability WHERE availability_id = %d AND staff_id = %d",
            $availability_id, $staff_id
        ));

        if (!$slot) {
            wp_send_json_error(__('Availability slot not found or does not belong to this staff.', 'spark-of-divine-scheduler'));
            return;
        }

        // Begin transaction for consistency
        $wpdb->query('START TRANSACTION');

        try {
            // Delete the specific slot
            $result = $wpdb->delete(
                $wpdb->prefix . 'sod_staff_availability',
                array('availability_id' => $availability_id, 'staff_id' => $staff_id),
                array('%d', '%d')
            );

            if ($result === false) {
                throw new Exception(__('Failed to delete availability slot', 'spark-of-divine-scheduler'));
            }

            $wpdb->query('COMMIT');
            wp_send_json_success(__('Availability slot deleted successfully', 'spark-of-divine-scheduler'));
        } 
        catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log("Failed to delete availability slot ID $availability_id for staff ID $staff_id: " . $e->getMessage());
            wp_send_json_error(__('Failed to delete availability slot. Please try again.', 'spark-of-divine-scheduler'));
        }
    }
    
    /**
     * Bulk delete selected availability slots
     */
    public function bulk_delete_slots() {
        check_ajax_referer('sod_availability_nonce_action', 'nonce');

        if (!current_user_can('administrator')) {
            wp_send_json_error(array('message' => __('You do not have permission to delete slots.', 'spark-of-divine-scheduler')));
            return;
        }

        // Get and validate staff ID
        $staff_id = isset($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;
        if (!$staff_id) {
            wp_send_json_error(array('message' => __('Invalid staff ID.', 'spark-of-divine-scheduler')));
            return;
        }

        // Get and validate selected slot IDs
        $slot_ids = isset($_POST['slot_ids']) ? $_POST['slot_ids'] : array();
        if (empty($slot_ids) || !is_array($slot_ids)) {
            wp_send_json_error(array('message' => __('No slots selected for deletion.', 'spark-of-divine-scheduler')));
            return;
        }

        // Sanitize slot IDs
        $slot_ids = array_map('intval', $slot_ids);
        
        // Create placeholders for SQL IN clause
        $placeholders = implode(',', array_fill(0, count($slot_ids), '%d'));
        
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        
        try {
            // Prepare query parameters: staff ID first, then all slot IDs
            $params = array_merge(array($staff_id), $slot_ids);
            
            // Delete selected slots
            $query = $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}sod_staff_availability 
                 WHERE staff_id = %d AND availability_id IN ($placeholders)",
                $params
            );
            
            $result = $wpdb->query($query);
            
            if ($result === false) {
                throw new Exception($wpdb->last_error);
            }
            
            $wpdb->query('COMMIT');
            
            wp_send_json_success(array(
                'message' => sprintf(__('%d availability slots deleted successfully.', 'spark-of-divine-scheduler'), $result),
                'deleted_count' => $result
            ));
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log("Error in bulk_delete_slots: " . $e->getMessage());
            wp_send_json_error(array('message' => __('Failed to delete slots. Please try again.', 'spark-of-divine-scheduler')));
        }
    }

    /**
     * Bulk delete availability slots by filter
     */
    public function bulk_delete_availability_slots() {
        if (!check_ajax_referer('sod_availability_nonce_action', 'sod_availability_nonce', false)) {
            wp_send_json_error(array('message' => __('Security verification failed.', 'spark-of-divine-scheduler')));
            return;
        }

        if (!current_user_can('administrator') && !current_user_can('manage_availability')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'spark-of-divine-scheduler')));
            return;
        }

        $staff_id = isset($_POST['staff_post_id']) ? intval($_POST['staff_post_id']) : 0;
        if (!$staff_id || !get_post($staff_id) || get_post_type($staff_id) !== 'sod_staff') {
            error_log("Invalid staff_post_id for bulk delete: $staff_id");
            wp_send_json_error(array('message' => __('Invalid staff member.', 'spark-of-divine-scheduler')));
            return;
        }

        $filter_type = isset($_POST['filter_type']) ? sanitize_text_field($_POST['filter_type']) : '';
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $month = isset($_POST['month']) ? sanitize_text_field($_POST['month']) : '';
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';

        if (empty($filter_type)) {
            wp_send_json_error(array('message' => __('Please select a filter type.', 'spark-of-divine-scheduler')));
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'sod_staff_availability';
        $query = "DELETE FROM $table_name WHERE staff_id = %d";
        $params = array($staff_id);

        switch ($filter_type) {
            case 'product':
                if (!$product_id) {
                    wp_send_json_error(array('message' => __('Please select a product.', 'spark-of-divine-scheduler')));
                    return;
                }
                
                // Check if this product exists for this staff
                $product_exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name WHERE staff_id = %d AND product_id = %d",
                    $staff_id, $product_id
                ));
                
                if (!$product_exists) {
                    wp_send_json_error(array('message' => __('Selected product is not associated with this staff member.', 'spark-of-divine-scheduler')));
                    return;
                }
                
                $query .= " AND product_id = %d";
                $params[] = $product_id;
                break;

            case 'month':
                if (empty($month) || !preg_match('/^\d{4}-\d{2}$/', $month)) {
                    wp_send_json_error(array('message' => __('Please enter a valid month (YYYY-MM).', 'spark-of-divine-scheduler')));
                    return;
                }
                $start = date('Y-m-01', strtotime($month));
                $end = date('Y-m-t', strtotime($month));
                $query .= " AND date BETWEEN %s AND %s";
                $params[] = $start;
                $params[] = $end;
                break;

            case 'range':
                if (empty($start_date) || empty($end_date)) {
                    wp_send_json_error(array('message' => __('Please specify a date range.', 'spark-of-divine-scheduler')));
                    return;
                }
                if (strtotime($start_date) > strtotime($end_date)) {
                    wp_send_json_error(array('message' => __('Start date cannot be after end date.', 'spark-of-divine-scheduler')));
                    return;
                }
                $query .= " AND date BETWEEN %s AND %s";
                $params[] = $start_date;
                $params[] = $end_date;
                break;

            default:
                wp_send_json_error(array('message' => __('Unknown filter type.', 'spark-of-divine-scheduler')));
                return;
        }

        $wpdb->query('START TRANSACTION');
        try {
            $prepared_query = $wpdb->prepare($query, $params);
            $result = $wpdb->query($prepared_query);
            if ($result === false) {
                throw new Exception($wpdb->last_error);
            }
            $deleted_count = $result;
            $staff_name = get_the_title($staff_id);

            $wpdb->query('COMMIT');
            wp_send_json_success(array(
                'message' => sprintf(__('%d availability slots deleted successfully for %s.', 'spark-of-divine-scheduler'), $deleted_count, $staff_name),
                'deleted_count' => $deleted_count
            ));
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log("Bulk delete failed for staff ID $staff_id: " . $e->getMessage());
            wp_send_json_error(array('message' => __('Failed to delete availability slots: ', 'spark-of-divine-scheduler') . $e->getMessage()));
        }
    }

    /**
     * Generate recurring slots based on schedule type
     */
    private function generate_recurring_slots($start_date, $end_date, $slot) {
        $dates = [];
        $current = new DateTime($start_date);
        $end = new DateTime($end_date);

        // Extract all properties from the slot object
        $day_of_week = $slot->day_of_week;
        $recurring_type = $slot->recurring_type;
        $recurring_end_date = $slot->recurring_end_date;
        $biweekly_pattern = $slot->biweekly_pattern; 
        $skip_5th_week = $slot->skip_5th_week; 
        $monthly_day = $slot->monthly_day;
        $monthly_occurrence = $slot->monthly_occurrence;

        error_log("Processing recurring slot: Type=$recurring_type, Day=$day_of_week, Monthly Day=$monthly_day, Occurrence=$monthly_occurrence");

        while ($current <= $end) {
            $current_day = $current->format('l'); // Full day name (Monday, Tuesday, etc.)
            $current_date = $current->format('Y-m-d');
            $day_of_month = (int)$current->format('j'); // Day of month (1-31)
            $week_of_month = ceil($day_of_month / 7); // Which week of the month (1-5)

            $include_date = false;

            // Weekly slots - just match the day of week
            if ($recurring_type === 'weekly' && $current_day === $day_of_week) {
                $include_date = true;
            }
            // Biweekly slots - match day of week and biweekly pattern
            else if ($recurring_type === 'biweekly' && $current_day === $day_of_week) {
                if ($biweekly_pattern === '1st_3rd' && ($week_of_month == 1 || $week_of_month == 3)) {
                    $include_date = true;
                } 
                else if ($biweekly_pattern === '2nd_4th' && ($week_of_month == 2 || $week_of_month == 4)) {
                    $include_date = true;
                }

                // Special handling for 5th week of the month
                if ($week_of_month == 5) {
                    if ($skip_5th_week == 1) {
                        $include_date = false; // Skip 5th week if explicitly set
                    } 
                    // Otherwise include it if it matches the pattern
                }
            }
            // Monthly slots - match by occurrence and day
            else if ($recurring_type === 'monthly' && $monthly_day && $monthly_occurrence) {
                if ($current_day === $monthly_day) {
                    // Calculate which occurrence this is
                    $temp_date = clone $current;
                    $temp_date->modify('first day of this month');

                    // Count which occurrence of this day in the month this is
                    $occurrence_count = 0;
                    $last_occurrence_date = null;

                    while ($temp_date->format('m') === $current->format('m')) {
                        if ($temp_date->format('l') === $monthly_day) {
                            $occurrence_count++;
                            $last_occurrence_date = clone $temp_date;
                        }
                        $temp_date->modify('+1 day');
                    }

                    // Reset to beginning of month to find specific occurrences
                    $temp_date = clone $current;
                    $temp_date->modify('first day of this month');

                    $this_occurrence_num = 0;
                    while ($temp_date <= $current) {
                        if ($temp_date->format('l') === $monthly_day) {
                            $this_occurrence_num++;
                        }
                        $temp_date->modify('+1 day');
                    }

                    // Check if this matches the requested occurrence
                    if ($monthly_occurrence === '1st' && $this_occurrence_num === 1) {
                        $include_date = true;
                    }
                    else if ($monthly_occurrence === '2nd' && $this_occurrence_num === 2) {
                        $include_date = true;
                    }
                    else if ($monthly_occurrence === '3rd' && $this_occurrence_num === 3) {
                        $include_date = true;
                    }
                    else if ($monthly_occurrence === '4th' && $this_occurrence_num === 4) {
                        $include_date = true;
                    }
                    else if ($monthly_occurrence === 'last' && $current->format('Y-m-d') === $last_occurrence_date->format('Y-m-d')) {
                        $include_date = true;
                    }
                }
            }

            // Don't include dates after the recurring end date
            if ($include_date && !empty($recurring_end_date)) {
                $include_date = $current_date <= $recurring_end_date;
            }

            if ($include_date) {
                $dates[] = $current_date;
            }

            $current->modify('+1 day');
        }

        return $dates;
    }

    /**
     * Sync staff with custom table when post is saved
     */
    public function sync_staff_with_custom_table($post_id, $post, $update) {
        error_log("Sync staff method called for post ID: " . $post_id);

        if ($post->post_type !== 'sod_staff') {
            error_log("Not a sod_staff post type. Exiting sync method.");
            return;
        }

        $user_id = get_post_meta($post_id, 'sod_staff_user_id', true);
        if (empty($user_id)) {
            error_log('Sync Error: Missing user ID for post ID: ' . $post_id);
            return;
        }

        // Check if the user exists
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            error_log("User with ID $user_id does not exist. Cannot sync staff.");
            return;
        }

        $phone = get_post_meta($post_id, 'sod_staff_phone', true);
        $accepts_cash = get_post_meta($post_id, 'sod_staff_accepts_cash', true) ? 1 : 0;

        global $wpdb;
        $table_name = $wpdb->prefix . 'sod_staff';

        $existing_staff = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d AND staff_id = %d",
            $user_id, $post_id
        ));

        $staff_data = array(
            'user_id' => $user_id,
            'phone_number' => $phone,
            'accepts_cash' => $accepts_cash
        );

        if ($existing_staff) {
            // Update existing record
            error_log("Updating existing staff record for user ID: $user_id. Staff ID: $post_id");
            $result = $wpdb->update(
                $table_name,
                $staff_data,
                array('user_id' => $user_id, 'staff_id' => $post_id),
                array('%d', '%s', '%d'),
                array('%d', '%d')
            );
        } else {
            // Insert new record
            error_log("Inserting new staff record for user ID: $user_id");
            $staff_data['staff_id'] = $post_id;
            $result = $wpdb->insert(
                $table_name,
                $staff_data,
                array('%d', '%d', '%s', '%d')
            );
        }

        if ($result === false) {
            error_log('Failed to sync staff member in custom table. SQL Error: ' . $wpdb->last_error);
        } else {
            error_log('Successfully synced staff member in custom table for post ID: ' . $post_id);
        }
    }

    /**
     * Get valid staff ID from current user or selected staff
     */
    private function get_valid_staff_id($user_id, $selected_staff_id = 0) {
        global $wpdb;

        // Staff user: Use their own staff post ID
        if (in_array('staff', wp_get_current_user()->roles)) {
            $staff_post_id = $this->get_or_create_staff_post_for_user($user_id);
            if (!$staff_post_id) {
                error_log("No valid staff post ID for user ID: $user_id");
                return false;
            }
            return $staff_post_id;
        }

        // Admin user: Use selected staff ID if valid, otherwise fallback to their own
        if (current_user_can('administrator')) {
            if ($selected_staff_id && get_post($selected_staff_id) && get_post_type($selected_staff_id) === 'sod_staff') {
                return $selected_staff_id;
            }
            $staff_post_id = $this->get_or_create_staff_post_for_user($user_id);
            if (!$staff_post_id) {
                error_log("No valid staff post ID for admin user ID: $user_id");
                return false;
            }
            return $staff_post_id;
        }

        // No valid context
        error_log("No valid staff ID context for user ID: $user_id");
        return false;
    }

    /**
     * Get or create staff post for a user
     */
    private function get_or_create_staff_post_for_user($user_id) {
        // Check if a staff post already exists for this user
        $existing_post_id = $this->get_staff_post_id_by_user($user_id);
        if ($existing_post_id) {
            return $existing_post_id;
        }

        return $this->create_staff_post_for_user($user_id);
    }
    
    /**
     * Create a new staff post for a user
     */
    private function create_staff_post_for_user($user_id) {
        // Clear old metadata associated with this user ID
        $this->clear_old_staff_meta($user_id);

        // Check if a staff post already exists for this user
        $existing_post_id = $this->get_staff_post_id_by_user($user_id);
        if ($existing_post_id) {
            error_log("Existing staff post found for user ID: $user_id. Post ID: $existing_post_id");
            return $existing_post_id;
        }

        // Create a new staff post
        $post_id = wp_insert_post(array(
            'post_title' => get_user_by('ID', $user_id)->display_name,
            'post_type' => 'sod_staff',
            'post_status' => 'publish',
            'meta_input' => array(
                'sod_staff_user_id' => $user_id,
                'sod_staff_phone' => get_user_meta($user_id, 'staff_phone', true),
                'sod_staff_email' => get_user_meta($user_id, 'staff_email', true),
            )
        ));

        // Link the staff post ID with the custom table staff ID
        if ($post_id !== 0 && !is_wp_error($post_id)) {
            error_log("New staff post created with ID: $post_id for user ID: $user_id");
            global $wpdb;
            $wpdb->insert("{$wpdb->prefix}sod_staff", array(
                'staff_id' => $post_id,
                'user_id' => $user_id,
                'phone_number' => get_user_meta($user_id, 'staff_phone', true),
                'accepts_cash' => 0
            ), array('%d', '%d', '%s', '%d'));
            return $post_id;
        } else {
            error_log("Failed to create staff post. Error: " . print_r($post_id, true));
            return false;
        }
    }
    
    /**
     * Clear old staff metadata for a user
     */
    private function clear_old_staff_meta($user_id) {
        global $wpdb;
        $post_meta_table = $wpdb->prefix . 'postmeta';
        $wpdb->delete($post_meta_table, array('meta_key' => 'sod_staff_user_id', 'meta_value' => $user_id), array('%s', '%d'));
        error_log("Cleared old staff meta data for user ID: $user_id");
    }
}

// Initialize the SOD_Staff_Availability_Form class
new SOD_Staff_Availability_Form();