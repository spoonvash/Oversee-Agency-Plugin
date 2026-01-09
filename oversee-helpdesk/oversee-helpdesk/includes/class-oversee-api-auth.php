<?php
/**
 * API Authentication Middleware
 * 
 * Handles REST API authentication using both cookies and bearer tokens.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Oversee_API_Auth {
    
    /**
     * Initialize API authentication
     */
    public static function init() {
        add_filter('rest_pre_dispatch', [__CLASS__, 'authenticate'], 10, 3);
    }
    
    /**
     * Authenticate REST API requests
     */
    public static function authenticate($result, $server, $request) {
        $route = $request->get_route();
        
        // Only check our endpoints
        if (strpos($route, '/oversee/v1/') !== 0) {
            return $result;
        }
        
        // Public endpoints that don't need auth
        if (self::is_public_route($route)) {
            return $result;
        }
        
        // METHOD 1: WordPress cookie auth (direct access)
        if (is_user_logged_in()) {
            if (current_user_can('oversee_view_dashboard')) {
                return $result; // Authenticated via WP cookie
            }
        }
        
        // METHOD 2: Bearer token auth (iframe access)
        $auth_header = $request->get_header('Authorization');
        if ($auth_header && preg_match('/^Bearer\s+(\S+)$/i', $auth_header, $matches)) {
            $token = $matches[1];
            $user_id = Oversee_Iframe_Auth::validate_token($token);
            
            if ($user_id) {
                wp_set_current_user($user_id);
                return $result; // Authenticated via token
            }
        }
        
        // Not authenticated
        return new WP_Error(
            'rest_not_authenticated',
            'Authentication required. Please log in or provide a valid token.',
            ['status' => 401]
        );
    }
    
    /**
     * Check if route is public (no auth required)
     */
    private static function is_public_route($route) {
        $public_routes = [
            '/oversee/v1/auth/login',
            '/oversee/v1/push/vapid-key',
            '/oversee/v1/webhook/incoming',
            '/oversee/v1/tickets/public',
            '/oversee/v1/form-token',
            '/oversee/v1/debug/tickets',
        ];
        
        foreach ($public_routes as $public) {
            if (strpos($route, $public) === 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get current user from request (for logging purposes)
     */
    public static function get_current_user_from_request($request) {
        // Check cookie auth
        if (is_user_logged_in()) {
            return wp_get_current_user();
        }
        
        // Check token auth
        $auth_header = $request->get_header('Authorization');
        if ($auth_header && preg_match('/^Bearer\s+(\S+)$/i', $auth_header, $matches)) {
            $token = $matches[1];
            $user_id = Oversee_Iframe_Auth::validate_token($token);
            
            if ($user_id) {
                return get_user_by('ID', $user_id);
            }
        }
        
        return null;
    }
    
    /**
     * Check if current request is authenticated
     */
    public static function is_authenticated($request = null) {
        // Cookie auth
        if (is_user_logged_in() && current_user_can('oversee_view_dashboard')) {
            return true;
        }
        
        // Token auth
        if ($request) {
            $auth_header = $request->get_header('Authorization');
            if ($auth_header && preg_match('/^Bearer\s+(\S+)$/i', $auth_header, $matches)) {
                $token = $matches[1];
                return (bool) Oversee_Iframe_Auth::validate_token($token);
            }
        }
        
        return false;
    }
    
    /**
     * Permission callback for admin-only endpoints
     */
    public static function admin_permission_callback() {
        return current_user_can('oversee_manage_settings');
    }
    
    /**
     * Permission callback for agent endpoints
     */
    public static function agent_permission_callback() {
        return current_user_can('oversee_view_dashboard');
    }
    
    /**
     * Permission callback for ticket response endpoints
     */
    public static function respond_permission_callback() {
        return current_user_can('oversee_respond_tickets');
    }
}
