import json, urllib.request, urllib.parse, datetime, pathlib
out=pathlib.Path('audit/upstream-duisburg');out.mkdir(parents=True,exist_ok=True)
results=[]
for label,q,end in [('duisburg-today','city:"Duisburg"','26.10.2026'),('duisburg-7days','city:"Duisburg"','01.11.2026'),('all-cities-today','','26.10.2026')]:
 args={'experience':'open-data','type':'Event','template':'ET2014A.json','mkt':'de-DE','limit':100,'offset':0,'unique':'true','q':q,'startdate':'26.10.2026','enddate':end,'sort':'title asc'}
 try:
  url='https://meta.et4.de/rest.ashx/search/?'+urllib.parse.urlencode(args)
  with urllib.request.urlopen(urllib.request.Request(url,headers={'Accept':'application/json','User-Agent':'PottFuchs-readonly-audit/1.0'}),timeout=30) as r:
   data=json.load(r);http=r.status
  items=data.get('items',[]) if isinstance(data,dict) else data
  if isinstance(items,dict):items=items.get('item',[])
  if isinstance(items,dict):items=[items]
  result={'label':label,'checked_utc':datetime.datetime.now(datetime.timezone.utc).isoformat(),'http':http,'query':args,'status':data.get('status') if isinstance(data,dict) else None,'overall':data.get('overallcount') if isinstance(data,dict) else None,'page_items':len(items),'sample':[{'id':i.get('global_id',i.get('id')),'title':i.get('title',i.get('name')),'city':i.get('city')} for i in items[:5]]}
 except Exception as e:result={'label':label,'error':str(e)}
 results.append(result);print(json.dumps(result,ensure_ascii=False))
(out/'result.json').write_text(json.dumps(results,ensure_ascii=False,indent=2))
