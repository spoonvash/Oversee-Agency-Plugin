<?php
/**
 * Commerce / variable-product extraction harness.
 *
 * Verifies OCD_Commerce against shimmed WooCommerce product objects that
 * mirror the real Oversee catalog (variable-subscription with several
 * attributes). No live WooCommerce required.
 *
 * Run: php oversee-customer-dashboard/tests/test-commerce.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// --- Minimal WordPress / WooCommerce shims ---
function get_option($k, $default = false)   { return $default; }
function update_option($k, $v)              { return true; }
function add_action(...$a)                  {}
function add_filter(...$a)                  {}
function add_shortcode(...$a)               {}
function register_setting(...$a)            {}
function register_activation_hook(...$a)    {}
function register_rest_route(...$a)         {}
function plugin_dir_path($f)                { return dirname($f) . '/'; }
function plugin_dir_url($f)                 { return 'https://example.com/'; }
function load_plugin_textdomain(...$a)      {}
function flush_rewrite_rules(...$a)         {}
function sanitize_text_field($v)            { return is_string($v) ? trim(strip_tags($v)) : $v; }
function sanitize_key($v)                   { return is_string($v) ? strtolower(preg_replace('/[^a-z0-9_-]/i', '', $v)) : ''; }
function sanitize_title($v)                 { return is_string($v) ? strtolower(preg_replace('/[^a-z0-9-]+/i', '-', trim($v, ' '))) : ''; }
function wp_strip_all_tags($v)              { return is_string($v) ? strip_tags($v) : $v; }
function wp_kses_post($v)                   { return is_string($v) ? strip_tags($v, '<a><strong><em><br><p><ul><li>') : $v; }
function trailingslashit($s)                { return rtrim($s, '/') . '/'; }
function home_url($p = '')                  { return 'https://example.com' . $p; }
function add_query_arg($args, $url)         { $sep = strpos($url, '?') === false ? '?' : '&'; return $url . $sep . http_build_query($args); }
function get_permalink($id)                 { return 'https://example.com/?p=' . (int) $id; }
function __($t, $d = '')                    { return $t; }
function is_wp_error($v)                    { return $v instanceof WP_Error; }
function get_the_terms($id, $tax)           { return []; }

if (!class_exists('WP_Error')) {
    class WP_Error {
        public $code; public $message; public $data;
        public function __construct($c = '', $m = '', $d = []) { $this->code = $c; $this->message = $m; $this->data = $d; }
        public function get_error_message() { return $this->message; }
        public function get_error_code()    { return $this->code; }
    }
}

// --- WooCommerce shims ---

$GLOBALS['__wc_products'] = [];

function wc_get_cart_url()                  { return 'https://example.com/cart/'; }
function wc_get_checkout_url()              { return 'https://example.com/checkout/'; }
function wc_get_product($id)                { return $GLOBALS['__wc_products'][(int) $id] ?? null; }
function wc_get_products($args)             {
    $type = $args['type'] ?? null;
    $out  = [];
    foreach ($GLOBALS['__wc_products'] as $p) {
        if ($p->get_status() !== 'publish') continue;
        // Skip variations — they show up in wc_get_product() lookups but never
        // in the storefront listing.
        if (in_array($p->get_type(), ['variation', 'subscription_variation'], true)) continue;
        if ($type && !in_array($p->get_type(), (array) $type, true)) continue;
        $out[] = $p;
    }
    return $out;
}
function wc_attribute_label($name, $product = null) { return ucwords(str_replace(['pa_', '-', '_'], ['', ' ', ' '], $name)); }
function wc_placeholder_img_src($size = 'medium')   { return 'https://example.com/placeholder.png'; }
function wp_get_attachment_image_url($id, $size)    { return 'https://example.com/img/' . $id . '.jpg'; }

class WC_Product_Stub_Attribute {
    private $name; private $options; private $variation; private $taxonomy;
    public function __construct($name, $options, $variation = true, $taxonomy = true) {
        $this->name = $name; $this->options = $options; $this->variation = $variation; $this->taxonomy = $taxonomy;
    }
    public function get_name() { return $this->name; }
    public function get_options() { return $this->options; }
    public function get_variation() { return $this->variation; }
    public function is_taxonomy() { return $this->taxonomy; }
    public function get_terms() {
        $out = [];
        foreach ($this->options as $o) {
            $t = new stdClass();
            $t->slug = sanitize_title((string) $o);
            $t->name = (string) $o;
            $out[] = $t;
        }
        return $out;
    }
}

class WC_Product_Stub {
    public $id; public $type; public $name; public $status;
    public $price; public $regular_price; public $price_html;
    public $purchasable = true; public $in_stock = true; public $on_sale = false;
    public $sku = ''; public $short_description = ''; public $description = '';
    public $children = [];
    public $attributes = [];
    public $available_variations = [];
    public $meta = [];
    public function __construct($id, $type, $name) {
        $this->id = $id; $this->type = $type; $this->name = $name; $this->status = 'publish';
        $this->price_html = '$0.00';
    }
    public function get_id() { return $this->id; }
    public function get_type() { return $this->type; }
    public function get_name() { return $this->name; }
    public function get_slug() { return sanitize_title($this->name); }
    public function get_status() { return $this->status; }
    public function get_price() { return $this->price; }
    public function get_regular_price() { return $this->regular_price; }
    public function get_price_html() { return $this->price_html; }
    public function get_image_id() { return 0; }
    public function get_short_description() { return $this->short_description; }
    public function get_description() { return $this->description; }
    public function get_sku() { return $this->sku; }
    public function get_gallery_image_ids() { return []; }
    public function get_children() { return $this->children; }
    public function get_attributes() { return $this->attributes; }
    public function get_available_variations() { return $this->available_variations; }
    public function get_meta($k, $single = true) { return $this->meta[$k] ?? ''; }
    public function is_purchasable() { return $this->purchasable; }
    public function is_in_stock() { return $this->in_stock; }
    public function is_on_sale() { return $this->on_sale; }
    public function is_type($t) {
        if (is_array($t)) return in_array($this->type, $t, true);
        return $this->type === $t;
    }
    public function add_to_cart_url() { return 'https://example.com/?add-to-cart=' . $this->id; }
}

// --- Mirror the real Oversee catalog ---
//
// Video Commercial Advertisement (variable, simple Length attribute).
$video = new WC_Product_Stub(15858, 'variable', 'Video Commercial Advertisement');
$video->price_html = '$5,000.00 – $13,000.00';
$video->attributes = [new WC_Product_Stub_Attribute('pa_length-of-commercial', ['30 Seconds', '60 Seconds', '90 Seconds'])];
$video->available_variations = [
    ['variation_id' => 15859, 'attributes' => ['attribute_pa_length-of-commercial' => '30-seconds'], 'is_in_stock' => true, 'is_purchasable' => true, 'display_price' => 5000.0],
    ['variation_id' => 15860, 'attributes' => ['attribute_pa_length-of-commercial' => '60-seconds'], 'is_in_stock' => true, 'is_purchasable' => true, 'display_price' => 9000.0],
    ['variation_id' => 15861, 'attributes' => ['attribute_pa_length-of-commercial' => '90-seconds'], 'is_in_stock' => true, 'is_purchasable' => true, 'display_price' => 13000.0],
];
$video->children = [15859, 15860, 15861];

// SEO (variable-subscription, three attributes).
$seo = new WC_Product_Stub(9732, 'variable-subscription', 'Search Engine Optimization');
$seo->price_html = 'From: $200.00 / month';
$seo->attributes = [
    new WC_Product_Stub_Attribute('pa_on-page-optimized-pages', ['0', '5', '10']),
    new WC_Product_Stub_Attribute('pa_blog-posts-or-pages', ['0', '1', '2', '3']),
    new WC_Product_Stub_Attribute('pa_backlinks', ['0', '10', '20', '50']),
];
$seo->available_variations = [
    ['variation_id' => 16722, 'attributes' => ['attribute_pa_on-page-optimized-pages' => '5', 'attribute_pa_blog-posts-or-pages' => '1', 'attribute_pa_backlinks' => '10'], 'is_in_stock' => true, 'is_purchasable' => true, 'display_price' => 400.0],
];
$seo->children = [16722];
$seo->meta = ['_subscription_period' => 'month', '_subscription_period_interval' => 1, '_subscription_length' => 0];

// Plain simple subscription (no variants).
$tiktok = new WC_Product_Stub(14110, 'subscription', 'Tiktok Ads');
$tiktok->price_html = '$250.00 / month';
$tiktok->price = '250.00';

// Variation products — wc_get_product($variation_id) must return a real WC_Product.
$variation_specs = [
    [15859, 'variation', '$5,000.00',  5000.0],
    [15860, 'variation', '$9,000.00',  9000.0],
    [15861, 'variation', '$13,000.00', 13000.0],
    [16722, 'subscription_variation', '$400.00 / month', 400.0],
];
$variations = [];
foreach ($variation_specs as [$vid, $vtype, $html, $price]) {
    $v = new WC_Product_Stub($vid, $vtype, 'Variation #' . $vid);
    $v->price_html = $html;
    $v->price = (string) $price;
    $variations[$vid] = $v;
}

$GLOBALS['__wc_products'] = [
    $video->id => $video,
    $seo->id   => $seo,
    $tiktok->id => $tiktok,
] + $variations;

require_once __DIR__ . '/../includes/class-ocd-commerce.php';

// --- Run assertions ---
$results = [];
function check($label, $cond, &$results) {
    $results[] = [$label, (bool) $cond];
    echo ($cond ? '  ok ' : 'FAIL ') . $label . PHP_EOL;
}

// Listing payload includes all three products.
$listing = OCD_Commerce::products(['per_page' => 100]);
check('listing reports available=true with WC active', $listing['available'] === true, $results);
check('listing returns three products from the shimmed catalog', count($listing['products']) === 3, $results);

$by_id = [];
foreach ($listing['products'] as $p) $by_id[$p['id']] = $p;

check('video commercial is detected as variable', $by_id[15858]['is_variable'] === true && $by_id[15858]['type'] === 'variable', $results);
check('video commercial requires_selection (no naked add-to-cart)', $by_id[15858]['requires_selection'] === true && $by_id[15858]['add_to_cart'] === '', $results);
check('SEO is detected as subscription + variable', $by_id[9732]['is_subscription'] === true && $by_id[9732]['is_variable'] === true, $results);
check('SEO subscription summary surfaces period + interval', is_array($by_id[9732]['subscription']) && $by_id[9732]['subscription']['period'] === 'month' && $by_id[9732]['subscription']['interval'] === 1, $results);
check('Tiktok simple subscription has add_to_cart URL', strpos($by_id[14110]['add_to_cart'], 'add-to-cart=14110') !== false, $results);
check('listing exposes cart_url and checkout_url', $listing['cart_url'] === 'https://example.com/cart/' && $listing['checkout_url'] === 'https://example.com/checkout/', $results);

// SEO attributes payload (3 selectors with options).
$seo_attrs = $by_id[9732]['attributes'];
check('SEO surfaces three variation attributes', count($seo_attrs) === 3, $results);
$keys = array_column($seo_attrs, 'key');
check('attribute keys are slugified (no pa_ prefix)',
    in_array('on-page-optimized-pages', $keys, true)
    && in_array('blog-posts-or-pages', $keys, true)
    && in_array('backlinks', $keys, true),
    $results
);

// Detail payload includes variations.
$detail = OCD_Commerce::product_detail(15858);
check('product_detail returns variations array', is_array($detail['variations']) && count($detail['variations']) === 3, $results);
check('variations carry attribute slugs',
    isset($detail['variations'][0]['attributes']['length-of-commercial']) && $detail['variations'][0]['attributes']['length-of-commercial'] === '30-seconds',
    $results
);

// Unknown product → 404.
$missing = OCD_Commerce::product_detail(999999);
check('unknown product returns WP_Error 404', is_wp_error($missing) && $missing->get_error_code() === 'ocd_unknown_product', $results);

// Variation resolver: valid input matches the right variation_id.
$ok = OCD_Commerce::resolve_variation(15858, ['length-of-commercial' => '60 Seconds']);
check('resolve_variation matches by human label',
    is_array($ok) && $ok['variation_id'] === 15860 && isset($ok['add_to_cart']),
    $results
);

$ok2 = OCD_Commerce::resolve_variation(15858, ['attribute_pa_length-of-commercial' => '30-seconds']);
check('resolve_variation matches by wc-prefixed key',
    is_array($ok2) && $ok2['variation_id'] === 15859,
    $results
);

// Variation resolver: missing attribute -> WP_Error with status 400.
$missing_attr = OCD_Commerce::resolve_variation(9732, ['on-page-optimized-pages' => '5']);
check('resolve_variation rejects missing attribute',
    is_wp_error($missing_attr) && $missing_attr->get_error_code() === 'ocd_missing_attribute',
    $results
);

// Variation resolver: invalid value -> WP_Error.
$bad_val = OCD_Commerce::resolve_variation(15858, ['length-of-commercial' => '500 Seconds']);
check('resolve_variation rejects invalid value',
    is_wp_error($bad_val) && $bad_val->get_error_code() === 'ocd_invalid_attribute_value',
    $results
);

// Variation resolver: combination doesn't exist -> WP_Error.
$nope = OCD_Commerce::resolve_variation(9732, [
    'on-page-optimized-pages' => '10',
    'blog-posts-or-pages'     => '3',
    'backlinks'               => '50',
]);
check('resolve_variation rejects combination not in available_variations',
    is_wp_error($nope) && $nope->get_error_code() === 'ocd_unknown_variation',
    $results
);

// Variation resolver refuses simple (non-variable) products.
$tiktok_sub = OCD_Commerce::resolve_variation(14110, []);
check('resolve_variation refuses simple products',
    is_wp_error($tiktok_sub) && $tiktok_sub->get_error_code() === 'ocd_not_variable',
    $results
);

// Variation add-to-cart URL must include variation_id + attributes for the WC native cart.
$cart_url = $ok['add_to_cart'];
check('variation add-to-cart URL carries variation_id', strpos($cart_url, 'variation_id=15860') !== false, $results);
check('variation add-to-cart URL carries attribute_pa_ key', strpos($cart_url, 'attribute_pa_length-of-commercial=60-seconds') !== false, $results);
check('variation add-to-cart URL targets /cart/', strpos($cart_url, '/cart/') !== false, $results);

// Listing filter: search.
$searched = OCD_Commerce::products(['per_page' => 100, 'type' => 'variable']);
// The shim respects type filter only.
check('type filter narrows results to variable products', count($searched['products']) === 1 && $searched['products'][0]['id'] === 15858, $results);

// WooCommerce-disabled path is covered by test-bootstrap.php (OCD_Store).
$failed = array_filter($results, function ($r) { return !$r[1]; });
if (count($failed) > 0) {
    echo PHP_EOL . count($failed) . ' assertion(s) failed.' . PHP_EOL;
    exit(1);
}
echo PHP_EOL . count($results) . ' assertions passed.' . PHP_EOL;
