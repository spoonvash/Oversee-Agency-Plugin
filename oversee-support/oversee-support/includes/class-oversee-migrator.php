<?php
/**
 * Database Migrator
 * 
 * Handles database migrations between plugin versions.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Oversee_Migrator {
    
    /**
     * Run migrations
     */
    public static function run() {
        $installed_version = get_option('oversee_db_version', '1.0.0');
        $current_version = OVERSEE_VERSION;
        
        if (version_compare($installed_version, $current_version, '>=')) {
            return; // Already up to date
        }
        
        // Run migrations in order
        $migrations = [
            '2.0.0' => 'migrate_to_2_0_0',
        ];
        
        foreach ($migrations as $version => $method) {
            if (version_compare($installed_version, $version, '<')) {
                if (method_exists(__CLASS__, $method)) {
                    self::$method();
                    Oversee_Logger::info("Completed migration to {$version}");
                }
            }
        }
        
        update_option('oversee_db_version', $current_version);
    }
    
    /**
     * Migration to version 2.0.0
     */
    private static function migrate_to_2_0_0() {
        // 1. Create new tables
        Oversee_Activator::create_tables();
        
        // 2. Add columns to existing agents table
        Oversee_Activator::maybe_add_agent_columns();
        
        // 3. Create roles if they don't exist
        Oversee_Activator::create_roles();
        
        // 4. Generate secrets if missing
        if (!get_option('oversee_hl_secret')) {
            update_option('oversee_hl_secret', bin2hex(random_bytes(16)));
        }
        
        if (!get_option('oversee_incoming_webhook_secret')) {
            update_option('oversee_incoming_webhook_secret', bin2hex(random_bytes(16)));
        }
        
        // 5. Migrate existing agents to link with WP users
        self::link_agents_to_wp_users();
        
        // 6. Set default options for new settings
        self::set_new_defaults();
    }
    
    /**
     * Link existing agents to WordPress users by email
     */
    private static function link_agents_to_wp_users() {
        global $wpdb;
        
        // Get all agents without wp_user_id
        $agents = $wpdb->get_results(
            "SELECT id, email FROM {$wpdb->prefix}oversee_agents WHERE wp_user_id IS NULL"
        );
        
        foreach ($agents as $agent) {
            $user = get_user_by('email', $agent->email);
            if ($user) {
                $wpdb->update(
                    $wpdb->prefix . 'oversee_agents',
                    ['wp_user_id' => $user->ID],
                    ['id' => $agent->id],
                    ['%d'],
                    ['%d']
                );
                
                // Grant role if not already
                if (!user_can($user, 'oversee_view_dashboard')) {
                    $user->add_role('oversee_agent');
                }
            }
        }
    }
    
    /**
     * Set default values for new v2.0 settings
     */
    private static function set_new_defaults() {
        $new_defaults = [
            'oversee_enable_remote_kb' => true,
            'oversee_kb_cache_duration' => HOUR_IN_SECONDS,
            'oversee_auto_assign_enabled' => true,
            'oversee_skip_offline_agents' => true,
            'oversee_push_enabled' => true,
        ];
        
        foreach ($new_defaults as $key => $value) {
            if (get_option($key) === false) {
                update_option($key, $value);
            }
        }
    }
}
