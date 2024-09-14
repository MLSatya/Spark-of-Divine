<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class SOD_Custom_Post_Types {
    public function __construct() {
        // Hook into the 'init' action
        add_action('init', array($this, 'registerCustomPostTypes'));
    }

    // Method to register all custom post types
    public function registerCustomPostTypes() {
        $this->registerBookingPostType();
        $this->registerStaffPostType();
        $this->registerServicePostType();
        $this->registerCustomerPostType();
    }

    // Register Booking Custom Post Type
    private function registerBookingPostType() {
        $labels = [
            'name'               => __('Bookings', 'textdomain'),
            'singular_name'      => __('Booking', 'textdomain'),
            'menu_name'          => __('Bookings', 'textdomain'),
            'name_admin_bar'     => __('Booking', 'textdomain'),
            'add_new'            => __('Add New Booking', 'textdomain'),
            'add_new_item'       => __('Add New Booking', 'textdomain'),
            'new_item'           => __('New Booking', 'textdomain'),
            'edit_item'          => __('Edit Booking', 'textdomain'),
            'view_item'          => __('View Booking', 'textdomain'),
            'all_items'          => __('All Bookings', 'textdomain'),
            'search_items'       => __('Search Bookings', 'textdomain'),
            'not_found'          => __('No bookings found.', 'textdomain'),
            'not_found_in_trash' => __('No bookings found in Trash.', 'textdomain'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'booking'],
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 5,
            'supports'           => ['title', 'editor', 'custom-fields', 'revisions'],
        ];

        register_post_type('sod_booking', $args); // Prefixing to avoid conflicts
    }

    // Register Staff Custom Post Type
    private function registerStaffPostType() {
        $labels = [
            'name'               => __('Staff', 'textdomain'),
            'singular_name'      => __('Staff', 'textdomain'),
            'menu_name'          => __('Staff', 'textdomain'),
            'name_admin_bar'     => __('Staff', 'textdomain'),
            'add_new'            => __('Add New Staff', 'textdomain'),
            'add_new_item'       => __('Add New Staff', 'textdomain'),
            'new_item'           => __('New Staff', 'textdomain'),
            'edit_item'          => __('Edit Staff', 'textdomain'),
            'view_item'          => __('View Staff', 'textdomain'),
            'all_items'          => __('All Staff', 'textdomain'),
            'search_items'       => __('Search Staff', 'textdomain'),
            'not_found'          => __('No staff found.', 'textdomain'),
            'not_found_in_trash' => __('No staff found in Trash.', 'textdomain'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'staff'],
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 6,
            'supports'           => ['title', 'editor', 'custom-fields', 'revisions'],
        ];

        register_post_type('sod_staff', $args); // Prefixing to avoid conflicts
    }

    // Register Service Custom Post Type
    private function registerServicePostType() {
        $labels = [
            'name'               => __('Services', 'textdomain'),
            'singular_name'      => __('Service', 'textdomain'),
            'menu_name'          => __('Services', 'textdomain'),
            'name_admin_bar'     => __('Service', 'textdomain'),
            'add_new'            => __('Add New Service', 'textdomain'),
            'add_new_item'       => __('Add New Service', 'textdomain'),
            'new_item'           => __('New Service', 'textdomain'),
            'edit_item'          => __('Edit Service', 'textdomain'),
            'view_item'          => __('View Service', 'textdomain'),
            'all_items'          => __('All Services', 'textdomain'),
            'search_items'       => __('Search Services', 'textdomain'),
            'not_found'          => __('No services found.', 'textdomain'),
            'not_found_in_trash' => __('No services found in Trash.', 'textdomain'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'service'],
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 7,
            'supports'           => ['title', 'editor', 'custom-fields', 'revisions'],
        ];

        register_post_type('sod_service', $args); // Prefixing to avoid conflicts
    }

    // Register Customer Custom Post Type
    private function registerCustomerPostType() {
        $labels = [
            'name'               => __('Customers', 'textdomain'),
            'singular_name'      => __('Customer', 'textdomain'),
            'menu_name'          => __('Customers', 'textdomain'),
            'name_admin_bar'     => __('Customer', 'textdomain'),
            'add_new'            => __('Add New Customer', 'textdomain'),
            'add_new_item'       => __('Add New Customer', 'textdomain'),
            'new_item'           => __('New Customer', 'textdomain'),
            'edit_item'          => __('Edit Customer', 'textdomain'),
            'view_item'          => __('View Customer', 'textdomain'),
            'all_items'          => __('All Customers', 'textdomain'),
            'search_items'       => __('Search Customers', 'textdomain'),
            'not_found'          => __('No customers found.', 'textdomain'),
            'not_found_in_trash' => __('No customers found in Trash.', 'textdomain'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'customer'],
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 8,
            'supports'           => ['title', 'editor', 'custom-fields', 'revisions'],
        ];

        register_post_type('sod_customer', $args); // Prefixing to avoid conflicts
    }
}