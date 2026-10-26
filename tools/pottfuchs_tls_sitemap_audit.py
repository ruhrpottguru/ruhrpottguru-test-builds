#!/usr/bin/env python3
"""Public TLS + complete first-level sitemap audit; GET only, never WordPress writes."""
import datetime
import json
import socket
import ssl
import urllib.request
import urllib.parse
import xml.etree.ElementTree as ET
from collections import Counter
from pathlib import Path

HOSTS=("pottfuchs.de","www.pottfuchs.de","ruhrpottguru.de","www.ruhrpottguru.de")
INDEXES=("https://www.pottfuchs.de/wp-sitemap.xml",
         "https://www.pottfuchs.de/rpg-sitemap.xml")
MAX_BYTES=12000000

def certificate(host):
    with socket.create_connection((host,443),timeout=15) as sock:
        with ssl.create_default_context().wrap_socket(sock,server_hostname=host) as tls:
            cert=tls.getpeercert()
            expiry=datetime.datetime.fromtimestamp(ssl.cert_time_to_seconds(cert["notAfter"]),datetime.timezone.utc)
            remaining=(expiry-datetime.datetime.now(datetime.timezone.utc)).total_seconds()/86400
            return {"host":host,"verified_tls":True,"expires_utc":expiry.isoformat(),
              "remaining_days":round(remaining,1),
              "issuer":[[list(p) for p in n] for n in cert.get("issuer",[])],
              "dns_sans":[x[1] for x in cert.get("subjectAltName",[]) if x[0]=="DNS"][:30]}

def fetch(url):
    try:
        with urllib.request.urlopen(urllib.request.Request(url,headers={"User-Agent":"PottFuchsPublicReadOnlySEOAudit/1.0"}),timeout=30) as r:
            raw=r.read(MAX_BYTES+1)
            return {"url":url,"status":r.status,"too_large":len(raw)>MAX_BYTES,"body":raw[:MAX_BYTES]}
    except Exception as e:
        return {"url":url,"error":str(e)[:350]}

def sitemap(data):
    root=ET.fromstring(data)
    tag=root.tag.rsplit("}",1)[-1]
    locs=[x.text.strip() for x in root.iter() if x.tag.rsplit("}",1)[-1]=="loc" and x.text]
    hosts=Counter(urllib.parse.urlsplit(url).netloc for url in locs)
    return {"type":tag,"loc_count":len(locs),"hostname_counts":dict(hosts),
            "unexpected_hosts":dict((host,count) for host,count in hosts.items() if host!="www.pottfuchs.de"),
            "sample":locs[:3],"locs":locs}

def main():
    rep={"utc":datetime.datetime.now(datetime.timezone.utc).isoformat(),
         "method":"TLS handshake/hostname verification and public XML GET",
         "certificates":[],"sitemap_files":[],"warnings":[]}
    for host in HOSTS:
        try: c=certificate(host)
        except Exception as e: c={"host":host,"verified_tls":False,"error":str(e)[:250]}
        rep["certificates"].append(c)
        if not c.get("verified_tls"):rep["warnings"].append("TLS "+host+": "+c.get("error",""))
    queue=list(INDEXES);seen=set()
    while queue and len(seen)<20:
        url=queue.pop(0)
        if url in seen:continue
        seen.add(url)
        u=urllib.parse.urlsplit(url)
        if u.scheme!="https" or u.netloc!="www.pottfuchs.de":
            rep["warnings"].append("Non-canonical sitemap reference "+url[:160]);continue
        response=fetch(url)
        summary={k:v for k,v in response.items() if k!="body"}
        if response.get("status")!=200 or response.get("too_large"):
            rep["warnings"].append("Sitemap unchecked "+url[:160])
        else:
            try:
                parsed=sitemap(response["body"])
                summary["sitemap"]={k:v for k,v in parsed.items() if k!="locs"}
                if parsed["unexpected_hosts"]:rep["warnings"].append("Wrong sitemap loc hosts "+url[:160])
                if parsed["type"]=="sitemapindex":
                    queue.extend(x for x in parsed["locs"] if x not in seen)
            except ET.ParseError as e:
                summary["xml_error"]=str(e)[:160];rep["warnings"].append("Invalid sitemap XML "+url[:160])
        rep["sitemap_files"].append(summary)
    if queue:rep["warnings"].append("Uninspected nested sitemap files "+str(len(queue)))
    file=Path("audit/public/pottfuchs-tls-sitemap-complete.json")
    file.parent.mkdir(parents=True,exist_ok=True)
    file.write_text(json.dumps(rep,indent=2,ensure_ascii=False)+"\n",encoding="utf-8")
    print("TLS CERTIFICATES")
    for c in rep["certificates"]:print(json.dumps(c,ensure_ascii=False)[:650])
    print("SITEMAP FILES",len(rep["sitemap_files"]))
    for row in rep["sitemap_files"]:print(json.dumps(row,ensure_ascii=False)[:650])
    print("WARNINGS",len(rep["warnings"]),rep["warnings"])
    print("OUTPUT",file)
    return 0
if __name__=="__main__":raise SystemExit(main())
