<?php
/**
 * Customer-facing dashboard mount point.
 *
 * The React SPA (built from /spa) hydrates into #oversee-dashboard-root and
 * renders the customer surface using hash-based routing. Server-side concerns
 * (auth, role gating, REST endpoints, WooCommerce integration) live in PHP;
 * the SPA fetches via REST and reads runtime config from window.OCD_CONFIG /
 * window.OVERSEE_CONFIG, both injected by OCD_Assets::enqueue_frontend().
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!is_user_logged_in()) {
    echo '<div class="ocd-notice ocd-notice--info"><p>'
        . esc_html__('Please sign in to view your dashboard.', 'oversee-customer-dashboard') . ' '
        . '<a href="' . esc_url(wp_login_url(get_permalink())) . '">' . esc_html__('Sign in', 'oversee-customer-dashboard') . '</a>'
        . '</p></div>';
    return;
}
?>
<div id="oversee-dashboard-root" data-oversee-mount="dashboard" data-ocd-role="customer"></div>
<noscript>
    <div class="ocd-notice ocd-notice--warning">
        <?php esc_html_e('The Oversee dashboard requires JavaScript. Please enable it to continue.', 'oversee-customer-dashboard'); ?>
    </div>
</noscript>
