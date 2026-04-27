# Oversee Dashboard — Admin Guide

This guide is for Oversee staff configuring the plugin on a fresh WordPress install. It assumes the parent **Hub** theme, **WooCommerce**, **WooCommerce Subscriptions**, and **WooCommerce Stripe** are already installed and active.

## Install order

1. Activate parent **Hub** theme.
2. Activate **Oversee Hub Child** (`/wp-content/themes/oversee-hub-child/`).
3. Activate **Oversee Customer Dashboard** plugin (`/wp-content/plugins/oversee-customer-dashboard/`). Activation runs:
   - schema migrations (legacy + board tables)
   - role registration (`oversee_client`, `oversee_account_manager`, `oversee_specialist`, `oversee_contractor`, `oversee_admin`)
   - capability backfill on `customer`, `subscriber`, `administrator`, `shop_manager`
4. Create a Page with slug `dashboard`. Set Page Template to **Oversee Dashboard (Full-Width)**.
5. Create Pages with slugs `login` and `register`. Set Template to **Oversee Login (Full-Width)**.
6. Build the SPA: `cd oversee-customer-dashboard/spa && npm install && npm run build`.

The plugin enqueues the built bundle from `assets/build/`. Until the bundle exists you'll see the SPA mount but no UI; rebuild after every front-end change.

## WordPress options / constants

| Setting | Plugin option | Constant / env |
|---|---|---|
| HighLevel API token | `ocd_settings.highlevel_token` | `HIGHLEVEL_API_TOKEN` |
| HighLevel location | `ocd_settings.highlevel_location_id` | `HIGHLEVEL_LOCATION_ID` |
| HighLevel base URL | `ocd_settings.highlevel_base_url` | `HIGHLEVEL_BASE_URL` |
| Magic-link endpoint | `ocd_settings.highlevel_magic_link_endpoint` | `HIGHLEVEL_MAGIC_LINK_ENDPOINT` |
| WooCommerce store URL | `ocd_settings.wp_base_url` | `OVERSEE_WP_BASE_URL` |
| Woo consumer key | `ocd_settings.woo_consumer_key` | `WOOCOMMERCE_CONSUMER_KEY` |
| Woo consumer secret | `ocd_settings.woo_consumer_secret` | `WOOCOMMERCE_CONSUMER_SECRET` |

Constants always win over options. Define them in `wp-config.php` for production credentials.

## HighLevel SSO setup

The dashboard embeds HighLevel UI in iframes. Each embed surface (`conversations`, `calendar`, `reports`, `documents`, `reputation`) is fetched server-side via a magic-link endpoint:

```
POST {HIGHLEVEL_MAGIC_LINK_ENDPOINT}
Authorization: Bearer {token}
Body: { "contactId": "<ohl_contact_id>", "surface": "<surface>" }
Expected response: { "url": "https://...", "expiresAt": "..." }
```

If your HighLevel deployment doesn't expose this endpoint yet, use the filter:

```php
add_filter('ocd_highlevel_magic_link', function ($v, $contact_id, $surface, $endpoint) {
    return ['url' => get_my_custom_link($contact_id, $surface), 'expires_at' => null];
}, 10, 4);
```

Each user must have an `ohl_contact_id` user meta value. The CRM↔WC contact sync is handled outside this plugin; populate this meta via your existing sync (or set it manually in WP admin → Users → Profile).

When the magic-link is unconfigured, embed pages render a friendly "isn't connected yet" empty state with a technical-detail disclosure for admins.

## SKU → board template mapping

1. WP admin → **Oversee Customer Dashboard → Board Templates → Add New**.
2. Title the template (e.g. "Web audit").
3. In post content, paste a JSON skeleton:

```json
{
  "groups": [
    {
      "title": "Discovery",
      "items": [
        {"title": "Kickoff call",  "status": "not_started"},
        {"title": "Audit deliverable", "status": "not_started"}
      ]
    }
  ]
}
```

4. In the meta sidebar set **`_oversee_template_skus`** to a comma-separated list of WooCommerce SKUs that should spawn this template (e.g. `web-audit,audit-pro`). Use `default` as a catch-all.
5. When a customer's order completes, the plugin walks line items, looks up the template by SKU, and spawns one `project_board` per matching item. Variation IDs and any line-item meta (selected addons, attribute slugs) survive into `ocd_board_intake_responses` so the delivery team has the original answers.

## WooCommerce settings

- Make sure **Account creation** → "Allow customers to create an account during checkout" is enabled. This fires `woocommerce_created_customer`, which the plugin uses to promote the new user to `oversee_client` and send the welcome email.
- WooCommerce Subscriptions: status changes (active / on-hold / cancelled / expired) and renewal payments map to `OCD_Boards::archive_board`, `OCD_Boards::reset_monthly_group`, etc. No additional config needed.

## Email delivery

The plugin uses `wp_mail` for the welcome email. For reliable production delivery install **WP Mail SMTP** or **Resend** and configure your transactional sender. The branded HTML email wrapper from the child theme is applied automatically to WooCommerce customer emails (order confirmations, etc.).

## Dark mode

Per-user dark mode preference is stored in `oversee_dark_mode` user meta and persisted via `POST /wp-json/ocd/v1/me/preferences`. The dashboard page template adds the `oversee-dark` class to `<html>` server-side so there's no light-mode flash.

## Staging checklist

- [ ] Activate child theme over Hub parent — confirm marketing site still renders Hub styles.
- [ ] Confirm `/dashboard/`, `/login/`, `/register/` use the correct templates.
- [ ] Confirm logged-out visitor on `/dashboard/foo` redirects to `/login/?redirect_to=/dashboard/foo`.
- [ ] Run the WP-shim test harness: `php oversee-customer-dashboard/tests/test-bootstrap.php` and `tests/test-boards.php`.
- [ ] Check that `oversee_client`, `oversee_account_manager`, `oversee_specialist`, `oversee_contractor`, `oversee_admin` roles appear in WP admin → Users.
- [ ] Run a test WooCommerce order and confirm a board is spawned.
- [ ] Run a renewal cycle and confirm the monthly group resets.
- [ ] Toggle dark mode in the SPA and confirm the preference persists across reloads.

## Limitations / staging verification still required

- The HighLevel magic-link endpoint is not part of HighLevel's public API; the integrator must point the plugin at the correct internal endpoint or override via filter before the embed surfaces light up in production.
- The Cmd+K command palette is a UI shell and does not yet hit a search endpoint.
- The Kanban view on board detail doesn't yet wire `@dnd-kit` to the `/items/<id>/move` REST endpoint — drag/drop reordering ships in a follow-up.
- TipTap and Pusher are scaffolded but not yet integrated into board updates.
- Live Stripe checkout, branded WooCommerce email rendering, and the redirect chain on `/dashboard/` need to be tested end-to-end on staging — none of this PR has been verified against a running WP install.
