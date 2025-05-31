<?php
if ( ! defined('ABSPATH') ) {
    exit; // Exit if accessed directly.
}

class SOD_Booking_Post_Type {
    public function __construct() {
        add_action('init', array($this, 'register_booking_post_type'));
    }
    
    public function register_booking_post_type() {
        $labels = array(
            'name'                  => __('Bookings', 'spark-of-divine-scheduler'),
            'singular_name'         => __('Booking', 'spark-of-divine-scheduler'),
            'menu_name'             => __('Bookings', 'spark-of-divine-scheduler'),
            'name_admin_bar'        => __('Booking', 'spark-of-divine-scheduler'),
            'add_new'               => __('Add New Booking', 'spark-of-divine-scheduler'),
            'add_new_item'          => __('Add New Booking', 'spark-of-divine-scheduler'),
            'new_item'              => __('New Booking', 'spark-of-divine-scheduler'),
            'edit_item'             => __('Edit Booking', 'spark-of-divine-scheduler'),
            'view_item'             => __('View Booking', 'spark-of-divine-scheduler'),
            'all_items'             => __('All Bookings', 'spark-of-divine-scheduler'),
            'search_items'          => __('Search Bookings', 'spark-of-divine-scheduler'),
            'not_found'             => __('No bookings found.', 'spark-of-divine-scheduler'),
            'not_found_in_trash'    => __('No bookings found in Trash.', 'spark-of-divine-scheduler'),
        );
    
        $args = array(
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'query_var'           => true,
            'rewrite'             => array('slug' => 'booking'),
            'capability_type'     => 'post',
            'has_archive'         => true,
            'hierarchical'        => false,
            'menu_position'       => 5,
            'supports'            => array('title', 'editor', 'custom-fields', 'revisions'),
        );
    
        register_post_type('sod_booking', $args);
    }
}

new SOD_Booking_Post_Type();