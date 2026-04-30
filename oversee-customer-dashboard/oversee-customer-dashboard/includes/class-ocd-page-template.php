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
    const ASSIGNED_FLAG  = 'ocd_dashboard_template_assigned_v1';

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
     * "dashboard" the first time the plugin runs after activation. We guard
     * with a one-shot option so editors who deliberately switch the template
     * back to Default don't get overridden on every page load.
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
            $current = get_post_meta($page->ID, '_wp_page_template', true);
            if (!$current || $current === 'default' || $current === '') {
                update_post_meta($page->ID, '_wp_page_template', self::TEMPLATE_FILE);
            }
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
