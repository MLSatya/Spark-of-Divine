<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class SOD_DB_Access {
    private $wpdb;
    private $charset_collate;

    public function __construct($wpdb) {
        $this->wpdb = $wpdb;
        $this->charset_collate = $wpdb->get_charset_collate();
        $this->loadTables(); // This will ensure tables are created on plugin activation
    }

    // Create tables if they don't exist
    public function loadTables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Table names
        $customers_table = $this->wpdb->prefix . 'sod_customers';
        $staff_table = $this->wpdb->prefix . 'sod_staff';
        $staff_availability_table = $this->wpdb->prefix . 'sod_staff_availability';
        $categories_table = $this->wpdb->prefix . 'sod_service_categories';
        $services_table = $this->wpdb->prefix . 'sod_services';
        $bookings_table = $this->wpdb->prefix . 'sod_bookings';
        $events_table = $this->wpdb->prefix . 'sod_booking_events';
        $payment_preferences_table = $this->wpdb->prefix . 'sod_payment_preferences';

        // SQL for creating tables
        $sql_customers = "CREATE TABLE IF NOT EXISTS $customers_table (
        customer_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        phone VARCHAR(20),
        client_phone VARCHAR(20),
        emergency_contact_name VARCHAR(255),
        emergency_contact_phone VARCHAR(20),
        signing_dependent BOOLEAN DEFAULT FALSE,
        dependent_name VARCHAR(255),
        dependent_dob DATE
        ) $this->charset_collate;";
        
        $sql_users_staff = "CREATE TABLE IF NOT EXISTS $users_staff_table (
        id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        staff_id BIGINT(20) UNSIGNED NOT NULL,
        FOREIGN KEY (user_id) REFERENCES {$this->wpdb->prefix}users(ID) ON DELETE CASCADE,
        FOREIGN KEY (staff_id) REFERENCES {$this->wpdb->prefix}sod_staff(staff_id) ON DELETE CASCADE
    ) $this->charset_collate;";

        $sql_users_services = "CREATE TABLE IF NOT EXISTS $users_services_table (
        id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        service_id BIGINT(20) UNSIGNED NOT NULL,
        FOREIGN KEY (user_id) REFERENCES {$this->wpdb->prefix}users(ID) ON DELETE CASCADE,
        FOREIGN KEY (service_id) REFERENCES {$this->wpdb->prefix}sod_services(service_id) ON DELETE CASCADE
    ) $this->charset_collate;";

        // 4. Bookings Table
        $bookings_table = $this->wpdb->prefix . 'sod_bookings';
        $sql_bookings = "CREATE TABLE IF NOT EXISTS $bookings_table (
        booking_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT(20) UNSIGNED NOT NULL, -- Foreign key to users table
        service_id BIGINT(20) UNSIGNED NOT NULL, -- Foreign key to services table
        staff_id BIGINT(20) UNSIGNED NOT NULL, -- Foreign key to staff table
        date DATE NOT NULL,
        time TIME NOT NULL,
        duration INT(11) NOT NULL,
        status VARCHAR(50) DEFAULT 'pending',
        payment_method VARCHAR(50) DEFAULT 'digital',
        FOREIGN KEY (user_id) REFERENCES {$this->wpdb->prefix}users(ID) ON DELETE CASCADE,
        FOREIGN KEY (service_id) REFERENCES {$this->wpdb->prefix}sod_services(service_id) ON DELETE CASCADE,
        FOREIGN KEY (staff_id) REFERENCES {$this->wpdb->prefix}sod_staff(staff_id) ON DELETE CASCADE
        ) $this->charset_collate;";
        dbDelta($sql_bookings);

        $sql_events = "CREATE TABLE IF NOT EXISTS $events_table (
            event_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            booking_id BIGINT(20) UNSIGNED NOT NULL,
            event_type VARCHAR(50) NOT NULL,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (booking_id) REFERENCES $bookings_table(booking_id) ON DELETE CASCADE
        ) $this->charset_collate;";

        $sql_payment_preferences = "CREATE TABLE IF NOT EXISTS $payment_preferences_table (
            payment_preference_id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            staff_id BIGINT(20) UNSIGNED NOT NULL,
            accepts_cash BOOLEAN NOT NULL DEFAULT FALSE,
            accepts_digital BOOLEAN NOT NULL DEFAULT TRUE,
            FOREIGN KEY (staff_id) REFERENCES $staff_table(staff_id) ON DELETE CASCADE
        ) $this->charset_collate;";

       // Execute the table creation
        dbDelta($sql_customers);
        dbDelta($sql_staff);
        dbDelta($sql_services);
        dbDelta($sql_bookings);
        dbDelta($sql_events);
        dbDelta($sql_payment_preferences);
    }

   // Create tables if they don't exist
    public function loadTables() {
        // Existing table creation code...
    }

    // Add CRUD methods for tables here

    // ------------------
    // Customers Table CRUD
    // ------------------

    // Create a customer
    public function createCustomer($name, $email, $client_phone, $emergency_contact_name, $emergency_contact_phone, $signing_dependent, $dependent_name, $dependent_dob) {
    $this->wpdb->insert($this->wpdb->prefix . 'sod_customers', [
        'name' => $name,
        'email' => $email,
        'client_phone' => $client_phone,
        'emergency_contact_name' => $emergency_contact_name,
        'emergency_contact_phone' => $emergency_contact_phone,
        'signing_dependent' => $signing_dependent,
        'dependent_name' => $dependent_name,
        'dependent_dob' => $dependent_dob,
    ], [
        '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s'
    ]);
    return $this->wpdb->insert_id;
    }

    // Read a customer
    public function getCustomer($customer_id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->customers_table} WHERE customer_id = %d", 
            $customer_id
        ));
    }

    // Update a customer
    public function updateCustomer($customer_id, $name, $email, $phone) {
        return $this->wpdb->update($this->customers_table, [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
        ], [
            'customer_id' => $customer_id,
        ], [
            '%s', '%s', '%s'
        ], [
            '%d'
        ]);
    }

    // Delete a customer
    public function deleteCustomer($customer_id) {
        return $this->wpdb->delete($this->customers_table, ['customer_id' => $customer_id], ['%d']);
    }

    // ------------------
    // Staff Table CRUD
    // ------------------

    // Create a staff member
    public function createStaff($name, $role, $availability) {
        $this->wpdb->insert($this->staff_table, [
            'name' => $name,
            'role' => $role,
            'availability' => $availability,
        ], [
            '%s', '%s', '%s'
        ]);
        return $this->wpdb->insert_id;
    }

    // Read a staff member
    public function getStaff($staff_id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->staff_table} WHERE staff_id = %d", 
            $staff_id
        ));
    }

    // Update a staff member
    public function updateStaff($staff_id, $name, $role, $availability) {
        return $this->wpdb->update($this->staff_table, [
            'name' => $name,
            'role' => $role,
            'availability' => $availability,
        ], [
            'staff_id' => $staff_id,
        ], [
            '%s', '%s', '%s'
        ], [
            '%d'
        ]);
    }

    // Delete a staff member
    public function deleteStaff($staff_id) {
        return $this->wpdb->delete($this->staff_table, ['staff_id' => $staff_id], ['%d']);
    }

    // ------------------
    // Services Table CRUD
    // ------------------

    // Create a service
    public function createService($name, $description, $price) {
        $this->wpdb->insert($this->services_table, [
            'name' => $name,
            'description' => $description,
            'price' => $price,
        ], [
            '%s', '%s', '%f'
        ]);
        return $this->wpdb->insert_id;
    }

    // Read a service
    public function getService($service_id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->services_table} WHERE service_id = %d", 
            $service_id
        ));
    }

    // Update a service
    public function updateService($service_id, $name, $description, $price) {
        return $this->wpdb->update($this->services_table, [
            'name' => $name,
            'description' => $description,
            'price' => $price,
        ], [
            'service_id' => $service_id,
        ], [
            '%s', '%s', '%f'
        ], [
            '%d'
        ]);
    }

    // Delete a service
    public function deleteService($service_id) {
        return $this->wpdb->delete($this->services_table, ['service_id' => $service_id], ['%d']);
    }

    // ------------------
    // Bookings Table CRUD
    // ------------------

    // Create a booking
    public function createBooking($customer_id, $service_id, $staff_id, $date, $time, $duration, $status, $payment_method) {
        $this->wpdb->insert($this->bookings_table, [
            'customer_id' => $customer_id,
            'service_id' => $service_id,
            'staff_id' => $staff_id,
            'date' => $date,
            'time' => $time,
            'duration' => $duration,
            'status' => $status,
            'payment_method' => $payment_method,
        ], [
            '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s'
        ]);
        return $this->wpdb->insert_id;
    }

    // Read a booking
    public function getBooking($booking_id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->bookings_table} WHERE booking_id = %d", 
            $booking_id
        ));
    }

    // Update a booking
    public function updateBooking($booking_id, $status, $payment_method) {
        return $this->wpdb->update($this->bookings_table, [
            'status' => $status,
            'payment_method' => $payment_method,
        ], [
            'booking_id' => $booking_id,
        ], [
            '%s', '%s'
        ], [
            '%d'
        ]);
    }

    // Delete a booking
    public function deleteBooking($booking_id) {
        return $this->wpdb->delete($this->bookings_table, ['booking_id' => $booking_id], ['%d']);
    }
    // ------------------
// Staff Table CRUD
// ------------------

    // Create a staff member
    public function createStaff($name, $role, $availability) {
    $this->wpdb->insert($this->wpdb->prefix . 'sod_staff', [
        'name' => $name,
        'role' => $role,
        'availability' => maybe_serialize($availability),
    ], [
        '%s', '%s', '%s'
    ]);
    return $this->wpdb->insert_id;
}

    // Read a staff member
    public function getStaff($staff_id) {
    return $this->wpdb->get_row($this->wpdb->prepare(
        "SELECT * FROM {$this->wpdb->prefix}sod_staff WHERE staff_id = %d", 
        $staff_id
    ));
}

    // Update a staff member
    public function updateStaff($staff_id, $name, $role, $availability) {
    return $this->wpdb->update($this->wpdb->prefix . 'sod_staff', [
        'name' => $name,
        'role' => $role,
        'availability' => maybe_serialize($availability),
    ], [
        'staff_id' => $staff_id,
    ], [
        '%s', '%s', '%s'
    ], [
        '%d'
    ]);
}

    // Delete a staff member
    public function deleteStaff($staff_id) {
    return $this->wpdb->delete($this->wpdb->prefix . 'sod_staff', ['staff_id' => $staff_id], ['%d']);
}

// ------------------
// Services Table CRUD
// ------------------

    // Create a service
    public function createService($name, $description, $price) {
    $this->wpdb->insert($this->wpdb->prefix . 'sod_services', [
        'name' => $name,
        'description' => $description,
        'price' => $price,
    ], [
        '%s', '%s', '%f'
    ]);
    return $this->wpdb->insert_id;
}

    // Read a service
    public function getService($service_id) {
    return $this->wpdb->get_row($this->wpdb->prepare(
        "SELECT * FROM {$this->wpdb->prefix}sod_services WHERE service_id = %d", 
        $service_id
    ));
}

    // Update a service
    public function updateService($service_id, $name, $description, $price) {
    return $this->wpdb->update($this->wpdb->prefix . 'sod_services', [
        'name' => $name,
        'description' => $description,
        'price' => $price,
    ], [
        'service_id' => $service_id,
    ], [
        '%s', '%s', '%f'
    ], [
        '%d'
    ]);
}

    // Delete a service
    public function deleteService($service_id) {
    return $this->wpdb->delete($this->wpdb->prefix . 'sod_services', ['service_id' => $service_id], ['%d']);
}

// ------------------
// Booking Events Table CRUD
// ------------------

    // Create a booking event
    public function createBookingEvent($booking_id, $event_type) {
    $this->wpdb->insert($this->wpdb->prefix . 'sod_booking_events', [
        'booking_id' => $booking_id,
        'event_type' => $event_type,
    ], [
        '%d', '%s'
    ]);
    return $this->wpdb->insert_id;
}

    // Read booking events
    public function getBookingEvents($booking_id) {
    return $this->wpdb->get_results($this->wpdb->prepare(
        "SELECT * FROM {$this->wpdb->prefix}sod_booking_events WHERE booking_id = %d", 
        $booking_id
    ));
}

    // Delete booking events (usually cascade delete will handle this)
    public function deleteBookingEvents($booking_id) {
    return $this->wpdb->delete($this->wpdb->prefix . 'sod_booking_events', ['booking_id' => $booking_id], ['%d']);
}

// ------------------
// Payment Preferences Table CRUD
// ------------------

    // Create or update payment preferences
    public function savePaymentPreferences($staff_id, $accepts_cash, $accepts_digital) {
    $existing = $this->wpdb->get_row($this->wpdb->prepare(
        "SELECT payment_preference_id FROM {$this->wpdb->prefix}sod_payment_preferences WHERE staff_id = %d", 
        $staff_id
    ));
    
    if ($existing) {
        return $this->wpdb->update($this->wpdb->prefix . 'sod_payment_preferences', [
            'accepts_cash' => $accepts_cash,
            'accepts_digital' => $accepts_digital,
        ], [
            'staff_id' => $staff_id,
        ], [
            '%d', '%d'
        ], [
            '%d'
        ]);
    } else {
        return $this->wpdb->insert($this->wpdb->prefix . 'sod_payment_preferences', [
            'staff_id' => $staff_id,
            'accepts_cash' => $accepts_cash,
            'accepts_digital' => $accepts_digital,
        ], [
            '%d', '%d', '%d'
        ]);
    }
}

    // Read payment preferences
    public function getPaymentPreferences($staff_id) {
    return $this->wpdb->get_row($this->wpdb->prepare(
        "SELECT * FROM {$this->wpdb->prefix}sod_payment_preferences WHERE staff_id = %d", 
        $staff_id
    ));
}

    // Delete payment preferences
    public function deletePaymentPreferences($staff_id) {
    return $this->wpdb->delete($this->wpdb->prefix . 'sod_payment_preferences', ['staff_id' => $staff_id], ['%d']);
    }
}