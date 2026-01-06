<?php
/**
 * Plugin Deactivator
 * 
 * Handles plugin deactivation: clears scheduled events and flushes rewrite rules.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Oversee_Deactivator {
    
    /**
     * Deactivate the plugin
     */
    public static function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('oversee_cleanup_tokens');
        wp_clear_scheduled_hook('oversee_cleanup_auth_log');
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log deactivation
        if (class_exists('Oversee_Logger')) {
            Oversee_Logger::info('Plugin deactivated');
        }
    }
}
