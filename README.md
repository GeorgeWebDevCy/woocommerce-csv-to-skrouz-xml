# WooCommerce CSV to Skroutz XML

This repo now includes a Windows desktop app that loads a WooCommerce product CSV, helps you fill the fields Skroutz expects, validates the results, and exports a Skroutz XML feed.

Relevant Skroutz tools:

- XML spec: `https://developer.skroutz.gr/products/xml_feed/`
- Validator: `https://validator.skroutz.gr/`

## Run locally

```powershell
$env:PYTHONPATH = (Resolve-Path .\src)
python -m skroutz_feed_builder
```

## What the app does

- Loads your WooCommerce export.
- Suggests a product link template from your image domain when possible.
- Maps CSV fields into Skroutz feed fields.
- Lets you edit missing data per product.
- Saves project overrides to JSON so manual fixes are not lost.
- Exports only products without blocking validation errors.
- Gives you a direct button to open Skroutz's validator after export.
- Writes a rotating application log and shows a live activity log panel in the app.

## Build a Windows executable

```powershell
.\scripts\build.ps1 -Clean
```

The build script creates a virtual environment, installs `pyinstaller`, runs the test suite, and packages the GUI app into `dist\SkroutzFeedBuilder\`.

## Logging

The app writes logs to:

- `%LOCALAPPDATA%\SkroutzFeedBuilder\logs\skroutz-feed-builder.log` on Windows
- `~/.skroutz-feed-builder/logs/skroutz-feed-builder.log` as a fallback

You can also open the log directly from the app with the `Open Log` button.

## Important note about the sample CSV

The included WooCommerce export is missing several fields that Skroutz typically expects for a compliant feed, especially manufacturer/brand, MPN/SKU, and EAN/barcode values. Because of that, the app is intentionally an editor + validator, not just a blind CSV-to-XML converter.
