<?php
/**
 * Autotask Plugin - Direct Fix Script
 * 
 * This script directly modifies the way the update checker works by applying
 * an additional filter to the update checker after it's been initialized.
 * 
 * Instructions:
 * 1. Add this code to the bottom of your main plugin file (autotask-time-entry.php)
 * 2. After the "new Autotask_Time_Entry();" line
 */

// Initialize the plugin
new Autotask_Time_Entry();

// Direct fix for GitHub release detection
add_action('admin_init', function() {
    // Force cache refresh
    delete_site_transient('update_plugins');
    
    // Add a filter to directly modify the GitHub API response
    add_filter('puc_request_info_result-autotask-time-entry', function($info, $result) {
        if (!$info) {
            return $info; // Don't modify if there's no info
        }
        
        error_log('Autotask Time Entry: Applying direct fix to update info');
        
        // Check current plugin version
        $currentVersion = defined('AUTOTASK_TIME_ENTRY_VERSION') ? AUTOTASK_TIME_ENTRY_VERSION : '1.0.19';
        
        // Override with correct version info
        $info->version = $currentVersion;
        $info->slug = 'autotask-time-entry';
        
        // Get the raw download URL for the zip file
        $downloadUrl = 'https://github.com/wnearhood/Autotask-Plugin/releases/download/v' . $currentVersion . '/autotask-time-entry.zip';
        $info->download_url = $downloadUrl;
        
        error_log('Autotask Time Entry: Modified update info - version: ' . $info->version);
        error_log('Autotask Time Entry: Download URL: ' . $info->download_url);
        
        return $info;
    }, 10, 2);
    
    // Debugging to trace the sources of version info
    add_action('admin_notices', function() {
        global $pagenow;
        
        // Only on update pages
        if ($pagenow !== 'update-core.php' && $pagenow !== 'plugins.php') {
            return;
        }
        
        // Get update info
        $update_plugins = get_site_transient('update_plugins');
        
        if ($update_plugins && !empty($update_plugins->response)) {
            error_log('Autotask Time Entry Debug: Available plugin updates: ' . print_r(array_keys($update_plugins->response), true));
        } else {
            error_log('Autotask Time Entry Debug: No plugin updates found');
        }
    });
}, 20); // Later priority to ensure it runs after plugin initialization
