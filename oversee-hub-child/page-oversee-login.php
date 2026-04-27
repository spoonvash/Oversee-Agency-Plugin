<?php
/**
 * Template Name: Oversee Login (Full-Width)
 *
 * Hosts the branded login / register forms. Uses the same orange-on-white
 * (orange-on-black in dark mode) brand language as the dashboard, but renders
 * a single-column auth layout. The actual auth handlers stay native WordPress —
 * we only restyle the form output.
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

$is_register = isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/register') === 0;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html($is_register ? 'Create your Oversee account' : 'Sign in to Oversee'); ?></title>
<?php wp_head(); ?>
</head>
<body <?php body_class('oversee-portal oversee-auth-page'); ?>>
<main class="oversee-auth-shell">
    <div class="oversee-auth-card">
        <a class="oversee-auth-logo" href="<?php echo esc_url(home_url('/')); ?>">
            <img src="<?php echo esc_url(OVERSEE_HUB_CHILD_URL . '/assets/img/oversee-logo.png'); ?>" alt="Oversee Agency">
        </a>
        <h1 class="oversee-auth-title">
            <?php echo $is_register ? esc_html__('Create your account', 'oversee-hub-child') : esc_html__('Welcome back', 'oversee-hub-child'); ?>
        </h1>
        <p class="oversee-auth-sub">
            <?php echo $is_register
                ? esc_html__('Set up your Oversee dashboard to manage projects, files, and your services.', 'oversee-hub-child')
                : esc_html__('Sign in to access your dashboard, projects, messages, and billing.', 'oversee-hub-child');
            ?>
        </p>

        <?php if ($is_register): ?>
            <?php
            // Defer to the WooCommerce account form so the same fields, validation,
            // and `woocommerce_created_customer` hook fire — that hook is what the
            // Oversee plugin uses to promote the new user to oversee_client.
            if (function_exists('wc_get_template')) {
                wc_get_template('myaccount/form-login.php');
            } else {
                wp_register_form();
            }
            ?>
        <?php else: ?>
            <?php
            wp_login_form([
                'redirect'       => isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : home_url('/dashboard/'),
                'label_username' => __('Email or username', 'oversee-hub-child'),
                'label_password' => __('Password', 'oversee-hub-child'),
                'label_log_in'   => __('Sign in', 'oversee-hub-child'),
            ]);
            ?>
            <p class="oversee-auth-meta">
                <a href="<?php echo esc_url(wp_lostpassword_url()); ?>"><?php esc_html_e('Forgot your password?', 'oversee-hub-child'); ?></a>
                · <a href="<?php echo esc_url(home_url('/register/')); ?>"><?php esc_html_e('Create an account', 'oversee-hub-child'); ?></a>
            </p>
        <?php endif; ?>
    </div>
</main>
<?php wp_footer(); ?>
</body>
</html>
