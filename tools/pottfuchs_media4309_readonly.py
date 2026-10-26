#!/usr/bin/env python3
"""Read-only verification of existing (not newly created) WordPress media ID 4309.
Source URL derived from WordPress' connected media-library read-only metadata.
No WordPress REST writes, no uploads, no hosting/database mutations.
"""
from datetime import datetime, timezone
from urllib.request import urlopen, Request
from pathlib import Path
import hashlib, json, struct

URL="https://www.pottfuchs.de/wp-content/uploads/2026/10/PottFuchs-Favicon-512x512-1.png"
DOCUMENTED_APPROVED_SVG_PNG_SHA256="e0df54cd75052526ff5ac26cc16296b10985ab6da630f41d69c87cba086a9ca9"
out=Path("audit/media4309");out.mkdir(parents=True,exist_ok=True)
with urlopen(Request(URL,headers={"User-Agent":"PottFuchsReadOnlyFaviconAssetAudit/1.0"}),timeout=20) as r:
    mime=r.headers.get("Content-Type","")
    raw=r.read(1000000)
if not raw.startswith(b"\x89PNG\r\n\x1a\n"):
    raise ValueError("Existing media 4309 is not a valid PNG file")
w,h=struct.unpack_from(">II",raw,16)
sha=hashlib.sha256(raw).hexdigest()
info={"utc":datetime.now(timezone.utc).isoformat(),
      "source":"Live public WordPress media ID 4309 (read-only)",
      "url":URL, "content_type":mime, "dimensions":[w,h],
      "bytes":len(raw), "sha256":sha,
      "equals_approved_vector_png_bytes":sha==DOCUMENTED_APPROVED_SVG_PNG_SHA256}
if w!=512 or h!=512:raise ValueError(f"Favicon has dimensions {w}x{h}, not 512x512")
(out/"existing-4309.png").write_bytes(raw)
(out/"result.json").write_text(json.dumps(info,indent=2,ensure_ascii=False)+"\n",encoding="utf-8")
print("POTTFUCHS_EXISTING_FAVICON_MEDIA",json.dumps(info,ensure_ascii=False))
