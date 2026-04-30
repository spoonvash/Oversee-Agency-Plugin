<?php
/**
 * Oversee — My Account / Orders
 *
 * Mirrors WooCommerce's default orders template with Assembly-style
 * presentation: 1px borders, neutral surfaces, accent only on the action
 * buttons. Behaviour stays identical — pagination, hooks, and filters all
 * fire as in the parent template.
 *
 * @see woocommerce/templates/myaccount/orders.php
 */

defined('ABSPATH') || exit;

do_action('woocommerce_before_account_orders', $has_orders);
?>

<div class="oversee-card" style="padding:0;border-radius:12px;overflow:hidden;">
<?php if ($has_orders) : ?>

    <table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table" style="width:100%;border-collapse:collapse;">
        <thead style="background:#fafafa;">
            <tr>
                <?php foreach (wc_get_account_orders_columns() as $column_id => $column_name) : ?>
                    <th class="<?php echo esc_attr($column_id); ?>" style="text-align:left;padding:12px 16px;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#52525b;border-bottom:1px solid #e5e5e5;">
                        <span class="nobr"><?php echo esc_html($column_name); ?></span>
                    </th>
                <?php endforeach; ?>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($customer_orders->orders as $customer_order) :
                $order = wc_get_order($customer_order);
                if (!$order) continue;
                $item_count = $order->get_item_count() - $order->get_item_count_refunded();
                ?>
                <tr class="woocommerce-orders-table__row order">
                    <?php foreach (wc_get_account_orders_columns() as $column_id => $column_name) : ?>
                        <td class="<?php echo esc_attr($column_id); ?>" data-title="<?php echo esc_attr($column_name); ?>" style="padding:12px 16px;border-bottom:1px solid #f4f4f5;font-size:14px;color:#0a0a0a;">
                            <?php if (has_action('woocommerce_my_account_my_orders_column_' . $column_id)) : ?>
                                <?php do_action('woocommerce_my_account_my_orders_column_' . $column_id, $order); ?>
                            <?php elseif ('order-number' === $column_id) : ?>
                                <a href="<?php echo esc_url($order->get_view_order_url()); ?>" style="color:#0a0a0a;font-weight:500;text-decoration:none;">
                                    <?php echo esc_html(_x('#', 'hash before order number', 'woocommerce') . $order->get_order_number()); ?>
                                </a>
                            <?php elseif ('order-date' === $column_id) : ?>
                                <time datetime="<?php echo esc_attr($order->get_date_created()->date('c')); ?>"><?php echo esc_html(wc_format_datetime($order->get_date_created())); ?></time>
                            <?php elseif ('order-status' === $column_id) : ?>
                                <span style="display:inline-block;padding:2px 10px;border-radius:9999px;background:#f4f4f5;color:#3f3f46;font-size:12px;font-weight:500;">
                                    <?php echo esc_html(wc_get_order_status_name($order->get_status())); ?>
                                </span>
                            <?php elseif ('order-total' === $column_id) : ?>
                                <?php echo wp_kses_post(sprintf(_n('%1$s for %2$s item', '%1$s for %2$s items', $item_count, 'woocommerce'), $order->get_formatted_order_total(), $item_count)); ?>
                            <?php elseif ('order-actions' === $column_id) : ?>
                                <?php $actions = wc_get_account_orders_actions($order); ?>
                                <?php foreach ($actions as $key => $action) : ?>
                                    <a href="<?php echo esc_url($action['url']); ?>" class="woocommerce-button button <?php echo sanitize_html_class($key); ?>" style="display:inline-block;padding:6px 12px;border-radius:8px;background:#ff8201;color:#fff;text-decoration:none;font-size:13px;font-weight:500;margin-right:6px;"><?php echo esc_html($action['name']); ?></a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

<?php else : ?>
    <div style="padding:48px 24px;text-align:center;">
        <p style="font-size:15px;color:#71717a;margin:0;"><?php esc_html_e('No orders yet.', 'oversee-hub-child'); ?></p>
        <p style="margin-top:12px;">
            <a class="woocommerce-Button button" href="<?php echo esc_url(apply_filters('woocommerce_return_to_shop_redirect', wc_get_page_permalink('shop'))); ?>" style="display:inline-block;padding:10px 18px;background:#ff8201;color:#fff;border-radius:8px;text-decoration:none;font-weight:500;"><?php esc_html_e('Browse services', 'oversee-hub-child'); ?></a>
        </p>
    </div>
<?php endif; ?>
</div>

<?php if (1 < $customer_orders->max_num_pages) : ?>
    <div class="woocommerce-pagination" style="margin-top:16px;display:flex;gap:8px;">
        <?php if (1 !== $current_page) : ?>
            <a class="woocommerce-button button" href="<?php echo esc_url(wc_get_endpoint_url('orders', $current_page - 1)); ?>" style="padding:8px 14px;border:1px solid #e5e5e5;border-radius:8px;text-decoration:none;color:#0a0a0a;"><?php esc_html_e('Previous', 'woocommerce'); ?></a>
        <?php endif; ?>
        <?php if (intval($customer_orders->max_num_pages) !== $current_page) : ?>
            <a class="woocommerce-button button" href="<?php echo esc_url(wc_get_endpoint_url('orders', $current_page + 1)); ?>" style="padding:8px 14px;border:1px solid #e5e5e5;border-radius:8px;text-decoration:none;color:#0a0a0a;"><?php esc_html_e('Next', 'woocommerce'); ?></a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php do_action('woocommerce_after_account_orders', $has_orders); ?>
