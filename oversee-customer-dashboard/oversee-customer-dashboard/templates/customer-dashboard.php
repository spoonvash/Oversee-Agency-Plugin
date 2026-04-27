<?php
/**
 * Customer-facing dashboard template.
 *
 * Information architecture (post-cleanup): a single primary nav with five
 * destinations — Home, Projects, Messages, Files, Billing. Add-ons/services
 * live as a card on Home instead of a separate Store tab. The customer-owned
 * CRM is exposed as a settings card on Home rather than its own top-level
 * tab so we don't show two ways to reach the same thing.
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
            <a class="ocd-btn ocd-btn--ghost" href="<?php echo esc_url(function_exists('wc_get_account_endpoint_url') ? (wc_get_account_endpoint_url('orders') ?: home_url('/my-account/')) : home_url('/my-account/')); ?>"><?php esc_html_e('My Account', 'oversee-customer-dashboard'); ?></a>
            <a class="ocd-btn ocd-btn--ghost" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"><?php esc_html_e('Sign out', 'oversee-customer-dashboard'); ?></a>
        </div>
    </header>

    <nav class="ocd-tabs" role="tablist">
        <button class="ocd-tab is-active" data-ocd-cust-tab="home"><?php esc_html_e('Home', 'oversee-customer-dashboard'); ?></button>
        <button class="ocd-tab" data-ocd-cust-tab="projects"><?php esc_html_e('Projects', 'oversee-customer-dashboard'); ?></button>
        <button class="ocd-tab" data-ocd-cust-tab="messages"><?php esc_html_e('Messages', 'oversee-customer-dashboard'); ?></button>
        <button class="ocd-tab" data-ocd-cust-tab="files"><?php esc_html_e('Files', 'oversee-customer-dashboard'); ?></button>
        <button class="ocd-tab" data-ocd-cust-tab="billing"><?php esc_html_e('Billing', 'oversee-customer-dashboard'); ?></button>
    </nav>

    <?php if (!$status['highlevel'] && !$status['woocommerce']) : ?>
        <div class="ocd-notice ocd-notice--warning">
            <strong><?php esc_html_e('Setup required.', 'oversee-customer-dashboard'); ?></strong>
            <?php esc_html_e('Connect HighLevel CRM and WooCommerce in Oversee Dashboard → Settings to power this view.', 'oversee-customer-dashboard'); ?>
        </div>
    <?php endif; ?>

    <section class="ocd-cust-panel is-active" data-ocd-cust-panel="home">
        <div class="ocd-grid ocd-grid--summary">
            <div class="ocd-card ocd-stat">
                <span class="ocd-stat__label"><?php esc_html_e('Active subscriptions', 'oversee-customer-dashboard'); ?></span>
                <span class="ocd-stat__value" data-ocd-stat="active-subs">—</span>
            </div>
            <div class="ocd-card ocd-stat">
                <span class="ocd-stat__label"><?php esc_html_e('Open projects', 'oversee-customer-dashboard'); ?></span>
                <span class="ocd-stat__value" data-ocd-stat="open-projects">—</span>
            </div>
            <div class="ocd-card ocd-stat">
                <span class="ocd-stat__label"><?php esc_html_e('Tasks to do', 'oversee-customer-dashboard'); ?></span>
                <span class="ocd-stat__value" data-ocd-stat="open-tasks">—</span>
            </div>
            <div class="ocd-card ocd-stat">
                <span class="ocd-stat__label"><?php esc_html_e('Total orders', 'oversee-customer-dashboard'); ?></span>
                <span class="ocd-stat__value" data-ocd-stat="total-orders">—</span>
            </div>
        </div>

        <div class="ocd-card">
            <div class="ocd-card__header">
                <h2><?php esc_html_e('Your tasks', 'oversee-customer-dashboard'); ?></h2>
                <span class="ocd-card__hint"><?php esc_html_e('Drag a card between columns to update its status.', 'oversee-customer-dashboard'); ?></span>
            </div>
            <div class="ocd-kanban" data-ocd-kanban="customer">
                <p class="ocd-empty"><?php esc_html_e('Loading…', 'oversee-customer-dashboard'); ?></p>
            </div>
        </div>

        <section class="ocd-grid ocd-grid--main">
            <div class="ocd-card">
                <div class="ocd-card__header">
                    <h2><?php esc_html_e('Add-ons & services', 'oversee-customer-dashboard'); ?></h2>
                    <a class="ocd-btn ocd-btn--ghost" data-ocd-store-cart hidden href="#"><?php esc_html_e('View cart', 'oversee-customer-dashboard'); ?></a>
                </div>
                <p class="ocd-muted"><?php esc_html_e('Each item is an existing WooCommerce product. Checkout uses the real WooCommerce cart — no separate billing.', 'oversee-customer-dashboard'); ?></p>
                <div class="ocd-store" data-ocd-list="store">
                    <p class="ocd-empty"><?php esc_html_e('Loading…', 'oversee-customer-dashboard'); ?></p>
                </div>
            </div>
            <div class="ocd-card">
                <div class="ocd-card__header"><h2><?php esc_html_e('Need help?', 'oversee-customer-dashboard'); ?></h2></div>
                <p class="ocd-muted"><?php esc_html_e('Open a support ticket through the Oversee Helpdesk.', 'oversee-customer-dashboard'); ?></p>
                <p>
                    <?php $helpdesk_url = apply_filters('ocd_helpdesk_url', '/support/'); ?>
                    <a class="ocd-btn ocd-btn--primary" href="<?php echo esc_url($helpdesk_url); ?>"><?php esc_html_e('Open Helpdesk', 'oversee-customer-dashboard'); ?></a>
                </p>
                <hr />
                <h3 class="ocd-h3"><?php esc_html_e('Your CRM (optional)', 'oversee-customer-dashboard'); ?></h3>
                <p class="ocd-muted"><?php esc_html_e('Link your own CRM account — separate from Oversee. Tokens are stored encrypted-at-rest server-side.', 'oversee-customer-dashboard'); ?></p>
                <div data-ocd-block="customer-crm" class="ocd-empty"><?php esc_html_e('Loading…', 'oversee-customer-dashboard'); ?></div>
                <form class="ocd-crm-form" data-ocd-form="connect-crm" hidden>
                    <label><?php esc_html_e('Provider', 'oversee-customer-dashboard'); ?>
                        <select name="provider">
                            <option value="highlevel">HighLevel</option>
                            <option value="hubspot">HubSpot</option>
                            <option value="pipedrive">Pipedrive</option>
                            <option value="salesforce">Salesforce</option>
                            <option value="other">Other</option>
                        </select>
                    </label>
                    <label><?php esc_html_e('Label (optional)', 'oversee-customer-dashboard'); ?>
                        <input type="text" name="label" />
                    </label>
                    <label><?php esc_html_e('Location/Account ID', 'oversee-customer-dashboard'); ?>
                        <input type="text" name="location_id" />
                    </label>
                    <label><?php esc_html_e('Access token', 'oversee-customer-dashboard'); ?>
                        <input type="password" name="access_token" required autocomplete="new-password" />
                    </label>
                    <label><?php esc_html_e('Refresh token (optional)', 'oversee-customer-dashboard'); ?>
                        <input type="password" name="refresh_token" autocomplete="new-password" />
                    </label>
                    <div class="ocd-actions">
                        <button type="submit" class="ocd-btn ocd-btn--primary"><?php esc_html_e('Save connection', 'oversee-customer-dashboard'); ?></button>
                    </div>
                </form>
            </div>
        </section>
    </section>

    <section class="ocd-cust-panel" data-ocd-cust-panel="projects">
        <div class="ocd-card">
            <div class="ocd-card__header"><h2><?php esc_html_e('Project timelines', 'oversee-customer-dashboard'); ?></h2></div>
            <div class="ocd-projects" data-ocd-list="projects">
                <p class="ocd-empty"><?php esc_html_e('Loading…', 'oversee-customer-dashboard'); ?></p>
            </div>
        </div>
    </section>

    <section class="ocd-cust-panel" data-ocd-cust-panel="messages">
        <div class="ocd-card ocd-messages">
            <div class="ocd-card__header">
                <h2><?php esc_html_e('Messages with Oversee', 'oversee-customer-dashboard'); ?></h2>
                <span class="ocd-muted" data-ocd-msg-status></span>
            </div>
            <div class="ocd-msg-thread" data-ocd-list="messages">
                <p class="ocd-empty"><?php esc_html_e('Loading…', 'oversee-customer-dashboard'); ?></p>
            </div>
            <form class="ocd-msg-form" data-ocd-form="send-message">
                <textarea maxlength="5000" name="message" placeholder="<?php esc_attr_e('Write a message to Oversee…', 'oversee-customer-dashboard'); ?>"></textarea>
                <div class="ocd-msg-form__row">
                    <label class="ocd-btn ocd-btn--ghost ocd-msg-form__attach">
                        <input type="file" name="attachment" accept="image/png,image/jpeg,image/gif,image/webp" multiple hidden />
                        <span><?php esc_html_e('Attach image', 'oversee-customer-dashboard'); ?></span>
                    </label>
                    <div class="ocd-msg-form__previews" data-ocd-msg-previews></div>
                    <button type="submit" class="ocd-btn ocd-btn--primary"><?php esc_html_e('Send', 'oversee-customer-dashboard'); ?></button>
                </div>
            </form>
        </div>
    </section>

    <section class="ocd-cust-panel" data-ocd-cust-panel="files">
        <div class="ocd-card">
            <div class="ocd-card__header"><h2><?php esc_html_e('Project files', 'oversee-customer-dashboard'); ?></h2></div>
            <p class="ocd-muted"><?php esc_html_e('Files Oversee has shared with you, plus anything you have uploaded. Image previews stream through a permission-checked endpoint — direct file paths are never exposed.', 'oversee-customer-dashboard'); ?></p>
            <div class="ocd-files" data-ocd-list="files">
                <p class="ocd-empty"><?php esc_html_e('Loading…', 'oversee-customer-dashboard'); ?></p>
            </div>
        </div>
    </section>

    <section class="ocd-cust-panel" data-ocd-cust-panel="billing">
        <div class="ocd-card">
            <div class="ocd-card__header"><h2><?php esc_html_e('Subscriptions', 'oversee-customer-dashboard'); ?></h2></div>
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
            <div class="ocd-card__header"><h2><?php esc_html_e('Recent orders', 'oversee-customer-dashboard'); ?></h2></div>
            <ul class="ocd-list" data-ocd-list="orders">
                <li class="ocd-empty"><?php esc_html_e('Loading…', 'oversee-customer-dashboard'); ?></li>
            </ul>
        </div>
    </section>
</div>
