<?php
/**
 * Plugin Activator
 * 
 * Handles plugin activation: creates tables, roles, and default options.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Oversee_Activator {
    
    /**
     * Activate the plugin
     */
    public static function activate() {
        self::create_tables();
        self::create_roles();
        self::create_default_options();
        self::schedule_cron();
        
        // Register rewrite rules and flush them immediately
        Oversee_Rewrite::add_rewrite_rules();
        flush_rewrite_rules();
        
        // Also set flag for backup flush on next page load
        update_option('oversee_flush_rewrite_rules', true);
        delete_option('oversee_rules_flushed'); // Reset so fallback will flush again if needed
        
        // Log activation
        Oversee_Logger::info('Plugin activated', ['version' => OVERSEE_VERSION]);
    }
    
    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Tickets table
        $sql_tickets = "CREATE TABLE {$wpdb->prefix}oversee_tickets (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ticket_number VARCHAR(20) NOT NULL,
            subject VARCHAR(500) NOT NULL,
            description LONGTEXT,
            status ENUM('new', 'open', 'pending', 'resolved') DEFAULT 'new',
            priority ENUM('low', 'high') DEFAULT 'low',
            customer_name VARCHAR(255),
            customer_email VARCHAR(255) NOT NULL,
            customer_phone VARCHAR(50),
            assigned_to BIGINT UNSIGNED NULL,
            access_token VARCHAR(64) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            resolved_at DATETIME NULL,
            UNIQUE KEY ticket_number (ticket_number),
            UNIQUE KEY access_token (access_token),
            KEY status (status),
            KEY priority (priority),
            KEY assigned_to (assigned_to),
            KEY customer_email (customer_email),
            KEY created_at (created_at)
        ) {$charset_collate};";
        
        // Ticket replies table
        $sql_replies = "CREATE TABLE {$wpdb->prefix}oversee_ticket_replies (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ticket_id BIGINT UNSIGNED NOT NULL,
            message LONGTEXT NOT NULL,
            is_internal_note TINYINT(1) DEFAULT 0,
            author_type ENUM('customer', 'agent') NOT NULL,
            author_id BIGINT UNSIGNED NULL,
            author_name VARCHAR(255),
            author_email VARCHAR(255),
            attachments LONGTEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY ticket_id (ticket_id),
            KEY author_type (author_type),
            KEY is_internal_note (is_internal_note)
        ) {$charset_collate};";
        
        // Agents table
        $sql_agents = "CREATE TABLE {$wpdb->prefix}oversee_agents (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            wp_user_id BIGINT UNSIGNED NULL,
            email VARCHAR(255) NOT NULL,
            first_name VARCHAR(100),
            last_name VARCHAR(100),
            role ENUM('admin', 'agent') DEFAULT 'agent',
            is_online TINYINT(1) DEFAULT 0,
            calendar_link VARCHAR(500),
            zoom_link VARCHAR(500),
            notification_preferences LONGTEXT,
            last_seen DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY email (email),
            KEY wp_user_id (wp_user_id),
            KEY is_online (is_online)
        ) {$charset_collate};";
        
        // Canned responses table
        $sql_canned = "CREATE TABLE {$wpdb->prefix}oversee_canned_responses (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content LONGTEXT NOT NULL,
            shortcut VARCHAR(50),
            created_by BIGINT UNSIGNED,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY shortcut (shortcut)
        ) {$charset_collate};";
        
        // Auth tokens table
        $sql_tokens = "CREATE TABLE {$wpdb->prefix}oversee_tokens (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            token_hash VARCHAR(64) NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            UNIQUE KEY token_hash (token_hash),
            KEY user_id (user_id),
            KEY expires_at (expires_at)
        ) {$charset_collate};";
        
        // Auth log table
        $sql_auth_log = "CREATE TABLE {$wpdb->prefix}oversee_auth_log (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NULL,
            email VARCHAR(255),
            auth_type VARCHAR(20) NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            referrer TEXT,
            success TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY user_id (user_id),
            KEY auth_type (auth_type),
            KEY created_at (created_at),
            KEY ip_address (ip_address)
        ) {$charset_collate};";
        
        // Push subscriptions table
        $sql_push = "CREATE TABLE {$wpdb->prefix}oversee_push_subscriptions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            endpoint VARCHAR(500) NOT NULL,
            p256dh_key VARCHAR(255) NOT NULL,
            auth_key VARCHAR(255) NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            user_type ENUM('agent', 'customer') NOT NULL,
            customer_email VARCHAR(255) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY endpoint (endpoint(191)),
            KEY user_id (user_id),
            KEY user_type (user_type)
        ) {$charset_collate};";
        
        // KB categories table
        $sql_kb_categories = "CREATE TABLE {$wpdb->prefix}oversee_kb_categories (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(255) NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            icon VARCHAR(100) DEFAULT 'fa-solid fa-folder',
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY slug (slug)
        ) {$charset_collate};";
        
        // KB articles table
        $sql_kb_articles = "CREATE TABLE {$wpdb->prefix}oversee_kb_articles (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(255) NOT NULL,
            category_slug VARCHAR(255) NOT NULL,
            title VARCHAR(500) NOT NULL,
            content LONGTEXT,
            excerpt TEXT,
            status ENUM('published', 'draft') DEFAULT 'draft',
            sort_order INT DEFAULT 0,
            author_id BIGINT UNSIGNED,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY slug_category (slug, category_slug),
            KEY category_slug (category_slug),
            KEY status (status),
            KEY author_id (author_id)
        ) {$charset_collate};";
        
        // Run dbDelta for all tables
        dbDelta($sql_tickets);
        dbDelta($sql_replies);
        dbDelta($sql_agents);
        dbDelta($sql_canned);
        dbDelta($sql_tokens);
        dbDelta($sql_auth_log);
        dbDelta($sql_push);
        dbDelta($sql_kb_categories);
        dbDelta($sql_kb_articles);
    }
    
    /**
     * Create custom roles and capabilities
     */
    public static function create_roles() {
        // Define capabilities
        $admin_caps = [
            'read' => true,
            'oversee_view_dashboard' => true,
            'oversee_manage_tickets' => true,
            'oversee_respond_tickets' => true,
            'oversee_view_all_tickets' => true,
            'oversee_manage_agents' => true,
            'oversee_manage_settings' => true,
            'oversee_manage_kb' => true,
        ];
        
        $agent_caps = [
            'read' => true,
            'oversee_view_dashboard' => true,
            'oversee_respond_tickets' => true,
        ];
        
        // Remove existing roles first (in case caps changed)
        remove_role('oversee_admin');
        remove_role('oversee_agent');
        
        // Add roles
        add_role('oversee_admin', 'Support Admin', $admin_caps);
        add_role('oversee_agent', 'Support Agent', $agent_caps);
        
        // Grant capabilities to WordPress Administrator
        $admin = get_role('administrator');
        if ($admin) {
            foreach (array_keys($admin_caps) as $cap) {
                $admin->add_cap($cap);
            }
        }
    }
    
    /**
     * Create default options
     */
    public static function create_default_options() {
        $defaults = [
            // Database version
            'oversee_db_version' => OVERSEE_VERSION,
            
            // HighLevel integration
            'oversee_hl_secret' => bin2hex(random_bytes(16)),
            
            // KB settings
            'oversee_kb_cache_duration' => HOUR_IN_SECONDS,
            
            // Assignment settings
            'oversee_auto_assign_enabled' => true,
            'oversee_skip_offline_agents' => true,
            'oversee_balance_by_workload' => false,
            
            // Portal settings
            'oversee_primary_color' => '#f97316',
            'oversee_secondary_color' => '#1e293b',
            'oversee_portal_title' => 'Help Center',
            'oversee_logo_url' => '',
            
            // Ticket form settings
            'oversee_require_phone' => false,
            'oversee_show_priority' => false,
            'oversee_allow_attachments' => true,
            
            // Email settings
            'oversee_email_from_address' => get_option('admin_email'),
            'oversee_email_from_name' => 'Support Team',
            'oversee_email_on_new_ticket' => true,
            'oversee_email_on_reply' => true,
            
            // Push notification settings
            'oversee_push_enabled' => true,
            
            // Webhook settings
            'oversee_webhook_url' => '',
            'oversee_webhook_events' => [],
            'oversee_incoming_webhook_secret' => bin2hex(random_bytes(16)),
            
            // Security settings
            'oversee_session_timeout' => DAY_IN_SECONDS,
            'oversee_remember_me_duration' => 30 * DAY_IN_SECONDS,
            'oversee_max_login_attempts' => 5,
            'oversee_lockout_duration' => 15 * MINUTE_IN_SECONDS,
            
            // Cleanup settings
            'oversee_delete_data_on_uninstall' => false,
        ];
        
        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                update_option($key, $value);
            }
        }
        
        // Generate VAPID keys if OpenSSL available
        if (!get_option('oversee_vapid_public_key')) {
            $vapid_keys = Oversee_Push::generate_vapid_keys();
            if (!is_wp_error($vapid_keys)) {
                update_option('oversee_vapid_public_key', $vapid_keys['public']);
                update_option('oversee_vapid_private_key', $vapid_keys['private']);
            }
        }
    }
    
    /**
     * Schedule cron jobs
     */
    public static function schedule_cron() {
        if (!wp_next_scheduled('oversee_cleanup_tokens')) {
            wp_schedule_event(time(), 'hourly', 'oversee_cleanup_tokens');
        }
        
        if (!wp_next_scheduled('oversee_cleanup_auth_log')) {
            wp_schedule_event(time(), 'daily', 'oversee_cleanup_auth_log');
        }
    }
    
    /**
     * Handle user deletion - cleanup related data
     */
    public static function handle_user_deletion($user_id) {
        global $wpdb;
        
        // Reassign their tickets to unassigned
        $wpdb->update(
            $wpdb->prefix . 'oversee_tickets',
            ['assigned_to' => null, 'updated_at' => current_time('mysql')],
            ['assigned_to' => $user_id],
            ['%s', '%s'],
            ['%d']
        );
        
        // Delete their auth tokens
        $wpdb->delete(
            $wpdb->prefix . 'oversee_tokens',
            ['user_id' => $user_id],
            ['%d']
        );
        
        // Delete their push subscriptions
        $wpdb->delete(
            $wpdb->prefix . 'oversee_push_subscriptions',
            ['user_id' => $user_id],
            ['%d']
        );
        
        // Delete agent record
        $wpdb->delete(
            $wpdb->prefix . 'oversee_agents',
            ['wp_user_id' => $user_id],
            ['%d']
        );
        
        Oversee_Logger::info('Cleaned up data for deleted user', ['user_id' => $user_id]);
    }
    
    /**
     * Maybe add columns to existing agents table (for upgrades)
     */
    public static function maybe_add_agent_columns() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'oversee_agents';
        
        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table
        ));
        
        if (!$table_exists) {
            return;
        }
        
        // Get existing columns
        $columns = $wpdb->get_col("SHOW COLUMNS FROM `{$table}`");
        
        // Define new columns for v2.0
        $new_columns = [
            'calendar_link' => 'VARCHAR(500) NULL',
            'zoom_link' => 'VARCHAR(500) NULL',
            'is_online' => 'TINYINT(1) DEFAULT 0',
            'wp_user_id' => 'BIGINT UNSIGNED NULL',
            'notification_preferences' => 'LONGTEXT NULL',
            'last_seen' => 'DATETIME NULL'
        ];
        
        foreach ($new_columns as $col_name => $col_def) {
            if (!in_array($col_name, $columns)) {
                $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN `{$col_name}` {$col_def}");
            }
        }
    }
}
