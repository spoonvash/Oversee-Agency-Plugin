<?php
/**
 * Feature entitlements / dashboard add-ons. Mapped from WooCommerce products
 * to a customer's account so the dashboard can gate features by purchase.
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class OCD_Entitlements {

    const STATUSES        = ['active', 'pending', 'on-hold', 'cancelled', 'expired'];
    const PRODUCT_MAP_OPT = 'ocd_product_feature_map';

    public static function grant($user_id, $slug, $args = []) {
        global $wpdb;
        $user_id = (int) $user_id;
        $slug    = sanitize_key($slug);
        if (!$user_id || !$slug) {
            return new WP_Error('ocd_invalid_entitlement', 'user_id and slug required.');
        }
        $existing = self::find_by_user_slug($user_id, $slug);
        $row = [
            'user_id'            => $user_id,
            'slug'               => $slug,
            'label'              => sanitize_text_field($args['label'] ?? ucwords(str_replace(['-', '_'], ' ', $slug))),
            'wc_product_id'      => isset($args['wc_product_id']) ? (int) $args['wc_product_id'] : null,
            'wc_order_id'        => isset($args['wc_order_id']) ? (int) $args['wc_order_id'] : null,
            'wc_subscription_id' => isset($args['wc_subscription_id']) ? (int) $args['wc_subscription_id'] : null,
            'status'             => 'active',
            'granted_at'         => current_time('mysql', true),
            'revoked_at'         => null,
            'meta'               => isset($args['meta']) ? wp_json_encode($args['meta']) : null,
        ];
        if ($existing) {
            $wpdb->update(OCD_Schema::table('entitlements'), $row, ['id' => (int) $existing['id']]);
            return self::get((int) $existing['id']);
        }
        $wpdb->insert(OCD_Schema::table('entitlements'), $row);
        return self::get((int) $wpdb->insert_id);
    }

    public static function set_status($user_id, $slug, $status) {
        global $wpdb;
        if (!in_array($status, self::STATUSES, true)) {
            return new WP_Error('ocd_invalid_status', 'Invalid entitlement status.');
        }
        $existing = self::find_by_user_slug($user_id, $slug);
        if (!$existing) return new WP_Error('ocd_no_entitlement', 'Not found.');

        $update = ['status' => $status];
        if (in_array($status, ['cancelled', 'expired'], true)) {
            $update['revoked_at'] = current_time('mysql', true);
        }
        $wpdb->update(OCD_Schema::table('entitlements'), $update, ['id' => (int) $existing['id']]);
        return self::get((int) $existing['id']);
    }

    public static function find_by_user_slug($user_id, $slug) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . OCD_Schema::table('entitlements')
                . ' WHERE user_id = %d AND slug = %s LIMIT 1',
                (int) $user_id,
                sanitize_key($slug)
            ),
            ARRAY_A
        ) ?: null;
    }

    public static function get($id) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . OCD_Schema::table('entitlements') . ' WHERE id = %d', (int) $id),
            ARRAY_A
        ) ?: null;
    }

    public static function for_user($user_id) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . OCD_Schema::table('entitlements')
                . ' WHERE user_id = %d ORDER BY granted_at DESC',
                (int) $user_id
            ),
            ARRAY_A
        ) ?: [];
    }

    public static function user_has($user_id, $slug) {
        $row = self::find_by_user_slug($user_id, $slug);
        return $row && $row['status'] === 'active';
    }

    public static function delete($id) {
        global $wpdb;
        return $wpdb->delete(OCD_Schema::table('entitlements'), ['id' => (int) $id]);
    }

    /* ---------------- Product → feature mapping ---------------- */

    /**
     * Returns the product → feature map.
     * Shape: [ product_id => [ 'slug' => 'feature-x', 'label' => 'Feature X' ] ]
     */
    public static function get_product_map() {
        $raw = get_option(self::PRODUCT_MAP_OPT, []);
        return is_array($raw) ? $raw : [];
    }

    public static function set_product_map($map) {
        $clean = [];
        if (is_array($map)) {
            foreach ($map as $product_id => $entry) {
                $pid = (int) $product_id;
                if ($pid <= 0) continue;
                $slug  = sanitize_key($entry['slug'] ?? '');
                $label = sanitize_text_field($entry['label'] ?? '');
                if (!$slug) continue;
                $clean[$pid] = ['slug' => $slug, 'label' => $label ?: ucwords(str_replace(['-', '_'], ' ', $slug))];
            }
        }
        update_option(self::PRODUCT_MAP_OPT, $clean);
        return $clean;
    }

    public static function feature_for_product($product_id) {
        $map = self::get_product_map();
        return $map[(int) $product_id] ?? null;
    }
}
