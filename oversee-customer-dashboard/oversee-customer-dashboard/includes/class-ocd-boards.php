<?php
/**
 * Project-board manager.
 *
 *   - spawn_from_template(): creates a project_board CPT post and the row in
 *     ocd_board_boards, then materialises the template's groups/columns/items.
 *   - spawn_for_order(): walks an order's line items, finds the board template
 *     for each SKU, and creates a board for each matching item. Variation /
 *     sub-selection meta on the order item is preserved as the new board's
 *     intake_responses row so the delivery team has the original answers.
 *   - archive_board(), restore_board(), reset_monthly_group(): subscription
 *     lifecycle hooks call into these.
 *
 * The default template (when a SKU has no matching board_template post) is a
 * minimal "Kickoff" board with one group and one "Welcome to Oversee" item.
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class OCD_Boards {

    /** Default columns for any newly-spawned board. */
    public static function default_columns() {
        return [
            ['slug' => 'status',      'label' => 'Status',      'kind' => 'status', 'options' => ['not_started', 'in_progress', 'in_review', 'done']],
            ['slug' => 'assignee',    'label' => 'Assignee',    'kind' => 'person'],
            ['slug' => 'due_date',    'label' => 'Due',         'kind' => 'date'],
            ['slug' => 'priority',    'label' => 'Priority',    'kind' => 'priority', 'options' => ['low', 'normal', 'high', 'urgent']],
            ['slug' => 'description', 'label' => 'Description', 'kind' => 'text'],
        ];
    }

    /**
     * Spawn a new board from a board_template CPT post. If template_post_id is
     * 0 the board is built from the default skeleton. Returns the new board
     * row id, or WP_Error on failure.
     */
    public static function spawn_from_template($args) {
        global $wpdb;

        $defaults = [
            'owner_user_id'           => 0,
            'account_manager_user_id' => 0,
            'template_post_id'        => 0,
            'wc_order_id'             => 0,
            'wc_subscription_id'      => 0,
            'wc_product_id'           => 0,
            'wc_variation_id'         => 0,
            'sku'                     => '',
            'title'                   => '',
        ];
        $args = array_merge($defaults, $args);

        if (!$args['owner_user_id']) {
            return new WP_Error('ocd_boards_no_owner', __('Owner user ID required.', 'oversee-customer-dashboard'));
        }

        if (!$args['title']) {
            $args['title'] = $args['template_post_id']
                ? get_the_title($args['template_post_id'])
                : __('Project board', 'oversee-customer-dashboard');
        }

        $post_id = wp_insert_post([
            'post_type'   => OCD_CPT::TYPE_BOARD,
            'post_status' => 'publish',
            'post_title'  => $args['title'],
            'post_author' => (int) $args['owner_user_id'],
        ], true);
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        $boards_tbl = OCD_Board_Schema::table('boards');
        $wpdb->insert($boards_tbl, [
            'board_post_id'           => (int) $post_id,
            'template_post_id'        => $args['template_post_id'] ?: null,
            'owner_user_id'           => (int) $args['owner_user_id'],
            'account_manager_user_id' => $args['account_manager_user_id'] ?: null,
            'wc_order_id'             => $args['wc_order_id'] ?: null,
            'wc_subscription_id'      => $args['wc_subscription_id'] ?: null,
            'wc_product_id'           => $args['wc_product_id'] ?: null,
            'wc_variation_id'         => $args['wc_variation_id'] ?: null,
            'sku'                     => $args['sku'] ?: null,
            'status'                  => 'active',
            'archived'                => 0,
        ]);
        $board_id = (int) $wpdb->insert_id;
        update_post_meta($post_id, OCD_CPT::META_BOARD_ROW_ID, $board_id);

        // Default columns.
        $columns_tbl = OCD_Board_Schema::table('columns');
        $sort = 0;
        foreach (self::default_columns() as $col) {
            $wpdb->insert($columns_tbl, [
                'board_id'   => $board_id,
                'slug'       => $col['slug'],
                'label'      => $col['label'],
                'kind'       => $col['kind'],
                'options'    => isset($col['options']) ? wp_json_encode($col['options']) : null,
                'sort_order' => $sort++,
                'visibility' => 'shared',
            ]);
        }

        // Groups + items: walk the template's content if it provides a JSON
        // skeleton in the post_content; otherwise use a minimal default.
        $skeleton = self::resolve_skeleton($args['template_post_id']);
        $groups_tbl = OCD_Board_Schema::table('groups');
        $items_tbl  = OCD_Board_Schema::table('items');
        $g_sort = 0;
        foreach ($skeleton['groups'] as $group) {
            $wpdb->insert($groups_tbl, [
                'board_id'   => $board_id,
                'title'      => $group['title'],
                'color'      => $group['color'] ?? null,
                'sort_order' => $g_sort++,
                'cycle_label' => $group['cycle_label'] ?? null,
            ]);
            $group_id = (int) $wpdb->insert_id;
            $i_sort = 0;
            foreach (($group['items'] ?? []) as $it) {
                $wpdb->insert($items_tbl, [
                    'board_id'    => $board_id,
                    'group_id'    => $group_id,
                    'title'       => $it['title'],
                    'status'      => $it['status'] ?? 'not_started',
                    'priority'    => $it['priority'] ?? 'normal',
                    'description' => $it['description'] ?? null,
                    'sort_order'  => $i_sort++,
                ]);
            }
        }

        // Activity log + side-effect hook.
        self::log($board_id, null, (int) $args['owner_user_id'], 'board.spawned', [
            'template_post_id' => $args['template_post_id'],
            'sku'              => $args['sku'],
        ]);
        do_action('ocd_board_spawned', $board_id, $args);

        return $board_id;
    }

    /**
     * Walk an order's line items and spawn a board for each SKU that has a
     * matching board_template. Variation IDs and item meta survive into the
     * intake_responses row so the delivery team has the original answers.
     */
    public static function spawn_for_order($order) {
        if (!$order || !method_exists($order, 'get_items')) {
            return [];
        }
        $owner_user_id = (int) (method_exists($order, 'get_user_id') ? $order->get_user_id() : 0);
        if (!$owner_user_id) {
            return [];
        }

        $created = [];
        foreach ($order->get_items() as $item) {
            $product_id   = method_exists($item, 'get_product_id')   ? (int) $item->get_product_id()   : 0;
            $variation_id = method_exists($item, 'get_variation_id') ? (int) $item->get_variation_id() : 0;
            $product = function_exists('wc_get_product') ? wc_get_product($variation_id ?: $product_id) : null;
            $sku = $product && method_exists($product, 'get_sku') ? $product->get_sku() : '';
            if (!$sku) {
                continue;
            }

            $template_id = OCD_CPT::find_template_for_sku($sku);
            if (!$template_id) {
                $template_id = OCD_CPT::find_template_for_sku('default');
            }
            if (!$template_id) {
                continue;
            }

            $title = method_exists($item, 'get_name') ? $item->get_name() : 'Project board';
            $board_id = self::spawn_from_template([
                'owner_user_id'    => $owner_user_id,
                'template_post_id' => $template_id,
                'wc_order_id'      => (int) $order->get_id(),
                'wc_product_id'    => $product_id,
                'wc_variation_id'  => $variation_id,
                'sku'              => $sku,
                'title'            => $title,
            ]);
            if (is_wp_error($board_id)) {
                continue;
            }

            // Capture line-item meta as intake answers (variation attributes,
            // product addons, etc.) so the delivery team has the source-of-truth.
            $answers = [];
            if (method_exists($item, 'get_formatted_meta_data')) {
                foreach ($item->get_formatted_meta_data() as $meta) {
                    $answers[(string) $meta->display_key] = (string) $meta->display_value;
                }
            }
            if ($answers) {
                global $wpdb;
                $wpdb->insert(OCD_Board_Schema::table('intake_responses'), [
                    'board_id'  => $board_id,
                    'item_id'   => null,
                    'user_id'   => $owner_user_id,
                    'form_slug' => 'wc_order_meta',
                    'answers'   => wp_json_encode($answers),
                ]);
            }
            $created[] = $board_id;
        }

        return $created;
    }

    public static function archive_board($board_id) {
        global $wpdb;
        $wpdb->update(
            OCD_Board_Schema::table('boards'),
            ['archived' => 1, 'archived_at' => current_time('mysql'), 'status' => 'archived'],
            ['id' => (int) $board_id]
        );
        self::log($board_id, null, get_current_user_id(), 'board.archived');
    }

    public static function restore_board($board_id) {
        global $wpdb;
        $wpdb->update(
            OCD_Board_Schema::table('boards'),
            ['archived' => 0, 'archived_at' => null, 'status' => 'active'],
            ['id' => (int) $board_id]
        );
        self::log($board_id, null, get_current_user_id(), 'board.restored');
    }

    /**
     * Reset/cycle the monthly group on a board. Marks all current cycle items
     * as completed and creates a fresh group titled with the current month.
     * Used by the WC Subscriptions renewal hook.
     */
    public static function reset_monthly_group($board_id) {
        global $wpdb;
        $now = current_time('mysql');
        $cycle_label = wp_date('F Y');

        // Close any open monthly cycle group.
        $groups_tbl = OCD_Board_Schema::table('groups');
        $items_tbl  = OCD_Board_Schema::table('items');
        $open = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $groups_tbl WHERE board_id = %d AND cycle_label IS NOT NULL AND archived = 0 ORDER BY id DESC LIMIT 1",
            (int) $board_id
        ));
        if ($open) {
            $wpdb->update($groups_tbl, ['archived' => 1], ['id' => (int) $open]);
        }

        // Create the new month's group at the top of the board.
        $wpdb->query($wpdb->prepare(
            "UPDATE $groups_tbl SET sort_order = sort_order + 1 WHERE board_id = %d",
            (int) $board_id
        ));
        $wpdb->insert($groups_tbl, [
            'board_id'    => (int) $board_id,
            'title'       => $cycle_label,
            'cycle_label' => $cycle_label,
            'sort_order'  => 0,
            'created_at'  => $now,
        ]);

        self::log($board_id, null, get_current_user_id(), 'board.cycle_reset', ['label' => $cycle_label]);
    }

    /**
     * Resolve the JSON skeleton stored in a board_template post. Templates
     * use a simple JSON shape in post_content:
     *
     *   { "groups": [ {"title": "...", "items": [{"title": "..."}]} ] }
     *
     * If the template is missing, malformed, or not provided, a default
     * one-group/one-item skeleton is returned so spawn_from_template never
     * produces an empty board.
     */
    public static function resolve_skeleton($template_post_id) {
        if ($template_post_id) {
            $post = get_post((int) $template_post_id);
            if ($post && $post->post_content) {
                $decoded = json_decode($post->post_content, true);
                if (is_array($decoded) && !empty($decoded['groups'])) {
                    return $decoded;
                }
            }
        }
        return [
            'groups' => [
                [
                    'title' => __('Kickoff', 'oversee-customer-dashboard'),
                    'items' => [
                        [
                            'title'       => __('Welcome to Oversee', 'oversee-customer-dashboard'),
                            'description' => __('Your account manager will reach out shortly to schedule kickoff.', 'oversee-customer-dashboard'),
                            'status'      => 'not_started',
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function log($board_id, $item_id, $actor_user_id, $event, $payload = null) {
        global $wpdb;
        $wpdb->insert(OCD_Board_Schema::table('activity_log'), [
            'board_id'      => $board_id ? (int) $board_id : null,
            'item_id'       => $item_id ? (int) $item_id : null,
            'actor_user_id' => $actor_user_id ? (int) $actor_user_id : null,
            'actor_kind'    => 'user',
            'event'         => (string) $event,
            'payload'       => $payload ? wp_json_encode($payload) : null,
        ]);
    }
}
