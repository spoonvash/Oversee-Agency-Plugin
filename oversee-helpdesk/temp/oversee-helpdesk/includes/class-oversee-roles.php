<?php
/**
 * Roles Class
 * 
 * Manages custom roles and capabilities.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Oversee_Roles {
    
    /**
     * All plugin capabilities
     */
    private static $capabilities = [
        'oversee_view_dashboard',
        'oversee_manage_tickets',
        'oversee_respond_tickets',
        'oversee_view_all_tickets',
        'oversee_manage_agents',
        'oversee_manage_settings',
        'oversee_manage_kb',
    ];
    
    /**
     * Initialize
     */
    public static function init() {
        // Ensure administrator role always has the required capabilities
        self::ensure_admin_capabilities();
    }
    
    /**
     * Ensure administrator role has all plugin capabilities
     * This runs on every page load to prevent "Access Denied" issues
     */
    public static function ensure_admin_capabilities() {
        $admin_role = get_role('administrator');
        
        if (!$admin_role) {
            return;
        }
        
        $caps_added = false;
        
        // Check if administrator has the view dashboard capability
        if (!$admin_role->has_cap('oversee_view_dashboard')) {
            // Add all admin capabilities to administrator role
            $admin_caps = self::get_admin_caps();
            foreach (array_keys($admin_caps) as $cap) {
                $admin_role->add_cap($cap);
            }
            $caps_added = true;
        }
        
        // Also ensure the custom roles exist
        self::ensure_custom_roles_exist();
        
        // If we added capabilities, refresh the current user to pick them up
        if ($caps_added && is_user_logged_in()) {
            $current_user = wp_get_current_user();
            if (in_array('administrator', $current_user->roles)) {
                // Refresh user capabilities by re-setting current user
                wp_set_current_user($current_user->ID);
            }
        }
    }
    
    /**
     * Ensure custom roles exist (recreate if missing)
     */
    public static function ensure_custom_roles_exist() {
        // Check if oversee_admin role exists
        if (!get_role('oversee_admin')) {
            add_role('oversee_admin', 'Support Admin', self::get_admin_caps());
        }
        
        // Check if oversee_agent role exists
        if (!get_role('oversee_agent')) {
            add_role('oversee_agent', 'Support Agent', self::get_agent_caps());
        }
    }
    
    /**
     * Get all capabilities
     */
    public static function get_capabilities() {
        return self::$capabilities;
    }
    
    /**
     * Get capabilities for admin role
     */
    public static function get_admin_caps() {
        return [
            'read' => true,
            'oversee_view_dashboard' => true,
            'oversee_manage_tickets' => true,
            'oversee_respond_tickets' => true,
            'oversee_view_all_tickets' => true,
            'oversee_manage_agents' => true,
            'oversee_manage_settings' => true,
            'oversee_manage_kb' => true,
        ];
    }
    
    /**
     * Get capabilities for agent role
     */
    public static function get_agent_caps() {
        return [
            'read' => true,
            'oversee_view_dashboard' => true,
            'oversee_respond_tickets' => true,
        ];
    }
    
    /**
     * Check if user is support admin
     */
    public static function is_support_admin($user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        return user_can($user_id, 'oversee_manage_settings');
    }
    
    /**
     * Check if user is support agent
     */
    public static function is_support_agent($user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        return user_can($user_id, 'oversee_view_dashboard');
    }
    
    /**
     * Check if user can manage settings
     */
    public static function can_manage_settings($user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        return user_can($user_id, 'oversee_manage_settings');
    }
    
    /**
     * Check if user can respond to tickets
     */
    public static function can_respond_tickets($user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        return user_can($user_id, 'oversee_respond_tickets');
    }
    
    /**
     * Check if user can view all tickets
     */
    public static function can_view_all_tickets($user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        return user_can($user_id, 'oversee_view_all_tickets');
    }
    
    /**
     * Remove all plugin roles (for uninstall)
     */
    public static function remove_roles() {
        remove_role('oversee_admin');
        remove_role('oversee_agent');
        
        // Remove capabilities from administrator
        $admin = get_role('administrator');
        if ($admin) {
            foreach (self::$capabilities as $cap) {
                $admin->remove_cap($cap);
            }
        }
    }
}
