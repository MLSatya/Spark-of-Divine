<?php
/**
 * Helper functions for the Spark of Divine Scheduler
 * File: includes/helper-functions.php
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get current user's staff ID if they are a staff member
 */
function sod_get_current_staff_id() {
    $user_id = get_current_user_id();
    if (!$user_id) return 0;
    
    global $wpdb;
    $staff_id = $wpdb->get_var($wpdb->prepare(
        "SELECT staff_id FROM {$wpdb->prefix}sod_staff WHERE user_id = %d",
        $user_id
    ));
    
    return $staff_id ? intval($staff_id) : 0;
}

/**
 * Check if current user is a staff member
 */
function sod_is_current_user_staff() {
    return sod_get_current_staff_id() > 0;
}