<?php
/**
 * KB REST API
 * 
 * REST API endpoints for managing custom KB articles and categories.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Oversee_KB_API {
    
    /**
     * Initialize KB API
     */
    public static function init() {
        self::register_public_routes();
        self::register_article_routes();
        self::register_category_routes();
    }
    
    /**
     * Register public routes (no auth required)
     */
    private static function register_public_routes() {
        // Public search endpoint
        register_rest_route('oversee/v1', '/kb/search', [
            'methods' => 'GET',
            'callback' => function($request) {
                $query = sanitize_text_field($request->get_param('q') ?? '');
                $limit = min(50, max(1, intval($request->get_param('limit') ?? 10)));
                
                if (strlen($query) < 2) {
                    return new WP_REST_Response([]);
                }
                
                $kb = new Oversee_KB();
                $results = $kb->search($query, $limit);
                
                return new WP_REST_Response($results);
            },
            'permission_callback' => '__return_true'
        ]);
        
        // Public categories endpoint
        register_rest_route('oversee/v1', '/kb/public/categories', [
            'methods' => 'GET',
            'callback' => function($request) {
                $kb = new Oversee_KB();
                $categories = $kb->get_categories();
                
                return new WP_REST_Response($categories);
            },
            'permission_callback' => '__return_true'
        ]);
        
        // Clear KB cache (admin only)
        register_rest_route('oversee/v1', '/kb/cache/clear', [
            'methods' => 'POST',
            'callback' => function($request) {
                $kb = new Oversee_KB();
                $kb->clear_cache();
                
                return new WP_REST_Response(['message' => 'KB cache cleared']);
            },
            'permission_callback' => function() {
                return current_user_can('oversee_manage_kb');
            }
        ]);
    }
    
    /**
     * Register article routes
     */
    private static function register_article_routes() {
        // List articles (from local database)
        register_rest_route('oversee/v1', '/kb/articles', [
            'methods' => 'GET',
            'callback' => function($request) {
                global $wpdb;
                
                $status = sanitize_text_field($request->get_param('status') ?? '');
                $category = sanitize_text_field($request->get_param('category') ?? '');
                $search = sanitize_text_field($request->get_param('search') ?? '');
                $page = max(1, intval($request->get_param('page') ?? 1));
                $per_page = max(1, min(100, intval($request->get_param('per_page') ?? 20)));
                $offset = ($page - 1) * $per_page;
                
                $where = ['1=1'];
                $values = [];
                
                if ($status) {
                    $where[] = 'a.status = %s';
                    $values[] = $status;
                }
                
                if ($category) {
                    $where[] = 'a.category_slug = %s';
                    $values[] = $category;
                }
                
                if ($search) {
                    $where[] = '(a.title LIKE %s OR a.content LIKE %s)';
                    $values[] = '%' . $wpdb->esc_like($search) . '%';
                    $values[] = '%' . $wpdb->esc_like($search) . '%';
                }
                
                $where_sql = implode(' AND ', $where);
                
                // Get total count
                $count_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}oversee_kb_articles a WHERE {$where_sql}";
                if (!empty($values)) {
                    $count_sql = $wpdb->prepare($count_sql, $values);
                }
                $total = (int) $wpdb->get_var($count_sql);
                
                // Get articles
                $sql = "SELECT a.*, c.name as category_name
                        FROM {$wpdb->prefix}oversee_kb_articles a
                        LEFT JOIN {$wpdb->prefix}oversee_kb_categories c ON a.category_slug = c.slug
                        WHERE {$where_sql}
                        ORDER BY a.sort_order ASC, a.title ASC
                        LIMIT %d OFFSET %d";
                
                $values[] = $per_page;
                $values[] = $offset;
                
                $articles = $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);
                
                return new WP_REST_Response([
                    'articles' => $articles,
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $per_page,
                    'total_pages' => ceil($total / $per_page)
                ]);
            },
            'permission_callback' => function() {
                return current_user_can('oversee_view_dashboard');
            }
        ]);
        
        // Get single article
        register_rest_route('oversee/v1', '/kb/articles/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => function($request) {
                global $wpdb;
                
                $id = (int) $request->get_param('id');
                
                $article = $wpdb->get_row($wpdb->prepare(
                    "SELECT a.*, c.name as category_name
                     FROM {$wpdb->prefix}oversee_kb_articles a
                     LEFT JOIN {$wpdb->prefix}oversee_kb_categories c ON a.category_slug = c.slug
                     WHERE a.id = %d",
                    $id
                ), ARRAY_A);
                
                if (!$article) {
                    return new WP_Error('not_found', 'Article not found', ['status' => 404]);
                }
                
                return new WP_REST_Response($article);
            },
            'permission_callback' => function() {
                return current_user_can('oversee_view_dashboard');
            }
        ]);
        
        // Create article
        register_rest_route('oversee/v1', '/kb/articles', [
            'methods' => 'POST',
            'callback' => function($request) {
                global $wpdb;
                
                $title = sanitize_text_field($request->get_param('title'));
                $content = wp_kses_post($request->get_param('content'));
                $category_slug = sanitize_title($request->get_param('category_slug'));
                $excerpt = sanitize_textarea_field($request->get_param('excerpt') ?? '');
                $status = in_array($request->get_param('status'), ['published', 'draft']) 
                    ? $request->get_param('status') 
                    : 'draft';
                
                if (empty($title)) {
                    return new WP_Error('missing_title', 'Title is required', ['status' => 400]);
                }
                
                if (empty($category_slug)) {
                    return new WP_Error('missing_category', 'Category is required', ['status' => 400]);
                }
                
                // Generate slug
                $slug = sanitize_title($title);
                
                // Check for duplicate
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}oversee_kb_articles 
                     WHERE slug = %s AND category_slug = %s",
                    $slug, $category_slug
                ));
                
                if ($exists) {
                    $slug .= '-' . time();
                }
                
                $result = $wpdb->insert(
                    $wpdb->prefix . 'oversee_kb_articles',
                    [
                        'slug' => $slug,
                        'category_slug' => $category_slug,
                        'title' => $title,
                        'content' => $content,
                        'excerpt' => $excerpt,
                        'status' => $status,
                        'author_id' => get_current_user_id()
                    ],
                    ['%s', '%s', '%s', '%s', '%s', '%s', '%d']
                );
                
                if ($result === false) {
                    return new WP_Error('db_error', 'Failed to create article', ['status' => 500]);
                }
                
                Oversee_Logger::info('Article created', [
                    'id' => $wpdb->insert_id,
                    'title' => $title
                ]);
                
                return new WP_REST_Response([
                    'id' => $wpdb->insert_id,
                    'slug' => $slug,
                    'message' => 'Article created'
                ], 201);
            },
            'permission_callback' => function() {
                return current_user_can('oversee_manage_kb');
            }
        ]);
        
        // Update article
        register_rest_route('oversee/v1', '/kb/articles/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => function($request) {
                global $wpdb;
                
                $id = (int) $request->get_param('id');
                
                // Check exists
                $article = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}oversee_kb_articles WHERE id = %d",
                    $id
                ));
                
                if (!$article) {
                    return new WP_Error('not_found', 'Article not found', ['status' => 404]);
                }
                
                $updates = [];
                $formats = [];
                
                if ($request->has_param('title')) {
                    $updates['title'] = sanitize_text_field($request->get_param('title'));
                    $formats[] = '%s';
                }
                
                if ($request->has_param('content')) {
                    $updates['content'] = wp_kses_post($request->get_param('content'));
                    $formats[] = '%s';
                }
                
                if ($request->has_param('excerpt')) {
                    $updates['excerpt'] = sanitize_textarea_field($request->get_param('excerpt'));
                    $formats[] = '%s';
                }
                
                if ($request->has_param('status')) {
                    $status = $request->get_param('status');
                    if (in_array($status, ['published', 'draft'])) {
                        $updates['status'] = $status;
                        $formats[] = '%s';
                    }
                }
                
                if ($request->has_param('category_slug')) {
                    $updates['category_slug'] = sanitize_title($request->get_param('category_slug'));
                    $formats[] = '%s';
                }
                
                if ($request->has_param('sort_order')) {
                    $updates['sort_order'] = (int) $request->get_param('sort_order');
                    $formats[] = '%d';
                }
                
                if (empty($updates)) {
                    return new WP_Error('no_updates', 'No fields to update', ['status' => 400]);
                }
                
                $result = $wpdb->update(
                    $wpdb->prefix . 'oversee_kb_articles',
                    $updates,
                    ['id' => $id],
                    $formats,
                    ['%d']
                );
                
                return new WP_REST_Response([
                    'id' => $id,
                    'message' => 'Article updated'
                ]);
            },
            'permission_callback' => function() {
                return current_user_can('oversee_manage_kb');
            }
        ]);
        
        // Delete article
        register_rest_route('oversee/v1', '/kb/articles/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => function($request) {
                global $wpdb;
                
                $id = (int) $request->get_param('id');
                
                $result = $wpdb->delete(
                    $wpdb->prefix . 'oversee_kb_articles',
                    ['id' => $id],
                    ['%d']
                );
                
                if ($result === 0) {
                    return new WP_Error('not_found', 'Article not found', ['status' => 404]);
                }
                
                Oversee_Logger::info('Article deleted', ['id' => $id]);
                
                return new WP_REST_Response(['message' => 'Article deleted']);
            },
            'permission_callback' => function() {
                return current_user_can('oversee_manage_kb');
            }
        ]);
    }
    
    /**
     * Register category routes
     */
    private static function register_category_routes() {
        // List categories (from local database for admin)
        register_rest_route('oversee/v1', '/kb/categories', [
            'methods' => 'GET',
            'callback' => function($request) {
                global $wpdb;
                
                $categories = $wpdb->get_results(
                    "SELECT c.*, 
                            (SELECT COUNT(*) FROM {$wpdb->prefix}oversee_kb_articles WHERE category_slug = c.slug) as article_count
                     FROM {$wpdb->prefix}oversee_kb_categories c
                     ORDER BY c.sort_order ASC, c.name ASC",
                    ARRAY_A
                );
                
                return new WP_REST_Response([
                    'categories' => $categories ?: [],
                    'total' => count($categories ?: [])
                ]);
            },
            'permission_callback' => function() {
                return current_user_can('oversee_view_dashboard');
            }
        ]);
        
        // Create category
        register_rest_route('oversee/v1', '/kb/categories', [
            'methods' => 'POST',
            'callback' => function($request) {
                global $wpdb;
                
                $name = sanitize_text_field($request->get_param('name'));
                $description = sanitize_textarea_field($request->get_param('description') ?? '');
                $icon = sanitize_text_field($request->get_param('icon') ?? 'fa-solid fa-folder');
                
                if (empty($name)) {
                    return new WP_Error('missing_name', 'Name is required', ['status' => 400]);
                }
                
                $slug = sanitize_title($name);
                
                // Check for duplicate
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}oversee_kb_categories WHERE slug = %s",
                    $slug
                ));
                
                if ($exists) {
                    $slug .= '-' . time();
                }
                
                $result = $wpdb->insert(
                    $wpdb->prefix . 'oversee_kb_categories',
                    [
                        'slug' => $slug,
                        'name' => $name,
                        'description' => $description,
                        'icon' => $icon
                    ],
                    ['%s', '%s', '%s', '%s']
                );
                
                if ($result === false) {
                    return new WP_Error('db_error', 'Failed to create category', ['status' => 500]);
                }
                
                return new WP_REST_Response([
                    'id' => $wpdb->insert_id,
                    'slug' => $slug,
                    'message' => 'Category created'
                ], 201);
            },
            'permission_callback' => function() {
                return current_user_can('oversee_manage_kb');
            }
        ]);
        
        // Update category
        register_rest_route('oversee/v1', '/kb/categories/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => function($request) {
                global $wpdb;
                
                $id = (int) $request->get_param('id');
                
                $updates = [];
                $formats = [];
                
                if ($request->has_param('name')) {
                    $updates['name'] = sanitize_text_field($request->get_param('name'));
                    $formats[] = '%s';
                }
                
                if ($request->has_param('description')) {
                    $updates['description'] = sanitize_textarea_field($request->get_param('description'));
                    $formats[] = '%s';
                }
                
                if ($request->has_param('icon')) {
                    $updates['icon'] = sanitize_text_field($request->get_param('icon'));
                    $formats[] = '%s';
                }
                
                if ($request->has_param('sort_order')) {
                    $updates['sort_order'] = (int) $request->get_param('sort_order');
                    $formats[] = '%d';
                }
                
                if (empty($updates)) {
                    return new WP_Error('no_updates', 'No fields to update', ['status' => 400]);
                }
                
                $wpdb->update(
                    $wpdb->prefix . 'oversee_kb_categories',
                    $updates,
                    ['id' => $id],
                    $formats,
                    ['%d']
                );
                
                return new WP_REST_Response(['message' => 'Category updated']);
            },
            'permission_callback' => function() {
                return current_user_can('oversee_manage_kb');
            }
        ]);
        
        // Delete category
        register_rest_route('oversee/v1', '/kb/categories/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => function($request) {
                global $wpdb;
                
                $id = (int) $request->get_param('id');
                
                // Get category slug first
                $category = $wpdb->get_row($wpdb->prepare(
                    "SELECT slug FROM {$wpdb->prefix}oversee_kb_categories WHERE id = %d",
                    $id
                ));
                
                if (!$category) {
                    return new WP_Error('not_found', 'Category not found', ['status' => 404]);
                }
                
                // Check if has articles
                $article_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}oversee_kb_articles WHERE category_slug = %s",
                    $category->slug
                ));
                
                if ($article_count > 0) {
                    return new WP_Error(
                        'has_articles',
                        'Cannot delete category with articles. Delete or move articles first.',
                        ['status' => 400]
                    );
                }
                
                $wpdb->delete(
                    $wpdb->prefix . 'oversee_kb_categories',
                    ['id' => $id],
                    ['%d']
                );
                
                return new WP_REST_Response(['message' => 'Category deleted']);
            },
            'permission_callback' => function() {
                return current_user_can('oversee_manage_kb');
            }
        ]);
    }
}

// KB Sync endpoint
add_action('rest_api_init', function() {
    register_rest_route('oversee/v1', '/kb/sync', [
        'methods' => 'POST',
        'callback' => function($request) {
            $kb = new Oversee_KB();
            $stats = $kb->sync();
            return new WP_REST_Response(['success'=>true,'stats'=>$stats]);
        },
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ]);
    
    register_rest_route('oversee/v1', '/kb/sync/status', [
        'methods' => 'GET',
        'callback' => function($request) {
            $kb = new Oversee_KB();
            return new WP_REST_Response($kb->get_sync_info());
        },
        'permission_callback' => function() {
            return current_user_can('oversee_manage_kb');
        }
    ]);
    
    register_rest_route('oversee/v1', '/kb/sync/clear', [
        'methods' => 'POST',
        'callback' => function($request) {
            Oversee_KB_Importer::clear_all_data();
            return new WP_REST_Response(['success'=>true,'message'=>'All KB data cleared']);
        },
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ]);
});
