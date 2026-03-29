from __future__ import annotations

import argparse
import sys
from pathlib import Path


REPO_ROOT = Path(__file__).resolve().parents[1]
SRC_ROOT = REPO_ROOT / "src"
if str(SRC_ROOT) not in sys.path:
    sys.path.insert(0, str(SRC_ROOT))

from skroutz_feed_builder.ellia_updates import build_ellia_update_files


def find_latest_export(repo_root: Path) -> Path:
    exports = sorted(repo_root.glob("wc-product-export-*.csv"), key=lambda path: path.stat().st_mtime, reverse=True)
    if not exports:
        raise FileNotFoundError("No WooCommerce export CSV files were found in the repository root.")
    return exports[0]


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Apply curated ELLIA barcode and SKU matches to a WooCommerce product export."
    )
    parser.add_argument(
        "input_csv",
        nargs="?",
        default=None,
        help="WooCommerce product export CSV. Defaults to the newest wc-product-export-*.csv file in the repo root.",
    )
    parser.add_argument(
        "--output-dir",
        default=str(REPO_ROOT / "build"),
        help="Directory where the generated CSV files will be written.",
    )
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    input_csv = Path(args.input_csv) if args.input_csv else find_latest_export(REPO_ROOT)
    output_paths, summary = build_ellia_update_files(input_csv, args.output_dir)

    print(f"Input CSV: {input_csv}")
    print(f"Matched rows: {summary['matched']}")
    print(f"Skipped rows: {summary['skipped']}")
    print(f"Review rows: {summary['review']}")
    print(f"Import-ready CSV: {output_paths['import_ready']}")
    print(f"SKU-only CSV: {output_paths['sku_only']}")
    print(f"Match report CSV: {output_paths['match_report']}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
