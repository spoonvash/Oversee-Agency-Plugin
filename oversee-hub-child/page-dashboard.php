<?php
/**
 * Template Name: Oversee Dashboard (Full-Width)
 *
 * Bypasses the Hub theme's header/footer/sidebar entirely. Renders only the
 * mount point that the Oversee Dashboard plugin's React SPA hydrates into.
 * The plugin enqueues the SPA assets via OCD_Assets when this template runs.
 *
 * @package Oversee_Hub_Child
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!is_user_logged_in()) {
    wp_safe_redirect(add_query_arg('redirect_to', urlencode(home_url($_SERVER['REQUEST_URI'])), home_url('/login/')));
    exit;
}

nocache_headers();

$dark_mode = get_user_meta(get_current_user_id(), 'oversee_dark_mode', true) === '1';
?>
<!doctype html>
<html <?php language_attributes(); ?> class="<?php echo $dark_mode ? 'oversee-dark' : ''; ?>">
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="<?php echo $dark_mode ? '#09090b' : '#ffffff'; ?>">
<meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html(get_bloginfo('name')); ?> — Dashboard</title>
<?php wp_head(); ?>
</head>
<body <?php body_class('oversee-portal oversee-no-hub-chrome'); ?>>
<div id="oversee-dashboard-root" data-oversee-mount="dashboard"></div>
<noscript>
    <div style="font-family:Inter,sans-serif;padding:24px;text-align:center;">
        The Oversee dashboard requires JavaScript. Please enable it to continue.
    </div>
</noscript>
<?php wp_footer(); ?>
</body>
</html>
