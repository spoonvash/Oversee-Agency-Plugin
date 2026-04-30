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
        OCD_Assets::enqueue_frontend();
        ob_start();
        // The template renders ONLY customer-facing panels. No admin/CRM operational nav.
        include OCD_TEMPLATES . '/customer-dashboard.php';
        return ob_get_clean();
    }

    public static function render_admin_dashboard($atts = []) {
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            return '<div class="ocd-notice ocd-notice--error">' . esc_html__('You do not have permission to view this page.', 'oversee-customer-dashboard') . '</div>';
        }
        OCD_Assets::enqueue_frontend();
        ob_start();
        // The template renders ONLY the admin operational surface. Staff who want to QA the
        // customer view should visit the customer page, not see customer panels mixed in here.
        include OCD_TEMPLATES . '/admin-dashboard.php';
        return ob_get_clean();
    }
}
