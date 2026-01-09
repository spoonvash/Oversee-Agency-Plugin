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
        
        // EARLY HOOK: Clear any output buffers before theme can render
        add_action('template_redirect', [__CLASS__, 'early_buffer_clear'], -999);
        
        // MAIN HOOK: Intercept template to render our pages
        add_filter('template_include', [__CLASS__, 'intercept_template'], -999);
        
        // Check if we need to flush rewrite rules
        if (get_option('oversee_flush_rewrite_rules', false)) {
            flush_rewrite_rules();
            delete_option('oversee_flush_rewrite_rules');
        }
    }
    
    /**
     * Early buffer clear - runs before anything else on our routes
     */
    public static function early_buffer_clear() {
        $route = get_query_var('oversee_route');
        if (!$route) {
            $route = self::parse_url_fallback();
        }
        
        if ($route) {
            // This is our route - prevent ANY output
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            // Start fresh buffer to catch any stray output
            ob_start();
        }
    }
    
    /**
     * Intercept template loading to render our pages directly
     */
    public static function intercept_template($template) {
        // Check for WP admin iframe token first
        if (isset($_GET['wp_token']) && !empty($_GET['wp_token'])) {
            Oversee_Auth::validate_wp_admin_token(sanitize_text_field($_GET['wp_token']));
        }
        
        $route = get_query_var('oversee_route');
        
        // Fallback: If rewrite rules haven't been flushed yet, parse URL manually
        if (!$route) {
            $route = self::parse_url_fallback();
        }
        
        // Not our route - let WordPress handle it
        if (!$route) {
            return $template;
        }
        
        // === OUR ROUTE: Take complete control ===
        
        // 1. Discard ANY content that was output before us
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // 2. For PUBLIC routes only, prevent any theme interference
        // Admin routes need wp_head for wp_editor (TinyMCE)
        $is_admin_route = strpos($route, 'admin_') === 0;
        if (!$is_admin_route) {
            remove_all_actions('wp_head');
            remove_all_actions('wp_footer');
            remove_all_actions('wp_print_styles');
            remove_all_actions('wp_print_scripts');
        }
        
        // 3. Handle our route
        self::handle_routes_internal($route);
        
        // 4. Die to prevent any further output
        die();
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
    /**
     * Internal route handler - called after template interception
     */
    private static function handle_routes_internal($route) {
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
            exit;
        }
        
        // Handle login form submission
        if ($route === 'admin_login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = Oversee_Auth::process_login();
            if ($result && isset($result['error'])) {
                $oversee_vars['login_error'] = $result['error'];
            }
        }
        
        // Map routes to templates
        $public_templates = [
            'kb_home' => 'home',
            'kb_category' => 'kb-category',
            'kb_article' => 'kb-article',
            'kb_search' => 'kb-search',
            'submit_ticket' => 'submit-ticket',
            'my_tickets' => 'my-tickets',
            'view_ticket' => 'view-ticket',
            'zoom_support' => 'zoom-support',
        ];
        
        $admin_templates = [
            'admin_login' => 'admin/login.php',
            'admin_dashboard' => 'admin/dashboard.php',
            'admin_tickets' => 'admin/tickets.php',
            'admin_settings' => 'admin/settings.php',
            'admin_profile' => 'admin/my-profile.php',
            'admin_articles' => 'admin/articles.php',
            'admin_article_new' => 'admin/article-editor.php',
            'admin_article_edit' => 'admin/article-editor.php',
        ];
        
        // Use Template Loader for public templates
        if (isset($public_templates[$route])) {
            $company_name = Oversee_Branding::get('company_name', 'Support Center');
            
            $page_titles = [
                'kb_home' => $company_name . ' - Help Center',
                'kb_category' => $company_name . ' - Knowledge Base',
                'kb_article' => $company_name . ' - Article',
                'kb_search' => 'Search Results - ' . $company_name,
                'submit_ticket' => 'Submit a Ticket - ' . $company_name,
                'my_tickets' => 'My Tickets - ' . $company_name,
                'view_ticket' => 'Ticket #' . ($oversee_vars['ticket'] ?? '') . ' - ' . $company_name,
                'zoom_support' => 'Live Support - ' . $company_name,
            ];
            
            Oversee_Template_Loader::render(
                $public_templates[$route],
                $oversee_vars,
                $page_titles[$route] ?? $company_name
            );
            exit;
        }
        
        // Legacy handling for admin templates (they handle their own layout)
        if (isset($admin_templates[$route])) {
            // License check for admin routes (except login)
            if ($route !== 'admin_login' && !self::check_admin_license()) {
                return; // Redirect already happened
            }
            
            $template_file = OVERSEE_TEMPLATES_PATH . '/' . $admin_templates[$route];
            
            if (file_exists($template_file)) {
                header('Content-Type: text/html; charset=utf-8');
                include $template_file;
                exit;
            } else {
                Oversee_Logger::error('Template not found', ['template' => $template_file]);
                wp_die('Template not found: ' . esc_html($admin_templates[$route]), 'Template Error', ['response' => 500]);
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
     * Check license for admin routes
     * Shows license required page or redirects based on settings
     * 
     * @return bool True if licensed, false if redirected/blocked
     */
    private static function check_admin_license() {
        // Check if license is valid
        if (Oversee_License::is_licensed()) {
            return true;
        }
        
        // Get license status for detailed info
        $status = Oversee_License::get_status();
        
        // If in grace period, allow access
        if (!empty($status['in_grace_period']) || $status['status'] === 'grace_period') {
            return true;
        }
        
        // License is invalid - show license required page for admin
        self::render_license_required_page($status);
        exit;
    }
    
    /**
     * Render a "License Required" page for admin portal
     */
    private static function render_license_required_page($status) {
        $branding = Oversee_Branding::get_for_context('login');
        $company = $branding['company_name'] ?: 'Support Center';
        $bg_css = Oversee_Branding::get_login_background_css();
        $primary_color = $branding['primary_color'] ?: '#f97316';
        $wp_admin_url = admin_url('admin.php?page=oversee-settings&tab=license');
        
        header('Content-Type: text/html; charset=utf-8');
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License Required - <?php echo esc_html($company); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Inter', sans-serif; 
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center;
            <?php echo $bg_css; ?>
        }
        .license-card {
            background: #fff;
            border-radius: 16px;
            padding: 48px;
            max-width: 480px;
            width: 90%;
            text-align: center;
            box-shadow: 0 25px 50px rgba(0,0,0,0.25);
        }
        .lock-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        .lock-icon i { font-size: 32px; color: #d97706; }
        h1 { font-size: 24px; font-weight: 700; color: #1e293b; margin-bottom: 12px; }
        .message { color: #64748b; font-size: 15px; line-height: 1.6; margin-bottom: 32px; }
        .status-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
            text-align: left;
        }
        .status-box .status-label { font-size: 12px; font-weight: 600; color: #991b1b; text-transform: uppercase; margin-bottom: 4px; }
        .status-box .status-message { font-size: 14px; color: #dc2626; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px 28px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.15s;
            cursor: pointer;
            border: none;
        }
        .btn-primary {
            background: <?php echo esc_attr($primary_color); ?>;
            color: #fff;
        }
        .btn-primary:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            margin-top: 12px;
        }
        .btn-secondary:hover { background: #e2e8f0; }
        .footer-text { margin-top: 24px; font-size: 13px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="license-card">
        <div class="lock-icon"><i class="fa-solid fa-lock"></i></div>
        <h1>License Required</h1>
        <p class="message">The admin dashboard requires a valid license. Please activate your license to continue using <?php echo esc_html($company); ?>.</p>
        
        <?php if (!empty($status['message'])): ?>
        <div class="status-box">
            <div class="status-label">Status</div>
            <div class="status-message"><?php echo esc_html($status['message']); ?></div>
        </div>
        <?php endif; ?>
        
        <a href="<?php echo esc_url($wp_admin_url); ?>" class="btn btn-primary">
            <i class="fa-solid fa-key"></i> Activate License
        </a>
        
        <?php if (!empty($status['renew_url'])): ?>
        <br>
        <a href="<?php echo esc_url($status['renew_url']); ?>" class="btn btn-secondary" target="_blank">
            <i class="fa-solid fa-shopping-cart"></i> Purchase License
        </a>
        <?php endif; ?>
        
        <p class="footer-text">Need help? Contact support at overseeagency.com</p>
    </div>
</body>
</html>
        <?php
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
