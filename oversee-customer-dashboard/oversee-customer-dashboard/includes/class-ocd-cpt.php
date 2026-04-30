<?php
/**
 * Custom post types for the Oversee project-board system.
 *
 *   project_board   — one CPT post per active customer board. The bulk of the
 *                     row data lives in the ocd_board_* tables; the CPT only
 *                     gives us a stable post_id for permalinks, post-meta,
 *                     and the WP admin list view.
 *
 *   board_template  — a reusable template that defines the columns, default
 *                     groups, default items, and SKU mapping. When a
 *                     WooCommerce order completes the OCD_Boards class spawns
 *                     a project_board from the matching template.
 *
 * Both CPTs are non-public — only Oversee staff/admin see them in WP admin.
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class OCD_CPT {

    const TYPE_BOARD    = 'project_board';
    const TYPE_TEMPLATE = 'board_template';

    /** Meta key on board_template posts: comma-separated SKU(s) it maps to. */
    const META_TEMPLATE_SKUS = '_oversee_template_skus';

    /** Meta key on project_board posts: ocd_board_boards.id (denormalized). */
    const META_BOARD_ROW_ID = '_oversee_board_row_id';

    public static function init() {
        add_action('init', [__CLASS__, 'register_post_types'], 5);
    }

    public static function register_post_types() {
        register_post_type(self::TYPE_BOARD, [
            'labels' => [
                'name'          => __('Project Boards', 'oversee-customer-dashboard'),
                'singular_name' => __('Project Board', 'oversee-customer-dashboard'),
                'menu_name'     => __('Project Boards', 'oversee-customer-dashboard'),
                'add_new_item'  => __('Add Project Board', 'oversee-customer-dashboard'),
                'edit_item'     => __('Edit Project Board', 'oversee-customer-dashboard'),
            ],
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => 'oversee-customer-dashboard',
            'show_in_rest'        => false,
            'supports'            => ['title', 'author'],
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
            'has_archive'         => false,
            'rewrite'             => false,
            'exclude_from_search' => true,
        ]);

        register_post_type(self::TYPE_TEMPLATE, [
            'labels' => [
                'name'          => __('Board Templates', 'oversee-customer-dashboard'),
                'singular_name' => __('Board Template', 'oversee-customer-dashboard'),
                'menu_name'     => __('Board Templates', 'oversee-customer-dashboard'),
                'add_new_item'  => __('Add Board Template', 'oversee-customer-dashboard'),
                'edit_item'     => __('Edit Board Template', 'oversee-customer-dashboard'),
            ],
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => 'oversee-customer-dashboard',
            'show_in_rest'        => false,
            'supports'            => ['title', 'editor'],
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
            'has_archive'         => false,
            'rewrite'             => false,
            'exclude_from_search' => true,
        ]);

        register_post_meta(self::TYPE_TEMPLATE, self::META_TEMPLATE_SKUS, [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => false,
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        register_post_meta(self::TYPE_BOARD, self::META_BOARD_ROW_ID, [
            'type'              => 'integer',
            'single'            => true,
            'show_in_rest'      => false,
            'sanitize_callback' => 'absint',
        ]);
    }

    /**
     * Find the first board_template post that maps to the given SKU. Returns
     * the post ID or 0 when no template matches. SKUs on the template post are
     * stored as a comma-separated string in `_oversee_template_skus`.
     */
    public static function find_template_for_sku($sku) {
        if (!$sku || !function_exists('get_posts')) {
            return 0;
        }
        $sku = trim((string) $sku);
        $matches = get_posts([
            'post_type'      => self::TYPE_TEMPLATE,
            'post_status'    => 'publish',
            'posts_per_page' => 25,
            'meta_query'     => [
                [
                    'key'     => self::META_TEMPLATE_SKUS,
                    'value'   => $sku,
                    'compare' => 'LIKE',
                ],
            ],
            'fields'         => 'ids',
        ]);
        foreach ($matches as $post_id) {
            $raw = get_post_meta($post_id, self::META_TEMPLATE_SKUS, true);
            $skus = array_map('trim', preg_split('/[,;\s]+/', (string) $raw));
            if (in_array($sku, $skus, true)) {
                return (int) $post_id;
            }
        }
        return 0;
    }
}
