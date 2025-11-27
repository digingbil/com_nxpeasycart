### Goal
Ship a minimal-but-complete order status experience that fits NXP Easy Cart’s current architecture, respects the single-currency MVP and security blueprint, and can be released quickly. The plan below is tailored to the current codebase (per README.md and INSTRUCTIONS.md): namespaced Joomla MVC, JSON APIs, Vue islands, existing checkout + webhooks, admin Orders workspace, and the documented state machine (`cart → pending → paid → fulfilled → refunded | canceled`).

### What we already have (context)
- Orders domain, JSON Orders API, admin Orders workspace, audit logging, and payment webhooks (Stripe/PayPal) that transition orders and send confirmation emails.
- Server-rendered storefront with Vue “islands,” CSRF handling for JSON endpoints, rate limiting service, and a clean routing scheme.
- Suggested frontend route pattern includes `view=order&no=…` in INSTRUCTIONS.md, but no explicit public tracking flow is described in README.md.

### What we’ll add (scope)
1) Guest-friendly, tokenized Order Status page on the storefront
2) Authenticated “My Orders” list + detail for logged-in users
3) Tokenized public lookup endpoint and rate limiting
4) Email templates updated with deep links to status
5) Admin UX: quick status link + tracking fields (optional MVP)
6) Minimal schema additions to support secure public access and timeline display

We’ll keep v1 read-only (no address edits/cancel flows) and ship fast; advanced self-service can follow.

---

### Phase 1 — Data model and security
- Add columns to `#__nxp_easycart_orders` (safe, additive migration):
  - `public_token CHAR(64)` UNIQUE NOT NULL — random, non-guessable token for guest status URLs.
  - `status_updated_at DATETIME` — last lifecycle transition timestamp.
  - `fulfillment_events JSON NULL` — array of `{type, message, meta, at}` for timeline (e.g., “Shipped”, tracking posted, delivery scan). Optional but valuable for UX.
  - `carrier VARCHAR(50) NULL`, `tracking_number VARCHAR(64) NULL`, `tracking_url VARCHAR(255) NULL` — optional shipping info.
- Backfill task (one-time): generate `public_token` for existing orders and set `status_updated_at = created` when null.
- Indexes: `UNIQUE(public_token)`, keep existing `order_no` unique.
- Security policy:
  - Public page requires either ownership (logged-in and `order.user_id == user.id`) or possession of `public_token`.
  - Public pages mask PII (show masked email, last 2 of postal code, etc.).
  - Rate-limit public lookups by IP and token using existing `RateLimiter` service.

Deliverables:
- SQL migration scripts (install/update) updating `#__nxp_easycart_orders`.
- `OrderService` updates to set/maintain `status_updated_at` and append to `fulfillment_events` on transitions.

Acceptance:
- New orders are created with a 64-char token.
- Existing orders get tokens on update script run.

---

### Phase 2 — Storefront routes and controllers
- Views/Routes:
  - Public order status: `index.php?option=com_nxpeasycart&view=order&ref=<public_token>`.
  - Authenticated detail: `index.php?option=com_nxpeasycart&view=order&no=<order_no>` (requires ownership).
  - Authenticated list: `index.php?option=com_nxpeasycart&view=orders` (My Orders).
- Site controllers/models:
  - `OrderController::show()` resolves order by `public_token` (guest) or by `order_no` + `user_id` (auth).
  - `OrdersController::index()` returns paginated user orders.
- Templating:
  - Server-render basic, accessible HTML with progressive enhancement via a small Vue island that polls (optional) or listens to SSE/webhooks later.
  - Display: Order number, date, statuses (with clear definitions), line items, totals (single-currency), shipping address (masked on tokenized views), fulfillment events timeline, and tracking (link out to carrier when present).

Security:
- Never accept `order_id` in query params; resolve by `public_token` or `order_no`+ownership.
- Ensure `_JEXEC`, ACL checks, CSRF for any state-changing endpoints (none in v1), output escaping.

Acceptance:
- Visiting `…&view=order&ref=…` shows an order without login, with masked PII.
- Visiting `…&view=orders` when logged-in shows only my orders.

---

### Phase 3 — JSON endpoints (read-only)
- Add read-only endpoints consumed by islands or external clients:
  - `GET /api/orders/{order_no}` — owner-only.
  - `GET /api/order-tracking/{public_token}` — token-only, returns masked payload.
  - `GET /api/my/orders?limit=&page=` — owner-only list.
- Response contract:
  - `order_no`, `state`, `status_updated_at`, `created`, monetary totals (`subtotal_cents`, `tax_cents`, `shipping_cents`, `discount_cents`, `total_cents`, `currency`), `items` (title, qty, unit_price_cents), masked `billing/shipping`, `fulfillment_events`, `tracking`.
- Technical consistency:
  - Use existing JSON responder and CSRF pattern for owner endpoints; tokenized endpoint should reject CSRF headers and rely solely on token (GET only), plus rate limiting.

Acceptance:
- Endpoints return correct shapes and adhere to masking on tokenized output.

---

### Phase 4 — Email and checkout integration
- Order confirmation email templates (`administrator/components/com_nxpeasycart/templates/email/*`):
  - Add a “Track your order” CTA that links to `&view=order&ref=<public_token>`.
  - Include friendly copy explaining statuses: `Pending` (payment started), `Paid` (confirmed), `Fulfilled` (shipped/delivered), `Canceled/Refunded`.
- Checkout confirmation view:
  - After successful payment, redirect to a confirmation page that also shows the “Track your order” link and QR code (optional) embedding the token URL.

Acceptance:
- Newly placed orders receive emails with a valid tokenized link.

---

### Phase 5 — Admin UX augmentations (quick-win)
- Admin Orders list/detail:
  - Add “Copy status link” action (shows tokenized URL) — permission-gated.
  - Inputs for `carrier`, `tracking_number`, `tracking_url` in Order detail; on save, append a `fulfillment_event` like `{type: 'shipped', at: now, meta: {carrier, tracking_number}}` and update `state` to `fulfilled` when appropriate.
  - Show the timeline from `fulfillment_events`.
- Audit log integration (already present): log tracking updates and state transitions.

Acceptance:
- Admin can paste a token link to a customer; setting tracking adds a visible event on both admin and public status pages.

---

### Phase 6 — Frontend UX polish (nice-to-have post-MVP)
- Vue island for order status page that:
  - Polls the tokenized endpoint every 20–30s for `pending/paid` states; stops when `fulfilled/canceled/refunded`.
  - Formats money using the same locale helpers used in cart/checkout islands.
  - Renders a friendly progress bar: `Processing → Paid → Shipped → Delivered`.
- Accessibility: ensure headings, focus states, and readable timeline. Mobile-first layout.

Acceptance:
- No layout shift; content is usable without JS; island enhances with progress and polling.

---

### Security, privacy, and rate limiting
- Tokenized access
  - 64-char random token (e.g., `bin2hex(random_bytes(32))`), unique index, never exposed alongside PII in query params beyond the token itself.
- PII minimization on tokenized responses
  - Mask email `j***@example.com`; mask address lines except city/region/country; show postal code as `**23`.
- Rate limiting
  - Public token endpoint: e.g., 60 requests/10 minutes per IP + sliding window by token.
  - Owner endpoints: standard auth checks + CSRF where applicable.
- Headers
  - Keep CSP and secure cookies; no new mutations in v1, so CSRF is mostly informational.

---

### Instrumentation and support readiness
- Log view events for tokenized lookups (order_no, token hash only, IP truncated) to detect enumeration.
- Add a small FAQ section/link on the status page: delivery estimates, contact channel.
- Admin setting: support contact email/URL surfaced on public status page.

---

### Testing
- PHPUnit: service tests for token generation, masking, and state/timeline updates.
- API contract tests: ensure JSON shapes, masking rules, and auth/ownership checks.
- Playwright E2E:
  - Place order → open email preview (dev) → follow tokenized link → see `Pending/Paid`.
  - Admin sets tracking → public status shows timeline update.
  - Auth user views My Orders and a specific order detail.
- Security tests: brute-force simulation on token endpoint to validate rate limiting and logging.

---

### Packaging and environment alignment
- Follow README/INSTRUCTIONS policy:
  - Keep `joomla/joomla-cms` in `require-dev`; rebuild trimmed runtime vendor for Joomla paths (`php tools/build-runtime-vendor.php`).
  - Register any new assets in `media/com_nxpeasycart/joomla.asset.json`; if an island is added, integrate with existing asset sync tooling.
  - Update admin and site language files accordingly (admin vs site split).

---

### Timeline (pragmatic)
- Day 1–2: DB migration + `OrderService` updates + public controller/view (server-rendered), masking + tests.
- Day 3: JSON endpoints (read-only) + rate limiting + tests.
- Day 4: Email template deep link + checkout confirmation link + admin “Copy link” action.
- Day 5: Admin tracking fields → fulfillment timeline + tests; polish copy and statuses; basic docs.
- Optional Day 6–7: Vue island (polling, progress bar), additional a11y/UX touches.

---

### Definition of Done (MVP)
- A buyer can open a tokenized link from the confirmation email and see order status safely without logging in.
- Logged-in buyers can see their order history and details.
- Admin can add tracking, which surfaces on the public status page.
- All endpoints adhere to masking, rate limiting, and security guidelines from INSTRUCTIONS.md.
- Tests cover the happy path and basic security guards; docs updated in README/docs.

### Future extensions (post-MVP)
- Let guest buyers “claim” an order to an account via email verification.
- Webhook-driven fulfillment event ingestion (carrier APIs) and delivery confirmation.
- Buyer-initiated cancel/return request flow (with CSRF/auth and ACL).
- Notifications: SMS or email on status transitions (`paid`, `fulfilled`).
