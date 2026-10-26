#!/usr/bin/env python3
"""P0 post-activation cache bypass read-only public HTML comparison.
Only GETs, no database writes, no access tokens or WordPress administration."""
import datetime as dt,json,sys,urllib.request,urllib.parse,hashlib
from pathlib import Path
sys.path.insert(0,str(Path(__file__).parent))
from pottfuchs_public_audit import parse_seo

base="https://www.pottfuchs.de"
paths=["/","/veranstaltungen/","/food/","/ort/16936/essen/zum-schwarzen/","/veranstaltung/6936/essen/77-essener-lichtwochen/"]
stamp=dt.datetime.now(dt.timezone.utc).strftime("%Y%m%dT%H%M%S")
result={"at_utc":dt.datetime.now(dt.timezone.utc).isoformat(),"read_only":True,"checks":[]}
for path in paths:
  for variant in ("ordinary","query-bypass","request-no-cache"):
    url=base+path
    headers={"User-Agent":"PottFuchs-P0-PostActivation-ReadOnly/1.0"}
    if variant=="query-bypass":
      url += "?pf_p0_readonly="+stamp
    if variant=="request-no-cache":
      headers["Cache-Control"]="no-cache"
      headers["Pragma"]="no-cache"
    try:
      with urllib.request.urlopen(urllib.request.Request(url,headers=headers),timeout=24) as r:
        raw=r.read(2500000)
        h=r.headers
        summary=parse_seo(raw.decode("utf-8","replace"))
        item={"path":path,"variant":variant,"http":r.status,
              "final_url":r.url,"html_sha256":hashlib.sha256(raw).hexdigest(),
              "title":summary["title"],"og_site_name":summary["og_site_name"],
              "brand_schema":summary["brand_schema"],"canonical":summary["canonical"],
              "cache_headers":{k:h.get(k) for k in ("Age","Cache-Control","X-Cache","CF-Cache-Status","X-LiteSpeed-Cache","ETag","Last-Modified") if h.get(k)}}
    except Exception as e:item={"path":path,"variant":variant,"error":str(e)[:280]}
    result["checks"].append(item)
    print("P0_LIVE",json.dumps(item,ensure_ascii=False))
out=Path("audit/p0-post-activation/cache-compare.json");out.parent.mkdir(parents=True,exist_ok=True)
out.write_text(json.dumps(result,ensure_ascii=False,indent=2)+"\n",encoding="utf-8")
