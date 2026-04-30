<?php
/**
 * Shortcodes: render the customer dashboard inside any WordPress page.
 *
 * Two strictly separate shortcodes:
 *   - [oversee_customer_dashboard] — customer-only view. Even when an
 *     Oversee staff user visits the page, they see the *customer* surface
 *     (subscriptions, projects, store, integrations). Admin/CRM operational
 *     navigation is never rendered here.
 *   - [oversee_admin_dashboard]    — staff-only operational view.
 *     Locked to manage_woocommerce / manage_options. Returns a forbidden
 *     notice for non-staff. The two surfaces never share navigation.
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class OCD_Shortcodes {

    public static function init() {
        add_shortcode('oversee_customer_dashboard', [__CLASS__, 'render_customer_dashboard']);
        add_shortcode('oversee_admin_dashboard', [__CLASS__, 'render_admin_dashboard']);
    }

    public static function render_customer_dashboard($atts = []) {
        try {
            OCD_Assets::enqueue_frontend();
            // Mount role is derived from capabilities, NOT from the shortcode
            // name. A staff user landing on /dashboard/ should boot into the
            // admin console; the customer surface is reserved for non-staff.
            // The dedicated [oversee_admin_dashboard] shortcode remains for
            // pages that should only ever render the staff UI.
            $role = OCD_Assets::derive_role();
            ob_start();
            include OCD_TEMPLATES . '/customer-dashboard.php';
            $html = ob_get_clean();
            return self::inject_mount_role($html, $role);
        } catch (\Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            self::log_render_error('customer', $e);
            return self::render_error_notice();
        }
    }

    /**
     * Replace the hard-coded data-ocd-role on the SPA mount node with the
     * capability-derived role. The template still ships a default value of
     * "customer" for safety in case this method is bypassed.
     */
    private static function inject_mount_role($html, $role) {
        if (!is_string($html) || $html === '') {
            return $html;
        }
        $allowed = ['guest', 'customer', 'admin'];
        if (!in_array($role, $allowed, true)) {
            $role = 'customer';
        }
        return preg_replace(
            '/(data-ocd-role=)"[^"]*"/',
            '$1"' . esc_attr($role) . '"',
            $html,
            1
        );
    }

    public static function render_admin_dashboard($atts = []) {
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            return '<div class="ocd-notice ocd-notice--error">' . esc_html__('You do not have permission to view this page.', 'oversee-customer-dashboard') . '</div>';
        }
        try {
            OCD_Assets::enqueue_frontend();
            ob_start();
            // The template renders ONLY the admin operational surface. Staff who want to QA the
            // customer view should visit the customer page, not see customer panels mixed in here.
            include OCD_TEMPLATES . '/admin-dashboard.php';
            return ob_get_clean();
        } catch (\Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            self::log_render_error('admin', $e);
            return self::render_error_notice();
        }
    }

    private static function render_error_notice() {
        return '<div class="ocd-notice ocd-notice--error">'
            . esc_html__('The Oversee dashboard is temporarily unavailable. Please refresh in a moment, or contact support if the issue persists.', 'oversee-customer-dashboard')
            . '</div>';
    }

    private static function log_render_error($surface, $e) {
        if (function_exists('error_log')) {
            error_log(sprintf(
                '[oversee-customer-dashboard] %s shortcode render failed: %s in %s:%d',
                $surface,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));
        }
    }
}
