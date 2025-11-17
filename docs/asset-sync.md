# Asset sync (Vite → Joomla Web Assets)

When the site bundle is rebuilt, Joomla must be told about the new hashed filename so it can enqueue the correct script.

- Build the site bundle: `PATH="$PWD/node_local/bin:$PATH" npm run build:site`
- The postbuild hook runs `npm run sync:assets`, which:
  - Reads `media/com_nxpeasycart/.vite/manifest.json`
  - Updates `media/com_nxpeasycart/joomla.asset.json` to point `com_nxpeasycart.site` at the latest JS filename
- Manual sync (if you skip the hook): `npm run sync:assets`

Helper script: `tools/sync-asset-manifest.js`
