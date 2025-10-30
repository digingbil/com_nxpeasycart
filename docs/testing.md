# Testing Blueprint

NXP Easy Cart ships with multiple surfaces (Joomla MVC, JSON APIs, Vue admin SPA, storefront). The following plan keeps automated coverage lean while giving us fast feedback loops aligned with the “ten-minute setup” goal.

## 1. Unit tests (PHPUnit)

- **Scope**: domain services (`CartService`, `OrderService`, `CustomerService`, `CouponService`, `TaxService`, `ShippingRuleService`, `SettingsService`, `AuditService`), helper utilities (config, money, slug generation), table validation rules.
- **Structure**: `tests/phpunit/unit/...` mirroring namespace; one test class per service/table.
- **Fixtures**: rely on SQLite in-memory where possible; seed with thin dataset builders to avoid brittle fixtures.
- **Assertions**: focus on invariants—currency enforcement, state transitions, transactional integrity, JSON serialisation helpers.

## 2. Integration tests (PHPUnit)

- **Scope**: Joomla MVC flows that cross controller ↔ model ↔ table boundaries (Products CRUD, Orders API, Cart session bootstrap).
- **Harness**: spin up Joomla Test Application via `Joomla\CMS\Test\TestCaseDatabase`; transact migrations in `setUpBeforeClass`.
- **Endpoints**: exercise JSON controller tasks via `JApplicationWebTestCase` mocking CSRF tokens + ACL.

## 3. API contract tests

- **Consumer**: Postman/Insomnia collection captured under `tests/contracts/`, auto-run via `newman` in CI for smoke validation.
- **Automation**: `npm run test:api` wrapper executing Newman against local Joomla instance with seed data.
- **Outputs**: ensure RFC-7807 structure, pagination envelope, ACL/CSRF failure paths.

## 4. Frontend (Vue) tests

- **Unit**: Vitest + Vue Test Utils for composables (`useProducts`, `useOrders`, `useCustomers`, `useCoupons`, `useSettings`, `useTaxRates`, `useShippingRules`, `useLogs`) and leaf components. Mock fetch client, validate optimistic updates, error handling, and translation helpers (placeholder substitution/currency fallbacks).
- **Component snapshots**: minimal usage; prefer behavioural assertions to avoid churn.

## 5. End-to-end (Playwright)

- **Journeys**: admin login → product creation → order review; storefront product browse → add to cart → checkout placeholder.
- **Environment**: boot Joomla via `docker-compose` fixture, seed database with migrations + fixtures.
- **Stability**: wrap Joomla login and CSRF acquisition in custom Playwright helpers.

## 6. Static analysis & linting

- **PHPStan**: level 6 baseline, run on each PR (`composer stan`).
- **PHP-CS-Fixer**: PSR-12 with project tweaks (`composer lint`).
- **ESLint**: Vue + recommended rules; format via Prettier.

## 7. CI wiring

- GitHub Actions (or GitLab CI) stages:
  1. `install`: cache Composer/npm dependencies.
  2. `lint`: PHP-CS-Fixer (dry-run) + ESLint.
  3. `static`: PHPStan.
  4. `unit`: PHPUnit (unit + integration suites).
  5. `api`: Newman contract run (optional nightly).
  6. `e2e`: Playwright against disposable Joomla container.
- Artefacts: coverage reports (PHPUnit, Playwright video on failure).

## 8. Manual QA checklist (release candidates)

- Smoke install via Joomla Discover.
- Admin SPA navigation (products/orders).
- Storefront product page render, SEO meta verified with browser debugger.
- Payment sandbox round-trip (once gateway wired).
