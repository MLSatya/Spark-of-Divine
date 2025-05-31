<?php
if ( ! defined('ABSPATH') ) {
    exit;
}

class SOD_Staff_Meta_Boxes {

    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_staff_meta_boxes' ) );
        add_action( 'save_post_sod_staff', array( $this, 'save_staff_meta_boxes' ) );
    }

    /**
     * Add meta boxes to the sod_staff post type.
     */
    public function add_staff_meta_boxes() {
        add_meta_box(
            'sod_staff_details',
            __( 'Staff Details', 'spark-of-divine-scheduler' ),
            array( $this, 'render_staff_details' ),
            'sod_staff',
            'normal',
            'default'
        );
        
        add_meta_box(
            'sod_staff_products',
            __( 'Staff Services & Products', 'spark-of-divine-scheduler' ),
            array( $this, 'render_staff_products' ),
            'sod_staff',
            'normal',
            'default'
        );
    }

    /**
     * Render the staff details meta box.
     *
     * @param WP_Post $post The current post object.
     */
    public function render_staff_details( $post ) {
        // Add a nonce field for security.
        wp_nonce_field( 'sod_staff_meta_box', 'sod_staff_meta_box_nonce' );

        // Retrieve existing meta values.
        $phone            = get_post_meta( $post->ID, 'sod_staff_phone', true );
        $email            = get_post_meta( $post->ID, 'sod_staff_email', true );
        $accepts_cash     = get_post_meta( $post->ID, 'sod_staff_accepts_cash', true );
        $appointment_only = get_post_meta( $post->ID, 'sod_staff_appointment_only', true );
        
        // Get linked user if any
        $user_id = get_post_meta( $post->ID, 'sod_staff_user_id', true );
        $user = $user_id ? get_user_by('ID', $user_id) : false;
        ?>
        <style>
        .staff-meta-field {
            margin-bottom: 15px;
        }
        .staff-meta-field label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .staff-meta-field input[type="text"],
        .staff-meta-field input[type="email"],
        .staff-meta-field select {
            width: 100%;
            max-width: 400px;
        }
        .staff-meta-field .checkbox-label {
            font-weight: normal;
        }
        </style>
        
        <div class="staff-meta-field">
            <label for="sod_staff_user_id"><?php _e( 'WordPress User:', 'spark-of-divine-scheduler' ); ?></label>
            <select name="sod_staff_user_id" id="sod_staff_user_id">
                <option value=""><?php _e('-- Select User --', 'spark-of-divine-scheduler'); ?></option>
                <?php 
                $users = get_users(['role__in' => ['administrator', 'staff', 'editor']]);
                foreach ($users as $u) {
                    echo '<option value="' . esc_attr($u->ID) . '" ' . selected($user_id, $u->ID, false) . '>' . 
                        esc_html($u->display_name) . ' (' . esc_html($u->user_email) . ')</option>';
                }
                ?>
            </select>
            <p class="description"><?php _e('Link this staff member to a WordPress user account', 'spark-of-divine-scheduler'); ?></p>
        </div>
        
        <div class="staff-meta-field">
            <label for="sod_staff_phone"><?php _e( 'Phone:', 'spark-of-divine-scheduler' ); ?></label>
            <input type="text" name="sod_staff_phone" id="sod_staff_phone" value="<?php echo esc_attr( $phone ); ?>" />
        </div>
        
        <div class="staff-meta-field">
            <label for="sod_staff_email"><?php _e( 'Email:', 'spark-of-divine-scheduler' ); ?></label>
            <input type="email" name="sod_staff_email" id="sod_staff_email" value="<?php echo esc_attr( $email ); ?>" />
        </div>
        
        <div class="staff-meta-field">
            <label class="checkbox-label">
                <input type="checkbox" name="sod_staff_accepts_cash" id="sod_staff_accepts_cash" value="1" <?php checked( $accepts_cash, '1' ); ?> />
                <?php _e( 'Accepts Cash', 'spark-of-divine-scheduler' ); ?>
            </label>
        </div>
        
        <div class="staff-meta-field">
            <label class="checkbox-label">
                <input type="checkbox" name="sod_staff_appointment_only" id="sod_staff_appointment_only" value="1" <?php checked( $appointment_only, '1' ); ?> />
                <?php _e( 'By Appointment Only', 'spark-of-divine-scheduler' ); ?>
            </label>
        </div>
        <?php
    }
    
    /**
     * Render the staff products meta box.
     *
     * @param WP_Post $post The current post object.
     */
    public function render_staff_products( $post ) {
        // Retrieve existing product associations
        $linked_products = get_post_meta( $post->ID, 'sod_staff_products', true );
        if ( ! is_array( $linked_products ) ) {
            $linked_products = array();
        }
        
        // Retrieve legacy service associations for reference
        $linked_services = get_post_meta( $post->ID, 'sod_staff_services', true );
        if ( ! is_array( $linked_services ) ) {
            $linked_services = array();
        }
        
        // Find products linked to these services
        $service_linked_products = array();
        foreach ($linked_services as $service_id) {
            $product_id = get_post_meta($service_id, '_sod_product_id', true);
            if ($product_id) {
                $service_linked_products[$product_id] = get_the_title($product_id);
            }
        }
        
        // Get all WooCommerce products
        $products = get_posts( array(
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC'
        ));
        
        // Get product categories for grouping
        $product_categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
        ));
        
        // Build a category map for easier lookups
        $category_map = array();
        foreach ($product_categories as $cat) {
            $category_map[$cat->term_id] = $cat->name;
        }
        
        // Group products by category
        $products_by_category = array();
        foreach ($products as $product) {
            $terms = get_the_terms($product->ID, 'product_cat');
            
            if (!empty($terms)) {
                foreach ($terms as $term) {
                    if (!isset($products_by_category[$term->term_id])) {
                        $products_by_category[$term->term_id] = array(
                            'name' => $term->name,
                            'products' => array()
                        );
                    }
                    $products_by_category[$term->term_id]['products'][] = $product;
                }
            } else {
                // Uncategorized products
                if (!isset($products_by_category[0])) {
                    $products_by_category[0] = array(
                        'name' => 'Uncategorized',
                        'products' => array()
                    );
                }
                $products_by_category[0]['products'][] = $product;
            }
        }
        
        // Sort categories alphabetically
        uasort($products_by_category, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
        ?>
        <div class="staff-products-container">
            <style>
            .staff-products-container {
                margin-bottom: 15px;
            }
            .staff-products-section {
                margin-bottom: 20px;
            }
            .staff-products-section h4 {
                margin-top: 0;
                margin-bottom: 10px;
                padding-bottom: 5px;
                border-bottom: 1px solid #eee;
            }
            .product-select-container {
                max-height: 300px;
                overflow-y: auto;
                border: 1px solid #ddd;
                padding: 10px;
                background: #f9f9f9;
            }
            .product-select-item {
                margin-bottom: 5px;
            }
            </style>
            
            <p class="description"><?php _e('Select which products/services this staff member offers', 'spark-of-divine-scheduler'); ?></p>
            
            <div class="product-select-container">
                <?php foreach ($products_by_category as $category_id => $category): ?>
                    <div class="staff-products-section">
                        <h4><?php echo esc_html($category['name']); ?></h4>
                        
                        <?php foreach ($category['products'] as $product): 
                            $checked = in_array($product->ID, $linked_products) ? 'checked' : '';
                            $highlighted = in_array($product->ID, array_keys($service_linked_products)) ? 'style="background-color: #f0f9ff;"' : '';
                        ?>
                        <div class="product-select-item" <?php echo $highlighted; ?>>
                            <label>
                                <input type="checkbox" name="sod_staff_products[]" value="<?php echo esc_attr($product->ID); ?>" <?php echo $checked; ?>>
                                <?php echo esc_html($product->post_title); ?>
                                <?php if (in_array($product->ID, array_keys($service_linked_products))): ?>
                                <span style="color: #0073aa;">(Linked from service)</span>
                                <?php endif; ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($products_by_category)): ?>
                <p><?php _e('No products found. Please create some WooCommerce products first.', 'spark-of-divine-scheduler'); ?></p>
                <?php endif; ?>
            </div>
            
            <p><a href="<?php echo admin_url('post-new.php?post_type=product'); ?>" target="_blank"><?php _e('+ Add New Product', 'spark-of-divine-scheduler'); ?></a></p>
        </div>
        <?php
    }
    /**
     * Save the meta box data when the staff post is saved.
     *
     * @param int $post_id The ID of the current post.
     */
    public function save_staff_meta_boxes( $post_id ) {
        error_log("=== STAFF META SAVE: Starting for post ID $post_id ===");

        // Security checks
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            error_log("STAFF META SAVE: Skipping autosave");
            return;
        }
        if ( wp_is_post_revision( $post_id ) ) {
            error_log("STAFF META SAVE: Skipping revision");
            return;
        }
        if ( ! isset( $_POST['sod_staff_meta_box_nonce'] ) || 
             ! wp_verify_nonce( $_POST['sod_staff_meta_box_nonce'], 'sod_staff_meta_box' ) ) {
            error_log("STAFF META SAVE: Nonce verification failed");
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            error_log("STAFF META SAVE: User lacks permission");
            return;
        }
        if ( get_post_type( $post_id ) !== 'sod_staff' ) {
            error_log("STAFF META SAVE: Not a staff post type");
            return;
        }

        // Get essential data only
        $phone = isset( $_POST['sod_staff_phone'] ) ? sanitize_text_field( $_POST['sod_staff_phone'] ) : '';
        $email = isset( $_POST['sod_staff_email'] ) ? sanitize_email( $_POST['sod_staff_email'] ) : '';
        $accepts_cash = isset( $_POST['sod_staff_accepts_cash'] ) && $_POST['sod_staff_accepts_cash'] == '1' ? 1 : 0;
        $appointment_only = isset( $_POST['sod_staff_appointment_only'] ) && $_POST['sod_staff_appointment_only'] == '1' ? 1 : 0;
        $selected_user_id = isset( $_POST['sod_staff_user_id'] ) ? intval( $_POST['sod_staff_user_id'] ) : 0;
        $products = isset( $_POST['sod_staff_products'] ) ? array_map( 'intval', (array) $_POST['sod_staff_products'] ) : array();

        // Validate email if provided
        if ( ! empty( $email ) && ! is_email( $email ) ) {
            error_log("STAFF META SAVE: Invalid email format: $email");
            $email = '';
        }

        $user_id = $selected_user_id;

        // Create WordPress user if no user selected and we have email
        if ( ! $user_id && ! empty( $email ) ) {
            $user_id = $this->create_wordpress_user_for_staff( $post_id, $email, $phone );
            if ( ! $user_id ) {
                error_log("STAFF META SAVE: Failed to create user");
                return;
            }
        }

        // Validate user_id if provided
        if ( $user_id > 0 && ! get_user_by( 'id', $user_id ) ) {
            error_log("STAFF META SAVE: Invalid user ID: $user_id");
            $user_id = 0;
        }

        error_log("STAFF META SAVE: Processing with User ID: $user_id, Email: $email, Phone: $phone, Products: " . print_r($products, true));

        // Save to WordPress post meta (essential fields only)
        update_post_meta( $post_id, 'sod_staff_phone', $phone );
        update_post_meta( $post_id, 'sod_staff_email', $email );
        update_post_meta( $post_id, 'sod_staff_accepts_cash', $accepts_cash );
        update_post_meta( $post_id, 'sod_staff_appointment_only', $appointment_only );
        update_post_meta( $post_id, 'sod_staff_user_id', $user_id );
        update_post_meta( $post_id, 'sod_staff_products', $products );

        // Sync data to WordPress user
        if ( $user_id ) {
            $this->sync_staff_data_to_user( $user_id, $post_id, $phone, $email, $accepts_cash, $appointment_only );
        }

        // Update custom table
        global $wpdb;
        $staff_table = $wpdb->prefix . 'sod_staff';

        $staff_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $staff_table WHERE staff_id = %d",
            $post_id
        ));

        $staff_title = get_the_title($post_id);
        $products_json = !empty($products) ? json_encode($products) : null;

        $staff_data = array(
            'user_id' => $user_id ? $user_id : null,
            'phone_number' => $phone,
            'email' => $email,
            'accepts_cash' => $accepts_cash,
            'services' => $products_json, // Store products in services column
            'appointment_only' => $appointment_only,
            'name' => $staff_title,
            'bio' => '' // Empty for now, can be populated later if needed
        );

        if ($staff_exists) {
            $result = $wpdb->update(
                $staff_table,
                $staff_data,
                array('staff_id' => $post_id),
                array('%d', '%s', '%s', '%d', '%s', '%d', '%s', '%s'),
                array('%d')
            );
            error_log("STAFF META SAVE: Updated existing record");
        } else {
            $staff_data['staff_id'] = $post_id;
            $result = $wpdb->insert(
                $staff_table,
                $staff_data,
                array('%d', '%d', '%s', '%s', '%d', '%s', '%d', '%s', '%s')
            );
            error_log("STAFF META SAVE: Inserted new record");
        }

        // Handle staff-product relationships
        $this->sync_staff_product_relationships( $post_id, $products );

        error_log("=== STAFF META SAVE: Completed for post ID $post_id ===");
    }

    /**
     * Create WordPress user for staff member
     */
    private function create_wordpress_user_for_staff( $post_id, $email, $phone ) {
        // Check if user with this email already exists
        $existing_user = get_user_by( 'email', $email );
        if ( $existing_user ) {
            error_log("STAFF META SAVE: User with email $email already exists, using existing user ID: " . $existing_user->ID);
            return $existing_user->ID;
        }

        $staff_title = get_the_title( $post_id );
        $username = $this->generate_unique_username( $email, $staff_title );

        // Generate a random password
        $password = wp_generate_password( 12, false );

        $userdata = array(
            'user_login' => $username,
            'user_email' => $email,
            'user_pass'  => $password,
            'display_name' => $staff_title,
            'first_name' => $staff_title,
            'role' => 'staff_member' // Assuming you have this role, or use 'subscriber'
        );

        $user_id = wp_insert_user( $userdata );

        if ( is_wp_error( $user_id ) ) {
            error_log("STAFF META SAVE: Failed to create user: " . $user_id->get_error_message());
            return false;
        }

        // Send new user notification
        wp_new_user_notification( $user_id, null, 'user' );

        error_log("STAFF META SAVE: Created new user ID: $user_id for staff member: $staff_title");
        return $user_id;
    }

    /**
     * Generate unique username from firstname and last initial
     */
    private function generate_unique_username( $email, $staff_title ) {
        // Extract first name and last initial from staff title
        $name_parts = explode( ' ', trim( $staff_title ) );

        if ( count( $name_parts ) >= 2 ) {
            // Get first name and last initial
            $first_name = sanitize_user( strtolower( $name_parts[0] ) );
            $last_initial = sanitize_user( strtolower( substr( $name_parts[ count($name_parts) - 1 ], 0, 1 ) ) );
            $base_username = $first_name . $last_initial;
        } elseif ( count( $name_parts ) == 1 ) {
            // Only one name provided, use it as base
            $base_username = sanitize_user( strtolower( $name_parts[0] ) );
        } else {
            // Fall back to email prefix if name parsing fails
            $email_prefix = substr( $email, 0, strpos( $email, '@' ) );
            $base_username = sanitize_user( strtolower( $email_prefix ) );

            // If email prefix also fails, use generic
            if ( empty( $base_username ) ) {
                $base_username = 'staff_member';
            }
        }

        // Remove any invalid characters and ensure it's not empty
        $base_username = preg_replace( '/[^a-z0-9]/', '', $base_username );
        if ( empty( $base_username ) ) {
            $base_username = 'staff';
        }

        $username = $base_username;
        $counter = 1;

        // Make sure username is unique
        while ( username_exists( $username ) ) {
            $username = $base_username . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Sync staff data to WordPress user
     */
    private function sync_staff_data_to_user( $user_id, $post_id, $phone, $email, $accepts_cash, $appointment_only ) {
        // Update user meta with staff-specific data
        update_user_meta( $user_id, 'sod_staff_post_id', $post_id );
        update_user_meta( $user_id, 'staff_phone', $phone );
        update_user_meta( $user_id, 'staff_accepts_cash', $accepts_cash );
        update_user_meta( $user_id, 'staff_appointment_only', $appointment_only );

        // Update user email if different
        $user = get_user_by( 'id', $user_id );
        if ( $user && $user->user_email !== $email && ! empty( $email ) ) {
            wp_update_user( array(
                'ID' => $user_id,
                'user_email' => $email
            ) );
        }

        // Ensure user has staff role
        $user = new WP_User( $user_id );
        if ( ! $user->has_cap( 'staff_member' ) ) {
            $user->add_role( 'staff_member' );
        }

        error_log("STAFF META SAVE: Synced data to user $user_id");
    }

    /**
     * Sync staff-product relationships
     */
    private function sync_staff_product_relationships( $post_id, $products ) {
        global $wpdb;
        $staff_products_table = $wpdb->prefix . 'sod_staff_products';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$staff_products_table}'") === $staff_products_table) {
            // Delete existing links
            $wpdb->delete($staff_products_table, ['staff_id' => $post_id], ['%d']);

            // Insert new links
            $inserted_count = 0;
            foreach ($products as $product_id) {
                if ($product_id > 0) {
                    $result = $wpdb->insert($staff_products_table, [
                        'staff_id'    => $post_id,
                        'product_id'  => $product_id,
                        'created_at'  => current_time('mysql')
                    ], ['%d', '%d', '%s']);

                    if ($result !== false) {
                        $inserted_count++;
                    }
                }
            }
            error_log("STAFF META SAVE: Updated $inserted_count product associations");
        }
    }

    /**
     * Update the availability form sync method to match
     */
    public function sync_staff_with_custom_table($post_id, $post, $update) {
        error_log("Availability form sync called for post ID: $post_id");

        if ($post->post_type !== 'sod_staff') {
            return;
        }

        // Get meta data
        $user_id = get_post_meta($post_id, 'sod_staff_user_id', true);
        $phone = get_post_meta($post_id, 'sod_staff_phone', true);
        $email = get_post_meta($post_id, 'sod_staff_email', true);
        $accepts_cash = get_post_meta($post_id, 'sod_staff_accepts_cash', true) ? 1 : 0;
        $appointment_only = get_post_meta($post_id, 'sod_staff_appointment_only', true) ? 1 : 0;
        $products = get_post_meta($post_id, 'sod_staff_products', true);

        // Create user if needed
        if (!$user_id && !empty($email)) {
            $user_id = $this->create_wordpress_user_for_staff($post_id, $email, $phone);
            if ($user_id) {
                update_post_meta($post_id, 'sod_staff_user_id', $user_id);
            }
        }

        if (empty($user_id)) {
            error_log('Sync Error: Missing user ID for post ID: ' . $post_id);
            return;
        }

        // Sync to user
        $this->sync_staff_data_to_user($user_id, $post_id, $phone, $email, $accepts_cash, $appointment_only);

        // Update custom table
        global $wpdb;
        $staff_table = $wpdb->prefix . 'sod_staff';

        $staff_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $staff_table WHERE staff_id = %d",
            $post_id
        ));

        $staff_title = get_the_title($post_id);
        $products_json = !empty($products) && is_array($products) ? json_encode($products) : null;

        $staff_data = array(
            'user_id' => $user_id,
            'phone_number' => $phone,
            'email' => $email,
            'accepts_cash' => $accepts_cash,
            'services' => $products_json,
            'appointment_only' => $appointment_only,
            'name' => $staff_title,
            'bio' => ''
        );

        if ($staff_exists) {
            $wpdb->update(
                $staff_table,
                $staff_data,
                array('staff_id' => $post_id),
                array('%d', '%s', '%s', '%d', '%s', '%d', '%s', '%s'),
                array('%d')
            );
        } else {
            $staff_data['staff_id'] = $post_id;
            $wpdb->insert(
                $staff_table,
                $staff_data,
                array('%d', '%d', '%s', '%s', '%d', '%s', '%d', '%s', '%s')
            );
        }

        error_log("Successfully synced staff member for post ID: $post_id");
    }

}

new SOD_Staff_Meta_Boxes();