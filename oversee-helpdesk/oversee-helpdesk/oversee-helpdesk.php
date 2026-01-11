<?php
/**
 * Plugin Name: Oversee Helpdesk
 * Plugin URI: https://overseeagency.com/plugins/oversee-helpdesk
 * Description: Professional helpdesk and support ticket system with integrated knowledge base. Features include ticket management, email notifications, searchable knowledge base, agent assignment, team dashboard, and full REST API. Fully customizable with white-label branding options.
 * Version: 2.7.0-beta
 * Author: Oversee Agency
 * Author URI: https://overseeagency.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: oversee-helpdesk
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.7
 * Requires PHP: 7.4
 * 
 * @package Oversee_Helpdesk
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check PHP version
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p><strong>Oversee Helpdesk</strong> requires PHP 7.4 or higher. You are running PHP ' . PHP_VERSION . '</p></div>';
    });
    return;
}

// Plugin constants
define('OVERSEE_VERSION', '2.7.0-beta');
define('OVERSEE_PLUGIN_FILE', __FILE__);
define('OVERSEE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OVERSEE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('OVERSEE_TEMPLATES_PATH', OVERSEE_PLUGIN_DIR . 'templates');
define('OVERSEE_INCLUDES_PATH', OVERSEE_PLUGIN_DIR . 'includes');

/**
 * Legacy autoloader for plugin classes (backward compatibility)
 * 
 * @deprecated 3.0.0 Use PSR-4 autoloading via Composer instead
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
        require_once OVERSEE_INCLUDES_PATH . '/class-oversee-license.php';
        require_once OVERSEE_INCLUDES_PATH . '/class-oversee-branding.php';
        require_once OVERSEE_INCLUDES_PATH . '/class-oversee-template-loader.php';
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Note: Activation/Deactivation hooks are registered at file level, not here
        // (WordPress requires them to be registered immediately when plugin loads)
        
        // Initialize license checking
        Oversee_License::init();
        
        // Initialize branding (always needed for settings)
        Oversee_Branding::init();
        
        // WordPress Admin Menu
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // Admin scripts for settings page
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // Admin notices for license
        add_action('admin_notices', [$this, 'license_admin_notices']);
        
        // Admin bar license indicator
        add_action('admin_bar_menu', [$this, 'admin_bar_license_indicator'], 100);
        
        // Only initialize full functionality if licensed
        if (Oversee_License::is_licensed()) {
            // Initialize components
            add_action('init', [$this, 'init']);
            Oversee_KB_CPT::init();
            add_action('rest_api_init', [$this, 'init_rest_api']);
            
            // AJAX fallback for search (works when REST API is blocked)
            add_action('wp_ajax_oversee_kb_search', [$this, 'ajax_kb_search']);
            add_action('wp_ajax_nopriv_oversee_kb_search', [$this, 'ajax_kb_search']);
            
            // AJAX for removing duplicates
            add_action('wp_ajax_oversee_remove_duplicates', [$this, 'ajax_remove_duplicates']);
        
            // Enqueue scripts/styles
            add_action('wp_enqueue_scripts', [$this, 'enqueue_public_assets']);
            
            // Admin notices
            add_action('admin_notices', [$this, 'admin_notices']);
            
            // User deletion cleanup
            add_action('delete_user', ['Oversee_Activator', 'handle_user_deletion']);
            
            // Set iframe headers for public pages
            add_action('send_headers', [$this, 'set_iframe_headers'], 999);
        }
    }
    
    /**
     * Show license notice in admin
     */
    public function license_admin_notices() {
        // Only show to admins
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $status = Oversee_License::get_status();
        $screen = get_current_screen();
        
        // Skip license page/tab itself
        if ($screen && (strpos($screen->id, 'oversee-license') !== false || 
            (isset($_GET['tab']) && $_GET['tab'] === 'license'))) {
            return;
        }
        
        // Grace period warning - show on all pages
        if (!empty($status['in_grace_period']) || $status['status'] === 'grace_period') {
            $days = $status['days_remaining'] ?? 7;
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong>⚠️ Oversee Helpdesk:</strong> 
                    License server unreachable. Operating in grace period (<?php echo intval($days); ?> days remaining).
                    <a href="<?php echo admin_url('admin.php?page=oversee-settings&tab=license'); ?>">Check License →</a>
                </p>
            </div>
            <?php
            return;
        }
        
        // Site migration warning
        if ($status['status'] === 'site_migrated') {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong>⚠️ Oversee Helpdesk:</strong> 
                    Your site URL has changed. Please reactivate your license.
                    <a href="<?php echo admin_url('admin.php?page=oversee-settings&tab=license'); ?>">Reactivate License →</a>
                </p>
            </div>
            <?php
            return;
        }
        
        // License invalid - only show on oversee pages
        if (!$screen || strpos($screen->id, 'oversee') === false) {
            return;
        }
        
        if (!Oversee_License::is_licensed()) {
            ?>
            <div class="notice notice-error">
                <p>
                    <strong>🔒 Oversee Helpdesk:</strong> 
                    <?php echo esc_html($status['message'] ?? 'License required.'); ?>
                    <a href="<?php echo admin_url('admin.php?page=oversee-settings&tab=license'); ?>">Enter License Key →</a>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Add license status indicator to admin bar
     */
    public function admin_bar_license_indicator($admin_bar) {
        // Only show to admins
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Don't show on frontend (unless on support pages)
        if (!is_admin()) {
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            if (strpos($uri, '/support/') === false) {
                return;
            }
        }
        
        $status = Oversee_License::get_status();
        $is_valid = !empty($status['valid']);
        $in_grace = !empty($status['in_grace_period']);
        
        if ($is_valid && !$in_grace) {
            // License is valid - show green indicator
            $admin_bar->add_node([
                'id' => 'oversee-license',
                'title' => '<span style="color:#22c55e;">●</span> Oversee Licensed',
                'href' => admin_url('admin.php?page=oversee-settings&tab=license'),
                'meta' => [
                    'title' => 'License is active'
                ]
            ]);
        } elseif ($in_grace) {
            // Grace period - show yellow indicator
            $days = !empty($status['days_remaining']) ? $status['days_remaining'] : '?';
            $admin_bar->add_node([
                'id' => 'oversee-license',
                'title' => '<span style="color:#f59e0b;">●</span> Oversee Grace Period',
                'href' => admin_url('admin.php?page=oversee-settings&tab=license'),
                'meta' => [
                    'title' => "Grace period: {$days} days remaining"
                ]
            ]);
        } else {
            // Unlicensed - show red indicator
            $admin_bar->add_node([
                'id' => 'oversee-license',
                'title' => '<span style="color:#ef4444;">●</span> Oversee Unlicensed',
                'href' => admin_url('admin.php?page=oversee-settings&tab=license'),
                'meta' => [
                    'title' => 'License required - Click to activate'
                ]
            ]);
        }
    }
    
    /**
     * Add WordPress admin menu
     */
    public function add_admin_menu() {
        $status = Oversee_License::get_status();
        $is_valid = !empty($status['valid']);
        $in_grace = !empty($status['in_grace_period']) || $status['status'] === 'grace_period';
        
        // If not licensed and not in grace period, show only the license page
        if (!$is_valid && !$in_grace) {
            add_menu_page(
                'License',
                'Helpdesk',
                'manage_options',
                'oversee-settings',
                [$this, 'render_settings_page'],
                'dashicons-lock',
                30
            );
            return;
        }
        
        // Licensed (or in grace period) - show full menu
        $company = Oversee_Branding::get('company_name', 'Helpdesk');
        
        add_menu_page(
            $company,
            'Helpdesk',
            'manage_options',
            'oversee-support',
            [$this, 'render_admin_page'],
            'dashicons-tickets-alt',
            30
        );
        
        // Settings includes Branding + License tabs
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
        $company = Oversee_Branding::get('company_name', 'Support Center');
        ?>
        <div class="wrap oversee-admin-wrap">
            <h1><?php echo esc_html($company); ?></h1>
            
            <div class="oversee-admin-cards">
                <div class="oversee-admin-card">
                    <h2>Support Dashboard</h2>
                    <p>Manage tickets, view analytics, and handle customer requests.</p>
                    <a href="<?php echo esc_url(home_url('/support/admin/')); ?>" target="_blank" class="button button-primary button-hero">Open Dashboard</a>
                </div>
                
                <div class="oversee-admin-card">
                    <h2>Quick Links</h2>
                    <ul class="oversee-quick-links">
                        <li><a href="<?php echo esc_url(home_url('/support/')); ?>" target="_blank">→ Public Help Center</a></li>
                        <li><a href="<?php echo esc_url(home_url('/support/admin/tickets')); ?>" target="_blank">→ Ticket Management</a></li>
                        <li><a href="<?php echo esc_url(home_url('/support/admin/articles')); ?>" target="_blank">→ Knowledge Base</a></li>
                        <li><a href="<?php echo esc_url(admin_url('admin.php?page=oversee-settings')); ?>">→ Branding & Settings</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'oversee') === false) {
            return;
        }
        
        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        // Inline admin CSS
        wp_add_inline_style('wp-admin', '
            .oversee-admin-wrap { max-width: 800px; }
            .oversee-admin-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
            .oversee-admin-card { background: #fff; border: 1px solid #c3c4c7; padding: 20px; border-radius: 4px; }
            .oversee-admin-card h2 { margin-top: 0; }
            .oversee-quick-links { list-style: none; margin: 0; padding: 0; }
            .oversee-quick-links li { margin: 8px 0; }
            .oversee-quick-links a { text-decoration: none; }
            .oversee-settings-card { background: #fff; border: 1px solid #c3c4c7; padding: 20px; border-radius: 4px; margin-top: 20px; }
        ');
        
        // Inline admin JS for media uploader
        wp_add_inline_script('wp-color-picker', '
            jQuery(function($) {
                $(".oversee-color-picker").wpColorPicker({
                    change: function(event, ui) {
                        // Trigger custom event for live preview
                        $(this).trigger("colorchange", [ui.color.toString()]);
                        // Also update the input value immediately
                        $(this).val(ui.color.toString());
                    },
                    clear: function() {
                        $(this).trigger("colorchange", [""]);
                    }
                });
                
                // Generic media upload handler
                function setupMediaUpload(buttonId, inputId, previewId, previewStyle) {
                    $(buttonId).on("click", function(e) {
                        e.preventDefault();
                        var frame = wp.media({ title: "Select Image", multiple: false });
                        frame.on("select", function() {
                            var url = frame.state().get("selection").first().toJSON().url;
                            $(inputId).val(url);
                            $(previewId).html("<img src=\"" + url + "\" style=\"" + previewStyle + "\">");
                            var removeBtn = buttonId.replace("upload-", "remove-");
                            if (!$(removeBtn).length) {
                                $(buttonId).after(" <button type=\"button\" class=\"button\" id=\"" + removeBtn.substring(1) + "\">Remove</button>");
                            }
                        });
                        frame.open();
                    });
                    
                    $(document).on("click", buttonId.replace("upload-", "remove-"), function() {
                        $(inputId).val("");
                        $(previewId).html("<span class=\"no-image\">No image uploaded</span>");
                        $(this).remove();
                    });
                }
                
                // Setup all image upload buttons
                setupMediaUpload("#upload-logo-btn", "#logo_url", "#logo-preview", "max-width:200px;height:auto;");
                setupMediaUpload("#upload-logo-dark-btn", "#logo_dark_url", "#logo-dark-preview", "max-width:200px;height:auto;");
                setupMediaUpload("#upload-favicon-btn", "#favicon_url", "#favicon-preview", "width:32px;height:32px;");
                setupMediaUpload("#upload-login-bg-btn", "#login_bg_image", "#login-bg-preview", "max-width:100%;height:auto;");
            });
        ');
    }
    
    /**
     * Render settings page with tabs
     */
    public function render_settings_page() {
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'branding';
        
        // Handle form submission
        if (isset($_POST['oversee_settings_nonce']) && wp_verify_nonce($_POST['oversee_settings_nonce'], 'oversee_settings')) {
            if ($tab === 'branding') {
                Oversee_Branding::save($_POST);
                echo '<div class="notice notice-success"><p>Branding settings saved.</p></div>';
            }
        }
        ?>
        <div class="wrap">
            <h1>Settings</h1>
            
            <nav class="nav-tab-wrapper">
                <a href="<?php echo admin_url('admin.php?page=oversee-settings&tab=branding'); ?>" class="nav-tab <?php echo $tab === 'branding' ? 'nav-tab-active' : ''; ?>">Branding</a>
                <a href="<?php echo admin_url('admin.php?page=oversee-settings&tab=license'); ?>" class="nav-tab <?php echo $tab === 'license' ? 'nav-tab-active' : ''; ?>">License</a>
            </nav>
            
            <div class="oversee-settings-card">
                <?php if ($tab === 'branding'): ?>
                    <form method="post">
                        <?php wp_nonce_field('oversee_settings', 'oversee_settings_nonce'); ?>
                        <h2>White-Label Branding</h2>
                        <p>Customize the appearance of your helpdesk. Changes apply to both admin and public pages.</p>
                        <?php Oversee_Branding::render_settings_form(); ?>
                        <?php submit_button('Save Branding'); ?>
                    </form>
                    
                    <hr>
                    <h3>Preview</h3>
                    <p>
                        <a href="<?php echo esc_url(home_url('/support/')); ?>" target="_blank" class="button">View Public Help Center</a>
                        <a href="<?php echo esc_url(home_url('/support/admin/')); ?>" target="_blank" class="button">View Admin Dashboard</a>
                    </p>
                    
                <?php elseif ($tab === 'license'): ?>
                    <?php Oversee_License::render_license_page(); ?>
                <?php endif; ?>
            </div>
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
     * AJAX handler for KB search (fallback when REST API blocked)
     */
    public function ajax_kb_search() {
        $query = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
        $limit = isset($_GET['limit']) ? min(50, max(1, intval($_GET['limit']))) : 10;
        
        if (strlen($query) < 2) {
            wp_send_json([]);
        }
        
        $kb = new Oversee_KB();
        $results = $kb->search($query, $limit);
        wp_send_json($results);
    }
    
    /**
     * AJAX handler for removing duplicate articles
     */
    public function ajax_remove_duplicates() {
        if (!current_user_can('oversee_manage_kb')) {
            wp_send_json_error(['message' => 'Permission denied'], 403);
        }
        
        $removed = Oversee_KB_Importer::remove_duplicates();
        wp_send_json_success([
            'removed' => $removed,
            'message' => "Removed {$removed} duplicate articles"
        ]);
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
        
        // UX Utilities (must load first - provides Toast, Skeleton, FormValidator, etc.)
        wp_enqueue_script(
            'oversee-ux-utils',
            OVERSEE_PLUGIN_URL . 'assets/js/ux-utils.js',
            [],
            OVERSEE_VERSION,
            true
        );

        // Public JS
        wp_enqueue_script(
            'oversee-public',
            OVERSEE_PLUGIN_URL . 'assets/js/public.js',
            ['oversee-ux-utils'],
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
    require_once OVERSEE_INCLUDES_PATH . '/class-oversee-license.php';
    require_once OVERSEE_INCLUDES_PATH . '/class-oversee-activator.php';
    
    Oversee_Activator::activate();
}

/**
 * Plugin deactivation callback
 */
function oversee_deactivate_plugin() {
    require_once OVERSEE_INCLUDES_PATH . '/class-oversee-license.php';
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

/**
 * Output KB article content with YouTube iframe support and consistent fonts
 */
function oversee_kb_content($content) {
    // Remove duplicate "Screenshots" section that dumps all images again at the end
    // This pattern matches ## Screenshots or ### Screenshots followed by just images
    $content = preg_replace('/#{2,}\s*Screenshots\s*\n+(\s*(\[?\!\[.*?\]\(.*?\)\]?|\<img[^>]*>)\s*\n*)+$/is', '', $content);
    
    // Also remove if it's just "Screenshots" as a header with images after
    $content = preg_replace('/\n\s*#{2,}\s*Screenshots\s*\n+([\s\S]*?)((?=\n\s*#{2,})|$)/i', '', $content);
    
    // Remove any "← Back to" navigation that might be in the content
    $content = preg_replace('/\[←[^\]]*Back to[^\]]*\]\([^)]*\)/i', '', $content);
    
    // Strip inline font-family styles to enforce consistent fonts
    $content = preg_replace('/font-family\s*:\s*[^;"\'>]+;?/i', '', $content);
    
    // Also strip font shorthand that might include font-family
    $content = preg_replace('/\bfont\s*:\s*[^;"\'>]*["\'][^;"\'>]*["\'][^;"\'>]*;?/i', '', $content);
    
    // Allow iframes and embedded content with all necessary attributes
    $allowed_html = wp_kses_allowed_html('post');
    $allowed_html['iframe'] = [
        'src' => true,
        'width' => true,
        'height' => true,
        'frameborder' => true,
        'allow' => true,
        'allowfullscreen' => true,
        'title' => true,
        'class' => true,
        'style' => true,
        'id' => true,
        'name' => true,
        'scrolling' => true,
        'sandbox' => true,
        'loading' => true,
        'referrerpolicy' => true,
    ];
    
    // Allow script tags for embedded widgets
    $allowed_html['script'] = [
        'src' => true,
        'type' => true,
        'async' => true,
        'defer' => true,
        'id' => true,
        'class' => true,
        'data-*' => true,
    ];
    
    // Allow data attributes on divs for widgets
    $allowed_html['div']['data-*'] = true;
    $allowed_html['div']['data-src'] = true;
    $allowed_html['div']['data-id'] = true;
    
    return wp_kses($content, $allowed_html);
}

/**
 * Plugin Update Checker
 */
class Oversee_Helpdesk_Updater {
    
    private $plugin_slug = 'oversee-helpdesk';
    private $plugin_file;
    private $current_version;
    private $update_server = 'https://overseeagency.com/wp-content/oversee-update-server/';
    
    public function __construct($plugin_file, $version) {
        $this->plugin_file = $plugin_file;
        $this->current_version = $version;
        
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 10, 3);
    }
    
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        $response = wp_remote_post($this->update_server . '?action=update-check', [
            'body' => [
                'slug' => $this->plugin_slug,
                'version' => $this->current_version,
                'site_url' => home_url()
            ],
            'timeout' => 10
        ]);
        
        if (is_wp_error($response)) {
            return $transient;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!empty($data['update_available'])) {
            $plugin_basename = plugin_basename($this->plugin_file);
            
            $transient->response[$plugin_basename] = (object) [
                'slug' => $this->plugin_slug,
                'plugin' => $plugin_basename,
                'new_version' => $data['version'],
                'package' => $data['download_url'],
                'url' => $data['changelog']
            ];
        }
        
        return $transient;
    }
    
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || $args->slug !== $this->plugin_slug) {
            return $result;
        }

        $response = wp_remote_post($this->update_server . '?action=plugin-info', [
            'body' => [
                'slug' => $this->plugin_slug,
                'site_url' => home_url()
            ],
            'timeout' => 10
        ]);

        if (is_wp_error($response)) {
            return $result;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($data) {
            return (object) $data;
        }

        return $result;
    }
}

// Initialize updater
new Oversee_Helpdesk_Updater(__FILE__, '2.7.0-beta');
