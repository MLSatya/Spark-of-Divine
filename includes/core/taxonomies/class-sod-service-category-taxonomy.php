<?php
if ( ! defined('ABSPATH') ) {
    exit;
}

class SOD_Service_Category_Taxonomy {
    public function __construct() {
        add_action('init', array($this, 'register_service_category_taxonomy'));
    }
    
    public function register_service_category_taxonomy() {
        $labels = array(
            'name'              => __('Service Categories', 'spark-of-divine-scheduler'),
            'singular_name'     => __('Service Category', 'spark-of-divine-scheduler'),
            'search_items'      => __('Search Service Categories', 'spark-of-divine-scheduler'),
            'all_items'         => __('All Service Categories', 'spark-of-divine-scheduler'),
            'parent_item'       => __('Parent Service Category', 'spark-of-divine-scheduler'),
            'parent_item_colon' => __('Parent Service Category:', 'spark-of-divine-scheduler'),
            'edit_item'         => __('Edit Service Category', 'spark-of-divine-scheduler'),
            'update_item'       => __('Update Service Category', 'spark-of-divine-scheduler'),
            'add_new_item'      => __('Add New Service Category', 'spark-of-divine-scheduler'),
            'new_item_name'     => __('New Service Category Name', 'spark-of-divine-scheduler'),
            'menu_name'         => __('Service Categories', 'spark-of-divine-scheduler'),
        );
    
        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'service-category'),
        );
    
        register_taxonomy('service_category', array('sod_service'), $args);
    }
}

new SOD_Service_Category_Taxonomy();