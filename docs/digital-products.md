# Digital Products & Downloads (v0.1.13)

Digital products let you sell files that are delivered via secure, tokenised download links. This covers schema changes, admin workflows, storefront behaviour, and the delivery guardrails.

## What changed

- **Schema:** products gain `product_type` (`physical`/`digital`); variants gain `is_digital`; orders/items track `has_digital`, `has_physical`, `is_digital`, `delivered_at`. New tables: `#__nxp_easycart_digital_files` (stored assets) and `#__nxp_easycart_downloads` (per-order tokens).
- **Settings:** defaults seeded for `digital_download_max`, `digital_download_expiry`, `digital_storage_path`, `digital_auto_fulfill`, `digital_max_file_size`, `digital_allowed_extensions`, `digital_custom_extensions`.
- **Services:** `DigitalFileService` handles uploads, token creation/validation, and streaming downloads from a protected storage path.
- **Order logic:** digital-only orders skip shipping and can auto-fulfill on payment. Order history includes download rows (counts/expiry/tokenised URLs).

## Admin workflow

- **Product editor:** choose **Physical** or **Digital**. The **Digital Files** tab only appears when Product Type is set to "Digital". Variants include an `is_digital` flag. The tab lets you upload files (optionally scoped to a variant and version), list/delete attachments, and see size/created time. A callout prompts you to save the product before attaching files.
- **Settings → Digital Products:** configure max downloads (0 = unlimited), link expiry days (0 = no expiry), storage path (default `/media/com_nxpeasycart/downloads` with server protection), auto-fulfill behaviour for digital-only orders, max file size (default 200MB), allowed file types by category, and custom extensions for exotic file types.
- **Orders:** detail view shows digital download links with counts/expiry and copy-to-clipboard.

## Storefront & emails

- **Checkout:** hides shipping when the cart has no physical items; digital badges appear on items; totals zero shipping for digital-only carts.
- **Order status page:** shows download buttons when the order is paid/fulfilled, with remaining count and expiry text.
- **Emails:** order confirmation includes a downloads table with filename, version, link, expiry/remaining info.

## File upload validation (v0.1.14)

Uploaded digital files are validated for security before being stored:

### Allowed file types

47 predefined file types are supported, grouped by category:

| Category    | Extensions                                              |
| ----------- | ------------------------------------------------------- |
| Archives    | zip, rar, 7z, tar, gz, tgz                              |
| Audio       | mp3, wav, flac                                          |
| Video       | mp4, webm, mov, avi, mkv                                |
| Images      | jpg, jpeg, png, gif, svg, webp, avif                    |
| Documents   | pdf, txt, rtf, doc, docx, xls, xlsx, ppt, pptx, odt, ods, odp, csv |
| Ebooks      | epub, mobi                                              |
| Installers  | exe, msi, deb, rpm, dmg, app, pkg, apk, ipa             |

### Configurable settings

In **Settings → Digital Products**:

- **Allowed file types**: Category-grouped checkboxes with Select All/Select None buttons. Installers are unchecked by default for security (executables pose higher risk).
- **Custom extensions**: Text field for exotic file types not in the predefined list (comma-separated, e.g., `blend,psd,ai`).
- **Max file size**: Configurable limit in MB (default 200MB).

### Validation process

1. **Extension check**: File extension must be in the enabled predefined list OR the custom extensions list.
2. **MIME type check**: File's MIME type must match the expected MIME for its extension (prevents extension spoofing).
3. **Size check**: File size must be under the configured maximum.

### Server protection

The storage directory is protected with both Apache and Nginx configuration files:

- **`.htaccess`**: `Deny from all` rule for Apache servers.
- **`nginx.conf`**: `deny all;` directive for Nginx servers (must be included in server block manually).

## Download delivery & security

- Files are stored under the protected storage path (`/media/com_nxpeasycart/downloads`) with `.htaccess`/`nginx.conf` protection and hashed filenames. Files are only streamed via `DownloadController`.
- Download tokens enforce max download count and expiry, and require the parent order to be `paid` or `fulfilled`.
- Download URLs omit `/administrator` so they're safe to embed in emails/public pages.

## API touchpoints

- Admin digital files API (`api.digitalfiles.list|upload|delete`) drives the product editor tab.
- Public download endpoint: `index.php?option=com_nxpeasycart&task=download.download&token=...` (token must be valid, unexpired, and under its max count).
