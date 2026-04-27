# Oversee Hub Child Theme

Branded child theme for Oversee Agency that sits on top of the existing **Hub** parent theme. It owns the visual chrome for the customer portal:

- `/dashboard/` (full-width SPA mount, Hub chrome suppressed)
- `/login/` and `/register/` (branded auth pages)
- WooCommerce templates: my-account, cart, checkout (Oversee-branded wrappers, native WooCommerce logic preserved)
- Branded HTML email wrapper for transactional WooCommerce emails

The marketing site continues to render with Hub styling — this child theme only kicks in on portal/account/cart/checkout pages and on dashboard auth surfaces.

## Activation

1. Upload `oversee-hub-child/` to `wp-content/themes/`.
2. **Appearance → Themes → Activate** "Oversee Hub Child".
3. In WordPress admin, create a Page with slug `dashboard`. Set its Page Attributes → Template to **Oversee Dashboard (Full-Width)**. The plugin will mount the React SPA into this page.
4. Create Pages with slugs `login` and `register` and set Template to **Oversee Login (Full-Width)**.
5. The `template_include` filter also enforces these templates by slug, so even if step 3–4 is skipped the portal still renders correctly.

## Brand tokens

CSS custom properties defined in `assets/css/oversee-dashboard.css`:

| Token | Light | Dark |
|---|---|---|
| `--oversee-orange` | `#ff8201` | `#ff8201` |
| `--oversee-orange-hover` | `#e67300` | `#e67300` |
| `--oversee-orange-soft` | `#fff2e5` | `rgba(255,130,1,0.12)` |
| `--oversee-bg` | `#ffffff` | `#09090b` |
| `--oversee-surface` | `#ffffff` | `#18181b` |
| `--oversee-text` | `#0a0a0a` | `#fafafa` |

Dark mode is enabled by adding the class `oversee-dark` to `<html>`. The dashboard plugin persists this preference per-user via `oversee_dark_mode` user meta and emits the class server-side on the dashboard template.

## WooCommerce overrides

`woocommerce/myaccount/dashboard.php`, `woocommerce/cart/cart.php`, and `woocommerce/checkout/form-checkout.php` are Oversee-branded wrappers. The native WooCommerce + WooCommerce Stripe + WooCommerce Subscriptions logic is preserved exactly — to revert any of them, delete the file from `oversee-hub-child/woocommerce/`.

## Email wrapper

The `woocommerce_email_header` / `woocommerce_email_footer` actions wrap **customer** transactional emails in an Oversee-branded HTML shell. Admin notifications keep the default WooCommerce layout.

## Hub chrome suppression

`functions.php` adds the body classes `oversee-portal` and `oversee-no-hub-chrome` on portal pages, and `assets/css/oversee-dashboard.css` hides Elementor and Hub header/footer/sidebar regions on those classes. This is a CSS-only suppression; the Hub theme itself is not modified.

## Caveats / staging checklist

- Verify the `dashboard`, `login`, and `register` pages exist and have the correct template before testing.
- Confirm `wp-content/themes/hub/` (parent) is installed and active before activating the child.
- Test WooCommerce checkout end-to-end with the WooCommerce Stripe gateway in test mode.
- Test dark mode toggle round-trips through the dashboard SPA into `oversee_dark_mode` user meta.
- Verify that the marketing site (homepage, etc.) still renders Hub styling — this child theme should not touch those pages.
