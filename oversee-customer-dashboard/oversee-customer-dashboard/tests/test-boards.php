<?php
/**
 * Lightweight verification harness for the new board / role / SSO surfaces.
 *
 * Run with: php oversee-customer-dashboard/oversee-customer-dashboard/tests/test-boards.php
 *
 * No real WordPress install required — we stub just enough of WP to load
 * the new classes and exercise their pure logic. Network calls are not made.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

$GLOBALS['__test_options'] = [];
$GLOBALS['__test_user_meta'] = [];
$GLOBALS['__test_roles'] = [];
$GLOBALS['__test_filters'] = [];
$GLOBALS['__test_actions'] = [];
$GLOBALS['__test_posts'] = [];
$GLOBALS['__test_post_meta'] = [];

function __t_err($code, $message, $data = []) { return new WP_Error($code, $message, $data); }

class WP_Error {
    private $code; private $message; private $data;
    public function __construct($c = '', $m = '', $d = []) { $this->code = $c; $this->message = $m; $this->data = $d; }
    public function get_error_message() { return $this->message; }
    public function get_error_code()    { return $this->code; }
}

function get_option($k, $default = false) { return $GLOBALS['__test_options'][$k] ?? $default; }
function update_option($k, $v) { $GLOBALS['__test_options'][$k] = $v; return true; }
function delete_option($k) { unset($GLOBALS['__test_options'][$k]); return true; }
function add_action($hook, $cb = null, $p = 10, $a = 1) { $GLOBALS['__test_actions'][$hook][] = $cb; }
function add_filter($hook, $cb = null, $p = 10, $a = 1) { $GLOBALS['__test_filters'][$hook][] = $cb; }
function apply_filters($hook, $value) {
    $args = func_get_args();
    array_shift($args);
    foreach (($GLOBALS['__test_filters'][$hook] ?? []) as $cb) {
        $args[0] = call_user_func_array($cb, $args);
    }
    return $args[0];
}
function do_action($hook, ...$args) {
    foreach (($GLOBALS['__test_actions'][$hook] ?? []) as $cb) {
        call_user_func_array($cb, $args);
    }
}
function add_shortcode($t, $cb) {}
function register_setting(...$a) {}
function register_activation_hook(...$a) {}
function register_rest_route(...$a) {}
function register_post_type(...$a) {}
function register_post_meta(...$a) {}
function plugin_dir_path($f) { return dirname($f) . '/'; }
function plugin_dir_url($f) { return 'https://example.com/'; }
function plugin_basename($f) { return basename($f); }
function load_plugin_textdomain(...$a) {}
function flush_rewrite_rules(...$a) {}
function nocache_headers() {}
function language_attributes() { echo 'lang="en"'; }
function bloginfo($k) { echo 'Test'; }
function body_class($c = '') { echo 'class="' . esc_attr(is_string($c) ? $c : implode(' ', (array) $c)) . '"'; }
function wp_head() {}
function wp_footer() {}
function get_bloginfo($k) { return 'Test'; }
function get_userdata($id) {
    $u = $GLOBALS['__test_users'][$id] ?? null;
    return $u;
}
function wp_get_current_user() { return $GLOBALS['__test_users'][1] ?? null; }
function get_current_user_id() { return 1; }
function is_user_logged_in() { return true; }
function current_user_can($cap) { return !empty($GLOBALS['__test_caps'][$cap]); }
function update_user_meta($id, $k, $v) { $GLOBALS['__test_user_meta'][$id][$k] = $v; return true; }
function get_user_meta($id, $k, $single = true) { return $GLOBALS['__test_user_meta'][$id][$k] ?? ''; }
function sanitize_text_field($v) { return is_string($v) ? trim(strip_tags($v)) : $v; }
function sanitize_key($v) { return is_string($v) ? strtolower(preg_replace('/[^a-z0-9_-]/i', '', $v)) : ''; }
function sanitize_file_name($v) { return is_string($v) ? preg_replace('/[^A-Za-z0-9._-]+/', '-', $v) : 'file'; }
function sanitize_title($v) { return is_string($v) ? strtolower(preg_replace('/[^a-z0-9-]+/i', '-', $v)) : ''; }
function wp_kses_post($v) { return is_string($v) ? strip_tags($v, '<a><strong><em><br><p>') : $v; }
function wp_strip_all_tags($v) { return is_string($v) ? strip_tags($v) : $v; }
function trailingslashit($s) { return rtrim($s, '/') . '/'; }
function rest_url($p) { return 'https://example.com/wp-json/' . ltrim($p, '/'); }
function add_query_arg($args, $url) {
    $sep = strpos($url, '?') === false ? '?' : '&';
    return $url . $sep . http_build_query($args);
}
function home_url($p = '') { return 'https://example.com' . $p; }
function wp_json_encode($v) { return json_encode($v); }
function esc_url_raw($u) { return $u; }
function esc_url($u) { return $u; }
function esc_html__($t, $d = '') { return htmlspecialchars($t, ENT_QUOTES); }
function esc_html($t) { return htmlspecialchars($t, ENT_QUOTES); }
function esc_attr($t) { return htmlspecialchars($t, ENT_QUOTES); }
function __($t, $d = '') { return $t; }
function _e($t, $d = '') { echo $t; }
function is_wp_error($v) { return $v instanceof WP_Error; }
function current_time($t, $g = 0) { return $t === 'mysql' ? gmdate('Y-m-d H:i:s') : time(); }
function wp_date($f, $ts = null) { return date($f, $ts ?? time()); }
function wp_generate_password($len = 12, $special = false, $extra = false) { return substr(md5((string) microtime(true)), 0, (int) $len); }
function wp_remote_post(...$a) { return ['response' => ['code' => 0], 'body' => '']; }
function wp_remote_request(...$a) { return ['response' => ['code' => 0], 'body' => '']; }
function wp_remote_retrieve_body($r) { return is_array($r) ? ($r['body'] ?? '') : ''; }
function wp_remote_retrieve_response_code($r) { return is_array($r) ? ($r['response']['code'] ?? 0) : 0; }
function wp_mail(...$a) { $GLOBALS['__test_mails'][] = $a; return true; }
function wp_safe_redirect(...$a) {}
function wp_specialchars_decode($t, $f = ENT_QUOTES) { return $t; }
function wp_insert_post($args, $strict = false) {
    static $i = 100;
    $i++;
    $GLOBALS['__test_posts'][$i] = $args;
    return $i;
}
function get_post($id) {
    if (!isset($GLOBALS['__test_posts'][$id])) return null;
    $obj = (object) $GLOBALS['__test_posts'][$id];
    if (!isset($obj->ID)) $obj->ID = $id;
    return $obj;
}
function get_posts($q) { return $GLOBALS['__test_posts_query_result'] ?? []; }
function update_post_meta($pid, $k, $v) { $GLOBALS['__test_post_meta'][$pid][$k] = $v; return true; }
function get_post_meta($pid, $k, $single = true) { return $GLOBALS['__test_post_meta'][$pid][$k] ?? ''; }
function get_the_title($pid) { return $GLOBALS['__test_posts'][$pid]['post_title'] ?? ''; }

class WP_Role_Stub {
    public $name; public $capabilities = [];
    public function __construct($name, $caps = []) { $this->name = $name; $this->capabilities = $caps; }
    public function add_cap($c) { $this->capabilities[$c] = true; }
    public function remove_cap($c) { unset($this->capabilities[$c]); }
    public function has_cap($c) { return !empty($this->capabilities[$c]); }
}
function get_role($name) { return $GLOBALS['__test_roles'][$name] ?? null; }
function add_role($name, $label, $caps) {
    $GLOBALS['__test_roles'][$name] = new WP_Role_Stub($name, $caps);
    return $GLOBALS['__test_roles'][$name];
}
function remove_role($name) { unset($GLOBALS['__test_roles'][$name]); }

// Pre-create the standard roles so OCD_Roles::install can mirror caps.
$GLOBALS['__test_roles']['administrator'] = new WP_Role_Stub('administrator');
$GLOBALS['__test_roles']['customer'] = new WP_Role_Stub('customer');

// Stub $wpdb just enough for OCD_Boards::log + spawn calls.
class wpdbStub {
    public $prefix = 'wp_';
    public $insert_id = 0;
    public $rows = [];
    public $charset_collate = '';
    public function get_charset_collate() { return ''; }
    public function get_var($q) { return 0; }
    public function get_results($q, $output = null) { return []; }
    public function get_row($q, $output = null) { return null; }
    public function prepare($q, ...$args) { return $q; }
    public function insert($t, $row) {
        $this->insert_id++;
        $this->rows[$t][$this->insert_id] = $row;
        return 1;
    }
    public function update($t, $row, $where) { return 1; }
    public function query($q) { return 1; }
}
$GLOBALS['wpdb'] = new wpdbStub();

require_once __DIR__ . '/../includes/class-ocd-settings.php';
require_once __DIR__ . '/../includes/class-ocd-roles.php';
require_once __DIR__ . '/../includes/class-ocd-cpt.php';
require_once __DIR__ . '/../includes/class-ocd-board-schema.php';
require_once __DIR__ . '/../includes/class-ocd-highlevel.php';
require_once __DIR__ . '/../includes/class-ocd-highlevel-sso.php';
require_once __DIR__ . '/../includes/class-ocd-boards.php';
require_once __DIR__ . '/../includes/class-ocd-signup-hooks.php';

$results = [];
function check($label, $cond, &$results) {
    $results[] = [$label, (bool) $cond];
    echo ($cond ? '  ok ' : 'FAIL ') . $label . PHP_EOL;
}

OCD_Settings::install_defaults();

// ---------- Roles ----------
OCD_Roles::install();
check('oversee_client role created', isset($GLOBALS['__test_roles']['oversee_client']), $results);
check('oversee_account_manager role created', isset($GLOBALS['__test_roles']['oversee_account_manager']), $results);
check('oversee_specialist role created', isset($GLOBALS['__test_roles']['oversee_specialist']), $results);
check('oversee_contractor role created', isset($GLOBALS['__test_roles']['oversee_contractor']), $results);
check('oversee_admin role created', isset($GLOBALS['__test_roles']['oversee_admin']), $results);

check('client role has access_oversee_dashboard',
    $GLOBALS['__test_roles']['oversee_client']->has_cap(OCD_Roles::CAP_VIEW_DASHBOARD), $results);
check('admin role has manage_oversee_dashboard',
    $GLOBALS['__test_roles']['oversee_admin']->has_cap(OCD_Roles::CAP_MANAGE_DASHBOARD), $results);
check('specialist has work_oversee_boards',
    $GLOBALS['__test_roles']['oversee_specialist']->has_cap(OCD_Roles::CAP_WORK_BOARDS), $results);
check('account manager has manage_oversee_clients',
    $GLOBALS['__test_roles']['oversee_account_manager']->has_cap(OCD_Roles::CAP_MANAGE_CLIENTS), $results);
check('client role does NOT have manage_oversee_dashboard',
    !$GLOBALS['__test_roles']['oversee_client']->has_cap(OCD_Roles::CAP_MANAGE_DASHBOARD), $results);
check('contractor does NOT have manage_oversee_clients',
    !$GLOBALS['__test_roles']['oversee_contractor']->has_cap(OCD_Roles::CAP_MANAGE_CLIENTS), $results);
check('administrator received view + manage caps mirror',
    $GLOBALS['__test_roles']['administrator']->has_cap(OCD_Roles::CAP_VIEW_DASHBOARD)
    && $GLOBALS['__test_roles']['administrator']->has_cap(OCD_Roles::CAP_MANAGE_DASHBOARD),
    $results);
check('woocommerce customer role got view cap added',
    $GLOBALS['__test_roles']['customer']->has_cap(OCD_Roles::CAP_VIEW_DASHBOARD), $results);

// promote_to_client should add the role + cap to a real user object.
class FakeUser {
    public $ID; public $roles = []; public $first_name = ''; public $display_name = 'A B'; public $user_email = 'a@b.test'; public $user_login = 'a';
    private $caps = [];
    public function __construct($id, $roles = []) { $this->ID = $id; $this->roles = $roles; }
    public function add_role($r) { if (!in_array($r, $this->roles, true)) $this->roles[] = $r; }
    public function add_cap($c) { $this->caps[$c] = true; }
    public function has_cap($c) { return !empty($this->caps[$c]); }
}
$GLOBALS['__test_users'][7] = new FakeUser(7, ['customer']);
$ok = OCD_Roles::promote_to_client(7);
check('promote_to_client succeeded', $ok === true, $results);
check('promote_to_client added oversee_client role',
    in_array('oversee_client', $GLOBALS['__test_users'][7]->roles, true), $results);
check('promote_to_client kept original customer role (subscriptions hooks need it)',
    in_array('customer', $GLOBALS['__test_users'][7]->roles, true), $results);

// is_oversee_staff
$GLOBALS['__test_users'][1] = new FakeUser(1, ['oversee_admin']);
check('is_oversee_staff true for oversee_admin',  OCD_Roles::is_oversee_staff(1), $results);
$GLOBALS['__test_users'][1] = new FakeUser(1, ['oversee_client']);
check('is_oversee_staff false for oversee_client', !OCD_Roles::is_oversee_staff(1), $results);

// ---------- CPT SKU lookup ----------
$tpl_id = 200;
$GLOBALS['__test_posts'][$tpl_id] = ['post_title' => 'Web Audit', 'post_content' => '', 'ID' => $tpl_id];
$GLOBALS['__test_post_meta'][$tpl_id][OCD_CPT::META_TEMPLATE_SKUS] = 'web-audit, audit-pro;default';
$GLOBALS['__test_posts_query_result'] = [$tpl_id];
$found = OCD_CPT::find_template_for_sku('audit-pro');
check('CPT::find_template_for_sku finds template by exact match', $found === $tpl_id, $results);
$missed = OCD_CPT::find_template_for_sku('not-listed');
check('CPT::find_template_for_sku returns 0 for non-matching SKU', $missed === 0, $results);

// ---------- HighLevel SSO embed config: error paths ----------
$GLOBALS['__test_users'][1] = new FakeUser(1, ['oversee_client']);
$err = OCD_HighLevel_SSO::embed_config(1, 'conversations');
check('embed_config returns ocd_hl_not_configured when token missing',
    is_wp_error($err) && $err->get_error_code() === 'ocd_hl_not_configured', $results);

OCD_Settings::update(['highlevel_token' => 'tok', 'highlevel_location_id' => 'loc']);
$err2 = OCD_HighLevel_SSO::embed_config(1, 'conversations');
check('embed_config returns ocd_hl_no_contact when ohl_contact_id missing',
    is_wp_error($err2) && $err2->get_error_code() === 'ocd_hl_no_contact', $results);

OCD_HighLevel_SSO::set_contact_id(1, 'contact_xyz');
check('set_contact_id round-trips through user meta',
    OCD_HighLevel_SSO::get_contact_id(1) === 'contact_xyz', $results);

$err3 = OCD_HighLevel_SSO::embed_config(1, 'conversations');
check('embed_config returns ocd_hl_magic_link_unconfigured when endpoint missing',
    is_wp_error($err3) && $err3->get_error_code() === 'ocd_hl_magic_link_unconfigured', $results);

$err4 = OCD_HighLevel_SSO::embed_config(1, 'definitely-not-a-surface');
check('embed_config rejects unknown surface',
    is_wp_error($err4) && $err4->get_error_code() === 'ocd_hl_bad_surface', $results);

// Filter short-circuit.
add_filter('ocd_highlevel_magic_link_endpoint', function ($v, $contact_id, $surface) {
    return 'https://magic.example/issue';
}, 10, 3);
add_filter('ocd_highlevel_magic_link', function ($v, $contact_id, $surface, $endpoint) {
    return ['url' => 'https://hl.example/embed/' . $surface, 'expires_at' => '2030-01-01T00:00:00Z'];
}, 10, 4);
$cfg = OCD_HighLevel_SSO::embed_config(1, 'conversations');
check('embed_config returns embed_url when filters short-circuit',
    is_array($cfg) && $cfg['embed_url'] === 'https://hl.example/embed/conversations', $results);

// ---------- Boards skeleton resolution ----------
$skeleton = OCD_Boards::resolve_skeleton(0);
check('resolve_skeleton(0) returns the default Kickoff group',
    isset($skeleton['groups'][0]['title']) && stripos($skeleton['groups'][0]['title'], 'Kickoff') !== false, $results);
$tpl_with_skeleton = 201;
$GLOBALS['__test_posts'][$tpl_with_skeleton] = [
    'post_title' => 'Custom',
    'post_content' => json_encode(['groups' => [
        ['title' => 'Sprint 1', 'items' => [['title' => 'Audit']]],
    ]]),
    'ID' => $tpl_with_skeleton,
];
$skel2 = OCD_Boards::resolve_skeleton($tpl_with_skeleton);
check('resolve_skeleton parses JSON template post_content',
    $skel2['groups'][0]['title'] === 'Sprint 1' && $skel2['groups'][0]['items'][0]['title'] === 'Audit', $results);

// Default columns include status/assignee/due_date.
$cols = OCD_Boards::default_columns();
$slugs = array_column($cols, 'slug');
check('default_columns includes status/assignee/due_date',
    in_array('status', $slugs, true) && in_array('assignee', $slugs, true) && in_array('due_date', $slugs, true), $results);

// Spawn from template — error path: no owner.
$err5 = OCD_Boards::spawn_from_template([]);
check('spawn_from_template requires owner_user_id',
    is_wp_error($err5) && $err5->get_error_code() === 'ocd_boards_no_owner', $results);

// Happy path: spawn returns int board id.
$board_id = OCD_Boards::spawn_from_template([
    'owner_user_id' => 7,
    'template_post_id' => $tpl_with_skeleton,
    'sku' => 'audit-pro',
    'title' => 'Test board',
]);
check('spawn_from_template returns numeric board id', is_int($board_id) && $board_id > 0, $results);

// ---------- Variation meta extraction (re-uses existing OCD_Entitlements via test-bootstrap) ----------
// Cross-check the existing bootstrap covers this; here we just confirm the
// boards layer uses get_formatted_meta_data when present. (Smoke test.)

// ---------- Welcome email is sent on customer creation ----------
$GLOBALS['__test_mails'] = [];
$GLOBALS['__test_users'][9] = new FakeUser(9, ['customer']);
$GLOBALS['__test_users'][9]->user_email = 'newuser@example.com';
$GLOBALS['__test_users'][9]->display_name = 'New User';
OCD_Signup_Hooks::send_welcome_email(9);
check('send_welcome_email queued one email', count($GLOBALS['__test_mails']) === 1, $results);
check('welcome email uses Inter-styled HTML body',
    isset($GLOBALS['__test_mails'][0][2]) && strpos((string) $GLOBALS['__test_mails'][0][2], '#ff8201') !== false, $results);

$failed = array_filter($results, function ($r) { return !$r[1]; });
if (count($failed) > 0) {
    echo PHP_EOL . count($failed) . ' assertion(s) failed.' . PHP_EOL;
    exit(1);
}
echo PHP_EOL . count($results) . ' assertions passed.' . PHP_EOL;
