<?php
/**
 * Auto account-creation + lifecycle hooks for Oversee.
 *
 *   woocommerce_created_customer            → promote to oversee_client, send
 *                                             welcome email, fire ocd_client_created
 *   woocommerce_order_status_completed      → spawn boards from SKUs
 *   woocommerce_subscription_status_*       → archive/restore boards
 *   woocommerce_scheduled_subscription_payment / renewal_payment_complete
 *                                           → reset monthly group on the
 *                                             matching board
 *
 * The original `class-ocd-woocommerce-hooks.php` continues to manage the
 * legacy `ocd_projects/...` tables. This file owns the new project-board
 * lifecycle so the two systems are clearly separated.
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class OCD_Signup_Hooks {

    public static function init() {
        add_action('woocommerce_created_customer', [__CLASS__, 'on_customer_created'], 10, 3);

        // Order completion fans out to BOTH the legacy projects flow (already
        // wired in OCD_WooCommerce_Hooks) and the new boards flow here. We hook
        // a level later so the legacy flow runs first.
        add_action('woocommerce_order_status_completed', [__CLASS__, 'on_order_completed'], 20, 1);

        // Subscription lifecycle.
        add_action('woocommerce_subscription_status_active',          [__CLASS__, 'on_sub_active'], 20, 1);
        add_action('woocommerce_subscription_status_on-hold',         [__CLASS__, 'on_sub_paused'], 20, 1);
        add_action('woocommerce_subscription_status_pending-cancel',  [__CLASS__, 'on_sub_paused'], 20, 1);
        add_action('woocommerce_subscription_status_cancelled',       [__CLASS__, 'on_sub_cancelled'], 20, 1);
        add_action('woocommerce_subscription_status_expired',         [__CLASS__, 'on_sub_cancelled'], 20, 1);

        // Renewal — cycle the monthly group on each affected board.
        add_action('woocommerce_subscription_renewal_payment_complete', [__CLASS__, 'on_sub_renewed'], 20, 1);
        add_action('woocommerce_scheduled_subscription_payment',        [__CLASS__, 'on_sub_renewed'], 20, 1);
    }

    public static function on_customer_created($user_id, $new_customer_data, $password_generated) {
        if (!$user_id) {
            return;
        }
        if (class_exists('OCD_Roles')) {
            OCD_Roles::promote_to_client($user_id);
        }
        self::send_welcome_email((int) $user_id);
        do_action('ocd_client_created', (int) $user_id, $new_customer_data);
    }

    public static function on_order_completed($order_id) {
        if (!function_exists('wc_get_order')) {
            return;
        }
        $order = wc_get_order((int) $order_id);
        if (!$order) {
            return;
        }
        if (!class_exists('OCD_Boards')) {
            return;
        }
        OCD_Boards::spawn_for_order($order);
    }

    public static function on_sub_active($subscription) {
        $boards = self::boards_for_subscription($subscription);
        foreach ($boards as $b) {
            if ((int) $b['archived'] === 1) {
                OCD_Boards::restore_board((int) $b['id']);
            }
        }
    }

    public static function on_sub_paused($subscription) {
        // We don't archive on paused — the client should still see history.
        $boards = self::boards_for_subscription($subscription);
        foreach ($boards as $b) {
            global $wpdb;
            $wpdb->update(
                OCD_Board_Schema::table('boards'),
                ['status' => 'paused'],
                ['id' => (int) $b['id']]
            );
            OCD_Boards::log((int) $b['id'], null, 0, 'board.paused');
        }
    }

    public static function on_sub_cancelled($subscription) {
        $boards = self::boards_for_subscription($subscription);
        foreach ($boards as $b) {
            OCD_Boards::archive_board((int) $b['id']);
        }
    }

    public static function on_sub_renewed($subscription_or_id) {
        $sub_id = is_object($subscription_or_id) && method_exists($subscription_or_id, 'get_id')
            ? (int) $subscription_or_id->get_id()
            : (int) $subscription_or_id;
        if (!$sub_id) {
            return;
        }
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM " . OCD_Board_Schema::table('boards') . " WHERE wc_subscription_id = %d AND archived = 0",
            $sub_id
        ), ARRAY_A);
        foreach (($rows ?: []) as $r) {
            OCD_Boards::reset_monthly_group((int) $r['id']);
        }
    }

    private static function boards_for_subscription($subscription) {
        if (!$subscription || !method_exists($subscription, 'get_id')) {
            return [];
        }
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, archived FROM " . OCD_Board_Schema::table('boards') . " WHERE wc_subscription_id = %d",
            (int) $subscription->get_id()
        ), ARRAY_A);
        return $rows ?: [];
    }

    /**
     * Send a branded welcome email to the new client. We deliberately keep
     * this minimal HTML inline so the email renders even if the WooCommerce
     * email styles aren't loaded for this transactional message.
     */
    public static function send_welcome_email($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }
        $dashboard_url = home_url('/dashboard/');
        $subject = sprintf(__('[%s] Welcome to Oversee', 'oversee-customer-dashboard'), wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES));
        $first_name = $user->first_name ?: $user->display_name ?: $user->user_login;

        $body  = "<div style=\"font-family:Inter,Arial,sans-serif;color:#0a0a0a;background:#fff;padding:24px;\">";
        $body .= "<div style=\"max-width:560px;margin:0 auto;border:1px solid #e5e5e5;border-radius:12px;overflow:hidden;\">";
        $body .= "<div style=\"padding:24px;\">";
        $body .= "<h1 style=\"margin:0;font-size:22px;color:#0a0a0a;\">Welcome to Oversee, " . esc_html($first_name) . "</h1>";
        $body .= "<div style=\"height:3px;width:48px;background:#ff8201;border-radius:2px;margin:12px 0 16px;\"></div>";
        $body .= "<p style=\"font-size:15px;line-height:1.55;color:#3f3f46;\">Your account is ready. Open your dashboard to see your projects, talk to your account manager, and access your files.</p>";
        $body .= "<p style=\"margin:24px 0;\"><a href=\"" . esc_url($dashboard_url) . "\" style=\"display:inline-block;background:#ff8201;color:#fff;padding:12px 18px;border-radius:8px;text-decoration:none;font-weight:500;\">Open dashboard</a></p>";
        $body .= "<p style=\"font-size:13px;color:#71717a;\">If you didn't expect this email, please ignore it.</p>";
        $body .= "</div></div></div>";

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        wp_mail($user->user_email, $subject, $body, $headers);
    }
}
