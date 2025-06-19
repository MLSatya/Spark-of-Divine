<?php
/**
 * SOD_Schedule_Display Class
 * 
 * Handles the logic for displaying schedules and booking forms
 */
class SOD_Schedule_Display {
    // Database tables
    private $slots_table;
    private $services_table;
    private $staff_table;
    private $users_table;
    private $term_relationships;
    private $term_taxonomy;
    private $terms_table;
    
    // Filters and view settings
    private $view;
    private $date_param;
    private $service_filter;
    private $staff_filter;
    private $category_filter;
    
    // Date ranges
    private $start_date;
    private $end_date;
    private $prev_date;
    private $next_date;
    
    // Data storage
    private $slots = [];
    private $slots_by_date = [];
    private $all_services = [];
    private $all_staff = [];
    private $all_categories = [];
    
    /**
     * Constructor - initialize with filters
     */
    public function __construct($view = 'week', $date_param = null, $service_filter = 0, $staff_filter = 0, $category_filter = 0) {
        global $wpdb;
        
        // shortcode initialization
        if (class_exists('SOD_Schedule_Display')) {
            SOD_Schedule_Display::register_shortcode();
        }

        // Set table names
        $this->slots_table = $wpdb->prefix . 'sod_staff_availability';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->slots_table}'") !== $this->slots_table) {
            $this->slots_table = 'wp_3be9vb_sod_staff_availability'; // Fallback
        }
        
        $this->services_table = $wpdb->prefix . 'sod_services';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->services_table}'") !== $this->services_table) {
            $this->services_table = 'wp_3be9vb_sod_services'; // Fallback
        }
        
        $this->staff_table = $wpdb->prefix . 'sod_staff';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->staff_table}'") !== $this->staff_table) {
            $this->staff_table = 'wp_3be9vb_sod_staff'; // Fallback
        }
        
        $this->users_table = $wpdb->users;
        $this->term_relationships = $wpdb->term_relationships;
        $this->term_taxonomy = $wpdb->term_taxonomy;
        $this->terms_table = $wpdb->terms;
        
        // Set filters and views
        $allowed_views = ['day', 'week', 'month', 'year'];
        $this->view = in_array($view, $allowed_views) ? $view : 'week';
        $this->date_param = $date_param ?: date('Y-m-d');
        $this->service_filter = intval($service_filter);
        $this->staff_filter = intval($staff_filter);
        $this->category_filter = intval($category_filter);
        
        // Initialize everything
        $this->calculate_date_ranges();
        $this->fetch_filters();
        $this->fetch_slots();
        
        // Handle form submissions
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sod_filter_action'])) {
            $this->handle_filter_submission();
        }
    }
    
    /**
     * Enqueue jQuery UI for the datepicker functionality
     */

    
    /**
     * Add this method to your SOD_Schedule_Display class
     */
    public static function register_shortcode() {
        add_shortcode('sod_schedule', [__CLASS__, 'shortcode_callback']);
    }

    /**
     * Shortcode callback method
     */
    public static function shortcode_callback($atts) {
        // Parse attributes
        $atts = shortcode_atts(array(
            'view' => 'week',
            'date' => date('Y-m-d'),
            'service' => 0,
            'staff' => 0,
            'category' => 0
        ), $atts);

        // Get URL parameters (they override shortcode attributes if present)
        $view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : $atts['view'];
        $date_param = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : $atts['date'];
        $service_filter = isset($_GET['service']) ? intval($_GET['service']) : intval($atts['service']);
        $staff_filter = isset($_GET['staff']) ? intval($_GET['staff']) : intval($atts['staff']);
        $category_filter = isset($_GET['category']) ? intval($_GET['category']) : intval($atts['category']);

        // Start output buffering
        ob_start();

        // Create and render schedule
        $schedule = new self($view, $date_param, $service_filter, $staff_filter, $category_filter);
        $schedule->render();

        // Get buffered content
        $output = ob_get_clean();

        return $output;
    }
    
    /**
     * Calculate date ranges based on view
     */
    private function calculate_date_ranges() {
        try {
            switch ($this->view) {
                case 'day':
                    $this->start_date = $this->end_date = date('Y-m-d', strtotime($this->date_param));
                    break;
                case 'week':
                    $dt = new DateTime($this->date_param);
                    $dt->modify('monday this week');
                    $this->start_date = $dt->format('Y-m-d');
                    $this->end_date = (clone $dt)->modify('+6 days')->format('Y-m-d');
                    break;
                case 'month':
                    $this->start_date = date('Y-m-01', strtotime($this->date_param));
                    $this->end_date = date('Y-m-t', strtotime($this->date_param));
                    break;
                case 'year':
                    $year = date('Y', strtotime($this->date_param));
                    $this->start_date = "$year-01-01";
                    $this->end_date = "$year-12-31";
                    break;
            }
            
            // Calculate navigation dates
            $this->prev_date = date('Y-m-d', strtotime($this->start_date . ($this->view === 'year' ? ' -1 year' : ($this->view === 'month' ? ' -1 month' : ' -1 ' . $this->view))));
            $this->next_date = date('Y-m-d', strtotime($this->start_date . ($this->view === 'year' ? ' +1 year' : ($this->view === 'month' ? ' +1 month' : ' +1 ' . $this->view))));
            
        } catch (Exception $e) {
            if (function_exists('sod_log_error')) {
                sod_log_error("Date calculation error: " . $e->getMessage(), "Schedule");
            }
            // Set fallback dates in case of error
            $this->start_date = $this->end_date = date('Y-m-d');
            $this->prev_date = date('Y-m-d', strtotime('-1 week'));
            $this->next_date = date('Y-m-d', strtotime('+1 week'));
        }
    }
    
    /**
     * Fetch all filters (services, staff, categories)
     */
    private function fetch_filters() {
        global $wpdb;
        
        // Get services (first try products since we've migrated to WooCommerce)
        $this->all_services = get_posts([
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
        
        // Fallback to old service posts if products not found
        if (empty($this->all_services)) {
            $this->all_services = get_posts([
                'post_type' => 'sod_service',
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'orderby' => 'title',
                'order' => 'ASC'
            ]);
        }
        
        // Get staff
        $this->all_staff = $wpdb->get_results("
            SELECT DISTINCT s.staff_id, u.display_name 
            FROM {$this->staff_table} s 
            LEFT JOIN {$this->users_table} u ON s.user_id = u.ID 
            ORDER BY u.display_name ASC
        ");
        
        // Get categories (try product categories first)
        $this->all_categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ]);
        
        // Fallback to service categories if product categories not found
        if (empty($this->all_categories) || is_wp_error($this->all_categories)) {
            $this->all_categories = get_terms([
                'taxonomy' => 'service_category',
                'hide_empty' => false,
            ]);
        }
    }
    
    /**
     * Fetch slots based on filters
     */
    private function fetch_slots() {
        global $wpdb;
        
        // Build query parameters
        $params = [$this->start_date, $this->end_date, $this->start_date];
        $where_one_time = "sa.date BETWEEN %s AND %s";
        $where_recurring = "sa.day_of_week IN ('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') 
                            AND (sa.recurring_end_date IS NULL OR sa.recurring_end_date >= %s)";
        $category_join = "";
        $category_where = "";
        
        // Add service/product filter
        if ($this->service_filter) {
            $where_one_time .= " AND sa.product_id = %d";
            $where_recurring .= " AND sa.product_id = %d";
            $params[] = $this->service_filter;
        }
        
        // Add staff filter
        if ($this->staff_filter) {
            $where_one_time .= " AND sa.staff_id = %d";
            $where_recurring .= " AND sa.staff_id = %d";
            $params[] = $this->staff_filter;
        }
        
        // Add category filter
        if ($this->category_filter) {
            $category_join = "LEFT JOIN {$this->term_relationships} tr ON sa.product_id = tr.object_id 
                             LEFT JOIN {$this->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id";
            $category_where = " AND tt.term_id = %d";
            $params[] = $this->category_filter;
        }
        
        // Query one-time slots
        $one_time_slots_query = "SELECT sa.*, p.post_title AS product_name, p.post_excerpt AS product_description,
                                sa.product_id, u.display_name AS staff_name, 
                                sa.appointment_only
                                FROM {$this->slots_table} sa
                                LEFT JOIN {$wpdb->posts} p ON sa.product_id = p.ID
                                LEFT JOIN {$this->staff_table} st ON sa.staff_id = st.staff_id
                                LEFT JOIN {$this->users_table} u ON st.user_id = u.ID
                                {$category_join}
                                WHERE (sa.recurring_type IS NULL OR sa.recurring_type = '') 
                                AND sa.date IS NOT NULL
                                AND {$where_one_time}
                                {$category_where}";
        
        $one_time_slots = $wpdb->get_results($wpdb->prepare($one_time_slots_query, $params));
        
        // Query recurring slots (with separate params array)
        $recurring_params = [$this->start_date];
        if ($this->service_filter) $recurring_params[] = $this->service_filter;
        if ($this->staff_filter) $recurring_params[] = $this->staff_filter;
        if ($this->category_filter) $recurring_params[] = $this->category_filter;
        
        $recurring_slots_query = "SELECT sa.*, p.post_title AS product_name, p.post_excerpt AS product_description,
                                 sa.product_id, u.display_name AS staff_name, 
                                 sa.appointment_only
                                 FROM {$this->slots_table} sa
                                 LEFT JOIN {$wpdb->posts} p ON sa.product_id = p.ID
                                 LEFT JOIN {$this->staff_table} st ON sa.staff_id = st.staff_id
                                 LEFT JOIN {$this->users_table} u ON st.user_id = u.ID
                                 {$category_join}
                                 WHERE sa.recurring_type IS NOT NULL 
                                 AND sa.recurring_type != '' 
                                 AND sa.day_of_week IS NOT NULL
                                 AND {$where_recurring}
                                 {$category_where}";
        
        $recurring_slots = $wpdb->get_results($wpdb->prepare($recurring_slots_query, $recurring_params));
        
        // Combine and store all slots
        $this->slots = array_merge($one_time_slots, $recurring_slots);
        
        // Organize slots by date
        $this->organize_slots_by_date();
    }
    
    /**
     * Organize slots by date for display
     */
    private function organize_slots_by_date() {
        if ($this->view !== 'year') {
            $current_date = new DateTime($this->start_date);
            $end_date_obj = new DateTime($this->end_date);
            
            foreach ($this->slots as $slot) {
                if (!empty($slot->date)) {
                    // Handle one-time slots
                    if (strtotime($slot->date) >= strtotime($this->start_date) && 
                        strtotime($slot->date) <= strtotime($this->end_date)) {
                        $this->slots_by_date[$slot->date][] = $slot;
                    }
                } elseif (!empty($slot->day_of_week)) {
                    // Handle recurring slots
                    $next_occurrence = $this->find_next_occurrence($slot, $current_date, $end_date_obj);
                    if ($next_occurrence && 
                        $next_occurrence->format('Y-m-d') >= $this->start_date && 
                        $next_occurrence->format('Y-m-d') <= $this->end_date) {
                        $date_key = $next_occurrence->format('Y-m-d');
                        $this->slots_by_date[$date_key][] = $slot;
                    }
                }
            }
        } else {
            // Year view organization (by month)
            $year = date('Y', strtotime($this->start_date));
            $this->slots_by_month = [];
            
            for ($month = 1; $month <= 12; $month++) {
                $month_start = sprintf('%s-%02d-01', $year, $month);
                $month_end = date('Y-m-t', strtotime($month_start));
                $this->slots_by_month[$month] = [];
                
                $month_start_obj = new DateTime($month_start);
                $month_end_obj = new DateTime($month_end);
                
                foreach ($this->slots as $slot) {
                    if (!empty($slot->date)) {
                        if (strtotime($slot->date) >= strtotime($month_start) && 
                            strtotime($slot->date) <= strtotime($month_end)) {
                            $this->slots_by_month[$month][] = $slot;
                        }
                    } elseif (!empty($slot->day_of_week)) {
                        $next_occurrence = $this->find_next_occurrence($slot, $month_start_obj, $month_end_obj);
                        if ($next_occurrence && 
                            $next_occurrence->format('Y-m-d') >= $month_start && 
                            $next_occurrence->format('Y-m-d') <= $month_end) {
                            $this->slots_by_month[$month][] = $slot;
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Find next occurrence of a recurring slot
     */
    private function find_next_occurrence($slot, $start_date, $end_date) {
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
    
    /**
     * Get product details
     */
    private function get_product_details($product_id) {
        if (empty($product_id) || !function_exists('wc_get_product')) {
            return null;
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            error_log("Product not found: $product_id");
            return null;
        }

        return [
            'id' => $product_id,
            'name' => $product->get_name(),
            'description' => $product->get_description(),
            'short_description' => $product->get_short_description(),
            'price' => $product->get_price(),
            'url' => get_permalink($product_id),
            'is_variable' => $product->is_type('variable'),
            'categories' => wp_get_post_terms($product_id, 'product_cat', ['fields' => 'all']),
            'is_event' => (bool)get_post_meta($product_id, '_sod_is_event', true),
            'appointment_only' => (bool)get_post_meta($product_id, '_sod_appointment_only', true)
        ];
    }
    
    /**
     * Get product attributes
     */
    public function get_booking_attributes($product_id) {
        // Use the more direct method that gets attributes from the product
        return $this->get_product_booking_attributes($product_id);
    }
    /**
     * Get booking attributes directly from the WooCommerce product
     * This is a more direct and efficient approach than querying the attributes table
     */
    public function get_product_booking_attributes($product_id) {
        // Get the product
        $product = wc_get_product($product_id);
        if (!$product) {
            error_log("Product not found: $product_id");
            return [];
        }

        $attributes = [];

        // If it's a variable product, get all variations
        if ($product->is_type('variable')) {
            $variations = $product->get_available_variations();

            foreach ($variations as $variation) {
                $variation_obj = wc_get_product($variation['variation_id']);
                if (!$variation_obj) continue;

                // Get the attribute that defines this variation
                $variation_attributes = $variation_obj->get_variation_attributes();

                // Try to determine the attribute type (duration, passes, package)
                $attribute_type = '';
                $attribute_value = '';

                foreach ($variation_attributes as $key => $value) {
                    $taxonomy = str_replace('attribute_', '', $key);
                    if (strpos($taxonomy, 'duration') !== false) {
                        $attribute_type = 'duration';
                        $attribute_value = $value;
                    } elseif (strpos($taxonomy, 'passes') !== false) {
                        $attribute_type = 'passes';
                        $attribute_value = $value;
                    } elseif (strpos($taxonomy, 'package') !== false) {
                        $attribute_type = 'package';
                        $attribute_value = $value;
                    }
                }

                // If we couldn't determine the type from taxonomy, check labels
                if (empty($attribute_type)) {
                    foreach ($variation_attributes as $value) {
                        if (preg_match('/(\d+)\s*min/i', $value)) {
                            $attribute_type = 'duration';
                            $attribute_value = $value;
                        } elseif (preg_match('/pass|session/i', $value)) {
                            $attribute_type = 'passes';
                            $attribute_value = $value;
                        } elseif (preg_match('/pack|bundle/i', $value)) {
                            $attribute_type = 'package';
                            $attribute_value = $value;
                        }
                    }
                }

                // As a last resort, use the first attribute
                if (empty($attribute_type) && !empty($variation_attributes)) {
                    $first_key = array_key_first($variation_attributes);
                    $attribute_type = 'custom';
                    $attribute_value = $variation_attributes[$first_key];
                }

                // If we have a type and value, create an attribute object
                if (!empty($attribute_type) && !empty($attribute_value)) {
                    $attr = new stdClass();
                    $attr->attribute_type = $attribute_type;
                    $attr->value = $attribute_value;
                    $attr->price = $variation_obj->get_price();
                    $attr->product_id = $product_id;
                    $attr->variation_id = $variation['variation_id'];

                    // For passes, try to determine the count
                    if ($attribute_type === 'passes') {
                        $pass_count = 1;
                        if (preg_match('/(\d+)/', $attribute_value, $matches)) {
                            $pass_count = (int)$matches[1];
                        }
                        $attr->passes = $pass_count;
                    } else {
                        $attr->passes = 1;
                    }

                    $attributes[] = $attr;
                }
            }
        } 
        // For simple products, check custom attributes and product meta
        else {
            // Check for duration meta
            $duration = get_post_meta($product_id, '_sod_duration', true);
            if ($duration) {
                $attr = new stdClass();
                $attr->attribute_type = 'duration';
                $attr->value = $duration . ' min';
                $attr->price = $product->get_price();
                $attr->product_id = $product_id;
                $attr->variation_id = 0;
                $attr->passes = 1;
                $attributes[] = $attr;
            }

            // Check for passes meta
            $passes = get_post_meta($product_id, '_sod_passes', true);
            if ($passes) {
                $attr = new stdClass();
                $attr->attribute_type = 'passes';
                $attr->value = $passes;
                $attr->price = $product->get_price();
                $attr->product_id = $product_id;
                $attr->variation_id = 0;
                $attr->passes = (int)$passes;
                $attributes[] = $attr;
            }

            // Check for package meta
            $package = get_post_meta($product_id, '_sod_package', true);
            if ($package) {
                $attr = new stdClass();
                $attr->attribute_type = 'package';
                $attr->value = $package;
                $attr->price = $product->get_price();
                $attr->product_id = $product_id;
                $attr->variation_id = 0;
                $attr->passes = 1;
                $attributes[] = $attr;
            }
        }

        // If no attributes were found, create a default duration attribute
        if (empty($attributes)) {
            $attr = new stdClass();
            $attr->attribute_type = 'duration';
            $attr->value = '60 min';
            $attr->price = $product->get_price();
            $attr->product_id = $product_id;
            $attr->variation_id = 0;
            $attr->passes = 1;
            $attributes[] = $attr;
        }

        error_log("Found " . count($attributes) . " attributes for product $product_id via direct product method");
        return $attributes;
    }
    
    /**
     * Handle filter form submissions to preserve URL parameters
     */
    public function handle_filter_submission() {
        // Get current URL parameters
        $current_params = $_GET;
        
        // Add or update filter parameters
        $filter_params = [
            'view' => isset($_POST['view']) ? sanitize_text_field($_POST['view']) : 'day',
            'date' => isset($_POST['date']) ? sanitize_text_field($_POST['date']) : date('Y-m-d'),
            'service' => isset($_POST['service']) ? intval($_POST['service']) : 0,
            'staff' => isset($_POST['staff']) ? intval($_POST['staff']) : 0,
            'category' => isset($_POST['category']) ? intval($_POST['category']) : 0,
        ];
        
        // Merge parameters
        $new_params = array_merge($current_params, $filter_params);
        
        // Build redirect URL
        $redirect_url = add_query_arg($new_params, home_url('/'));
        
        // Redirect to filtered URL
        wp_safe_redirect($redirect_url);
        exit;
    }
    
    /**
     * Render booking form
     */
    public function render_booking_form($slot, $date_key) {
        global $wpdb;

        // Get product ID - make sure it's available
          $product_id = $slot->product_id;
          $product_name = $slot->product_name ?? '';

        if (!$product_id) {
            error_log("Missing product ID in slot: " . print_r($slot, true));
            echo '<p class="booking-error">Product information unavailable</p>';
            return;
        }
        
        // Added debugging specifically for "Tarot Cards with Amanda"
        if (strpos($product_name, 'Tarot Cards with Amanda') !== false) {
            error_log("=== DEBUG: Tarot Cards with Amanda ===");
            error_log("Product ID: " . print_r($product_id, true));
            error_log("Slot data: " . print_r($slot, true));
        }

        if (!$product_id) {
            error_log("Missing product ID for slot: " . ($product_name ?: 'Unknown Product'));
            echo '<p class="booking-error">Product information unavailable</p>';
            return;
        }

        // Debug
        error_log("Rendering booking form for product: $product_id");

        // Get product details
        $product_details = $this->get_product_details($product_id);
        $product_name = $product_details ? $product_details['name'] : $slot->product_name;

        // Get attributes for this product
        $attributes = $this->get_booking_attributes($product_id);

        // Sort attributes by type
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

        // Determine product type based on attributes and categories
        $is_event_category = false;
        $is_event_product = false;
        $is_appointment = !empty($duration_attributes);
        $is_passes = !empty($passes_attributes);
        $is_package = !empty($package_attributes);

        if ($product_details) {
            if (!empty($product_details['categories'])) {
                foreach ($product_details['categories'] as $category) {
                    if (strtolower($category->slug) === 'events') {
                        $is_event_category = true;
                        break;
                    }
                }
            }
            $is_event_product = $product_details['is_event'];
        }

        $is_event = $is_event_category || $is_event_product;

        // Add data attributes for product type detection
        $data_attrs = '';
        if ($is_appointment) {
            $data_attrs .= ' data-product-type="appointment"';
        } elseif ($is_event) {
            $data_attrs .= ' data-product-type="event"';
        } elseif ($is_package) {
            $data_attrs .= ' data-product-type="package"';
        } elseif ($is_passes) {
            $data_attrs .= ' data-product-type="passes"';
        }

        if ($is_event_category) {
            $data_attrs .= ' data-category="events"';
        }

        // Begin form output
        ?>
        <form method="post" action="<?php echo admin_url('admin-ajax.php'); ?>" class="booking-form"<?php echo $data_attrs; ?>>
            <input type="hidden" name="action" value="sod_submit_booking">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('sod_booking_nonce'); ?>">
            <input type="hidden" name="product_id" value="<?php echo esc_attr($product_id); ?>" class="default-product-id">
            <input type="hidden" name="staff_id" value="<?php echo esc_attr($slot->staff_id); ?>">
            <input type="hidden" name="date" value="<?php echo esc_attr($date_key); ?>">

            <?php if ($is_event): ?>
                <input type="hidden" name="event_category" value="1">
            <?php endif; ?>

            <?php if ($is_appointment && !empty($duration_attributes)): ?>
                <!-- Appointment booking (with duration selection) -->
                <div class="booking-form-row">
                    <select name="attribute" class="attribute-select" required>
                        <option value=""><?php _e('Select a duration', 'spark-of-divine-scheduler'); ?></option>
                        <?php foreach ($duration_attributes as $attribute): 
                            $attribute_json = json_encode(['type' => 'duration', 'value' => $attribute->value]);
                            $duration_minutes = (int)preg_replace('/[^0-9]/', '', $attribute->value);
                            $display_text = sprintf('%d minutes', $duration_minutes);
                            if (!empty($attribute->price)) {
                                $display_text .= sprintf(' - $%s', number_format((float)$attribute->price, 2));
                            }
                            $data_attrs = '';

                            if (!empty($attribute->product_id)) {
                                $data_attrs .= ' data-product-id="' . esc_attr($attribute->product_id) . '"';
                            }

                            if (!empty($attribute->variation_id)) {
                                $data_attrs .= ' data-variation-id="' . esc_attr($attribute->variation_id) . '"';
                            }
                        ?>
                            <option value='<?php echo esc_attr($attribute_json); ?>'<?php echo $data_attrs; ?> data-duration="<?php echo esc_attr($duration_minutes); ?>">
                                <?php echo esc_html($display_text); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="booking-form-row">
                    <select name="timeslot" required>
                        <option value=""><?php _e('Select a time slot', 'spark-of-divine-scheduler'); ?></option>
                        <?php 
                        $start_time = date('g:i A', strtotime($slot->start_time ?? '09:00:00'));
                        $time_value = $slot->start_time ?? '09:00:00';
                        ?>
                        <option value="<?php echo esc_attr($time_value); ?>"><?php echo esc_html($start_time); ?></option>
                    </select>
                </div>

            <?php elseif ($is_passes && !empty($passes_attributes)): ?>
                <!-- Passes booking -->
                <div class="booking-form-row">
                    <select name="attribute" class="attribute-select" required>
                        <option value=""><?php _e('Select passes', 'spark-of-divine-scheduler'); ?></option>
                        <?php foreach ($passes_attributes as $attribute): 
                            $attribute_json = json_encode(['type' => 'passes', 'value' => $attribute->value]);
                            $display_text = $attribute->value;

                            if (!empty($attribute->passes)) {
                                $display_text .= sprintf(' (%d passes)', $attribute->passes);
                            }

                            if (!empty($attribute->price)) {
                                $display_text .= sprintf(' - $%s', number_format((float)$attribute->price, 2));
                            }

                            $data_attrs = '';

                            if (!empty($attribute->product_id)) {
                                $data_attrs .= ' data-product-id="' . esc_attr($attribute->product_id) . '"';
                            }

                            if (!empty($attribute->variation_id)) {
                                $data_attrs .= ' data-variation-id="' . esc_attr($attribute->variation_id) . '"';
                            }
                        ?>
                            <option value='<?php echo esc_attr($attribute_json); ?>'<?php echo $data_attrs; ?>>
                                <?php echo esc_html($display_text); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="hidden" name="timeslot" value="<?php echo esc_attr($slot->start_time ?? '09:00:00'); ?>">

            <?php elseif ($is_package && !empty($package_attributes)): ?>
                <!-- Package booking -->
                <div class="booking-form-row">
                    <select name="attribute" class="attribute-select" required>
                        <option value=""><?php _e('Select a package', 'spark-of-divine-scheduler'); ?></option>
                        <?php foreach ($package_attributes as $attribute): 
                            $attribute_json = json_encode(['type' => 'package', 'value' => $attribute->value]);
                            $display_text = $attribute->value;

                            if (!empty($attribute->price)) {
                                $display_text .= sprintf(' - $%s', number_format((float)$attribute->price, 2));
                            }

                            $data_attrs = '';

                            if (!empty($attribute->product_id)) {
                                $data_attrs .= ' data-product-id="' . esc_attr($attribute->product_id) . '"';
                            }

                            if (!empty($attribute->variation_id)) {
                                $data_attrs .= ' data-variation-id="' . esc_attr($attribute->variation_id) . '"';
                            }
                        ?>
                            <option value='<?php echo esc_attr($attribute_json); ?>'<?php echo $data_attrs; ?>>
                                <?php echo esc_html($display_text); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="hidden" name="timeslot" value="<?php echo esc_attr($slot->start_time ?? '09:00:00'); ?>">

            <?php elseif ($is_event): ?>
                <!-- Event booking (simpler form) -->
                <?php if (!empty($passes_attributes) && count($passes_attributes) == 1): ?>
                    <!-- If there's only one pass option, use it automatically -->
                    <input type="hidden" name="attribute" value='<?php echo esc_attr(json_encode(['type' => 'passes', 'value' => $passes_attributes[0]->value])); ?>'>
                    <?php if (!empty($passes_attributes[0]->product_id)): ?>
                        <input type="hidden" name="product_id" value="<?php echo esc_attr($passes_attributes[0]->product_id); ?>">
                    <?php endif; ?>
                    <?php if (!empty($passes_attributes[0]->variation_id)): ?>
                        <input type="hidden" name="variation_id" value="<?php echo esc_attr($passes_attributes[0]->variation_id); ?>">
                    <?php endif; ?>
                <?php else: ?>
                    <!-- Standard booking -->
                    <input type="hidden" name="attribute" value='<?php echo esc_attr(json_encode(['type' => 'event', 'value' => 'standard'])); ?>'>
                <?php endif; ?>
                <input type="hidden" name="timeslot" value="<?php echo esc_attr($slot->start_time ?? '09:00:00'); ?>">

            <?php else: ?>
                <!-- Default case - simple booking -->
                <input type="hidden" name="attribute" value='<?php echo esc_attr(json_encode(['type' => 'product', 'value' => 'standard'])); ?>'>
                <input type="hidden" name="timeslot" value="<?php echo esc_attr($slot->start_time ?? '09:00:00'); ?>">
            <?php endif; ?>

            <div class="booking-form-row">
                <button type="submit" class="book-now"><?php _e('BOOK', 'spark-of-divine-scheduler'); ?></button>
            </div>
        </form>
        <?php
    }

    /**
     * Render filters
     */
    public function render_filters() {
        ?>
        <div class="sod-filter-sidebar">
            <form method="get" id="filter-form" action="<?php echo esc_url(home_url('/schedule/')); ?>" class="instant-filter">
                <h3><?php _e('Filter Schedule', 'spark-of-divine-scheduler'); ?></h3>
                
                <?php if (!empty($this->all_categories)): ?>
                <div class="filter-row">
                    <label for="category"><?php _e('Category:', 'spark-of-divine-scheduler'); ?></label>
                    <select name="category" id="category">
                        <option value="0"><?php _e('All Categories', 'spark-of-divine-scheduler'); ?></option>
                        <?php foreach ($this->all_categories as $category): ?>
                            <option value="<?php echo esc_attr($category->term_id); ?>" <?php selected($this->category_filter, $category->term_id); ?>>
                                <?php echo esc_html($category->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($this->all_services)): ?>
                <div class="filter-row">
                    <label for="service"><?php _e('Service:', 'spark-of-divine-scheduler'); ?></label>
                    <select name="service" id="service">
                        <option value="0"><?php _e('All Services', 'spark-of-divine-scheduler'); ?></option>
                        <?php foreach ($this->all_services as $service): ?>
                            <option value="<?php echo esc_attr($service->ID); ?>" <?php selected($this->service_filter, $service->ID); ?>>
                                <?php echo esc_html($service->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($this->all_staff)): ?>
                <div class="filter-row">
                    <label for="staff"><?php _e('Staff:', 'spark-of-divine-scheduler'); ?></label>
                    <select name="staff" id="staff">
                        <option value="0"><?php _e('All Staff', 'spark-of-divine-scheduler'); ?></option>
                        <?php foreach ($this->all_staff as $stf): ?>
                            <option value="<?php echo esc_attr($stf->staff_id); ?>" <?php selected($this->staff_filter, $stf->staff_id); ?>>
                                <?php echo esc_html($stf->display_name ?: 'Staff ID ' . $stf->staff_id); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <input type="hidden" name="view" value="<?php echo esc_attr($this->view); ?>">
                <input type="hidden" name="date" value="<?php echo esc_attr($this->date_param); ?>">
                <noscript>
                    <button type="submit" class="button"><?php _e('Apply Filters', 'spark-of-divine-scheduler'); ?></button>
                </noscript>
                
                <?php if ($this->service_filter || $this->staff_filter || $this->category_filter): ?>
                    <a href="<?php echo esc_url(add_query_arg([
                        'view' => $this->view,
                        'date' => $this->date_param
                    ])); ?>" class="clear-filters"><?php _e('Clear Filters', 'spark-of-divine-scheduler'); ?></a>
                <?php endif; ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render navigation
     */
    public function render_navigation() {
        ?>
        <div class="view-selector">
            <form method="get" id="view-form" action="<?php echo esc_url(home_url('/schedule/')); ?>" class="instant-filter">
                <label for="view"><?php _e('View:', 'spark-of-divine-scheduler'); ?></label>
                <select name="view" id="view">
                    <?php foreach (['day', 'week'] as $v): ?>
                        <option value="<?php echo esc_attr($v); ?>" <?php selected($this->view, $v); ?>>
                            <?php echo esc_html(ucfirst($v)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="date" value="<?php echo esc_attr($this->date_param); ?>">
                <?php if ($this->service_filter): ?>
                    <input type="hidden" name="service" value="<?php echo esc_attr($this->service_filter); ?>">
                <?php endif; ?>
                <?php if ($this->staff_filter): ?>
                    <input type="hidden" name="staff" value="<?php echo esc_attr($this->staff_filter); ?>">
                <?php endif; ?>
                <?php if ($this->category_filter): ?>
                    <input type="hidden" name="category" value="<?php echo esc_attr($this->category_filter); ?>">
                <?php endif; ?>
                <noscript>
                    <button type="submit" class="button"><?php _e('Go', 'spark-of-divine-scheduler'); ?></button>
                </noscript>
            </form>
        </div>

        <div class="calendar-nav">
            <a href="<?php echo esc_url(add_query_arg([
                'view' => $this->view, 
                'date' => $this->prev_date, 
                'service' => $this->service_filter, 
                'staff' => $this->staff_filter, 
                'category' => $this->category_filter
            ])); ?>" class="nav-button">← <?php _e('Previous', 'spark-of-divine-scheduler'); ?></a>
            
            <h2>
                <?php 
                switch ($this->view) {
                    case 'day':
                        echo esc_html(date('l, F j, Y', strtotime($this->start_date)));
                        break;
                    case 'week':
                        echo esc_html(date('F j', strtotime($this->start_date)) . ' - ' . date('F j, Y', strtotime($this->end_date)));
                        break;
                    case 'month':
                        echo esc_html(date('F Y', strtotime($this->start_date)));
                        break;
                    case 'year':
                        echo esc_html(date('Y', strtotime($this->start_date)));
                        break;
                }
                ?>
            </h2>
            
            <a href="<?php echo esc_url(add_query_arg([
                'view' => $this->view, 
                'date' => $this->next_date, 
                'service' => $this->service_filter, 
                'staff' => $this->staff_filter, 
                'category' => $this->category_filter
            ])); ?>" class="nav-button"><?php _e('Next', 'spark-of-divine-scheduler'); ?> →</a>
        </div>
        <?php
    }
    
    /**
     * Render week view
     */
    public function render_week_view() {
        ?>
        <div class="calendar-grid view-week" id="week-calendar">
            <div class="calendar-wrapper desktop-view">
                <div class="calendar-header">
                    <?php 
                    foreach (new DatePeriod(new DateTime($this->start_date), new DateInterval('P1D'), (new DateTime($this->end_date))->modify('+1 day')) as $date):
                        $dateKey = $date->format('Y-m-d');
                        $dayClasses = array('calendar-cell');
                        
                        // Add weekend class
                        if ($date->format('N') > 5) {
                            $dayClasses[] = 'weekend';
                        }
                        
                        // Add active class for current day
                        if ($dateKey === $this->date_param && $this->view === 'day') {
                            $dayClasses[] = 'active';
                        }
                    ?>
                        <div class="<?php echo esc_attr(implode(' ', $dayClasses)); ?>">
                            <?php echo esc_html($date->format('D j')); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="calendar-body">
                    <?php 
                    foreach (new DatePeriod(new DateTime($this->start_date), new DateInterval('P1D'), (new DateTime($this->end_date))->modify('+1 day')) as $date):
                        $dateKey = $date->format('Y-m-d');
                        $daySlots = $this->slots_by_date[$dateKey] ?? [];
                        $dayClasses = ['calendar-cell'];
                        
                        // Add weekend class
                        if ($date->format('N') > 5) {
                            $dayClasses[] = 'weekend';
                        }
                    ?>
                        <div class="<?php echo esc_attr(implode(' ', $dayClasses)); ?>">
                            <?php if (!empty($daySlots)): ?>
                                <div class="day-slots">
                                    <?php 
                                    // Sort slots by start time
                                    usort($daySlots, function($a, $b) {
                                        return strtotime($a->start_time ?? '00:00:00') - strtotime($b->start_time ?? '00:00:00');
                                    });
                                    
                                    foreach ($daySlots as $slot):
                                        $start_time = date('g:iA', strtotime($slot->start_time ?? '00:00:00'));
                                        $end_time = date('g:iA', strtotime($slot->end_time ?? '00:00:00'));
                                        $staff_name = $slot->staff_name ?: 'Staff';
                                        
                                        // Get product details
                                        $product_id = $slot->product_id ?? 0;
                                        $product_details = $this->get_product_details($product_id);
                                        $product_name = $product_details ? $product_details['name'] : $slot->product_name;
                                        
                                        // Check if product is in events category
                                        $is_event_category = false;
                                        $is_event_product = false;
                                        if ($product_details) {
                                            if (!empty($product_details['categories'])) {
                                                foreach ($product_details['categories'] as $category) {
                                                    if (strtolower($category->slug) === 'events') {
                                                        $is_event_category = true;
                                                        break;
                                                    }
                                                }
                                            }
                                            $is_event_product = $product_details['is_event'];
                                        }
                                        
                                        // Determine slot type
                                        $slotClass = 'schedule-slot';
                                        
                                        // Add appointment-only class if needed
                                        if (!empty($slot->appointment_only) || 
                                            ($product_details && $product_details['appointment_only'])) {
                                            $slotClass .= ' appointment-only';
                                        }
                                        
                                        // Add event class if needed
                                        if ($is_event_category || $is_event_product) {
                                            $slotClass .= ' event-slot';
                                        }
                                        
                                        // Add data attributes for JS detection
                                        $data_attrs = '';
                                        if ($is_event_category || $is_event_product) {
                                            $data_attrs = ' data-category="events"';
                                        }
                                        if (!empty($slot->appointment_only) || 
                                            ($product_details && $product_details['appointment_only'])) {
                                            $data_attrs .= ' data-appointment-only="true"';
                                        }
                                    ?>
                                        <div class="<?php echo esc_attr($slotClass); ?>"<?php echo $data_attrs; ?>>
                                            <div class="slot-info">
                                                <span class="slot-time"><?php echo esc_html("$start_time-$end_time"); ?></span>
                                                <span class="slot-title"><?php echo esc_html($product_name); ?></span>
                                                <span class="slot-staff"><?php _e('With:', 'spark-of-divine-scheduler'); ?> <?php echo esc_html($staff_name); ?></span>
                                            </div>
                                            <?php $this->render_booking_form($slot, $dateKey); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="no-slots"><?php _e('No slots', 'spark-of-divine-scheduler'); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Mobile View (hidden by default, shown via JavaScript) -->
            <div class="calendar-wrapper mobile-view" id="mobile-week-calendar" style="display: none;">
                <?php 
                foreach (new DatePeriod(new DateTime($this->start_date), new DateInterval('P1D'), (new DateTime($this->end_date))->modify('+1 day')) as $date):
                    $dateKey = $date->format('Y-m-d');
                    $daySlots = $this->slots_by_date[$dateKey] ?? [];
                    $dayName = $date->format('l'); // Full day name (e.g., Monday)
                    $dayDate = $date->format('F j'); // Month day (e.g., January 1)
                ?>
                    <div class="mobile-day-section">
                        <h3 class="mobile-day-label">
                            <a href="<?php echo esc_url(add_query_arg([
                                'view' => 'day', 
                                'date' => $dateKey, 
                                'service' => $this->service_filter, 
                                'staff' => $this->staff_filter, 
                                'category' => $this->category_filter
                            ])); ?>" class="day-link">
                                <?php echo esc_html("$dayName, $dayDate"); ?>
                            </a>
                        </h3>
                        <div class="mobile-day-slots">
                            <?php if (!empty($daySlots)): ?>
                                <?php 
                                // Sort slots by start time
                                usort($daySlots, function($a, $b) {
                                    return strtotime($a->start_time ?? '00:00:00') - strtotime($b->start_time ?? '00:00:00');
                                });
                                
                                foreach ($daySlots as $slot):
                                    $start_time = date('g:iA', strtotime($slot->start_time ?? '00:00:00'));
                                    $end_time = date('g:iA', strtotime($slot->end_time ?? '00:00:00'));
                                    $staff_name = $slot->staff_name ?: 'Staff';
                                    
                                    // Get product details
                                    $product_id = $slot->product_id ?? 0;
                                    $product_details = $this->get_product_details($product_id);
                                    $product_name = $product_details ? $product_details['name'] : $slot->product_name;
                                    
                                    // Check if product is in events category
                                    $is_event_category = false;
                                    $is_event_product = false;
                                    if ($product_details) {
                                        if (!empty($product_details['categories'])) {
                                            foreach ($product_details['categories'] as $category) {
                                                if (strtolower($category->slug) === 'events') {
                                                    $is_event_category = true;
                                                    break;
                                                }
                                            }
                                        }
                                        $is_event_product = $product_details['is_event'];
                                    }
                                    
                                    // Determine slot type
                                    $slotClass = 'schedule-slot';
                                    
                                    // Add appointment-only class if needed
                                    if (!empty($slot->appointment_only) || 
                                        ($product_details && $product_details['appointment_only'])) {
                                        $slotClass .= ' appointment-only';
                                    }
                                    
                                    // Add event class if needed
                                    if ($is_event_category || $is_event_product) {
                                        $slotClass .= ' event-slot';
                                    }
                                    
                                    // Add data attributes for JS detection
                                    $data_attrs = '';
                                    if ($is_event_category || $is_event_product) {
                                        $data_attrs = ' data-category="events"';
                                    }
                                    if (!empty($slot->appointment_only) || 
                                        ($product_details && $product_details['appointment_only'])) {
                                        $data_attrs .= ' data-appointment-only="true"';
                                    }
                                ?>
                                    <div class="<?php echo esc_attr($slotClass); ?>"<?php echo $data_attrs; ?>>
                                        <div class="slot-info">
                                            <span class="slot-time"><?php echo esc_html("$start_time-$end_time"); ?></span>
                                            <span class="slot-title"><?php echo esc_html($product_name); ?></span>
                                            <span class="slot-staff"><?php _e('With:', 'spark-of-divine-scheduler'); ?> <?php echo esc_html($staff_name); ?></span>
                                        </div>
                                        <?php $this->render_booking_form($slot, $dateKey); ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="no-slots"><?php _e('No slots', 'spark-of-divine-scheduler'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render day view
     */
    public function render_day_view() {
        $day_slots = $this->slots_by_date[$this->start_date] ?? [];
        
        // Sort slots by start time
        usort($day_slots, function($a, $b) {
            return strtotime($a->start_time ?? '00:00:00') - strtotime($b->start_time ?? '00:00:00');
        });
        ?>
        <div class="day-view-list">
            <div class="day-header">
                <h3><?php echo esc_html(date('l, F j, Y', strtotime($this->start_date))); ?></h3>
            </div>
            
            <?php if (!empty($day_slots)): ?>
                <div class="day-slots-list">
                    <?php foreach ($day_slots as $slot):
                        $start_time = date('g:iA', strtotime($slot->start_time ?? '00:00:00'));
                        $end_time = date('g:iA', strtotime($slot->end_time ?? '00:00:00'));
                        $staff_name = $slot->staff_name ?: 'Staff';
                        
                        // Get product details
                        $product_id = $slot->product_id ?? 0;
                        $product_details = $this->get_product_details($product_id);
                        $product_name = $product_details ? $product_details['name'] : $slot->product_name;
                        
                        // Check if product is in events category
                        $is_event_category = false;
                        $is_event_product = false;
                        if ($product_details) {
                            if (!empty($product_details['categories'])) {
                                foreach ($product_details['categories'] as $category) {
                                    if (strtolower($category->slug) === 'events') {
                                        $is_event_category = true;
                                        break;
                                    }
                                }
                            }
                            $is_event_product = $product_details['is_event'];
                        }
                        
                        // Determine slot type
                        $slotClass = 'schedule-slot';
                        
                        // Add appointment-only class if needed
                        if (!empty($slot->appointment_only) || 
                            ($product_details && $product_details['appointment_only'])) {
                            $slotClass .= ' appointment-only';
                        }
                        
                        // Add event class if needed
                        if ($is_event_category || $is_event_product) {
                            $slotClass .= ' event-slot';
                        }
                        
                        // Add data attributes for JS detection
                        $data_attrs = '';
                        if ($is_event_category || $is_event_product) {
                            $data_attrs = ' data-category="events"';
                        }
                        if (!empty($slot->appointment_only) || 
                            ($product_details && $product_details['appointment_only'])) {
                            $data_attrs .= ' data-appointment-only="true"';
                        }
                    ?>
                        <div class="<?php echo esc_attr($slotClass); ?>"<?php echo $data_attrs; ?>>
                            <div class="slot-info">
                                <span class="slot-time"><?php echo esc_html("$start_time-$end_time"); ?></span>
                                <span class="slot-title"><?php echo esc_html($product_name); ?></span>
                                <span class="slot-staff"><?php _e('With:', 'spark-of-divine-scheduler'); ?> <?php echo esc_html($staff_name); ?></span>
                            </div>
                            <?php $this->render_booking_form($slot, $this->start_date); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="no-slots"><?php _e('No slots available on this day.', 'spark-of-divine-scheduler'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render the schedule
     */
    public function render() {
        ?>
        <div class="sod-schedule-container">
            <?php $this->render_navigation(); ?>
            
            <div class="schedule-main-content">
                <?php $this->render_filters(); ?>
                
                <div class="schedule-content">
                    <?php 
                    if ($this->view === 'day') {
                        $this->render_day_view();
                    } else {
                        $this->render_week_view();
                    }
                    ?>
                </div>
            </div>
            
            <?php $this->render_scripts(); ?>
        </div>
        <?php
    }
    
    /**
     * Add inline JavaScript for filter form handling
     */
    public function render_scripts() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Instant filter submission
            $('.instant-filter select').on('change', function() {
                $(this).closest('form').submit();
            });
            
            // Switch between desktop and mobile views based on screen size
            function checkScreenSize() {
                if ($(window).width() < 768) {
                    $('#week-calendar .desktop-view').hide();
                    $('#mobile-week-calendar').show();
                } else {
                    $('#week-calendar .desktop-view').show();
                    $('#mobile-week-calendar').hide();
                }
            }
            
            // Initial check
            checkScreenSize();
            
            // Check on resize
            $(window).resize(function() {
                checkScreenSize();
            });
            
            // Remove "Book:" prefix from titles if present
            $('.slot-title').each(function() {
                var title = $(this).text();
                if (title.startsWith('Book:')) {
                    $(this).text(title.replace('Book:', '').trim());
                }
            });
        });
        </script>
        <?php
    }
}

//// DEBUG SCRIPTS ////
/**
 * Add AJAX debugging to help diagnose 500 errors
 */
function sod_debug_booking_requests() {
    // Add listener for AJAX actions
    add_action('wp_ajax_sod_submit_booking', 'sod_log_booking_request', 1);
    add_action('wp_ajax_nopriv_sod_submit_booking', 'sod_log_booking_request', 1);
    add_action('wp_ajax_sod_get_available_timeslots', 'sod_log_timeslot_request', 1);
    add_action('wp_ajax_nopriv_sod_get_available_timeslots', 'sod_log_timeslot_request', 1);
}
add_action('init', 'sod_debug_booking_requests');

/**
 * Log booking request details to help diagnose issues
 */
function sod_log_booking_request() {
    $log_data = array(
        'action' => isset($_POST['action']) ? $_POST['action'] : 'unknown',
        'product_id' => isset($_POST['product_id']) ? $_POST['product_id'] : 'not set',
        'staff_id' => isset($_POST['staff_id']) ? $_POST['staff_id'] : 'not set',
        'date' => isset($_POST['date']) ? $_POST['date'] : 'not set',
        'timeslot' => isset($_POST['timeslot']) ? $_POST['timeslot'] : 'not set',
        'attribute' => isset($_POST['attribute']) ? $_POST['attribute'] : 'not set',
        'has_nonce' => isset($_POST['nonce']) ? 'yes' : 'no',
        'user_logged_in' => is_user_logged_in() ? 'yes' : 'no'
    );
    error_log("SOD Booking Request: " . json_encode($log_data));
    
    // Verify nonce
    if (isset($_POST['nonce'])) {
        $nonce_verify = wp_verify_nonce($_POST['nonce'], 'sod_booking_nonce');
        error_log("SOD Nonce Verification: " . ($nonce_verify ? 'passed' : 'failed'));
    }
}

/**
 * Log timeslot request details
 */
function sod_log_timeslot_request() {
    $log_data = array(
        'action' => isset($_POST['action']) ? $_POST['action'] : 'unknown',
        'staff_id' => isset($_POST['staff_id']) ? $_POST['staff_id'] : 'not set',
        'date' => isset($_POST['date']) ? $_POST['date'] : 'not set',
        'duration' => isset($_POST['duration']) ? $_POST['duration'] : 'not set',
        'has_nonce' => isset($_POST['nonce']) ? 'yes' : 'no'
    );
    error_log("SOD Timeslot Request: " . json_encode($log_data));
}

/**
 * Fixed version of the validate_booking_form function
 * to override the buggy version in the plugin
 */
function sod_fixed_validate_booking_form() {
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Override the validateBookingForm function
        window.validateBookingForm = function($form) {
            const $requiredFields = $form.find('[required]');
            let isValid = true;
            
            $requiredFields.each(function() {
                if (!$(this).val()) {
                    isValid = false;
                    $(this).addClass('error');
                } else {
                    $(this).removeClass('error');
                }
            });
            
            if (!isValid) {
                alert('Please fill in all required fields.');
                console.log('Form validation failed - missing required fields');
                return false;
            }
            
            console.log('Form validation passed');
            return true;
        };
        
        // Fix for the datepicker initialization
        if ($.fn.datepicker && $('#sod_booking_date').length) {
            try {
                $('#sod_booking_date').datepicker({
                    dateFormat: 'yy-mm-dd',
                    minDate: 0,
                    onSelect: function(dateText) {
                        // Get selected duration
                        const duration = typeof getDuration === 'function' ? getDuration() : 60;
                        if (typeof updateTimeslots === 'function') {
                            updateTimeslots(duration);
                        }
                    }
                });
                console.log('Datepicker initialized successfully');
            } catch (e) {
                console.error('Error initializing datepicker:', e);
            }
        }
    });
    </script>
    <?php
}
add_action('wp_footer', 'sod_fixed_validate_booking_form');