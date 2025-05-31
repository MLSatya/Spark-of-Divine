<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Class SOD_DB_Access
 *
 * Manages the creation and maintenance of custom database tables for the Spark of Divine Scheduler plugin.
 * All table schemas are defined and initialized here to ensure consistency across the plugin.
 */
class SOD_DB_Access {
    /**
     * @var wpdb WordPress database access abstraction object.
     */
    private $wpdb;
    
    /**
     * @var string Character set and collation for database tables, derived from WordPress configuration.
     */
    private $charset_collate;

    /**
     * SOD_DB_Access constructor.
     *
     * Initializes the database handler with the global wpdb object and triggers table loading.
     *
     * @param wpdb $wpdb The WordPress database object passed during instantiation.
     */
    public function __construct($wpdb) {
        $this->wpdb = $wpdb;
        $this->charset_collate = $wpdb->get_charset_collate();
        $this->load_tables();
    }

    /**
     * Loads (creates or updates) all custom tables required by the plugin.
     *
     * Uses dbDelta to ensure tables are created or updated to match the defined schema.
     * Tables are prefixed with the WordPress table prefix (e.g., wp_sod_).
     */
    public function load_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Define table names with WordPress prefix
        $tables = [
            'customers' => $this->wpdb->prefix . 'sod_customers',
            'staff' => $this->wpdb->prefix . 'sod_staff',
            'staff_availability' => $this->wpdb->prefix . 'sod_staff_availability',
            'service_categories' => $this->wpdb->prefix . 'sod_service_categories',
            'staff_services' => $this->wpdb->prefix . 'sod_staff_services',
            'services' => $this->wpdb->prefix . 'sod_services',
            'service_attributes' => $this->wpdb->prefix . 'sod_service_attributes',
            'bookings' => $this->wpdb->prefix . 'sod_bookings',
            'booking_events' => $this->wpdb->prefix . 'sod_booking_events',
            'payment_preferences' => $this->wpdb->prefix . 'sod_payment_preferences',
            'passes' => $this->wpdb->prefix . 'sod_passes',
            'pass_usage' => $this->wpdb->prefix . 'sod_pass_usage',
            'packages' => $this->wpdb->prefix . 'sod_packages',
            'package_usage' => $this->wpdb->prefix . 'sod_package_usage',
        ];

        $sql = [];

        // Service Categories Table
        $sql['service_categories'] = "CREATE TABLE IF NOT EXISTS {$tables['service_categories']} (
            category_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT
        ) {$this->charset_collate};";

        // Services Table
        $sql['services'] = "CREATE TABLE IF NOT EXISTS {$tables['services']} (
            service_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            category_id BIGINT(20) UNSIGNED,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            FOREIGN KEY (category_id) REFERENCES {$tables['service_categories']}(category_id) ON DELETE CASCADE
        ) {$this->charset_collate};";

        // Service Attributes Table
        $sql['service_attributes'] = "CREATE TABLE IF NOT EXISTS {$tables['service_attributes']} (
            attribute_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            service_id BIGINT(20) UNSIGNED NOT NULL,
            attribute_type VARCHAR(50) NOT NULL,
            value VARCHAR(255) NOT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            product_id BIGINT(20) UNSIGNED NULL,
            variation_id BIGINT(20) UNSIGNED NULL,
            passes INT UNSIGNED NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            KEY service_idx (service_id),
            KEY type_value_idx (attribute_type, value),
            FOREIGN KEY (service_id) REFERENCES {$tables['services']}(service_id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES {$this->wpdb->prefix}posts(ID) ON DELETE SET NULL,
            FOREIGN KEY (variation_id) REFERENCES {$this->wpdb->prefix}posts(ID) ON DELETE SET NULL
        ) {$this->charset_collate};";

        // Customers Table
        $sql['customers'] = "CREATE TABLE IF NOT EXISTS {$tables['customers']} (
            customer_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT(20) UNSIGNED UNIQUE,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            phone VARCHAR(20),
            emergency_contact_name VARCHAR(255),
            emergency_contact_phone VARCHAR(20),
            signing_dependent BOOLEAN DEFAULT FALSE,
            dependent_name VARCHAR(255),
            dependent_dob DATE,
            FOREIGN KEY (user_id) REFERENCES {$this->wpdb->prefix}users(ID) ON DELETE CASCADE
        ) {$this->charset_collate};";

        // Staff Table
        $sql['staff'] = "CREATE TABLE IF NOT EXISTS {$tables['staff']} (
            staff_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT(20) UNSIGNED NOT NULL UNIQUE,
            phone_number VARCHAR(20) NOT NULL,
            accepts_cash TINYINT(1) NOT NULL DEFAULT 0,
            services TEXT,
            FOREIGN KEY (user_id) REFERENCES {$this->wpdb->prefix}users(ID) ON DELETE CASCADE
        ) {$this->charset_collate};";

        // Staff Availability Table
        $sql['staff_availability'] = "CREATE TABLE IF NOT EXISTS {$tables['staff_availability']} (
            availability_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            staff_id BIGINT(20) UNSIGNED NOT NULL,
            service_id BIGINT(20) UNSIGNED NOT NULL,
            day_of_week VARCHAR(20),
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            date DATE NULL,
            recurring_type VARCHAR(50) NULL,
            recurring_end_date DATE NULL,
            appointment_only TINYINT(1) NOT NULL DEFAULT 0,
            FOREIGN KEY (staff_id) REFERENCES {$tables['staff']}(staff_id) ON DELETE CASCADE,
            FOREIGN KEY (service_id) REFERENCES {$tables['services']}(service_id) ON DELETE CASCADE
        ) {$this->charset_collate};";

        // Staff Services Table
        $sql['staff_services'] = "CREATE TABLE IF NOT EXISTS {$tables['staff_services']} (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            staff_id BIGINT(20) UNSIGNED NOT NULL,
            service_id BIGINT(20) UNSIGNED NOT NULL,
            FOREIGN KEY (staff_id) REFERENCES {$tables['staff']}(staff_id) ON DELETE CASCADE,
            FOREIGN KEY (service_id) REFERENCES {$tables['services']}(service_id) ON DELETE CASCADE
        ) {$this->charset_collate};";

        // Bookings Table (Updated with end_time and attributes)
        $sql['bookings'] = "CREATE TABLE IF NOT EXISTS {$tables['bookings']} (
            booking_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            service_id BIGINT(20) UNSIGNED NOT NULL,
            customer_id BIGINT(20) UNSIGNED NOT NULL,
            staff_id BIGINT(20) UNSIGNED,
            start_time DATETIME NOT NULL,
            end_time DATETIME NOT NULL, -- Added to track booking end time
            attribute_type VARCHAR(50) NOT NULL, -- Type of attribute (e.g., 'duration', 'pass')
            attribute_value VARCHAR(255) NOT NULL, -- Value of the attribute (e.g., '60', '5 passes')
            status VARCHAR(50) NOT NULL DEFAULT 'pending',
            payment_method VARCHAR(50),
            order_id BIGINT(20) UNSIGNED,
            FOREIGN KEY (service_id) REFERENCES {$tables['services']}(service_id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES {$tables['customers']}(customer_id) ON DELETE CASCADE,
            FOREIGN KEY (staff_id) REFERENCES {$tables['staff']}(staff_id) ON DELETE CASCADE,
            FOREIGN KEY (order_id) REFERENCES {$this->wpdb->prefix}posts(ID) ON DELETE SET NULL
        ) {$this->charset_collate};";

        // Booking Events Table
        $sql['booking_events'] = "CREATE TABLE IF NOT EXISTS {$tables['booking_events']} (
            event_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            booking_id BIGINT(20) UNSIGNED NOT NULL,
            event_type VARCHAR(50) NOT NULL,
            event_timestamp DATETIME NOT NULL,
            FOREIGN KEY (booking_id) REFERENCES {$tables['bookings']}(booking_id) ON DELETE CASCADE
        ) {$this->charset_collate};";

        // Payment Preferences Table
        $sql['payment_preferences'] = "CREATE TABLE IF NOT EXISTS {$tables['payment_preferences']} (
            preference_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            staff_id BIGINT(20) UNSIGNED NOT NULL,
            accepts_cash TINYINT(1) NOT NULL DEFAULT 0,
            accepts_digital TINYINT(1) NOT NULL DEFAULT 0,
            FOREIGN KEY (staff_id) REFERENCES {$tables['staff']}(staff_id) ON DELETE CASCADE
        ) {$this->charset_collate};";

        // Passes Table
        $sql['passes'] = "CREATE TABLE IF NOT EXISTS {$tables['passes']} (
            pass_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            order_id BIGINT(20) UNSIGNED NOT NULL,
            service_id BIGINT(20) UNSIGNED NOT NULL,
            attribute_type VARCHAR(50) NOT NULL,
            attribute_value VARCHAR(255) NOT NULL,
            total_passes INT NOT NULL,
            remaining_passes INT NOT NULL,
            expiration_date DATE NULL,
            pass_type VARCHAR(50) NOT NULL DEFAULT 'single',
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            KEY user_idx (user_id),
            KEY order_idx (order_id),
            KEY service_idx (service_id),
            FOREIGN KEY (user_id) REFERENCES {$this->wpdb->prefix}users(ID) ON DELETE CASCADE,
            FOREIGN KEY (service_id) REFERENCES {$tables['services']}(service_id) ON DELETE CASCADE
        ) {$this->charset_collate};";

        // Pass Usage Table
        $sql['pass_usage'] = "CREATE TABLE IF NOT EXISTS {$tables['pass_usage']} (
            usage_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            pass_id BIGINT(20) UNSIGNED NOT NULL,
            booking_id BIGINT(20) UNSIGNED NOT NULL,
            usage_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY pass_idx (pass_id),
            KEY booking_idx (booking_id),
            FOREIGN KEY (pass_id) REFERENCES {$tables['passes']}(pass_id) ON DELETE CASCADE,
            FOREIGN KEY (booking_id) REFERENCES {$tables['bookings']}(booking_id) ON DELETE CASCADE
        ) {$this->charset_collate};";

        // Packages Table
        $sql['packages'] = "CREATE TABLE IF NOT EXISTS {$tables['packages']} (
            package_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            order_id BIGINT(20) UNSIGNED NOT NULL,
            package_name VARCHAR(255) NOT NULL,
            description TEXT,
            service_ids TEXT,
            price DECIMAL(10,2) NOT NULL,
            expiration_date DATE NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
            KEY user_idx (user_id),
            KEY order_idx (order_id),
            FOREIGN KEY (user_id) REFERENCES {$this->wpdb->prefix}users(ID) ON DELETE CASCADE
        ) {$this->charset_collate};";

        // Package Usage Table
        $sql['package_usage'] = "CREATE TABLE IF NOT EXISTS {$tables['package_usage']} (
            usage_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            package_id BIGINT(20) UNSIGNED NOT NULL,
            booking_id BIGINT(20) UNSIGNED NOT NULL,
            service_id BIGINT(20) UNSIGNED NOT NULL,
            usage_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY package_idx (package_id),
            KEY booking_idx (booking_id),
            FOREIGN KEY (package_id) REFERENCES {$tables['packages']}(package_id) ON DELETE CASCADE,
            FOREIGN KEY (booking_id) REFERENCES {$tables['bookings']}(booking_id) ON DELETE CASCADE,
            FOREIGN KEY (service_id) REFERENCES {$tables['services']}(service_id) ON DELETE CASCADE
        ) {$this->charset_collate};";

        // Execute SQL queries to create or update tables
        foreach ($sql as $table => $query) {
            dbDelta($query);
            error_log("Created/updated table: {$table}");
        }
    }

    /**
     * Drops a specified table from the database if it exists.
     *
     * Useful for cleanup or uninstallation processes.
     *
     * @param string $table_name The name of the table to drop.
     */
    public function drop_table($table_name) {
        if ($this->wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") == $table_name) {
            $this->wpdb->query("DROP TABLE {$table_name}");
            error_log("Dropped table: {$table_name}");
        }
    }
}