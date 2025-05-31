<?php
/**
 * Spark of Divine Elementor Debug Setup
 * 
 * This file should be included in your main plugin file to enable/disable the debugger.
 * It provides a simple interface to toggle debugging without modifying your core plugin code.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Function to determine if debug mode should be enabled
 * 
 * @return bool Whether to enable debug mode
 */
function sod_elementor_debug_enabled() {
    // Option 1: Toggle via constant (define in wp-config.php)
    if (defined('SOD_ELEMENTOR_DEBUG') && SOD_ELEMENTOR_DEBUG) {
        return true;
    }

    // Option 2: Toggle via URL parameter (for developer use only)
    if (isset($_GET['sod_debug']) && $_GET['sod_debug'] === 'elementor' && current_user_can('manage_options')) {
        return true;
    }

    // Option 3: Toggle via site option (can be set in admin settings)
    $debug_option = get_option('sod_elementor_debug_enabled', false);
    if ($debug_option) {
        return true;
    }

    // Default: disabled
    return false;
}

/**
 * Function to activate the Elementor debugger
 */
function activate_elementor_debug() {
    // Check if the debugger should be enabled
    if (!sod_elementor_debug_enabled()) {
        return;
    }

    // Check if the debugger file exists
    $debug_file = SOD_PLUGIN_PATH . 'includes/debug/sod-elementor-debug.php';
    if (file_exists($debug_file)) {
        // Include the debugger
        include_once $debug_file;
        
        // Log activation if the logging function is available
        if (function_exists('sod_widget_debug')) {
            sod_widget_debug("Debugger activated via setup file", "Setup", "INFO");
        }
    } else {
        // Log error if the debug file is missing
        error_log('SOD Elementor Debugger file not found at: ' . $debug_file);
    }
}

/**
 * Helper function to toggle debug mode via site option
 * 
 * @param bool $enabled Whether to enable debug mode
 */
function sod_set_elementor_debug($enabled = true) {
    update_option('sod_elementor_debug_enabled', $enabled);
}

// Add an admin notice when debug mode is active
add_action('admin_notices', function() {
    if (sod_elementor_debug_enabled() && current_user_can('manage_options')) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><strong>SOD Elementor Debug Mode Enabled</strong> - Widget debugging is active and performance may be affected. Disable when not needed.</p>
            <p>Debug log location: <code><?php echo WP_CONTENT_DIR . '/sod-elementor-debug.log'; ?></code></p>
        </div>
        <?php
    }
});

// Automatically clean up old log files (older than 7 days)
add_action('admin_init', function() {
    $log_file = WP_CONTENT_DIR . '/sod-elementor-debug.log';
    if (file_exists($log_file) && filemtime($log_file) < strtotime('-7 days')) {
        @unlink($log_file);
    }
});

// Admin AJAX endpoint to toggle debug mode
add_action('wp_ajax_sod_toggle_elementor_debug', function() {
    // Verify permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sod_elementor_debug')) {
        wp_send_json_error('Invalid nonce');
    }
    
    // Toggle the option
    $current = get_option('sod_elementor_debug_enabled', false);
    sod_set_elementor_debug(!$current);
    
    wp_send_json_success([
        'enabled' => !$current,
        'message' => !$current ? 'Debug mode enabled' : 'Debug mode disabled'
    ]);
});

// Activate the debugger
activate_elementor_debug();