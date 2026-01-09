<?php
/**
 * Logger Class
 * 
 * Handles error and activity logging for debugging.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Oversee_Logger {
    
    const LOG_OPTION = 'oversee_error_log';
    const MAX_ENTRIES = 100;
    
    /**
     * Log a message
     */
    public static function log($level, $message, $context = []) {
        // Only log if debug enabled or it's an error
        if (!WP_DEBUG && !in_array($level, ['error', 'warning'])) {
            return;
        }
        
        $entry = [
            'timestamp' => current_time('mysql'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'url' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
            'user_id' => get_current_user_id(),
            'ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''
        ];
        
        // Get existing log
        $log = get_option(self::LOG_OPTION, []);
        
        if (!is_array($log)) {
            $log = [];
        }
        
        // Add new entry at beginning
        array_unshift($log, $entry);
        
        // Keep only last N entries
        $log = array_slice($log, 0, self::MAX_ENTRIES);
        
        // Save
        update_option(self::LOG_OPTION, $log, false);
        
        // Also write to PHP error log for errors
        if ($level === 'error') {
            error_log('Oversee Error: ' . $message . ' | ' . wp_json_encode($context));
        }
    }
    
    /**
     * Log an error
     */
    public static function error($message, $context = []) {
        self::log('error', $message, $context);
    }
    
    /**
     * Log a warning
     */
    public static function warning($message, $context = []) {
        self::log('warning', $message, $context);
    }
    
    /**
     * Log an info message
     */
    public static function info($message, $context = []) {
        self::log('info', $message, $context);
    }
    
    /**
     * Log a debug message
     */
    public static function debug($message, $context = []) {
        self::log('debug', $message, $context);
    }
    
    /**
     * Get the log
     */
    public static function get_log($level = null, $limit = 50) {
        $log = get_option(self::LOG_OPTION, []);
        
        if (!is_array($log)) {
            return [];
        }
        
        // Filter by level if specified
        if ($level) {
            $log = array_filter($log, function($entry) use ($level) {
                return isset($entry['level']) && $entry['level'] === $level;
            });
        }
        
        return array_slice($log, 0, $limit);
    }
    
    /**
     * Clear the log
     */
    public static function clear_log() {
        delete_option(self::LOG_OPTION);
    }
    
    /**
     * Get log count by level
     */
    public static function get_counts() {
        $log = get_option(self::LOG_OPTION, []);
        
        if (!is_array($log)) {
            return ['error' => 0, 'warning' => 0, 'info' => 0, 'debug' => 0];
        }
        
        $counts = ['error' => 0, 'warning' => 0, 'info' => 0, 'debug' => 0];
        
        foreach ($log as $entry) {
            $level = isset($entry['level']) ? $entry['level'] : 'info';
            if (isset($counts[$level])) {
                $counts[$level]++;
            }
        }
        
        return $counts;
    }
}
