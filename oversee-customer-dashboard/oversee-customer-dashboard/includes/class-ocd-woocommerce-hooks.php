<?php
/**
 * WooCommerce hooks: grant dashboard access + entitlements + project records
 * when an order completes/processes, and on subscription status changes.
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class OCD_WooCommerce_Hooks {

    const DASHBOARD_CAP = 'access_oversee_dashboard';

    public static function init() {
        // Order processed/completed.
        add_action('woocommerce_order_status_processing', [__CLASS__, 'handle_order_paid'], 10, 1);
        add_action('woocommerce_order_status_completed',  [__CLASS__, 'handle_order_paid'], 10, 1);

        // Subscription status transitions (WC Subscriptions).
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

        $items = $order->get_items();
        foreach ($items as $item) {
            $product_id = (int) $item->get_product_id();
            $feature = OCD_Entitlements::feature_for_product($product_id);
            if ($feature) {
                OCD_Entitlements::grant($user_id, $feature['slug'], [
                    'label'         => $feature['label'],
                    'wc_product_id' => $product_id,
                    'wc_order_id'   => (int) $order_id,
                ]);
            }
            // Auto-create a project for purchased products if none exists yet.
            self::maybe_create_project_for_purchase($user_id, (int) $order_id, $product_id, $item->get_name());
        }
        do_action('ocd_order_paid_processed', $order_id, $user_id);
    }

    public static function handle_sub_active($subscription) {
        $user_id = (int) $subscription->get_user_id();
        if (!$user_id) return;
        self::ensure_dashboard_capability($user_id);

        foreach ($subscription->get_items() as $item) {
            $product_id = (int) $item->get_product_id();
            $feature = OCD_Entitlements::feature_for_product($product_id);
            if ($feature) {
                OCD_Entitlements::grant($user_id, $feature['slug'], [
                    'label'              => $feature['label'],
                    'wc_product_id'      => $product_id,
                    'wc_subscription_id' => (int) $subscription->get_id(),
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
            $product_id = (int) $item->get_product_id();
            $feature = OCD_Entitlements::feature_for_product($product_id);
            if ($feature) {
                OCD_Entitlements::set_status($user_id, $feature['slug'], $status);
            }
        }
    }

    private static function maybe_create_project_for_purchase($user_id, $order_id, $product_id, $name) {
        global $wpdb;
        $existing = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . OCD_Schema::table('projects')
            . ' WHERE user_id = %d AND wc_order_id = %d AND wc_product_id = %d LIMIT 1',
            (int) $user_id, (int) $order_id, (int) $product_id
        ));
        if ($existing) return;
        OCD_Projects::create([
            'user_id'       => $user_id,
            'wc_order_id'   => $order_id,
            'wc_product_id' => $product_id,
            'title'         => $name ?: ('Project for product #' . $product_id),
            'status'        => 'planning',
        ]);
    }

    public static function add_capability_to_role($role) {
        $role_obj = get_role($role);
        if ($role_obj && !$role_obj->has_cap(self::DASHBOARD_CAP)) {
            $role_obj->add_cap(self::DASHBOARD_CAP);
        }
    }
}
