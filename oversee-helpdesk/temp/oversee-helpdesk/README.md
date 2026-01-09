# Oversee Helpdesk

A complete white-label support ticket system with knowledge base for WordPress.

## Version 2.1.0

## Features

### Core Functionality
- **Ticket Management** - Full ticket lifecycle: create, assign, respond, close
- **Knowledge Base** - Articles, categories, search, and public help center
- **Admin Dashboard** - Standalone admin portal at `/support/admin/`
- **Multi-Agent Support** - Roles, permissions, and agent management

### White-Label Branding (v2.1)
- Company name, logo, and colors
- Dark/light logo variants for different backgrounds
- Custom login page with split-screen design
- Configurable background (gradient, solid color, or image)
- Welcome message customization
- Favicon support
- Footer copyright with dynamic tokens (`{year}`, `{company}`)
- Custom CSS injection

### License System (v2.1)
- License key activation/deactivation
- 24-hour caching for valid licenses
- 7-day grace period when server unreachable
- Site migration detection
- Configurable redirect URL for unlicensed state
- Optional "License Required" landing page
- Demo mode (allow KB access without license)
- Admin bar license indicator

### Hard Lock Enforcement (v2.1)
- Public pages redirect/block when unlicensed
- REST API returns 403 for protected endpoints
- Grace period allows continued operation
- Demo mode exemption for KB pages

## Installation

1. Upload the `oversee-helpdesk` folder to `/wp-content/plugins/`
2. Activate through WordPress admin
3. Enter your license key at **Helpdesk → Settings → License**
4. Configure branding at **Helpdesk → Settings → Branding**

## URLs

After activation, the following URLs become available:

### Public (Customer-Facing)
- `/support/` - Help center home
- `/support/kb/` - Knowledge base
- `/support/kb/{category-slug}/` - Category listing
- `/support/kb/{category-slug}/{article-slug}/` - Article view
- `/support/tickets/` - My tickets (logged-in users)
- `/support/tickets/new/` - Submit ticket
- `/support/tickets/{id}/` - View ticket

### Admin Portal
- `/support/admin/` - Dashboard
- `/support/admin/login/` - Agent login
- `/support/admin/tickets/` - Ticket management
- `/support/admin/articles/` - KB article management
- `/support/admin/settings/` - Portal settings

## Configuration

### License Settings

Navigate to **Helpdesk → Settings → License**:

| Option | Description |
|--------|-------------|
| License Key | Your Oversee Helpdesk license key |
| Redirect URL | Where to send users when unlicensed (default: pricing page) |
| Demo Mode | Allow KB access without license |
| Show License Page | Show branded internal page instead of redirect |

### Branding Settings

Navigate to **Helpdesk → Settings → Branding**:

| Section | Options |
|---------|---------|
| Company Info | Name, tagline, support email/phone |
| Logo & Images | Primary logo, dark logo, favicon |
| Colors | Primary, secondary, accent colors |
| Login Page | Background style, welcome message |
| Footer | Copyright text, additional HTML |
| Custom | CSS injection |

## REST API

All endpoints require authentication (except public KB endpoints).

### Authentication
- WordPress cookie authentication
- Bearer token authentication (for iframe embedding)

### Endpoints

```
GET    /wp-json/oversee/v1/auth/me
GET    /wp-json/oversee/v1/dashboard/stats
GET    /wp-json/oversee/v1/tickets
POST   /wp-json/oversee/v1/tickets
GET    /wp-json/oversee/v1/tickets/{id}
PATCH  /wp-json/oversee/v1/tickets/{id}
POST   /wp-json/oversee/v1/tickets/{id}/reply
```

### License Enforcement
Protected endpoints return `403` with error code `license_required` when:
- No valid license
- License expired
- Not in grace period

## Hooks & Filters

### Actions
```php
// After ticket created
do_action('oversee_ticket_created', $ticket_id, $ticket_data);

// After ticket reply
do_action('oversee_ticket_reply', $ticket_id, $reply_id);

// After agent status change
do_action('oversee_agent_status_changed', $user_id, $is_online);
```

### Filters
```php
// Modify KB search results
add_filter('oversee_kb_search_results', function($results, $query) {
    return $results;
}, 10, 2);

// Modify branding defaults
add_filter('oversee_branding_defaults', function($defaults) {
    $defaults['primary_color'] = '#0066cc';
    return $defaults;
});
```

## Database Tables

The plugin creates the following tables:

| Table | Purpose |
|-------|---------|
| `wp_oversee_tickets` | Ticket records |
| `wp_oversee_messages` | Ticket messages/replies |
| `wp_oversee_agents` | Agent metadata |
| `wp_oversee_sessions` | Customer sessions |
| `wp_oversee_canned` | Canned responses |
| `wp_oversee_push_subscriptions` | Push notification subscriptions |

## User Roles & Capabilities

### Roles
- `oversee_admin` - Full access to all features
- `oversee_agent` - Ticket management, limited settings

### Capabilities
- `oversee_view_dashboard` - Access admin dashboard
- `oversee_manage_tickets` - Create/assign/manage tickets
- `oversee_respond_tickets` - Reply to tickets
- `oversee_manage_kb` - Manage knowledge base articles
- `oversee_manage_settings` - Access settings

## Changelog

### 2.1.0
- Enhanced white-label branding (login page, dark logo, background options)
- Improved license system (intelligent caching, grace period, migration detection)
- Hard lock enforcement with demo mode option
- Admin bar license indicator
- "License Required" landing page option

### 2.0.0
- Complete template system rebuild
- Centralized Template Loader
- Fixed duplicate header bug
- Improved branding CSS injection

### 1.0.0
- Initial release

## Support

For support, contact support@overseeagency.com or visit https://overseeagency.com/support

## License

GPL v2 or later
