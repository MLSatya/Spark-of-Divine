<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Handles synchronization between the sod_booking post type and the custom sod_bookings database table.
 * Updated to work with product_id instead of service_id.
 */
class SOD_Booking_Sync {
    private static $instance;
    private $wpdb;
    private $booking_table;
    private $debug_mode = false;

    /**
     * Get the singleton instance.
     */
    public static function getInstance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor to initialize hooks.
     */
    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->booking_table = $wpdb->prefix . 'sod_bookings';
        
        // Set debug mode based on WordPress constant
        $this->debug_mode = defined('WP_DEBUG') && WP_DEBUG;
        
        // Ensure we use the correct table name
        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->booking_table}'") !== $this->booking_table) {
            $this->booking_table = 'wp_3be9vb_sod_bookings';
        }
        
        // Hook into post actions
        add_action('save_post_sod_booking', array($this, 'sync_post_to_database'), 10, 3);
        add_action('before_delete_post', array($this, 'delete_booking_from_database'));
        add_action('wp_trash_post', array($this, 'update_booking_status_to_cancelled'));
        
        // Hook into custom database actions
        add_action('sod_after_create_booking', array($this, 'create_booking_post_from_database'), 10, 2);
        add_action('sod_after_update_booking', array($this, 'update_booking_post_from_database'), 10, 2);
        add_action('sod_after_delete_booking', array($this, 'delete_booking_post_from_database'), 10, 1);
        
        // Init hook to check table structure
        add_action('init', array($this, 'ensure_database_table_exists'));
        
        $this->log("SOD Booking Sync initialized");
    }

    /**
     * Log message to error log if debug mode is enabled
     * 
     * @param string $message Message to log
     * @param string $type Type of message (info, error, warning)
     */
    private function log($message, $type = 'info') {
        if ($this->debug_mode) {
            error_log("[SOD Booking Sync] [$type] $message");
        }
    }

    /**
     * Make sure the booking table exists and has necessary columns.
     */
    public function ensure_database_table_exists() {
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$this->booking_table}'") === $this->booking_table;
        
        if (!$table_exists) {
            $this->create_bookings_table();
        } else {
            // Check if product_id column exists, add if not
            $column_exists = $this->wpdb->get_var("SHOW COLUMNS FROM {$this->booking_table} LIKE 'product_id'");
            if (!$column_exists) {
                $this->wpdb->query("ALTER TABLE {$this->booking_table} ADD COLUMN product_id bigint unsigned DEFAULT 0 AFTER customer_id");
                $this->log("Added product_id column to bookings table");
            }
            
            // Check if end_time column exists, add if not
            $column_exists = $this->wpdb->get_var("SHOW COLUMNS FROM {$this->booking_table} LIKE 'end_time'");
            if (!$column_exists) {
                $this->wpdb->query("ALTER TABLE {$this->booking_table} ADD COLUMN end_time time NOT NULL AFTER start_time");
                $this->log("Added end_time column to bookings table");
            }
            
            // Check if duration column exists, add if not
            $column_exists = $this->wpdb->get_var("SHOW COLUMNS FROM {$this->booking_table} LIKE 'duration'");
            if (!$column_exists) {
                $this->wpdb->query("ALTER TABLE {$this->booking_table} ADD COLUMN duration int DEFAULT 60 AFTER end_time");
                $this->log("Added duration column to bookings table");
            }
            
            // Check if remaining_balance column exists, add if not
            $column_exists = $this->wpdb->get_var("SHOW COLUMNS FROM {$this->booking_table} LIKE 'remaining_balance'");
            if (!$column_exists) {
                $this->wpdb->query("ALTER TABLE {$this->booking_table} ADD COLUMN remaining_balance decimal(10,2) DEFAULT 0.00 AFTER payment_method");
                $this->log("Added remaining_balance column to bookings table");
            }
            
            // Check if balance_amount column exists, add if not
            $column_exists = $this->wpdb->get_var("SHOW COLUMNS FROM {$this->booking_table} LIKE 'balance_amount'");
            if (!$column_exists) {
                $this->wpdb->query("ALTER TABLE {$this->booking_table} ADD COLUMN balance_amount decimal(10,2) DEFAULT 0.00 AFTER remaining_balance");
                $this->log("Added balance_amount column to bookings table");
            }
            
            // Check if original_booking_id column exists, add if not
            $column_exists = $this->wpdb->get_var("SHOW COLUMNS FROM {$this->booking_table} LIKE 'original_booking_id'");
            if (!$column_exists) {
                $this->wpdb->query("ALTER TABLE {$this->booking_table} ADD COLUMN original_booking_id bigint unsigned DEFAULT 0 AFTER balance_amount");
                $this->log("Added original_booking_id column to bookings table");
            }
            
            // Check for created_at and updated_at timestamps
            $column_exists = $this->wpdb->get_var("SHOW COLUMNS FROM {$this->booking_table} LIKE 'created_at'");
            if (!$column_exists) {
                $this->wpdb->query("ALTER TABLE {$this->booking_table} ADD COLUMN created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP");
                $this->log("Added created_at column to bookings table");
            }
            
            $column_exists = $this->wpdb->get_var("SHOW COLUMNS FROM {$this->booking_table} LIKE 'updated_at'");
            if (!$column_exists) {
                $this->wpdb->query("ALTER TABLE {$this->booking_table} ADD COLUMN updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
                $this->log("Added updated_at column to bookings table");
            }
        }
    }

    /**
     * Create the bookings table if it doesn't exist
     */
    private function create_bookings_table() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->booking_table} (
            booking_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) UNSIGNED DEFAULT NULL,
            product_id bigint(20) UNSIGNED DEFAULT 0,
            service_id bigint(20) UNSIGNED DEFAULT NULL,
            staff_id bigint(20) UNSIGNED NOT NULL,
            date date NOT NULL,
            start_time time NOT NULL,
            end_time time NOT NULL,
            duration int DEFAULT 60,
            status varchar(50) DEFAULT 'pending',
            order_id bigint(20) UNSIGNED DEFAULT NULL,
            payment_method varchar(50) DEFAULT NULL,
            remaining_balance decimal(10,2) DEFAULT 0.00,
            balance_amount decimal(10,2) DEFAULT 0.00,
            original_booking_id bigint(20) UNSIGNED DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (booking_id),
            KEY customer_id (customer_id),
            KEY product_id (product_id),
            KEY service_id (service_id),
            KEY staff_id (staff_id),
            KEY date (date),
            KEY status (status),
            KEY order_id (order_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        $this->log("Created bookings table");
    }

    /**
     * Sync post data to the database when a booking post is saved.
     */
    public function sync_post_to_database($post_id, $post, $update) {
        // Skip auto-saves and revisions
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;
        if ($post->post_type !== 'sod_booking') return;
        
        // Get post meta data
        $product_id = get_post_meta($post_id, 'sod_product_id', true);
        $service_id = get_post_meta($post_id, 'sod_service_id', true);
        $staff_id = get_post_meta($post_id, 'sod_staff_id', true);
        $start_time = get_post_meta($post_id, 'sod_start_time', true);
        $end_time = get_post_meta($post_id, 'sod_end_time', true);
        $duration = get_post_meta($post_id, 'sod_duration', true) ?: 60;
        $status = get_post_meta($post_id, 'sod_status', true) ?: 'pending';
        $customer_id = get_post_meta($post_id, 'sod_customer_id', true) ?: get_current_user_id();
        $order_id = get_post_meta($post_id, 'sod_order_id', true) ?: 0;
        $payment_method = get_post_meta($post_id, 'sod_payment_method', true) ?: '';
        $remaining_balance = get_post_meta($post_id, 'sod_remaining_balance', true) ?: 0;
        $balance_amount = get_post_meta($post_id, 'sod_balance_amount', true) ?: 0;
        $original_booking_id = get_post_meta($post_id, 'sod_original_booking_id', true) ?: 0;
        
        // If product_id is not set but service_id is, try to get the product from the service
        if (!$product_id && $service_id) {
            $service_product_id = get_post_meta($service_id, '_sod_product_id', true);
            if ($service_product_id) {
                $product_id = $service_product_id;
                update_post_meta($post_id, 'sod_product_id', $product_id); // Save for future use
                $this->log("Updated booking $post_id with product_id $product_id from associated service $service_id");
            }
        }
        
        // Parse start_time to extract date and time components
        if (!empty($start_time)) {
            $start_datetime = new DateTime($start_time);
            $date = $start_datetime->format('Y-m-d');
            $time = $start_datetime->format('H:i:s');
            
            // If end_time is empty, calculate based on duration
            if (empty($end_time)) {
                $end_datetime = clone $start_datetime;
                $end_datetime->modify("+{$duration} minutes");
                $end_time = $end_datetime->format('H:i:s');
            } else {
                $end_datetime = new DateTime($end_time);
                $end_time = $end_datetime->format('H:i:s');
            }
            
            // Check if this booking exists in the database
            $existing_id = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT booking_id FROM {$this->booking_table} WHERE booking_id = %d",
                $post_id
            ));
            
            if ($existing_id) {
                // Update existing record
                $this->wpdb->update(
                    $this->booking_table,
                    array(
                        'customer_id' => $customer_id,
                        'product_id' => $product_id,
                        'service_id' => $service_id, // Keep for backward compatibility
                        'staff_id' => $staff_id,
                        'date' => $date,
                        'start_time' => $time,
                        'end_time' => $end_time,
                        'duration' => $duration,
                        'status' => $status,
                        'order_id' => $order_id,
                        'payment_method' => $payment_method,
                        'remaining_balance' => $remaining_balance,
                        'balance_amount' => $balance_amount,
                        'original_booking_id' => $original_booking_id
                    ),
                    array('booking_id' => $post_id),
                    array('%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%f', '%f', '%d'),
                    array('%d')
                );
                
                $this->log("Updated booking in database: $post_id");
            } else {
                // Insert new record, try to use post ID as booking_id
                $this->wpdb->insert(
                    $this->booking_table,
                    array(
                        'booking_id' => $post_id,
                        'customer_id' => $customer_id,
                        'product_id' => $product_id,
                        'service_id' => $service_id, // Keep for backward compatibility
                        'staff_id' => $staff_id,
                        'date' => $date,
                        'start_time' => $time,
                        'end_time' => $end_time,
                        'duration' => $duration,
                        'status' => $status,
                        'order_id' => $order_id,
                        'payment_method' => $payment_method,
                        'remaining_balance' => $remaining_balance,
                        'balance_amount' => $balance_amount,
                        'original_booking_id' => $original_booking_id
                    ),
                    array('%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%f', '%f', '%d')
                );
                
                $this->log("Inserted booking into database: $post_id");
            }
        } else {
            $this->log("Missing start_time for booking $post_id, cannot sync to database", 'warning');
        }
    }

    /**
     * Delete booking from database when post is deleted.
     */
    public function delete_booking_from_database($post_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'sod_booking') return;
        
        $this->wpdb->delete(
            $this->booking_table,
            array('booking_id' => $post_id),
            array('%d')
        );
        
        $this->log("Deleted booking from database: $post_id");
    }

    /**
     * Update booking status to cancelled when post is trashed.
     */
    public function update_booking_status_to_cancelled($post_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'sod_booking') return;
        
        $this->wpdb->update(
            $this->booking_table,
            array('status' => 'cancelled'),
            array('booking_id' => $post_id),
            array('%s'),
            array('%d')
        );
        
        update_post_meta($post_id, 'sod_status', 'cancelled');
        $this->log("Updated booking status to cancelled for post $post_id");
    }

    /**
     * Create a booking post from database record.
     */
    public function create_booking_post_from_database($booking_id, $booking_data) {
        // Check if post already exists
        $existing_post = get_post($booking_id);
        if ($existing_post && $existing_post->post_type === 'sod_booking') {
            $this->log("Booking post already exists for ID $booking_id, updating instead");
            $this->update_booking_post_from_database($booking_id, $booking_data);
            return;
        }
        
        // Get product and staff names for title
        $product_title = "Unknown Product";
        if (!empty($booking_data['product_id'])) {
            $product = wc_get_product($booking_data['product_id']);
            if ($product) {
                $product_title = $product->get_title();
            }
        } elseif (!empty($booking_data['service_id'])) {
            $service_title = get_the_title($booking_data['service_id']);
            if ($service_title) {
                $product_title = $service_title;
            }
        }
        
        $staff_title = get_the_title($booking_data['staff_id']) ?: "Unknown Staff";
        $date = $booking_data['date'];
        $time = $booking_data['start_time'];
        
        // Create post
        $post_data = array(
            'post_type' => 'sod_booking',
            'post_title' => sprintf(
                __('Booking for %1$s with %2$s on %3$s at %4$s', 'spark-of-divine-scheduler'),
                $product_title,
                $staff_title,
                date('M j, Y', strtotime($date)),
                date('g:i A', strtotime($time))
            ),
            'post_status' => 'publish',
            'post_author' => $booking_data['customer_id'],
            'meta_input' => array(
                'sod_product_id' => $booking_data['product_id'],
                'sod_service_id' => $booking_data['service_id'], // Keep for backward compatibility
                'sod_staff_id' => $booking_data['staff_id'],
                'sod_customer_id' => $booking_data['customer_id'],
                'sod_start_time' => "$date $time",
                'sod_end_time' => "$date {$booking_data['end_time']}",
                'sod_duration' => $booking_data['duration'] ?: 60,
                'sod_status' => $booking_data['status'] ?: 'pending',
                'sod_order_id' => $booking_data['order_id'] ?: 0,
                'sod_payment_method' => $booking_data['payment_method'] ?: '',
                'sod_remaining_balance' => $booking_data['remaining_balance'] ?: 0,
                'sod_balance_amount' => $booking_data['balance_amount'] ?: 0,
                'sod_original_booking_id' => $booking_data['original_booking_id'] ?: 0
            )
        );
        
        // Use the same ID if possible
        if ($booking_id > 0) {
            $post_data['import_id'] = $booking_id;
        }
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            $this->log("Error creating booking post: " . $post_id->get_error_message(), 'error');
        } else {
            $this->log("Created booking post $post_id from database record $booking_id");
            
            // Update database with post ID if needed
            if ($post_id != $booking_id && $booking_id > 0) {
                $this->wpdb->update(
                    $this->booking_table,
                    array('booking_id' => $post_id),
                    array('booking_id' => $booking_id),
                    array('%d'),
                    array('%d')
                );
            }
        }
        
        return $post_id;
    }

    /**
     * Update a booking post from database record.
     */
    public function update_booking_post_from_database($booking_id, $booking_data) {
        // Check if post exists
        $post = get_post($booking_id);
        if (!$post || $post->post_type !== 'sod_booking') {
            $this->log("Booking post not found for ID $booking_id, creating instead");
            return $this->create_booking_post_from_database($booking_id, $booking_data);
        }
        
        // Get product and staff names for title
        $product_title = "Unknown Product";
        if (!empty($booking_data['product_id'])) {
            $product = wc_get_product($booking_data['product_id']);
            if ($product) {
                $product_title = $product->get_title();
            }
        } elseif (!empty($booking_data['service_id'])) {
            $service_title = get_the_title($booking_data['service_id']);
            if ($service_title) {
                $product_title = $service_title;
            }
        }
        
        $staff_title = get_the_title($booking_data['staff_id']) ?: "Unknown Staff";
        $date = $booking_data['date'];
        $time = $booking_data['start_time'];
        
        // Update post
        $post_data = array(
            'ID' => $booking_id,
            'post_title' => sprintf(
                __('Booking for %1$s with %2$s on %3$s at %4$s', 'spark-of-divine-scheduler'),
                $product_title,
                $staff_title,
                date('M j, Y', strtotime($date)),
                date('g:i A', strtotime($time))
            ),
            'post_author' => $booking_data['customer_id'],
        );
        
        wp_update_post($post_data);
        
        // Update meta
        update_post_meta($booking_id, 'sod_product_id', $booking_data['product_id']);
        update_post_meta($booking_id, 'sod_service_id', $booking_data['service_id']); // Keep for backward compatibility
        update_post_meta($booking_id, 'sod_staff_id', $booking_data['staff_id']);
        update_post_meta($booking_id, 'sod_customer_id', $booking_data['customer_id']);
        update_post_meta($booking_id, 'sod_start_time', "$date $time");
        update_post_meta($booking_id, 'sod_end_time', "$date {$booking_data['end_time']}");
        update_post_meta($booking_id, 'sod_duration', $booking_data['duration'] ?: 60);
        update_post_meta($booking_id, 'sod_status', $booking_data['status'] ?: 'pending');
        update_post_meta($booking_id, 'sod_order_id', $booking_data['order_id'] ?: 0);
        update_post_meta($booking_id, 'sod_payment_method', $booking_data['payment_method'] ?: '');
        update_post_meta($booking_id, 'sod_remaining_balance', $booking_data['remaining_balance'] ?: 0);
        update_post_meta($booking_id, 'sod_balance_amount', $booking_data['balance_amount'] ?: 0);
        update_post_meta($booking_id, 'sod_original_booking_id', $booking_data['original_booking_id'] ?: 0);
        
        $this->log("Updated booking post $booking_id from database record");
    }

    /**
     * Delete a booking post when database record is deleted.
     */
    public function delete_booking_post_from_database($booking_id) {
        // Check if post exists
        $post = get_post($booking_id);
        if (!$post || $post->post_type !== 'sod_booking') {
            $this->log("Booking post not found for ID $booking_id, nothing to delete");
            return;
        }
        
        // Delete post
        wp_delete_post($booking_id, true);
        $this->log("Deleted booking post $booking_id after database record was deleted");
    }
}

// Initialize the sync handler
SOD_Booking_Sync::getInstance();