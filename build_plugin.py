import json
import os
import re

# Load articles
with open('articles_full.json', 'r') as f:
    articles = json.load(f)

print(f"Building plugin with {len(articles)} articles...")

# Get unique folders/categories
folders = {}
for a in articles:
    folder = a.get('folder', 'General') or 'General'
    if folder not in folders:
        folders[folder] = []
    folders[folder].append(a)

print(f"Found {len(folders)} categories")

# Create plugin directory
os.makedirs('oversee-support-tickets/templates', exist_ok=True)

# Fix image URLs
def fix_image_url(url):
    if url.startswith('../../../../'):
        return 'https://' + url.replace('../../../../', '')
    elif url.startswith('//'):
        return 'https:' + url
    elif not url.startswith('http'):
        return 'https://s3.amazonaws.com/cdn.freshdesk.com/' + url
    return url

# Escape for PHP
def php_escape(s):
    if not s:
        return ''
    return s.replace('\\', '\\\\').replace("'", "\\'")

# Generate articles PHP array
articles_php = []
for i, a in enumerate(articles):
    title = php_escape(a.get('title', ''))
    slug = php_escape(a.get('slug', ''))
    folder = php_escape(a.get('folder', 'General') or 'General')
    content = php_escape(a.get('content', '')[:60000])
    
    # Fix image URLs
    images = [fix_image_url(img) for img in a.get('images', [])[:15]]
    videos = a.get('videos', [])[:5]
    
    images_str = "array()" if not images else "array('" + "','".join([php_escape(i) for i in images]) + "')"
    videos_str = "array()" if not videos else "array('" + "','".join([php_escape(v) for v in videos]) + "')"
    
    articles_php.append(f"""    array(
        'id' => {i+1},
        'title' => '{title}',
        'slug' => '{slug}',
        'folder' => '{folder}',
        'content' => '{content}',
        'images' => {images_str},
        'videos' => {videos_str}
    )""")

# Generate folders PHP array  
folders_php = []
for i, (name, arts) in enumerate(folders.items()):
    fname = php_escape(name)
    folders_php.append(f"    array('id' => {i+1}, 'name' => '{fname}', 'slug' => '{php_escape(name.lower().replace(' ', '-').replace('&', 'and'))}', 'count' => {len(arts)})")

print("Generating main plugin file...")

plugin_code = '''<?php
/**
 * Plugin Name: OverseeCRM Support Tickets
 * Description: Complete support ticket system with knowledge base for OverseeCRM
 * Version: 2.0.0
 * Author: OverseeCRM
 */

if (!defined('ABSPATH')) exit;

class OverseeCRM_Support {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        add_action('init', array($this, 'register_routes'));
        add_action('rest_api_init', array($this, 'register_api_routes'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('oversee_support', array($this, 'support_shortcode'));
        add_shortcode('oversee_kb', array($this, 'kb_shortcode'));
    }
    
    public function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tickets table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}oversee_tickets (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            ticket_id varchar(20) NOT NULL,
            name varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            phone varchar(50),
            subject varchar(500) NOT NULL,
            description longtext NOT NULL,
            category varchar(100) DEFAULT 'General',
            priority varchar(20) DEFAULT 'medium',
            status varchar(20) DEFAULT 'open',
            rating int(1),
            location_id varchar(100),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY ticket_id (ticket_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Comments table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}oversee_comments (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            ticket_id bigint(20) NOT NULL,
            author_name varchar(255) NOT NULL,
            author_email varchar(255),
            content longtext NOT NULL,
            is_internal tinyint(1) DEFAULT 0,
            is_agent tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ticket_id (ticket_id)
        ) $charset_collate;";
        dbDelta($sql);
        
        // KB Categories table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}oversee_kb_categories (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            icon varchar(50) DEFAULT 'folder',
            sort_order int DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";
        dbDelta($sql);
        
        // KB Articles table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}oversee_kb_articles (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            title varchar(500) NOT NULL,
            slug varchar(500) NOT NULL,
            content longtext,
            excerpt text,
            category_id bigint(20),
            folder varchar(255),
            images longtext,
            videos longtext,
            views int DEFAULT 0,
            helpful int DEFAULT 0,
            not_helpful int DEFAULT 0,
            status varchar(20) DEFAULT 'published',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY slug (slug(191)),
            KEY category_id (category_id),
            FULLTEXT KEY search_idx (title, content)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Seed KB data
        $this->seed_kb_data();
        
        // Generate API key
        if (!get_option('oversee_api_key')) {
            update_option('oversee_api_key', wp_generate_password(32, false));
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public function seed_kb_data() {
        global $wpdb;
        
        // Check if already seeded
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}oversee_kb_articles");
        if ($count > 0) return;
        
        $articles = $this->get_kb_articles();
        $categories = array();
        
        foreach ($articles as $article) {
            $folder = $article['folder'];
            if (!isset($categories[$folder])) {
                $cat_slug = sanitize_title($folder);
                $wpdb->insert(
                    "{$wpdb->prefix}oversee_kb_categories",
                    array(
                        'name' => $folder,
                        'slug' => $cat_slug,
                        'description' => 'Articles about ' . $folder
                    )
                );
                $categories[$folder] = $wpdb->insert_id;
            }
            
            $wpdb->insert(
                "{$wpdb->prefix}oversee_kb_articles",
                array(
                    'title' => $article['title'],
                    'slug' => $article['slug'],
                    'content' => $article['content'],
                    'category_id' => $categories[$folder],
                    'folder' => $folder,
                    'images' => json_encode($article['images']),
                    'videos' => json_encode($article['videos'])
                )
            );
        }
    }
    
    public function get_kb_articles() {
        return array(
''' + ",\n".join(articles_php) + '''
        );
    }
    
    public function register_routes() {
        add_rewrite_rule('^support/?$', 'index.php?oversee_page=portal', 'top');
        add_rewrite_rule('^support/admin/?$', 'index.php?oversee_page=admin', 'top');
        add_rewrite_rule('^support/kb/?$', 'index.php?oversee_page=kb', 'top');
        add_rewrite_rule('^support/kb/([^/]+)/?$', 'index.php?oversee_page=kb&category=$matches[1]', 'top');
        add_rewrite_rule('^support/article/([^/]+)/?$', 'index.php?oversee_page=article&article_slug=$matches[1]', 'top');
        
        add_filter('query_vars', function($vars) {
            $vars[] = 'oversee_page';
            $vars[] = 'category';
            $vars[] = 'article_slug';
            return $vars;
        });
        
        add_action('template_redirect', array($this, 'handle_routes'));
    }
    
    public function handle_routes() {
        $page = get_query_var('oversee_page');
        if (!$page) return;
        
        switch ($page) {
            case 'portal':
                include plugin_dir_path(__FILE__) . 'templates/customer-portal.php';
                exit;
            case 'admin':
                include plugin_dir_path(__FILE__) . 'templates/admin-dashboard.php';
                exit;
            case 'kb':
                $category = get_query_var('category');
                include plugin_dir_path(__FILE__) . 'templates/knowledge-base.php';
                exit;
            case 'article':
                $slug = get_query_var('article_slug');
                include plugin_dir_path(__FILE__) . 'templates/article.php';
                exit;
        }
    }
    
    public function register_api_routes() {
        // Public endpoints
        register_rest_route('oversee/v1', '/tickets', array(
            array('methods' => 'GET', 'callback' => array($this, 'get_tickets'), 'permission_callback' => '__return_true'),
            array('methods' => 'POST', 'callback' => array($this, 'create_ticket'), 'permission_callback' => '__return_true')
        ));
        
        register_rest_route('oversee/v1', '/tickets/(?P<id>[\\w-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_ticket'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('oversee/v1', '/tickets/(?P<id>[\\w-]+)/comments', array(
            'methods' => 'POST',
            'callback' => array($this, 'add_comment'),
            'permission_callback' => '__return_true'
        ));
        
        // KB endpoints
        register_rest_route('oversee/v1', '/kb/search', array(
            'methods' => 'GET',
            'callback' => array($this, 'search_kb'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('oversee/v1', '/kb/categories', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_kb_categories'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('oversee/v1', '/kb/articles', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_kb_articles_api'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('oversee/v1', '/kb/article/(?P<slug>[\\w-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_kb_article'),
            'permission_callback' => '__return_true'
        ));
        
        // Admin endpoints
        register_rest_route('oversee/v1', '/admin/tickets', array(
            'methods' => 'GET',
            'callback' => array($this, 'admin_get_tickets'),
            'permission_callback' => array($this, 'check_api_key')
        ));
        
        register_rest_route('oversee/v1', '/admin/tickets/(?P<id>\\d+)', array(
            'methods' => 'PUT',
            'callback' => array($this, 'admin_update_ticket'),
            'permission_callback' => array($this, 'check_api_key')
        ));
        
        register_rest_route('oversee/v1', '/admin/stats', array(
            'methods' => 'GET',
            'callback' => array($this, 'admin_get_stats'),
            'permission_callback' => array($this, 'check_api_key')
        ));
    }
    
    public function check_api_key($request) {
        $api_key = $request->get_header('X-API-Key');
        return $api_key === get_option('oversee_api_key');
    }
    
    // Ticket methods
    public function get_tickets($request) {
        global $wpdb;
        $email = sanitize_email($request->get_param('email'));
        if (!$email) return new WP_Error('no_email', 'Email required', array('status' => 400));
        
        $tickets = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}oversee_tickets WHERE email = %s ORDER BY created_at DESC",
            $email
        ));
        return rest_ensure_response($tickets);
    }
    
    public function create_ticket($request) {
        global $wpdb;
        
        $data = $request->get_json_params();
        $ticket_id = 'TKT-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
        
        $result = $wpdb->insert(
            "{$wpdb->prefix}oversee_tickets",
            array(
                'ticket_id' => $ticket_id,
                'name' => sanitize_text_field($data['name']),
                'email' => sanitize_email($data['email']),
                'phone' => sanitize_text_field($data['phone'] ?? ''),
                'subject' => sanitize_text_field($data['subject']),
                'description' => wp_kses_post($data['description']),
                'category' => sanitize_text_field($data['category'] ?? 'General'),
                'priority' => sanitize_text_field($data['priority'] ?? 'medium'),
                'location_id' => sanitize_text_field($data['location_id'] ?? '')
            )
        );
        
        if ($result) {
            return rest_ensure_response(array(
                'success' => true,
                'ticket_id' => $ticket_id,
                'id' => $wpdb->insert_id
            ));
        }
        return new WP_Error('create_failed', 'Failed to create ticket', array('status' => 500));
    }
    
    public function get_ticket($request) {
        global $wpdb;
        $id = sanitize_text_field($request['id']);
        
        $ticket = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}oversee_tickets WHERE ticket_id = %s OR id = %s",
            $id, $id
        ));
        
        if (!$ticket) return new WP_Error('not_found', 'Ticket not found', array('status' => 404));
        
        $ticket->comments = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}oversee_comments WHERE ticket_id = %d AND is_internal = 0 ORDER BY created_at ASC",
            $ticket->id
        ));
        
        return rest_ensure_response($ticket);
    }
    
    public function add_comment($request) {
        global $wpdb;
        $id = sanitize_text_field($request['id']);
        $data = $request->get_json_params();
        
        $ticket = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}oversee_tickets WHERE ticket_id = %s OR id = %s",
            $id, $id
        ));
        
        if (!$ticket) return new WP_Error('not_found', 'Ticket not found', array('status' => 404));
        
        $result = $wpdb->insert(
            "{$wpdb->prefix}oversee_comments",
            array(
                'ticket_id' => $ticket->id,
                'author_name' => sanitize_text_field($data['name']),
                'author_email' => sanitize_email($data['email'] ?? ''),
                'content' => wp_kses_post($data['content']),
                'is_agent' => intval($data['is_agent'] ?? 0)
            )
        );
        
        return rest_ensure_response(array('success' => (bool)$result));
    }
    
    // KB methods
    public function search_kb($request) {
        global $wpdb;
        $q = sanitize_text_field($request->get_param('q'));
        if (strlen($q) < 2) return rest_ensure_response(array());
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT a.id, a.title, a.slug, a.folder, c.name as category_name 
            FROM {$wpdb->prefix}oversee_kb_articles a
            LEFT JOIN {$wpdb->prefix}oversee_kb_categories c ON a.category_id = c.id
            WHERE MATCH(a.title, a.content) AGAINST(%s IN NATURAL LANGUAGE MODE)
            OR a.title LIKE %s
            LIMIT 20",
            $q, '%' . $wpdb->esc_like($q) . '%'
        ));
        
        return rest_ensure_response($results);
    }
    
    public function get_kb_categories() {
        global $wpdb;
        $categories = $wpdb->get_results(
            "SELECT c.*, COUNT(a.id) as article_count 
            FROM {$wpdb->prefix}oversee_kb_categories c
            LEFT JOIN {$wpdb->prefix}oversee_kb_articles a ON c.id = a.category_id
            GROUP BY c.id
            ORDER BY c.sort_order, c.name"
        );
        return rest_ensure_response($categories);
    }
    
    public function get_kb_articles_api($request) {
        global $wpdb;
        $category = sanitize_text_field($request->get_param('category'));
        $limit = intval($request->get_param('limit')) ?: 50;
        
        $where = $category ? $wpdb->prepare(" WHERE c.slug = %s", $category) : "";
        
        $articles = $wpdb->get_results(
            "SELECT a.id, a.title, a.slug, a.folder, a.views, c.name as category_name
            FROM {$wpdb->prefix}oversee_kb_articles a
            LEFT JOIN {$wpdb->prefix}oversee_kb_categories c ON a.category_id = c.id
            $where
            ORDER BY a.title
            LIMIT $limit"
        );
        
        return rest_ensure_response($articles);
    }
    
    public function get_kb_article($request) {
        global $wpdb;
        $slug = sanitize_text_field($request['slug']);
        
        $article = $wpdb->get_row($wpdb->prepare(
            "SELECT a.*, c.name as category_name, c.slug as category_slug
            FROM {$wpdb->prefix}oversee_kb_articles a
            LEFT JOIN {$wpdb->prefix}oversee_kb_categories c ON a.category_id = c.id
            WHERE a.slug = %s",
            $slug
        ));
        
        if ($article) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}oversee_kb_articles SET views = views + 1 WHERE id = %d",
                $article->id
            ));
            $article->images = json_decode($article->images) ?: array();
            $article->videos = json_decode($article->videos) ?: array();
        }
        
        return rest_ensure_response($article);
    }
    
    // Admin methods
    public function admin_get_tickets($request) {
        global $wpdb;
        $status = sanitize_text_field($request->get_param('status'));
        $where = $status ? $wpdb->prepare(" WHERE status = %s", $status) : "";
        
        $tickets = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}oversee_tickets $where ORDER BY created_at DESC LIMIT 100"
        );
        return rest_ensure_response($tickets);
    }
    
    public function admin_update_ticket($request) {
        global $wpdb;
        $id = intval($request['id']);
        $data = $request->get_json_params();
        
        $update = array();
        if (isset($data['status'])) $update['status'] = sanitize_text_field($data['status']);
        if (isset($data['priority'])) $update['priority'] = sanitize_text_field($data['priority']);
        
        if (!empty($update)) {
            $wpdb->update("{$wpdb->prefix}oversee_tickets", $update, array('id' => $id));
        }
        
        return rest_ensure_response(array('success' => true));
    }
    
    public function admin_get_stats() {
        global $wpdb;
        
        $stats = array(
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}oversee_tickets"),
            'open' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}oversee_tickets WHERE status = 'open'"),
            'in_progress' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}oversee_tickets WHERE status = 'in_progress'"),
            'resolved' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}oversee_tickets WHERE status = 'resolved'"),
            'kb_articles' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}oversee_kb_articles"),
            'kb_categories' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}oversee_kb_categories")
        );
        
        return rest_ensure_response($stats);
    }
    
    public function admin_menu() {
        add_menu_page(
            'Support Tickets',
            'Support Tickets',
            'manage_options',
            'oversee-support',
            array($this, 'admin_page'),
            'dashicons-tickets-alt',
            30
        );
    }
    
    public function admin_page() {
        $api_key = get_option('oversee_api_key');
        ?>
        <div class="wrap">
            <h1>OverseeCRM Support Tickets</h1>
            <div class="card">
                <h2>Quick Links</h2>
                <p><a href="<?php echo home_url('/support/'); ?>" target="_blank">Customer Portal</a></p>
                <p><a href="<?php echo home_url('/support/admin/'); ?>" target="_blank">Admin Dashboard</a></p>
                <p><a href="<?php echo home_url('/support/kb/'); ?>" target="_blank">Knowledge Base</a></p>
            </div>
            <div class="card">
                <h2>API Key</h2>
                <code style="display:block;padding:10px;background:#f0f0f0"><?php echo esc_html($api_key); ?></code>
                <p><small>Use this key in the X-API-Key header for admin API requests</small></p>
            </div>
            <div class="card">
                <h2>Statistics</h2>
                <?php
                global $wpdb;
                $tickets = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}oversee_tickets");
                $articles = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}oversee_kb_articles");
                $categories = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}oversee_kb_categories");
                ?>
                <p>Total Tickets: <strong><?php echo $tickets; ?></strong></p>
                <p>KB Articles: <strong><?php echo $articles; ?></strong></p>
                <p>KB Categories: <strong><?php echo $categories; ?></strong></p>
            </div>
        </div>
        <?php
    }
    
    public function enqueue_scripts() {
        // Scripts loaded in templates
    }
    
    public function support_shortcode($atts) {
        ob_start();
        include plugin_dir_path(__FILE__) . 'templates/customer-portal.php';
        return ob_get_clean();
    }
    
    public function kb_shortcode($atts) {
        $atts = shortcode_atts(array('category' => ''), $atts);
        $category = $atts['category'];
        ob_start();
        include plugin_dir_path(__FILE__) . 'templates/knowledge-base.php';
        return ob_get_clean();
    }
}

// Initialize
OverseeCRM_Support::get_instance();
'''

with open('oversee-support-tickets/oversee-support-tickets.php', 'w', encoding='utf-8') as f:
    f.write(plugin_code)

print("Main plugin file created!")
print(f"Total size: {len(plugin_code)} bytes")
print("Now creating templates...")
