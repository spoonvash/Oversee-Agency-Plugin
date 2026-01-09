<?php
/**
 * Knowledge Base Custom Post Type
 */

if (!defined('ABSPATH')) {
    exit;
}

class Oversee_KB_CPT {
    
    const POST_TYPE = 'kb_article';
    const TAXONOMY = 'kb_category';
    
    public static function init() {
        add_action('init', [__CLASS__, 'register_post_type']);
        add_action('init', [__CLASS__, 'register_taxonomy']);
    }
    
    public static function register_post_type() {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => 'KB Articles',
                'singular_name' => 'KB Article',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_rest' => true,
            'rest_base' => 'kb-articles',
            'supports' => ['title', 'editor', 'excerpt', 'custom-fields'],
            'menu_icon' => 'dashicons-book-alt',
        ]);
    }
    
    public static function register_taxonomy() {
        register_taxonomy(self::TAXONOMY, self::POST_TYPE, [
            'labels' => [
                'name' => 'KB Categories',
                'singular_name' => 'KB Category',
            ],
            'hierarchical' => true,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_rest' => true,
            'rest_base' => 'kb-categories',
        ]);
    }
    
    public static function increment_views($post_id) {
        $views = (int) get_post_meta($post_id, '_kb_views', true);
        update_post_meta($post_id, '_kb_views', $views + 1);
        return $views + 1;
    }
}
