# Order Status & Tracking (MVP)

## Overview
- Public tokenised status page (`view=order&ref=<token>`) with masked PII and rate limiting.
- Authenticated “My Orders” list/detail (`view=orders`, `view=order&no=`) restricted to the owning user.
- Admin orders panel now exposes a copyable status link plus tracking fields (carrier, tracking number, tracking URL) that append fulfilment events and optionally mark orders fulfilled.
- Confirmation emails include a “Track your order” CTA pointing to the tokenised status page.

## Schema additions
- `#__nxp_easycart_orders` now includes:
  - `public_token` (CHAR(64) UNIQUE NOT NULL)
  - `status_updated_at` (DATETIME NOT NULL)
  - `fulfillment_events` (JSON)
  - `carrier`, `tracking_number`, `tracking_url`
- Update script `sql/updates/mysql/0.1.3.sql` backfills tokens + timestamps and enforces the new index.

## Storefront surface
- `view=order&ref=<public_token>`: guest-friendly status page with masked email/address and fulfilment timeline.
- `view=order&no=<order_no>`: owner-only detail (requires logged-in user match).
- `view=orders`: authenticated “My Orders” list.
- Token lookups are rate-limited (IP + token) and audit/transaction data is hidden on public views.

## JSON endpoints (site)
- `task=status.tracking&ref=<token>` → masked order payload for public tokens (rate-limited).
- `task=status.show&no=<order_no>` → owner-only order detail (401 on guests/mismatch).
- `task=status.mine&limit=&start=` → owner-only list of orders.

## Admin API
- New endpoint `task=api.orders.tracking` accepts `id`, `carrier`, `tracking_number`, `tracking_url`, and `mark_fulfilled`:
  - Updates tracking fields
  - Appends a fulfilment event (`type: tracking`)
  - Optionally transitions to `fulfilled`
  - Records audit action `order.tracking.updated`

## Admin UI (Vue)
- Orders detail modal shows a copyable status link built from the public token.
- Tracking form saves via the new admin endpoint; fulfilment events + audit entries appear in the timeline.
- Timeline now merges fulfilment events with audit history and sorts by timestamp.

## Email
- Order confirmation template adds a “Track your order” CTA linking to the tokenised status page (absolute URL).

## Security & privacy
- Public views mask email, addresses, and phone; transactions/audit history are hidden.
- Token endpoints use rate limiting; owner endpoints enforce login and state ownership.
- GDPR anonymisation now clears tracking fields and fulfilment events.
