<?php
/**
 * Dashboard feature store. Reads from a curated list of WooCommerce product
 * IDs (the same ones used by the entitlement map) and exposes them to the
 * dashboard so customers can purchase add-ons. Purchases route through the
 * real WooCommerce cart/checkout — no fake transactions.
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class OCD_Store {

    public static function listings_for_user($user_id) {
        $map = OCD_Entitlements::get_product_map();
        if (empty($map)) {
            return [];
        }
        $owned = [];
        foreach (OCD_Entitlements::for_user($user_id) as $e) {
            $owned[$e['slug']] = $e['status'];
        }
        $listings = [];
        foreach ($map as $product_id => $entry) {
            $product = function_exists('wc_get_product') ? wc_get_product((int) $product_id) : null;
            if (!$product) {
                $listings[] = [
                    'product_id'   => (int) $product_id,
                    'slug'         => $entry['slug'],
                    'label'        => $entry['label'],
                    'name'         => $entry['label'],
                    'price_html'   => '',
                    'description'  => '',
                    'available'    => false,
                    'add_to_cart'  => '',
                    'permalink'    => '',
                    'owned_status' => $owned[$entry['slug']] ?? null,
                    'reason'       => 'WooCommerce product not found',
                ];
                continue;
            }
            $listings[] = [
                'product_id'   => (int) $product_id,
                'slug'         => $entry['slug'],
                'label'        => $entry['label'],
                'name'         => $product->get_name(),
                'price_html'   => $product->get_price_html(),
                'description'  => wp_strip_all_tags($product->get_short_description() ?: $product->get_description()),
                'available'    => $product->is_purchasable() && $product->is_in_stock(),
                'add_to_cart'  => self::add_to_cart_url((int) $product_id),
                'permalink'    => get_permalink($product->get_id()),
                'owned_status' => $owned[$entry['slug']] ?? null,
            ];
        }
        return $listings;
    }

    private static function add_to_cart_url($product_id) {
        $cart = function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/');
        $sep  = strpos($cart, '?') === false ? '?' : '&';
        return $cart . $sep . 'add-to-cart=' . (int) $product_id;
    }
}
