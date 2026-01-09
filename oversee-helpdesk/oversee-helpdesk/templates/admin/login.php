<?php
/**
 * Admin Login Page - White-Label Branded
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

// Get branding
$branding = Oversee_Branding::get_for_context('login');
$portal_title = $branding['company_name'] ?: oversee_get_portal_title();
$bg_css = Oversee_Branding::get_login_background_css();
$favicon = Oversee_Branding::get('favicon_url');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo esc_html($portal_title); ?></title>
    <?php if ($favicon): ?>
    <link rel="icon" href="<?php echo esc_url($favicon); ?>" type="image/x-icon">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; min-height: 100vh; display: flex; }
        
        .login-split { display: flex; min-height: 100vh; width: 100%; }
        
        /* Branded Sidebar */
        .login-sidebar {
            flex: 0 0 45%;
            <?php echo $bg_css; ?>
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 60px;
            color: #fff;
            text-align: center;
            position: relative;
        }
        .login-sidebar::before {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.2);
            pointer-events: none;
        }
        .login-sidebar-content {
            position: relative;
            z-index: 1;
            max-width: 400px;
        }
        .sidebar-logo { margin-bottom: 40px; }
        .sidebar-logo img { max-width: 180px; height: auto; filter: brightness(0) invert(1); }
        .sidebar-logo .logo-text { font-size: 32px; font-weight: 700; }
        .sidebar-welcome h1 { font-size: 36px; font-weight: 700; margin-bottom: 16px; line-height: 1.2; }
        .sidebar-welcome p { font-size: 17px; opacity: 0.85; line-height: 1.6; }
        .sidebar-features { margin-top: 50px; text-align: left; }
        .sidebar-feature { display: flex; align-items: center; gap: 14px; margin-bottom: 16px; font-size: 15px; opacity: 0.9; }
        .sidebar-feature i { width: 24px; color: <?php echo esc_attr($branding['primary_color']); ?>; }
        
        /* Form Side */
        .login-form-side {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 60px;
            background: #fff;
        }
        .login-form-container { width: 100%; max-width: 400px; }
        .form-logo { margin-bottom: 40px; text-align: center; }
        .form-logo img { max-width: 150px; height: auto; }
        .form-logo .logo-text { font-size: 24px; font-weight: 700; color: #1e293b; }
        .form-header { margin-bottom: 32px; }
        .form-header h2 { font-size: 28px; font-weight: 700; color: #1e293b; margin-bottom: 8px; }
        .form-header p { color: #64748b; font-size: 15px; }
        
        /* Alerts */
        .alert { padding: 14px 16px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-size: 14px; }
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .alert-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-warning { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; }
        
        /* Form */
        .login-form .form-group { margin-bottom: 20px; }
        .login-form label { display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px; }
        .login-form input[type="email"],
        .login-form input[type="password"] {
            width: 100%;
            padding: 12px 14px;
            font-size: 15px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .login-form input:focus {
            outline: none;
            border-color: <?php echo esc_attr($branding['primary_color']); ?>;
            box-shadow: 0 0 0 3px <?php echo esc_attr($branding['primary_color']); ?>20;
        }
        .form-group-checkbox { display: flex; align-items: center; gap: 8px; }
        .form-group-checkbox input { width: 16px; height: 16px; accent-color: <?php echo esc_attr($branding['primary_color']); ?>; }
        .form-group-checkbox label { margin-bottom: 0; font-weight: 400; color: #64748b; cursor: pointer; }
        
        .btn-login {
            width: 100%;
            padding: 14px;
            font-size: 15px;
            font-weight: 600;
            color: #fff;
            background: <?php echo esc_attr($branding['primary_color']); ?>;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background 0.15s, transform 0.15s;
        }
        .btn-login:hover { background: <?php echo esc_attr(Oversee_Branding::darken_color($branding['primary_color'], 10)); ?>; transform: translateY(-1px); }
        
        .login-footer { margin-top: 24px; text-align: center; }
        .login-footer a { color: #64748b; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .login-footer a:hover { color: <?php echo esc_attr($branding['primary_color']); ?>; }
        
        /* Responsive */
        @media (max-width: 900px) {
            .login-sidebar { display: none; }
            .login-form-side { padding: 40px 24px; }
        }
    </style>
</head>
<body>
    <div class="login-split">
        <!-- Branded Sidebar -->
        <div class="login-sidebar">
            <div class="login-sidebar-content">
                <?php if ($branding['show_logo']): ?>
                <div class="sidebar-logo">
                    <?php echo Oversee_Branding::get_logo_html('sidebar-logo-img', 'admin'); ?>
                </div>
                <?php endif; ?>
                
                <div class="sidebar-welcome">
                    <h1><?php echo esc_html($branding['welcome_title']); ?></h1>
                    <p><?php echo esc_html($branding['welcome_message']); ?></p>
                </div>
                
                <div class="sidebar-features">
                    <div class="sidebar-feature">
                        <i class="fa-solid fa-ticket"></i>
                        <span>Manage support tickets</span>
                    </div>
                    <div class="sidebar-feature">
                        <i class="fa-solid fa-book-open"></i>
                        <span>Knowledge base administration</span>
                    </div>
                    <div class="sidebar-feature">
                        <i class="fa-solid fa-chart-line"></i>
                        <span>Analytics and reporting</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Form Side -->
        <div class="login-form-side">
            <div class="login-form-container">
                <div class="form-logo">
                    <?php echo Oversee_Branding::get_logo_html('form-logo-img', 'public'); ?>
                </div>
                
                <div class="form-header">
                    <h2>Sign in</h2>
                    <p>Enter your credentials to access the dashboard</p>
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
                        <input type="email" id="email" name="email" placeholder="you@example.com" required autofocus>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="••••••••" required>
                    </div>
                    
                    <div class="form-group form-group-checkbox">
                        <input type="checkbox" name="remember" value="1" id="remember">
                        <label for="remember">Remember me for 30 days</label>
                    </div>
                    
                    <button type="submit" class="btn-login"><i class="fa-solid fa-right-to-bracket"></i> Sign In</button>
                </form>
                
                <div class="login-footer">
                    <a href="<?php echo esc_url(oversee_kb_url()); ?>"><i class="fa-solid fa-arrow-left"></i> Back to Help Center</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
