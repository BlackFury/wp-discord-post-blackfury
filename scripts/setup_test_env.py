"""Download pinned official dependencies into a disposable, unserved directory."""
from pathlib import Path
import hashlib
import io
import shutil
import urllib.request
import zipfile

ROOT = Path(__file__).resolve().parents[1]
BASE = ROOT / ".test-env"
if BASE.exists():
    raise SystemExit(".test-env already exists; reuse it or select a fresh checkout. Nothing removed.")

def unpack(url, digest, target):
    data = urllib.request.urlopen(url, timeout=90).read()
    if hashlib.sha256(data).hexdigest() != digest:
        raise RuntimeError("Dependency checksum mismatch: " + url)
    with zipfile.ZipFile(io.BytesIO(data)) as archive:
        for name in archive.namelist():
            candidate = (target / name).resolve()
            try:
                candidate.relative_to(target.resolve())
            except ValueError:
                raise RuntimeError("Unsafe archive path")
        archive.extractall(target)

unpack("https://downloads.wordpress.org/release/wordpress-7.1.4.zip", "a147146c159362778ecbbbfb07bb20ddf4e85b130f971c824e656a8f938c5f87", BASE)
wp = BASE / "wordpress"
unpack("https://downloads.wordpress.org/plugin/sqlite-database-integration.3.1.3.zip", "f6ce35e8419d0b9326221a907627d905e25c2a5ba2afc86492811f14c851efe0", wp / "wp-content/plugins")
shutil.copyfile(wp / "wp-content/plugins/sqlite-database-integration/db.copy", wp / "wp-content/db.php")
(wp / "wp-config.php").write_text("""<?php
// Disposable synthetic environment, never serve or deploy this directory.
define('DB_NAME', 'synthetic');
define('DB_USER', 'synthetic');
define('DB_PASSWORD', 'synthetic');
define('DB_HOST', 'localhost');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');
define('WP_HOME', 'http://localhost');
define('WP_SITEURL', 'http://localhost');
define('WP_DEBUG', true);
define('WP_HTTP_BLOCK_EXTERNAL', true);
define('DISABLE_WP_CRON', true);
define('AUTOMATIC_UPDATER_DISABLED', true);
define('WP_ENVIRONMENT_TYPE', 'local');
$table_prefix = 'bf_test_';
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
require_once ABSPATH . 'wp-settings.php';
""", encoding="utf-8")
(wp / ".blackfury-test-only").write_text("Disposable local test environment\n")
print("WP_TEST_ROOT=" + str(wp))
print("Next: php tests/install.php && php tests/run.php")
