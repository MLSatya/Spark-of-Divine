<?php
/**
 * Template Name: Product-Centric Schedule Template
 *
 * Customer-facing schedule display using WooCommerce products exclusively.
 * Located in theme root folder - includes proper plugin integration
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

global $wpdb;

// ==================== PLUGIN INTEGRATION CHECK ====================

// Check if SOD plugin is active and loaded
if (!defined('SOD_PLUGIN_PATH') || !class_exists('SOD_Plugin_Initializer')) {
    echo '<div class="sod-error" style="background: #ffe6e6; padding: 20px; margin: 20px; border: 1px solid #ff0000; color: #cc0000;">';
    echo '<h3>Plugin Not Available</h3>';
    echo '<p>The Spark of Divine Scheduler plugin is not active or not properly loaded.</p>';
    echo '<p>Please ensure the plugin is activated in WordPress admin.</p>';
    echo '</div>';
    get_footer();
    return;
}

// Ensure the filter handler is loaded
if (!function_exists('sod_get_filter_handler')) {
    // Define the function if it doesn't exist
    function sod_get_filter_handler() {
        global $sod_filter_handler;
        
        if (isset($sod_filter_handler)) {
            return $sod_filter_handler;
        }
        
        // Try to get from plugin components
        if (class_exists('SOD_Plugin_Initializer')) {
            $plugin = SOD_Plugin_Initializer::get_instance();
            if (isset($plugin->components['filter_handler'])) {
                return $plugin->components['filter_handler'];
            }
        }
        
        // Try to load the filter handler class manually
        $filter_handler_file = SOD_PLUGIN_PATH . 'includes/core/class-sod-schedule-filter-handler.php';
        if (file_exists($filter_handler_file) && !class_exists('SOD_Schedule_Filter_Handler')) {
            require_once $filter_handler_file;
        }
        
        // Try direct instantiation
        if (class_exists('SOD_Schedule_Filter_Handler')) {
            return SOD_Schedule_Filter_Handler::get_instance();
        }
        
        return null;
    }
}

// Get filter handler instance
$filter_handler = sod_get_filter_handler();

if (!$filter_handler) {
    echo '<div class="sod-error" style="background: #ffe6e6; padding: 20px; margin: 20px; border: 1px solid #ff0000; color: #cc0000;">';
    echo '<h3>Filter System Not Available</h3>';
    echo '<p>The scheduling filter system could not be loaded.</p>';
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        echo '<div style="margin-top: 15px; padding: 10px; background: #f0f0f0; border: 1px solid #ccc;">';
        echo '<strong>Debug Information:</strong><br>';
        echo 'Plugin Path: ' . (defined('SOD_PLUGIN_PATH') ? SOD_PLUGIN_PATH : 'NOT DEFINED') . '<br>';
        echo 'Filter Handler File: ' . (defined('SOD_PLUGIN_PATH') ? SOD_PLUGIN_PATH . 'includes/core/class-sod-schedule-filter-handler.php' : 'PLUGIN PATH NOT DEFINED') . '<br>';
        echo 'Filter Handler Class Exists: ' . (class_exists('SOD_Schedule_Filter_Handler') ? 'YES' : 'NO') . '<br>';
        echo 'Plugin Class Exists: ' . (class_exists('SOD_Plugin_Initializer') ? 'YES' : 'NO') . '<br>';
        echo '</div>';
    }
    
    echo '</div>';
    get_footer();
    return;
}

// Ensure CSS is loaded for the filter system
if (!wp_style_is('sod-filter', 'enqueued') && !wp_style_is('sod-schedule-filter', 'enqueued')) {
    wp_enqueue_style(
        'sod-schedule-filter', 
        SOD_PLUGIN_URL . 'assets/css/sod-filter.css', 
        [], 
        SOD_PLUGIN_VERSION
    );
}

// ==================== HELPER FUNCTIONS ====================

/**
 * Debug helper function
 */
function sod_debug_log($message, $data = null) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("[SOD Schedule] " . $message);
        if ($data !== null) {
            error_log("[SOD Schedule Data] " . print_r($data, true));
        }
    }
}

/**
 * Get booking attributes from custom table by product ID
 */
function get_product_booking_attributes($product_id) {
    global $wpdb;
    
    if (!$product_id) {
        return [];
    }
    
    // Check both possible table names
    $attributes_table = $wpdb->prefix . 'sod_service_attributes';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$attributes_table}'") !== $attributes_table) {
        $attributes_table = 'wp_3be9vb_sod_service_attributes';
    }
    
    $query = "SELECT attribute_type, value, price, product_id, variation_id 
              FROM {$attributes_table} 
              WHERE product_id = %d
              ORDER BY attribute_type, value";
    
    $attributes = $wpdb->get_results($wpdb->prepare($query, $product_id));
    
    return $attributes ?: [];
}

/**
 * Render booking form for a time slot
 */
function render_product_booking_form($slot, $date) {
    global $wpdb;
    
    $product_id = $slot->product_id;
    if (!$product_id) {
        echo '<p class="booking-error">Product information unavailable</p>';
        return;
    }
    
    // Get product details from WooCommerce
    $product = wc_get_product($product_id);
    if (!$product) {
        echo '<p class="booking-error">Product not found</p>';
        return;
    }
    
    // Get attributes
    $attributes = get_product_booking_attributes($product_id);
    $duration_attributes = [];
    $passes_attributes = [];
    $package_attributes = [];
    
    foreach ($attributes as $attr) {
        switch ($attr->attribute_type) {
            case 'duration':
                $duration_attributes[] = $attr;
                break;
            case 'passes':
                $passes_attributes[] = $attr;
                break;
            case 'package':
                $package_attributes[] = $attr;
                break;
        }
    }
    
    $has_durations = !empty($duration_attributes);
    $has_passes = !empty($passes_attributes);
    $has_packages = !empty($package_attributes);
    $is_yoga_class = stripos($product->get_name(), 'yoga all levels') !== false;
    
    // Sort attributes
    if ($has_durations) {
        usort($duration_attributes, function($a, $b) {
            $a_value = (int)preg_replace('/[^0-9]/', '', $a->value);
            $b_value = (int)preg_replace('/[^0-9]/', '', $b->value);
            return $a_value - $b_value;
        });
    }
    ?>
    
    <form method="post" action="<?php echo admin_url('admin-ajax.php'); ?>" class="booking-form">
        <input type="hidden" name="action" value="sod_submit_booking">
        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('sod_booking_nonce'); ?>">
        <input type="hidden" name="product_id" value="<?php echo esc_attr($product_id); ?>" class="default-product-id">
        <input type="hidden" name="staff_id" value="<?php echo esc_attr($slot->staff_id); ?>">
        <input type="hidden" name="staff" value="<?php echo esc_attr($slot->staff_id); ?>">
        <input type="hidden" name="date" value="<?php echo esc_attr($date); ?>">
        <input type="hidden" name="booking_date" value="<?php echo esc_attr($date); ?>">
        
        <?php if ($has_durations && !$is_yoga_class): ?>
            <div class="booking-form-row">
                <select name="attribute" class="attribute-select" required>
                    <option value=""><?php _e('Select duration', 'spark-of-divine-scheduler'); ?></option>
                    <?php foreach ($duration_attributes as $attr): 
                        $attribute_json = json_encode(['type' => 'duration', 'value' => $attr->value]);
                        $duration_minutes = (int)preg_replace('/[^0-9]/', '', $attr->value);
                        $display_text = "$duration_minutes minutes" . ($attr->price ? ' ($' . number_format((float)$attr->price, 2) . ')' : '');
                    ?>
                        <option value='<?php echo esc_attr($attribute_json); ?>' 
                                data-duration="<?php echo esc_attr($duration_minutes); ?>"
                                <?php if ($attr->product_id): ?>data-product-id="<?php echo esc_attr($attr->product_id); ?>"<?php endif; ?>
                                <?php if ($attr->variation_id): ?>data-variation-id="<?php echo esc_attr($attr->variation_id); ?>"<?php endif; ?>>
                            <?php echo esc_html($display_text); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="booking-form-row">
                <select name="timeslot" required>
                    <option value=""><?php _e('Select a time', 'spark-of-divine-scheduler'); ?></option>
                </select>
            </div>
        <?php elseif ($has_passes && !$has_durations): ?>
            <div class="booking-form-row">
                <select name="attribute" class="attribute-select" required>
                    <option value=""><?php _e('Select passes', 'spark-of-divine-scheduler'); ?></option>
                    <?php foreach ($passes_attributes as $attr): 
                        $attribute_json = json_encode(['type' => 'passes', 'value' => $attr->value]);
                        $display_text = $attr->value . ($attr->price ? ' ($' . number_format((float)$attr->price, 2) . ')' : '');
                    ?>
                        <option value='<?php echo esc_attr($attribute_json); ?>'
                                <?php if ($attr->product_id): ?>data-product-id="<?php echo esc_attr($attr->product_id); ?>"<?php endif; ?>
                                <?php if ($attr->variation_id): ?>data-variation-id="<?php echo esc_attr($attr->variation_id); ?>"<?php endif; ?>>
                            <?php echo esc_html($display_text); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="hidden" name="timeslot" value="<?php echo esc_attr($slot->start_time); ?>">
        <?php elseif ($has_packages && !$has_durations): ?>
            <div class="booking-form-row">
                <select name="attribute" class="attribute-select" required>
                    <option value=""><?php _e('Select package', 'spark-of-divine-scheduler'); ?></option>
                    <?php foreach ($package_attributes as $attr): 
                        $attribute_json = json_encode(['type' => 'package', 'value' => $attr->value]);
                        $display_text = $attr->value . ($attr->price ? ' ($' . number_format((float)$attr->price, 2) . ')' : '');
                    ?>
                        <option value='<?php echo esc_attr($attribute_json); ?>'
                                <?php if ($attr->product_id): ?>data-product-id="<?php echo esc_attr($attr->product_id); ?>"<?php endif; ?>
                                <?php if ($attr->variation_id): ?>data-variation-id="<?php echo esc_attr($attr->variation_id); ?>"<?php endif; ?>>
                            <?php echo esc_html($display_text); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="hidden" name="timeslot" value="<?php echo esc_attr($slot->start_time); ?>">
        <?php else: ?>
            <!-- Simple product without attributes -->
            <input type="hidden" name="attribute" value='<?php echo esc_attr(json_encode(['type' => 'product', 'value' => 'standard'])); ?>'>
            <input type="hidden" name="timeslot" value="<?php echo esc_attr($slot->start_time); ?>">
        <?php endif; ?>
        
        <div class="booking-form-row">
            <button type="submit" class="book-now"><?php _e('BOOK', 'spark-of-divine-scheduler'); ?></button>
        </div>
    </form>
    <?php
}

/**
 * Find next occurrence for recurring slots
 */
function find_next_occurrence($slot, $start_date, $end_date) {
    $day_map = [
        'Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4,
        'Friday' => 5, 'Saturday' => 6, 'Sunday' => 7
    ];
    
    if (empty($slot->day_of_week) || !isset($day_map[$slot->day_of_week])) {
        return null;
    }
    
    $target_day = $day_map[$slot->day_of_week];
    $current = clone $start_date;
    
    while ($current <= $end_date) {
        if ((int)$current->format('N') === $target_day) {
            if (!$slot->recurring_end_date || $current->format('Y-m-d') <= $slot->recurring_end_date) {
                return $current;
            }
        }
        $current->modify('+1 day');
    }
    
    return null;
}

// ==================== MAIN SCHEDULE LOGIC ====================

// Initialize debugging
sod_debug_log("Starting product-centric schedule template with filter handler");

// Get current filters from the handler (no authentication required)
$filters = $filter_handler->get_filters();
$view = $filters['view'];
$current_date = $filters['date'];
$product_filter = $filters['product'];
$staff_filter = $filters['staff'];
$category_filter = $filters['category'];

// Debug logging
sod_debug_log("Filters from handler", $filters);

// Get query conditions from filter handler (public access)
$query_data = $filter_handler->get_query_conditions();
$date_range = $query_data['date_range'];
$start_date = $date_range['start'];
$end_date = $date_range['end'];

sod_debug_log("Date range: $start_date to $end_date");

// Query staff availability with products using filter conditions
$slots_table = $wpdb->prefix . 'sod_staff_availability';
$staff_table = $wpdb->prefix . 'sod_staff';

$query = "SELECT 
          sa.*,
          p.post_title AS product_name,
          p.post_content AS product_description,
          u.display_name AS staff_name,
          pm.meta_value AS product_price
        FROM {$slots_table} sa
        LEFT JOIN {$wpdb->posts} p ON sa.product_id = p.ID AND p.post_type = 'product' AND p.post_status = 'publish'
        LEFT JOIN {$staff_table} st ON sa.staff_id = st.staff_id
        LEFT JOIN {$wpdb->users} u ON st.user_id = u.ID
        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_price'
        WHERE sa.product_id IS NOT NULL
        AND sa.product_id > 0
        AND (" . implode(" AND ", $query_data['conditions']) . ")
        ORDER BY sa.date, sa.start_time";

$slots = $wpdb->get_results($wpdb->prepare($query, $query_data['params']));
sod_debug_log("Query fetched " . count($slots) . " slots");

// Process slots by date
$slots_by_date = [];

// For day view, we need to be more specific about the date range
if ($view === 'day') {
    $current_date_obj = new DateTime($start_date);
    $end_date_obj = new DateTime($start_date); // Same day for day view
} else {
    $current_date_obj = new DateTime($start_date);
    $end_date_obj = new DateTime($end_date);
}

foreach ($slots as $slot) {
    if (!empty($slot->date)) {
        // Specific date slots
        if (strtotime($slot->date) >= strtotime($start_date) && strtotime($slot->date) <= strtotime($end_date)) {
            $slots_by_date[$slot->date][] = $slot;
        }
    } elseif (!empty($slot->day_of_week)) {
        // Recurring slots
        if ($view === 'day') {
            // For day view, check if this day of week matches
            $day_name = $current_date_obj->format('l');
            if ($slot->day_of_week === $day_name) {
                $date_key = $current_date_obj->format('Y-m-d');
                $slots_by_date[$date_key][] = $slot;
            }
        } else {
            // For week view, find next occurrence
            $next_occurrence = find_next_occurrence($slot, $current_date_obj, $end_date_obj);
            if ($next_occurrence) {
                $date_key = $next_occurrence->format('Y-m-d');
                $slots_by_date[$date_key][] = $slot;
            }
        }
    }
}

sod_debug_log("Processed slots by date for view '$view': " . print_r(array_keys($slots_by_date), true));
?>

<div class="sod-schedule-container">
    <h2><?php _e('SPARK OF DIVINE CALENDAR', 'spark-of-divine-scheduler'); ?></h2>
    
    <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
    <!-- Debug info -->
    <div style="display:none;" class="debug-info">
        Current URL: <?php echo esc_html(home_url('/')); ?><br>
        Page ID: <?php echo get_the_ID(); ?><br>
        Is Page: <?php echo is_page() ? 'Yes' : 'No'; ?><br>
        Is Front Page: <?php echo is_front_page() ? 'Yes' : 'No'; ?><br>
        Filters: <?php echo esc_html(print_r($filters, true)); ?>
    </div>
    <?php endif; ?>
    
    <!-- View Selector -->
    <?php echo $filter_handler->render_view_selector(); ?>
    
    <!-- Calendar Navigation -->
    <?php echo $filter_handler->render_navigation(); ?>
    
    <!-- Filters Sidebar -->
    <?php echo $filter_handler->render_filter_form(); ?>
    
    <!-- Schedule Display -->
    <?php 
    sod_debug_log("About to display schedule - View type: " . $view);
    sod_debug_log("Slots by date: " . print_r(array_keys($slots_by_date), true));
    ?>
    
    <?php if ($view === 'week'): ?>
        <!-- Week View -->
        <div class="calendar-grid view-week" id="week-calendar">
            <div class="calendar-wrapper desktop-view">
                <div class="calendar-header">
                    <?php 
                    foreach (new DatePeriod(new DateTime($start_date), new DateInterval('P1D'), (new DateTime($end_date))->modify('+1 day')) as $date):
                        $dateKey = $date->format('Y-m-d');
                        $day_url = $filter_handler->build_url(['view' => 'day', 'date' => $dateKey]);
                    ?>
                        <a href="<?php echo esc_url($day_url); ?>" 
                           class="calendar-cell <?php echo ($date->format('N') > 5) ? 'weekend' : ''; ?>"
                           title="<?php echo esc_attr('View ' . $date->format('l, F j')); ?>">
                            <?php echo esc_html($date->format('D j')); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <div class="calendar-body">
                    <?php 
                    foreach (new DatePeriod(new DateTime($start_date), new DateInterval('P1D'), (new DateTime($end_date))->modify('+1 day')) as $date):
                        $dateKey = $date->format('Y-m-d');
                        $daySlots = $slots_by_date[$dateKey] ?? [];
                    ?>
                        <div class="calendar-cell <?php echo ($date->format('N') > 5) ? 'weekend' : ''; ?>">
                            <?php if (!empty($daySlots)): ?>
                                <div class="day-slots">
                                    <?php 
                                    usort($daySlots, fn($a, $b) => strtotime($a->start_time) - strtotime($b->start_time));
                                    foreach ($daySlots as $slot):
                                        $start_time = date('g:iA', strtotime($slot->start_time));
                                        $end_time = date('g:iA', strtotime($slot->end_time));
                                        $is_yoga_class = stripos($slot->product_name, 'yoga all levels') !== false;
                                        $slotClass = $is_yoga_class ? 'schedule-slot yoga-class' : 'schedule-slot';
                                        if ($slot->appointment_only) $slotClass .= ' appointment-only';
                                    ?>
                                        <div class="<?php echo esc_attr($slotClass); ?>">
                                            <span class="slot-info">
                                                <?php echo esc_html("$start_time-$end_time | "); ?>
                                                <a href="<?php echo get_permalink($slot->product_id); ?>" class="slot-service-title">
                                                    <?php echo esc_html($slot->product_name); ?>
                                                </a>
                                                <?php echo esc_html(" | {$slot->staff_name}"); ?>
                                            </span>
                                            <?php render_product_booking_form($slot, $dateKey); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p><?php _e('No slots', 'spark-of-divine-scheduler'); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Mobile View -->
            <div class="calendar-wrapper mobile-view" id="mobile-week-calendar" style="display: none;">
                <?php 
                foreach (new DatePeriod(new DateTime($start_date), new DateInterval('P1D'), (new DateTime($end_date))->modify('+1 day')) as $date):
                    $dateKey = $date->format('Y-m-d');
                    $daySlots = $slots_by_date[$dateKey] ?? [];
                ?>
                    <div class="mobile-day-section">
                        <h3 class="mobile-day-label"><?php echo esc_html($date->format('l')); ?></h3>
                        <div class="mobile-day-slots">
                            <?php if (!empty($daySlots)): ?>
                                <?php 
                                usort($daySlots, fn($a, $b) => strtotime($a->start_time) - strtotime($b->start_time));
                                foreach ($daySlots as $slot):
                                    $start_time = date('g:iA', strtotime($slot->start_time));
                                    $end_time = date('g:iA', strtotime($slot->end_time));
                                    $is_yoga_class = stripos($slot->product_name, 'yoga all levels') !== false;
                                    $slotClass = $is_yoga_class ? 'schedule-slot yoga-class' : 'schedule-slot';
                                    if ($slot->appointment_only) $slotClass .= ' appointment-only';
                                ?>
                                    <div class="<?php echo esc_attr($slotClass); ?>">
                                        <span class="slot-info">
                                            <?php echo esc_html("$start_time-$end_time | "); ?>
                                            <a href="<?php echo get_permalink($slot->product_id); ?>" class="slot-service-title">
                                                <?php echo esc_html($slot->product_name); ?>
                                            </a>
                                            <?php echo esc_html(" | {$slot->staff_name}"); ?>
                                        </span>
                                        <?php render_product_booking_form($slot, $dateKey); ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p><?php _e('No slots', 'spark-of-divine-scheduler'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <!-- Day View -->
        <?php sod_debug_log("Rendering day view for date: " . $start_date); ?>
        <div class="day-view-list">
            <div class="day-header">
                <h3><?php echo esc_html(date('l, F j, Y', strtotime($start_date))); ?></h3>
            </div>
            <?php 
            $day_slots = isset($slots_by_date[$start_date]) ? $slots_by_date[$start_date] : [];
            sod_debug_log("Day slots count: " . count($day_slots));
            
            if (!empty($day_slots)):
                usort($day_slots, fn($a, $b) => strtotime($a->start_time) - strtotime($b->start_time));
            ?>
                <div class="day-slots-list">
                    <?php foreach ($day_slots as $slot):
                        $start_time = date('g:iA', strtotime($slot->start_time));
                        $is_yoga_class = stripos($slot->product_name, 'yoga all levels') !== false;
                        $slotClass = $is_yoga_class ? 'day-slot-item yoga-class' : 'day-slot-item';
                        if ($slot->appointment_only) $slotClass .= ' appointment-only';
                    ?>
                        <div class="<?php echo esc_attr($slotClass); ?>">
                            <div class="slot-time-column">
                                <span class="slot-time"><?php echo esc_html($start_time); ?></span>
                            </div>
                            <div class="slot-details-column">
                                <h4 class="slot-service-title">
                                    <a href="<?php echo get_permalink($slot->product_id); ?>">
                                        <?php echo esc_html($slot->product_name); ?>
                                    </a>
                                </h4>
                                <div class="slot-meta">
                                    <span class="slot-staff"><?php _e('With:', 'spark-of-divine-scheduler'); ?> <?php echo esc_html($slot->staff_name); ?></span>
                                    <?php if (!empty($slot->product_description)): ?>
                                        <div class="slot-description">
                                            <?php echo wp_kses_post(wpautop($slot->product_description)); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="slot-booking-column">
                                <?php render_product_booking_form($slot, $start_date); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-slots-message">
                    <p><?php _e('No services available on this day.', 'spark-of-divine-scheduler'); ?></p>
                    <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
                        <p style="font-size: 0.8em; color: #666;">
                            Debug: Looking for slots on <?php echo esc_html($start_date); ?><br>
                            Total slots found: <?php echo count($slots); ?><br>
                            Dates with slots: <?php echo esc_html(implode(', ', array_keys($slots_by_date))); ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php get_footer(); ?>