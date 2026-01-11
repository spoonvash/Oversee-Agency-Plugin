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
        'admin_surface_hover' => '#f8fafc',      // Hover state for surfaces
        'admin_surface_muted' => '#f8fafc',      // Muted surfaces (bulk actions, etc)
        'admin_text_color' => '#1e293b',         // Admin primary text
        'admin_text_secondary' => '#64748b',     // Admin secondary text
        'admin_text_muted' => '#9ca3af',         // Admin muted/disabled text
        'admin_border_color' => '#e2e8f0',       // Admin borders
        'admin_border_light' => '#f1f5f9',       // Admin light borders/dividers
        'admin_input_border' => '#d1d5db',       // Input field borders

        // Sidebar/Admin Nav Colors
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
        $values = [];
        foreach (self::$defaults as $key => $default) {
            $value = get_option('oversee_' . $key, '');
            $values[$key] = ($value === '' || $value === false) ? $default : $value;
        }
        return $values;
    }

    /**
     * Get branding values for a specific context
     */
    public static function get_for_context($context = 'public') {
        $all = self::get_all();

        switch ($context) {
            case 'admin':
                return [
                    'company_name' => $all['company_name'],
                    'logo_url' => $all['logo_dark_url'] ?: $all['logo_url'],
                    'logo_width' => $all['logo_width'],
                    'primary_color' => $all['primary_color'],
                    'secondary_color' => $all['secondary_color'],
                    'sidebar_text_color' => $all['sidebar_text_color'],
                    'sidebar_text_hover' => $all['sidebar_text_hover'],
                    'sidebar_text_active' => $all['sidebar_text_active'],
                    'sidebar_heading_color' => $all['sidebar_heading_color'],
                ];
            case 'login':
                return [
                    'company_name' => $all['company_name'],
                    'tagline' => $all['tagline'],
                    'logo_url' => $all['logo_url'],
                    'logo_dark_url' => $all['logo_dark_url'],
                    'logo_width' => $all['logo_width'],
                    'primary_color' => $all['primary_color'],
                    'login_bg_type' => $all['login_bg_type'],
                    'login_bg_color' => $all['login_bg_color'],
                    'login_bg_gradient' => $all['login_bg_gradient'],
                    'login_bg_image' => $all['login_bg_image'],
                    'login_welcome_title' => $all['login_welcome_title'],
                    'login_welcome_message' => $all['login_welcome_message'],
                    'login_show_logo' => $all['login_show_logo'],
                ];
            default:
                return $all;
        }
    }

    /**
     * Get login page background CSS
     */
    public static function get_login_background_css() {
        $b = self::get_all();

        switch ($b['login_bg_type']) {
            case 'color':
                return 'background-color: ' . esc_attr($b['login_bg_color']) . ';';
            case 'image':
                if ($b['login_bg_image']) {
                    return 'background-image: url(' . esc_url($b['login_bg_image']) . '); background-size: cover; background-position: center;';
                }
                return 'background: ' . esc_attr($b['login_bg_gradient']) . ';';
            case 'gradient':
            default:
                return 'background: ' . esc_attr($b['login_bg_gradient']) . ';';
        }
    }

    /**
     * Get logo HTML
     */
    public static function get_logo_html($id = '', $context = 'public') {
        $b = self::get_all();
        $logo = ($context === 'admin' || $context === 'login-sidebar')
            ? ($b['logo_dark_url'] ?: $b['logo_url'])
            : $b['logo_url'];
        $width = $b['logo_width'];

        if ($logo) {
            return '<img ' . ($id ? 'id="' . esc_attr($id) . '"' : '') . ' src="' . esc_url($logo) . '" alt="' . esc_attr($b['company_name']) . '" style="max-width: ' . intval($width) . 'px; height: auto;">';
        }
        return '<span class="company-name">' . esc_html($b['company_name']) . '</span>';
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
            'admin_surface_hover' => 'sanitize_hex_color',
            'admin_surface_muted' => 'sanitize_hex_color',
            'admin_text_color' => 'sanitize_hex_color',
            'admin_text_secondary' => 'sanitize_hex_color',
            'admin_text_muted' => 'sanitize_hex_color',
            'admin_border_color' => 'sanitize_hex_color',
            'admin_border_light' => 'sanitize_hex_color',
            'admin_input_border' => 'sanitize_hex_color',
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
        if (!empty($data['primary_color'])) {
            $hover = self::darken_color($data['primary_color'], 10);
            update_option('oversee_primary_hover', $hover);
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
    --border-color: var(--color-border);

    /* Hero section */
    --hero-bg: ' . esc_attr($b['hero_bg_color'] ?: '#1e293b') . ';
    --hero-text: ' . esc_attr($b['hero_text_color'] ?: '#ffffff') . ';
    --hero-gradient: linear-gradient(135deg, ' . esc_attr($b['hero_bg_color'] ?: '#1e293b') . ' 0%, ' . esc_attr(self::darken_color($b['hero_bg_color'] ?: '#1e293b', 20)) . ' 100%);

    /* Admin page colors - comprehensive set for admin.css */
    --admin-bg: ' . esc_attr($b['admin_bg_color'] ?: '#f1f5f9') . ';
    --admin-surface: ' . esc_attr($b['admin_surface_color'] ?: '#ffffff') . ';
    --admin-surface-hover: ' . esc_attr($b['admin_surface_hover'] ?: '#f8fafc') . ';
    --admin-surface-muted: ' . esc_attr($b['admin_surface_muted'] ?: '#f8fafc') . ';
    --admin-text: ' . esc_attr($b['admin_text_color'] ?: '#1e293b') . ';
    --admin-text-secondary: ' . esc_attr($b['admin_text_secondary'] ?: '#64748b') . ';
    --admin-text-muted: ' . esc_attr($b['admin_text_muted'] ?: '#9ca3af') . ';
    --admin-border: ' . esc_attr($b['admin_border_color'] ?: '#e2e8f0') . ';
    --admin-border-light: ' . esc_attr($b['admin_border_light'] ?: '#f1f5f9') . ';
    --admin-input-border: ' . esc_attr($b['admin_input_border'] ?: '#d1d5db') . ';

    /* Admin body/card/text aliases for admin.css compatibility */
    --body-bg: ' . esc_attr($b['admin_bg_color'] ?: '#f1f5f9') . ';
    --card-bg: ' . esc_attr($b['admin_surface_color'] ?: '#ffffff') . ';
    --border-color: ' . esc_attr($b['admin_border_color'] ?: '#e2e8f0') . ';
    --border-light: ' . esc_attr($b['admin_border_light'] ?: '#f1f5f9') . ';
    --text-primary: ' . esc_attr($b['admin_text_color'] ?: '#1e293b') . ';
    --text-secondary: ' . esc_attr($b['admin_text_secondary'] ?: '#64748b') . ';
    --text-muted: ' . esc_attr($b['admin_text_muted'] ?: '#9ca3af') . ';
}
</style>';

        if (!empty($b['custom_css'])) {
            $css .= '<style id="oversee-custom-css">' . $b['custom_css'] . '</style>';
        }

        return $css;
    }

    /**
     * Convert hex to RGB string
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
     * Darken a hex color
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
     * Lighten a hex color
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
     * Render the branding settings form (for WP admin Settings page)
     */
    public static function render_settings_form() {
        $b = self::get_all();
        ?>
        <div class="oversee-branding-form">
            <div class="branding-layout">
                <!-- Left Column: Controls -->
                <div class="branding-controls">
                    <!-- Section Tabs -->
                    <div class="section-tabs">
                        <button type="button" class="section-tab active" data-section="general">
                            <i class="dashicons dashicons-admin-settings"></i> General
                        </button>
                        <button type="button" class="section-tab" data-section="kb">
                            <i class="dashicons dashicons-welcome-widgets-menus"></i> Knowledge Base
                        </button>
                        <button type="button" class="section-tab" data-section="admin">
                            <i class="dashicons dashicons-dashboard"></i> Admin Portal
                        </button>
                        <button type="button" class="section-tab" data-section="login">
                            <i class="dashicons dashicons-lock"></i> Login Page
                        </button>
                    </div>

                    <!-- General Section -->
                    <div class="section-content active" id="section-general">
                        <div class="settings-group">
                            <h4>Company Information</h4>
                            <div class="form-row">
                                <label>Company Name</label>
                                <input type="text" name="company_name" value="<?php echo esc_attr($b['company_name']); ?>" class="regular-text">
                            </div>
                            <div class="form-row">
                                <label>Tagline</label>
                                <input type="text" name="tagline" value="<?php echo esc_attr($b['tagline']); ?>" class="regular-text">
                            </div>
                            <div class="form-row-inline">
                                <div class="form-row">
                                    <label>Support Email</label>
                                    <input type="email" name="support_email" value="<?php echo esc_attr($b['support_email']); ?>">
                                </div>
                                <div class="form-row">
                                    <label>Support Phone</label>
                                    <input type="text" name="support_phone" value="<?php echo esc_attr($b['support_phone']); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="settings-group">
                            <h4>Logo & Images</h4>
                            <div class="logo-uploads">
                                <div class="logo-upload-item">
                                    <label>Primary Logo</label>
                                    <input type="hidden" id="logo_url" name="logo_url" value="<?php echo esc_url($b['logo_url']); ?>">
                                    <div id="logo-preview" class="image-preview">
                                        <?php if ($b['logo_url']): ?>
                                            <img src="<?php echo esc_url($b['logo_url']); ?>">
                                        <?php else: ?>
                                            <span class="no-image">No logo</span>
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" class="button button-small" id="upload-logo-btn">Upload</button>
                                    <?php if ($b['logo_url']): ?>
                                        <button type="button" class="button button-small" id="remove-logo-btn">Remove</button>
                                    <?php endif; ?>
                                </div>
                                <div class="logo-upload-item">
                                    <label>Logo for Dark BG</label>
                                    <input type="hidden" id="logo_dark_url" name="logo_dark_url" value="<?php echo esc_url($b['logo_dark_url']); ?>">
                                    <div id="logo-dark-preview" class="image-preview dark-bg">
                                        <?php if ($b['logo_dark_url']): ?>
                                            <img src="<?php echo esc_url($b['logo_dark_url']); ?>">
                                        <?php else: ?>
                                            <span class="no-image">Uses primary</span>
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" class="button button-small" id="upload-logo-dark-btn">Upload</button>
                                    <?php if ($b['logo_dark_url']): ?>
                                        <button type="button" class="button button-small" id="remove-logo-dark-btn">Remove</button>
                                    <?php endif; ?>
                                </div>
                                <div class="logo-upload-item">
                                    <label>Favicon</label>
                                    <input type="hidden" id="favicon_url" name="favicon_url" value="<?php echo esc_url($b['favicon_url']); ?>">
                                    <div id="favicon-preview" class="image-preview small">
                                        <?php if ($b['favicon_url']): ?>
                                            <img src="<?php echo esc_url($b['favicon_url']); ?>">
                                        <?php else: ?>
                                            <span class="no-image">None</span>
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" class="button button-small" id="upload-favicon-btn">Upload</button>
                                </div>
                            </div>
                            <div class="form-row" style="margin-top: 16px;">
                                <label>Logo Width</label>
                                <input type="number" name="logo_width" value="<?php echo esc_attr($b['logo_width']); ?>" min="50" max="400" style="width: 80px;"> px
                            </div>
                        </div>

                        <div class="settings-group">
                            <h4>Brand Colors</h4>
                            <div class="color-row">
                                <div class="color-field">
                                    <label>Primary</label>
                                    <input type="text" name="primary_color" value="<?php echo esc_attr($b['primary_color']); ?>" class="oversee-color-picker" data-default-color="#f97316">
                                    <span class="color-hint">Buttons, links</span>
                                </div>
                                <div class="color-field">
                                    <label>Accent</label>
                                    <input type="text" name="accent_color" value="<?php echo esc_attr($b['accent_color']); ?>" class="oversee-color-picker" data-default-color="#3b82f6">
                                    <span class="color-hint">Info, secondary</span>
                                </div>
                            </div>
                        </div>

                        <div class="settings-group">
                            <h4>Status Colors</h4>
                            <div class="color-row three-col">
                                <div class="color-field">
                                    <label>Success</label>
                                    <input type="text" name="success_color" value="<?php echo esc_attr($b['success_color']); ?>" class="oversee-color-picker" data-default-color="#10b981">
                                </div>
                                <div class="color-field">
                                    <label>Warning</label>
                                    <input type="text" name="warning_color" value="<?php echo esc_attr($b['warning_color']); ?>" class="oversee-color-picker" data-default-color="#f59e0b">
                                </div>
                                <div class="color-field">
                                    <label>Error</label>
                                    <input type="text" name="error_color" value="<?php echo esc_attr($b['error_color']); ?>" class="oversee-color-picker" data-default-color="#ef4444">
                                </div>
                            </div>
                        </div>

                        <div class="settings-group">
                            <h4>Footer</h4>
                            <div class="form-row">
                                <label>Copyright Text</label>
                                <input type="text" name="footer_copyright" value="<?php echo esc_attr($b['footer_copyright']); ?>" class="regular-text">
                                <span class="field-hint">Use {year} and {company} as placeholders</span>
                            </div>
                            <div class="form-row">
                                <label>Additional Footer HTML</label>
                                <textarea name="footer_text" rows="2" class="regular-text"><?php echo esc_textarea($b['footer_text']); ?></textarea>
                            </div>
                        </div>

                        <div class="settings-group">
                            <h4>Custom CSS</h4>
                            <div class="form-row">
                                <textarea name="custom_css" rows="4" class="regular-text code"><?php echo esc_textarea($b['custom_css']); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Knowledge Base Section -->
                    <div class="section-content" id="section-kb">
                        <div class="settings-group">
                            <h4>Hero Section</h4>
                            <div class="color-row">
                                <div class="color-field">
                                    <label>Background</label>
                                    <input type="text" name="hero_bg_color" value="<?php echo esc_attr($b['hero_bg_color']); ?>" class="oversee-color-picker" data-default-color="#1e293b">
                                </div>
                                <div class="color-field">
                                    <label>Text Color</label>
                                    <input type="text" name="hero_text_color" value="<?php echo esc_attr($b['hero_text_color']); ?>" class="oversee-color-picker" data-default-color="#ffffff">
                                </div>
                            </div>
                        </div>

                        <div class="settings-group">
                            <h4>Page Background</h4>
                            <div class="color-row">
                                <div class="color-field">
                                    <label>Page Background</label>
                                    <input type="text" name="public_bg_color" value="<?php echo esc_attr($b['public_bg_color']); ?>" class="oversee-color-picker" data-default-color="#f9fafb">
                                </div>
                                <div class="color-field">
                                    <label>Card Background</label>
                                    <input type="text" name="public_surface_color" value="<?php echo esc_attr($b['public_surface_color']); ?>" class="oversee-color-picker" data-default-color="#ffffff">
                                </div>
                            </div>
                        </div>

                        <div class="settings-group">
                            <h4>Text Colors</h4>
                            <div class="color-row">
                                <div class="color-field">
                                    <label>Primary Text</label>
                                    <input type="text" name="public_text_color" value="<?php echo esc_attr($b['public_text_color']); ?>" class="oversee-color-picker" data-default-color="#1f2937">
                                </div>
                                <div class="color-field">
                                    <label>Muted Text</label>
                                    <input type="text" name="public_text_muted" value="<?php echo esc_attr($b['public_text_muted']); ?>" class="oversee-color-picker" data-default-color="#6b7280">
                                </div>
                            </div>
                        </div>

                        <div class="settings-group">
                            <h4>Borders & Dividers</h4>
                            <div class="color-row">
                                <div class="color-field">
                                    <label>Border Color</label>
                                    <input type="text" name="public_border_color" value="<?php echo esc_attr($b['public_border_color']); ?>" class="oversee-color-picker" data-default-color="#e5e7eb">
                                    <span class="color-hint">Used for borders and dividers</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Admin Portal Section -->
                    <div class="section-content" id="section-admin">
                        <div class="settings-group">
                            <h4>Page Background</h4>
                            <div class="color-row">
                                <div class="color-field">
                                    <label>Page Background</label>
                                    <input type="text" name="admin_bg_color" value="<?php echo esc_attr($b['admin_bg_color']); ?>" class="oversee-color-picker" data-default-color="#f1f5f9">
                                </div>
                                <div class="color-field">
                                    <label>Card Background</label>
                                    <input type="text" name="admin_surface_color" value="<?php echo esc_attr($b['admin_surface_color']); ?>" class="oversee-color-picker" data-default-color="#ffffff">
                                </div>
                            </div>
                        </div>

                        <div class="settings-group">
                            <h4>Surface Variations</h4>
                            <div class="color-row">
                                <div class="color-field">
                                    <label>Hover Background</label>
                                    <input type="text" name="admin_surface_hover" value="<?php echo esc_attr($b['admin_surface_hover']); ?>" class="oversee-color-picker" data-default-color="#f8fafc">
                                    <span class="color-hint">Button/row hover states</span>
                                </div>
                                <div class="color-field">
                                    <label>Muted Background</label>
                                    <input type="text" name="admin_surface_muted" value="<?php echo esc_attr($b['admin_surface_muted']); ?>" class="oversee-color-picker" data-default-color="#f8fafc">
                                    <span class="color-hint">Toolbars, action bars</span>
                                </div>
                            </div>
                        </div>

                        <div class="settings-group">
                            <h4>Text Colors</h4>
                            <div class="color-row">
                                <div class="color-field">
                                    <label>Primary Text</label>
                                    <input type="text" name="admin_text_color" value="<?php echo esc_attr($b['admin_text_color']); ?>" class="oversee-color-picker" data-default-color="#1e293b">
                                    <span class="color-hint">Headings, main text</span>
                                </div>
                                <div class="color-field">
                                    <label>Secondary Text</label>
                                    <input type="text" name="admin_text_secondary" value="<?php echo esc_attr($b['admin_text_secondary']); ?>" class="oversee-color-picker" data-default-color="#64748b">
                                    <span class="color-hint">Labels, descriptions</span>
                                </div>
                            </div>
                            <div class="color-row">
                                <div class="color-field">
                                    <label>Muted Text</label>
                                    <input type="text" name="admin_text_muted" value="<?php echo esc_attr($b['admin_text_muted']); ?>" class="oversee-color-picker" data-default-color="#9ca3af">
                                    <span class="color-hint">Placeholders, hints</span>
                                </div>
                            </div>
                        </div>

                        <div class="settings-group">
                            <h4>Borders & Dividers</h4>
                            <div class="color-row">
                                <div class="color-field">
                                    <label>Border Color</label>
                                    <input type="text" name="admin_border_color" value="<?php echo esc_attr($b['admin_border_color']); ?>" class="oversee-color-picker" data-default-color="#e2e8f0">
                                    <span class="color-hint">Card borders, dividers</span>
                                </div>
                                <div class="color-field">
                                    <label>Light Border</label>
                                    <input type="text" name="admin_border_light" value="<?php echo esc_attr($b['admin_border_light']); ?>" class="oversee-color-picker" data-default-color="#f1f5f9">
                                    <span class="color-hint">Subtle separators</span>
                                </div>
                            </div>
                            <div class="color-row">
                                <div class="color-field">
                                    <label>Input Border</label>
                                    <input type="text" name="admin_input_border" value="<?php echo esc_attr($b['admin_input_border']); ?>" class="oversee-color-picker" data-default-color="#d1d5db">
                                    <span class="color-hint">Form fields, selects</span>
                                </div>
                            </div>
                        </div>

                        <div class="settings-group">
                            <h4>Sidebar</h4>
                            <div class="color-row">
                                <div class="color-field">
                                    <label>Background</label>
                                    <input type="text" name="secondary_color" value="<?php echo esc_attr($b['secondary_color']); ?>" class="oversee-color-picker" data-default-color="#1e293b">
                                </div>
                                <div class="color-field">
                                    <label>Nav Text</label>
                                    <input type="text" name="sidebar_text_color" value="<?php echo esc_attr($b['sidebar_text_color']); ?>" class="oversee-color-picker" data-default-color="#94a3b8">
                                </div>
                            </div>
                            <div class="color-row">
                                <div class="color-field">
                                    <label>Hover Text</label>
                                    <input type="text" name="sidebar_text_hover" value="<?php echo esc_attr($b['sidebar_text_hover']); ?>" class="oversee-color-picker" data-default-color="#ffffff">
                                </div>
                                <div class="color-field">
                                    <label>Active Text</label>
                                    <input type="text" name="sidebar_text_active" value="<?php echo esc_attr($b['sidebar_text_active']); ?>" class="oversee-color-picker" data-default-color="#ffffff">
                                </div>
                            </div>
                            <div class="color-row">
                                <div class="color-field">
                                    <label>Section Headings</label>
                                    <input type="text" name="sidebar_heading_color" value="<?php echo esc_attr($b['sidebar_heading_color']); ?>" class="oversee-color-picker" data-default-color="#64748b">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Login Page Section -->
                    <div class="section-content" id="section-login">
                        <div class="settings-group">
                            <h4>Background</h4>
                            <div class="form-row">
                                <label>Background Style</label>
                                <select name="login_bg_type" id="login_bg_type" onchange="toggleLoginBgOptions(); updatePreview();">
                                    <option value="gradient" <?php selected($b['login_bg_type'], 'gradient'); ?>>Gradient</option>
                                    <option value="color" <?php selected($b['login_bg_type'], 'color'); ?>>Solid Color</option>
                                    <option value="image" <?php selected($b['login_bg_type'], 'image'); ?>>Background Image</option>
                                </select>
                            </div>
                            <div class="form-row" id="login-bg-color-row" style="<?php echo $b['login_bg_type'] === 'gradient' ? 'display:none;' : ''; ?>">
                                <label>Background Color</label>
                                <input type="text" name="login_bg_color" value="<?php echo esc_attr($b['login_bg_color']); ?>" class="oversee-color-picker" data-default-color="#1e293b">
                            </div>
                            <div class="form-row" id="login-bg-gradient-row" style="<?php echo $b['login_bg_type'] !== 'gradient' ? 'display:none;' : ''; ?>">
                                <label>Gradient CSS</label>
                                <input type="text" name="login_bg_gradient" value="<?php echo esc_attr($b['login_bg_gradient']); ?>" class="regular-text">
                                <span class="field-hint">e.g., linear-gradient(135deg, #1e3a5f 0%, #0f172a 100%)</span>
                            </div>
                            <div class="form-row" id="login-bg-image-row" style="<?php echo $b['login_bg_type'] !== 'image' ? 'display:none;' : ''; ?>">
                                <label>Background Image</label>
                                <input type="hidden" id="login_bg_image" name="login_bg_image" value="<?php echo esc_url($b['login_bg_image']); ?>">
                                <div id="login-bg-preview" class="image-preview wide">
                                    <?php if ($b['login_bg_image']): ?>
                                        <img src="<?php echo esc_url($b['login_bg_image']); ?>">
                                    <?php else: ?>
                                        <span class="no-image">No image</span>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="button button-small" id="upload-login-bg-btn">Upload</button>
                            </div>
                        </div>

                        <div class="settings-group">
                            <h4>Welcome Message</h4>
                            <div class="form-row">
                                <label>Title</label>
                                <input type="text" name="login_welcome_title" value="<?php echo esc_attr($b['login_welcome_title']); ?>" class="regular-text">
                            </div>
                            <div class="form-row">
                                <label>Message</label>
                                <textarea name="login_welcome_message" rows="2" class="regular-text"><?php echo esc_textarea($b['login_welcome_message']); ?></textarea>
                            </div>
                            <div class="form-row">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="login_show_logo" value="1" <?php checked($b['login_show_logo'], '1'); ?>>
                                    Show logo on login page
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Live Preview -->
                <div class="branding-preview">
                    <div class="preview-sticky">
                        <div class="preview-header">
                            <span class="preview-title">Live Preview</span>
                            <span class="preview-hint">Changes update in real-time</span>
                        </div>

                        <!-- KB Preview -->
                        <div class="preview-frame active" id="preview-frame-kb">
                            <div class="preview-kb-page" id="preview-kb-page">
                                <div class="preview-hero" id="preview-hero">
                                    <div class="preview-hero-content">
                                        <div class="preview-hero-logo">
                                            <?php if ($b['logo_dark_url'] ?: $b['logo_url']): ?>
                                                <img src="<?php echo esc_url($b['logo_dark_url'] ?: $b['logo_url']); ?>" alt="Logo">
                                            <?php else: ?>
                                                <span><?php echo esc_html($b['company_name']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <h2 id="preview-hero-title">How can we help?</h2>
                                        <div class="preview-search-box">
                                            <input type="text" placeholder="Search for answers..." disabled>
                                            <button class="preview-search-btn">Search</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-kb-content" id="preview-kb-content">
                                    <div class="preview-kb-cards">
                                        <div class="preview-kb-card">
                                            <div class="preview-card-icon"><i class="dashicons dashicons-book"></i></div>
                                            <div class="preview-card-body">
                                                <h4>Getting Started</h4>
                                                <p>Learn the basics quickly.</p>
                                            </div>
                                        </div>
                                        <div class="preview-kb-card">
                                            <div class="preview-card-icon"><i class="dashicons dashicons-admin-tools"></i></div>
                                            <div class="preview-card-body">
                                                <h4>Troubleshooting</h4>
                                                <p>Common solutions.</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="preview-divider" id="preview-kb-divider"></div>
                                    <div class="preview-status-row">
                                        <span class="preview-badge preview-badge-success">Resolved</span>
                                        <span class="preview-badge preview-badge-warning">Pending</span>
                                        <span class="preview-badge preview-badge-error">Closed</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Admin Preview -->
                        <div class="preview-frame" id="preview-frame-admin">
                            <div class="preview-admin-page" id="preview-admin-page">
                                <div class="preview-sidebar" id="preview-sidebar">
                                    <div class="preview-sidebar-header">
                                        <?php if ($b['logo_dark_url'] ?: $b['logo_url']): ?>
                                            <img src="<?php echo esc_url($b['logo_dark_url'] ?: $b['logo_url']); ?>" alt="Logo">
                                        <?php else: ?>
                                            <span><?php echo esc_html($b['company_name']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="preview-sidebar-divider" id="preview-sidebar-divider"></div>
                                    <div class="preview-sidebar-nav">
                                        <div class="preview-sidebar-section">Main</div>
                                        <a href="#" class="preview-sidebar-item active" onclick="return false;">
                                            <i class="dashicons dashicons-dashboard"></i> Dashboard
                                        </a>
                                        <a href="#" class="preview-sidebar-item" onclick="return false;">
                                            <i class="dashicons dashicons-tickets-alt"></i> Tickets
                                        </a>
                                        <a href="#" class="preview-sidebar-item hover" onclick="return false;">
                                            <i class="dashicons dashicons-book"></i> Articles
                                        </a>
                                    </div>
                                </div>
                                <div class="preview-admin-content" id="preview-admin-content">
                                    <div class="preview-admin-header" id="preview-admin-header">
                                        <h3>Dashboard</h3>
                                    </div>
                                    <div class="preview-admin-body">
                                        <div class="preview-admin-card" id="preview-admin-card">
                                            <div class="preview-admin-card-header">Recent Tickets</div>
                                            <div class="preview-admin-card-divider" id="preview-admin-card-divider"></div>
                                            <div class="preview-admin-card-body">
                                                <div class="preview-ticket-row">
                                                    <span class="ticket-id">#1234</span>
                                                    <span>Need help with login</span>
                                                    <span class="preview-badge preview-badge-warning">Open</span>
                                                </div>
                                                <div class="preview-ticket-row-divider" id="preview-ticket-divider"></div>
                                                <div class="preview-ticket-row">
                                                    <span class="ticket-id">#1233</span>
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
                        <div class="preview-frame" id="preview-frame-login">
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
                                            <input type="password" placeholder="********" disabled>
                                        </div>
                                        <button class="preview-btn preview-btn-primary preview-btn-full">Sign In</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- General Preview (shows KB by default) -->
                        <div class="preview-frame" id="preview-frame-general">
                            <div class="preview-general-page">
                                <div class="preview-general-header">
                                    <div class="preview-general-logo">
                                        <?php if ($b['logo_url']): ?>
                                            <img src="<?php echo esc_url($b['logo_url']); ?>" alt="Logo">
                                        <?php else: ?>
                                            <span><?php echo esc_html($b['company_name']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="preview-general-nav">
                                        <span>Home</span>
                                        <span>Articles</span>
                                        <button class="preview-btn preview-btn-primary">Submit Ticket</button>
                                    </div>
                                </div>
                                <div class="preview-general-content">
                                    <div class="preview-general-card">
                                        <h4>Sample Content</h4>
                                        <p>This shows your brand colors in action.</p>
                                        <div class="preview-status-row" style="margin-top: 12px;">
                                            <span class="preview-badge preview-badge-success">Success</span>
                                            <span class="preview-badge preview-badge-warning">Warning</span>
                                            <span class="preview-badge preview-badge-error">Error</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
            /* Main Layout */
            .oversee-branding-form { max-width: 1600px; }
            .branding-layout { display: flex; gap: 40px; align-items: flex-start; }
            .branding-controls { flex: 1; min-width: 0; max-width: 580px; }
            .branding-preview { width: 560px; flex-shrink: 0; }

            /* Section Tabs */
            .section-tabs { display: flex; gap: 4px; background: #f1f5f9; padding: 4px; border-radius: 10px; margin-bottom: 24px; }
            .section-tab { flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px; padding: 12px 8px; border: none; background: transparent; border-radius: 8px; font-size: 13px; font-weight: 500; color: #64748b; cursor: pointer; transition: all 0.2s; white-space: nowrap; }
            .section-tab:hover { color: #1e293b; background: rgba(255,255,255,0.5); }
            .section-tab.active { background: #fff; color: #1e293b; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
            .section-tab .dashicons { font-size: 16px; width: 16px; height: 16px; }

            .section-content { display: none; }
            .section-content.active { display: block; }

            /* Settings Groups */
            .settings-group { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 16px; }
            .settings-group h4 { margin: 0 0 16px 0; font-size: 14px; font-weight: 600; color: #1e293b; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9; }

            /* Form Rows */
            .form-row { margin-bottom: 14px; }
            .form-row:last-child { margin-bottom: 0; }
            .form-row label { display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 6px; }
            .form-row input[type="text"], .form-row input[type="email"], .form-row input[type="number"], .form-row textarea, .form-row select { width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; }
            .form-row input:focus, .form-row textarea:focus, .form-row select:focus { outline: none; border-color: <?php echo esc_attr($b['primary_color']); ?>; box-shadow: 0 0 0 3px <?php echo esc_attr($b['primary_color']); ?>15; }
            .form-row-inline { display: flex; gap: 16px; }
            .form-row-inline .form-row { flex: 1; }
            .field-hint { display: block; font-size: 11px; color: #9ca3af; margin-top: 4px; }
            .checkbox-label { display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; }
            .checkbox-label input { width: auto; }

            /* Color Fields */
            .color-row { display: flex; gap: 16px; margin-bottom: 14px; }
            .color-row:last-child { margin-bottom: 0; }
            .color-row.three-col .color-field { flex: 1; }
            .color-field { flex: 1; }
            .color-field label { display: block; font-size: 12px; font-weight: 500; color: #374151; margin-bottom: 6px; }
            .color-hint { display: block; font-size: 10px; color: #9ca3af; margin-top: 4px; }

            /* Logo Uploads */
            .logo-uploads { display: flex; gap: 16px; flex-wrap: wrap; }
            .logo-upload-item { flex: 1; min-width: 120px; }
            .logo-upload-item label { display: block; font-size: 12px; font-weight: 500; color: #374151; margin-bottom: 8px; }
            .image-preview { width: 100%; height: 60px; border: 2px dashed #e2e8f0; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 8px; background: #f8fafc; overflow: hidden; }
            .image-preview.dark-bg { background: #1e293b; border-color: #334155; }
            .image-preview.dark-bg .no-image { color: #94a3b8; }
            .image-preview.small { height: 48px; }
            .image-preview.wide { height: 80px; }
            .image-preview img { max-width: 100%; max-height: 100%; object-fit: contain; }
            .image-preview .no-image { font-size: 11px; color: #94a3b8; }

            /* Preview Panel */
            .preview-sticky { position: sticky; top: 32px; max-height: calc(100vh - 64px); overflow-y: auto; }
            .preview-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding: 0 4px; }
            .preview-title { font-size: 15px; font-weight: 600; color: #1e293b; }
            .preview-hint { font-size: 12px; color: #64748b; }

            .preview-frame { display: none; border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 8px 24px rgba(0,0,0,0.12); }
            .preview-frame.active { display: block; }

            /* KB Preview */
            .preview-kb-page { background: #fff; }
            .preview-hero { background: <?php echo esc_attr($b['hero_bg_color'] ?? '#1e293b'); ?>; padding: 32px 20px; text-align: center; }
            .preview-hero-content { max-width: 380px; margin: 0 auto; }
            .preview-hero-logo img { max-height: 36px; margin-bottom: 16px; }
            .preview-hero-logo span { color: #fff; font-size: 16px; font-weight: 600; display: block; margin-bottom: 16px; }
            .preview-hero h2 { color: <?php echo esc_attr($b['hero_text_color'] ?? '#ffffff'); ?>; font-size: 20px; font-weight: 600; margin: 0 0 16px 0; }
            .preview-search-box { display: flex; background: rgba(255,255,255,0.1); border-radius: 8px; overflow: hidden; }
            .preview-search-box input { flex: 1; padding: 12px 14px; border: none; background: transparent; color: #fff; font-size: 14px; }
            .preview-search-box input::placeholder { color: rgba(255,255,255,0.6); }
            .preview-search-btn { padding: 12px 18px; background: <?php echo esc_attr($b['primary_color']); ?>; color: #fff; border: none; font-size: 14px; font-weight: 500; }
            .preview-kb-content { background: <?php echo esc_attr($b['public_bg_color'] ?? '#f9fafb'); ?>; padding: 20px; }
            .preview-kb-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px; }
            .preview-kb-card { display: flex; gap: 14px; padding: 16px; background: <?php echo esc_attr($b['public_surface_color'] ?? '#ffffff'); ?>; border: 1px solid <?php echo esc_attr($b['public_border_color'] ?? '#e5e7eb'); ?>; border-radius: 10px; }
            .preview-card-icon { width: 36px; height: 36px; background: <?php echo esc_attr($b['primary_color']); ?>15; color: <?php echo esc_attr($b['primary_color']); ?>; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
            .preview-card-icon .dashicons { font-size: 18px; width: 18px; height: 18px; }
            .preview-card-body h4 { margin: 0 0 4px 0; font-size: 14px; font-weight: 600; color: <?php echo esc_attr($b['public_text_color'] ?? '#1f2937'); ?>; }
            .preview-card-body p { margin: 0; font-size: 12px; color: <?php echo esc_attr($b['public_text_muted'] ?? '#6b7280'); ?>; }
            .preview-divider { height: 1px; background: <?php echo esc_attr($b['public_border_color'] ?? '#e5e7eb'); ?>; margin: 16px 0; }
            .preview-status-row { display: flex; gap: 8px; }

            /* Admin Preview */
            .preview-admin-page { display: flex; min-height: 340px; }
            .preview-sidebar { background: <?php echo esc_attr($b['secondary_color']); ?>; width: 160px; flex-shrink: 0; }
            .preview-sidebar-header { padding: 16px; }
            .preview-sidebar-header img { max-height: 24px; }
            .preview-sidebar-header span { color: #fff; font-weight: 600; font-size: 14px; }
            .preview-sidebar-divider { height: 1px; background: rgba(255,255,255,0.1); margin: 0 16px; }
            .preview-sidebar-nav { padding: 12px 8px; }
            .preview-sidebar-section { font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: <?php echo esc_attr($b['sidebar_heading_color'] ?: '#64748b'); ?>; padding: 8px 10px 6px; }
            .preview-sidebar-item { display: flex; align-items: center; gap: 8px; padding: 10px 12px; border-radius: 6px; font-size: 13px; color: <?php echo esc_attr($b['sidebar_text_color'] ?: '#94a3b8'); ?>; text-decoration: none; margin-bottom: 4px; }
            .preview-sidebar-item .dashicons { font-size: 14px; width: 14px; height: 14px; }
            .preview-sidebar-item.active { background: <?php echo esc_attr($b['primary_color']); ?>; color: <?php echo esc_attr($b['sidebar_text_active'] ?: '#ffffff'); ?>; }
            .preview-sidebar-item.hover { background: rgba(255,255,255,0.05); color: <?php echo esc_attr($b['sidebar_text_hover'] ?: '#ffffff'); ?>; }
            .preview-admin-content { flex: 1; background: <?php echo esc_attr($b['admin_bg_color'] ?? '#f1f5f9'); ?>; display: flex; flex-direction: column; }
            .preview-admin-header { padding: 16px 18px; background: <?php echo esc_attr($b['admin_surface_color'] ?? '#ffffff'); ?>; border-bottom: 1px solid <?php echo esc_attr($b['admin_border_color'] ?? '#e2e8f0'); ?>; }
            .preview-admin-header h3 { margin: 0; font-size: 16px; color: <?php echo esc_attr($b['admin_text_color'] ?? '#1e293b'); ?>; }
            .preview-admin-body { padding: 18px; flex: 1; }
            .preview-admin-card { background: <?php echo esc_attr($b['admin_surface_color'] ?? '#ffffff'); ?>; border: 1px solid <?php echo esc_attr($b['admin_border_color'] ?? '#e2e8f0'); ?>; border-radius: 10px; overflow: hidden; }
            .preview-admin-card-header { padding: 14px 16px; font-size: 13px; font-weight: 600; color: <?php echo esc_attr($b['admin_text_color'] ?? '#1e293b'); ?>; }
            .preview-admin-card-divider { height: 1px; background: <?php echo esc_attr($b['admin_border_color'] ?? '#e2e8f0'); ?>; }
            .preview-admin-card-body { padding: 8px 0; }
            .preview-ticket-row { display: flex; align-items: center; gap: 10px; padding: 10px 16px; font-size: 12px; color: <?php echo esc_attr($b['admin_text_color'] ?? '#1e293b'); ?>; }
            .preview-ticket-row .ticket-id { color: <?php echo esc_attr($b['primary_color']); ?>; font-weight: 600; min-width: 50px; }
            .preview-ticket-row span:nth-child(2) { flex: 1; }
            .preview-ticket-row-divider { height: 1px; background: <?php echo esc_attr($b['admin_border_color'] ?? '#e2e8f0'); ?>; margin: 0 16px; }

            /* Login Preview */
            .preview-login-page { display: flex; min-height: 340px; }
            .preview-login-sidebar { width: 220px; background: <?php echo esc_attr($b['login_bg_gradient'] ?: 'linear-gradient(135deg, #1e3a5f 0%, #0f172a 100%)'); ?>; padding: 32px 20px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; }
            .preview-login-brand img { max-height: 36px; margin-bottom: 20px; }
            .preview-login-brand span { color: #fff; font-size: 16px; font-weight: 600; display: block; margin-bottom: 20px; }
            .preview-login-sidebar h3 { color: #fff; font-size: 18px; margin: 0 0 8px 0; }
            .preview-login-sidebar p { color: rgba(255,255,255,0.7); font-size: 13px; margin: 0; line-height: 1.5; }
            .preview-login-form { flex: 1; background: #f8fafc; display: flex; align-items: center; justify-content: center; padding: 24px; }
            .preview-login-card { background: #fff; padding: 28px; border-radius: 12px; width: 100%; max-width: 260px; box-shadow: 0 8px 24px rgba(0,0,0,0.1); }
            .preview-login-card h4 { margin: 0 0 20px 0; font-size: 18px; text-align: center; }
            .preview-form-group { margin-bottom: 16px; }
            .preview-form-group label { display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 6px; }
            .preview-form-group input { width: 100%; padding: 12px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; box-sizing: border-box; }

            /* General Preview */
            .preview-general-page { background: <?php echo esc_attr($b['public_bg_color'] ?? '#f9fafb'); ?>; min-height: 300px; }
            .preview-general-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; background: <?php echo esc_attr($b['public_surface_color'] ?? '#ffffff'); ?>; border-bottom: 1px solid <?php echo esc_attr($b['public_border_color'] ?? '#e5e7eb'); ?>; }
            .preview-general-logo img { max-height: 28px; }
            .preview-general-logo span { font-weight: 600; font-size: 16px; color: <?php echo esc_attr($b['public_text_color'] ?? '#1f2937'); ?>; }
            .preview-general-nav { display: flex; align-items: center; gap: 16px; font-size: 14px; color: <?php echo esc_attr($b['public_text_muted'] ?? '#6b7280'); ?>; }
            .preview-general-content { padding: 20px; }
            .preview-general-card { background: <?php echo esc_attr($b['public_surface_color'] ?? '#ffffff'); ?>; border: 1px solid <?php echo esc_attr($b['public_border_color'] ?? '#e5e7eb'); ?>; border-radius: 10px; padding: 20px; }
            .preview-general-card h4 { margin: 0 0 10px 0; font-size: 16px; color: <?php echo esc_attr($b['public_text_color'] ?? '#1f2937'); ?>; }
            .preview-general-card p { margin: 0; font-size: 14px; color: <?php echo esc_attr($b['public_text_muted'] ?? '#6b7280'); ?>; }

            /* Shared Preview Styles */
            .preview-btn { padding: 10px 16px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: default; border: none; }
            .preview-btn-primary { background: <?php echo esc_attr($b['primary_color']); ?>; color: #fff; }
            .preview-btn-full { width: 100%; padding: 14px; }
            .preview-badge { padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 500; }
            .preview-badge-success { background: <?php echo esc_attr($b['success_color']); ?>20; color: <?php echo esc_attr($b['success_color']); ?>; }
            .preview-badge-warning { background: <?php echo esc_attr($b['warning_color']); ?>20; color: <?php echo esc_attr($b['warning_color']); ?>; }
            .preview-badge-error { background: <?php echo esc_attr($b['error_color']); ?>20; color: <?php echo esc_attr($b['error_color']); ?>; }

            /* Responsive */
            @media (max-width: 1200px) {
                .branding-layout { flex-direction: column; }
                .branding-controls { max-width: none; }
                .branding-preview { width: 100%; max-width: 600px; }
                .preview-sticky { position: static; }
            }
        </style>

        <script>
        function toggleLoginBgOptions() {
            var type = document.getElementById('login_bg_type').value;
            document.getElementById('login-bg-color-row').style.display = (type === 'color' || type === 'image') ? '' : 'none';
            document.getElementById('login-bg-gradient-row').style.display = (type === 'gradient') ? '' : 'none';
            document.getElementById('login-bg-image-row').style.display = (type === 'image') ? '' : 'none';
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Section tab switching
            document.querySelectorAll('.section-tab').forEach(function(tab) {
                tab.addEventListener('click', function() {
                    var section = tab.dataset.section;

                    // Update tabs
                    document.querySelectorAll('.section-tab').forEach(function(t) { t.classList.remove('active'); });
                    document.querySelectorAll('.section-content').forEach(function(c) { c.classList.remove('active'); });
                    tab.classList.add('active');
                    document.getElementById('section-' + section).classList.add('active');

                    // Update preview frame
                    document.querySelectorAll('.preview-frame').forEach(function(f) { f.classList.remove('active'); });
                    var previewMap = { general: 'general', kb: 'kb', admin: 'admin', login: 'login' };
                    document.getElementById('preview-frame-' + previewMap[section]).classList.add('active');
                });
            });

            // Live preview update
            window.updatePreview = function() {
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

                // KB Preview
                var hero = document.getElementById('preview-hero');
                if (hero) hero.style.background = colors.heroBg;
                var heroTitle = document.getElementById('preview-hero-title');
                if (heroTitle) heroTitle.style.color = colors.heroText;

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

                // KB Dividers
                document.querySelectorAll('.preview-divider, #preview-kb-divider').forEach(function(el) {
                    el.style.backgroundColor = colors.publicBorder;
                });

                // Admin Preview
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

                var adminContent = document.getElementById('preview-admin-content');
                if (adminContent) adminContent.style.backgroundColor = colors.adminBg;

                var adminHeader = document.getElementById('preview-admin-header');
                if (adminHeader) {
                    adminHeader.style.backgroundColor = colors.adminSurface;
                    adminHeader.style.borderColor = colors.adminBorder;
                }

                document.querySelectorAll('.preview-admin-header h3').forEach(function(el) {
                    el.style.color = colors.adminText;
                });

                var adminCard = document.getElementById('preview-admin-card');
                if (adminCard) {
                    adminCard.style.backgroundColor = colors.adminSurface;
                    adminCard.style.borderColor = colors.adminBorder;
                }

                document.querySelectorAll('.preview-admin-card-header').forEach(function(el) {
                    el.style.color = colors.adminText;
                });

                // Admin Dividers
                document.querySelectorAll('.preview-admin-card-divider, .preview-ticket-row-divider, #preview-sidebar-divider').forEach(function(el) {
                    if (el.id === 'preview-sidebar-divider') {
                        el.style.backgroundColor = 'rgba(255,255,255,0.1)';
                    } else {
                        el.style.backgroundColor = colors.adminBorder;
                    }
                });

                document.querySelectorAll('.preview-ticket-row').forEach(function(el) {
                    el.style.color = colors.adminText;
                });

                document.querySelectorAll('.preview-ticket-row .ticket-id').forEach(function(el) {
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

                // General Preview
                document.querySelectorAll('.preview-general-header').forEach(function(el) {
                    el.style.backgroundColor = colors.publicSurface;
                    el.style.borderColor = colors.publicBorder;
                });
                document.querySelectorAll('.preview-general-page').forEach(function(el) {
                    el.style.backgroundColor = colors.publicBg;
                });
                document.querySelectorAll('.preview-general-card').forEach(function(el) {
                    el.style.backgroundColor = colors.publicSurface;
                    el.style.borderColor = colors.publicBorder;
                });
                document.querySelectorAll('.preview-general-card h4').forEach(function(el) {
                    el.style.color = colors.publicText;
                });
                document.querySelectorAll('.preview-general-card p').forEach(function(el) {
                    el.style.color = colors.publicMuted;
                });
                document.querySelectorAll('.preview-general-logo span').forEach(function(el) {
                    el.style.color = colors.publicText;
                });
                document.querySelectorAll('.preview-general-nav').forEach(function(el) {
                    el.style.color = colors.publicMuted;
                });
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
