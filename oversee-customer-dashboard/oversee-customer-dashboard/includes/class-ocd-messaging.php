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
     */
    public static function customer_send($user_id, $body, $extra = []) {
        global $wpdb;
        $body = trim((string) $body);
        if ($body === '') {
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
     */
    public static function admin_reply($user_id, $body, $admin_id) {
        global $wpdb;
        $body = trim((string) $body);
        if ($body === '') {
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
        return $row ?: null;
    }

    public static function thread_for_user($user_id, $limit = 100) {
        global $wpdb;
        $limit = max(1, min(500, (int) $limit));
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . OCD_Schema::table('messages') . ' WHERE user_id = %d ORDER BY created_at ASC, id ASC LIMIT %d',
                (int) $user_id,
                $limit
            ),
            ARRAY_A
        );
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
