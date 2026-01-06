<?php
/**
 * Admin Login Page
 */

if (!defined('ABSPATH')) {
    exit;
}

if (is_user_logged_in() && current_user_can('oversee_view_dashboard')) {
    wp_safe_redirect(oversee_admin_url());
    exit;
}

global $oversee_vars;
$error = isset($oversee_vars['login_error']) ? $oversee_vars['login_error'] : '';
$redirect = isset($_GET['redirect']) ? esc_url($_GET['redirect']) : '';
$logged_out = isset($_GET['logged_out']);
$expired = isset($_GET['expired']);
$portal_title = oversee_get_portal_title();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo esc_html($portal_title); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo esc_url(OVERSEE_PLUGIN_URL . 'assets/css/admin.css?v=2.0.0'); ?>">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo"><i class="fa-solid fa-headset"></i></div>
                <h1><?php echo esc_html($portal_title); ?></h1>
                <p>Sign in to your support dashboard</p>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?php echo esc_html($error); ?></div>
            <?php endif; ?>
            
            <?php if ($logged_out): ?>
            <div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> You have been logged out successfully.</div>
            <?php endif; ?>
            
            <?php if ($expired): ?>
            <div class="alert alert-warning"><i class="fa-solid fa-clock"></i> Your session has expired. Please log in again.</div>
            <?php endif; ?>
            
            <form method="POST" action="" class="login-form">
                <?php wp_nonce_field('oversee_login', 'oversee_login_nonce'); ?>
                <input type="hidden" name="redirect" value="<?php echo esc_attr($redirect); ?>">
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="you@example.com" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
                
                <div class="form-group" style="display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" name="remember" value="1" id="remember" style="width:16px;height:16px;">
                    <label for="remember" style="margin:0;font-weight:400;cursor:pointer;">Remember me</label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-login"><i class="fa-solid fa-right-to-bracket"></i> Sign In</button>
            </form>
            
            <div class="login-footer">
                <a href="<?php echo esc_url(oversee_kb_url()); ?>"><i class="fa-solid fa-arrow-left"></i> Back to Help Center</a>
            </div>
        </div>
    </div>
</body>
</html>
