from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from skroutz_feed_builder.core import (
    FeedSettings,
    ProductOverride,
    SourceProduct,
    auto_detect_link_template,
    export_xml,
    load_woocommerce_csv,
    resolve_products,
)


REPO_ROOT = Path(__file__).resolve().parents[1]
SAMPLE_CSV = REPO_ROOT / "wc-product-export-23-3-2026-1774263916232.csv"


class CoreTests(unittest.TestCase):
    def test_load_csv(self) -> None:
        products = load_woocommerce_csv(SAMPLE_CSV)
        self.assertEqual(len(products), 41)
        self.assertEqual(products[0].product_id, "2725")

    def test_detect_link_template(self) -> None:
        products = load_woocommerce_csv(SAMPLE_CSV)
        self.assertEqual(auto_detect_link_template(products), "https://www.ellianaturalcosmetics.com/product/{slug}/")

    def test_missing_ean_is_flagged(self) -> None:
        products = load_woocommerce_csv(SAMPLE_CSV)
        resolved = resolve_products(
            products,
            FeedSettings(
                link_template="https://www.ellianaturalcosmetics.com/product/{slug}/",
                default_manufacturer="Ellia Natural Cosmetics",
            ),
        )
        sample = next(product for product in resolved if product.source_id == "2725")
        fields = {issue.field for issue in sample.issues if issue.severity == "error"}
        self.assertIn("ean", fields)
        self.assertTrue(sample.link.startswith("https://www.ellianaturalcosmetics.com/product/"))

    def test_xml_export_for_ready_product(self) -> None:
        products = load_woocommerce_csv(SAMPLE_CSV)
        resolved = resolve_products(
            products,
            FeedSettings(
                link_template="https://www.ellianaturalcosmetics.com/product/{slug}/",
                default_manufacturer="Ellia Natural Cosmetics",
            ),
            overrides={"2725": ProductOverride(ean="1234567890123")},
        )
        ready = [product for product in resolved if product.source_id == "2725" and product.error_count == 0]
        self.assertEqual(len(ready), 1)
        with tempfile.TemporaryDirectory() as tmp_dir:
            target = Path(tmp_dir) / "feed.xml"
            export_xml(ready, target, "mywebstore")
            xml_text = target.read_text(encoding="utf-8")
            self.assertIn("<id>2725</id>", xml_text)
            self.assertIn("<ean>1234567890123</ean>", xml_text)

    def test_uses_13_digit_sku_as_ean_fallback(self) -> None:
        products = [
            SourceProduct(
                row_number=2,
                product_id="1",
                row_type="simple",
                parent_id="",
                published=True,
                visibility="visible",
                name="Sample Product",
                sku="1234567890123",
                gtin="",
                short_description="",
                description="Sample description",
                in_stock="1",
                stock="5",
                sale_price="",
                regular_price="9.99",
                categories="Beauty > Care",
                images="https://example.com/image.jpg",
                weight_kg="0.5",
                brand="Example Brand",
                attribute_name="",
                attribute_values="",
            )
        ]
        resolved = resolve_products(
            products,
            FeedSettings(link_template="https://example.com/products/{slug}/"),
        )
        self.assertEqual(resolved[0].ean, "1234567890123")
        error_fields = {issue.field for issue in resolved[0].issues if issue.severity == "error"}
        self.assertNotIn("ean", error_fields)


if __name__ == "__main__":
    unittest.main()
