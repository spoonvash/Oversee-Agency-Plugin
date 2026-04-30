<?php
/**
 * Oversee passthrough override of woocommerce/checkout/form-checkout.php.
 *
 * We keep the WooCommerce + WooCommerce Stripe checkout flow exactly as-is —
 * payment, validation, and order placement must remain WooCommerce-native.
 * This file only exists to wrap the default markup in an Oversee-branded
 * container so the surrounding spacing/typography matches the dashboard.
 *
 * To restore default behavior simply delete this file.
 *
 * @package Oversee_Hub_Child
 */

if (!defined('ABSPATH')) {
    exit;
}

defined('ABSPATH') || exit;

do_action('woocommerce_before_checkout_form', WC()->checkout());

if (!is_user_logged_in() && WC()->checkout()->is_registration_required()) {
    echo '<div class="oversee-checkout-login-notice" style="margin-bottom:16px;">';
    esc_html_e('You must be logged in to check out.', 'oversee-hub-child');
    echo '</div>';
    return;
}

?>
<div class="oversee-checkout-shell" style="background:var(--oversee-surface);border:1px solid var(--oversee-border);border-radius:var(--oversee-radius);padding:24px;">
<form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url(wc_get_checkout_url()); ?>" enctype="multipart/form-data" aria-label="<?php esc_attr_e('Checkout', 'woocommerce'); ?>">

    <?php if ($checkout->get_checkout_fields()) : ?>

        <?php do_action('woocommerce_checkout_before_customer_details'); ?>

        <div class="col2-set" id="customer_details">
            <div class="col-1">
                <?php do_action('woocommerce_checkout_billing'); ?>
            </div>
            <div class="col-2">
                <?php do_action('woocommerce_checkout_shipping'); ?>
            </div>
        </div>

        <?php do_action('woocommerce_checkout_after_customer_details'); ?>

    <?php endif; ?>

    <?php do_action('woocommerce_checkout_before_order_review_heading'); ?>

    <h3 id="order_review_heading"><?php esc_html_e('Your order', 'woocommerce'); ?></h3>

    <?php do_action('woocommerce_checkout_before_order_review'); ?>

    <div id="order_review" class="woocommerce-checkout-review-order">
        <?php do_action('woocommerce_checkout_order_review'); ?>
    </div>

    <?php do_action('woocommerce_checkout_after_order_review'); ?>

</form>
</div>
<?php do_action('woocommerce_after_checkout_form', WC()->checkout()); ?>
