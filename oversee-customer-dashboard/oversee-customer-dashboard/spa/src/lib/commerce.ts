// Commerce-specific shared utilities.
//
// Single source of truth for "which image should I display for this product?"
// across the storefront card, the configure/detail sheet, the cart line, and
// the checkout/order summary.
//
// Resolution precedence (highest first):
//   1. The currently-selected variation's own image (Woo allows per-variation
//      art for variable products) — only when a variation is matched.
//   2. The product's primary image — `imageUrl` on the normalized WooProduct.
//      If the product was hydrated from the Woo Store API directly, callers
//      can pass through `images[0].src` when normalizing instead.
//   3. `undefined` — the visual layer is responsible for rendering a neutral
//      fallback (handled by <ServiceVisual />).
//
// Callers should treat a falsy/empty/whitespace-only string as "no image";
// the same product on the storefront and in the cart must always resolve to
// the same URL given the same selection.

import type { WooProduct, WooVariation } from "./demo-store";

export type ResolvedProductImage = {
  src?: string;
  alt: string;
};

/**
 * Resolve the image to display for a WooCommerce product, optionally taking
 * the user's currently-selected variation into account.
 *
 * - Prefers a variation-specific image when present (Woo variable products).
 * - Falls back to the product's primary image.
 * - Returns `src: undefined` when no usable image exists, so the visual layer
 *   can render its category/initials fallback (NOT a generic gradient when an
 *   image is available).
 */
export function getProductImage(
  product: Pick<WooProduct, "name" | "imageUrl" | "imageAlt">,
  selectedVariation?: Pick<WooVariation, "image" | "imageAlt"> | null,
): ResolvedProductImage {
  const candidate = isUsable(selectedVariation?.image)
    ? selectedVariation?.image
    : isUsable(product.imageUrl)
      ? product.imageUrl
      : undefined;

  const alt =
    (selectedVariation?.imageAlt && selectedVariation.imageAlt.trim()) ||
    (product.imageAlt && product.imageAlt.trim()) ||
    product.name;

  return { src: candidate, alt };
}

function isUsable(value: unknown): value is string {
  return typeof value === "string" && value.trim().length > 0;
}
