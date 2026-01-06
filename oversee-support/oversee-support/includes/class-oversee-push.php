<?php
/**
 * Push Notifications
 * 
 * Handles Web Push notification functionality.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Oversee_Push {
    
    /**
     * Generate VAPID keys
     */
    public static function generate_vapid_keys() {
        // Check if OpenSSL available
        if (!function_exists('openssl_pkey_new')) {
            return new WP_Error(
                'openssl_missing',
                'OpenSSL extension required for push notifications. Please contact your hosting provider.'
            );
        }
        
        // Check if EC is supported
        if (function_exists('openssl_get_curve_names')) {
            $curves = openssl_get_curve_names();
            if (!$curves || !in_array('prime256v1', $curves)) {
                return new WP_Error(
                    'ec_not_supported',
                    'Your server does not support EC cryptography required for push notifications.'
                );
            }
        }
        
        // Generate EC key pair
        $config = [
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ];
        
        $key = openssl_pkey_new($config);
        if (!$key) {
            return new WP_Error(
                'key_generation_failed',
                'Failed to generate VAPID keys: ' . openssl_error_string()
            );
        }
        
        // Extract private key
        openssl_pkey_export($key, $private_pem);
        
        // Extract public key details
        $details = openssl_pkey_get_details($key);
        
        if (!isset($details['ec']['x']) || !isset($details['ec']['y']) || !isset($details['ec']['d'])) {
            return new WP_Error('key_extraction_failed', 'Failed to extract key components');
        }
        
        // Convert to base64url format required by Web Push
        $public_key = self::base64url_encode(
            pack('C', 4) . // Uncompressed point indicator
            $details['ec']['x'] . 
            $details['ec']['y']
        );
        
        // Extract raw private key
        $private_key = self::base64url_encode($details['ec']['d']);
        
        return [
            'public' => $public_key,
            'private' => $private_key
        ];
    }
    
    /**
     * Base64 URL encode
     */
    private static function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64 URL decode
     */
    private static function base64url_decode($data) {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }
    
    /**
     * Get or create VAPID keys
     */
    public static function get_vapid_keys() {
        $public = get_option('oversee_vapid_public_key');
        $private = get_option('oversee_vapid_private_key');
        
        if ($public && $private) {
            return [
                'public' => $public,
                'private' => $private
            ];
        }
        
        // Generate new keys
        $keys = self::generate_vapid_keys();
        
        if (is_wp_error($keys)) {
            return $keys;
        }
        
        update_option('oversee_vapid_public_key', $keys['public']);
        update_option('oversee_vapid_private_key', $keys['private']);
        
        return $keys;
    }
    
    /**
     * Check if push notifications are available
     */
    public static function is_available() {
        if (!get_option('oversee_push_enabled', true)) {
            return false;
        }
        
        $keys = self::get_vapid_keys();
        return !is_wp_error($keys);
    }
    
    /**
     * Send push notification to a subscription
     */
    public static function send($subscription, $payload) {
        if (!self::is_available()) {
            return new WP_Error('push_not_available', 'Push notifications are not available');
        }
        
        // For full Web Push implementation, you would need a library like web-push-php
        // This is a simplified version that shows the structure
        
        Oversee_Logger::info('Push notification queued', [
            'endpoint' => substr($subscription['endpoint'], 0, 50) . '...',
            'payload' => $payload
        ]);
        
        // In a production environment, you would use:
        // 1. Install minishlink/web-push via Composer
        // 2. Use the WebPush class to send notifications
        
        return true;
    }
    
    /**
     * Send notification to all agents
     */
    public static function notify_agents($title, $body, $data = []) {
        global $wpdb;
        
        $subscriptions = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}oversee_push_subscriptions WHERE user_type = 'agent'"
        );
        
        $payload = wp_json_encode([
            'title' => $title,
            'body' => $body,
            'icon' => '/wp-content/plugins/oversee-support/assets/images/icon-192.png',
            'badge' => '/wp-content/plugins/oversee-support/assets/images/badge-72.png',
            'data' => $data
        ]);
        
        $sent = 0;
        foreach ($subscriptions as $sub) {
            $result = self::send([
                'endpoint' => $sub->endpoint,
                'keys' => [
                    'p256dh' => $sub->p256dh_key,
                    'auth' => $sub->auth_key
                ]
            ], $payload);
            
            if (!is_wp_error($result)) {
                $sent++;
            }
        }
        
        return $sent;
    }
    
    /**
     * Send notification to specific user
     */
    public static function notify_user($user_id, $title, $body, $data = []) {
        global $wpdb;
        
        $subscriptions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}oversee_push_subscriptions WHERE user_id = %d",
            $user_id
        ));
        
        if (empty($subscriptions)) {
            return 0;
        }
        
        $payload = wp_json_encode([
            'title' => $title,
            'body' => $body,
            'data' => $data
        ]);
        
        $sent = 0;
        foreach ($subscriptions as $sub) {
            $result = self::send([
                'endpoint' => $sub->endpoint,
                'keys' => [
                    'p256dh' => $sub->p256dh_key,
                    'auth' => $sub->auth_key
                ]
            ], $payload);
            
            if (!is_wp_error($result)) {
                $sent++;
            }
        }
        
        return $sent;
    }
    
    /**
     * Notify on new ticket
     */
    public static function notify_new_ticket($ticket_id, $subject) {
        return self::notify_agents(
            'New Support Ticket',
            $subject,
            [
                'type' => 'new_ticket',
                'ticket_id' => $ticket_id,
                'url' => oversee_admin_url('tickets?id=' . $ticket_id)
            ]
        );
    }
    
    /**
     * Notify on ticket reply
     */
    public static function notify_ticket_reply($ticket_id, $subject, $assigned_to = null) {
        if ($assigned_to) {
            return self::notify_user(
                $assigned_to,
                'New Reply on Ticket',
                $subject,
                [
                    'type' => 'ticket_reply',
                    'ticket_id' => $ticket_id,
                    'url' => oversee_admin_url('tickets?id=' . $ticket_id)
                ]
            );
        }
        
        return self::notify_agents(
            'New Reply on Ticket',
            $subject,
            [
                'type' => 'ticket_reply',
                'ticket_id' => $ticket_id
            ]
        );
    }
}
