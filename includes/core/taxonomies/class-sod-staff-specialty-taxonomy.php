<?php
if ( ! defined('ABSPATH') ) {
    exit;
}

class SOD_Staff_Specialty_Taxonomy {
    public function __construct() {
        add_action('init', array($this, 'register_staff_specialty_taxonomy'));
    }
    
    public function register_staff_specialty_taxonomy() {
        $labels = array(
            'name'              => __('Staff Specialties', 'spark-of-divine-scheduler'),
            'singular_name'     => __('Staff Specialty', 'spark-of-divine-scheduler'),
            'search_items'      => __('Search Staff Specialties', 'spark-of-divine-scheduler'),
            'all_items'         => __('All Staff Specialties', 'spark-of-divine-scheduler'),
            'parent_item'       => __('Parent Staff Specialty', 'spark-of-divine-scheduler'),
            'parent_item_colon' => __('Parent Staff Specialty:', 'spark-of-divine-scheduler'),
            'edit_item'         => __('Edit Staff Specialty', 'spark-of-divine-scheduler'),
            'update_item'       => __('Update Staff Specialty', 'spark-of-divine-scheduler'),
            'add_new_item'      => __('Add New Staff Specialty', 'spark-of-divine-scheduler'),
            'new_item_name'     => __('New Staff Specialty Name', 'spark-of-divine-scheduler'),
            'menu_name'         => __('Staff Specialties', 'spark-of-divine-scheduler'),
        );
    
        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'staff-specialty'),
        );
    
        register_taxonomy('staff_specialty', array('sod_staff'), $args);
    }
}

new SOD_Staff_Specialty_Taxonomy();