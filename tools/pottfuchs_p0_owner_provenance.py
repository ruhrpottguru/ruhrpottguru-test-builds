#!/usr/bin/env python3
"""Read-only owner evidence for unresolved branded page titles (no admin calls)."""
import json, datetime, urllib.request, urllib.parse
from pathlib import Path
from html.parser import HTMLParser

BASE="https://www.pottfuchs.de"
SLUGS=["veranstaltungen","food","highlights","kultur","aktiv","zeitreise-von-oben"]
class Head(HTMLParser):
 def __init__(self):
  super().__init__();self.in_head=False;self.title=False;self.out={"title":"","meta":[],"link":[]}
 def handle_starttag(self,t,a):
  d=dict(a)
  if t=="head":self.in_head=True
  if t=="title" and self.in_head:self.title=True
  if self.in_head and t in ("meta","link"):
   self.out[t].append(d)
 def handle_endtag(self,t):
  if t=="title":self.title=False
  if t=="head":self.in_head=False
 def handle_data(self,d):
  if self.title:self.out["title"]+=d
def get(url):
 try:
  with urllib.request.urlopen(urllib.request.Request(url,headers={"User-Agent":"PottFuchs-P0ReadOnlyOwnerProvenance/1.0"}),timeout=20) as r:
   return {"http":r.status,"headers":dict(r.headers.items()),"body":r.read(1500000).decode("utf8","replace")}
 except Exception as e:return {"error":str(e)[:250]}
def main():
 out={"utc":datetime.datetime.now(datetime.timezone.utc).isoformat(),"pages":[]}
 for slug in SLUGS:
  api=get(BASE+"/wp-json/wp/v2/pages?"+urllib.parse.urlencode({"slug":slug,"_fields":"id,slug,title,link,meta,status"}))
  pages=None
  if api.get("http")==200:
   try:pages=json.loads(api["body"])
   except ValueError:pages=None
  html=get(BASE+"/"+slug+"/")
  parser=Head()
  if html.get("http")==200:parser.feed(html.get("body",""))
  h=parser.out
  metas=[x for x in h["meta"] if x.get("property") in ("og:title","og:site_name") or x.get("name") in ("generator","twitter:title")]
  content_evidence=None
  if slug=="zeitreise-von-oben":
   extra=get(BASE+"/wp-json/wp/v2/pages/4115?_fields=id,content,title,slug")
   if extra.get("http")==200:
    try:
     payload=json.loads(extra.get("body",""))
     page_content=str(payload.get("content",{}).get("rendered",""))
     marker="RuhrpottGuru-Standort"
     i=page_content.find(marker)
     content_evidence={"http":extra.get("http"),"length":len(page_content),"literal_present":i>=0,
                      "occurrences":page_content.count(marker),
                      "literal_context":page_content[max(0,i-65):i+len(marker)+85] if i>=0 else ""}
    except ValueError:content_evidence={"error":"invalid-json"}
   else:content_evidence={"http":extra.get("http"),"error":extra.get("error")}
  out["pages"].append({"slug":slug,"wp_rest":{"status":api.get("http"),"pages":pages[:2] if isinstance(pages,list) else None,"error":api.get("error")},
    "content_evidence":content_evidence,"front":{"http":html.get("http"),"title":h["title"].strip(),"head_meta":metas,
        "x_pingback":html.get("headers",{}).get("X-Pingback")}})
 for x in out["pages"]:print("P0_OWNER",json.dumps(x,ensure_ascii=False)[:3000])
 path=Path("audit/p0-owner/provenance.json");path.parent.mkdir(parents=True,exist_ok=True)
 path.write_text(json.dumps(out,ensure_ascii=False,indent=2)+"\n")
if __name__=="__main__":main()
