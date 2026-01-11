# Changelog

All notable changes to Oversee Helpdesk will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.7.1-beta] - 2026-01-11

### Fixed
- **CSS Variable Compatibility**
  - Template loader now outputs all CSS variable names that stylesheets expect
  - Added color-primary, color-primary-hover, color-primary-light variants
  - Added border, text-light, bg-body aliases for full compatibility
  - Hero section now uses dynamic hero text color instead of hardcoded white
  - Added body-bg and card-bg aliases for admin portal styling

### Improved
- **White-Label Preview UI**
  - Larger preview panel (560px wide) for better visibility
  - Increased preview element sizes and font sizes
  - Enhanced sticky positioning with proper scroll behavior
  - Improved shadow and border-radius for modern appearance
  - Updated responsive breakpoint to 1200px

## [2.7.0-beta] - 2026-01-11

### Added
- **Comprehensive White-Label Customization**
  - Full color customization for Knowledge Base pages (background, surface, text, borders, hero section)
  - Full color customization for Admin Portal pages (background, surface, text, borders)
  - Login page color customization with dedicated preview
  - All pages now respect brand colors for complete visual consistency

- **Redesigned White-Label Settings UI**
  - Side-by-side layout with controls on left and live preview on right
  - Section tabs: General, Knowledge Base, Admin Portal, Login Page
  - Sticky preview panel that stays visible while scrolling
  - Real-time preview updates as colors are changed
  - Dividers in previews that update with border color changes
  - Responsive design that stacks on smaller screens

- **CSS Design System**
  - Dynamic CSS variables generated from brand colors
  - RGB color values for RGBA shadow support
  - Automatic color calculations (darken/lighten variants)
  - Consistent styling across all public and admin pages

### Changed
- Removed dark mode in favor of full color customization
- Removed all hardcoded orange colors from templates
- Reorganized branding settings into logical sections
- Improved color picker interactions with instant preview feedback

### Fixed
- Template loader now outputs all custom color variables
- Hero section properly respects custom gradient colors
- Border and divider colors now consistent across components

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
