<?php
/**
 * Theme functions and definitions.
 *
 * For additional information on potential customization options,
 * read the developers' documentation:
 *
 * https://developers.elementor.com/docs/hello-elementor-theme/
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'HELLO_ELEMENTOR_CHILD_VERSION', '2.0.0' );

/**
 * Load child theme scripts & styles.
 *
 * @return void
 */
function hello_elementor_child_scripts_styles() {
    // Enqueue parent theme stylesheet
    wp_enqueue_style(
        'hello-elementor-child-style',
        get_stylesheet_directory_uri() . '/style.css',
        ['hello-elementor-theme-style'],
        HELLO_ELEMENTOR_CHILD_VERSION
    );

    // Enqueue FullCalendar script and style
    wp_enqueue_script('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js', array('jquery'), '6.1.8', true);
    wp_enqueue_style('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css', array(), '6.1.8');

    // Enqueue custom schedule style
    wp_enqueue_style('sod-schedule-style', get_stylesheet_directory_uri() . '/assets/css/schedule-style.css', array('fullcalendar'), HELLO_ELEMENTOR_CHILD_VERSION);

    // Enqueue custom schedule script
    wp_enqueue_script('sod-schedule-script', get_stylesheet_directory_uri() . '/assets/js/schedule-script.js', array('jquery', 'fullcalendar'), HELLO_ELEMENTOR_CHILD_VERSION, true);

    // Localize the script with new data
    $script_data = array(
        'ajax_url' => admin_url('admin-ajax.php'),
        // Add any other data you want to pass to your script
    );
    wp_localize_script('sod-schedule-script', 'sodData', $script_data);
}
add_action( 'wp_enqueue_scripts', 'hello_elementor_child_scripts_styles', 20 );