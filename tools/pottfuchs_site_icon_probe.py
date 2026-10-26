#!/usr/bin/env python3
"""Read-only PottFuchs Site Icon cross-host probe. Never sends credentials or writes."""
import json, urllib.request, urllib.error, time, hashlib
from urllib.parse import urlparse
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs): return None
opener=urllib.request.build_opener(NoRedirect)
targets=[
    'https://www.pottfuchs.de/wp-json/',
    'https://pottfuchs.de/wp-json/',
    'https://www.ruhrpottguru.de/wp-json/',
    'https://ruhrpottguru.de/wp-json/',
    'https://www.pottfuchs.de/wp-json/wp/v2/media/4215',
    'https://www.pottfuchs.de/favicon.ico',
    'https://www.pottfuchs.de/PottFuchs-Favicon-512x512.png',
    'https://www.pottfuchs.de/PottFuchs-Favicon-512x512.ico',
    'https://www.ruhrpottguru.de/favicon.ico',
    'https://pottfuchs.de/favicon.ico',
    'https://ruhrpottguru.de/favicon.ico',
]
for url in targets:
    q='?pf_readonly_icon_probe='+str(time.time_ns())
    req=urllib.request.Request(url+q,headers={'User-Agent':'PottFuchsPublicIconDiagnostic/1.0','Accept':'application/json,image/*;q=0.6,*/*;q=0.2','Cache-Control':'no-cache'})
    try:
        try:response=opener.open(req,timeout=18)
        except urllib.error.HTTPError as e:response=e
        data=response.read(650000) if response.status==200 else b''
        o={'url':url,'http':response.status,'location':response.headers.get('Location'),
           'content_type':response.headers.get('Content-Type'),'age':response.headers.get('Age'),
           'cache_control':response.headers.get('Cache-Control')}
        if '/favicon.ico' in url or 'PottFuchs-Favicon-512x512' in url:
            import struct
            kind=('ico' if data[:4]==b'\x00\x00\x01\x00' else 'png' if data[:8]==b'\x89PNG\r\n\x1a\n' else 'html' if data[:40].lstrip().lower().startswith((b'<!doctype',b'<html')) else 'unknown')
            count=struct.unpack_from('<H',data,4)[0] if kind=='ico' and len(data)>=6 else None
            o.update({'body_kind':kind,'body_length':len(data),'sha256':hashlib.sha256(data).hexdigest() if data else None,
                      'ico_entries':count,'matches_archived_ico':hashlib.sha256(data).hexdigest()=='a367a6ea0b7ba28b4bdf34ebf4f1e82e6ebcb6e10f08619329bcb68e9bada9c5',
                      'valid_root_ico':bool(url=='https://www.pottfuchs.de/favicon.ico' and response.status==200 and kind=='ico' and count==6)})
        if data and response.headers.get('Content-Type','').startswith('application/json'):
            d=json.loads(data)
            if '/wp/v2/media/' in url:
                o.update({'media_id':d.get('id'),'media_status':d.get('status'),'mime_type':d.get('mime_type'),
                          'media_source_url':d.get('source_url'),'media_details':{
                           'width':d.get('media_details',{}).get('width'),'height':d.get('media_details',{}).get('height')}})
            else:
                o.update({'name':d.get('name'),'url':d.get('url'),'home':d.get('home'),
                  'site_logo':d.get('site_logo'),'site_icon':d.get('site_icon'),
                  'site_icon_url':d.get('site_icon_url'),
                  'namespaces_count':len(d.get('namespaces',[]))})
        print(json.dumps(o,ensure_ascii=False),flush=True)
    except Exception as e:print(json.dumps({'url':url,'error':str(e)[:160]},ensure_ascii=False),flush=True)
