<?php
/**
 * Webhooks
 * 
 * Handles outgoing webhook notifications for ticket events.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Oversee_Webhooks {
    
    /**
     * Available webhook events
     */
    const EVENTS = [
        'ticket_created' => 'Ticket Created',
        'ticket_updated' => 'Ticket Updated',
        'ticket_resolved' => 'Ticket Resolved',
        'reply_added' => 'Reply Added',
        'ticket_assigned' => 'Ticket Assigned',
    ];
    
    /**
     * Initialize webhooks
     */
    public static function init() {
        // Nothing to initialize on load
    }
    
    /**
     * Trigger a webhook event
     */
    public static function trigger($event, $data = []) {
        $webhook_url = get_option('oversee_webhook_url');
        
        if (empty($webhook_url)) {
            return false;
        }
        
        $enabled_events = get_option('oversee_webhook_events', []);
        
        if (!empty($enabled_events) && !in_array($event, $enabled_events)) {
            return false;
        }
        
        // Add metadata
        $payload = [
            'event' => $event,
            'timestamp' => time(),
            'site_url' => home_url(),
            'data' => $data
        ];
        
        // Send webhook (non-blocking)
        $result = wp_remote_post($webhook_url, [
            'body' => wp_json_encode($payload),
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Webhook-Source' => 'oversee-crm',
                'X-Webhook-Event' => $event,
                'X-Webhook-Timestamp' => (string) time()
            ],
            'timeout' => 10,
            'blocking' => false,
            'data_format' => 'body'
        ]);
        
        if (is_wp_error($result)) {
            Oversee_Logger::warning('Webhook failed', [
                'event' => $event,
                'url' => $webhook_url,
                'error' => $result->get_error_message()
            ]);
            return false;
        }
        
        Oversee_Logger::debug('Webhook triggered', [
            'event' => $event,
            'url' => $webhook_url
        ]);
        
        return true;
    }
    
    /**
     * Get available events
     */
    public static function get_events() {
        return self::EVENTS;
    }
    
    /**
     * Test webhook URL
     */
    public static function test($url = null) {
        if ($url === null) {
            $url = get_option('oversee_webhook_url');
        }
        
        if (empty($url)) {
            return new WP_Error('no_url', 'No webhook URL configured');
        }
        
        $payload = [
            'event' => 'test',
            'timestamp' => time(),
            'site_url' => home_url(),
            'data' => [
                'message' => 'This is a test webhook from OverseeCRM Support'
            ]
        ];
        
        $result = wp_remote_post($url, [
            'body' => wp_json_encode($payload),
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Webhook-Source' => 'oversee-crm',
                'X-Webhook-Event' => 'test'
            ],
            'timeout' => 15,
            'blocking' => true
        ]);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $code = wp_remote_retrieve_response_code($result);
        
        if ($code >= 200 && $code < 300) {
            return [
                'success' => true,
                'status_code' => $code,
                'message' => 'Webhook test successful'
            ];
        }
        
        return new WP_Error(
            'webhook_failed',
            'Webhook returned status code ' . $code,
            ['status_code' => $code]
        );
    }
}
