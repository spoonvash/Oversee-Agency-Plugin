<?php
/**
 * Oversee override of woocommerce/myaccount/dashboard.php.
 *
 * Replaces the default WooCommerce welcome card with an Oversee-branded one
 * that nudges customers into the Oversee dashboard at /dashboard/. Subscriptions,
 * orders, and addresses still come through the standard WooCommerce navigation
 * — this only customizes the landing card.
 *
 * Compatible with WooCommerce 8+.
 *
 * @package Oversee_Hub_Child
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
?>
<div class="oversee-account-welcome" style="
    background: var(--oversee-surface);
    border: 1px solid var(--oversee-border);
    border-radius: var(--oversee-radius);
    padding: 24px;
    margin-bottom: 24px;
">
    <h2 style="margin:0 0 8px;font-size:20px;font-weight:600;color:var(--oversee-text);">
        <?php
        printf(
            /* translators: 1: user display name */
            esc_html__('Hello %1$s — welcome back.', 'oversee-hub-child'),
            esc_html($current_user->display_name)
        );
        ?>
    </h2>
    <p style="margin:0 0 16px;color:var(--oversee-text-muted);">
        <?php esc_html_e('Manage your projects, conversations, and files in the Oversee dashboard. Use this account area for billing and order details.', 'oversee-hub-child'); ?>
    </p>
    <p style="margin:0;">
        <a class="button" href="<?php echo esc_url(home_url('/dashboard/')); ?>">
            <?php esc_html_e('Open dashboard', 'oversee-hub-child'); ?>
        </a>
        <a class="button secondary" href="<?php echo esc_url(wc_get_account_endpoint_url('orders')); ?>" style="margin-left:8px;">
            <?php esc_html_e('View orders', 'oversee-hub-child'); ?>
        </a>
    </p>
</div>

<?php
/**
 * The default WooCommerce dashboard hook still fires below so plugins (Subscriptions,
 * Memberships, etc.) can attach their own content. We don't render the default
 * "Hello {name}" copy — our welcome card replaces it.
 */
do_action('woocommerce_account_dashboard');
