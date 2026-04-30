<?php
/**
 * Spec-aligned REST namespace `oversee/v1`.
 *
 * Implements the endpoints listed in the latest authoritative spec:
 *
 *   /clients (CRUD)
 *   /clients/<id>/notes (CRUD)
 *   /projects (CRUD)
 *   /projects/<id>/tasks (list/create)
 *   /tasks (CRUD)
 *   /tasks/<id>/comments (list/create)
 *   /forms (CRUD), /forms/<id>/responses
 *   /contracts, /contracts/<id>/sign
 *   /messages, /conversations
 *   /files, /files/<id>/annotations
 *   /automations
 *   /service-templates
 *   /highlevel/sso-link  (POST { user_id, app })
 *   /highlevel/calendar/appointments
 *   /ai/assist
 *
 * Many endpoints return useful skeleton payloads or `WP_Error('not_implemented')`
 * with a 501 status when full delivery logic is intentionally deferred. The
 * legacy `ocd/v1` routes registered in class-ocd-rest-api.php continue to work
 * for backwards compatibility.
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class Oversee_REST_API {

    const NS = 'oversee/v1';

    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes() {
        // -------- Status / me --------
        register_rest_route(self::NS, '/status', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'status'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::NS, '/me', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'me'],
            'permission_callback' => [__CLASS__, 'logged_in'],
        ]);

        // -------- Clients --------
        register_rest_route(self::NS, '/clients', [
            ['methods' => 'GET',  'callback' => [__CLASS__, 'list_clients'],  'permission_callback' => [__CLASS__, 'manage_clients']],
            ['methods' => 'POST', 'callback' => [__CLASS__, 'create_client'], 'permission_callback' => [__CLASS__, 'manage_clients']],
        ]);
        register_rest_route(self::NS, '/clients/(?P<id>\d+)', [
            ['methods' => 'GET',    'callback' => [__CLASS__, 'get_client'],    'permission_callback' => [__CLASS__, 'manage_clients']],
            ['methods' => 'PUT',    'callback' => [__CLASS__, 'update_client'], 'permission_callback' => [__CLASS__, 'manage_clients']],
            ['methods' => 'DELETE', 'callback' => [__CLASS__, 'delete_client'], 'permission_callback' => [__CLASS__, 'manage_clients']],
        ]);
        register_rest_route(self::NS, '/clients/(?P<id>\d+)/notes', [
            ['methods' => 'GET',  'callback' => [__CLASS__, 'list_client_notes'],   'permission_callback' => [__CLASS__, 'manage_clients']],
            ['methods' => 'POST', 'callback' => [__CLASS__, 'create_client_note'],  'permission_callback' => [__CLASS__, 'manage_clients']],
        ]);

        // -------- Projects --------
        register_rest_route(self::NS, '/projects', [
            ['methods' => 'GET',  'callback' => [__CLASS__, 'list_projects'],  'permission_callback' => [__CLASS__, 'logged_in']],
            ['methods' => 'POST', 'callback' => [__CLASS__, 'create_project'], 'permission_callback' => [__CLASS__, 'manage_clients']],
        ]);
        register_rest_route(self::NS, '/projects/(?P<id>\d+)', [
            ['methods' => 'GET',    'callback' => [__CLASS__, 'get_project'],    'permission_callback' => [__CLASS__, 'logged_in']],
            ['methods' => 'PUT',    'callback' => [__CLASS__, 'update_project'], 'permission_callback' => [__CLASS__, 'manage_clients']],
            ['methods' => 'DELETE', 'callback' => [__CLASS__, 'delete_project'], 'permission_callback' => [__CLASS__, 'manage_clients']],
        ]);
        register_rest_route(self::NS, '/projects/(?P<id>\d+)/tasks', [
            ['methods' => 'GET',  'callback' => [__CLASS__, 'list_project_tasks'],  'permission_callback' => [__CLASS__, 'logged_in']],
            ['methods' => 'POST', 'callback' => [__CLASS__, 'create_project_task'], 'permission_callback' => [__CLASS__, 'logged_in']],
        ]);

        // -------- Tasks --------
        register_rest_route(self::NS, '/tasks', [
            ['methods' => 'GET',  'callback' => [__CLASS__, 'list_tasks'],  'permission_callback' => [__CLASS__, 'logged_in']],
            ['methods' => 'POST', 'callback' => [__CLASS__, 'create_task'], 'permission_callback' => [__CLASS__, 'logged_in']],
        ]);
        register_rest_route(self::NS, '/tasks/(?P<id>\d+)', [
            ['methods' => 'GET',    'callback' => [__CLASS__, 'get_task'],    'permission_callback' => [__CLASS__, 'logged_in']],
            ['methods' => 'PUT',    'callback' => [__CLASS__, 'update_task'], 'permission_callback' => [__CLASS__, 'logged_in']],
            ['methods' => 'DELETE', 'callback' => [__CLASS__, 'delete_task'], 'permission_callback' => [__CLASS__, 'manage_clients']],
        ]);
        register_rest_route(self::NS, '/tasks/(?P<id>\d+)/comments', [
            ['methods' => 'GET',  'callback' => [__CLASS__, 'list_task_comments'],  'permission_callback' => [__CLASS__, 'logged_in']],
            ['methods' => 'POST', 'callback' => [__CLASS__, 'create_task_comment'], 'permission_callback' => [__CLASS__, 'logged_in']],
        ]);

        // -------- Forms --------
        register_rest_route(self::NS, '/forms', [
            ['methods' => 'GET',  'callback' => [__CLASS__, 'list_forms'],  'permission_callback' => [__CLASS__, 'logged_in']],
            ['methods' => 'POST', 'callback' => [__CLASS__, 'create_form'], 'permission_callback' => [__CLASS__, 'manage_clients']],
        ]);
        register_rest_route(self::NS, '/forms/(?P<id>\d+)', [
            ['methods' => 'GET',    'callback' => [__CLASS__, 'get_form'],    'permission_callback' => [__CLASS__, 'logged_in']],
            ['methods' => 'PUT',    'callback' => [__CLASS__, 'update_form'], 'permission_callback' => [__CLASS__, 'manage_clients']],
        ]);
        register_rest_route(self::NS, '/forms/(?P<id>\d+)/responses', [
            ['methods' => 'GET',  'callback' => [__CLASS__, 'list_form_responses'],  'permission_callback' => [__CLASS__, 'logged_in']],
            ['methods' => 'POST', 'callback' => [__CLASS__, 'submit_form_response'], 'permission_callback' => [__CLASS__, 'logged_in']],
        ]);

        // -------- Contracts --------
        register_rest_route(self::NS, '/contracts', [
            ['methods' => 'GET',  'callback' => [__CLASS__, 'list_contracts'],  'permission_callback' => [__CLASS__, 'logged_in']],
            ['methods' => 'POST', 'callback' => [__CLASS__, 'create_contract'], 'permission_callback' => [__CLASS__, 'manage_clients']],
        ]);
        register_rest_route(self::NS, '/contracts/(?P<id>\d+)', [
            ['methods' => 'GET',  'callback' => [__CLASS__, 'get_contract'], 'permission_callback' => [__CLASS__, 'logged_in']],
        ]);
        register_rest_route(self::NS, '/contracts/(?P<id>\d+)/sign', [
            ['methods' => 'POST', 'callback' => [__CLASS__, 'sign_contract'], 'permission_callback' => [__CLASS__, 'logged_in']],
        ]);

        // -------- Messages / conversations --------
        register_rest_route(self::NS, '/conversations', [
            ['methods' => 'GET',  'callback' => [__CLASS__, 'list_conversations'],  'permission_callback' => [__CLASS__, 'logged_in']],
            ['methods' => 'POST', 'callback' => [__CLASS__, 'create_conversation'], 'permission_callback' => [__CLASS__, 'logged_in']],
        ]);
        register_rest_route(self::NS, '/conversations/(?P<id>\d+)/messages', [
            ['methods' => 'GET',  'callback' => [__CLASS__, 'list_messages'], 'permission_callback' => [__CLASS__, 'logged_in']],
            ['methods' => 'POST', 'callback' => [__CLASS__, 'send_message'],  'permission_callback' => [__CLASS__, 'logged_in']],
        ]);

        // -------- Files --------
        register_rest_route(self::NS, '/files', [
            ['methods' => 'GET',  'callback' => [__CLASS__, 'list_files'],   'permission_callback' => [__CLASS__, 'logged_in']],
            ['methods' => 'POST', 'callback' => [__CLASS__, 'upload_file'],  'permission_callback' => [__CLASS__, 'logged_in']],
        ]);
        register_rest_route(self::NS, '/files/(?P<id>\d+)/annotations', [
            ['methods' => 'GET',  'callback' => [__CLASS__, 'list_file_annotations'], 'permission_callback' => [__CLASS__, 'logged_in']],
            ['methods' => 'POST', 'callback' => [__CLASS__, 'create_file_annotation'], 'permission_callback' => [__CLASS__, 'logged_in']],
        ]);

        // -------- Automations --------
        register_rest_route(self::NS, '/automations', [
            ['methods' => 'GET',  'callback' => [__CLASS__, 'list_automations'],  'permission_callback' => [__CLASS__, 'manage_clients']],
            ['methods' => 'POST', 'callback' => [__CLASS__, 'create_automation'], 'permission_callback' => [__CLASS__, 'manage_clients']],
        ]);

        // -------- Service templates --------
        register_rest_route(self::NS, '/service-templates', [
            ['methods' => 'GET',  'callback' => [__CLASS__, 'list_service_templates'],  'permission_callback' => [__CLASS__, 'manage_clients']],
            ['methods' => 'POST', 'callback' => [__CLASS__, 'create_service_template'], 'permission_callback' => [__CLASS__, 'manage_clients']],
        ]);

        // -------- HighLevel SSO + calendar --------
        register_rest_route(self::NS, '/highlevel/sso-link', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'highlevel_sso_link'],
            'permission_callback' => [__CLASS__, 'logged_in'],
            'args'     => [
                'app'     => ['type' => 'string', 'required' => true],
                'user_id' => ['type' => 'integer', 'required' => false],
            ],
        ]);
        register_rest_route(self::NS, '/highlevel/calendar/appointments', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'highlevel_calendar_appointments'],
            'permission_callback' => [__CLASS__, 'logged_in'],
        ]);

        // -------- AI assist --------
        register_rest_route(self::NS, '/ai/assist', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'ai_assist'],
            'permission_callback' => [__CLASS__, 'logged_in'],
            'args'     => [
                'prompt'  => ['type' => 'string', 'required' => true],
                'context' => ['type' => 'string', 'required' => false],
            ],
        ]);

        // -------- Home (Assembly-style payload) --------
        register_rest_route(self::NS, '/home', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'home_payload'],
            'permission_callback' => [__CLASS__, 'logged_in'],
        ]);

        // -------- Commerce (real WooCommerce products only) --------
        register_rest_route(self::NS, '/commerce/products', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'commerce_products'],
            'permission_callback' => [__CLASS__, 'logged_in'],
            'args'                => [
                'per_page' => ['type' => 'integer', 'required' => false],
                'search'   => ['type' => 'string', 'required' => false],
                'category' => ['type' => 'string', 'required' => false],
                'type'     => ['type' => 'string', 'required' => false],
            ],
        ]);
        register_rest_route(self::NS, '/commerce/products/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'commerce_product_detail'],
            'permission_callback' => [__CLASS__, 'logged_in'],
        ]);
        register_rest_route(self::NS, '/commerce/products/(?P<id>\d+)/resolve-variation', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'commerce_resolve_variation'],
            'permission_callback' => [__CLASS__, 'logged_in'],
            'args'                => [
                'attributes' => ['required' => true],
            ],
        ]);
    }

    /* ---------------- Permissions ---------------- */

    public static function logged_in() {
        return is_user_logged_in() ? true : new WP_Error('oversee_not_authenticated', 'Authentication required.', ['status' => 401]);
    }

    public static function manage_clients() {
        if (!is_user_logged_in()) {
            return new WP_Error('oversee_not_authenticated', 'Authentication required.', ['status' => 401]);
        }
        if (current_user_can('manage_options') || current_user_can('manage_woocommerce')
            || current_user_can('manage_oversee_clients') || current_user_can('manage_oversee_dashboard')) {
            return true;
        }
        return new WP_Error('oversee_forbidden', 'Insufficient permissions.', ['status' => 403]);
    }

    /* ---------------- Status / me ---------------- */

    public static function status() {
        return rest_ensure_response([
            'plugin'      => 'oversee-dashboard',
            'version'     => defined('OCD_VERSION') ? OCD_VERSION : '2.0.0',
            'namespace'   => self::NS,
            'spec'        => '2.0.0',
        ]);
    }

    public static function me() {
        $u = wp_get_current_user();
        return rest_ensure_response([
            'id'             => (int) $u->ID,
            'email'          => $u->user_email,
            'name'           => $u->display_name,
            'roles'          => array_values((array) $u->roles),
            'is_admin'       => current_user_can('manage_options') || current_user_can('manage_woocommerce')
                                 || current_user_can('manage_oversee_dashboard'),
            'is_oversee_client' => in_array('oversee_client', (array) $u->roles, true) || in_array('customer', (array) $u->roles, true),
            'hl_contact_id'  => (string) get_user_meta($u->ID, 'ohl_contact_id', true),
        ]);
    }

    /* ---------------- Clients ---------------- */

    public static function list_clients($req) {
        $args = [
            'role__in' => ['oversee_client', 'customer'],
            'number'   => max(1, min(200, (int) $req->get_param('per_page') ?: 50)),
            'paged'    => max(1, (int) $req->get_param('page') ?: 1),
        ];
        $search = (string) $req->get_param('search');
        if ($search !== '') {
            $args['search'] = '*' . esc_attr($search) . '*';
        }
        $query = new WP_User_Query($args);
        $rows  = [];
        foreach ($query->get_results() as $u) {
            $rows[] = self::present_client($u);
        }
        return rest_ensure_response([
            'clients' => $rows,
            'total'   => (int) $query->get_total(),
        ]);
    }

    public static function get_client($req) {
        $u = get_userdata((int) $req['id']);
        if (!$u) return new WP_Error('oversee_not_found', 'Client not found.', ['status' => 404]);
        return rest_ensure_response(self::present_client($u));
    }

    public static function create_client($req) {
        $email = sanitize_email((string) $req->get_param('email'));
        $name  = sanitize_text_field((string) $req->get_param('name'));
        if (!$email || !is_email($email)) {
            return new WP_Error('oversee_invalid_email', 'Valid email required.', ['status' => 400]);
        }
        if (email_exists($email)) {
            return new WP_Error('oversee_exists', 'A user with this email already exists.', ['status' => 409]);
        }
        $user_id = wp_insert_user([
            'user_login'   => $email,
            'user_email'   => $email,
            'display_name' => $name ?: $email,
            'first_name'   => sanitize_text_field((string) $req->get_param('first_name')),
            'last_name'    => sanitize_text_field((string) $req->get_param('last_name')),
            'role'         => 'oversee_client',
            'user_pass'    => wp_generate_password(20, true, true),
        ]);
        if (is_wp_error($user_id)) return $user_id;
        return rest_ensure_response(self::present_client(get_userdata($user_id)));
    }

    public static function update_client($req) {
        $u = get_userdata((int) $req['id']);
        if (!$u) return new WP_Error('oversee_not_found', 'Client not found.', ['status' => 404]);
        $update = ['ID' => $u->ID];
        foreach (['first_name', 'last_name', 'display_name'] as $f) {
            if ($req->get_param($f) !== null) $update[$f] = sanitize_text_field((string) $req->get_param($f));
        }
        if ($req->get_param('hl_contact_id') !== null) {
            update_user_meta($u->ID, 'ohl_contact_id', sanitize_text_field((string) $req->get_param('hl_contact_id')));
        }
        wp_update_user($update);
        return rest_ensure_response(self::present_client(get_userdata($u->ID)));
    }

    public static function delete_client($req) {
        $u = get_userdata((int) $req['id']);
        if (!$u) return new WP_Error('oversee_not_found', 'Client not found.', ['status' => 404]);
        // Soft-delete: remove oversee_client role; keep WP user.
        $u = new WP_User($u->ID);
        $u->remove_role('oversee_client');
        return rest_ensure_response(['deleted' => true]);
    }

    public static function list_client_notes($req) {
        global $wpdb;
        $cid = (int) $req['id'];
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . Oversee_Schema::table('client_notes') . ' WHERE client_user_id = %d ORDER BY created_at DESC LIMIT 200',
            $cid
        ), ARRAY_A);
        return rest_ensure_response($rows ?: []);
    }

    public static function create_client_note($req) {
        global $wpdb;
        $cid = (int) $req['id'];
        $body = (string) $req->get_param('body');
        if ($body === '') return new WP_Error('oversee_invalid', 'Body required.', ['status' => 400]);
        $author = wp_get_current_user();
        $ok = $wpdb->insert(Oversee_Schema::table('client_notes'), [
            'client_user_id' => $cid,
            'author_user_id' => (int) $author->ID,
            'body'           => $body,
            'visibility'     => sanitize_key((string) $req->get_param('visibility') ?: 'internal'),
            'created_at'     => current_time('mysql', true),
        ]);
        if (!$ok) return new WP_Error('oversee_db_error', 'Could not insert note.', ['status' => 500]);
        return rest_ensure_response(['id' => (int) $wpdb->insert_id, 'client_user_id' => $cid]);
    }

    /* ---------------- Projects ---------------- */

    public static function list_projects($req) {
        $args = [
            'post_type'      => Oversee_CPT::TYPE_PROJECT,
            'post_status'    => 'publish',
            'posts_per_page' => max(1, min(200, (int) $req->get_param('per_page') ?: 50)),
        ];
        if (!self::user_is_staff()) {
            $args['meta_query'] = [['key' => Oversee_CPT::META_PROJECT_OWNER, 'value' => get_current_user_id()]];
        } elseif ($cid = (int) $req->get_param('client_user_id')) {
            $args['meta_query'] = [['key' => Oversee_CPT::META_PROJECT_OWNER, 'value' => $cid]];
        }
        $posts = get_posts($args);
        return rest_ensure_response(array_map([__CLASS__, 'present_project'], $posts));
    }

    public static function get_project($req) {
        $post = get_post((int) $req['id']);
        if (!$post || $post->post_type !== Oversee_CPT::TYPE_PROJECT) {
            return new WP_Error('oversee_not_found', 'Project not found.', ['status' => 404]);
        }
        if (!self::user_can_see_project($post)) {
            return new WP_Error('oversee_forbidden', 'Not your project.', ['status' => 403]);
        }
        return rest_ensure_response(self::present_project($post));
    }

    public static function create_project($req) {
        $title = sanitize_text_field((string) $req->get_param('title'));
        $owner = (int) $req->get_param('client_user_id');
        if (!$title || !$owner) {
            return new WP_Error('oversee_invalid', 'title + client_user_id required.', ['status' => 400]);
        }
        $post_id = wp_insert_post([
            'post_type'   => Oversee_CPT::TYPE_PROJECT,
            'post_status' => 'publish',
            'post_title'  => $title,
            'post_author' => get_current_user_id(),
        ], true);
        if (is_wp_error($post_id)) return $post_id;
        update_post_meta($post_id, Oversee_CPT::META_PROJECT_OWNER, $owner);
        update_post_meta($post_id, Oversee_CPT::META_PROJECT_STATUS, 'active');
        if ($am = (int) $req->get_param('account_manager_user_id')) {
            update_post_meta($post_id, Oversee_CPT::META_PROJECT_AM, $am);
        }
        return rest_ensure_response(self::present_project(get_post($post_id)));
    }

    public static function update_project($req) {
        $post = get_post((int) $req['id']);
        if (!$post || $post->post_type !== Oversee_CPT::TYPE_PROJECT) {
            return new WP_Error('oversee_not_found', 'Project not found.', ['status' => 404]);
        }
        if ($title = sanitize_text_field((string) $req->get_param('title'))) {
            wp_update_post(['ID' => $post->ID, 'post_title' => $title]);
        }
        if (($status = sanitize_key((string) $req->get_param('status'))) !== '') {
            update_post_meta($post->ID, Oversee_CPT::META_PROJECT_STATUS, $status);
        }
        return rest_ensure_response(self::present_project(get_post($post->ID)));
    }

    public static function delete_project($req) {
        $post = get_post((int) $req['id']);
        if (!$post || $post->post_type !== Oversee_CPT::TYPE_PROJECT) {
            return new WP_Error('oversee_not_found', 'Project not found.', ['status' => 404]);
        }
        wp_trash_post($post->ID);
        return rest_ensure_response(['deleted' => true]);
    }

    public static function list_project_tasks($req) {
        global $wpdb;
        $pid = (int) $req['id'];
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . Oversee_Schema::table('tasks') . ' WHERE project_post_id = %d ORDER BY sort_order ASC, id DESC LIMIT 500',
            $pid
        ), ARRAY_A);
        return rest_ensure_response($rows ?: []);
    }

    public static function create_project_task($req) {
        $req->set_param('project_post_id', (int) $req['id']);
        return self::create_task($req);
    }

    /* ---------------- Tasks ---------------- */

    public static function list_tasks($req) {
        global $wpdb;
        $where = [];
        $args  = [];
        if (!self::user_is_staff()) {
            $where[] = 'client_user_id = %d';
            $args[]  = get_current_user_id();
        } else {
            if ($c = (int) $req->get_param('client_user_id')) { $where[] = 'client_user_id = %d'; $args[] = $c; }
            if ($p = (int) $req->get_param('project_post_id')) { $where[] = 'project_post_id = %d'; $args[] = $p; }
            if ($s = sanitize_key((string) $req->get_param('status'))) { $where[] = 'status = %s'; $args[] = $s; }
        }
        $sql = 'SELECT * FROM ' . Oversee_Schema::table('tasks');
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY id DESC LIMIT 500';
        $rows = $args ? $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);
        return rest_ensure_response($rows ?: []);
    }

    public static function get_task($req) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . Oversee_Schema::table('tasks') . ' WHERE id = %d', (int) $req['id']
        ), ARRAY_A);
        if (!$row) return new WP_Error('oversee_not_found', 'Task not found.', ['status' => 404]);
        if (!self::user_is_staff() && (int) $row['client_user_id'] !== get_current_user_id()) {
            return new WP_Error('oversee_forbidden', 'Not your task.', ['status' => 403]);
        }
        return rest_ensure_response($row);
    }

    public static function create_task($req) {
        global $wpdb;
        $title = sanitize_text_field((string) $req->get_param('title'));
        if ($title === '') return new WP_Error('oversee_invalid', 'title required.', ['status' => 400]);
        $current = get_current_user_id();
        $client = self::user_is_staff() ? (int) $req->get_param('client_user_id') : $current;
        if (!$client) return new WP_Error('oversee_invalid', 'client_user_id required.', ['status' => 400]);
        $data = [
            'client_user_id'   => $client,
            'project_post_id'  => (int) $req->get_param('project_post_id'),
            'assignee_user_id' => (int) $req->get_param('assignee_user_id'),
            'assigner_user_id' => $current,
            'title'            => $title,
            'description'      => (string) $req->get_param('description'),
            'status'           => sanitize_key((string) $req->get_param('status') ?: 'todo'),
            'priority'         => sanitize_key((string) $req->get_param('priority') ?: 'normal'),
            'visibility'       => sanitize_key((string) $req->get_param('visibility') ?: 'shared'),
            'due_date'         => $req->get_param('due_date') ?: null,
            'created_at'       => current_time('mysql', true),
            'updated_at'       => current_time('mysql', true),
        ];
        if (!$wpdb->insert(Oversee_Schema::table('tasks'), $data)) {
            return new WP_Error('oversee_db_error', 'Could not insert task.', ['status' => 500]);
        }
        $data['id'] = (int) $wpdb->insert_id;
        return rest_ensure_response($data);
    }

    public static function update_task($req) {
        global $wpdb;
        $id = (int) $req['id'];
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Oversee_Schema::table('tasks') . ' WHERE id = %d', $id), ARRAY_A);
        if (!$row) return new WP_Error('oversee_not_found', 'Task not found.', ['status' => 404]);
        if (!self::user_is_staff() && (int) $row['client_user_id'] !== get_current_user_id()) {
            return new WP_Error('oversee_forbidden', 'Not your task.', ['status' => 403]);
        }
        $update = [];
        foreach (['title', 'description', 'status', 'priority', 'visibility', 'due_date'] as $f) {
            if ($req->get_param($f) !== null) $update[$f] = sanitize_text_field((string) $req->get_param($f));
        }
        if (!$update) return rest_ensure_response($row);
        $update['updated_at'] = current_time('mysql', true);
        $wpdb->update(Oversee_Schema::table('tasks'), $update, ['id' => $id]);
        $row = array_merge($row, $update);
        return rest_ensure_response($row);
    }

    public static function delete_task($req) {
        global $wpdb;
        $wpdb->delete(Oversee_Schema::table('tasks'), ['id' => (int) $req['id']]);
        return rest_ensure_response(['deleted' => true]);
    }

    public static function list_task_comments($req) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . Oversee_Schema::table('task_comments') . ' WHERE task_id = %d ORDER BY id ASC LIMIT 500',
            (int) $req['id']
        ), ARRAY_A);
        return rest_ensure_response($rows ?: []);
    }

    public static function create_task_comment($req) {
        global $wpdb;
        $body = (string) $req->get_param('body');
        if ($body === '') return new WP_Error('oversee_invalid', 'body required.', ['status' => 400]);
        $ok = $wpdb->insert(Oversee_Schema::table('task_comments'), [
            'task_id'        => (int) $req['id'],
            'author_user_id' => get_current_user_id(),
            'visibility'     => self::user_is_staff() ? sanitize_key((string) $req->get_param('visibility') ?: 'shared') : 'shared',
            'body'           => $body,
            'created_at'     => current_time('mysql', true),
        ]);
        return $ok ? rest_ensure_response(['id' => (int) $wpdb->insert_id]) : new WP_Error('oversee_db_error', 'Insert failed.', ['status' => 500]);
    }

    /* ---------------- Forms ---------------- */

    public static function list_forms() {
        $posts = get_posts(['post_type' => Oversee_CPT::TYPE_FORM_TEMPLATE, 'post_status' => 'publish', 'posts_per_page' => 200]);
        return rest_ensure_response(array_map(function ($p) {
            return ['id' => $p->ID, 'title' => $p->post_title];
        }, $posts));
    }

    public static function get_form($req) {
        $post = get_post((int) $req['id']);
        if (!$post || $post->post_type !== Oversee_CPT::TYPE_FORM_TEMPLATE) {
            return new WP_Error('oversee_not_found', 'Form not found.', ['status' => 404]);
        }
        return rest_ensure_response([
            'id'     => $post->ID,
            'title'  => $post->post_title,
            'schema' => json_decode((string) get_post_meta($post->ID, Oversee_CPT::META_FORM_SCHEMA, true), true),
        ]);
    }

    public static function create_form($req) {
        $id = wp_insert_post([
            'post_type'   => Oversee_CPT::TYPE_FORM_TEMPLATE,
            'post_status' => 'publish',
            'post_title'  => sanitize_text_field((string) $req->get_param('title') ?: 'Untitled form'),
        ], true);
        if (is_wp_error($id)) return $id;
        $schema = $req->get_param('schema');
        if ($schema) update_post_meta($id, Oversee_CPT::META_FORM_SCHEMA, wp_json_encode($schema));
        return rest_ensure_response(['id' => $id]);
    }

    public static function update_form($req) {
        $post = get_post((int) $req['id']);
        if (!$post || $post->post_type !== Oversee_CPT::TYPE_FORM_TEMPLATE) {
            return new WP_Error('oversee_not_found', 'Form not found.', ['status' => 404]);
        }
        if ($t = sanitize_text_field((string) $req->get_param('title'))) {
            wp_update_post(['ID' => $post->ID, 'post_title' => $t]);
        }
        if ($s = $req->get_param('schema')) {
            update_post_meta($post->ID, Oversee_CPT::META_FORM_SCHEMA, wp_json_encode($s));
        }
        return rest_ensure_response(['id' => $post->ID]);
    }

    public static function list_form_responses($req) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . Oversee_Schema::table('form_responses') . ' WHERE form_post_id = %d ORDER BY submitted_at DESC LIMIT 500',
            (int) $req['id']
        ), ARRAY_A);
        return rest_ensure_response($rows ?: []);
    }

    public static function submit_form_response($req) {
        global $wpdb;
        $answers = $req->get_param('answers');
        if (!is_array($answers)) return new WP_Error('oversee_invalid', 'answers object required.', ['status' => 400]);
        $ok = $wpdb->insert(Oversee_Schema::table('form_responses'), [
            'form_post_id'    => (int) $req['id'],
            'client_user_id'  => get_current_user_id(),
            'project_post_id' => (int) $req->get_param('project_post_id'),
            'answers'         => wp_json_encode($answers),
            'status'          => 'submitted',
            'submitted_at'    => current_time('mysql', true),
        ]);
        return $ok ? rest_ensure_response(['id' => (int) $wpdb->insert_id]) : new WP_Error('oversee_db_error', 'Insert failed.', ['status' => 500]);
    }

    /* ---------------- Contracts ---------------- */

    public static function list_contracts($req) {
        global $wpdb;
        $where = [];
        $args  = [];
        if (!self::user_is_staff()) {
            $where[] = 'client_user_id = %d';
            $args[] = get_current_user_id();
        }
        $sql = 'SELECT * FROM ' . Oversee_Schema::table('contracts');
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY id DESC LIMIT 200';
        $rows = $args ? $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);
        return rest_ensure_response($rows ?: []);
    }

    public static function get_contract($req) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . Oversee_Schema::table('contracts') . ' WHERE id = %d', (int) $req['id']
        ), ARRAY_A);
        if (!$row) return new WP_Error('oversee_not_found', 'Contract not found.', ['status' => 404]);
        if (!self::user_is_staff() && (int) $row['client_user_id'] !== get_current_user_id()) {
            return new WP_Error('oversee_forbidden', 'Not your contract.', ['status' => 403]);
        }
        return rest_ensure_response($row);
    }

    public static function create_contract($req) {
        global $wpdb;
        $title = sanitize_text_field((string) $req->get_param('title'));
        $body  = (string) $req->get_param('body_html');
        $client = (int) $req->get_param('client_user_id');
        if (!$title || !$body || !$client) {
            return new WP_Error('oversee_invalid', 'title + body_html + client_user_id required.', ['status' => 400]);
        }
        $ok = $wpdb->insert(Oversee_Schema::table('contracts'), [
            'template_post_id' => (int) $req->get_param('template_post_id'),
            'client_user_id'   => $client,
            'project_post_id'  => (int) $req->get_param('project_post_id'),
            'title'            => $title,
            'body_html'        => wp_kses_post($body),
            'status'           => 'draft',
            'created_at'       => current_time('mysql', true),
        ]);
        return $ok ? rest_ensure_response(['id' => (int) $wpdb->insert_id]) : new WP_Error('oversee_db_error', 'Insert failed.', ['status' => 500]);
    }

    public static function sign_contract($req) {
        global $wpdb;
        $id = (int) $req['id'];
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Oversee_Schema::table('contracts') . ' WHERE id = %d', $id), ARRAY_A);
        if (!$row) return new WP_Error('oversee_not_found', 'Contract not found.', ['status' => 404]);
        if ((int) $row['client_user_id'] !== get_current_user_id()) {
            return new WP_Error('oversee_forbidden', 'Not your contract.', ['status' => 403]);
        }
        $name = sanitize_text_field((string) $req->get_param('signature_name'));
        if (!$name) return new WP_Error('oversee_invalid', 'signature_name required.', ['status' => 400]);
        $wpdb->update(Oversee_Schema::table('contracts'), [
            'status'         => 'signed',
            'signed_at'      => current_time('mysql', true),
            'signature_name' => $name,
            'signature_ip'   => isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '',
            'signature_data' => (string) $req->get_param('signature_data'),
        ], ['id' => $id]);
        return rest_ensure_response(['signed' => true, 'id' => $id]);
    }

    /* ---------------- Conversations / messages ---------------- */

    public static function list_conversations($req) {
        global $wpdb;
        $where = [];
        $args  = [];
        if (!self::user_is_staff()) {
            $where[] = 'client_user_id = %d';
            $args[] = get_current_user_id();
        }
        $sql = 'SELECT * FROM ' . Oversee_Schema::table('conversations');
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY last_message_at DESC LIMIT 200';
        $rows = $args ? $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);
        return rest_ensure_response($rows ?: []);
    }

    public static function create_conversation($req) {
        global $wpdb;
        $client = self::user_is_staff() ? (int) $req->get_param('client_user_id') : get_current_user_id();
        if (!$client) return new WP_Error('oversee_invalid', 'client_user_id required.', ['status' => 400]);
        $ok = $wpdb->insert(Oversee_Schema::table('conversations'), [
            'client_user_id' => $client,
            'project_post_id' => (int) $req->get_param('project_post_id'),
            'title'          => sanitize_text_field((string) $req->get_param('title')),
            'created_at'     => current_time('mysql', true),
        ]);
        return $ok ? rest_ensure_response(['id' => (int) $wpdb->insert_id]) : new WP_Error('oversee_db_error', 'Insert failed.', ['status' => 500]);
    }

    public static function list_messages($req) {
        global $wpdb;
        $cid = (int) $req['id'];
        $conv = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Oversee_Schema::table('conversations') . ' WHERE id = %d', $cid), ARRAY_A);
        if (!$conv) return new WP_Error('oversee_not_found', 'Conversation not found.', ['status' => 404]);
        if (!self::user_is_staff() && (int) $conv['client_user_id'] !== get_current_user_id()) {
            return new WP_Error('oversee_forbidden', 'Not your conversation.', ['status' => 403]);
        }
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . Oversee_Schema::table('messages') . ' WHERE conversation_id = %d ORDER BY id ASC LIMIT 500',
            $cid
        ), ARRAY_A);
        return rest_ensure_response($rows ?: []);
    }

    public static function send_message($req) {
        global $wpdb;
        $cid = (int) $req['id'];
        $body = (string) $req->get_param('body');
        if ($body === '') return new WP_Error('oversee_invalid', 'body required.', ['status' => 400]);
        $u = wp_get_current_user();
        $role = self::user_is_staff() ? 'oversee_staff' : 'oversee_client';
        $ok = $wpdb->insert(Oversee_Schema::table('messages'), [
            'conversation_id' => $cid,
            'sender_user_id'  => (int) $u->ID,
            'sender_role'     => $role,
            'body'            => $body,
            'created_at'      => current_time('mysql', true),
        ]);
        if (!$ok) return new WP_Error('oversee_db_error', 'Insert failed.', ['status' => 500]);
        $wpdb->update(Oversee_Schema::table('conversations'), [
            'last_message_at' => current_time('mysql', true),
        ], ['id' => $cid]);
        return rest_ensure_response(['id' => (int) $wpdb->insert_id]);
    }

    /* ---------------- Files (skeleton) ---------------- */

    public static function list_files($req) {
        global $wpdb;
        $where = [];
        $args  = [];
        if (!self::user_is_staff()) {
            $where[] = 'client_user_id = %d';
            $args[] = get_current_user_id();
        }
        $sql = 'SELECT * FROM ' . Oversee_Schema::table('files');
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY id DESC LIMIT 200';
        $rows = $args ? $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);
        return rest_ensure_response($rows ?: []);
    }

    public static function upload_file($req) {
        // Defer to legacy handler if available, otherwise return not_implemented.
        if (class_exists('OCD_REST_API') && method_exists('OCD_REST_API', 'customer_upload_message_attachment')) {
            return new WP_Error(
                'oversee_use_legacy',
                'Use ocd/v1/customer/files for uploads while the wp_oversee_files migration is in progress.',
                ['status' => 501]
            );
        }
        return new WP_Error('oversee_not_implemented', 'File uploads pending Bunny CDN wiring.', ['status' => 501]);
    }

    public static function list_file_annotations() {
        return rest_ensure_response([]);
    }

    public static function create_file_annotation() {
        return new WP_Error('oversee_not_implemented', 'File annotations require canvas viewer integration.', ['status' => 501]);
    }

    /* ---------------- Automations ---------------- */

    public static function list_automations() {
        global $wpdb;
        $rows = $wpdb->get_results('SELECT * FROM ' . Oversee_Schema::table('automations') . ' ORDER BY id DESC LIMIT 200', ARRAY_A);
        return rest_ensure_response($rows ?: []);
    }

    public static function create_automation($req) {
        global $wpdb;
        $label = sanitize_text_field((string) $req->get_param('label'));
        $trigger_kind = sanitize_key((string) $req->get_param('trigger_kind'));
        $action_kind  = sanitize_key((string) $req->get_param('action_kind'));
        if (!$label || !$trigger_kind || !$action_kind) {
            return new WP_Error('oversee_invalid', 'label + trigger_kind + action_kind required.', ['status' => 400]);
        }
        $ok = $wpdb->insert(Oversee_Schema::table('automations'), [
            'label'          => $label,
            'trigger_kind'   => $trigger_kind,
            'trigger_config' => wp_json_encode($req->get_param('trigger_config') ?: []),
            'action_kind'    => $action_kind,
            'action_config'  => wp_json_encode($req->get_param('action_config') ?: []),
            'scope'          => sanitize_key((string) $req->get_param('scope') ?: 'global'),
            'scope_id'       => (int) $req->get_param('scope_id'),
            'enabled'        => 1,
            'created_at'     => current_time('mysql', true),
        ]);
        return $ok ? rest_ensure_response(['id' => (int) $wpdb->insert_id]) : new WP_Error('oversee_db_error', 'Insert failed.', ['status' => 500]);
    }

    /* ---------------- Service templates ---------------- */

    public static function list_service_templates() {
        $posts = get_posts([
            'post_type'      => Oversee_CPT::TYPE_SERVICE_TEMPLATE,
            'post_status'    => 'publish',
            'posts_per_page' => 200,
        ]);
        return rest_ensure_response(array_map(function ($p) {
            return [
                'id'    => (int) $p->ID,
                'title' => $p->post_title,
                'skus'  => array_filter(array_map('trim', preg_split('/[,;\s]+/', (string) get_post_meta($p->ID, Oversee_CPT::META_SERVICE_SKUS, true)))),
            ];
        }, $posts));
    }

    public static function create_service_template($req) {
        $title = sanitize_text_field((string) $req->get_param('title'));
        $skus  = (array) $req->get_param('skus');
        if (!$title) return new WP_Error('oversee_invalid', 'title required.', ['status' => 400]);
        // Validate SKUs reference existing WC products.
        if ($skus && function_exists('wc_get_product_id_by_sku')) {
            foreach ($skus as $sku) {
                $sku = trim((string) $sku);
                if ($sku === '') continue;
                if (!wc_get_product_id_by_sku($sku)) {
                    return new WP_Error('oversee_unknown_sku', sprintf('SKU "%s" does not match any existing WooCommerce product.', $sku), ['status' => 400]);
                }
            }
        }
        $id = wp_insert_post([
            'post_type'   => Oversee_CPT::TYPE_SERVICE_TEMPLATE,
            'post_status' => 'publish',
            'post_title'  => $title,
            'post_content'=> (string) $req->get_param('description'),
        ], true);
        if (is_wp_error($id)) return $id;
        update_post_meta($id, Oversee_CPT::META_SERVICE_SKUS, implode(',', array_map('trim', $skus)));
        return rest_ensure_response(['id' => (int) $id, 'title' => $title, 'skus' => array_values($skus)]);
    }

    /* ---------------- HighLevel SSO ---------------- */

    public static function highlevel_sso_link($req) {
        $app = sanitize_key((string) $req->get_param('app'));
        $user_id = (int) $req->get_param('user_id') ?: get_current_user_id();

        // Non-admin can only request a link for themselves.
        if (!self::user_is_staff() && $user_id !== get_current_user_id()) {
            return new WP_Error('oversee_forbidden', 'Not your SSO link.', ['status' => 403]);
        }

        $allowed_apps = ['conversations', 'calendar', 'reports', 'reputation', 'documents'];
        if (!in_array($app, $allowed_apps, true)) {
            return new WP_Error('oversee_invalid_app', 'Unknown app.', ['status' => 400, 'allowed_apps' => $allowed_apps]);
        }

        $contact_id = (string) get_user_meta($user_id, 'ohl_contact_id', true);
        if ($contact_id === '') {
            return new WP_Error(
                'oversee_no_hl_contact',
                'No HighLevel contact id linked to this user. Set the ohl_contact_id user meta or wait for the WC sync to backfill.',
                ['status' => 422]
            );
        }

        // Delegate to existing OCD_HighLevel_SSO if available; otherwise return
        // a 501 telling the caller to configure the magic-link endpoint.
        if (class_exists('OCD_HighLevel_SSO') && method_exists('OCD_HighLevel_SSO', 'embed_config')) {
            // Map spec app names to legacy surfaces (1:1 today).
            $surface = $app;
            $cfg = OCD_HighLevel_SSO::embed_config($user_id, $surface);
            if (is_wp_error($cfg)) return $cfg;
            return rest_ensure_response([
                'app'        => $app,
                'sso_url'    => $cfg['embed_url'] ?? '',
                'expires_at' => $cfg['expires_at'] ?? null,
                'contact_id' => $contact_id,
            ]);
        }

        return new WP_Error(
            'oversee_hl_unconfigured',
            'HighLevel SSO endpoint is not configured. Filter `oversee_highlevel_sso_link` or set HIGHLEVEL_MAGIC_LINK_ENDPOINT.',
            ['status' => 501]
        );
    }

    public static function highlevel_calendar_appointments($req) {
        $contact_id = (string) get_user_meta(get_current_user_id(), 'ohl_contact_id', true);
        if ($contact_id === '') {
            return new WP_Error('oversee_no_hl_contact', 'No HighLevel contact id linked to this user.', ['status' => 422]);
        }
        if (class_exists('OCD_HighLevel') && method_exists('OCD_HighLevel', 'list_appointments_for_contact')) {
            $rows = OCD_HighLevel::list_appointments_for_contact($contact_id);
            return is_wp_error($rows) ? $rows : rest_ensure_response($rows);
        }
        return rest_ensure_response(['appointments' => []]);
    }

    /* ---------------- AI assist ---------------- */

    public static function ai_assist($req) {
        $key = self::get_ai_api_key();
        if (!$key) {
            return new WP_Error(
                'oversee_ai_unconfigured',
                'AI assist requires an OpenAI/Anthropic API key. Set OVERSEE_AI_API_KEY in wp-config.php or env.',
                ['status' => 501]
            );
        }
        $prompt = trim((string) $req->get_param('prompt'));
        if ($prompt === '') {
            return new WP_Error('oversee_invalid', 'prompt required.', ['status' => 400]);
        }

        // Filter hook lets integrators inject the actual completion call. We
        // never call out from core to avoid hard-coding a vendor; the contract
        // is: filter receives ['prompt', 'context', 'user_id'] and returns
        // either ['text' => ...] or a WP_Error.
        $result = apply_filters('oversee_ai_assist', null, [
            'prompt'  => $prompt,
            'context' => (string) $req->get_param('context'),
            'user_id' => get_current_user_id(),
            'api_key' => $key,
        ]);

        if ($result === null) {
            return new WP_Error(
                'oversee_ai_unconfigured',
                'AI assist filter not registered. Hook `oversee_ai_assist` to wire the provider.',
                ['status' => 501]
            );
        }
        if (is_wp_error($result)) return $result;
        return rest_ensure_response($result);
    }

    private static function get_ai_api_key() {
        if (defined('OVERSEE_AI_API_KEY') && OVERSEE_AI_API_KEY) {
            return (string) OVERSEE_AI_API_KEY;
        }
        $env = getenv('OVERSEE_AI_API_KEY');
        if ($env) return (string) $env;
        $opts = get_option('oversee_settings', []);
        if (!empty($opts['ai_api_key'])) return (string) $opts['ai_api_key'];
        return '';
    }

    /* ---------------- Home payload ---------------- */

    public static function home_payload() {
        $u = wp_get_current_user();
        global $wpdb;

        $invoices_pending = 0;
        $contracts_pending = 0;
        $tasks_pending = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . Oversee_Schema::table('tasks') . ' WHERE client_user_id = %d AND status NOT IN (\'done\',\'archived\')',
            $u->ID
        ));
        $forms_pending = 0;

        if (function_exists('wc_get_orders')) {
            $orders = wc_get_orders([
                'customer_id' => $u->ID,
                'status'      => ['pending', 'on-hold'],
                'limit'       => 50,
            ]);
            $invoices_pending = is_array($orders) ? count($orders) : 0;
        }
        $contracts_pending = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . Oversee_Schema::table('contracts') . ' WHERE client_user_id = %d AND status IN (\'sent\',\'draft\')',
            $u->ID
        ));

        // Active projects (last 5).
        $projects = get_posts([
            'post_type'      => Oversee_CPT::TYPE_PROJECT,
            'post_status'    => 'publish',
            'posts_per_page' => 5,
            'meta_query'     => [['key' => Oversee_CPT::META_PROJECT_OWNER, 'value' => (int) $u->ID]],
        ]);

        // Recent updates (activity log).
        $updates = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . Oversee_Schema::table('activity_log')
            . ' WHERE actor_user_id = %d OR (entity_type = %s AND entity_id IN (SELECT ID FROM ' . $wpdb->posts . ' WHERE post_author = %d))'
            . ' ORDER BY created_at DESC LIMIT 10',
            $u->ID, 'project_board', $u->ID
        ), ARRAY_A);

        return rest_ensure_response([
            'user' => [
                'id'    => (int) $u->ID,
                'name'  => $u->display_name,
                'email' => $u->user_email,
                'first_name' => $u->first_name ?: $u->display_name,
            ],
            'actions' => [
                'invoices'  => $invoices_pending,
                'contracts' => $contracts_pending,
                'tasks'     => $tasks_pending,
                'forms'     => $forms_pending,
            ],
            'team' => self::team_for_user($u->ID),
            'active_projects' => array_map([__CLASS__, 'present_project'], $projects),
            'recent_updates' => array_map(function ($r) {
                return [
                    'id'         => (int) $r['id'],
                    'event'      => $r['event'],
                    'entity'     => $r['entity_type'],
                    'created_at' => $r['created_at'],
                    'payload'    => json_decode((string) ($r['payload'] ?? ''), true),
                ];
            }, $updates ?: []),
        ]);
    }

    private static function team_for_user($user_id) {
        // Returns the account manager + assigned specialists for the user's
        // active projects. If none, return an empty list — the SPA shows the
        // "no team yet" empty state.
        $am_ids = [];
        $projects = get_posts([
            'post_type'      => Oversee_CPT::TYPE_PROJECT,
            'post_status'    => 'publish',
            'posts_per_page' => 25,
            'meta_query'     => [['key' => Oversee_CPT::META_PROJECT_OWNER, 'value' => (int) $user_id]],
        ]);
        foreach ($projects as $p) {
            $am = (int) get_post_meta($p->ID, Oversee_CPT::META_PROJECT_AM, true);
            if ($am) $am_ids[$am] = true;
        }
        $rows = [];
        foreach (array_keys($am_ids) as $uid) {
            $u = get_userdata($uid);
            if (!$u) continue;
            $rows[] = [
                'id'    => (int) $u->ID,
                'name'  => $u->display_name,
                'email' => $u->user_email,
                'role'  => 'Account Manager',
                'avatar' => get_avatar_url($u->ID, ['size' => 64]),
            ];
        }
        return $rows;
    }

    /* ---------------- Commerce ---------------- */

    public static function commerce_products($req) {
        $payload = OCD_Commerce::products([
            'per_page' => (int) ($req->get_param('per_page') ?: 50),
            'search'   => (string) ($req->get_param('search') ?: ''),
            'category' => (string) ($req->get_param('category') ?: ''),
            'type'     => (string) ($req->get_param('type') ?: ''),
        ]);
        return rest_ensure_response($payload);
    }

    public static function commerce_product_detail($req) {
        $res = OCD_Commerce::product_detail((int) $req['id']);
        return is_wp_error($res) ? $res : rest_ensure_response($res);
    }

    public static function commerce_resolve_variation($req) {
        $attrs = $req->get_param('attributes');
        if (!is_array($attrs)) {
            return new WP_Error('oversee_invalid_attributes', 'Attributes must be an object.', ['status' => 400]);
        }
        $clean = [];
        foreach ($attrs as $k => $v) {
            $clean[(string) $k] = is_scalar($v) ? (string) $v : '';
        }
        $res = OCD_Commerce::resolve_variation((int) $req['id'], $clean);
        return is_wp_error($res) ? $res : rest_ensure_response($res);
    }

    /* ---------------- Helpers ---------------- */

    public static function user_is_staff() {
        return current_user_can('manage_options') || current_user_can('manage_woocommerce')
            || current_user_can('manage_oversee_dashboard') || current_user_can('manage_oversee_clients')
            || current_user_can('work_oversee_boards');
    }

    public static function user_can_see_project($post) {
        if (self::user_is_staff()) return true;
        $owner = (int) get_post_meta($post->ID, Oversee_CPT::META_PROJECT_OWNER, true);
        return $owner === get_current_user_id();
    }

    public static function present_client($u) {
        return [
            'id'             => (int) $u->ID,
            'email'          => $u->user_email,
            'name'           => $u->display_name,
            'first_name'     => $u->first_name,
            'last_name'      => $u->last_name,
            'roles'          => array_values((array) $u->roles),
            'hl_contact_id'  => (string) get_user_meta($u->ID, 'ohl_contact_id', true),
            'registered'     => $u->user_registered,
        ];
    }

    public static function present_project($post) {
        return [
            'id'              => (int) $post->ID,
            'title'           => $post->post_title,
            'status'          => (string) get_post_meta($post->ID, Oversee_CPT::META_PROJECT_STATUS, true),
            'client_user_id'  => (int) get_post_meta($post->ID, Oversee_CPT::META_PROJECT_OWNER, true),
            'account_manager_user_id' => (int) get_post_meta($post->ID, Oversee_CPT::META_PROJECT_AM, true),
            'wc_order_id'     => (int) get_post_meta($post->ID, Oversee_CPT::META_PROJECT_WC_ORDER, true),
            'wc_subscription_id' => (int) get_post_meta($post->ID, Oversee_CPT::META_PROJECT_WC_SUB, true),
            'created_at'      => $post->post_date_gmt,
        ];
    }
}
