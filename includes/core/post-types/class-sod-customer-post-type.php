<?php
if ( ! defined('ABSPATH') ) {
    exit;
}

class SOD_Customer_Post_Type {
    public function __construct() {
        add_action('init', array($this, 'register_customer_post_type'));
    }
    
    public function register_customer_post_type() {
        $labels = array(
            'name'                  => __('Customers', 'spark-of-divine-scheduler'),
            'singular_name'         => __('Customer', 'spark-of-divine-scheduler'),
            'menu_name'             => __('Customers', 'spark-of-divine-scheduler'),
            'name_admin_bar'        => __('Customer', 'spark-of-divine-scheduler'),
            'add_new'               => __('Add New Customer', 'spark-of-divine-scheduler'),
            'add_new_item'          => __('Add New Customer', 'spark-of-divine-scheduler'),
            'new_item'              => __('New Customer', 'spark-of-divine-scheduler'),
            'edit_item'             => __('Edit Customer', 'spark-of-divine-scheduler'),
            'view_item'             => __('View Customer', 'spark-of-divine-scheduler'),
            'all_items'             => __('All Customers', 'spark-of-divine-scheduler'),
            'search_items'          => __('Search Customers', 'spark-of-divine-scheduler'),
            'not_found'             => __('No customers found.', 'spark-of-divine-scheduler'),
            'not_found_in_trash'    => __('No customers found in Trash.', 'spark-of-divine-scheduler'),
        );
    
        $args = array(
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'query_var'           => true,
            'rewrite'             => array('slug' => 'customer'),
            'capability_type'     => 'post',
            'has_archive'         => true,
            'hierarchical'        => false,
            'menu_position'       => 8,
            'supports'            => array('title', 'editor', 'custom-fields', 'revisions'),
        );
    
        register_post_type('sod_customer', $args);
    }
}

new SOD_Customer_Post_Type();