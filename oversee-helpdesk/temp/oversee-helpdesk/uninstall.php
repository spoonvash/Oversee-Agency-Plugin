<?php
/**
 * Uninstall Script
 * 
 * Runs when the plugin is deleted (not just deactivated).
 */

// Exit if not called by WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check if data deletion is enabled
$delete_data = get_option('oversee_delete_data_on_uninstall', false);

// Always remove roles and capabilities
remove_role('oversee_admin');
remove_role('oversee_agent');

// Remove capabilities from administrator
$admin = get_role('administrator');
if ($admin) {
    $caps = [
        'oversee_view_dashboard',
        'oversee_manage_tickets',
        'oversee_respond_tickets',
        'oversee_view_all_tickets',
        'oversee_manage_agents',
        'oversee_manage_settings',
        'oversee_manage_kb',
    ];
    foreach ($caps as $cap) {
        $admin->remove_cap($cap);
    }
}

// Clear scheduled events
wp_clear_scheduled_hook('oversee_cleanup_tokens');
wp_clear_scheduled_hook('oversee_cleanup_auth_log');

// If data deletion is enabled, remove all plugin data
if ($delete_data) {
    global $wpdb;
    
    // Drop all plugin tables
    $tables = [
        $wpdb->prefix . 'oversee_tickets',
        $wpdb->prefix . 'oversee_ticket_replies',
        $wpdb->prefix . 'oversee_agents',
        $wpdb->prefix . 'oversee_canned_responses',
        $wpdb->prefix . 'oversee_tokens',
        $wpdb->prefix . 'oversee_auth_log',
        $wpdb->prefix . 'oversee_push_subscriptions',
        $wpdb->prefix . 'oversee_kb_articles',
        $wpdb->prefix . 'oversee_kb_categories',
    ];
    
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
    
    // Delete all plugin options
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE 'oversee_%'
         OR option_name LIKE '_transient_oversee_%'
         OR option_name LIKE '_transient_timeout_oversee_%'"
    );
    
    // Delete user meta
    $wpdb->query(
        "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'oversee_%'"
    );
}

// Flush rewrite rules
flush_rewrite_rules();
