<?php
/**
 * Customer-facing dashboard template.
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

$user   = wp_get_current_user();
$status = OCD_Settings::connection_status();
?>
<div class="ocd-app" data-ocd-role="customer">
    <header class="ocd-header">
        <div class="ocd-header__brand">
            <span class="ocd-logo-mark" aria-hidden="true">O</span>
            <div>
                <h1 class="ocd-header__title"><?php esc_html_e('Customer Dashboard', 'oversee-customer-dashboard'); ?></h1>
                <p class="ocd-header__sub"><?php echo esc_html(sprintf(__('Welcome back, %s', 'oversee-customer-dashboard'), $user->display_name ?: $user->user_email)); ?></p>
            </div>
        </div>
        <div class="ocd-header__meta">
            <a class="ocd-btn ocd-btn--ghost" href="<?php echo esc_url(wc_get_account_endpoint_url('orders') ?: home_url('/my-account/')); ?>"><?php esc_html_e('My Account', 'oversee-customer-dashboard'); ?></a>
            <a class="ocd-btn ocd-btn--ghost" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"><?php esc_html_e('Sign out', 'oversee-customer-dashboard'); ?></a>
        </div>
    </header>

    <?php if (!$status['highlevel'] && !$status['woocommerce']) : ?>
        <div class="ocd-notice ocd-notice--warning">
            <strong><?php esc_html_e('Setup required.', 'oversee-customer-dashboard'); ?></strong>
            <?php esc_html_e('Connect HighLevel CRM and WooCommerce in Oversee Dashboard → Settings to power this view.', 'oversee-customer-dashboard'); ?>
        </div>
    <?php endif; ?>

    <section class="ocd-grid ocd-grid--summary">
        <div class="ocd-card ocd-stat">
            <span class="ocd-stat__label"><?php esc_html_e('Active subscriptions', 'oversee-customer-dashboard'); ?></span>
            <span class="ocd-stat__value" data-ocd-stat="active-subs">—</span>
        </div>
        <div class="ocd-card ocd-stat">
            <span class="ocd-stat__label"><?php esc_html_e('Total orders', 'oversee-customer-dashboard'); ?></span>
            <span class="ocd-stat__value" data-ocd-stat="total-orders">—</span>
        </div>
        <div class="ocd-card ocd-stat">
            <span class="ocd-stat__label"><?php esc_html_e('Open opportunities', 'oversee-customer-dashboard'); ?></span>
            <span class="ocd-stat__value" data-ocd-stat="open-opps">—</span>
        </div>
        <div class="ocd-card ocd-stat">
            <span class="ocd-stat__label"><?php esc_html_e('CRM sync', 'oversee-customer-dashboard'); ?></span>
            <span class="ocd-stat__value ocd-stat__value--small" data-ocd-stat="crm-sync"><?php echo $status['highlevel'] ? esc_html__('Connected', 'oversee-customer-dashboard') : esc_html__('Disconnected', 'oversee-customer-dashboard'); ?></span>
        </div>
    </section>

    <section class="ocd-grid ocd-grid--main">
        <div class="ocd-card">
            <div class="ocd-card__header">
                <h2><?php esc_html_e('Subscriptions', 'oversee-customer-dashboard'); ?></h2>
                <span class="ocd-card__hint" data-ocd-hint="subs"></span>
            </div>
            <div class="ocd-table-wrap">
                <table class="ocd-table">
                    <thead><tr>
                        <th><?php esc_html_e('Subscription', 'oversee-customer-dashboard'); ?></th>
                        <th><?php esc_html_e('Status', 'oversee-customer-dashboard'); ?></th>
                        <th><?php esc_html_e('Total', 'oversee-customer-dashboard'); ?></th>
                        <th><?php esc_html_e('Next payment', 'oversee-customer-dashboard'); ?></th>
                        <th></th>
                    </tr></thead>
                    <tbody data-ocd-list="subscriptions">
                        <tr><td colspan="5" class="ocd-empty"><?php esc_html_e('Loading…', 'oversee-customer-dashboard'); ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="ocd-card">
            <div class="ocd-card__header">
                <h2><?php esc_html_e('Recent orders', 'oversee-customer-dashboard'); ?></h2>
            </div>
            <ul class="ocd-list" data-ocd-list="orders">
                <li class="ocd-empty"><?php esc_html_e('Loading…', 'oversee-customer-dashboard'); ?></li>
            </ul>
        </div>
    </section>

    <section class="ocd-grid ocd-grid--side">
        <div class="ocd-card">
            <div class="ocd-card__header">
                <h2><?php esc_html_e('CRM contact', 'oversee-customer-dashboard'); ?></h2>
            </div>
            <div data-ocd-block="crm-contact" class="ocd-empty"><?php esc_html_e('Loading…', 'oversee-customer-dashboard'); ?></div>
        </div>
        <div class="ocd-card">
            <div class="ocd-card__header">
                <h2><?php esc_html_e('Opportunities', 'oversee-customer-dashboard'); ?></h2>
            </div>
            <ul class="ocd-list" data-ocd-list="opportunities">
                <li class="ocd-empty"><?php esc_html_e('Loading…', 'oversee-customer-dashboard'); ?></li>
            </ul>
        </div>
        <div class="ocd-card">
            <div class="ocd-card__header">
                <h2><?php esc_html_e('Need help?', 'oversee-customer-dashboard'); ?></h2>
            </div>
            <p class="ocd-muted"><?php esc_html_e('Open a support ticket through the Oversee Helpdesk.', 'oversee-customer-dashboard'); ?></p>
            <p>
                <?php
                $helpdesk_url = apply_filters('ocd_helpdesk_url', '/support/');
                ?>
                <a class="ocd-btn ocd-btn--primary" href="<?php echo esc_url($helpdesk_url); ?>"><?php esc_html_e('Open Helpdesk', 'oversee-customer-dashboard'); ?></a>
            </p>
        </div>
    </section>
</div>
