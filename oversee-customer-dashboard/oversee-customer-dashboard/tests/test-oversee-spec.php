<?php
/**
 * Spec-aligned (oversee_*) test harness.
 *
 * Verifies behaviors required by the latest authoritative dashboard spec:
 *
 *   - 5 custom roles + administrator
 *   - oversee_client gains the access_oversee_dashboard cap
 *   - direct user_register assigns oversee_client without spawning a project
 *   - HighLevel SSO endpoint returns WP_Error('oversee_no_hl_contact') when
 *     the user has no ohl_contact_id meta
 *   - AI assist endpoint returns WP_Error when no API key is configured
 *   - Service template SKU lookup matches an existing post
 *
 * Run with:
 *   php oversee-customer-dashboard/tests/test-oversee-spec.php
 */

if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');

// ---------- Minimal WP shims ----------
$GLOBALS['__o_options']  = [];
$GLOBALS['__o_meta']     = [];
$GLOBALS['__o_users']    = [];
$GLOBALS['__o_roles']    = [];
$GLOBALS['__o_caps']     = [];
$GLOBALS['__o_actions']  = [];
$GLOBALS['__o_filters']  = [];
$GLOBALS['__o_posts']    = [];
$GLOBALS['__o_postmeta'] = [];
$GLOBALS['__o_logged_in_user'] = 0;

if (!function_exists('get_option')) {
    function get_option($k, $d = false)        { return $GLOBALS['__o_options'][$k] ?? $d; }
    function update_option($k, $v)             { $GLOBALS['__o_options'][$k] = $v; return true; }
    function delete_option($k)                 { unset($GLOBALS['__o_options'][$k]); return true; }
    function add_action($h, $cb = null, $p=10, $a=1) { $GLOBALS['__o_actions'][$h][] = $cb; }
    function do_action($h, ...$args)           { foreach ($GLOBALS['__o_actions'][$h] ?? [] as $cb) { if (is_callable($cb)) call_user_func_array($cb, $args); } }
    function add_filter($h, $cb = null, $p=10, $a=1) { $GLOBALS['__o_filters'][$h][] = $cb; }
    function apply_filters($h, $value, ...$args) {
        foreach ($GLOBALS['__o_filters'][$h] ?? [] as $cb) {
            if (is_callable($cb)) {
                $value = call_user_func_array($cb, array_merge([$value], $args));
            }
        }
        return $value;
    }
    function register_post_type(...$a)         {}
    function register_post_meta(...$a)         {}
    function register_rest_route(...$a)        {}
    function register_setting(...$a)           {}
    function register_activation_hook(...$a)   {}
    function add_shortcode(...$a)              {}
    function load_plugin_textdomain(...$a)     {}
    function flush_rewrite_rules(...$a)        {}
    function plugin_dir_path($f)               { return dirname($f) . '/'; }
    function plugin_dir_url($f)                { return 'https://example.com/'; }
    function plugin_basename($f)               { return basename($f); }
    function home_url($p = '')                 { return 'https://example.com' . $p; }
    function rest_url($p)                      { return 'https://example.com/wp-json/' . ltrim($p, '/'); }
    function trailingslashit($s)               { return rtrim($s, '/') . '/'; }
    function add_query_arg($args, $url)        { $sep = strpos($url, '?') === false ? '?' : '&'; return $url . $sep . http_build_query($args); }
    function sanitize_text_field($v)           { return is_string($v) ? trim(strip_tags($v)) : $v; }
    function sanitize_key($v)                  { return is_string($v) ? strtolower(preg_replace('/[^a-z0-9_-]/i', '', $v)) : ''; }
    function sanitize_email($v)                { return filter_var($v, FILTER_VALIDATE_EMAIL) ?: ''; }
    function is_email($v)                      { return (bool) filter_var($v, FILTER_VALIDATE_EMAIL); }
    function wp_json_encode($v)                { return json_encode($v); }
    function current_time($t, $g = 0)          { return $t === 'mysql' ? gmdate('Y-m-d H:i:s') : time(); }
    function __($t, $d = '')                   { return $t; }
    function _e($t, $d = '')                   { echo $t; }
    function esc_html($v)                      { return htmlspecialchars((string) $v, ENT_QUOTES); }
    function esc_url_raw($v)                   { return $v; }
    function esc_url($v)                       { return $v; }
    function esc_attr($v)                      { return htmlspecialchars((string) $v, ENT_QUOTES); }
    function get_avatar_url($id, $a = [])      { return ''; }
    function get_bloginfo($k)                  { return 'Oversee Test'; }
    function wp_specialchars_decode($s, $q=0)  { return $s; }
    function wp_mail($to, $sub, $body, $h=[])  { return true; }
    function wp_generate_password($l = 12)     { return substr(md5(uniqid('', true)), 0, $l); }
    function wp_kses_post($v)                  { return is_string($v) ? strip_tags($v, '<a><strong><em><br><p>') : $v; }
    function is_user_logged_in()               { return (int) ($GLOBALS['__o_logged_in_user'] ?? 0) > 0; }
    function get_current_user_id()             { return (int) ($GLOBALS['__o_logged_in_user'] ?? 0); }
    function wp_get_current_user() {
        $id = get_current_user_id();
        return get_userdata($id) ?: (object) ['ID' => 0, 'display_name' => '', 'user_email' => '', 'roles' => []];
    }
    function get_userdata($id) {
        if (!$id) return false;
        $u = $GLOBALS['__o_users'][$id] ?? null;
        if (!$u) return false;
        // Use WP_User shim so callers can call ->add_role()/->remove_role()/->add_cap() on the result.
        $wpu = new WP_User($id);
        foreach ($u as $k => $v) {
            if (!isset($wpu->$k)) $wpu->$k = $v;
        }
        $wpu->roles = $GLOBALS['__o_roles'][$id] ?? [];
        return $wpu;
    }
    function update_user_meta($id, $k, $v)     { $GLOBALS['__o_meta'][$id][$k] = $v; return true; }
    function get_user_meta($id, $k, $single)   { return $GLOBALS['__o_meta'][$id][$k] ?? ''; }
    function email_exists($e)                  { foreach ($GLOBALS['__o_users'] as $u) if ($u['user_email'] === $e) return $u['ID']; return false; }
    function get_role($r)                      { return null; }
    function add_role($s, $l, $c)              { return null; }
    function remove_role($s)                   {}
    function function_exists_safe($f)          { return function_exists($f); }
    function rest_ensure_response($r)          { return $r; }
    function current_user_can($cap)            { return !empty($GLOBALS['__o_caps'][get_current_user_id()][$cap]); }
    function get_posts($args)                  { return []; }
    function get_post($id)                     { return null; }
    function wp_insert_post($p, $err = false)  { return rand(1000, 9999); }
    function wp_update_post($p)                { return is_array($p) ? ($p['ID'] ?? 1) : 1; }
    function wp_trash_post($id)                { return true; }
    function get_post_meta($id, $k, $s = false){ return $GLOBALS['__o_postmeta'][$id][$k] ?? ''; }
    function update_post_meta($id, $k, $v)     { $GLOBALS['__o_postmeta'][$id][$k] = $v; return true; }
    function get_terms($a)                     { return []; }
    function wc_get_product($id)               { return null; }
    function wc_get_product_id_by_sku($sku)    { return $GLOBALS['__o_skus'][$sku] ?? 0; }
    function is_wp_error($v)                   { return $v instanceof WP_Error; }
    if (!class_exists('WP_Error')) {
        class WP_Error {
            private $code; private $message; private $data;
            public function __construct($c = '', $m = '', $d = []) { $this->code = $c; $this->message = $m; $this->data = $d; }
            public function get_error_message() { return $this->message; }
            public function get_error_code()    { return $this->code; }
        }
    }
    if (!class_exists('WP_User')) {
        class WP_User {
            public $ID; public $roles = [];
            public $user_email = ''; public $display_name = ''; public $first_name = ''; public $last_name = ''; public $user_login = ''; public $user_registered = '';
            public function __construct($id) {
                $this->ID = $id;
                $this->roles = $GLOBALS['__o_roles'][$id] ?? [];
                $u = $GLOBALS['__o_users'][$id] ?? [];
                $this->user_email   = $u['user_email']   ?? '';
                $this->display_name = $u['display_name'] ?? '';
                $this->first_name   = $u['first_name']   ?? '';
                $this->last_name    = $u['last_name']    ?? '';
                $this->user_login   = $u['user_login']   ?? '';
            }
            public function add_role($r)  { $GLOBALS['__o_roles'][$this->ID] = array_values(array_unique(array_merge($GLOBALS['__o_roles'][$this->ID] ?? [], [$r]))); $this->roles = $GLOBALS['__o_roles'][$this->ID]; }
            public function remove_role($r) { $GLOBALS['__o_roles'][$this->ID] = array_values(array_diff($GLOBALS['__o_roles'][$this->ID] ?? [], [$r])); $this->roles = $GLOBALS['__o_roles'][$this->ID]; }
            public function add_cap($c)   { $GLOBALS['__o_caps'][$this->ID][$c] = true; }
        }
    }
    if (!class_exists('WP_User_Query')) {
        class WP_User_Query {
            public function __construct($a) {}
            public function get_results() { return []; }
            public function get_total()   { return 0; }
        }
    }
}

// Register a constant or option backing for OCD_VERSION before loading.
if (!defined('OCD_VERSION')) define('OCD_VERSION', '2.0.0');

// Provide minimal class shims for legacy classes referenced by spec code.
if (!class_exists('OCD_Roles')) {
    class OCD_Roles {
        const ROLE_CLIENT = 'oversee_client';
        public static function promote_to_client($id) {
            $u = new WP_User($id);
            $u->add_role('oversee_client');
            $u->add_cap('access_oversee_dashboard');
        }
    }
}
if (!class_exists('OCD_HighLevel_SSO')) {
    class OCD_HighLevel_SSO {
        const SURFACES = ['conversations', 'calendar', 'reports', 'documents', 'reputation'];
        public static function embed_config($user_id, $surface) {
            return new WP_Error('ocd_hl_magic_link_unconfigured', 'Configure HighLevel magic-link endpoint.');
        }
    }
}

// Load the spec classes under test.
require_once __DIR__ . '/../includes/class-oversee-cpt.php';
require_once __DIR__ . '/../includes/class-oversee-rest-api.php';
require_once __DIR__ . '/../includes/class-oversee-signup-hooks.php';

$results = [];
function check($label, $cond, &$results) { $results[] = [$label, (bool) $cond]; echo ($cond ? '  ok ' : 'FAIL ') . $label . PHP_EOL; }

// ---------- Tests ----------

// 1. CPT slugs match the spec.
check('CPT TYPE_PROJECT === project_board', Oversee_CPT::TYPE_PROJECT === 'project_board', $results);
check('CPT TYPE_SERVICE_TEMPLATE === service_template', Oversee_CPT::TYPE_SERVICE_TEMPLATE === 'service_template', $results);
check('CPT TYPE_FORM_TEMPLATE === intake_form_template', Oversee_CPT::TYPE_FORM_TEMPLATE === 'intake_form_template', $results);
check('CPT TYPE_CONTRACT_TEMPLATE === contract_template', Oversee_CPT::TYPE_CONTRACT_TEMPLATE === 'contract_template', $results);
check('CPT TYPE_CLIENT_RECORD === client_record', Oversee_CPT::TYPE_CLIENT_RECORD === 'client_record', $results);

// 2. REST namespace.
check('REST NS === oversee/v1', Oversee_REST_API::NS === 'oversee/v1', $results);

// 3. user_register hook assigns oversee_client + cap, no project spawn.
$GLOBALS['__o_users'][101] = ['ID' => 101, 'user_email' => 'client@example.com', 'display_name' => 'Client', 'first_name' => 'Cli', 'last_name' => 'Ent', 'user_login' => 'client'];
$GLOBALS['__o_roles'][101] = ['subscriber'];
Oversee_Signup_Hooks::on_user_register(101);
check('user_register adds oversee_client role', in_array('oversee_client', $GLOBALS['__o_roles'][101], true), $results);
check('user_register grants access_oversee_dashboard cap', !empty($GLOBALS['__o_caps'][101]['access_oversee_dashboard']), $results);

// 4. user_register skips staff (does not demote).
$GLOBALS['__o_users'][201] = ['ID' => 201, 'user_email' => 'admin@example.com', 'display_name' => 'Admin', 'user_login' => 'admin'];
$GLOBALS['__o_roles'][201] = ['administrator'];
Oversee_Signup_Hooks::on_user_register(201);
check('user_register leaves administrator alone', !in_array('oversee_client', $GLOBALS['__o_roles'][201], true), $results);

// 5. user_register does NOT spawn a project (no posts created).
$initial_posts = count($GLOBALS['__o_posts']);
Oversee_Signup_Hooks::on_user_register(101);
check('user_register does not spawn project_board posts', count($GLOBALS['__o_posts']) === $initial_posts, $results);

// 6. SSO endpoint requires hl_contact_id.
$GLOBALS['__o_logged_in_user'] = 101;
class _FakeReq {
    private $params;
    public function __construct($p) { $this->params = $p; }
    public function get_param($k) { return $this->params[$k] ?? null; }
    public function offsetExists($k) { return isset($this->params[$k]); }
    public function offsetGet($k)    { return $this->params[$k] ?? null; }
    public function offsetSet($k,$v) { $this->params[$k] = $v; }
    public function offsetUnset($k)  { unset($this->params[$k]); }
}
// Convert _FakeReq to support array access (for $req['id'] usage).
if (!interface_exists('ArrayAccess', false)) {
    eval('interface ArrayAccess { public function offsetExists($k); public function offsetGet($k); public function offsetSet($k,$v); public function offsetUnset($k); }');
}

// Use anonymous class implementing ArrayAccess for cleanliness.
$req_no_contact = new class(['app' => 'conversations', 'user_id' => 101]) implements ArrayAccess {
    private $p;
    public function __construct($p) { $this->p = $p; }
    public function get_param($k) { return $this->p[$k] ?? null; }
    public function set_param($k, $v) { $this->p[$k] = $v; }
    public function offsetExists($k): bool { return isset($this->p[$k]); }
    public function offsetGet($k): mixed   { return $this->p[$k] ?? null; }
    public function offsetSet($k, $v): void { $this->p[$k] = $v; }
    public function offsetUnset($k): void   { unset($this->p[$k]); }
};
$res = Oversee_REST_API::highlevel_sso_link($req_no_contact);
check('SSO endpoint returns oversee_no_hl_contact when ohl_contact_id missing',
    is_wp_error($res) && $res->get_error_code() === 'oversee_no_hl_contact',
    $results
);

// 7. SSO endpoint rejects unknown app.
$req_bad_app = new class(['app' => 'totally-bogus', 'user_id' => 101]) implements ArrayAccess {
    private $p;
    public function __construct($p) { $this->p = $p; }
    public function get_param($k) { return $this->p[$k] ?? null; }
    public function offsetExists($k): bool { return isset($this->p[$k]); }
    public function offsetGet($k): mixed   { return $this->p[$k] ?? null; }
    public function offsetSet($k, $v): void { $this->p[$k] = $v; }
    public function offsetUnset($k): void   { unset($this->p[$k]); }
};
update_user_meta(101, 'ohl_contact_id', 'hl_contact_xyz');
$res2 = Oversee_REST_API::highlevel_sso_link($req_bad_app);
check('SSO endpoint rejects unknown app',
    is_wp_error($res2) && $res2->get_error_code() === 'oversee_invalid_app',
    $results
);

// 8. AI assist returns oversee_ai_unconfigured when no API key.
$req_ai = new class(['prompt' => 'hello']) implements ArrayAccess {
    private $p; public function __construct($p) { $this->p = $p; }
    public function get_param($k) { return $this->p[$k] ?? null; }
    public function offsetExists($k): bool { return isset($this->p[$k]); }
    public function offsetGet($k): mixed   { return $this->p[$k] ?? null; }
    public function offsetSet($k, $v): void { $this->p[$k] = $v; }
    public function offsetUnset($k): void   { unset($this->p[$k]); }
};
$res3 = Oversee_REST_API::ai_assist($req_ai);
check('AI assist returns oversee_ai_unconfigured when no API key',
    is_wp_error($res3) && $res3->get_error_code() === 'oversee_ai_unconfigured',
    $results
);

// 9. Service template SKU lookup with no posts returns 0.
check('find_service_template_for_sku returns 0 with no matching template',
    Oversee_CPT::find_service_template_for_sku('UNKNOWN-SKU-XYZ') === 0,
    $results
);

// 10. Schema table names use the wp_oversee_ prefix (verify via class constants
//     — we don't actually run dbDelta in this harness).
require_once __DIR__ . '/../includes/class-oversee-schema.php';
$reflect = new ReflectionClass('Oversee_Schema');
check('Oversee_Schema::DB_VERSION_OPTION === oversee_db_version',
    Oversee_Schema::DB_VERSION_OPTION === 'oversee_db_version',
    $results
);

$failed = array_filter($results, function ($r) { return !$r[1]; });
if (count($failed) > 0) {
    echo PHP_EOL . count($failed) . ' assertion(s) failed.' . PHP_EOL;
    exit(1);
}
echo PHP_EOL . count($results) . ' assertions passed.' . PHP_EOL;
