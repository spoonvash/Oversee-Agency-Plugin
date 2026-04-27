# Oversee Dashboard — Install

This document walks an admin through installing the Oversee dashboard on an
existing WordPress site (overseeagency.com is the canonical deployment).

## Requirements

- WordPress **6.0+** (tested up to 6.7)
- PHP **7.4+** (PHP 8.1+ recommended)
- WooCommerce **8.0+**
- WooCommerce Subscriptions **5.0+**
- WooCommerce Stripe Gateway **8.0+** (Stripe is the only supported PSP — the
  dashboard itself never talks to Stripe directly; it lets WC Stripe handle
  every charge.)
- Magic Login (or another passwordless plugin) — optional but recommended

## Plugin layout

This repository ships **two** companion components that work together:

1. **`oversee-hub-child`** — child theme that overrides WooCommerce templates
   and renders the `/dashboard/`, `/login/`, `/register/` surfaces.
2. **`oversee-customer-dashboard`** — companion plugin (internally named
   *Oversee Dashboard*, slug `oversee-dashboard` going forward; the legacy
   `oversee-customer-dashboard` slug is preserved for back-compat).

Both must be installed and activated.

## Installation steps

```bash
# 1. SSH to the WP host and clone (or upload) the two folders into:
#      wp-content/themes/oversee-hub-child
#      wp-content/plugins/oversee-customer-dashboard

# 2. Build the React SPA bundle:
cd wp-content/plugins/oversee-customer-dashboard/spa
npm ci
npm run build       # outputs to assets/spa/

# 3. Activate the child theme + plugin from /wp-admin → Themes / Plugins.
#    Activation runs `OCD_Schema::install`, `Oversee_Schema::install`,
#    and `OCD_Roles::install` to create custom tables + roles.

# 4. Create three new pages (Pages → Add New) with these slugs:
#      /dashboard
#      /login
#      /register
#    Assign each the matching template under "Page Attributes":
#      "Oversee Dashboard (Full-Width)"  → /dashboard
#      "Oversee Login (Full-Width)"      → /login and /register
```

## Configuration

Add the following to `wp-config.php` (or surface via Settings → Oversee):

```php
// HighLevel CRM
define('HIGHLEVEL_API_TOKEN',  'pat-...');
define('HIGHLEVEL_LOCATION_ID', 'loc-...');
define('HIGHLEVEL_MAGIC_LINK_ENDPOINT', 'https://services.leadconnectorhq.com/...'); // optional

// AI assist (Resend, OpenAI, Anthropic — wired via filter)
define('OVERSEE_AI_API_KEY',   'sk-...');

// Webhook secret for HighLevel inbound webhooks
define('OCD_WEBHOOK_SECRET',   'long-random-string');

// Optional: Pusher for live updates
define('OVERSEE_PUSHER_KEY',     'public-key');
define('OVERSEE_PUSHER_SECRET',  'private-key');
define('OVERSEE_PUSHER_CLUSTER', 'us-east-1');

// Optional: Bunny CDN for private file delivery
define('OVERSEE_BUNNY_PULL_ZONE', 'oversee-private');
define('OVERSEE_BUNNY_API_KEY',   '...');

// Optional: Resend for transactional email
define('OVERSEE_RESEND_API_KEY', 're_...');
```

## Verifying the install

After activation:

1. Visit `/wp-json/oversee/v1/status` — must return JSON with `version` and `namespace: "oversee/v1"`.
2. Visit `/wp-json/ocd/v1/status` — must return the legacy namespace status (back-compat).
3. Visit `/dashboard/` while logged in — must mount the React SPA.
4. Place a test order — `woocommerce_order_status_completed` should spawn a `project_board` CPT post automatically (visible in the WP admin under Project Boards).

## Build / dev commands (SPA)

```bash
# from wp-content/plugins/oversee-customer-dashboard/spa
npm install                  # install deps
npm run dev                  # vite dev server (HMR) — proxies to WP host
npm run build                # production build → assets/spa/
npm run lint                 # eslint
npm run typecheck            # tsc --noEmit
```

The plugin auto-loads the built bundle on the dashboard surface; no extra
configuration is required after `npm run build`.
