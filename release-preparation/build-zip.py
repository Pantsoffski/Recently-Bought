"""Build the reproducible installation ZIP for owner review."""

import argparse
import hashlib
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile, ZipInfo


SLUG = "recently-bought-this-for-woocommerce"
ROOT = Path(__file__).resolve().parents[1]
FILES = (
    "_inc/recently-bought-style.css",
    "_inc/recently-bought.js",
    "classes.php",
    f"languages/{SLUG}-pl_PL.l10n.php",
    "pp-recently-bought-for-woocommerce.php",
    "readme.txt",
    "views/options.php",
)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("zip_path", type=Path)
    archive_path = parser.parse_args().zip_path
    manifest = {}
    with ZipFile(archive_path, "w", compression=ZIP_DEFLATED, compresslevel=9) as archive:
        for relative_path in FILES:
            content = (ROOT / relative_path).read_bytes().replace(b"\r\n", b"\n")
            name = f"{SLUG}/{relative_path}"
            entry = ZipInfo(name)
            entry.create_system = 3
            entry.external_attr = 0o100644 << 16
            archive.writestr(entry, content, compress_type=ZIP_DEFLATED, compresslevel=9)
            manifest[name] = content

    with ZipFile(archive_path) as archive:
        assert archive.testzip() is None
        assert archive.namelist() == list(manifest)
        for name, content in manifest.items():
            assert archive.read(name) == content
    payload = archive_path.read_bytes()
    print(f"{archive_path} | {len(payload)} bytes | SHA-256 {hashlib.sha256(payload).hexdigest()}")


if __name__ == "__main__":
    main()
