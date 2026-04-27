# Oversee Customer Dashboard

A WordPress plugin that surfaces a customer-facing dashboard and an Oversee staff admin portal, integrating **HighLevel/LeadConnector CRM** and **WooCommerce Subscriptions** via real REST APIs.

This plugin lives in the `Oversee-Agency-Plugin` repository alongside `oversee-helpdesk`, `oversee-license-server` and `oversee-update-server`. It is designed to be installed on the Oversee Agency WordPress site (which already runs WooCommerce + WooCommerce Subscriptions and the helpdesk plugin).

## Brand

The visual design uses the Oversee brand color discovered in the Hub Child theme on the agency site:

- Primary: `#ff8201`
- Primary hover: `#e67300`

(These supersede any prior teal palette from earlier visual previews.)

## What's in release 1.4 (this PR)

This release adds the foundation for the Monday-style project-board system, the Oversee role hierarchy, the HighLevel SSO/embed bridge, and the React SPA mounted at `/dashboard/`. Everything from 1.3.x continues to work — the new pieces live alongside the existing classes.

- **Roles** — five custom roles (`oversee_client`, `oversee_account_manager`, `oversee_specialist`, `oversee_contractor`, `oversee_admin`) with capability mapping. Standard `administrator` role gets the same operational caps mirrored on. WooCommerce `customer` role gets dashboard-view access. See `includes/class-ocd-roles.php`.
- **Custom post types** — `project_board` (one row per active client board) and `board_template` (reusable skeletons mapped to WooCommerce SKUs). See `includes/class-ocd-cpt.php`.
- **Board schema** — 12 new tables (`wp_ocd_board_*`) modelling boards, groups, items, subitems, columns, views, updates, attachments, item files, automations, intake responses, and an append-only activity log. See `includes/class-ocd-board-schema.php`.
- **Auto provisioning** — `woocommerce_created_customer` promotes the new user to `oversee_client` and sends a branded welcome email (`includes/class-ocd-signup-hooks.php`). `woocommerce_order_status_completed` walks line items, looks up the board template by SKU, and spawns one board per matching item, preserving variation IDs and line-item meta as the board's intake answers (`includes/class-ocd-boards.php`).
- **Subscription lifecycle** — `subscription_status_*` hooks pause/archive/restore boards. `subscription_renewal_payment_complete` rolls the board's monthly group over so each new month starts fresh.
- **HighLevel SSO bridge** — `ohl_contact_id` user meta, server-side magic-link fetch, configurable endpoint via filter / constant / option, friendly empty states when unconfigured. Five embed surfaces: `conversations`, `calendar`, `reports`, `documents`, `reputation`. See `includes/class-ocd-highlevel-sso.php`.
- **REST API extensions** — `/me/preferences` (dark mode), `/boards`, `/boards/<id>`, `/boards/<id>/items`, `/items/<id>`, `/items/<id>/move`, `/items/<id>/updates`, `/boards/<id>/views`, `/highlevel/embed-pages`, `/highlevel/embed/<surface>`, `/notifications`. All require login; write endpoints additionally require `work_oversee_boards`. See `includes/class-ocd-boards-rest.php`.
- **Child theme `oversee-hub-child`** — branded full-width dashboard / login / register page templates that suppress Hub chrome, WooCommerce template overrides for cart/checkout/my-account (native WooCommerce flow preserved), branded HTML email wrapper, brand CSS tokens (`#ff8201` primary, white in light mode, black/zinc in dark mode), and the `template_include` filter that enforces the templates by slug. See `oversee-hub-child/`.
- **React SPA scaffold** — Vite + React 19 + TypeScript + Tailwind v4 + shadcn-style local primitives + lucide-react + Recharts + @dnd-kit + TipTap + Pusher. Sidebar (12 client / 13 admin items per the spec), top bar with Cmd+K command palette stub, breadcrumb, notification bell, dark mode toggle. Routes for home, projects list, board detail (table + kanban view), files, messages, schedule-call, performance-reports, documents, reviews, knowledge, services, billing, account, plus admin counterparts. See `spa/`.
- **Tests** — new harness at `tests/test-boards.php` covers role registration & capabilities, `promote_to_client`, CPT SKU lookup, HighLevel SSO error paths, embed-config short-circuit via filter, board skeleton resolution, default columns, spawn validation, and the welcome email. 33 new assertions on top of the existing 99 (132 total).
- **Docs** — `docs/ADMIN_GUIDE.md` (install order, env/options, HighLevel setup, SKU mapping, staging checklist, limitations) and `docs/CLIENT_ONBOARDING.md` (client-facing walkthrough placeholder for Loom).

This is **source-code / PR work only** — none of it has been verified against a running WP install. See the staging checklist in `docs/ADMIN_GUIDE.md`.

## What's in release 1.3.x

This release answers the latest UX feedback round: **send images in messages**, **see images in tasks**, **drag-and-drop tasks across columns**, and **stop showing several menus that go to the same place**.

### Message image attachments

- Customers and admins can attach images to a message before sending it. The flow is two-step: each selected file is POSTed to a private upload endpoint (`/customer/messages/attachments` or `/admin/messages/thread/<user_id>/attachments`), the server stores it in `wp-content/uploads/oversee-private/<owner-id>/...` (the same private storage used for project files — never the WP Media Library), and returns a file id. The message itself is then POSTed with `attachment_ids: [...]`.
- Stored files reference the customer as `owner_user_id` whether the customer or an admin uploaded them — that way the customer's permission-checked download endpoint (`/files/<id>/download`) is the only path used to render them, even from the admin side.
- The MIME allow-list is reused from `OCD_Project_Files` (PNG, JPEG, GIF, WebP and more), and the same 25 MB-per-file size cap applies. The frontend's `<input type="file">` is restricted to `image/*` for the message UI; non-image files are rejected by both the client `accept` and the server allow-list.
- A new `wp_ocd_message_attachments` join table links message rows to file rows. Messages and attachments can each be queried independently; the join is included in `OCD_Messaging::thread_for_user()` and `get_message()` so the frontend gets attachments inline with each message.
- **HighLevel sync**: when a customer message is forwarded to HighLevel, only the message body is synced. Attachment URLs would require either signed URLs or a public CDN, neither of which we want to expose for private project files. The local thread keeps the attachments; the HighLevel mirror gets the text. This is intentional and documented as a limitation — the alternative (leaking private URLs to the CRM) is worse.

### Task image previews + admin instruction images

- The customer task payload now includes an inline `attachments[]` array of client-visible files attached to that task. `is_image: true` files are rendered as thumbnails directly on the kanban card, and clicking a thumbnail opens the full-size image through the same permission-checked endpoint.
- Admins have a new "Attach image" button on each task card (admin kanban) that uploads directly to `POST /admin/tasks/<id>/attachments` — the file is stored against the *task's* customer, scoped to that task, and defaults to `client` visibility so the customer can immediately see it.
- The existing `instruction_image_url` field on tasks/milestones is still validated against the same-host allow-list (`OCD_Instruction_Media::validate_image_url`), so external image URLs are rejected at the storage boundary. Inline attachments use the same private-storage path used everywhere else in the plugin — no path leakage either way.

### Drag/drop kanban for tasks

- The customer's "Your tasks" section on Home is now a column kanban (Needs Your Input, Not Started, In Progress, Waiting on Oversee, Done). Cards are HTML5-draggable; drop targets are the column bodies.
- The admin Work tab gets a parallel kanban with the broader admin column set (adds Blocked).
- Drag/drop posts to:
  - `POST /ocd/v1/customer/tasks/<id>/status` — customer endpoint, validates the calling user owns the task and the new status is in `OCD_Tasks::CUSTOMER_ALLOWED_STATUSES` (`not_started`, `in_progress`, `completed`, `waiting_on_oversee`). Anything else returns a 4xx and the UI snaps the card back. Internal-visibility tasks are forbidden — customers can never see them, let alone move them.
  - `POST /ocd/v1/admin/tasks/<id>/status` — admin endpoint, accepts any value in `OCD_Tasks::VALID_STATUSES`, including `blocked` and `cancelled`. Permission gated by `manage_woocommerce`/`manage_options`.
- Both endpoints reuse `OCD_Tasks::update($id, ['status' => …], $context)`, so the existing context-aware permission checks (and the `completed_at` side-effect for `completed`) keep working from the new entry points.

### UX simplification: fewer duplicate menus

The customer dashboard was previously six top-level tabs (Overview / Projects / Tasks / Messages / Store / Integrations). Several pointed at the same data:

- "Overview" duplicated "Projects" and "Tasks" widgets that already had dedicated tabs.
- "Store" was its own tab even though it's a small surface — and "Integrations" was a top-level tab for what is really a single optional CRM connect form.

The new IA collapses this to **five tabs that are strictly disjoint**:

- **Home** — KPIs + the task kanban (drag/drop), an Add-ons & services card (the old Store), and a "Need help / your CRM" card (the old Integrations).
- **Projects** — only project timelines.
- **Messages** — only the thread + composer (with image attachments).
- **Files** — the private project files, with image previews. Was previously hidden inside the Overview payload.
- **Billing** — subscriptions + recent orders.

Admin nav was equivalently simplified from nine tabs to **six**:

- **Command** (overview), **Inbox** (messages, with image attachments), **Clients** (customers + CRM contacts + opportunities, previously three tabs), **Work** (tasks kanban + projects, previously two tabs), **Billing** (subscriptions + product → feature map, previously two tabs), **Settings** (sync status + store category).

The deleted tabs are not re-implemented as duplicate menu entries pointing at the same data; they are merged into the new tab they belong to.

## What's in release 1.2.x

This release implements the marketing-agency-dashboard backlog from the Oversee research brief and the requirements review:

- **Project files**: project- and task-scoped uploads with stage folders (`intake`, `working`, `deliverables`, `archive`), versioning, visibility flags (`client` vs `internal`), approval workflow (`pending`/`approved`/`rejected`/`not_required`), view + download counters, and PHP-served downloads from a plugin-private uploads directory (`wp-content/uploads/oversee-private/`) protected by `Require all denied`/`Deny from all`/`web.config` files. Media Library is **never** used for client files. See "Project files & private serving" below.
- **Loom / YouTube / Vimeo instruction media**: tasks and milestones each accept a single instruction-video URL, validated against an allow-list pattern. The plugin never accepts arbitrary iframe HTML — the frontend reconstructs an embed from the validated provider+id.
- **Monday-style task board**: status set is `not_started`, `in_progress`, `needs_your_input`, `waiting_on_oversee`, `completed`, `blocked`, `cancelled`. Tasks have `task_type` (`client_required`/`internal`), `visibility` (`client`/`internal`), `priority`, `assignee_user_id`, `internal_notes` (admin-only), and `instruction_video_url`. Customers see only `client`-visibility rows; admins see everything.
- **Task comments**: customers and staff post comments per task. Comments have a visibility flag — staff comments default to `shared` but can be marked `internal` (staff-only). Customers can never post internal comments and never see internal comments.
- **Subscription variants & sub-selections**: the product → feature map supports per-variation overrides (slug, label, required_steps). Order/subscription line items are extracted with `product_id`, `variation_id`, `pa_*` attributes, and display meta preserved. WC variation handling never explodes into fake products. Complex sub-selections live as `client_required` task forms attached to the project, not as new variations.
- **Required-step templates**: admins define onboarding step templates keyed by slug. When an order is paid (or a subscription activates) on a feature whose `required_steps` reference those templates, the plugin auto-creates `client_required` tasks tied to the new project.
- **Billing self-service via WooCommerce-native flows**: card details never enter this plugin. The customer billing payload exposes:
  - **Payment Methods** link (`/my-account/payment-methods/`)
  - **Add Payment Method** link
  - Per-subscription **Change Payment Method** link (`view-subscription/<id>/?change_payment_method=<id>`) gated by eligibility (active status, automatic gateway, future renewal scheduled, not staging)
  - Per-order **Pay** + **Invoice** + **View order** links
- **Pending Actions** aggregator: `/customer/pending-actions` returns the home-dashboard "what needs your attention" panel — tasks needing input, files awaiting client approval, failed/pending orders that need payment, and on-hold subscriptions whose card can be updated.
- **Subscription switching**: `/customer/subscriptions/<id>/switch-options` surfaces the native WC Subscriptions switch URL (or the unavailable reason: status, staging mode, etc.) — the plugin **never** custom-processes a payment or plan change.

## What's in earlier release 1.1.x

- **Two strictly separate dashboards.** `[oversee_customer_dashboard]` renders only the customer-facing surface (subscriptions, projects, tasks, messages, store, integrations). `[oversee_admin_dashboard]` is staff-only (`manage_woocommerce`/`manage_options`) and renders only the operational surface (inbox, projects, tasks, entitlements, customers, subscriptions, CRM, sync). The two never share navigation — even an admin user visiting the customer page sees the customer view only.
- **Dashboard store backed by existing WooCommerce products only.** The plugin **never** creates, seeds, or mocks products. It surfaces real `wc_get_products()` results — name, price, image, short description, permalink — pulled either from the admin-curated product → feature map or, optionally, every published product in an admin-selected WooCommerce category.
- **Checkout routes through real WooCommerce.** Every CTA is the product's live add-to-cart URL (`$product->add_to_cart_url()`); a "Checkout" link goes to `wc_get_checkout_url()`. There are no fake purchase buttons.
- **Two-way customer messaging** stored in custom tables and forwarded to the agency HighLevel sub-account via `POST /conversations/messages/inbound`. Outbound replies from HighLevel can be mirrored back via the webhook ingest endpoint.
- **Project timelines + milestones** with progress tracking and admin CRUD.
- **Client tasks/steps** that admins assign and customers can complete from the dashboard.
- **Feature entitlements + dashboard store** mapped from existing WooCommerce products. Buying a mapped product (or activating a subscription on a mapped product) automatically grants the entitlement and creates an associated project.
- **Customer-owned CRM connections** are stored in a separate table from the agency CRM credentials — they never mix.
- **Auto dashboard access**: a `access_oversee_dashboard` capability is granted on order processing/completion and on subscription activation.

### Setup / empty state

When WooCommerce isn't active, or no products are mapped (and no store category is configured), the customer dashboard's store renders a clearly worded setup empty state:

> *No eligible WooCommerce products mapped yet. An admin can map existing products to the dashboard under Oversee Admin → Entitlements.*

The plugin never falls back to placeholder products or fake checkout flows.

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
│   ├── class-ocd-tasks.php              # Client tasks (Monday-style + comments)
│   ├── class-ocd-entitlements.php       # Feature entitlements + product/variation map
│   ├── class-ocd-customer-crm.php       # Customer-owned CRM connections (separate)
│   ├── class-ocd-store.php              # In-dashboard feature store (WC product passthrough)
│   ├── class-ocd-project-files.php      # Project-scoped private file storage + approvals
│   ├── class-ocd-instruction-media.php  # Loom/YouTube/Vimeo URL validator
│   ├── class-ocd-billing.php            # WooCommerce-native payment-method links + eligibility
│   ├── class-ocd-pending-actions.php    # Aggregated "what needs your attention"
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

Map **existing** WooCommerce products to dashboard feature slugs under **Oversee Admin → Entitlements**. The mapping UI is a live product search (by name or SKU) that talks to `wc_get_products()` server-side — picking from products that already exist in WooCommerce. The plugin will refuse to save a mapping for a product ID that does not exist in WooCommerce.

Optionally, an admin can pick a single existing **product category**; every published product in that category will be surfaced in the customer dashboard store (in addition to any explicit mappings). This lets the agency curate a "Dashboard add-ons" category in WooCommerce and have the dashboard mirror it without ever needing a duplicate catalog.

When a customer's order moves to `processing` or `completed` (or a subscription becomes `active`), the plugin:

- Adds the `access_oversee_dashboard` capability to the customer
- Grants matching entitlements (active) for any line items whose product IDs are in the map
- Creates a project record per purchased product if one doesn't exist

Subscription transitions to `on-hold`, `pending-cancel`, `cancelled`, or `expired` propagate to entitlement statuses.

### Customer checkout flow

The dashboard store does not implement its own cart or payment processing. Each product card has:

- **Add to cart** → `$product->add_to_cart_url()` (the live WooCommerce URL)
- **Checkout** → `wc_get_checkout_url()`
- **Details** → `get_permalink($product)` (the standard product page)

This means the agency configures payment gateways, taxes, shipping, etc. once in WooCommerce. The dashboard is a discovery surface, not a parallel store.

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
| GET | `/customer/store` | Existing WC products only; CTA → real Woo `add_to_cart_url()` and `wc_get_checkout_url()`. Renders setup empty state when WC inactive / no mapping. | Logged-in |
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
| GET, POST | `/admin/product-map` | Read / write WC product → feature mapping. POST validates that every product ID exists in WooCommerce. | manage_woocommerce |
| GET | `/admin/wc-products?search=…` | Server-side search of *existing* WooCommerce products (id, name, sku, image, price). Used to power the mapping picker. | manage_woocommerce |
| GET, POST | `/admin/store-category` | Optional: pick an existing WC category whose products are surfaced in the store. | manage_woocommerce |
| POST | `/webhooks/highlevel` | HighLevel webhook ingestion + outbound mirroring | HMAC `X-OCD-Signature` |
| GET, POST | `/customer/files` | List/upload customer-owned project/task files (multipart `file`, optional `project_id`, `task_id`, `folder`) | Logged-in |
| POST | `/customer/files/{id}/approval` | Customer approve/reject deliverable + comment | Logged-in |
| GET | `/files/{id}/download` | Permission-checked PHP-served download (never a raw filesystem URL) | Logged-in |
| GET, POST | `/admin/files` | Admin list / upload (visibility flag, approval status, folder) | manage_woocommerce |
| DELETE | `/admin/files/{id}` | Admin remove (also deletes from disk) | manage_woocommerce |
| POST | `/admin/files/{id}/archive` | Move file to archive folder | manage_woocommerce |
| POST | `/admin/files/{id}/approval` | Admin set approval state + comment | manage_woocommerce |
| GET, POST | `/customer/tasks/{id}/comments` | Owner-only: list shared comments / post a comment | Logged-in |
| GET, POST | `/admin/tasks/{id}/comments` | Admin list (incl. internal) / post (visibility=shared|internal) | manage_woocommerce |
| DELETE | `/admin/task-comments/{id}` | Remove comment | manage_woocommerce |
| GET | `/customer/pending-actions` | Aggregated "what needs your attention" list | Logged-in |
| GET | `/customer/billing` | WC-native payment-method links, change-payment eligibility, invoices, pay-now | Logged-in |
| GET | `/customer/task-status-sets` | Status keys, labels, groups, priorities for the UI | Public |
| GET | `/customer/subscriptions/{id}/switch-options` | WC-native switch URL or unavailable reasons | Logged-in |
| GET, POST | `/admin/required-step-templates` | Admin onboarding-step library | manage_woocommerce |

### Subscription statuses

The plugin treats these as the canonical valid statuses (matching WooCommerce Subscriptions):
`active`, `pending`, `on-hold`, `pending-cancel`, `cancelled`, `expired`.

## Project files & private serving

Client files are stored in a plugin-private directory **outside** of `wp-content/uploads/<year>/<month>/` so that no Media Library URL ever exposes them.

```
wp-content/uploads/oversee-private/
├── .htaccess              # Require all denied / Deny from all
├── web.config             # IIS deny-all rule
├── index.html             # empty, for directory listing protection
└── <user_id>/<timestamp>-<random>.<ext>
```

- The on-disk filename is randomized (`YYYYMMDD-HHMMSS-<token>.<ext>`); the original filename is stored in the DB row only.
- MIME type is verified server-side via `finfo_open(FILEINFO_MIME_TYPE)`. The allow-list (`OCD_Project_Files::ALLOWED_MIME`) covers images, PDFs, Office documents, plain text, CSV, audio, and MP4/MOV. Disallowed types (e.g. `application/x-php`, `text/html`) are rejected.
- File size is capped at `OCD_Project_Files::MAX_BYTES` (25 MB) server-side.
- Downloads are served **only** through `GET /wp-json/ocd/v1/files/{id}/download`, which:
  1. Verifies the requesting user is either the file owner or an admin (`manage_woocommerce`/`manage_options`).
  2. Refuses any internal-visibility file for non-admins.
  3. Bumps the view + download counters.
  4. Streams the bytes via `readfile()` with `Content-Disposition: inline; filename="<name>"` and `X-Content-Type-Options: nosniff`.
- The DB row carries the SHA-256 checksum, version (auto-incremented per project+filename), uploader role, view/download counts, and approval metadata.

> **nginx note:** `.htaccess` is ignored by nginx. Mirror the deny rule in your nginx config:
> ```
> location ^~ /wp-content/uploads/oversee-private/ { deny all; return 403; }
> ```
> Apache and IIS users get the included `.htaccess` and `web.config` automatically.

### Approval workflow

Files have one of four approval states:

| State | Meaning |
|---|---|
| `not_required` | Default — no approval gate |
| `pending` | Admin uploaded a deliverable; client must approve |
| `approved` | Client approved (with optional comment) |
| `rejected` | Client rejected (with comment) |

Pending files are surfaced via `/customer/pending-actions` so they appear on the home dashboard.

## Loom / YouTube / Vimeo instruction media

`OCD_Instruction_Media::validate_video_url($url)` accepts only `https://` URLs from these providers and returns `{provider, url, embed_url, id}`:

- Loom: `https://www.loom.com/share/<id>` or `/embed/<id>` → embed: `https://www.loom.com/embed/<id>`
- YouTube: `https://www.youtube.com/watch?v=<id>`, `https://youtu.be/<id>`, `/embed/<id>`, `/shorts/<id>`, `youtube-nocookie.com` → embed: `https://www.youtube-nocookie.com/embed/<id>`
- Vimeo: `https://vimeo.com/<id>` or `/video/<id>` → embed: `https://player.vimeo.com/video/<id>`

Anything else is rejected. Tasks, milestones, and required-step templates only ever store the validated URL + provider; the frontend reconstructs the embed iframe from those fields, so untrusted markup never round-trips through the database.

`OCD_Instruction_Media::validate_image_url($url)` accepts only images served from the local site host. Off-host image URLs are rejected to prevent SSRF and tracking pixels.

## Variation / sub-selection strategy

The product → feature map (`ocd_product_feature_map`) is variation-aware:

```php
[
  42 => [
    'slug' => 'analytics-pro',
    'label' => 'Analytics Pro',
    'required_steps' => ['onboarding-call'],
    'variations' => [
      101 => ['slug' => 'analytics-pro-monthly', 'label' => 'Analytics Pro · Monthly'],
      102 => ['slug' => 'analytics-pro-annual',  'label' => 'Analytics Pro · Annual',
              'required_steps' => ['brand-assets']],
    ],
  ],
]
```

`OCD_Entitlements::feature_for_product($product_id, $variation_id)` resolves a line item into the most-specific feature: variation entry wins over parent entry. Unknown product IDs are rejected by the admin mapping endpoint (every ID must exist in WooCommerce).

For complex sub-selections (the kind that would explode WC variation counts), use **required-step templates** instead: store a per-feature list of step slugs (`required_steps`), and define each step in `ocd_required_step_templates`. When an order or subscription with that feature is paid, the plugin auto-creates a `client_required` task for each step on the customer's project. The form responses live as task records or comments — never as fake products.

`OCD_Entitlements::extract_line_item($wc_line_item)` normalises a WooCommerce REST line-item payload into:
- `product_id`, `variation_id`, `name`, `parent_name`, `quantity`
- `attributes` — keyed by attribute slug (the `pa_` prefix is stripped)
- `meta` — display meta (private `_`-prefixed keys are dropped)

This is the canonical way to display sub-selections on the dashboard.

## Billing — WooCommerce-native flows only

This plugin **does not** collect, transmit, or store card details. Every billing action links to the WooCommerce-native flow:

| Customer action | Endpoint we hand them |
|---|---|
| Manage saved cards | `/my-account/payment-methods/` |
| Add a new card | `/my-account/add-payment-method/` |
| Update card on a single subscription | `/my-account/view-subscription/<id>/?change_payment_method=<id>` |
| Pay a pending/failed order | `/checkout/order-pay/<id>/?key=<order_key>` |
| Download invoice / view order | `/my-account/view-order/<id>/` |
| Switch / upgrade / downgrade subscription | `/my-account/view-subscription/<id>/` (native WC switch CTA) |

`OCD_Billing::subscription_payment_eligibility($sub)` enforces the documented WooCommerce constraints before showing the "Update card" CTA:
- subscription is `active`
- it has an automatic-payment gateway (`payment_method` populated)
- a future payment is scheduled (`next_payment_date_gmt`)
- site is not in staging mode (`WP_STAGING`, `WCS_STAGING`, or `WP_ENVIRONMENT_TYPE` of `staging`/`development`)

Subscription switching: `/customer/subscriptions/{id}/switch-options` returns `switch_available: true|false` and a list of `unavailable_reasons` so the UI can render the native CTA only when it'll work.

## Marketing-agency dashboard workflow

Putting it all together, the workflow informed by the research brief is:

1. **Pending Actions panel** on the customer home: tasks needing input, deliverables awaiting approval, failed payments, on-hold subscriptions whose card can be rotated. (`/customer/pending-actions`)
2. **Project detail**: phase timeline (milestones with `phase_label`, instruction video, image), Monday-style task board grouped by status, file manager with `intake/working/deliverables/archive` folders, two-way comments per task.
3. **Client-required steps**: paying for a feature whose `required_steps` reference templates auto-creates a checklist of `client_required` tasks. The customer sees them in `Needs Your Input`. Each step can carry its own Loom video.
4. **Files**: client uploads land in `intake`; staff uploads land in `working` or `deliverables`. Deliverables can be flagged `pending` for client approval, surfaced in Pending Actions.
5. **Billing**: every CTA is a deep link to the appropriate WooCommerce My Account page. No card data flows through this plugin.
6. **Subscription self-service**: "Switch" / "Upgrade" / "Downgrade" deep-link to the native WC view-subscription page. The plugin only computes URLs and eligibility — it never modifies subscription state outside `pending-cancel`.
7. **Admin operational view**: file manager by client/project, full task board (incl. internal tasks + notes), instruction-media editor, product/variation mapping, required-step template library, sync health.

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
