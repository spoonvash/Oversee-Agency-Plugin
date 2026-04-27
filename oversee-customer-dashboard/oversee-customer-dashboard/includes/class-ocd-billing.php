<?php
/**
 * Billing helpers. Card data NEVER touches this plugin — every action that
 * collects payment info routes the customer to the native WooCommerce flow:
 *
 *   - My Account  → Payment Methods           (manage saved cards)
 *   - Subscription View → Change Payment       (rotate the card on a single
 *                                                subscription)
 *   - Order View → "Pay" link                  (settle a pending order)
 *
 * The plugin computes the URLs and reports eligibility flags so the UI can
 * show CTAs only where the WooCommerce-native flow is available.
 *
 * Eligibility for "Change Payment" on a subscription (per WooCommerce docs):
 *   - subscription is `active`
 *   - it has automatic recurring payments (gateway supports it)
 *   - a future payment is scheduled
 *   - site is NOT in staging mode
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class OCD_Billing {

    public static function payment_methods_url() {
        $base = self::base_url();
        return $base ? trailingslashit($base) . 'my-account/payment-methods/' : '';
    }

    public static function add_payment_method_url() {
        $base = self::base_url();
        return $base ? trailingslashit($base) . 'my-account/add-payment-method/' : '';
    }

    public static function subscription_view_url($subscription_id) {
        $base = self::base_url();
        if (!$base || !$subscription_id) return '';
        return trailingslashit($base) . 'my-account/view-subscription/' . (int) $subscription_id . '/';
    }

    public static function change_payment_url($subscription_id) {
        $base = self::base_url();
        if (!$base || !$subscription_id) return '';
        // WooCommerce Subscriptions builds the link with `change_payment_method`
        // query arg on the my-account view-subscription page.
        return add_query_arg([
            'change_payment_method' => (int) $subscription_id,
        ], trailingslashit($base) . 'my-account/view-subscription/' . (int) $subscription_id . '/');
    }

    public static function order_pay_url($order) {
        $base = self::base_url();
        if (!$base || !is_array($order)) return '';
        $oid = (int) ($order['id'] ?? 0);
        $key = $order['order_key'] ?? '';
        if (!$oid || !$key) return '';
        return add_query_arg(
            ['key' => $key],
            trailingslashit($base) . 'checkout/order-pay/' . $oid . '/'
        );
    }

    public static function order_view_url($order_id) {
        $base = self::base_url();
        if (!$base || !$order_id) return '';
        return trailingslashit($base) . 'my-account/view-order/' . (int) $order_id . '/';
    }

    public static function invoice_download_url($order) {
        // The WooCommerce-native invoice URL depends on which invoice plugin is
        // active. We expose only the order-view link by default — the UI shows
        // the "Download invoice" CTA only if the order payload includes one.
        if (!is_array($order)) return '';
        if (!empty($order['invoice_url']) && filter_var($order['invoice_url'], FILTER_VALIDATE_URL)) {
            return $order['invoice_url'];
        }
        return self::order_view_url((int) ($order['id'] ?? 0));
    }

    /**
     * Eligibility check for the per-subscription "Change Payment Method" CTA.
     *
     * @param array $sub  WC REST API subscription payload
     * @return array      ['can_change_payment' => bool, 'reason' => string|null,
     *                     'change_payment_url' => string]
     */
    public static function subscription_payment_eligibility($sub) {
        if (!is_array($sub)) {
            return ['can_change_payment' => false, 'reason' => 'Subscription not loaded.', 'change_payment_url' => ''];
        }
        $sid = (int) ($sub['id'] ?? 0);
        $status = (string) ($sub['status'] ?? '');
        if ($status !== 'active') {
            return [
                'can_change_payment' => false,
                'reason'             => 'Change Payment Method is only available while a subscription is active.',
                'change_payment_url' => '',
            ];
        }
        // WooCommerce stores "manual renewal" on a flag we read from the REST payload.
        $manual = false;
        if (isset($sub['payment_method_meta'])) {
            $manual = empty($sub['payment_method_meta']);
        }
        if (!empty($sub['needs_payment']) && empty($sub['payment_method'])) {
            $manual = true;
        }
        if (empty($sub['payment_method'])) {
            return [
                'can_change_payment' => false,
                'reason'             => 'No automatic payment gateway is attached to this subscription.',
                'change_payment_url' => '',
            ];
        }
        // Future-payment requirement: WC sends `next_payment_date_gmt` for active subs.
        $next = $sub['next_payment_date_gmt'] ?? ($sub['next_payment_date'] ?? '');
        if (!$next) {
            return [
                'can_change_payment' => false,
                'reason'             => 'No future payment is scheduled, so card details cannot be updated yet.',
                'change_payment_url' => '',
            ];
        }
        if (self::is_staging_mode()) {
            return [
                'can_change_payment' => false,
                'reason'             => 'Site is in staging mode; payment changes are disabled.',
                'change_payment_url' => '',
            ];
        }
        return [
            'can_change_payment' => true,
            'reason'             => null,
            'change_payment_url' => self::change_payment_url($sid),
        ];
    }

    public static function is_staging_mode() {
        if (defined('WP_STAGING') && WP_STAGING) return true;
        // WC Subscriptions adds a `wcs_staging` constant when it detects staging.
        if (defined('WCS_STAGING') && WCS_STAGING) return true;
        if (defined('WP_ENVIRONMENT_TYPE') && in_array(strtolower(WP_ENVIRONMENT_TYPE), ['staging', 'development'], true)) {
            return true;
        }
        return false;
    }

    /**
     * Customer-facing billing payload. Surfaces native links + invoices.
     */
    public static function payload_for_customer($user_id, $subscriptions, $orders) {
        $subs_out = [];
        foreach ((array) $subscriptions as $sub) {
            $eligibility = self::subscription_payment_eligibility($sub);
            $subs_out[] = [
                'id'                  => (int) ($sub['id'] ?? 0),
                'status'              => (string) ($sub['status'] ?? ''),
                'total'               => (string) ($sub['total'] ?? ''),
                'currency'            => (string) ($sub['currency'] ?? ''),
                'next_payment_date'   => $sub['next_payment_date'] ?? null,
                'payment_method_title'=> $sub['payment_method_title'] ?? '',
                'view_url'            => self::subscription_view_url((int) ($sub['id'] ?? 0)),
                'change_payment_url'  => $eligibility['change_payment_url'],
                'can_change_payment'  => $eligibility['can_change_payment'],
                'change_payment_reason' => $eligibility['reason'],
            ];
        }
        $orders_out = [];
        foreach ((array) $orders as $order) {
            $oid = (int) ($order['id'] ?? 0);
            $orders_out[] = [
                'id'             => $oid,
                'number'         => $order['number'] ?? (string) $oid,
                'status'         => (string) ($order['status'] ?? ''),
                'total'          => (string) ($order['total'] ?? ''),
                'currency'       => (string) ($order['currency'] ?? ''),
                'date_paid'      => $order['date_paid'] ?? null,
                'view_url'       => self::order_view_url($oid),
                'pay_url'        => self::order_pay_url($order),
                'invoice_url'    => self::invoice_download_url($order),
                'needs_payment'  => in_array($order['status'] ?? '', ['pending', 'failed', 'on-hold'], true),
            ];
        }
        return [
            'payment_methods_url'     => self::payment_methods_url(),
            'add_payment_method_url'  => self::add_payment_method_url(),
            'subscriptions'           => $subs_out,
            'orders'                  => $orders_out,
            'staging_mode'            => self::is_staging_mode(),
        ];
    }

    private static function base_url() {
        // Prefer the configured WooCommerce site (cross-site) but fall back to
        // home_url so single-site installs still work.
        $configured = '';
        if (class_exists('OCD_Settings')) {
            $configured = (string) OCD_Settings::get('wp_base_url');
        }
        if ($configured) return rtrim($configured, '/');
        return function_exists('home_url') ? rtrim(home_url(), '/') : '';
    }
}
