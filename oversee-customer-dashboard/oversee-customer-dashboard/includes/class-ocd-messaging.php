<?php
/**
 * Two-way customer messaging. Stores threads locally and (when configured)
 * pushes inbound customer messages to the agency HighLevel sub-account so
 * Oversee staff can reply in the CRM. Outbound HighLevel messages can be
 * mirrored back via webhook (see OCD_REST_API::webhook_highlevel).
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class OCD_Messaging {

    public static function settings_defaults() {
        return [
            'agency_conversation_provider_id' => '',
            'agency_inbound_type'             => 'Custom',
        ];
    }

    /**
     * Persist a customer-authored message and try to forward it to HighLevel.
     *
     * $extra may include 'attachment_ids' (array of ocd_project_files.id values
     * the customer just uploaded). Each id must be owned by $user_id and
     * client-visible — anything else is silently dropped.
     */
    public static function customer_send($user_id, $body, $extra = []) {
        global $wpdb;
        $body = trim((string) $body);
        $attachment_ids = self::filter_attachment_ids_for_owner((array) ($extra['attachment_ids'] ?? []), (int) $user_id);
        if ($body === '' && empty($attachment_ids)) {
            return new WP_Error('ocd_empty_message', __('Message cannot be empty.', 'oversee-customer-dashboard'));
        }
        if (strlen($body) > 5000) {
            return new WP_Error('ocd_message_too_long', __('Message exceeds 5000 characters.', 'oversee-customer-dashboard'));
        }
        $user = get_userdata((int) $user_id);
        if (!$user) {
            return new WP_Error('ocd_no_user', __('User not found.', 'oversee-customer-dashboard'));
        }

        $hl_contact_id = self::resolve_hl_contact_id_for_user($user);
        $row = [
            'user_id'        => (int) $user_id,
            'direction'      => 'inbound',
            'source'         => 'dashboard',
            'body'           => $body,
            'hl_contact_id'  => $hl_contact_id,
            'hl_synced'      => 0,
            'read_by_admin'  => 0,
            'read_by_user'   => 1,
            'created_at'     => current_time('mysql', true),
        ];
        $wpdb->insert(OCD_Schema::table('messages'), $row);
        $message_id = (int) $wpdb->insert_id;

        self::attach_files_to_message($message_id, $attachment_ids);

        $sync = self::sync_inbound_to_highlevel($message_id);
        if (is_wp_error($sync)) {
            $wpdb->update(
                OCD_Schema::table('messages'),
                ['hl_sync_error' => substr($sync->get_error_message(), 0, 1000)],
                ['id' => $message_id]
            );
        }

        return self::get_message($message_id);
    }

    /**
     * Persist a staff/admin-authored reply. Outbound CRM send is gated on
     * configuration; if not configured we still record the local reply.
     *
     * $extra['attachment_ids'] — files the admin attached. Each must already
     * be stored against $user_id (owner) so the customer can preview them
     * back through the same permission-checked endpoint they use elsewhere.
     */
    public static function admin_reply($user_id, $body, $admin_id, $extra = []) {
        global $wpdb;
        $body = trim((string) $body);
        $attachment_ids = self::filter_attachment_ids_for_owner((array) ($extra['attachment_ids'] ?? []), (int) $user_id);
        if ($body === '' && empty($attachment_ids)) {
            return new WP_Error('ocd_empty_message', __('Reply cannot be empty.', 'oversee-customer-dashboard'));
        }
        if (strlen($body) > 5000) {
            return new WP_Error('ocd_message_too_long', __('Reply exceeds 5000 characters.', 'oversee-customer-dashboard'));
        }
        $user = get_userdata((int) $user_id);
        if (!$user) {
            return new WP_Error('ocd_no_user', __('User not found.', 'oversee-customer-dashboard'));
        }

        $row = [
            'user_id'        => (int) $user_id,
            'direction'      => 'outbound',
            'source'         => 'admin',
            'body'           => $body,
            'hl_contact_id'  => self::resolve_hl_contact_id_for_user($user),
            'hl_synced'      => 0,
            'read_by_admin'  => 1,
            'read_by_user'   => 0,
            'created_at'     => current_time('mysql', true),
        ];
        $wpdb->insert(OCD_Schema::table('messages'), $row);
        $message_id = (int) $wpdb->insert_id;

        self::attach_files_to_message($message_id, $attachment_ids);

        return self::get_message($message_id);
    }

    /**
     * Insert a message ingested from a HighLevel webhook (outbound from CRM
     * to customer). Idempotent on hl_message_id.
     */
    public static function ingest_from_webhook($payload) {
        global $wpdb;
        $hl_message_id = isset($payload['messageId']) ? sanitize_text_field($payload['messageId']) : '';
        if ($hl_message_id) {
            $existing = (int) $wpdb->get_var($wpdb->prepare(
                'SELECT id FROM ' . OCD_Schema::table('messages') . ' WHERE hl_message_id = %s LIMIT 1',
                $hl_message_id
            ));
            if ($existing) {
                return self::get_message($existing);
            }
        }

        $hl_contact_id = isset($payload['contactId']) ? sanitize_text_field($payload['contactId']) : '';
        $user_id = $hl_contact_id ? self::find_user_id_by_hl_contact($hl_contact_id) : 0;
        if (!$user_id) {
            return new WP_Error('ocd_no_local_user', 'No matching local user for contact.');
        }

        $body = '';
        foreach (['message', 'body', 'html', 'subject'] as $f) {
            if (!empty($payload[$f])) { $body = (string) $payload[$f]; break; }
        }
        $body = trim(wp_strip_all_tags($body));
        if ($body === '') {
            return new WP_Error('ocd_empty_payload', 'Empty webhook payload.');
        }

        $row = [
            'user_id'             => (int) $user_id,
            'direction'           => 'outbound',
            'source'              => 'highlevel',
            'body'                => $body,
            'hl_contact_id'       => $hl_contact_id,
            'hl_conversation_id'  => isset($payload['conversationId']) ? sanitize_text_field($payload['conversationId']) : null,
            'hl_message_id'       => $hl_message_id ?: null,
            'hl_synced'           => 1,
            'read_by_admin'       => 1,
            'read_by_user'        => 0,
            'created_at'          => current_time('mysql', true),
        ];
        $wpdb->insert(OCD_Schema::table('messages'), $row);
        return self::get_message((int) $wpdb->insert_id);
    }

    public static function sync_inbound_to_highlevel($message_id) {
        global $wpdb;
        $msg = self::get_message($message_id);
        if (!$msg) {
            return new WP_Error('ocd_no_message', 'Message not found.');
        }
        if (!OCD_HighLevel::is_configured()) {
            return new WP_Error('ocd_not_configured', 'HighLevel not configured; message stored locally.');
        }
        if (empty($msg['hl_contact_id'])) {
            return new WP_Error('ocd_no_contact', 'No HighLevel contact resolved for this user.');
        }

        $opts = get_option(OCD_Settings::OPTION_KEY, OCD_Settings::defaults());
        $provider = isset($opts['agency_conversation_provider_id']) ? $opts['agency_conversation_provider_id'] : '';
        $type     = isset($opts['agency_inbound_type']) && $opts['agency_inbound_type'] ? $opts['agency_inbound_type'] : 'Custom';

        $args = [
            'contact_id' => $msg['hl_contact_id'],
            'message'    => $msg['body'],
            'type'       => $type,
            'direction'  => 'inbound',
            'alt_id'     => 'ocd-' . (int) $msg['id'],
        ];
        if ($provider) {
            $args['conversation_provider_id'] = $provider;
        }
        $resp = OCD_HighLevel::post_inbound_message($args);
        if (is_wp_error($resp)) {
            return $resp;
        }

        $update = [
            'hl_synced' => 1,
            'hl_sync_error' => null,
        ];
        if (!empty($resp['conversationId'])) $update['hl_conversation_id'] = sanitize_text_field($resp['conversationId']);
        if (!empty($resp['messageId']))      $update['hl_message_id']      = sanitize_text_field($resp['messageId']);
        $wpdb->update(OCD_Schema::table('messages'), $update, ['id' => (int) $msg['id']]);
        return $resp;
    }

    public static function get_message($id) {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . OCD_Schema::table('messages') . ' WHERE id = %d', (int) $id),
            ARRAY_A
        );
        if (!$row) return null;
        $row['attachments'] = self::attachments_for_message((int) $row['id']);
        return $row;
    }

    public static function thread_for_user($user_id, $limit = 100) {
        global $wpdb;
        $limit = max(1, min(500, (int) $limit));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . OCD_Schema::table('messages') . ' WHERE user_id = %d ORDER BY created_at ASC, id ASC LIMIT %d',
                (int) $user_id,
                $limit
            ),
            ARRAY_A
        ) ?: [];
        if (!$rows) return [];
        $ids = array_map(function ($r) { return (int) $r['id']; }, $rows);
        $by_msg = self::attachments_indexed_by_message($ids);
        foreach ($rows as &$r) {
            $r['attachments'] = $by_msg[(int) $r['id']] ?? [];
        }
        return $rows;
    }

    /* ---------------- Attachments ---------------- */

    public static function filter_attachment_ids_for_owner($ids, $owner_user_id) {
        $owner_user_id = (int) $owner_user_id;
        $clean = [];
        foreach ((array) $ids as $raw) {
            $id = (int) $raw;
            if ($id <= 0) continue;
            if (!class_exists('OCD_Project_Files')) continue;
            $file = OCD_Project_Files::get($id);
            if (!$file) continue;
            // The file MUST be owned by the message's user. We don't trust
            // the caller — even an admin attaches "to the customer", and
            // the file row carries owner_user_id = customer.
            if ((int) $file['owner_user_id'] !== $owner_user_id) continue;
            // Force client-visible: messages are shared with the customer.
            if (($file['visibility'] ?? '') !== 'client') continue;
            $clean[] = $id;
        }
        return array_values(array_unique($clean));
    }

    public static function attach_files_to_message($message_id, $file_ids) {
        global $wpdb;
        $message_id = (int) $message_id;
        $file_ids = array_values(array_unique(array_map('intval', (array) $file_ids)));
        if ($message_id <= 0 || empty($file_ids)) return;
        $tbl = OCD_Schema::table('message_attachments');
        $now = current_time('mysql', true);
        foreach ($file_ids as $fid) {
            $wpdb->insert($tbl, [
                'message_id' => $message_id,
                'file_id'    => (int) $fid,
                'created_at' => $now,
            ]);
        }
    }

    public static function attachments_for_message($message_id) {
        global $wpdb;
        $message_id = (int) $message_id;
        if ($message_id <= 0 || !class_exists('OCD_Project_Files')) return [];
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT a.file_id FROM ' . OCD_Schema::table('message_attachments') . ' a '
            . 'WHERE a.message_id = %d ORDER BY a.id ASC',
            $message_id
        ), ARRAY_A) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $f = OCD_Project_Files::get((int) $r['file_id']);
            if (!$f) continue;
            $out[] = OCD_Project_Files::present($f, false);
        }
        return $out;
    }

    public static function attachments_indexed_by_message($message_ids) {
        global $wpdb;
        $message_ids = array_values(array_unique(array_map('intval', (array) $message_ids)));
        if (empty($message_ids) || !class_exists('OCD_Project_Files')) return [];
        $placeholders = implode(',', array_fill(0, count($message_ids), '%d'));
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT message_id, file_id FROM ' . OCD_Schema::table('message_attachments')
            . ' WHERE message_id IN (' . $placeholders . ') ORDER BY id ASC',
            $message_ids
        ), ARRAY_A) ?: [];
        $by_msg = [];
        foreach ($rows as $r) {
            $f = OCD_Project_Files::get((int) $r['file_id']);
            if (!$f) continue;
            $by_msg[(int) $r['message_id']][] = OCD_Project_Files::present($f, false);
        }
        return $by_msg;
    }

    public static function admin_inbox($args = []) {
        global $wpdb;
        $per_page = max(1, min(100, (int) ($args['per_page'] ?? 25)));
        $sql = 'SELECT m.user_id, MAX(m.created_at) AS last_at, '
             . 'SUM(CASE WHEN direction = "inbound" AND read_by_admin = 0 THEN 1 ELSE 0 END) AS unread, '
             . 'COUNT(*) AS total '
             . 'FROM ' . OCD_Schema::table('messages') . ' m '
             . 'GROUP BY m.user_id ORDER BY last_at DESC LIMIT %d';
        $rows = $wpdb->get_results($wpdb->prepare($sql, $per_page), ARRAY_A) ?: [];
        $out  = [];
        foreach ($rows as $r) {
            $u = get_userdata((int) $r['user_id']);
            if (!$u) continue;
            $last = $wpdb->get_row($wpdb->prepare(
                'SELECT body, direction FROM ' . OCD_Schema::table('messages')
                . ' WHERE user_id = %d ORDER BY created_at DESC, id DESC LIMIT 1',
                (int) $r['user_id']
            ), ARRAY_A);
            $out[] = [
                'user_id'      => (int) $r['user_id'],
                'name'         => $u->display_name,
                'email'        => $u->user_email,
                'last_at'      => $r['last_at'],
                'unread'       => (int) $r['unread'],
                'total'        => (int) $r['total'],
                'last_preview' => $last ? mb_substr((string) $last['body'], 0, 140) : '',
                'last_direction' => $last ? $last['direction'] : null,
            ];
        }
        return $out;
    }

    public static function mark_thread_read($user_id, $reader = 'admin') {
        global $wpdb;
        $col = $reader === 'admin' ? 'read_by_admin' : 'read_by_user';
        return $wpdb->update(
            OCD_Schema::table('messages'),
            [$col => 1],
            ['user_id' => (int) $user_id, $col => 0]
        );
    }

    public static function resolve_hl_contact_id_for_user($user) {
        if (!$user || !OCD_HighLevel::is_configured()) return '';
        $cached = get_user_meta($user->ID, '_ocd_hl_contact_id', true);
        if ($cached) return $cached;
        $contact = OCD_HighLevel::search_contact_by_email($user->user_email);
        if (is_wp_error($contact) || !$contact || empty($contact['id'])) return '';
        update_user_meta($user->ID, '_ocd_hl_contact_id', $contact['id']);
        return $contact['id'];
    }

    public static function find_user_id_by_hl_contact($hl_contact_id) {
        global $wpdb;
        $cached = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
            '_ocd_hl_contact_id',
            $hl_contact_id
        ));
        return (int) $cached;
    }
}
