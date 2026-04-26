<?php
/**
 * Oversee staff admin dashboard template.
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

$status = OCD_Settings::connection_status();
?>
<div class="ocd-app ocd-app--admin" data-ocd-role="admin">
    <header class="ocd-header">
        <div class="ocd-header__brand">
            <span class="ocd-logo-mark" aria-hidden="true">O</span>
            <div>
                <h1 class="ocd-header__title"><?php esc_html_e('Oversee Admin', 'oversee-customer-dashboard'); ?></h1>
                <p class="ocd-header__sub"><?php esc_html_e('Manage customers, subscriptions, CRM contacts, opportunities and ticket links.', 'oversee-customer-dashboard'); ?></p>
            </div>
        </div>
        <nav class="ocd-tabs" role="tablist">
            <button class="ocd-tab is-active" data-ocd-tab="overview"><?php esc_html_e('Overview', 'oversee-customer-dashboard'); ?></button>
            <button class="ocd-tab" data-ocd-tab="customers"><?php esc_html_e('Customers', 'oversee-customer-dashboard'); ?></button>
            <button class="ocd-tab" data-ocd-tab="subscriptions"><?php esc_html_e('Subscriptions', 'oversee-customer-dashboard'); ?></button>
            <button class="ocd-tab" data-ocd-tab="crm"><?php esc_html_e('CRM', 'oversee-customer-dashboard'); ?></button>
            <button class="ocd-tab" data-ocd-tab="sync"><?php esc_html_e('Sync', 'oversee-customer-dashboard'); ?></button>
        </nav>
    </header>

    <section class="ocd-tab-panel is-active" data-ocd-panel="overview">
        <div class="ocd-grid ocd-grid--summary">
            <div class="ocd-card ocd-stat"><span class="ocd-stat__label"><?php esc_html_e('Active subscriptions', 'oversee-customer-dashboard'); ?></span><span class="ocd-stat__value" data-ocd-admin-stat="active">—</span></div>
            <div class="ocd-card ocd-stat"><span class="ocd-stat__label"><?php esc_html_e('On hold', 'oversee-customer-dashboard'); ?></span><span class="ocd-stat__value" data-ocd-admin-stat="on-hold">—</span></div>
            <div class="ocd-card ocd-stat"><span class="ocd-stat__label"><?php esc_html_e('Pending cancel', 'oversee-customer-dashboard'); ?></span><span class="ocd-stat__value" data-ocd-admin-stat="pending-cancel">—</span></div>
            <div class="ocd-card ocd-stat"><span class="ocd-stat__label"><?php esc_html_e('Cancelled (recent)', 'oversee-customer-dashboard'); ?></span><span class="ocd-stat__value" data-ocd-admin-stat="cancelled">—</span></div>
        </div>
        <div class="ocd-card">
            <div class="ocd-card__header"><h2><?php esc_html_e('Recent subscriptions', 'oversee-customer-dashboard'); ?></h2></div>
            <div class="ocd-table-wrap">
                <table class="ocd-table">
                    <thead><tr>
                        <th>#</th><th><?php esc_html_e('Customer', 'oversee-customer-dashboard'); ?></th><th><?php esc_html_e('Status', 'oversee-customer-dashboard'); ?></th><th><?php esc_html_e('Total', 'oversee-customer-dashboard'); ?></th><th><?php esc_html_e('Next', 'oversee-customer-dashboard'); ?></th>
                    </tr></thead>
                    <tbody data-ocd-list="admin-recent-subs"><tr><td colspan="5" class="ocd-empty"><?php esc_html_e('Loading…', 'oversee-customer-dashboard'); ?></td></tr></tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="ocd-tab-panel" data-ocd-panel="customers">
        <div class="ocd-card">
            <div class="ocd-card__header">
                <h2><?php esc_html_e('Customers', 'oversee-customer-dashboard'); ?></h2>
                <input type="search" class="ocd-input" data-ocd-search="customers" placeholder="<?php esc_attr_e('Search by name or email…', 'oversee-customer-dashboard'); ?>" />
            </div>
            <div class="ocd-table-wrap">
                <table class="ocd-table">
                    <thead><tr><th>#</th><th><?php esc_html_e('Name', 'oversee-customer-dashboard'); ?></th><th><?php esc_html_e('Email', 'oversee-customer-dashboard'); ?></th><th><?php esc_html_e('Orders', 'oversee-customer-dashboard'); ?></th><th><?php esc_html_e('Spent', 'oversee-customer-dashboard'); ?></th></tr></thead>
                    <tbody data-ocd-list="admin-customers"><tr><td colspan="5" class="ocd-empty"><?php esc_html_e('Loading…', 'oversee-customer-dashboard'); ?></td></tr></tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="ocd-tab-panel" data-ocd-panel="subscriptions">
        <div class="ocd-card">
            <div class="ocd-card__header">
                <h2><?php esc_html_e('Subscriptions', 'oversee-customer-dashboard'); ?></h2>
                <select class="ocd-input" data-ocd-filter="sub-status">
                    <option value=""><?php esc_html_e('All statuses', 'oversee-customer-dashboard'); ?></option>
                    <option value="active">active</option>
                    <option value="pending">pending</option>
                    <option value="on-hold">on-hold</option>
                    <option value="pending-cancel">pending-cancel</option>
                    <option value="cancelled">cancelled</option>
                    <option value="expired">expired</option>
                </select>
            </div>
            <div class="ocd-table-wrap">
                <table class="ocd-table">
                    <thead><tr><th>#</th><th><?php esc_html_e('Customer', 'oversee-customer-dashboard'); ?></th><th><?php esc_html_e('Status', 'oversee-customer-dashboard'); ?></th><th><?php esc_html_e('Total', 'oversee-customer-dashboard'); ?></th><th><?php esc_html_e('Actions', 'oversee-customer-dashboard'); ?></th></tr></thead>
                    <tbody data-ocd-list="admin-subscriptions"><tr><td colspan="5" class="ocd-empty"><?php esc_html_e('Loading…', 'oversee-customer-dashboard'); ?></td></tr></tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="ocd-tab-panel" data-ocd-panel="crm">
        <div class="ocd-grid ocd-grid--main">
            <div class="ocd-card">
                <div class="ocd-card__header">
                    <h2><?php esc_html_e('Contacts', 'oversee-customer-dashboard'); ?></h2>
                    <input type="search" class="ocd-input" data-ocd-search="contacts" placeholder="<?php esc_attr_e('Search HighLevel contacts…', 'oversee-customer-dashboard'); ?>" />
                </div>
                <ul class="ocd-list" data-ocd-list="admin-contacts"><li class="ocd-empty"><?php esc_html_e('Loading…', 'oversee-customer-dashboard'); ?></li></ul>
            </div>
            <div class="ocd-card">
                <div class="ocd-card__header"><h2><?php esc_html_e('Opportunities', 'oversee-customer-dashboard'); ?></h2></div>
                <ul class="ocd-list" data-ocd-list="admin-opportunities"><li class="ocd-empty"><?php esc_html_e('Loading…', 'oversee-customer-dashboard'); ?></li></ul>
            </div>
        </div>
    </section>

    <section class="ocd-tab-panel" data-ocd-panel="sync">
        <div class="ocd-card">
            <div class="ocd-card__header"><h2><?php esc_html_e('Connection status', 'oversee-customer-dashboard'); ?></h2><button class="ocd-btn ocd-btn--ghost" data-ocd-action="refresh-sync"><?php esc_html_e('Refresh', 'oversee-customer-dashboard'); ?></button></div>
            <ul class="ocd-list" data-ocd-list="sync-status">
                <li><strong>HighLevel:</strong> <?php echo $status['highlevel'] ? esc_html__('configured', 'oversee-customer-dashboard') : esc_html__('not configured', 'oversee-customer-dashboard'); ?></li>
                <li><strong>WooCommerce:</strong> <?php echo $status['woocommerce'] ? esc_html__('configured', 'oversee-customer-dashboard') : esc_html__('not configured', 'oversee-customer-dashboard'); ?></li>
            </ul>
            <p class="ocd-muted"><?php esc_html_e('Live ping checks both APIs. Tokens stay server-side.', 'oversee-customer-dashboard'); ?></p>
        </div>
    </section>
</div>
