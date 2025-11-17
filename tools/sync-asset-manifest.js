#!/usr/bin/env node

/**
 * Sync Joomla web asset definition with the latest Vite site build.
 *
 * Reads `.vite/manifest.json` for the site entry and rewrites
 * `media/com_nxpeasycart/joomla.asset.json` to point to the newest hashed bundle.
 */

import fs from "node:fs";
import path from "node:path";

const manifestPath = path.resolve(
    "media/com_nxpeasycart/.vite/manifest.json"
);
const assetPath = path.resolve("media/com_nxpeasycart/joomla.asset.json");
const siteEntry = "media/com_nxpeasycart/src/site-main.js";

const readJson = (file) => JSON.parse(fs.readFileSync(file, "utf8"));

const manifest = readJson(manifestPath);
const assetJson = readJson(assetPath);

const entry = manifest[siteEntry];

if (!entry || !entry.file) {
    throw new Error(
        `Cannot find Vite entry "${siteEntry}" in manifest at ${manifestPath}`
    );
}

const newUri = `com_nxpeasycart/${entry.file}`;

// Remove old hashed site.*.js files before updating the asset manifest
try {
    const jsDir = path.resolve("media/com_nxpeasycart/js");
    const currentJsFile = path.basename(entry.file); // e.g., "site.ABC123.js"
    const files = fs.readdirSync(jsDir);
    files.forEach((f) => {
        if (f !== currentJsFile && /^site\..+\.js$/.test(f)) {
            fs.unlinkSync(path.join(jsDir, f));
            console.log(`[sync-asset-manifest] Removed old bundle: ${f}`);
        }
    });
} catch (err) {
    if (err && err.code !== "ENOENT") {
        console.warn(
            `[sync-asset-manifest] Warning while cleaning old site bundles: ${err.message}`
        );
    }
}

let updated = false;

if (Array.isArray(assetJson.assets)) {
    assetJson.assets = assetJson.assets.map((asset) => {
        if (asset.name === "com_nxpeasycart.site") {
            if (asset.uri !== newUri) {
                asset.uri = newUri;
                updated = true;
            }
        }

        return asset;
    });
}

if (!updated) {
    console.log(
        `[sync-asset-manifest] No changes needed; site asset already points to ${newUri}`
    );
} else {
    fs.writeFileSync(assetPath, JSON.stringify(assetJson, null, 4));
    console.log(
        `[sync-asset-manifest] Updated com_nxpeasycart.site asset to ${newUri}`
    );
}
