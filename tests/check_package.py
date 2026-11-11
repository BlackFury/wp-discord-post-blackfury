"""Inspect the actual distribution archive, not merely the build configuration."""
from pathlib import Path
import hashlib
import re
import zipfile

root = Path(__file__).resolve().parents[1]
archive = root / "dist/wp-discord-post-blackfury-2.1.1.zip"
expected = {
    "wp-discord-post.php", "readme.txt", "LICENSE", "README.md", "CHANGELOG.md", "SECURITY.md", "CONTRIBUTING.md",
    "includes/class-wp-discord-post-admin.php", "includes/class-wp-discord-post-contact-form-7.php",
    "includes/class-wp-discord-post-dank-meme.php", "includes/class-wp-discord-post-formatting.php",
    "includes/class-wp-discord-post-gravityforms.php", "includes/class-wp-discord-post-http.php",
    "includes/class-wp-discord-post-jetpack-contact-form.php", "includes/class-wp-discord-post-post.php",
    "includes/class-wp-discord-post-woocommerce.php", "includes/functions-general.php",
    "docs/DEPLOYMENT.md", "docs/AUDIT.md", "docs/VALIDATION.md", "docs/UPSTREAM.md", "docs/PUBLISHING.md", "docs/upstream-sha256.json",
}
with zipfile.ZipFile(archive) as package:
    names = package.namelist()
    assert len(names) == len(set(names)), "Duplicate ZIP paths"
    assert set(names) == {"wp-discord-post/" + name for name in expected}, "Wrong package contents/root"
    for name in expected:
        data = package.read("wp-discord-post/" + name)
        assert data == (root / name).read_bytes().replace(b"\r\n", b"\n"), "Stale packaged file: " + name
        assert not re.search(rb"https://(?:canary\.|ptb\.)?discord(?:app)?\.com/api(?:/v\d+)?/webhooks/\d+/[A-Za-z0-9_-]{20,}", data), "Webhook credential in package"
    header = package.read("wp-discord-post/wp-discord-post.php")
    assert b"Version:     2.1.1" in header
    assert b"Update URI:  false" in header
    assert b"Nicola Mustone" in header and b"GPL-2.0+" in header
assert hashlib.sha256(archive.read_bytes()).hexdigest() == archive.with_suffix(".zip.sha256").read_text().split()[0], "Checksum mismatch"
print(f"PASS package: {len(expected)} files, correct plugin path, source equality, license and SHA256")
