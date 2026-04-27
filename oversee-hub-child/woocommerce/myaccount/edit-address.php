<?php
/**
 * Oversee — My Account / Edit address
 *
 * @see woocommerce/templates/myaccount/edit-address.php
 */

defined('ABSPATH') || exit;

$page_title = ('billing' === $load_address) ? esc_html__('Billing address', 'woocommerce') : esc_html__('Shipping address', 'woocommerce');
?>

<div class="oversee-card" style="padding:24px;border-radius:12px;">
    <p style="font-size:15px;color:#3f3f46;margin:0 0 16px;"><?php echo apply_filters('woocommerce_my_account_edit_address_description', esc_html__('The following addresses will be used on the checkout page by default.', 'woocommerce'), $load_address); ?></p>

    <?php if (!wc_ship_to_billing_address_only() && wc_shipping_enabled()) : ?>
        <h3 style="font-size:18px;font-weight:600;margin:0 0 16px;color:#0a0a0a;"><?php echo $page_title; ?></h3>
    <?php endif; ?>

    <form method="post">
        <?php do_action("woocommerce_before_edit_address_form_{$load_address}"); ?>

        <div class="woocommerce-address-fields__field-wrapper" style="display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
            <?php
            foreach ($address as $key => $field) {
                woocommerce_form_field($key, $field, wc_get_post_data_by_key($key, $field['value']));
            }
            ?>
        </div>

        <?php do_action("woocommerce_after_edit_address_form_{$load_address}"); ?>

        <p style="margin-top:24px;">
            <button type="submit" class="woocommerce-Button button" name="save_address" value="<?php esc_attr_e('Save address', 'woocommerce'); ?>" style="padding:10px 18px;background:#ff8201;color:#fff;border:0;border-radius:8px;font-weight:500;cursor:pointer;"><?php esc_html_e('Save address', 'woocommerce'); ?></button>
            <?php wp_nonce_field('woocommerce-edit_address', 'woocommerce-edit-address-nonce'); ?>
            <input type="hidden" name="action" value="edit_address" />
        </p>
    </form>
</div>
