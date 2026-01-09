# Changelog

All notable changes to NXP Easy Cart will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.3.2] - 2025-01-09

### Fixed

- Minor bug fixes and stability improvements
- Admin UI polish and edge case handling

## [0.3.1] - 2025-01-05

### Added

- **Variant images**: Per-variant image support for storefront image switching
  - Product gallery automatically switches when variant is selected
  - Fallback to main product images when variant has none
  - Import/export support for variant images in all formats (WooCommerce, Shopify, native)

## [0.3.0] - 2024-12-28

### Added

- **Import/Export system**: Comprehensive product catalogue migration and backup
  - Multi-platform support: VirtueMart, WooCommerce, HikaShop, Shopify, native JSON
  - Two-phase import with preview and validation
  - Dry-run mode for testing imports
  - Category and tag mapping during import
  - Full export including variants, images, and digital files

## [0.2.0] - 2024-12-20

### Added

- **Sale pricing for variants**: Time-limited discounted prices on individual product variants
  - Per-variant sale price, start date, and end date (all optional)
  - Automatic activation/deactivation based on dates
  - Visual indicators: sale badges, strikethrough prices, highlighted sale prices
  - Works across product pages, category listings, cart, and checkout
  - Coupon stacking support (sale price used as base for percentage discounts)
  - Admin UI with datetime-local inputs and UTC conversion

## [0.1.14] - 2024-12-15

### Added

- **Digital products**: Sell downloadable files with secure delivery
  - 47 supported file types across documents, audio, video, images, archives, ebooks
  - Configurable download limits and link expiry
  - Auto-fulfillment for digital-only orders
  - Secure download URLs with token validation

### Security

- Digital file upload validation with MIME type checking
- Dangerous extension blocklist
- Upload directory protection

## [0.1.12] - 2025-12-11

### Fixed

- Uninstall SQL now executes correctly - changed manifest `charset="utf8mb4"` to `charset="utf8"` (Joomla's installer only recognizes `utf8`)
- Added `SET FOREIGN_KEY_CHECKS = 0/1` around DROP statements in uninstall SQL to prevent FK constraint errors

## [0.1.11] - 2025-12-09

### Added

- Initial public release of NXP Easy Cart for Joomla 5+ and 6+
- Complete admin SPA built with Vue 3 (Dashboard, Products, Categories, Orders, Customers, Coupons, Tax, Shipping, Settings)
- Mobile-responsive admin UI with card-based table layouts and touch-friendly controls
- Server-rendered storefront with Vue "islands" for enhanced interactivity
- Product management with variants, images, and multi-category support
- Order management with status tracking, transaction history, and manual payment recording
- Customer management with order history
- Coupon system with percentage and fixed discounts
- Tax rates management with country/region support and inclusive/exclusive options
- Shipping rules management with flat rate and free-over-threshold options
- Single-currency MVP with configurable base currency and locale-aware formatting
- Stripe and PayPal payment gateway integration (tokenized/hosted for PCI SAQ-A compliance)
- Automatic PayPal webhook payment capture
- GDPR-compliant data export and anonymization endpoints
- Comprehensive audit logging for order lifecycle events
- Rate limiting for cart operations, coupon attempts, and checkout
- Order state machine with strict transition guards and review flagging
- Webhook amount variance detection for payment integrity
- Stale order cleanup via Joomla Scheduled Task plugin
- Email notifications for order confirmation, shipping, and refunds
- Public order status pages with tokenized access
- "My Orders" authenticated customer portal
- Onboarding wizard for quick store setup
- Mini cart module (`mod_nxpeasycart_cart`) for template integration
- SEF URL routing with SEO-friendly slugs and canonical product URLs
- Schema.org structured data for products
- Visual customization settings with template-aware color detection
- Floating checkout summary bar for improved UX
- Cache-first data strategy in admin SPA with performance tracking

### Security

- All endpoints protected with Joomla ACL and CSRF tokens
- Server-side price recalculation to prevent tampering (database is single source of truth)
- Coupon discount recalculation from database prices
- Mandatory webhook signature validation for Stripe and PayPal
- Idempotent payment and webhook handling
- No credit card data storage (gateway tokenization)
- XSS prevention with proper output escaping and InputFilter for rich text
- Rate limiting on sensitive operations
- Session regeneration after checkout

[Unreleased]: https://github.com/nexusplugins/com_nxpeasycart/compare/v0.3.2...HEAD
[0.3.2]: https://github.com/nexusplugins/com_nxpeasycart/compare/v0.3.1...v0.3.2
[0.3.1]: https://github.com/nexusplugins/com_nxpeasycart/compare/v0.3.0...v0.3.1
[0.3.0]: https://github.com/nexusplugins/com_nxpeasycart/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/nexusplugins/com_nxpeasycart/compare/v0.1.14...v0.2.0
[0.1.14]: https://github.com/nexusplugins/com_nxpeasycart/compare/v0.1.12...v0.1.14
[0.1.12]: https://github.com/nexusplugins/com_nxpeasycart/compare/v0.1.11...v0.1.12
[0.1.11]: https://github.com/nexusplugins/com_nxpeasycart/releases/tag/v0.1.11
