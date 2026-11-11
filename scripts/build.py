"""Build a deterministic WordPress-installable ZIP using an explicit allowlist."""
from pathlib import Path
import hashlib
import re
import zipfile

ROOT = Path(__file__).resolve().parents[1]
version = re.search(r"\* Version:\s+([0-9.]+)", (ROOT / "wp-discord-post.php").read_text()).group(1)
files = [ROOT / name for name in ("wp-discord-post.php", "readme.txt", "LICENSE", "README.md", "CHANGELOG.md", "SECURITY.md", "CONTRIBUTING.md")]
files += sorted((ROOT / "includes").glob("*.php"))
for name in ("DEPLOYMENT.md", "AUDIT.md", "VALIDATION.md", "UPSTREAM.md", "PUBLISHING.md", "upstream-sha256.json"):
    files.append(ROOT / "docs" / name)
for suffix in ("*.mo", "*.po", "*.pot"):
    files += sorted((ROOT / "languages").glob(suffix))
destination = ROOT / "dist"
destination.mkdir(exist_ok=True)
archive = destination / f"wp-discord-post-blackfury-{version}.zip"
with zipfile.ZipFile(archive, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as output:
    for source in sorted(files):
        info = zipfile.ZipInfo("wp-discord-post/" + source.relative_to(ROOT).as_posix(), date_time=(2026, 11, 11, 0, 0, 0))
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
