<?php
/**
 * Knowledge Base REST API
 * Handles KB search, sync, progress tracking
 */
if (!defined('ABSPATH')) exit;


class Oversee_KB_API {
    
    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }
    
    public static function register_routes() {
        $namespace = 'oversee/v1';
        
        // Search articles
        register_rest_route($namespace, '/kb/search', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'search_articles'],
            'permission_callback' => '__return_true',
            'args' => [
                'q' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
                'limit' => ['default' => 10, 'sanitize_callback' => 'absint']
            ]
        ]);
        
        // Get categories
        register_rest_route($namespace, '/kb/categories', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_categories'],
            'permission_callback' => '__return_true'
        ]);
        
        // Get single article
        register_rest_route($namespace, '/kb/article/(?P<slug>[a-zA-Z0-9-]+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_article'],
            'permission_callback' => '__return_true'
        ]);
        
        // Sync KB (admin only)
        register_rest_route($namespace, '/kb/sync', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'sync_kb'],
            'permission_callback' => [__CLASS__, 'admin_permission_check']
        ]);
        
        // Get sync status
        register_rest_route($namespace, '/kb/sync/status', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_sync_status'],
            'permission_callback' => [__CLASS__, 'admin_permission_check']
        ]);
        
        // Get sync progress (for polling during sync)
        register_rest_route($namespace, '/kb/sync/progress', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_sync_progress'],
            'permission_callback' => [__CLASS__, 'admin_permission_check']
        ]);
        
        // Clear KB data
        register_rest_route($namespace, '/kb/sync/clear', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'clear_kb_data'],
            'permission_callback' => [__CLASS__, 'admin_permission_check']
        ]);
    }
    
    public static function admin_permission_check() {
        return current_user_can('manage_options');
    }
    
    /**
     * Search KB articles
     */
    public static function search_articles($request) {
        $query = $request->get_param('q');
        $limit = min($request->get_param('limit'), 50);
        
        $args = [
            'post_type' => Oversee_KB_CPT::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            's' => $query,
            'orderby' => 'relevance'
        ];
        
        $posts = get_posts($args);
        $results = [];
        
        foreach ($posts as $post) {
            $terms = wp_get_object_terms($post->ID, Oversee_KB_CPT::TAXONOMY);
            $category_name = '';
            $category_slug = '';
            
            if (!empty($terms) && !is_wp_error($terms)) {
                $category_name = $terms[0]->name;
                $category_slug = $terms[0]->slug;
            }
            
            $results[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'slug' => $post->post_name,
                'excerpt' => wp_trim_words($post->post_excerpt ?: $post->post_content, 20),
                'category_name' => $category_name,
                'category_slug' => $category_slug
            ];
        }
        
        return rest_ensure_response($results);
    }
    
    /**
     * Get all categories with article counts
     */
    public static function get_categories() {
        $terms = get_terms([
            'taxonomy' => Oversee_KB_CPT::TAXONOMY,
            'hide_empty' => false,
            'orderby' => 'name'
        ]);
        
        if (is_wp_error($terms)) {
            return rest_ensure_response([]);
        }
        
        $categories = [];
        foreach ($terms as $term) {
            $categories[] = [
                'id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'article_count' => $term->count,
                'icon' => get_term_meta($term->term_id, 'icon', true) ?: 'fa-solid fa-folder'
            ];
        }
        
        return rest_ensure_response($categories);
    }
    
    /**
     * Get single article by slug
     */
    public static function get_article($request) {
        $slug = $request->get_param('slug');
        
        $posts = get_posts([
            'post_type' => Oversee_KB_CPT::POST_TYPE,
            'post_status' => 'publish',
            'name' => $slug,
            'posts_per_page' => 1
        ]);
        
        if (empty($posts)) {
            return new WP_Error('not_found', 'Article not found', ['status' => 404]);
        }
        
        $post = $posts[0];
        $terms = wp_get_object_terms($post->ID, Oversee_KB_CPT::TAXONOMY);
        
        // Increment view count
        $views = (int) get_post_meta($post->ID, '_kb_views', true);
        update_post_meta($post->ID, '_kb_views', $views + 1);
        
        return rest_ensure_response([
            'id' => $post->ID,
            'title' => $post->post_title,
            'slug' => $post->post_name,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'category' => !empty($terms) ? [
                'name' => $terms[0]->name,
                'slug' => $terms[0]->slug
            ] : null,
            'views' => $views + 1,
            'modified' => $post->post_modified
        ]);
    }
    
    /**
     * Start KB sync
     */
    public static function sync_kb() {
        // Clear any previous progress
        delete_option('oversee_kb_sync_progress');
        
        $importer = new Oversee_KB_Importer();
        $stats = $importer->run_full_import();
        
        return rest_ensure_response([
            'success' => true,
            'stats' => $stats
        ]);
    }
    
    /**
     * Get sync status (last sync info)
     */
    public static function get_sync_status() {
        global $wpdb;
        
        $last_sync = get_option('oversee_kb_last_sync');
        $stats = get_option('oversee_kb_sync_stats', []);
        
        // Get real counts from database
        $total_articles = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
            Oversee_KB_CPT::POST_TYPE
        ));
        
        $total_categories = (int) wp_count_terms([
            'taxonomy' => Oversee_KB_CPT::TAXONOMY,
            'hide_empty' => false
        ]);
        
        return rest_ensure_response([
            'last_sync' => $last_sync,
            'total_articles' => $total_articles,
            'total_categories' => $total_categories,
            'stats' => $stats
        ]);
    }
    
    /**
     * Get sync progress (for polling during sync)
     * Uses Importer's direct DB read method to bypass object cache
     */
    public static function get_sync_progress() {
        // Use the importer's method which reads directly from DB
        $progress = Oversee_KB_Importer::get_progress();
        return rest_ensure_response($progress);
    }
    
    /**
     * Clear all KB data
     */
    public static function clear_kb_data() {
        Oversee_KB_Importer::clear_all_data();
        
        return rest_ensure_response([
            'success' => true,
            'message' => 'All KB data cleared'
        ]);
    }
}

// Initialize
Oversee_KB_API::init();