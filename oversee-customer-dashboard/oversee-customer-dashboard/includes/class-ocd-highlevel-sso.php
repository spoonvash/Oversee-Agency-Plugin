<?php
/**
 * HighLevel / OverseeCRM SSO + embed bridge.
 *
 * The dashboard surfaces (Conversations, Calendar, Performance Reports,
 * Documents, Reputation) embed the existing HighLevel UI inside iframes.
 * The embed URL is fetched server-side via this class so the API token and
 * the user's `ohl_contact_id` never leak to the browser.
 *
 * The actual HighLevel "magic link" endpoint isn't part of the public API
 * yet — it's surfaced via internal endpoints that vary across HighLevel
 * deployments. We therefore implement a placeholder that:
 *
 *   1. Reads the contact id and target surface.
 *   2. Looks up the magic-link endpoint from settings / env / a filter.
 *   3. Performs the request and returns the resulting URL, OR
 *   4. Returns a structured WP_Error explaining what's missing so the SPA
 *      can show a clear setup hint to admins instead of a broken iframe.
 *
 * Real-deployment integrators replace `fetch_magic_link()` (or filter
 * `ocd_highlevel_magic_link`) with the actual call once HighLevel exposes the
 * endpoint, without touching the rest of the plugin.
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class OCD_HighLevel_SSO {

    /** Surfaces we know how to embed. */
    const SURFACES = ['conversations', 'calendar', 'reports', 'documents', 'reputation'];

    /** User meta key for the linked HighLevel contact id. */
    const META_CONTACT_ID = 'ohl_contact_id';

    public static function get_contact_id($user_id) {
        $val = get_user_meta((int) $user_id, self::META_CONTACT_ID, true);
        return is_string($val) ? trim($val) : '';
    }

    public static function set_contact_id($user_id, $contact_id) {
        update_user_meta((int) $user_id, self::META_CONTACT_ID, sanitize_text_field((string) $contact_id));
    }

    /**
     * Build an embed config for the given user + surface. Returns either
     *   ['embed_url' => '...', 'expires_at' => 'ISO']
     * or a WP_Error explaining why we cannot embed yet (no contact, no
     * config, magic-link endpoint missing).
     */
    public static function embed_config($user_id, $surface) {
        $surface = sanitize_key($surface);
        if (!in_array($surface, self::SURFACES, true)) {
            return new WP_Error('ocd_hl_bad_surface', sprintf(__('Unknown surface "%s".', 'oversee-customer-dashboard'), $surface));
        }
        if (!OCD_Settings::is_highlevel_configured()) {
            return new WP_Error('ocd_hl_not_configured', __('HighLevel CRM is not configured. Set HIGHLEVEL_API_TOKEN and HIGHLEVEL_LOCATION_ID.', 'oversee-customer-dashboard'));
        }
        $contact_id = self::get_contact_id($user_id);
        if (!$contact_id) {
            return new WP_Error('ocd_hl_no_contact', __('No HighLevel contact linked to this user. Set the ohl_contact_id user meta.', 'oversee-customer-dashboard'));
        }

        $magic = self::fetch_magic_link($contact_id, $surface);
        if (is_wp_error($magic)) {
            return $magic;
        }

        return [
            'surface'    => $surface,
            'embed_url'  => $magic['url'],
            'expires_at' => $magic['expires_at'] ?? null,
            'contact_id' => $contact_id,
        ];
    }

    /**
     * Resolve a magic-link URL for the given contact + surface.
     *
     * The real HighLevel endpoint is configured via:
     *   - filter:    ocd_highlevel_magic_link_endpoint  (preferred)
     *   - constant:  HIGHLEVEL_MAGIC_LINK_ENDPOINT      (wp-config / env-bridge)
     *   - option:    ocd_settings.highlevel_magic_link_endpoint
     *
     * If none is configured we return WP_Error('ocd_hl_magic_link_unconfigured').
     * The SPA shows the admin a "set up SSO" panel; clients see a friendly
     * empty state and a button to message their account manager.
     */
    public static function fetch_magic_link($contact_id, $surface) {
        $endpoint = apply_filters(
            'ocd_highlevel_magic_link_endpoint',
            self::resolve_magic_link_endpoint(),
            $contact_id,
            $surface
        );

        if (!$endpoint) {
            return new WP_Error(
                'ocd_hl_magic_link_unconfigured',
                __('HighLevel magic-link endpoint is not configured. See Plugin Settings → HighLevel SSO.', 'oversee-customer-dashboard')
            );
        }

        // Allow an integrator to fully short-circuit and return a URL directly.
        $short = apply_filters('ocd_highlevel_magic_link', null, $contact_id, $surface, $endpoint);
        if (is_array($short) && !empty($short['url'])) {
            return $short;
        }

        $token   = OCD_Settings::get('highlevel_token');
        $version = OCD_Settings::get('highlevel_api_version', '2021-07-28');
        $body    = [
            'contactId' => $contact_id,
            'surface'   => $surface,
        ];

        $response = wp_remote_post($endpoint, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Version'       => $version,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($body),
        ]);
        if (is_wp_error($response)) {
            return $response;
        }
        $code = wp_remote_retrieve_response_code($response);
        $raw  = wp_remote_retrieve_body($response);
        $data = json_decode($raw, true);
        if ($code < 200 || $code >= 300 || !is_array($data) || empty($data['url'])) {
            return new WP_Error('ocd_hl_magic_link_failed', sprintf(__('HighLevel magic-link request failed (%d).', 'oversee-customer-dashboard'), $code), [
                'status' => $code,
            ]);
        }
        return [
            'url'        => esc_url_raw($data['url']),
            'expires_at' => $data['expiresAt'] ?? null,
        ];
    }

    private static function resolve_magic_link_endpoint() {
        if (defined('HIGHLEVEL_MAGIC_LINK_ENDPOINT') && HIGHLEVEL_MAGIC_LINK_ENDPOINT) {
            return (string) HIGHLEVEL_MAGIC_LINK_ENDPOINT;
        }
        $env = getenv('HIGHLEVEL_MAGIC_LINK_ENDPOINT');
        if ($env) {
            return $env;
        }
        $opts = get_option(OCD_Settings::OPTION_KEY, []);
        if (!empty($opts['highlevel_magic_link_endpoint'])) {
            return (string) $opts['highlevel_magic_link_endpoint'];
        }
        return '';
    }
}
