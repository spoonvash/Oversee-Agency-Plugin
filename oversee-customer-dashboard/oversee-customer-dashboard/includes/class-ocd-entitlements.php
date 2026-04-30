<?php
/**
 * Feature entitlements / dashboard add-ons. Mapped from WooCommerce products
 * AND product variations to a customer's account so the dashboard can gate
 * features by purchase.
 *
 * Mapping shape (option `ocd_product_feature_map`):
 *   [
 *     product_id => [
 *       'slug'        => 'feature-x',
 *       'label'       => 'Feature X',
 *       'variations'  => [
 *         variation_id => [
 *           'slug'  => 'feature-x-pro',         // optional variation-specific slug
 *           'label' => 'Feature X Pro',
 *           'required_steps' => [ 'brand-assets', 'access-tokens' ],
 *         ]
 *       ],
 *       'required_steps' => [ 'project-brief' ],
 *     ]
 *   ]
 *
 * "Required steps" are intake/onboarding step templates (option
 * `ocd_required_step_templates`) that are auto-attached when a matching
 * order/subscription line is paid. Sub-selections that would explode WC
 * variation counts live there as form responses keyed off the
 * subscription/order — not as fake products.
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class OCD_Entitlements {

    const STATUSES                  = ['active', 'pending', 'on-hold', 'cancelled', 'expired'];
    const PRODUCT_MAP_OPT           = 'ocd_product_feature_map';
    const REQUIRED_STEP_TEMPLATES_OPT = 'ocd_required_step_templates';

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
            'wc_variation_id'    => isset($args['wc_variation_id']) ? (int) $args['wc_variation_id'] : null,
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

    /* ---------------- Product → feature mapping (with variations) ---------------- */

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
                $row = [
                    'slug'  => $slug,
                    'label' => $label ?: ucwords(str_replace(['-', '_'], ' ', $slug)),
                ];
                // Variations: variation_id => ['slug' => ..., 'label' => ..., 'required_steps' => [..]]
                if (!empty($entry['variations']) && is_array($entry['variations'])) {
                    $clean_vars = [];
                    foreach ($entry['variations'] as $vid => $ventry) {
                        $vid = (int) $vid;
                        if ($vid <= 0) continue;
                        $vslug  = sanitize_key($ventry['slug'] ?? $slug);
                        $vlabel = sanitize_text_field($ventry['label'] ?? $row['label']);
                        $clean_var = ['slug' => $vslug, 'label' => $vlabel];
                        if (!empty($ventry['required_steps']) && is_array($ventry['required_steps'])) {
                            $clean_var['required_steps'] = array_values(array_filter(array_map('sanitize_key', $ventry['required_steps'])));
                        }
                        $clean_vars[$vid] = $clean_var;
                    }
                    if ($clean_vars) $row['variations'] = $clean_vars;
                }
                if (!empty($entry['required_steps']) && is_array($entry['required_steps'])) {
                    $row['required_steps'] = array_values(array_filter(array_map('sanitize_key', $entry['required_steps'])));
                }
                $clean[$pid] = $row;
            }
        }
        update_option(self::PRODUCT_MAP_OPT, $clean);
        return $clean;
    }

    /**
     * Resolve product (+ optional variation) into the feature definition.
     * Variation-specific entry wins over the parent entry.
     */
    public static function feature_for_product($product_id, $variation_id = 0) {
        $map = self::get_product_map();
        $entry = $map[(int) $product_id] ?? null;
        if (!$entry) return null;
        $resolved = ['slug' => $entry['slug'], 'label' => $entry['label']];
        $resolved['required_steps'] = $entry['required_steps'] ?? [];
        if ($variation_id && !empty($entry['variations'][(int) $variation_id])) {
            $v = $entry['variations'][(int) $variation_id];
            $resolved['slug']  = $v['slug'] ?? $resolved['slug'];
            $resolved['label'] = $v['label'] ?? $resolved['label'];
            // Variation required steps replace parent steps if defined.
            if (!empty($v['required_steps'])) {
                $resolved['required_steps'] = $v['required_steps'];
            }
            $resolved['variation_id'] = (int) $variation_id;
        }
        return $resolved;
    }

    /* ---------------- Required step templates ---------------- */

    /**
     * Returns the configured required-step templates.
     * Shape: [ slug => [ 'title' => ..., 'description' => ..., 'task_type' => 'client_required',
     *                    'instruction_video_url' => ..., 'requires_file' => bool ] ]
     */
    public static function get_required_step_templates() {
        $raw = get_option(self::REQUIRED_STEP_TEMPLATES_OPT, []);
        return is_array($raw) ? $raw : [];
    }

    public static function set_required_step_templates($templates) {
        $clean = [];
        if (is_array($templates)) {
            foreach ($templates as $slug => $tpl) {
                $slug = sanitize_key($slug);
                if (!$slug) continue;
                $title = sanitize_text_field($tpl['title'] ?? '');
                if (!$title) continue;
                $row = [
                    'title'         => $title,
                    'description'   => isset($tpl['description']) ? wp_kses_post((string) $tpl['description']) : '',
                    'task_type'     => 'client_required',
                    'requires_file' => !empty($tpl['requires_file']),
                ];
                if (!empty($tpl['instruction_video_url'])) {
                    $v = OCD_Instruction_Media::validate_video_url($tpl['instruction_video_url']);
                    if (!is_wp_error($v)) {
                        $row['instruction_video_url'] = $v['url'];
                        $row['instruction_video_provider'] = $v['provider'];
                    }
                }
                $clean[$slug] = $row;
            }
        }
        update_option(self::REQUIRED_STEP_TEMPLATES_OPT, $clean);
        return $clean;
    }

    /**
     * Materialises required-step tasks for a customer based on a feature
     * resolution. No-ops if the templates are missing.
     *
     * @return int Count of tasks created.
     */
    public static function materialize_required_steps($user_id, $feature, $context = []) {
        if (!is_array($feature) || empty($feature['required_steps'])) return 0;
        $tpls = self::get_required_step_templates();
        $count = 0;
        foreach ($feature['required_steps'] as $slug) {
            $tpl = $tpls[$slug] ?? null;
            if (!$tpl) continue;
            // Avoid duplicates: skip if a task with the same title already exists for the user/project.
            $existing = OCD_Tasks::all([
                'user_id'    => (int) $user_id,
                'project_id' => isset($context['project_id']) ? (int) $context['project_id'] : null,
                'per_page'   => 50,
            ]);
            $title = $tpl['title'];
            $dup = false;
            foreach ($existing as $row) {
                if (($row['title'] ?? '') === $title) { $dup = true; break; }
            }
            if ($dup) continue;
            OCD_Tasks::create([
                'user_id'              => $user_id,
                'project_id'           => $context['project_id'] ?? null,
                'title'                => $title,
                'details'              => $tpl['description'] ?? '',
                'status'               => 'needs_your_input',
                'task_type'            => 'client_required',
                'visibility'           => 'client',
                'instruction_video_url'=> $tpl['instruction_video_url'] ?? null,
                'assigned_by'          => $context['assigned_by'] ?? null,
            ]);
            $count++;
        }
        return $count;
    }

    /* ---------------- Variation/line-item extraction ---------------- */

    /**
     * Extracts variation, attributes, and meta data from a WC line item array
     * returned by the REST API. Used to preserve sub-selections at display time.
     *
     * @param array $item   WC REST API line item ('product_id', 'variation_id', 'meta_data', 'parent_name', etc.)
     * @return array        Normalised: ['product_id'=>int,'variation_id'=>int,'name'=>str,'attributes'=>[],'meta'=>[]]
     */
    public static function extract_line_item($item) {
        $out = [
            'product_id'   => isset($item['product_id']) ? (int) $item['product_id'] : 0,
            'variation_id' => isset($item['variation_id']) ? (int) $item['variation_id'] : 0,
            'name'         => isset($item['name']) ? (string) $item['name'] : '',
            'parent_name'  => isset($item['parent_name']) ? (string) $item['parent_name'] : '',
            'quantity'     => isset($item['quantity']) ? (int) $item['quantity'] : 1,
            'attributes'   => [],
            'meta'         => [],
        ];
        if (!empty($item['meta_data']) && is_array($item['meta_data'])) {
            foreach ($item['meta_data'] as $m) {
                if (!is_array($m)) continue;
                $key = isset($m['key']) ? (string) $m['key'] : (isset($m['display_key']) ? (string) $m['display_key'] : '');
                $val = isset($m['value']) ? $m['value'] : (isset($m['display_value']) ? $m['display_value'] : '');
                if ($key === '') continue;
                // Underscore-prefixed keys are private internal meta — never display them.
                if ($key[0] === '_') continue;
                // pa_<slug> keys = product attribute. Strip prefix into attributes bag.
                if (strpos($key, 'pa_') === 0) {
                    $out['attributes'][substr($key, 3)] = is_scalar($val) ? (string) $val : wp_json_encode($val);
                } else {
                    $out['meta'][$key] = is_scalar($val) ? (string) $val : wp_json_encode($val);
                }
            }
        }
        return $out;
    }
}
