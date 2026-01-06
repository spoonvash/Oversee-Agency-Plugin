<?php
/**
 * Cron Jobs
 * 
 * Handles scheduled cleanup tasks.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Oversee_Cron {
    
    /**
     * Initialize cron jobs
     */
    public static function init() {
        // Register cleanup handlers
        add_action('oversee_cleanup_tokens', [__CLASS__, 'cleanup_expired_tokens']);
        add_action('oversee_cleanup_auth_log', [__CLASS__, 'cleanup_old_auth_logs']);
        
        // Schedule if not already scheduled
        self::schedule_events();
    }
    
    /**
     * Schedule cron events
     */
    public static function schedule_events() {
        if (!wp_next_scheduled('oversee_cleanup_tokens')) {
            wp_schedule_event(time(), 'hourly', 'oversee_cleanup_tokens');
        }
        
        if (!wp_next_scheduled('oversee_cleanup_auth_log')) {
            wp_schedule_event(time(), 'daily', 'oversee_cleanup_auth_log');
        }
    }
    
    /**
     * Clear all scheduled events (for deactivation)
     */
    public static function clear_events() {
        wp_clear_scheduled_hook('oversee_cleanup_tokens');
        wp_clear_scheduled_hook('oversee_cleanup_auth_log');
    }
    
    /**
     * Cleanup expired auth tokens
     */
    public static function cleanup_expired_tokens() {
        global $wpdb;
        
        $deleted = $wpdb->query(
            "DELETE FROM {$wpdb->prefix}oversee_tokens WHERE expires_at < NOW()"
        );
        
        if ($deleted > 0) {
            Oversee_Logger::debug('Cleaned up expired tokens', ['count' => $deleted]);
        }
    }
    
    /**
     * Cleanup old auth logs (keep last 30 days)
     */
    public static function cleanup_old_auth_logs() {
        global $wpdb;
        
        $deleted = $wpdb->query(
            "DELETE FROM {$wpdb->prefix}oversee_auth_log 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        if ($deleted > 0) {
            Oversee_Logger::debug('Cleaned up old auth logs', ['count' => $deleted]);
        }
    }
    
    /**
     * Cleanup stale agent online status (agents who haven't been seen in 30 minutes)
     */
    public static function cleanup_stale_online_status() {
        global $wpdb;
        
        $wpdb->query(
            "UPDATE {$wpdb->prefix}oversee_agents 
             SET is_online = 0 
             WHERE is_online = 1 
             AND last_seen < DATE_SUB(NOW(), INTERVAL 30 MINUTE)"
        );
    }
}
