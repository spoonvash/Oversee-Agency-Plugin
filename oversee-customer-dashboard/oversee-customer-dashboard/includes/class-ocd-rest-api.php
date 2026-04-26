<?php
/**
 * REST API: bridges the dashboard frontend with HighLevel + WooCommerce.
 * Tokens are server-side; the frontend never sees them.
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class OCD_REST_API {

    const NAMESPACE = 'ocd/v1';

    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes() {
        register_rest_route(self::NAMESPACE, '/status', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_status'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/me', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_me'],
            'permission_callback' => [__CLASS__, 'logged_in_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/customer/dashboard', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'customer_dashboard'],
            'permission_callback' => [__CLASS__, 'logged_in_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/customer/subscriptions/(?P<id>\d+)/cancel', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'customer_cancel_subscription'],
            'permission_callback' => [__CLASS__, 'logged_in_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/admin/customers', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'admin_list_customers'],
            'permission_callback' => [__CLASS__, 'admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/admin/subscriptions', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'admin_list_subscriptions'],
            'permission_callback' => [__CLASS__, 'admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/admin/subscriptions/(?P<id>\d+)', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'admin_update_subscription'],
            'permission_callback' => [__CLASS__, 'admin_permission'],
            'args'                => [
                'status' => ['required' => true, 'type' => 'string'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/admin/contacts', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'admin_list_contacts'],
            'permission_callback' => [__CLASS__, 'admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/admin/opportunities', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'admin_list_opportunities'],
            'permission_callback' => [__CLASS__, 'admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/admin/sync-status', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'admin_sync_status'],
            'permission_callback' => [__CLASS__, 'admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/webhooks/highlevel', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'webhook_highlevel'],
            'permission_callback' => [__CLASS__, 'verify_webhook'],
        ]);
    }

    public static function logged_in_permission() {
        return is_user_logged_in() ? true : new WP_Error('ocd_not_authenticated', 'Authentication required.', ['status' => 401]);
    }

    public static function admin_permission() {
        if (!is_user_logged_in()) {
            return new WP_Error('ocd_not_authenticated', 'Authentication required.', ['status' => 401]);
        }
        if (current_user_can('manage_woocommerce') || current_user_can('manage_options')) {
            return true;
        }
        return new WP_Error('ocd_forbidden', 'Insufficient permissions.', ['status' => 403]);
    }

    public static function verify_webhook($request) {
        $secret = getenv('OCD_WEBHOOK_SECRET') ?: (defined('OCD_WEBHOOK_SECRET') ? OCD_WEBHOOK_SECRET : '');
        if (!$secret) {
            return new WP_Error('ocd_webhook_disabled', 'Webhook secret not set.', ['status' => 403]);
        }
        $given = $request->get_header('x-ocd-signature');
        $body  = $request->get_body();
        $expected = hash_hmac('sha256', $body, $secret);
        return hash_equals($expected, (string) $given);
    }

    public static function get_status() {
        return rest_ensure_response([
            'plugin'      => 'oversee-customer-dashboard',
            'version'     => OCD_VERSION,
            'connections' => OCD_Settings::connection_status(),
        ]);
    }

    public static function get_me() {
        $user = wp_get_current_user();
        return rest_ensure_response([
            'id'    => $user->ID,
            'email' => $user->user_email,
            'name'  => $user->display_name,
            'roles' => $user->roles,
            'is_admin' => current_user_can('manage_woocommerce') || current_user_can('manage_options'),
        ]);
    }

    public static function customer_dashboard() {
        $user = wp_get_current_user();
        $email = $user->user_email;

        $payload = [
            'user' => [
                'id'    => $user->ID,
                'email' => $email,
                'name'  => $user->display_name,
            ],
            'connections'   => OCD_Settings::connection_status(),
            'crm'           => null,
            'subscriptions' => [],
            'orders'        => [],
            'errors'        => [],
        ];

        if (OCD_HighLevel::is_configured()) {
            $contact = OCD_HighLevel::search_contact_by_email($email);
            if (is_wp_error($contact)) {
                $payload['errors'][] = ['source' => 'highlevel', 'message' => $contact->get_error_message()];
            } elseif ($contact) {
                $opps = OCD_HighLevel::list_opportunities_for_contact($contact['id']);
                $payload['crm'] = [
                    'contact'       => $contact,
                    'opportunities' => is_wp_error($opps) ? [] : ($opps['opportunities'] ?? []),
                ];
            }
        }

        if (OCD_WooCommerce::is_configured()) {
            $customer = OCD_WooCommerce::find_customer_by_email($email);
            if (is_wp_error($customer)) {
                $payload['errors'][] = ['source' => 'woocommerce', 'message' => $customer->get_error_message()];
            } elseif ($customer) {
                $subs = OCD_WooCommerce::list_subscriptions_for_customer($customer['id']);
                $orders = OCD_WooCommerce::list_orders_for_customer($customer['id']);
                $payload['subscriptions'] = is_wp_error($subs) ? [] : $subs;
                $payload['orders']        = is_wp_error($orders) ? [] : $orders;
            }
        }

        return rest_ensure_response($payload);
    }

    public static function customer_cancel_subscription($request) {
        $sub_id = (int) $request['id'];
        $user   = wp_get_current_user();

        if (!OCD_WooCommerce::is_configured()) {
            return new WP_Error('ocd_not_configured', 'WooCommerce not configured.', ['status' => 400]);
        }

        $customer = OCD_WooCommerce::find_customer_by_email($user->user_email);
        if (is_wp_error($customer) || !$customer) {
            return new WP_Error('ocd_no_customer', 'Customer not found in WooCommerce.', ['status' => 404]);
        }

        $sub = OCD_WooCommerce::get_subscription($sub_id);
        if (is_wp_error($sub)) return $sub;

        $owner_id = (int) ($sub['customer_id'] ?? 0);
        if ($owner_id !== (int) $customer['id']) {
            return new WP_Error('ocd_forbidden', 'You do not own this subscription.', ['status' => 403]);
        }

        $result = OCD_WooCommerce::update_subscription_status($sub_id, 'pending-cancel');
        if (is_wp_error($result)) return $result;
        return rest_ensure_response(['success' => true, 'subscription' => $result]);
    }

    public static function admin_list_customers($request) {
        if (!OCD_WooCommerce::is_configured()) {
            return new WP_Error('ocd_not_configured', 'WooCommerce not configured.', ['status' => 400]);
        }
        $args = [];
        if ($search = $request->get_param('search')) $args['search'] = sanitize_text_field($search);
        if ($per_page = $request->get_param('per_page')) $args['per_page'] = max(1, min(100, (int) $per_page));
        if ($page = $request->get_param('page')) $args['page'] = max(1, (int) $page);
        $result = OCD_WooCommerce::list_customers($args);
        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    public static function admin_list_subscriptions($request) {
        if (!OCD_WooCommerce::is_configured()) {
            return new WP_Error('ocd_not_configured', 'WooCommerce not configured.', ['status' => 400]);
        }
        $args = [];
        if ($status = $request->get_param('status')) {
            if (!in_array($status, OCD_WooCommerce::VALID_SUB_STATUSES, true)) {
                return new WP_Error('ocd_invalid_status', 'Invalid status', ['status' => 400]);
            }
            $args['status'] = $status;
        }
        if ($per_page = $request->get_param('per_page')) $args['per_page'] = max(1, min(100, (int) $per_page));
        if ($page = $request->get_param('page')) $args['page'] = max(1, (int) $page);
        $result = OCD_WooCommerce::list_subscriptions($args);
        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    public static function admin_update_subscription($request) {
        $id     = (int) $request['id'];
        $status = sanitize_text_field($request->get_param('status'));
        $result = OCD_WooCommerce::update_subscription_status($id, $status);
        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    public static function admin_list_contacts($request) {
        if (!OCD_HighLevel::is_configured()) {
            return new WP_Error('ocd_not_configured', 'HighLevel not configured.', ['status' => 400]);
        }
        $args = [];
        if ($q = $request->get_param('query')) $args['query'] = sanitize_text_field($q);
        if ($limit = $request->get_param('limit')) $args['limit'] = max(1, min(100, (int) $limit));
        $result = OCD_HighLevel::list_contacts($args);
        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    public static function admin_list_opportunities($request) {
        if (!OCD_HighLevel::is_configured()) {
            return new WP_Error('ocd_not_configured', 'HighLevel not configured.', ['status' => 400]);
        }
        $args = [];
        if ($status = $request->get_param('status')) $args['status'] = sanitize_text_field($status);
        if ($limit = $request->get_param('limit')) $args['limit'] = max(1, min(100, (int) $limit));
        $result = OCD_HighLevel::list_opportunities($args);
        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    public static function admin_sync_status() {
        $hl_ok = OCD_HighLevel::is_configured();
        $wc_ok = OCD_WooCommerce::is_configured();

        $hl_ping = $hl_ok ? OCD_HighLevel::ping() : null;
        $wc_ping = $wc_ok ? OCD_WooCommerce::ping() : null;

        return rest_ensure_response([
            'highlevel' => [
                'configured' => $hl_ok,
                'ok'         => $hl_ok && !is_wp_error($hl_ping),
                'error'      => is_wp_error($hl_ping) ? $hl_ping->get_error_message() : null,
            ],
            'woocommerce' => [
                'configured' => $wc_ok,
                'ok'         => $wc_ok && !is_wp_error($wc_ping),
                'error'      => is_wp_error($wc_ping) ? $wc_ping->get_error_message() : null,
            ],
            'last_check' => current_time('c'),
        ]);
    }

    public static function webhook_highlevel($request) {
        $payload = $request->get_json_params();
        do_action('ocd_highlevel_webhook', $payload);
        return rest_ensure_response(['received' => true]);
    }
}
