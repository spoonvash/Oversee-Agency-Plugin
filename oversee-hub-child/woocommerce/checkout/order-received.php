<?php
/**
 * Oversee — Order received
 *
 * Confirmation page after checkout. Same hooks as default; restyled.
 *
 * @see woocommerce/templates/checkout/thankyou.php
 */

defined('ABSPATH') || exit;
?>

<div class="oversee-card" style="padding:32px;border-radius:12px;text-align:center;">
    <?php if ($order) : ?>
        <?php if ($order->has_status('failed')) : ?>
            <p style="font-size:16px;color:#b91c1c;"><?php esc_html_e('Unfortunately your order cannot be processed as the originating bank/merchant has declined your transaction. Please attempt your purchase again.', 'woocommerce'); ?></p>
            <p style="margin-top:24px;">
                <a href="<?php echo esc_url($order->get_checkout_payment_url()); ?>" class="button pay" style="display:inline-block;padding:10px 18px;background:#ff8201;color:#fff;border-radius:8px;text-decoration:none;font-weight:500;"><?php esc_html_e('Pay', 'woocommerce'); ?></a>
                <?php if (is_user_logged_in()) : ?>
                    <a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>" class="button" style="display:inline-block;padding:10px 18px;background:#fff;color:#0a0a0a;border:1px solid #e5e5e5;border-radius:8px;text-decoration:none;font-weight:500;margin-left:8px;"><?php esc_html_e('My account', 'woocommerce'); ?></a>
                <?php endif; ?>
            </p>
        <?php else : ?>
            <div style="display:inline-flex;align-items:center;justify-content:center;width:48px;height:48px;border-radius:9999px;background:#f0fdf4;color:#16a34a;margin:0 auto 16px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
            </div>
            <h1 style="font-size:24px;font-weight:600;margin:0;color:#0a0a0a;"><?php esc_html_e('Thank you. Your order has been received.', 'woocommerce'); ?></h1>
            <p style="font-size:14px;color:#71717a;margin-top:8px;"><?php esc_html_e('A confirmation email is on the way. Your project workspace will appear in your dashboard shortly.', 'oversee-hub-child'); ?></p>

            <ul class="woocommerce-order-overview" style="display:flex;justify-content:center;gap:24px;flex-wrap:wrap;list-style:none;padding:0;margin:24px 0;">
                <li><strong><?php esc_html_e('Order #:', 'woocommerce'); ?></strong> <?php echo esc_html($order->get_order_number()); ?></li>
                <li><strong><?php esc_html_e('Date:', 'woocommerce'); ?></strong> <?php echo esc_html(wc_format_datetime($order->get_date_created())); ?></li>
                <li><strong><?php esc_html_e('Total:', 'woocommerce'); ?></strong> <?php echo wp_kses_post($order->get_formatted_order_total()); ?></li>
            </ul>

            <p>
                <a href="<?php echo esc_url(home_url('/dashboard/')); ?>" style="display:inline-block;padding:12px 22px;background:#ff8201;color:#fff;border-radius:8px;text-decoration:none;font-weight:500;"><?php esc_html_e('Open dashboard', 'oversee-hub-child'); ?></a>
            </p>
        <?php endif; ?>

        <?php
        do_action('woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id());
        do_action('woocommerce_thankyou', $order->get_id());
        ?>
    <?php else : ?>
        <p><?php esc_html_e('Thank you. Your order has been received.', 'woocommerce'); ?></p>
    <?php endif; ?>
</div>
