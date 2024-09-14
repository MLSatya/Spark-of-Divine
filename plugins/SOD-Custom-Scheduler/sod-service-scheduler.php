<?php
/*
Plugin Name: Spark of Divine Service Scheduler
Description: A custom plugin for scheduling and managing services at the healing center.
Version: 1.2
Author: MLSatya
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class SparkOfDivineServiceScheduler {
    private $db_access;
    private $error_log_file;

    public function __construct() {
        global $wpdb;
        
        // Set up the error log path
        $this->error_log_file = WP_CONTENT_DIR . '/sod-error.log';
        
        // Register activation hook for creating tables on plugin activation
        register_activation_hook(__FILE__, array($this, 'activate_plugin'));

        // Attempt to include files and set up hooks
        try {
            $this->include_files();
            $this->setup_hooks();
        } catch (Exception $e) {
            $this->log_error('Plugin initialization failed: ' . $e->getMessage());
            add_action('admin_notices', array($this, 'display_admin_error'));
        }
    }

    // Include necessary files
    private function include_files() {
        $required_files = array(
            'includes/booking-handler.php',
            'includes/booking-endpoint.php',
            'includes/events-api.php',
            'includes/booking-emails.php',
            'includes/sod-bookings-admin-page.php',
            'includes/class-sod-db-access.php',
            'includes/class-sod-custom-post-types.php',
            'includes/class-sod-registration-form.php',
            'includes/class-sod-background-process.php', // Add background process class if needed
        );

        // Additional WooCommerce-related files
        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            $required_files = array_merge($required_files, array(
                'includes/booking-created.php',
                'includes/booking-confirmed.php',
                'includes/booking-updated.php',
                'includes/booking-canceled.php',
                'includes/booking-paid.php',
            ));
        }

        // Require each file and throw an exception if not found
        foreach ($required_files as $file) {
            $file_path = plugin_dir_path(__FILE__) . $file;
            if (!file_exists($file_path)) {
                throw new Exception("Required file not found: $file");
            }
            require_once $file_path;
        }
    }

    // Set up hooks and initialize components
    private function setup_hooks() {
        add_action('init', array($this, 'initialize_components'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    // Activate plugin: Create tables and flush rewrite rules
    public function activate_plugin() {
        try {
            global $wpdb;
            $this->db_access = new SOD_DB_Access($wpdb);
            $this->db_access->loadTables(); // Create necessary database tables
            flush_rewrite_rules(); // Flush rewrite rules on activation
        } catch (Exception $e) {
            $this->log_error('Plugin activation failed: ' . $e->getMessage());
            wp_die('Error activating plugin. Please check the error log.');
        }
    }

    // Initialize plugin components
    public function initialize_components() {
        // Register custom post types
        new SOD_Custom_Post_Types();

        // Initialize registration form
        new SOD_Registration_Form();

        // Initialize background processes
        new SOD_Background_Process();
    }

    // Enqueue scripts and styles
    public function enqueue_scripts() {
        wp_enqueue_style('spark-divine-scheduler-style', plugins_url('assets/css/custom-style.css', __FILE__));
        wp_enqueue_script('spark-divine-scheduler-script', plugins_url('assets/js/custom-script.js', __FILE__), array('jquery'), null, true);
    }

    // Register REST API routes
    public function register_rest_routes() {
        register_rest_route('spark-divine/v1', '/events', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_events'),
            'permission_callback' => '__return_true',
        ));
    }

    // Sample method to use the database access layer
    public function get_events() {
        return $this->db_access ? rest_ensure_response($this->db_access->get_all_events()) : rest_ensure_response([]);
    }

    // Log errors to the error log file
    private function log_error($message) {
        error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, $this->error_log_file);
    }

    // Display admin errors in the WordPress dashboard
    public function display_admin_error() {
        $class = 'notice notice-error';
        $message = __('There was an error initializing the Spark of Divine Service Scheduler plugin. Please check the error log.', 'spark-divine-service');
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
    }
}

// Instantiate the main class
new SparkOfDivineServiceScheduler();