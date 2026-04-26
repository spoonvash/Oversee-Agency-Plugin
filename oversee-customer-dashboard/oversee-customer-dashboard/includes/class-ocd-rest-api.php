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

        // ---------- Customer messaging ----------
        register_rest_route(self::NAMESPACE, '/customer/messages', [
            [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'customer_list_messages'],
                'permission_callback' => [__CLASS__, 'logged_in_permission'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'customer_send_message'],
                'permission_callback' => [__CLASS__, 'logged_in_permission'],
                'args'                => ['message' => ['required' => true, 'type' => 'string']],
            ],
        ]);

        // ---------- Customer projects (read-only) ----------
        register_rest_route(self::NAMESPACE, '/customer/projects', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'customer_list_projects'],
            'permission_callback' => [__CLASS__, 'logged_in_permission'],
        ]);

        // ---------- Customer tasks ----------
        register_rest_route(self::NAMESPACE, '/customer/tasks', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'customer_list_tasks'],
            'permission_callback' => [__CLASS__, 'logged_in_permission'],
        ]);
        register_rest_route(self::NAMESPACE, '/customer/tasks/(?P<id>\d+)', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'customer_update_task'],
            'permission_callback' => [__CLASS__, 'logged_in_permission'],
        ]);

        // ---------- Customer entitlements + store ----------
        register_rest_route(self::NAMESPACE, '/customer/entitlements', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'customer_list_entitlements'],
            'permission_callback' => [__CLASS__, 'logged_in_permission'],
        ]);
        register_rest_route(self::NAMESPACE, '/customer/store', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'customer_store'],
            'permission_callback' => [__CLASS__, 'logged_in_permission'],
        ]);

        // ---------- Customer-owned CRM ----------
        register_rest_route(self::NAMESPACE, '/customer/crm', [
            [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'customer_crm_status'],
                'permission_callback' => [__CLASS__, 'logged_in_permission'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'customer_crm_connect'],
                'permission_callback' => [__CLASS__, 'logged_in_permission'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [__CLASS__, 'customer_crm_disconnect'],
                'permission_callback' => [__CLASS__, 'logged_in_permission'],
            ],
        ]);

        // ---------- Admin: messaging ----------
        register_rest_route(self::NAMESPACE, '/admin/messages/inbox', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'admin_inbox'],
            'permission_callback' => [__CLASS__, 'admin_permission'],
        ]);
        register_rest_route(self::NAMESPACE, '/admin/messages/thread/(?P<user_id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'admin_get_thread'],
                'permission_callback' => [__CLASS__, 'admin_permission'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'admin_reply_thread'],
                'permission_callback' => [__CLASS__, 'admin_permission'],
                'args'                => ['message' => ['required' => true, 'type' => 'string']],
            ],
        ]);

        // ---------- Admin: projects ----------
        register_rest_route(self::NAMESPACE, '/admin/projects', [
            [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'admin_list_projects'],
                'permission_callback' => [__CLASS__, 'admin_permission'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'admin_create_project'],
                'permission_callback' => [__CLASS__, 'admin_permission'],
            ],
        ]);
        register_rest_route(self::NAMESPACE, '/admin/projects/(?P<id>\d+)', [
            [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'admin_update_project'],
                'permission_callback' => [__CLASS__, 'admin_permission'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [__CLASS__, 'admin_delete_project'],
                'permission_callback' => [__CLASS__, 'admin_permission'],
            ],
        ]);
        register_rest_route(self::NAMESPACE, '/admin/projects/(?P<id>\d+)/milestones', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'admin_add_milestone'],
            'permission_callback' => [__CLASS__, 'admin_permission'],
        ]);
        register_rest_route(self::NAMESPACE, '/admin/milestones/(?P<id>\d+)', [
            [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'admin_update_milestone'],
                'permission_callback' => [__CLASS__, 'admin_permission'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [__CLASS__, 'admin_delete_milestone'],
                'permission_callback' => [__CLASS__, 'admin_permission'],
            ],
        ]);

        // ---------- Admin: tasks ----------
        register_rest_route(self::NAMESPACE, '/admin/tasks', [
            [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'admin_list_tasks'],
                'permission_callback' => [__CLASS__, 'admin_permission'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'admin_create_task'],
                'permission_callback' => [__CLASS__, 'admin_permission'],
            ],
        ]);
        register_rest_route(self::NAMESPACE, '/admin/tasks/(?P<id>\d+)', [
            [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'admin_update_task'],
                'permission_callback' => [__CLASS__, 'admin_permission'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [__CLASS__, 'admin_delete_task'],
                'permission_callback' => [__CLASS__, 'admin_permission'],
            ],
        ]);

        // ---------- Admin: entitlements + product map ----------
        register_rest_route(self::NAMESPACE, '/admin/entitlements', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'admin_list_entitlements_for_user'],
            'permission_callback' => [__CLASS__, 'admin_permission'],
        ]);
        register_rest_route(self::NAMESPACE, '/admin/product-map', [
            [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'admin_get_product_map'],
                'permission_callback' => [__CLASS__, 'admin_permission'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'admin_set_product_map'],
                'permission_callback' => [__CLASS__, 'admin_permission'],
            ],
        ]);

        // Search existing WooCommerce products (admin mapping UI). Existing products only — never creates products.
        register_rest_route(self::NAMESPACE, '/admin/wc-products', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'admin_search_wc_products'],
            'permission_callback' => [__CLASS__, 'admin_permission'],
        ]);

        // Read/write the optional "store category" (existing WC category slug used to surface products in the customer dashboard).
        register_rest_route(self::NAMESPACE, '/admin/store-category', [
            [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'admin_get_store_category'],
                'permission_callback' => [__CLASS__, 'admin_permission'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'admin_set_store_category'],
                'permission_callback' => [__CLASS__, 'admin_permission'],
            ],
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
            'connections'      => OCD_Settings::connection_status(),
            'crm'              => null,
            'subscriptions'    => [],
            'orders'           => [],
            'errors'           => [],
            'projects'         => OCD_Projects::for_user($user->ID),
            'tasks'            => OCD_Tasks::for_user($user->ID),
            'entitlements'     => OCD_Entitlements::for_user($user->ID),
            'store'            => OCD_Store::listings_for_user($user->ID),
            'customer_crm'     => OCD_Customer_CRM::status_for_user($user->ID),
            'messages_unread'  => self::count_user_unread($user->ID, 'user'),
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
        // If the webhook is an outbound message from CRM, mirror into local thread.
        if (is_array($payload) && !empty($payload['contactId']) && (!empty($payload['message']) || !empty($payload['html']) || !empty($payload['body']))) {
            $result = OCD_Messaging::ingest_from_webhook($payload);
            do_action('ocd_highlevel_webhook', $payload, $result);
            return rest_ensure_response(['received' => true, 'ingested' => !is_wp_error($result)]);
        }
        do_action('ocd_highlevel_webhook', $payload, null);
        return rest_ensure_response(['received' => true]);
    }

    /* ---------------- Customer messaging ---------------- */

    public static function customer_list_messages() {
        $user = wp_get_current_user();
        OCD_Messaging::mark_thread_read($user->ID, 'user');
        return rest_ensure_response([
            'messages' => OCD_Messaging::thread_for_user($user->ID),
            'connected_to_agency_crm' => OCD_HighLevel::is_configured(),
        ]);
    }

    public static function customer_send_message($request) {
        $user = wp_get_current_user();
        $body = (string) $request->get_param('message');
        $msg  = OCD_Messaging::customer_send($user->ID, $body);
        if (is_wp_error($msg)) return $msg;
        return rest_ensure_response(['message' => $msg, 'connected_to_agency_crm' => OCD_HighLevel::is_configured()]);
    }

    /* ---------------- Customer projects/tasks/entitlements ---------------- */

    public static function customer_list_projects() {
        $user = wp_get_current_user();
        return rest_ensure_response(OCD_Projects::for_user($user->ID));
    }

    public static function customer_list_tasks() {
        $user = wp_get_current_user();
        return rest_ensure_response(OCD_Tasks::for_user($user->ID));
    }

    public static function customer_update_task($request) {
        $user    = wp_get_current_user();
        $task_id = (int) $request['id'];
        $task    = OCD_Tasks::get($task_id);
        if (!$task) return new WP_Error('ocd_no_task', 'Task not found.', ['status' => 404]);
        if ((int) $task['user_id'] !== (int) $user->ID) {
            return new WP_Error('ocd_forbidden', 'Not your task.', ['status' => 403]);
        }
        $update = [];
        if ($request->get_param('status') !== null) $update['status'] = sanitize_text_field($request->get_param('status'));
        $result = OCD_Tasks::update($task_id, $update, 'customer');
        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    public static function customer_list_entitlements() {
        $user = wp_get_current_user();
        return rest_ensure_response(OCD_Entitlements::for_user($user->ID));
    }

    public static function customer_store() {
        $user    = wp_get_current_user();
        $payload = OCD_Store::dashboard_payload($user->ID);
        // Tell the client whether WooCommerce is reachable so it can render a setup empty-state instead of a broken UI.
        $payload['wc_active'] = function_exists('wc_get_products');
        return rest_ensure_response($payload);
    }

    /* ---------------- Customer-owned CRM ---------------- */

    public static function customer_crm_status() {
        $user = wp_get_current_user();
        return rest_ensure_response(OCD_Customer_CRM::status_for_user($user->ID));
    }

    public static function customer_crm_connect($request) {
        $user = wp_get_current_user();
        $data = [
            'provider'         => sanitize_key($request->get_param('provider') ?: 'highlevel'),
            'label'            => sanitize_text_field($request->get_param('label') ?: ''),
            'location_id'      => sanitize_text_field($request->get_param('location_id') ?: ''),
            'access_token'     => (string) $request->get_param('access_token'),
            'refresh_token'    => (string) $request->get_param('refresh_token'),
            'token_expires_at' => sanitize_text_field($request->get_param('token_expires_at') ?: ''),
        ];
        if (empty($data['access_token'])) {
            return new WP_Error('ocd_invalid_token', 'access_token required.', ['status' => 400]);
        }
        $result = OCD_Customer_CRM::connect($user->ID, $data);
        return is_wp_error($result) ? $result : rest_ensure_response($result);
    }

    public static function customer_crm_disconnect($request) {
        $user = wp_get_current_user();
        $provider = sanitize_key($request->get_param('provider') ?: 'highlevel');
        OCD_Customer_CRM::disconnect($user->ID, $provider);
        return rest_ensure_response(['disconnected' => true]);
    }

    /* ---------------- Admin messaging ---------------- */

    public static function admin_inbox($request) {
        return rest_ensure_response(OCD_Messaging::admin_inbox(['per_page' => (int) $request->get_param('per_page') ?: 25]));
    }

    public static function admin_get_thread($request) {
        $user_id = (int) $request['user_id'];
        OCD_Messaging::mark_thread_read($user_id, 'admin');
        $u = get_userdata($user_id);
        return rest_ensure_response([
            'user_id'  => $user_id,
            'name'     => $u ? $u->display_name : null,
            'email'    => $u ? $u->user_email : null,
            'messages' => OCD_Messaging::thread_for_user($user_id),
        ]);
    }

    public static function admin_reply_thread($request) {
        $user_id = (int) $request['user_id'];
        $admin   = wp_get_current_user();
        $body    = (string) $request->get_param('message');
        $msg     = OCD_Messaging::admin_reply($user_id, $body, $admin->ID);
        return is_wp_error($msg) ? $msg : rest_ensure_response($msg);
    }

    /* ---------------- Admin projects ---------------- */

    public static function admin_list_projects($request) {
        return rest_ensure_response(OCD_Projects::all([
            'user_id'  => (int) $request->get_param('user_id'),
            'status'   => sanitize_text_field((string) $request->get_param('status')),
            'per_page' => (int) $request->get_param('per_page') ?: 50,
        ]));
    }

    public static function admin_create_project($request) {
        $data = [
            'user_id'            => (int) $request->get_param('user_id'),
            'title'              => sanitize_text_field((string) $request->get_param('title')),
            'description'        => (string) $request->get_param('description'),
            'status'             => sanitize_text_field((string) $request->get_param('status')) ?: 'planning',
            'progress'           => (int) $request->get_param('progress'),
            'start_date'         => $request->get_param('start_date'),
            'target_date'        => $request->get_param('target_date'),
            'wc_order_id'        => (int) $request->get_param('wc_order_id'),
            'wc_subscription_id' => (int) $request->get_param('wc_subscription_id'),
            'wc_product_id'      => (int) $request->get_param('wc_product_id'),
        ];
        $res = OCD_Projects::create($data);
        return is_wp_error($res) ? $res : rest_ensure_response($res);
    }

    public static function admin_update_project($request) {
        $id = (int) $request['id'];
        $data = [];
        foreach (['title', 'description', 'status', 'start_date', 'target_date'] as $f) {
            if ($request->get_param($f) !== null) $data[$f] = $request->get_param($f);
        }
        if ($request->get_param('progress') !== null) $data['progress'] = (int) $request->get_param('progress');
        $res = OCD_Projects::update($id, $data);
        return is_wp_error($res) ? $res : rest_ensure_response($res);
    }

    public static function admin_delete_project($request) {
        OCD_Projects::delete((int) $request['id']);
        return rest_ensure_response(['deleted' => true]);
    }

    public static function admin_add_milestone($request) {
        $project_id = (int) $request['id'];
        $data = [
            'title'      => sanitize_text_field((string) $request->get_param('title')),
            'note'       => (string) $request->get_param('note'),
            'status'     => sanitize_text_field((string) $request->get_param('status')) ?: 'pending',
            'sort_order' => (int) $request->get_param('sort_order'),
            'due_date'   => $request->get_param('due_date'),
        ];
        $res = OCD_Projects::add_milestone($project_id, $data);
        return is_wp_error($res) ? $res : rest_ensure_response($res);
    }

    public static function admin_update_milestone($request) {
        $id = (int) $request['id'];
        $data = [];
        foreach (['title', 'note', 'status', 'due_date'] as $f) {
            if ($request->get_param($f) !== null) $data[$f] = $request->get_param($f);
        }
        if ($request->get_param('sort_order') !== null) $data['sort_order'] = (int) $request->get_param('sort_order');
        $res = OCD_Projects::update_milestone($id, $data);
        return is_wp_error($res) ? $res : rest_ensure_response($res);
    }

    public static function admin_delete_milestone($request) {
        OCD_Projects::delete_milestone((int) $request['id']);
        return rest_ensure_response(['deleted' => true]);
    }

    /* ---------------- Admin tasks ---------------- */

    public static function admin_list_tasks($request) {
        return rest_ensure_response(OCD_Tasks::all([
            'user_id'  => (int) $request->get_param('user_id'),
            'status'   => sanitize_text_field((string) $request->get_param('status')),
            'per_page' => (int) $request->get_param('per_page') ?: 100,
        ]));
    }

    public static function admin_create_task($request) {
        $admin = wp_get_current_user();
        $data = [
            'user_id'    => (int) $request->get_param('user_id'),
            'project_id' => (int) $request->get_param('project_id'),
            'title'      => sanitize_text_field((string) $request->get_param('title')),
            'details'    => (string) $request->get_param('details'),
            'status'     => sanitize_text_field((string) $request->get_param('status')) ?: 'open',
            'due_date'   => $request->get_param('due_date'),
            'assigned_by'=> $admin->ID,
        ];
        $res = OCD_Tasks::create($data);
        return is_wp_error($res) ? $res : rest_ensure_response($res);
    }

    public static function admin_update_task($request) {
        $id = (int) $request['id'];
        $data = [];
        foreach (['title', 'details', 'status', 'due_date'] as $f) {
            if ($request->get_param($f) !== null) $data[$f] = $request->get_param($f);
        }
        if ($request->get_param('project_id') !== null) $data['project_id'] = (int) $request->get_param('project_id');
        $res = OCD_Tasks::update($id, $data, 'admin');
        return is_wp_error($res) ? $res : rest_ensure_response($res);
    }

    public static function admin_delete_task($request) {
        OCD_Tasks::delete((int) $request['id']);
        return rest_ensure_response(['deleted' => true]);
    }

    /* ---------------- Admin entitlements + map ---------------- */

    public static function admin_list_entitlements_for_user($request) {
        $user_id = (int) $request->get_param('user_id');
        if (!$user_id) return new WP_Error('ocd_invalid', 'user_id required.', ['status' => 400]);
        return rest_ensure_response(OCD_Entitlements::for_user($user_id));
    }

    public static function admin_get_product_map() {
        return rest_ensure_response(OCD_Entitlements::get_product_map());
    }

    public static function admin_set_product_map($request) {
        $map = $request->get_param('map');
        if (!is_array($map)) return new WP_Error('ocd_invalid_map', 'Expected map array.', ['status' => 400]);
        // Validate every product id exists in WooCommerce. We never create products from the dashboard;
        // mapping must reference an existing WC product.
        if (function_exists('wc_get_product')) {
            foreach ($map as $product_id => $entry) {
                $pid = (int) $product_id;
                if ($pid <= 0) continue;
                $product = wc_get_product($pid);
                if (!$product) {
                    return new WP_Error(
                        'ocd_unknown_product',
                        sprintf('WooCommerce product #%d does not exist. Mapping must reference an existing product.', $pid),
                        ['status' => 400]
                    );
                }
            }
        }
        return rest_ensure_response(OCD_Entitlements::set_product_map($map));
    }

    public static function admin_search_wc_products($request) {
        $args = [
            'search'   => sanitize_text_field((string) $request->get_param('search')),
            'per_page' => (int) ($request->get_param('per_page') ?: 25),
        ];
        return rest_ensure_response(OCD_Store::search_existing_products($args));
    }

    public static function admin_get_store_category() {
        $cat = OCD_Store::get_store_category();
        $available = [];
        if (function_exists('get_terms')) {
            $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
            if (!is_wp_error($terms)) {
                foreach ((array) $terms as $term) {
                    if (!is_object($term)) continue;
                    $available[] = [
                        'slug'  => $term->slug,
                        'name'  => $term->name,
                        'count' => (int) ($term->count ?? 0),
                    ];
                }
            }
        }
        return rest_ensure_response([
            'category'   => $cat,
            'available'  => $available,
        ]);
    }

    public static function admin_set_store_category($request) {
        $slug = sanitize_title((string) $request->get_param('category'));
        return rest_ensure_response(['category' => OCD_Store::set_store_category($slug)]);
    }

    /* ---------------- helpers ---------------- */

    private static function count_user_unread($user_id, $reader) {
        global $wpdb;
        $col = $reader === 'admin' ? 'read_by_admin' : 'read_by_user';
        return (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . OCD_Schema::table('messages')
            . ' WHERE user_id = %d AND ' . $col . ' = 0',
            (int) $user_id
        ));
    }
}
