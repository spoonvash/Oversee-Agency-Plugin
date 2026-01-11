<?php
/**
 * Branding Management - Enhanced
 * 
 * Handles comprehensive white-label branding for:
 * - Public support pages (header, footer, colors)
 * - Standalone admin portal (sidebar, colors)
 * - Login page customization
 * - Email templates
 * 
 * @package Oversee_Helpdesk
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Oversee_Branding {
    
    /**
     * Default branding values organized by section
     */
    private static $defaults = [
        // Company Info
        'company_name' => 'Support Center',
        'tagline' => 'We\'re here to help',
        'support_email' => '',
        'support_phone' => '',

        // Logo & Images
        'logo_url' => '',
        'logo_width' => '150',
        'logo_dark_url' => '',  // For dark backgrounds
        'favicon_url' => '',

        // Colors
        'primary_color' => '#f97316',
        'primary_hover' => '#ea580c',
        'secondary_color' => '#1e293b',
        'accent_color' => '#3b82f6',
        'success_color' => '#10b981',
        'warning_color' => '#f59e0b',
        'error_color' => '#ef4444',
        
        // Sidebar/Admin Text Colors
        'sidebar_text_color' => '#94a3b8',      // Muted text for nav items
        'sidebar_text_hover' => '#ffffff',       // Text on hover
        'sidebar_text_active' => '#ffffff',      // Active nav item text
        'sidebar_heading_color' => '#64748b',    // Section headings
        
        // Login Page
        'login_bg_type' => 'gradient',  // gradient, color, image
        'login_bg_color' => '#1e293b',
        'login_bg_gradient' => 'linear-gradient(135deg, #1e3a5f 0%, #0f172a 100%)',
        'login_bg_image' => '',
        'login_welcome_title' => 'Welcome Back',
        'login_welcome_message' => 'Sign in to access your support dashboard.',
        'login_show_logo' => '1',
        'login_logo_position' => 'both',  // form, sidebar, both
        
        // Footer
        'footer_text' => '',
        'footer_copyright' => '© {year} {company}. All rights reserved.',
        'footer_links' => '',  // JSON array of {label, url}
        
        // Custom
        'custom_css' => '',
        'custom_js' => '',
    ];
    
    /**
     * Initialize branding system
     */
    public static function init() {
        add_action('admin_init', [__CLASS__, 'register_settings']);
        
        // Don't output CSS via wp_head - Template Loader handles public pages
        // Only output for WP admin pages where plugin is active
        add_action('admin_head', [__CLASS__, 'output_admin_css'], 5);
    }
    
    /**
     * Register all settings
     */
    public static function register_settings() {
        foreach (array_keys(self::$defaults) as $key) {
            register_setting('oversee_branding', 'oversee_' . $key);
        }
    }
    
    /**
     * Get a single branding value
     */
    public static function get($key, $default = null) {
        $value = get_option('oversee_' . $key, '');
        if ($value === '' || $value === false) {
            return $default !== null ? $default : (self::$defaults[$key] ?? '');
        }
        return $value;
    }
    
    /**
     * Get all branding values
     */
    public static function get_all() {
        $branding = [];
        foreach (array_keys(self::$defaults) as $key) {
            $branding[$key] = self::get($key);
        }
        
        // Process dynamic values
        $branding['footer_copyright'] = str_replace(
            ['{year}', '{company}'],
            [date('Y'), $branding['company_name']],
            $branding['footer_copyright']
        );
        
        return $branding;
    }
    
    /**
     * Get branding for a specific context
     */
    public static function get_for_context($context = 'public') {
        $all = self::get_all();
        
        switch ($context) {
            case 'login':
                return [
                    'logo_url' => $all['logo_dark_url'] ?: $all['logo_url'],
                    'logo_width' => $all['logo_width'],
                    'company_name' => $all['company_name'],
                    'bg_type' => $all['login_bg_type'],
                    'bg_color' => $all['login_bg_color'],
                    'bg_gradient' => $all['login_bg_gradient'],
                    'bg_image' => $all['login_bg_image'],
                    'welcome_title' => $all['login_welcome_title'],
                    'welcome_message' => $all['login_welcome_message'],
                    'show_logo' => $all['login_show_logo'],
                    'logo_position' => $all['login_logo_position'],
                    'primary_color' => $all['primary_color'],
                ];
            
            case 'admin':
                return [
                    'logo_url' => $all['logo_dark_url'] ?: $all['logo_url'],
                    'logo_width' => $all['logo_width'],
                    'company_name' => $all['company_name'],
                    'primary_color' => $all['primary_color'],
                    'secondary_color' => $all['secondary_color'],
                    'accent_color' => $all['accent_color'],
                ];
            
            case 'email':
                return [
                    'logo_url' => $all['logo_url'],
                    'company_name' => $all['company_name'],
                    'support_email' => $all['support_email'],
                    'primary_color' => $all['primary_color'],
                ];
            
            default: // public
                return $all;
        }
    }
    
    /**
     * Save branding settings
     */
    public static function save($data) {
        $sanitizers = [
            'company_name' => 'sanitize_text_field',
            'tagline' => 'sanitize_text_field',
            'support_email' => 'sanitize_email',
            'support_phone' => 'sanitize_text_field',
            'logo_url' => 'esc_url_raw',
            'logo_width' => 'absint',
            'logo_dark_url' => 'esc_url_raw',
            'favicon_url' => 'esc_url_raw',
            'primary_color' => 'sanitize_hex_color',
            'primary_hover' => 'sanitize_hex_color',
            'secondary_color' => 'sanitize_hex_color',
            'accent_color' => 'sanitize_hex_color',
            'success_color' => 'sanitize_hex_color',
            'warning_color' => 'sanitize_hex_color',
            'error_color' => 'sanitize_hex_color',
            'sidebar_text_color' => 'sanitize_hex_color',
            'sidebar_text_hover' => 'sanitize_hex_color',
            'sidebar_text_active' => 'sanitize_hex_color',
            'sidebar_heading_color' => 'sanitize_hex_color',
            'login_bg_type' => 'sanitize_text_field',
            'login_bg_color' => 'sanitize_hex_color',
            'login_bg_gradient' => 'sanitize_text_field',
            'login_bg_image' => 'esc_url_raw',
            'login_welcome_title' => 'sanitize_text_field',
            'login_welcome_message' => 'sanitize_textarea_field',
            'login_show_logo' => 'absint',
            'login_logo_position' => 'sanitize_text_field',
            'footer_text' => 'wp_kses_post',
            'footer_copyright' => 'sanitize_text_field',
            'footer_links' => 'sanitize_text_field',
            'custom_css' => 'strip_tags',
            'custom_js' => 'strip_tags',
        ];
        
        foreach ($sanitizers as $key => $sanitizer) {
            if (isset($data[$key])) {
                $value = call_user_func($sanitizer, $data[$key]);
                update_option('oversee_' . $key, $value);
            }
        }
        
        // Auto-generate primary_hover if not set
        if (isset($data['primary_color']) && empty($data['primary_hover'])) {
            update_option('oversee_primary_hover', self::darken_color($data['primary_color'], 10));
        }
    }
    
    /**
     * Output CSS for WP admin pages
     */
    public static function output_admin_css() {
        // Only on our plugin pages
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'oversee') === false) {
            return;
        }
        
        echo self::get_css_variables();
    }
    
    /**
     * Get CSS variables block
     */
    public static function get_css_variables() {
        $b = self::get_all();
        
        $css = '<style id="oversee-branding-vars">
:root {
    --oversee-primary: ' . esc_attr($b['primary_color']) . ';
    --oversee-primary-hover: ' . esc_attr($b['primary_hover'] ?: self::darken_color($b['primary_color'], 10)) . ';
    --oversee-secondary: ' . esc_attr($b['secondary_color']) . ';
    --oversee-accent: ' . esc_attr($b['accent_color']) . ';
    --oversee-success: ' . esc_attr($b['success_color']) . ';
    --oversee-warning: ' . esc_attr($b['warning_color']) . ';
    --oversee-error: ' . esc_attr($b['error_color']) . ';
    
    /* Sidebar text colors */
    --sidebar-text: ' . esc_attr($b['sidebar_text_color'] ?: '#94a3b8') . ';
    --sidebar-text-hover: ' . esc_attr($b['sidebar_text_hover'] ?: '#ffffff') . ';
    --sidebar-text-active: ' . esc_attr($b['sidebar_text_active'] ?: '#ffffff') . ';
    --sidebar-heading: ' . esc_attr($b['sidebar_heading_color'] ?: '#64748b') . ';
    
    /* Aliases for templates */
    --primary: var(--oversee-primary);
    --primary-hover: var(--oversee-primary-hover);
    --secondary: var(--oversee-secondary);
    --accent: var(--oversee-accent);
    --sidebar-bg: var(--oversee-secondary);
    --hero-gradient: linear-gradient(135deg, ' . esc_attr($b['secondary_color']) . ' 0%, ' . esc_attr(self::darken_color($b['secondary_color'], 20)) . ' 100%);
}
</style>';
        
        if (!empty($b['custom_css'])) {
            $css .= '<style id="oversee-custom-css">' . $b['custom_css'] . '</style>';
        }
        
        return $css;
    }
    
    /**
     * Get login page background CSS
     */
    public static function get_login_background_css() {
        $b = self::get_for_context('login');
        
        switch ($b['bg_type']) {
            case 'image':
                if ($b['bg_image']) {
                    return 'background: url(' . esc_url($b['bg_image']) . ') center/cover no-repeat fixed; background-color: ' . esc_attr($b['bg_color']) . ';';
                }
                // Fallback to color
                return 'background-color: ' . esc_attr($b['bg_color']) . ';';
            
            case 'color':
                return 'background-color: ' . esc_attr($b['bg_color']) . ';';
            
            case 'gradient':
            default:
                return 'background: ' . esc_attr($b['bg_gradient']) . ';';
        }
    }
    
    /**
     * Darken a hex color by percentage
     */
    public static function darken_color($hex, $percent) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return '#' . $hex;
        }
        $r = max(0, hexdec(substr($hex, 0, 2)) - (255 * $percent / 100));
        $g = max(0, hexdec(substr($hex, 2, 2)) - (255 * $percent / 100));
        $b = max(0, hexdec(substr($hex, 4, 2)) - (255 * $percent / 100));
        return sprintf('#%02x%02x%02x', (int)$r, (int)$g, (int)$b);
    }
    
    /**
     * Lighten a hex color by percentage
     */
    public static function lighten_color($hex, $percent) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return '#' . $hex;
        }
        $r = min(255, hexdec(substr($hex, 0, 2)) + (255 * $percent / 100));
        $g = min(255, hexdec(substr($hex, 2, 2)) + (255 * $percent / 100));
        $b = min(255, hexdec(substr($hex, 4, 2)) + (255 * $percent / 100));
        return sprintf('#%02x%02x%02x', (int)$r, (int)$g, (int)$b);
    }
    
    /**
     * Get logo HTML
     */
    public static function get_logo_html($class = 'oversee-logo', $context = 'public') {
        $logo = $context === 'public' ? self::get('logo_url') : (self::get('logo_dark_url') ?: self::get('logo_url'));
        $name = self::get('company_name', 'Support');
        $width = self::get('logo_width', 150);
        
        if ($logo) {
            return '<img src="' . esc_url($logo) . '" alt="' . esc_attr($name) . '" class="' . esc_attr($class) . '" style="max-width:' . intval($width) . 'px;height:auto;">';
        }
        return '<span class="' . esc_attr($class) . ' logo-text">' . esc_html($name) . '</span>';
    }
    
    /**
     * Render the branding settings form (for WP admin Settings page)
     */
    public static function render_settings_form() {
        $b = self::get_all();
        ?>
        <div class="oversee-branding-form">
            <!-- Company Info Section -->
            <div class="branding-section">
                <h3><i class="dashicons dashicons-building"></i> Company Information</h3>
                <table class="form-table">
                    <tr>
                        <th><label for="company_name">Company Name</label></th>
                        <td>
                            <input type="text" id="company_name" name="company_name" value="<?php echo esc_attr($b['company_name']); ?>" class="regular-text">
                            <p class="description">Shown in header, footer, and emails.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tagline">Tagline</label></th>
                        <td>
                            <input type="text" id="tagline" name="tagline" value="<?php echo esc_attr($b['tagline']); ?>" class="regular-text">
                            <p class="description">Short description shown on login page.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="support_email">Support Email</label></th>
                        <td>
                            <input type="email" id="support_email" name="support_email" value="<?php echo esc_attr($b['support_email']); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="support_phone">Support Phone</label></th>
                        <td>
                            <input type="text" id="support_phone" name="support_phone" value="<?php echo esc_attr($b['support_phone']); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Logo Section -->
            <div class="branding-section">
                <h3><i class="dashicons dashicons-format-image"></i> Logo & Images</h3>
                <table class="form-table">
                    <tr>
                        <th><label>Primary Logo</label></th>
                        <td>
                            <input type="hidden" id="logo_url" name="logo_url" value="<?php echo esc_url($b['logo_url']); ?>">
                            <div id="logo-preview" class="image-preview">
                                <?php if ($b['logo_url']): ?>
                                    <img src="<?php echo esc_url($b['logo_url']); ?>">
                                <?php else: ?>
                                    <span class="no-image">No logo uploaded</span>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="button" id="upload-logo-btn">Upload Logo</button>
                            <?php if ($b['logo_url']): ?>
                                <button type="button" class="button" id="remove-logo-btn">Remove</button>
                            <?php endif; ?>
                            <p class="description">Used on light backgrounds. PNG with transparency recommended.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Logo for Dark Backgrounds</label></th>
                        <td>
                            <input type="hidden" id="logo_dark_url" name="logo_dark_url" value="<?php echo esc_url($b['logo_dark_url']); ?>">
                            <div id="logo-dark-preview" class="image-preview dark-bg">
                                <?php if ($b['logo_dark_url']): ?>
                                    <img src="<?php echo esc_url($b['logo_dark_url']); ?>">
                                <?php else: ?>
                                    <span class="no-image">No logo uploaded (will use primary)</span>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="button" id="upload-logo-dark-btn">Upload Dark Logo</button>
                            <?php if ($b['logo_dark_url']): ?>
                                <button type="button" class="button" id="remove-logo-dark-btn">Remove</button>
                            <?php endif; ?>
                            <p class="description">Used on admin sidebar and login page. Light/white version recommended.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="logo_width">Logo Width</label></th>
                        <td>
                            <input type="number" id="logo_width" name="logo_width" value="<?php echo esc_attr($b['logo_width']); ?>" class="small-text" min="50" max="400"> px
                        </td>
                    </tr>
                    <tr>
                        <th><label>Favicon</label></th>
                        <td>
                            <input type="hidden" id="favicon_url" name="favicon_url" value="<?php echo esc_url($b['favicon_url']); ?>">
                            <div id="favicon-preview" class="image-preview small">
                                <?php if ($b['favicon_url']): ?>
                                    <img src="<?php echo esc_url($b['favicon_url']); ?>">
                                <?php else: ?>
                                    <span class="no-image">No favicon</span>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="button" id="upload-favicon-btn">Upload Favicon</button>
                            <?php if ($b['favicon_url']): ?>
                                <button type="button" class="button" id="remove-favicon-btn">Remove</button>
                            <?php endif; ?>
                            <p class="description">Square image, at least 32x32px.</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Colors Section -->
            <div class="branding-section">
                <h3><i class="dashicons dashicons-admin-customizer"></i> Brand Colors</h3>
                <table class="form-table">
                    <tr>
                        <th>Primary Color</th>
                        <td>
                            <input type="text" name="primary_color" value="<?php echo esc_attr($b['primary_color']); ?>" class="oversee-color-picker" data-default-color="#f97316">
                            <p class="description">Buttons, links, accents.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Secondary Color</th>
                        <td>
                            <input type="text" name="secondary_color" value="<?php echo esc_attr($b['secondary_color']); ?>" class="oversee-color-picker" data-default-color="#1e293b">
                            <p class="description">Admin sidebar background, hero backgrounds.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Accent Color</th>
                        <td>
                            <input type="text" name="accent_color" value="<?php echo esc_attr($b['accent_color']); ?>" class="oversee-color-picker" data-default-color="#3b82f6">
                            <p class="description">Info badges, secondary actions.</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Sidebar Text Colors Section -->
            <div class="branding-section">
                <h3><i class="dashicons dashicons-text-page"></i> Sidebar Text Colors</h3>
                <p class="section-description">Customize text colors in the admin sidebar. Adjust these when changing the sidebar background color.</p>
                <table class="form-table">
                    <tr>
                        <th>Navigation Text</th>
                        <td>
                            <input type="text" name="sidebar_text_color" value="<?php echo esc_attr($b['sidebar_text_color']); ?>" class="oversee-color-picker" data-default-color="#94a3b8">
                            <p class="description">Default text color for sidebar menu items.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Hover Text</th>
                        <td>
                            <input type="text" name="sidebar_text_hover" value="<?php echo esc_attr($b['sidebar_text_hover']); ?>" class="oversee-color-picker" data-default-color="#ffffff">
                            <p class="description">Text color when hovering over menu items.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Active Text</th>
                        <td>
                            <input type="text" name="sidebar_text_active" value="<?php echo esc_attr($b['sidebar_text_active']); ?>" class="oversee-color-picker" data-default-color="#ffffff">
                            <p class="description">Text color for the currently active menu item.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Section Headings</th>
                        <td>
                            <input type="text" name="sidebar_heading_color" value="<?php echo esc_attr($b['sidebar_heading_color']); ?>" class="oversee-color-picker" data-default-color="#64748b">
                            <p class="description">Text color for "Main", "Manage", etc. section labels.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Live Preview Section -->
            <div class="branding-section">
                <h3><i class="dashicons dashicons-visibility"></i> Live Preview</h3>
                <p class="section-description">See how your branding will look before saving.</p>

                <div class="preview-container">
                    <!-- Public Header Preview -->
                    <div class="preview-panel">
                        <h4>Public Header</h4>
                        <div class="preview-box preview-header" id="preview-header">
                            <div class="preview-header-inner">
                                <div class="preview-logo" id="preview-logo">
                                    <?php if ($b['logo_url']): ?>
                                        <img src="<?php echo esc_url($b['logo_url']); ?>" alt="Logo">
                                    <?php else: ?>
                                        <span class="preview-logo-text"><?php echo esc_html($b['company_name']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="preview-nav">
                                    <span class="preview-nav-link">Home</span>
                                    <span class="preview-nav-link">Articles</span>
                                    <button class="preview-btn preview-btn-primary" id="preview-btn">Submit Ticket</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Button Styles Preview -->
                    <div class="preview-panel">
                        <h4>Buttons & Links</h4>
                        <div class="preview-box preview-buttons">
                            <button class="preview-btn preview-btn-primary" id="preview-btn-primary">Primary Button</button>
                            <button class="preview-btn preview-btn-secondary" id="preview-btn-secondary">Secondary</button>
                            <a href="#" class="preview-link" id="preview-link" onclick="return false;">Link Text</a>
                            <span class="preview-badge preview-badge-success">Open</span>
                            <span class="preview-badge preview-badge-warning">Pending</span>
                            <span class="preview-badge preview-badge-error">Closed</span>
                        </div>
                    </div>

                    <!-- Admin Sidebar Preview -->
                    <div class="preview-panel">
                        <h4>Admin Sidebar</h4>
                        <div class="preview-box preview-sidebar" id="preview-sidebar">
                            <div class="preview-sidebar-header" id="preview-sidebar-header">
                                <?php if ($b['logo_dark_url'] ?: $b['logo_url']): ?>
                                    <img src="<?php echo esc_url($b['logo_dark_url'] ?: $b['logo_url']); ?>" alt="Logo">
                                <?php else: ?>
                                    <span><?php echo esc_html($b['company_name']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="preview-sidebar-nav">
                                <div class="preview-sidebar-section" id="preview-sidebar-heading">Main</div>
                                <a href="#" class="preview-sidebar-item active" id="preview-sidebar-active" onclick="return false;">
                                    <i class="dashicons dashicons-dashboard"></i> Dashboard
                                </a>
                                <a href="#" class="preview-sidebar-item" id="preview-sidebar-item" onclick="return false;">
                                    <i class="dashicons dashicons-tickets-alt"></i> Tickets
                                </a>
                                <a href="#" class="preview-sidebar-item hover" id="preview-sidebar-hover" onclick="return false;">
                                    <i class="dashicons dashicons-book"></i> Articles (Hover)
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Login Page Section -->
            <div class="branding-section">
                <h3><i class="dashicons dashicons-lock"></i> Login Page Customization</h3>
                <table class="form-table">
                    <tr>
                        <th>Background Style</th>
                        <td>
                            <select name="login_bg_type" id="login_bg_type" onchange="toggleLoginBgOptions()">
                                <option value="gradient" <?php selected($b['login_bg_type'], 'gradient'); ?>>Gradient</option>
                                <option value="color" <?php selected($b['login_bg_type'], 'color'); ?>>Solid Color</option>
                                <option value="image" <?php selected($b['login_bg_type'], 'image'); ?>>Background Image</option>
                            </select>
                        </td>
                    </tr>
                    <tr id="login-bg-color-row" style="<?php echo $b['login_bg_type'] === 'gradient' ? 'display:none;' : ''; ?>">
                        <th>Background Color</th>
                        <td>
                            <input type="text" name="login_bg_color" value="<?php echo esc_attr($b['login_bg_color']); ?>" class="oversee-color-picker" data-default-color="#1e293b">
                        </td>
                    </tr>
                    <tr id="login-bg-gradient-row" style="<?php echo $b['login_bg_type'] !== 'gradient' ? 'display:none;' : ''; ?>">
                        <th>Gradient</th>
                        <td>
                            <input type="text" name="login_bg_gradient" value="<?php echo esc_attr($b['login_bg_gradient']); ?>" class="regular-text">
                            <p class="description">CSS gradient (e.g., linear-gradient(135deg, #1e3a5f 0%, #0f172a 100%))</p>
                        </td>
                    </tr>
                    <tr id="login-bg-image-row" style="<?php echo $b['login_bg_type'] !== 'image' ? 'display:none;' : ''; ?>">
                        <th>Background Image</th>
                        <td>
                            <input type="hidden" id="login_bg_image" name="login_bg_image" value="<?php echo esc_url($b['login_bg_image']); ?>">
                            <div id="login-bg-preview" class="image-preview wide">
                                <?php if ($b['login_bg_image']): ?>
                                    <img src="<?php echo esc_url($b['login_bg_image']); ?>">
                                <?php else: ?>
                                    <span class="no-image">No image uploaded</span>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="button" id="upload-login-bg-btn">Upload Image</button>
                            <?php if ($b['login_bg_image']): ?>
                                <button type="button" class="button" id="remove-login-bg-btn">Remove</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="login_welcome_title">Welcome Title</label></th>
                        <td>
                            <input type="text" id="login_welcome_title" name="login_welcome_title" value="<?php echo esc_attr($b['login_welcome_title']); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="login_welcome_message">Welcome Message</label></th>
                        <td>
                            <textarea id="login_welcome_message" name="login_welcome_message" rows="2" class="large-text"><?php echo esc_textarea($b['login_welcome_message']); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th>Show Logo</th>
                        <td>
                            <label>
                                <input type="checkbox" name="login_show_logo" value="1" <?php checked($b['login_show_logo'], '1'); ?>>
                                Display logo on login page
                            </label>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Footer Section -->
            <div class="branding-section">
                <h3><i class="dashicons dashicons-editor-alignleft"></i> Footer</h3>
                <table class="form-table">
                    <tr>
                        <th><label for="footer_copyright">Copyright Text</label></th>
                        <td>
                            <input type="text" id="footer_copyright" name="footer_copyright" value="<?php echo esc_attr(self::get('footer_copyright')); ?>" class="regular-text">
                            <p class="description">Use {year} for current year, {company} for company name.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="footer_text">Additional Footer HTML</label></th>
                        <td>
                            <textarea id="footer_text" name="footer_text" rows="2" class="large-text"><?php echo esc_textarea($b['footer_text']); ?></textarea>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Custom Code Section -->
            <div class="branding-section">
                <h3><i class="dashicons dashicons-editor-code"></i> Custom Code</h3>
                <table class="form-table">
                    <tr>
                        <th><label for="custom_css">Custom CSS</label></th>
                        <td>
                            <textarea id="custom_css" name="custom_css" rows="6" class="large-text code"><?php echo esc_textarea($b['custom_css']); ?></textarea>
                            <p class="description">Added to all public and admin pages.</p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <style>
            .oversee-branding-form { max-width: 900px; }
            .branding-section { background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 20px; }
            .branding-section h3 { margin: 0 0 15px 0; padding-bottom: 10px; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 8px; }
            .branding-section h3 .dashicons { color: #666; }
            .branding-section h4 { margin: 0 0 10px 0; font-size: 13px; color: #555; }
            .section-description { margin: 0 0 15px 0; color: #666; font-size: 13px; }
            .image-preview { width: 200px; height: 80px; border: 1px dashed #ccc; border-radius: 4px; display: flex; align-items: center; justify-content: center; margin-bottom: 10px; background: #f9f9f9; overflow: hidden; }
            .image-preview.dark-bg { background: #1e293b; }
            .image-preview.dark-bg .no-image { color: #fff; }
            .image-preview.small { width: 64px; height: 64px; }
            .image-preview.wide { width: 300px; height: 150px; }
            .image-preview img { max-width: 100%; max-height: 100%; object-fit: contain; }
            .image-preview .no-image { font-size: 12px; color: #999; text-align: center; }

            /* Live Preview Styles */
            .preview-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; }
            .preview-panel { background: #f9fafb; border-radius: 8px; padding: 15px; }
            .preview-box { border-radius: 6px; overflow: hidden; }

            /* Header Preview */
            .preview-header { background: #fff; border: 1px solid #e5e7eb; }
            .preview-header-inner { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; }
            .preview-logo img { max-height: 32px; width: auto; }
            .preview-logo-text { font-weight: 600; font-size: 16px; color: #1f2937; }
            .preview-nav { display: flex; align-items: center; gap: 16px; }
            .preview-nav-link { font-size: 13px; color: #6b7280; }

            /* Button Preview */
            .preview-buttons { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; padding: 20px; background: #fff; border: 1px solid #e5e7eb; }
            .preview-btn { padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: default; border: none; }
            .preview-btn-primary { background: <?php echo esc_attr($b['primary_color']); ?>; color: #fff; }
            .preview-btn-secondary { background: #f3f4f6; color: #374151; border: 1px solid #e5e7eb; }
            .preview-link { color: <?php echo esc_attr($b['primary_color']); ?>; font-size: 13px; text-decoration: none; }
            .preview-badge { padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 500; }
            .preview-badge-success { background: #d1fae5; color: #047857; }
            .preview-badge-warning { background: #fef3c7; color: #b45309; }
            .preview-badge-error { background: #fee2e2; color: #dc2626; }

            /* Sidebar Preview */
            .preview-sidebar { background: <?php echo esc_attr($b['secondary_color']); ?>; min-height: 200px; width: 180px; }
            .preview-sidebar-header { padding: 12px 14px; border-bottom: 1px solid rgba(255,255,255,0.1); }
            .preview-sidebar-header img { max-height: 28px; width: auto; }
            .preview-sidebar-header span { color: #fff; font-weight: 600; font-size: 14px; }
            .preview-sidebar-nav { padding: 12px 8px; }
            .preview-sidebar-section { font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: <?php echo esc_attr($b['sidebar_heading_color'] ?: '#64748b'); ?>; padding: 8px 10px 4px; }
            .preview-sidebar-item { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border-radius: 6px; font-size: 13px; color: <?php echo esc_attr($b['sidebar_text_color'] ?: '#94a3b8'); ?>; text-decoration: none; margin-bottom: 2px; }
            .preview-sidebar-item .dashicons { font-size: 16px; width: 16px; height: 16px; }
            .preview-sidebar-item.active { background: <?php echo esc_attr($b['primary_color']); ?>; color: <?php echo esc_attr($b['sidebar_text_active'] ?: '#ffffff'); ?>; }
            .preview-sidebar-item.hover { background: rgba(255,255,255,0.05); color: <?php echo esc_attr($b['sidebar_text_hover'] ?: '#ffffff'); ?>; }
        </style>

        <script>
        function toggleLoginBgOptions() {
            var type = document.getElementById('login_bg_type').value;
            document.getElementById('login-bg-color-row').style.display = (type === 'color' || type === 'image') ? '' : 'none';
            document.getElementById('login-bg-gradient-row').style.display = (type === 'gradient') ? '' : 'none';
            document.getElementById('login-bg-image-row').style.display = (type === 'image') ? '' : 'none';
        }

        // Live Preview Updates
        document.addEventListener('DOMContentLoaded', function() {
            // Color picker change handler
            function updatePreview() {
                var primary = getColorValue('primary_color', '#f97316');
                var secondary = getColorValue('secondary_color', '#1e293b');
                var sidebarText = getColorValue('sidebar_text_color', '#94a3b8');
                var sidebarHover = getColorValue('sidebar_text_hover', '#ffffff');
                var sidebarActive = getColorValue('sidebar_text_active', '#ffffff');
                var sidebarHeading = getColorValue('sidebar_heading_color', '#64748b');

                // Update primary button
                var primaryBtns = document.querySelectorAll('.preview-btn-primary');
                primaryBtns.forEach(function(btn) {
                    btn.style.backgroundColor = primary;
                });

                // Update link color
                var links = document.querySelectorAll('.preview-link');
                links.forEach(function(link) {
                    link.style.color = primary;
                });

                // Update sidebar background
                var sidebar = document.getElementById('preview-sidebar');
                if (sidebar) sidebar.style.backgroundColor = secondary;

                // Update sidebar text colors
                var sidebarItems = document.querySelectorAll('.preview-sidebar-item:not(.active):not(.hover)');
                sidebarItems.forEach(function(item) {
                    item.style.color = sidebarText;
                });

                var sidebarActiveItem = document.querySelector('.preview-sidebar-item.active');
                if (sidebarActiveItem) {
                    sidebarActiveItem.style.backgroundColor = primary;
                    sidebarActiveItem.style.color = sidebarActive;
                }

                var sidebarHoverItem = document.querySelector('.preview-sidebar-item.hover');
                if (sidebarHoverItem) {
                    sidebarHoverItem.style.color = sidebarHover;
                }

                var sidebarHeadingEl = document.getElementById('preview-sidebar-heading');
                if (sidebarHeadingEl) sidebarHeadingEl.style.color = sidebarHeading;
            }

            function getColorValue(name, defaultVal) {
                var input = document.querySelector('input[name="' + name + '"]');
                if (!input) return defaultVal;
                return input.value || defaultVal;
            }

            // Watch for color picker changes via custom colorchange event
            if (typeof jQuery !== 'undefined') {
                jQuery('.oversee-color-picker').on('colorchange', function() {
                    setTimeout(updatePreview, 50);
                });
            }

            // Also watch for regular input changes as fallback
            var colorInputs = document.querySelectorAll('.oversee-color-picker');
            colorInputs.forEach(function(input) {
                input.addEventListener('change', updatePreview);
                input.addEventListener('input', updatePreview);
            });

            // Initial update after color pickers are initialized
            setTimeout(updatePreview, 500);
        });
        </script>
        <?php
    }
}
