<?php
/**
 * Plugin Name: Spark of Divine Scheduler
 * Description: A custom plugin for scheduling and managing services, events, and classes at the healing center.
 * Version: 3.1
 * Author: MLSatya
 * Text Domain: spark-of-divine-scheduler
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define plugin constants
define('SOD_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SOD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SOD_PLUGIN_VERSION', '3.1');

/**
 * Utility Functions
 */

/**
 * Log debug messages when WP_DEBUG is enabled
 */
function sod_debug_log($message, $section = 'General') {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("[SOD Debug][$section] $message");
    }
}

/**
 * Log error messages
 */
function sod_log_error($message, $section = 'General') {
    error_log("[SOD Error][$section] $message");
}

/**
 * Initialize error logging
 */
if (defined('WP_DEBUG') && WP_DEBUG) {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', WP_CONTENT_DIR . '/sod-error.log');
    error_reporting(E_ALL);
}

/**
 * Main Plugin Initializer Class
 */
class SOD_Plugin_Initializer {
    private static $instance = null;
    private $components = [];
    private $error_log_file;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->error_log_file = WP_CONTENT_DIR . '/sod-error.log';
        
        // Initialize plugin structure first
        add_action('init', [$this, 'initialize_plugin_structure'], 5);
        
        // Check dependencies and initialize
        add_action('plugins_loaded', [$this, 'check_dependencies'], 0);
        
        // Register activation/deactivation hooks
        register_activation_hook(__FILE__, [$this, 'activate_plugin']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate_plugin']);
    }

   /**
     * Ensure required plugin directories and files exist
     */
    public function initialize_plugin_structure() {
        // Create directories if they don't exist
        $dirs = [
            'templates' => SOD_PLUGIN_PATH . 'templates',
            'includes' => SOD_PLUGIN_PATH . 'includes',
            'includes/core' => SOD_PLUGIN_PATH . 'includes/core',
            'includes/core/main' => SOD_PLUGIN_PATH . 'includes/core/main',
            'includes/core/handlers' => SOD_PLUGIN_PATH . 'includes/core/handlers',
            'includes/dashboards' => SOD_PLUGIN_PATH . 'includes/dashboards',
            'templates/staff' => SOD_PLUGIN_PATH . 'templates/staff',
            'assets/js' => SOD_PLUGIN_PATH . 'assets/js',
            'assets/css' => SOD_PLUGIN_PATH . 'assets/css',
        ];

        foreach ($dirs as $name => $path) {
            if (!file_exists($path) || !is_dir($path)) {
                if (wp_mkdir_p($path)) {
                    sod_debug_log("Created $name directory", "Init");
                } else {
                    sod_log_error("Failed to create $name directory", "Init");
                }
            }
        }

        // Include helper functions with error checking
        $helper_file = SOD_PLUGIN_PATH . 'includes/helper-functions.php';
        if (file_exists($helper_file)) {
            require_once $helper_file;
            sod_debug_log("Helper functions loaded successfully", "Init");
        } else {
            sod_log_error("Helper functions file not found: " . $helper_file, "Init");

            // Create basic helper functions inline as fallback
            if (!function_exists('sod_get_current_staff_id')) {
                function sod_get_current_staff_id() {
                    $user_id = get_current_user_id();
                    if (!$user_id) return 0;

                    global $wpdb;
                    $staff_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT staff_id FROM {$wpdb->prefix}sod_staff WHERE user_id = %d",
                        $user_id
                    ));

                    return $staff_id ? intval($staff_id) : 0;
                }
            }

            if (!function_exists('sod_is_current_user_staff')) {
                function sod_is_current_user_staff() {
                    return sod_get_current_staff_id() > 0;
                }
            }
        }
    }

    /**
     * Check dependencies before initializing
     */
    public function check_dependencies() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'woocommerce_missing_notice']);
            return;
        }
        
        add_action('woocommerce_init', [$this, 'initialize_plugin']);
    }

    /**
     * Initialize the plugin
     */
    public function initialize_plugin() {
        try {
            sod_debug_log("Beginning plugin initialization", "Plugin");

            // Load components in the correct order
            $this->load_database_access();
            $this->load_core_classes();
            $this->load_filter_handler();
            $this->load_handler_classes();
            $this->load_enhanced_features();
            $this->load_integration_classes();
            $this->load_post_type_classes();
            $this->load_taxonomy_classes();
            $this->load_crud_classes();
            $this->load_meta_box_classes();
            $this->load_form_classes();
            $this->load_registration_classes();
            $this->load_admin_classes();
            $this->load_email_classes();
            $this->load_schedule_display();
            $this->load_dashboards();
            $this->load_elementor_integration();
            $this->initialize_scheduler();

            // Enqueue assets
            add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

            sod_debug_log("Plugin initialization completed successfully", "Plugin");
        } catch (Exception $e) {
            sod_log_error("Initialization error: " . $e->getMessage(), "Plugin");
            add_action('admin_notices', [$this, 'plugin_init_error_notice']);
        }
    }

    /**
     * Load database access layer
     */
    private function load_database_access() {
        $file = SOD_PLUGIN_PATH . 'includes/core/db/class-sod-db-access.php';
        if (file_exists($file)) {
            require_once $file;
            global $wpdb;
            $this->components['db_access'] = new SOD_DB_Access($wpdb);
            $GLOBALS['sod_db_access'] = $this->components['db_access'];
            sod_debug_log("Database access loaded", "Plugin");
        }
    }

    /**
     * Load core classes
     */
    private function load_core_classes() {
        $file = SOD_PLUGIN_PATH . 'includes/core/handlers/class-sod-booking-sync.php';
        if (file_exists($file)) {
            require_once $file;
            $this->components['booking_sync'] = SOD_Booking_Sync::getInstance();
            $GLOBALS['sod_booking_sync'] = $this->components['booking_sync'];
            sod_debug_log("Booking sync loaded", "Plugin");
        }
    }

    /**
     * Load filter handler - NEW METHOD
     */
    private function load_filter_handler() {
        $file = SOD_PLUGIN_PATH . 'includes/core/handlers/class-sod-schedule-filter-handler.php';
        if (file_exists($file)) {
            require_once $file;
            $this->components['filter_handler'] = SOD_Schedule_Filter_Handler::get_instance();
            $GLOBALS['sod_filter_handler'] = $this->components['filter_handler'];
            sod_debug_log("Filter handler loaded", "Plugin");
        } else {
            sod_log_error("Filter handler file not found: " . $file, "Plugin");
        }
    }

    /**
     * Load handler classes
     */
    private function load_handler_classes() {
        $handlers = [
            'class-sod-passes-handler.php' => [
                'class' => 'SOD_Passes_Handler',
                'method' => 'getInstance',
                'global' => 'passes_handler'
            ],
            'class-sod-packages-handler.php' => [
                'class' => 'SOD_Packages_Handler',
                'method' => 'getInstance',
                'global' => 'packages_handler'
            ],
            'class-sod-booking-validator.php' => [
                'class' => 'SOD_Booking_Validator',
                'method' => 'getInstance',
                'global' => 'booking_validator'
            ],
            'class-sod-booking-handler.php' => [
                'class' => 'SOD_Booking_Handler',
                'method' => 'getInstance',
                'global' => 'booking_handler'
            ],
            'class-sod-schedule-handler.php' => [
                'class' => 'SOD_Schedule_Handler',
                'method' => 'getInstance',
                'global' => 'schedule_handler'
            ],
            'class-sod-cart-handler.php' => [
                'class' => 'SOD_Cart_Handler',
                'method' => null,
                'global' => 'sod_cart_handler'
            ]
        ];

        foreach ($handlers as $file => $config) {
            $path = SOD_PLUGIN_PATH . 'includes/core/handlers/' . $file;
            if (file_exists($path)) {
                require_once $path;
                
                if (class_exists($config['class'])) {
                    if ($config['method']) {
                        $this->components[$config['global']] = call_user_func([$config['class'], $config['method']]);
                    } else {
                        $this->components[$config['global']] = new $config['class']();
                    }
                    $GLOBALS[$config['global']] = $this->components[$config['global']];
                    sod_debug_log($config['class'] . " loaded", "Plugin");
                }
            }
        }
    }

    /**
     * Load enhanced booking features
     */
    private function load_enhanced_features() {
        $enhanced_file = SOD_PLUGIN_PATH . 'includes/core/handlers/class-sod-enhanced-integration.php';
        if (file_exists($enhanced_file)) {
            require_once $enhanced_file;
            $this->components['enhanced_integration'] = SOD_Enhanced_Integration::getInstance();
            $GLOBALS['sod_enhanced_integration'] = $this->components['enhanced_integration'];
            sod_debug_log("Enhanced booking integration loaded", "Plugin");
        } else {
            sod_debug_log("Enhanced integration file not found, using standard functionality", "Plugin");
        }
    }

    /**
     * Load integration classes
     */
    private function load_integration_classes() {
        // Service Product Integration
        $file = SOD_PLUGIN_PATH . 'includes/core/woocommerce/class-sod-service-product-integration.php';
        if (file_exists($file)) {
            require_once $file;
            $this->components['service_product_integration'] = new SOD_Service_Product_Integration();
            $GLOBALS['sod_service_product_integration'] = $this->components['service_product_integration'];
            sod_debug_log("Service product integration loaded", "Plugin");
        }

        // WooCommerce Integration
        $file = SOD_PLUGIN_PATH . 'includes/core/woocommerce/class-sod-woocommerce-integration.php';
        if (file_exists($file)) {
            require_once $file;
            $this->components['woocommerce_integration'] = new SOD_WooCommerce_Integration($this->components['db_access']);
            $GLOBALS['sod_woocommerce_integration'] = $this->components['woocommerce_integration'];
            sod_debug_log("WooCommerce integration loaded", "Plugin");
        }
    }

    /**
     * Load post type classes
     */
    private function load_post_type_classes() {
        $post_types = [
            'class-sod-booking-post-type.php',
            'class-sod-event-post-type.php',
            'class-sod-class-post-type.php',
            'class-sod-staff-post-type.php',
            'class-sod-customer-post-type.php'
        ];

        foreach ($post_types as $file) {
            $path = SOD_PLUGIN_PATH . 'includes/core/post-types/' . $file;
            if (file_exists($path)) {
                require_once $path;
            }
        }
        sod_debug_log("Post types loaded", "Plugin");
    }

    /**
     * Load taxonomy classes
     */
    private function load_taxonomy_classes() {
        $taxonomies = [
            'class-sod-service-category-taxonomy.php',
            'class-sod-staff-specialty-taxonomy.php'
        ];

        foreach ($taxonomies as $file) {
            $path = SOD_PLUGIN_PATH . 'includes/core/taxonomies/' . $file;
            if (file_exists($path)) {
                require_once $path;
            }
        }
        sod_debug_log("Taxonomies loaded", "Plugin");
    }

    /**
     * Load CRUD classes
     */
    private function load_crud_classes() {
        $crud_files = [
            'class-sod-booking-crud.php',
            'class-sod-staff-crud.php',
            'class-sod-customer-crud.php',
        ];

        foreach ($crud_files as $file) {
            $path = SOD_PLUGIN_PATH . 'includes/core/crud/' . $file;
            if (file_exists($path)) {
                require_once $path;
            }
        }
        sod_debug_log("CRUD classes loaded", "Plugin");
    }

    /**
     * Load meta box classes
     */
    private function load_meta_box_classes() {
        $meta_box_files = [
            'class-sod-booking-meta-boxes.php',
            'class-sod-customer-meta-boxes.php',
            'class-sod-staff-meta-boxes.php',
        ];

        foreach ($meta_box_files as $file) {
            $path = SOD_PLUGIN_PATH . 'includes/core/fields/' . $file;
            if (file_exists($path)) {
                require_once $path;
            }
        }
        sod_debug_log("Meta box classes loaded", "Plugin");
    }

    /**
     * Load form classes
     */
    private function load_form_classes() {
        $form_files = [
            'class-sod-event-form.php',
            'class-sod-staff-availability.php'
        ];

        foreach ($form_files as $file) {
            $path = SOD_PLUGIN_PATH . 'includes/core/forms/' . $file;
            if (file_exists($path)) {
                require_once $path;
            }
        }
        sod_debug_log("Form classes loaded", "Plugin");
    }

    /**
     * Load registration classes
     */
    private function load_registration_classes() {
        $file = SOD_PLUGIN_PATH . 'includes/registration/class-sod-registration-form.php';
        if (file_exists($file)) {
            require_once $file;
            $this->components['registration_form'] = new SOD_Registration_Form($this->components['db_access']);
            sod_debug_log("Registration classes loaded", "Plugin");
        }
    }

    /**
     * Load admin classes
     */
    private function load_admin_classes() {
        $file = SOD_PLUGIN_PATH . 'includes/core/admin/class-sod-bookings-admin-page.php';
        if (file_exists($file)) {
            require_once $file;
            $this->components['bookings_admin'] = new SOD_Bookings_Admin_Page($this->components['db_access']);
            sod_debug_log("Admin classes loaded", "Plugin");
        }
    }

    /**
     * Load email classes
     */
    private function load_email_classes() {
        if (class_exists('WC_Email')) {
            $files = [
                SOD_PLUGIN_PATH . 'includes/emails/class-sod-booking-email.php',
                SOD_PLUGIN_PATH . 'includes/core/handlers/class-sod-emails-handler.php'
            ];

            foreach ($files as $file) {
                if (file_exists($file)) {
                    require_once $file;
                }
            }

            if (class_exists('SOD_Emails_Handler')) {
                $this->components['booking_emails'] = SOD_Emails_Handler::get_instance();
                $GLOBALS['sod_emails_handler'] = $this->components['booking_emails'];
                sod_debug_log("Email classes loaded", "Plugin");
            }
        }
    }

    /**
     * Load schedule display
     */
    private function load_schedule_display() {
        $file = SOD_PLUGIN_PATH . 'includes/core/main/class-sod-schedule-display.php';
        if (file_exists($file)) {
            require_once $file;
            sod_debug_log("Schedule display loaded", "Plugin");
        }
    }

    /**
     * Load dashboards
     */
    private function load_dashboards() {
        $dashboards = [
            'staff' => [
                'file' => 'includes/dashboards/class-sod-staff-dashboard.php',
                'class' => 'SOD_Staff_Dashboard',
                'singleton' => false
            ],
            'team' => [
                'file' => 'includes/dashboards/class-sod-team-dashboard.php',
                'class' => 'SOD_Team_Dashboard',
                'singleton' => true
            ]
        ];

        foreach ($dashboards as $key => $config) {
            $path = SOD_PLUGIN_PATH . $config['file'];
            if (file_exists($path)) {
                require_once $path;
                if (class_exists($config['class'])) {
                    if ($config['singleton']) {
                        $this->components[$key . '_dashboard'] = call_user_func([$config['class'], 'get_instance']);
                    } else {
                        $this->components[$key . '_dashboard'] = new $config['class']();
                    }
                    $GLOBALS['sod_' . $key . '_dashboard'] = $this->components[$key . '_dashboard'];
                    sod_debug_log($config['class'] . " loaded", "Plugin");
                }
            }
        }
    }

    /**
     * Load Elementor integration
     */
    private function load_elementor_integration() {
        if (did_action('elementor/loaded')) {
            $file = SOD_PLUGIN_PATH . 'elementor/class-elementor-sod-integration.php';
            if (file_exists($file)) {
                require_once $file;
                $this->components['elementor_integration'] = SOD_Elementor_Integration::get_instance();
                sod_debug_log("Elementor integration loaded", "Plugin");
            }
        }
    }

    /**
     * Initialize main scheduler
     */
    private function initialize_scheduler() {
        $file = SOD_PLUGIN_PATH . 'includes/core/main/class-sod-scheduler.php';
        if (file_exists($file)) {
            require_once $file;
            $this->components['scheduler'] = new SOD_Scheduler($this->components['db_access']);
            $GLOBALS['spark_of_divine_scheduler'] = $this->components['scheduler'];
            sod_debug_log("Main scheduler initialized", "Plugin");
        }
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Only load on pages that need it
        if (!$this->should_load_booking_assets()) {
            return;
        }

        $assets = [
            'js' => [
                'sod-booking-form' => 'sod-booking-form.js',
                'sod-enhanced-integration' => 'sod-enhanced-integration.js',
                'sod-schedule' => 'sod-schedule.js',
                'sod-cart-checkout' => 'sod-cart-checkout.js',
                'sod-product-integration' => 'sod-product-integration.js',
                'staff-availability' => 'staff-availability.js',
                'sod-filter-fix' => 'sod-filter-fix.js',
                'sod-filter-debug' => 'sod-filter-debug.js',
            ],
            'css' => [
                'sod-filter' => 'sod-filter.css', // ADD FILTER CSS
                'sod-booking-form' => 'sod-booking-form.css',
                'sod-enhanced-booking' => 'sod-enhanced-booking.css',
                'sod-schedule-style' => 'sod-schedule-style.css',
                'sod-cart-checkout' => 'sod-cart-checkout.css',
                'sod-product-integration' => 'sod-product-integration.css',
                'staff-availability' => 'staff-availability.css',
            ]
        ];

        // JavaScript dependencies
        $js_deps = [
            'sod-booking-form' => ['jquery'],
            'sod-enhanced-integration' => ['jquery', 'sod-booking-form'],
            'sod-schedule' => ['jquery', 'sod-booking-form'],
            'sod-cart-checkout' => ['jquery'],
            'sod-product-integration' => ['jquery'],
        ];

        // Enqueue JavaScript
        foreach ($assets['js'] as $handle => $file) {
            $path = SOD_PLUGIN_PATH . "assets/js/$file";
            if (file_exists($path)) {
                $deps = $js_deps[$handle] ?? ['jquery'];
                wp_enqueue_script($handle, SOD_PLUGIN_URL . "assets/js/$file", $deps, SOD_PLUGIN_VERSION, true);
            }
        }

        // Enqueue CSS
        foreach ($assets['css'] as $handle => $file) {
            $path = SOD_PLUGIN_PATH . "assets/css/$file";
            if (file_exists($path)) {
                wp_enqueue_style($handle, SOD_PLUGIN_URL . "assets/css/$file", [], SOD_PLUGIN_VERSION);
            }
        }

        // Localize scripts
        $this->localize_scripts();
    }

    /**
     * Check if we should load booking assets
     */
    private function should_load_booking_assets() {
        global $post;

        // Always load on front page (since that's where the schedule is)
        if (is_front_page()) {
            return true;
        }

        // Load if any filter parameters are present
        if (isset($_GET['view']) || isset($_GET['date']) || 
            isset($_GET['product']) || isset($_GET['service']) ||
            isset($_GET['staff']) || isset($_GET['category'])) {
            return true;
        }

        // Check URL first (doesn't depend on $post)
        $current_url = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($current_url, 'staff-availability') !== false || 
            strpos($current_url, 'booking') !== false ||
            strpos($current_url, 'schedule') !== false) {
            return true;
        }

        // Load on pages with booking shortcodes
        if ($post && !empty($post->post_content)) {
            if (has_shortcode($post->post_content, 'sod_booking_form') ||
                has_shortcode($post->post_content, 'sod_schedule') ||
                has_shortcode($post->post_content, 'sod_staff_availability_form') ||
                strpos($post->post_content, 'sod-booking') !== false) {
                return true;
            }
        }

        // Check if it's the staff availability page by slug
        if (is_page('staff-availability')) {
            return true;
        }

        // Load on booking-related pages
        if (is_page() && $post) {
            $template = get_page_template_slug($post);
            if ($template && (strpos($template, 'booking') !== false || 
                strpos($template, 'schedule') !== false ||
                strpos($template, 'staff') !== false)) {
                return true;
            }
        }

        // Load on cart/checkout pages
        if (function_exists('is_cart') && (is_cart() || is_checkout())) {
            return true;
        }

        return false;
    }

    /**
     * Localize scripts with data - UPDATED WITH FILTER SUPPORT
     */
     private function localize_scripts() {
        // Get filter handler instance
        $filter_handler = isset($this->components['filter_handler']) ? $this->components['filter_handler'] : null;

        // Basic booking data
        wp_localize_script('sod-booking-form', 'sodBooking', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sod_booking_nonce'),
            'texts' => [
                'selectCategory' => __('Select Category', 'spark-of-divine-scheduler'),
                'selectService' => __('Select Item', 'spark-of-divine-scheduler'),
                'selectStaff' => __('Select Staff', 'spark-of-divine-scheduler'),
                'selectTimeSlot' => __('Select Time Slot', 'spark-of-divine-scheduler'),
                'pleaseSelect' => __('Please Select', 'spark-of-divine-scheduler')
            ]
        ]);

        // Enhanced booking data
        wp_localize_script('sod-enhanced-integration', 'sodEnhanced', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sod_booking_nonce'),
            'defaultDuration' => 60,
            'debugMode' => defined('WP_DEBUG') && WP_DEBUG,
            'strings' => [
                'loadingSlots' => __('Loading available time slots...', 'spark-of-divine-scheduler'),
                'noSlotsAvailable' => __('No time slots available for selected duration', 'spark-of-divine-scheduler'),
                'selectAllFields' => __('Please complete all required fields', 'spark-of-divine-scheduler'),
                'bookingError' => __('Error processing booking', 'spark-of-divine-scheduler')
            ]
        ]);

        // Schedule and filter data - CONSISTENT NONCE NAMES
        $current_filters = $filter_handler ? $filter_handler->get_filters() : [
            'view' => isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'week',
            'date' => isset($_GET['date']) ? sanitize_text_field($_GET['date']) : date('Y-m-d'),
            'product' => isset($_GET['product']) ? intval($_GET['product']) : 0,
            'staff' => isset($_GET['staff']) ? intval($_GET['staff']) : 0,
            'category' => isset($_GET['category']) ? intval($_GET['category']) : 0,
        ];

        wp_localize_script('sod-schedule', 'sodSchedule', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sod_booking_nonce'),
            'filter_nonce' => wp_create_nonce('sod_filter_nonce'),
            'base_url' => home_url('/'),
            'currentFilters' => $current_filters,
            'strings' => [
                'loading' => __('Loading schedule...', 'spark-of-divine-scheduler'),
                'error' => __('Error loading schedule', 'spark-of-divine-scheduler'),
                'noResults' => __('No results found', 'spark-of-divine-scheduler')
            ]
        ]);

        // Localize staff-availability script if it's enqueued - UPDATED TO USE DATABASE
        if (wp_script_is('staff-availability', 'enqueued')) {
            global $wpdb;
            
            // Get products for the dropdown (unchanged)
            $products = [];
            $product_query = new WP_Query([
                'post_type' => 'product',
                'posts_per_page' => -1,
                'post_status' => 'publish'
            ]);

            if ($product_query->have_posts()) {
                while ($product_query->have_posts()) {
                    $product_query->the_post();
                    $products[] = [
                        'id' => get_the_ID(),
                        'title' => get_the_title()
                    ];
                }
                wp_reset_postdata();
            }

            // Get staff names from DATABASE table (not WordPress posts)
            $staff_names = [];
            $staff_table = $wpdb->prefix . 'sod_staff';
            
            $staff_results = $wpdb->get_results("
                SELECT s.staff_id, s.user_id, s.name 
                FROM {$staff_table} s 
                WHERE s.name IS NOT NULL 
                AND s.name != '' 
                ORDER BY s.name ASC
            ");
            
            if ($staff_results) {
                foreach ($staff_results as $staff) {
                    // Skip if no user_id (invalid staff record)
                    if (!$staff->user_id) {
                        continue;
                    }
                    
                    // Get WordPress user to check roles
                    $user = get_user_by('ID', $staff->user_id);
                    
                    // Skip if user has admin or shop manager role
                    if ($user && (
                        in_array('administrator', $user->roles) || 
                        in_array('shop_manager', $user->roles)
                    )) {
                        error_log("SOD: Skipping admin/shop manager in availability: " . $staff->name);
                        continue;
                    }
                    
                    // Use staff name from database
                    $staff_names[$staff->staff_id] = $staff->name;
                }
            }
            
            error_log("SOD: Localized " . count($staff_names) . " staff members for availability script");

            wp_localize_script('staff-availability', 'sodAvailability', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sod_availability_nonce_action'),
                'products' => $products,
                'staff_names' => $staff_names
            ]);
        }
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook_suffix) {
        if (false === strpos($hook_suffix, 'sod')) {
            return;
        }
        
        wp_enqueue_style('sod-admin-style', SOD_PLUGIN_URL . 'assets/admin/css/sod-admin.css', [], SOD_PLUGIN_VERSION);
        wp_enqueue_script('admin-bookings', SOD_PLUGIN_URL . 'assets/admin/js/admin-bookings.js', ['jquery'], SOD_PLUGIN_VERSION, true);
    }

    /**
     * Plugin activation
     */
    public function activate_plugin() {
        if (isset($this->components['scheduler'])) {
            $this->components['scheduler']->plugin_activation();
        }
        
        if (isset($this->components['booking_emails'])) {
            $this->components['booking_emails']->schedule_reminder_cron();
        }
        
        sod_debug_log("Plugin activation completed", "Plugin");
    }

    /**
     * Plugin deactivation
     */
    public function deactivate_plugin() {
        wp_clear_scheduled_hook('sod_send_booking_reminders');
        sod_debug_log("Plugin deactivation completed", "Plugin");
    }

    /**
     * Admin notices
     */
    public function woocommerce_missing_notice() {
        echo '<div class="error"><p>' . esc_html__('Spark of Divine Scheduler requires WooCommerce to be installed and active.', 'spark-of-divine-scheduler') . '</p></div>';
    }

    public function plugin_init_error_notice() {
        echo '<div class="error"><p>' . esc_html__('Spark of Divine Scheduler encountered an error during initialization. Please check the error log.', 'spark-of-divine-scheduler') . '</p></div>';
    }
}
/**
 * Initialize the plugin
 */
SOD_Plugin_Initializer::get_instance();


    /**
     * Helper Functions
     */

    /**
     * Get filter handler instance
     */
    function sod_get_filter_handler() {
        global $sod_filter_handler;

        if (isset($sod_filter_handler)) {
            return $sod_filter_handler;
        }

        // Try to get from plugin components
        $plugin = SOD_Plugin_Initializer::get_instance();
        if (isset($plugin->components['filter_handler'])) {
            return $plugin->components['filter_handler'];
        }

        // Fallback: try to instantiate
        if (class_exists('SOD_Schedule_Filter_Handler')) {
            return SOD_Schedule_Filter_Handler::get_instance();
        }

        return null;
    }

    /**
     * Get a Schedule Display instance
     */
    function sod_get_schedule_display($view = 'week', $date = '', $service = 0, $staff = 0, $category = 0) {
        if (!class_exists('SOD_Schedule_Display')) {
            $file = SOD_PLUGIN_PATH . 'includes/core/main/class-sod-schedule-display.php';
            if (file_exists($file)) {
                require_once $file;
            } else {
                sod_log_error("Unable to load SOD_Schedule_Display class file", "Schedule");
                return null;
            }
        }

        if (empty($date)) {
            $date = date('Y-m-d');
        }

        if (class_exists('SOD_Schedule_Display')) {
            return new SOD_Schedule_Display($view, $date, $service, $staff, $category);
        }

        return null;
    }

    /**
     * Schedule shortcode - UPDATED WITH FILTER HANDLER SUPPORT
     */
    function sod_schedule_shortcode($atts) {
        $atts = shortcode_atts(array(
            'view' => 'week',
            'date' => date('Y-m-d'),
            'product' => 0,
            'service' => 0,
            'staff' => 0,
            'category' => 0
        ), $atts);

        // Get filter handler
        $filter_handler = sod_get_filter_handler();

        if (!$filter_handler) {
            sod_log_error("Filter handler not available for shortcode", "Shortcode");
            return '<p>Schedule not available. Please contact support.</p>';
        }

        ob_start();

        // Set current context for filter handler
        $_GET['view'] = $atts['view'];
        $_GET['date'] = $atts['date'];
        if ($atts['product']) $_GET['product'] = $atts['product'];
        if ($atts['service']) $_GET['service'] = $atts['service']; // Backward compatibility
        if ($atts['staff']) $_GET['staff'] = $atts['staff'];
        if ($atts['category']) $_GET['category'] = $atts['category'];

        // Get filters
        $service_filter = !empty($atts['product']) ? intval($atts['product']) : intval($atts['service']);
        $staff_filter = intval($atts['staff']);
        $category_filter = intval($atts['category']);

        // Set globals for backward compatibility
        $GLOBALS['sod_customer_view'] = true;
        $GLOBALS['sod_schedule_view'] = $atts['view'];
        $GLOBALS['sod_schedule_date'] = $atts['date'];
        $GLOBALS['sod_service_filter'] = $service_filter;
        $GLOBALS['sod_staff_filter'] = $staff_filter;
        $GLOBALS['sod_category_filter'] = $category_filter;

        // Try to use the SOD_Schedule_Display class
        $schedule = sod_get_schedule_display($atts['view'], $atts['date'], $service_filter, $staff_filter, $category_filter);

        if ($schedule) {
            $schedule->render();
        } else {
            echo '<p>Schedule not available. Please contact support.</p>';
            sod_log_error("Schedule display could not be loaded", "Shortcode");
        }

        return ob_get_clean();
        }
        add_shortcode('sod_schedule', 'sod_schedule_shortcode');

       /* function sod_add_rewrite_rules() {
            // Add rewrite rule for front page with filters
            add_rewrite_rule(
                '^schedule/?$',
                'index.php?pagename=schedule',
                'top'
            );

            // Add rewrite rules for front page filtering
            add_rewrite_rule(
                '^/?([^/]*)/([^/]*)/([^/]*)/([^/]*)/([^/]*)/?$',
                'index.php?view=$matches[1]&date=$matches[2]&product=$matches[3]&staff=$matches[4]&category=$matches[5]',
                'top'
            );
        }
        add_action('init', 'sod_add_rewrite_rules');


        // Add this to your plugin activation method:
        function sod_flush_rewrite_rules() {
            sod_add_rewrite_rules();
            flush_rewrite_rules();
        }
        register_activation_hook(__FILE__, 'sod_flush_rewrite_rules');*/


        // Update the existing sod_add_filter_query_vars function:
        function sod_add_filter_query_vars($vars) {
            $vars[] = 'view';
            $vars[] = 'date';
            $vars[] = 'product';
            $vars[] = 'staff';
            $vars[] = 'category';
            return $vars;
        }
        add_filter('query_vars', 'sod_add_filter_query_vars');

        // Add this function to ensure the correct template is used:
        function sod_template_include($template) {
            // Check if we're on the front page with schedule filters
            if (is_front_page() && (
                get_query_var('view') || 
                get_query_var('date') || 
                get_query_var('product') ||
                get_query_var('staff') || 
                get_query_var('category')
            )) {
                // Look for schedule template in theme
                $schedule_template = locate_template(['schedule-template.php']);
                if ($schedule_template) {
                    return $schedule_template;
                }

                // Use plugin template as fallback
                $plugin_template = SOD_PLUGIN_PATH . 'templates/schedule-template.php';
                if (file_exists($plugin_template)) {
                    return $plugin_template;
                }
            }

            return $template;
        }
        add_filter('template_include', 'sod_template_include', 99);

        // Add this function to generate proper .htaccess rules:
        function sod_generate_htaccess_rules() {
            $rules = '
        # SOD Schedule Filter Rules
        <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteBase /

        # Handle schedule filtering on front page
        RewriteRule ^schedule/?$ / [QSA,L]

        # Handle query parameters properly
        RewriteCond %{QUERY_STRING} ^(.*)$
        RewriteRule ^/?$ /index.php [QSA,L]
        </IfModule>
        ';

            return $rules;
        }

        // Update the filter handler's render_filter_form method to use the correct action URL:
        function sod_get_filter_form_action_url() {
            // Always use the front page URL for filtering
            return home_url('/');
        }

        function sod_debug_filter_status() {
            if (!defined('WP_DEBUG') || !WP_DEBUG) {
                return;
            }

            error_log("=== SOD Filter System Debug ===");
            error_log("Filter handler class exists: " . (class_exists('SOD_Schedule_Filter_Handler') ? 'YES' : 'NO'));
            error_log("Filter handler file path: " . SOD_PLUGIN_PATH . 'includes/core/handlers/class-sod-schedule-filter-handler.php');
            error_log("Filter handler file exists: " . (file_exists(SOD_PLUGIN_PATH . 'includes/core/handlers/class-sod-schedule-filter-handler.php') ? 'YES' : 'NO'));

            $filter_handler = sod_get_filter_handler();
            error_log("Filter handler instance available: " . ($filter_handler ? 'YES' : 'NO'));

            if ($filter_handler) {
                $filters = $filter_handler->get_filters();
                error_log("Current filters: " . print_r($filters, true));
            }

            // Check query vars
            global $wp_query;
            $query_vars = $wp_query->query_vars;
            $sod_vars = array_intersect_key($query_vars, array_flip(['view', 'date', 'product', 'service', 'staff', 'category']));
            error_log("Query vars: " . print_r($sod_vars, true));

            // Check if we're on front page
            error_log("Is front page: " . (is_front_page() ? 'YES' : 'NO'));
            error_log("Current URL: " . $_SERVER['REQUEST_URI']);
            error_log("Query string: " . $_SERVER['QUERY_STRING']);
        }
        add_action('wp_footer', 'sod_debug_filter_status');

        /**
         * Handle front page schedule with filters - REQUIRED FOR FRONT PAGE FILTERING
         */
        function sod_handle_front_page_filters() {
            if (is_front_page() && (
                isset($_GET['view']) || isset($_GET['date']) || 
                isset($_GET['product']) ||
                isset($_GET['staff']) || isset($_GET['category'])
            )) {
                // Ensure the filter handler is available
                $filter_handler = sod_get_filter_handler();
                if ($filter_handler) {
                    // Let the filter handler process the current request
                    global $wp_query;
                    $wp_query->is_home = false;
                    $wp_query->is_front_page = true;
                }
            }
        }
        /*add_action('template_redirect', 'sod_handle_front_page_filters');*/

        /**
         * Debug function to check filter system status - DEBUGGING HELPER
         */
        function sod_debug_filter_system() {
            if (!defined('WP_DEBUG') || !WP_DEBUG) {
                return;
            }

            $status = [
                'filter_handler_loaded' => class_exists('SOD_Schedule_Filter_Handler'),
                'filter_handler_instance' => sod_get_filter_handler() !== null,
                'css_exists' => file_exists(SOD_PLUGIN_PATH . 'assets/css/sod-filter.css'),
                'js_exists' => file_exists(SOD_PLUGIN_PATH . 'assets/js/sod-schedule.js'),
                'current_filters' => sod_get_filter_handler() ? sod_get_filter_handler()->get_filters() : 'N/A'
            ];

            sod_debug_log("Filter system status", $status);
        }
        add_action('wp_footer', 'sod_debug_filter_system');
// Add debug function to schedule display
add_action('init', function() {
    if (isset($_GET['debug_schedule'])) {
        // Check if schedule display can be instantiated
        if (function_exists('sod_get_schedule_display')) {
            $schedule = sod_get_schedule_display('week', date('Y-m-d'), 0, 0, 0);
            if ($schedule) {
                echo "Schedule Display object created successfully<br>";
                echo "Class: " . get_class($schedule) . "<br>";
            } else {
                echo "Failed to create Schedule Display object<br>";
            }
        } else {
            echo "sod_get_schedule_display function not found<br>";
        }
        
        // Check database
        global $wpdb;
        $slots = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sod_staff_availability");
        echo "Total availability slots: " . $slots . "<br>";
        
        die();
    }
});

