<?php
/**
 * Spec-aligned Custom Post Types for the Assembly-style Oversee dashboard.
 *
 *   project_board         — one post per active project workspace (per spec).
 *   service_template      — admin-side reusable service definitions
 *                           (mapped to existing WC product SKUs only).
 *   intake_form_template  — reusable form definitions used at onboarding
 *                           and on-demand inside a project.
 *   contract_template     — reusable contract bodies used to generate
 *                           per-client signed contracts (TipTap source).
 *   client_record         — denormalized client profile that exposes a
 *                           stable post id for HighLevel + WooCommerce links.
 *
 * The legacy `board_template` CPT registered in class-ocd-cpt.php remains for
 * backwards compatibility; new code should target the spec CPTs above.
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class Oversee_CPT {

    const TYPE_PROJECT          = 'project_board';
    const TYPE_SERVICE_TEMPLATE = 'service_template';
    const TYPE_FORM_TEMPLATE    = 'intake_form_template';
    const TYPE_CONTRACT_TEMPLATE = 'contract_template';
    const TYPE_CLIENT_RECORD    = 'client_record';

    const META_SERVICE_SKUS    = '_oversee_service_skus';
    const META_SERVICE_TASKS   = '_oversee_service_default_tasks';
    const META_FORM_SCHEMA     = '_oversee_form_schema';
    const META_CONTRACT_BODY   = '_oversee_contract_body_doc';
    const META_CLIENT_USER_ID  = '_oversee_client_user_id';
    const META_PROJECT_OWNER   = '_oversee_project_owner_user_id';
    const META_PROJECT_AM      = '_oversee_project_account_manager_user_id';
    const META_PROJECT_STATUS  = '_oversee_project_status';
    const META_PROJECT_WC_ORDER = '_oversee_project_wc_order_id';
    const META_PROJECT_WC_SUB  = '_oversee_project_wc_subscription_id';

    public static function init() {
        // The legacy class-ocd-cpt.php already registers `project_board`. To
        // avoid double-registration we only register the new CPTs here; the
        // legacy registration is kept until the next major and supplemented
        // with the spec meta below via register_meta().
        add_action('init', [__CLASS__, 'register_post_types'], 6);
        add_action('init', [__CLASS__, 'register_post_meta_extras'], 7);
    }

    public static function register_post_types() {
        $common = [
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => 'oversee-customer-dashboard',
            'show_in_rest'        => false,
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
            'has_archive'         => false,
            'rewrite'             => false,
            'exclude_from_search' => true,
        ];

        register_post_type(self::TYPE_SERVICE_TEMPLATE, array_merge($common, [
            'labels' => [
                'name'          => __('Service Templates', 'oversee-customer-dashboard'),
                'singular_name' => __('Service Template', 'oversee-customer-dashboard'),
                'menu_name'     => __('Service Templates', 'oversee-customer-dashboard'),
            ],
            'supports' => ['title', 'editor'],
        ]));

        register_post_type(self::TYPE_FORM_TEMPLATE, array_merge($common, [
            'labels' => [
                'name'          => __('Intake Form Templates', 'oversee-customer-dashboard'),
                'singular_name' => __('Form Template', 'oversee-customer-dashboard'),
                'menu_name'     => __('Form Templates', 'oversee-customer-dashboard'),
            ],
            'supports' => ['title'],
        ]));

        register_post_type(self::TYPE_CONTRACT_TEMPLATE, array_merge($common, [
            'labels' => [
                'name'          => __('Contract Templates', 'oversee-customer-dashboard'),
                'singular_name' => __('Contract Template', 'oversee-customer-dashboard'),
                'menu_name'     => __('Contract Templates', 'oversee-customer-dashboard'),
            ],
            'supports' => ['title'],
        ]));

        register_post_type(self::TYPE_CLIENT_RECORD, array_merge($common, [
            'labels' => [
                'name'          => __('Client Records', 'oversee-customer-dashboard'),
                'singular_name' => __('Client Record', 'oversee-customer-dashboard'),
                'menu_name'     => __('Client Records', 'oversee-customer-dashboard'),
            ],
            'supports' => ['title'],
        ]));
    }

    public static function register_post_meta_extras() {
        register_post_meta(self::TYPE_SERVICE_TEMPLATE, self::META_SERVICE_SKUS, [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => false,
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_post_meta(self::TYPE_SERVICE_TEMPLATE, self::META_SERVICE_TASKS, [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => false,
        ]);
        register_post_meta(self::TYPE_FORM_TEMPLATE, self::META_FORM_SCHEMA, [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => false,
        ]);
        register_post_meta(self::TYPE_CONTRACT_TEMPLATE, self::META_CONTRACT_BODY, [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => false,
        ]);
        register_post_meta(self::TYPE_CLIENT_RECORD, self::META_CLIENT_USER_ID, [
            'type'              => 'integer',
            'single'            => true,
            'show_in_rest'      => false,
            'sanitize_callback' => 'absint',
        ]);

        // Project_board meta — registered here even though the post type is
        // owned by class-ocd-cpt.php to avoid the double-registration warning.
        register_post_meta(self::TYPE_PROJECT, self::META_PROJECT_OWNER, [
            'type' => 'integer', 'single' => true, 'show_in_rest' => false, 'sanitize_callback' => 'absint',
        ]);
        register_post_meta(self::TYPE_PROJECT, self::META_PROJECT_AM, [
            'type' => 'integer', 'single' => true, 'show_in_rest' => false, 'sanitize_callback' => 'absint',
        ]);
        register_post_meta(self::TYPE_PROJECT, self::META_PROJECT_STATUS, [
            'type' => 'string', 'single' => true, 'show_in_rest' => false, 'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_post_meta(self::TYPE_PROJECT, self::META_PROJECT_WC_ORDER, [
            'type' => 'integer', 'single' => true, 'show_in_rest' => false, 'sanitize_callback' => 'absint',
        ]);
        register_post_meta(self::TYPE_PROJECT, self::META_PROJECT_WC_SUB, [
            'type' => 'integer', 'single' => true, 'show_in_rest' => false, 'sanitize_callback' => 'absint',
        ]);
    }

    public static function find_service_template_for_sku($sku) {
        if (!$sku || !function_exists('get_posts')) {
            return 0;
        }
        $sku = trim((string) $sku);
        $matches = get_posts([
            'post_type'      => self::TYPE_SERVICE_TEMPLATE,
            'post_status'    => 'publish',
            'posts_per_page' => 25,
            'meta_query'     => [
                [
                    'key'     => self::META_SERVICE_SKUS,
                    'value'   => $sku,
                    'compare' => 'LIKE',
                ],
            ],
            'fields'         => 'ids',
        ]);
        foreach ((array) $matches as $post_id) {
            $raw = get_post_meta($post_id, self::META_SERVICE_SKUS, true);
            $skus = array_map('trim', preg_split('/[,;\s]+/', (string) $raw));
            if (in_array($sku, $skus, true)) {
                return (int) $post_id;
            }
        }
        return 0;
    }
}
