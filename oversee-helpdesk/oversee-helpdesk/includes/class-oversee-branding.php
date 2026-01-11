<?php
/**
 * Branding Management - Simplified White-Label System
 *
 * Handles comprehensive white-label branding for:
 * - Public support pages (header, footer, colors)
 * - Standalone admin portal (sidebar, colors)
 * - Login page customization
 *
 * @package Oversee_Helpdesk
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Oversee_Branding {

    /**
     * Default branding values - Simplified color system
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
        'logo_dark_url' => '',
        'favicon_url' => '',

        // ===== BRAND COLORS =====
        'primary_color' => '#f97316',     // Primary brand color (buttons, CTAs)
        'secondary_color' => '#1e293b',   // Secondary color (dark areas)
        'accent_color' => '#3b82f6',      // Accent color (highlights, badges, special elements)
        'link_color' => '#2563eb',        // Text link color

        // ===== STATUS COLORS =====
        'success_color' => '#10b981',     // Success states
        'warning_color' => '#f59e0b',     // Warning states
        'error_color' => '#ef4444',       // Error states
        'info_color' => '#3b82f6',        // Info states

        // ===== PUBLIC PAGE COLORS =====
        'public_bg' => '#f9fafb',         // Page background
        'public_surface' => '#ffffff',    // Cards, panels
        'public_surface_hover' => '#f8fafc', // Card hover state
        'public_text' => '#1f2937',       // Primary text
        'public_text_muted' => '#6b7280', // Secondary/muted text
        'public_border' => '#e5e7eb',     // Borders
        'public_input_bg' => '#ffffff',   // Input field backgrounds
        'public_icon_color' => '#f97316', // Icon color on public pages

        // ===== PUBLIC HERO SECTION =====
        'hero_bg' => '#1e293b',           // Hero background
        'hero_text' => '#ffffff',         // Hero text

        // ===== ADMIN PAGE COLORS =====
        'admin_bg' => '#f1f5f9',          // Page background
        'admin_surface' => '#ffffff',     // Cards, panels
        'admin_surface_hover' => '#f8fafc', // Card hover state
        'admin_text' => '#1e293b',        // Primary text
        'admin_text_muted' => '#64748b',  // Secondary/muted text
        'admin_border' => '#e2e8f0',      // Borders
        'admin_input_bg' => '#ffffff',    // Input field backgrounds
        'admin_icon_color' => '#f97316',  // Icon color on admin pages

        // ===== SIDEBAR COLORS =====
        'sidebar_bg' => '#1e293b',        // Sidebar background
        'sidebar_text' => '#94a3b8',      // Nav item text
        'sidebar_text_active' => '#ffffff', // Active/hover text
        'sidebar_heading' => '#64748b',   // Section headings
        'sidebar_icon_color' => '#94a3b8', // Sidebar icon color

        // ===== GLOBAL =====
        'text_on_dark' => '#ffffff',      // Text on dark backgrounds

        // ===== LOGIN PAGE =====
        'login_bg_type' => 'gradient',
        'login_bg_color' => '#1e293b',
        'login_bg_gradient' => 'linear-gradient(135deg, #1e3a5f 0%, #0f172a 100%)',
        'login_bg_image' => '',
        'login_welcome_title' => 'Welcome Back',
        'login_welcome_message' => 'Sign in to access your support dashboard.',
        'login_show_logo' => '1',
        'login_logo_position' => 'both',

        // Footer
        'footer_text' => '',
        'footer_copyright' => '© {year} {company}. All rights reserved.',
        'footer_links' => '',

        // Custom
        'custom_css' => '',
        'custom_js' => '',
    ];

    /**
     * Initialize branding system
     */
    public static function init() {
        // Run migration from old field names to new field names
        self::maybe_migrate_settings();

        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_head', [__CLASS__, 'output_admin_css'], 5);
    }

    /**
     * Migrate old settings field names to new simplified names
     * This runs once when updating from 2.7.2 or earlier to 2.7.3+
     */
    public static function maybe_migrate_settings() {
        // Check if migration already done
        if (get_option('oversee_branding_migrated_274')) {
            return;
        }

        // Map of old field names to new field names (from v2.7.2 and earlier)
        $migrations = [
            // Public page colors
            'oversee_public_bg_color' => 'oversee_public_bg',
            'oversee_public_surface_color' => 'oversee_public_surface',
            'oversee_public_text_color' => 'oversee_public_text',
            'oversee_public_text_muted' => 'oversee_public_text_muted',  // Was NOT _color suffix
            'oversee_public_border_color' => 'oversee_public_border',
            // Hero section
            'oversee_hero_bg_color' => 'oversee_hero_bg',
            'oversee_hero_text_color' => 'oversee_hero_text',
            // Admin page colors
            'oversee_admin_bg_color' => 'oversee_admin_bg',
            'oversee_admin_surface_color' => 'oversee_admin_surface',
            'oversee_admin_text_color' => 'oversee_admin_text',
            'oversee_admin_text_secondary' => 'oversee_admin_text_muted',  // Was text_secondary
            'oversee_admin_text_muted' => 'oversee_admin_text_muted',      // Also check this
            'oversee_admin_border_color' => 'oversee_admin_border',
            // Sidebar - use secondary_color as sidebar_bg (there was no sidebar_bg before)
            'oversee_secondary_color' => 'oversee_sidebar_bg',
            'oversee_sidebar_text_color' => 'oversee_sidebar_text',
            'oversee_sidebar_text_active' => 'oversee_sidebar_text_active',  // Was NOT _color suffix
        ];

        $migrated = false;

        foreach ($migrations as $old_key => $new_key) {
            $old_value = get_option($old_key);
            if ($old_value !== false && $old_value !== '') {
                // Only migrate if new key doesn't already have a value
                $new_value = get_option($new_key);
                if ($new_value === false || $new_value === '') {
                    update_option($new_key, $old_value);
                    $migrated = true;
                }
            }
        }

        // Mark migration as complete
        update_option('oversee_branding_migrated_274', '1');

        if ($migrated) {
            error_log('Oversee Helpdesk: Migrated branding settings to v2.7.3 format');
        }
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
                    'sidebar_bg' => $all['sidebar_bg'],
                    'sidebar_text' => $all['sidebar_text'],
                    'sidebar_text_active' => $all['sidebar_text_active'],
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
            'secondary_color' => 'sanitize_hex_color',
            'success_color' => 'sanitize_hex_color',
            'warning_color' => 'sanitize_hex_color',
            'error_color' => 'sanitize_hex_color',
            'info_color' => 'sanitize_hex_color',
            'public_bg' => 'sanitize_hex_color',
            'public_surface' => 'sanitize_hex_color',
            'public_text' => 'sanitize_hex_color',
            'public_text_muted' => 'sanitize_hex_color',
            'public_border' => 'sanitize_hex_color',
            'hero_bg' => 'sanitize_hex_color',
            'hero_text' => 'sanitize_hex_color',
            'admin_bg' => 'sanitize_hex_color',
            'admin_surface' => 'sanitize_hex_color',
            'admin_text' => 'sanitize_hex_color',
            'admin_text_muted' => 'sanitize_hex_color',
            'admin_border' => 'sanitize_hex_color',
            'sidebar_bg' => 'sanitize_hex_color',
            'sidebar_text' => 'sanitize_hex_color',
            'sidebar_text_active' => 'sanitize_hex_color',
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
    }

    /**
     * Output CSS for WP admin pages
     */
    public static function output_admin_css() {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'oversee') === false) {
            return;
        }
        echo self::get_css_variables();
    }

    /**
     * Get CSS variables block - Comprehensive variable system
     */
    public static function get_css_variables() {
        $b = self::get_all();

        // Primary color variants
        $primary = $b['primary_color'];
        $primary_hover = self::darken_color($primary, 10);
        $primary_light = self::lighten_color($primary, 35);
        $primary_rgb = self::hex_to_rgb($primary);

        // Accent color variants
        $accent = $b['accent_color'];
        $accent_hover = self::darken_color($accent, 10);
        $accent_light = self::lighten_color($accent, 35);

        // Link color variants
        $link = $b['link_color'];
        $link_hover = self::darken_color($link, 15);

        // Status color variants
        $success = $b['success_color'];
        $success_light = self::lighten_color($success, 40);
        $success_dark = self::darken_color($success, 20);

        $warning = $b['warning_color'];
        $warning_light = self::lighten_color($warning, 40);
        $warning_dark = self::darken_color($warning, 20);

        $error = $b['error_color'];
        $error_light = self::lighten_color($error, 40);
        $error_dark = self::darken_color($error, 20);

        $info = $b['info_color'];
        $info_light = self::lighten_color($info, 40);
        $info_dark = self::darken_color($info, 20);

        // Computed variants for backward compatibility
        $admin_border_light = self::lighten_color($b['admin_border'], 5);

        $css = '<style id="oversee-branding-vars">
:root {
    /* ===== BRAND COLORS ===== */
    --primary: ' . esc_attr($primary) . ';
    --primary-hover: ' . esc_attr($primary_hover) . ';
    --primary-light: ' . esc_attr($primary_light) . ';
    --primary-rgb: ' . $primary_rgb . ';
    --secondary: ' . esc_attr($b['secondary_color']) . ';
    --accent: ' . esc_attr($accent) . ';
    --accent-hover: ' . esc_attr($accent_hover) . ';
    --accent-light: ' . esc_attr($accent_light) . ';
    --link: ' . esc_attr($link) . ';
    --link-hover: ' . esc_attr($link_hover) . ';

    /* ===== STATUS COLORS ===== */
    --success: ' . esc_attr($success) . ';
    --success-light: ' . esc_attr($success_light) . ';
    --success-dark: ' . esc_attr($success_dark) . ';
    --warning: ' . esc_attr($warning) . ';
    --warning-light: ' . esc_attr($warning_light) . ';
    --warning-dark: ' . esc_attr($warning_dark) . ';
    --error: ' . esc_attr($error) . ';
    --error-light: ' . esc_attr($error_light) . ';
    --error-dark: ' . esc_attr($error_dark) . ';
    --info: ' . esc_attr($info) . ';
    --info-light: ' . esc_attr($info_light) . ';
    --info-dark: ' . esc_attr($info_dark) . ';

    /* ===== PUBLIC PAGE COLORS ===== */
    --public-bg: ' . esc_attr($b['public_bg']) . ';
    --public-surface: ' . esc_attr($b['public_surface']) . ';
    --public-surface-hover: ' . esc_attr($b['public_surface_hover']) . ';
    --public-text: ' . esc_attr($b['public_text']) . ';
    --public-text-muted: ' . esc_attr($b['public_text_muted']) . ';
    --public-border: ' . esc_attr($b['public_border']) . ';
    --public-input-bg: ' . esc_attr($b['public_input_bg']) . ';
    --public-icon-color: ' . esc_attr($b['public_icon_color']) . ';

    /* ===== PUBLIC HERO ===== */
    --hero-bg: ' . esc_attr($b['hero_bg']) . ';
    --hero-text: ' . esc_attr($b['hero_text']) . ';

    /* ===== ADMIN PAGE COLORS ===== */
    --admin-bg: ' . esc_attr($b['admin_bg']) . ';
    --admin-surface: ' . esc_attr($b['admin_surface']) . ';
    --admin-surface-hover: ' . esc_attr($b['admin_surface_hover']) . ';
    --admin-text: ' . esc_attr($b['admin_text']) . ';
    --admin-text-muted: ' . esc_attr($b['admin_text_muted']) . ';
    --admin-border: ' . esc_attr($b['admin_border']) . ';
    --admin-border-light: ' . esc_attr($admin_border_light) . ';
    --admin-input-bg: ' . esc_attr($b['admin_input_bg']) . ';
    --admin-icon-color: ' . esc_attr($b['admin_icon_color']) . ';

    /* ===== SIDEBAR COLORS ===== */
    --sidebar-bg: ' . esc_attr($b['sidebar_bg']) . ';
    --sidebar-text: ' . esc_attr($b['sidebar_text']) . ';
    --sidebar-text-active: ' . esc_attr($b['sidebar_text_active']) . ';
    --sidebar-heading: ' . esc_attr($b['sidebar_heading']) . ';
    --sidebar-icon-color: ' . esc_attr($b['sidebar_icon_color']) . ';
    --sidebar-hover: rgba(255, 255, 255, 0.05);

    /* ===== GLOBAL ===== */
    --text-on-dark: ' . esc_attr($b['text_on_dark']) . ';
    --text-inverse: ' . esc_attr($b['text_on_dark']) . ';
    --shadow-color: rgba(0, 0, 0, 0.1);
    --shadow-primary: rgba(' . $primary_rgb . ', 0.25);
    --focus-ring: 0 0 0 3px rgba(' . $primary_rgb . ', 0.15);
    --input-border: ' . esc_attr($b['admin_border']) . ';

    /* ===== LEGACY ALIASES (for backward compatibility) ===== */
    --color-primary: var(--primary);
    --color-primary-hover: var(--primary-hover);
    --oversee-primary: var(--primary);
    --oversee-secondary: var(--secondary);
    --oversee-success: var(--success);
    --oversee-warning: var(--warning);
    --oversee-error: var(--error);
    --body-bg: var(--admin-bg);
    --card-bg: var(--admin-surface);
    --border-color: var(--admin-border);
    --text-primary: var(--admin-text);
    --text-secondary: var(--admin-text-muted);
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
     * Render the branding settings form
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
                        <button type="button" class="section-tab active" data-section="general" data-preview="general">
                            <i class="dashicons dashicons-admin-settings"></i> General
                        </button>
                        <button type="button" class="section-tab" data-section="public" data-preview="public">
                            <i class="dashicons dashicons-welcome-widgets-menus"></i> Public Pages
                        </button>
                        <button type="button" class="section-tab" data-section="admin" data-preview="admin">
                            <i class="dashicons dashicons-dashboard"></i> Admin Portal
                        </button>
                        <button type="button" class="section-tab" data-section="login" data-preview="login">
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
                            <p class="group-description">These colors are used across all pages for buttons, links, and accents.</p>
                            <div class="color-row">
                                <div class="color-field">
                                    <label>Primary Color</label>
                                    <input type="text" name="primary_color" value="<?php echo esc_attr($b['primary_color']); ?>" class="oversee-color-picker" data-default-color="#f97316">
                                    <span class="color-hint">Buttons, CTAs, active states</span>
                                </div>
                                <div class="color-field">
                                    <label>Secondary Color</label>
                                    <input type="text" name="secondary_color" value="<?php echo esc_attr($b['secondary_color']); ?>" class="oversee-color-picker" data-default-color="#1e293b">
                                    <span class="color-hint">Dark areas, headers</span>
                                </div>
                            </div>
                            <div class="color-row">
                                <div class="color-field">
                                    <label>Accent Color</label>
                                    <input type="text" name="accent_color" value="<?php echo esc_attr($b['accent_color']); ?>" class="oversee-color-picker" data-default-color="#3b82f6">
                                    <span class="color-hint">Highlights, badges, special elements</span>
                                </div>
                                <div class="color-field">
                                    <label>Link Color</label>
                                    <input type="text" name="link_color" value="<?php echo esc_attr($b['link_color']); ?>" class="oversee-color-picker" data-default-color="#2563eb">
                                    <span class="color-hint">Text links (not buttons)</span>
                                </div>
                            </div>
                            <div class="color-row">
                                <div class="color-field">
                                    <label>Text on Dark</label>
                                    <input type="text" name="text_on_dark" value="<?php echo esc_attr($b['text_on_dark']); ?>" class="oversee-color-picker" data-default-color="#ffffff">
                                    <span class="color-hint">Text color on dark backgrounds</span>
                                </div>
                            </div>
                        </div>

                        <div class="settings-group">
                            <h4>Status Colors</h4>
                            <p class="group-description">Used for alerts, badges, and status indicators.</p>
                            <div class="color-row">
                                <div class="color-field">
                                    <label>Success</label>
                                    <input type="text" name="success_color" value="<?php echo esc_attr($b['success_color']); ?>" class="oversee-color-picker" data-default-color="#10b981">
                                </div>
                                <div class="color-field">
                                    <label>Warning</label>
                                    <input type="text" name="warning_color" value="<?php echo esc_attr($b['warning_color']); ?>" class="oversee-color-picker" data-default-color="#f59e0b">
                                </div>
                            </div>
                            <div class="color-row">
                                <div class="color-field">
                                    <label>Error</label>
                                    <input type="text" name="error_color" value="<?php echo esc_attr($b['error_color']); ?>" class="oversee-color-picker" data-default-color="#ef4444">
                                </div>
                                <div class="color-field">
                                    <label>Info</label>
                                    <input type="text" name="info_color" value="<?php echo esc_attr($b['info_color']); ?>" class="oversee-color-picker" data-default-color="#3b82f6">
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
                        </div>

                        <div class="settings-group">
                            <h4>Custom CSS</h4>
                            <div class="form-row">
                                <textarea name="custom_css" rows="4" class="regular-text code"><?php echo esc_textarea($b['custom_css']); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Public Pages Section -->
                    <div class="section-content" id="section-public">
                        <div class="settings-group">
                            <h4>Hero Section</h4>
                            <p class="group-description">The hero appears at the top of the knowledge base.</p>
                            <div class="color-row">
                                <div class="color-field">
                                    <label>Background</label>
                                    <input type="text" name="hero_bg" value="<?php echo esc_attr($b['hero_bg']); ?>" class="oversee-color-picker" data-default-color="#1e293b">
                                </div>
                                <div class="color-field">
                                    <label>Text Color</label>
                                    <input type="text" name="hero_text" value="<?php echo esc_attr($b['hero_text']); ?>" class="oversee-color-picker" data-default-color="#ffffff">
                                </div>
                            </div>
                        </div>

                        <div class="settings-group">
                            <h4>Page Colors</h4>
                            <p class="group-description">Background and surface colors for public pages.</p>
                            <div class="color-row">
                                <div class="color-field">
                                    <label>Page Background</label>
                                    <input type="text" name="public_bg" value="<?php echo esc_attr($b['public_bg']); ?>" class="oversee-color-picker" data-default-color="#f9fafb">
                                </div>
                                <div class="color-field">
                                    <label>Card Background</label>
                                    <input type="text" name="public_surface" value="<?php echo esc_attr($b['public_surface']); ?>" class="oversee-color-picker" data-default-color="#ffffff">
                                </div>
                            </div>
                            <div class="color-row">
                                <div class="color-field">
                                    <label>Card Hover</label>
                                    <input type="text" name="public_surface_hover" value="<?php echo esc_attr($b['public_surface_hover']); ?>" class="oversee-color-picker" data-default-color="#f8fafc">
                                    <span class="color-hint">Card hover state</span>
                                </div>
                                <div class="color-field">
                                    <label>Input Background</label>
                                    <input type="text" name="public_input_bg" value="<?php echo esc_attr($b['public_input_bg']); ?>" class="oversee-color-picker" data-default-color="#ffffff">
                                    <span class="color-hint">Form fields</span>
                                </div>
                            </div>
                            <div class="color-row">
                                <div class="color-field">
                                    <label>Icon Color</label>
                                    <input type="text" name="public_icon_color" value="<?php echo esc_attr($b['public_icon_color']); ?>" class="oversee-color-picker" data-default-color="#f97316">
                                    <span class="color-hint">Icons on public pages</span>
                                </div>
                            </div>
                        </div>

                        <div class="settings-group">
                            <h4>Text Colors</h4>
                            <div class="color-row">
                                <div class="color-field">
                                    <label>Primary Text</label>
                                    <input type="text" name="public_text" value="<?php echo esc_attr($b['public_text']); ?>" class="oversee-color-picker" data-default-color="#1f2937">
                                </div>
                                <div class="color-field">
                                    <label>Muted Text</label>
                                    <input type="text" name="public_text_muted" value="<?php echo esc_attr($b['public_text_muted']); ?>" class="oversee-color-picker" data-default-color="#6b7280">
                                </div>
                            </div>
                        </div>

                        <div class="settings-group">
                            <h4>Borders</h4>
                            <div class="color-row">
                                <div class="color-field">
                                    <label>Border Color</label>
                                    <input type="text" name="public_border" value="<?php echo esc_attr($b['public_border']); ?>" class="oversee-color-picker" data-default-color="#e5e7eb">
                                    <span class="color-hint">Cards, dividers, inputs</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Admin Portal Section -->
                    <div class="section-content" id="section-admin">
                        <div class="settings-group">
                            <h4>Page Colors</h4>
                            <p class="group-description">Background and surface colors for the admin portal.</p>
                            <div class="color-row">
                                <div class="color-field">
                                    <label>Page Background</label>
                                    <input type="text" name="admin_bg" value="<?php echo esc_attr($b['admin_bg']); ?>" class="oversee-color-picker" data-default-color="#f1f5f9">
                                </div>
                                <div class="color-field">
                                    <label>Card Background</label>
                                    <input type="text" name="admin_surface" value="<?php echo esc_attr($b['admin_surface']); ?>" class="oversee-color-picker" data-default-color="#ffffff">
                                </div>
                            </div>
                            <div class="color-row">
                                <div class="color-field">
                                    <label>Card Hover</label>
                                    <input type="text" name="admin_surface_hover" value="<?php echo esc_attr($b['admin_surface_hover']); ?>" class="oversee-color-picker" data-default-color="#f8fafc">
                                    <span class="color-hint">Card hover state</span>
                                </div>
                                <div class="color-field">
                                    <label>Input Background</label>
                                    <input type="text" name="admin_input_bg" value="<?php echo esc_attr($b['admin_input_bg']); ?>" class="oversee-color-picker" data-default-color="#ffffff">
                                    <span class="color-hint">Form fields</span>
                                </div>
                            </div>
                            <div class="color-row">
                                <div class="color-field">
                                    <label>Icon Color</label>
                                    <input type="text" name="admin_icon_color" value="<?php echo esc_attr($b['admin_icon_color']); ?>" class="oversee-color-picker" data-default-color="#f97316">
                                    <span class="color-hint">Icons on admin pages</span>
                                </div>
                            </div>
                        </div>

                        <div class="settings-group">
                            <h4>Text Colors</h4>
                            <div class="color-row">
                                <div class="color-field">
                                    <label>Primary Text</label>
                                    <input type="text" name="admin_text" value="<?php echo esc_attr($b['admin_text']); ?>" class="oversee-color-picker" data-default-color="#1e293b">
                                </div>
                                <div class="color-field">
                                    <label>Muted Text</label>
                                    <input type="text" name="admin_text_muted" value="<?php echo esc_attr($b['admin_text_muted']); ?>" class="oversee-color-picker" data-default-color="#64748b">
                                </div>
                            </div>
                        </div>

                        <div class="settings-group">
                            <h4>Borders</h4>
                            <div class="color-row">
                                <div class="color-field">
                                    <label>Border Color</label>
                                    <input type="text" name="admin_border" value="<?php echo esc_attr($b['admin_border']); ?>" class="oversee-color-picker" data-default-color="#e2e8f0">
                                    <span class="color-hint">Cards, dividers, inputs</span>
                                </div>
                            </div>
                        </div>

                        <div class="settings-group">
                            <h4>Sidebar</h4>
                            <p class="group-description">Navigation sidebar appearance.</p>
                            <div class="color-row">
                                <div class="color-field">
                                    <label>Background</label>
                                    <input type="text" name="sidebar_bg" value="<?php echo esc_attr($b['sidebar_bg']); ?>" class="oversee-color-picker" data-default-color="#1e293b">
                                </div>
                                <div class="color-field">
                                    <label>Text Color</label>
                                    <input type="text" name="sidebar_text" value="<?php echo esc_attr($b['sidebar_text']); ?>" class="oversee-color-picker" data-default-color="#94a3b8">
                                </div>
                            </div>
                            <div class="color-row">
                                <div class="color-field">
                                    <label>Active/Hover Text</label>
                                    <input type="text" name="sidebar_text_active" value="<?php echo esc_attr($b['sidebar_text_active']); ?>" class="oversee-color-picker" data-default-color="#ffffff">
                                </div>
                                <div class="color-field">
                                    <label>Section Headings</label>
                                    <input type="text" name="sidebar_heading" value="<?php echo esc_attr($b['sidebar_heading']); ?>" class="oversee-color-picker" data-default-color="#64748b">
                                    <span class="color-hint">Nav section titles</span>
                                </div>
                            </div>
                            <div class="color-row">
                                <div class="color-field">
                                    <label>Icon Color</label>
                                    <input type="text" name="sidebar_icon_color" value="<?php echo esc_attr($b['sidebar_icon_color']); ?>" class="oversee-color-picker" data-default-color="#94a3b8">
                                    <span class="color-hint">Sidebar navigation icons</span>
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
                                <select name="login_bg_type" id="login_bg_type">
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
                            <span class="preview-hint">Updates as you change colors</span>
                        </div>

                        <!-- General Preview -->
                        <div class="preview-frame active" id="preview-general">
                            <div class="preview-container">
                                <div class="preview-section-label">Brand Colors & Status</div>
                                <div class="preview-general-demo">
                                    <div class="demo-buttons">
                                        <button class="demo-btn demo-btn-primary">Primary Button</button>
                                        <button class="demo-btn demo-btn-secondary">Secondary</button>
                                    </div>
                                    <div class="demo-badges">
                                        <span class="demo-badge demo-badge-success">Success</span>
                                        <span class="demo-badge demo-badge-warning">Warning</span>
                                        <span class="demo-badge demo-badge-error">Error</span>
                                        <span class="demo-badge demo-badge-info">Info</span>
                                    </div>
                                    <div class="demo-alerts">
                                        <div class="demo-alert demo-alert-success">Success message</div>
                                        <div class="demo-alert demo-alert-warning">Warning message</div>
                                        <div class="demo-alert demo-alert-error">Error message</div>
                                        <div class="demo-alert demo-alert-info">Info message</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Public Preview -->
                        <div class="preview-frame" id="preview-public">
                            <div class="preview-public-page">
                                <div class="preview-hero">
                                    <div class="preview-hero-content">
                                        <div class="preview-hero-logo">
                                            <?php if ($b['logo_dark_url'] ?: $b['logo_url']): ?>
                                                <img src="<?php echo esc_url($b['logo_dark_url'] ?: $b['logo_url']); ?>" alt="Logo">
                                            <?php else: ?>
                                                <span class="logo-text"><?php echo esc_html($b['company_name']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <h2>How can we help?</h2>
                                        <div class="preview-search-box">
                                            <input type="text" placeholder="Search for answers..." disabled>
                                            <button class="preview-search-btn">Search</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-public-content">
                                    <div class="preview-cards">
                                        <div class="preview-card">
                                            <div class="preview-card-icon"><i class="dashicons dashicons-book"></i></div>
                                            <div class="preview-card-body">
                                                <h4>Getting Started</h4>
                                                <p>Learn the basics quickly.</p>
                                            </div>
                                        </div>
                                        <div class="preview-card">
                                            <div class="preview-card-icon"><i class="dashicons dashicons-admin-tools"></i></div>
                                            <div class="preview-card-body">
                                                <h4>Troubleshooting</h4>
                                                <p>Common solutions.</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="preview-divider"></div>
                                    <div class="preview-badges">
                                        <span class="demo-badge demo-badge-success">Resolved</span>
                                        <span class="demo-badge demo-badge-warning">Pending</span>
                                        <span class="demo-badge demo-badge-error">Closed</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Admin Preview -->
                        <div class="preview-frame" id="preview-admin">
                            <div class="preview-admin-page">
                                <div class="preview-sidebar">
                                    <div class="preview-sidebar-header">
                                        <?php if ($b['logo_dark_url'] ?: $b['logo_url']): ?>
                                            <img src="<?php echo esc_url($b['logo_dark_url'] ?: $b['logo_url']); ?>" alt="Logo">
                                        <?php else: ?>
                                            <span class="logo-text"><?php echo esc_html($b['company_name']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="preview-sidebar-divider"></div>
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
                                <div class="preview-admin-content">
                                    <div class="preview-admin-header">
                                        <h3>Dashboard</h3>
                                    </div>
                                    <div class="preview-admin-body">
                                        <div class="preview-admin-card">
                                            <div class="preview-admin-card-header">Recent Tickets</div>
                                            <div class="preview-admin-card-divider"></div>
                                            <div class="preview-admin-card-body">
                                                <div class="preview-ticket-row">
                                                    <span class="ticket-id">#1234</span>
                                                    <span class="ticket-title">Need help with login</span>
                                                    <span class="demo-badge demo-badge-warning">Open</span>
                                                </div>
                                                <div class="preview-ticket-divider"></div>
                                                <div class="preview-ticket-row">
                                                    <span class="ticket-id">#1233</span>
                                                    <span class="ticket-title">Billing question</span>
                                                    <span class="demo-badge demo-badge-success">Resolved</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Login Preview -->
                        <div class="preview-frame" id="preview-login">
                            <div class="preview-login-page">
                                <div class="preview-login-sidebar">
                                    <div class="preview-login-brand">
                                        <?php if ($b['logo_dark_url'] ?: $b['logo_url']): ?>
                                            <img src="<?php echo esc_url($b['logo_dark_url'] ?: $b['logo_url']); ?>" alt="Logo">
                                        <?php else: ?>
                                            <span class="logo-text"><?php echo esc_html($b['company_name']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <h3 class="login-title"><?php echo esc_html($b['login_welcome_title']); ?></h3>
                                    <p class="login-message"><?php echo esc_html($b['login_welcome_message']); ?></p>
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
                                        <button class="demo-btn demo-btn-primary demo-btn-full">Sign In</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
            /* ===== MAIN LAYOUT ===== */
            .oversee-branding-form { max-width: 1400px; }
            .branding-layout { display: flex; gap: 32px; align-items: flex-start; }
            .branding-controls { flex: 0 0 480px; min-width: 0; }
            .branding-preview { flex: 1; min-width: 400px; max-width: 700px; }

            /* ===== SECTION TABS ===== */
            .section-tabs { display: flex; gap: 4px; background: #f1f5f9; padding: 4px; border-radius: 10px; margin-bottom: 24px; }
            .section-tab { flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px; padding: 12px 8px; border: none; background: transparent; border-radius: 8px; font-size: 13px; font-weight: 500; color: #64748b; cursor: pointer; transition: all 0.2s; white-space: nowrap; }
            .section-tab:hover { color: #1e293b; background: rgba(255,255,255,0.5); }
            .section-tab.active { background: #fff; color: #1e293b; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
            .section-tab .dashicons { font-size: 16px; width: 16px; height: 16px; }
            .section-content { display: none; }
            .section-content.active { display: block; }

            /* ===== SETTINGS GROUPS ===== */
            .settings-group { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 16px; }
            .settings-group h4 { margin: 0 0 4px 0; font-size: 14px; font-weight: 600; color: #1e293b; }
            .settings-group .group-description { margin: 0 0 16px 0; font-size: 12px; color: #64748b; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9; }
            .settings-group h4 + .color-row, .settings-group h4 + .form-row { margin-top: 16px; }

            /* ===== FORM ELEMENTS ===== */
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

            /* ===== COLOR FIELDS ===== */
            .color-row { display: flex; gap: 16px; margin-bottom: 14px; }
            .color-row:last-child { margin-bottom: 0; }
            .color-field { flex: 1; }
            .color-field label { display: block; font-size: 12px; font-weight: 500; color: #374151; margin-bottom: 6px; }
            .color-hint { display: block; font-size: 10px; color: #9ca3af; margin-top: 4px; }

            /* ===== LOGO UPLOADS ===== */
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

            /* ===== PREVIEW PANEL ===== */
            .preview-sticky { position: sticky; top: 32px; }
            .preview-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding: 0 4px; }
            .preview-title { font-size: 15px; font-weight: 600; color: #1e293b; }
            .preview-hint { font-size: 12px; color: #64748b; }
            .preview-frame { display: none; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
            .preview-frame.active { display: block; }
            .preview-section-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-bottom: 16px; }

            /* ===== GENERAL PREVIEW ===== */
            .preview-container { padding: 24px; background: #f8fafc; }
            .preview-general-demo { background: #fff; border-radius: 10px; padding: 20px; border: 1px solid #e2e8f0; }
            .demo-buttons { display: flex; gap: 12px; margin-bottom: 20px; }
            .demo-btn { padding: 10px 20px; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: default; border: none; transition: all 0.2s; }
            .demo-btn-primary { background: <?php echo esc_attr($b['primary_color']); ?>; color: #fff; }
            .demo-btn-secondary { background: <?php echo esc_attr($b['secondary_color']); ?>; color: #fff; }
            .demo-btn-full { width: 100%; padding: 14px; }
            .demo-badges { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; }
            .demo-badge { padding: 5px 12px; border-radius: 999px; font-size: 12px; font-weight: 500; }
            .demo-badge-success { background: <?php echo esc_attr($b['success_color']); ?>20; color: <?php echo esc_attr($b['success_color']); ?>; }
            .demo-badge-warning { background: <?php echo esc_attr($b['warning_color']); ?>20; color: <?php echo esc_attr($b['warning_color']); ?>; }
            .demo-badge-error { background: <?php echo esc_attr($b['error_color']); ?>20; color: <?php echo esc_attr($b['error_color']); ?>; }
            .demo-badge-info { background: <?php echo esc_attr($b['info_color']); ?>20; color: <?php echo esc_attr($b['info_color']); ?>; }
            .demo-alerts { display: flex; flex-direction: column; gap: 8px; }
            .demo-alert { padding: 10px 14px; border-radius: 8px; font-size: 12px; font-weight: 500; }
            .demo-alert-success { background: <?php echo esc_attr($b['success_color']); ?>15; color: <?php echo esc_attr($b['success_color']); ?>; border-left: 3px solid <?php echo esc_attr($b['success_color']); ?>; }
            .demo-alert-warning { background: <?php echo esc_attr($b['warning_color']); ?>15; color: <?php echo esc_attr($b['warning_color']); ?>; border-left: 3px solid <?php echo esc_attr($b['warning_color']); ?>; }
            .demo-alert-error { background: <?php echo esc_attr($b['error_color']); ?>15; color: <?php echo esc_attr($b['error_color']); ?>; border-left: 3px solid <?php echo esc_attr($b['error_color']); ?>; }
            .demo-alert-info { background: <?php echo esc_attr($b['info_color']); ?>15; color: <?php echo esc_attr($b['info_color']); ?>; border-left: 3px solid <?php echo esc_attr($b['info_color']); ?>; }

            /* ===== PUBLIC PREVIEW ===== */
            .preview-public-page { background: #fff; }
            .preview-hero { background: <?php echo esc_attr($b['hero_bg']); ?>; padding: 28px 20px; text-align: center; }
            .preview-hero-content { max-width: 100%; }
            .preview-hero-logo img { max-height: 32px; margin-bottom: 14px; }
            .preview-hero-logo .logo-text { color: <?php echo esc_attr($b['hero_text']); ?>; font-size: 16px; font-weight: 600; display: block; margin-bottom: 14px; }
            .preview-hero h2 { color: <?php echo esc_attr($b['hero_text']); ?>; font-size: 18px; font-weight: 600; margin: 0 0 14px 0; }
            .preview-search-box { display: flex; background: rgba(255,255,255,0.1); border-radius: 8px; overflow: hidden; max-width: 360px; margin: 0 auto; }
            .preview-search-box input { flex: 1; padding: 12px 14px; border: none; background: transparent; color: #fff; font-size: 13px; min-width: 0; }
            .preview-search-box input::placeholder { color: rgba(255,255,255,0.6); }
            .preview-search-btn { padding: 12px 18px; background: <?php echo esc_attr($b['primary_color']); ?>; color: #fff; border: none; font-size: 13px; font-weight: 500; white-space: nowrap; }
            .preview-public-content { background: <?php echo esc_attr($b['public_bg']); ?>; padding: 20px; }
            .preview-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px; }
            .preview-card { display: flex; gap: 12px; padding: 14px; background: <?php echo esc_attr($b['public_surface']); ?>; border: 1px solid <?php echo esc_attr($b['public_border']); ?>; border-radius: 10px; }
            .preview-card-icon { width: 36px; height: 36px; background: <?php echo esc_attr($b['primary_color']); ?>15; color: <?php echo esc_attr($b['primary_color']); ?>; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
            .preview-card-icon .dashicons { font-size: 16px; width: 16px; height: 16px; }
            .preview-card-body h4 { margin: 0 0 4px 0; font-size: 13px; font-weight: 600; color: <?php echo esc_attr($b['public_text']); ?>; }
            .preview-card-body p { margin: 0; font-size: 11px; color: <?php echo esc_attr($b['public_text_muted']); ?>; }
            .preview-divider { height: 1px; background: <?php echo esc_attr($b['public_border']); ?>; margin: 16px 0; }
            .preview-badges { display: flex; gap: 8px; }

            /* ===== ADMIN PREVIEW ===== */
            .preview-admin-page { display: flex; min-height: 320px; }
            .preview-sidebar { background: <?php echo esc_attr($b['sidebar_bg']); ?>; width: 140px; flex-shrink: 0; }
            .preview-sidebar-header { padding: 14px; }
            .preview-sidebar-header img { max-height: 22px; }
            .preview-sidebar-header .logo-text { color: #fff; font-weight: 600; font-size: 13px; }
            .preview-sidebar-divider { height: 1px; background: rgba(255,255,255,0.1); margin: 0 14px; }
            .preview-sidebar-nav { padding: 10px 8px; }
            .preview-sidebar-section { font-size: 9px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: <?php echo esc_attr($b['admin_text_muted']); ?>; padding: 8px 10px 6px; }
            .preview-sidebar-item { display: flex; align-items: center; gap: 8px; padding: 9px 10px; border-radius: 6px; font-size: 12px; color: <?php echo esc_attr($b['sidebar_text']); ?>; text-decoration: none; margin-bottom: 3px; }
            .preview-sidebar-item .dashicons { font-size: 14px; width: 14px; height: 14px; }
            .preview-sidebar-item.active { background: <?php echo esc_attr($b['primary_color']); ?>; color: <?php echo esc_attr($b['sidebar_text_active']); ?>; }
            .preview-sidebar-item.hover { background: rgba(255,255,255,0.05); color: <?php echo esc_attr($b['sidebar_text_active']); ?>; }
            .preview-admin-content { flex: 1; background: <?php echo esc_attr($b['admin_bg']); ?>; display: flex; flex-direction: column; }
            .preview-admin-header { padding: 14px 16px; background: <?php echo esc_attr($b['admin_surface']); ?>; border-bottom: 1px solid <?php echo esc_attr($b['admin_border']); ?>; }
            .preview-admin-header h3 { margin: 0; font-size: 15px; color: <?php echo esc_attr($b['admin_text']); ?>; }
            .preview-admin-body { padding: 16px; flex: 1; }
            .preview-admin-card { background: <?php echo esc_attr($b['admin_surface']); ?>; border: 1px solid <?php echo esc_attr($b['admin_border']); ?>; border-radius: 10px; overflow: hidden; }
            .preview-admin-card-header { padding: 12px 14px; font-size: 12px; font-weight: 600; color: <?php echo esc_attr($b['admin_text']); ?>; }
            .preview-admin-card-divider { height: 1px; background: <?php echo esc_attr($b['admin_border']); ?>; }
            .preview-admin-card-body { padding: 6px 0; }
            .preview-ticket-row { display: flex; align-items: center; gap: 8px; padding: 10px 14px; font-size: 11px; color: <?php echo esc_attr($b['admin_text']); ?>; }
            .preview-ticket-row .ticket-id { color: <?php echo esc_attr($b['primary_color']); ?>; font-weight: 600; min-width: 40px; }
            .preview-ticket-row .ticket-title { flex: 1; color: <?php echo esc_attr($b['admin_text_muted']); ?>; }
            .preview-ticket-divider { height: 1px; background: <?php echo esc_attr($b['admin_border']); ?>; margin: 0 14px; }

            /* ===== LOGIN PREVIEW ===== */
            .preview-login-page { display: flex; min-height: 320px; }
            .preview-login-sidebar { width: 200px; background: <?php echo esc_attr($b['login_bg_gradient']); ?>; padding: 28px 18px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; }
            .preview-login-brand img { max-height: 32px; margin-bottom: 18px; }
            .preview-login-brand .logo-text { color: #fff; font-size: 15px; font-weight: 600; display: block; margin-bottom: 18px; }
            .preview-login-sidebar h3 { color: #fff; font-size: 16px; margin: 0 0 8px 0; }
            .preview-login-sidebar p { color: rgba(255,255,255,0.7); font-size: 12px; margin: 0; line-height: 1.5; }
            .preview-login-form { flex: 1; background: #f8fafc; display: flex; align-items: center; justify-content: center; padding: 20px; }
            .preview-login-card { background: #fff; padding: 24px; border-radius: 12px; width: 100%; max-width: 240px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
            .preview-login-card h4 { margin: 0 0 18px 0; font-size: 16px; text-align: center; color: <?php echo esc_attr($b['admin_text']); ?>; }
            .preview-form-group { margin-bottom: 14px; }
            .preview-form-group label { display: block; font-size: 12px; font-weight: 500; color: <?php echo esc_attr($b['admin_text_muted']); ?>; margin-bottom: 5px; }
            .preview-form-group input { width: 100%; padding: 10px 12px; border: 1px solid <?php echo esc_attr($b['admin_border']); ?>; border-radius: 8px; font-size: 12px; box-sizing: border-box; }

            /* ===== RESPONSIVE ===== */
            @media (max-width: 1100px) {
                .branding-layout { flex-direction: column; }
                .branding-controls { flex: none; width: 100%; max-width: 600px; }
                .branding-preview { width: 100%; max-width: 600px; }
                .preview-sticky { position: static; }
            }
        </style>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle login background options
            var loginBgType = document.getElementById('login_bg_type');
            if (loginBgType) {
                loginBgType.addEventListener('change', function() {
                    var type = this.value;
                    document.getElementById('login-bg-color-row').style.display = (type === 'color' || type === 'image') ? '' : 'none';
                    document.getElementById('login-bg-gradient-row').style.display = (type === 'gradient') ? '' : 'none';
                    document.getElementById('login-bg-image-row').style.display = (type === 'image') ? '' : 'none';
                    updatePreview();
                });
            }

            // Section tab switching
            document.querySelectorAll('.section-tab').forEach(function(tab) {
                tab.addEventListener('click', function() {
                    var section = this.dataset.section;
                    var preview = this.dataset.preview;

                    // Update tabs
                    document.querySelectorAll('.section-tab').forEach(function(t) { t.classList.remove('active'); });
                    document.querySelectorAll('.section-content').forEach(function(c) { c.classList.remove('active'); });
                    this.classList.add('active');
                    document.getElementById('section-' + section).classList.add('active');

                    // Update preview frame
                    document.querySelectorAll('.preview-frame').forEach(function(f) { f.classList.remove('active'); });
                    document.getElementById('preview-' + preview).classList.add('active');
                });
            });

            // Live preview update function
            function updatePreview() {
                var colors = {
                    primary: getColorValue('primary_color', '#f97316'),
                    secondary: getColorValue('secondary_color', '#1e293b'),
                    success: getColorValue('success_color', '#10b981'),
                    warning: getColorValue('warning_color', '#f59e0b'),
                    error: getColorValue('error_color', '#ef4444'),
                    info: getColorValue('info_color', '#3b82f6'),
                    publicBg: getColorValue('public_bg', '#f9fafb'),
                    publicSurface: getColorValue('public_surface', '#ffffff'),
                    publicText: getColorValue('public_text', '#1f2937'),
                    publicTextMuted: getColorValue('public_text_muted', '#6b7280'),
                    publicBorder: getColorValue('public_border', '#e5e7eb'),
                    heroBg: getColorValue('hero_bg', '#1e293b'),
                    heroText: getColorValue('hero_text', '#ffffff'),
                    adminBg: getColorValue('admin_bg', '#f1f5f9'),
                    adminSurface: getColorValue('admin_surface', '#ffffff'),
                    adminText: getColorValue('admin_text', '#1e293b'),
                    adminTextMuted: getColorValue('admin_text_muted', '#64748b'),
                    adminBorder: getColorValue('admin_border', '#e2e8f0'),
                    sidebarBg: getColorValue('sidebar_bg', '#1e293b'),
                    sidebarText: getColorValue('sidebar_text', '#94a3b8'),
                    sidebarTextActive: getColorValue('sidebar_text_active', '#ffffff'),
                    loginGradient: getTextValue('login_bg_gradient', 'linear-gradient(135deg, #1e3a5f 0%, #0f172a 100%)'),
                    loginBgColor: getColorValue('login_bg_color', '#1e293b')
                };

                // ===== GENERAL PREVIEW =====
                // Primary buttons
                document.querySelectorAll('.demo-btn-primary, .preview-search-btn').forEach(function(el) {
                    el.style.backgroundColor = colors.primary;
                });
                // Secondary buttons
                document.querySelectorAll('.demo-btn-secondary').forEach(function(el) {
                    el.style.backgroundColor = colors.secondary;
                });
                // Status badges
                updateBadge('demo-badge-success', colors.success);
                updateBadge('demo-badge-warning', colors.warning);
                updateBadge('demo-badge-error', colors.error);
                updateBadge('demo-badge-info', colors.info);
                // Status alerts
                updateAlert('demo-alert-success', colors.success);
                updateAlert('demo-alert-warning', colors.warning);
                updateAlert('demo-alert-error', colors.error);
                updateAlert('demo-alert-info', colors.info);

                // ===== PUBLIC PREVIEW =====
                // Hero
                document.querySelectorAll('.preview-hero').forEach(function(el) {
                    el.style.background = colors.heroBg;
                });
                document.querySelectorAll('.preview-hero h2, .preview-hero-logo .logo-text').forEach(function(el) {
                    el.style.color = colors.heroText;
                });
                // Content area
                document.querySelectorAll('.preview-public-content').forEach(function(el) {
                    el.style.backgroundColor = colors.publicBg;
                });
                // Cards
                document.querySelectorAll('.preview-card').forEach(function(el) {
                    el.style.backgroundColor = colors.publicSurface;
                    el.style.borderColor = colors.publicBorder;
                });
                document.querySelectorAll('.preview-card-icon').forEach(function(el) {
                    el.style.backgroundColor = colors.primary + '15';
                    el.style.color = colors.primary;
                });
                document.querySelectorAll('.preview-card-body h4').forEach(function(el) {
                    el.style.color = colors.publicText;
                });
                document.querySelectorAll('.preview-card-body p').forEach(function(el) {
                    el.style.color = colors.publicTextMuted;
                });
                document.querySelectorAll('.preview-divider').forEach(function(el) {
                    el.style.backgroundColor = colors.publicBorder;
                });

                // ===== ADMIN PREVIEW =====
                // Sidebar
                document.querySelectorAll('.preview-sidebar').forEach(function(el) {
                    el.style.backgroundColor = colors.sidebarBg;
                });
                document.querySelectorAll('.preview-sidebar-item:not(.active):not(.hover)').forEach(function(el) {
                    el.style.color = colors.sidebarText;
                });
                document.querySelectorAll('.preview-sidebar-item.active').forEach(function(el) {
                    el.style.backgroundColor = colors.primary;
                    el.style.color = colors.sidebarTextActive;
                });
                document.querySelectorAll('.preview-sidebar-item.hover').forEach(function(el) {
                    el.style.color = colors.sidebarTextActive;
                });
                // Admin content
                document.querySelectorAll('.preview-admin-content').forEach(function(el) {
                    el.style.backgroundColor = colors.adminBg;
                });
                document.querySelectorAll('.preview-admin-header').forEach(function(el) {
                    el.style.backgroundColor = colors.adminSurface;
                    el.style.borderColor = colors.adminBorder;
                });
                document.querySelectorAll('.preview-admin-header h3').forEach(function(el) {
                    el.style.color = colors.adminText;
                });
                document.querySelectorAll('.preview-admin-card').forEach(function(el) {
                    el.style.backgroundColor = colors.adminSurface;
                    el.style.borderColor = colors.adminBorder;
                });
                document.querySelectorAll('.preview-admin-card-header').forEach(function(el) {
                    el.style.color = colors.adminText;
                });
                document.querySelectorAll('.preview-admin-card-divider, .preview-ticket-divider').forEach(function(el) {
                    el.style.backgroundColor = colors.adminBorder;
                });
                document.querySelectorAll('.preview-ticket-row').forEach(function(el) {
                    el.style.color = colors.adminText;
                });
                document.querySelectorAll('.preview-ticket-row .ticket-id').forEach(function(el) {
                    el.style.color = colors.primary;
                });
                document.querySelectorAll('.preview-ticket-row .ticket-title').forEach(function(el) {
                    el.style.color = colors.adminTextMuted;
                });

                // ===== LOGIN PREVIEW =====
                var loginSidebar = document.querySelector('.preview-login-sidebar');
                if (loginSidebar) {
                    var loginType = document.getElementById('login_bg_type');
                    if (loginType && loginType.value === 'color') {
                        loginSidebar.style.background = colors.loginBgColor;
                    } else {
                        loginSidebar.style.background = colors.loginGradient;
                    }
                }
                document.querySelectorAll('.preview-login-card h4').forEach(function(el) {
                    el.style.color = colors.adminText;
                });
                document.querySelectorAll('.preview-form-group label').forEach(function(el) {
                    el.style.color = colors.adminTextMuted;
                });
                document.querySelectorAll('.preview-form-group input').forEach(function(el) {
                    el.style.borderColor = colors.adminBorder;
                });
            }

            function updateBadge(className, color) {
                document.querySelectorAll('.' + className).forEach(function(badge) {
                    badge.style.backgroundColor = color + '20';
                    badge.style.color = color;
                });
            }

            function updateAlert(className, color) {
                document.querySelectorAll('.' + className).forEach(function(alert) {
                    alert.style.backgroundColor = color + '15';
                    alert.style.color = color;
                    alert.style.borderLeftColor = color;
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
                jQuery('.oversee-color-picker').on('change input colorchange', function() {
                    setTimeout(updatePreview, 50);
                });
            }

            // Watch for input changes
            document.querySelectorAll('.oversee-color-picker, input[name="login_bg_gradient"]').forEach(function(input) {
                input.addEventListener('change', updatePreview);
                input.addEventListener('input', updatePreview);
            });

            // Make updatePreview globally available
            window.updatePreview = updatePreview;

            // Initial update
            setTimeout(updatePreview, 300);
        });
        </script>
        <?php
    }
}
