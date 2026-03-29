from __future__ import annotations

import csv
from dataclasses import dataclass
from pathlib import Path


SKU_COLUMN = "SKU"
GTIN_COLUMN = "GTIN, UPC, EAN, or ISBN"


@dataclass(frozen=True)
class CatalogUpdate:
    product_id: str
    barcode: str
    sku: str
    source_name: str
    note: str = ""


ELLIA_CATALOG_UPDATES: dict[str, CatalogUpdate] = {
    "2725": CatalogUpdate("2725", "5292586000603", "5292586000603", "Cleansing milk 250ml"),
    "2979": CatalogUpdate(
        "2979",
        "5292586000047",
        "5292586000047",
        "Herbal water (soul soothing) 50 ml",
        "Matched from the soothing rose-lavender description and the 50ml size.",
    ),
    "2981": CatalogUpdate(
        "2981",
        "5292586000030",
        "5292586000030",
        "Herbal water (soul soothing) 100 ml",
        "Matched from the soothing rose-lavender description and the 100ml size.",
    ),
    "2983": CatalogUpdate("2983", "5292586000733", "5292586000733", "Face scrub-mask 100ml"),
    "2985": CatalogUpdate("2985", "5292586000764", "5292586000764", "24 H Youth revive 60ml"),
    "2987": CatalogUpdate(
        "2987",
        "5292586000757",
        "5292586000757",
        "24 H Antioxidant hydrator cream 60ml",
    ),
    "2989": CatalogUpdate("2989", "5292586000078", "5292586000078", "24h Protector 60ml"),
    "2991": CatalogUpdate("2991", "5292586000221", "5292586000221", "After Shave 60ml"),
    "2993": CatalogUpdate(
        "2993",
        "5292586000214",
        "5292586000214",
        "Shaving soap basil / aimond 120gr",
        "The ELLIA sheet lists two shaving soap variants, but they share the same barcode.",
    ),
    "2995": CatalogUpdate("2995", "5292586000740", "5292586000740", "Young Ever After balm 60ml"),
    "2997": CatalogUpdate(
        "2997",
        "5292586000252",
        "5292586000252",
        "Young ever after Oil Serum 50ml",
    ),
    "3000": CatalogUpdate("3000", "5292586000689", "5292586000689", "pure skin fluid 60ml"),
    "3006": CatalogUpdate("3006", "5292586000771", "5292586000771", "Moisture source balm 60ml"),
    "3008": CatalogUpdate(
        "3008",
        "5292586000788",
        "5292586000788",
        "Moisture source Oil Serum 50ml",
    ),
    "3010": CatalogUpdate("3010", "5292586000795", "5292586000795", "youth rescuer balm 60ml"),
    "3012": CatalogUpdate(
        "3012",
        "5292586000801",
        "5292586000801",
        "youth rescuer Oil Serum 50ml",
    ),
    "3022": CatalogUpdate(
        "3022",
        "5292586000290",
        "5292586000290",
        "Anti Hair Loss Shampoo 250ml",
    ),
    "3024": CatalogUpdate(
        "3024",
        "5292586000306",
        "5292586000306",
        "Hair smoothing & restructuring oil 50ml",
    ),
    "3026": CatalogUpdate(
        "3026",
        "5292586000283",
        "5292586000283",
        "Hair smoothing & restructuring oil 100ml",
    ),
    "3028": CatalogUpdate(
        "3028",
        "5292586000702",
        "5292586000702",
        "Helios cream 50 SPF 60ml",
        "The WooCommerce description explicitly says SPF 50.",
    ),
    "3031": CatalogUpdate(
        "3031",
        "5292586000337",
        "5292586000337",
        "Mild soothing Shower gel (up lifting) 250ml",
        "Matched from the uplifting grapefruit-geranium description.",
    ),
    "3034": CatalogUpdate(
        "3034",
        "5292586000313",
        "5292586000313",
        "Mild soothing Shower gel (soul soothing) 250ml",
        "Matched from the soul soothing lavender-myrrh description.",
    ),
    "3036": CatalogUpdate(
        "3036",
        "5292586000382",
        "5292586000382",
        "Body Lotion (up lifting) 250ml",
        "Matched from the uplifting grapefruit-geranium description.",
    ),
    "3038": CatalogUpdate(
        "3038",
        "5292586000375",
        "5292586000375",
        "Body Lotion (soul soothing) 250ml",
        "Matched from the soul soothing lavender-myrrh description.",
    ),
    "3044": CatalogUpdate("3044", "5292586000320", "5292586000320", "Body scrub 100ml"),
    "3049": CatalogUpdate(
        "3049",
        "5292586000344",
        "5292586000344-CM",
        "Body cream vanila-strawberry 100ml",
        "Matched from the vanilla-strawberry scent in the WooCommerce description.",
    ),
    "3053": CatalogUpdate(
        "3053",
        "5292586000344",
        "5292586000344-SN",
        "body cream vanila-sandawoot 100ml",
        "Matched from the vanilla-sandalwood-myrrh scent in the WooCommerce description.",
    ),
    "3055": CatalogUpdate(
        "3055",
        "5292586000344",
        "5292586000344-TD",
        "Body cream orange-jasmin 100ml",
        "Matched from the orange-jasmine scent in the WooCommerce description.",
    ),
    "3057": CatalogUpdate("3057", "5292586000467", "5292586000467", "Hand cream 75ml"),
    "3164": CatalogUpdate("3164", "5292586000566", "5292586000566", "SOS Balm 60ml"),
    "3169": CatalogUpdate(
        "3169",
        "5292586000245",
        "5292586000245",
        "24 H Ultimate recovery 60ml",
    ),
    "3638": CatalogUpdate("3638", "5292586000023", "5292586000023", "Olive oil soaps 120gr"),
    "3644": CatalogUpdate("3644", "5292586000016", "5292586000016", "Olive oil soaps 60gr"),
    "4905": CatalogUpdate(
        "4905",
        "5292586000849",
        "5292586000849",
        "Gentle Cleansing Gel (NEW) 250ml",
    ),
    "4909": CatalogUpdate(
        "4909",
        "5292586000061",
        "5292586000061",
        "24 H Ultimate Lift cream 60ml",
    ),
    "4919": CatalogUpdate(
        "4919",
        "5292586000054",
        "5292586000054",
        "Deep Cleansing scrub mask NEW 100ML",
    ),
    "5026": CatalogUpdate("5026", "5292586000962", "5292586000962", "DEODORANT ROLL 50 ml"),
}


SKIPPED_PRODUCTS: dict[str, str] = {
    "3002": "Variable parent left blank on purpose. Its child variation row 3638 receives the SKU and barcode.",
    "3004": "Variable parent left blank on purpose. Its child variation row 3644 receives the SKU and barcode.",
}


REVIEW_PRODUCTS: dict[str, str] = {
    "3171": "No confident match was found in the 2025 ELLIA price list for this renamed cleansing gel.",
    "4899": "No confident match was found in the 2025 ELLIA price list and the product is unpublished in WooCommerce.",
}


def clean_text(value: object) -> str:
    return str(value or "").strip()


def load_export_rows(csv_path: str | Path) -> list[dict[str, str]]:
    source = Path(csv_path)
    with source.open("r", encoding="utf-8-sig", newline="") as handle:
        return list(csv.DictReader(handle))


def write_csv(csv_path: str | Path, rows: list[dict[str, str]], fieldnames: list[str]) -> Path:
    destination = Path(csv_path)
    destination.parent.mkdir(parents=True, exist_ok=True)
    with destination.open("w", encoding="utf-8-sig", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=fieldnames)
        writer.writeheader()
        writer.writerows(rows)
    return destination


def validate_unique_skus(rows: list[dict[str, str]]) -> None:
    seen: dict[str, str] = {}
    for row in rows:
        product_id = clean_text(row.get("ID"))
        sku = clean_text(row.get(SKU_COLUMN))
        if not sku:
            continue
        if len(sku) > 80:
            raise ValueError(f"SKU exceeds 80 characters for product {product_id}: {sku}")
        if sku in seen:
            raise ValueError(f"Duplicate SKU detected for products {seen[sku]} and {product_id}: {sku}")
        seen[sku] = product_id


def apply_ellia_catalog_updates(rows: list[dict[str, str]]) -> tuple[list[dict[str, str]], list[dict[str, str]], dict[str, int]]:
    enriched_rows: list[dict[str, str]] = []
    report_rows: list[dict[str, str]] = []
    summary = {
        "total": len(rows),
        "matched": 0,
        "skipped": 0,
        "review": 0,
        "preexisting": 0,
    }

    for row in rows:
        updated_row = dict(row)
        product_id = clean_text(row.get("ID"))
        existing_sku = clean_text(row.get(SKU_COLUMN))
        existing_gtin = clean_text(row.get(GTIN_COLUMN))
        status = "review"
        source_name = ""
        note = REVIEW_PRODUCTS.get(product_id, "No curated barcode match is configured for this product.")

        if product_id in SKIPPED_PRODUCTS:
            status = "skipped"
            note = SKIPPED_PRODUCTS[product_id]
            summary["skipped"] += 1
        else:
            update = ELLIA_CATALOG_UPDATES.get(product_id)
            if update is not None:
                if existing_gtin and existing_gtin != update.barcode:
                    note = f"Existing GTIN differs from the curated ELLIA match ({update.barcode})."
                    summary["review"] += 1
                elif existing_sku and existing_sku != update.sku:
                    note = f"Existing SKU differs from the curated ELLIA match ({update.sku})."
                    summary["review"] += 1
                else:
                    updated_row[GTIN_COLUMN] = existing_gtin or update.barcode
                    updated_row[SKU_COLUMN] = existing_sku or update.sku
                    source_name = update.source_name
                    note = update.note
                    status = "matched"
                    if existing_gtin or existing_sku:
                        summary["preexisting"] += 1
                    summary["matched"] += 1
            else:
                summary["review"] += 1

        enriched_rows.append(updated_row)
        report_rows.append(
            {
                "ID": product_id,
                "Type": clean_text(row.get("Type")),
                "Parent": clean_text(row.get("Parent")),
                "Published": clean_text(row.get("Published")),
                "Name": clean_text(row.get("Name")),
                "Brands": clean_text(row.get("Brands")),
                "Status": status,
                "Matched ELLIA entry": source_name,
                "SKU": clean_text(updated_row.get(SKU_COLUMN)),
                GTIN_COLUMN: clean_text(updated_row.get(GTIN_COLUMN)),
                "Note": note,
            }
        )

    validate_unique_skus(enriched_rows)
    return enriched_rows, report_rows, summary


def build_output_paths(input_csv_path: str | Path, output_dir: str | Path) -> dict[str, Path]:
    source = Path(input_csv_path)
    base = Path(output_dir)
    stem = source.stem
    return {
        "import_ready": base / f"{stem}-ellia-import-ready.csv",
        "sku_gtin_only": base / f"{stem}-ellia-sku-gtin-only.csv",
        "match_report": base / f"{stem}-ellia-match-report.csv",
    }


def build_ellia_update_files(input_csv_path: str | Path, output_dir: str | Path) -> tuple[dict[str, Path], dict[str, int]]:
    rows = load_export_rows(input_csv_path)
    enriched_rows, report_rows, summary = apply_ellia_catalog_updates(rows)
    output_paths = build_output_paths(input_csv_path, output_dir)

    import_ready_fields = list(rows[0].keys()) if rows else []
    sku_gtin_fields = ["ID", "Type", "Parent", "Published", "Name", "Brands", SKU_COLUMN, GTIN_COLUMN]

    write_csv(output_paths["import_ready"], enriched_rows, import_ready_fields)
    write_csv(
        output_paths["sku_gtin_only"],
        [{field: row.get(field, "") for field in sku_gtin_fields} for row in enriched_rows],
        sku_gtin_fields,
    )
    write_csv(output_paths["match_report"], report_rows, list(report_rows[0].keys()) if report_rows else [])

    return output_paths, summary
