=== Skroutz XML Feed for WooCommerce ===
Contributors: orionaselite
Tags: skroutz, woocommerce, xml, product feed, marketplace
Requires at least: 6.5
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate a Skroutz-compatible WooCommerce XML feed with validation, per-product overrides, logging, caching, and a public feed URL.

== Description ==

Skroutz XML Feed for WooCommerce generates a Skroutz-ready product feed directly from your WooCommerce catalog.

The plugin is built for stores that need more than a one-click export. It validates the data that Skroutz expects, reports blocking issues, and lets you fix edge cases with product-level and variation-level overrides before the XML is consumed downstream.

= Features =

* Generates a public feed endpoint at `/skroutz-feed.xml`
* Builds a cached XML file inside your uploads directory
* Validates feed rows and highlights products that need attention
* Supports product and variation overrides for title, category, manufacturer, MPN, EAN, availability, size, color, description, and more
* Uses WooCommerce data, variation attributes, product tax settings, stock state, images, and categories where possible
* Provides a feed dashboard inside WooCommerce
* Writes a plugin log file for feed-generation diagnostics
* Supports GitHub-based updates through Plugin Update Checker

= Feed behavior =

The plugin exports products that are publishable and supported by the feed rules. Variable parent products are reported but exported through their child variations. Products with blocking validation errors are kept out of the final XML until their issues are fixed.

= Logs and cache files =

By default, the generated XML and log file are stored in:

* `wp-content/uploads/skroutz-xml-feed/skroutz-feed.xml`
* `wp-content/uploads/skroutz-xml-feed/logs/skroutz-feed.log`

== Installation ==

1. Upload the `skroutz-xml-feed-for-woocommerce` folder to the `/wp-content/plugins/` directory.
1. Activate the plugin through the `Plugins` screen in WordPress.
1. Make sure WooCommerce is installed and active.
1. Open `WooCommerce -> Skroutz XML Feed`.
1. Review the generated feed URL, settings, and validation report.
1. Click `Generate Feed Now` to build the first feed.
1. Submit the public feed URL to Skroutz or validate it first at `https://validator.skroutz.gr/`.

== Frequently Asked Questions ==

= What URL should I give to Skroutz? =

Use the public endpoint shown in the plugin dashboard, typically `https://your-store.example/skroutz-feed.xml`.

= Why is a product marked "Needs fixes"? =

That means the product is intended for export, but it has one or more blocking validation errors. It will not be written to the XML until those issues are resolved.

= Why is a product marked "Excluded"? =

That means the plugin is intentionally skipping that row, for example because the product is unpublished, manually excluded, hidden from the catalog, unsupported, or a variable parent that should be represented by variations instead.

= Can SKU be used as EAN? =

Only as a fallback when the SKU looks like a real 13-digit barcode. Otherwise the plugin keeps the product blocked until a valid EAN is available.

= How do I avoid stale feed data with cache plugins or a CDN? =

Always submit the public feed endpoint, usually `https://your-store.example/skroutz-feed.xml`, to Skroutz. The plugin invalidates its own XML cache when product content, stock, pricing, or plugin settings change, and the public endpoint sends no-cache headers for common cache layers.

If your site uses server-side page caching, a reverse proxy, or a CDN, exclude `/skroutz-feed.xml` from caching. The uploads-based cached XML file is intended for debugging and should not be used as the public feed URL.

= Does the plugin work with product variations? =

Yes. Variation rows are exported individually, and the plugin can infer color and size from WooCommerce variation attributes.

== Screenshots ==

1. The main WooCommerce feed dashboard with the public feed URL, cache status, and validation summary.
2. The validation report highlighting products that need fixes before they can be exported.
3. Product-level Skroutz override fields inside the WooCommerce product editor.

== Changelog ==

= 1.0.6 =
* Added an explicit “Apply Default Brand” action in the plugin dashboard to force brand-term creation and assignment even when the setting value itself has not changed.
* Manual brand sync now scans products across all statuses and regenerates the feed immediately after the sync finishes.

= 1.0.5 =
* Creates the default manufacturer as a real WooCommerce brand term in a supported brand taxonomy and assigns it to products that are missing a brand.
* Keeps the Skroutz manufacturer sync aligned with those brand assignments so feed data and WooCommerce taxonomy data stay in step.

= 1.0.4 =
* Maintenance release to support updater verification from GitHub releases.

= 1.0.3 =
* Expanded the reset action so the saved “Products Needing Attention” report clears together with the plugin log.
* Updated the admin button label to clarify that both the log and report are reset.

= 1.0.2 =
* Added a Clear Log button to the feed dashboard for resetting the plugin log file without leaving WordPress.
* Improved manufacturer syncing so detected WooCommerce manufacturer or brand data is persisted when available, and the default manufacturer fills the remaining gaps.
* Regenerates the feed immediately after settings changes so the validation report reflects the latest manufacturer updates.

= 1.0.1 =
* Added database backfill for the default manufacturer setting on products that do not already have a manufacturer source.
* Added auto-update of previously backfilled manufacturer values when the default manufacturer changes.
* Added stronger feed-cache invalidation and response headers to reduce stale XML responses.

= 1.0.0 =
* Initial public release.
* Added a public Skroutz XML endpoint and cached XML generation.
* Added WooCommerce admin tools, validation reporting, logging, and per-product overrides.
* Added GitHub-based update support using Plugin Update Checker.

== Upgrade Notice ==

= 1.0.6 =
Adds a dedicated button to force default-brand creation and assignment for products, then regenerates the feed immediately.

= 1.0.5 =
Adds default-manufacturer backfill into supported WooCommerce brand taxonomies so missing brands are created and assigned automatically.

= 1.0.4 =
Maintenance updater-test release.

= 1.0.3 =
Clears the saved “Products Needing Attention” report together with the log, so the diagnostics reset is complete.

= 1.0.2 =
Adds a Clear Log button and improves manufacturer syncing so feed validation updates immediately after settings changes.

= 1.0.1 =
Updates the feed cache hardening and can backfill the default manufacturer into product meta for products that are still missing manufacturer data.

= 1.0.0 =
Initial release of Skroutz XML Feed for WooCommerce.
