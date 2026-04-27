<?php
/**
 * Oversee commerce facade. Surfaces *existing* WooCommerce products
 * (simple, variable, subscription, variable-subscription) for the
 * dashboard SPA. Never creates, modifies, or fakes products.
 *
 * Responsible for converting native WC product objects into a
 * SPA-friendly payload that mirrors what the live store displays:
 * variable products expose their attributes/variations so the
 * frontend can require selection before adding to cart, and
 * variable-subscription products preserve their billing interval +
 * duration so the cart/checkout/billing payloads stay accurate.
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class OCD_Commerce {

    /**
     * Returns the lightweight product list for the storefront.
     *
     * @param array $args  Optional. {
     *   @type int    $per_page   Defaults to 50, capped at 100.
     *   @type string $search     Free-text search.
     *   @type string $category   Category slug.
     *   @type string $type       Filter by product type.
     * }
     * @return array {
     *   @type bool   $available
     *   @type string $reason
     *   @type array  $products   Listing rows.
     *   @type string $cart_url
     *   @type string $checkout_url
     * }
     */
    public static function products($args = []) {
        $cart_url     = function_exists('wc_get_cart_url') ? wc_get_cart_url() : '';
        $checkout_url = function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : '';

        if (!self::wc_loaded()) {
            return [
                'available'    => false,
                'reason'       => __('WooCommerce is not active. Activate WooCommerce to surface real Oversee products in the dashboard.', 'oversee-customer-dashboard'),
                'products'     => [],
                'cart_url'     => '',
                'checkout_url' => '',
            ];
        }

        $defaults = [
            'per_page' => 50,
            'search'   => '',
            'category' => '',
            'type'     => '',
        ];
        $args = array_merge($defaults, $args);

        $query = [
            'status'  => 'publish',
            'limit'   => max(1, min(100, (int) $args['per_page'])),
            'orderby' => 'date',
            'order'   => 'DESC',
            'return'  => 'objects',
        ];
        $search = trim((string) $args['search']);
        if ($search !== '') {
            $query['s'] = $search;
        }
        $category = trim((string) $args['category']);
        if ($category !== '') {
            $query['category'] = [$category];
        }
        $type = trim((string) $args['type']);
        if ($type !== '') {
            $query['type'] = [$type];
        }

        $products = wc_get_products($query);
        $rows     = [];
        foreach ($products as $product) {
            if (!$product) continue;
            if (!$product->is_purchasable() && !$product->is_type('variable') && !$product->is_type('variable-subscription')) {
                continue;
            }
            $rows[] = self::present_product($product);
        }

        if (empty($rows)) {
            return [
                'available'    => false,
                'reason'       => __('No published WooCommerce products match this filter yet.', 'oversee-customer-dashboard'),
                'products'     => [],
                'cart_url'     => $cart_url,
                'checkout_url' => $checkout_url,
            ];
        }

        return [
            'available'    => true,
            'reason'       => '',
            'products'     => $rows,
            'cart_url'     => $cart_url,
            'checkout_url' => $checkout_url,
        ];
    }

    /**
     * Returns full detail (including all variations) for a single product.
     *
     * @param int $product_id
     * @return array|WP_Error
     */
    public static function product_detail($product_id) {
        if (!self::wc_loaded()) {
            return new WP_Error('ocd_wc_inactive', __('WooCommerce is not active.', 'oversee-customer-dashboard'), ['status' => 503]);
        }
        $product = wc_get_product((int) $product_id);
        if (!$product) {
            return new WP_Error('ocd_unknown_product', __('Unknown product.', 'oversee-customer-dashboard'), ['status' => 404]);
        }
        $row = self::present_product($product, true);
        return $row;
    }

    /**
     * Resolves a chosen attributes set against a variable product to a
     * concrete variation_id. Returns WP_Error if the resolution fails or
     * the resulting variation isn't purchasable. This intentionally
     * delegates to WC's native variation matcher when available, so we
     * never invent a variation that doesn't exist on the live site.
     *
     * @param int   $product_id
     * @param array $attributes  ['pa_xyz' => 'value', 'pa_abc' => 'value']
     * @return array|WP_Error    ['variation_id' => int, 'attributes' => array, 'price_html' => string, 'add_to_cart' => string]
     */
    public static function resolve_variation($product_id, array $attributes) {
        if (!self::wc_loaded()) {
            return new WP_Error('ocd_wc_inactive', __('WooCommerce is not active.', 'oversee-customer-dashboard'), ['status' => 503]);
        }
        $product = wc_get_product((int) $product_id);
        if (!$product) {
            return new WP_Error('ocd_unknown_product', __('Unknown product.', 'oversee-customer-dashboard'), ['status' => 404]);
        }
        if (!self::is_variable($product)) {
            return new WP_Error('ocd_not_variable', __('Product is not a variable product.', 'oversee-customer-dashboard'), ['status' => 400]);
        }

        // Normalise + validate input attributes against the product.
        $normalized = self::normalise_attribute_input($product, $attributes);
        if (is_wp_error($normalized)) return $normalized;

        $variation_id = 0;
        if (class_exists('WC_Data_Store') && method_exists('WC_Data_Store', 'load')) {
            // Native WC variation resolver — the same one /add-to-cart uses.
            $data_store   = WC_Data_Store::load('product');
            if (method_exists($data_store, 'find_matching_product_variation')) {
                $variation_id = (int) $data_store->find_matching_product_variation($product, $normalized);
            }
        }
        if (!$variation_id && method_exists($product, 'get_available_variations')) {
            // Fallback: scan available variations for an exact attributes match.
            foreach ($product->get_available_variations() as $av) {
                if (self::variation_matches($av, $normalized)) {
                    $variation_id = (int) $av['variation_id'];
                    break;
                }
            }
        }
        if (!$variation_id) {
            return new WP_Error('ocd_unknown_variation', __('No variation matches the selected options.', 'oversee-customer-dashboard'), ['status' => 404]);
        }

        $variation = wc_get_product($variation_id);
        if (!$variation || !$variation->is_purchasable()) {
            return new WP_Error('ocd_variation_unavailable', __('Selected variation is not currently purchasable.', 'oversee-customer-dashboard'), ['status' => 409]);
        }

        return [
            'variation_id' => $variation_id,
            'product_id'   => (int) $product->get_id(),
            'attributes'   => $normalized,
            'price_html'   => $variation->get_price_html(),
            'add_to_cart'  => self::variation_add_to_cart_url($product, $variation_id, $normalized),
            'permalink'    => get_permalink($product->get_id()),
        ];
    }

    /* ---------------- Presentation ---------------- */

    public static function present_product($product, $with_variations = false) {
        $type = $product->get_type();
        $is_variable = self::is_variable($product);

        $row = [
            'id'                 => (int) $product->get_id(),
            'name'               => $product->get_name(),
            'slug'               => $product->get_slug(),
            'type'               => $type,
            'is_variable'        => $is_variable,
            'is_subscription'    => self::is_subscription($product),
            'is_purchasable'     => $product->is_purchasable(),
            'in_stock'           => $product->is_in_stock(),
            'on_sale'            => $product->is_on_sale(),
            'sku'                => $product->get_sku(),
            'short_description'  => wp_strip_all_tags($product->get_short_description() ?: ''),
            'description'        => wp_kses_post($product->get_description() ?: ''),
            'price_html'         => $product->get_price_html(),
            'price'              => self::float_or_null($product->get_price()),
            'regular_price'      => self::float_or_null($product->get_regular_price()),
            'image'              => self::product_image($product, 'medium'),
            'image_large'        => self::product_image($product, 'large'),
            'images'             => self::gallery($product),
            'permalink'          => get_permalink($product->get_id()),
            'add_to_cart'        => $is_variable ? '' : self::add_to_cart_url($product),
            'categories'         => self::terms($product, 'product_cat'),
            'tags'               => self::terms($product, 'product_tag'),
            'attributes'         => $is_variable ? self::attributes_for_variable($product) : [],
            'subscription'       => self::subscription_summary($product),
            'requires_selection' => $is_variable,
        ];

        if ($with_variations && $is_variable) {
            $row['variations'] = self::variations_payload($product);
        } elseif ($is_variable) {
            // Lightweight summary so cards know how many options exist.
            $row['variation_count'] = (int) count($product->get_children());
        }

        return $row;
    }

    private static function attributes_for_variable($product) {
        $out = [];
        if (!method_exists($product, 'get_attributes')) {
            return $out;
        }
        foreach ($product->get_attributes() as $attribute) {
            // Only attributes used for variations are selectable.
            if (method_exists($attribute, 'get_variation') && !$attribute->get_variation()) {
                continue;
            }
            $name    = $attribute->get_name();
            $taxonomy = method_exists($attribute, 'is_taxonomy') && $attribute->is_taxonomy();
            $label   = function_exists('wc_attribute_label') ? wc_attribute_label($name, $product) : $name;
            $options = [];
            if ($taxonomy) {
                $terms = method_exists($attribute, 'get_terms') ? (array) $attribute->get_terms() : [];
                foreach ($terms as $term) {
                    $options[] = [
                        'slug'  => is_object($term) ? $term->slug : (string) $term,
                        'label' => is_object($term) ? $term->name : (string) $term,
                    ];
                }
            } else {
                foreach ((array) $attribute->get_options() as $val) {
                    $val = (string) $val;
                    $options[] = ['slug' => sanitize_title($val), 'label' => $val];
                }
            }
            $out[] = [
                'key'      => self::attribute_key($name),
                'name'     => $name,
                'label'    => $label,
                'taxonomy' => (bool) $taxonomy,
                'options'  => $options,
            ];
        }
        return $out;
    }

    private static function variations_payload($product) {
        if (!method_exists($product, 'get_available_variations')) return [];
        $rows = [];
        foreach ($product->get_available_variations() as $av) {
            $vid = (int) ($av['variation_id'] ?? 0);
            $variation = $vid ? wc_get_product($vid) : null;
            $attrs = [];
            foreach (($av['attributes'] ?? []) as $key => $val) {
                $attrs[self::attribute_key($key)] = (string) $val;
            }
            $rows[] = [
                'id'           => $vid,
                'attributes'   => $attrs,
                'price'        => isset($av['display_price']) ? (float) $av['display_price'] : ($variation ? self::float_or_null($variation->get_price()) : null),
                'price_html'   => $variation ? $variation->get_price_html() : '',
                'is_in_stock'  => !empty($av['is_in_stock']),
                'is_purchasable' => !empty($av['is_purchasable']),
                'subscription' => $variation ? self::subscription_summary($variation) : null,
                'add_to_cart'  => $variation ? self::variation_add_to_cart_url($product, $vid, $attrs) : '',
            ];
        }
        return $rows;
    }

    private static function subscription_summary($product) {
        if (!self::is_subscription($product)) return null;
        $period   = method_exists($product, 'get_meta') ? (string) $product->get_meta('_subscription_period', true) : '';
        $interval = method_exists($product, 'get_meta') ? (int) $product->get_meta('_subscription_period_interval', true) : 0;
        $length   = method_exists($product, 'get_meta') ? (int) $product->get_meta('_subscription_length', true) : 0;
        $trial    = method_exists($product, 'get_meta') ? (int) $product->get_meta('_subscription_trial_length', true) : 0;
        $signup   = method_exists($product, 'get_meta') ? self::float_or_null($product->get_meta('_subscription_sign_up_fee', true)) : null;
        return [
            'period'        => $period ?: 'month',
            'interval'      => $interval ?: 1,
            'length'        => $length,
            'trial_length'  => $trial,
            'sign_up_fee'   => $signup,
        ];
    }

    /* ---------------- Validators ---------------- */

    public static function is_variable($product) {
        return $product && ($product->is_type('variable') || $product->is_type('variable-subscription'));
    }

    public static function is_subscription($product) {
        if (!$product) return false;
        return $product->is_type('subscription') || $product->is_type('variable-subscription');
    }

    /**
     * Normalises the supplied attribute input to a [key => value] map keyed
     * by the same `attribute_*` keys WooCommerce uses internally for
     * variation lookups (`attribute_pa_<slug>` for taxonomy attributes,
     * `attribute_<slug>` for custom attributes).
     */
    public static function normalise_attribute_input($product, array $attributes) {
        $out = [];
        if (!method_exists($product, 'get_attributes')) {
            return new WP_Error('ocd_no_attrs', __('Product has no attributes.', 'oversee-customer-dashboard'), ['status' => 400]);
        }
        $product_attrs = $product->get_attributes();
        foreach ($product_attrs as $attr) {
            if (method_exists($attr, 'get_variation') && !$attr->get_variation()) {
                continue;
            }
            $name        = $attr->get_name();
            $key         = self::attribute_key($name);
            // Use the raw name (lowercased) so taxonomy attributes keep their
            // pa_<slug> form. Sanitize only the suffix to keep WC native lookups
            // working — sanitize_title would clobber the underscore in pa_.
            $wc_key      = 'attribute_' . strtolower($name);
            // Accept the value under either the bare slug, the wc-prefixed key or the human label.
            $val         = self::pick_value($attributes, [$key, $wc_key, $name, sanitize_title($name)]);
            if ($val === null || $val === '') {
                return new WP_Error('ocd_missing_attribute', sprintf(/* translators: %s: attribute label */ __('Please choose a %s.', 'oversee-customer-dashboard'), $key), ['status' => 400, 'attribute' => $key]);
            }
            // Validate value is one of the available options.
            $valid = false;
            $value = (string) $val;
            if (method_exists($attr, 'is_taxonomy') && $attr->is_taxonomy()) {
                foreach ((array) $attr->get_terms() as $term) {
                    if (is_object($term) && ($term->slug === $value || $term->name === $value)) {
                        $value = $term->slug;
                        $valid = true;
                        break;
                    }
                }
            } else {
                foreach ((array) $attr->get_options() as $opt) {
                    if ((string) $opt === $value || sanitize_title((string) $opt) === sanitize_title($value)) {
                        $value = (string) $opt;
                        $valid = true;
                        break;
                    }
                }
            }
            if (!$valid) {
                return new WP_Error('ocd_invalid_attribute_value', sprintf(/* translators: %s: attribute label */ __('Invalid value for %s.', 'oversee-customer-dashboard'), $key), ['status' => 400, 'attribute' => $key]);
            }
            $out[$wc_key] = $value;
        }
        return $out;
    }

    private static function variation_matches($av, $normalized) {
        if (empty($av['attributes'])) return false;
        foreach ($normalized as $k => $v) {
            $other = isset($av['attributes'][$k]) ? (string) $av['attributes'][$k] : null;
            if ($other === null) return false;
            // WC may return blank when the variation accepts "any" of an attribute.
            if ($other !== '' && strtolower($other) !== strtolower((string) $v)) {
                return false;
            }
        }
        return true;
    }

    private static function variation_add_to_cart_url($product, $variation_id, $attributes) {
        $base = function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/');
        $args = [
            'add-to-cart' => (int) $product->get_id(),
            'variation_id' => (int) $variation_id,
        ];
        foreach ($attributes as $k => $v) {
            $args[$k] = $v;
        }
        return add_query_arg($args, $base);
    }

    /* ---------------- Helpers ---------------- */

    private static function attribute_key($name) {
        // pa_select-number-of-webpages → select-number-of-webpages
        $key = (string) $name;
        if (strpos($key, 'pa_') === 0) {
            $key = substr($key, 3);
        }
        if (strpos($key, 'attribute_pa_') === 0) {
            $key = substr($key, strlen('attribute_pa_'));
        }
        if (strpos($key, 'attribute_') === 0) {
            $key = substr($key, strlen('attribute_'));
        }
        return sanitize_title($key);
    }

    private static function pick_value($haystack, $candidates) {
        foreach ($candidates as $c) {
            if (isset($haystack[$c]) && $haystack[$c] !== '') return $haystack[$c];
        }
        return null;
    }

    private static function add_to_cart_url($product) {
        if (method_exists($product, 'add_to_cart_url')) {
            $url = $product->add_to_cart_url();
            if ($url) return $url;
        }
        $base = function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/');
        return add_query_arg(['add-to-cart' => (int) $product->get_id()], $base);
    }

    private static function product_image($product, $size = 'medium') {
        $thumb_id = $product ? (int) $product->get_image_id() : 0;
        if ($thumb_id && function_exists('wp_get_attachment_image_url')) {
            $url = wp_get_attachment_image_url($thumb_id, $size);
            if ($url) return $url;
        }
        if (function_exists('wc_placeholder_img_src')) {
            return wc_placeholder_img_src($size);
        }
        return '';
    }

    private static function gallery($product) {
        $out = [];
        $main = self::product_image($product, 'large');
        if ($main) $out[] = $main;
        $ids = method_exists($product, 'get_gallery_image_ids') ? (array) $product->get_gallery_image_ids() : [];
        foreach ($ids as $id) {
            $url = function_exists('wp_get_attachment_image_url') ? wp_get_attachment_image_url((int) $id, 'large') : '';
            if ($url) $out[] = $url;
        }
        return $out;
    }

    private static function terms($product, $taxonomy) {
        $terms = function_exists('get_the_terms') ? get_the_terms($product->get_id(), $taxonomy) : [];
        if (!is_array($terms)) return [];
        $out = [];
        foreach ($terms as $t) {
            $out[] = ['id' => (int) $t->term_id, 'slug' => (string) $t->slug, 'name' => (string) $t->name];
        }
        return $out;
    }

    private static function float_or_null($v) {
        if ($v === '' || $v === null) return null;
        return is_numeric($v) ? (float) $v : null;
    }

    private static function wc_loaded() {
        return function_exists('wc_get_products') && function_exists('wc_get_product');
    }
}
