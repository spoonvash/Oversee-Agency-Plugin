<?php
/**
 * Shortcodes: render the customer dashboard inside any WordPress page.
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
        include OCD_TEMPLATES . '/customer-dashboard.php';
        return ob_get_clean();
    }

    public static function render_admin_dashboard($atts = []) {
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            return '<div class="ocd-notice ocd-notice--error">' . esc_html__('You do not have permission to view this page.', 'oversee-customer-dashboard') . '</div>';
        }
        OCD_Assets::enqueue_frontend();
        ob_start();
        include OCD_TEMPLATES . '/admin-dashboard.php';
        return ob_get_clean();
    }
}
