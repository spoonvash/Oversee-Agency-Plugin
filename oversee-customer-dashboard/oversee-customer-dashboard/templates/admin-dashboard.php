<?php
/**
 * Oversee staff admin dashboard template.
 *
 * Information architecture (post-cleanup): six top-level destinations —
 * Command (overview), Inbox (messages), Clients (customers + CRM contacts),
 * Work (projects + tasks + files), Billing (subscriptions + product map),
 * Settings (sync + product category + integrations). Old Subscriptions /
 * CRM / Sync / Entitlements / Tasks / Projects tabs that all lived next
 * to each other are merged into these six.
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
                <p class="ocd-header__sub"><?php esc_html_e('Inbox, work, clients and billing in one place.', 'oversee-customer-dashboard'); ?></p>
            </div>
        </div>
        <nav class="ocd-tabs" role="tablist">
            <button class="ocd-tab is-active" data-ocd-tab="command"><?php esc_html_e('Command', 'oversee-customer-dashboard'); ?></button>
            <button class="ocd-tab" data-ocd-tab="inbox"><?php esc_html_e('Inbox', 'oversee-customer-dashboard'); ?></button>
            <button class="ocd-tab" data-ocd-tab="clients"><?php esc_html_e('Clients', 'oversee-customer-dashboard'); ?></button>
            <button class="ocd-tab" data-ocd-tab="work"><?php esc_html_e('Work', 'oversee-customer-dashboard'); ?></button>
            <button class="ocd-tab" data-ocd-tab="billing"><?php esc_html_e('Billing', 'oversee-customer-dashboard'); ?></button>
            <button class="ocd-tab" data-ocd-tab="settings"><?php esc_html_e('Settings', 'oversee-customer-dashboard'); ?></button>
        </nav>
    </header>

    <section class="ocd-tab-panel is-active" data-ocd-panel="command">
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

    <section class="ocd-tab-panel" data-ocd-panel="inbox">
        <div class="ocd-card">
            <div class="ocd-card__header"><h2><?php esc_html_e('Customer messages', 'oversee-customer-dashboard'); ?></h2><button class="ocd-btn ocd-btn--ghost" data-ocd-action="refresh-inbox"><?php esc_html_e('Refresh', 'oversee-customer-dashboard'); ?></button></div>
            <div class="ocd-inbox">
                <ul class="ocd-inbox__list" data-ocd-list="admin-inbox"><li class="ocd-empty"><?php esc_html_e('Loading…', 'oversee-customer-dashboard'); ?></li></ul>
                <div class="ocd-inbox__thread">
                    <div class="ocd-inbox__thread-head" data-ocd-block="thread-head"><p class="ocd-muted"><?php esc_html_e('Select a conversation to view it.', 'oversee-customer-dashboard'); ?></p></div>
                    <div class="ocd-inbox__messages" data-ocd-list="admin-thread"></div>
                    <form class="ocd-msg-form" data-ocd-form="admin-reply" hidden>
                        <textarea maxlength="5000" name="message" placeholder="<?php esc_attr_e('Reply to customer…', 'oversee-customer-dashboard'); ?>"></textarea>
                        <div class="ocd-msg-form__row">
                            <label class="ocd-btn ocd-btn--ghost ocd-msg-form__attach">
                                <input type="file" name="attachment" accept="image/png,image/jpeg,image/gif,image/webp" multiple hidden />
                                <span><?php esc_html_e('Attach image', 'oversee-customer-dashboard'); ?></span>
                            </label>
                            <div class="ocd-msg-form__previews" data-ocd-msg-previews="admin"></div>
                            <button type="submit" class="ocd-btn ocd-btn--primary"><?php esc_html_e('Send reply', 'oversee-customer-dashboard'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <section class="ocd-tab-panel" data-ocd-panel="clients">
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
        <div class="ocd-grid ocd-grid--main">
            <div class="ocd-card">
                <div class="ocd-card__header">
                    <h2><?php esc_html_e('CRM contacts', 'oversee-customer-dashboard'); ?></h2>
                    <input type="search" class="ocd-input" data-ocd-search="contacts" placeholder="<?php esc_attr_e('Search HighLevel contacts…', 'oversee-customer-dashboard'); ?>" />
                </div>
                <ul class="ocd-list" data-ocd-list="admin-contacts"><li class="ocd-empty"><?php esc_html_e('Loading…', 'oversee-customer-dashboard'); ?></li></ul>
            </div>
            <div class="ocd-card">
                <div class="ocd-card__header"><h2><?php esc_html_e('CRM opportunities', 'oversee-customer-dashboard'); ?></h2></div>
                <ul class="ocd-list" data-ocd-list="admin-opportunities"><li class="ocd-empty"><?php esc_html_e('Loading…', 'oversee-customer-dashboard'); ?></li></ul>
            </div>
        </div>
    </section>

    <section class="ocd-tab-panel" data-ocd-panel="work">
        <div class="ocd-card">
            <div class="ocd-card__header">
                <h2><?php esc_html_e('Tasks', 'oversee-customer-dashboard'); ?></h2>
                <span class="ocd-card__hint"><?php esc_html_e('Drag a card between columns to update its status.', 'oversee-customer-dashboard'); ?></span>
                <button class="ocd-btn ocd-btn--primary" data-ocd-action="new-task"><?php esc_html_e('Assign task', 'oversee-customer-dashboard'); ?></button>
            </div>
            <div class="ocd-kanban" data-ocd-kanban="admin">
                <p class="ocd-empty"><?php esc_html_e('Loading…', 'oversee-customer-dashboard'); ?></p>
            </div>
        </div>
        <div class="ocd-card">
            <div class="ocd-card__header">
                <h2><?php esc_html_e('Projects', 'oversee-customer-dashboard'); ?></h2>
                <button class="ocd-btn ocd-btn--primary" data-ocd-action="new-project"><?php esc_html_e('New project', 'oversee-customer-dashboard'); ?></button>
            </div>
            <div class="ocd-projects" data-ocd-list="admin-projects">
                <p class="ocd-empty"><?php esc_html_e('Loading…', 'oversee-customer-dashboard'); ?></p>
            </div>
        </div>
    </section>

    <section class="ocd-tab-panel" data-ocd-panel="billing">
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
        <div class="ocd-card">
            <div class="ocd-card__header">
                <h2><?php esc_html_e('Existing WooCommerce products → feature mapping', 'oversee-customer-dashboard'); ?></h2>
            </div>
            <p class="ocd-muted">
                <?php esc_html_e('Map an existing WooCommerce product to a dashboard feature slug. The dashboard never creates new products — only existing WC products are surfaced. When a customer buys a mapped product (or their subscription activates), the matching feature is granted automatically.', 'oversee-customer-dashboard'); ?>
            </p>

            <div class="ocd-product-picker">
                <label class="ocd-product-picker__label">
                    <?php esc_html_e('Search existing products', 'oversee-customer-dashboard'); ?>
                    <input type="search" class="ocd-input" data-ocd-search="wc-products" placeholder="<?php esc_attr_e('Type a product name or SKU…', 'oversee-customer-dashboard'); ?>" autocomplete="off" />
                </label>
                <ul class="ocd-product-picker__results" data-ocd-list="wc-products" hidden></ul>
            </div>

            <div class="ocd-table-wrap">
                <table class="ocd-table">
                    <thead><tr>
                        <th></th>
                        <th><?php esc_html_e('Product', 'oversee-customer-dashboard'); ?></th>
                        <th><?php esc_html_e('Feature slug', 'oversee-customer-dashboard'); ?></th>
                        <th><?php esc_html_e('Label', 'oversee-customer-dashboard'); ?></th>
                        <th></th>
                    </tr></thead>
                    <tbody data-ocd-list="admin-product-map"><tr><td colspan="5" class="ocd-empty"><?php esc_html_e('Loading…', 'oversee-customer-dashboard'); ?></td></tr></tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="ocd-tab-panel" data-ocd-panel="settings">
        <div class="ocd-card">
            <div class="ocd-card__header"><h2><?php esc_html_e('Connection status', 'oversee-customer-dashboard'); ?></h2><button class="ocd-btn ocd-btn--ghost" data-ocd-action="refresh-sync"><?php esc_html_e('Refresh', 'oversee-customer-dashboard'); ?></button></div>
            <ul class="ocd-list" data-ocd-list="sync-status">
                <li><strong>HighLevel (agency):</strong> <?php echo $status['highlevel'] ? esc_html__('configured', 'oversee-customer-dashboard') : esc_html__('not configured', 'oversee-customer-dashboard'); ?></li>
                <li><strong>WooCommerce:</strong> <?php echo $status['woocommerce'] ? esc_html__('configured', 'oversee-customer-dashboard') : esc_html__('not configured', 'oversee-customer-dashboard'); ?></li>
            </ul>
            <p class="ocd-muted">
                <?php esc_html_e('Live ping checks both APIs. Tokens stay server-side. Customer-owned CRM connections are stored separately and never mixed with agency credentials.', 'oversee-customer-dashboard'); ?>
            </p>
        </div>
        <div class="ocd-card">
            <div class="ocd-card__header">
                <h2><?php esc_html_e('Optional: store product category', 'oversee-customer-dashboard'); ?></h2>
            </div>
            <p class="ocd-muted">
                <?php esc_html_e('Pick an existing WooCommerce product category to surface every published product in that category in the dashboard store — in addition to any explicit mappings under Billing. Leave empty to only show explicitly-mapped products.', 'oversee-customer-dashboard'); ?>
            </p>
            <div data-ocd-block="store-category">
                <p class="ocd-empty"><?php esc_html_e('Loading…', 'oversee-customer-dashboard'); ?></p>
            </div>
        </div>
        <div class="ocd-card">
            <p class="ocd-muted">
                <?php
                /* translators: %s is a Settings link. */
                printf(
                    esc_html__('Configure HighLevel/WooCommerce credentials in %s.', 'oversee-customer-dashboard'),
                    '<a href="' . esc_url(admin_url('admin.php?page=oversee-customer-dashboard-settings')) . '">' . esc_html__('Oversee Dashboard → Settings', 'oversee-customer-dashboard') . '</a>'
                );
                ?>
            </p>
        </div>
    </section>
</div>
