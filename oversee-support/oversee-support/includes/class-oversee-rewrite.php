<?php
/**
 * Rewrite Rules
 * 
 * Handles URL rewriting for the support portal.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Oversee_Rewrite {
    
    /**
     * Initialize rewrite rules
     */
    public static function init() {
        // Register rewrite rules directly (we're already in 'init' context)
        self::add_rewrite_rules();
        
        // Register query vars filter
        add_filter('query_vars', [__CLASS__, 'add_query_vars']);
        
        // Handle routes on template_redirect
        add_action('template_redirect', [__CLASS__, 'handle_routes'], 20);
        
        // Check if we need to flush rewrite rules
        if (get_option('oversee_flush_rewrite_rules', false)) {
            flush_rewrite_rules();
            delete_option('oversee_flush_rewrite_rules');
        }
    }
    
    /**
     * Add rewrite rules
     */
    public static function add_rewrite_rules() {
        // Public KB routes
        add_rewrite_rule(
            '^support/?$',
            'index.php?oversee_route=kb_home',
            'top'
        );
        add_rewrite_rule(
            '^support/kb/?$',
            'index.php?oversee_route=kb_home',
            'top'
        );
        add_rewrite_rule(
            '^support/kb/search/?$',
            'index.php?oversee_route=kb_search',
            'top'
        );
        add_rewrite_rule(
            '^support/kb/([^/]+)/?$',
            'index.php?oversee_route=kb_category&oversee_category=$matches[1]',
            'top'
        );
        add_rewrite_rule(
            '^support/kb/([^/]+)/([^/]+)/?$',
            'index.php?oversee_route=kb_article&oversee_category=$matches[1]&oversee_article=$matches[2]',
            'top'
        );
        
        // Ticket routes
        add_rewrite_rule(
            '^support/submit/?$',
            'index.php?oversee_route=submit_ticket',
            'top'
        );
        add_rewrite_rule(
            '^support/tickets/?$',
            'index.php?oversee_route=my_tickets',
            'top'
        );
        add_rewrite_rule(
            '^support/tickets/([^/]+)/?$',
            'index.php?oversee_route=view_ticket&oversee_ticket=$matches[1]',
            'top'
        );
        add_rewrite_rule(
            '^support/zoom/?$',
            'index.php?oversee_route=zoom_support',
            'top'
        );
        
        // Admin routes
        add_rewrite_rule(
            '^support/admin/?$',
            'index.php?oversee_route=admin_dashboard',
            'top'
        );
        add_rewrite_rule(
            '^support/admin/login/?$',
            'index.php?oversee_route=admin_login',
            'top'
        );
        add_rewrite_rule(
            '^support/admin/logout/?$',
            'index.php?oversee_route=admin_logout',
            'top'
        );
        add_rewrite_rule(
            '^support/admin/tickets/?$',
            'index.php?oversee_route=admin_tickets',
            'top'
        );
        add_rewrite_rule(
            '^support/admin/settings/?$',
            'index.php?oversee_route=admin_settings',
            'top'
        );
        add_rewrite_rule(
            '^support/admin/profile/?$',
            'index.php?oversee_route=admin_profile',
            'top'
        );
        add_rewrite_rule(
            '^support/admin/articles/?$',
            'index.php?oversee_route=admin_articles',
            'top'
        );
        add_rewrite_rule(
            '^support/admin/articles/new/?$',
            'index.php?oversee_route=admin_article_new',
            'top'
        );
        add_rewrite_rule(
            '^support/admin/articles/edit/([0-9]+)/?$',
            'index.php?oversee_route=admin_article_edit&oversee_article_id=$matches[1]',
            'top'
        );
    }
    
    /**
     * Add query variables
     */
    public static function add_query_vars($vars) {
        $vars[] = 'oversee_route';
        $vars[] = 'oversee_category';
        $vars[] = 'oversee_article';
        $vars[] = 'oversee_ticket';
        $vars[] = 'oversee_article_id';
        return $vars;
    }
    
    /**
     * Handle routes and load templates
     */
    public static function handle_routes() {

        // Check for WP admin iframe token
        if (isset($_GET['wp_token']) && !empty($_GET['wp_token'])) {
            Oversee_Auth::validate_wp_admin_token(sanitize_text_field($_GET['wp_token']));
        }
        

        $route = get_query_var('oversee_route');
        
        // Fallback: If rewrite rules haven't been flushed yet, parse URL manually
        if (!$route) {
            $route = self::parse_url_fallback();
        }
        
        if (!$route) {
            return;
        }
        
        // Set global variables for templates
        global $oversee_vars;
        $oversee_vars = [
            'route' => $route,
            'category' => get_query_var('oversee_category'),
            'article' => get_query_var('oversee_article'),
            'ticket' => get_query_var('oversee_ticket'),
            'article_id' => get_query_var('oversee_article_id'),
        ];
        
        // Handle logout
        if ($route === 'admin_logout') {
            Oversee_Auth::process_logout();
            return;
        }
        
        // Handle login form submission
        if ($route === 'admin_login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = Oversee_Auth::process_login();
            if ($result && isset($result['error'])) {
                $oversee_vars['login_error'] = $result['error'];
            }
        }
        
        // Map routes to templates
        $templates = [
            // Public
            'kb_home' => 'home.php',
            'kb_category' => 'kb-category.php',
            'kb_article' => 'kb-article.php',
            'kb_search' => 'kb-search.php',
            'submit_ticket' => 'submit-ticket.php',
            'my_tickets' => 'my-tickets.php',
            'view_ticket' => 'view-ticket.php',
            'zoom_support' => 'zoom-support.php',
            // Admin
            'admin_login' => 'admin/login.php',
            'admin_dashboard' => 'admin/dashboard.php',
            'admin_tickets' => 'admin/tickets.php',
            'admin_settings' => 'admin/settings.php',
            'admin_profile' => 'admin/my-profile.php',
            'admin_articles' => 'admin/articles.php',
            'admin_article_new' => 'admin/article-editor.php',
            'admin_article_edit' => 'admin/article-editor.php',
        ];
        
        if (isset($templates[$route])) {
            $template_file = OVERSEE_TEMPLATES_PATH . '/' . $templates[$route];
            
            if (file_exists($template_file)) {
                // Set proper content type
                header('Content-Type: text/html; charset=utf-8');
                
                include $template_file;
                exit;
            } else {
                Oversee_Logger::error('Template not found', ['template' => $template_file]);
                wp_die('Template not found: ' . esc_html($templates[$route]), 'Template Error', ['response' => 500]);
            }
        }
        
        // Unknown route
        wp_die('Unknown route: ' . esc_html($route), 'Route Error', ['response' => 404]);
    }
    
    /**
     * Fallback URL parser for when rewrite rules haven't been saved yet
     */
    private static function parse_url_fallback() {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $path = trim(parse_url($request_uri, PHP_URL_PATH), '/');
        
        // Remove any subdirectory WordPress might be installed in
        $home_path = trim(parse_url(home_url(), PHP_URL_PATH), '/');
        if ($home_path && strpos($path, $home_path) === 0) {
            $path = substr($path, strlen($home_path) + 1);
        }
        $path = trim($path, '/');
        
        global $oversee_vars;
        $oversee_vars = [];
        
        // Match patterns manually
        $patterns = [
            // Admin routes (check these first - more specific)
            '#^support/admin/articles/edit/(\d+)/?$#' => function($m) {
                global $oversee_vars;
                $oversee_vars['article_id'] = $m[1];
                set_query_var('oversee_article_id', $m[1]);
                return 'admin_article_edit';
            },
            '#^support/admin/articles/new/?$#' => 'admin_article_new',
            '#^support/admin/articles/?$#' => 'admin_articles',
            '#^support/admin/settings/?$#' => 'admin_settings',
            '#^support/admin/profile/?$#' => 'admin_profile',
            '#^support/admin/tickets/?$#' => 'admin_tickets',
            '#^support/admin/logout/?$#' => 'admin_logout',
            '#^support/admin/login/?$#' => 'admin_login',
            '#^support/admin/?$#' => 'admin_dashboard',
            
            // Ticket routes
            '#^support/tickets/([^/]+)/?$#' => function($m) {
                set_query_var('oversee_ticket', $m[1]);
                return 'view_ticket';
            },
            '#^support/tickets/?$#' => 'my_tickets',
            '#^support/submit/?$#' => 'submit_ticket',
            '#^support/zoom/?$#' => 'zoom_support',
            
            // KB routes
            '#^support/kb/search/?$#' => 'kb_search',
            '#^support/kb/([^/]+)/([^/]+)/?$#' => function($m) {
                set_query_var('oversee_category', $m[1]);
                set_query_var('oversee_article', $m[2]);
                return 'kb_article';
            },
            '#^support/kb/([^/]+)/?$#' => function($m) {
                set_query_var('oversee_category', $m[1]);
                return 'kb_category';
            },
            '#^support/kb/?$#' => 'kb_home',
            '#^support/?$#' => 'kb_home',
        ];
        
        foreach ($patterns as $pattern => $route) {
            if (preg_match($pattern, $path, $matches)) {
                // Trigger a rewrite rules flush for next request
                if (!get_option('oversee_rules_flushed')) {
                    flush_rewrite_rules();
                    update_option('oversee_rules_flushed', true);
                }
                
                if (is_callable($route)) {
                    return $route($matches);
                }
                return $route;
            }
        }
        
        return null;
    }
    
    /**
     * Flush rewrite rules
     */
    public static function flush() {
        self::add_rewrite_rules();
        flush_rewrite_rules();
    }
    
    /**
     * Get URL for a route
     */
    public static function get_url($route, $params = []) {
        $base = home_url('/support/');
        
        $paths = [
            'kb_home' => '',
            'kb_category' => 'kb/' . ($params['category'] ?? ''),
            'kb_article' => 'kb/' . ($params['category'] ?? '') . '/' . ($params['article'] ?? ''),
            'kb_search' => 'kb/search',
            'submit_ticket' => 'submit',
            'my_tickets' => 'tickets',
            'view_ticket' => 'tickets/' . ($params['ticket'] ?? ''),
            'zoom_support' => 'zoom',
            'admin_dashboard' => 'admin',
            'admin_login' => 'admin/login',
            'admin_tickets' => 'admin/tickets',
            'admin_settings' => 'admin/settings',
            'admin_profile' => 'admin/profile',
            'admin_articles' => 'admin/articles',
        ];
        
        if (isset($paths[$route])) {
            return $base . $paths[$route];
        }
        
        return $base;
    }
}
