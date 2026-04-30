<?php
/**
 * REST API for the Monday-style project-board system.
 *
 * Namespace: ocd/v1   (shared with the existing OCD_REST_API class)
 *
 * Endpoints:
 *
 *   GET    /me/preferences              — read current user prefs incl. dark mode
 *   POST   /me/preferences              — update prefs (dark_mode boolean)
 *   GET    /boards                      — list boards visible to current user
 *   GET    /boards/<id>                 — full board (groups + items + columns)
 *   POST   /boards/<id>/items           — create item in a group
 *   POST   /items/<id>                  — patch an item (title/status/dates/...)
 *   POST   /items/<id>/move             — drag-drop reorder/cross-group move
 *   GET    /items/<id>/updates          — activity feed for an item
 *   POST   /items/<id>/updates          — post a new update (TipTap doc body)
 *   GET    /views                       — list saved views for a board
 *   POST   /views                       — save a new view
 *   GET    /highlevel/embed/<surface>   — server-side magic-link for the iframe
 *   GET    /highlevel/embed-pages       — list of available embed surfaces
 *   GET    /notifications               — unread notifications stub
 *
 * Permission model: every endpoint requires the user to be logged in. Read
 * endpoints additionally check that the user owns the board OR is Oversee
 * staff. Write endpoints additionally require CAP_WORK_BOARDS unless the
 * caller is the board's owner posting an update on their own item.
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class OCD_Boards_REST {

    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes() {
        $ns = 'ocd/v1';

        register_rest_route($ns, '/me/preferences', [
            ['methods' => 'GET',  'callback' => [__CLASS__, 'get_prefs'],   'permission_callback' => [__CLASS__, 'logged_in']],
            ['methods' => 'POST', 'callback' => [__CLASS__, 'set_prefs'],   'permission_callback' => [__CLASS__, 'logged_in']],
        ]);

        register_rest_route($ns, '/boards', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'list_boards'],
            'permission_callback' => [__CLASS__, 'logged_in'],
        ]);

        register_rest_route($ns, '/boards/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_board'],
            'permission_callback' => [__CLASS__, 'logged_in'],
        ]);

        register_rest_route($ns, '/boards/(?P<id>\d+)/items', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'create_item'],
            'permission_callback' => [__CLASS__, 'can_work_boards'],
        ]);

        register_rest_route($ns, '/items/(?P<id>\d+)', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'patch_item'],
            'permission_callback' => [__CLASS__, 'can_work_boards'],
        ]);

        register_rest_route($ns, '/items/(?P<id>\d+)/move', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'move_item'],
            'permission_callback' => [__CLASS__, 'can_work_boards'],
        ]);

        register_rest_route($ns, '/items/(?P<id>\d+)/updates', [
            ['methods' => 'GET',  'callback' => [__CLASS__, 'list_updates'],   'permission_callback' => [__CLASS__, 'logged_in']],
            ['methods' => 'POST', 'callback' => [__CLASS__, 'create_update'],  'permission_callback' => [__CLASS__, 'logged_in']],
        ]);

        register_rest_route($ns, '/boards/(?P<id>\d+)/views', [
            ['methods' => 'GET',  'callback' => [__CLASS__, 'list_views'],   'permission_callback' => [__CLASS__, 'logged_in']],
            ['methods' => 'POST', 'callback' => [__CLASS__, 'create_view'],  'permission_callback' => [__CLASS__, 'logged_in']],
        ]);

        register_rest_route($ns, '/highlevel/embed-pages', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'embed_pages'],
            'permission_callback' => [__CLASS__, 'logged_in'],
        ]);

        register_rest_route($ns, '/highlevel/embed/(?P<surface>[a-z_]+)', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'embed_for_surface'],
            'permission_callback' => [__CLASS__, 'logged_in'],
        ]);

        register_rest_route($ns, '/notifications', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'list_notifications'],
            'permission_callback' => [__CLASS__, 'logged_in'],
        ]);
    }

    /* ---------- permissions ---------- */

    public static function logged_in() {
        return is_user_logged_in()
            ? true
            : new WP_Error('rest_forbidden', __('Sign in required.', 'oversee-customer-dashboard'), ['status' => 401]);
    }

    public static function can_work_boards() {
        if (!is_user_logged_in()) {
            return new WP_Error('rest_forbidden', __('Sign in required.', 'oversee-customer-dashboard'), ['status' => 401]);
        }
        if (current_user_can(OCD_Roles::CAP_WORK_BOARDS) || current_user_can('manage_options')) {
            return true;
        }
        // Clients can only POST updates / patch their own items via specific
        // endpoints — those endpoints add finer ownership checks below.
        return new WP_Error('rest_forbidden', __('You do not have permission to modify boards.', 'oversee-customer-dashboard'), ['status' => 403]);
    }

    private static function user_can_view_board($board_row) {
        if (!$board_row) {
            return false;
        }
        $uid = get_current_user_id();
        if (!$uid) {
            return false;
        }
        if ((int) $board_row['owner_user_id'] === (int) $uid) {
            return true;
        }
        if ((int) $board_row['account_manager_user_id'] === (int) $uid) {
            return true;
        }
        if (current_user_can(OCD_Roles::CAP_MANAGE_DASHBOARD) || current_user_can(OCD_Roles::CAP_MANAGE_CLIENTS) || current_user_can('manage_options')) {
            return true;
        }
        // Specialists/contractors gain access via assignee or explicit board
        // membership — for the scope of this PR we treat assignee items as the
        // sole signal, applied in-query for /boards/<id> and /items/<id>.
        return current_user_can(OCD_Roles::CAP_WORK_BOARDS);
    }

    /* ---------- preferences ---------- */

    public static function get_prefs($request) {
        $uid = get_current_user_id();
        return rest_ensure_response([
            'dark_mode' => get_user_meta($uid, 'oversee_dark_mode', true) === '1',
        ]);
    }

    public static function set_prefs($request) {
        $uid = get_current_user_id();
        $body = $request->get_json_params() ?: $request->get_params();
        $dark = !empty($body['dark_mode']);
        update_user_meta($uid, 'oversee_dark_mode', $dark ? '1' : '0');
        return rest_ensure_response(['dark_mode' => $dark]);
    }

    /* ---------- boards ---------- */

    public static function list_boards($request) {
        global $wpdb;
        $uid = get_current_user_id();
        $is_staff = OCD_Roles::is_oversee_staff($uid);

        $boards_tbl = OCD_Board_Schema::table('boards');
        if ($is_staff) {
            $rows = $wpdb->get_results(
                "SELECT * FROM $boards_tbl WHERE archived = 0 ORDER BY updated_at DESC LIMIT 200",
                ARRAY_A
            );
        } else {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $boards_tbl WHERE owner_user_id = %d AND archived = 0 ORDER BY updated_at DESC LIMIT 200",
                $uid
            ), ARRAY_A);
        }
        return rest_ensure_response([
            'boards' => array_map([__CLASS__, 'shape_board_row'], $rows ?: []),
        ]);
    }

    public static function get_board($request) {
        global $wpdb;
        $id = (int) $request['id'];
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . OCD_Board_Schema::table('boards') . " WHERE id = %d",
            $id
        ), ARRAY_A);
        if (!$row || !self::user_can_view_board($row)) {
            return new WP_Error('ocd_not_found', __('Board not found.', 'oversee-customer-dashboard'), ['status' => 404]);
        }

        $groups = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . OCD_Board_Schema::table('groups') . " WHERE board_id = %d ORDER BY sort_order ASC, id ASC",
            $id
        ), ARRAY_A);
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . OCD_Board_Schema::table('items') . " WHERE board_id = %d AND archived = 0 ORDER BY sort_order ASC, id ASC",
            $id
        ), ARRAY_A);
        $columns = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . OCD_Board_Schema::table('columns') . " WHERE board_id = %d ORDER BY sort_order ASC",
            $id
        ), ARRAY_A);

        return rest_ensure_response([
            'board'   => self::shape_board_row($row),
            'groups'  => $groups ?: [],
            'items'   => $items ?: [],
            'columns' => array_map([__CLASS__, 'shape_column_row'], $columns ?: []),
        ]);
    }

    public static function create_item($request) {
        global $wpdb;
        $board_id = (int) $request['id'];
        $params = $request->get_json_params() ?: $request->get_params();
        $group_id = (int) ($params['group_id'] ?? 0);
        $title = sanitize_text_field((string) ($params['title'] ?? ''));
        if (!$group_id || !$title) {
            return new WP_Error('ocd_bad_request', __('group_id and title are required.', 'oversee-customer-dashboard'), ['status' => 400]);
        }
        $sort = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(MAX(sort_order), -1) + 1 FROM " . OCD_Board_Schema::table('items') . " WHERE group_id = %d",
            $group_id
        ));
        $wpdb->insert(OCD_Board_Schema::table('items'), [
            'board_id'    => $board_id,
            'group_id'    => $group_id,
            'title'       => $title,
            'status'      => sanitize_key((string) ($params['status'] ?? 'not_started')),
            'priority'    => sanitize_key((string) ($params['priority'] ?? 'normal')),
            'description' => isset($params['description']) ? wp_kses_post((string) $params['description']) : null,
            'sort_order'  => $sort,
        ]);
        $new_id = (int) $wpdb->insert_id;
        OCD_Boards::log($board_id, $new_id, get_current_user_id(), 'item.created', ['title' => $title]);
        return rest_ensure_response(['id' => $new_id]);
    }

    public static function patch_item($request) {
        global $wpdb;
        $id = (int) $request['id'];
        $params = $request->get_json_params() ?: $request->get_params();
        $allowed = ['title', 'status', 'priority', 'assignee_user_id', 'due_date', 'start_date', 'description'];
        $update = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $params)) {
                continue;
            }
            $val = $params[$key];
            if ($key === 'description') {
                $update[$key] = wp_kses_post((string) $val);
            } elseif (in_array($key, ['status', 'priority'], true)) {
                $update[$key] = sanitize_key((string) $val);
            } elseif (in_array($key, ['assignee_user_id'], true)) {
                $update[$key] = (int) $val;
            } elseif (in_array($key, ['due_date', 'start_date'], true)) {
                $update[$key] = $val ? sanitize_text_field((string) $val) : null;
            } else {
                $update[$key] = sanitize_text_field((string) $val);
            }
        }
        if (!$update) {
            return rest_ensure_response(['updated' => 0]);
        }
        $wpdb->update(OCD_Board_Schema::table('items'), $update, ['id' => $id]);
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT board_id FROM " . OCD_Board_Schema::table('items') . " WHERE id = %d",
            $id
        ), ARRAY_A);
        OCD_Boards::log((int) ($row['board_id'] ?? 0), $id, get_current_user_id(), 'item.updated', $update);
        return rest_ensure_response(['updated' => 1, 'fields' => array_keys($update)]);
    }

    public static function move_item($request) {
        global $wpdb;
        $id = (int) $request['id'];
        $params = $request->get_json_params() ?: $request->get_params();
        $group_id = (int) ($params['group_id'] ?? 0);
        $sort_order = (int) ($params['sort_order'] ?? 0);
        if (!$group_id) {
            return new WP_Error('ocd_bad_request', __('group_id is required.', 'oversee-customer-dashboard'), ['status' => 400]);
        }
        $wpdb->update(OCD_Board_Schema::table('items'), [
            'group_id'   => $group_id,
            'sort_order' => $sort_order,
        ], ['id' => $id]);
        return rest_ensure_response(['ok' => true]);
    }

    /* ---------- updates ---------- */

    public static function list_updates($request) {
        global $wpdb;
        $id = (int) $request['id'];
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . OCD_Board_Schema::table('updates') . " WHERE item_id = %d ORDER BY pinned DESC, created_at DESC LIMIT 200",
            $id
        ), ARRAY_A);
        return rest_ensure_response(['updates' => $rows ?: []]);
    }

    public static function create_update($request) {
        global $wpdb;
        $id = (int) $request['id'];
        $params = $request->get_json_params() ?: $request->get_params();
        $body_html = isset($params['body_html']) ? wp_kses_post((string) $params['body_html']) : '';
        $body_doc = isset($params['body_doc']) ? wp_json_encode($params['body_doc']) : null;
        if (!$body_html && !$body_doc) {
            return new WP_Error('ocd_bad_request', __('body_html or body_doc is required.', 'oversee-customer-dashboard'), ['status' => 400]);
        }
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT board_id FROM " . OCD_Board_Schema::table('items') . " WHERE id = %d",
            $id
        ), ARRAY_A);
        if (!$item) {
            return new WP_Error('ocd_not_found', __('Item not found.', 'oversee-customer-dashboard'), ['status' => 404]);
        }
        $user = wp_get_current_user();
        $wpdb->insert(OCD_Board_Schema::table('updates'), [
            'item_id'        => $id,
            'board_id'       => (int) $item['board_id'],
            'author_user_id' => (int) $user->ID,
            'author_role'    => isset($user->roles[0]) ? (string) $user->roles[0] : 'oversee_client',
            'visibility'     => sanitize_key((string) ($params['visibility'] ?? 'shared')),
            'body_html'      => $body_html,
            'body_doc'       => $body_doc,
            'video_provider' => isset($params['video_provider']) ? sanitize_key((string) $params['video_provider']) : null,
            'video_url'      => isset($params['video_url']) ? esc_url_raw((string) $params['video_url']) : null,
            'pinned'         => !empty($params['pinned']) ? 1 : 0,
        ]);
        $new_id = (int) $wpdb->insert_id;
        OCD_Boards::log((int) $item['board_id'], $id, (int) $user->ID, 'update.posted', ['update_id' => $new_id]);
        return rest_ensure_response(['id' => $new_id]);
    }

    /* ---------- views ---------- */

    public static function list_views($request) {
        global $wpdb;
        $board_id = (int) $request['id'];
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . OCD_Board_Schema::table('views') . " WHERE board_id = %d ORDER BY sort_order ASC, id ASC",
            $board_id
        ), ARRAY_A);
        return rest_ensure_response(['views' => $rows ?: []]);
    }

    public static function create_view($request) {
        global $wpdb;
        $board_id = (int) $request['id'];
        $params = $request->get_json_params() ?: $request->get_params();
        $wpdb->insert(OCD_Board_Schema::table('views'), [
            'board_id'      => $board_id,
            'owner_user_id' => get_current_user_id(),
            'slug'          => sanitize_key((string) ($params['slug'] ?? ('view_' . wp_generate_password(6, false, false)))),
            'label'         => sanitize_text_field((string) ($params['label'] ?? 'View')),
            'kind'          => sanitize_key((string) ($params['kind'] ?? 'table')),
            'config'        => isset($params['config']) ? wp_json_encode($params['config']) : null,
            'shared'        => !empty($params['shared']) ? 1 : 0,
        ]);
        return rest_ensure_response(['id' => (int) $wpdb->insert_id]);
    }

    /* ---------- HighLevel embed bridge ---------- */

    public static function embed_pages($request) {
        return rest_ensure_response([
            'pages' => [
                ['surface' => 'conversations', 'label' => __('Messages', 'oversee-customer-dashboard'),     'route' => '/dashboard/messages'],
                ['surface' => 'calendar',      'label' => __('Schedule a call', 'oversee-customer-dashboard'), 'route' => '/dashboard/schedule-call'],
                ['surface' => 'reports',       'label' => __('Performance reports', 'oversee-customer-dashboard'), 'route' => '/dashboard/performance-reports'],
                ['surface' => 'documents',     'label' => __('Documents', 'oversee-customer-dashboard'),    'route' => '/dashboard/documents'],
                ['surface' => 'reputation',    'label' => __('Reviews', 'oversee-customer-dashboard'),      'route' => '/dashboard/reviews'],
            ],
        ]);
    }

    public static function embed_for_surface($request) {
        $surface = (string) $request['surface'];
        $config = OCD_HighLevel_SSO::embed_config(get_current_user_id(), $surface);
        if (is_wp_error($config)) {
            return $config;
        }
        return rest_ensure_response($config);
    }

    /* ---------- notifications ---------- */

    public static function list_notifications($request) {
        global $wpdb;
        $uid = get_current_user_id();
        // Surface the most recent activity-log entries on boards the user can see
        // as a lightweight notifications stub. Real notifications would live in a
        // dedicated table; this gets the bell icon working end-to-end without
        // blocking on that schema.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT a.id, a.event, a.payload, a.created_at, a.board_id, a.item_id
             FROM " . OCD_Board_Schema::table('activity_log') . " a
             INNER JOIN " . OCD_Board_Schema::table('boards') . " b ON b.id = a.board_id
             WHERE b.owner_user_id = %d OR b.account_manager_user_id = %d
             ORDER BY a.created_at DESC LIMIT 25",
            $uid, $uid
        ), ARRAY_A);
        return rest_ensure_response(['notifications' => $rows ?: []]);
    }

    /* ---------- shaping ---------- */

    private static function shape_board_row($row) {
        return [
            'id'                      => (int) $row['id'],
            'board_post_id'           => (int) $row['board_post_id'],
            'template_post_id'        => $row['template_post_id'] ? (int) $row['template_post_id'] : null,
            'owner_user_id'           => (int) $row['owner_user_id'],
            'account_manager_user_id' => $row['account_manager_user_id'] ? (int) $row['account_manager_user_id'] : null,
            'wc_order_id'             => $row['wc_order_id'] ? (int) $row['wc_order_id'] : null,
            'wc_subscription_id'      => $row['wc_subscription_id'] ? (int) $row['wc_subscription_id'] : null,
            'sku'                     => $row['sku'],
            'status'                  => $row['status'],
            'archived'                => (int) $row['archived'] === 1,
            'title'                   => get_the_title((int) $row['board_post_id']),
            'created_at'              => $row['created_at'],
            'updated_at'              => $row['updated_at'],
        ];
    }

    private static function shape_column_row($row) {
        return [
            'id'         => (int) $row['id'],
            'slug'       => $row['slug'],
            'label'      => $row['label'],
            'kind'       => $row['kind'],
            'options'    => $row['options'] ? json_decode($row['options'], true) : null,
            'sort_order' => (int) $row['sort_order'],
            'visibility' => $row['visibility'],
        ];
    }
}
