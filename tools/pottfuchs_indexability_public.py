#!/usr/bin/env python3
"""PottFuchs read-only indexability inventory. Public GET only, no WordPress changes."""
import json, datetime, re, urllib.request, urllib.error, xml.etree.ElementTree as ET
from html.parser import HTMLParser
from urllib.parse import urlsplit, urljoin
class Parser(HTMLParser):
    def __init__(self): super().__init__();self.meta=[];self.links=[];self.title='';self.in_title=False
    def handle_starttag(self,t,a):
        d=dict(a)
        if t=="meta":self.meta.append(d)
        if t=="link":self.links.append(d)
        if t=="title":self.in_title=True
    def handle_data(self,d):
        if self.in_title:self.title+=d
    def handle_endtag(self,t):
        if t=="title":self.in_title=False
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args,**kwargs):return None
opener=urllib.request.build_opener(NoRedirect)
def get(url,maxbytes=2100000):
    r=urllib.request.Request(url,headers={'User-Agent':'PottFuchs-GSC-Indexability-ReadOnly/1.0','Accept':'text/html,application/xml'})
    try:
        try:p=opener.open(r,timeout=19)
        except urllib.error.HTTPError as e:p=e
        return p.status,dict(p.headers),p.read(maxbytes) if p.status==200 else b''
    except Exception as e:return 0,{'error':str(e)[:180]},b''
site='https://www.pottfuchs.de'
fixed=['/','/veranstaltungen/','/food/','/highlights/','/kultur/','/aktiv/',
       '/ort/16936/essen/zum-schwarzen/','/ort/10523/wesel/weseler-wirtshaus/',
       '/veranstaltung/6936/essen/77-essener-lichtwochen/',
       '/zeitreise-von-oben/','/suche/?q=essen',
       '/veranstaltungen/?place=wesel&radius=5',
       '/ort/999999999999/test/kein-ort/']
targets=[site+x for x in fixed]
for sitemap in ['/rpg-sitemap-places.xml','/rpg-sitemap-events.xml','/wp-sitemap.xml']:
    status,headers,body=get(site+sitemap,7000000)
    urls=re.findall(r'<loc>\s*(https?://[^<]+)\s*</loc>',body.decode('utf8','replace'))
    add=[u.replace('&amp;','&') for u in urls if u.startswith(site+'/') and not u.endswith('.xml')]
    if add:
        for idx in sorted(set([0,min(10,len(add)-1),len(add)//2,len(add)-1])):
            targets.append(add[idx])
    print('SITEMAP',json.dumps({'url':site+sitemap,'http':status,'entries_sampled':len(add),'count':len(urls),'x_robots':headers.get('X-Robots-Tag')},ensure_ascii=False))
targets=list(dict.fromkeys(targets))
results=[]
for url in targets:
    status,headers,body=get(url)
    h=Parser()
    try:h.feed(body.decode('utf8','replace'))
    except Exception:pass
    canonical=[a.get('href') for a in h.links if 'canonical' in (a.get('rel') or '').split()]
    robots=[{'name':a.get('name'),'content':a.get('content')} for a in h.meta if (a.get('name') or '').lower() in ('robots','googlebot','bingbot')]
    directives=" ".join([str(x.get('content','')) for x in robots]+[str(headers.get('X-Robots-Tag',''))])
    noindex='noindex' in directives.lower()
    record={'url':url,'http':status,'location':headers.get('Location'),'x_robots':headers.get('X-Robots-Tag'),
            'canonical':canonical,'meta_robots':robots,'noindex':noindex,'title':h.title.strip()[:150],
            'expected_new_host':all(urlsplit(c).netloc=='www.pottfuchs.de' for c in canonical)}
    results.append(record)
    print('URL',json.dumps(record,ensure_ascii=False))
summary={'tested_urls':len(results),'http_200':sum(z['http']==200 for z in results),
         'http_404':sum(z['http']==404 for z in results),
         'noindex':sum(z['noindex'] for z in results),
         'canonical_mismatch':sum(bool(z['canonical']) and not z['expected_new_host'] for z in results)}
print('SUMMARY',json.dumps(summary,ensure_ascii=False))
