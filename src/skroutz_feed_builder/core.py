from __future__ import annotations

import csv
import html
import json
import re
import unicodedata
from collections import Counter, defaultdict
from dataclasses import asdict, dataclass, field
from datetime import datetime
from decimal import Decimal, InvalidOperation, ROUND_HALF_UP
from pathlib import Path
from typing import Any
from urllib.parse import urlparse
import xml.etree.ElementTree as ET

from .logging_utils import get_logger


DEFAULT_IN_STOCK_AVAILABILITY = "In stock"
DEFAULT_OUT_OF_STOCK_AVAILABILITY = "Available up to 12 days"
ALLOWED_AVAILABILITIES = (
    "In stock",
    "Available from 1 to 3 days",
    "Available from 4 to 6 days",
    "Available up to 12 days",
)
MAX_ADDITIONAL_IMAGES = 15
OVERRIDE_FIELDS = (
    "included",
    "name",
    "link",
    "image",
    "additional_images",
    "category",
    "price",
    "vat",
    "manufacturer",
    "mpn",
    "ean",
    "availability",
    "weight",
    "color",
    "size",
    "description",
    "quantity",
)


logger = get_logger("core")


@dataclass
class ValidationIssue:
    severity: str
    field: str
    message: str


@dataclass
class FeedSettings:
    root_element: str = "mywebstore"
    link_template: str = ""
    default_manufacturer: str = ""
    vat_rate: str = "24.00"
    in_stock_availability: str = DEFAULT_IN_STOCK_AVAILABILITY
    out_of_stock_availability: str = DEFAULT_OUT_OF_STOCK_AVAILABILITY
    export_only_published: bool = True
    skip_hidden_catalog: bool = True

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)

    @classmethod
    def from_dict(cls, payload: dict[str, Any] | None) -> "FeedSettings":
        return cls(**(payload or {}))


@dataclass
class SourceProduct:
    row_number: int
    product_id: str
    row_type: str
    parent_id: str
    published: bool
    visibility: str
    name: str
    sku: str
    gtin: str
    short_description: str
    description: str
    in_stock: str
    stock: str
    sale_price: str
    regular_price: str
    categories: str
    images: str
    weight_kg: str
    brand: str
    attribute_name: str
    attribute_values: str


@dataclass
class ProductOverride:
    included: bool | None = None
    name: str | None = None
    link: str | None = None
    image: str | None = None
    additional_images: str | None = None
    category: str | None = None
    price: str | None = None
    vat: str | None = None
    manufacturer: str | None = None
    mpn: str | None = None
    ean: str | None = None
    availability: str | None = None
    weight: str | None = None
    color: str | None = None
    size: str | None = None
    description: str | None = None
    quantity: str | None = None

    def to_dict(self) -> dict[str, Any]:
        return {key: value for key, value in asdict(self).items() if value is not None}

    @classmethod
    def from_dict(cls, payload: dict[str, Any] | None) -> "ProductOverride":
        if not payload:
            return cls()
        return cls(**{field: payload.get(field) for field in OVERRIDE_FIELDS})


@dataclass
class ResolvedProduct:
    source_id: str
    row_type: str
    parent_id: str
    row_number: int
    included: bool
    published: bool
    visibility: str
    name: str
    link: str
    image: str
    additional_images: list[str]
    category: str
    price: str
    vat: str
    manufacturer: str
    mpn: str
    ean: str
    availability: str
    weight: str
    color: str
    size: str
    description: str
    quantity: str
    issues: list[ValidationIssue] = field(default_factory=list)

    @property
    def error_count(self) -> int:
        return sum(issue.severity == "error" for issue in self.issues)

    @property
    def warning_count(self) -> int:
        return sum(issue.severity == "warning" for issue in self.issues)

    @property
    def status(self) -> str:
        if not self.included:
            return "Excluded"
        if self.error_count:
            return "Needs fixes"
        if self.warning_count:
            return "Review"
        return "Ready"


def clean_text(value: Any) -> str:
    return str(value or "").strip()


def split_escaped_list(value: str) -> list[str]:
    items: list[str] = []
    current: list[str] = []
    escaped = False
    for char in clean_text(value):
        if escaped:
            current.append(char)
            escaped = False
            continue
        if char == "\\":
            escaped = True
            continue
        if char == ",":
            item = "".join(current).strip()
            if item:
                items.append(item)
            current = []
            continue
        current.append(char)
    tail = "".join(current).strip()
    if tail:
        items.append(tail)
    return items


def strip_html(value: str) -> str:
    raw = clean_text(value).replace("\\n", "\n")
    raw = re.sub(r"(?i)<br\\s*/?>|</p>|</div>|</li>", "\n", raw)
    raw = re.sub(r"<[^>]+>", " ", raw)
    raw = html.unescape(raw)
    lines = [re.sub(r"\s+", " ", line).strip() for line in raw.splitlines()]
    return "\n".join(line for line in lines if line).strip()


def slugify(value: str) -> str:
    normalized = unicodedata.normalize("NFKD", clean_text(value)).encode("ascii", "ignore").decode("ascii").lower()
    slug = re.sub(r"[^a-z0-9]+", "-", normalized).strip("-")
    return slug or "product"


def parse_parent_id(value: str) -> str:
    raw = clean_text(value)
    return raw.split(":", 1)[1].strip() if raw.lower().startswith("id:") else raw


def parse_quantity(value: str) -> int | None:
    raw = clean_text(value)
    if not raw:
        return None
    try:
        return max(int(Decimal(raw.replace(",", "."))), 0)
    except (InvalidOperation, ValueError):
        return None


def parse_decimal(value: str) -> Decimal | None:
    raw = clean_text(value).replace(",", ".")
    if not raw:
        return None
    try:
        return Decimal(raw)
    except InvalidOperation:
        return None


def format_decimal(value: Decimal | None, places: int = 2) -> str:
    if value is None:
        return ""
    exp = Decimal(10) ** -places
    return f"{value.quantize(exp, rounding=ROUND_HALF_UP):f}"


def normalize_ean(value: str) -> str:
    return re.sub(r"\D+", "", clean_text(value))


def resolve_ean_value(gtin: str, sku: str) -> str:
    normalized_gtin = normalize_ean(gtin)
    if len(normalized_gtin) == 13:
        return normalized_gtin

    normalized_sku = normalize_ean(sku)
    if len(normalized_sku) == 13:
        logger.debug("Using SKU as EAN fallback because GTIN is missing or invalid. sku=%s", sku)
        return normalized_sku

    return normalized_gtin or normalized_sku


def extract_images(value: str) -> list[str]:
    return [part.strip() for part in clean_text(value).split(",") if part.strip()]


def infer_primary_category(value: str) -> str:
    categories = split_escaped_list(value)
    for category in categories:
        if ">" in category:
            return category
    return categories[0] if categories else ""


def normalize_weight_to_grams(value: str) -> str:
    weight = parse_decimal(value)
    if weight is None:
        return ""
    grams = (weight * Decimal("1000")).quantize(Decimal("1"), rounding=ROUND_HALF_UP)
    return str(int(grams))


def choose_price(sale_price: str, regular_price: str) -> str:
    sale = parse_decimal(sale_price)
    regular = parse_decimal(regular_price)
    chosen = sale if sale and sale > 0 else regular
    return format_decimal(chosen)


def looks_like_https_url(value: str) -> bool:
    raw = clean_text(value)
    parsed = urlparse(raw)
    return parsed.scheme.lower() == "https" and bool(parsed.netloc)


def render_link(template: str, source: SourceProduct, name: str) -> str:
    template = clean_text(template)
    replacements = {
        "{id}": source.product_id,
        "{parent_id}": source.parent_id,
        "{name}": name,
        "{slug}": slugify(name),
        "{sku}": source.sku,
        "{sku_or_id}": source.sku or source.product_id,
    }
    for key, value in replacements.items():
        template = template.replace(key, value)
    return template


def load_woocommerce_csv(csv_path: str | Path) -> list[SourceProduct]:
    csv_file = Path(csv_path)
    logger.info("Loading WooCommerce CSV from %s", csv_file)
    with csv_file.open("r", encoding="utf-8-sig", newline="") as handle:
        reader = csv.DictReader(handle)
        products = [
            SourceProduct(
                row_number=index,
                product_id=clean_text(row.get("ID")),
                row_type=clean_text(row.get("Type")).lower(),
                parent_id=parse_parent_id(row.get("Parent")),
                published=clean_text(row.get("Published")) == "1",
                visibility=clean_text(row.get("Visibility in catalog")).lower(),
                name=clean_text(row.get("Name")),
                sku=clean_text(row.get("SKU")),
                gtin=clean_text(row.get("GTIN, UPC, EAN, or ISBN")),
                short_description=clean_text(row.get("Short description")),
                description=clean_text(row.get("Description")),
                in_stock=clean_text(row.get("In stock?")),
                stock=clean_text(row.get("Stock")),
                sale_price=clean_text(row.get("Sale price")),
                regular_price=clean_text(row.get("Regular price")),
                categories=clean_text(row.get("Categories")),
                images=clean_text(row.get("Images")),
                weight_kg=clean_text(row.get("Weight (kg)")),
                brand=clean_text(row.get("Brands")),
                attribute_name=clean_text(row.get("Attribute 1 name")),
                attribute_values=clean_text(row.get("Attribute 1 value(s)")),
            )
            for index, row in enumerate(reader, start=2)
        ]
    row_types = Counter(product.row_type for product in products)
    logger.info(
        "Loaded %d product rows from %s. Types=%s published=%d hidden=%d",
        len(products),
        csv_file,
        dict(row_types),
        sum(product.published for product in products),
        sum(product.visibility in {"hidden", "search"} for product in products),
    )
    return products


def auto_detect_link_template(products: list[SourceProduct]) -> str:
    logger.debug("Attempting to auto-detect link template from %d products", len(products))
    for product in products:
        images = extract_images(product.images)
        if images:
            parsed = urlparse(images[0])
            if parsed.scheme and parsed.netloc:
                template = f"{parsed.scheme}://{parsed.netloc}/product/{{slug}}/"
                logger.info("Auto-detected link template: %s", template)
                return template
    logger.warning("Could not auto-detect a product link template from CSV images.")
    return ""


def infer_attribute_value(source: SourceProduct, parent: SourceProduct | None, keyword: str) -> str:
    attribute_name = clean_text(source.attribute_name or (parent.attribute_name if parent else ""))
    attribute_value = clean_text(source.attribute_values or (parent.attribute_values if parent else ""))
    if keyword not in attribute_name.lower():
        return ""
    parts = split_escaped_list(attribute_value)
    return parts[0] if len(parts) == 1 else ""


def resolve_products(
    source_products: list[SourceProduct],
    settings: FeedSettings,
    overrides: dict[str, ProductOverride] | None = None,
) -> list[ResolvedProduct]:
    overrides = overrides or {}
    logger.info(
        "Resolving %d source products with %d override entries. root=%s export_only_published=%s skip_hidden_catalog=%s",
        len(source_products),
        len(overrides),
        settings.root_element,
        settings.export_only_published,
        settings.skip_hidden_catalog,
    )
    parent_map = {product.product_id: product for product in source_products if product.product_id}
    child_map: dict[str, list[SourceProduct]] = defaultdict(list)
    for product in source_products:
        if product.parent_id:
            child_map[product.parent_id].append(product)

    resolved: list[ResolvedProduct] = []
    for source in source_products:
        parent = parent_map.get(source.parent_id) if source.parent_id else None
        has_children = bool(child_map.get(source.product_id))
        sibling_count = len(child_map.get(source.parent_id, []))
        override = overrides.get(source.product_id, ProductOverride())

        def merged(field: str) -> str:
            local = clean_text(getattr(source, field))
            if local:
                return local
            if source.row_type == "variation" and parent is not None:
                return clean_text(getattr(parent, field))
            return ""

        quantity = parse_quantity(source.stock)
        if quantity is None and source.row_type == "variation" and parent and sibling_count == 1:
            quantity = parse_quantity(parent.stock)
        quantity = quantity if quantity is not None else 0

        name = override.name if override.name is not None else strip_html(merged("name"))
        effective_sku = merged("sku")
        images = extract_images(merged("images"))
        included_default = (
            (not settings.export_only_published or source.published)
            and (not settings.skip_hidden_catalog or source.visibility not in {"hidden", "search"})
            and not (source.row_type == "variable" and has_children)
            and source.row_type in {"simple", "variation", "variable"}
        )

        product = ResolvedProduct(
            source_id=source.product_id,
            row_type=source.row_type,
            parent_id=source.parent_id,
            row_number=source.row_number,
            included=override.included if override.included is not None else included_default,
            published=source.published,
            visibility=source.visibility,
            name=name,
            link=clean_text(override.link if override.link is not None else render_link(settings.link_template, source, name)),
            image=clean_text(override.image if override.image is not None else (images[0] if images else "")),
            additional_images=extract_images(override.additional_images) if override.additional_images is not None else images[1 : 1 + MAX_ADDITIONAL_IMAGES],
            category=clean_text(override.category if override.category is not None else infer_primary_category(merged("categories"))),
            price=clean_text(override.price if override.price is not None else choose_price(merged("sale_price"), merged("regular_price"))),
            vat=clean_text(override.vat if override.vat is not None else settings.vat_rate),
            manufacturer=clean_text(override.manufacturer if override.manufacturer is not None else (merged("brand") or settings.default_manufacturer)),
            mpn=clean_text(override.mpn if override.mpn is not None else (effective_sku or source.product_id)),
            ean=normalize_ean(override.ean) if override.ean is not None else resolve_ean_value(merged("gtin"), effective_sku),
            availability=clean_text(
                override.availability
                if override.availability is not None
                else (settings.in_stock_availability if quantity > 0 else settings.out_of_stock_availability)
            ),
            weight=clean_text(override.weight if override.weight is not None else normalize_weight_to_grams(merged("weight_kg"))),
            color=clean_text(override.color if override.color is not None else infer_attribute_value(source, parent, "color")),
            size=clean_text(override.size if override.size is not None else infer_attribute_value(source, parent, "size")),
            description=clean_text(override.description if override.description is not None else strip_html(merged("description") or merged("short_description"))),
            quantity=clean_text(override.quantity if override.quantity is not None else str(quantity)),
        )
        product.issues = validate_product(product, has_children)
        logger.debug(
            "Resolved product %s type=%s included=%s price=%s quantity=%s errors=%d warnings=%d",
            product.source_id,
            product.row_type,
            product.included,
            product.price,
            product.quantity,
            product.error_count,
            product.warning_count,
        )
        if product.issues:
            for issue in product.issues:
                level = "ERROR" if issue.severity == "error" else "WARNING"
                logger.debug(
                    "Resolved product %s issue %s %s: %s",
                    product.source_id,
                    level,
                    issue.field,
                    issue.message,
                )
        resolved.append(product)
    summary = count_statuses(resolved)
    logger.info(
        "Resolved products summary: total=%d included=%d ready=%d review=%d needs_fixes=%d excluded=%d",
        summary["total"],
        summary["included"],
        summary["ready"],
        summary["review"],
        summary["needs_fixes"],
        summary["excluded"],
    )
    return resolved


def validate_product(product: ResolvedProduct, has_children: bool = False) -> list[ValidationIssue]:
    issues: list[ValidationIssue] = []

    def add(severity: str, field: str, message: str) -> None:
        issues.append(ValidationIssue(severity, field, message))

    if product.row_type == "variable" and has_children:
        add("warning", "included", "Variable parents are usually exported through child variation rows.")
    if not product.published:
        add("warning", "included", "This product is unpublished in WooCommerce.")
    if product.visibility in {"hidden", "search"}:
        add("warning", "included", f'This product visibility is "{product.visibility}".')
    if not product.name:
        add("error", "name", "Name is required.")
    elif len(product.name) > 300:
        add("error", "name", "Name exceeds 300 characters.")
    if not product.link:
        add("error", "link", "Product link is required.")
    elif len(product.link) > 1000 or not looks_like_https_url(product.link):
        add("error", "link", "Product link must be a valid https URL.")
    if not product.image:
        add("warning", "image", "Main image is empty.")
    elif len(product.image) > 400 or not looks_like_https_url(product.image):
        add("error", "image", "Main image must be a valid https URL up to 400 characters.")
    if any(len(image) > 400 or not looks_like_https_url(image) for image in product.additional_images):
        add("error", "additional_images", "Additional images must be valid https URLs up to 400 characters.")
    if not product.category:
        add("error", "category", "Category path is required.")
    elif len(product.category) > 250:
        add("error", "category", "Category exceeds 250 characters.")
    price = parse_decimal(product.price)
    if price is None:
        add("error", "price", "Price with VAT is required.")
    elif price < 0:
        add("error", "price", "Price cannot be negative.")
    vat = parse_decimal(product.vat)
    if vat is None:
        add("error", "vat", "VAT is required.")
    elif vat < 0 or vat > 100:
        add("error", "vat", "VAT must be between 0 and 100.")
    if not product.manufacturer:
        add("error", "manufacturer", "Manufacturer is required.")
    elif len(product.manufacturer) > 100:
        add("error", "manufacturer", "Manufacturer exceeds 100 characters.")
    if not product.mpn:
        add("error", "mpn", "MPN is required.")
    elif len(product.mpn) > 80:
        add("error", "mpn", "MPN exceeds 80 characters.")
    if not product.ean:
        add("error", "ean", "EAN / barcode is required for a compliant Skroutz feed.")
    elif not re.fullmatch(r"\d{13}", product.ean):
        add("error", "ean", "EAN must contain exactly 13 digits.")
    if not product.availability:
        add("error", "availability", "Availability is required.")
    elif product.availability not in ALLOWED_AVAILABILITIES:
        add("warning", "availability", "Availability does not match Skroutz's standard labels.")
    if not product.description:
        add("error", "description", "Description is required.")
    elif len(product.description) > 10000:
        add("error", "description", "Description exceeds 10000 characters.")
    elif "<" in product.description or ">" in product.description:
        add("error", "description", "Description cannot contain HTML.")
    quantity = parse_quantity(product.quantity)
    if quantity is None:
        add("error", "quantity", "Quantity must be a whole number.")
    elif quantity > 10_000_000:
        add("error", "quantity", "Quantity exceeds Skroutz's maximum value.")
    if product.weight and parse_decimal(product.weight) is None:
        add("error", "weight", "Weight must be numeric.")
    if issues:
        logger.debug(
            "Validation produced %d issue(s) for product %s (%s).",
            len(issues),
            product.source_id,
            product.name,
        )
    return issues


def export_xml(products: list[ResolvedProduct], output_path: str | Path, root_element: str) -> Path:
    logger.info("Exporting %d product(s) to XML at %s with root element %s", len(products), output_path, root_element)
    root_name = re.sub(r"[^A-Za-z0-9_]+", "_", clean_text(root_element)) or "mywebstore"
    root = ET.Element(root_name)
    ET.SubElement(root, "created_at").text = datetime.now().strftime("%Y-%m-%d %H:%M")
    products_node = ET.SubElement(root, "products")
    for product in products:
        logger.debug("Writing product %s (%s) to XML", product.source_id, product.name)
        node = ET.SubElement(products_node, "product")
        ET.SubElement(node, "id").text = product.source_id
        ET.SubElement(node, "name").text = product.name
        ET.SubElement(node, "link").text = product.link
        ET.SubElement(node, "image").text = product.image
        for extra in product.additional_images[:MAX_ADDITIONAL_IMAGES]:
            ET.SubElement(node, "additionalimage").text = extra
        ET.SubElement(node, "category").text = product.category
        ET.SubElement(node, "price_with_vat").text = product.price
        ET.SubElement(node, "vat").text = product.vat
        ET.SubElement(node, "manufacturer").text = product.manufacturer
        ET.SubElement(node, "mpn").text = product.mpn
        ET.SubElement(node, "ean").text = product.ean
        ET.SubElement(node, "availability").text = product.availability
        if product.size:
            ET.SubElement(node, "size").text = product.size
        if product.weight:
            ET.SubElement(node, "weight").text = product.weight
        if product.color:
            ET.SubElement(node, "color").text = product.color
        ET.SubElement(node, "description").text = product.description
        ET.SubElement(node, "quantity").text = product.quantity
    tree = ET.ElementTree(root)
    ET.indent(tree, space="  ")
    destination = Path(output_path)
    destination.parent.mkdir(parents=True, exist_ok=True)
    tree.write(destination, encoding="utf-8", xml_declaration=True)
    logger.info("XML export completed: %s", destination)
    return destination


def save_project(project_path: str | Path, csv_path: str | Path, settings: FeedSettings, overrides: dict[str, ProductOverride]) -> Path:
    destination = Path(project_path)
    destination.parent.mkdir(parents=True, exist_ok=True)
    logger.info(
        "Saving project file to %s using csv=%s and %d override entries",
        destination,
        csv_path,
        len(overrides),
    )
    payload = {
        "version": 1,
        "csv_path": str(Path(csv_path)),
        "settings": settings.to_dict(),
        "overrides": {key: value.to_dict() for key, value in overrides.items() if value.to_dict()},
    }
    destination.write_text(json.dumps(payload, indent=2), encoding="utf-8")
    logger.info("Project file saved to %s", destination)
    return destination


def load_project(project_path: str | Path) -> tuple[Path, FeedSettings, dict[str, ProductOverride]]:
    project_file = Path(project_path)
    logger.info("Loading project file from %s", project_file)
    payload = json.loads(project_file.read_text(encoding="utf-8"))
    csv_path = Path(payload["csv_path"])
    if not csv_path.is_absolute():
        csv_path = (project_file.parent / csv_path).resolve()
    settings = FeedSettings.from_dict(payload.get("settings"))
    overrides = {key: ProductOverride.from_dict(value) for key, value in (payload.get("overrides") or {}).items()}
    logger.info("Loaded project file %s with csv=%s and %d overrides", project_file, csv_path, len(overrides))
    return csv_path, settings, overrides


def count_statuses(products: list[ResolvedProduct]) -> dict[str, int]:
    summary = {
        "total": len(products),
        "included": sum(product.included for product in products),
        "ready": sum(product.included and product.error_count == 0 and product.warning_count == 0 for product in products),
        "review": sum(product.included and product.error_count == 0 and product.warning_count > 0 for product in products),
        "needs_fixes": sum(product.included and product.error_count > 0 for product in products),
        "excluded": sum(not product.included for product in products),
    }
    logger.debug("Calculated status summary: %s", summary)
    return summary
