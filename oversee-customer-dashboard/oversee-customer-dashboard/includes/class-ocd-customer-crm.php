<?php
/**
 * Customer-owned CRM connections. Storage is intentionally separate from the
 * agency CRM settings so customer credentials never mix with Oversee's keys.
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class OCD_Customer_CRM {

    const PROVIDERS = ['highlevel', 'hubspot', 'pipedrive', 'salesforce', 'other'];

    public static function get_for_user($user_id, $provider = 'highlevel') {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . OCD_Schema::table('customer_crm')
                . ' WHERE user_id = %d AND provider = %s LIMIT 1',
                (int) $user_id,
                sanitize_key($provider)
            ),
            ARRAY_A
        );
        if (!$row) return null;
        // Never expose tokens to API consumers — strip on read.
        unset($row['access_token'], $row['refresh_token']);
        return $row;
    }

    public static function status_for_user($user_id) {
        $row = self::get_for_user($user_id);
        if (!$row) return ['connected' => false, 'provider' => null];
        return [
            'connected'        => empty($row['disconnected_at']),
            'provider'         => $row['provider'],
            'label'            => $row['label'],
            'location_id'      => $row['location_id'],
            'connected_at'     => $row['connected_at'],
            'token_expires_at' => $row['token_expires_at'],
        ];
    }

    public static function connect($user_id, $data) {
        global $wpdb;
        $provider = sanitize_key($data['provider'] ?? 'highlevel');
        if (!in_array($provider, self::PROVIDERS, true)) {
            return new WP_Error('ocd_invalid_provider', 'Unsupported CRM provider.');
        }
        // Tokens stored encrypted-at-rest is the operator's responsibility (e.g.
        // via WP_CRYPT or KMS). At minimum we keep them out of meta tables.
        $row = [
            'user_id'         => (int) $user_id,
            'provider'        => $provider,
            'label'           => sanitize_text_field($data['label'] ?? ucfirst($provider)),
            'location_id'     => isset($data['location_id']) ? sanitize_text_field($data['location_id']) : null,
            'access_token'    => isset($data['access_token']) ? (string) $data['access_token'] : null,
            'refresh_token'   => isset($data['refresh_token']) ? (string) $data['refresh_token'] : null,
            'token_expires_at'=> isset($data['token_expires_at']) ? sanitize_text_field($data['token_expires_at']) : null,
            'connected_at'    => current_time('mysql', true),
            'disconnected_at' => null,
            'meta'            => isset($data['meta']) ? wp_json_encode($data['meta']) : null,
        ];
        $existing = $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . OCD_Schema::table('customer_crm') . ' WHERE user_id = %d AND provider = %s LIMIT 1',
            (int) $user_id, $provider
        ));
        if ($existing) {
            $wpdb->update(OCD_Schema::table('customer_crm'), $row, ['id' => (int) $existing]);
        } else {
            $wpdb->insert(OCD_Schema::table('customer_crm'), $row);
        }
        return self::get_for_user($user_id, $provider);
    }

    public static function disconnect($user_id, $provider = 'highlevel') {
        global $wpdb;
        return $wpdb->update(
            OCD_Schema::table('customer_crm'),
            [
                'access_token'    => null,
                'refresh_token'   => null,
                'token_expires_at'=> null,
                'disconnected_at' => current_time('mysql', true),
            ],
            ['user_id' => (int) $user_id, 'provider' => sanitize_key($provider)]
        );
    }
}
