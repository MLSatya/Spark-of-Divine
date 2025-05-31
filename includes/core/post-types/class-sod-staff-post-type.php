<?php
if ( ! defined('ABSPATH') ) {
    exit;
}

class SOD_Staff_Post_Type {
    public function __construct() {
        add_action('init', array($this, 'register_staff_post_type'));
    }
    
    public function register_staff_post_type() {
        $labels = array(
            'name'                  => __('Staff', 'spark-of-divine-scheduler'),
            'singular_name'         => __('Staff Member', 'spark-of-divine-scheduler'),
            'menu_name'             => __('Staff', 'spark-of-divine-scheduler'),
            'name_admin_bar'        => __('Staff Member', 'spark-of-divine-scheduler'),
            'add_new'               => __('Add New Staff Member', 'spark-of-divine-scheduler'),
            'add_new_item'          => __('Add New Staff Member', 'spark-of-divine-scheduler'),
            'new_item'              => __('New Staff Member', 'spark-of-divine-scheduler'),
            'edit_item'             => __('Edit Staff Member', 'spark-of-divine-scheduler'),
            'view_item'             => __('View Staff Member', 'spark-of-divine-scheduler'),
            'all_items'             => __('All Staff', 'spark-of-divine-scheduler'),
            'search_items'          => __('Search Staff', 'spark-of-divine-scheduler'),
            'not_found'             => __('No staff members found.', 'spark-of-divine-scheduler'),
            'not_found_in_trash'    => __('No staff members found in Trash.', 'spark-of-divine-scheduler'),
        );
    
        $args = array(
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'query_var'           => true,
            'rewrite'             => array('slug' => 'staff'),
            'capability_type'     => array('sod_staff', 'sod_staffs'),
            'map_meta_cap'        => true,
            'has_archive'         => true,
            'hierarchical'        => false,
            'menu_position'       => 6,
            'supports'            => array('title', 'editor', 'custom-fields', 'revisions'),
            'capabilities'        => array(
                'edit_post'          => 'edit_sod_staff',
                'read_post'          => 'read_sod_staff',
                'delete_post'        => 'delete_sod_staff',
                'edit_posts'         => 'edit_sod_staffs',
                'edit_others_posts'  => 'edit_others_sod_staffs',
                'publish_posts'      => 'publish_sod_staffs',
                'read_private_posts' => 'read_private_sod_staffs',
            ),
        );
    
        register_post_type('sod_staff', $args);
    }
}

new SOD_Staff_Post_Type();