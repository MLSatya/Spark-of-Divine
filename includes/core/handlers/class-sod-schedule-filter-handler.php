<?php
/**
 * Schedule Filter Handler
 * 
 * Handles filtering functionality for the schedule display
 * 
 * @package SOD
 * @since 3.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SOD_Schedule_Filter_Handler {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Current filters
     */
    private $filters = [];
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize the filter handler
     */
    private function init() {
        // Set up default filters
        $this->setup_filters();
        
        // Add AJAX handlers
        add_action('wp_ajax_sod_filter_schedule', [$this, 'handle_ajax_filter']);
        add_action('wp_ajax_nopriv_sod_filter_schedule', [$this, 'handle_ajax_filter']);
        
        // Add filter processing
        add_action('init', [$this, 'process_filter_request'], 20);
    }
    
    /**
     * Setup filters from request
     */
    private function setup_filters() {
        $this->filters = [
            'view' => $this->get_filter_value('view', 'week'),
            'date' => $this->get_filter_value('date', date('Y-m-d')),
            'product' => intval($this->get_filter_value('product', 0)),
            'service' => intval($this->get_filter_value('service', 0)), // Backward compatibility
            'staff' => intval($this->get_filter_value('staff', 0)),
            'category' => intval($this->get_filter_value('category', 0))
        ];
        
        // Handle service/product interchangeably for backward compatibility
        if (empty($this->filters['product']) && !empty($this->filters['service'])) {
            $this->filters['product'] = $this->filters['service'];
        }
    }
    
    /**
     * Get filter value from request
     */
    private function get_filter_value($key, $default = '') {
        // Check GET parameters first
        if (isset($_GET[$key])) {
            return sanitize_text_field($_GET[$key]);
        }
        
        // Check query vars
        $value = get_query_var($key, '');
        if (!empty($value)) {
            return sanitize_text_field($value);
        }
        
        return $default;
    }
    
    /**
     * Get current filters
     * 
     * @return array Current filter values
     */
    public function get_filters() {
        return $this->filters;
    }
    
    /**
     * Get single filter value
     */
    public function get_filter($key, $default = '') {
        return isset($this->filters[$key]) ? $this->filters[$key] : $default;
    }
    
    /**
     * Set filter value
     */
    public function set_filter($key, $value) {
        $this->filters[$key] = $value;
    }
    
    /**
     * Process filter request
     */
    public function process_filter_request() {
        // Re-setup filters in case they changed
        $this->setup_filters();
        
        // Set globals for backward compatibility
        if (!empty($this->filters)) {
            $GLOBALS['sod_schedule_view'] = $this->filters['view'];
            $GLOBALS['sod_schedule_date'] = $this->filters['date'];
            $GLOBALS['sod_service_filter'] = $this->filters['product'];
            $GLOBALS['sod_staff_filter'] = $this->filters['staff'];
            $GLOBALS['sod_category_filter'] = $this->filters['category'];
        }
    }
    
    /**
     * Handle AJAX filter request
     */
    public function handle_ajax_filter() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sod_filter_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Update filters from POST data
        if (isset($_POST['filters']) && is_array($_POST['filters'])) {
            foreach ($_POST['filters'] as $key => $value) {
                if (in_array($key, ['view', 'date', 'product', 'service', 'staff', 'category'])) {
                    $this->set_filter($key, sanitize_text_field($value));
                }
            }
        }
        
        // Get schedule display
        $schedule = sod_get_schedule_display(
            $this->get_filter('view', 'week'),
            $this->get_filter('date', date('Y-m-d')),
            $this->get_filter('product', 0),
            $this->get_filter('staff', 0),
            $this->get_filter('category', 0)
        );
        
        if ($schedule) {
            ob_start();
            $schedule->render();
            $html = ob_get_clean();
            
            wp_send_json_success([
                'html' => $html,
                'filters' => $this->get_filters()
            ]);
        } else {
            wp_send_json_error('Could not load schedule');
        }
    }
    
    /**
     * Render filter form
     */
    public function render_filter_form() {
        global $wpdb;
        
        ?>
        <div class="sod-schedule-filters">
            <form method="get" action="<?php echo esc_url(home_url('/')); ?>" class="sod-filter-form">
                
                <!-- View selector -->
                <div class="filter-group">
                    <label for="view"><?php _e('View', 'spark-of-divine-scheduler'); ?></label>
                    <select name="view" id="view" class="sod-filter-select">
                        <option value="week" <?php selected($this->get_filter('view'), 'week'); ?>><?php _e('Week', 'spark-of-divine-scheduler'); ?></option>
                        <option value="day" <?php selected($this->get_filter('view'), 'day'); ?>><?php _e('Day', 'spark-of-divine-scheduler'); ?></option>
                        <option value="month" <?php selected($this->get_filter('view'), 'month'); ?>><?php _e('Month', 'spark-of-divine-scheduler'); ?></option>
                    </select>
                </div>
                
                <!-- Date picker -->
                <div class="filter-group">
                    <label for="date"><?php _e('Date', 'spark-of-divine-scheduler'); ?></label>
                    <input type="date" name="date" id="date" value="<?php echo esc_attr($this->get_filter('date')); ?>" class="sod-filter-input">
                </div>
                
                <!-- Product/Service selector -->
                <div class="filter-group">
                    <label for="product"><?php _e('Service', 'spark-of-divine-scheduler'); ?></label>
                    <select name="product" id="product" class="sod-filter-select">
                        <option value=""><?php _e('All Services', 'spark-of-divine-scheduler'); ?></option>
                        <?php
                        $products = get_posts([
                            'post_type' => 'product',
                            'posts_per_page' => -1,
                            'orderby' => 'title',
                            'order' => 'ASC',
                            'post_status' => 'publish'
                        ]);
                        
                        foreach ($products as $product) {
                            printf(
                                '<option value="%d" %s>%s</option>',
                                $product->ID,
                                selected($this->get_filter('product'), $product->ID, false),
                                esc_html($product->post_title)
                            );
                        }
                        ?>
                    </select>
                </div>
                
                <!-- Staff selector -->
                <div class="filter-group">
                    <label for="staff"><?php _e('Staff', 'spark-of-divine-scheduler'); ?></label>
                    <select name="staff" id="staff" class="sod-filter-select">
                        <option value=""><?php _e('All Staff', 'spark-of-divine-scheduler'); ?></option>
                        <?php
                        $staff_table = $wpdb->prefix . 'sod_staff';
                        $staff_members = $wpdb->get_results("
                            SELECT staff_id, name 
                            FROM {$staff_table} 
                            WHERE name IS NOT NULL 
                            ORDER BY name ASC
                        ");
                        
                        foreach ($staff_members as $staff) {
                            printf(
                                '<option value="%d" %s>%s</option>',
                                $staff->staff_id,
                                selected($this->get_filter('staff'), $staff->staff_id, false),
                                esc_html($staff->name)
                            );
                        }
                        ?>
                    </select>
                </div>
                
                <!-- Category selector -->
                <div class="filter-group">
                    <label for="category"><?php _e('Category', 'spark-of-divine-scheduler'); ?></label>
                    <select name="category" id="category" class="sod-filter-select">
                        <option value=""><?php _e('All Categories', 'spark-of-divine-scheduler'); ?></option>
                        <?php
                        $categories = get_terms([
                            'taxonomy' => 'product_cat',
                            'hide_empty' => true,
                            'orderby' => 'name',
                            'order' => 'ASC'
                        ]);
                        
                        if (!is_wp_error($categories)) {
                            foreach ($categories as $category) {
                                printf(
                                    '<option value="%d" %s>%s</option>',
                                    $category->term_id,
                                    selected($this->get_filter('category'), $category->term_id, false),
                                    esc_html($category->name)
                                );
                            }
                        }
                        ?>
                    </select>
                </div>
                
                <div class="filter-group filter-buttons">
                    <button type="submit" class="button sod-filter-submit"><?php _e('Apply Filters', 'spark-of-divine-scheduler'); ?></button>
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="button sod-filter-reset"><?php _e('Reset', 'spark-of-divine-scheduler'); ?></a>
                </div>
                
            </form>
        </div>
        <?php
    }
    
    /**
     * Get filtered bookings query args
     */
    public function get_filtered_query_args($base_args = []) {
        $args = wp_parse_args($base_args, [
            'post_type' => 'sod_booking',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'meta_query' => [],
            'tax_query' => []
        ]);
        
        // Add date filter
        if (!empty($this->filters['date'])) {
            $args['meta_query'][] = [
                'key' => '_booking_date',
                'value' => $this->filters['date'],
                'compare' => '='
            ];
        }
        
        // Add product/service filter
        if (!empty($this->filters['product'])) {
            $args['meta_query'][] = [
                'key' => '_service_id',
                'value' => $this->filters['product'],
                'compare' => '='
            ];
        }
        
        // Add staff filter
        if (!empty($this->filters['staff'])) {
            $args['meta_query'][] = [
                'key' => '_staff_id',
                'value' => $this->filters['staff'],
                'compare' => '='
            ];
        }
        
        // Add category filter
        if (!empty($this->filters['category'])) {
            // Get products in category
            $product_args = [
                'post_type' => 'product',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'tax_query' => [
                    [
                        'taxonomy' => 'product_cat',
                        'field' => 'term_id',
                        'terms' => $this->filters['category']
                    ]
                ]
            ];
            
            $products = get_posts($product_args);
            
            if (!empty($products)) {
                $args['meta_query'][] = [
                    'key' => '_service_id',
                    'value' => $products,
                    'compare' => 'IN'
                ];
            }
        }
        
        return $args;
    }
    
    /**
     * Build filter URL
     */
    public function build_filter_url($filters = []) {
        $base_url = home_url('/');
        $query_args = [];
        
        // Merge with current filters
        $filters = wp_parse_args($filters, $this->filters);
        
        // Build query string
        foreach ($filters as $key => $value) {
            if (!empty($value) && !in_array($value, ['', '0', 0])) {
                $query_args[$key] = $value;
            }
        }
        
        if (!empty($query_args)) {
            $base_url = add_query_arg($query_args, $base_url);
        }
        
        return $base_url;
    }
}

// Initialize the filter handler
add_action('init', function() {
    SOD_Schedule_Filter_Handler::get_instance();
}, 10);