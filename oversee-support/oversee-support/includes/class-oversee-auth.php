<?php
/**
 * WordPress Native Authentication
 * 
 * Handles login, logout, and session management for direct WordPress access.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Oversee_Auth {
    
    /**
     * Initialize authentication
     */
    public static function init() {
        add_action('template_redirect', [__CLASS__, 'check_admin_access'], 10);
    }
    
    /**
     * Check if user can access admin pages
     */
    public static function check_admin_access() {
        // Only check admin pages
        if (!self::is_admin_page()) {
            return;
        }
        
        // Check for iframe auth first (handled by Oversee_Iframe_Auth)
        if (isset($_GET['hl_email']) && isset($_GET['hl_key'])) {
            return; // Let iframe auth handle this
        }
        
        // Login page is always accessible
        if (self::is_login_page()) {
            // If already logged in with permission, redirect to dashboard
            if (is_user_logged_in() && current_user_can('oversee_view_dashboard')) {
                wp_safe_redirect(oversee_admin_url());
                exit;
            }
            return;
        }
        
        // Check authentication
        if (!is_user_logged_in()) {
            $redirect = urlencode($_SERVER['REQUEST_URI']);
            wp_safe_redirect(oversee_admin_url('login?redirect=' . $redirect));
            exit;
        }
        
        // Check permission
        if (!current_user_can('oversee_view_dashboard')) {
            wp_die(
                '<h1>Access Denied</h1><p>You do not have permission to access the support dashboard.</p><p><a href="' . esc_url(home_url()) . '">Go to Homepage</a></p>',
                'Access Denied',
                ['response' => 403]
            );
        }
    }
    
    /**
     * Process login form
     */
    public static function process_login() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return null;
        }
        
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        
        // Check brute force protection
        $ip_check = self::check_brute_force('ip_' . $ip);
        if ($ip_check['blocked']) {
            return ['error' => $ip_check['message']];
        }
        
        if (!empty($email)) {
            $email_check = self::check_brute_force('email_' . $email);
            if ($email_check['blocked']) {
                return ['error' => $email_check['message']];
            }
        }
        
        // Verify nonce
        if (!isset($_POST['oversee_login_nonce']) || !wp_verify_nonce($_POST['oversee_login_nonce'], 'oversee_login')) {
            return ['error' => 'Invalid security token. Please refresh the page and try again.'];
        }
        
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $remember = !empty($_POST['remember']);
        
        if (empty($email) || empty($password)) {
            return ['error' => 'Please enter your email and password.'];
        }
        
        // Get user by email
        $user = get_user_by('email', $email);
        if (!$user) {
            // Try username
            $user = get_user_by('login', $email);
        }
        
        if (!$user) {
            self::record_failed_attempt('ip_' . $ip);
            if (!empty($email)) {
                self::record_failed_attempt('email_' . $email);
            }
            self::log_auth('failed', null, $email, false);
            return ['error' => 'Invalid email or password.'];
        }
        
        // Attempt login
        $result = wp_signon([
            'user_login' => $user->user_login,
            'user_password' => $password,
            'remember' => $remember
        ]);
        
        if (is_wp_error($result)) {
            self::record_failed_attempt('ip_' . $ip);
            self::record_failed_attempt('email_' . $email);
            self::log_auth('failed', $user->ID, $email, false);
            return ['error' => 'Invalid email or password.'];
        }
        
        // Check permission
        if (!user_can($user, 'oversee_view_dashboard')) {
            wp_logout();
            self::log_auth('denied', $user->ID, $email, false);
            return ['error' => 'You do not have permission to access the support dashboard.'];
        }
        
        // Success - clear failed attempts
        self::clear_failed_attempts('ip_' . $ip);
        self::clear_failed_attempts('email_' . $email);
        self::log_auth('login', $user->ID, $user->user_email, true);
        
        // Redirect
        $redirect = isset($_POST['redirect']) && !empty($_POST['redirect']) 
            ? esc_url_raw($_POST['redirect']) 
            : oversee_admin_url();
        
        wp_safe_redirect($redirect);
        exit;
    }
    
    /**
     * Process logout
     */
    public static function process_logout() {
        $user_id = get_current_user_id();
        $email = '';
        
        if ($user_id) {
            $user = get_user_by('ID', $user_id);
            $email = $user ? $user->user_email : '';
        }
        
        wp_logout();
        
        self::log_auth('logout', $user_id, $email, true);
        
        wp_safe_redirect(oversee_admin_url('login?logged_out=1'));
        exit;
    }
    
    /**
     * Check brute force protection
     */
    public static function check_brute_force($identifier) {
        $max_attempts = (int) get_option('oversee_max_login_attempts', 5);
        $lockout_duration = (int) get_option('oversee_lockout_duration', 900);
        
        $cache_key = 'oversee_login_attempts_' . md5($identifier);
        $attempts = get_transient($cache_key);
        
        if ($attempts && $attempts >= $max_attempts) {
            $timeout_key = '_transient_timeout_' . $cache_key;
            $timeout = get_option($timeout_key);
            $remaining = $timeout ? max(0, $timeout - time()) : 0;
            
            return [
                'blocked' => true,
                'remaining_seconds' => $remaining,
                'message' => sprintf(
                    'Too many failed login attempts. Please try again in %d minutes.',
                    max(1, ceil($remaining / 60))
                )
            ];
        }
        
        return ['blocked' => false];
    }
    
    /**
     * Record a failed login attempt
     */
    public static function record_failed_attempt($identifier) {
        $lockout_duration = (int) get_option('oversee_lockout_duration', 900);
        $cache_key = 'oversee_login_attempts_' . md5($identifier);
        
        $attempts = get_transient($cache_key);
        $attempts = $attempts ? (int) $attempts : 0;
        
        set_transient($cache_key, $attempts + 1, $lockout_duration);
    }
    
    /**
     * Clear failed login attempts
     */
    public static function clear_failed_attempts($identifier) {
        $cache_key = 'oversee_login_attempts_' . md5($identifier);
        delete_transient($cache_key);
    }
    
    /**
     * Log authentication event
     */
    public static function log_auth($type, $user_id, $email, $success = true) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'oversee_auth_log',
            [
                'user_id' => $user_id,
                'email' => $email,
                'auth_type' => $type,
                'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : '',
                'referrer' => isset($_SERVER['HTTP_REFERER']) ? substr($_SERVER['HTTP_REFERER'], 0, 500) : '',
                'success' => $success ? 1 : 0
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%d']
        );
    }
    
    /**
     * Check if current page is an admin page
     */
    public static function is_admin_page() {
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        return strpos($uri, '/support/admin') !== false;
    }
    
    /**
     * Check if current page is the login page
     */
    public static function is_login_page() {
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        return strpos($uri, '/support/admin/login') !== false;
    }
    
    /**
     * Get current agent record
     */
    public static function get_current_agent() {
        if (!is_user_logged_in()) {
            return null;
        }
        
        global $wpdb;
        $user_id = get_current_user_id();
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}oversee_agents WHERE wp_user_id = %d",
            $user_id
        ));
    }
    
    /**
     * Ensure agent record exists for current user
     */
    public static function ensure_agent_record($user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return false;
        }
        
        global $wpdb;
        
        // Check if record exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}oversee_agents WHERE wp_user_id = %d OR email = %s",
            $user_id,
            $user->user_email
        ));
        
        if ($exists) {
            // Update wp_user_id if needed
            $wpdb->update(
                $wpdb->prefix . 'oversee_agents',
                ['wp_user_id' => $user_id],
                ['id' => $exists],
                ['%d'],
                ['%d']
            );
            return $exists;
        }
        
        // Create new record
        $role = user_can($user_id, 'oversee_manage_settings') ? 'admin' : 'agent';
        
        $wpdb->insert(
            $wpdb->prefix . 'oversee_agents',
            [
                'wp_user_id' => $user_id,
                'email' => $user->user_email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'role' => $role,
                'is_online' => 0
            ],
            ['%d', '%s', '%s', '%s', '%s', '%d']
        );
        
        return $wpdb->insert_id;
    }

    /**
     * Validate WP admin iframe token
     */
    public static function validate_wp_admin_token($token) {
        if (empty($token)) {
            return false;
        }
        
        $hash = hash('sha256', $token);
        $user_id = get_transient('oversee_wp_token_' . $hash);
        
        if ($user_id) {
            // Delete token after use (one-time)
            delete_transient('oversee_wp_token_' . $hash);
            
            // Log the user in for this request
            wp_set_current_user($user_id);
            return true;
        }
        
        return false;
    }

}