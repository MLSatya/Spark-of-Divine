<?php
/**
 * Template Name: Schedule Template
 *
 * This template uses the SOD_Schedule_Display class to render schedules,
 * while also allowing standard page content to be displayed above the schedule.
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

// Start the loop to get page content
while (have_posts()) {
    the_post();
    ?>
    <div class="page-content-container">
        <?php the_content(); ?>
    </div>
    <?php
}

/**
 * Step 1: Get parameters from the URL or use defaults
 */
$view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'week';
$date_param = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : date('Y-m-d');
$service_filter = isset($_GET['service']) ? intval($_GET['service']) : 0;
$staff_filter = isset($_GET['staff']) ? intval($_GET['staff']) : 0;
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;

/**
 * Step 2: Render the schedule using the SOD_Schedule_Display class
 */
if (class_exists('SOD_Schedule_Display')) {
    $schedule = new SOD_Schedule_Display($view, $date_param, $service_filter, $staff_filter, $category_filter);
    $schedule->render();
} else {
    // If the class doesn't exist, display a fallback
    echo '<div class="sod-schedule-container">';
    echo '<p>The schedule display component could not be loaded. Please contact the administrator.</p>';
    echo '</div>';
    
    // Log the error
    if (function_exists('sod_log_error')) {
        sod_log_error("SOD_Schedule_Display class not found", "Template");
    } else {
        error_log("SOD_Schedule_Display class not found");
    }
}

get_footer();