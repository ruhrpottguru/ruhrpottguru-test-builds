#!/usr/bin/env python3
"""Read-only live data-quality evidence for PottFuchs 53-city policy. No writes.
Queries only public visitor POST endpoints already called by the existing theme.
Outputs aggregates, no raw PII or complete event payloads."""
import datetime as dt, json, math, urllib.request, urllib.error
from pathlib import Path

BASE="https://www.pottfuchs.de/wp-json/ruhrpottguru/v1/"
CITIES={"Wesel":(51.6572,6.6186),"Duisburg":(51.4344,6.7623)}
CATS=("food","highlights","culture","activities")
def post(route, data, limit=6500000):
    request=urllib.request.Request(BASE+route,data=json.dumps(data).encode("utf-8"),
      method="POST", headers={"User-Agent":"PottFuchsPublicGeoPolicyAudit/1.0",
                              "Content-Type":"application/json"})
    try:
        with urllib.request.urlopen(request,timeout=32) as response:
            raw=response.read(limit+1)
            if len(raw)>limit:return {"status":response.status,"error":"response_too_large"}
            try:j=json.loads(raw)
            except ValueError:return {"status":response.status,"error":"invalid_json"}
            return {"status":response.status,"json":j}
    except urllib.error.HTTPError as e:return {"status":e.code,"error":"http_error"}
    except Exception as e:return {"status":None,"error":str(e)[:180]}
def coords_place(x):
    if not isinstance(x,dict):return None
    c=x.get("center",x.get("coords",x))
    try:
        lat=float(c.get("lat",c.get("latitude")));lng=float(c.get("lon",c.get("lng",c.get("longitude"))))
        if abs(lat)<=90 and abs(lng)<=180 and (lat!=0 or lng!=0):return (lat,lng)
    except (TypeError,ValueError,AttributeError):pass
    return None
def coords_event(x):
    if not isinstance(x,dict):return None
    for c in (x.get("geo"),x.get("location"),x.get("address"),x):
        p=coords_place(c)
        if p:return p
    return None
def distance(a,b):
    lat1,lng1,lat2,lng2=map(math.radians,(a[0],a[1],b[0],b[1]))
    z=2*6371.0*math.asin(min(1,math.sqrt(math.sin((lat2-lat1)/2)**2+math.cos(lat1)*math.cos(lat2)*math.sin((lng2-lng1)/2)**2)))
    return round(z,2)
def norm_place(x):
    tags=x.get("tags",{}) if isinstance(x,dict) else {}
    if not isinstance(tags,dict):tags={}
    c=coords_place(x)
    has_address=bool(tags.get("addr:street") or tags.get("addr:full") or tags.get("addr:place"))
    has_image=bool(tags.get("image") or tags.get("image:url") or tags.get("wikimedia_commons"))
    return c,has_address,has_image
def norm_event(x):
    city_scope=(str(x.get("rpg_geo_scope","")).lower()=="city" or
        bool(isinstance(x.get("rpg_event_db"),dict) and x["rpg_event_db"].get("cityScope")))
    coord=None if city_scope else coords_event(x)
    details=x.get("rpg_event_details") if isinstance(x.get("rpg_event_details"),dict) else {}
    address=x.get("address")
    has_address=bool(details.get("address") or (isinstance(address,str) and address.strip()) or
                     (isinstance(address,dict) and (address.get("street") or address.get("address"))))
    media=x.get("rpg_event_media")
    has_image=bool(x.get("image") or x.get("imageUrl") or (isinstance(media,dict) and media.get("url")))
    return coord,has_address,has_image,city_scope

def analyze_items(items,center,event=False):
    count=0;missing_coord=0;missing_address=0;missing_image=0;cityscope=0;route_unknown=[]
    found_5km=0;found_10km=0;dists=[]
    for x in items:
        if not isinstance(x,dict):continue
        count+=1
        c,addr,img,scope=norm_event(x) if event else (*norm_place(x),False)
        if scope:cityscope+=1
        if not c:missing_coord+=1
        if not addr:missing_address+=1
        if not img:missing_image+=1
        if c:
            d=distance(center,c);dists.append(d)
            if d<=5:found_5km+=1
            if d<=10:found_10km+=1
    return {"received":count,"no_reliable_coords_in_payload":missing_coord,
      "no_full_address_evidence":missing_address,"no_image_tag_evidence":missing_image,
      "cityscope_flag":cityscope,"coord_within_5km":found_5km,"coord_within_10km":found_10km,
      "max_distance_of_geocoded_rows":round(max(dists),2) if dists else None}
def summarize(city,cat,resp,center):
    out={"city":city,"category":cat,"status":resp.get("status")}
    if "error" in resp:return {**out,"error":resp["error"]}
    j=resp.get("json")
    if not isinstance(j,dict):return {**out,"error":"response_not_object"}
    items=j.get("items") if cat=="events" else j.get("elements")
    if not isinstance(items,list):return {**out,"error":"missing_items_or_elements","keys":sorted(j.keys())[:30]}
    return {**out,**analyze_items(items,center,cat=="events"),"keys":sorted(j.keys())[:16]}
def main():
    result={"utc":dt.datetime.now(dt.timezone.utc).isoformat(),
      "limitations":"Public visitor payload only; not full importer/db; no invented distances for unlocated rows; cityscope counted separately.",
      "cities":[]}
    for city,coords in CITIES.items():
        lat,lng=coords
        records=[]
        events=post("events",{"lat":lat,"lng":lng,"city":city,"maxRadiusKm":10})
        records.append(summarize(city,"events",events,coords))
        for cat in CATS:
            resp=post("places/home",{"category":cat,"lat":lat,"lng":lng,"radiusKm":10,"limit":100})
            records.append(summarize(city,cat,resp,coords))
        result["cities"].extend(records)
    out=Path("audit/geo/pottfuchs-public-geo-quality.json")
    out.parent.mkdir(parents=True,exist_ok=True)
    out.write_text(json.dumps(result,indent=2,ensure_ascii=False)+"\n",encoding="utf-8")
    for row in result["cities"]:print("GEO",json.dumps(row,ensure_ascii=False))
    print("REPORT",out)
    return 0
if __name__=="__main__":raise SystemExit(main())
