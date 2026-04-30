<?php
/**
 * Verifies the dashboard shell hardening introduced alongside the full-bleed
 * page template + role-aware boot fixes.
 *
 * Covers:
 *   - OCD_Assets::derive_role() returns admin/customer/guest from caps
 *   - OCD_Assets::runtime_config() returns the keys the SPA boot shim reads
 *   - OCD_Shortcodes capability-based mount role rewrite
 *   - OCD_Page_Template registers and resolves the full-bleed template
 *
 * Run with: php oversee-customer-dashboard/tests/test-dashboard-shell.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// --- Minimal WordPress shims (mirrors test-bootstrap.php style) ---
$GLOBALS['__ocd_options']   = [];
$GLOBALS['__ocd_user_meta'] = [];
$GLOBALS['__ocd_post_meta'] = [];
$GLOBALS['__ocd_caps']      = [];
$GLOBALS['__ocd_logged_in'] = false;
$GLOBALS['__ocd_pages']     = [];

function get_option($k, $default = false)         { return $GLOBALS['__ocd_options'][$k] ?? $default; }
function update_option($k, $v, $autoload = true)  { $GLOBALS['__ocd_options'][$k] = $v; return true; }
function add_action(...$a)                        {}
function add_filter(...$a)                        {}
function add_shortcode(...$a)                     {}
function register_setting(...$a)                  {}
function register_activation_hook(...$a)          {}
function register_rest_route(...$a)               {}
function plugin_dir_path($f)                      { return dirname($f) . '/'; }
function plugin_dir_url($f)                       { return 'https://example.com/'; }
function plugin_basename($f)                      { return basename($f); }
function load_plugin_textdomain(...$a)            {}
function __($s, $d = null)                        { return $s; }
function _e($s, $d = null)                        { echo $s; }
function esc_html__($s, $d = null)                { return $s; }
function esc_html_e($s, $d = null)                { echo $s; }
function esc_attr($s)                             { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_url_raw($s)                          { return $s; }
function esc_url($s)                              { return $s; }
function rest_url($p = '')                        { return 'https://example.com/wp-json/' . ltrim($p, '/'); }
function home_url($p = '/')                       { return 'https://example.com' . $p; }
function wp_create_nonce($a)                      { return 'nonce_' . md5($a); }
function wp_logout_url($r)                        { return 'https://example.com/wp-login.php?action=logout&redirect_to=' . urlencode($r); }
function wp_login_url($r = '')                    { return 'https://example.com/wp-login.php?redirect_to=' . urlencode($r); }
function is_user_logged_in()                      { return (bool) $GLOBALS['__ocd_logged_in']; }
function current_user_can($cap)                   { return !empty($GLOBALS['__ocd_caps'][$cap]); }
function get_post_meta($id, $k, $single = false)  { return $GLOBALS['__ocd_post_meta'][$id][$k] ?? ''; }
function update_post_meta($id, $k, $v)            { $GLOBALS['__ocd_post_meta'][$id][$k] = $v; return true; }
function get_page_by_path($slug)                  {
    foreach ($GLOBALS['__ocd_pages'] as $p) {
        if ($p->post_name === $slug) return $p;
    }
    return null;
}
function get_permalink($id)                       { return 'https://example.com/?p=' . (int) $id; }
function wp_get_current_user() {
    $u = new stdClass();
    $u->ID            = $GLOBALS['__ocd_logged_in'] ? 42 : 0;
    $u->display_name  = 'Test User';
    $u->user_email    = 'test@example.com';
    $u->roles         = $GLOBALS['__ocd_logged_in'] ? ['subscriber'] : [];
    return $u;
}
function wc_get_page_permalink($p)                { return 'https://example.com/' . $p . '/'; }
function wc_get_cart_url()                        { return 'https://example.com/cart/'; }
function wc_get_checkout_url()                    { return 'https://example.com/checkout/'; }
function wp_json_encode($v)                       { return json_encode($v); }
function wp_style_is(...$a)                       { return false; }
function wp_register_style(...$a)                 {}
function wp_enqueue_style(...$a)                  {}
function wp_register_script(...$a)                {}
function wp_enqueue_script(...$a)                 {}
function wp_localize_script(...$a)                {}

// Plugin constants matching the bootstrap from oversee-customer-dashboard.php
if (!defined('OCD_VERSION'))   define('OCD_VERSION',   '2.0.0');
if (!defined('OCD_FILE'))      define('OCD_FILE',      __FILE__);
if (!defined('OCD_DIR'))       define('OCD_DIR',       dirname(__DIR__) . '/');
if (!defined('OCD_URL'))       define('OCD_URL',       'https://example.com/wp-content/plugins/oversee-customer-dashboard/');
if (!defined('OCD_INCLUDES'))  define('OCD_INCLUDES',  OCD_DIR . 'includes');
if (!defined('OCD_TEMPLATES')) define('OCD_TEMPLATES', OCD_DIR . 'templates');

// Provide a stub for OCD_REST_API constant used in runtime_config.
class OCD_REST_API { const NAMESPACE = 'oversee/v1'; }

require_once OCD_INCLUDES . '/class-ocd-assets.php';
require_once OCD_INCLUDES . '/class-ocd-shortcodes.php';
require_once OCD_INCLUDES . '/class-ocd-page-template.php';

$assertions = 0;
function assert_true($condition, $label) {
    global $assertions;
    if (!$condition) {
        echo "  FAIL $label\n";
        exit(1);
    }
    $assertions++;
    echo "  ok $label\n";
}

// --- derive_role ---
$GLOBALS['__ocd_logged_in'] = false;
$GLOBALS['__ocd_caps']      = [];
assert_true(OCD_Assets::derive_role() === 'guest', 'logged-out user is guest');

$GLOBALS['__ocd_logged_in'] = true;
$GLOBALS['__ocd_caps']      = [];
assert_true(OCD_Assets::derive_role() === 'customer', 'logged-in non-staff is customer');

$GLOBALS['__ocd_caps'] = ['manage_woocommerce' => true];
assert_true(OCD_Assets::derive_role() === 'admin', 'manage_woocommerce promotes to admin');

$GLOBALS['__ocd_caps'] = ['manage_options' => true];
assert_true(OCD_Assets::derive_role() === 'admin', 'manage_options promotes to admin');

// --- runtime_config keys ---
$GLOBALS['__ocd_logged_in'] = true;
$GLOBALS['__ocd_caps']      = ['manage_options' => true];
$cfg = OCD_Assets::runtime_config();
foreach (['restUrl','overseeRestUrl','nonce','isAdmin','isLoggedIn','role','pluginUrl','dashboardUrl','currentUser'] as $k) {
    assert_true(array_key_exists($k, $cfg), "runtime_config exposes $k");
}
assert_true($cfg['isAdmin'] === true,    'runtime_config isAdmin true for staff');
assert_true($cfg['isLoggedIn'] === true, 'runtime_config isLoggedIn true');
assert_true($cfg['role'] === 'admin',    'runtime_config role admin for staff');

// --- shortcode role injection ---
$reflection = new ReflectionClass('OCD_Shortcodes');
$method     = $reflection->getMethod('inject_mount_role');
$method->setAccessible(true);

$html = '<div id="oversee-dashboard-root" data-oversee-mount="dashboard" data-ocd-role="customer"></div>';
$out  = $method->invoke(null, $html, 'admin');
assert_true(strpos($out, 'data-ocd-role="admin"') !== false,  'mount role rewritten to admin');
assert_true(strpos($out, 'data-ocd-role="customer"') === false, 'old customer role removed');

$out2 = $method->invoke(null, $html, 'guest');
assert_true(strpos($out2, 'data-ocd-role="guest"') !== false, 'mount role can become guest');

$out3 = $method->invoke(null, $html, 'totally-bogus');
assert_true(strpos($out3, 'data-ocd-role="customer"') !== false, 'bogus role falls back to customer');

// --- page template registration & resolution ---
$registered = OCD_Page_Template::register_template([], null, null, 'page');
assert_true(isset($registered[OCD_Page_Template::TEMPLATE_FILE]), 'template registered in theme_page_templates');
assert_true($registered[OCD_Page_Template::TEMPLATE_FILE] === OCD_Page_Template::TEMPLATE_LABEL, 'template label correct');

assert_true(file_exists(OCD_TEMPLATES . '/' . OCD_Page_Template::TEMPLATE_FILE), 'template file exists on disk');

// --- auto-assign behavior ---
$GLOBALS['__ocd_pages'] = [(object) [
    'ID'        => 7,
    'post_name' => 'dashboard',
]];
$GLOBALS['__ocd_options'] = [];
$GLOBALS['__ocd_post_meta'] = [];

// Re-declare is_singular / get_queried_object late since they aren't needed
// for force_assign_on_activation.
OCD_Page_Template::force_assign_on_activation();
assert_true(get_post_meta(7, '_wp_page_template', true) === OCD_Page_Template::TEMPLATE_FILE,
    'force_assign_on_activation sets the template on dashboard page');
assert_true(get_option(OCD_Page_Template::ASSIGNED_FLAG) !== false,
    'force_assign_on_activation records the one-shot flag');

// maybe_assign_template should NOT clobber an editor's manual selection.
$GLOBALS['__ocd_options'] = [];
$GLOBALS['__ocd_post_meta'] = [7 => ['_wp_page_template' => 'custom-template.php']];
OCD_Page_Template::maybe_assign_template();
assert_true(get_post_meta(7, '_wp_page_template', true) === 'custom-template.php',
    'maybe_assign_template respects existing non-default template selection');

echo "\n$assertions assertions passed.\n";
