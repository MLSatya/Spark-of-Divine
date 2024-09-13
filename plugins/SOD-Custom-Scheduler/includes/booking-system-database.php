<?php 
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class BookingSystemDatabase {
    private $wpdb;
    private $charset_collate;

    public function __construct($wpdb) {
        $this->wpdb = $wpdb;
        $this->charset_collate = $wpdb->get_charset_collate();
    }

    public function loadTables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // 1. Bookings Table
        $bookings_table = $this->wpdb->prefix . 'bookings';
        $sql_bookings = "CREATE TABLE IF NOT EXISTS $bookings_table (
            booking_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_id BIGINT(20) UNSIGNED NOT NULL,
            service_id BIGINT(20) UNSIGNED NOT NULL,
            staff_id BIGINT(20) UNSIGNED NOT NULL,
            date DATE NOT NULL,
            time TIME NOT NULL,
            duration INT(11) NOT NULL,
            status VARCHAR(50) DEFAULT 'pending',
            payment_method VARCHAR(50) DEFAULT 'digital',
            FOREIGN KEY (customer_id) REFERENCES {$this->wpdb->prefix}customers(customer_id) ON DELETE CASCADE,
            FOREIGN KEY (staff_id) REFERENCES {$this->wpdb->prefix}staff(staff_id) ON DELETE CASCADE
        ) $this->charset_collate;";
        dbDelta($sql_bookings);

        // 2. Customers Table
        $customers_table = $this->wpdb->prefix . 'customers';
        $sql_customers = "CREATE TABLE IF NOT EXISTS $customers_table (
            customer_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(20)
        ) $this->charset_collate;";
        dbDelta($sql_customers);

        // 3. Staff Table
        $staff_table = $this->wpdb->prefix . 'staff';
        $sql_staff = "CREATE TABLE IF NOT EXISTS $staff_table (
            staff_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            role VARCHAR(255) NOT NULL,
            availability TEXT
        ) $this->charset_collate;";
        dbDelta($sql_staff);

        // 4. Booking Events Table
        $events_table = $this->wpdb->prefix . 'booking_events';
        $sql_events = "CREATE TABLE IF NOT EXISTS $events_table (
            event_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            booking_id BIGINT(20) UNSIGNED NOT NULL,
            event_type VARCHAR(50) NOT NULL,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (booking_id) REFERENCES $bookings_table(booking_id) ON DELETE CASCADE
        ) $this->charset_collate;";
        dbDelta($sql_events);

        // 5. Services Table
        $services_table = $this->wpdb->prefix . 'services';
        $sql_services = "CREATE TABLE IF NOT EXISTS $services_table (
            service_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            price DECIMAL(10, 2) NOT NULL
        ) $this->charset_collate;";
        dbDelta($sql_services);

        // 6. Payment Preferences Table
        $payment_preferences_table = $this->wpdb->prefix . 'payment_preferences';
        $sql_payment_preferences = "CREATE TABLE IF NOT EXISTS $payment_preferences_table (
            payment_preference_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            staff_id BIGINT(20) UNSIGNED NOT NULL,
            accepts_cash BOOLEAN DEFAULT FALSE,
            accepts_digital BOOLEAN DEFAULT TRUE,
            FOREIGN KEY (staff_id) REFERENCES $staff_table(staff_id) ON DELETE CASCADE
        ) $this->charset_collate;";
        dbDelta($sql_payment_preferences);
    }
}

// To use this class, you would instantiate it and call the loadTables method:
global $wpdb;
$bookingSystemDatabase = new BookingSystemDatabase($wpdb);
$bookingSystemDatabase->loadTables();