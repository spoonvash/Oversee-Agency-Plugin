<?php
/**
 * Verifies that OCD_Assets:
 *   1) registers an early `wp_enqueue_scripts` handler that detects the
 *      dashboard page or a page containing one of our shortcodes and
 *      invokes enqueue_frontend() before the shortcode itself is rendered;
 *   2) localizes OCD_CONFIG with the keys the SPA / inline JS expects
 *      (restUrl, overseeRestUrl, nonce, isLoggedIn, isAdmin, role, mountRole,
 *      currentUser, dashboardUrl, pluginUrl).
 *
 * Run with:
 *   php oversee-customer-dashboard/tests/test-assets-early-enqueue.php
 */

if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');

// ---------- Minimal WP shims ----------
$GLOBALS['__a_actions']   = [];
$GLOBALS['__a_filters']   = [];
$GLOBALS['__a_styles_reg']     = [];
$GLOBALS['__a_styles_enq']     = [];
$GLOBALS['__a_scripts_enq']    = [];
$GLOBALS['__a_localized']      = [];
$GLOBALS['__a_current_post']   = null;
$GLOBALS['__a_is_page_match']  = null;
$GLOBALS['__a_logged_in']      = true;
$GLOBALS['__a_caps']           = [];

function add_action($h, $cb = null, $p = 10, $a = 1) { $GLOBALS['__a_actions'][$h][] = ['cb' => $cb, 'p' => $p]; }
function do_action_priority($h) {
    $list = $GLOBALS['__a_actions'][$h] ?? [];
    usort($list, fn($x, $y) => $x['p'] <=> $y['p']);
    foreach ($list as $row) { if (is_callable($row['cb'])) call_user_func($row['cb']); }
}
function add_filter($h, $cb = null, $p = 10, $a = 1) { $GLOBALS['__a_filters'][$h][] = $cb; }
function is_admin() { return false; }
function is_page($slug = '') { return $GLOBALS['__a_is_page_match'] === $slug; }
function get_post($id = null) { return $GLOBALS['__a_current_post']; }
function has_shortcode($content, $tag) { return is_string($content) && strpos($content, '[' . $tag) !== false; }
function wp_register_style($h, $src, $deps = [], $ver = false) { $GLOBALS['__a_styles_reg'][$h] = compact('src', 'deps', 'ver'); }
function wp_style_is($h, $list = 'enqueued') { return $list === 'registered' ? isset($GLOBALS['__a_styles_reg'][$h]) : isset($GLOBALS['__a_styles_enq'][$h]); }
function wp_enqueue_style($h, $src = '', $deps = [], $ver = false) { $GLOBALS['__a_styles_enq'][$h] = compact('src', 'deps', 'ver'); }
function wp_enqueue_script($h, $src = '', $deps = [], $ver = false, $in_footer = false) { $GLOBALS['__a_scripts_enq'][$h] = compact('src', 'deps', 'ver'); }
function wp_localize_script($h, $object_name, $data) { $GLOBALS['__a_localized'][$object_name] = $data; }
function wp_create_nonce($action) { return 'nonce_' . md5($action); }
function wp_logout_url($redir = '') { return 'https://example.com/wp-login.php?action=logout'; }
function wp_get_current_user() {
    $u = new stdClass();
    $u->ID = 7;
    $u->display_name = 'Test User';
    $u->user_email = 'test@example.com';
    $u->roles = ['oversee_client'];
    return $u;
}
function is_user_logged_in() { return (bool) $GLOBALS['__a_logged_in']; }
function current_user_can($cap) { return in_array($cap, $GLOBALS['__a_caps'], true); }
function home_url($p = '') { return 'https://example.com' . $p; }
function rest_url($p) { return 'https://example.com/wp-json/' . ltrim($p, '/'); }
function esc_url_raw($u) { return $u; }
function esc_url($u) { return $u; }
function esc_attr($v) { return htmlspecialchars((string) $v, ENT_QUOTES); }
function plugin_dir_path($f) { return dirname($f) . '/'; }
function plugin_dir_url($f)  { return 'https://example.com/wp-content/plugins/oversee-customer-dashboard/'; }

// Plugin constants (mirroring main bootstrap).
if (!defined('OCD_VERSION'))  define('OCD_VERSION', 'test');
if (!defined('OCD_DIR'))      define('OCD_DIR', dirname(__DIR__) . '/');
if (!defined('OCD_URL'))      define('OCD_URL', 'https://example.com/wp-content/plugins/oversee-customer-dashboard/');

// Stub the REST_API namespace constant the runtime config reads from.
if (!class_exists('OCD_REST_API')) {
    class OCD_REST_API { const NAMESPACE = 'ocd/v1'; }
}

require_once __DIR__ . '/../includes/class-ocd-assets.php';

$results = [];
function check($label, $cond, &$results) {
    $results[] = [$label, (bool) $cond];
    echo ($cond ? '  ok ' : 'FAIL ') . $label . PHP_EOL;
}

// --- Boot the asset wiring exactly as the plugin would ---
OCD_Assets::init();

// 1) The early enqueue hook is registered on wp_enqueue_scripts at priority 1
//    (must run before page builders late-render shortcodes).
$priorities = array_map(fn($r) => $r['p'], $GLOBALS['__a_actions']['wp_enqueue_scripts'] ?? []);
check('early enqueue hook registers at priority 1', in_array(1, $priorities, true), $results);

// 2) Page slug `dashboard` triggers enqueue_frontend without rendering shortcode.
$GLOBALS['__a_is_page_match'] = 'dashboard';
$GLOBALS['__a_current_post']  = null;
do_action_priority('wp_enqueue_scripts');

check('dashboard page triggers oversee-spa script enqueue (with build manifest)',
    isset($GLOBALS['__a_scripts_enq']['oversee-spa']) || !file_exists(OCD_DIR . 'assets/build/.vite/manifest.json'),
    $results
);
check('OCD_CONFIG localized for dashboard page',
    isset($GLOBALS['__a_localized']['OCD_CONFIG']),
    $results
);
check('OVERSEE_CONFIG aliased for dashboard page',
    isset($GLOBALS['__a_localized']['OVERSEE_CONFIG']),
    $results
);

$cfg = $GLOBALS['__a_localized']['OCD_CONFIG'] ?? [];
foreach (['restUrl', 'overseeRestUrl', 'nonce', 'isLoggedIn', 'isAdmin', 'role', 'mountRole', 'currentUser', 'dashboardUrl', 'pluginUrl'] as $key) {
    check('OCD_CONFIG exposes ' . $key, array_key_exists($key, $cfg), $results);
}
check('OCD_CONFIG.role for non-admin logged-in user is "customer"', ($cfg['role'] ?? null) === 'customer', $results);
check('OCD_CONFIG.dashboardUrl points at /dashboard/', strpos((string) ($cfg['dashboardUrl'] ?? ''), '/dashboard/') !== false, $results);
check('OCD_CONFIG.pluginUrl points at the plugin folder', strpos((string) ($cfg['pluginUrl'] ?? ''), 'oversee-customer-dashboard') !== false, $results);

// 3) Shortcode-content fallback also triggers enqueue, even when slug doesn't match.
//    Reset enqueue state and re-bootstrap to clear the static "$enqueued" guard.
$GLOBALS['__a_styles_enq']  = [];
$GLOBALS['__a_scripts_enq'] = [];
$GLOBALS['__a_localized']   = [];
$GLOBALS['__a_actions']     = [];
$GLOBALS['__a_filters']     = [];
$GLOBALS['__a_is_page_match'] = null;
$post = new stdClass();
$post->post_content = 'Welcome! [oversee_customer_dashboard] later in the body.';
$GLOBALS['__a_current_post'] = $post;

// Reset the static $enqueued guard via reflection (so the second run actually enqueues).
$ref = new ReflectionClass('OCD_Assets');
$prop = $ref->getProperty('enqueued');
$prop->setAccessible(true);
$prop->setValue(null, false);

OCD_Assets::init();
do_action_priority('wp_enqueue_scripts');

check('shortcode-only page (no /dashboard/ slug) still triggers OCD_CONFIG localize',
    isset($GLOBALS['__a_localized']['OCD_CONFIG']),
    $results
);

// 4) Non-dashboard, non-shortcode page must NOT enqueue (no needless inflation).
$GLOBALS['__a_styles_enq']  = [];
$GLOBALS['__a_scripts_enq'] = [];
$GLOBALS['__a_localized']   = [];
$GLOBALS['__a_actions']     = [];
$GLOBALS['__a_filters']     = [];
$GLOBALS['__a_is_page_match'] = 'about';
$other = new stdClass();
$other->post_content = 'Just a marketing page.';
$GLOBALS['__a_current_post'] = $other;
$prop->setValue(null, false);
OCD_Assets::init();
do_action_priority('wp_enqueue_scripts');

check('non-dashboard page does NOT auto-enqueue SPA',
    !isset($GLOBALS['__a_localized']['OCD_CONFIG']) && !isset($GLOBALS['__a_scripts_enq']['oversee-spa']),
    $results
);

$failed = array_filter($results, fn($r) => !$r[1]);
if (count($failed) > 0) {
    echo PHP_EOL . count($failed) . ' assertion(s) failed.' . PHP_EOL;
    exit(1);
}
echo PHP_EOL . count($results) . ' assertions passed.' . PHP_EOL;
