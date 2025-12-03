# Packaging & Release

## Quick Start

Run the release script from the repository root:

```bash
./make-release.sh
```

### Options

| Flag           | Description                                   |
| -------------- | --------------------------------------------- |
| `--dry-run`    | Build package without git operations          |
| `--skip-build` | Skip npm/composer build (use existing assets) |
| `--deploy`     | Upload to update server (when configured)     |

### Interactive Prompts

1. **Component version**: Choose major/minor/patch increment or keep current
2. **Module version**: Sync with component, keep current, or set custom
3. **Plugin version**: Sync with component, keep current, or set custom
4. **Git operations**: Confirm commit and tag creation
5. **Push**: Confirm push to remote

## What the Script Does

1. **Pre-flight checks**
    - Verifies on `main` branch
    - Checks for uncommitted changes
    - Validates required tools (`jq`, `zip`, `git`, etc.)

2. **Version management**
    - Updates version in `nxpeasycart.xml` (component)
    - Updates version in `mod_nxpeasycart_cart.xml` (module)
    - Updates version in `nxpeasycartcleanup.xml` (plugin)
    - Updates version in `composer.json`

3. **Build production assets**
    - `npm ci && npm run build:admin && npm run build:site`
    - `php tools/build-runtime-vendor.php` (creates trimmed vendor directory)

4. **Create package**
    - Copies only release files to staging directory
    - Excludes dev files, tests, docs, source maps, etc.
    - Creates `com_nxpeasycart_vX.Y.Z.zip`

5. **Generate checksums**
    - SHA256 and SHA384 for update server

6. **Create updates.xml**
    - Ready for Joomla update server

7. **Git operations** (unless `--dry-run`)
    - Commits version changes
    - Creates annotated tag `vX.Y.Z`
    - Optionally pushes to origin

## Package Contents

The generated ZIP is a Joomla package containing:

```
pkg_nxpeasycart_vX.Y.Z.zip
├── pkg_nxpeasycart.xml          # Package manifest
└── packages/
    ├── com_nxpeasycart.zip      # Component
    ├── mod_nxpeasycart_cart.zip # Cart summary module
    └── plg_task_nxpeasycartcleanup.zip # Cleanup scheduler plugin
```

### Component Contents (com_nxpeasycart.zip)
```
├── nxpeasycart.xml              # Component manifest
├── script.php
├── sql/
├── admin/
│   ├── forms/
│   ├── language/
│   ├── services/
│   ├── sql/
│   ├── src/
│   ├── templates/
│   ├── tmpl/
│   └── vendor/                  # Runtime dependencies only
├── site/
│   ├── src/
│   └── tmpl/
├── media/
│   ├── css/                     # Built CSS
│   ├── js/                      # Built JS (admin.iife.js, site.*.js)
│   └── joomla.asset.json
└── language/
    └── en-GB/
```

### Module Contents (mod_nxpeasycart_cart.zip)
```
├── mod_nxpeasycart_cart.xml
├── mod_nxpeasycart_cart.php
├── language/
└── tmpl/
```

### Plugin Contents (plg_task_nxpeasycartcleanup.zip)
```
├── nxpeasycartcleanup.xml
├── services/
├── src/
└── language/
```

## Excluded from Package

- Development files: `composer.json`, `package.json`, `phpunit.xml.dist`, etc.
- Source files: `media/com_nxpeasycart/src/` (Vue sources)
- Build tools: `build/`, `tools/`, `node_modules/`, `node_local/`
- Documentation: `docs/`, `*.md` (except LICENSE)
- Tests: `tests/`
- Root vendor: `vendor/` (dev dependencies)
- IDE/editor files: `.vscode/`, `.idea/`, `.claude/`

## Configuration

Edit `release-config.json` to customize:

```json
{
    "package_name": "com_nxpeasycart",
    "github_repo": "nexusplugins/com_nxpeasycart",
    "update_server": {
        "enabled": false,
        "remote_server": "your-server.com",
        "remote_user": "deploy",
        "remote_path": "/var/www/updates",
        "remote_domain": "updates.example.com"
    },
    "target_platforms": [
        { "name": "joomla", "version": "5.*" },
        { "name": "joomla", "version": "6.*" }
    ]
}
```

## Manual Steps (Alternative)

If you prefer not to use the script:

1. **Install dependencies**

    ```bash
    composer install --no-dev --optimize-autoloader
    npm ci && npm run build:admin && npm run build:site
    php tools/build-runtime-vendor.php
    ```

2. **Update versions** in manifest files

3. **Create ZIP** excluding dev artifacts:

    ```bash
    zip -r com_nxpeasycart.zip administrator components media modules language \
      -x "*.md" -x "media/com_nxpeasycart/src/*" -x "*.map"
    ```

4. **Install** on Joomla via Extensions → Install

## Smoke Testing

After installing on a staging site:

1. ✅ Admin panel loads without errors
2. ✅ All menu items accessible (Products, Orders, etc.)
3. ✅ Frontend shop pages render correctly
4. ✅ Cart functionality works
5. ✅ Checkout flow completes (sandbox payments)
6. ✅ Mini cart module displays correctly
7. ✅ Cleanup plugin appears in System → Scheduled Tasks
8. ✅ Cleanup task can be configured and enabled
