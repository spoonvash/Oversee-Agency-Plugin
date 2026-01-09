<?php
/**
 * KB REST API - Uses Custom Post Types
 */

if (!defined('ABSPATH')) {
    exit;
}

class Oversee_KB_API {
    
    public static function init() {
        // Called from rest_api_init hook, so register directly
        self::register_routes();
    }
    
    /**
     * Check capability AND license for API access
     */
    private static function check_permission($capability) {
        if (!current_user_can($capability)) {
            return false;
        }
        
        // Check license
        if (Oversee_License::is_licensed()) {
            return true;
        }
        
        // Check grace period
        $status = Oversee_License::get_status();
        if (!empty($status['in_grace_period']) || $status['status'] === 'grace_period') {
            return true;
        }
        
        return new WP_Error('license_required', 'Valid license required', ['status' => 403]);
    }
    
    public static function register_routes() {
        // Search
        register_rest_route('oversee/v1', '/kb/search', [
            'methods' => 'GET',
            'callback' => function($request) {
                try {
                    $query = sanitize_text_field($request->get_param('q') ?? '');
                    $limit = min(50, max(1, intval($request->get_param('limit') ?? 10)));
                    if (strlen($query) < 2) return new WP_REST_Response([]);
                    $kb = new Oversee_KB();
                    $results = $kb->search($query, $limit);
                    return new WP_REST_Response($results);
                } catch (Exception $e) {
                    return new WP_REST_Response([
                        'error' => true,
                        'message' => $e->getMessage()
                    ], 500);
                }
            },
            'permission_callback' => '__return_true'
        ]);
        
        // Public categories
        register_rest_route('oversee/v1', '/kb/public/categories', [
            'methods' => 'GET',
            'callback' => function($request) {
                $kb = new Oversee_KB();
                return new WP_REST_Response($kb->get_categories());
            },
            'permission_callback' => '__return_true'
        ]);
        
        // List categories (admin)
        register_rest_route('oversee/v1', '/kb/categories', [
            'methods' => 'GET',
            'callback' => function($request) {
                $kb = new Oversee_KB();
                $categories = $kb->get_categories();
                return new WP_REST_Response(['categories' => $categories, 'total' => count($categories)]);
            },
            'permission_callback' => function() { return self::check_permission('oversee_view_dashboard'); }
        ]);
        
        // Create category
        register_rest_route('oversee/v1', '/kb/categories', [
            'methods' => 'POST',
            'callback' => function($request) {
                $name = sanitize_text_field($request->get_param('name'));
                $desc = sanitize_textarea_field($request->get_param('description') ?? '');
                $icon = sanitize_text_field($request->get_param('icon') ?? 'fa-solid fa-folder');
                if (empty($name)) return new WP_Error('missing_name', 'Name is required', ['status' => 400]);
                $result = wp_insert_term($name, Oversee_KB_CPT::TAXONOMY, ['description' => $desc]);
                if (is_wp_error($result)) return $result;
                update_term_meta($result['term_id'], 'icon', $icon);
                $term = get_term($result['term_id'], Oversee_KB_CPT::TAXONOMY);
                return new WP_REST_Response(['id' => $term->term_id, 'slug' => $term->slug, 'name' => $term->name, 'description' => $term->description, 'icon' => $icon, 'article_count' => 0]);
            },
            'permission_callback' => function() { return self::check_permission('oversee_manage_kb'); }
        ]);
        
        // Update category
        register_rest_route('oversee/v1', '/kb/categories/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => function($request) {
                $id = intval($request['id']);
                $name = sanitize_text_field($request->get_param('name'));
                $desc = sanitize_textarea_field($request->get_param('description') ?? '');
                $icon = sanitize_text_field($request->get_param('icon') ?? '');
                wp_update_term($id, Oversee_KB_CPT::TAXONOMY, ['name' => $name, 'description' => $desc]);
                if ($icon) update_term_meta($id, 'icon', $icon);
                $term = get_term($id, Oversee_KB_CPT::TAXONOMY);
                return new WP_REST_Response(['id' => $term->term_id, 'slug' => $term->slug, 'name' => $term->name, 'description' => $term->description, 'icon' => get_term_meta($id, 'icon', true), 'article_count' => $term->count]);
            },
            'permission_callback' => function() { return self::check_permission('oversee_manage_kb'); }
        ]);
        
        // Delete category
        register_rest_route('oversee/v1', '/kb/categories/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => function($request) {
                $id = intval($request['id']);
                wp_delete_term($id, Oversee_KB_CPT::TAXONOMY);
                return new WP_REST_Response(['success' => true]);
            },
            'permission_callback' => function() { return self::check_permission('oversee_manage_kb'); }
        ]);
        
        // List articles
        register_rest_route('oversee/v1', '/kb/articles', [
            'methods' => 'GET',
            'callback' => function($request) {
                $status = sanitize_text_field($request->get_param('status') ?? '');
                $category = sanitize_text_field($request->get_param('category') ?? '');
                $search = sanitize_text_field($request->get_param('search') ?? '');
                $per_page = max(1, min(500, intval($request->get_param('per_page') ?? 100)));
                
                $args = ['post_type' => Oversee_KB_CPT::POST_TYPE, 'posts_per_page' => $per_page, 'orderby' => 'title', 'order' => 'ASC'];
                
                if ($status === 'published') $args['post_status'] = 'publish';
                elseif ($status === 'draft') $args['post_status'] = 'draft';
                else $args['post_status'] = ['publish', 'draft'];
                
                if ($category) {
                    $term = get_term_by('slug', $category, Oversee_KB_CPT::TAXONOMY);
                    if ($term) $args['tax_query'] = [['taxonomy' => Oversee_KB_CPT::TAXONOMY, 'field' => 'term_id', 'terms' => $term->term_id]];
                }
                
                if ($search) $args['s'] = $search;
                
                $posts = get_posts($args);
                $articles = [];
                foreach ($posts as $p) {
                    $terms = wp_get_post_terms($p->ID, Oversee_KB_CPT::TAXONOMY);
                    $articles[] = [
                        'id' => $p->ID,
                        'slug' => $p->post_name,
                        'title' => $p->post_title,
                        'status' => $p->post_status === 'publish' ? 'published' : 'draft',
                        'category_slug' => !empty($terms) ? $terms[0]->slug : '',
                        'category_name' => !empty($terms) ? $terms[0]->name : '',
                        'views' => (int) get_post_meta($p->ID, '_kb_views', true),
                        'created_at' => $p->post_date,
                        'updated_at' => $p->post_modified,
                    ];
                }
                
                // Get accurate counts from database
                $counts = wp_count_posts(Oversee_KB_CPT::POST_TYPE);
                $total_published = (int) ($counts->publish ?? 0);
                $total_draft = (int) ($counts->draft ?? 0);
                $total_all = $total_published + $total_draft;
                
                return new WP_REST_Response([
                    'articles' => $articles, 
                    'total' => count($articles),
                    'total_all' => $total_all,
                    'total_published' => $total_published,
                    'total_draft' => $total_draft
                ]);
            },
            'permission_callback' => function() { return self::check_permission('oversee_view_dashboard'); }
        ]);
        
        // Get single article
        register_rest_route('oversee/v1', '/kb/articles/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => function($request) {
                $post = get_post(intval($request['id']));
                if (!$post || $post->post_type !== Oversee_KB_CPT::POST_TYPE) {
                    return new WP_Error('not_found', 'Article not found', ['status' => 404]);
                }
                $terms = wp_get_post_terms($post->ID, Oversee_KB_CPT::TAXONOMY);
                return new WP_REST_Response([
                    'id' => $post->ID,
                    'slug' => $post->post_name,
                    'title' => $post->post_title,
                    'content' => $post->post_content,
                    'excerpt' => $post->post_excerpt,
                    'status' => $post->post_status === 'publish' ? 'published' : 'draft',
                    'category_slug' => !empty($terms) ? $terms[0]->slug : '',
                    'category_name' => !empty($terms) ? $terms[0]->name : '',
                ]);
            },
            'permission_callback' => function() { return self::check_permission('oversee_view_dashboard'); }
        ]);
        
        // Create article
        register_rest_route('oversee/v1', '/kb/articles', [
            'methods' => 'POST',
            'callback' => function($request) {
                $title = sanitize_text_field($request->get_param('title'));
                $slug = sanitize_title($request->get_param('slug') ?? '');
                $content = wp_kses_post($request->get_param('content') ?? '');
                $excerpt = sanitize_textarea_field($request->get_param('excerpt') ?? '');
                $status = $request->get_param('status') === 'published' ? 'publish' : 'draft';
                $category = sanitize_text_field($request->get_param('category_slug') ?? '');
                
                if (empty($title)) return new WP_Error('missing_title', 'Title is required', ['status' => 400]);
                
                // Generate slug from title if not provided
                if (empty($slug)) {
                    $slug = sanitize_title($title);
                }
                
                $post_id = wp_insert_post([
                    'post_type' => Oversee_KB_CPT::POST_TYPE,
                    'post_title' => $title,
                    'post_name' => $slug,
                    'post_content' => $content,
                    'post_excerpt' => $excerpt ?: wp_trim_words(strip_tags($content), 30),
                    'post_status' => $status,
                ]);
                
                if (is_wp_error($post_id)) return $post_id;
                
                if ($category) {
                    $term = get_term_by('slug', $category, Oversee_KB_CPT::TAXONOMY);
                    if ($term) wp_set_object_terms($post_id, $term->term_id, Oversee_KB_CPT::TAXONOMY);
                }
                
                update_post_meta($post_id, '_kb_views', 0);
                
                // Clear category cache to update article counts
                delete_transient('oversee_kb_cats_db');
                
                $post = get_post($post_id);
                return new WP_REST_Response([
                    'id' => $post->ID, 
                    'slug' => $post->post_name, 
                    'title' => $post->post_title, 
                    'status' => $status === 'publish' ? 'published' : 'draft'
                ]);
            },
            'permission_callback' => function() { return self::check_permission('oversee_manage_kb'); }
        ]);
        
        // Update article
        register_rest_route('oversee/v1', '/kb/articles/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => function($request) {
                $id = intval($request['id']);
                $post = get_post($id);
                if (!$post || $post->post_type !== Oversee_KB_CPT::POST_TYPE) {
                    return new WP_Error('not_found', 'Article not found', ['status' => 404]);
                }
                
                $data = ['ID' => $id];
                
                if ($request->get_param('title') !== null) {
                    $data['post_title'] = sanitize_text_field($request->get_param('title'));
                }
                if ($request->get_param('slug') !== null) {
                    $data['post_name'] = sanitize_title($request->get_param('slug'));
                }
                if ($request->get_param('content') !== null) {
                    $data['post_content'] = wp_kses_post($request->get_param('content'));
                }
                if ($request->get_param('excerpt') !== null) {
                    $data['post_excerpt'] = sanitize_textarea_field($request->get_param('excerpt'));
                }
                if ($request->get_param('status') !== null) {
                    $data['post_status'] = $request->get_param('status') === 'published' ? 'publish' : 'draft';
                }
                
                wp_update_post($data);
                
                $category = $request->get_param('category_slug');
                if ($category !== null) {
                    $category = sanitize_text_field($category);
                    if ($category) {
                        $term = get_term_by('slug', $category, Oversee_KB_CPT::TAXONOMY);
                        if ($term) wp_set_object_terms($id, $term->term_id, Oversee_KB_CPT::TAXONOMY);
                    } else {
                        wp_set_object_terms($id, [], Oversee_KB_CPT::TAXONOMY);
                    }
                }
                
                // Clear category cache to update article counts
                delete_transient('oversee_kb_cats_db');
                
                $post = get_post($id);
                $terms = wp_get_post_terms($post->ID, Oversee_KB_CPT::TAXONOMY);
                
                return new WP_REST_Response([
                    'id' => $post->ID, 
                    'slug' => $post->post_name, 
                    'title' => $post->post_title,
                    'status' => $post->post_status === 'publish' ? 'published' : 'draft',
                    'category_slug' => !empty($terms) ? $terms[0]->slug : ''
                ]);
            },
            'permission_callback' => function() { return self::check_permission('oversee_manage_kb'); }
        ]);
        
        // Delete article
        register_rest_route('oversee/v1', '/kb/articles/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => function($request) {
                $id = intval($request['id']);
                $post = get_post($id);
                if (!$post || $post->post_type !== Oversee_KB_CPT::POST_TYPE) {
                    return new WP_Error('not_found', 'Article not found', ['status' => 404]);
                }
                wp_delete_post($id, true);
                
                // Clear category cache to update article counts
                delete_transient('oversee_kb_cats_db');
                
                return new WP_REST_Response(['success' => true]);
            },
            'permission_callback' => function() { return self::check_permission('oversee_manage_kb'); }
        ]);
        
        // Sync
        register_rest_route('oversee/v1', '/kb/sync', [
            'methods' => 'POST',
            'callback' => function($request) {
                $kb = new Oversee_KB();
                $stats = $kb->sync();
                return new WP_REST_Response(['success' => true, 'stats' => $stats]);
            },
            'permission_callback' => function() { return self::check_permission('oversee_manage_settings'); }
        ]);
        
        // Sync status
        register_rest_route('oversee/v1', '/kb/sync/status', [
            'methods' => 'GET',
            'callback' => function($request) {
                $kb = new Oversee_KB();
                return new WP_REST_Response($kb->get_sync_info());
            },
            'permission_callback' => function() { return self::check_permission('oversee_manage_kb'); }
        ]);
        
        // Sync progress (for polling during sync)
        register_rest_route('oversee/v1', '/kb/sync/progress', [
            'methods' => 'GET',
            'callback' => function($request) {
                $progress = Oversee_KB_Importer::get_progress();
                return new WP_REST_Response($progress);
            },
            'permission_callback' => function() { return self::check_permission('oversee_manage_settings'); }
        ]);
        
        // Clear all KB data
        register_rest_route('oversee/v1', '/kb/sync/clear', [
            'methods' => 'POST',
            'callback' => function($request) {
                Oversee_KB_Importer::clear_all_data();
                return new WP_REST_Response(['success' => true, 'message' => 'All KB data cleared']);
            },
            'permission_callback' => function() { return self::check_permission('oversee_manage_settings'); }
        ]);
        
        // Clear cache
        register_rest_route('oversee/v1', '/kb/cache/clear', [
            'methods' => 'POST',
            'callback' => function($request) {
                $kb = new Oversee_KB();
                $kb->clear_cache();
                return new WP_REST_Response(['message' => 'Cache cleared']);
            },
            'permission_callback' => function() { return self::check_permission('oversee_manage_kb'); }
        ]);
        
        // Remove duplicate articles
        register_rest_route('oversee/v1', '/kb/duplicates/remove', [
            'methods' => 'POST',
            'callback' => function($request) {
                $removed = Oversee_KB_Importer::remove_duplicates();
                return new WP_REST_Response([
                    'success' => true, 
                    'removed' => $removed,
                    'message' => "Removed {$removed} duplicate articles"
                ]);
            },
            'permission_callback' => function() { return self::check_permission('oversee_manage_kb'); }
        ]);
    }
}

