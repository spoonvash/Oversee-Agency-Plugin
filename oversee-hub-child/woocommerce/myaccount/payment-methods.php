<?php
/**
 * Oversee — My Account / Payment methods
 *
 * Same logic as the WooCommerce default; restyled to Assembly cards. The
 * "Add payment method" CTA inherits the accent token so brand changes
 * propagate without per-template overrides.
 *
 * @see woocommerce/templates/myaccount/payment-methods.php
 */

defined('ABSPATH') || exit;

$saved_methods = wc_get_customer_saved_methods_list(get_current_user_id());
$has_methods   = (bool) apply_filters('woocommerce_account_payment_methods_has_methods', count($saved_methods));
$types         = wc_get_account_payment_methods_types();
?>

<div class="oversee-card" style="padding:0;border-radius:12px;overflow:hidden;">
<?php if ($has_methods) : ?>
    <table class="account-payment-methods-table shop_table" style="width:100%;border-collapse:collapse;">
        <thead style="background:#fafafa;">
            <tr>
                <?php foreach (wc_get_account_payment_methods_columns() as $column_id => $column_name) : ?>
                    <th class="payment-method-<?php echo esc_attr($column_id); ?>" style="text-align:left;padding:12px 16px;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#52525b;border-bottom:1px solid #e5e5e5;">
                        <?php echo esc_html($column_name); ?>
                    </th>
                <?php endforeach; ?>
            </tr>
        </thead>

        <?php foreach ($saved_methods as $type => $methods) : ?>
            <tbody class="payment-method-<?php echo esc_attr($type); ?>">
                <?php foreach ($methods as $method) : ?>
                    <tr class="payment-method<?php echo !empty($method['is_default']) ? ' default-payment-method' : ''; ?>">
                        <?php foreach (wc_get_account_payment_methods_columns() as $column_id => $column_name) : ?>
                            <td class="payment-method-<?php echo esc_attr($column_id); ?>" data-title="<?php echo esc_attr($column_name); ?>" style="padding:12px 16px;border-bottom:1px solid #f4f4f5;font-size:14px;color:#0a0a0a;">
                                <?php
                                if (has_action('woocommerce_account_payment_methods_column_' . $column_id)) {
                                    do_action('woocommerce_account_payment_methods_column_' . $column_id, $method);
                                } elseif ('method' === $column_id) {
                                    if (!empty($method['method']['last4'])) {
                                        echo esc_html(sprintf(__('%1$s ending in %2$s', 'woocommerce'), esc_html(wc_get_credit_card_type_label($method['method']['brand'])), esc_html($method['method']['last4'])));
                                    } else {
                                        echo esc_html(wc_get_credit_card_type_label($method['method']['brand']));
                                    }
                                } elseif ('expires' === $column_id) {
                                    echo esc_html($method['expires']);
                                } elseif ('actions' === $column_id) {
                                    foreach ($method['actions'] as $key => $action) {
                                        echo '<a href="' . esc_url($action['url']) . '" class="button ' . sanitize_html_class($key) . '" style="display:inline-block;padding:6px 12px;border-radius:8px;background:#ff8201;color:#fff;text-decoration:none;font-size:13px;font-weight:500;margin-right:6px;">' . esc_html($action['name']) . '</a>';
                                    }
                                }
                                ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        <?php endforeach; ?>
    </table>
<?php else : ?>
    <div style="padding:48px 24px;text-align:center;">
        <p style="font-size:15px;color:#71717a;margin:0;"><?php esc_html_e('No saved payment methods.', 'oversee-hub-child'); ?></p>
    </div>
<?php endif; ?>
</div>

<p style="margin-top:16px;">
    <a class="woocommerce-Button button" href="<?php echo esc_url(wc_get_endpoint_url('add-payment-method')); ?>" style="display:inline-block;padding:10px 18px;background:#ff8201;color:#fff;border-radius:8px;text-decoration:none;font-weight:500;"><?php esc_html_e('Add payment method', 'woocommerce'); ?></a>
</p>
