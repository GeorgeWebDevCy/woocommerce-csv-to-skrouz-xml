from __future__ import annotations

from pathlib import Path
from typing import Iterable

from PIL import Image, ImageDraw, ImageFont


REPO_ROOT = Path(__file__).resolve().parent.parent
ASSET_DIR = REPO_ROOT / "plugin" / "skroutz-xml-feed-for-woocommerce" / "assets"

BG_TOP = (255, 139, 61)
BG_BOTTOM = (242, 90, 73)
NAVY = (16, 28, 52)
NAVY_SOFT = (29, 45, 78)
CREAM = (252, 247, 238)
INK = (24, 33, 52)
MINT = (114, 223, 193)
SKY = (166, 218, 255)
GOLD = (255, 213, 102)
ROSE = (255, 196, 184)
WHITE = (255, 255, 255)
SHADOW = (10, 15, 30, 35)


def main() -> None:
    ASSET_DIR.mkdir(parents=True, exist_ok=True)

    create_icon_assets()
    create_banner_assets()
    create_screenshots()
    create_svg_icon()


def create_icon_assets() -> None:
    base = draw_icon(256)
    base.save(ASSET_DIR / "icon-256x256.png")
    base.resize((128, 128), Image.Resampling.LANCZOS).save(ASSET_DIR / "icon-128x128.png")


def create_banner_assets() -> None:
    large = draw_banner(1544, 500)
    large.save(ASSET_DIR / "banner-1544x500.png")
    large.resize((772, 250), Image.Resampling.LANCZOS).save(ASSET_DIR / "banner-772x250.png")


def create_screenshots() -> None:
    draw_dashboard_screenshot().save(ASSET_DIR / "screenshot-1.png")
    draw_validation_screenshot().save(ASSET_DIR / "screenshot-2.png")
    draw_product_editor_screenshot().save(ASSET_DIR / "screenshot-3.png")


def draw_icon(size: int) -> Image.Image:
    image = Image.new("RGBA", (size, size), WHITE)
    draw_vertical_gradient(image, BG_TOP, BG_BOTTOM)
    draw = ImageDraw.Draw(image, "RGBA")

    inset = int(size * 0.11)
    panel = (inset, inset, size - inset, size - inset)
    draw.rounded_rectangle(offset_box(panel, 0, int(size * 0.03)), radius=int(size * 0.16), fill=SHADOW)
    draw.rounded_rectangle(panel, radius=int(size * 0.16), fill=NAVY)

    arc_width = max(6, size // 24)
    draw.arc((size * 0.18, size * 0.22, size * 0.82, size * 0.84), start=220, end=320, fill=GOLD, width=arc_width)
    draw.arc((size * 0.18, size * 0.16, size * 0.82, size * 0.78), start=40, end=140, fill=MINT, width=arc_width)

    code_font = load_font(int(size * 0.23), bold=True)
    tag_font = load_font(int(size * 0.12), bold=True)

    center_text(draw, (size * 0.5, size * 0.42), "</>", code_font, fill=CREAM)
    center_text(draw, (size * 0.5, size * 0.65), "XML FEED", tag_font, fill=SKY)

    return image


def draw_banner(width: int, height: int) -> Image.Image:
    image = Image.new("RGBA", (width, height), WHITE)
    draw_vertical_gradient(image, BG_TOP, BG_BOTTOM)
    draw = ImageDraw.Draw(image, "RGBA")

    add_orbs(draw, width, height)

    left_margin = int(width * 0.055)
    top_margin = int(height * 0.12)

    eyebrow_font = load_font(int(height * 0.09), bold=True)
    title_font = load_font(int(height * 0.14), bold=True)
    body_font = load_font(int(height * 0.062))
    pill_font = load_font(int(height * 0.055), bold=True)

    draw.rounded_rectangle((left_margin, top_margin, left_margin + int(width * 0.18), top_margin + int(height * 0.12)), radius=24, fill=(255, 255, 255, 48))
    draw.text((left_margin + 18, top_margin + 10), "WOOCOMMERCE PLUGIN", font=eyebrow_font, fill=CREAM)

    title_y = top_margin + int(height * 0.16)
    draw.text((left_margin, title_y), "Skroutz XML Feed", font=title_font, fill=CREAM)
    draw.text((left_margin, title_y + int(height * 0.16)), "for WooCommerce", font=title_font, fill=CREAM)

    body_y = title_y + int(height * 0.35)
    draw.text((left_margin, body_y), "Generate, validate, cache, and publish", font=body_font, fill=(255, 244, 232))
    draw.text((left_margin, body_y + int(height * 0.08)), "your Skroutz product feed directly from WordPress.", font=body_font, fill=(255, 244, 232))

    pill_y = body_y + int(height * 0.19)
    draw_pill(draw, (left_margin, pill_y), "Public feed URL", pill_font, NAVY, SKY)
    draw_pill(draw, (left_margin + int(width * 0.19), pill_y), "Validation report", pill_font, NAVY, MINT)
    draw_pill(draw, (left_margin + int(width * 0.39), pill_y), "GitHub updates", pill_font, NAVY, GOLD)

    panel_width = int(width * 0.36)
    panel_height = int(height * 0.7)
    panel_x = width - panel_width - int(width * 0.06)
    panel_y = int(height * 0.14)
    draw_mock_dashboard(draw, (panel_x, panel_y, panel_x + panel_width, panel_y + panel_height))

    return image


def draw_dashboard_screenshot() -> Image.Image:
    image = Image.new("RGBA", (1600, 1000), CREAM)
    draw = ImageDraw.Draw(image, "RGBA")
    draw_app_shell(draw, image.size, "WooCommerce -> Skroutz XML Feed")

    draw.text((96, 118), "Skroutz XML Feed", font=load_font(42, bold=True), fill=INK)
    draw.text((96, 168), "Feed URL, cache state, and validation summary", font=load_font(22), fill=(73, 83, 103))

    draw_info_panel(draw, (96, 230, 1504, 358), "Public feed URL", "https://store.example/skroutz-feed.xml", ("Open Feed", "Generate Feed", "Open Validator"))

    card_specs = [
        ("Total", "248", SKY),
        ("Ready", "221", MINT),
        ("Review", "14", GOLD),
        ("Needs fixes", "8", ROSE),
        ("Excluded", "5", (215, 224, 240)),
    ]
    x = 96
    for label, value, accent in card_specs:
        draw_stat_card(draw, (x, 394, x + 248, 522), label, value, accent)
        x += 272

    draw_panel(draw, (96, 560, 782, 924), "Plugin Settings")
    fields = [
        ("Root XML element", "mywebstore"),
        ("Default manufacturer", "Store Brand"),
        ("In-stock availability label", "In stock"),
        ("Cache TTL (minutes)", "60"),
    ]
    y = 630
    for label, value in fields:
        draw_setting_row(draw, (126, y, 752, y + 52), label, value)
        y += 68
    draw_button(draw, (126, 854, 298, 902), "Save Settings", fill=NAVY, text_fill=CREAM)

    draw_panel(draw, (818, 560, 1504, 924), "Products Needing Attention")
    draw_table_header(draw, (848, 628, 1474, 670), ("Product", "Status", "Issues"))
    rows = [
        ("Athena Sandal #183", "Needs fixes", "Missing manufacturer, missing EAN"),
        ("Atlas Backpack #204", "Review", "Availability label is custom"),
        ("Delta Jacket Blue #219", "Needs fixes", "Description too long"),
        ("Luna Mug Red #221", "Review", "Main image missing"),
    ]
    y = 686
    for product, status, issues in rows:
        draw_table_row(draw, (848, y, 1474, y + 54), (product, status, issues), status)
        y += 62

    return image


def draw_validation_screenshot() -> Image.Image:
    image = Image.new("RGBA", (1600, 1000), CREAM)
    draw = ImageDraw.Draw(image, "RGBA")
    draw_app_shell(draw, image.size, "WooCommerce -> Skroutz XML Feed")

    draw.text((96, 118), "Validation Report", font=load_font(42, bold=True), fill=INK)
    draw.text((96, 168), "Included products with warnings or blocking feed errors", font=load_font(22), fill=(73, 83, 103))

    draw_panel(draw, (96, 230, 1504, 914), "Products Needing Attention")
    draw_table_header(draw, (126, 298, 1474, 344), ("Product", "Status", "Issues"))

    rows = [
        ("Helios Lantern #142", "Needs fixes", "Manufacturer is required. EAN must contain exactly 13 digits."),
        ("Helios Lantern Bronze #143", "Needs fixes", "Category path is required."),
        ("Aegean Pillow #177", "Review", "Availability does not match Skroutz standard labels."),
        ("Aegean Pillow Blue #178", "Needs fixes", "Description cannot contain HTML."),
        ("Meridian Towel #181", "Review", "Main image is empty."),
        ("Iris Bottle #188", "Needs fixes", "Product link must be a valid HTTPS URL."),
        ("Argo Sneakers #191", "Review", "Variable parents are exported through child variation rows."),
        ("Argo Sneakers Black 42 #192", "Needs fixes", "EAN / barcode is required."),
    ]
    y = 360
    for product, status, issues in rows:
        draw_table_row(draw, (126, y, 1474, y + 58), (product, status, issues), status)
        y += 66

    return image


def draw_product_editor_screenshot() -> Image.Image:
    image = Image.new("RGBA", (1600, 1000), CREAM)
    draw = ImageDraw.Draw(image, "RGBA")
    draw_app_shell(draw, image.size, "Products -> Edit product")

    draw.text((96, 118), "Product-Level Feed Overrides", font=load_font(42, bold=True), fill=INK)
    draw.text((96, 168), "Use per-product fields when WooCommerce data needs a feed-specific correction", font=load_font(22), fill=(73, 83, 103))

    draw_panel(draw, (96, 230, 914, 914), "Product data")
    draw_meta_tabs(draw, (126, 300, 286, 866))
    draw_settings_panel(draw, (310, 300, 884, 866))

    draw_panel(draw, (948, 230, 1504, 914), "Override examples")
    notes = [
        ("Custom feed name", "Atlas Backpack 25L Travel Pack"),
        ("Manufacturer", "Atlas Gear"),
        ("EAN", "5201234567890"),
        ("Availability label", "Available from 1 to 3 days"),
        ("Custom description", "Compact commuter backpack with padded laptop sleeve."),
    ]
    y = 314
    for label, value in notes:
        draw_setting_row(draw, (978, y, 1474, y + 56), label, value)
        y += 86

    return image


def draw_app_shell(draw: ImageDraw.ImageDraw, size: tuple[int, int], breadcrumb: str) -> None:
    width, height = size
    draw.rectangle((0, 0, width, height), fill=CREAM)
    draw.rectangle((0, 0, width, 74), fill=NAVY)
    draw.text((30, 22), "WordPress Admin", font=load_font(26, bold=True), fill=CREAM)
    draw.text((320, 24), breadcrumb, font=load_font(22), fill=(208, 221, 246))
    draw.rounded_rectangle((0, 74, 78, height), radius=0, fill=NAVY_SOFT)
    for index, label in enumerate(("Dashboard", "WooCommerce", "Products", "Marketing", "Tools")):
        top = 112 + index * 94
        draw.rounded_rectangle((12, top, 66, top + 54), radius=16, fill=(255, 255, 255, 16))
        draw.text((21, top + 15), label[0], font=load_font(22, bold=True), fill=CREAM)


def draw_info_panel(draw: ImageDraw.ImageDraw, box: tuple[int, int, int, int], title: str, value: str, buttons: Iterable[str]) -> None:
    draw_panel(draw, box, title)
    left, top, right, _ = box
    draw.text((left + 30, top + 56), value, font=load_font(26, bold=True), fill=INK)
    x = left + 30
    for button in buttons:
        draw_button(draw, (x, top + 96, x + 178, top + 138), button, fill=NAVY if button == "Generate Feed" else WHITE, text_fill=CREAM if button == "Generate Feed" else INK, outline=(216, 222, 233))
        x += 196


def draw_mock_dashboard(draw: ImageDraw.ImageDraw, box: tuple[int, int, int, int]) -> None:
    left, top, right, bottom = box
    draw.rounded_rectangle(offset_box(box, 0, 18), radius=34, fill=SHADOW)
    draw.rounded_rectangle(box, radius=34, fill=CREAM)
    draw.rounded_rectangle((left + 26, top + 24, right - 26, top + 86), radius=20, fill=NAVY)
    draw.text((left + 50, top + 42), "Skroutz XML Feed", font=load_font(26, bold=True), fill=CREAM)
    draw.text((left + 50, top + 110), "https://store.example/skroutz-feed.xml", font=load_font(18, bold=True), fill=INK)
    draw.rounded_rectangle((left + 46, top + 148, left + 178, top + 190), radius=18, fill=(230, 241, 255))
    draw.rounded_rectangle((left + 196, top + 148, left + 356, top + 190), radius=18, fill=(226, 247, 238))
    draw.rounded_rectangle((left + 374, top + 148, left + 534, top + 190), radius=18, fill=(255, 242, 212))
    draw.text((left + 70, top + 159), "Ready 221", font=load_font(18, bold=True), fill=NAVY)
    draw.text((left + 228, top + 159), "Review 14", font=load_font(18, bold=True), fill=NAVY)
    draw.text((left + 405, top + 159), "Fixes 8", font=load_font(18, bold=True), fill=NAVY)

    draw.rounded_rectangle((left + 46, top + 220, right - 46, bottom - 40), radius=24, fill=WHITE, outline=(224, 228, 236))
    draw.text((left + 72, top + 246), "Products needing attention", font=load_font(22, bold=True), fill=INK)
    headers = ("Product", "Status", "Issues")
    positions = (left + 72, left + 300, left + 458)
    for header, x in zip(headers, positions):
        draw.text((x, top + 292), header, font=load_font(17, bold=True), fill=(87, 97, 117))
    rows = (
        ("Atlas Backpack", "Needs fixes", "Missing EAN"),
        ("Luna Mug Blue", "Review", "Image missing"),
        ("Argo Sneakers 42", "Ready", "Exportable"),
    )
    y = top + 328
    for product, status, issue in rows:
        draw.rounded_rectangle((left + 60, y, right - 60, y + 46), radius=14, fill=(249, 250, 252))
        draw.text((left + 72, y + 12), product, font=load_font(16, bold=True), fill=INK)
        draw_status_badge(draw, (left + 300, y + 7, left + 428, y + 39), status)
        draw.text((left + 458, y + 12), issue, font=load_font(15), fill=(74, 83, 102))
        y += 60


def draw_panel(draw: ImageDraw.ImageDraw, box: tuple[int, int, int, int], title: str) -> None:
    left, top, right, bottom = box
    draw.rounded_rectangle(offset_box(box, 0, 8), radius=28, fill=(50, 62, 89, 16))
    draw.rounded_rectangle(box, radius=28, fill=WHITE, outline=(224, 228, 236))
    draw.text((left + 30, top + 22), title, font=load_font(28, bold=True), fill=INK)


def draw_stat_card(draw: ImageDraw.ImageDraw, box: tuple[int, int, int, int], label: str, value: str, accent: tuple[int, int, int]) -> None:
    left, top, right, bottom = box
    draw.rounded_rectangle(box, radius=26, fill=WHITE, outline=(224, 228, 236))
    draw.rounded_rectangle((left + 24, top + 20, left + 88, top + 84), radius=20, fill=accent)
    draw.text((left + 112, top + 28), label, font=load_font(22, bold=True), fill=(87, 97, 117))
    draw.text((left + 112, top + 66), value, font=load_font(40, bold=True), fill=INK)


def draw_setting_row(draw: ImageDraw.ImageDraw, box: tuple[int, int, int, int], label: str, value: str) -> None:
    left, top, right, bottom = box
    draw.rounded_rectangle(box, radius=18, fill=(249, 250, 252), outline=(231, 235, 241))
    draw.text((left + 18, top + 12), label, font=load_font(16, bold=True), fill=(87, 97, 117))
    draw.text((left + 18, top + 28), value, font=load_font(18), fill=INK)


def draw_button(
    draw: ImageDraw.ImageDraw,
    box: tuple[int, int, int, int],
    label: str,
    *,
    fill: tuple[int, int, int] | tuple[int, int, int, int],
    text_fill: tuple[int, int, int] | tuple[int, int, int, int],
    outline: tuple[int, int, int] | None = None,
) -> None:
    draw.rounded_rectangle(box, radius=18, fill=fill, outline=outline, width=2 if outline else 0)
    center_text(draw, ((box[0] + box[2]) / 2, (box[1] + box[3]) / 2), label, load_font(17, bold=True), fill=text_fill)


def draw_table_header(draw: ImageDraw.ImageDraw, box: tuple[int, int, int, int], headers: tuple[str, str, str]) -> None:
    left, top, right, bottom = box
    draw.rounded_rectangle(box, radius=18, fill=(244, 246, 250))
    positions = (left + 18, left + 408, left + 630)
    for header, x in zip(headers, positions):
        draw.text((x, top + 10), header, font=load_font(18, bold=True), fill=(87, 97, 117))


def draw_table_row(draw: ImageDraw.ImageDraw, box: tuple[int, int, int, int], values: tuple[str, str, str], status: str) -> None:
    left, top, right, bottom = box
    draw.rounded_rectangle(box, radius=18, fill=WHITE, outline=(231, 235, 241))
    draw.text((left + 18, top + 16), values[0], font=load_font(18, bold=True), fill=INK)
    draw_status_badge(draw, (left + 392, top + 12, left + 560, top + 46), status)
    draw.multiline_text((left + 614, top + 12), values[2], font=load_font(17), fill=(74, 83, 102), spacing=4)


def draw_status_badge(draw: ImageDraw.ImageDraw, box: tuple[int, int, int, int], status: str) -> None:
    fills = {
        "Ready": (226, 247, 238),
        "Review": (255, 242, 212),
        "Needs fixes": (255, 225, 218),
        "Excluded": (230, 234, 240),
    }
    text_colors = {
        "Ready": NAVY,
        "Review": NAVY,
        "Needs fixes": (142, 56, 31),
        "Excluded": (72, 82, 100),
    }
    draw.rounded_rectangle(box, radius=16, fill=fills.get(status, SKY))
    center_text(draw, ((box[0] + box[2]) / 2, (box[1] + box[3]) / 2), status, load_font(15, bold=True), fill=text_colors.get(status, NAVY))


def draw_meta_tabs(draw: ImageDraw.ImageDraw, box: tuple[int, int, int, int]) -> None:
    left, top, right, bottom = box
    draw.rounded_rectangle(box, radius=24, fill=(244, 246, 250))
    items = ("General", "Inventory", "Shipping", "Attributes", "Variations", "Skroutz Feed")
    y = top + 22
    for item in items:
        active = item == "Skroutz Feed"
        fill = NAVY if active else (244, 246, 250)
        text_fill = CREAM if active else INK
        outline = None if active else (224, 228, 236)
        draw.rounded_rectangle((left + 16, y, right - 16, y + 54), radius=18, fill=fill, outline=outline)
        draw.text((left + 28, y + 16), item, font=load_font(18, bold=active), fill=text_fill)
        y += 68


def draw_settings_panel(draw: ImageDraw.ImageDraw, box: tuple[int, int, int, int]) -> None:
    left, top, right, bottom = box
    draw.rounded_rectangle(box, radius=24, fill=WHITE, outline=(224, 228, 236))
    draw.text((left + 26, top + 24), "Skroutz Feed Overrides", font=load_font(26, bold=True), fill=INK)

    rows = [
        ("Exclude from Skroutz feed", "No"),
        ("Custom feed name", "Atlas Backpack 25L Travel Pack"),
        ("Custom product URL", "https://store.example/product/atlas-backpack"),
        ("Manufacturer", "Atlas Gear"),
        ("MPN", "ATLAS-BAG-25"),
        ("EAN", "5201234567890"),
        ("Availability label", "Available from 1 to 3 days"),
        ("Color", "Olive"),
        ("Size", "25L"),
    ]
    y = top + 84
    for label, value in rows:
        draw_setting_row(draw, (left + 24, y, right - 24, y + 54), label, value)
        y += 66
    draw_button(draw, (left + 24, bottom - 78, left + 212, bottom - 30), "Update", fill=NAVY, text_fill=CREAM)


def add_orbs(draw: ImageDraw.ImageDraw, width: int, height: int) -> None:
    draw.ellipse((width * 0.58, -height * 0.12, width * 0.94, height * 0.56), fill=(255, 255, 255, 28))
    draw.ellipse((width * 0.82, height * 0.48, width * 1.08, height * 0.98), fill=(255, 255, 255, 24))
    draw.ellipse((width * 0.46, height * 0.54, width * 0.66, height * 0.96), fill=(255, 255, 255, 18))


def draw_pill(draw: ImageDraw.ImageDraw, position: tuple[int, int], label: str, font: ImageFont.FreeTypeFont, fill: tuple[int, int, int], text_fill: tuple[int, int, int]) -> None:
    x, y = position
    bbox = draw.textbbox((0, 0), label, font=font)
    width = bbox[2] - bbox[0] + 34
    height = bbox[3] - bbox[1] + 18
    draw.rounded_rectangle((x, y, x + width, y + height), radius=height // 2, fill=fill)
    draw.text((x + 17, y + 8), label, font=font, fill=text_fill)


def draw_vertical_gradient(image: Image.Image, top_color: tuple[int, int, int], bottom_color: tuple[int, int, int]) -> None:
    width, height = image.size
    draw = ImageDraw.Draw(image)
    for y in range(height):
        mix = y / max(1, height - 1)
        color = tuple(int(top_color[index] * (1 - mix) + bottom_color[index] * mix) for index in range(3))
        draw.line((0, y, width, y), fill=color)


def create_svg_icon() -> None:
    svg = """<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" role="img" aria-labelledby="title desc">
  <title id="title">Skroutz XML Feed for WooCommerce</title>
  <desc id="desc">Orange gradient icon with a navy card and XML label.</desc>
  <defs>
    <linearGradient id="bg" x1="0" x2="0" y1="0" y2="1">
      <stop offset="0%" stop-color="#ff8b3d"/>
      <stop offset="100%" stop-color="#f25a49"/>
    </linearGradient>
  </defs>
  <rect width="256" height="256" rx="44" fill="url(#bg)"/>
  <rect x="28" y="36" width="200" height="200" rx="34" fill="#101c34"/>
  <path d="M61 150c23-14 49-39 57-76" fill="none" stroke="#ffd566" stroke-linecap="round" stroke-width="10"/>
  <path d="M195 80c-23 13-49 39-57 76" fill="none" stroke="#72dfc1" stroke-linecap="round" stroke-width="10"/>
  <text x="128" y="118" fill="#fcf7ee" font-family="Segoe UI, Arial, sans-serif" font-size="54" font-weight="700" text-anchor="middle">&lt;/&gt;</text>
  <text x="128" y="168" fill="#a6daff" font-family="Segoe UI, Arial, sans-serif" font-size="24" font-weight="700" text-anchor="middle">XML FEED</text>
</svg>
"""
    (ASSET_DIR / "icon.svg").write_text(svg, encoding="utf-8")


def center_text(
    draw: ImageDraw.ImageDraw,
    center: tuple[float, float],
    text: str,
    font: ImageFont.FreeTypeFont,
    *,
    fill: tuple[int, int, int] | tuple[int, int, int, int],
) -> None:
    bbox = draw.textbbox((0, 0), text, font=font)
    width = bbox[2] - bbox[0]
    height = bbox[3] - bbox[1]
    draw.text((center[0] - width / 2, center[1] - height / 2 - bbox[1]), text, font=font, fill=fill)


def offset_box(box: tuple[int, int, int, int], dx: int, dy: int) -> tuple[int, int, int, int]:
    return (box[0] + dx, box[1] + dy, box[2] + dx, box[3] + dy)


def load_font(size: int, *, bold: bool = False) -> ImageFont.FreeTypeFont:
    candidates = []
    if bold:
        candidates.extend(
            [
                Path("C:/Windows/Fonts/seguisb.ttf"),
                Path("C:/Windows/Fonts/segoeuib.ttf"),
                Path("C:/Windows/Fonts/arialbd.ttf"),
            ]
        )
    else:
        candidates.extend(
            [
                Path("C:/Windows/Fonts/segoeui.ttf"),
                Path("C:/Windows/Fonts/arial.ttf"),
            ]
        )

    for path in candidates:
        if path.exists():
            return ImageFont.truetype(str(path), size=size)

    return ImageFont.load_default()


if __name__ == "__main__":
    main()
