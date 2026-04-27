<?php
/**
 * Oversee Hub Child theme functions.
 *
 * Responsibilities:
 *   - Load the parent (Hub) stylesheet.
 *   - Register the dashboard page template that bypasses Hub header/footer/sidebar.
 *   - Enqueue Inter + the dashboard stylesheet only on /dashboard/, /login/,
 *     /register/, and the WooCommerce my-account / cart / checkout pages.
 *   - Override WooCommerce templates from this child theme's woocommerce/ folder.
 *   - Redirect logged-out visitors hitting /dashboard/ to /login/?redirect_to=/dashboard/.
 *   - Provide a branded HTML email wrapper used by transactional WooCommerce emails.
 *
 * @package Oversee_Hub_Child
 */

if (!defined('ABSPATH')) {
    exit;
}

define('OVERSEE_HUB_CHILD_VERSION', '1.0.0');
define('OVERSEE_HUB_CHILD_DIR', get_stylesheet_directory());
define('OVERSEE_HUB_CHILD_URL', get_stylesheet_directory_uri());

/**
 * Load parent + child styles. Inter is loaded only where the child surfaces
 * actually render; it is not pushed onto the marketing site.
 */
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('hub-parent', get_template_directory_uri() . '/style.css', [], null);

    if (oversee_is_dashboard_surface()) {
        wp_enqueue_style(
            'oversee-inter',
            'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
            [],
            null
        );
        wp_enqueue_style(
            'oversee-dashboard',
            OVERSEE_HUB_CHILD_URL . '/assets/css/oversee-dashboard.css',
            ['hub-parent'],
            OVERSEE_HUB_CHILD_VERSION
        );
    }
}, 20);

/**
 * Returns true on any portal surface that should receive Oversee branding.
 * Marketing pages remain on Hub styling.
 */
function oversee_is_dashboard_surface() {
    if (is_admin()) {
        return false;
    }
    $path = isset($_SERVER['REQUEST_URI']) ? wp_parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
    if ($path && (
        strpos($path, '/dashboard') === 0 ||
        strpos($path, '/login') === 0 ||
        strpos($path, '/register') === 0
    )) {
        return true;
    }
    if (function_exists('is_account_page') && (is_account_page() || is_cart() || is_checkout())) {
        return true;
    }
    return false;
}

/**
 * Register the page template that the dashboard page uses. The template
 * suppresses the Hub header/footer/sidebar and renders only the React SPA mount.
 */
add_filter('theme_page_templates', function ($templates) {
    $templates['page-dashboard.php']     = __('Oversee Dashboard (Full-Width)', 'oversee-hub-child');
    $templates['page-oversee-login.php'] = __('Oversee Login (Full-Width)', 'oversee-hub-child');
    return $templates;
});

/**
 * Force /dashboard/ to use the dashboard template even if the WP admin selector
 * was missed. We match the exact slug rather than fuzzy-string compare.
 */
add_filter('template_include', function ($template) {
    if (is_admin() || !is_singular('page')) {
        return $template;
    }
    $post = get_queried_object();
    if (!$post || empty($post->post_name)) {
        return $template;
    }
    if ($post->post_name === 'dashboard') {
        $candidate = OVERSEE_HUB_CHILD_DIR . '/page-dashboard.php';
        if (file_exists($candidate)) {
            return $candidate;
        }
    }
    if ($post->post_name === 'login' || $post->post_name === 'register') {
        $candidate = OVERSEE_HUB_CHILD_DIR . '/page-oversee-login.php';
        if (file_exists($candidate)) {
            return $candidate;
        }
    }
    return $template;
});

/**
 * Logged-out visitors hitting /dashboard/* are bounced to /login/ with a
 * redirect_to back to the page they were trying to reach. We do this before
 * any output so the redirect is clean.
 */
add_action('template_redirect', function () {
    if (is_user_logged_in() || is_admin()) {
        return;
    }
    $path = isset($_SERVER['REQUEST_URI']) ? wp_parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
    if (!$path || strpos($path, '/dashboard') !== 0) {
        return;
    }
    $redirect_to = home_url($_SERVER['REQUEST_URI']);
    wp_safe_redirect(add_query_arg('redirect_to', urlencode($redirect_to), home_url('/login/')));
    exit;
});

/**
 * WooCommerce template overrides. Drop replacement templates into
 * oversee-hub-child/woocommerce/<path> mirroring the WooCommerce templates
 * directory. Initial overrides are placeholders that the child theme adds
 * Oversee branding to (see woocommerce/myaccount/dashboard.php).
 */
add_filter('woocommerce_locate_template', function ($template, $template_name, $template_path) {
    $candidate = OVERSEE_HUB_CHILD_DIR . '/woocommerce/' . $template_name;
    if (file_exists($candidate)) {
        return $candidate;
    }
    return $template;
}, 10, 3);

/**
 * Branded HTML email wrapper. WooCommerce calls these via woocommerce_email_header
 * and woocommerce_email_footer. We override only when the email is going to a
 * customer; admin notifications keep the default WooCommerce layout to avoid
 * disrupting Oversee staff inbox filters.
 */
add_action('woocommerce_email_header', function ($email_heading, $email = null) {
    if (!$email || !method_exists($email, 'is_customer_email') || !$email->is_customer_email()) {
        return;
    }
    $logo = OVERSEE_HUB_CHILD_URL . '/assets/img/oversee-logo.png';
    ?>
    <div style="background:#ffffff;padding:24px;font-family:Inter,Helvetica,Arial,sans-serif;color:#0a0a0a;">
        <div style="max-width:560px;margin:0 auto;border:1px solid #e5e5e5;border-radius:12px;overflow:hidden;">
            <div style="padding:24px 24px 0;text-align:left;">
                <img src="<?php echo esc_url($logo); ?>" alt="Oversee Agency" style="height:28px;display:inline-block;">
            </div>
            <div style="padding:8px 24px 0;">
                <h1 style="font-size:22px;line-height:1.3;margin:16px 0 0;color:#0a0a0a;font-weight:600;">
                    <?php echo esc_html($email_heading); ?>
                </h1>
                <div style="height:3px;width:48px;background:#ff8201;border-radius:2px;margin:12px 0 24px;"></div>
            </div>
            <div style="padding:0 24px 24px;font-size:15px;line-height:1.55;color:#3f3f46;">
    <?php
}, 10, 2);

add_action('woocommerce_email_footer', function ($email = null) {
    if (!$email || !method_exists($email, 'is_customer_email') || !$email->is_customer_email()) {
        return;
    }
    ?>
            </div>
            <div style="padding:16px 24px;background:#fafafa;border-top:1px solid #e5e5e5;font-size:12px;color:#71717a;text-align:center;">
                Oversee Agency · <a href="<?php echo esc_url(home_url('/dashboard/')); ?>" style="color:#ff8201;text-decoration:none;">Open dashboard</a>
            </div>
        </div>
    </div>
    <?php
}, 10, 1);

/**
 * Allow the React SPA to mount on /dashboard/* without Hub injecting its sidebar.
 * Hub looks for a body class to decide whether to print the marketing chrome;
 * this filter strips that class on portal pages.
 */
add_filter('body_class', function ($classes) {
    if (oversee_is_dashboard_surface()) {
        $classes[] = 'oversee-portal';
        $classes[] = 'oversee-no-hub-chrome';
    }
    return $classes;
});
