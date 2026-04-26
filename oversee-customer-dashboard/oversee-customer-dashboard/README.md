# Oversee Customer Dashboard

A WordPress plugin that surfaces a customer-facing dashboard and an Oversee staff admin portal, integrating **HighLevel/LeadConnector CRM** and **WooCommerce Subscriptions** via real REST APIs.

This plugin lives in the `Oversee-Agency-Plugin` repository alongside `oversee-helpdesk`, `oversee-license-server` and `oversee-update-server`. It is designed to be installed on the Oversee Agency WordPress site (which already runs WooCommerce + WooCommerce Subscriptions and the helpdesk plugin).

## Brand

The visual design uses the Oversee brand color discovered in the Hub Child theme on the agency site:

- Primary: `#ff8201`
- Primary hover: `#e67300`

(These supersede any prior teal palette from earlier visual previews.)

## What's in this release (1.1.x)

- **Two-way customer messaging** stored in custom tables and forwarded to the agency HighLevel sub-account via `POST /conversations/messages/inbound`. Outbound replies from HighLevel can be mirrored back via the webhook ingest endpoint.
- **Project timelines + milestones** with progress tracking and admin CRUD.
- **Client tasks/steps** that admins assign and customers can complete from the dashboard.
- **Feature entitlements + dashboard store** mapped from WooCommerce products. Buying a product (or activating a subscription) automatically grants the entitlement and creates an associated project.
- **Customer-owned CRM connections** are stored in a separate table from the agency CRM credentials — they never mix.
- **Auto dashboard access**: a `access_oversee_dashboard` capability is granted on order processing/completion and on subscription activation.

## Architecture

```
oversee-customer-dashboard/
├── oversee-customer-dashboard.php       # Plugin bootstrap
├── includes/
│   ├── class-ocd-settings.php           # Reads env vars first, options as fallback
│   ├── class-ocd-schema.php             # Custom-table installer (dbDelta)
│   ├── class-ocd-highlevel.php          # HighLevel REST client (contacts, opps, conversations)
│   ├── class-ocd-woocommerce.php        # WooCommerce REST client (incl. Subscriptions)
│   ├── class-ocd-messaging.php          # Two-way customer ↔ agency CRM messaging
│   ├── class-ocd-projects.php           # Projects + milestones
│   ├── class-ocd-tasks.php              # Client tasks
│   ├── class-ocd-entitlements.php       # Feature entitlements + product map
│   ├── class-ocd-customer-crm.php       # Customer-owned CRM connections (separate)
│   ├── class-ocd-store.php              # In-dashboard feature store (WC product passthrough)
│   ├── class-ocd-woocommerce-hooks.php  # WC order/subscription → entitlements + access
│   ├── class-ocd-rest-api.php           # /wp-json/ocd/v1/* endpoints
│   ├── class-ocd-shortcodes.php         # Shortcodes
│   ├── class-ocd-assets.php             # Asset enqueueing & nonce localization
│   └── class-ocd-admin.php              # WP-Admin menu + settings page
├── templates/
│   ├── customer-dashboard.php           # Customer portal (Overview / Projects / Tasks / Messages / Store / Integrations)
│   └── admin-dashboard.php              # Staff portal (Overview / Inbox / Projects / Tasks / Entitlements / Customers / Subscriptions / CRM / Sync)
└── assets/
    ├── css/frontend.css                 # Brand-aligned design tokens
    ├── css/admin.css
    └── js/frontend.js                   # Customer + admin controllers
```

## Data model

Custom tables, all prefixed `{wp_prefix}ocd_`:

| Table | Purpose |
|---|---|
| `messages` | Customer/staff chat thread with `hl_contact_id` / `hl_conversation_id` / `hl_message_id`, `direction`, sync state, read flags |
| `projects` | Project per customer, optionally tied to a Woo order/subscription/product |
| `milestones` | Ordered milestones per project |
| `tasks` | Client tasks/steps with statuses, due dates, assigned-by |
| `entitlements` | Active feature entitlements per user, indexed by slug |
| `customer_crm` | Customer-owned CRM tokens (kept separate from agency CRM settings) |

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

## HighLevel setup (agency CRM)

The token configured in this section belongs to the **Oversee Agency** HighLevel sub-account. Customer-owned CRM connections are stored separately (see *Customer-owned CRM*, below).

1. In your HighLevel sub-account, create a **Private Integration Token** with these scopes:
   - `contacts.readonly`, `contacts.write`
   - `opportunities.readonly`
   - `locations.readonly`
   - `conversations/message.write`, `conversations/message.readonly`
   - `conversations.readonly`, `conversations.write`
2. Copy the Location ID from your sub-account.
3. Set both as env vars (preferred) or save them under WP-Admin → Oversee Dashboard → Settings.

### Conversation provider (optional but recommended)

To make customer messages appear in HighLevel as a custom inbound channel, create a **Custom Conversation Provider** in HighLevel and copy its provider ID into Settings → "Conversation Provider ID". The plugin posts each customer message via `POST /conversations/messages/inbound` with that provider ID, the configured "Inbound message type" (default `Custom`; alternatives include `Live_Chat`, `WebChat`, `SMS`), and headers `Authorization: Bearer …`, `Version: 2021-04-15`. Without a provider ID configured, messages are still posted as inbound but won't be tagged to a custom channel.

To mirror outbound HighLevel messages back into the customer dashboard, configure your provider's outbound webhook to point at:

```
POST https://your-site/wp-json/ocd/v1/webhooks/highlevel
Header X-OCD-Signature: hex_hmac_sha256(body, OCD_WEBHOOK_SECRET)
```

Webhook payloads with `contactId` + `message`/`html`/`body` are persisted into the local thread (idempotent on `messageId`) so the customer sees staff replies even when they aren't logged into HighLevel.

## Customer-owned CRM

Each customer can connect their own CRM account separately under **Dashboard → Integrations**. Tokens are stored in the `ocd_customer_crm` table — never in the agency settings. Tokens are stripped from API responses on read; encryption-at-rest is the operator's responsibility (e.g. via WP cryptography or KMS-backed disk).

## WooCommerce → entitlement mapping

Map WooCommerce product IDs to dashboard feature slugs under **Oversee Admin → Entitlements**. When a customer's order moves to `processing` or `completed` (or a subscription becomes `active`), the plugin:

- Adds the `access_oversee_dashboard` capability to the customer
- Grants matching entitlements (active)
- Creates a project record per purchased product if one doesn't exist

Subscription transitions to `on-hold`, `pending-cancel`, `cancelled`, or `expired` propagate to entitlement statuses.

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
| GET, POST | `/customer/messages` | Read thread; POST sends + forwards to HighLevel | Logged-in |
| GET | `/customer/projects` | Owner-only list with milestones | Logged-in |
| GET | `/customer/tasks` | Owner-only task list | Logged-in |
| POST | `/customer/tasks/{id}` | Owner toggles task `open` ↔ `completed` | Logged-in |
| GET | `/customer/entitlements` | Active feature entitlements | Logged-in |
| GET | `/customer/store` | Listings derived from product map; CTA → real Woo cart | Logged-in |
| GET, POST, DELETE | `/customer/crm` | Customer-owned CRM connection (separate from agency) | Logged-in |
| GET | `/admin/messages/inbox` | Conversation list with unread counts | manage_woocommerce |
| GET, POST | `/admin/messages/thread/{user_id}` | View thread / post staff reply | manage_woocommerce |
| GET, POST | `/admin/projects` | List / create projects | manage_woocommerce |
| POST, DELETE | `/admin/projects/{id}` | Update / delete projects | manage_woocommerce |
| POST | `/admin/projects/{id}/milestones` | Add milestone | manage_woocommerce |
| POST, DELETE | `/admin/milestones/{id}` | Update / delete milestone | manage_woocommerce |
| GET, POST | `/admin/tasks` | List / create tasks | manage_woocommerce |
| POST, DELETE | `/admin/tasks/{id}` | Update / delete task | manage_woocommerce |
| GET | `/admin/entitlements?user_id=…` | Per-user entitlements | manage_woocommerce |
| GET, POST | `/admin/product-map` | Read / write WC product → feature mapping | manage_woocommerce |
| POST | `/webhooks/highlevel` | HighLevel webhook ingestion + outbound mirroring | HMAC `X-OCD-Signature` |

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
- Renders a clearly disabled state with helpful copy ("WooCommerce not connected", "HighLevel CRM not connected", "Local-only — agency CRM not connected yet") — no fake success buttons.

When neither integration is configured, the dashboard renders a top-of-page setup notice pointing at the settings page.

## Limitations

- **Outbound HighLevel sending from staff**: posting a staff reply currently records the reply locally and (when configured) appears alongside CRM-mirrored messages. Posting *from the dashboard out through HighLevel SMS/email* requires either (a) a Custom Conversation Provider where HighLevel pushes outbound messages back to us, or (b) calling `POST /conversations/messages` with `conversations/message.write`. The provider-mirror flow is wired (webhook ingest); a direct outbound send hook can be added by calling `OCD_HighLevel::send_outbound_message()` from your own code.
- **Customer CRM tokens**: stored at rest in MySQL. Encrypt via wp-config secrets or a KMS hook before exposing to customer self-service in production.
- **Entitlement mapping**: keyed by WooCommerce product ID. If you change product IDs (rare), update the map under Admin → Entitlements.
- **WooCommerce REST 404s**: usually mean (a) the WP base URL points at a host without WooCommerce, (b) pretty permalinks are off, or (c) the consumer key/secret pair lacks `read/write`. The Sync tab surfaces the underlying error message.
