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
        add_action('oversee_reset_weekly_metrics', [__CLASS__, 'reset_weekly_metrics']);
        add_action('oversee_reset_monthly_metrics', [__CLASS__, 'reset_monthly_metrics']);
        
        // Register custom cron schedule for weekly
        add_filter('cron_schedules', [__CLASS__, 'add_custom_schedules']);
        
        // Schedule if not already scheduled
        self::schedule_events();
    }
    
    /**
     * Add custom cron schedules
     */
    public static function add_custom_schedules($schedules) {
        $schedules['weekly'] = [
            'interval' => WEEK_IN_SECONDS,
            'display' => 'Once Weekly'
        ];
        $schedules['monthly'] = [
            'interval' => 30 * DAY_IN_SECONDS,
            'display' => 'Once Monthly'
        ];
        return $schedules;
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
        
        // Schedule weekly metrics reset for Monday at midnight
        if (!wp_next_scheduled('oversee_reset_weekly_metrics')) {
            // Calculate next Monday midnight
            $next_monday = strtotime('next monday midnight');
            wp_schedule_event($next_monday, 'weekly', 'oversee_reset_weekly_metrics');
        }
        
        // Schedule monthly metrics reset for 1st of month
        if (!wp_next_scheduled('oversee_reset_monthly_metrics')) {
            // Calculate first of next month
            $first_of_month = strtotime('first day of next month midnight');
            wp_schedule_event($first_of_month, 'monthly', 'oversee_reset_monthly_metrics');
        }
    }
    
    /**
     * Clear all scheduled events (for deactivation)
     */
    public static function clear_events() {
        wp_clear_scheduled_hook('oversee_cleanup_tokens');
        wp_clear_scheduled_hook('oversee_cleanup_auth_log');
        wp_clear_scheduled_hook('oversee_reset_weekly_metrics');
        wp_clear_scheduled_hook('oversee_reset_monthly_metrics');
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
    
    /**
     * Reset weekly metrics for all agents
     */
    public static function reset_weekly_metrics() {
        global $wpdb;
        
        $wpdb->query(
            "UPDATE {$wpdb->prefix}oversee_agents 
             SET tickets_resolved_week = 0, 
                 responses_week = 0,
                 metrics_last_updated = NOW()"
        );
        
        Oversee_Logger::info('Weekly agent metrics reset');
    }
    
    /**
     * Reset monthly metrics for all agents
     */
    public static function reset_monthly_metrics() {
        global $wpdb;
        
        $wpdb->query(
            "UPDATE {$wpdb->prefix}oversee_agents 
             SET tickets_resolved_month = 0, 
                 responses_month = 0,
                 metrics_last_updated = NOW()"
        );
        
        Oversee_Logger::info('Monthly agent metrics reset');
    }
}
