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
 * The mount node ships with a default data-ocd-role="customer" but
 * OCD_Shortcodes::render_customer_dashboard rewrites it from the current
 * user's capabilities so logged-in staff get the admin console and logged-out
 * visitors get the passwordless login CTA.
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div
    id="oversee-dashboard-root"
    class="oversee-dashboard-mount"
    data-oversee-mount="dashboard"
    data-ocd-role="customer"
></div>
<noscript>
    <div class="ocd-notice ocd-notice--warning">
        <?php esc_html_e('The Oversee dashboard requires JavaScript. Please enable it to continue.', 'oversee-customer-dashboard'); ?>
    </div>
</noscript>
