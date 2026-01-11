<?php
/**
 * Template Loader
 * 
 * Central template rendering system that ensures clean, single-pass output.
 * All public templates are rendered through this loader to prevent duplicate
 * headers, footers, or any HTML structure issues.
 * 
 * @package Oversee_Helpdesk
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Oversee_Template_Loader {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Whether we're currently rendering a template
     */
    private static $rendering = false;
    
    /**
     * Current template data
     */
    private static $template_data = [];
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor
     */
    private function __construct() {}
    
    /**
     * Check license before rendering public templates
     * Redirects to configured URL if unlicensed (unless demo mode or license page)
     * 
     * @param string $template Template name
     * @return bool True if allowed, false if redirected/blocked
     */
    private static function check_license_for_template($template) {
        // Skip license check for admin templates (they have their own auth)
        if (strpos($template, 'admin/') === 0) {
            return true;
        }
        
        // Check if license is valid
        if (Oversee_License::is_licensed()) {
            return true;
        }
        
        // Get license status for detailed info
        $status = Oversee_License::get_status();
        
        // If in grace period, allow access
        if (!empty($status['in_grace_period']) || $status['status'] === 'grace_period') {
            return true;
        }
        
        // Check demo mode - allows KB templates without license
        if (Oversee_License::is_demo_allowed_template($template)) {
            return true;
        }
        
        // License is invalid - check if we should show internal page or redirect
        if (Oversee_License::show_license_page()) {
            // Show internal "License Required" page
            self::render_license_required_page($template, $status);
            return false;
        }
        
        // Redirect to configured URL
        $redirect_url = Oversee_License::get_unlicensed_redirect_url();
        
        // Log the redirect for debugging
        if (class_exists('Oversee_Logger')) {
            Oversee_Logger::info('License enforcement redirect', [
                'template' => $template,
                'status' => $status['status'] ?? 'unknown',
                'redirect_url' => $redirect_url,
            ]);
        }
        
        // Perform redirect
        wp_redirect($redirect_url, 302);
        exit;
    }
    
    /**
     * Render the "License Required" page
     */
    private static function render_license_required_page($template, $status) {
        $branding = self::get_branding();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License Required - <?php echo esc_html($branding['company_name']); ?></title>
    <?php if ($branding['favicon_url']): ?>
    <link rel="icon" href="<?php echo esc_url($branding['favicon_url']); ?>" type="image/x-icon">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; 
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center;
            background: linear-gradient(135deg, #1e3a5f 0%, #0f172a 100%);
            padding: 20px;
        }
        .license-card {
            background: #fff;
            border-radius: 16px;
            padding: 48px;
            max-width: 480px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
        }
        .icon {
            width: 80px;
            height: 80px;
            background: #fef3c7;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        .icon i { font-size: 36px; color: #d97706; }
        h1 { font-size: 24px; color: #1e293b; margin-bottom: 12px; }
        p { color: #64748b; font-size: 15px; line-height: 1.6; margin-bottom: 24px; }
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            background: #fee2e2;
            color: #dc2626;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 24px;
        }
        .btn {
            display: inline-block;
            padding: 14px 28px;
            background: <?php echo esc_attr($branding['primary_color']); ?>;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .contact { margin-top: 32px; padding-top: 24px; border-top: 1px solid #e2e8f0; }
        .contact p { margin-bottom: 8px; font-size: 14px; }
        .contact a { color: <?php echo esc_attr($branding['primary_color']); ?>; text-decoration: none; }
    </style>
</head>
<body>
    <div class="license-card">
        <div class="icon"><i class="fa-solid fa-key"></i></div>
        <h1>License Required</h1>
        <p>This support portal requires a valid license to operate. Please contact your administrator to activate a license.</p>
        
        <?php 
        $status_label = 'Unlicensed';
        if ($status['status'] === 'expired') $status_label = 'License Expired';
        elseif ($status['status'] === 'cancelled') $status_label = 'Subscription Cancelled';
        elseif ($status['status'] === 'site_mismatch') $status_label = 'Site Mismatch';
        ?>
        <span class="status-badge"><i class="fa-solid fa-circle-xmark"></i> <?php echo esc_html($status_label); ?></span>
        
        <p>
            <a href="<?php echo esc_url(Oversee_License::get_unlicensed_redirect_url()); ?>" class="btn">
                <i class="fa-solid fa-external-link-alt"></i> Get a License
            </a>
        </p>
        
        <?php if (!empty($branding['support_email'])): ?>
        <div class="contact">
            <p>Need help?</p>
            <p><a href="mailto:<?php echo esc_attr($branding['support_email']); ?>"><?php echo esc_html($branding['support_email']); ?></a></p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
        <?php
        exit;
    }
    
    /**
     * Render a public template with full layout wrapper
     * 
     * @param string $template Template name (without .php extension)
     * @param array $data Data to pass to template
     * @param string $page_title Page title
     */
    public static function render($template, $data = [], $page_title = '') {
        // HARD LOCK: Check license before rendering any public page
        if (!self::check_license_for_template($template)) {
            return; // Redirect already happened
        }
        
        // Prevent nested rendering
        if (self::$rendering) {
            return;
        }
        
        self::$rendering = true;
        self::$template_data = $data;
        
        // Resolve template path
        $template_file = self::resolve_template_path($template);
        
        if (!$template_file) {
            self::$rendering = false;
            wp_die(
                sprintf('Template not found: %s', esc_html($template)),
                'Template Error',
                ['response' => 404]
            );
            return;
        }
        
        // Determine if this is a public or admin template
        $is_admin_template = strpos($template, 'admin/') === 0;
        
        // Set clean headers
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: no-cache, no-store, must-revalidate');
        }
        
        // Capture template content
        ob_start();
        extract($data, EXTR_SKIP);
        include $template_file;
        $content = ob_get_clean();
        
        // Render the full page
        if ($is_admin_template) {
            self::render_admin_layout($content, $page_title, $data);
        } else {
            self::render_public_layout($content, $page_title, $data);
        }
        
        self::$rendering = false;
        self::$template_data = [];
    }
    
    /**
     * Render public layout wrapper
     */
    private static function render_public_layout($content, $page_title, $data) {
        // Get branding settings
        $branding = self::get_branding();
        
        // Set default page title if not provided
        if (empty($page_title)) {
            $page_title = $branding['company_name'] . ' - Support';
        }
        
        // Determine active nav item
        $current_path = $_SERVER['REQUEST_URI'] ?? '';
        $nav_state = self::get_nav_state($current_path);
        
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($page_title); ?></title>
    <?php if ($branding['favicon_url']): ?>
    <link rel="icon" href="<?php echo esc_url($branding['favicon_url']); ?>" type="image/x-icon">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="<?php echo esc_url(OVERSEE_PLUGIN_URL . 'assets/css/public.css?v=' . OVERSEE_VERSION); ?>">
    <?php
    // Get computed values
    $primary = $branding['primary_color'];
    $primary_hover = Oversee_Branding::darken_color($primary, 10);
    $primary_light = Oversee_Branding::lighten_color($primary, 35);
    $primary_rgb = Oversee_Branding::hex_to_rgb($primary);
    $success_light = Oversee_Branding::lighten_color($branding['success_color'], 40);
    $success_dark = Oversee_Branding::darken_color($branding['success_color'], 20);
    $warning_light = Oversee_Branding::lighten_color($branding['warning_color'], 40);
    $warning_dark = Oversee_Branding::darken_color($branding['warning_color'], 20);
    $error_light = Oversee_Branding::lighten_color($branding['error_color'], 40);
    $error_dark = Oversee_Branding::darken_color($branding['error_color'], 20);
    $info_light = Oversee_Branding::lighten_color($branding['info_color'], 40);
    $info_dark = Oversee_Branding::darken_color($branding['info_color'], 20);
    // Accent and link color variants
    $accent = $branding['accent_color'];
    $accent_hover = Oversee_Branding::darken_color($accent, 10);
    $accent_light = Oversee_Branding::lighten_color($accent, 35);
    $link = $branding['link_color'];
    $link_hover = Oversee_Branding::darken_color($link, 15);
    ?>
    <style id="oversee-branding-vars">
        :root {
            /* Brand colors */
            --primary: <?php echo esc_attr($primary); ?>;
            --primary-hover: <?php echo esc_attr($primary_hover); ?>;
            --primary-light: <?php echo esc_attr($primary_light); ?>;
            --secondary: <?php echo esc_attr($branding['secondary_color']); ?>;
            --accent: <?php echo esc_attr($accent); ?>;
            --accent-hover: <?php echo esc_attr($accent_hover); ?>;
            --accent-light: <?php echo esc_attr($accent_light); ?>;
            --link: <?php echo esc_attr($link); ?>;
            --link-hover: <?php echo esc_attr($link_hover); ?>;

            /* Status colors */
            --success: <?php echo esc_attr($branding['success_color']); ?>;
            --success-light: <?php echo esc_attr($success_light); ?>;
            --success-dark: <?php echo esc_attr($success_dark); ?>;
            --warning: <?php echo esc_attr($branding['warning_color']); ?>;
            --warning-light: <?php echo esc_attr($warning_light); ?>;
            --warning-dark: <?php echo esc_attr($warning_dark); ?>;
            --error: <?php echo esc_attr($branding['error_color']); ?>;
            --error-light: <?php echo esc_attr($error_light); ?>;
            --error-dark: <?php echo esc_attr($error_dark); ?>;
            --info: <?php echo esc_attr($branding['info_color']); ?>;
            --info-light: <?php echo esc_attr($info_light); ?>;
            --info-dark: <?php echo esc_attr($info_dark); ?>;

            /* Public page colors */
            --public-bg: <?php echo esc_attr($branding['public_bg']); ?>;
            --public-surface: <?php echo esc_attr($branding['public_surface']); ?>;
            --public-surface-hover: <?php echo esc_attr($branding['public_surface_hover']); ?>;
            --public-text: <?php echo esc_attr($branding['public_text']); ?>;
            --public-text-muted: <?php echo esc_attr($branding['public_text_muted']); ?>;
            --public-border: <?php echo esc_attr($branding['public_border']); ?>;
            --public-input-bg: <?php echo esc_attr($branding['public_input_bg']); ?>;

            /* Hero colors */
            --hero-bg: <?php echo esc_attr($branding['hero_bg']); ?>;
            --hero-text: <?php echo esc_attr($branding['hero_text']); ?>;

            /* Global */
            --text-on-dark: <?php echo esc_attr($branding['text_on_dark']); ?>;
            --text-inverse: var(--text-on-dark);
            --input-border: <?php echo esc_attr($branding['public_border']); ?>;

            /* Legacy aliases */
            --bg-body: var(--public-bg);
            --bg-white: var(--public-surface);
            --bg-light: var(--public-bg);
            --text-primary: var(--public-text);
            --text-secondary: var(--public-text-muted);
            --text-muted: var(--public-text-muted);
            --border: var(--public-border);
            --color-primary: var(--primary);
            --color-primary-hover: var(--primary-hover);
            --success-text: var(--success-dark);
            --warning-text: var(--warning-dark);
            --error-text: var(--error-dark);
            --info-text: var(--info-dark);

            /* Primary shadows */
            --shadow-primary-sm: 0 2px 4px rgba(<?php echo $primary_rgb; ?>, 0.25);
            --shadow-primary: 0 4px 12px rgba(<?php echo $primary_rgb; ?>, 0.3);
            --shadow-primary-lg: 0 8px 24px rgba(<?php echo $primary_rgb; ?>, 0.2);
            --focus-ring-primary: 0 0 0 3px rgba(<?php echo $primary_rgb; ?>, 0.15);
        }
    </style>
    <?php if (!empty($branding['custom_css'])): ?>
    <style id="oversee-custom-css"><?php echo $branding['custom_css']; ?></style>
    <?php endif; ?>
</head>
<body class="oversee-public">
    <div class="page-wrapper">
        <?php self::render_public_header($branding, $nav_state); ?>
        
        <main class="main-content">
            <?php echo $content; ?>
        </main>
        
        <?php self::render_public_footer($branding); ?>
    </div>
    
    <script>
    function toggleMobileMenu() {
        var menu = document.getElementById('mobileMenu');
        if (menu) menu.classList.toggle('active');
    }
    </script>
</body>
</html>
        <?php
    }
    
    /**
     * Render the public header
     */
    private static function render_public_header($branding, $nav_state) {
        ?>
        <header class="site-header">
            <div class="header-inner">
                <a href="<?php echo esc_url(home_url('/support/')); ?>" class="header-logo">
                    <?php if (!empty($branding['logo_url'])): ?>
                        <img src="<?php echo esc_url($branding['logo_url']); ?>" 
                             alt="<?php echo esc_attr($branding['company_name']); ?>" 
                             style="max-width:<?php echo intval($branding['logo_width']); ?>px;height:auto;">
                    <?php else: ?>
                        <i class="fa-solid fa-headset"></i>
                        <span><?php echo esc_html($branding['company_name']); ?></span>
                    <?php endif; ?>
                </a>
                
                <nav class="header-nav">
                    <a href="<?php echo esc_url(home_url('/support/kb/')); ?>" class="<?php echo $nav_state['is_kb'] ? 'active' : ''; ?>">
                        <i class="fa-solid fa-book"></i>
                        <span>Knowledge Base</span>
                    </a>
                    <a href="<?php echo esc_url(home_url('/support/tickets/')); ?>" class="<?php echo $nav_state['is_tickets'] ? 'active' : ''; ?>">
                        <i class="fa-solid fa-ticket"></i>
                        <span>My Tickets</span>
                    </a>
                    <a href="<?php echo esc_url(home_url('/support/zoom/')); ?>" class="<?php echo $nav_state['is_zoom'] ? 'active' : ''; ?>">
                        <i class="fa-solid fa-video"></i>
                        <span>Live Support</span>
                    </a>
                </nav>
                
                <div class="header-actions">
                    <a href="<?php echo esc_url(home_url('/support/submit/')); ?>" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-plus"></i>
                        <span>Submit Ticket</span>
                    </a>
                </div>
                
                <button class="mobile-menu-btn" onclick="toggleMobileMenu()" aria-label="Toggle menu">
                    <i class="fa-solid fa-bars"></i>
                </button>
            </div>
            
            <div class="mobile-menu" id="mobileMenu">
                <a href="<?php echo esc_url(home_url('/support/kb/')); ?>" class="<?php echo $nav_state['is_kb'] ? 'active' : ''; ?>">
                    <i class="fa-solid fa-book"></i> Knowledge Base
                </a>
                <a href="<?php echo esc_url(home_url('/support/tickets/')); ?>" class="<?php echo $nav_state['is_tickets'] ? 'active' : ''; ?>">
                    <i class="fa-solid fa-ticket"></i> My Tickets
                </a>
                <a href="<?php echo esc_url(home_url('/support/zoom/')); ?>" class="<?php echo $nav_state['is_zoom'] ? 'active' : ''; ?>">
                    <i class="fa-solid fa-video"></i> Live Support
                </a>
                <a href="<?php echo esc_url(home_url('/support/submit/')); ?>" class="highlight">
                    <i class="fa-solid fa-plus"></i> Submit Ticket
                </a>
            </div>
        </header>
        <?php
    }
    
    /**
     * Render the public footer
     */
    private static function render_public_footer($branding) {
        ?>
        <footer class="site-footer">
            <div class="footer-inner">
                <?php if (!empty($branding['footer_text'])): ?>
                    <div class="footer-text"><?php echo wp_kses_post($branding['footer_text']); ?></div>
                <?php endif; ?>
                
                <p class="footer-copyright"><?php echo esc_html($branding['footer_copyright']); ?></p>
                
                <?php if (!empty($branding['support_email']) || !empty($branding['support_phone'])): ?>
                <div class="footer-contact">
                    <?php if (!empty($branding['support_email'])): ?>
                        <a href="mailto:<?php echo esc_attr($branding['support_email']); ?>">
                            <i class="fa-solid fa-envelope"></i> <?php echo esc_html($branding['support_email']); ?>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($branding['support_phone'])): ?>
                        <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $branding['support_phone'])); ?>">
                            <i class="fa-solid fa-phone"></i> <?php echo esc_html($branding['support_phone']); ?>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </footer>
        <?php
    }
    
    /**
     * Render admin layout wrapper (for standalone admin pages)
     */
    private static function render_admin_layout($content, $page_title, $data) {
        // Admin templates handle their own layout for now
        // This can be expanded later for the /support/admin/ routes
        echo $content;
    }
    
    /**
     * Get branding settings with defaults
     */
    private static function get_branding() {
        $branding = [
            // Company info
            'company_name' => Oversee_Branding::get('company_name', 'Support Center'),
            'tagline' => Oversee_Branding::get('tagline', ''),
            'logo_url' => Oversee_Branding::get('logo_url', ''),
            'logo_width' => Oversee_Branding::get('logo_width', 150),
            'favicon_url' => Oversee_Branding::get('favicon_url', ''),
            // Brand colors
            'primary_color' => Oversee_Branding::get('primary_color', '#f97316'),
            'secondary_color' => Oversee_Branding::get('secondary_color', '#1e293b'),
            // Status colors
            'success_color' => Oversee_Branding::get('success_color', '#10b981'),
            'warning_color' => Oversee_Branding::get('warning_color', '#f59e0b'),
            'error_color' => Oversee_Branding::get('error_color', '#ef4444'),
            'info_color' => Oversee_Branding::get('info_color', '#3b82f6'),
            // Brand colors
            'accent_color' => Oversee_Branding::get('accent_color', '#3b82f6'),
            'link_color' => Oversee_Branding::get('link_color', '#2563eb'),
            'text_on_dark' => Oversee_Branding::get('text_on_dark', '#ffffff'),
            // Public page colors (simplified names)
            'public_bg' => Oversee_Branding::get('public_bg', '#f9fafb'),
            'public_surface' => Oversee_Branding::get('public_surface', '#ffffff'),
            'public_surface_hover' => Oversee_Branding::get('public_surface_hover', '#f8fafc'),
            'public_text' => Oversee_Branding::get('public_text', '#1f2937'),
            'public_text_muted' => Oversee_Branding::get('public_text_muted', '#6b7280'),
            'public_border' => Oversee_Branding::get('public_border', '#e5e7eb'),
            'public_input_bg' => Oversee_Branding::get('public_input_bg', '#ffffff'),
            // Hero colors
            'hero_bg' => Oversee_Branding::get('hero_bg', '#1e293b'),
            'hero_text' => Oversee_Branding::get('hero_text', '#ffffff'),
            // Other
            'support_email' => Oversee_Branding::get('support_email', ''),
            'support_phone' => Oversee_Branding::get('support_phone', ''),
            'footer_text' => Oversee_Branding::get('footer_text', ''),
            'footer_copyright' => Oversee_Branding::get('footer_copyright', '© {year} {company}. All rights reserved.'),
            'custom_css' => Oversee_Branding::get('custom_css', ''),
        ];
        
        // Process dynamic tokens in footer_copyright
        $branding['footer_copyright'] = str_replace(
            ['{year}', '{company}'],
            [date('Y'), $branding['company_name']],
            $branding['footer_copyright']
        );
        
        return $branding;
    }
    
    /**
     * Get navigation state based on current URL
     */
    private static function get_nav_state($current_path) {
        return [
            'is_kb' => (
                strpos($current_path, '/support/kb') !== false || 
                $current_path === '/support/' || 
                $current_path === '/support' ||
                preg_match('#/support/?$#', $current_path)
            ),
            'is_tickets' => (
                strpos($current_path, '/support/tickets') !== false || 
                strpos($current_path, '/support/ticket/') !== false
            ),
            'is_zoom' => strpos($current_path, '/support/zoom') !== false,
            'is_submit' => strpos($current_path, '/support/submit') !== false,
        ];
    }
    
    /**
     * Resolve template path
     */
    private static function resolve_template_path($template) {
        // Remove .php extension if present
        $template = preg_replace('/\.php$/', '', $template);
        
        // Check in pages subdirectory first (new structure)
        $paths = [
            OVERSEE_TEMPLATES_PATH . '/pages/' . $template . '.php',
            OVERSEE_TEMPLATES_PATH . '/' . $template . '.php',
        ];
        
        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        return false;
    }
    
    /**
     * Get template data (for use within templates)
     */
    public static function get_data($key = null, $default = null) {
        if ($key === null) {
            return self::$template_data;
        }
        return isset(self::$template_data[$key]) ? self::$template_data[$key] : $default;
    }
    
    /**
     * Check if currently rendering
     */
    public static function is_rendering() {
        return self::$rendering;
    }
}
