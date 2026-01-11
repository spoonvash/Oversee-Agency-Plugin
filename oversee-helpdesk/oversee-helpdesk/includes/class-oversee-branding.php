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

        // Brand Colors
        'primary_color' => '#f97316',
        'primary_hover' => '#ea580c',
        'secondary_color' => '#1e293b',
        'accent_color' => '#3b82f6',
        'success_color' => '#10b981',
        'warning_color' => '#f59e0b',
        'error_color' => '#ef4444',

        // Public/KB Page Colors
        'public_bg_color' => '#f9fafb',          // Main page background
        'public_surface_color' => '#ffffff',     // Cards, panels
        'public_text_color' => '#1f2937',        // Primary text
        'public_text_muted' => '#6b7280',        // Secondary text
        'public_border_color' => '#e5e7eb',      // Borders
        'hero_bg_color' => '#1e293b',            // Hero section background
        'hero_text_color' => '#ffffff',          // Hero text

        // Admin Page Colors
        'admin_bg_color' => '#f1f5f9',           // Main admin background
        'admin_surface_color' => '#ffffff',      // Admin cards, panels
        'admin_text_color' => '#1e293b',         // Admin primary text
        'admin_border_color' => '#e2e8f0',       // Admin borders

        // Sidebar/Admin Text Colors
        'sidebar_text_color' => '#94a3b8',       // Muted text for nav items
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
            'public_bg_color' => 'sanitize_hex_color',
            'public_surface_color' => 'sanitize_hex_color',
            'public_text_color' => 'sanitize_hex_color',
            'public_text_muted' => 'sanitize_hex_color',
            'public_border_color' => 'sanitize_hex_color',
            'hero_bg_color' => 'sanitize_hex_color',
            'hero_text_color' => 'sanitize_hex_color',
            'admin_bg_color' => 'sanitize_hex_color',
            'admin_surface_color' => 'sanitize_hex_color',
            'admin_text_color' => 'sanitize_hex_color',
            'admin_border_color' => 'sanitize_hex_color',
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
        $primary = $b['primary_color'];
        $primary_hover = $b['primary_hover'] ?: self::darken_color($primary, 10);
        $primary_light = self::lighten_color($primary, 35);
        $primary_lighter = self::lighten_color($primary, 42);

        // Convert hex to RGB for shadow/transparency support
        $primary_rgb = self::hex_to_rgb($primary);

        $css = '<style id="oversee-branding-vars">
:root {
    /* Primary color system */
    --color-primary: ' . esc_attr($primary) . ';
    --color-primary-hover: ' . esc_attr($primary_hover) . ';
    --color-primary-active: ' . esc_attr(self::darken_color($primary, 20)) . ';
    --color-primary-light: ' . esc_attr($primary_light) . ';
    --color-primary-lighter: ' . esc_attr($primary_lighter) . ';

    /* Primary shadows and focus rings */
    --shadow-primary-sm: 0 2px 4px rgba(' . $primary_rgb . ', 0.25);
    --shadow-primary: 0 4px 12px rgba(' . $primary_rgb . ', 0.3);
    --shadow-primary-lg: 0 8px 24px rgba(' . $primary_rgb . ', 0.2);
    --focus-ring-primary: 0 0 0 3px rgba(' . $primary_rgb . ', 0.15);

    /* Legacy aliases */
    --oversee-primary: var(--color-primary);
    --oversee-primary-hover: var(--color-primary-hover);
    --primary: var(--color-primary);
    --primary-hover: var(--color-primary-hover);
    --primary-light: var(--color-primary-lighter);

    /* Secondary/Sidebar colors */
    --oversee-secondary: ' . esc_attr($b['secondary_color']) . ';
    --oversee-accent: ' . esc_attr($b['accent_color']) . ';
    --secondary: var(--oversee-secondary);
    --accent: var(--oversee-accent);
    --sidebar-bg: var(--oversee-secondary);

    /* Sidebar text colors */
    --sidebar-text: ' . esc_attr($b['sidebar_text_color'] ?: '#94a3b8') . ';
    --sidebar-text-hover: ' . esc_attr($b['sidebar_text_hover'] ?: '#ffffff') . ';
    --sidebar-text-active: ' . esc_attr($b['sidebar_text_active'] ?: '#ffffff') . ';
    --sidebar-heading: ' . esc_attr($b['sidebar_heading_color'] ?: '#64748b') . ';

    /* Status colors */
    --oversee-success: ' . esc_attr($b['success_color']) . ';
    --oversee-warning: ' . esc_attr($b['warning_color']) . ';
    --oversee-error: ' . esc_attr($b['error_color']) . ';
    --success: var(--oversee-success);
    --warning: var(--oversee-warning);
    --error: var(--oversee-error);

    /* Public/KB page colors */
    --color-background: ' . esc_attr($b['public_bg_color'] ?: '#f9fafb') . ';
    --color-surface: ' . esc_attr($b['public_surface_color'] ?: '#ffffff') . ';
    --color-text-primary: ' . esc_attr($b['public_text_color'] ?: '#1f2937') . ';
    --color-text-secondary: ' . esc_attr($b['public_text_muted'] ?: '#6b7280') . ';
    --color-border: ' . esc_attr($b['public_border_color'] ?: '#e5e7eb') . ';
    --bg-light: var(--color-background);
    --bg-white: var(--color-surface);
    --text-primary: var(--color-text-primary);
    --text-secondary: var(--color-text-secondary);
    --text-muted: var(--color-text-secondary);
    --border: var(--color-border);

    /* Hero section */
    --hero-bg: ' . esc_attr($b['hero_bg_color'] ?: '#1e293b') . ';
    --hero-text: ' . esc_attr($b['hero_text_color'] ?: '#ffffff') . ';
    --hero-gradient: linear-gradient(135deg, ' . esc_attr($b['hero_bg_color'] ?: '#1e293b') . ' 0%, ' . esc_attr(self::darken_color($b['hero_bg_color'] ?: '#1e293b', 20)) . ' 100%);

    /* Admin page colors */
    --admin-bg: ' . esc_attr($b['admin_bg_color'] ?: '#f1f5f9') . ';
    --admin-surface: ' . esc_attr($b['admin_surface_color'] ?: '#ffffff') . ';
    --admin-text: ' . esc_attr($b['admin_text_color'] ?: '#1e293b') . ';
    --admin-border: ' . esc_attr($b['admin_border_color'] ?: '#e2e8f0') . ';
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
     * Convert hex color to RGB string for use in rgba()
     */
    public static function hex_to_rgb($hex) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return '0, 0, 0';
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return $r . ', ' . $g . ', ' . $b;
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
            
            <!-- Brand Colors Section -->
            <div class="branding-section">
                <h3><i class="dashicons dashicons-admin-customizer"></i> Brand Colors</h3>
                <p class="section-description">Core brand colors used throughout your support portal.</p>
                <div class="color-grid">
                    <div class="color-item">
                        <label>Primary Color</label>
                        <input type="text" name="primary_color" value="<?php echo esc_attr($b['primary_color']); ?>" class="oversee-color-picker" data-default-color="#f97316">
                        <span class="color-desc">Buttons, links, active states</span>
                    </div>
                    <div class="color-item">
                        <label>Accent Color</label>
                        <input type="text" name="accent_color" value="<?php echo esc_attr($b['accent_color']); ?>" class="oversee-color-picker" data-default-color="#3b82f6">
                        <span class="color-desc">Info badges, secondary actions</span>
                    </div>
                </div>
            </div>

            <!-- Status Colors Section -->
            <div class="branding-section">
                <h3><i class="dashicons dashicons-flag"></i> Status Colors</h3>
                <p class="section-description">Colors for ticket statuses, alerts, and notifications.</p>
                <div class="color-grid">
                    <div class="color-item">
                        <label>Success</label>
                        <input type="text" name="success_color" value="<?php echo esc_attr($b['success_color']); ?>" class="oversee-color-picker" data-default-color="#10b981">
                        <span class="color-desc">Resolved, completed</span>
                    </div>
                    <div class="color-item">
                        <label>Warning</label>
                        <input type="text" name="warning_color" value="<?php echo esc_attr($b['warning_color']); ?>" class="oversee-color-picker" data-default-color="#f59e0b">
                        <span class="color-desc">Pending, waiting</span>
                    </div>
                    <div class="color-item">
                        <label>Error</label>
                        <input type="text" name="error_color" value="<?php echo esc_attr($b['error_color']); ?>" class="oversee-color-picker" data-default-color="#ef4444">
                        <span class="color-desc">Closed, urgent, errors</span>
                    </div>
                </div>
            </div>

            <!-- Public/KB Page Colors Section -->
            <div class="branding-section">
                <h3><i class="dashicons dashicons-welcome-widgets-menus"></i> Knowledge Base Page Colors</h3>
                <p class="section-description">Customize the look of your public-facing knowledge base and support pages.</p>
                <div class="color-grid">
                    <div class="color-item">
                        <label>Page Background</label>
                        <input type="text" name="public_bg_color" value="<?php echo esc_attr($b['public_bg_color'] ?? '#f9fafb'); ?>" class="oversee-color-picker" data-default-color="#f9fafb">
                        <span class="color-desc">Main page background</span>
                    </div>
                    <div class="color-item">
                        <label>Card Background</label>
                        <input type="text" name="public_surface_color" value="<?php echo esc_attr($b['public_surface_color'] ?? '#ffffff'); ?>" class="oversee-color-picker" data-default-color="#ffffff">
                        <span class="color-desc">Cards, panels, modals</span>
                    </div>
                    <div class="color-item">
                        <label>Text Color</label>
                        <input type="text" name="public_text_color" value="<?php echo esc_attr($b['public_text_color'] ?? '#1f2937'); ?>" class="oversee-color-picker" data-default-color="#1f2937">
                        <span class="color-desc">Headings and body text</span>
                    </div>
                    <div class="color-item">
                        <label>Muted Text</label>
                        <input type="text" name="public_text_muted" value="<?php echo esc_attr($b['public_text_muted'] ?? '#6b7280'); ?>" class="oversee-color-picker" data-default-color="#6b7280">
                        <span class="color-desc">Secondary, helper text</span>
                    </div>
                    <div class="color-item">
                        <label>Border Color</label>
                        <input type="text" name="public_border_color" value="<?php echo esc_attr($b['public_border_color'] ?? '#e5e7eb'); ?>" class="oversee-color-picker" data-default-color="#e5e7eb">
                        <span class="color-desc">Dividers and borders</span>
                    </div>
                </div>
                <h4 style="margin-top:20px;">Hero Section</h4>
                <div class="color-grid">
                    <div class="color-item">
                        <label>Hero Background</label>
                        <input type="text" name="hero_bg_color" value="<?php echo esc_attr($b['hero_bg_color'] ?? '#1e293b'); ?>" class="oversee-color-picker" data-default-color="#1e293b">
                        <span class="color-desc">Hero section background</span>
                    </div>
                    <div class="color-item">
                        <label>Hero Text</label>
                        <input type="text" name="hero_text_color" value="<?php echo esc_attr($b['hero_text_color'] ?? '#ffffff'); ?>" class="oversee-color-picker" data-default-color="#ffffff">
                        <span class="color-desc">Hero title and search</span>
                    </div>
                </div>
            </div>

            <!-- Admin Page Colors Section -->
            <div class="branding-section">
                <h3><i class="dashicons dashicons-admin-generic"></i> Admin Portal Colors</h3>
                <p class="section-description">Customize the admin dashboard and ticket management interface.</p>
                <div class="color-grid">
                    <div class="color-item">
                        <label>Page Background</label>
                        <input type="text" name="admin_bg_color" value="<?php echo esc_attr($b['admin_bg_color'] ?? '#f1f5f9'); ?>" class="oversee-color-picker" data-default-color="#f1f5f9">
                        <span class="color-desc">Main admin background</span>
                    </div>
                    <div class="color-item">
                        <label>Card Background</label>
                        <input type="text" name="admin_surface_color" value="<?php echo esc_attr($b['admin_surface_color'] ?? '#ffffff'); ?>" class="oversee-color-picker" data-default-color="#ffffff">
                        <span class="color-desc">Panels, cards, modals</span>
                    </div>
                    <div class="color-item">
                        <label>Text Color</label>
                        <input type="text" name="admin_text_color" value="<?php echo esc_attr($b['admin_text_color'] ?? '#1e293b'); ?>" class="oversee-color-picker" data-default-color="#1e293b">
                        <span class="color-desc">Admin text color</span>
                    </div>
                    <div class="color-item">
                        <label>Border Color</label>
                        <input type="text" name="admin_border_color" value="<?php echo esc_attr($b['admin_border_color'] ?? '#e2e8f0'); ?>" class="oversee-color-picker" data-default-color="#e2e8f0">
                        <span class="color-desc">Dividers and borders</span>
                    </div>
                </div>
                <h4 style="margin-top:20px;">Sidebar</h4>
                <div class="color-grid">
                    <div class="color-item">
                        <label>Sidebar Background</label>
                        <input type="text" name="secondary_color" value="<?php echo esc_attr($b['secondary_color']); ?>" class="oversee-color-picker" data-default-color="#1e293b">
                        <span class="color-desc">Admin sidebar background</span>
                    </div>
                    <div class="color-item">
                        <label>Navigation Text</label>
                        <input type="text" name="sidebar_text_color" value="<?php echo esc_attr($b['sidebar_text_color']); ?>" class="oversee-color-picker" data-default-color="#94a3b8">
                        <span class="color-desc">Menu item text</span>
                    </div>
                    <div class="color-item">
                        <label>Hover Text</label>
                        <input type="text" name="sidebar_text_hover" value="<?php echo esc_attr($b['sidebar_text_hover']); ?>" class="oversee-color-picker" data-default-color="#ffffff">
                        <span class="color-desc">Text on hover</span>
                    </div>
                    <div class="color-item">
                        <label>Active Text</label>
                        <input type="text" name="sidebar_text_active" value="<?php echo esc_attr($b['sidebar_text_active']); ?>" class="oversee-color-picker" data-default-color="#ffffff">
                        <span class="color-desc">Active menu item</span>
                    </div>
                    <div class="color-item">
                        <label>Section Headings</label>
                        <input type="text" name="sidebar_heading_color" value="<?php echo esc_attr($b['sidebar_heading_color']); ?>" class="oversee-color-picker" data-default-color="#64748b">
                        <span class="color-desc">"Main", "Manage" labels</span>
                    </div>
                </div>
            </div>

            <!-- Live Preview Section -->
            <div class="branding-section">
                <h3><i class="dashicons dashicons-visibility"></i> Live Preview</h3>
                <p class="section-description">See how your branding will look before saving. Changes update in real-time.</p>

                <div class="preview-tabs">
                    <button type="button" class="preview-tab active" data-tab="kb">Knowledge Base</button>
                    <button type="button" class="preview-tab" data-tab="admin">Admin Portal</button>
                    <button type="button" class="preview-tab" data-tab="login">Login Page</button>
                </div>

                <!-- KB Page Preview -->
                <div class="preview-content active" id="preview-kb">
                    <div class="preview-kb-page" id="preview-kb-page">
                        <!-- Hero Section -->
                        <div class="preview-hero" id="preview-hero">
                            <div class="preview-hero-content">
                                <div class="preview-hero-logo" id="preview-hero-logo">
                                    <?php if ($b['logo_dark_url'] ?: $b['logo_url']): ?>
                                        <img src="<?php echo esc_url($b['logo_dark_url'] ?: $b['logo_url']); ?>" alt="Logo">
                                    <?php else: ?>
                                        <span><?php echo esc_html($b['company_name']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <h2 id="preview-hero-title">How can we help?</h2>
                                <div class="preview-search-box">
                                    <input type="text" placeholder="Search for answers..." disabled>
                                    <button class="preview-search-btn" id="preview-search-btn">Search</button>
                                </div>
                            </div>
                        </div>
                        <!-- Content Area -->
                        <div class="preview-kb-content" id="preview-kb-content">
                            <div class="preview-kb-cards">
                                <div class="preview-kb-card" id="preview-kb-card">
                                    <div class="preview-card-icon" id="preview-card-icon"><i class="dashicons dashicons-book"></i></div>
                                    <div class="preview-card-body">
                                        <h4 id="preview-card-title">Getting Started</h4>
                                        <p id="preview-card-text">Learn the basics and get up to speed quickly.</p>
                                    </div>
                                </div>
                                <div class="preview-kb-card" id="preview-kb-card-2">
                                    <div class="preview-card-icon"><i class="dashicons dashicons-admin-tools"></i></div>
                                    <div class="preview-card-body">
                                        <h4>Troubleshooting</h4>
                                        <p>Solutions to common problems and issues.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="preview-status-row">
                                <span class="preview-badge preview-badge-success" id="preview-badge-success">Resolved</span>
                                <span class="preview-badge preview-badge-warning" id="preview-badge-warning">Pending</span>
                                <span class="preview-badge preview-badge-error" id="preview-badge-error">Closed</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Admin Preview -->
                <div class="preview-content" id="preview-admin">
                    <div class="preview-admin-page" id="preview-admin-page">
                        <!-- Sidebar -->
                        <div class="preview-sidebar" id="preview-sidebar">
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
                                    <i class="dashicons dashicons-book"></i> Articles
                                </a>
                            </div>
                        </div>
                        <!-- Main Content -->
                        <div class="preview-admin-content" id="preview-admin-content">
                            <div class="preview-admin-header" id="preview-admin-header">
                                <h3 id="preview-admin-title">Dashboard</h3>
                            </div>
                            <div class="preview-admin-body">
                                <div class="preview-admin-card" id="preview-admin-card">
                                    <div class="preview-admin-card-header">Recent Tickets</div>
                                    <div class="preview-admin-card-body" id="preview-admin-card-body">
                                        <div class="preview-ticket-row">
                                            <span>#1234</span>
                                            <span>Need help with login</span>
                                            <span class="preview-badge preview-badge-warning">Open</span>
                                        </div>
                                        <div class="preview-ticket-row">
                                            <span>#1233</span>
                                            <span>Billing question</span>
                                            <span class="preview-badge preview-badge-success">Resolved</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Login Preview -->
                <div class="preview-content" id="preview-login">
                    <div class="preview-login-page" id="preview-login-page">
                        <div class="preview-login-sidebar" id="preview-login-sidebar">
                            <div class="preview-login-brand">
                                <?php if ($b['logo_dark_url'] ?: $b['logo_url']): ?>
                                    <img src="<?php echo esc_url($b['logo_dark_url'] ?: $b['logo_url']); ?>" alt="Logo">
                                <?php else: ?>
                                    <span><?php echo esc_html($b['company_name']); ?></span>
                                <?php endif; ?>
                            </div>
                            <h3 id="preview-login-title"><?php echo esc_html($b['login_welcome_title']); ?></h3>
                            <p id="preview-login-msg"><?php echo esc_html($b['login_welcome_message']); ?></p>
                        </div>
                        <div class="preview-login-form">
                            <div class="preview-login-card">
                                <h4>Sign In</h4>
                                <div class="preview-form-group">
                                    <label>Email</label>
                                    <input type="text" placeholder="you@example.com" disabled>
                                </div>
                                <div class="preview-form-group">
                                    <label>Password</label>
                                    <input type="password" placeholder="••••••••" disabled>
                                </div>
                                <button class="preview-btn preview-btn-primary preview-btn-full" id="preview-login-btn">Sign In</button>
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
            .oversee-branding-form { max-width: 1000px; }
            .branding-section { background: #fff; padding: 24px; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 24px; }
            .branding-section h3 { margin: 0 0 8px 0; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 10px; font-size: 16px; }
            .branding-section h3 .dashicons { color: #64748b; }
            .branding-section h4 { margin: 0 0 12px 0; font-size: 14px; color: #475569; font-weight: 600; }
            .section-description { margin: 0 0 20px 0; color: #64748b; font-size: 13px; }

            /* Color Grid */
            .color-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px; }
            .color-item { display: flex; flex-direction: column; gap: 6px; }
            .color-item label { font-size: 13px; font-weight: 600; color: #374151; }
            .color-item .color-desc { font-size: 11px; color: #9ca3af; }

            /* Image Preview */
            .image-preview { width: 200px; height: 80px; border: 2px dashed #e2e8f0; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 10px; background: #f8fafc; overflow: hidden; }
            .image-preview.dark-bg { background: #1e293b; border-color: #334155; }
            .image-preview.dark-bg .no-image { color: #94a3b8; }
            .image-preview.small { width: 64px; height: 64px; }
            .image-preview.wide { width: 300px; height: 150px; }
            .image-preview img { max-width: 100%; max-height: 100%; object-fit: contain; }
            .image-preview .no-image { font-size: 12px; color: #94a3b8; text-align: center; }

            /* Preview Tabs */
            .preview-tabs { display: flex; gap: 4px; margin-bottom: 16px; background: #f1f5f9; padding: 4px; border-radius: 8px; }
            .preview-tab { padding: 10px 20px; border: none; background: transparent; border-radius: 6px; font-size: 13px; font-weight: 500; color: #64748b; cursor: pointer; transition: all 0.2s; }
            .preview-tab:hover { color: #1e293b; }
            .preview-tab.active { background: #fff; color: #1e293b; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }

            .preview-content { display: none; }
            .preview-content.active { display: block; }

            /* KB Page Preview */
            .preview-kb-page { border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; }
            .preview-hero { background: <?php echo esc_attr($b['hero_bg_color'] ?? '#1e293b'); ?>; padding: 30px 20px; text-align: center; }
            .preview-hero-content { max-width: 400px; margin: 0 auto; }
            .preview-hero-logo img { max-height: 36px; margin-bottom: 16px; }
            .preview-hero-logo span { color: #fff; font-size: 18px; font-weight: 600; display: block; margin-bottom: 16px; }
            .preview-hero h2 { color: <?php echo esc_attr($b['hero_text_color'] ?? '#ffffff'); ?>; font-size: 20px; font-weight: 600; margin: 0 0 16px 0; }
            .preview-search-box { display: flex; background: rgba(255,255,255,0.1); border-radius: 8px; overflow: hidden; }
            .preview-search-box input { flex: 1; padding: 10px 14px; border: none; background: transparent; color: #fff; font-size: 13px; }
            .preview-search-box input::placeholder { color: rgba(255,255,255,0.6); }
            .preview-search-btn { padding: 10px 16px; background: <?php echo esc_attr($b['primary_color']); ?>; color: #fff; border: none; font-size: 13px; font-weight: 500; }
            .preview-kb-content { background: <?php echo esc_attr($b['public_bg_color'] ?? '#f9fafb'); ?>; padding: 20px; }
            .preview-kb-cards { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 16px; }
            .preview-kb-card { display: flex; gap: 12px; padding: 14px; background: <?php echo esc_attr($b['public_surface_color'] ?? '#ffffff'); ?>; border: 1px solid <?php echo esc_attr($b['public_border_color'] ?? '#e5e7eb'); ?>; border-radius: 8px; }
            .preview-card-icon { width: 36px; height: 36px; background: <?php echo esc_attr($b['primary_color']); ?>15; color: <?php echo esc_attr($b['primary_color']); ?>; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
            .preview-card-icon .dashicons { font-size: 18px; width: 18px; height: 18px; }
            .preview-card-body h4 { margin: 0 0 4px 0; font-size: 13px; font-weight: 600; color: <?php echo esc_attr($b['public_text_color'] ?? '#1f2937'); ?>; }
            .preview-card-body p { margin: 0; font-size: 11px; color: <?php echo esc_attr($b['public_text_muted'] ?? '#6b7280'); ?>; }
            .preview-status-row { display: flex; gap: 8px; }

            /* Admin Page Preview */
            .preview-admin-page { display: flex; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; min-height: 280px; }
            .preview-sidebar { background: <?php echo esc_attr($b['secondary_color']); ?>; width: 160px; flex-shrink: 0; }
            .preview-sidebar-header { padding: 14px; border-bottom: 1px solid rgba(255,255,255,0.1); }
            .preview-sidebar-header img { max-height: 24px; width: auto; }
            .preview-sidebar-header span { color: #fff; font-weight: 600; font-size: 13px; }
            .preview-sidebar-nav { padding: 12px 8px; }
            .preview-sidebar-section { font-size: 9px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: <?php echo esc_attr($b['sidebar_heading_color'] ?: '#64748b'); ?>; padding: 8px 10px 4px; }
            .preview-sidebar-item { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border-radius: 6px; font-size: 12px; color: <?php echo esc_attr($b['sidebar_text_color'] ?: '#94a3b8'); ?>; text-decoration: none; margin-bottom: 2px; }
            .preview-sidebar-item .dashicons { font-size: 14px; width: 14px; height: 14px; }
            .preview-sidebar-item.active { background: <?php echo esc_attr($b['primary_color']); ?>; color: <?php echo esc_attr($b['sidebar_text_active'] ?: '#ffffff'); ?>; }
            .preview-sidebar-item.hover { background: rgba(255,255,255,0.05); color: <?php echo esc_attr($b['sidebar_text_hover'] ?: '#ffffff'); ?>; }
            .preview-admin-content { flex: 1; background: <?php echo esc_attr($b['admin_bg_color'] ?? '#f1f5f9'); ?>; display: flex; flex-direction: column; }
            .preview-admin-header { padding: 14px 16px; background: <?php echo esc_attr($b['admin_surface_color'] ?? '#ffffff'); ?>; border-bottom: 1px solid <?php echo esc_attr($b['admin_border_color'] ?? '#e2e8f0'); ?>; }
            .preview-admin-header h3 { margin: 0; font-size: 15px; color: <?php echo esc_attr($b['admin_text_color'] ?? '#1e293b'); ?>; }
            .preview-admin-body { padding: 16px; flex: 1; }
            .preview-admin-card { background: <?php echo esc_attr($b['admin_surface_color'] ?? '#ffffff'); ?>; border: 1px solid <?php echo esc_attr($b['admin_border_color'] ?? '#e2e8f0'); ?>; border-radius: 8px; overflow: hidden; }
            .preview-admin-card-header { padding: 12px 14px; font-size: 12px; font-weight: 600; color: <?php echo esc_attr($b['admin_text_color'] ?? '#1e293b'); ?>; border-bottom: 1px solid <?php echo esc_attr($b['admin_border_color'] ?? '#e2e8f0'); ?>; }
            .preview-admin-card-body { padding: 8px 0; }
            .preview-ticket-row { display: flex; align-items: center; gap: 12px; padding: 8px 14px; font-size: 11px; color: <?php echo esc_attr($b['admin_text_color'] ?? '#1e293b'); ?>; }
            .preview-ticket-row span:first-child { color: <?php echo esc_attr($b['primary_color']); ?>; font-weight: 600; }
            .preview-ticket-row span:nth-child(2) { flex: 1; }

            /* Login Preview */
            .preview-login-page { display: flex; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; min-height: 280px; }
            .preview-login-sidebar { width: 200px; background: <?php echo esc_attr($b['login_bg_gradient'] ?: 'linear-gradient(135deg, #1e3a5f 0%, #0f172a 100%)'); ?>; padding: 30px 20px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; }
            .preview-login-brand img { max-height: 32px; margin-bottom: 20px; }
            .preview-login-brand span { color: #fff; font-size: 16px; font-weight: 600; display: block; margin-bottom: 20px; }
            .preview-login-sidebar h3 { color: #fff; font-size: 16px; margin: 0 0 8px 0; }
            .preview-login-sidebar p { color: rgba(255,255,255,0.7); font-size: 12px; margin: 0; }
            .preview-login-form { flex: 1; background: #f8fafc; display: flex; align-items: center; justify-content: center; padding: 20px; }
            .preview-login-card { background: #fff; padding: 24px; border-radius: 12px; width: 100%; max-width: 280px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
            .preview-login-card h4 { margin: 0 0 20px 0; font-size: 16px; text-align: center; }
            .preview-form-group { margin-bottom: 14px; }
            .preview-form-group label { display: block; font-size: 12px; font-weight: 500; color: #374151; margin-bottom: 6px; }
            .preview-form-group input { width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 13px; box-sizing: border-box; }

            /* Shared Preview Styles */
            .preview-btn { padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: default; border: none; }
            .preview-btn-primary { background: <?php echo esc_attr($b['primary_color']); ?>; color: #fff; }
            .preview-btn-full { width: 100%; }
            .preview-badge { padding: 4px 10px; border-radius: 999px; font-size: 10px; font-weight: 500; }
            .preview-badge-success { background: <?php echo esc_attr($b['success_color']); ?>20; color: <?php echo esc_attr($b['success_color']); ?>; }
            .preview-badge-warning { background: <?php echo esc_attr($b['warning_color']); ?>20; color: <?php echo esc_attr($b['warning_color']); ?>; }
            .preview-badge-error { background: <?php echo esc_attr($b['error_color']); ?>20; color: <?php echo esc_attr($b['error_color']); ?>; }
        </style>

        <script>
        function toggleLoginBgOptions() {
            var type = document.getElementById('login_bg_type').value;
            document.getElementById('login-bg-color-row').style.display = (type === 'color' || type === 'image') ? '' : 'none';
            document.getElementById('login-bg-gradient-row').style.display = (type === 'gradient') ? '' : 'none';
            document.getElementById('login-bg-image-row').style.display = (type === 'image') ? '' : 'none';
            updatePreview();
        }

        // Live Preview Updates
        document.addEventListener('DOMContentLoaded', function() {
            // Tab switching
            document.querySelectorAll('.preview-tab').forEach(function(tab) {
                tab.addEventListener('click', function() {
                    document.querySelectorAll('.preview-tab').forEach(function(t) { t.classList.remove('active'); });
                    document.querySelectorAll('.preview-content').forEach(function(c) { c.classList.remove('active'); });
                    tab.classList.add('active');
                    document.getElementById('preview-' + tab.dataset.tab).classList.add('active');
                });
            });

            // Comprehensive preview update
            window.updatePreview = function() {
                // Get all color values
                var colors = {
                    primary: getColorValue('primary_color', '#f97316'),
                    accent: getColorValue('accent_color', '#3b82f6'),
                    success: getColorValue('success_color', '#10b981'),
                    warning: getColorValue('warning_color', '#f59e0b'),
                    error: getColorValue('error_color', '#ef4444'),
                    secondary: getColorValue('secondary_color', '#1e293b'),
                    publicBg: getColorValue('public_bg_color', '#f9fafb'),
                    publicSurface: getColorValue('public_surface_color', '#ffffff'),
                    publicText: getColorValue('public_text_color', '#1f2937'),
                    publicMuted: getColorValue('public_text_muted', '#6b7280'),
                    publicBorder: getColorValue('public_border_color', '#e5e7eb'),
                    heroBg: getColorValue('hero_bg_color', '#1e293b'),
                    heroText: getColorValue('hero_text_color', '#ffffff'),
                    adminBg: getColorValue('admin_bg_color', '#f1f5f9'),
                    adminSurface: getColorValue('admin_surface_color', '#ffffff'),
                    adminText: getColorValue('admin_text_color', '#1e293b'),
                    adminBorder: getColorValue('admin_border_color', '#e2e8f0'),
                    sidebarText: getColorValue('sidebar_text_color', '#94a3b8'),
                    sidebarHover: getColorValue('sidebar_text_hover', '#ffffff'),
                    sidebarActive: getColorValue('sidebar_text_active', '#ffffff'),
                    sidebarHeading: getColorValue('sidebar_heading_color', '#64748b'),
                    loginGradient: getTextValue('login_bg_gradient', 'linear-gradient(135deg, #1e3a5f 0%, #0f172a 100%)'),
                    loginBgColor: getColorValue('login_bg_color', '#1e293b')
                };

                // Primary buttons
                document.querySelectorAll('.preview-btn-primary, .preview-search-btn').forEach(function(el) {
                    el.style.backgroundColor = colors.primary;
                });

                // Status badges
                updateBadge('preview-badge-success', colors.success);
                updateBadge('preview-badge-warning', colors.warning);
                updateBadge('preview-badge-error', colors.error);

                // KB Preview - Hero
                var hero = document.getElementById('preview-hero');
                if (hero) hero.style.background = colors.heroBg;
                var heroTitle = document.getElementById('preview-hero-title');
                if (heroTitle) heroTitle.style.color = colors.heroText;

                // KB Preview - Content
                var kbContent = document.getElementById('preview-kb-content');
                if (kbContent) kbContent.style.backgroundColor = colors.publicBg;

                document.querySelectorAll('.preview-kb-card').forEach(function(card) {
                    card.style.backgroundColor = colors.publicSurface;
                    card.style.borderColor = colors.publicBorder;
                });

                document.querySelectorAll('.preview-card-icon').forEach(function(icon) {
                    icon.style.backgroundColor = colors.primary + '15';
                    icon.style.color = colors.primary;
                });

                document.querySelectorAll('.preview-card-body h4').forEach(function(h4) {
                    h4.style.color = colors.publicText;
                });

                document.querySelectorAll('.preview-card-body p').forEach(function(p) {
                    p.style.color = colors.publicMuted;
                });

                // Admin Preview - Sidebar
                var sidebar = document.getElementById('preview-sidebar');
                if (sidebar) sidebar.style.backgroundColor = colors.secondary;

                document.querySelectorAll('.preview-sidebar-section').forEach(function(el) {
                    el.style.color = colors.sidebarHeading;
                });

                document.querySelectorAll('.preview-sidebar-item:not(.active):not(.hover)').forEach(function(el) {
                    el.style.color = colors.sidebarText;
                });

                document.querySelectorAll('.preview-sidebar-item.active').forEach(function(el) {
                    el.style.backgroundColor = colors.primary;
                    el.style.color = colors.sidebarActive;
                });

                document.querySelectorAll('.preview-sidebar-item.hover').forEach(function(el) {
                    el.style.color = colors.sidebarHover;
                });

                // Admin Preview - Content
                var adminContent = document.getElementById('preview-admin-content');
                if (adminContent) adminContent.style.backgroundColor = colors.adminBg;

                var adminHeader = document.getElementById('preview-admin-header');
                if (adminHeader) {
                    adminHeader.style.backgroundColor = colors.adminSurface;
                    adminHeader.style.borderColor = colors.adminBorder;
                }

                var adminTitle = document.getElementById('preview-admin-title');
                if (adminTitle) adminTitle.style.color = colors.adminText;

                var adminCard = document.getElementById('preview-admin-card');
                if (adminCard) {
                    adminCard.style.backgroundColor = colors.adminSurface;
                    adminCard.style.borderColor = colors.adminBorder;
                }

                document.querySelectorAll('.preview-admin-card-header').forEach(function(el) {
                    el.style.color = colors.adminText;
                    el.style.borderColor = colors.adminBorder;
                });

                document.querySelectorAll('.preview-ticket-row').forEach(function(el) {
                    el.style.color = colors.adminText;
                });

                document.querySelectorAll('.preview-ticket-row span:first-child').forEach(function(el) {
                    el.style.color = colors.primary;
                });

                // Login Preview
                var loginSidebar = document.getElementById('preview-login-sidebar');
                if (loginSidebar) {
                    var loginType = document.getElementById('login_bg_type');
                    if (loginType && loginType.value === 'color') {
                        loginSidebar.style.background = colors.loginBgColor;
                    } else {
                        loginSidebar.style.background = colors.loginGradient;
                    }
                }
            };

            function updateBadge(className, color) {
                document.querySelectorAll('.' + className).forEach(function(badge) {
                    badge.style.backgroundColor = color + '20';
                    badge.style.color = color;
                });
            }

            function getColorValue(name, defaultVal) {
                var input = document.querySelector('input[name="' + name + '"]');
                if (!input) return defaultVal;
                return input.value || defaultVal;
            }

            function getTextValue(name, defaultVal) {
                var input = document.querySelector('input[name="' + name + '"]');
                if (!input) return defaultVal;
                return input.value || defaultVal;
            }

            // Watch for color picker changes
            if (typeof jQuery !== 'undefined') {
                jQuery('.oversee-color-picker').on('colorchange', function() {
                    setTimeout(updatePreview, 50);
                });
            }

            // Watch for input changes
            document.querySelectorAll('.oversee-color-picker, input[name="login_bg_gradient"]').forEach(function(input) {
                input.addEventListener('change', updatePreview);
                input.addEventListener('input', updatePreview);
            });

            // Initial update
            setTimeout(updatePreview, 500);
        });
        </script>
        <?php
    }
}
