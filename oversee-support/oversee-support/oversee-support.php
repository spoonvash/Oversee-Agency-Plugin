<?php
/**
 * Plugin Name: OverseeCRM Support
 * Plugin URI: https://overseecrm.com
 * Description: Complete support ticket system with knowledge base, admin dashboard, and HighLevel integration.
 * Version: 2.0.0
 * Author: OverseeCRM
 * Author URI: https://overseecrm.com
 * License: GPL v2 or later
 * Text Domain: oversee-support
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check PHP version
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p><strong>OverseeCRM Support</strong> requires PHP 7.4 or higher. You are running PHP ' . PHP_VERSION . '</p></div>';
    });
    return;
}

// Plugin constants
define('OVERSEE_VERSION', '2.0.0');
define('OVERSEE_PLUGIN_FILE', __FILE__);
define('OVERSEE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OVERSEE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('OVERSEE_TEMPLATES_PATH', OVERSEE_PLUGIN_DIR . 'templates');
define('OVERSEE_INCLUDES_PATH', OVERSEE_PLUGIN_DIR . 'includes');

/**
 * Autoloader for plugin classes
 */
spl_autoload_register(function($class) {
    $prefix = 'Oversee_';
    
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    
    $class_name = substr($class, strlen($prefix));
    $class_name = strtolower(str_replace('_', '-', $class_name));
    $file = OVERSEE_INCLUDES_PATH . '/class-oversee-' . $class_name . '.php';
    
    if (file_exists($file)) {
        require_once $file;
    }
});

/**
 * Main plugin class
 */
final class Oversee_Support {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once OVERSEE_INCLUDES_PATH . '/class-oversee-activator.php';
        require_once OVERSEE_INCLUDES_PATH . '/class-oversee-deactivator.php';
        require_once OVERSEE_INCLUDES_PATH . '/class-oversee-migrator.php';
        require_once OVERSEE_INCLUDES_PATH . '/class-oversee-roles.php';
        require_once OVERSEE_INCLUDES_PATH . '/class-oversee-auth.php';
        require_once OVERSEE_INCLUDES_PATH . '/class-oversee-iframe-auth.php';
        require_once OVERSEE_INCLUDES_PATH . '/class-oversee-api-auth.php';
        require_once OVERSEE_INCLUDES_PATH . '/class-oversee-rewrite.php';
        require_once OVERSEE_INCLUDES_PATH . '/class-oversee-rest-api.php';
        require_once OVERSEE_INCLUDES_PATH . '/class-oversee-kb-cpt.php';
        require_once OVERSEE_INCLUDES_PATH . '/class-oversee-kb-importer.php';
        require_once OVERSEE_INCLUDES_PATH . '/class-oversee-kb.php';
        require_once OVERSEE_INCLUDES_PATH . '/class-oversee-kb-api.php';
        require_once OVERSEE_INCLUDES_PATH . '/class-oversee-push.php';
        require_once OVERSEE_INCLUDES_PATH . '/class-oversee-webhooks.php';
        require_once OVERSEE_INCLUDES_PATH . '/class-oversee-cron.php';
        require_once OVERSEE_INCLUDES_PATH . '/class-oversee-logger.php';
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Note: Activation/Deactivation hooks are registered at file level, not here
        // (WordPress requires them to be registered immediately when plugin loads)
        
        // WordPress Admin Menu
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // Initialize components
        add_action('init', [$this, 'init']);
        Oversee_KB_CPT::init();
        add_action('rest_api_init', [$this, 'init_rest_api']);
        
        // Enqueue scripts/styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_public_assets']);
        
        // Admin notices
        add_action('admin_notices', [$this, 'admin_notices']);
        
        // User deletion cleanup
        add_action('delete_user', ['Oversee_Activator', 'handle_user_deletion']);
        
        // Set iframe headers for public pages
        add_action('send_headers', [$this, 'set_iframe_headers'], 999);
    }
    
    /**
     * Add WordPress admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'OverseeCRM Support',
            'Support Tickets',
            'manage_options',
            'oversee-support',
            [$this, 'render_admin_page'],
            'dashicons-tickets-alt',
            30
        );
        
        add_submenu_page(
            'oversee-support',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'oversee-support',
            [$this, 'render_admin_page']
        );
        
        add_submenu_page(
            'oversee-support',
            'All Tickets',
            'All Tickets',
            'manage_options',
            'oversee-tickets',
            [$this, 'render_tickets_page']
        );
        
        add_submenu_page(
            'oversee-support',
            'Knowledge Base',
            'Knowledge Base',
            'manage_options',
            'oversee-kb',
            [$this, 'render_kb_page']
        );
        
        add_submenu_page(
            'oversee-support',
            'Settings',
            'Settings',
            'manage_options',
            'oversee-settings',
            [$this, 'render_settings_page']
        );
    }
    
    /**
     * Render admin dashboard page
     */
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1>OverseeCRM Support Dashboard</h1>
            <div class="card" style="max-width: 600px; padding: 20px;">
                <h2 style="margin-top: 0;">Open Support Dashboard</h2>
                <p>Click below to open the full support dashboard.</p>
                <p>
                    <a href="<?php echo esc_url(home_url('/support/admin/')); ?>" target="_blank" class="button button-primary button-hero">
                        Open Dashboard
                    </a>
                </p>
                <hr>
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="<?php echo esc_url(home_url('/support/')); ?>" target="_blank">Public Help Center</a></li>
                    <li><a href="<?php echo esc_url(home_url('/support/admin/tickets')); ?>" target="_blank">All Tickets</a></li>
                    <li><a href="<?php echo esc_url(home_url('/support/admin/settings')); ?>" target="_blank">Settings</a></li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render tickets page
     */
    public function render_tickets_page() {
        ?>
        <div class="wrap">
            <h1>Support Tickets</h1>
            <p><a href="<?php echo esc_url(home_url('/support/admin/tickets')); ?>" target="_blank" class="button button-primary">Open Tickets Dashboard</a></p>
        </div>
        <?php
    }
    
    /**
     * Render KB page
     */
    public function render_kb_page() {
        ?>
        <div class="wrap">
            <h1>Knowledge Base</h1>
            <p><a href="<?php echo esc_url(home_url('/support/admin/articles')); ?>" target="_blank" class="button button-primary">Manage Articles</a></p>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Support Settings</h1>
            <p><a href="<?php echo esc_url(home_url('/support/admin/settings')); ?>" target="_blank" class="button button-primary">Open Settings</a></p>
        </div>
        <?php
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load textdomain
        load_plugin_textdomain('oversee-support', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize components
        Oversee_Migrator::run();
        Oversee_Roles::init();
        Oversee_Auth::init();
        Oversee_Iframe_Auth::init();
        Oversee_Rewrite::init();
        Oversee_Cron::init();
        Oversee_Webhooks::init();
    }
    
    /**
     * Initialize REST API
     */
    public function init_rest_api() {
        Oversee_API_Auth::init();
        Oversee_REST_API::init();
        Oversee_KB_API::init();
    }
    
    /**
     * Enqueue public assets
     */
    public function enqueue_public_assets() {
        if (!$this->is_oversee_page()) {
            return;
        }
        
        // Public CSS
        wp_enqueue_style(
            'oversee-public',
            OVERSEE_PLUGIN_URL . 'assets/css/public.css',
            [],
            OVERSEE_VERSION
        );
        
        // Font Awesome
        wp_enqueue_style(
            'font-awesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
            [],
            '6.4.0'
        );
        
        // Public JS
        wp_enqueue_script(
            'oversee-public',
            OVERSEE_PLUGIN_URL . 'assets/js/public.js',
            [],
            OVERSEE_VERSION,
            true
        );
        
        wp_localize_script('oversee-public', 'overseeData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('oversee/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'homeUrl' => home_url('/support/')
        ]);
        
        // Admin-specific assets
        if ($this->is_admin_page()) {
            wp_enqueue_style(
                'oversee-admin',
                OVERSEE_PLUGIN_URL . 'assets/css/admin.css',
                [],
                OVERSEE_VERSION
            );
            
            // WordPress editor for article creation
            if ($this->is_article_editor_page()) {
                wp_enqueue_editor();
                wp_enqueue_media();
            }
            
            wp_enqueue_script(
                'oversee-api-client',
                OVERSEE_PLUGIN_URL . 'assets/js/api-client.js',
                [],
                OVERSEE_VERSION,
                true
            );
            
            wp_enqueue_script(
                'oversee-admin',
                OVERSEE_PLUGIN_URL . 'assets/js/admin.js',
                ['oversee-api-client'],
                OVERSEE_VERSION,
                true
            );
        }
    }
    
    /**
     * Check if current page is an Oversee page
     */
    private function is_oversee_page() {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return strpos($uri, '/support/') !== false || strpos($uri, '/support') !== false;
    }
    
    /**
     * Check if current page is admin page
     */
    private function is_admin_page() {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return strpos($uri, '/support/admin') !== false;
    }
    
    /**
     * Check if current page is article editor
     */
    private function is_article_editor_page() {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return strpos($uri, '/support/admin/articles/new') !== false 
            || strpos($uri, '/support/admin/articles/edit') !== false;
    }
    
    /**
     * Set headers for iframe embedding
     */
    public function set_iframe_headers() {
        if (!$this->is_oversee_page() || $this->is_admin_page()) {
            return;
        }
        
        // Remove restrictive headers for public KB pages
        header_remove('X-Frame-Options');
        
        // Allow embedding from HighLevel domains
        $allowed_origins = [
            'https://app.overseecrm.com',
            'https://*.gohighlevel.com',
            'https://*.leadconnectorhq.com'
        ];
        
        header("Content-Security-Policy: frame-ancestors 'self' " . implode(' ', $allowed_origins));
    }
    
    /**
     * Admin notices
     */
    public function admin_notices() {
        // Check if flush needed
        if (get_option('oversee_flush_rewrite_rules', false)) {
            flush_rewrite_rules();
            delete_option('oversee_flush_rewrite_rules');
        }
    }
}

/**
 * Initialize the plugin
 */
function oversee_support() {
    return Oversee_Support::instance();
}

// Start the plugin
add_action('plugins_loaded', 'oversee_support');

// CRITICAL: Register activation/deactivation hooks IMMEDIATELY (not on plugins_loaded)
// These must be registered when the plugin file is loaded, not deferred
register_activation_hook(__FILE__, 'oversee_activate_plugin');
register_deactivation_hook(__FILE__, 'oversee_deactivate_plugin');

/**
 * Plugin activation callback
 */
function oversee_activate_plugin() {
    // Manually load required files since autoloader may not have all dependencies ready
    require_once OVERSEE_INCLUDES_PATH . '/class-oversee-logger.php';
    require_once OVERSEE_INCLUDES_PATH . '/class-oversee-push.php';
    require_once OVERSEE_INCLUDES_PATH . '/class-oversee-rewrite.php';
    require_once OVERSEE_INCLUDES_PATH . '/class-oversee-activator.php';
    
    Oversee_Activator::activate();
}

/**
 * Plugin deactivation callback
 */
function oversee_deactivate_plugin() {
    require_once OVERSEE_INCLUDES_PATH . '/class-oversee-deactivator.php';
    Oversee_Deactivator::deactivate();
}

/**
 * Helper functions
 */

/**
 * Get KB article
 */
function oversee_get_article($category, $slug) {
    $kb = new Oversee_KB();
    return $kb->get_article($category, $slug);
}

/**
 * Get KB categories
 */
function oversee_get_categories() {
    $kb = new Oversee_KB();
    return $kb->get_categories();
}

/**
 * Get KB URL
 */
function oversee_kb_url($path = '') {
    return home_url('/support/kb/' . ltrim($path, '/'));
}

/**
 * Get support URL
 */
function oversee_support_url($path = '') {
    return home_url('/support/' . ltrim($path, '/'));
}

/**
 * Get admin URL
 */
function oversee_admin_url($path = '') {
    return home_url('/support/admin/' . ltrim($path, '/'));
}

/**
 * Check if remote KB is enabled
 */
function oversee_is_remote_kb_enabled() {
    return (bool) get_option('oversee_enable_remote_kb', true);
}

/**
 * Get primary color
 */
function oversee_get_primary_color() {
    return get_option('oversee_primary_color', '#f97316');
}

/**
 * Get portal title
 */
function oversee_get_portal_title() {
    return get_option('oversee_portal_title', 'Help Center');
}
