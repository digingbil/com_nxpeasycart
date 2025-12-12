# Digital Products & Downloads (v0.1.13)

Digital products let you sell files that are delivered via secure, tokenised download links. This covers schema changes, admin workflows, storefront behaviour, and the delivery guardrails.

## What changed

- **Schema:** products gain `product_type` (`physical`/`digital`); variants gain `is_digital`; orders/items track `has_digital`, `has_physical`, `is_digital`, `delivered_at`. New tables: `#__nxp_easycart_digital_files` (stored assets) and `#__nxp_easycart_downloads` (per-order tokens).
- **Settings:** defaults seeded for `digital_download_max`, `digital_download_expiry`, `digital_storage_path`, `digital_auto_fulfill`.
- **Services:** `DigitalFileService` handles uploads, token creation/validation, and streaming downloads from a protected storage path.
- **Order logic:** digital-only orders skip shipping and can auto-fulfill on payment. Order history includes download rows (counts/expiry/tokenised URLs).

## Admin workflow

- **Product editor:** choose **Physical** or **Digital**. Variants include an `is_digital` flag. A **Digital Files** tab lets you upload files (optionally scoped to a variant and version), list/delete attachments, and see size/created time.
- **Settings → Digital:** configure max downloads (0 = unlimited), link expiry days (0 = no expiry), storage path (default `/media/com_nxpeasycart/downloads` with `.htaccess` deny), and auto-fulfill behaviour for digital-only orders.
- **Orders:** detail view shows digital download links with counts/expiry and copy-to-clipboard.

## Storefront & emails

- **Checkout:** hides shipping when the cart has no physical items; digital badges appear on items; totals zero shipping for digital-only carts.
- **Order status page:** shows download buttons when the order is paid/fulfilled, with remaining count and expiry text.
- **Emails:** order confirmation includes a downloads table with filename, version, link, expiry/remaining info.

## Download delivery & security

- Files are stored under the protected storage path (`/media/com_nxpeasycart/downloads`) with `.htaccess` deny and hashed filenames. Files are only streamed via `DownloadController`.
- Download tokens enforce max download count and expiry, and require the parent order to be `paid` or `fulfilled`.
- Download URLs omit `/administrator` so they’re safe to embed in emails/public pages.

## API touchpoints

- Admin digital files API (`api.digitalfiles.list|upload|delete`) drives the product editor tab.
- Public download endpoint: `index.php?option=com_nxpeasycart&task=download.download&token=...` (token must be valid, unexpired, and under its max count).
