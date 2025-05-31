<?php
if ( ! defined('ABSPATH') ) {
    exit;
}

class SOD_Service_Post_Type {
    public function __construct() {
        add_action('init', array($this, 'register_service_post_type'));
    }
    
    public function register_service_post_type() {
        $labels = array(
            'name'                  => __('Services', 'spark-of-divine-scheduler'),
            'singular_name'         => __('Service', 'spark-of-divine-scheduler'),
            'menu_name'             => __('Services', 'spark-of-divine-scheduler'),
            'name_admin_bar'        => __('Service', 'spark-of-divine-scheduler'),
            'add_new'               => __('Add New Service', 'spark-of-divine-scheduler'),
            'add_new_item'          => __('Add New Service', 'spark-of-divine-scheduler'),
            'new_item'              => __('New Service', 'spark-of-divine-scheduler'),
            'edit_item'             => __('Edit Service', 'spark-of-divine-scheduler'),
            'view_item'             => __('View Service', 'spark-of-divine-scheduler'),
            'all_items'             => __('All Services', 'spark-of-divine-scheduler'),
            'search_items'          => __('Search Services', 'spark-of-divine-scheduler'),
            'not_found'             => __('No services found.', 'spark-of-divine-scheduler'),
            'not_found_in_trash'    => __('No services found in Trash.', 'spark-of-divine-scheduler'),
        );
    
        $args = array(
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'query_var'           => true,
            'rewrite'             => array('slug' => 'service'),
            'capability_type'     => 'post',
            'has_archive'         => true,
            'hierarchical'        => false,
            'menu_position'       => 7,
            'supports'            => array('title', 'editor', 'custom-fields', 'revisions'),
            'taxonomies'          => array('service_category'),
        );
    
        register_post_type('sod_service', $args);
    }
}

new SOD_Service_Post_Type();