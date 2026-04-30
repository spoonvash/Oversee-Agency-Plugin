<?php
/**
 * Template Name: Oversee Login (Full-Width)
 *
 * Assembly/BrandMages-style split auth layout. Left pane: form. Right pane:
 * marketing reassurance card with the Oversee accent. Auth handlers are
 * unchanged — magic-link is the primary path (delegates to the Magic Login
 * plugin if active), password fallback is the secondary.
 *
 * @package Oversee_Hub_Child
 */

if (!defined('ABSPATH')) {
    exit;
}

if (is_user_logged_in()) {
    $redirect = isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : home_url('/dashboard/');
    wp_safe_redirect($redirect);
    exit;
}

$is_register   = isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/register') === 0;
$magic_active  = class_exists('Magic_Login_Plugin') || function_exists('magic_login_send_link');
$show_password = !$magic_active || isset($_GET['password']);
$redirect_to   = isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : home_url('/dashboard/');
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html($is_register ? 'Create your Oversee account' : 'Sign in to Oversee'); ?></title>
<?php wp_head(); ?>
</head>
<body <?php body_class('oversee-portal oversee-auth-page oversee-auth-split'); ?>>
<main class="oversee-auth-shell" style="display:grid;grid-template-columns:1fr 1fr;min-height:100vh;background:#fff;">

    <section class="oversee-auth-form" style="display:flex;align-items:center;justify-content:center;padding:48px 32px;">
        <div class="oversee-auth-card" style="width:100%;max-width:360px;">
            <a class="oversee-auth-logo" href="<?php echo esc_url(home_url('/')); ?>" style="display:inline-block;margin-bottom:32px;">
                <img src="<?php echo esc_url(OVERSEE_HUB_CHILD_URL . '/assets/img/oversee-logo.png'); ?>" alt="Oversee Agency" style="height:28px;">
            </a>
            <h1 class="oversee-auth-title" style="font-size:28px;font-weight:600;letter-spacing:-0.02em;color:#0a0a0a;margin:0 0 8px;">
                <?php echo $is_register ? esc_html__('Create your account', 'oversee-hub-child') : esc_html__('Welcome back', 'oversee-hub-child'); ?>
            </h1>
            <p class="oversee-auth-sub" style="font-size:14px;color:#71717a;margin:0 0 32px;">
                <?php echo $is_register
                    ? esc_html__('Set up your Oversee dashboard to manage projects, files, and services.', 'oversee-hub-child')
                    : esc_html__('Sign in to access your dashboard, projects, and billing.', 'oversee-hub-child');
                ?>
            </p>

            <?php if ($is_register): ?>
                <?php
                if (function_exists('wc_get_template')) {
                    wc_get_template('myaccount/form-login.php');
                } else {
                    wp_register_form();
                }
                ?>
            <?php elseif ($magic_active && !$show_password): ?>
                <form method="post" action="<?php echo esc_url(home_url('/wp-login.php?action=magic_login')); ?>" class="oversee-magic-form" style="display:flex;flex-direction:column;gap:14px;">
                    <label style="font-size:13px;font-weight:500;color:#0a0a0a;">
                        <?php esc_html_e('Email address', 'oversee-hub-child'); ?>
                        <input type="email" name="email" required autocomplete="email" style="margin-top:4px;width:100%;padding:10px 12px;border:1px solid #e5e5e5;border-radius:8px;font-size:14px;font-family:inherit;">
                    </label>
                    <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>">
                    <?php wp_nonce_field('magic-login', 'magic_login_nonce'); ?>
                    <button type="submit" style="width:100%;padding:12px;background:#ff8201;color:#fff;border:0;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;">
                        <?php esc_html_e('Send magic link', 'oversee-hub-child'); ?>
                    </button>
                </form>
                <p style="margin-top:24px;font-size:13px;color:#71717a;">
                    <a href="?password=1" style="color:#0a0a0a;text-decoration:underline;"><?php esc_html_e('Use password instead', 'oversee-hub-child'); ?></a>
                    · <a href="<?php echo esc_url(home_url('/register/')); ?>" style="color:#0a0a0a;text-decoration:underline;"><?php esc_html_e('Create account', 'oversee-hub-child'); ?></a>
                </p>
            <?php else: ?>
                <?php
                wp_login_form([
                    'redirect'       => $redirect_to,
                    'label_username' => __('Email or username', 'oversee-hub-child'),
                    'label_password' => __('Password', 'oversee-hub-child'),
                    'label_log_in'   => __('Sign in', 'oversee-hub-child'),
                ]);
                ?>
                <p class="oversee-auth-meta" style="margin-top:24px;font-size:13px;color:#71717a;">
                    <?php if ($magic_active): ?>
                        <a href="?" style="color:#0a0a0a;text-decoration:underline;"><?php esc_html_e('Use magic link instead', 'oversee-hub-child'); ?></a>
                        ·
                    <?php endif; ?>
                    <a href="<?php echo esc_url(wp_lostpassword_url()); ?>" style="color:#0a0a0a;text-decoration:underline;"><?php esc_html_e('Forgot your password?', 'oversee-hub-child'); ?></a>
                    · <a href="<?php echo esc_url(home_url('/register/')); ?>" style="color:#0a0a0a;text-decoration:underline;"><?php esc_html_e('Create an account', 'oversee-hub-child'); ?></a>
                </p>
            <?php endif; ?>
        </div>
    </section>

    <aside class="oversee-auth-marketing" style="display:flex;align-items:center;justify-content:center;padding:48px 32px;background:#0a0a0a;color:#fff;">
        <div style="max-width:420px;">
            <div style="display:inline-block;padding:4px 10px;border:1px solid #ff8201;color:#ff8201;border-radius:9999px;font-size:11px;text-transform:uppercase;letter-spacing:.06em;font-weight:500;margin-bottom:24px;">Oversee</div>
            <blockquote style="font-size:24px;line-height:1.4;font-weight:500;margin:0;color:#fff;">
                <?php esc_html_e('"One dashboard, one place to ship work. Our clients log in and see exactly what is in motion."', 'oversee-hub-child'); ?>
            </blockquote>
            <p style="margin-top:32px;font-size:14px;color:#a1a1aa;">— Oversee Agency</p>
        </div>
    </aside>
</main>
<style>
@media (max-width: 720px) { .oversee-auth-shell { grid-template-columns: 1fr !important; } .oversee-auth-marketing { display: none !important; } }
</style>
<?php wp_footer(); ?>
</body>
</html>
