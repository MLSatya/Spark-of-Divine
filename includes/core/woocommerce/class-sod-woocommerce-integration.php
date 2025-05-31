<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class SOD_WooCommerce_Integration {
    private $db_access;

    public function __construct($db_access) {
        $this->db_access = $db_access;

        // Hook into WooCommerce's customer creation and update actions
        add_action('woocommerce_created_customer', array($this, 'sync_custom_table_with_woocommerce'), 10, 3);
        add_action('profile_update', array($this, 'sync_custom_table_with_woocommerce_update'), 10, 2);

        // Staff-specific product integration
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_staff_service_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_staff_service_fields'), 20);
        add_action('add_meta_boxes', array($this, 'add_related_products_metabox'));
        add_action('admin_footer', array($this, 'preselect_service_for_product'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Filters for booking process integration
        add_filter('fetch_product_data_improved', array($this, 'enhance_product_lookup'), 10, 3);
        add_filter('sod_calendar_slot_title', array($this, 'fix_calendar_slot_titles'), 10, 2);
        
        add_action('save_post_product', array($this, 'syncProductToService'), 10, 3);
    }

    /**
     * Sync custom table with WooCommerce customer creation.
     * This method is called when a new customer is created in WooCommerce.
     */
    public function sync_custom_table_with_woocommerce($customer_id, $new_customer_data, $password_generated) {
        // Get customer data from WooCommerce
        $user_data = get_userdata($customer_id);

        // Prepare data for the custom table
        $customer_data = array(
            'name' => $user_data->user_login,
            'email' => $user_data->user_email,
            'client_phone' => get_user_meta($customer_id, 'billing_phone', true),
            'emergency_contact_name' => get_user_meta($customer_id, 'emergency_contact_name', true),
            'emergency_contact_phone' => get_user_meta($customer_id, 'emergency_contact_phone', true),
            'signing_dependent' => get_user_meta($customer_id, 'signing_dependent', true),
            'dependent_name' => get_user_meta($customer_id, 'dependent_name', true),
            'dependent_dob' => get_user_meta($customer_id, 'dependent_dob', true),
        );

        // Create customer in custom table
        $custom_customer_id = $this->db_access->createCustomer($customer_data);

        // Error handling and logging
        if ($custom_customer_id === false) {
            sod_log_error('Failed to create customer in custom table for WooCommerce customer ID: ' . $customer_id, 'WooCommerce');
        } else {
            // Save the custom customer ID in WooCommerce user meta for reference
            update_user_meta($customer_id, 'sod_customer_id', $custom_customer_id);
            sod_debug_log('Successfully created customer in custom table for WooCommerce customer ID: ' . $customer_id, 'WooCommerce');
        }
    }

    /**
     * Sync custom table with WooCommerce customer updates.
     * This method is called when a customer profile is updated.
     */
    public function sync_custom_table_with_woocommerce_update($customer_id, $old_user_data) {
        // Check if this is a WooCommerce customer
        if (user_can($customer_id, 'customer')) {
            // Get updated customer data
            $user_data = get_userdata($customer_id);

            // Get custom fields from WooCommerce user meta
            $customer_data = array(
                'name' => $user_data->user_login,
                'email' => $user_data->user_email,
                'client_phone' => get_user_meta($customer_id, 'billing_phone', true),
                'emergency_contact_name' => get_user_meta($customer_id, 'emergency_contact_name', true),
                'emergency_contact_phone' => get_user_meta($customer_id, 'emergency_contact_phone', true),
                'signing_dependent' => get_user_meta($customer_id, 'signing_dependent', true),
                'dependent_name' => get_user_meta($customer_id, 'dependent_name', true),
                'dependent_dob' => get_user_meta($customer_id, 'dependent_dob', true),
            );

            // Check if the customer exists in the custom table
            $custom_customer_id = get_user_meta($customer_id, 'sod_customer_id', true);
            if ($custom_customer_id) {
                // Update the customer in the custom table
                $result = $this->db_access->updateCustomer($custom_customer_id, $customer_data['name'], $customer_data['email'], $customer_data['client_phone']);

                if ($result === false) {
                    sod_log_error('Failed to update custom table for WooCommerce customer ID: ' . $customer_id, 'WooCommerce');
                } else {
                    sod_debug_log('Successfully updated custom table for WooCommerce customer ID: ' . $customer_id, 'WooCommerce');
                }
            }
        }
    }

    /**
     * Enqueue admin scripts for the product editor
     */
    public function enqueue_admin_scripts($hook) {
        global $post_type;
        
        if (($hook === 'post.php' || $hook === 'post-new.php') && $post_type === 'product') {
            wp_enqueue_script(
                'sod-product-staff-service',
                plugins_url('/assets/js/sod-product-staff-service.js', dirname(__FILE__)),
                array('jquery'),
                '1.0.0',
                true
            );
        }
    }

    /**
     * Add service and staff selection fields to WooCommerce products
     */
    public function add_staff_service_fields() {
        global $post;
        
        // Get current values
        $service_id = get_post_meta($post->ID, '_sod_service_id', true);
        $staff_id = get_post_meta($post->ID, '_sod_staff_id', true);
        
        echo '<div class="options_group show_if_simple show_if_variable">';
        echo '<h4 style="padding-left:10px;">' . __('Spark of Divine - Staff Specific Pricing', 'spark-of-divine-scheduler') . '</h4>';
        
        // Get all services
        $services = get_posts(array(
            'post_type' => array('sod_service', 'sod_event', 'sod_class'),
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        
        $service_options = array('' => __('Select a service', 'spark-of-divine-scheduler'));
        foreach ($services as $service) {
            $type_label = '';
            switch($service->post_type) {
                case 'sod_service': $type_label = 'Service'; break;
                case 'sod_event': $type_label = 'Event'; break;
                case 'sod_class': $type_label = 'Class'; break;
            }
            $service_options[$service->ID] = $service->post_title . ' (' . $type_label . ')';
        }
        
        woocommerce_wp_select(array(
            'id' => '_sod_service_id',
            'label' => __('Service', 'spark-of-divine-scheduler'),
            'description' => __('Link this product to a specific service', 'spark-of-divine-scheduler'),
            'desc_tip' => true,
            'options' => $service_options,
            'value' => $service_id
        ));
        
        // Get all staff members
        $staff_members = get_posts(array(
            'post_type' => 'sod_staff',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        
        $staff_options = array('' => __('All Staff / Default Pricing', 'spark-of-divine-scheduler'));
        foreach ($staff_members as $staff) {
            $staff_options[$staff->ID] = $staff->post_title;
        }
        
        woocommerce_wp_select(array(
            'id' => '_sod_staff_id',
            'label' => __('Staff Member', 'spark-of-divine-scheduler'),
            'description' => __('If this product is for a specific staff member\'s pricing', 'spark-of-divine-scheduler'),
            'desc_tip' => true,
            'options' => $staff_options,
            'value' => $staff_id
        ));
        
        echo '<p class="form-field">';
        echo '<em>Note: When you link a product to both a service and staff member, this product will be used for that specific staff member when booking this service.</em>';
        echo '</p>';
        
        echo '</div>';
    }

    /**
     * Save the service and staff IDs
     */
    public function save_staff_service_fields($post_id) {
        // Save staff ID if provided
        if (isset($_POST['_sod_staff_id'])) {
            $staff_id = sanitize_text_field($_POST['_sod_staff_id']);
            update_post_meta($post_id, '_sod_staff_id', $staff_id);
        }
        
        // Save service ID if provided and update relationships
        if (isset($_POST['_sod_service_id'])) {
            $service_id = intval($_POST['_sod_service_id']);
            $existing_service_id = get_post_meta($post_id, '_sod_service_id', true);
            
            // Only update if the service ID has changed
            if ($service_id !== $existing_service_id) {
                // If there was a previous service, remove this product from its related list
                if ($existing_service_id) {
                    $old_related = get_post_meta($existing_service_id, '_sod_related_products', true);
                    if (is_array($old_related)) {
                        $old_related = array_diff($old_related, array($post_id));
                        update_post_meta($existing_service_id, '_sod_related_products', $old_related);
                    }
                }
                
                // Add product to the new service's related products
                if ($service_id) {
                    $related_products = get_post_meta($service_id, '_sod_related_products', true);
                    if (!is_array($related_products)) {
                        $related_products = array();
                    }
                    
                    if (!in_array($post_id, $related_products)) {
                        $related_products[] = $post_id;
                        update_post_meta($service_id, '_sod_related_products', $related_products);
                    }
                    
                    // Set the service type
                    $service_post_type = get_post_type($service_id);
                    if (in_array($service_post_type, array('sod_service', 'sod_event', 'sod_class'))) {
                        $service_type = $service_post_type === 'sod_service' ? 'service' : 
                                       ($service_post_type === 'sod_event' ? 'event' : 'class');
                        update_post_meta($post_id, '_sod_service_type', $service_type);
                    }
                }
                
                // Update the service ID 
                update_post_meta($post_id, '_sod_service_id', $service_id);
            }
        }
    }

    /**
     * Show linked products on service edit screen
     */
    public function add_related_products_metabox() {
        add_meta_box(
            'sod_related_products',
            __('Related Products', 'spark-of-divine-scheduler'),
            array($this, 'render_related_products_metabox'),
            array('sod_service', 'sod_event', 'sod_class'),
            'side',
            'default'
        );
    }

    /**
     * Render the related products metabox
     */
    public function render_related_products_metabox($post) {
        // Get main product
        $main_product_id = get_post_meta($post->ID, '_sod_product_id', true);
        
        // Get staff-specific products
        $related_products = get_post_meta($post->ID, '_sod_related_products', true);
        
        if (empty($main_product_id) && empty($related_products)) {
            echo '<p>' . __('No products are associated with this service.', 'spark-of-divine-scheduler') . '</p>';
        } else {
            echo '<ul style="list-style: disc; margin-left: 15px;">';
            
            // Show main product if exists
            if ($main_product_id) {
                $product = wc_get_product($main_product_id);
                if ($product) {
                    echo '<li><strong>' . __('Main Product', 'spark-of-divine-scheduler') . '</strong>: ';
                    echo '<a href="' . get_edit_post_link($main_product_id) . '">' . $product->get_name() . '</a>';
                    echo '</li>';
                }
            }
            
            // Show staff-specific products
            if (is_array($related_products) && !empty($related_products)) {
                foreach ($related_products as $product_id) {
                    $product = wc_get_product($product_id);
                    if (!$product) continue;
                    
                    $staff_id = get_post_meta($product_id, '_sod_staff_id', true);
                    $staff_name = $staff_id ? get_the_title($staff_id) : __('All Staff', 'spark-of-divine-scheduler');
                    
                    echo '<li>';
                    echo '<a href="' . get_edit_post_link($product_id) . '">' . $product->get_name() . '</a> - ';
                    echo wc_price($product->get_price()) . ' - ';
                    echo '<em>' . $staff_name . '</em>';
                    echo '</li>';
                }
            }
            
            echo '</ul>';
        }
        
        // Add button to create new product for this service
        echo '<p><a href="' . admin_url('post-new.php?post_type=product&sod_service=' . $post->ID) . '" class="button">' . 
             __('Add Product', 'spark-of-divine-scheduler') . '</a></p>';
    }

    /**
     * Pre-select service when creating a product from service page
     */
    public function preselect_service_for_product() {
        global $pagenow;
        
        if ($pagenow === 'post-new.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'product' && isset($_GET['sod_service'])) {
            $service_id = intval($_GET['sod_service']);
            
            if ($service_id > 0) {
                ?>
                <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $('#_sod_service_id').val('<?php echo $service_id; ?>');
                    
                    // Auto-suggest product name based on service
                    var serviceTitle = $('#_sod_service_id option:selected').text();
                    if ($('#title').val() === '' && serviceTitle !== '') {
                        $('#title').val(serviceTitle);
                    }
                });
                </script>
                <?php
            }
        }
    }

    /**
     * Enhance product lookup to consider staff-specific products
     */
    public function enhance_product_lookup($product_data, $service_id, $attribute = null) {
        // If we already have product data, return it
        if ($product_data && !empty($product_data['product_id'])) {
            return $product_data;
        }

        // Get staff ID from the request
        $staff_id = isset($_POST['staff']) ? intval($_POST['staff']) : 0;
        if (!$staff_id || !$service_id) {
            return $product_data;
        }

        // Get all related products for this service
        $related_products = get_post_meta($service_id, '_sod_related_products', true);
        if (!is_array($related_products) || empty($related_products)) {
            return $product_data;
        }

        sod_debug_log("Looking for staff-specific product for service $service_id and staff $staff_id", 'WooCommerce');

        // Look for products specifically linked to this staff member
        foreach ($related_products as $product_id) {
            $product_staff_id = get_post_meta($product_id, '_sod_staff_id', true);

            if ($product_staff_id == $staff_id) {
                $product = wc_get_product($product_id);
                if (!$product) continue;

                // Return the product data without trying to match variations
                sod_debug_log("Found staff-specific product $product_id for service $service_id and staff $staff_id", 'WooCommerce');
                return array(
                    'product_id' => $product_id,
                    'variation_id' => 0,  // No variation matching
                    'price' => $product->get_price()
                );
            }
        }

        sod_debug_log("No staff-specific product found for service $service_id and staff $staff_id", 'WooCommerce');
        return $product_data;
    }

    /**
     * Fix calendar slot titles to show correct staff pricing
     */
    public function fix_calendar_slot_titles($title, $slot) {
        // Only modify if we have both service and staff
        if (empty($slot->service_id) || empty($slot->staff_id)) {
            return $title;
        }
        
        // Check if there's a staff-specific product
        $related_products = get_post_meta($slot->service_id, '_sod_related_products', true);
        if (!is_array($related_products) || empty($related_products)) {
            return $title;
        }
        
        foreach ($related_products as $product_id) {
            $product_staff_id = get_post_meta($product_id, '_sod_staff_id', true);
            
            if ($product_staff_id == $slot->staff_id) {
                $product = wc_get_product($product_id);
                if (!$product) continue;
                
                // Build a better title with service name, staff name, and price
                $service_name = get_the_title($slot->service_id);
                $staff_name = get_the_title($slot->staff_id);
                $price = wc_price($product->get_price());
                
                return sprintf("%s with %s - %s", $service_name, $staff_name, $price);
            }
        }
        
        return $title;
    }
    
    public function syncProductToService($post_id, $post, $update) {
        // Abort if autosave, revision, or wrong post type.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ('product' !== $post->post_type) return;
        
        // Retrieve the enhanced fields from product meta.
        $sod_service_id = get_post_meta($post_id, '_sod_service_id', true); // existing service post ID if any
        $sod_staff_id   = get_post_meta($post_id, '_sod_staff_id', true);
        $sod_duration   = get_post_meta($post_id, '_sod_duration', true);
        // Pricing is managed via variations now.

        // If this product should sync to a service, update or create the service post.
        $service_post_id = $sod_service_id;
        if (empty($service_post_id)) {
            $service_post_id = wp_insert_post(array(
                'post_title'  => get_the_title($post_id),
                'post_type'   => 'sod_service',
                'post_status' => 'publish'
            ));
        } else {
            wp_update_post(array(
                'ID'         => $service_post_id,
                'post_title' => get_the_title($post_id)
            ));
        }
        
        // Now update the service post meta with the custom fields.
        update_post_meta($service_post_id, '_sod_staff_id', $sod_staff_id);
        update_post_meta($service_post_id, '_sod_duration', $sod_duration);
        // No need to update sod_staff_price; variation pricing is used.
        
        // Optionally, update the product meta to store the linked service post ID.
        update_post_meta($post_id, '_sod_service_id', $service_post_id);
    }
}