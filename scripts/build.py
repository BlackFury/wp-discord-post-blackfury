"""Build a deterministic WordPress-installable ZIP using an explicit allowlist."""
from pathlib import Path
import hashlib
import re
import zipfile

ROOT = Path(__file__).resolve().parents[1]
version = re.search(r"\* Version:\s+(\S+)", (ROOT / "blackfury-relay.php").read_text()).group(1)
major, minor, *_ = map(int, re.match(r"\d+\.\d+\.\d+", version).group().split("."))
if (major, minor) >= (2, 5) and (ROOT / "includes/class-blackfury-relay-migration.php").exists():
    raise SystemExit("BlackFury Relay 2.5: retire the WP Discord Post importer and update the migration guide before releasing.")
files = [ROOT / name for name in ("blackfury-relay.php", "readme.txt", "LICENSE", "README.md", "CHANGELOG.md", "SECURITY.md", "CONTRIBUTING.md")]
files += sorted((ROOT / "includes").glob("*.php"))
files += [ROOT / "assets/relay.css", ROOT / "assets/relay-editor.js"]
for name in ("DEPLOYMENT.md", "AUDIT.md", "VALIDATION.md", "UPSTREAM.md", "PUBLISHING.md", "RELAY.md", "upstream-sha256.json"):
    files.append(ROOT / "docs" / name)
for suffix in ("*.mo", "*.po", "*.pot"):
    files += sorted((ROOT / "languages").glob(suffix))
destination = ROOT / "dist"
destination.mkdir(exist_ok=True)
archive = destination / f"blackfury-relay-{version}.zip"
with zipfile.ZipFile(archive, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as output:
    for source in sorted(files):
        info = zipfile.ZipInfo("blackfury-relay/" + source.relative_to(ROOT).as_posix(), date_time=(2026, 11, 11, 0, 0, 0))
        info.compress_type = zipfile.ZIP_DEFLATED
        info.create_system = 3
        info.external_attr = 0o100644 << 16
        # Normalize text for identical Windows/Linux archives; .mo remains binary.
        data = source.read_bytes()
        if source.suffix != ".mo":
            data = data.replace(b"\r\n", b"\n")
        output.writestr(info, data, compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
digest = hashlib.sha256(archive.read_bytes()).hexdigest()
archive.with_suffix(".zip.sha256").write_text(f"{digest}  {archive.name}\n", encoding="ascii")
print(f"{archive.name}: {len(files)} files, SHA256 {digest}")
