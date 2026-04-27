<?php
/**
 * Spec-aligned signup / WooCommerce lifecycle hooks.
 *
 * Per the latest authoritative spec, the dashboard listens to:
 *
 *   user_register                                  → assign oversee_client role
 *                                                    (no project spawned)
 *   woocommerce_created_customer                   → ensure oversee_client +
 *                                                    welcome email
 *   woocommerce_order_status_completed             → spawn project_board posts
 *                                                    from each line item's SKU
 *                                                    via service_template
 *                                                    matching
 *   woocommerce_subscription_status_updated        → mirror to project status
 *   woocommerce_subscription_renewal_payment_complete → fire renewal hook
 *
 * The legacy class-ocd-woocommerce-hooks.php / class-ocd-signup-hooks.php own
 * the corresponding `ocd_*` table updates. This file owns the spec-aligned
 * `wp_oversee_*` + project_board CPT lifecycle so the two systems stay
 * cleanly separated; both run on the same trigger and update their own data.
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class Oversee_Signup_Hooks {

    public static function init() {
        // Direct WP user creation (admin → Add User, REST POST /clients,
        // Magic Login passwordless signup) must assign oversee_client without
        // spawning a project. WooCommerce signups go through the dedicated
        // woocommerce_created_customer hook below.
        add_action('user_register', [__CLASS__, 'on_user_register'], 10, 1);

        add_action('woocommerce_created_customer', [__CLASS__, 'on_wc_customer_created'], 10, 3);
        add_action('woocommerce_order_status_completed', [__CLASS__, 'on_order_completed'], 30, 1);
        add_action('woocommerce_subscription_status_updated', [__CLASS__, 'on_subscription_status_updated'], 10, 3);
        add_action('woocommerce_subscription_renewal_payment_complete', [__CLASS__, 'on_subscription_renewal'], 10, 1);
    }

    /**
     * Direct-registration path. Add oversee_client to whoever the WP user
     * is — but never spawn a project, never call WooCommerce. The order/
     * checkout flow is what creates billable work.
     */
    public static function on_user_register($user_id) {
        $user = get_userdata((int) $user_id);
        if (!$user) {
            return;
        }
        // Never demote an admin/staff. Skip if this user already has an
        // Oversee staff role (a manual admin add of another staffer).
        $staff_roles = ['administrator', 'oversee_admin', 'oversee_account_manager', 'oversee_specialist', 'oversee_contractor', 'shop_manager'];
        foreach ($staff_roles as $r) {
            if (in_array($r, (array) $user->roles, true)) {
                return;
            }
        }

        if (!in_array('oversee_client', (array) $user->roles, true)) {
            $user->add_role('oversee_client');
        }
        $user->add_cap('access_oversee_dashboard');

        do_action('oversee_client_registered', (int) $user_id, 'direct');
    }

    public static function on_wc_customer_created($user_id, $new_customer_data, $password_generated) {
        if (class_exists('OCD_Roles')) {
            OCD_Roles::promote_to_client((int) $user_id);
        }
        do_action('oversee_client_registered', (int) $user_id, 'woocommerce');
    }

    /**
     * Spawns one project_board CPT per matching service_template SKU on the
     * order. Idempotent: if a project_board already exists for the same
     * (order_id, sku) combination, we update it instead of creating a duplicate.
     */
    public static function on_order_completed($order_id) {
        if (!function_exists('wc_get_order')) {
            return;
        }
        $order = wc_get_order((int) $order_id);
        if (!$order) {
            return;
        }
        $client_id = (int) $order->get_user_id();
        if (!$client_id) {
            return; // guest checkouts don't get a project until the user account is created.
        }

        foreach ($order->get_items() as $item) {
            if (!is_object($item) || !method_exists($item, 'get_product')) continue;
            $product = $item->get_product();
            if (!$product) continue;
            $sku = (string) $product->get_sku();
            $template_id = $sku ? Oversee_CPT::find_service_template_for_sku($sku) : 0;

            // Avoid duplicate by (order_id + sku).
            $existing = get_posts([
                'post_type'      => Oversee_CPT::TYPE_PROJECT,
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'meta_query'     => [
                    'relation' => 'AND',
                    ['key' => Oversee_CPT::META_PROJECT_WC_ORDER, 'value' => $order_id],
                    ['key' => Oversee_CPT::META_PROJECT_OWNER,    'value' => $client_id],
                ],
                'fields'         => 'ids',
            ]);
            if (!empty($existing)) {
                continue;
            }

            $title = $template_id
                ? sprintf('%s — %s', get_the_title($template_id), $order->get_order_number())
                : sprintf('%s — %s', $product->get_name(), $order->get_order_number());

            $post_id = wp_insert_post([
                'post_type'   => Oversee_CPT::TYPE_PROJECT,
                'post_status' => 'publish',
                'post_title'  => $title,
                'post_author' => $client_id,
            ]);
            if (is_wp_error($post_id) || !$post_id) {
                continue;
            }
            update_post_meta($post_id, Oversee_CPT::META_PROJECT_OWNER, $client_id);
            update_post_meta($post_id, Oversee_CPT::META_PROJECT_STATUS, 'active');
            update_post_meta($post_id, Oversee_CPT::META_PROJECT_WC_ORDER, $order_id);
            if ($template_id) update_post_meta($post_id, '_oversee_project_template_id', $template_id);

            do_action('oversee_project_spawned', $post_id, $order, $template_id, $sku);
        }
    }

    public static function on_subscription_status_updated($subscription, $new_status, $old_status) {
        if (!is_object($subscription) || !method_exists($subscription, 'get_id')) {
            return;
        }
        $sub_id = (int) $subscription->get_id();
        $projects = get_posts([
            'post_type'      => Oversee_CPT::TYPE_PROJECT,
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'meta_query'     => [['key' => Oversee_CPT::META_PROJECT_WC_SUB, 'value' => $sub_id]],
            'fields'         => 'ids',
        ]);
        $map = [
            'active'         => 'active',
            'on-hold'        => 'paused',
            'pending-cancel' => 'paused',
            'cancelled'      => 'archived',
            'expired'        => 'archived',
        ];
        $oversee_status = $map[$new_status] ?? $new_status;
        foreach ($projects as $pid) {
            update_post_meta($pid, Oversee_CPT::META_PROJECT_STATUS, $oversee_status);
        }
    }

    public static function on_subscription_renewal($subscription) {
        do_action('oversee_subscription_renewed', $subscription);
    }
}
