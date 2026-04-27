<?php
/**
 * Spec-aligned wp_oversee_* schema (Assembly-style WordPress-native dashboard).
 *
 * Tables (per latest authoritative spec):
 *
 *   wp_oversee_tasks
 *   wp_oversee_task_comments
 *   wp_oversee_task_attachments
 *   wp_oversee_form_responses
 *   wp_oversee_contracts
 *   wp_oversee_messages
 *   wp_oversee_conversations
 *   wp_oversee_files
 *   wp_oversee_automations
 *   wp_oversee_activity_log
 *   wp_oversee_client_notes
 *   wp_oversee_payment_links
 *
 * The legacy `ocd_*` tables are retained for backwards compatibility; the new
 * tables are the source of truth going forward. Schema is versioned on the
 * `oversee_db_version` option so dbDelta runs again when columns change.
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class Oversee_Schema {

    const DB_VERSION_OPTION = 'oversee_db_version';
    const DB_VERSION        = '2.0.0';

    public static function table($name) {
        global $wpdb;
        return $wpdb->prefix . 'oversee_' . $name;
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

        $tasks            = self::table('tasks');
        $task_comments    = self::table('task_comments');
        $task_attachments = self::table('task_attachments');
        $form_responses   = self::table('form_responses');
        $contracts        = self::table('contracts');
        $messages         = self::table('messages');
        $conversations    = self::table('conversations');
        $files            = self::table('files');
        $automations      = self::table('automations');
        $activity_log     = self::table('activity_log');
        $client_notes     = self::table('client_notes');
        $payment_links    = self::table('payment_links');

        $sql = [];

        $sql[] = "CREATE TABLE $tasks (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_user_id BIGINT UNSIGNED NOT NULL,
            project_post_id BIGINT UNSIGNED DEFAULT NULL,
            assignee_user_id BIGINT UNSIGNED DEFAULT NULL,
            assigner_user_id BIGINT UNSIGNED DEFAULT NULL,
            title VARCHAR(255) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'todo',
            priority VARCHAR(20) NOT NULL DEFAULT 'normal',
            visibility VARCHAR(20) NOT NULL DEFAULT 'shared',
            due_date DATETIME DEFAULT NULL,
            started_at DATETIME DEFAULT NULL,
            completed_at DATETIME DEFAULT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY client_user_id (client_user_id),
            KEY project_post_id (project_post_id),
            KEY assignee_user_id (assignee_user_id),
            KEY status (status),
            KEY due_date (due_date)
        ) $charset;";

        $sql[] = "CREATE TABLE $task_comments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            task_id BIGINT UNSIGNED NOT NULL,
            author_user_id BIGINT UNSIGNED NOT NULL,
            visibility VARCHAR(20) NOT NULL DEFAULT 'shared',
            body LONGTEXT NOT NULL,
            body_doc LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY task_id (task_id),
            KEY author_user_id (author_user_id)
        ) $charset;";

        $sql[] = "CREATE TABLE $task_attachments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            task_id BIGINT UNSIGNED NOT NULL,
            file_id BIGINT UNSIGNED NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY task_file (task_id, file_id),
            KEY task_id (task_id)
        ) $charset;";

        $sql[] = "CREATE TABLE $form_responses (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            form_post_id BIGINT UNSIGNED NOT NULL,
            client_user_id BIGINT UNSIGNED NOT NULL,
            project_post_id BIGINT UNSIGNED DEFAULT NULL,
            answers LONGTEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'submitted',
            submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY form_post_id (form_post_id),
            KEY client_user_id (client_user_id),
            KEY project_post_id (project_post_id)
        ) $charset;";

        $sql[] = "CREATE TABLE $contracts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            template_post_id BIGINT UNSIGNED DEFAULT NULL,
            client_user_id BIGINT UNSIGNED NOT NULL,
            project_post_id BIGINT UNSIGNED DEFAULT NULL,
            title VARCHAR(255) NOT NULL,
            body_html LONGTEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            sent_at DATETIME DEFAULT NULL,
            signed_at DATETIME DEFAULT NULL,
            signature_name VARCHAR(255) DEFAULT NULL,
            signature_ip VARCHAR(64) DEFAULT NULL,
            signature_data LONGTEXT DEFAULT NULL,
            countersigned_by BIGINT UNSIGNED DEFAULT NULL,
            countersigned_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY client_user_id (client_user_id),
            KEY status (status)
        ) $charset;";

        $sql[] = "CREATE TABLE $conversations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_user_id BIGINT UNSIGNED NOT NULL,
            project_post_id BIGINT UNSIGNED DEFAULT NULL,
            title VARCHAR(255) DEFAULT NULL,
            last_message_at DATETIME DEFAULT NULL,
            unread_client INT UNSIGNED NOT NULL DEFAULT 0,
            unread_staff INT UNSIGNED NOT NULL DEFAULT 0,
            archived TINYINT(1) NOT NULL DEFAULT 0,
            hl_conversation_id VARCHAR(64) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY client_user_id (client_user_id),
            KEY project_post_id (project_post_id),
            KEY hl_conversation_id (hl_conversation_id)
        ) $charset;";

        $sql[] = "CREATE TABLE $messages (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            conversation_id BIGINT UNSIGNED NOT NULL,
            sender_user_id BIGINT UNSIGNED NOT NULL,
            sender_role VARCHAR(40) NOT NULL DEFAULT 'oversee_client',
            body LONGTEXT NOT NULL,
            body_doc LONGTEXT DEFAULT NULL,
            hl_message_id VARCHAR(64) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id),
            KEY sender_user_id (sender_user_id)
        ) $charset;";

        $sql[] = "CREATE TABLE $files (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_user_id BIGINT UNSIGNED NOT NULL,
            uploader_user_id BIGINT UNSIGNED NOT NULL,
            project_post_id BIGINT UNSIGNED DEFAULT NULL,
            file_name VARCHAR(255) NOT NULL,
            stored_path VARCHAR(512) NOT NULL,
            mime_type VARCHAR(120) NOT NULL,
            size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
            visibility VARCHAR(20) NOT NULL DEFAULT 'shared',
            approval_status VARCHAR(20) NOT NULL DEFAULT 'not_required',
            approval_comment LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY client_user_id (client_user_id),
            KEY project_post_id (project_post_id),
            KEY visibility (visibility)
        ) $charset;";

        $sql[] = "CREATE TABLE $automations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            label VARCHAR(255) NOT NULL,
            trigger_kind VARCHAR(60) NOT NULL,
            trigger_config LONGTEXT DEFAULT NULL,
            action_kind VARCHAR(60) NOT NULL,
            action_config LONGTEXT DEFAULT NULL,
            scope VARCHAR(40) NOT NULL DEFAULT 'global',
            scope_id BIGINT UNSIGNED DEFAULT NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            last_run_at DATETIME DEFAULT NULL,
            run_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY trigger_kind (trigger_kind),
            KEY enabled (enabled),
            KEY scope (scope, scope_id)
        ) $charset;";

        $sql[] = "CREATE TABLE $activity_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            actor_user_id BIGINT UNSIGNED DEFAULT NULL,
            actor_role VARCHAR(40) NOT NULL DEFAULT 'oversee_client',
            entity_type VARCHAR(60) NOT NULL,
            entity_id BIGINT UNSIGNED DEFAULT NULL,
            event VARCHAR(80) NOT NULL,
            payload LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY entity (entity_type, entity_id),
            KEY actor_user_id (actor_user_id),
            KEY event (event),
            KEY created_at (created_at)
        ) $charset;";

        $sql[] = "CREATE TABLE $client_notes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_user_id BIGINT UNSIGNED NOT NULL,
            author_user_id BIGINT UNSIGNED NOT NULL,
            body LONGTEXT NOT NULL,
            body_doc LONGTEXT DEFAULT NULL,
            visibility VARCHAR(20) NOT NULL DEFAULT 'internal',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY client_user_id (client_user_id),
            KEY author_user_id (author_user_id)
        ) $charset;";

        $sql[] = "CREATE TABLE $payment_links (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_user_id BIGINT UNSIGNED NOT NULL,
            wc_order_id BIGINT UNSIGNED DEFAULT NULL,
            wc_product_id BIGINT UNSIGNED DEFAULT NULL,
            label VARCHAR(255) NOT NULL,
            amount_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
            currency VARCHAR(8) NOT NULL DEFAULT 'USD',
            url TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            paid_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY client_user_id (client_user_id),
            KEY status (status)
        ) $charset;";

        foreach ($sql as $statement) {
            dbDelta($statement);
        }
    }
}
