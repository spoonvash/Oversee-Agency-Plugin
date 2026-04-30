# Oversee Dashboard SPA

Vite + React 18 + TypeScript + Tailwind 3 SPA mounted at `/dashboard/` by the
Oversee Customer Dashboard plugin. Replaces the legacy 1.x/2.0 implementation
with the current preview/coded design (assembly-style boards, real WooCommerce
variants, consolidated Account hub, single-close file preview, simplified admin
boards, zero inline styles).

## Build

```sh
cd spa
npm install
npm run build      # outputs to ../assets/build (manifest + hashed JS/CSS)
npm run dev        # local Vite dev server
npm run typecheck  # tsc --noEmit
```

The plugin's `OCD_Assets` class reads `assets/build/.vite/manifest.json` and
emits `<script type="module">` for the entry chunk plus a `<link>` for the
emitted CSS.

## Mount point

`src/main.tsx` mounts into either `#oversee-dashboard-root` (the WordPress
mount node, used by the `oversee-hub-child` `page-dashboard.php` template and
by the plugin's `[oversee_customer_dashboard]` / `[oversee_admin_dashboard]`
shortcodes) or, in standalone Vite dev mode, falls back to `#root`.

## Routing

The SPA uses **wouter hash routing** so it can live under any WordPress page
permalink (e.g. `/dashboard/`) without a server-side rewrite for every sub-route.
Top-level routes:

```
#/                  Preview selector (offers /login, /client, /admin)
#/login             Login screen (handoff to WP login on submit)
#/client[...]       Customer surface (Home, Work, Files, Commerce, Account, Onboarding, Updates)
#/admin[...]        Staff surface (Today, Boards, Clients, Files & Approvals,
                    Commerce handoff, System, Updates)
```

Authoritative source-of-truth principles enforced in the SPA:

- WooCommerce products / variants / images surface in `pages/client/commerce.tsx`.
- The Account hub (`pages/client/account.tsx`) consolidates subscriptions,
  orders, payment methods, addresses, and profile — deep-linked to the real
  WooCommerce my-account / cart / checkout endpoints.
- `components/client-comms.tsx` is the Admin Clients comms composer
  (HighLevel client-facing, Slack internal, email draft handoff). No real
  messages are sent in tests; everything is a draft/handoff payload.
- `components/document-preview.tsx` exposes a single close control.
- Admin board pages (`pages/admin/boards.tsx`) use the simplified IA and
  approval-clarity terminology from `lib/approval-clarity.ts`.

## WordPress runtime config

`OCD_Assets::enqueue_frontend()` injects two globals (identical payloads,
both names supported):

```ts
window.OCD_CONFIG = window.OVERSEE_CONFIG = {
  restUrl, overseeRestUrl, wcRestUrl, nonce, isAdmin, isLoggedIn,
  siteUrl, accountUrl, cartUrl, checkoutUrl, logoutUrl, currentUser
};
```

`lib/queryClient.ts` and the commerce/account flows read these for the real
WooCommerce deep links instead of hardcoding URLs.

## Inline-style policy

There must be **zero** inline `style={{ … }}` props in `src/`. CI / review
should grep for `style={{` and fail on any match. Token customisation
goes through CSS custom properties in `src/index.css` and Tailwind theme
extensions (`tailwind.config.ts`).

## Demo data

`src/lib/demo-store.tsx` is an in-memory store the SPA falls back to when
WordPress REST endpoints aren't yet wired for a given screen. It is **not**
used for cart/checkout/payment/subscription flows — those always defer to
WooCommerce native surfaces via the URLs in `OCD_CONFIG`.
