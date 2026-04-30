<?php
/**
 * Oversee staff admin dashboard mount point.
 *
 * Same React SPA as the customer surface, just gated to staff. The SPA reads
 * window.OCD_CONFIG.isAdmin (set true for manage_woocommerce / manage_options)
 * and renders the admin pages from /admin under hash routing.
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
    echo '<div class="ocd-notice ocd-notice--error">' . esc_html__('Forbidden.', 'oversee-customer-dashboard') . '</div>';
    return;
}
?>
<div id="oversee-dashboard-root" data-oversee-mount="dashboard" data-ocd-role="admin"></div>
<noscript>
    <div class="ocd-notice ocd-notice--warning">
        <?php esc_html_e('The Oversee admin dashboard requires JavaScript. Please enable it to continue.', 'oversee-customer-dashboard'); ?>
    </div>
</noscript>
