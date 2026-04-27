<?php
/**
 * Oversee passthrough override of woocommerce/cart/cart.php.
 *
 * Wraps the default cart markup in an Oversee-branded container. The cart
 * line-item logic, totals, coupons, and proceed-to-checkout action all stay
 * WooCommerce-native. To restore default behavior, delete this file.
 *
 * @package Oversee_Hub_Child
 */

if (!defined('ABSPATH')) {
    exit;
}

defined('ABSPATH') || exit;

do_action('woocommerce_before_cart');
?>
<div class="oversee-cart-shell" style="background:var(--oversee-surface);border:1px solid var(--oversee-border);border-radius:var(--oversee-radius);padding:24px;">
<form class="woocommerce-cart-form" action="<?php echo esc_url(wc_get_cart_url()); ?>" method="post">
    <?php do_action('woocommerce_before_cart_table'); ?>

    <table class="shop_table shop_table_responsive cart woocommerce-cart-form__contents" cellspacing="0" aria-label="<?php esc_attr_e('Your cart', 'woocommerce'); ?>">
        <thead>
            <tr>
                <th class="product-remove"><span class="screen-reader-text"><?php esc_html_e('Remove item', 'woocommerce'); ?></span></th>
                <th class="product-thumbnail"><span class="screen-reader-text"><?php esc_html_e('Thumbnail image', 'woocommerce'); ?></span></th>
                <th class="product-name"><?php esc_html_e('Product', 'woocommerce'); ?></th>
                <th class="product-price"><?php esc_html_e('Price', 'woocommerce'); ?></th>
                <th class="product-quantity"><?php esc_html_e('Quantity', 'woocommerce'); ?></th>
                <th class="product-subtotal"><?php esc_html_e('Subtotal', 'woocommerce'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php do_action('woocommerce_before_cart_contents'); ?>

            <?php
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
                if ($_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters('woocommerce_cart_item_visible', true, $cart_item, $cart_item_key)) {
                    wc_get_template('cart/cart-item.php', [
                        'cart_item_key' => $cart_item_key,
                        'cart_item'     => $cart_item,
                        'product'       => $_product,
                    ]);
                }
            }
            ?>

            <?php do_action('woocommerce_cart_contents'); ?>

            <tr>
                <td colspan="6" class="actions">
                    <?php if (wc_coupons_enabled()) : ?>
                        <div class="coupon">
                            <label for="coupon_code" class="screen-reader-text"><?php esc_html_e('Coupon:', 'woocommerce'); ?></label>
                            <input type="text" name="coupon_code" class="input-text" id="coupon_code" value="" placeholder="<?php esc_attr_e('Coupon code', 'woocommerce'); ?>" />
                            <button type="submit" class="button" name="apply_coupon" value="<?php esc_attr_e('Apply coupon', 'woocommerce'); ?>"><?php esc_html_e('Apply coupon', 'woocommerce'); ?></button>
                            <?php do_action('woocommerce_cart_coupon'); ?>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="button" name="update_cart" value="<?php esc_attr_e('Update cart', 'woocommerce'); ?>"><?php esc_html_e('Update cart', 'woocommerce'); ?></button>

                    <?php do_action('woocommerce_cart_actions'); ?>
                    <?php wp_nonce_field('woocommerce-cart', 'woocommerce-cart-nonce'); ?>
                </td>
            </tr>

            <?php do_action('woocommerce_after_cart_contents'); ?>
        </tbody>
    </table>
    <?php do_action('woocommerce_after_cart_table'); ?>
</form>

<?php do_action('woocommerce_before_cart_collaterals'); ?>

<div class="cart-collaterals">
    <?php do_action('woocommerce_cart_collaterals'); ?>
</div>
</div>
<?php do_action('woocommerce_after_cart'); ?>
