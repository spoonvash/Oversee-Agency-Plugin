# Changelog

All notable changes to Oversee Helpdesk will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.1.0] - 2025-01-09

### Added
- **White-Label Branding Enhancements**
  - Split-screen login page design with customizable sidebar
  - Dark logo variant for admin sidebar and login page
  - Login background options: gradient, solid color, or custom image
  - Welcome title and message customization
  - Footer copyright with dynamic tokens (`{year}`, `{company}`)
  - Tagline field for company description

- **License System Improvements**
  - Intelligent caching (24hr valid, 1hr invalid, 15min error)
  - 7-day grace period when license server unreachable
  - Site migration detection with admin warnings
  - Configurable redirect URL for unlicensed visitors
  - "License Required" landing page option (vs external redirect)
  - Demo mode to allow KB access without license
  - Admin bar license status indicator

- **Hard Lock Enforcement**
  - Public pages redirect/display license page when unlicensed
  - REST API returns 403 for protected endpoints
  - Grace period allows continued operation
  - Demo mode exemption for KB pages only

### Changed
- Updated branding settings UI with organized sections
- Improved media uploader handling for all image fields
- Better admin notices with context-aware display
- Enhanced CSS variable system for consistent styling

### Fixed
- Admin sidebar now properly displays branded logo
- Template Loader properly injects CSS variables

## [2.0.0] - 2025-01-08

### Added
- Centralized Template Loader system
- Content-only template architecture

### Fixed
- Critical duplicate header bug in public templates
- Inline CSS causing double renders

### Changed
- All 8 public templates converted to content-only format
- CSS moved from inline to external stylesheets
- Branding class no longer outputs via wp_head

## [1.0.0] - 2024-12-01

### Added
- Initial release
- Ticket management system
- Knowledge base with categories
- Admin dashboard portal
- Agent management
- REST API
- License validation
- Basic branding options
