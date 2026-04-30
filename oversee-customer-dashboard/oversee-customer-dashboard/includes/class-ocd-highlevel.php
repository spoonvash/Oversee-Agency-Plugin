<?php
/**
 * HighLevel / LeadConnector API client.
 * Docs: https://highlevel.stoplight.io — base URL https://services.leadconnectorhq.com
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class OCD_HighLevel {

    public static function is_configured() {
        return OCD_Settings::is_highlevel_configured();
    }

    private static function request($method, $path, $args = [], $body = null, $header_overrides = []) {
        if (!self::is_configured()) {
            return new WP_Error('ocd_not_configured', __('HighLevel CRM is not configured. Set HIGHLEVEL_API_TOKEN and HIGHLEVEL_LOCATION_ID.', 'oversee-customer-dashboard'));
        }

        $base    = rtrim(OCD_Settings::get('highlevel_base_url', 'https://services.leadconnectorhq.com'), '/');
        $token   = OCD_Settings::get('highlevel_token');
        $version = OCD_Settings::get('highlevel_api_version', '2021-07-28');
        $url     = $base . '/' . ltrim($path, '/');

        if (!empty($args)) {
            $url = add_query_arg($args, $url);
        }

        $headers = array_merge([
            'Authorization' => 'Bearer ' . $token,
            'Version'       => $version,
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ], is_array($header_overrides) ? $header_overrides : []);

        $request_args = [
            'method'  => strtoupper($method),
            'headers' => $headers,
            'timeout' => 20,
        ];
        if ($body !== null) {
            $request_args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($url, $request_args);
        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw  = wp_remote_retrieve_body($response);
        $data = json_decode($raw, true);

        if ($code < 200 || $code >= 300) {
            return new WP_Error(
                'ocd_highlevel_error',
                sprintf('HighLevel API error %d: %s', $code, is_string($raw) ? substr($raw, 0, 500) : ''),
                ['status' => $code, 'body' => $data]
            );
        }
        return $data;
    }

    public static function search_contact_by_email($email) {
        $location_id = OCD_Settings::get('highlevel_location_id');
        $result = self::request('GET', 'contacts/', [
            'locationId' => $location_id,
            'query'      => $email,
            'limit'      => 5,
        ]);
        if (is_wp_error($result)) return $result;

        $contacts = $result['contacts'] ?? [];
        foreach ($contacts as $c) {
            if (!empty($c['email']) && strcasecmp($c['email'], $email) === 0) {
                return $c;
            }
        }
        return $contacts[0] ?? null;
    }

    public static function get_contact($contact_id) {
        return self::request('GET', 'contacts/' . rawurlencode($contact_id));
    }

    public static function list_opportunities_for_contact($contact_id, $limit = 25) {
        $location_id = OCD_Settings::get('highlevel_location_id');
        return self::request('GET', 'opportunities/search', [
            'location_id' => $location_id,
            'contact_id'  => $contact_id,
            'limit'       => $limit,
        ]);
    }

    public static function list_opportunities($args = []) {
        $location_id = OCD_Settings::get('highlevel_location_id');
        $defaults = ['location_id' => $location_id, 'limit' => 50];
        return self::request('GET', 'opportunities/search', array_merge($defaults, $args));
    }

    public static function list_contacts($args = []) {
        $location_id = OCD_Settings::get('highlevel_location_id');
        $defaults = ['locationId' => $location_id, 'limit' => 50];
        return self::request('GET', 'contacts/', array_merge($defaults, $args));
    }

    public static function add_note_to_contact($contact_id, $body) {
        return self::request('POST', 'contacts/' . rawurlencode($contact_id) . '/notes', [], [
            'body' => $body,
        ]);
    }

    public static function ping() {
        $location_id = OCD_Settings::get('highlevel_location_id');
        if (!$location_id) {
            return new WP_Error('ocd_no_location', 'No locationId configured.');
        }
        return self::request('GET', 'locations/' . rawurlencode($location_id));
    }

    /**
     * Post an inbound message into HighLevel Conversations.
     * Docs: POST /conversations/messages/inbound (Version 2021-04-15)
     * Required: type, message, and either conversationId or contactId.
     * For custom providers, conversationProviderId is required.
     *
     * @param array $args  Keys: contact_id, conversation_id, message, type, conversation_provider_id, alt_id, direction, date.
     */
    public static function post_inbound_message($args) {
        if (!self::is_configured()) {
            return new WP_Error('ocd_not_configured', __('HighLevel CRM is not configured.', 'oversee-customer-dashboard'));
        }
        if (empty($args['message'])) {
            return new WP_Error('ocd_invalid_message', 'Message body required.');
        }
        if (empty($args['contact_id']) && empty($args['conversation_id'])) {
            return new WP_Error('ocd_invalid_target', 'contactId or conversationId required.');
        }

        $body = [
            'type'    => isset($args['type']) ? $args['type'] : 'Custom',
            'message' => (string) $args['message'],
        ];
        if (!empty($args['contact_id']))               $body['contactId']             = $args['contact_id'];
        if (!empty($args['conversation_id']))          $body['conversationId']        = $args['conversation_id'];
        if (!empty($args['conversation_provider_id'])) $body['conversationProviderId'] = $args['conversation_provider_id'];
        if (!empty($args['alt_id']))                   $body['altId']                 = $args['alt_id'];
        if (!empty($args['direction']))                $body['direction']             = $args['direction'];
        if (!empty($args['date']))                     $body['date']                  = $args['date'];

        // Inbound endpoint uses Version 2021-04-15 per docs; pass as override header.
        return self::request('POST', 'conversations/messages/inbound', [], $body, ['Version' => '2021-04-15']);
    }

    /**
     * Send an outbound message via HighLevel.
     * Docs: POST /conversations/messages
     */
    public static function send_outbound_message($args) {
        if (!self::is_configured()) {
            return new WP_Error('ocd_not_configured', __('HighLevel CRM is not configured.', 'oversee-customer-dashboard'));
        }
        if (empty($args['message']) || empty($args['contact_id']) || empty($args['type'])) {
            return new WP_Error('ocd_invalid_message', 'type, contactId, and message are required.');
        }
        $body = [
            'type'      => $args['type'],
            'contactId' => $args['contact_id'],
            'message'   => (string) $args['message'],
        ];
        if (!empty($args['conversation_id'])) $body['conversationId'] = $args['conversation_id'];
        return self::request('POST', 'conversations/messages', [], $body);
    }

    public static function list_conversations($args = []) {
        $location_id = OCD_Settings::get('highlevel_location_id');
        $defaults = ['locationId' => $location_id, 'limit' => 25];
        return self::request('GET', 'conversations/search', array_merge($defaults, $args));
    }

    public static function get_conversation_messages($conversation_id) {
        return self::request('GET', 'conversations/' . rawurlencode($conversation_id) . '/messages');
    }
}
