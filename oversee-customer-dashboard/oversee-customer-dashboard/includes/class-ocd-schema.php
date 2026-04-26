<?php
/**
 * Custom-table schema for messages, projects, milestones, tasks, entitlements
 * and customer-owned CRM connections. Activated tables are created with
 * dbDelta on plugin activation and via a manual installer hook.
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class OCD_Schema {

    const DB_VERSION_OPTION = 'ocd_db_version';
    const DB_VERSION        = '1.1.0';

    public static function table($name) {
        global $wpdb;
        return $wpdb->prefix . 'ocd_' . $name;
    }

    public static function maybe_install() {
        $current = get_option(self::DB_VERSION_OPTION, '');
        if ($current === self::DB_VERSION) {
            return;
        }
        self::install();
        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
    }

    public static function install() {
        global $wpdb;
        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = $wpdb->get_charset_collate();

        $messages    = self::table('messages');
        $projects    = self::table('projects');
        $milestones  = self::table('milestones');
        $tasks       = self::table('tasks');
        $entitlements = self::table('entitlements');
        $crm_links   = self::table('customer_crm');

        $sql = [];

        $sql[] = "CREATE TABLE $messages (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            direction VARCHAR(20) NOT NULL DEFAULT 'inbound',
            source VARCHAR(40) NOT NULL DEFAULT 'dashboard',
            body LONGTEXT NOT NULL,
            hl_contact_id VARCHAR(64) DEFAULT NULL,
            hl_conversation_id VARCHAR(64) DEFAULT NULL,
            hl_message_id VARCHAR(64) DEFAULT NULL,
            hl_synced TINYINT(1) NOT NULL DEFAULT 0,
            hl_sync_error TEXT DEFAULT NULL,
            read_by_admin TINYINT(1) NOT NULL DEFAULT 0,
            read_by_user TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY hl_conversation_id (hl_conversation_id),
            KEY hl_contact_id (hl_contact_id),
            KEY direction (direction),
            KEY created_at (created_at)
        ) $charset;";

        $sql[] = "CREATE TABLE $projects (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            wc_order_id BIGINT UNSIGNED DEFAULT NULL,
            wc_subscription_id BIGINT UNSIGNED DEFAULT NULL,
            wc_product_id BIGINT UNSIGNED DEFAULT NULL,
            title VARCHAR(255) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'planning',
            progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
            start_date DATE DEFAULT NULL,
            target_date DATE DEFAULT NULL,
            completed_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY wc_order_id (wc_order_id),
            KEY wc_subscription_id (wc_subscription_id),
            KEY status (status)
        ) $charset;";

        $sql[] = "CREATE TABLE $milestones (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            project_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            note LONGTEXT DEFAULT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'pending',
            sort_order INT NOT NULL DEFAULT 0,
            due_date DATE DEFAULT NULL,
            completed_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY project_id (project_id),
            KEY status (status)
        ) $charset;";

        $sql[] = "CREATE TABLE $tasks (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            project_id BIGINT UNSIGNED DEFAULT NULL,
            title VARCHAR(255) NOT NULL,
            details LONGTEXT DEFAULT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'open',
            due_date DATE DEFAULT NULL,
            completed_at DATETIME DEFAULT NULL,
            assigned_by BIGINT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY project_id (project_id),
            KEY status (status),
            KEY due_date (due_date)
        ) $charset;";

        $sql[] = "CREATE TABLE $entitlements (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            slug VARCHAR(120) NOT NULL,
            label VARCHAR(255) NOT NULL,
            wc_product_id BIGINT UNSIGNED DEFAULT NULL,
            wc_order_id BIGINT UNSIGNED DEFAULT NULL,
            wc_subscription_id BIGINT UNSIGNED DEFAULT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'active',
            granted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            revoked_at DATETIME DEFAULT NULL,
            meta LONGTEXT DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_slug (user_id, slug),
            KEY wc_product_id (wc_product_id),
            KEY status (status)
        ) $charset;";

        $sql[] = "CREATE TABLE $crm_links (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            provider VARCHAR(40) NOT NULL DEFAULT 'highlevel',
            label VARCHAR(255) DEFAULT NULL,
            location_id VARCHAR(120) DEFAULT NULL,
            access_token TEXT DEFAULT NULL,
            refresh_token TEXT DEFAULT NULL,
            token_expires_at DATETIME DEFAULT NULL,
            connected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            disconnected_at DATETIME DEFAULT NULL,
            meta LONGTEXT DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_provider (user_id, provider)
        ) $charset;";

        foreach ($sql as $statement) {
            dbDelta($statement);
        }
    }
}
