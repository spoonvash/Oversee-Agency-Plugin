<?php
/**
 * Settings: Reads connection config from environment variables first, then falls
 * back to wp-options. Tokens/secrets must never be exposed to the frontend.
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class OCD_Settings {

    const OPTION_KEY = 'ocd_settings';

    public static function init() {
        add_action('admin_init', [__CLASS__, 'register']);
    }

    public static function install_defaults() {
        if (false === get_option(self::OPTION_KEY)) {
            update_option(self::OPTION_KEY, self::defaults());
        }
    }

    public static function defaults() {
        return [
            'highlevel_base_url'       => 'https://services.leadconnectorhq.com',
            'highlevel_api_version'    => '2021-07-28',
            'highlevel_token'          => '',
            'highlevel_location_id'    => '',
            'highlevel_magic_link_endpoint' => '',
            'agency_conversation_provider_id' => '',
            'agency_inbound_type'      => 'Custom',
            'wp_base_url'              => '',
            'woo_consumer_key'         => '',
            'woo_consumer_secret'      => '',
            'allow_admin_for_oversee_staff' => 1,
            'sync_enabled'             => 0,
        ];
    }

    public static function get($key, $default = null) {
        $env_map = [
            'highlevel_base_url'    => 'HIGHLEVEL_BASE_URL',
            'highlevel_token'       => 'HIGHLEVEL_API_TOKEN',
            'highlevel_location_id' => 'HIGHLEVEL_LOCATION_ID',
            'highlevel_api_version' => 'HIGHLEVEL_API_VERSION',
            'wp_base_url'           => 'OVERSEE_WP_BASE_URL',
            'woo_consumer_key'      => 'WOOCOMMERCE_CONSUMER_KEY',
            'woo_consumer_secret'   => 'WOOCOMMERCE_CONSUMER_SECRET',
        ];

        if (isset($env_map[$key])) {
            $env = getenv($env_map[$key]);
            if ($env !== false && $env !== '') {
                return $env;
            }
            if (defined($env_map[$key])) {
                $constant = constant($env_map[$key]);
                if ($constant !== '') {
                    return $constant;
                }
            }
        }

        $opts = get_option(self::OPTION_KEY, self::defaults());
        if (isset($opts[$key]) && $opts[$key] !== '') {
            return $opts[$key];
        }
        return $default !== null ? $default : (self::defaults()[$key] ?? null);
    }

    public static function update($values) {
        $current = get_option(self::OPTION_KEY, self::defaults());
        $merged  = array_merge($current, $values);
        update_option(self::OPTION_KEY, $merged);
        return $merged;
    }

    public static function is_highlevel_configured() {
        return (bool) self::get('highlevel_token') && (bool) self::get('highlevel_location_id');
    }

    public static function is_woocommerce_configured() {
        return (bool) self::get('wp_base_url') && (bool) self::get('woo_consumer_key') && (bool) self::get('woo_consumer_secret');
    }

    public static function connection_status() {
        return [
            'highlevel'   => self::is_highlevel_configured(),
            'woocommerce' => self::is_woocommerce_configured(),
        ];
    }

    public static function register() {
        register_setting('ocd_settings_group', self::OPTION_KEY, [
            'type'              => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize'],
            'default'           => self::defaults(),
        ]);
    }

    public static function sanitize($input) {
        $current = get_option(self::OPTION_KEY, self::defaults());
        $clean   = $current;

        $string_keys = [
            'highlevel_base_url', 'highlevel_api_version', 'highlevel_token',
            'highlevel_location_id', 'agency_conversation_provider_id', 'agency_inbound_type',
            'wp_base_url', 'woo_consumer_key', 'woo_consumer_secret',
        ];
        foreach ($string_keys as $k) {
            if (isset($input[$k])) {
                $clean[$k] = trim(sanitize_text_field($input[$k]));
            }
        }
        $bool_keys = ['allow_admin_for_oversee_staff', 'sync_enabled'];
        foreach ($bool_keys as $k) {
            $clean[$k] = !empty($input[$k]) ? 1 : 0;
        }
        return $clean;
    }
}
