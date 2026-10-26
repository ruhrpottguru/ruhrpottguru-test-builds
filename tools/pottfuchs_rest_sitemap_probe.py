#!/usr/bin/env python3
"""Read-only public PottFuchs site-move REST+SEO checks (no credentials)."""
import json
import urllib.request
import urllib.error
import urllib.parse
import hashlib
import xml.etree.ElementTree as ET
from datetime import datetime,timezone
from pathlib import Path

BASE="https://www.pottfuchs.de"
LEGACY="https://www.ruhrpottguru.de"
ALLOWED_PATH="/wp-json/ruhrpottguru/v1/global-search"
QUERIES=("topgolf","topgolf oberhausen","top golf oberhausen","zzqkeintreffer987")
USER_AGENT="PottFuchs-ReadOnlyMigrationCheck/1.0 (+public-smoke)"

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,req,fp,code,msg,headers,newurl):
        return None

def request(url, data=None, content_type=None, limit=2500000):
    request=urllib.request.Request(url,data=data,
        headers={"User-Agent":USER_AGENT,**({"Content-Type":content_type} if content_type else {})},
        method="POST" if data is not None else "GET")
    try:
        r=urllib.request.build_opener(NoRedirect).open(request,timeout=18)
    except urllib.error.HTTPError as e:
        r=e
    except (urllib.error.URLError,TimeoutError,OSError) as e:
        return {"url":url,"method":request.get_method(),"error":str(e)[:260]}
    with r:
        raw=r.read(limit+1)
        return {"url":url,"method":request.get_method(),"status":r.status,
            "location":r.headers.get("Location"),
            "content_type":r.headers.get("Content-Type",""),
            "bytes_read":len(raw),
            "truncated":len(raw)>limit,
            "text":raw[:limit].decode("utf-8","replace")}

def search_summary(raw):
    try:
        j=json.loads(raw)
    except (ValueError,TypeError) as e:
        return {"error":"invalid_json"}
    if not isinstance(j,dict):
        return {"error":"unexpected_json_shape"}
    if j.get("code") and "groups" not in j:
        return {"api_error_code":j.get("code"),"message":str(j.get("message",""))[:170]}
    groups=j.get("groups",{})
    if not isinstance(groups,dict):
        return {"error":"unexpected_groups_shape","total":j.get("total")}
    out={}
    for name,items in groups.items():
        if not isinstance(items,list):
            out[str(name)]={"error":"not_a_list"}
            continue
        sample=[]
        for item in items[:12]:
            if not isinstance(item,dict):continue
            obj=item.get("item") or item.get("element") or item
            if not isinstance(obj,dict):obj=item
            sample.append({
                "id":str(obj.get("id",item.get("id","")))[:100],
                "title":str(obj.get("title",obj.get("name",item.get("title",""))))[:120],
                "city":str(obj.get("city",item.get("city","")))[:80]
            })
        out[str(name)]={"count":len(items),"sample":sample}
    return {"total":j.get("total"),"groups":out}

def sitemap_summary(body, original):
    try:
        root=ET.fromstring(body)
    except ET.ParseError as e:
        return {"error":"xml_parse_error "+str(e)[:100]}
    urls=[]
    for elem in root.iter():
        if str(elem.tag).endswith("}loc") or elem.tag=="loc":
            if elem.text:urls.append(elem.text.strip())
    from collections import Counter
    hosts=Counter(urllib.parse.urlparse(x).netloc for x in urls)
    return {"type":"index" if str(root.tag).endswith("sitemapindex") else "urlset",
            "loc_count":len(urls),"hosts":dict(hosts),
            "sample":urls[:4],
            "non_pottfuchs_loc_samples":[x for x in urls if urllib.parse.urlparse(x).netloc not in ("www.pottfuchs.de","pottfuchs.de")][:12]}

def main():
    report={"timestamp":datetime.now(timezone.utc).isoformat(),"read_only":True,
        "note":"Strictly public read-only search POST endpoint; no admin, no import, no mutation APIs.",
        "post_checks":[],"seo_sitemaps":[],"warnings":[]}
    for host in (BASE,LEGACY):
        for query in QUERIES:
            body=json.dumps({"q":query,"limit":30},ensure_ascii=False).encode("utf-8")
            r=request(host+ALLOWED_PATH,data=body,content_type="application/json")
            summary=search_summary(r.get("text","")) if r.get("status")==200 else {}
            row={k:v for k,v in r.items() if k!="text"}
            row["query"]=query; row["summary"]=summary
            report["post_checks"].append(row)
            if r.get("status")!=200:
                report["warnings"].append(f"API_POST {host} '{query}': HTTP {r.get('status','ERROR')} redirect={r.get('location')}")
            elif "total" not in summary:
                report["warnings"].append(f"API_POST {host} '{query}': unrecognized JSON response")
    for file in ("/robots.txt","/wp-sitemap.xml","/rpg-sitemap.xml"):
        r=request(BASE+file)
        summary=({} if r.get("status")!=200 else
                 {"sitemap":sitemap_summary(r.get("text",""),BASE+file)} if file.endswith(".xml")
                  else {"robots_excerpt":r.get("text","")[:1800]})
        report["seo_sitemaps"].append({**{k:v for k,v in r.items() if k!="text"},**summary})
        if r.get("status")!=200:report["warnings"].append(f"RESOURCE {file} HTTP {r.get('status','ERROR')}")
        elif summary.get("sitemap",{}).get("non_pottfuchs_loc_samples"):
            report["warnings"].append(f"NON_CANONICAL_SITEMAP_LOCS {file}")
    out=Path("audit/rest/pottfuchs-rest-sitemap.json")
    out.parent.mkdir(parents=True,exist_ok=True)
    out.write_text(json.dumps(report,ensure_ascii=False,indent=2)+"\n",encoding="utf-8")
    print("PUBLIC REST + SITEMAP INSPECTION",report["timestamp"])
    for r in report["post_checks"]:
        print("POST",r["url"],"q=",r["query"],"HTTP",r.get("status"),
              "total",r.get("summary",{}).get("total"),"groups",[(k,v["count"]) for k,v in r.get("summary",{}).get("groups",{}).items() if "count" in v],
              "redirect",r.get("location"))
    for r in report["seo_sitemaps"]:
        print("SEO",r["url"],"HTTP",r.get("status"),"summary",json.dumps(r.get("sitemap",r.get("robots_excerpt","")),ensure_ascii=False)[:1600])
    print("WARNINGS",len(report["warnings"]))
    for msg in report["warnings"]:print("-",msg)
    print("Full evidence",out)
    return 0

if __name__=="__main__":raise SystemExit(main())
