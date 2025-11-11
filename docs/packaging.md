# Packaging & Release Checklist

1. **Install dependencies**
   - `composer install --no-dev --optimize-autoloader`
   - `npm install && npm run build:admin && npm run build:site`
   - `php tools/build-runtime-vendor.php` (ensures `administrator/components/com_nxpeasycart/vendor/` contains only runtime deps)

2. **Prune development artefacts**
   - Remove `node_modules/`, `tests/`, `.github/`, local config overrides

3. **Warm cache + assets**
   - `php cli/joomla.php extension:discover`
   - Verify cached shipping/tax datasets via CacheService (`cache:clear` prior to snapshot)

4. **Assemble package tree**
   - Copy `administrator/components/com_nxpeasycart`
   - Copy `components/com_nxpeasycart`
   - Copy `media/com_nxpeasycart`
   - Include `vendor/` (runtime dependencies only – generated via `tools/build-runtime-vendor.php`)

5. **Generate manifest zip**
   - `zip -r com_nxpeasycart.zip administrator components media vendor`

6. **Smoke install**
   - Install zip on staging Joomla 5 instance
   - Run GDPR export/anonymise smoke tests and checkout via Stripe/PayPal sandbox

7. **Tag & release**
   - Update `README.md` changelog
   - Tag semver release (`git tag vx.y.z && git push --tags`)
   - Publish package to update server indicated in `nxpeasycart.xml`
