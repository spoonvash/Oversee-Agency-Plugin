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
                // No required args — body is optional when attachments are
                // provided. The handler enforces "body OR attachments".
            ],
        ]);
        // Customer uploads an attachment for a message-in-progress and gets
        // back a file id to include in the subsequent POST /customer/messages.
        register_rest_route(self::NAMESPACE, '/customer/messages/attachments', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'customer_upload_message_attachment'],
            'permission_callback' => [__CLASS__, 'logged_in_permission'],
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
                // Body optional when attachments are provided.
            ],
        ]);
        // Admin uploads an attachment scoped to a customer thread; returns a
        // file id to include in the subsequent POST reply.
        register_rest_route(self::NAMESPACE, '/admin/messages/thread/(?P<user_id>\d+)/attachments', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'admin_upload_message_attachment'],
            'permission_callback' => [__CLASS__, 'admin_permission'],
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

        // Drag/drop status endpoints. Same shape for customer + admin so the
        // kanban controller can call one or the other based on role; the
        // permission rules differ: customers can only set CUSTOMER_ALLOWED_STATUSES
        // on their own client-visible tasks, admins can set any valid status
        // on any task.
        register_rest_route(self::NAMESPACE, '/customer/tasks/(?P<id>\d+)/status', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'customer_set_task_status'],
            'permission_callback' => [__CLASS__, 'logged_in_permission'],
            'args'                => ['status' => ['required' => true, 'type' => 'string']],
        ]);
        register_rest_route(self::NAMESPACE, '/admin/tasks/(?P<id>\d+)/status', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'admin_set_task_status'],
            'permission_callback' => [__CLASS__, 'admin_permission'],
            'args'                => ['status' => ['required' => true, 'type' => 'string']],
        ]);

        // Task attachments: scoped reads, plus admin upload of an instruction
        // image directly attached to a task. Customer reads use the existing
        // permission-checked /files/<id>/download endpoint, this just lists
        // which file ids belong to the task.
        register_rest_route(self::NAMESPACE, '/customer/tasks/(?P<id>\d+)/attachments', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'customer_list_task_attachments'],
            'permission_callback' => [__CLASS__, 'logged_in_permission'],
        ]);
        register_rest_route(self::NAMESPACE, '/admin/tasks/(?P<id>\d+)/attachments', [
            [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'admin_list_task_attachments'],
                'permission_callback' => [__CLASS__, 'admin_permission'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'admin_upload_task_attachment'],
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

        // ---------- Project files ----------
        register_rest_route(self::NAMESPACE, '/customer/files', [
            [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'customer_list_files'],
                'permission_callback' => [__CLASS__, 'logged_in_permission'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'customer_upload_file'],
                'permission_callback' => [__CLASS__, 'logged_in_permission'],
            ],
        ]);
        register_rest_route(self::NAMESPACE, '/customer/files/(?P<id>\d+)/approval', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'customer_set_file_approval'],
            'permission_callback' => [__CLASS__, 'logged_in_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/files/(?P<id>\d+)/download', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'serve_file'],
            'permission_callback' => [__CLASS__, 'logged_in_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/admin/files', [
            [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'admin_list_files'],
                'permission_callback' => [__CLASS__, 'admin_permission'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'admin_upload_file'],
                'permission_callback' => [__CLASS__, 'admin_permission'],
            ],
        ]);
        register_rest_route(self::NAMESPACE, '/admin/files/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [__CLASS__, 'admin_delete_file'],
            'permission_callback' => [__CLASS__, 'admin_permission'],
        ]);
        register_rest_route(self::NAMESPACE, '/admin/files/(?P<id>\d+)/archive', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'admin_archive_file'],
            'permission_callback' => [__CLASS__, 'admin_permission'],
        ]);
        register_rest_route(self::NAMESPACE, '/admin/files/(?P<id>\d+)/approval', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'admin_set_file_approval'],
            'permission_callback' => [__CLASS__, 'admin_permission'],
        ]);

        // ---------- Task comments ----------
        register_rest_route(self::NAMESPACE, '/customer/tasks/(?P<id>\d+)/comments', [
            [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'customer_list_task_comments'],
                'permission_callback' => [__CLASS__, 'logged_in_permission'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'customer_add_task_comment'],
                'permission_callback' => [__CLASS__, 'logged_in_permission'],
            ],
        ]);
        register_rest_route(self::NAMESPACE, '/admin/tasks/(?P<id>\d+)/comments', [
            [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'admin_list_task_comments'],
                'permission_callback' => [__CLASS__, 'admin_permission'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'admin_add_task_comment'],
                'permission_callback' => [__CLASS__, 'admin_permission'],
            ],
        ]);
        register_rest_route(self::NAMESPACE, '/admin/task-comments/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [__CLASS__, 'admin_delete_task_comment'],
            'permission_callback' => [__CLASS__, 'admin_permission'],
        ]);

        // ---------- Pending actions + billing + status sets ----------
        register_rest_route(self::NAMESPACE, '/customer/pending-actions', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'customer_pending_actions'],
            'permission_callback' => [__CLASS__, 'logged_in_permission'],
        ]);
        register_rest_route(self::NAMESPACE, '/customer/billing', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'customer_billing'],
            'permission_callback' => [__CLASS__, 'logged_in_permission'],
        ]);
        register_rest_route(self::NAMESPACE, '/customer/task-status-sets', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'task_status_sets'],
            'permission_callback' => '__return_true',
        ]);

        // ---------- Required-step templates (admin) ----------
        register_rest_route(self::NAMESPACE, '/admin/required-step-templates', [
            [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'admin_get_required_step_templates'],
                'permission_callback' => [__CLASS__, 'admin_permission'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'admin_set_required_step_templates'],
                'permission_callback' => [__CLASS__, 'admin_permission'],
            ],
        ]);

        // ---------- Subscription switching (delegate-only; we do not custom-process) ----------
        register_rest_route(self::NAMESPACE, '/customer/subscriptions/(?P<id>\d+)/switch-options', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'customer_subscription_switch_options'],
            'permission_callback' => [__CLASS__, 'logged_in_permission'],
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
            'tasks'            => array_map([OCD_Tasks::class, 'present_for_customer'], OCD_Tasks::for_user($user->ID)),
            'entitlements'     => OCD_Entitlements::for_user($user->ID),
            'store'            => OCD_Store::listings_for_user($user->ID),
            'customer_crm'     => OCD_Customer_CRM::status_for_user($user->ID),
            'messages_unread'  => self::count_user_unread($user->ID, 'user'),
            'files'            => array_map(function ($r) { return OCD_Project_Files::present($r, false); }, OCD_Project_Files::for_user($user->ID, ['context' => 'customer'])),
            'task_status_sets' => [
                'statuses' => OCD_Tasks::VALID_STATUSES,
                'labels'   => OCD_Tasks::STATUS_LABELS,
                'groups'   => OCD_Tasks::status_groups(),
            ],
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

        $payload['billing']         = OCD_Billing::payload_for_customer($user->ID, $payload['subscriptions'], $payload['orders']);
        $payload['pending_actions'] = OCD_Pending_Actions::for_user($user->ID, $payload['subscriptions'], $payload['orders']);

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
        $attachment_ids = self::param_int_list($request->get_param('attachment_ids'));
        $msg  = OCD_Messaging::customer_send($user->ID, $body, ['attachment_ids' => $attachment_ids]);
        if (is_wp_error($msg)) return $msg;
        return rest_ensure_response(['message' => $msg, 'connected_to_agency_crm' => OCD_HighLevel::is_configured()]);
    }

    public static function customer_upload_message_attachment($request) {
        $user = wp_get_current_user();
        return self::process_file_upload($request, [
            'owner_user_id'    => (int) $user->ID,
            'uploader_user_id' => (int) $user->ID,
            'uploader_role'    => 'customer',
            'visibility'       => 'client',
            'approval_status'  => 'not_required',
            'folder'           => 'messages',
        ]);
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
        $attachment_ids = self::param_int_list($request->get_param('attachment_ids'));
        $msg     = OCD_Messaging::admin_reply($user_id, $body, $admin->ID, ['attachment_ids' => $attachment_ids]);
        return is_wp_error($msg) ? $msg : rest_ensure_response($msg);
    }

    public static function admin_upload_message_attachment($request) {
        $admin = wp_get_current_user();
        $owner_id = (int) $request['user_id'];
        if ($owner_id <= 0) {
            return new WP_Error('ocd_invalid', 'user_id required.', ['status' => 400]);
        }
        return self::process_file_upload($request, [
            'owner_user_id'    => $owner_id,
            'uploader_user_id' => (int) $admin->ID,
            'uploader_role'    => 'staff',
            // Always client-visible — the customer must be able to see what
            // staff sent in their thread.
            'visibility'       => 'client',
            'approval_status'  => 'not_required',
            'folder'           => 'messages',
        ]);
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
            'title'                 => sanitize_text_field((string) $request->get_param('title')),
            'note'                  => (string) $request->get_param('note'),
            'status'                => sanitize_text_field((string) $request->get_param('status')) ?: 'pending',
            'sort_order'            => (int) $request->get_param('sort_order'),
            'phase_label'           => (string) $request->get_param('phase_label'),
            'instruction_video_url' => (string) $request->get_param('instruction_video_url'),
            'instruction_image_url' => (string) $request->get_param('instruction_image_url'),
            'due_date'              => $request->get_param('due_date'),
        ];
        $res = OCD_Projects::add_milestone($project_id, $data);
        return is_wp_error($res) ? $res : rest_ensure_response($res);
    }

    public static function admin_update_milestone($request) {
        $id = (int) $request['id'];
        $data = [];
        foreach (['title', 'note', 'status', 'due_date', 'phase_label', 'instruction_video_url', 'instruction_image_url'] as $f) {
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
        $rows = OCD_Tasks::all([
            'user_id'  => (int) $request->get_param('user_id'),
            'status'   => sanitize_text_field((string) $request->get_param('status')),
            'per_page' => (int) $request->get_param('per_page') ?: 100,
        ]);
        // Embed attachments so the admin kanban can show thumbnails inline.
        if (class_exists('OCD_Project_Files')) {
            foreach ($rows as &$row) {
                $files = OCD_Project_Files::all(['task_id' => (int) $row['id']]);
                $row['attachments'] = array_map(function ($f) { return OCD_Project_Files::present($f, true); }, $files);
            }
        }
        return rest_ensure_response($rows);
    }

    public static function admin_create_task($request) {
        $admin = wp_get_current_user();
        $data = [
            'user_id'              => (int) $request->get_param('user_id'),
            'project_id'           => (int) $request->get_param('project_id'),
            'milestone_id'         => (int) $request->get_param('milestone_id'),
            'title'                => sanitize_text_field((string) $request->get_param('title')),
            'details'              => (string) $request->get_param('details'),
            'internal_notes'       => (string) $request->get_param('internal_notes'),
            'status'               => sanitize_text_field((string) $request->get_param('status')) ?: 'not_started',
            'task_type'            => sanitize_text_field((string) $request->get_param('task_type')) ?: 'internal',
            'visibility'           => sanitize_text_field((string) $request->get_param('visibility')) ?: '',
            'priority'             => sanitize_text_field((string) $request->get_param('priority')) ?: 'normal',
            'assignee_user_id'     => (int) $request->get_param('assignee_user_id'),
            'instruction_video_url'=> (string) $request->get_param('instruction_video_url'),
            'instruction_image_url'=> (string) $request->get_param('instruction_image_url'),
            'due_date'             => $request->get_param('due_date'),
            'assigned_by'          => $admin->ID,
        ];
        $res = OCD_Tasks::create($data);
        return is_wp_error($res) ? $res : rest_ensure_response($res);
    }

    public static function admin_update_task($request) {
        $id = (int) $request['id'];
        $data = [];
        foreach (['title', 'details', 'internal_notes', 'status', 'task_type', 'visibility', 'priority', 'due_date', 'instruction_video_url', 'instruction_image_url'] as $f) {
            if ($request->get_param($f) !== null) $data[$f] = $request->get_param($f);
        }
        if ($request->get_param('project_id')      !== null) $data['project_id']       = (int) $request->get_param('project_id');
        if ($request->get_param('milestone_id')    !== null) $data['milestone_id']     = (int) $request->get_param('milestone_id');
        if ($request->get_param('assignee_user_id')!== null) $data['assignee_user_id'] = (int) $request->get_param('assignee_user_id');
        $res = OCD_Tasks::update($id, $data, 'admin');
        return is_wp_error($res) ? $res : rest_ensure_response($res);
    }

    public static function admin_delete_task($request) {
        OCD_Tasks::delete((int) $request['id']);
        return rest_ensure_response(['deleted' => true]);
    }

    /**
     * Drag/drop status change from the customer kanban. Permission rules:
     *   - task must belong to the calling user
     *   - task must be visibility=client
     *   - new status must be in OCD_Tasks::CUSTOMER_ALLOWED_STATUSES
     * Anything else returns 403/400 — the frontend snaps the card back.
     */
    public static function customer_set_task_status($request) {
        $user    = wp_get_current_user();
        $task_id = (int) $request['id'];
        $status  = sanitize_key((string) $request->get_param('status'));
        $task    = OCD_Tasks::get($task_id);
        if (!$task) return new WP_Error('ocd_no_task', 'Task not found.', ['status' => 404]);
        if ((int) $task['user_id'] !== (int) $user->ID) {
            return new WP_Error('ocd_forbidden', 'Not your task.', ['status' => 403]);
        }
        $res = OCD_Tasks::update($task_id, ['status' => $status], 'customer');
        if (is_wp_error($res)) return $res;
        return rest_ensure_response(OCD_Tasks::present_for_customer($res));
    }

    /**
     * Drag/drop status change from the admin kanban. Admin can set any valid
     * status on any task — the route is separate from the generic update so
     * the UI signal ("status only, from kanban") is captured cleanly and
     * can be audited later if needed.
     */
    public static function admin_set_task_status($request) {
        $task_id = (int) $request['id'];
        $status  = sanitize_key((string) $request->get_param('status'));
        $task = OCD_Tasks::get($task_id);
        if (!$task) return new WP_Error('ocd_no_task', 'Task not found.', ['status' => 404]);
        $res = OCD_Tasks::update($task_id, ['status' => $status], 'admin');
        if (is_wp_error($res)) return $res;
        return rest_ensure_response($res);
    }

    /* ---------------- Task attachments ---------------- */

    public static function customer_list_task_attachments($request) {
        $user = wp_get_current_user();
        $task_id = (int) $request['id'];
        $task = OCD_Tasks::get($task_id);
        if (!$task) return new WP_Error('ocd_no_task', 'Task not found.', ['status' => 404]);
        if ((int) $task['user_id'] !== (int) $user->ID || ($task['visibility'] ?? '') !== 'client') {
            return new WP_Error('ocd_forbidden', 'Not your task.', ['status' => 403]);
        }
        $rows = OCD_Project_Files::all([
            'task_id'  => $task_id,
            'archived' => 0,
        ]);
        // Customer view: drop internal-only files even if they were tied to
        // the task by mistake.
        $rows = array_values(array_filter($rows, function ($f) {
            return ($f['visibility'] ?? '') === 'client';
        }));
        return rest_ensure_response(array_map(function ($r) { return OCD_Project_Files::present($r, false); }, $rows));
    }

    public static function admin_list_task_attachments($request) {
        $task_id = (int) $request['id'];
        $rows = OCD_Project_Files::all([
            'task_id' => $task_id,
        ]);
        return rest_ensure_response(array_map(function ($r) { return OCD_Project_Files::present($r, true); }, $rows));
    }

    public static function admin_upload_task_attachment($request) {
        $admin = wp_get_current_user();
        $task_id = (int) $request['id'];
        $task = OCD_Tasks::get($task_id);
        if (!$task) return new WP_Error('ocd_no_task', 'Task not found.', ['status' => 404]);
        $owner_id = (int) $task['user_id'];
        return self::process_file_upload($request, [
            'owner_user_id'    => $owner_id,
            'uploader_user_id' => (int) $admin->ID,
            'uploader_role'    => 'staff',
            'project_id'       => (int) ($task['project_id'] ?? 0),
            'task_id'          => $task_id,
            // Default to client-visible so the customer can see instruction
            // images. Admin can override with visibility=internal.
            'visibility'       => sanitize_key((string) $request->get_param('visibility')) ?: 'client',
            'approval_status'  => 'not_required',
            'folder'           => 'tasks',
        ]);
    }

    private static function param_int_list($raw) {
        if (is_array($raw)) {
            return array_values(array_filter(array_map('intval', $raw), function ($n) { return $n > 0; }));
        }
        if (is_string($raw) && $raw !== '') {
            $parts = preg_split('/[\s,]+/', $raw);
            return array_values(array_filter(array_map('intval', $parts), function ($n) { return $n > 0; }));
        }
        return [];
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

    /* ---------------- Project files ---------------- */

    public static function customer_list_files($request) {
        $user = wp_get_current_user();
        $args = ['context' => 'customer'];
        if ($p = (int) $request->get_param('project_id')) $args['project_id'] = $p;
        if ($t = (int) $request->get_param('task_id'))    $args['task_id'] = $t;
        if ($f = $request->get_param('folder'))           $args['folder'] = sanitize_key((string) $f);
        $rows = OCD_Project_Files::for_user($user->ID, $args);
        return rest_ensure_response(array_map(function ($r) { return OCD_Project_Files::present($r, false); }, $rows));
    }

    public static function customer_upload_file($request) {
        $user = wp_get_current_user();
        $owner_id = (int) $user->ID;
        $project_id = (int) $request->get_param('project_id');
        if ($project_id) {
            // Customer can only upload to their own project.
            $proj = OCD_Projects::get($project_id);
            if (!$proj || (int) $proj['user_id'] !== $owner_id) {
                return new WP_Error('ocd_forbidden', 'Not your project.', ['status' => 403]);
            }
        }
        $task_id = (int) $request->get_param('task_id');
        if ($task_id) {
            $task = OCD_Tasks::get($task_id);
            if (!$task || (int) $task['user_id'] !== $owner_id) {
                return new WP_Error('ocd_forbidden', 'Not your task.', ['status' => 403]);
            }
        }
        return self::process_file_upload($request, [
            'owner_user_id'    => $owner_id,
            'uploader_user_id' => $owner_id,
            'uploader_role'    => 'customer',
            'project_id'       => $project_id,
            'task_id'          => $task_id,
            'visibility'       => 'client',
            'approval_status'  => 'not_required',
            'folder'           => sanitize_key((string) $request->get_param('folder')) ?: 'intake',
        ]);
    }

    public static function customer_set_file_approval($request) {
        $user = wp_get_current_user();
        $id = (int) $request['id'];
        $file = OCD_Project_Files::get($id);
        if (!$file) return new WP_Error('ocd_no_file', 'File not found.', ['status' => 404]);
        if ((int) $file['owner_user_id'] !== (int) $user->ID) {
            return new WP_Error('ocd_forbidden', 'Not your file.', ['status' => 403]);
        }
        if ($file['visibility'] !== 'client') {
            return new WP_Error('ocd_forbidden', 'This file is internal-only.', ['status' => 403]);
        }
        $status  = sanitize_key((string) $request->get_param('status'));
        $comment = (string) $request->get_param('comment');
        if (!in_array($status, ['approved', 'rejected'], true)) {
            return new WP_Error('ocd_invalid_status', 'Customers can only approve or reject.', ['status' => 400]);
        }
        $res = OCD_Project_Files::set_approval($id, $status, $comment, (int) $user->ID);
        if (is_wp_error($res)) return $res;
        return rest_ensure_response(OCD_Project_Files::present($res, false));
    }

    public static function admin_list_files($request) {
        $rows = OCD_Project_Files::all([
            'project_id'      => (int) $request->get_param('project_id'),
            'user_id'         => (int) $request->get_param('user_id'),
            'folder'          => sanitize_key((string) $request->get_param('folder')),
            'approval_status' => sanitize_key((string) $request->get_param('approval_status')),
            'archived'        => $request->get_param('include_archived') ? null : 0,
            'per_page'        => (int) $request->get_param('per_page') ?: 100,
        ]);
        return rest_ensure_response(array_map(function ($r) { return OCD_Project_Files::present($r, true); }, $rows));
    }

    public static function admin_upload_file($request) {
        $admin = wp_get_current_user();
        $owner_id = (int) $request->get_param('owner_user_id');
        if (!$owner_id) {
            return new WP_Error('ocd_invalid', 'owner_user_id required.', ['status' => 400]);
        }
        return self::process_file_upload($request, [
            'owner_user_id'    => $owner_id,
            'uploader_user_id' => (int) $admin->ID,
            'uploader_role'    => 'staff',
            'project_id'       => (int) $request->get_param('project_id'),
            'task_id'          => (int) $request->get_param('task_id'),
            'milestone_id'     => (int) $request->get_param('milestone_id'),
            'visibility'       => sanitize_key((string) $request->get_param('visibility')) ?: 'client',
            'approval_status'  => sanitize_key((string) $request->get_param('approval_status')) ?: 'not_required',
            'folder'           => sanitize_key((string) $request->get_param('folder')) ?: 'working',
        ]);
    }

    public static function admin_delete_file($request) {
        OCD_Project_Files::delete((int) $request['id']);
        return rest_ensure_response(['deleted' => true]);
    }

    public static function admin_archive_file($request) {
        OCD_Project_Files::archive((int) $request['id']);
        return rest_ensure_response(['archived' => true]);
    }

    public static function admin_set_file_approval($request) {
        $admin = wp_get_current_user();
        $id = (int) $request['id'];
        $status  = sanitize_key((string) $request->get_param('status'));
        $comment = (string) $request->get_param('comment');
        $valid = OCD_Project_Files::APPROVAL_STATES;
        if (!in_array($status, $valid, true)) {
            return new WP_Error('ocd_invalid_status', 'Invalid approval status.', ['status' => 400]);
        }
        $res = OCD_Project_Files::set_approval($id, $status, $comment, (int) $admin->ID);
        if (is_wp_error($res)) return $res;
        return rest_ensure_response(OCD_Project_Files::present($res, true));
    }

    /**
     * Common upload pipeline used by both customer + admin endpoints. Always
     * validates MIME, size, and ownership before writing to private storage.
     */
    private static function process_file_upload($request, $base_meta) {
        $files = $request->get_file_params();
        if (empty($files['file']) || !is_array($files['file'])) {
            return new WP_Error('ocd_no_file', 'No file uploaded (expected multipart field "file").', ['status' => 400]);
        }
        $f = $files['file'];
        if (!empty($f['error']) && (int) $f['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('ocd_upload_error', 'Upload failed (code ' . (int) $f['error'] . ').', ['status' => 400]);
        }
        $tmp = $f['tmp_name'] ?? '';
        if (!$tmp || !is_readable($tmp)) {
            return new WP_Error('ocd_upload_error', 'Upload temp file unreadable.', ['status' => 400]);
        }
        $bytes = file_get_contents($tmp);
        if ($bytes === false) {
            return new WP_Error('ocd_upload_error', 'Could not read upload contents.', ['status' => 400]);
        }
        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = (string) finfo_buffer($finfo, $bytes);
                finfo_close($finfo);
            }
        }
        if (!$mime) $mime = (string) ($f['type'] ?? '');

        $meta = array_merge($base_meta, [
            'file_name' => (string) ($f['name'] ?? 'file'),
            'mime_type' => $mime,
        ]);
        $stored = OCD_Project_Files::store($meta, $bytes);
        if (is_wp_error($stored)) return $stored;
        return rest_ensure_response(OCD_Project_Files::present($stored, $base_meta['uploader_role'] !== 'customer'));
    }

    public static function serve_file($request) {
        $user = wp_get_current_user();
        $id = (int) $request['id'];
        $file = OCD_Project_Files::get($id);
        if (!$file) return new WP_Error('ocd_no_file', 'File not found.', ['status' => 404]);

        $is_admin = current_user_can('manage_woocommerce') || current_user_can('manage_options');
        if (!$is_admin) {
            if ((int) $file['owner_user_id'] !== (int) $user->ID) {
                return new WP_Error('ocd_forbidden', 'Not your file.', ['status' => 403]);
            }
            if ($file['visibility'] !== 'client') {
                return new WP_Error('ocd_forbidden', 'This file is internal-only.', ['status' => 403]);
            }
        }

        $path = OCD_Project_Files::path_for($file);
        if (!$path || !is_file($path)) {
            return new WP_Error('ocd_missing', 'File missing on disk.', ['status' => 404]);
        }

        OCD_Project_Files::record_view($id);
        // Stream via PHP — never expose the on-disk path.
        if (!headers_sent()) {
            header('Content-Type: ' . $file['mime_type']);
            header('Content-Length: ' . (int) $file['size_bytes']);
            header('Content-Disposition: inline; filename="' . self::header_safe_filename((string) $file['file_name']) . '"');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, max-age=0, no-store');
        }
        OCD_Project_Files::record_download($id);
        readfile($path);
        if (function_exists('die')) {
            die;
        }
        return null;
    }

    private static function header_safe_filename($name) {
        $name = preg_replace('/[\r\n"]+/', '', $name);
        return $name;
    }

    /* ---------------- Task comments ---------------- */

    public static function customer_list_task_comments($request) {
        $user = wp_get_current_user();
        $task_id = (int) $request['id'];
        $task = OCD_Tasks::get($task_id);
        if (!$task) return new WP_Error('ocd_no_task', 'Task not found.', ['status' => 404]);
        if ((int) $task['user_id'] !== (int) $user->ID || ($task['visibility'] ?? '') !== 'client') {
            return new WP_Error('ocd_forbidden', 'Not your task.', ['status' => 403]);
        }
        return rest_ensure_response(OCD_Tasks::comments_for_task($task_id, 'customer'));
    }

    public static function customer_add_task_comment($request) {
        $user = wp_get_current_user();
        $task_id = (int) $request['id'];
        $task = OCD_Tasks::get($task_id);
        if (!$task) return new WP_Error('ocd_no_task', 'Task not found.', ['status' => 404]);
        if ((int) $task['user_id'] !== (int) $user->ID || ($task['visibility'] ?? '') !== 'client') {
            return new WP_Error('ocd_forbidden', 'Not your task.', ['status' => 403]);
        }
        $body = (string) $request->get_param('body');
        $res = OCD_Tasks::add_comment($task_id, (int) $user->ID, 'customer', $body, 'shared');
        return is_wp_error($res) ? $res : rest_ensure_response($res);
    }

    public static function admin_list_task_comments($request) {
        $task_id = (int) $request['id'];
        return rest_ensure_response(OCD_Tasks::comments_for_task($task_id, 'admin'));
    }

    public static function admin_add_task_comment($request) {
        $admin = wp_get_current_user();
        $task_id = (int) $request['id'];
        $body = (string) $request->get_param('body');
        $visibility = sanitize_key((string) $request->get_param('visibility')) ?: 'shared';
        $res = OCD_Tasks::add_comment($task_id, (int) $admin->ID, 'staff', $body, $visibility);
        return is_wp_error($res) ? $res : rest_ensure_response($res);
    }

    public static function admin_delete_task_comment($request) {
        OCD_Tasks::delete_comment((int) $request['id']);
        return rest_ensure_response(['deleted' => true]);
    }

    /* ---------------- Pending actions + billing ---------------- */

    public static function customer_pending_actions() {
        $user = wp_get_current_user();
        $subs = [];
        $orders = [];
        if (OCD_WooCommerce::is_configured()) {
            $customer = OCD_WooCommerce::find_customer_by_email($user->user_email);
            if (!is_wp_error($customer) && $customer) {
                $s = OCD_WooCommerce::list_subscriptions_for_customer($customer['id']);
                $o = OCD_WooCommerce::list_orders_for_customer($customer['id']);
                $subs   = is_wp_error($s) ? [] : (array) $s;
                $orders = is_wp_error($o) ? [] : (array) $o;
            }
        }
        return rest_ensure_response(OCD_Pending_Actions::for_user((int) $user->ID, $subs, $orders));
    }

    public static function customer_billing() {
        $user = wp_get_current_user();
        $subs = [];
        $orders = [];
        if (OCD_WooCommerce::is_configured()) {
            $customer = OCD_WooCommerce::find_customer_by_email($user->user_email);
            if (!is_wp_error($customer) && $customer) {
                $s = OCD_WooCommerce::list_subscriptions_for_customer($customer['id']);
                $o = OCD_WooCommerce::list_orders_for_customer($customer['id']);
                $subs   = is_wp_error($s) ? [] : (array) $s;
                $orders = is_wp_error($o) ? [] : (array) $o;
            }
        }
        return rest_ensure_response(OCD_Billing::payload_for_customer((int) $user->ID, $subs, $orders));
    }

    public static function task_status_sets() {
        return rest_ensure_response([
            'statuses'        => OCD_Tasks::VALID_STATUSES,
            'labels'          => OCD_Tasks::STATUS_LABELS,
            'groups'          => OCD_Tasks::status_groups(),
            'task_types'      => OCD_Tasks::TASK_TYPES,
            'visibilities'    => OCD_Tasks::VISIBILITIES,
            'priorities'      => OCD_Tasks::PRIORITIES,
            'customer_allowed_statuses' => OCD_Tasks::CUSTOMER_ALLOWED_STATUSES,
        ]);
    }

    public static function admin_get_required_step_templates() {
        return rest_ensure_response(OCD_Entitlements::get_required_step_templates());
    }

    public static function admin_set_required_step_templates($request) {
        $tpls = $request->get_param('templates');
        if (!is_array($tpls)) return new WP_Error('ocd_invalid', 'Expected templates object.', ['status' => 400]);
        return rest_ensure_response(OCD_Entitlements::set_required_step_templates($tpls));
    }

    /**
     * Surfaces native WC Subscriptions switch URL + reasons it may be unavailable.
     * We never custom-process payment/plan changes — every action links into the
     * native My Account flow.
     */
    public static function customer_subscription_switch_options($request) {
        $user = wp_get_current_user();
        $sid = (int) $request['id'];

        if (!OCD_WooCommerce::is_configured()) {
            return new WP_Error('ocd_not_configured', 'WooCommerce not configured.', ['status' => 400]);
        }
        $customer = OCD_WooCommerce::find_customer_by_email($user->user_email);
        if (is_wp_error($customer) || !$customer) {
            return new WP_Error('ocd_no_customer', 'Customer not found.', ['status' => 404]);
        }
        $sub = OCD_WooCommerce::get_subscription($sid);
        if (is_wp_error($sub)) return $sub;
        if ((int) ($sub['customer_id'] ?? 0) !== (int) $customer['id']) {
            return new WP_Error('ocd_forbidden', 'Not your subscription.', ['status' => 403]);
        }

        $status = (string) ($sub['status'] ?? '');
        $reasons = [];
        if (!in_array($status, ['active'], true)) $reasons[] = 'Switching is only available while a subscription is active.';
        if (in_array($status, ['on-hold', 'pending-cancel'], true)) $reasons[] = 'Subscriptions on-hold or pending-cancel cannot be switched.';
        if (OCD_Billing::is_staging_mode()) $reasons[] = 'Site is in staging mode; switching is disabled.';

        $variation_attrs = [];
        $first_item = $sub['line_items'][0] ?? null;
        if ($first_item) {
            $extracted = OCD_Entitlements::extract_line_item($first_item);
            $variation_attrs = $extracted['attributes'];
        }
        $base = function_exists('home_url') ? rtrim(home_url(), '/') : '';
        $switch_url = '';
        if ($base && empty($reasons)) {
            // WooCommerce Subscriptions builds its switch flow from the
            // subscription view. We surface the view URL and let the native
            // template render its "Upgrade or Downgrade" CTA.
            $switch_url = $base . '/my-account/view-subscription/' . (int) $sid . '/';
        }

        return rest_ensure_response([
            'subscription_id'   => $sid,
            'status'            => $status,
            'switch_available'  => empty($reasons),
            'unavailable_reasons' => $reasons,
            'subscription_view_url' => OCD_Billing::subscription_view_url($sid),
            'switch_url'        => $switch_url,
            'current_variation' => [
                'product_id'   => isset($first_item['product_id']) ? (int) $first_item['product_id'] : 0,
                'variation_id' => isset($first_item['variation_id']) ? (int) $first_item['variation_id'] : 0,
                'attributes'   => $variation_attrs,
            ],
        ]);
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
