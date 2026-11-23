"""Verify the catalog assets and assemble owner-review boards and the SVN manifest."""

import argparse
import hashlib
import json
from pathlib import Path
from zipfile import ZipFile

from PIL import Image, ImageDraw, ImageFont, ImageOps


RELEASE = Path(__file__).resolve().parent
ROOT = RELEASE.parent
CATALOG = ROOT / "wordpress-org-assets"
FILES = (
    ("banner-772x250.png", (772, 250), "Standard directory banner"),
    ("banner-1544x500.png", (1544, 500), "Retina directory banner"),
    ("icon-128x128.png", (128, 128), "Standard directory icon"),
    ("icon-256x256.png", (256, 256), "Retina directory icon"),
    ("screenshot-1.png", (1440, 1040), "WooCommerce storefront notification"),
    ("screenshot-2.png", (1280, 900), "WordPress admin settings"),
    ("screenshot-3.png", (1440, 1040), "Approximate time on a dark storefront"),
    ("screenshot-4.png", (390, 900), "Mobile storefront"),
)
REDACTIONS = {
    "banner-772x250.png": (660, 188, 746, 202),
    "icon-128x128.png": (49, 46, 104, 57),
    "icon-256x256.png": (96, 92, 209, 115),
    "screenshot-1.png": (1415, 716, 1575, 740),
    "screenshot-2.png": (980, 690, 1155, 714),
}
INK = "#16372d"
MUTED = "#4e665a"
PAPER = "#f2f4eb"


def digest(file):
    return hashlib.sha256(file.read_bytes()).hexdigest()


def font(size, bold=False):
    names = ("segoeuib.ttf", "DejaVuSans-Bold.ttf") if bold else ("segoeui.ttf", "DejaVuSans.ttf")
    for name in names:
        for parent in (Path("C:/Windows/Fonts"), Path("/usr/share/fonts/truetype/dejavu")):
            candidate = parent / name
            if candidate.is_file():
                return ImageFont.truetype(str(candidate), size)
    return ImageFont.load_default(size=size)


def frame(board, source, x, y, width, height, crop=None):
    img = Image.open(source).convert("RGBA")
    if crop:
        ImageDraw.Draw(img).rectangle(crop, fill="#e8ebe3")
    thumbnail = ImageOps.contain(img, (min(img.width, width - 20), min(img.height, height - 20)), Image.Resampling.LANCZOS)
    ImageDraw.Draw(board).rounded_rectangle(
        (x, y, x + width, y + height), radius=14, fill="#ffffff", outline="#d1dad0", width=2
    )
    board.paste(thumbnail, (x + (width - thumbnail.width) // 2, y + (height - thumbnail.height) // 2), thumbnail)


def label(board, xy, text, size=22, bold=False, color=INK):
    ImageDraw.Draw(board).text(xy, text, font=font(size, bold), fill=color)


def header(board, headline, description):
    label(board, (54, 42), headline, 44, True)
    label(board, (57, 105), description, 20, color=MUTED)
    ImageDraw.Draw(board).line((55, 146, board.width - 55, 146), fill="#93aa96", width=2)


def build_comparison(old):
    board = Image.new("RGB", (1650, 2420), PAPER)
    header(board, "OLD vs NEW", "Recently Bought This for WooCommerce  ·  WordPress.org")
    label(board, (54, 166), "0.4.0   /   dotychczasowe pliki SVN", 23, True)
    label(board, (847, 166), "1.0.0   /   kandydat wydania", 23, True)
    pairs = (
        ("Baner", "banner-772x250.png", "banner-772x250.png", 254),
        ("Ikona · podgląd 128 px", "icon-128x128.png", "icon-128x128.png", 159),
        ("Witryna sklepu", "screenshot-1.png", "screenshot-1.png", 350),
        ("Panel ustawień", "screenshot-3.png", "screenshot-2.png", 350),
        ("Powiadomienie na witrynie / przybliżony czas", "screenshot-2.png", "screenshot-3.png", 350),
    )
    y = 210
    for heading, before, after, height in pairs:
        label(board, (54, y), heading, 19, True)
        y += 31
        frame(board, old / before, 54, y, 750, height, REDACTIONS.get(before))
        frame(board, CATALOG / after, 847, y, 750, height)
        y += height + 19
    label(board, (54, y + 8), "Dotychczasowe grafiki · zakryto pola z imionami", 19, color=MUTED)
    label(board, (847, y + 8), "Dodatkowy zrzut mobilny · 390 × 900", 19, True)
    frame(board, CATALOG / "screenshot-4.png", 847, y + 42, 750, 410)
    board.save(RELEASE / "old-vs-new.png", optimize=True)


def build_new_board():
    board = Image.new("RGB", (1650, 1740), PAPER)
    header(board, "Nowy zestaw graficzny", "Recently Bought This for WooCommerce  ·  1.0.0")
    label(board, (54, 169), "Baner standardowy · 772 × 250", 20, True)
    label(board, (847, 169), "Baner Retina · 1544 × 500", 20, True)
    frame(board, CATALOG / "banner-772x250.png", 54, 201, 750, 250)
    frame(board, CATALOG / "banner-1544x500.png", 847, 201, 750, 250)
    for x, res in [(54, 128), (847, 256)]:
        label(board, (x, 480), f"Ikona · {res} × {res}", 20, True)
        frame(board, CATALOG / f"icon-{res}x{res}.png", x, 512, 750, 274)
    for index, heading in enumerate(("Witryna sklepu", "Ustawienia", "Przybliżony czas", "Widok mobilny")):
        x = [54, 847][index % 2]
        y = 810 + (index // 2) * 450
        label(board, (x, y), heading, 20, True)
        frame(board, CATALOG / f"screenshot-{index + 1}.png", x, y + 35, 750, 400)
    board.save(RELEASE / "new-catalog-set.png", optimize=True)


def catalog_manifest():
    result = []
    assert {file.name for file in CATALOG.iterdir()} == {name for name, _, _ in FILES}
    for name, dimensions, use in FILES:
        image_path = CATALOG / name
        with Image.open(image_path) as image:
            assert image.format == "PNG" and image.size == dimensions, image_path
            image.verify()
        with Image.open(image_path) as image:
            frame_image = image.convert("RGBA" if "A" in image.getbands() else "RGB")
            clean = Image.frombytes(frame_image.mode, frame_image.size, frame_image.tobytes())
            clean.save(image_path, format="PNG", optimize=True)
        with Image.open(image_path) as image:
            assert not image.text and not image.getexif() and "icc_profile" not in image.info, image_path
        limit = 1_000_000 if name.startswith("icon") else 4_000_000 if name.startswith("banner") else 10_000_000
        size = image_path.stat().st_size
        assert size <= limit
        result.append({
            "file": name, "width": dimensions[0], "height": dimensions[1],
            "mime_type": "image/png", "bytes": size, "sha256": digest(image_path),
            "purpose": use, "svn_path": "assets/" + name
        })
    return result


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("old_assets", type=Path)
    parser.add_argument("zip_path", type=Path)
    args = parser.parse_args()
    catalog = catalog_manifest()
    build_comparison(args.old_assets)
    build_new_board()

    with ZipFile(args.zip_path) as archive:
        assert archive.testzip() is None
        zip_files = archive.namelist()
        runtime = [name.split("/", 1)[1] for name in zip_files]
    manifest = {
        "version": "1.0.0",
        "slug": "recently-bought-this-for-woocommerce",
        "package": {
            "file": args.zip_path.name,
            "bytes": args.zip_path.stat().st_size,
            "sha256": digest(args.zip_path),
            "files": zip_files
        },
        "svn": {
            "trunk/": ["trunk/" + name for name in runtime],
            "tags/1.0.0/": ["tags/1.0.0/" + name for name in runtime],
            "assets/": catalog
        },
    }
    (RELEASE / "manifest.json").write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8")
    for entry in catalog:
        print(f"{entry['file']}: {entry['width']} x {entry['height']}, {entry['bytes']} bytes")


if __name__ == "__main__":
    main()
