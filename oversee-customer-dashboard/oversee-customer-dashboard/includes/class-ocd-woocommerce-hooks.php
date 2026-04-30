<?php
/**
 * WooCommerce hooks: grant dashboard access + entitlements + project records
 * when an order completes/processes, and on subscription status changes.
 *
 * Variation-aware:
 *   - reads `$item->get_variation_id()` and resolves the feature against the
 *     parent product OR the specific variation (variation overrides parent).
 *   - persists `wc_variation_id` on the entitlement and project rows.
 *
 * Required steps:
 *   - if the resolved feature has `required_steps`, those step templates are
 *     materialised as `client_required` tasks tied to the new project.
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class OCD_WooCommerce_Hooks {

    const DASHBOARD_CAP = 'access_oversee_dashboard';

    public static function init() {
        add_action('woocommerce_order_status_processing', [__CLASS__, 'handle_order_paid'], 10, 1);
        add_action('woocommerce_order_status_completed',  [__CLASS__, 'handle_order_paid'], 10, 1);

        add_action('woocommerce_subscription_status_active',          [__CLASS__, 'handle_sub_active'], 10, 1);
        add_action('woocommerce_subscription_status_on-hold',         [__CLASS__, 'handle_sub_on_hold'], 10, 1);
        add_action('woocommerce_subscription_status_pending-cancel',  [__CLASS__, 'handle_sub_pending_cancel'], 10, 1);
        add_action('woocommerce_subscription_status_cancelled',       [__CLASS__, 'handle_sub_cancelled'], 10, 1);
        add_action('woocommerce_subscription_status_expired',         [__CLASS__, 'handle_sub_cancelled'], 10, 1);
    }

    public static function ensure_dashboard_capability($user_id) {
        $user = get_userdata((int) $user_id);
        if (!$user) return;
        if (!user_can($user, self::DASHBOARD_CAP)) {
            $user->add_cap(self::DASHBOARD_CAP);
        }
    }

    public static function handle_order_paid($order_id) {
        if (!function_exists('wc_get_order')) return;
        $order = wc_get_order((int) $order_id);
        if (!$order) return;
        $user_id = (int) $order->get_user_id();
        if (!$user_id) return;

        self::ensure_dashboard_capability($user_id);

        foreach ($order->get_items() as $item) {
            $product_id   = method_exists($item, 'get_product_id')   ? (int) $item->get_product_id()   : 0;
            $variation_id = method_exists($item, 'get_variation_id') ? (int) $item->get_variation_id() : 0;
            $feature = OCD_Entitlements::feature_for_product($product_id, $variation_id);
            if ($feature) {
                OCD_Entitlements::grant($user_id, $feature['slug'], [
                    'label'           => $feature['label'],
                    'wc_product_id'   => $product_id,
                    'wc_variation_id' => $variation_id ?: null,
                    'wc_order_id'     => (int) $order_id,
                ]);
            }
            $project_id = self::maybe_create_project_for_purchase(
                $user_id,
                (int) $order_id,
                0,
                $product_id,
                $variation_id,
                method_exists($item, 'get_name') ? $item->get_name() : ''
            );
            if ($feature && $project_id) {
                OCD_Entitlements::materialize_required_steps($user_id, $feature, [
                    'project_id'  => $project_id,
                    'assigned_by' => 0,
                ]);
            }
        }
        do_action('ocd_order_paid_processed', $order_id, $user_id);
    }

    public static function handle_sub_active($subscription) {
        $user_id = (int) $subscription->get_user_id();
        if (!$user_id) return;
        self::ensure_dashboard_capability($user_id);

        $sub_id = method_exists($subscription, 'get_id') ? (int) $subscription->get_id() : 0;
        foreach ($subscription->get_items() as $item) {
            $product_id   = method_exists($item, 'get_product_id')   ? (int) $item->get_product_id()   : 0;
            $variation_id = method_exists($item, 'get_variation_id') ? (int) $item->get_variation_id() : 0;
            $feature = OCD_Entitlements::feature_for_product($product_id, $variation_id);
            if ($feature) {
                OCD_Entitlements::grant($user_id, $feature['slug'], [
                    'label'              => $feature['label'],
                    'wc_product_id'      => $product_id,
                    'wc_variation_id'    => $variation_id ?: null,
                    'wc_subscription_id' => $sub_id,
                ]);
            }
            $project_id = self::maybe_create_project_for_purchase(
                $user_id,
                0,
                $sub_id,
                $product_id,
                $variation_id,
                method_exists($item, 'get_name') ? $item->get_name() : ''
            );
            if ($feature && $project_id) {
                OCD_Entitlements::materialize_required_steps($user_id, $feature, [
                    'project_id'  => $project_id,
                    'assigned_by' => 0,
                ]);
            }
        }
    }

    public static function handle_sub_on_hold($subscription) {
        self::set_sub_status($subscription, 'on-hold');
    }

    public static function handle_sub_pending_cancel($subscription) {
        self::set_sub_status($subscription, 'pending');
    }

    public static function handle_sub_cancelled($subscription) {
        self::set_sub_status($subscription, 'cancelled');
    }

    private static function set_sub_status($subscription, $status) {
        $user_id = (int) $subscription->get_user_id();
        if (!$user_id) return;
        foreach ($subscription->get_items() as $item) {
            $product_id   = method_exists($item, 'get_product_id')   ? (int) $item->get_product_id()   : 0;
            $variation_id = method_exists($item, 'get_variation_id') ? (int) $item->get_variation_id() : 0;
            $feature = OCD_Entitlements::feature_for_product($product_id, $variation_id);
            if ($feature) {
                OCD_Entitlements::set_status($user_id, $feature['slug'], $status);
            }
        }
    }

    private static function maybe_create_project_for_purchase($user_id, $order_id, $subscription_id, $product_id, $variation_id, $name) {
        global $wpdb;
        $where = 'user_id = %d AND wc_product_id = %d';
        $params = [(int) $user_id, (int) $product_id];
        if ($order_id) {
            $where .= ' AND wc_order_id = %d';
            $params[] = (int) $order_id;
        }
        if ($subscription_id) {
            $where .= ' AND wc_subscription_id = %d';
            $params[] = (int) $subscription_id;
        }
        if ($variation_id) {
            $where .= ' AND wc_variation_id = %d';
            $params[] = (int) $variation_id;
        }
        $existing = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . OCD_Schema::table('projects') . ' WHERE ' . $where . ' LIMIT 1',
            $params
        ));
        if ($existing) return $existing;

        $project = OCD_Projects::create([
            'user_id'            => $user_id,
            'wc_order_id'        => $order_id ?: null,
            'wc_subscription_id' => $subscription_id ?: null,
            'wc_product_id'      => $product_id,
            'wc_variation_id'    => $variation_id ?: null,
            'title'              => $name ?: ('Project for product #' . $product_id),
            'status'             => 'planning',
        ]);
        if (is_wp_error($project) || !$project) return 0;
        return (int) $project['id'];
    }

    public static function add_capability_to_role($role) {
        $role_obj = get_role($role);
        if ($role_obj && !$role_obj->has_cap(self::DASHBOARD_CAP)) {
            $role_obj->add_cap(self::DASHBOARD_CAP);
        }
    }
}
