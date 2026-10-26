#!/usr/bin/env python3
"""PottFuchs public READ-ONLY HTTP/SEO smoke audit. No WordPress credentials or writes."""
import datetime as dt
import hashlib
import json
import re
from html.parser import HTMLParser
from pathlib import Path
from urllib.error import HTTPError, URLError
from urllib.parse import urljoin, urlparse
from urllib.request import HTTPRedirectHandler, Request, build_opener

HOST = "www.pottfuchs.de"
MAX_HOPS = 8
LIMIT = 2500000

class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None

def get(url):
    opener = build_opener(NoRedirect())
    seen, chain = set(), []
    for _ in range(MAX_HOPS + 1):
        if url in seen:
            return {"chain": chain, "error": "redirect loop", "url": url}
        seen.add(url)
        try:
            response = opener.open(Request(url, headers={"User-Agent":"PottFuchsReadOnlyAudit/1.0"}), timeout=15)
        except HTTPError as exc:
            response = exc
        except (URLError, TimeoutError, OSError) as exc:
            return {"chain": chain, "error": str(exc)[:220], "url": url}
        with response as r:
            status = r.status
            location = r.headers.get("Location")
            chain.append({"url": url, "status": status, "location": location})
            if status in (301,302,303,307,308) and location:
                url = urljoin(url,location)
                continue
            data = r.read(LIMIT+1)
            return {"chain":chain, "url":url, "status":status,
                    "truncated":len(data)>LIMIT,
                    "sha256":hashlib.sha256(data).hexdigest(),
                    "body":data[:LIMIT].decode("utf-8","replace")}
    return {"chain":chain, "error":"too many redirects", "url":url}

class SEO(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.title = ""
        self.intitle = False
        self.h1 = 0
        self.meta = []
        self.links = []
        self.inschema = False
        self.schema = []
    def handle_starttag(self,tag,attrs):
        a = dict(attrs)
        if tag=="title": self.intitle=True
        if tag=="h1": self.h1+=1
        if tag=="meta": self.meta.append(a)
        if tag=="link": self.links.append(a)
        if tag=="script" and a.get("type","").lower()=="application/ld+json":
            self.inschema=True
            self.schema.append("")
    def handle_endtag(self,tag):
        if tag=="title": self.intitle=False
        if tag=="script": self.inschema=False
    def handle_data(self,data):
        if self.intitle: self.title+=data
        if self.inschema and self.schema: self.schema[-1]+=data

def parse_seo(html):
    p=SEO()
    p.feed(html)
    canon=[x.get("href") for x in p.links if "canonical" in x.get("rel","").lower().split()]
    icons=[x.get("href") for x in p.links if "icon" in x.get("rel","").lower().split()]
    descriptions=[x.get("content") for x in p.meta if x.get("name","").lower()=="description"]
    og=[x.get("content") for x in p.meta if x.get("property","").lower()=="og:site_name"]
    brand=[]
    types=set()
    for raw in p.schema:
        try: stack=[json.loads(raw)]
        except ValueError: continue
        while stack:
            obj=stack.pop()
            if isinstance(obj,list): stack.extend(obj)
            if isinstance(obj,dict):
                ts=obj.get("@type",[])
                if isinstance(ts,str): ts=[ts]
                if isinstance(ts,list):
                    types.update(str(v) for v in ts)
                    if any(x in ("WebSite","Organization") for x in ts):
                        brand.append({"type":ts, "name":obj.get("name")})
                stack.extend(v for v in obj.values() if isinstance(v,(dict,list)))
    return {"title":p.title.strip(),"h1":p.h1,"canonical":canon,"descriptions":descriptions,
            "icons":icons,"og_site_name":og,"brand_schema":brand,"schema_types":sorted(types)}

def is_compatibility_endpoint(url):
    # Cutover runbook v1.0 §3.2: preserve API/App clients on the old host.
    # Their POST redirects are not interchangeable with a public-page 301.
    return urlparse(url).path.startswith("/wp-json/")

def clean(x):
    return {k:v for k,v in x.items() if k!="body"}

def main():
    report={"at_utc":dt.datetime.now(dt.timezone.utc).isoformat(),
            "method":"Public HTTP GET + TLS verification + HTML smoke test; no browser/Google indexing verification",
            "redirects":[],"pages":[],"resources":[],"issues":[]}
    roots=["http://pottfuchs.de/","https://pottfuchs.de/","http://www.pottfuchs.de/",
           "https://www.pottfuchs.de/","http://ruhrpottguru.de/","https://ruhrpottguru.de/",
           "http://www.ruhrpottguru.de/","https://www.ruhrpottguru.de/",
           "https://www.ruhrpottguru.de/impressum/",
           "https://www.ruhrpottguru.de/ort/16936/essen/zum-schwarzen/",
           "https://www.ruhrpottguru.de/veranstaltungen/",
           "https://www.ruhrpottguru.de/food/",
           "https://www.ruhrpottguru.de/stadt/wesel/sehenswuerdigkeiten/",
           "https://www.ruhrpottguru.de/halloween-im-pott/",
           "https://www.ruhrpottguru.de/wp-json/",
           "https://www.ruhrpottguru.de/veranstaltungen/?place=wesel&radius=10",
           "https://www.ruhrpottguru.de/ort/16936/essen/zum-schwarzen/?utm_source=oldbrand"]
    for url in roots:
        x=get(url)
        old="ruhrpottguru.de" in url
        expected=urlparse(url).path
        final=urlparse(x["url"])
        issues=[]
        is_api=is_compatibility_endpoint(url)
        if x.get("error"): issues.append("REQUEST_ERROR "+x["error"])
        else:
            if x.get("status")!=200: issues.append("FINAL_STATUS_"+str(x["status"]))
            if final.path!=expected: issues.append("PATH_CHANGED")
            if final.query!=urlparse(url).query: issues.append("QUERY_CHANGED")
            if is_api:
                # A deliberate old-host REST API exception is not a broken
                # public-page redirect. This is a GET-only reachability probe.
                if final.scheme!="https": issues.append("REST_HTTPS_REQUIRED")
                if final.hostname not in (HOST,"www.ruhrpottguru.de","ruhrpottguru.de"):
                    issues.append("REST_WRONG_HOST")
            else:
                if final.hostname!=HOST or final.scheme!="https": issues.append("NOT_CANONICAL_HOST")
                if old and (not x["chain"] or x["chain"][0]["status"]!=301): issues.append("OLD_DOMAIN_FIRST_HOP_NOT_301")
        report["redirects"].append({"start":url, **clean(x),
                                    "legacy_rest_compatibility_exception":is_api,
                                    "requires_post_contract_test":is_api, "issues":issues})
        report["issues"].extend("REDIRECT "+url+": "+i for i in issues)
    pages=[
        "https://www.pottfuchs.de/",
        "https://www.pottfuchs.de/ort/16936/essen/zum-schwarzen/",
        "https://www.pottfuchs.de/ort/10523/wesel/weseler-wirtshaus/",
        "https://www.pottfuchs.de/veranstaltung/6936/essen/77-essener-lichtwochen/",
        "https://www.pottfuchs.de/veranstaltungen/",
        "https://www.pottfuchs.de/food/",
        "https://www.pottfuchs.de/highlights/",
        "https://www.pottfuchs.de/kultur/",
        "https://www.pottfuchs.de/aktiv/",
        "https://www.pottfuchs.de/datenschutzerklaerung/",
        "https://www.pottfuchs.de/impressum/",
        "https://www.pottfuchs.de/zeitreise-von-oben/",
        "https://www.pottfuchs.de/stadt/wesel/sehenswuerdigkeiten/"]
    icons=set()
    for url in pages:
        x=get(url)
        seo=parse_seo(x.get("body","")) if x.get("status")==200 else {}
        issues=[]
        if x.get("error"): issues.append("REQUEST_ERROR "+x["error"])
        elif x.get("status")!=200: issues.append("HTTP_"+str(x["status"]))
        else:
            if len(seo["canonical"])>1: issues.append("DUPLICATE_CANONICAL")
            if len(seo["descriptions"])>1: issues.append("DUPLICATE_META_DESCRIPTION")
            if any(urlparse(c or "").hostname not in (None,HOST) for c in seo["canonical"]): issues.append("OLD_OR_FOREIGN_CANONICAL")
            if re.search("RuhrpottGuru",seo["title"],re.I): issues.append("OLD_BRAND_TITLE")
            if any(re.search("RuhrpottGuru",str(name or ""),re.I) for name in seo["og_site_name"]): issues.append("OLD_BRAND_OG_SITE_NAME")
            if any(re.search("RuhrpottGuru",str(b.get("name","")),re.I) for b in seo["brand_schema"]): issues.append("OLD_BRAND_SCHEMA")
            if "RuhrpottGuru-Standort" in x.get("body",""): issues.append("OLD_BRAND_VISIBLE_TIME_TRAVEL")
            if "/ort/" in url or "/veranstaltung/" in url:
                if len(seo["canonical"])!=1: issues.append("DETAIL_CANONICAL_COUNT")
                if len(seo["descriptions"])!=1: issues.append("DETAIL_DESCRIPTION_COUNT")
                if seo["h1"]!=1: issues.append("DETAIL_H1_COUNT")
            icons.update(urljoin(url,i) for i in seo["icons"] if i)
        report["pages"].append({"start":url, **clean(x),"seo":seo,"issues":issues})
        report["issues"].extend("PAGE "+url+": "+i for i in issues)
    icons.add("https://www.pottfuchs.de/wp-content/themes/ruhrpott-guru/favicon.svg")
    for url in sorted(icons):
        x=get(url)
        issues=[]
        if x.get("error") or x.get("status")!=200: issues.append("ICON_UNREACHABLE")
        # Project decision 23.10.: the existing orange mining-tower SVG is the APPROVED favicon.
        # Its presence is a pass/identity marker, not a branding defect.
        intended_tower="<!-- Main A-frame tower -->" in x.get("body","")
        report["resources"].append({"start":url,**clean(x),"approved_tower_favicon_present":intended_tower,"issues":issues})
        report["issues"].extend("ICON "+url+": "+i for i in issues)
    for url in ("https://www.pottfuchs.de/robots.txt","https://www.pottfuchs.de/wp-sitemap.xml",
                "https://www.pottfuchs.de/rpg-sitemap.xml"):
        x=get(url)
        issues=[]
        if x.get("error") or x.get("status")!=200: issues.append("RESOURCE_UNREACHABLE")
        if "sitemap" in url and re.search(r"<loc>\s*https?://(?:www\.)?ruhrpottguru\.de",x.get("body",""),re.I):
            issues.append("OLD_DOMAIN_SITEMAP_SAMPLE")
        report["resources"].append({"start":url,**clean(x),"issues":issues})
        report["issues"].extend("RESOURCE "+url+": "+i for i in issues)
    # Preserve existing public brand/favicons byte-for-byte for visual asset comparison.
    # Only READ public media; do not create, crop, replace or upload a WordPress icon.
    from urllib.request import urlopen
    asset_paths = {
        "pottfuchs-logo-2026.png": "/wp-content/uploads/2026/10/cropped-Pottfuchs_Web_Transparent.png",
        "existing-favicon-2021-a.png": "/wp-content/uploads/2021/07/cropped-favicon-1.png",
        "existing-favicon-2021-b.png": "/wp-content/uploads/2021/07/favicon.png",
        "existing-favicon-2021-c.png": "/wp-content/uploads/2021/07/cropped-favicon.png"
    }
    report["asset_samples"]=[]
    for filename, path in asset_paths.items():
        try:
            public_url="https://"+HOST+path
            with urlopen(Request(public_url,headers={"User-Agent":"PottFuchsReadOnlyAudit/1.0"}),timeout=15) as response:
                raw=response.read(4000000)
                mime=response.headers.get("Content-Type","")
            if not raw.startswith(b"\x89PNG\r\n\x1a\n"):
                raise ValueError("not PNG")
            target=Path("audit/assets")/filename
            target.parent.mkdir(parents=True,exist_ok=True)
            target.write_bytes(raw)
            report["asset_samples"].append({"url":public_url,"file":str(target),"bytes":len(raw),"sha256":hashlib.sha256(raw).hexdigest(),"mime":mime})
        except (URLError, TimeoutError, OSError, ValueError) as exc:
            report["asset_samples"].append({"file":filename,"error":str(exc)[:150]})
    output=Path("audit/pottfuchs-public-audit.json")
    output.parent.mkdir(parents=True,exist_ok=True)
    output.write_text(json.dumps(report,indent=2,ensure_ascii=False)+"\n",encoding="utf-8")
    print("POTT FUCHS PUBLIC READ-ONLY AUDIT",report["at_utc"])
    print("REDIRECT CHAINS",len(report["redirects"]),"HTML PAGES",len(report["pages"]),
          "RESOURCES",len(report["resources"]),"ISSUES",len(report["issues"]))
    for issue in report["issues"]: print("-",issue)
    print("FULL REPORT",output)
    return 0

if __name__=="__main__": raise SystemExit(main())
