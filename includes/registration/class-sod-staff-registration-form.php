<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class SOD_Staff_Registration_Form {

    public function __construct() {
         // Sync categories only once or when triggered by the admin
        if (get_option('sod_sync_categories_flag') !== 'completed') {
            $this->sync_categories_from_taxonomy_to_table();
            update_option('sod_sync_categories_flag', 'completed');
            error_log('Categories synced from taxonomy to custom table on initialization.');
        }
        add_shortcode('sod_staff_registration_form', array($this, 'render_registration_form'));
        add_action('wp_ajax_save_staff_registration', array($this, 'handle_registration'));
        add_action('wp_ajax_nopriv_save_staff_registration', array($this, 'handle_registration'));
        add_action('save_post_sod_staff', array($this, 'sync_staff_with_custom_table'), 10, 3);
        add_action('admin_menu', array($this, 'add_admin_tools'));
    }
    
    // Render the registration form
    public function render_registration_form() {
        if (is_user_logged_in()) {
            return __('You are already registered.', 'spark-of-divine-scheduler');
        }

        ob_start(); // Start output buffering
        ?>
        <form id="sod-staff-registration-form" class="sod-form">
            <h3><?php _e('Staff Registration', 'spark-of-divine-scheduler'); ?></h3>

            <div class="sod-form-group">
                <label for="staff_first_name"><?php _e('First Name', 'spark-of-divine-scheduler'); ?></label>
                <input type="text" id="staff_first_name" name="staff_first_name" class="sod-form-input" required />
            </div>

            <div class="sod-form-group">
                <label for="staff_last_name"><?php _e('Last Name', 'spark-of-divine-scheduler'); ?></label>
                <input type="text" id="staff_last_name" name="staff_last_name" class="sod-form-input" required />
            </div>

            <div class="sod-form-group">
                <label for="staff_username"><?php _e('Username', 'spark-of-divine-scheduler'); ?></label>
                <input type="text" id="staff_username" name="staff_username" class="sod-form-input" required />
            </div>

            <div class="sod-form-group">
                <label for="staff_email"><?php _e('Email', 'spark-of-divine-scheduler'); ?></label>
                <input type="email" id="staff_email" name="staff_email" class="sod-form-input" required />
            </div>

            <div class="sod-form-group">
                <label for="staff_phone"><?php _e('Phone', 'spark-of-divine-scheduler'); ?></label>
                <input type="text" id="staff_phone" name="staff_phone" class="sod-form-input" required />
            </div>

            <div class="sod-form-group">
                <label for="staff_password"><?php _e('Password', 'spark-of-divine-scheduler'); ?></label>
                <input type="password" id="staff_password" name="staff_password" class="sod-form-input" required />
            </div>

            <h4><?php _e('Services', 'spark-of-divine-scheduler'); ?></h4>
            <div id="service-entries" class="sod-form-group">
                <!-- Services will be added dynamically here -->
            </div>

            <button type="button" id="add-service-entry" class="button button-secondary"><?php _e('Add Service', 'spark-of-divine-scheduler'); ?></button>

            <button type="submit" class="button button-primary"><?php _e('Register Staff Member', 'spark-of-divine-scheduler'); ?></button>

            <?php wp_nonce_field('sod_registration_nonce', 'sod_registration_nonce'); ?>
        </form>
        <?php
        return ob_get_clean();
    }
    
public function handle_registration() {
    global $wpdb;
    error_log('Staff Registration: Starting process');

    // Verify nonce
    if (!$this->verify_nonce()) {
        error_log('Staff Registration: Nonce verification failed');
        wp_send_json_error('Invalid security token sent.');
        return;
    }

    // Sanitize and validate input data
    $user_data = $this->sanitize_user_input();
    if (!$user_data) {
        wp_send_json_error(__('Please complete all required fields.', 'spark-of-divine-scheduler'));
        return;
    }

    // Check if user already exists
    if (email_exists($user_data['email']) || username_exists($user_data['username'])) {
        wp_send_json_error(__('A user with this email or username already exists. Please use different credentials.', 'spark-of-divine-scheduler'));
        return;
    }

    // Clear any existing auth tokens for this email
    $this->clear_existing_auth_tokens($user_data['email']);

    // Start a database transaction
    $wpdb->query('START TRANSACTION');

    try {
        // Create WordPress user
        $user_id = $this->create_wp_user($user_data);
        if (!$user_id) {
            throw new Exception(__('User creation failed', 'spark-of-divine-scheduler'));
        }

        // Handle service registration and linkage to the user
        $services = $this->register_services_for_user($user_id);
        if (!$services) {
            throw new Exception(__('Service registration failed', 'spark-of-divine-scheduler'));
        }

        // Create the staff custom post type entry
        $staff_post_id = $this->create_staff_post($user_id, $user_data['phone'], $user_data['email'], $services);
        if (!$staff_post_id) {
            throw new Exception(__('Staff post creation failed', 'spark-of-divine-scheduler'));
        }

        // Generate auth token and commit the transaction
        $redirect_url = $this->generate_auth_token_and_redirect($user_id);

        // Commit transaction and send success response
        $wpdb->query('COMMIT');
        wp_send_json_success(array(
            'message' => __('Staff member registered successfully. You will be redirected to set your availability.', 'spark-of-divine-scheduler'),
            'redirect_url' => $redirect_url
        ));
    } catch (Exception $e) {
        // Rollback transaction if an error occurs
        $wpdb->query('ROLLBACK');
        $this->cleanup_failed_registration($user_id);
        error_log('Staff Registration Error: ' . $e->getMessage());
        wp_send_json_error('An error occurred: ' . $e->getMessage());
    }
}
public function sync_staff_with_custom_table($post_id, $post, $update) {
    error_log("Sync staff method called for post ID: " . $post_id);
    
    if ($post->post_type !== 'sod_staff') {
        error_log("Not a sod_staff post type. Exiting sync method.");
        return;
    }

    $user_id = get_post_meta($post_id, 'sod_staff_user_id', true);
    $phone = get_post_meta($post_id, 'sod_staff_phone', true);
    $accepts_cash = get_post_meta($post_id, 'sod_staff_accepts_cash', true) ? 1 : 0;
    $services = maybe_serialize(get_post_meta($post_id, 'sod_staff_services', true));

    if (empty($user_id)) {
        error_log('Sync Error: Missing user ID for post ID: ' . $post_id);
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'sod_staff';

    $existing_staff = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d AND staff_id = %d",
        $user_id, $post_id
    ));

    $staff_data = array(
        'user_id' => $user_id,
        'phone_number' => $phone,
        'accepts_cash' => $accepts_cash,
        'services' => $services,
    );

    if ($existing_staff) {
        // Update existing record
        $result = $wpdb->update(
            $table_name,
            $staff_data,
            array('user_id' => $user_id, 'staff_id' => $post_id),
            array('%d', '%s', '%d', '%s'),
            array('%d', '%d')
        );
    } else {
        // Insert new record
        $staff_data['staff_id'] = $post_id;
        $result = $wpdb->insert(
            $table_name,
            $staff_data,
            array('%d', '%d', '%s', '%d', '%s')
        );
    }

    if ($result === false) {
        error_log('Failed to sync staff member in custom table. SQL Error: ' . $wpdb->last_error);
    } else {
        error_log('Successfully synced staff member in custom table for post ID: ' . $post_id);
    }
}
    
// Helper method to get category ID by name and ensure it exists in the table
private function get_category_id_by_name($category_name) {
    global $wpdb;

    // Sync categories from the taxonomy to the custom table
    $this->sync_categories_from_taxonomy_to_table();

    // Search for the category ID in the custom table
    $category_id = $wpdb->get_var($wpdb->prepare(
        "SELECT category_id FROM {$wpdb->prefix}sod_service_categories WHERE name = %s",
        $category_name
    ));

    // If no category found, return false or handle the error as needed
    if (!$category_id) {
        error_log('Category not found in custom table: ' . $category_name);
        return false;
    }

    return $category_id;
 }

    private function sync_categories_from_taxonomy_to_table() {
    $terms = get_terms(array(
        'taxonomy' => 'service_category',
        'hide_empty' => false,
    ));

    if (!is_wp_error($terms)) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sod_service_categories';

        foreach ($terms as $term) {
            $existing_category = $wpdb->get_var($wpdb->prepare(
                "SELECT category_id FROM $table_name WHERE name = %s",
                $term->name
            ));

            if (!$existing_category) {
                $wpdb->insert(
                    $table_name,
                    array('name' => $term->name),
                    array('%s')
                );
                error_log("Inserted new category: " . $term->name);
            } else {
                error_log("Category already exists: " . $term->name);
            }
        }
    }
}
    
// Verify the nonce
private function verify_nonce() {
    return check_ajax_referer('sod_registration_nonce', 'nonce', false);
}

// Sanitize and validate user input
private function sanitize_user_input() {
    $required_fields = array('staff_first_name', 'staff_last_name', 'staff_email', 'staff_phone', 'staff_username');
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            return false;
        }
    }

    return array(
        'first_name' => sanitize_text_field($_POST['staff_first_name']),
        'last_name' => sanitize_text_field($_POST['staff_last_name']),
        'email' => sanitize_email($_POST['staff_email']),
        'phone' => sanitize_text_field($_POST['staff_phone']),
        'username' => sanitize_user($_POST['staff_username']),
        'password' => isset($_POST['staff_password']) ? $_POST['staff_password'] : wp_generate_password()
    );
}

// Create a new WordPress user
private function create_wp_user($user_data) {
    $userdata = array(
        'user_login' => $user_data['username'],
        'user_pass' => $user_data['password'],
        'user_email' => $user_data['email'],
        'first_name' => $user_data['first_name'],
        'last_name' => $user_data['last_name'],
        'role' => 'staff'
    );

    $user_id = wp_insert_user($userdata);

    if (is_wp_error($user_id)) {
        throw new Exception($user_id->get_error_message());
    }

    update_user_meta($user_id, 'staff_phone', $user_data['phone']);

    return $user_id;
}

// Generate a unique username from the email
private function generate_unique_username($email) {
    $username = sanitize_user(current(explode('@', $email)), true);
    $counter = 1;
    $original_username = $username;

    while (username_exists($username)) {
        $username = $original_username . $counter;
        $counter++;
    }

    return $username;
}

// Register services for the user and return linked service IDs
private function register_services_for_user($user_id) {
    if (!isset($_POST['service_name']) || !is_array($_POST['service_name'])) {
        return array();
    }

    $services = array();

    foreach ($_POST['service_name'] as $index => $name) {
        $category_name = $_POST['service_category'][$index];
        $category_id = $this->get_category_id_by_name($category_name);

        if (!$category_id) {
            error_log("Invalid service category provided: " . $category_name);
            throw new Exception(__('Invalid service category provided: ' . $category_name, 'spark-of-divine-scheduler'));
        }

        error_log("Attempting to create or update service: " . $name . " under category ID: " . $category_id);

        $service_id = $this->create_or_update_service($name, $category_id);

        if (!$service_id) {
            error_log("Failed to insert or update service: " . $name);
            throw new Exception(__('Failed to insert or update service: ' . $name, 'spark-of-divine-scheduler'));
        }

        $services[] = $service_id; // Store the new or updated service ID
        error_log('Service registered with ID: ' . $service_id);
    }

    update_user_meta($user_id, 'staff_services', $services);
    return $services;
}

// Create a new staff post linked to the user
private function create_staff_post($user_id, $phone, $email, $services) {
    global $wpdb;

    // Check if a staff record already exists
    $existing_staff = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sod_staff WHERE user_id = %d",
        $user_id
    ));

    if ($existing_staff) {
        // Update existing record
        $wpdb->update(
            "{$wpdb->prefix}sod_staff",
            array(
                'phone_number' => $phone,
                'services' => maybe_serialize($services)
            ),
            array('user_id' => $user_id),
            array('%s', '%s'),
            array('%d')
        );
        $staff_id = $existing_staff->staff_id;
    } else {
        // Insert new record
        $wpdb->insert(
            "{$wpdb->prefix}sod_staff",
            array(
                'user_id' => $user_id,
                'phone_number' => $phone,
                'accepts_cash' => 0,
                'services' => maybe_serialize($services)
            ),
            array('%d', '%s', '%d', '%s')
        );
        $staff_id = $wpdb->insert_id;
    }

    $post_id = wp_insert_post(array(
        'post_title' => get_user_by('ID', $user_id)->display_name,
        'post_type' => 'sod_staff',
        'post_status' => 'publish',
        'meta_input' => array(
            'sod_staff_user_id' => $user_id,
            'sod_staff_phone' => $phone,
            'sod_staff_email' => $email,
        )
    ));

    if (is_wp_error($post_id)) {
        error_log('Failed to create staff post: ' . $post_id->get_error_message());
        return false;
    }

    return $staff_id;
}

    private function clear_old_staff_meta($user_id) {
    global $wpdb;

    $post_meta_table = $wpdb->prefix . 'postmeta';
    $wpdb->delete($post_meta_table, array('meta_key' => 'sod_staff_user_id', 'meta_value' => $user_id), array('%s', '%d'));

    error_log("Cleared old staff meta data for user ID: $user_id");
}
    
// Generate an auth token and create the redirect URL
private function generate_auth_token_and_redirect($user_id) {
    $auth_token = wp_generate_password(32, false);
    update_user_meta($user_id, 'staff_auth_token', $auth_token);

    return add_query_arg(
        array(
            'staff_id' => $user_id,
            'staff_token' => $auth_token
        ),
        home_url('/staff-availability/')
    );
}
    // Add the missing create_or_update_service method to this class
    private function create_or_update_service($name, $category_id) {
        global $wpdb;

        // Check if the service already exists based on name and category
        $existing_service_id = $wpdb->get_var($wpdb->prepare(
            "SELECT service_id FROM {$wpdb->prefix}sod_services WHERE name = %s AND category_id = %d",
            $name, $category_id
        ));

        // If the service exists, update it; otherwise, create a new one
        if ($existing_service_id) {
            $result = $wpdb->update(
                "{$wpdb->prefix}sod_services",
                array(
                    'name' => $name,
                    'category_id' => $category_id,
                    'description' => '', // Set a default value or use dynamic content if available
                    'price' => '0.00' // Set a default value or use dynamic content if available
                ),
                array('service_id' => $existing_service_id),
                array('%s', '%d', '%s', '%f'), // Data types for update
                array('%d') // WHERE clause data type
            );

            if ($result === false) {
                error_log('Failed to update service: ' . $wpdb->last_error);
                return false;
            }
            return $existing_service_id; // Return the existing service ID
        } else {
            $result = $wpdb->insert(
                "{$wpdb->prefix}sod_services",
                array(
                    'name' => $name,
                    'category_id' => $category_id,
                    'description' => '', // Set a default value or use dynamic content if available
                    'price' => '0.00' // Set a default value or use dynamic content if available
                ),
                array('%s', '%d', '%s', '%f') // Data types for insert
            );

            if ($result === false) {
                error_log('Failed to insert service: ' . $wpdb->last_error);
                return false;
            }

            return $wpdb->insert_id; // Return the newly inserted service ID
        }
    }
    private function get_or_create_staff_post_for_user($user_id) {
    global $wpdb;

    // Check if a staff record exists in the custom table
    $staff_id = $wpdb->get_var($wpdb->prepare(
        "SELECT staff_id FROM {$wpdb->prefix}sod_staff WHERE user_id = %d",
        $user_id
    ));

    if ($staff_id) {
        // Check if the corresponding post exists
        $post = get_post($staff_id);
        if (!$post || $post->post_type !== 'sod_staff') {
            // Create the post if it doesn't exist
            $post_id = wp_insert_post(array(
                'ID' => $staff_id,
                'post_title' => get_user_by('ID', $user_id)->display_name,
                'post_type' => 'sod_staff',
                'post_status' => 'publish',
                'meta_input' => array(
                    'sod_staff_user_id' => $user_id,
                )
            ));
            return $post_id;
        }
        return $staff_id;
    }

    // If no staff record exists, create both the custom table entry and the post
    return $this->create_staff_post_for_user($user_id);
}
private function clear_existing_auth_tokens($email) {
    global $wpdb;
    $user = get_user_by('email', $email);
    if ($user) {
        delete_user_meta($user->ID, 'staff_auth_token');
    }
}
    private function cleanup_failed_registration($user_id) {
    if ($user_id) {
        wp_delete_user($user_id);
        delete_user_meta($user_id, 'staff_auth_token');
        
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'sod_staff', array('user_id' => $user_id), array('%d'));
        
        $staff_post = get_posts(array(
            'post_type' => 'sod_staff',
            'meta_key' => 'sod_staff_user_id',
            'meta_value' => $user_id,
            'posts_per_page' => 1
        ));
        
        if (!empty($staff_post)) {
            wp_delete_post($staff_post[0]->ID, true);
        }
    }
}
    
//Add staff posts to the database
public function add_admin_tools() {
     error_log('add_admin_tools called');
    add_management_page(
        'Staff Sync Tools',
        'Staff Sync',
        'manage_options',
        'sod-staff-sync',
        array($this, 'render_sync_page')
    );
}

public function render_sync_page() {
    if (isset($_POST['sync_staff']) && check_admin_referer('sod_sync_staff')) {
        $results = $this->sync_existing_staff_posts();
        ?>
        <div class="notice notice-success">
            <p>
                Sync completed:<br>
                Successfully synced: <?php echo $results['success']; ?><br>
                Failed: <?php echo $results['failed']; ?><br>
                <?php if (!empty($results['errors'])): ?>
                    Errors:<br>
                    <?php foreach ($results['errors'] as $error): ?>
                        - <?php echo esc_html($error); ?><br>
                    <?php endforeach; ?>
                <?php endif; ?>
            </p>
        </div>
        <?php
    }
    ?>
    <div class="wrap">
        <h1>Staff Synchronization Tools</h1>
        <form method="post">
            <?php wp_nonce_field('sod_sync_staff'); ?>
            <p>This tool will synchronize existing staff posts with the custom database table.</p>
            <button type="submit" name="sync_staff" class="button button-primary">
                Sync Staff Posts
            </button>
        </form>
    </div>
    <?php
}
    
public function sync_existing_staff_posts() {
    global $wpdb;
    
    // Get all staff posts regardless of meta status
    $staff_posts = get_posts(array(
        'post_type' => 'sod_staff',
        'posts_per_page' => -1,
        'post_status' => 'any'
    ));

    $results = array(
        'success' => 0,
        'failed' => 0,
        'errors' => array(),
        'skipped' => 0
    );

    foreach ($staff_posts as $staff_post) {
        error_log("Processing staff post ID: " . $staff_post->ID);

        // Check for user_id first
        $user_id = get_post_meta($staff_post->ID, 'sod_staff_user_id', true);
        
        // If no user_id, try to find or create one from the post title
        if (!$user_id) {
            $user = get_user_by('login', sanitize_title($staff_post->post_title));
            if (!$user) {
                // Create a new user if none exists
                $random_email = 'staff_' . $staff_post->ID . '@example.com'; // Temporary email
                $user_id = wp_insert_user(array(
                    'user_login' => sanitize_title($staff_post->post_title),
                    'user_pass' => wp_generate_password(),
                    'user_email' => $random_email,
                    'display_name' => $staff_post->post_title,
                    'role' => 'staff'
                ));
                if (is_wp_error($user_id)) {
                    $results['failed']++;
                    $results['errors'][] = "Failed to create user for staff ID {$staff_post->ID}: " . $user_id->get_error_message();
                    continue;
                }
            } else {
                $user_id = $user->ID;
            }
            // Save the user ID to the post meta
            update_post_meta($staff_post->ID, 'sod_staff_user_id', $user_id);
        }

        // Get other meta data
        $phone = get_post_meta($staff_post->ID, 'sod_staff_phone', true);
        $email = get_post_meta($staff_post->ID, 'sod_staff_email', true);
        $accepts_cash = get_post_meta($staff_post->ID, 'sod_staff_accepts_cash', true);
        $services = get_post_meta($staff_post->ID, 'sod_staff_services', true);

        // Check if staff exists in custom table
        $existing_staff = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sod_staff WHERE staff_id = %d OR user_id = %d",
            $staff_post->ID, $user_id
        ));

        try {
            if (!$existing_staff) {
                // Insert new record
                $result = $wpdb->insert(
                    $wpdb->prefix . 'sod_staff',
                    array(
                        'staff_id' => $staff_post->ID,
                        'user_id' => $user_id,
                        'phone_number' => $phone ?: '',
                        'accepts_cash' => $accepts_cash ? 1 : 0,
                        'services' => is_array($services) ? maybe_serialize($services) : ''
                    ),
                    array('%d', '%d', '%s', '%d', '%s')
                );

                if ($result === false) {
                    throw new Exception($wpdb->last_error);
                }
                $results['success']++;
                error_log("Successfully inserted staff record for ID: " . $staff_post->ID);
            } else {
                // Update existing record
                $result = $wpdb->update(
                    $wpdb->prefix . 'sod_staff',
                    array(
                        'phone_number' => $phone ?: '',
                        'accepts_cash' => $accepts_cash ? 1 : 0,
                        'services' => is_array($services) ? maybe_serialize($services) : ''
                    ),
                    array('staff_id' => $staff_post->ID),
                    array('%s', '%d', '%s'),
                    array('%d')
                );

                if ($result === false) {
                    throw new Exception($wpdb->last_error);
                }
                $results['skipped']++;
                error_log("Updated existing staff record for ID: " . $staff_post->ID);
            }
        } catch (Exception $e) {
            $results['failed']++;
            $results['errors'][] = "Error processing staff ID {$staff_post->ID}: " . $e->getMessage();
            error_log("Error processing staff ID {$staff_post->ID}: " . $e->getMessage());
        }
    }

    error_log("Sync completed - Success: {$results['success']}, Failed: {$results['failed']}, Skipped: {$results['skipped']}");
    return $results;
}
    
}
// Initialize the class
new SOD_Staff_Registration_Form();