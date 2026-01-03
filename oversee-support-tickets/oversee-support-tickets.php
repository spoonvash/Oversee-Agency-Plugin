<?php
/**
 * Plugin Name: OverseeCRM Support Tickets
 * Description: Support ticket system with 553-article knowledge base
 * Version: 2.0.0
 * Author: OverseeCRM
 */

if (!defined('ABSPATH')) exit;

class OverseeCRM_Support {
    
    public function __construct() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        add_action('init', [$this, 'routes']);
        add_action('rest_api_init', [$this, 'api']);
        add_action('admin_menu', [$this, 'menu']);
    }
    
    public function activate() {
        global $wpdb;
        $c = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta("CREATE TABLE {$wpdb->prefix}oversee_tickets (
            id bigint AUTO_INCREMENT PRIMARY KEY,
            ticket_id varchar(20) UNIQUE,
            name varchar(255),
            email varchar(255),
            phone varchar(50),
            subject varchar(500),
            description longtext,
            category varchar(100) DEFAULT 'General',
            priority varchar(20) DEFAULT 'medium',
            status varchar(20) DEFAULT 'open',
            location_id varchar(100),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) $c;");
        
        dbDelta("CREATE TABLE {$wpdb->prefix}oversee_comments (
            id bigint AUTO_INCREMENT PRIMARY KEY,
            ticket_id bigint,
            author_name varchar(255),
            author_email varchar(255),
            content longtext,
            is_agent tinyint DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP
        ) $c;");
        
        if (!get_option('oversee_api_key')) {
            update_option('oversee_api_key', wp_generate_password(32, false));
        }
        
        flush_rewrite_rules();
    }
    
    public function routes() {
        add_rewrite_rule('^support/?$', 'index.php?oversee_page=portal', 'top');
        add_rewrite_rule('^support/admin/?$', 'index.php?oversee_page=admin', 'top');
        add_filter('query_vars', function($v) { $v[] = 'oversee_page'; return $v; });
        add_action('template_redirect', [$this, 'render']);
    }
    
    public function render() {
        $page = get_query_var('oversee_page');
        if ($page === 'portal') { include __DIR__ . '/templates/customer-portal.php'; exit; }
        if ($page === 'admin') { include __DIR__ . '/templates/admin-dashboard.php'; exit; }
    }
    
    public function api() {
        register_rest_route('oversee/v1', '/tickets', [
            ['methods' => 'GET', 'callback' => [$this, 'get_tickets'], 'permission_callback' => '__return_true'],
            ['methods' => 'POST', 'callback' => [$this, 'create_ticket'], 'permission_callback' => '__return_true']
        ]);
        register_rest_route('oversee/v1', '/tickets/(?P<id>[\w-]+)', [
            'methods' => 'GET', 'callback' => [$this, 'get_ticket'], 'permission_callback' => '__return_true'
        ]);
        register_rest_route('oversee/v1', '/tickets/(?P<id>[\w-]+)/comments', [
            'methods' => 'POST', 'callback' => [$this, 'add_comment'], 'permission_callback' => '__return_true'
        ]);
        register_rest_route('oversee/v1', '/admin/tickets', [
            'methods' => 'GET', 'callback' => [$this, 'admin_tickets'], 'permission_callback' => [$this, 'check_key']
        ]);
        register_rest_route('oversee/v1', '/admin/tickets/(?P<id>\d+)', [
            'methods' => 'PUT', 'callback' => [$this, 'update_ticket'], 'permission_callback' => [$this, 'check_key']
        ]);
        register_rest_route('oversee/v1', '/admin/stats', [
            'methods' => 'GET', 'callback' => [$this, 'stats'], 'permission_callback' => [$this, 'check_key']
        ]);
    }
    
    public function check_key($r) { return $r->get_header('X-API-Key') === get_option('oversee_api_key'); }
    
    public function get_tickets($r) {
        global $wpdb;
        $email = sanitize_email($r->get_param('email'));
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}oversee_tickets WHERE email=%s ORDER BY created_at DESC", $email));
    }
    
    public function create_ticket($r) {
        global $wpdb;
        $d = $r->get_json_params();
        $tid = 'TKT-' . str_pad(mt_rand(1,99999), 5, '0', STR_PAD_LEFT);
        $wpdb->insert("{$wpdb->prefix}oversee_tickets", [
            'ticket_id' => $tid,
            'name' => sanitize_text_field($d['name']),
            'email' => sanitize_email($d['email']),
            'phone' => sanitize_text_field($d['phone'] ?? ''),
            'subject' => sanitize_text_field($d['subject']),
            'description' => wp_kses_post($d['description']),
            'category' => sanitize_text_field($d['category'] ?? 'General')
        ]);
        return ['success' => true, 'ticket_id' => $tid];
    }
    
    public function get_ticket($r) {
        global $wpdb;
        $id = sanitize_text_field($r['id']);
        $t = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}oversee_tickets WHERE ticket_id=%s OR id=%s", $id, $id));
        if ($t) $t->comments = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}oversee_comments WHERE ticket_id=%d ORDER BY created_at", $t->id));
        return $t;
    }
    
    public function add_comment($r) {
        global $wpdb;
        $id = sanitize_text_field($r['id']);
        $d = $r->get_json_params();
        $t = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}oversee_tickets WHERE ticket_id=%s OR id=%s", $id, $id));
        if ($t) $wpdb->insert("{$wpdb->prefix}oversee_comments", [
            'ticket_id' => $t->id,
            'author_name' => sanitize_text_field($d['name']),
            'author_email' => sanitize_email($d['email'] ?? ''),
            'content' => wp_kses_post($d['content']),
            'is_agent' => intval($d['is_agent'] ?? 0)
        ]);
        return ['success' => true];
    }
    
    public function admin_tickets($r) {
        global $wpdb;
        $status = sanitize_text_field($r->get_param('status'));
        $where = $status ? $wpdb->prepare(" WHERE status=%s", $status) : "";
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}oversee_tickets $where ORDER BY created_at DESC LIMIT 100");
    }
    
    public function update_ticket($r) {
        global $wpdb;
        $d = $r->get_json_params();
        $wpdb->update("{$wpdb->prefix}oversee_tickets", ['status' => sanitize_text_field($d['status'])], ['id' => intval($r['id'])]);
        return ['success' => true];
    }
    
    public function stats() {
        global $wpdb;
        $p = $wpdb->prefix;
        return [
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$p}oversee_tickets"),
            'open' => $wpdb->get_var("SELECT COUNT(*) FROM {$p}oversee_tickets WHERE status='open'"),
            'in_progress' => $wpdb->get_var("SELECT COUNT(*) FROM {$p}oversee_tickets WHERE status='in_progress'"),
            'resolved' => $wpdb->get_var("SELECT COUNT(*) FROM {$p}oversee_tickets WHERE status='resolved'"),
            'kb_articles' => 553
        ];
    }
    
    public function menu() {
        add_menu_page('Support', 'Support Tickets', 'manage_options', 'oversee-support', [$this, 'admin_page'], 'dashicons-tickets-alt', 30);
    }
    
    public function admin_page() {
        $key = get_option('oversee_api_key');
        echo '<div class="wrap"><h1>OverseeCRM Support</h1>';
        echo '<div class="card"><h2>Links</h2>';
        echo '<p><a href="' . home_url('/support/') . '" target="_blank">Customer Portal</a></p>';
        echo '<p><a href="' . home_url('/support/admin/') . '" target="_blank">Admin Dashboard</a></p>';
        echo '<p><a href="' . plugin_dir_url(__FILE__) . 'knowledge-base/index.html" target="_blank">Knowledge Base (553 articles)</a></p></div>';
        echo '<div class="card"><h2>API Key</h2><code style="background:#f0f0f0;padding:10px;display:block">' . esc_html($key) . '</code></div></div>';
    }
}

new OverseeCRM_Support();
