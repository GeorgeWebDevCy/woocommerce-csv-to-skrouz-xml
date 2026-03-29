from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from skroutz_feed_builder.ellia_updates import GTIN_COLUMN, SKU_COLUMN, apply_ellia_catalog_updates, build_ellia_update_files, load_export_rows


REPO_ROOT = Path(__file__).resolve().parents[1]
LATEST_EXPORT = REPO_ROOT / "wc-product-export-29-3-2026-1774786776454.csv"


class ElliaUpdatesTests(unittest.TestCase):
    def test_curated_updates_fill_expected_rows(self) -> None:
        rows = load_export_rows(LATEST_EXPORT)
        enriched_rows, report_rows, summary = apply_ellia_catalog_updates(rows)
        by_id = {row["ID"]: row for row in enriched_rows}
        report_by_id = {row["ID"]: row for row in report_rows}

        self.assertEqual(summary["matched"], 37)
        self.assertEqual(summary["skipped"], 2)
        self.assertEqual(summary["review"], 2)

        self.assertEqual(by_id["2725"][GTIN_COLUMN], "5292586000603")
        self.assertEqual(by_id["2725"][SKU_COLUMN], "ELLIA-CLEANSING-MILK-250ML")
        self.assertEqual(by_id["3031"][GTIN_COLUMN], "5292586000337")
        self.assertEqual(by_id["3034"][GTIN_COLUMN], "5292586000313")
        self.assertEqual(by_id["3638"][SKU_COLUMN], "ELLIA-OLIVE-OIL-SOAPS-120GR")
        self.assertEqual(by_id["3644"][GTIN_COLUMN], "5292586000016")
        self.assertEqual(by_id["3002"][GTIN_COLUMN], "")
        self.assertEqual(by_id["3171"][GTIN_COLUMN], "")
        self.assertEqual(report_by_id["3002"]["Status"], "skipped")
        self.assertEqual(report_by_id["4899"]["Status"], "review")

        skus = [row[SKU_COLUMN] for row in enriched_rows if row[SKU_COLUMN]]
        self.assertEqual(len(skus), len(set(skus)))
        self.assertTrue(all(len(sku) <= 80 for sku in skus))

    def test_build_update_files_writes_outputs(self) -> None:
        with tempfile.TemporaryDirectory() as tmp_dir:
            output_paths, summary = build_ellia_update_files(LATEST_EXPORT, tmp_dir)
            self.assertEqual(summary["matched"], 37)
            self.assertTrue(output_paths["import_ready"].exists())
            self.assertTrue(output_paths["sku_gtin_only"].exists())
            self.assertTrue(output_paths["match_report"].exists())


if __name__ == "__main__":
    unittest.main()
