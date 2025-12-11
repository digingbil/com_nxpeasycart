# Changelog

All notable changes to NXP Easy Cart will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/nexusplugins/com_nxpeasycart/compare/v0.1.12...HEAD
[0.1.12]: https://github.com/nexusplugins/com_nxpeasycart/compare/v0.1.11...v0.1.12
[0.1.11]: https://github.com/nexusplugins/com_nxpeasycart/releases/tag/v0.1.11
