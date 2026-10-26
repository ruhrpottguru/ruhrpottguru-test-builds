import json, urllib.request, urllib.parse, datetime, pathlib
out=pathlib.Path('audit/upstream-duisburg');out.mkdir(parents=True,exist_ok=True)
args={'experience':'open-data','type':'Event','template':'ET2014A.json','mkt':'de-DE','limit':100,'offset':0,'unique':'true','q':'city:"Duisburg"','startdate':'26.10.2026','enddate':'26.10.2026','sort':'title asc'}
url='https://meta.et4.de/rest.ashx/search/?'+urllib.parse.urlencode(args)
try:
 with urllib.request.urlopen(urllib.request.Request(url,headers={'Accept':'application/json','User-Agent':'PottFuchs-readonly-audit/1.0'}),timeout=30) as r:
  data=json.load(r); status=r.status
 result={'checked_utc':datetime.datetime.now(datetime.timezone.utc).isoformat(),'http':status,'query':args,'root_type':type(data).__name__}
 if isinstance(data,dict):
  result['root_keys']=list(data); result['overall']=data.get('overallcount',data.get('overallCount',data.get('total')));items=data.get('items',[])
  if isinstance(items,dict):items=items.get('item',[])
 else:items=data
 if isinstance(items,dict):items=[items]
 result['page_items']=len(items);result['sample']=[{'keys':list(i),'id':i.get('global_id',i.get('id')),'title':i.get('title',i.get('name')),'city':i.get('city'),'dates':{k:v for k,v in i.items() if any(x in k.lower() for x in ['date','time','interval'])}} for i in items[:10]]
except Exception as e: result={'error':str(e)}
(out/'result.json').write_text(json.dumps(result,ensure_ascii=False,indent=2));print(json.dumps(result,ensure_ascii=False))
