"""Prepare an artificial future ZIP for the end-to-end WordPress upgrader test."""
from pathlib import Path
import re
import sys
import zipfile

archive = Path(sys.argv[1])
out = Path(sys.argv[2])
out.parent.mkdir(parents=True, exist_ok=True)
with zipfile.ZipFile(archive) as source, zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED) as target:
    for name in source.namelist():
        data = source.read(name)
        if name == "blackfury-relay/blackfury-relay.php":
            data = re.sub(rb"(?m)^( \* Version:\s+)\S+", rb"\g<1>2.4.99", data, count=1)
        if name == "blackfury-relay/readme.txt":
            data = re.sub(rb"(?m)^Stable tag:\s+\S+", b"Stable tag: 2.4.99", data, count=1)
        target.writestr(name, data)
