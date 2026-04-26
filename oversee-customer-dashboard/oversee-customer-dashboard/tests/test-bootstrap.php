<?php
/**
 * Lightweight verification harness — no full WordPress install required.
 * Stubs the WordPress core functions used by the integration classes and
 * confirms that the plugin classes load and behave correctly when API
 * credentials are absent (the "disconnected" path).
 *
 * Run with:  php oversee-customer-dashboard/tests/test-bootstrap.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// --- Minimal WordPress shims ---
$GLOBALS['__ocd_options'] = [];
$GLOBALS['__ocd_user_meta'] = [];

function get_option($k, $default = false)   { return $GLOBALS['__ocd_options'][$k] ?? $default; }
function update_option($k, $v)              { $GLOBALS['__ocd_options'][$k] = $v; return true; }
function add_action(...$a)                  {}
function add_filter(...$a)                  {}
function add_shortcode(...$a)               {}
function register_setting(...$a)            {}
function register_activation_hook(...$a)    {}
function register_rest_route(...$a)         {}
function plugin_dir_path($f)                { return dirname($f) . '/'; }
function plugin_dir_url($f)                 { return 'https://example.com/'; }
function plugin_basename($f)                { return basename($f); }
function load_plugin_textdomain(...$a)      {}
function flush_rewrite_rules(...$a)         {}
function sanitize_text_field($v)            { return is_string($v) ? trim(strip_tags($v)) : $v; }
function sanitize_key($v)                   { return is_string($v) ? strtolower(preg_replace('/[^a-z0-9_-]/i', '', $v)) : ''; }
function wp_kses_post($v)                   { return is_string($v) ? strip_tags($v, '<a><strong><em><br><p>') : $v; }
function wp_strip_all_tags($v)              { return is_string($v) ? strip_tags($v) : $v; }
function add_query_arg($args, $url) {
    $sep = strpos($url, '?') === false ? '?' : '&';
    return $url . $sep . http_build_query($args);
}
function wp_json_encode($v)                 { return json_encode($v); }
function wp_remote_request($url, $args)     { return ['response' => ['code' => 0], 'body' => '']; }
function get_userdata($id)                  { return null; }
function update_user_meta($id, $k, $v)      { $GLOBALS['__ocd_user_meta'][$id][$k] = $v; return true; }
function get_user_meta($id, $k, $single)    { return $GLOBALS['__ocd_user_meta'][$id][$k] ?? ''; }
function current_time($type, $gmt = 0)      { return $type === 'mysql' ? gmdate('Y-m-d H:i:s') : time(); }
function __($t, $d = '')                    { return $t; }
function _e($t, $d = '')                    { echo $t; }
function esc_html__($t, $d = '')            { return htmlspecialchars($t, ENT_QUOTES); }
function is_wp_error($v)                    { return $v instanceof WP_Error; }
function home_url($p = '')                  { return 'https://example.com' . $p; }
function sanitize_title($v)                 { return is_string($v) ? strtolower(preg_replace('/[^a-z0-9-]+/i', '-', $v)) : ''; }
class WP_Error {
    private $code; private $message; private $data;
    public function __construct($c = '', $m = '', $d = []) { $this->code = $c; $this->message = $m; $this->data = $d; }
    public function get_error_message() { return $this->message; }
    public function get_error_code()    { return $this->code; }
}

require_once __DIR__ . '/../includes/class-ocd-settings.php';
require_once __DIR__ . '/../includes/class-ocd-highlevel.php';
require_once __DIR__ . '/../includes/class-ocd-woocommerce.php';
require_once __DIR__ . '/../includes/class-ocd-projects.php';
require_once __DIR__ . '/../includes/class-ocd-tasks.php';
require_once __DIR__ . '/../includes/class-ocd-entitlements.php';

// Provide a minimal OCD_Schema + OCD_Entitlements::for_user shim so the
// store class can be loaded and its WC-unavailable + empty-map paths
// exercised without a full WordPress install.
if (!class_exists('OCD_Schema_Stub')) {
    class OCD_Schema_Stub {
        public static function table($_) { return 'wp_ocd_x'; }
    }
}
if (!class_exists('OCD_Schema')) {
    class_alias('OCD_Schema_Stub', 'OCD_Schema');
}
require_once __DIR__ . '/../includes/class-ocd-store.php';

// --- Assertions ---
$results = [];
function check($label, $cond, &$results) {
    $results[] = [$label, (bool) $cond];
    echo ($cond ? '  ok ' : 'FAIL ') . $label . PHP_EOL;
}

OCD_Settings::install_defaults();
$d = OCD_Settings::defaults();
check('defaults include highlevel base url', $d['highlevel_base_url'] === 'https://services.leadconnectorhq.com', $results);
check('defaults include api version', $d['highlevel_api_version'] === '2021-07-28', $results);
check('defaults include agency conversation provider id key', array_key_exists('agency_conversation_provider_id', $d), $results);

check('highlevel disconnected by default', !OCD_HighLevel::is_configured(), $results);
check('woocommerce disconnected by default', !OCD_WooCommerce::is_configured(), $results);

$status = OCD_Settings::connection_status();
check('connection_status returns expected keys',
    isset($status['highlevel'], $status['woocommerce']) && $status['highlevel'] === false && $status['woocommerce'] === false,
    $results
);

$err = OCD_HighLevel::ping();
check('highlevel ping returns WP_Error when not configured',
    is_wp_error($err) && in_array($err->get_error_code(), ['ocd_not_configured', 'ocd_no_location'], true),
    $results
);

$err2 = OCD_WooCommerce::ping();
check('woocommerce ping returns WP_Error when not configured', is_wp_error($err2) && $err2->get_error_code() === 'ocd_not_configured', $results);

$bad = OCD_WooCommerce::update_subscription_status(123, 'definitely-not-valid');
check('invalid subscription status rejected', is_wp_error($bad) && $bad->get_error_code() === 'ocd_invalid_status', $results);

check('all six valid subscription statuses present',
    OCD_WooCommerce::VALID_SUB_STATUSES === ['active', 'pending', 'on-hold', 'pending-cancel', 'cancelled', 'expired'],
    $results
);

OCD_Settings::update(['highlevel_token' => 'tok', 'highlevel_location_id' => 'loc']);
check('highlevel becomes configured when token+location set', OCD_HighLevel::is_configured(), $results);

OCD_Settings::update(['wp_base_url' => 'https://x', 'woo_consumer_key' => 'k', 'woo_consumer_secret' => 's']);
check('woocommerce becomes configured when keys set', OCD_WooCommerce::is_configured(), $results);

// HighLevel inbound message validation (without network).
$res = OCD_HighLevel::post_inbound_message(['message' => '']);
check('inbound message rejects empty body', is_wp_error($res), $results);

$res2 = OCD_HighLevel::post_inbound_message(['message' => 'hi']);
check('inbound message rejects missing target', is_wp_error($res2) && $res2->get_error_code() === 'ocd_invalid_target', $results);

// Project status validation (no DB writes — purely validation surface).
check('project statuses include planning + completed', in_array('planning', OCD_Projects::VALID_STATUSES, true) && in_array('completed', OCD_Projects::VALID_STATUSES, true), $results);
check('milestone statuses include pending + completed', in_array('pending', OCD_Projects::VALID_MILESTONE_STATUSES, true) && in_array('completed', OCD_Projects::VALID_MILESTONE_STATUSES, true), $results);
check('task statuses include open + completed', in_array('open', OCD_Tasks::VALID_STATUSES, true) && in_array('completed', OCD_Tasks::VALID_STATUSES, true), $results);

// Entitlement product map round-trip.
$map_in  = ['42' => ['slug' => 'analytics-pro', 'label' => 'Analytics Pro'], 'bad' => ['slug' => '']];
$map_out = OCD_Entitlements::set_product_map($map_in);
check('product map drops invalid rows and keeps valid ones',
    isset($map_out[42]) && $map_out[42]['slug'] === 'analytics-pro' && !isset($map_out['bad']) && !isset($map_out[0]),
    $results
);
$f = OCD_Entitlements::feature_for_product(42);
check('feature_for_product returns the mapped slug', is_array($f) && $f['slug'] === 'analytics-pro', $results);
$nope = OCD_Entitlements::feature_for_product(999999);
check('feature_for_product returns null for unmapped products', $nope === null, $results);

// --- Store: WooCommerce-unavailable path returns a setup empty state, never fake products. ---
$payload = OCD_Store::dashboard_payload(123);
check('store payload returns available=false when WooCommerce is not loaded',
    is_array($payload) && $payload['available'] === false,
    $results
);
check('store payload returns no listings when WooCommerce is not loaded',
    is_array($payload['listings']) && count($payload['listings']) === 0,
    $results
);
check('store payload includes a human-readable reason for empty state',
    is_string($payload['reason']) && stripos($payload['reason'], 'WooCommerce') !== false,
    $results
);
check('search_existing_products returns empty when WC is not loaded (never fabricates products)',
    OCD_Store::search_existing_products(['search' => 'anything']) === [],
    $results
);
check('OCD_Store::listings_for_user backwards-compatible wrapper still returns an array',
    is_array(OCD_Store::listings_for_user(123)),
    $results
);

// --- Store category persistence ---
$cat_in = OCD_Store::set_store_category('Add-Ons & Bonuses!');
check('store category sanitizes to a slug', $cat_in === 'add-ons-bonuses-' || preg_match('/^[a-z0-9-]+$/', $cat_in), $results);
check('store category round-trips via wp options', OCD_Store::get_store_category() === $cat_in, $results);

$failed = array_filter($results, function ($r) { return !$r[1]; });
if (count($failed) > 0) {
    echo PHP_EOL . count($failed) . ' assertion(s) failed.' . PHP_EOL;
    exit(1);
}
echo PHP_EOL . count($results) . ' assertions passed.' . PHP_EOL;
