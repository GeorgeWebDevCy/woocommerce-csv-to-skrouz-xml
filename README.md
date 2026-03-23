# WooCommerce to Skroutz Feed Tools

This repository now contains two companion tools for producing Skroutz-compatible product feeds from WooCommerce data:

- a Windows desktop app for working from WooCommerce CSV exports
- a WordPress plugin for generating the XML feed directly from WooCommerce

Relevant Skroutz resources:

- XML spec: `https://developer.skroutz.gr/products/xml_feed/`
- Validator: `https://validator.skroutz.gr/`

## Repository layout

- `src/skroutz_feed_builder/` contains the desktop app source.
- `plugin/skroutz-xml-feed-for-woocommerce/` contains the WordPress plugin.
- `scripts/build.ps1` builds the Windows executable.
- `scripts/build-plugin.ps1` packages the WordPress plugin ZIP.
- `readme.txt` and `skroutz-xml-feed-for-woocommerce.php` at the repo root are release metadata files for the plugin updater.

## Windows desktop app

The desktop app loads a WooCommerce product CSV, helps fill missing Skroutz fields, validates each item, and exports only compliant products to XML.

Run locally:

```powershell
$env:PYTHONPATH = (Resolve-Path .\src)
python -m skroutz_feed_builder
```

Build the Windows executable:

```powershell
.\scripts\build.ps1 -Clean
```

The build script creates a virtual environment, installs `pyinstaller`, runs the test suite, and packages the GUI app into `dist\SkroutzFeedBuilder\`.

Desktop app log locations:

- `%LOCALAPPDATA%\SkroutzFeedBuilder\logs\skroutz-feed-builder.log` on Windows
- `~/.skroutz-feed-builder/logs/skroutz-feed-builder.log` as a fallback

## WordPress plugin

The WordPress plugin adds a WooCommerce admin screen, a public feed endpoint at `/skroutz-feed.xml`, per-product and per-variation override fields, caching, validation reports, and a file log inside the uploads directory.

Plugin source:

- `plugin/skroutz-xml-feed-for-woocommerce/`

Build the distributable ZIP:

```powershell
.\scripts\build-plugin.ps1
```

That script validates the plugin metadata, optionally runs `php -l` when PHP is available, and creates a versioned ZIP in `dist\`.

The plugin uses `yahnis-elsts/plugin-update-checker` and this GitHub repository as its update source. The packaged ZIP is intended to be attached to GitHub releases named with version tags such as `v1.0.0`.

For feed freshness, the public endpoint is the canonical URL to share with Skroutz. It sends no-cache headers and the plugin invalidates its XML cache when relevant WooCommerce product, stock, price, and settings events occur. If the site also uses a cache plugin or CDN, exclude `/skroutz-feed.xml` from those cache layers.

## Important note about the sample CSV

The included WooCommerce export is missing several fields that Skroutz typically expects for a compliant feed, especially manufacturer/brand, MPN/SKU, and EAN/barcode values. That is why both tools expose validation and manual overrides instead of doing a blind export.
