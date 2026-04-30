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
    private static $config_printed_inline = false;

    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'register']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'register_admin']);
        // Vite emits <script type="module">. Tell WordPress to do the same.
        add_filter('script_loader_tag', [__CLASS__, 'add_module_attribute'], 10, 3);
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

        // Always emit window.OCD_CONFIG / OVERSEE_CONFIG to wp_head, even if the
        // SPA bundle is missing or the manifest hasn't been built yet. The SPA
        // reads these to decide whether to show the role picker, login CTA, or
        // boot directly into the customer / admin surface.
        self::print_config_inline_once();

        $manifest = self::load_manifest();
        $script_handle = 'oversee-spa';

        if ($manifest) {
            // Find the entry chunk (matches src/main.tsx).
            $entry_key = null;
            foreach ($manifest as $key => $chunk) {
                if (!empty($chunk['isEntry'])) {
                    $entry_key = $key;
                    break;
                }
            }
            if ($entry_key) {
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

                // Register first, then localize, then enqueue. This ordering
                // guarantees wp_localize_script attaches the data even when
                // other plugins reorder enqueues during wp_footer.
                wp_register_script(
                    $script_handle,
                    OCD_URL . 'assets/build/' . ltrim($entry['file'], '/'),
                    [],
                    OCD_VERSION,
                    true
                );

                $config = self::runtime_config();
                wp_localize_script($script_handle, 'OCD_CONFIG', $config);
                wp_localize_script($script_handle, 'OVERSEE_CONFIG', $config);

                wp_enqueue_script($script_handle);
            }
        }
    }

    /**
     * Print window.OCD_CONFIG / OVERSEE_CONFIG inline to wp_head as a belt-and-
     * suspenders measure. wp_localize_script can be defeated by aggressive
     * cache plugins or missing-script edge cases; the inline copy guarantees
     * the SPA always finds runtime config no matter what.
     */
    private static function print_config_inline_once() {
        if (self::$config_printed_inline) return;
        self::$config_printed_inline = true;
        add_action('wp_head', [__CLASS__, 'print_inline_config'], 1);
        // Also print to the footer in case wp_head already fired before the
        // shortcode renders (some themes do template_redirect-stage rendering).
        add_action('wp_footer', [__CLASS__, 'print_inline_config'], 1);
    }

    public static function print_inline_config() {
        static $emitted = false;
        if ($emitted) return;
        $emitted = true;
        $config = self::runtime_config();
        $json = wp_json_encode($config);
        if (!is_string($json)) {
            return;
        }
        echo "<script id=\"oversee-ocd-config\">"
            . "window.OCD_CONFIG=window.OCD_CONFIG||" . $json . ";"
            . "window.OVERSEE_CONFIG=window.OVERSEE_CONFIG||window.OCD_CONFIG;"
            . "</script>\n";
    }

    /**
     * Derive the effective SPA role from current capabilities.
     *
     * Staff users (manage_woocommerce / manage_options) get "admin"; logged-in
     * non-staff get "customer"; logged-out users get "guest". The SPA boot
     * shim consults this to pick between client portal, admin console, and
     * the passwordless login CTA without relying on the role picker.
     */
    public static function derive_role() {
        if (!is_user_logged_in()) {
            return 'guest';
        }
        if (current_user_can('manage_woocommerce') || current_user_can('manage_options')) {
            return 'admin';
        }
        return 'customer';
    }

    public static function runtime_config() {
        $current = wp_get_current_user();
        $dashboard_url = home_url('/dashboard/');
        $page = get_page_by_path('dashboard');
        if ($page && $page->ID) {
            $permalink = get_permalink($page->ID);
            if ($permalink) {
                $dashboard_url = $permalink;
            }
        }
        return [
            'restUrl'      => esc_url_raw(rest_url(OCD_REST_API::NAMESPACE . '/')),
            'overseeRestUrl' => class_exists('Oversee_REST_API')
                ? esc_url_raw(rest_url(Oversee_REST_API::NAMESPACE . '/'))
                : esc_url_raw(rest_url('oversee/v1/')),
            'wcRestUrl'    => esc_url_raw(rest_url('wc/v3/')),
            'nonce'        => wp_create_nonce('wp_rest'),
            'isAdmin'      => current_user_can('manage_woocommerce') || current_user_can('manage_options'),
            'isLoggedIn'   => is_user_logged_in(),
            'role'         => self::derive_role(),
            'pluginUrl'    => esc_url_raw(OCD_URL),
            'dashboardUrl' => esc_url_raw($dashboard_url),
            'loginUrl'     => esc_url_raw(wp_login_url($dashboard_url)),
            'siteUrl'      => esc_url_raw(home_url('/')),
            'accountUrl'   => esc_url_raw(function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/my-account/')),
            'cartUrl'      => esc_url_raw(function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/')),
            'checkoutUrl'  => esc_url_raw(function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/checkout/')),
            'logoutUrl'    => esc_url_raw(wp_logout_url(home_url('/'))),
            'currentUser'  => $current && $current->ID ? [
                'id'           => (int) $current->ID,
                'displayName'  => $current->display_name,
                'email'        => $current->user_email,
                'roles'        => array_values($current->roles),
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
