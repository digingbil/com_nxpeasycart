# NXP Easy Cart Documentation

Complete technical documentation for developers, merchants, and security auditors.

---

## 🏗️ Architecture & Design

### [architecture.md](architecture.md)

Component architecture, MVC structure, service layer design, and development patterns.

**Topics covered:**

- Namespaced MVC structure
- Dependency injection container
- Service layer (Cart, Order, Payment)
- Vue admin SPA architecture
- Progressive storefront islands

### [order-status.md](order-status.md)

Order status/tracking flow, tokenised storefront links, admin tracking fields, and JSON endpoints.

### [currency-localization.md](currency-localization.md) ⭐ **NEW v0.1.13**

Locale-aware currency formatting for international stores.

**Topics covered:**

- Automatic locale detection from Joomla site language
- Store-level locale override setting
- MoneyHelper centralized formatting
- Supported locale formats (Joomla-style and ICU-style)
- PHP intl extension requirements

---

## 🔒 Security Documentation

### [security-audit-fixes.md](security-audit-fixes.md) ⭐ **CRITICAL**

Detailed documentation of critical security vulnerabilities resolved during the 2025-11-27 security audit.

**Issues resolved:**

- **XSS vulnerability** in checkout success message (FIXED)
- **Stripe webhook forgery** - signature validation now mandatory (FIXED)
- **PayPal webhook forgery** - complete verification implemented (FIXED)

**Includes:**

- Vulnerability descriptions
- Attack scenarios
- Fix implementations
- Testing procedures
- Deployment requirements

### [security-rate-limiting.md](security-rate-limiting.md)

Rate limiting implementation for cart and payment endpoints.

**Topics covered:**

- RateLimiter service design
- PSR-16 cache backing
- Configuration via admin panel
- Per-IP, per-email, per-session limits

### [order-state-machine.md](order-state-machine.md) ⭐ **NEW v0.1.9**

Order state machine guards, webhook amount validation, and stale order cleanup.

**Topics covered:**

- Order state transition rules and validation
- Terminal states (refunded, canceled)
- Webhook amount variance detection (1 cent tolerance)
- Order review flag system (`needs_review`, `review_reason`)
- Stale order cleanup task plugin
- Graceful error handling for invalid transitions

### [manual-transactions.md](manual-transactions.md) ⭐ **NEW v0.1.9**

Manual transaction recording for offline payment methods (COD, Bank Transfer).

**Topics covered:**

- Recording payments for Cash on Delivery orders
- Recording payments for Bank Transfer orders
- Admin UI integration and form fields
- API endpoint documentation
- Audit trail and security considerations

---

## 📧 Email & Communications

### [email-templates.md](email-templates.md)

Transactional email system for order lifecycle notifications.

**Topics covered:**

- Order confirmation emails
- Shipping notification emails (with tracking)
- Refund notification emails
- Template customisation
- Email trigger points

---

## 🔐 Privacy & Compliance

### [gdpr.md](gdpr.md)

GDPR compliance implementation for data export and anonymisation.

**Topics covered:**

- Data export API (Article 20 - Data Portability)
- Anonymisation API (Article 17 - Right to Erasure)
- PII handling and retention
- Compliance checklist
- API endpoints and permissions

---

## ⚡ Performance & Optimization

### [performance-optimization.md](performance-optimization.md)

Admin SPA performance enhancements and caching strategies.

**Topics covered:**

- Cache-first data strategy (5-minute TTL)
- 60-80% reduction in API calls
- Skeleton loaders
- Layout shift elimination (CLS < 0.001)
- Performance tracking utilities

---

## 🧪 Testing & Quality Assurance

### [testing.md](testing.md)

Test automation blueprint covering unit, integration, and E2E tests.

**Topics covered:**

- PHPUnit setup (unit/integration)
- API contract tests
- Vue component unit tests
- Playwright E2E journeys
- Security test procedures

---

## 📦 Packaging & Deployment

### [packaging.md](packaging.md)

Release workflow, dependency management, and deployment procedures.

**Topics covered:**

- Composer vendor optimization
- Asset bundling (Vite)
- Language file distribution
- Joomla discovery mode
- Release checklist

### [asset-sync.md](asset-sync.md)

Automated asset manifest synchronization for cache-busting.

**Topics covered:**

- Vite hash generation
- `joomla.asset.json` updates
- Post-build automation
- Manual sync procedures

---

## 📊 Risk Management

### [risk-register.md](risk-register.md)

Component risk assessment, mitigation strategies, and monitoring.

**Topics covered:**

- Gateway misconfiguration risks
- Webhook failure handling
- Double-charge prevention
- Performance degradation
- Template conflicts

---

## 🚀 Quick Start

For first-time developers, read in this order:

1. **[architecture.md](architecture.md)** - Understand the component structure
2. **[security-audit-fixes.md](security-audit-fixes.md)** - Critical security requirements
3. **[testing.md](testing.md)** - Set up your test environment
4. **[packaging.md](packaging.md)** - Build and deploy

---

## 🛡️ Security Checklist (Production Deployment)

Before going live, ensure:

- [ ] Read `security-audit-fixes.md` completely
- [ ] Configure Stripe webhook secret in admin panel
- [ ] Configure PayPal webhook ID in admin panel
- [ ] Test webhook endpoints with real gateway data
- [ ] Enable rate limiting (see `security-rate-limiting.md`)
- [ ] Review `risk-register.md` mitigation steps
- [ ] Run security test suite (see `testing.md`)
- [ ] Monitor webhook logs for rejected requests
- [ ] Configure email server (SMTP) in Joomla Global Configuration
- [ ] Test all email templates (confirmation, shipped, refunded)
- [ ] Document GDPR data retention policy (see `gdpr.md`)
- [ ] Test GDPR export/anonymise endpoints
- [ ] Configure stale order cleanup threshold (see `order-state-machine.md`)
- [ ] Install and enable the cleanup task plugin
- [ ] Test order state transitions for proper validation
- [ ] Test manual payment recording for COD/Bank Transfer orders (see `manual-transactions.md`)

---

## 📝 Contributing

When adding new features:

1. Update relevant documentation in this directory
2. Add security considerations to `risk-register.md`
3. Create test cases in `testing.md`
4. Document performance impacts in `performance-optimization.md`

---

## 🔗 External Resources

- [Joomla 5 Developer Documentation](https://docs.joomla.org/J5.x:Developing_an_MVC_Component)
- [Stripe Webhook Security](https://stripe.com/docs/webhooks/signatures)
- [PayPal Webhook Verification](https://developer.paypal.com/api/rest/webhooks/)
- [OWASP XSS Prevention](https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html)
- [PCI DSS Requirements](https://www.pcisecuritystandards.org/document_library/)

---

**Last Updated**: 2025-12-04
**Component Version**: 0.1.13
