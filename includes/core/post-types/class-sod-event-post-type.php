<?php
/**
 * Class SOD_Event_Post_Type
 *
 * Registers the custom post type "Event" for event management.
 *
 * @package SparkOfDivineScheduler
 * @since 2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class SOD_Event_Post_Type {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'init', array( $this, 'register_event_post_type' ) );
    }

    /**
     * Registers the custom post type for Events.
     */
    public function register_event_post_type() {
        $labels = array(
            'name'                  => _x( 'Events', 'Post Type General Name', 'spark-of-divine-scheduler' ),
            'singular_name'         => _x( 'Event', 'Post Type Singular Name', 'spark-of-divine-scheduler' ),
            'menu_name'             => __( 'Events', 'spark-of-divine-scheduler' ),
            'name_admin_bar'        => __( 'Event', 'spark-of-divine-scheduler' ),
            'archives'              => __( 'Event Archives', 'spark-of-divine-scheduler' ),
            'attributes'            => __( 'Event Attributes', 'spark-of-divine-scheduler' ),
            'parent_item_colon'     => __( 'Parent Event:', 'spark-of-divine-scheduler' ),
            'all_items'             => __( 'All Events', 'spark-of-divine-scheduler' ),
            'add_new_item'          => __( 'Add New Event', 'spark-of-divine-scheduler' ),
            'add_new'               => __( 'Add New', 'spark-of-divine-scheduler' ),
            'new_item'              => __( 'New Event', 'spark-of-divine-scheduler' ),
            'edit_item'             => __( 'Edit Event', 'spark-of-divine-scheduler' ),
            'update_item'           => __( 'Update Event', 'spark-of-divine-scheduler' ),
            'view_item'             => __( 'View Event', 'spark-of-divine-scheduler' ),
            'view_items'            => __( 'View Events', 'spark-of-divine-scheduler' ),
            'search_items'          => __( 'Search Event', 'spark-of-divine-scheduler' ),
            'not_found'             => __( 'Not found', 'spark-of-divine-scheduler' ),
            'not_found_in_trash'    => __( 'Not found in Trash', 'spark-of-divine-scheduler' ),
            'featured_image'        => __( 'Featured Image', 'spark-of-divine-scheduler' ),
            'set_featured_image'    => __( 'Set featured image', 'spark-of-divine-scheduler' ),
            'remove_featured_image' => __( 'Remove featured image', 'spark-of-divine-scheduler' ),
            'use_featured_image'    => __( 'Use as featured image', 'spark-of-divine-scheduler' ),
            'insert_into_item'      => __( 'Insert into event', 'spark-of-divine-scheduler' ),
            'uploaded_to_this_item' => __( 'Uploaded to this event', 'spark-of-divine-scheduler' ),
            'items_list'            => __( 'Events list', 'spark-of-divine-scheduler' ),
            'items_list_navigation' => __( 'Events list navigation', 'spark-of-divine-scheduler' ),
            'filter_items_list'     => __( 'Filter events list', 'spark-of-divine-scheduler' ),
        );

        $args = array(
            'label'                 => __( 'Event', 'spark-of-divine-scheduler' ),
            'description'           => __( 'Custom post type for managing events', 'spark-of-divine-scheduler' ),
            'labels'                => $labels,
            'supports'              => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
            'hierarchical'          => false,
            'public'                => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_position'         => 5,
            'menu_icon'             => 'dashicons-calendar-alt',
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => true,
            'can_export'            => true,
            'has_archive'           => true,
            'exclude_from_search'   => false,
            'publicly_queryable'    => true,
            'capability_type'       => 'post',
        );

        register_post_type( 'sod_event', $args );
    }
}

// Initialize the event post type.
new SOD_Event_Post_Type();