<?php
/**
 * WooCommerce + WooCommerce Subscriptions REST client.
 * Docs:
 *   - https://woocommerce.github.io/woocommerce-rest-api-docs/
 *   - https://woocommerce.github.io/subscriptions-rest-api-docs/
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class OCD_WooCommerce {

    const VALID_SUB_STATUSES = ['active', 'pending', 'on-hold', 'pending-cancel', 'cancelled', 'expired'];

    public static function is_configured() {
        return OCD_Settings::is_woocommerce_configured();
    }

    public static function is_local() {
        $base = OCD_Settings::get('wp_base_url');
        if (!$base) return false;
        $home = home_url();
        return rtrim($base, '/') === rtrim($home, '/');
    }

    private static function request($method, $path, $args = [], $body = null) {
        if (!self::is_configured()) {
            return new WP_Error('ocd_not_configured', __('WooCommerce is not configured. Set OVERSEE_WP_BASE_URL, WOOCOMMERCE_CONSUMER_KEY, WOOCOMMERCE_CONSUMER_SECRET.', 'oversee-customer-dashboard'));
        }

        $base   = rtrim(OCD_Settings::get('wp_base_url'), '/');
        $key    = OCD_Settings::get('woo_consumer_key');
        $secret = OCD_Settings::get('woo_consumer_secret');

        $url = $base . '/wp-json/wc/v3/' . ltrim($path, '/');
        $args = array_merge($args, [
            'consumer_key'    => $key,
            'consumer_secret' => $secret,
        ]);
        if (!empty($args)) {
            $url = add_query_arg($args, $url);
        }

        $headers = [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $request_args = [
            'method'  => strtoupper($method),
            'headers' => $headers,
            'timeout' => 20,
        ];
        if ($body !== null) {
            $request_args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($url, $request_args);
        if (is_wp_error($response)) return $response;

        $code = wp_remote_retrieve_response_code($response);
        $raw  = wp_remote_retrieve_body($response);
        $data = json_decode($raw, true);

        if ($code < 200 || $code >= 300) {
            return new WP_Error(
                'ocd_woo_error',
                sprintf('WooCommerce API error %d: %s', $code, is_string($raw) ? substr($raw, 0, 500) : ''),
                ['status' => $code, 'body' => $data]
            );
        }
        return $data;
    }

    public static function find_customer_by_email($email) {
        $result = self::request('GET', 'customers', ['email' => $email, 'per_page' => 1]);
        if (is_wp_error($result)) return $result;
        return $result[0] ?? null;
    }

    public static function list_customers($args = []) {
        $defaults = ['per_page' => 25, 'orderby' => 'registered_date', 'order' => 'desc'];
        return self::request('GET', 'customers', array_merge($defaults, $args));
    }

    public static function list_orders_for_customer($customer_id, $args = []) {
        $defaults = ['customer' => (int) $customer_id, 'per_page' => 20, 'orderby' => 'date', 'order' => 'desc'];
        return self::request('GET', 'orders', array_merge($defaults, $args));
    }

    public static function list_subscriptions($args = []) {
        $defaults = ['per_page' => 25];
        return self::request('GET', 'subscriptions', array_merge($defaults, $args));
    }

    public static function list_subscriptions_for_customer($customer_id, $args = []) {
        $defaults = ['customer' => (int) $customer_id, 'per_page' => 25];
        return self::request('GET', 'subscriptions', array_merge($defaults, $args));
    }

    public static function get_subscription($subscription_id) {
        return self::request('GET', 'subscriptions/' . (int) $subscription_id);
    }

    public static function update_subscription_status($subscription_id, $status) {
        if (!in_array($status, self::VALID_SUB_STATUSES, true)) {
            return new WP_Error('ocd_invalid_status', 'Invalid subscription status: ' . $status);
        }
        return self::request('PUT', 'subscriptions/' . (int) $subscription_id, [], ['status' => $status]);
    }

    public static function get_subscription_orders($subscription_id) {
        return self::request('GET', 'subscriptions/' . (int) $subscription_id . '/orders');
    }

    public static function get_orders_subscriptions($order_id) {
        return self::request('GET', 'orders/' . (int) $order_id . '/subscriptions');
    }

    public static function ping() {
        return self::request('GET', 'system_status');
    }
}
