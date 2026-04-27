<?php
/**
 * Monday.com-style schema extension.
 *
 * The original `ocd_projects/milestones/tasks/...` tables stay as-is for the
 * existing dashboard surfaces. This class adds a parallel set of tables that
 * model the richer Monday-style "board → group → item → subitem" hierarchy
 * used by the new project-board system spawned from WooCommerce SKUs:
 *
 *   ocd_boards               One row per project_board CPT, denormalized.
 *   ocd_board_groups         Groups inside a board (e.g. "This month").
 *   ocd_board_items          Items inside a group (the rows in the table).
 *   ocd_board_subitems       Children of an item (sub-tasks).
 *   ocd_board_columns        Column definitions on a board (status, date, ...).
 *   ocd_board_views          Saved views (table, kanban, calendar, timeline).
 *   ocd_board_updates        Activity feed entries on items (TipTap doc body).
 *   ocd_board_update_attachments  Files referenced from updates.
 *   ocd_board_item_files     Files attached directly to items.
 *   ocd_board_automations    Automation rules (when X then Y).
 *   ocd_board_intake_responses    Intake form responses tied to an item.
 *   ocd_board_activity_log   Append-only audit trail.
 *
 * Versioned via the `ocd_board_db_version` option so dbDelta runs again when
 * we ship column changes.
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class OCD_Board_Schema {

    const DB_VERSION_OPTION = 'ocd_board_db_version';
    const DB_VERSION        = '1.0.0';

    public static function table($name) {
        global $wpdb;
        return $wpdb->prefix . 'ocd_board_' . $name;
    }

    public static function maybe_install() {
        if (get_option(self::DB_VERSION_OPTION) === self::DB_VERSION) {
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

        $boards         = self::table('boards');
        $groups         = self::table('groups');
        $items          = self::table('items');
        $subitems       = self::table('subitems');
        $columns        = self::table('columns');
        $views          = self::table('views');
        $updates        = self::table('updates');
        $update_attach  = self::table('update_attachments');
        $item_files     = self::table('item_files');
        $automations    = self::table('automations');
        $intake_responses = self::table('intake_responses');
        $activity_log   = self::table('activity_log');

        $sql = [];

        $sql[] = "CREATE TABLE $boards (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            board_post_id BIGINT UNSIGNED NOT NULL,
            template_post_id BIGINT UNSIGNED DEFAULT NULL,
            owner_user_id BIGINT UNSIGNED NOT NULL,
            account_manager_user_id BIGINT UNSIGNED DEFAULT NULL,
            wc_order_id BIGINT UNSIGNED DEFAULT NULL,
            wc_subscription_id BIGINT UNSIGNED DEFAULT NULL,
            wc_product_id BIGINT UNSIGNED DEFAULT NULL,
            wc_variation_id BIGINT UNSIGNED DEFAULT NULL,
            sku VARCHAR(120) DEFAULT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'active',
            archived TINYINT(1) NOT NULL DEFAULT 0,
            archived_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY board_post (board_post_id),
            KEY owner_user_id (owner_user_id),
            KEY account_manager_user_id (account_manager_user_id),
            KEY wc_order_id (wc_order_id),
            KEY wc_subscription_id (wc_subscription_id),
            KEY sku (sku),
            KEY status (status),
            KEY archived (archived)
        ) $charset;";

        $sql[] = "CREATE TABLE $groups (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            board_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            color VARCHAR(20) DEFAULT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            collapsed TINYINT(1) NOT NULL DEFAULT 0,
            archived TINYINT(1) NOT NULL DEFAULT 0,
            cycle_label VARCHAR(80) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY board_id (board_id),
            KEY archived (archived)
        ) $charset;";

        $sql[] = "CREATE TABLE $items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            board_id BIGINT UNSIGNED NOT NULL,
            group_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'not_started',
            priority VARCHAR(20) NOT NULL DEFAULT 'normal',
            assignee_user_id BIGINT UNSIGNED DEFAULT NULL,
            due_date DATE DEFAULT NULL,
            start_date DATE DEFAULT NULL,
            description LONGTEXT DEFAULT NULL,
            column_values LONGTEXT DEFAULT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            archived TINYINT(1) NOT NULL DEFAULT 0,
            completed_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY board_id (board_id),
            KEY group_id (group_id),
            KEY assignee_user_id (assignee_user_id),
            KEY status (status),
            KEY due_date (due_date),
            KEY archived (archived)
        ) $charset;";

        $sql[] = "CREATE TABLE $subitems (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            item_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'not_started',
            assignee_user_id BIGINT UNSIGNED DEFAULT NULL,
            due_date DATE DEFAULT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            completed_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY item_id (item_id),
            KEY assignee_user_id (assignee_user_id),
            KEY status (status)
        ) $charset;";

        $sql[] = "CREATE TABLE $columns (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            board_id BIGINT UNSIGNED NOT NULL,
            slug VARCHAR(80) NOT NULL,
            label VARCHAR(255) NOT NULL,
            kind VARCHAR(40) NOT NULL DEFAULT 'text',
            options LONGTEXT DEFAULT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            visibility VARCHAR(20) NOT NULL DEFAULT 'shared',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY board_slug (board_id, slug),
            KEY kind (kind)
        ) $charset;";

        $sql[] = "CREATE TABLE $views (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            board_id BIGINT UNSIGNED NOT NULL,
            owner_user_id BIGINT UNSIGNED DEFAULT NULL,
            slug VARCHAR(80) NOT NULL,
            label VARCHAR(255) NOT NULL,
            kind VARCHAR(40) NOT NULL DEFAULT 'table',
            config LONGTEXT DEFAULT NULL,
            shared TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY board_id (board_id),
            KEY owner_user_id (owner_user_id),
            KEY kind (kind)
        ) $charset;";

        $sql[] = "CREATE TABLE $updates (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            item_id BIGINT UNSIGNED NOT NULL,
            board_id BIGINT UNSIGNED NOT NULL,
            author_user_id BIGINT UNSIGNED NOT NULL,
            author_role VARCHAR(40) NOT NULL DEFAULT 'oversee_client',
            visibility VARCHAR(20) NOT NULL DEFAULT 'shared',
            body_html LONGTEXT NOT NULL,
            body_doc LONGTEXT DEFAULT NULL,
            video_provider VARCHAR(40) DEFAULT NULL,
            video_url TEXT DEFAULT NULL,
            pinned TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY item_id (item_id),
            KEY board_id (board_id),
            KEY author_user_id (author_user_id),
            KEY visibility (visibility),
            KEY created_at (created_at)
        ) $charset;";

        $sql[] = "CREATE TABLE $update_attach (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            update_id BIGINT UNSIGNED NOT NULL,
            file_id BIGINT UNSIGNED NOT NULL,
            kind VARCHAR(20) NOT NULL DEFAULT 'file',
            sort_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY update_file (update_id, file_id),
            KEY update_id (update_id)
        ) $charset;";

        $sql[] = "CREATE TABLE $item_files (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            item_id BIGINT UNSIGNED NOT NULL,
            file_id BIGINT UNSIGNED NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY item_file (item_id, file_id),
            KEY item_id (item_id)
        ) $charset;";

        $sql[] = "CREATE TABLE $automations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            board_id BIGINT UNSIGNED NOT NULL,
            label VARCHAR(255) NOT NULL,
            trigger_kind VARCHAR(60) NOT NULL,
            trigger_config LONGTEXT DEFAULT NULL,
            action_kind VARCHAR(60) NOT NULL,
            action_config LONGTEXT DEFAULT NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            last_run_at DATETIME DEFAULT NULL,
            run_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY board_id (board_id),
            KEY trigger_kind (trigger_kind),
            KEY enabled (enabled)
        ) $charset;";

        $sql[] = "CREATE TABLE $intake_responses (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            board_id BIGINT UNSIGNED NOT NULL,
            item_id BIGINT UNSIGNED DEFAULT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            form_slug VARCHAR(120) NOT NULL,
            answers LONGTEXT NOT NULL,
            submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY board_id (board_id),
            KEY item_id (item_id),
            KEY user_id (user_id),
            KEY form_slug (form_slug)
        ) $charset;";

        $sql[] = "CREATE TABLE $activity_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            board_id BIGINT UNSIGNED DEFAULT NULL,
            item_id BIGINT UNSIGNED DEFAULT NULL,
            actor_user_id BIGINT UNSIGNED DEFAULT NULL,
            actor_kind VARCHAR(40) NOT NULL DEFAULT 'user',
            event VARCHAR(80) NOT NULL,
            payload LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY board_id (board_id),
            KEY item_id (item_id),
            KEY event (event),
            KEY created_at (created_at)
        ) $charset;";

        foreach ($sql as $statement) {
            dbDelta($statement);
        }
    }
}
