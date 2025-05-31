<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Class SOD_Service_Product_Integration
 * 
 * Manages product variations and attributes for the booking system.
 * Removed all service ID dependencies.
 */
class SOD_Service_Product_Integration {
    
    /**
     * Constructor.
     */
    public function __construct() {
        // Manage product variations and fields
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_product_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_product_fields'));
        
        // Service variations tab
        add_filter('woocommerce_product_data_tabs', array($this, 'add_service_variations_tab'));
        add_action('woocommerce_product_data_panels', array($this, 'add_service_variations_panel'));
        add_action('woocommerce_process_product_meta', array($this, 'save_service_variations'), 20);
    }
    
    /**
     * Add fields to WooCommerce product general options.
     */
    public function add_product_fields() {
        global $post;
        
        echo '<div class="options_group show_if_simple">';
        echo '<h4>' . __('Spark of Divine Options', 'spark-of-divine-scheduler') . '</h4>';
        
        // Staff selection field
        $staff_id = get_post_meta($post->ID, '_sod_staff_id', true);
        
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
        
        woocommerce_wp_select(
            array(
                'id' => '_sod_staff_id',
                'label' => __('Staff Member', 'spark-of-divine-scheduler'),
                'options' => $staff_options,
                'desc_tip' => true,
                'description' => __('If this product is for a specific staff member\'s pricing', 'spark-of-divine-scheduler'),
                'value' => $staff_id,
            )
        );
        
        // Duration field
        $duration = get_post_meta($post->ID, '_sod_duration', true);
        
        woocommerce_wp_text_input(
            array(
                'id' => '_sod_duration',
                'label' => __('Duration (minutes)', 'spark-of-divine-scheduler'),
                'desc_tip' => true,
                'description' => __('Duration of this service in minutes', 'spark-of-divine-scheduler'),
                'type' => 'number',
                'custom_attributes' => array(
                    'step' => '1',
                    'min' => '0',
                ),
                'value' => $duration,
            )
        );
        
        echo '</div>';
    }
    
    /**
     * Save product fields.
     */
    public function save_product_fields($post_id) {
        // Save staff ID
        if (isset($_POST['_sod_staff_id'])) {
            $staff_id = sanitize_text_field($_POST['_sod_staff_id']);
            update_post_meta($post_id, '_sod_staff_id', $staff_id);
        }
        
        // Save duration
        if (isset($_POST['_sod_duration'])) {
            $duration = intval($_POST['_sod_duration']);
            update_post_meta($post_id, '_sod_duration', $duration);
        }
    }
    
    /**
     * Add service variations tab to WooCommerce product data tabs.
     */
    public function add_service_variations_tab($tabs) {
        $tabs['sod_variations'] = array(
            'label' => __('Service Variations', 'spark-of-divine-scheduler'),
            'target' => 'sod_service_variations',
            'class' => array('show_if_simple', 'hide_if_variable', 'hide_if_grouped', 'hide_if_external'),
        );
        
        return $tabs;
    }
    
    /**
     * Add service variations panel to WooCommerce product data panels.
     */
    public function add_service_variations_panel() {
        global $post;
        
        ?>
        <div id="sod_service_variations" class="panel woocommerce_options_panel">
            <div class="options_group">
                <p class="form-field">
                    <label><?php _e('Service Variations', 'spark-of-divine-scheduler'); ?></label>
                    <span class="description"><?php _e('Define variations for this product.', 'spark-of-divine-scheduler'); ?></span>
                </p>
                
                <div id="sod_variations_container">
                    <?php
                    // Get existing variations
                    $variations = get_post_meta($post->ID, '_sod_service_variations', true);
                    if (!is_array($variations)) {
                        $variations = array();
                    }
                    
                    // Output existing variations
                    foreach ($variations as $index => $variation) {
                        $type = isset($variation['type']) ? $variation['type'] : '';
                        $value = isset($variation['value']) ? $variation['value'] : '';
                        $price = isset($variation['price']) ? $variation['price'] : '';
                        
                        ?>
                        <div class="sod-variation-row">
                            <select name="sod_variation_type[]" class="sod-variation-type">
                                <option value="duration" <?php selected($type, 'duration'); ?>><?php _e('Duration', 'spark-of-divine-scheduler'); ?></option>
                                <option value="passes" <?php selected($type, 'passes'); ?>><?php _e('Passes', 'spark-of-divine-scheduler'); ?></option>
                                <option value="package" <?php selected($type, 'package'); ?>><?php _e('Package', 'spark-of-divine-scheduler'); ?></option>
                                <option value="custom" <?php selected($type, 'custom'); ?>><?php _e('Custom', 'spark-of-divine-scheduler'); ?></option>
                            </select>
                            <input type="text" name="sod_variation_value[]" value="<?php echo esc_attr($value); ?>" placeholder="<?php _e('Value', 'spark-of-divine-scheduler'); ?>" class="sod-variation-value" />
                            <input type="text" name="sod_variation_price[]" value="<?php echo esc_attr($price); ?>" placeholder="<?php _e('Price', 'spark-of-divine-scheduler'); ?>" class="sod-variation-price" />
                            <button type="button" class="button remove-variation"><?php _e('Remove', 'spark-of-divine-scheduler'); ?></button>
                        </div>
                        <?php
                    }
                    ?>
                </div>
                
                <p>
                    <button type="button" class="button button-primary add-variation"><?php _e('Add Variation', 'spark-of-divine-scheduler'); ?></button>
                </p>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Add variation
            $('.add-variation').on('click', function() {
                var row = '<div class="sod-variation-row">' +
                    '<select name="sod_variation_type[]" class="sod-variation-type">' +
                    '<option value="duration"><?php _e('Duration', 'spark-of-divine-scheduler'); ?></option>' +
                    '<option value="passes"><?php _e('Passes', 'spark-of-divine-scheduler'); ?></option>' +
                    '<option value="package"><?php _e('Package', 'spark-of-divine-scheduler'); ?></option>' +
                    '<option value="custom"><?php _e('Custom', 'spark-of-divine-scheduler'); ?></option>' +
                    '</select>' +
                    '<input type="text" name="sod_variation_value[]" placeholder="<?php _e('Value', 'spark-of-divine-scheduler'); ?>" class="sod-variation-value" />' +
                    '<input type="text" name="sod_variation_price[]" placeholder="<?php _e('Price', 'spark-of-divine-scheduler'); ?>" class="sod-variation-price" />' +
                    '<button type="button" class="button remove-variation"><?php _e('Remove', 'spark-of-divine-scheduler'); ?></button>' +
                    '</div>';
                
                $('#sod_variations_container').append(row);
            });
            
            // Remove variation
            $(document).on('click', '.remove-variation', function() {
                $(this).closest('.sod-variation-row').remove();
            });
            
            // Add initial row if none exist
            if ($('.sod-variation-row').length === 0) {
                $('.add-variation').click();
            }
        });
        </script>
        
        <style type="text/css">
        .sod-variation-row {
            margin-bottom: 10px;
            padding: 10px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        .sod-variation-type {
            width: 30%;
            margin-right: 1%;
        }
        .sod-variation-value {
            width: 30%;
            margin-right: 1%;
        }
        .sod-variation-price {
            width: 20%;
            margin-right: 1%;
        }
        </style>
        <?php
    }
    
    /**
     * Save service variations.
     */
    public function save_service_variations($post_id) {
        // Check if variations data was submitted
        if (!isset($_POST['sod_variation_type']) || !isset($_POST['sod_variation_value']) || !isset($_POST['sod_variation_price'])) {
            return;
        }
        
        $types = $_POST['sod_variation_type'];
        $values = $_POST['sod_variation_value'];
        $prices = $_POST['sod_variation_price'];
        
        $variations = array();
        
        // Process variation data
        for ($i = 0; $i < count($types); $i++) {
            if (empty($values[$i])) {
                continue;
            }
            
            $variations[] = array(
                'type' => sanitize_text_field($types[$i]),
                'value' => sanitize_text_field($values[$i]),
                'price' => floatval($prices[$i]),
            );
        }
        
        // Save variations to product meta
        update_post_meta($post_id, '_sod_service_variations', $variations);
        
        if (function_exists('sod_debug_log')) {
            sod_debug_log("Saved service variations for product $post_id", "Product Integration");
        }
    }
}