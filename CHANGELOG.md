# Changelog

All notable changes to NXP Easy Cart will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.4] - 2025-11-28

### Added

- Initial release of NXP Easy Cart for Joomla 5+ and 6+
- Complete admin SPA built with Vue 3 (Dashboard, Products, Categories, Orders, Customers, Coupons, Settings, Logs)
- Mobile-responsive admin UI with card-based table layouts and touch-friendly controls
- Server-rendered storefront with Vue "islands" for enhanced interactivity
- Product management with variants, images, and categories
- Order management with status tracking and transaction history
- Customer management with order history
- Coupon system with percentage and fixed discounts
- Single-currency MVP with configurable base currency
- Stripe and PayPal payment gateway integration (tokenized/hosted for PCI SAQ-A compliance)
- GDPR-compliant data export and anonymization
- Comprehensive audit logging
- Rate limiting for cart operations and coupon attempts
- Security hardened: CSRF protection, input sanitization, prepared queries, HTTPS enforcement
- Onboarding wizard for quick store setup
- Mini cart module (`mod_nxpeasycart_cart`) for template integration
- SEF URL routing with SEO-friendly slugs
- Schema.org structured data for products

### Security

- All endpoints protected with Joomla ACL and CSRF tokens
- Server-side price validation to prevent tampering
- Idempotent payment and webhook handling
- No credit card data storage (gateway tokenization)

[Unreleased]: https://github.com/nexusplugins/com_nxpeasycart/compare/v0.1.4...HEAD
[0.1.4]: https://github.com/nexusplugins/com_nxpeasycart/releases/tag/v0.1.4
