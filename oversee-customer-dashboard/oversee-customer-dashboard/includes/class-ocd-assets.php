<?php
/**
 * Asset registration / enqueueing.
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class OCD_Assets {

    private static $enqueued = false;

    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'register']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'register_admin']);
    }

    public static function register() {
        wp_register_style('ocd-frontend', OCD_URL . 'assets/css/frontend.css', [], OCD_VERSION);
        wp_register_script('ocd-frontend', OCD_URL . 'assets/js/frontend.js', [], OCD_VERSION, true);
    }

    public static function register_admin($hook) {
        if (strpos((string) $hook, 'oversee-customer-dashboard') !== false) {
            wp_enqueue_style('ocd-admin', OCD_URL . 'assets/css/admin.css', [], OCD_VERSION);
        }
    }

    public static function enqueue_frontend() {
        if (self::$enqueued) return;
        self::$enqueued = true;
        wp_enqueue_style('ocd-frontend');
        wp_enqueue_script('ocd-frontend');
        wp_localize_script('ocd-frontend', 'OCD_CONFIG', [
            'restUrl' => esc_url_raw(rest_url(OCD_REST_API::NAMESPACE . '/')),
            'nonce'   => wp_create_nonce('wp_rest'),
            'isAdmin' => current_user_can('manage_woocommerce') || current_user_can('manage_options'),
        ]);
    }
}
