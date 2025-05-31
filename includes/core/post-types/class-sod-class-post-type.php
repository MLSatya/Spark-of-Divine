<?php
/**
 * Custom Post Type: SOD Class
 *
 * @package SparkOfDivineScheduler
 * @since 2.1
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SOD_Class_Post_Type {
    public function __construct() {
        add_action( 'init', array( $this, 'register_post_type' ) );
    }

    public function register_post_type() {
        $labels = array(
            'name'                  => __( 'Classes', 'spark-of-divine-scheduler' ),
            'singular_name'         => __( 'Class', 'spark-of-divine-scheduler' ),
            'menu_name'             => __( 'Classes', 'spark-of-divine-scheduler' ),
            'name_admin_bar'        => __( 'Class', 'spark-of-divine-scheduler' ),
            'add_new'               => __( 'Add New', 'spark-of-divine-scheduler' ),
            'add_new_item'          => __( 'Add New Class', 'spark-of-divine-scheduler' ),
            'new_item'              => __( 'New Class', 'spark-of-divine-scheduler' ),
            'edit_item'             => __( 'Edit Class', 'spark-of-divine-scheduler' ),
            'view_item'             => __( 'View Class', 'spark-of-divine-scheduler' ),
            'all_items'             => __( 'All Classes', 'spark-of-divine-scheduler' ),
            'search_items'          => __( 'Search Classes', 'spark-of-divine-scheduler' ),
            'not_found'             => __( 'No classes found.', 'spark-of-divine-scheduler' ),
            'not_found_in_trash'    => __( 'No classes found in Trash.', 'spark-of-divine-scheduler' ),
        );
    
        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'sod_class' ),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'supports'           => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
            'show_in_rest'       => true,
        );
    
        register_post_type( 'sod_class', $args );
    }
}

new SOD_Class_Post_Type();