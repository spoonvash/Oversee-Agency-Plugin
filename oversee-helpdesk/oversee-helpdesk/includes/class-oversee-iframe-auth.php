<?php
/**
 * Iframe Authentication
 * 
 * Handles token-based authentication for HighLevel iframe embedding.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Oversee_Iframe_Auth {
    
    /**
     * Initialize iframe authentication
     */
    public static function init() {
        add_action('template_redirect', [__CLASS__, 'handle_iframe_auth'], 5);
    }
    
    /**
     * Handle iframe authentication
     */
    public static function handle_iframe_auth() {
        // Only on admin pages
        if (strpos($_SERVER['REQUEST_URI'], '/support/admin') === false) {
            return;
        }
        
        // Check for HighLevel params
        if (!isset($_GET['hl_email']) || !isset($_GET['hl_key'])) {
            return; // Not an iframe request, use normal auth
        }
        
        $email = sanitize_email($_GET['hl_email']);
        $key = sanitize_text_field($_GET['hl_key']);
        $name = isset($_GET['hl_name']) ? sanitize_text_field($_GET['hl_name']) : '';
        
        // Validate key
        $stored_key = get_option('oversee_hl_secret');
        if (empty($stored_key) || !hash_equals($stored_key, $key)) {
            self::auth_error('Invalid access key. Please contact your administrator.');
            return;
        }
        
        // Find WordPress user by email
        $user = get_user_by('email', $email);
        if (!$user) {
            self::auth_error('No account found for this email (' . esc_html($email) . '). Please contact your administrator to set up access.');
            return;
        }
        
        // Check permission
        if (!user_can($user, 'oversee_view_dashboard')) {
            self::auth_error('You do not have permission to access the support dashboard.');
            return;
        }
        
        // Generate token
        $token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $token);
        
        // Store token
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'oversee_tokens',
            [
                'token_hash' => $token_hash,
                'user_id' => $user->ID,
                'created_at' => current_time('mysql'),
                'expires_at' => date('Y-m-d H:i:s', time() + DAY_IN_SECONDS)
            ],
            ['%s', '%d', '%s', '%s']
        );
        
        // Ensure agent record exists
        Oversee_Auth::ensure_agent_record($user->ID);
        
        // Log the auth
        Oversee_Auth::log_auth('iframe', $user->ID, $email, true);
        
        // Output JavaScript to store token and redirect
        self::output_token_script($token, $user);
    }
    
    /**
     * Output script to store token and redirect
     */
    private static function output_token_script($token, $user) {
        $user_data = [
            'id' => $user->ID,
            'email' => $user->user_email,
            'name' => $user->display_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name
        ];
        
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Authenticating...</title>
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    display: flex; 
                    align-items: center; 
                    justify-content: center; 
                    height: 100vh; 
                    background: #f5f7fa;
                }
                .loader {
                    text-align: center;
                    color: #6b7280;
                }
                .spinner {
                    width: 40px;
                    height: 40px;
                    border: 3px solid #e5e7eb;
                    border-top-color: #f97316;
                    border-radius: 50%;
                    animation: spin 0.8s linear infinite;
                    margin: 0 auto 16px;
                }
                @keyframes spin { to { transform: rotate(360deg); } }
                p { font-size: 14px; }
            </style>
        </head>
        <body>
            <div class="loader">
                <div class="spinner"></div>
                <p>Authenticating...</p>
            </div>
            <script>
                try {
                    localStorage.setItem('oversee_token', <?php echo wp_json_encode($token); ?>);
                    localStorage.setItem('oversee_user', <?php echo wp_json_encode($user_data); ?>);
                    window.location.replace(<?php echo wp_json_encode(oversee_admin_url()); ?>);
                } catch (e) {
                    document.body.innerHTML = '<div style="text-align:center;padding:40px;"><h2>Storage Error</h2><p>Please enable cookies/localStorage for this site.</p></div>';
                }
            </script>
        </body>
        </html>
        <?php
        exit;
    }
    
    /**
     * Show authentication error
     */
    private static function auth_error($message) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Access Denied</title>
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    display: flex; 
                    align-items: center; 
                    justify-content: center; 
                    height: 100vh; 
                    background: #f5f7fa;
                }
                .error-box {
                    background: white;
                    padding: 40px;
                    border-radius: 12px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                    text-align: center;
                    max-width: 400px;
                    margin: 20px;
                }
                .error-icon { font-size: 48px; margin-bottom: 16px; }
                h1 { font-size: 20px; margin: 0 0 12px; color: #111827; }
                p { color: #6b7280; margin: 0; line-height: 1.5; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class="error-box">
                <div class="error-icon">🚫</div>
                <h1>Access Denied</h1>
                <p><?php echo esc_html($message); ?></p>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    /**
     * Validate a token
     * 
     * @param string $token The raw token
     * @return int|false User ID if valid, false otherwise
     */
    public static function validate_token($token) {
        if (empty($token)) {
            return false;
        }
        
        global $wpdb;
        
        $token_hash = hash('sha256', $token);
        
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT user_id, expires_at FROM {$wpdb->prefix}oversee_tokens 
             WHERE token_hash = %s",
            $token_hash
        ));
        
        if (!$record) {
            return false;
        }
        
        // Check expiry
        if (strtotime($record->expires_at) < time()) {
            // Clean up expired token
            $wpdb->delete(
                $wpdb->prefix . 'oversee_tokens',
                ['token_hash' => $token_hash],
                ['%s']
            );
            return false;
        }
        
        // Verify user still exists and has permission
        $user = get_user_by('ID', $record->user_id);
        if (!$user || !user_can($user, 'oversee_view_dashboard')) {
            return false;
        }
        
        return (int) $record->user_id;
    }
    
    /**
     * Invalidate a token (logout)
     */
    public static function invalidate_token($token) {
        if (empty($token)) {
            return false;
        }
        
        global $wpdb;
        
        $token_hash = hash('sha256', $token);
        
        return $wpdb->delete(
            $wpdb->prefix . 'oversee_tokens',
            ['token_hash' => $token_hash],
            ['%s']
        );
    }
    
    /**
     * Invalidate all tokens for a user
     */
    public static function invalidate_user_tokens($user_id) {
        global $wpdb;
        
        return $wpdb->delete(
            $wpdb->prefix . 'oversee_tokens',
            ['user_id' => $user_id],
            ['%d']
        );
    }
    
    /**
     * Get active sessions for a user
     */
    public static function get_user_sessions($user_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, created_at, expires_at 
             FROM {$wpdb->prefix}oversee_tokens 
             WHERE user_id = %d AND expires_at > NOW()
             ORDER BY created_at DESC",
            $user_id
        ));
    }
}
