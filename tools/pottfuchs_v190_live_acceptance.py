"""Read-only public POST queries; no import controls or production writes."""
import datetime,json,math,urllib.request
from pathlib import Path

def query(city,lat,lng,radius):
    req=urllib.request.Request('https://www.pottfuchs.de/wp-json/ruhrpottguru/v1/events',
        data=json.dumps(dict(city=city,lat=lat,lng=lng,maxRadiusKm=radius)).encode(),
        headers={'Content-Type':'application/json','User-Agent':'PottFuchs-read-only-v190-acceptance'})
    with urllib.request.urlopen(req,timeout=70) as response:
        assert response.status==200
        data=json.load(response)
    assert data.get('ok') and data.get('ready') and data.get('cache')=='DB', 'production DB path required'
    items=data['items'];scoped=[];precise=0
    for item in items:
        db=item.get('rpg_event_db',{})
        assert db.get('eventId'), 'persistent identity required'
        if db.get('cityScope'):
            assert db.get('distanceKm') is None, 'no city-scope distance'
            assert item.get('rpg_geo_scope')=='city'
            assert item.get('rpg_geo_label','').strip().casefold()==city.casefold(), 'actual selected city'
            assert not any(k in item for k in ('geo','latitude','longitude','lat','lng')), 'no fabricated coordinates'
            scoped.append({'id':db['eventId'],'title':item['title'],'source':item.get('provider_source'),
                'city':item.get('rpg_geo_label'),'address':item.get('address'),
                'canonicalUrl':item.get('rpg_event_details',{}).get('canonicalUrl')})
        else:
            geo=item['geo'];a=math.radians(geo['latitude']-lat);b=math.radians(geo['longitude']-lng)
            h=math.sin(a/2)**2+math.cos(math.radians(lat))*math.cos(math.radians(geo['latitude']))*math.sin(b/2)**2
            km=6371*2*math.atan2(math.sqrt(h),math.sqrt(max(0,1-h)))
            assert km<=radius+0.001, 'precise event outside requested radius'
            assert abs(km-db['distanceKm'])<0.001, 'real distance retained'
            precise+=1
    return {'city':city,'radius':radius,'items':len(items),'precise':precise,'cityScoped':len(scoped),
        'cityScopedSources':sorted(set(x['source'] for x in scoped)),
        'samples':scoped[:3],'allScopedIds':sorted(x['id'] for x in scoped)}

if __name__=='__main__':
    results=[]
    for city,lat,lng in [('Duisburg',51.435,6.763),('Wesel',51.6572,6.6186),('Selm',51.6965,7.4683),('Essen',51.4556,7.0116),('Dortmund',51.5136,7.4653)]:
        pair=[]
        for radius in (5,10):
            row=query(city,lat,lng,radius);results.append(row);pair.append(row)
            print('LIVE_CITY_SCOPE',json.dumps(row,ensure_ascii=False),flush=True)
        assert pair[0]['allScopedIds']==pair[1]['allScopedIds'], 'city-scope membership independent of exact radius'
    summary={'at':datetime.datetime.now(datetime.timezone.utc).isoformat(),'method':'public visitor POST search, read-only; no control endpoints','results':results}
    out=Path('audit/v190');out.mkdir(parents=True,exist_ok=True)
    (out/'live-acceptance.json').write_text(json.dumps(summary,ensure_ascii=False,indent=2)+'\n')
    print('PASS',len(results),'production city/radius samples')
