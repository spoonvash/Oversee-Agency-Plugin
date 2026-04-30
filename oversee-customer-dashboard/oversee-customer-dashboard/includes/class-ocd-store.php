<?php
/**
 * Dashboard feature store. Surfaces *existing* WooCommerce products only —
 * never seeds, creates, or fakes products. Sources:
 *
 *   1. The product → feature map (admin-curated mapping of existing
 *      WooCommerce product IDs to dashboard entitlement slugs).
 *   2. Optionally, all purchasable products visible in the configured
 *      product category (admin selects an existing WC category).
 *
 * Purchases route through the real WooCommerce cart/checkout — every CTA
 * is a live add-to-cart URL on a real product. There are no mock buttons.
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class OCD_Store {

    /**
     * Returns dashboard product cards for the given customer.
     *
     * @param int $user_id
     * @return array {
     *   @type bool   $available  False when WooCommerce isn't loaded or returns no products.
     *   @type string $reason     Human-readable empty-state copy.
     *   @type array  $listings   Product cards (existing WC products only).
     *   @type string $cart_url
     *   @type string $checkout_url
     * }
     */
    public static function dashboard_payload($user_id) {
        $cart_url     = function_exists('wc_get_cart_url') ? wc_get_cart_url() : '';
        $checkout_url = function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : '';

        if (!self::woocommerce_loaded()) {
            return [
                'available'    => false,
                'reason'       => __('WooCommerce is not active on this site. Activate WooCommerce to surface real products in the dashboard store.', 'oversee-customer-dashboard'),
                'listings'     => [],
                'cart_url'     => '',
                'checkout_url' => '',
            ];
        }

        $owned = [];
        foreach (OCD_Entitlements::for_user($user_id) as $e) {
            $owned[$e['slug']] = $e['status'];
        }

        $product_ids = self::resolve_product_ids();
        if (empty($product_ids)) {
            return [
                'available'    => false,
                'reason'       => __('No eligible WooCommerce products mapped yet. An admin can map existing products to the dashboard under Oversee Admin → Entitlements.', 'oversee-customer-dashboard'),
                'listings'     => [],
                'cart_url'     => $cart_url,
                'checkout_url' => $checkout_url,
            ];
        }

        $listings = [];
        $map      = OCD_Entitlements::get_product_map();
        foreach ($product_ids as $pid) {
            $product = wc_get_product($pid);
            if (!$product) continue;
            if (!$product->is_purchasable()) continue;

            $entry      = $map[(int) $pid] ?? null;
            $owned_slug = $entry['slug'] ?? null;
            $listings[] = [
                'product_id'   => (int) $pid,
                'slug'         => $owned_slug,
                'label'        => $entry['label'] ?? $product->get_name(),
                'name'         => $product->get_name(),
                'price_html'   => $product->get_price_html(),
                'description'  => wp_strip_all_tags($product->get_short_description() ?: $product->get_description()),
                'image'        => self::product_image_url($product),
                'available'    => $product->is_in_stock(),
                'add_to_cart'  => self::add_to_cart_url($product),
                'permalink'    => get_permalink($product->get_id()),
                'sku'          => $product->get_sku(),
                'on_sale'      => $product->is_on_sale(),
                'owned_status' => $owned_slug ? ($owned[$owned_slug] ?? null) : null,
                'mapped'       => (bool) $entry,
            ];
        }

        if (empty($listings)) {
            return [
                'available'    => false,
                'reason'       => __('Mapped WooCommerce products were found but none are currently purchasable.', 'oversee-customer-dashboard'),
                'listings'     => [],
                'cart_url'     => $cart_url,
                'checkout_url' => $checkout_url,
            ];
        }

        return [
            'available'    => true,
            'reason'       => '',
            'listings'     => $listings,
            'cart_url'     => $cart_url,
            'checkout_url' => $checkout_url,
        ];
    }

    /**
     * Backwards-compatible wrapper used by existing callers.
     */
    public static function listings_for_user($user_id) {
        $payload = self::dashboard_payload($user_id);
        return $payload['listings'];
    }

    /**
     * Search existing WooCommerce products (admin product-mapping UI).
     *
     * @param array $args  search, per_page
     * @return array       lightweight rows of existing products
     */
    public static function search_existing_products($args = []) {
        if (!self::woocommerce_loaded()) {
            return [];
        }
        $defaults = ['search' => '', 'per_page' => 25];
        $args     = array_merge($defaults, $args);

        $query_args = [
            'status'  => ['publish', 'private'],
            'limit'   => max(1, min(100, (int) $args['per_page'])),
            'orderby' => 'title',
            'order'   => 'ASC',
            'return'  => 'objects',
        ];
        $search = trim((string) $args['search']);
        if ($search !== '') {
            // wc_get_products supports `s` for product search since WC 3.6.
            $query_args['s'] = $search;
        }

        $results = wc_get_products($query_args);
        $out     = [];
        foreach ($results as $product) {
            if (!$product) continue;
            $out[] = [
                'id'         => (int) $product->get_id(),
                'name'       => $product->get_name(),
                'sku'        => $product->get_sku(),
                'price_html' => $product->get_price_html(),
                'image'      => self::product_image_url($product),
                'permalink'  => get_permalink($product->get_id()),
                'type'       => $product->get_type(),
                'status'     => $product->get_status(),
            ];
        }
        return $out;
    }

    /* ---------------- Configuration ---------------- */

    const STORE_CATEGORY_OPT = 'ocd_store_product_category';

    public static function get_store_category() {
        return (string) get_option(self::STORE_CATEGORY_OPT, '');
    }

    public static function set_store_category($slug) {
        $slug = sanitize_title((string) $slug);
        update_option(self::STORE_CATEGORY_OPT, $slug);
        return $slug;
    }

    /* ---------------- Internals ---------------- */

    private static function woocommerce_loaded() {
        return function_exists('wc_get_products') && function_exists('wc_get_product');
    }

    /**
     * Final product-id list for the store. Combines:
     *   - Admin-curated product → feature map (existing product IDs)
     *   - Optional store category (existing WC category slug). All
     *     published products in the category are included so that the
     *     dashboard mirrors what the customer would see in the WC store.
     */
    private static function resolve_product_ids() {
        $ids = [];

        $map = OCD_Entitlements::get_product_map();
        foreach ($map as $pid => $entry) {
            $pid = (int) $pid;
            if ($pid > 0) $ids[$pid] = true;
        }

        $cat = self::get_store_category();
        if ($cat !== '' && self::woocommerce_loaded()) {
            $extra = wc_get_products([
                'status'   => 'publish',
                'limit'    => 50,
                'category' => [$cat],
                'return'   => 'ids',
            ]);
            foreach ((array) $extra as $pid) {
                $pid = (int) $pid;
                if ($pid > 0) $ids[$pid] = true;
            }
        }

        return array_keys($ids);
    }

    private static function product_image_url($product) {
        $thumb_id = $product ? (int) $product->get_image_id() : 0;
        if ($thumb_id && function_exists('wp_get_attachment_image_url')) {
            $url = wp_get_attachment_image_url($thumb_id, 'medium');
            if ($url) return $url;
        }
        if (function_exists('wc_placeholder_img_src')) {
            return wc_placeholder_img_src('medium');
        }
        return '';
    }

    private static function add_to_cart_url($product) {
        if ($product && method_exists($product, 'add_to_cart_url')) {
            $url = $product->add_to_cart_url();
            if ($url) return $url;
        }
        $base = function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/');
        $sep  = strpos($base, '?') === false ? '?' : '&';
        return $base . $sep . 'add-to-cart=' . (int) $product->get_id();
    }
}
