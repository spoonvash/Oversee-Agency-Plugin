<?php
/**
 * License Management - Improved
 * 
 * Handles license validation with OverseeAgency.com license server.
 * Following EDD Software Licensing best practices with:
 * - Intelligent caching (24hr valid, 1hr invalid, 15min error)
 * - 7-day grace period when server unreachable
 * - Site migration detection
 * - Detailed error codes
 * - Unlicensed redirect URL
 * 
 * @package Oversee_Helpdesk
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Oversee_License {
    
    /**
     * License server URL
     */
    const LICENSE_SERVER = 'https://overseeagency.com';
    
    /**
     * Option keys - separate storage for each data type
     */
    const OPT_KEY = 'oversee_license_key';
    const OPT_STATUS = 'oversee_license_status';
    const OPT_DATA = 'oversee_license_data';
    const OPT_LAST_CHECK = 'oversee_license_last_check';
    const OPT_LAST_SUCCESS = 'oversee_license_last_success';
    const OPT_LICENSED_URL = 'oversee_license_url';
    const OPT_REDIRECT_URL = 'oversee_unlicensed_redirect';
    const OPT_DEMO_MODE = 'oversee_demo_mode';
    const OPT_SHOW_LICENSE_PAGE = 'oversee_show_license_page';
    const TRANSIENT = 'oversee_license_cache';
    
    /**
     * Cache durations (seconds)
     */
    const CACHE_VALID = 86400;      // 24 hours for valid license
    const CACHE_INVALID = 3600;     // 1 hour for invalid license  
    const CACHE_ERROR = 900;        // 15 minutes on server error
    const GRACE_PERIOD = 604800;    // 7 days grace period
    
    /**
     * Error codes with messages
     */
    private static $error_messages = [
        'no_key' => 'Please enter your license key to activate.',
        'invalid_key' => 'License key not found. Please check your key.',
        'expired' => 'Your license has expired. Please renew your subscription.',
        'cancelled' => 'Your subscription has been cancelled.',
        'payment_failed' => 'Payment failed. Please update your payment method.',
        'site_mismatch' => 'This license is active on a different website.',
        'not_activated' => 'License has not been activated. Please activate it first.',
        'limit_reached' => 'Activation limit reached for this license.',
        'connection_error' => 'Cannot connect to license server.',
        'server_error' => 'License server error. Please try again later.',
        'site_migrated' => 'Site URL has changed. Please reactivate your license.',
    ];
    
    /**
     * Initialize license system
     */
    public static function init() {
        // Schedule periodic license check (twice daily)
        if (!wp_next_scheduled('oversee_license_check_cron')) {
            wp_schedule_event(time(), 'twicedaily', 'oversee_license_check_cron');
        }
        add_action('oversee_license_check_cron', [__CLASS__, 'scheduled_check']);
        
        // Check for site migration on admin init
        add_action('admin_init', [__CLASS__, 'check_site_migration']);
        
        // AJAX handlers
        add_action('wp_ajax_oversee_activate_license', [__CLASS__, 'ajax_activate']);
        add_action('wp_ajax_oversee_deactivate_license', [__CLASS__, 'ajax_deactivate']);
        add_action('wp_ajax_oversee_check_license', [__CLASS__, 'ajax_check']);
    }
    
    /**
     * Called on plugin activation - force fresh verification
     */
    public static function on_plugin_activate() {
        // Clear ALL cached license data
        delete_transient(self::TRANSIENT);
        delete_option(self::OPT_STATUS);
        delete_option(self::OPT_DATA);
        delete_option(self::OPT_LAST_CHECK);
        // Don't clear OPT_KEY, OPT_LICENSED_URL, OPT_LAST_SUCCESS, OPT_REDIRECT_URL
        
        // If license key exists, immediately verify with server
        $license_key = get_option(self::OPT_KEY, '');
        if (!empty($license_key)) {
            self::verify_with_server($license_key, true);
        }
    }
    
    /**
     * Called on plugin deactivation - clear cache but keep key
     */
    public static function on_plugin_deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('oversee_license_check_cron');
        
        // Clear cached data
        delete_transient(self::TRANSIENT);
        delete_option(self::OPT_STATUS);
        delete_option(self::OPT_DATA);
        delete_option(self::OPT_LAST_CHECK);
        
        // Keep: OPT_KEY, OPT_LICENSED_URL, OPT_LAST_SUCCESS, OPT_REDIRECT_URL
    }
    
    /**
     * Check if site URL has changed (migration detection)
     */
    public static function check_site_migration() {
        $stored_url = get_option(self::OPT_LICENSED_URL, '');
        $current_url = home_url();
        
        if (empty($stored_url)) {
            return; // No stored URL yet
        }
        
        // Normalize URLs for comparison
        $stored_host = self::normalize_domain($stored_url);
        $current_host = self::normalize_domain($current_url);
        
        if ($stored_host !== $current_host) {
            // Site has migrated - clear license status
            delete_transient(self::TRANSIENT);
            update_option(self::OPT_STATUS, 'site_migrated');
            update_option(self::OPT_DATA, [
                'valid' => false,
                'status' => 'site_migrated',
                'message' => sprintf(
                    'Site URL changed from %s to %s. Please reactivate your license.',
                    $stored_host,
                    $current_host
                ),
                'old_url' => $stored_host,
                'new_url' => $current_host,
            ]);
        }
    }
    
    /**
     * Normalize domain for comparison (strips www, protocol, trailing slash)
     */
    private static function normalize_domain($url) {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            $host = $url;
        }
        return strtolower(preg_replace('/^www\./', '', $host));
    }
    
    /**
     * Check if plugin is currently licensed
     * This is the main function called throughout the plugin
     */
    public static function is_licensed() {
        $license_key = get_option(self::OPT_KEY, '');
        
        if (empty($license_key)) {
            return false;
        }
        
        // Check transient cache first
        $cached = get_transient(self::TRANSIENT);
        if ($cached !== false) {
            return $cached === 'valid';
        }
        
        // No cache - verify with server
        return self::verify_with_server($license_key) === true;
    }
    
    /**
     * Get detailed license status
     */
    public static function get_status() {
        $license_key = get_option(self::OPT_KEY, '');
        
        if (empty($license_key)) {
            return [
                'valid' => false,
                'status' => 'no_key',
                'message' => self::$error_messages['no_key'],
                'renew_url' => self::LICENSE_SERVER . '/pricing',
            ];
        }
        
        // Check for site migration status
        $status = get_option(self::OPT_STATUS, '');
        if ($status === 'site_migrated') {
            return get_option(self::OPT_DATA, [
                'valid' => false,
                'status' => 'site_migrated',
                'message' => self::$error_messages['site_migrated'],
            ]);
        }
        
        // Try to get cached data
        $cached_data = get_option(self::OPT_DATA, []);
        if (!empty($cached_data) && isset($cached_data['status'])) {
            // Check grace period for connection errors
            if (in_array($cached_data['status'], ['connection_error', 'server_error', 'grace_period'])) {
                $last_success = get_option(self::OPT_LAST_SUCCESS, 0);
                if ($last_success && (time() - $last_success) < self::GRACE_PERIOD) {
                    // Within grace period - treat as valid
                    $cached_data['in_grace_period'] = true;
                    $cached_data['grace_expires'] = $last_success + self::GRACE_PERIOD;
                    $cached_data['valid'] = true;
                    return $cached_data;
                }
            }
            return $cached_data;
        }
        
        // Fresh check needed
        self::verify_with_server($license_key);
        return get_option(self::OPT_DATA, [
            'valid' => false,
            'status' => 'unknown',
            'message' => 'Unable to verify license.',
        ]);
    }
    
    /**
     * Verify license with remote server
     */
    private static function verify_with_server($license_key, $force = false) {
        $current_url = home_url();
        
        $response = wp_remote_post(self::LICENSE_SERVER . '/wp-json/oversee-license/v1/validate', [
            'timeout' => 15,
            'sslverify' => true,
            'body' => [
                'license_key' => $license_key,
                'site_url' => $current_url,
                'site_name' => get_bloginfo('name'),
                'plugin_version' => defined('OVERSEE_VERSION') ? OVERSEE_VERSION : '1.0.0',
            ],
        ]);
        
        // Update last check time
        update_option(self::OPT_LAST_CHECK, time());
        
        // Handle connection errors
        if (is_wp_error($response)) {
            return self::handle_connection_error($force);
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // Handle server errors (5xx)
        if ($code >= 500) {
            return self::handle_server_error($body);
        }
        
        // Handle successful validation
        if ($code === 200 && !empty($body['valid'])) {
            return self::handle_valid_license($body, $current_url);
        }
        
        // Handle invalid license responses
        return self::handle_invalid_license($body, $code);
    }
    
    /**
     * Handle connection error (network issues)
     */
    private static function handle_connection_error($force) {
        $last_success = get_option(self::OPT_LAST_SUCCESS, 0);
        $last_status = get_option(self::OPT_STATUS, '');
        
        // Grace period: If previously valid and within 7 days, treat as valid
        if (!$force && $last_status === 'valid' && $last_success) {
            $time_since_success = time() - $last_success;
            
            if ($time_since_success < self::GRACE_PERIOD) {
                // Within grace period - allow continued use
                update_option(self::OPT_STATUS, 'grace_period');
                update_option(self::OPT_DATA, [
                    'valid' => true,
                    'status' => 'grace_period',
                    'message' => 'License server unreachable. Operating in grace period.',
                    'grace_expires' => $last_success + self::GRACE_PERIOD,
                    'days_remaining' => ceil((self::GRACE_PERIOD - $time_since_success) / 86400),
                ]);
                set_transient(self::TRANSIENT, 'valid', self::CACHE_ERROR);
                return true;
            }
        }
        
        // Grace period expired or forced check
        update_option(self::OPT_STATUS, 'connection_error');
        update_option(self::OPT_DATA, [
            'valid' => false,
            'status' => 'connection_error',
            'message' => self::$error_messages['connection_error'],
            'last_attempt' => current_time('mysql'),
        ]);
        set_transient(self::TRANSIENT, 'invalid', self::CACHE_ERROR);
        return false;
    }
    
    /**
     * Handle server error (5xx responses)
     */
    private static function handle_server_error($body) {
        $last_success = get_option(self::OPT_LAST_SUCCESS, 0);
        
        // Apply grace period logic
        if ($last_success && (time() - $last_success) < self::GRACE_PERIOD) {
            update_option(self::OPT_STATUS, 'grace_period');
            set_transient(self::TRANSIENT, 'valid', self::CACHE_ERROR);
            return true;
        }
        
        update_option(self::OPT_STATUS, 'server_error');
        update_option(self::OPT_DATA, [
            'valid' => false,
            'status' => 'server_error',
            'message' => self::$error_messages['server_error'],
        ]);
        set_transient(self::TRANSIENT, 'invalid', self::CACHE_ERROR);
        return false;
    }
    
    /**
     * Handle valid license response
     */
    private static function handle_valid_license($body, $current_url) {
        update_option(self::OPT_STATUS, 'valid');
        update_option(self::OPT_LAST_SUCCESS, time());
        update_option(self::OPT_LICENSED_URL, $current_url);
        update_option(self::OPT_DATA, [
            'valid' => true,
            'status' => 'active',
            'message' => 'License is valid and active.',
            'plan' => $body['plan'] ?? 'standard',
            'expires_at' => $body['expires_at'] ?? null,
            'customer_email' => $body['customer_email'] ?? '',
            'last_check' => current_time('mysql'),
        ]);
        set_transient(self::TRANSIENT, 'valid', self::CACHE_VALID);
        return true;
    }
    
    /**
     * Handle invalid license responses
     */
    private static function handle_invalid_license($body, $code) {
        $error_code = $body['error'] ?? $body['status'] ?? 'invalid';
        $message = $body['message'] ?? self::$error_messages[$error_code] ?? 'License validation failed.';
        
        update_option(self::OPT_STATUS, $error_code);
        update_option(self::OPT_DATA, [
            'valid' => false,
            'status' => $error_code,
            'message' => $message,
            'registered_site' => $body['registered_site'] ?? '',
            'renew_url' => $body['renew_url'] ?? self::LICENSE_SERVER . '/pricing',
            'last_check' => current_time('mysql'),
        ]);
        set_transient(self::TRANSIENT, 'invalid', self::CACHE_INVALID);
        return false;
    }
    
    /**
     * Activate license on this site
     */
    public static function activate($license_key) {
        if (empty($license_key)) {
            return [
                'success' => false,
                'error' => 'no_key',
                'message' => 'Please enter a license key.',
            ];
        }
        
        // Clean the key
        $license_key = strtoupper(trim($license_key));
        
        $response = wp_remote_post(self::LICENSE_SERVER . '/wp-json/oversee-license/v1/activate', [
            'timeout' => 15,
            'sslverify' => true,
            'body' => [
                'license_key' => $license_key,
                'site_url' => home_url(),
                'site_name' => get_bloginfo('name'),
                'plugin_version' => defined('OVERSEE_VERSION') ? OVERSEE_VERSION : '1.0.0',
            ],
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'connection_error',
                'message' => 'Cannot connect to license server: ' . $response->get_error_message(),
            ];
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($code === 200 && !empty($body['success'])) {
            // Save license data
            update_option(self::OPT_KEY, $license_key);
            update_option(self::OPT_STATUS, 'valid');
            update_option(self::OPT_LAST_SUCCESS, time());
            update_option(self::OPT_LICENSED_URL, home_url());
            update_option(self::OPT_DATA, [
                'valid' => true,
                'status' => 'active',
                'message' => 'License activated successfully.',
                'plan' => $body['plan'] ?? 'standard',
                'expires_at' => $body['expires_at'] ?? null,
                'last_check' => current_time('mysql'),
            ]);
            set_transient(self::TRANSIENT, 'valid', self::CACHE_VALID);
            
            return [
                'success' => true,
                'message' => $body['message'] ?? 'License activated successfully!',
                'plan' => $body['plan'] ?? 'standard',
            ];
        }
        
        return [
            'success' => false,
            'error' => $body['error'] ?? 'activation_failed',
            'message' => $body['message'] ?? 'License activation failed.',
            'registered_site' => $body['registered_site'] ?? '',
        ];
    }
    
    /**
     * Deactivate license
     */
    public static function deactivate() {
        $license_key = get_option(self::OPT_KEY, '');
        
        if (!empty($license_key)) {
            // Notify server
            wp_remote_post(self::LICENSE_SERVER . '/wp-json/oversee-license/v1/deactivate', [
                'timeout' => 10,
                'sslverify' => true,
                'body' => [
                    'license_key' => $license_key,
                    'site_url' => home_url(),
                ],
            ]);
        }
        
        // Clear all local license data
        delete_option(self::OPT_KEY);
        delete_option(self::OPT_STATUS);
        delete_option(self::OPT_DATA);
        delete_option(self::OPT_LAST_CHECK);
        delete_option(self::OPT_LAST_SUCCESS);
        delete_option(self::OPT_LICENSED_URL);
        delete_transient(self::TRANSIENT);
        
        return ['success' => true, 'message' => 'License deactivated successfully.'];
    }
    
    /**
     * Force recheck license (clears cache)
     */
    public static function force_check() {
        delete_transient(self::TRANSIENT);
        $license_key = get_option(self::OPT_KEY, '');
        if (!empty($license_key)) {
            return self::verify_with_server($license_key, true);
        }
        return false;
    }
    
    /**
     * Scheduled cron check
     */
    public static function scheduled_check() {
        $license_key = get_option(self::OPT_KEY, '');
        if (!empty($license_key)) {
            delete_transient(self::TRANSIENT);
            self::verify_with_server($license_key, false);
        }
    }
    
    /**
     * Get redirect URL for unlicensed state
     */
    public static function get_unlicensed_redirect_url() {
        $url = get_option(self::OPT_REDIRECT_URL, '');
        if (empty($url)) {
            return self::LICENSE_SERVER . '/pricing';
        }
        return $url;
    }
    
    /**
     * Set redirect URL for unlicensed state
     */
    public static function set_unlicensed_redirect_url($url) {
        if (empty($url)) {
            delete_option(self::OPT_REDIRECT_URL);
        } else {
            update_option(self::OPT_REDIRECT_URL, esc_url_raw($url));
        }
    }
    
    /**
     * Check if demo mode is enabled
     * Demo mode allows KB access without license (for preview/demo purposes)
     */
    public static function is_demo_mode() {
        return (bool) get_option(self::OPT_DEMO_MODE, false);
    }
    
    /**
     * Set demo mode
     */
    public static function set_demo_mode($enabled) {
        update_option(self::OPT_DEMO_MODE, (bool) $enabled);
    }
    
    /**
     * Check if should show internal license required page
     */
    public static function show_license_page() {
        return (bool) get_option(self::OPT_SHOW_LICENSE_PAGE, false);
    }
    
    /**
     * Set whether to show internal license page
     */
    public static function set_show_license_page($enabled) {
        update_option(self::OPT_SHOW_LICENSE_PAGE, (bool) $enabled);
    }
    
    /**
     * Check if a specific template is allowed in demo mode
     */
    public static function is_demo_allowed_template($template) {
        if (!self::is_demo_mode()) {
            return false;
        }
        
        // Templates allowed in demo mode (KB only)
        $allowed = [
            'pages/home',
            'pages/kb-article',
            'pages/kb-category',
            'pages/kb-search',
        ];
        
        return in_array($template, $allowed, true);
    }
    
    /**
     * AJAX: Activate license
     */
    public static function ajax_activate() {
        check_ajax_referer('oversee_license_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }
        
        $license_key = sanitize_text_field($_POST['license_key'] ?? '');
        $result = self::activate($license_key);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Deactivate license
     */
    public static function ajax_deactivate() {
        check_ajax_referer('oversee_license_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }
        
        $result = self::deactivate();
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Force check license
     */
    public static function ajax_check() {
        check_ajax_referer('oversee_license_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }
        
        $result = self::force_check();
        $status = self::get_status();
        
        wp_send_json_success([
            'valid' => $result,
            'status' => $status,
        ]);
    }
    
    /**
     * Render the license admin page
     */
    public static function render_license_page() {
        // Handle form submissions
        if (isset($_POST['oversee_activate_license']) && check_admin_referer('oversee_license_action')) {
            $license_key = sanitize_text_field($_POST['license_key'] ?? '');
            $result = self::activate($license_key);
            
            if ($result['success']) {
                echo '<div class="notice notice-success"><p>' . esc_html($result['message']) . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html($result['message']) . '</p></div>';
            }
        }
        
        if (isset($_POST['oversee_deactivate_license']) && check_admin_referer('oversee_license_action')) {
            $result = self::deactivate();
            echo '<div class="notice notice-success"><p>' . esc_html($result['message']) . '</p></div>';
        }
        
        if (isset($_POST['oversee_recheck_license']) && check_admin_referer('oversee_license_action')) {
            self::force_check();
            echo '<div class="notice notice-info"><p>License status refreshed.</p></div>';
        }
        
        if (isset($_POST['oversee_save_redirect']) && check_admin_referer('oversee_license_action')) {
            $redirect_url = esc_url_raw($_POST['unlicensed_redirect_url'] ?? '');
            self::set_unlicensed_redirect_url($redirect_url);
            echo '<div class="notice notice-success"><p>Redirect URL saved.</p></div>';
        }
        
        if (isset($_POST['oversee_save_enforcement']) && check_admin_referer('oversee_license_action')) {
            self::set_demo_mode(isset($_POST['demo_mode']));
            self::set_show_license_page(isset($_POST['show_license_page']));
            echo '<div class="notice notice-success"><p>Enforcement settings saved.</p></div>';
        }
        
        $license_key = get_option(self::OPT_KEY, '');
        $status = self::get_status();
        $is_valid = !empty($status['valid']);
        $redirect_url = get_option(self::OPT_REDIRECT_URL, '');
        ?>
        <div class="oversee-license-page">
            <?php if (empty($license_key)): ?>
                <div class="oversee-license-box">
                    <h2>🔐 Activate Your License</h2>
                    <p>Enter your license key to unlock all features of Oversee Helpdesk.</p>
                    
                    <form method="post" class="oversee-license-form">
                        <?php wp_nonce_field('oversee_license_action'); ?>
                        <div class="license-input-wrap">
                            <input type="text" name="license_key" 
                                   placeholder="OVS-XXXX-XXXX-XXXX-XXXX" 
                                   class="license-input" required
                                   pattern="[A-Za-z0-9\-]+"
                                   style="text-transform: uppercase;">
                            <button type="submit" name="oversee_activate_license" class="button button-primary">
                                Activate License
                            </button>
                        </div>
                    </form>
                    
                    <p class="license-help">
                        Don't have a license? <a href="<?php echo esc_url(self::LICENSE_SERVER . '/pricing'); ?>" target="_blank">Purchase one here →</a>
                    </p>
                </div>
                
            <?php else: ?>
                <div class="oversee-license-box <?php echo $is_valid ? 'license-valid' : 'license-invalid'; ?>">
                    <h2><?php echo $is_valid ? '✓ License Active' : '⚠ License Issue'; ?></h2>
                    
                    <table class="license-details">
                        <tr>
                            <th>License Key</th>
                            <td><code><?php echo esc_html(self::mask_key($license_key)); ?></code></td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td>
                                <?php if ($is_valid): ?>
                                    <?php if (!empty($status['in_grace_period'])): ?>
                                        <span class="status-badge status-warning">Grace Period</span>
                                        <small>(expires in <?php echo esc_html(human_time_diff(time(), $status['grace_expires'])); ?>)</small>
                                    <?php else: ?>
                                        <span class="status-badge status-active">Active</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="status-badge status-error"><?php echo esc_html(ucwords(str_replace('_', ' ', $status['status']))); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if (!$is_valid && !empty($status['message'])): ?>
                        <tr>
                            <th>Issue</th>
                            <td class="error-message"><?php echo esc_html($status['message']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($status['registered_site']) && $status['status'] === 'site_mismatch'): ?>
                        <tr>
                            <th>Registered On</th>
                            <td><code><?php echo esc_html($status['registered_site']); ?></code></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($status['plan'])): ?>
                        <tr>
                            <th>Plan</th>
                            <td><?php echo esc_html(ucfirst($status['plan'])); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($status['expires_at'])): ?>
                        <tr>
                            <th>Expires</th>
                            <td>
                                <?php 
                                $exp = strtotime($status['expires_at']);
                                echo esc_html(date('F j, Y', $exp));
                                if ($exp < strtotime('+30 days')) {
                                    echo ' <span class="status-badge status-warning">Expiring Soon</span>';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($status['last_check'])): ?>
                        <tr>
                            <th>Last Verified</th>
                            <td><?php echo esc_html($status['last_check']); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                    
                    <form method="post" class="license-actions">
                        <?php wp_nonce_field('oversee_license_action'); ?>
                        <button type="submit" name="oversee_recheck_license" class="button">
                            <span class="dashicons dashicons-update" style="vertical-align: middle;"></span> Refresh Status
                        </button>
                        <button type="submit" name="oversee_deactivate_license" class="button" 
                                onclick="return confirm('Deactivate this license? You will need to reactivate to use the plugin.');">
                            Deactivate License
                        </button>
                        
                        <?php if (!$is_valid && !empty($status['renew_url'])): ?>
                            <a href="<?php echo esc_url($status['renew_url']); ?>" class="button button-primary" target="_blank">
                                Renew License
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            <?php endif; ?>
            
            <!-- Unlicensed Redirect Settings -->
            <div class="oversee-license-box" style="margin-top: 20px;">
                <h3>Unlicensed Redirect Settings</h3>
                <p>When the license is invalid or missing, redirect public support pages to this URL:</p>
                
                <form method="post">
                    <?php wp_nonce_field('oversee_license_action'); ?>
                    <div class="license-input-wrap">
                        <input type="url" name="unlicensed_redirect_url" 
                               value="<?php echo esc_attr($redirect_url); ?>"
                               placeholder="https://example.com/get-license" 
                               class="license-input">
                        <button type="submit" name="oversee_save_redirect" class="button">
                            Save URL
                        </button>
                    </div>
                    <p class="description">Leave blank to use the default pricing page at OverseeAgency.com</p>
                </form>
            </div>
            
            <!-- License Enforcement Options -->
            <div class="oversee-license-box" style="margin-top: 20px;">
                <h3>Hard Lock Enforcement</h3>
                <p>Control how unlicensed access is handled:</p>
                
                <form method="post" class="enforcement-options">
                    <?php wp_nonce_field('oversee_license_action'); ?>
                    
                    <label class="checkbox-option">
                        <input type="checkbox" name="demo_mode" value="1" <?php checked(self::is_demo_mode()); ?>>
                        <span class="checkbox-label">
                            <strong>Demo Mode</strong><br>
                            <small>Allow Knowledge Base access without a valid license (for preview/demo purposes). Ticket submission still requires a license.</small>
                        </span>
                    </label>
                    
                    <label class="checkbox-option">
                        <input type="checkbox" name="show_license_page" value="1" <?php checked(self::show_license_page()); ?>>
                        <span class="checkbox-label">
                            <strong>Show License Required Page</strong><br>
                            <small>Instead of redirecting to an external URL, show a branded "License Required" page. Useful for white-label deployments.</small>
                        </span>
                    </label>
                    
                    <div style="margin-top: 16px;">
                        <button type="submit" name="oversee_save_enforcement" class="button button-primary">
                            Save Enforcement Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <style>
            .oversee-license-page { max-width: 700px; }
            .oversee-license-box { background: #fff; padding: 24px; border-radius: 8px; border: 1px solid #ddd; }
            .oversee-license-box h2 { margin-top: 0; }
            .oversee-license-box h3 { margin-top: 0; margin-bottom: 8px; }
            .oversee-license-box.license-valid { border-left: 4px solid #22c55e; }
            .oversee-license-box.license-invalid { border-left: 4px solid #ef4444; }
            .license-input-wrap { display: flex; gap: 10px; margin: 20px 0; }
            .license-input { flex: 1; padding: 10px 14px; font-size: 15px; font-family: monospace; border: 1px solid #ddd; border-radius: 4px; }
            .license-details { width: 100%; margin: 20px 0; border-collapse: collapse; }
            .license-details th { text-align: left; padding: 10px 12px 10px 0; color: #666; font-weight: 500; width: 130px; vertical-align: top; }
            .license-details td { padding: 10px 0; }
            .license-details code { background: #f5f5f5; padding: 4px 8px; border-radius: 3px; font-size: 13px; }
            .status-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
            .status-active { background: #dcfce7; color: #166534; }
            .status-warning { background: #fef3c7; color: #92400e; }
            .status-error { background: #fee2e2; color: #991b1b; }
            .error-message { color: #dc2626; }
            .license-actions { margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; display: flex; gap: 10px; flex-wrap: wrap; }
            .license-help { margin-top: 15px; font-size: 13px; color: #666; }
            .description { font-size: 13px; color: #666; margin-top: 8px; }
            .checkbox-option { display: flex; gap: 12px; padding: 14px; margin: 12px 0; background: #f8fafc; border-radius: 6px; cursor: pointer; }
            .checkbox-option input { margin-top: 2px; }
            .checkbox-option:hover { background: #f1f5f9; }
            .checkbox-label { line-height: 1.4; }
            .checkbox-label strong { display: block; margin-bottom: 2px; }
            .checkbox-label small { color: #64748b; }
        </style>
        <?php
    }
    
    /**
     * Mask license key for display
     */
    private static function mask_key($key) {
        if (strlen($key) <= 8) {
            return $key;
        }
        return substr($key, 0, 8) . str_repeat('•', max(0, strlen($key) - 12)) . substr($key, -4);
    }
}
