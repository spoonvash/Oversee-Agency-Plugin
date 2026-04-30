<?php
/**
 * Register the full-bleed Oversee Dashboard page template via plugin so it
 * works regardless of the active theme, and auto-assign it to the page with
 * slug "dashboard" so /dashboard/ never gets wrapped in the Hub child theme
 * header / footer / nav.
 *
 * The template file lives in /templates/page-oversee-dashboard.php. Selecting
 * it manually via Page Attributes → Template is also supported.
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class OCD_Page_Template {

    const TEMPLATE_FILE  = 'page-oversee-dashboard.php';
    const TEMPLATE_LABEL = 'Oversee Dashboard (full-bleed)';
    // Bumped from v1 -> v2: PR#5 v1 auto-assign skipped pages whose template
    // was already a non-default value (e.g. elementor_canvas, hub-app.php),
    // which is exactly the production state that broke /dashboard/. v2 forces
    // a one-time re-assign so the full-bleed template wins on those pages too.
    const ASSIGNED_FLAG  = 'ocd_dashboard_template_assigned_v2';

    public static function init() {
        add_filter('theme_page_templates', [__CLASS__, 'register_template'], 10, 4);
        add_filter('template_include', [__CLASS__, 'load_template'], 99);
        add_action('init', [__CLASS__, 'maybe_assign_template'], 20);
    }

    /**
     * Make the plugin template selectable in the Page Attributes meta box.
     */
    public static function register_template($post_templates, $wp_theme = null, $post = null, $post_type = null) {
        if (!is_array($post_templates)) {
            $post_templates = [];
        }
        $post_templates[self::TEMPLATE_FILE] = self::TEMPLATE_LABEL;
        return $post_templates;
    }

    /**
     * When WP looks for the template file, hand it the plugin copy.
     */
    public static function load_template($template) {
        if (!is_singular()) {
            return $template;
        }
        $post = get_queried_object();
        if (!$post || empty($post->ID)) {
            return $template;
        }
        $assigned = get_post_meta($post->ID, '_wp_page_template', true);
        if ($assigned !== self::TEMPLATE_FILE) {
            return $template;
        }
        $candidate = OCD_TEMPLATES . '/' . self::TEMPLATE_FILE;
        if (file_exists($candidate)) {
            return $candidate;
        }
        return $template;
    }

    /**
     * Idempotently assign the full-bleed template to the page with slug
     * "dashboard" the first time this version of the plugin runs. We guard
     * with a versioned one-shot flag so editors who later switch the template
     * away don't get overridden on every page load — but a flag bump (e.g.
     * v1 -> v2) DOES trigger one fresh assignment, which is what PR#6 needs
     * to recover production from a wrong template selection.
     */
    public static function maybe_assign_template() {
        if (get_option(self::ASSIGNED_FLAG)) {
            return;
        }
        if (!function_exists('get_page_by_path')) {
            return;
        }
        $page = get_page_by_path('dashboard');
        if ($page && $page->ID) {
            // Force-assign on the versioned flag transition. The previous
            // "only if empty/default" guard left production stuck on the wrong
            // template after PR#5 because the page already had a non-default
            // value. Future template switches by editors are still respected
            // because the flag prevents a second forced re-assignment.
            update_post_meta($page->ID, '_wp_page_template', self::TEMPLATE_FILE);
        }
        update_option(self::ASSIGNED_FLAG, time(), false);
    }

    /**
     * Force-assign the template at activation. Called from the plugin's
     * register_activation_hook.
     */
    public static function force_assign_on_activation() {
        if (!function_exists('get_page_by_path')) {
            return;
        }
        $page = get_page_by_path('dashboard');
        if ($page && $page->ID) {
            update_post_meta($page->ID, '_wp_page_template', self::TEMPLATE_FILE);
        }
        update_option(self::ASSIGNED_FLAG, time(), false);
    }
}
