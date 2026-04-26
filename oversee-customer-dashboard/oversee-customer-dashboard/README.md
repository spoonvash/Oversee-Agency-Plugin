# Oversee Customer Dashboard

A WordPress plugin that surfaces a customer-facing dashboard and an Oversee staff admin portal, integrating **HighLevel/LeadConnector CRM** and **WooCommerce Subscriptions** via real REST APIs.

This plugin lives in the `Oversee-Agency-Plugin` repository alongside `oversee-helpdesk`, `oversee-license-server` and `oversee-update-server`. It is designed to be installed on the Oversee Agency WordPress site (which already runs WooCommerce + WooCommerce Subscriptions and the helpdesk plugin).

## Brand

The visual design uses the Oversee brand color discovered in the Hub Child theme on the agency site:

- Primary: `#ff8201`
- Primary hover: `#e67300`

(These supersede any prior teal palette from earlier visual previews.)

## Architecture

```
oversee-customer-dashboard/
├── oversee-customer-dashboard.php   # Plugin bootstrap
├── includes/
│   ├── class-ocd-settings.php       # Reads env vars first, options as fallback
│   ├── class-ocd-highlevel.php      # HighLevel REST client
│   ├── class-ocd-woocommerce.php    # WooCommerce REST client (incl. Subscriptions)
│   ├── class-ocd-rest-api.php       # /wp-json/ocd/v1/* endpoints
│   ├── class-ocd-shortcodes.php     # [oversee_customer_dashboard], [oversee_admin_dashboard]
│   ├── class-ocd-assets.php         # Asset enqueueing & nonce localization
│   └── class-ocd-admin.php          # WP-Admin menu + settings page
├── templates/
│   ├── customer-dashboard.php       # Customer portal markup
│   └── admin-dashboard.php          # Oversee staff admin portal
└── assets/
    ├── css/frontend.css             # Design tokens + components (brand colors)
    ├── css/admin.css
    └── js/frontend.js               # Customer + admin controllers
```

The frontend never sees API tokens. All third-party calls go through `/wp-json/ocd/v1/*` which is authenticated via WordPress cookie + `X-WP-Nonce`.

## Required environment variables

Set these in `wp-config.php` as constants, in `.env` consumed by your deployment, or in WP-Admin → Oversee Dashboard → Settings.

| Variable | Purpose |
|---|---|
| `HIGHLEVEL_API_TOKEN` | HighLevel/LeadConnector Bearer token |
| `HIGHLEVEL_LOCATION_ID` | HighLevel sub-account location id |
| `HIGHLEVEL_BASE_URL` | Defaults to `https://services.leadconnectorhq.com` |
| `HIGHLEVEL_API_VERSION` | Defaults to `2021-07-28` |
| `OVERSEE_WP_BASE_URL` | Base URL of the WordPress site running WooCommerce (e.g. `https://overseeagency.com`) |
| `WOOCOMMERCE_CONSUMER_KEY` | WooCommerce REST API consumer key |
| `WOOCOMMERCE_CONSUMER_SECRET` | WooCommerce REST API consumer secret |
| `OCD_WEBHOOK_SECRET` | HMAC secret for `/ocd/v1/webhooks/highlevel` |

The plugin reads env vars *first* and falls back to wp-options. Production deployments should prefer env vars / `wp-config.php` constants so secrets are not stored in the database.

`wp-config.php` example:
```php
define('HIGHLEVEL_API_TOKEN', 'pit-xxxxxxxx');
define('HIGHLEVEL_LOCATION_ID', 'abc123');
define('OVERSEE_WP_BASE_URL', 'https://overseeagency.com');
define('WOOCOMMERCE_CONSUMER_KEY', 'ck_xxx');
define('WOOCOMMERCE_CONSUMER_SECRET', 'cs_xxx');
define('OCD_WEBHOOK_SECRET', 'change-me');
```

## WordPress / WooCommerce setup

1. Install and activate **WooCommerce** and **WooCommerce Subscriptions** (already present on the Oversee Agency site).
2. Generate a REST API key under WooCommerce → Settings → Advanced → REST API with **Read/Write** permissions for an admin user. Use these as `WOOCOMMERCE_CONSUMER_KEY` / `WOOCOMMERCE_CONSUMER_SECRET`.
3. Install and activate this plugin (`oversee-customer-dashboard`).
4. Place `[oversee_customer_dashboard]` on a logged-in customer page (e.g. `/dashboard/`).
5. Place `[oversee_admin_dashboard]` on an internal staff page (visible only to roles with `manage_woocommerce`).

## HighLevel setup

1. In your HighLevel sub-account, create a **Private Integration Token** with these scopes:
   - `contacts.readonly`, `contacts.write`
   - `opportunities.readonly`
   - `locations.readonly`
2. Copy the Location ID from your sub-account.
3. Set both as env vars (preferred) or save them under WP-Admin → Oversee Dashboard → Settings.

## REST API surface

All routes are under `/wp-json/ocd/v1/`. Authentication: WordPress cookie + `X-WP-Nonce` header (the plugin localizes the nonce automatically).

| Method | Path | Purpose | Permission |
|---|---|---|---|
| GET | `/status` | Plugin version + connection status | Public |
| GET | `/me` | Current user | Logged-in |
| GET | `/customer/dashboard` | Aggregated customer view (CRM + subs + orders) | Logged-in |
| POST | `/customer/subscriptions/{id}/cancel` | Owner-only: requests `pending-cancel` | Logged-in |
| GET | `/admin/customers` | List Woo customers (`search`, `page`, `per_page`) | manage_woocommerce |
| GET | `/admin/subscriptions` | List subscriptions (`status`, `page`, `per_page`) | manage_woocommerce |
| POST | `/admin/subscriptions/{id}` | Update subscription status | manage_woocommerce |
| GET | `/admin/contacts` | HighLevel contacts (`query`, `limit`) | manage_woocommerce |
| GET | `/admin/opportunities` | HighLevel opportunities (`status`, `limit`) | manage_woocommerce |
| GET | `/admin/sync-status` | Live ping of both APIs | manage_woocommerce |
| POST | `/webhooks/highlevel` | HighLevel webhook ingestion | HMAC `X-OCD-Signature` |

### Subscription statuses

The plugin treats these as the canonical valid statuses (matching WooCommerce Subscriptions):
`active`, `pending`, `on-hold`, `pending-cancel`, `cancelled`, `expired`.

## Webhook security

Configure HighLevel to POST events to `https://your-site/wp-json/ocd/v1/webhooks/highlevel` with header `X-OCD-Signature: hex_hmac_sha256(body, OCD_WEBHOOK_SECRET)`. Requests that fail HMAC verification are rejected with 403.

## Testing / verification

Manual verification (bash):
```bash
# Public status
curl -s https://your-site/wp-json/ocd/v1/status | jq .

# Authenticated customer dashboard (requires cookie+nonce – use logged-in browser)
```

PHP syntax check (no WordPress required):
```bash
find oversee-customer-dashboard -name '*.php' -print0 \
  | xargs -0 -n1 php -l
```

## Disconnected / setup state

Every UI action either:
- Calls a real backend endpoint that performs a real WooCommerce / HighLevel operation, or
- Renders a clearly disabled state with helpful copy ("WooCommerce not connected", "HighLevel CRM not connected") — no fake success buttons.

When neither integration is configured, the dashboard renders a top-of-page setup notice pointing at the settings page.
