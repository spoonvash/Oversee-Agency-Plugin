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
function sanitize_file_name($v)             { return is_string($v) ? preg_replace('/[^A-Za-z0-9._-]+/', '-', $v) : 'file'; }
function wp_kses_post($v)                   { return is_string($v) ? strip_tags($v, '<a><strong><em><br><p>') : $v; }
function wp_strip_all_tags($v)              { return is_string($v) ? strip_tags($v) : $v; }
function trailingslashit($s)                { return rtrim($s, '/') . '/'; }
function rest_url($p)                       { return 'https://example.com/wp-json/' . ltrim($p, '/'); }
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
function wp_generate_password($len = 12, $special = false, $extra = false) { return substr(md5((string) microtime(true) . random_bytes(8)), 0, (int) $len); }
class WP_Error {
    private $code; private $message; private $data;
    public function __construct($c = '', $m = '', $d = []) { $this->code = $c; $this->message = $m; $this->data = $d; }
    public function get_error_message() { return $this->message; }
    public function get_error_code()    { return $this->code; }
}

require_once __DIR__ . '/../includes/class-ocd-settings.php';
require_once __DIR__ . '/../includes/class-ocd-instruction-media.php';
require_once __DIR__ . '/../includes/class-ocd-highlevel.php';
require_once __DIR__ . '/../includes/class-ocd-woocommerce.php';
require_once __DIR__ . '/../includes/class-ocd-projects.php';
require_once __DIR__ . '/../includes/class-ocd-tasks.php';
require_once __DIR__ . '/../includes/class-ocd-entitlements.php';
require_once __DIR__ . '/../includes/class-ocd-billing.php';

// Provide a minimal OCD_Schema + OCD_Entitlements::for_user shim so the
// store and project-files classes can be loaded and their unconfigured
// paths exercised without a full WordPress install.
if (!class_exists('OCD_Schema_Stub')) {
    class OCD_Schema_Stub {
        public static function table($_) { return 'wp_ocd_x'; }
    }
}
if (!class_exists('OCD_Schema')) {
    class_alias('OCD_Schema_Stub', 'OCD_Schema');
}
require_once __DIR__ . '/../includes/class-ocd-store.php';
require_once __DIR__ . '/../includes/class-ocd-project-files.php';

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

// Project status validation.
check('project statuses include planning + completed', in_array('planning', OCD_Projects::VALID_STATUSES, true) && in_array('completed', OCD_Projects::VALID_STATUSES, true), $results);
check('milestone statuses include pending + completed', in_array('pending', OCD_Projects::VALID_MILESTONE_STATUSES, true) && in_array('completed', OCD_Projects::VALID_MILESTONE_STATUSES, true), $results);

// Monday-style task status set.
check('task statuses include not_started + needs_your_input + waiting_on_oversee + completed + blocked',
    in_array('not_started', OCD_Tasks::VALID_STATUSES, true)
    && in_array('needs_your_input', OCD_Tasks::VALID_STATUSES, true)
    && in_array('waiting_on_oversee', OCD_Tasks::VALID_STATUSES, true)
    && in_array('completed', OCD_Tasks::VALID_STATUSES, true)
    && in_array('blocked', OCD_Tasks::VALID_STATUSES, true),
    $results
);
check('task labels are plain language',
    OCD_Tasks::STATUS_LABELS['needs_your_input'] === 'Needs Your Input'
    && OCD_Tasks::STATUS_LABELS['waiting_on_oversee'] === 'Waiting on Oversee',
    $results
);
check('customer-allowed task statuses exclude blocked + cancelled',
    !in_array('blocked', OCD_Tasks::CUSTOMER_ALLOWED_STATUSES, true)
    && !in_array('cancelled', OCD_Tasks::CUSTOMER_ALLOWED_STATUSES, true),
    $results
);
check('task status groups are ordered for client surface (needs_your_input first)',
    OCD_Tasks::status_groups()[0]['key'] === 'needs_your_input',
    $results
);

// Visibilities and types.
check('task visibilities include client + internal',
    in_array('client', OCD_Tasks::VISIBILITIES, true) && in_array('internal', OCD_Tasks::VISIBILITIES, true),
    $results
);
check('task types include client_required + internal',
    in_array('client_required', OCD_Tasks::TASK_TYPES, true) && in_array('internal', OCD_Tasks::TASK_TYPES, true),
    $results
);

// requires_client_action heuristic.
$cri = ['visibility' => 'client', 'task_type' => 'client_required', 'status' => 'needs_your_input'];
$completed = ['visibility' => 'client', 'task_type' => 'client_required', 'status' => 'completed'];
$internal = ['visibility' => 'internal', 'task_type' => 'client_required', 'status' => 'needs_your_input'];
check('requires_client_action true for client_required+needs_your_input',
    OCD_Tasks::requires_client_action($cri),
    $results
);
check('requires_client_action false for completed task',
    !OCD_Tasks::requires_client_action($completed),
    $results
);
check('requires_client_action false for internal-visibility task',
    !OCD_Tasks::requires_client_action($internal),
    $results
);

// Entitlement product map round-trip with variations.
$map_in  = [
    '42'  => [
        'slug'  => 'analytics-pro',
        'label' => 'Analytics Pro',
        'variations' => [
            '101' => ['slug' => 'analytics-pro-monthly', 'label' => 'Analytics Pro · Monthly'],
            '102' => ['slug' => 'analytics-pro-annual',  'label' => 'Analytics Pro · Annual', 'required_steps' => ['brand-assets']],
        ],
        'required_steps' => ['onboarding-call'],
    ],
    'bad' => ['slug' => ''],
];
$map_out = OCD_Entitlements::set_product_map($map_in);
check('product map drops invalid rows and keeps valid ones',
    isset($map_out[42]) && $map_out[42]['slug'] === 'analytics-pro' && !isset($map_out['bad']) && !isset($map_out[0]),
    $results
);
check('product map preserves variations',
    isset($map_out[42]['variations'][101], $map_out[42]['variations'][102])
    && $map_out[42]['variations'][102]['slug'] === 'analytics-pro-annual',
    $results
);
$f = OCD_Entitlements::feature_for_product(42);
check('feature_for_product returns parent slug when no variation supplied', is_array($f) && $f['slug'] === 'analytics-pro', $results);
$fv = OCD_Entitlements::feature_for_product(42, 102);
check('feature_for_product returns variation slug when variation supplied', is_array($fv) && $fv['slug'] === 'analytics-pro-annual', $results);
check('feature_for_product variation overrides required_steps',
    is_array($fv) && in_array('brand-assets', $fv['required_steps'], true),
    $results
);
$fv_unknown = OCD_Entitlements::feature_for_product(42, 999);
check('feature_for_product falls back to parent when variation unknown',
    is_array($fv_unknown) && $fv_unknown['slug'] === 'analytics-pro',
    $results
);
$nope = OCD_Entitlements::feature_for_product(999999);
check('feature_for_product returns null for unmapped products', $nope === null, $results);

// Line item extraction with sub-selection meta.
$item = [
    'product_id'   => 42,
    'variation_id' => 102,
    'name'         => 'Analytics Pro - Annual',
    'meta_data'    => [
        ['key' => 'pa_billing-cycle', 'value' => 'annual'],
        ['key' => 'Keywords', 'value' => 'site speed, audit'],
        ['key' => '_internal_thing', 'value' => 'should-not-show'],
    ],
];
$ext = OCD_Entitlements::extract_line_item($item);
check('line item extraction preserves variation_id', $ext['variation_id'] === 102, $results);
check('line item extraction surfaces pa_ attributes', ($ext['attributes']['billing-cycle'] ?? '') === 'annual', $results);
check('line item extraction keeps display meta', ($ext['meta']['Keywords'] ?? '') === 'site speed, audit', $results);
check('line item extraction strips underscore-prefixed private meta', !isset($ext['meta']['_internal_thing']), $results);

// Instruction-media validator.
$loom = OCD_Instruction_Media::validate_video_url('https://www.loom.com/share/abc123');
check('Loom share URL is accepted', is_array($loom) && $loom['provider'] === 'loom' && $loom['embed_url'] === 'https://www.loom.com/embed/abc123', $results);
$yt   = OCD_Instruction_Media::validate_video_url('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
check('YouTube watch URL is accepted', is_array($yt) && $yt['provider'] === 'youtube' && $yt['id'] === 'dQw4w9WgXcQ', $results);
$ytb  = OCD_Instruction_Media::validate_video_url('https://youtu.be/dQw4w9WgXcQ');
check('youtu.be short URL is accepted', is_array($ytb) && $ytb['provider'] === 'youtube', $results);
$vimeo = OCD_Instruction_Media::validate_video_url('https://vimeo.com/76979871');
check('Vimeo URL is accepted', is_array($vimeo) && $vimeo['provider'] === 'vimeo' && $vimeo['embed_url'] === 'https://player.vimeo.com/video/76979871', $results);
$evil1 = OCD_Instruction_Media::validate_video_url('https://attacker.example.com/share/abc');
check('Unknown provider rejected', is_wp_error($evil1) && $evil1->get_error_code() === 'ocd_media_provider', $results);
$evil2 = OCD_Instruction_Media::validate_video_url('javascript:alert(1)');
check('Non-https URL rejected', is_wp_error($evil2), $results);
$evil3 = OCD_Instruction_Media::validate_video_url('https://www.loom.com/share/<script>');
check('Loom URL with non-alnum id rejected', is_wp_error($evil3), $results);
$evil4 = OCD_Instruction_Media::validate_image_url('https://attacker.example.com/img.png');
check('Image URL on non-local host rejected', is_wp_error($evil4), $results);
$ok_img = OCD_Instruction_Media::validate_image_url('https://example.com/wp-content/uploads/2024/img.png');
check('Image URL on local host accepted', is_array($ok_img) && $ok_img['provider'] === 'local', $results);

// Project files: MIME + size validation.
check('project file MIME allow-list contains image/png',
    OCD_Project_Files::is_allowed_mime('image/png'),
    $results
);
check('project file MIME allow-list does NOT contain application/x-php',
    !OCD_Project_Files::is_allowed_mime('application/x-php'),
    $results
);
$too_big = OCD_Project_Files::validate_upload('huge.pdf', 'application/pdf', OCD_Project_Files::MAX_BYTES + 1);
check('project file >MAX_BYTES rejected', is_wp_error($too_big) && $too_big->get_error_code() === 'ocd_file_too_large', $results);
$bad_mime = OCD_Project_Files::validate_upload('script.php', 'application/x-php', 1024);
check('project file with disallowed MIME rejected', is_wp_error($bad_mime) && $bad_mime->get_error_code() === 'ocd_file_mime', $results);
$empty = OCD_Project_Files::validate_upload('a.png', 'image/png', 0);
check('empty project file rejected', is_wp_error($empty), $results);
$ok = OCD_Project_Files::validate_upload('logo.png', 'image/png', 1024);
check('valid image+size accepted', $ok === true, $results);
check('FOLDERS contain intake/working/deliverables/archive',
    OCD_Project_Files::FOLDERS === ['intake', 'working', 'deliverables', 'archive'],
    $results
);
check('approval states contain pending/approved/rejected/not_required',
    OCD_Project_Files::APPROVAL_STATES === ['pending', 'approved', 'rejected', 'not_required'],
    $results
);
check('download_url goes through REST permission-checked endpoint, never raw path',
    OCD_Project_Files::download_url(99) === 'https://example.com/wp-json/ocd/v1/files/99/download',
    $results
);

// Billing eligibility logic — no card data ever flows through this plugin.
$active_with_renewal = [
    'id'                    => 7,
    'status'                => 'active',
    'payment_method'        => 'stripe',
    'next_payment_date_gmt' => '2026-05-01T00:00:00',
];
$change = OCD_Billing::subscription_payment_eligibility($active_with_renewal);
check('change-payment available for active sub with auto gateway + future renewal',
    $change['can_change_payment'] === true && strpos($change['change_payment_url'], 'change_payment_method=7') !== false,
    $results
);
$pending_cancel = ['id' => 8, 'status' => 'pending-cancel', 'payment_method' => 'stripe', 'next_payment_date_gmt' => '2026-05-01'];
$change2 = OCD_Billing::subscription_payment_eligibility($pending_cancel);
check('change-payment unavailable for pending-cancel', !$change2['can_change_payment'] && is_string($change2['reason']), $results);
$no_gateway = ['id' => 9, 'status' => 'active', 'payment_method' => '', 'next_payment_date_gmt' => '2026-05-01'];
$change3 = OCD_Billing::subscription_payment_eligibility($no_gateway);
check('change-payment unavailable when no automatic gateway attached', !$change3['can_change_payment'], $results);
$no_renewal = ['id' => 10, 'status' => 'active', 'payment_method' => 'stripe'];
$change4 = OCD_Billing::subscription_payment_eligibility($no_renewal);
check('change-payment unavailable when no future payment scheduled', !$change4['can_change_payment'], $results);
check('payment-methods URL points at native /my-account/payment-methods/',
    strpos(OCD_Billing::payment_methods_url(), '/my-account/payment-methods/') !== false,
    $results
);

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
