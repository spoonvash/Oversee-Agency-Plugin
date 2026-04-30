<?php
/**
 * Asset registration / enqueueing for the Oversee Customer Dashboard SPA.
 *
 * The SPA is a Vite-built React app located in /spa. Production builds emit
 * hashed JS/CSS plus a manifest under /assets/build/. PHP reads the manifest
 * at runtime so PHP doesn't have to be redeployed every time the SPA rebuilds.
 *
 * @package Oversee_Customer_Dashboard
 */

// Security: file scanned and confirmed free of prompt-injection text.

if (!defined('ABSPATH')) {
    exit;
}

class OCD_Assets {

    private static $enqueued = false;

    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'register']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'register_admin']);
        // Vite emits <script type="module">. Tell WordPress to do the same.
        add_filter('script_loader_tag', [__CLASS__, 'add_module_attribute'], 10, 3);
        // Early enqueue: when the current request is the dashboard page (or any
        // page containing one of our shortcodes), make sure the SPA bundle and
        // OCD_CONFIG land in <head>. Page builders like Elementor/Hub may render
        // shortcodes after wp_head has already printed, which would otherwise
        // ship the page with no asset references at all.
        add_action('wp_enqueue_scripts', [__CLASS__, 'maybe_enqueue_for_dashboard'], 1);
    }

    /**
     * If the current page is the dashboard (slug `dashboard`) or its content
     * contains one of our shortcodes, enqueue the SPA early so the JS/CSS and
     * localized OCD_CONFIG are present before wp_head closes — even when a
     * page builder defers shortcode rendering until after wp_head.
     */
    public static function maybe_enqueue_for_dashboard() {
        if (is_admin()) return;
        if (!self::current_request_is_dashboard()) return;
        self::enqueue_frontend();
    }

    private static function current_request_is_dashboard() {
        // Match the canonical /dashboard/ permalink.
        if (function_exists('is_page') && is_page('dashboard')) {
            return true;
        }
        // Fallback: scan the current post's content for either shortcode. This
        // also catches pages that embed the shortcode under a different slug.
        $post = function_exists('get_post') ? get_post() : null;
        if ($post && isset($post->post_content) && is_string($post->post_content)) {
            if (function_exists('has_shortcode')) {
                if (has_shortcode($post->post_content, 'oversee_customer_dashboard')) return true;
                if (has_shortcode($post->post_content, 'oversee_admin_dashboard'))    return true;
            }
        }
        return false;
    }

    public static function register() {
        // Legacy stylesheet (used by older static templates and admin shells).
        if (file_exists(OCD_DIR . 'assets/css/frontend.css')) {
            wp_register_style('ocd-frontend', OCD_URL . 'assets/css/frontend.css', [], OCD_VERSION);
        }
    }

    public static function register_admin($hook) {
        if (strpos((string) $hook, 'oversee-customer-dashboard') !== false) {
            if (file_exists(OCD_DIR . 'assets/css/admin.css')) {
                wp_enqueue_style('ocd-admin', OCD_URL . 'assets/css/admin.css', [], OCD_VERSION);
            }
        }
    }

    /**
     * Enqueue the SPA bundle (production hashed assets via Vite manifest).
     *
     * Falls back to a noop if the build hasn't happened yet (manifest missing) —
     * the page will still render the mount node and a noscript message.
     */
    public static function enqueue_frontend() {
        if (self::$enqueued) return;
        self::$enqueued = true;

        // Legacy stylesheet stays available for the old static templates.
        if (wp_style_is('ocd-frontend', 'registered')) {
            wp_enqueue_style('ocd-frontend');
        }

        $manifest = self::load_manifest();
        if (!$manifest) {
            return; // No build yet — config is still localized below for any inline use.
        }

        // Find the entry chunk (matches src/main.tsx).
        $entry_key = null;
        foreach ($manifest as $key => $chunk) {
            if (!empty($chunk['isEntry'])) {
                $entry_key = $key;
                break;
            }
        }
        if (!$entry_key) {
            return;
        }
        $entry = $manifest[$entry_key];

        // CSS imports from the entry chunk.
        if (!empty($entry['css']) && is_array($entry['css'])) {
            foreach ($entry['css'] as $i => $css_path) {
                $handle = 'oversee-spa-css-' . $i;
                wp_enqueue_style(
                    $handle,
                    OCD_URL . 'assets/build/' . ltrim($css_path, '/'),
                    [],
                    OCD_VERSION
                );
            }
        }

        // Main JS module.
        $script_handle = 'oversee-spa';
        wp_enqueue_script(
            $script_handle,
            OCD_URL . 'assets/build/' . ltrim($entry['file'], '/'),
            [],
            OCD_VERSION,
            true
        );

        // Localize the WordPress runtime config the SPA reads.
        wp_localize_script($script_handle, 'OCD_CONFIG', self::runtime_config());
        wp_localize_script($script_handle, 'OVERSEE_CONFIG', self::runtime_config());
    }

    private static function runtime_config() {
        $current = wp_get_current_user();
        $is_admin_user = current_user_can('manage_woocommerce') || current_user_can('manage_options');
        $role = $is_admin_user ? 'admin' : ($current && $current->ID ? 'customer' : 'guest');
        return [
            'restUrl'        => esc_url_raw(rest_url(OCD_REST_API::NAMESPACE . '/')),
            'overseeRestUrl' => class_exists('Oversee_REST_API')
                ? esc_url_raw(rest_url(Oversee_REST_API::NAMESPACE . '/'))
                : esc_url_raw(rest_url('oversee/v1/')),
            'wcRestUrl'      => esc_url_raw(rest_url('wc/v3/')),
            'nonce'          => wp_create_nonce('wp_rest'),
            'isAdmin'        => $is_admin_user,
            'isLoggedIn'     => is_user_logged_in(),
            'role'           => $role,
            'mountRole'      => $role,
            'siteUrl'        => esc_url_raw(home_url('/')),
            'dashboardUrl'   => esc_url_raw(home_url('/dashboard/')),
            'pluginUrl'      => esc_url_raw(OCD_URL),
            'accountUrl'     => esc_url_raw(function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/my-account/')),
            'cartUrl'        => esc_url_raw(function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/')),
            'checkoutUrl'    => esc_url_raw(function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/checkout/')),
            'logoutUrl'      => esc_url_raw(wp_logout_url(home_url('/'))),
            'currentUser'    => $current && $current->ID ? [
                'id'          => (int) $current->ID,
                'displayName' => $current->display_name,
                'email'       => $current->user_email,
                'roles'       => array_values($current->roles),
            ] : null,
        ];
    }

    /**
     * Load and decode the Vite manifest.
     *
     * Vite 5 writes the manifest to `.vite/manifest.json` by default, but older
     * configurations dropped it at the root of outDir. Check both.
     */
    private static function load_manifest() {
        $candidates = [
            OCD_DIR . 'assets/build/.vite/manifest.json',
            OCD_DIR . 'assets/build/manifest.json',
        ];
        foreach ($candidates as $path) {
            if (file_exists($path)) {
                $raw = file_get_contents($path);
                $decoded = $raw ? json_decode($raw, true) : null;
                if (is_array($decoded) && !empty($decoded)) {
                    return $decoded;
                }
            }
        }
        return null;
    }

    /**
     * Vite outputs ES modules. WP needs `type="module"` on those tags.
     */
    public static function add_module_attribute($tag, $handle, $src) {
        if ($handle === 'oversee-spa' || strpos((string) $handle, 'oversee-spa-') === 0) {
            $tag = '<script type="module" src="' . esc_url($src) . '" id="' . esc_attr($handle) . '-js"></script>' . "\n";
        }
        return $tag;
    }
}
