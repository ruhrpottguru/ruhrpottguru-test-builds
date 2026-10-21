const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
process.env.TZ='Europe/Berlin';
const context={console,URL,URLSearchParams,window:{wp:{element:{}},ruhrpottSettings:{root:'https://www.ruhrpottguru.de/wp-json/',homeUrl:'https://www.ruhrpottguru.de/'}}};vm.createContext(context);
const root=__dirname+'/../';
for(const name of ['runtime','api','media-layout','location-helpers','category-food','category-highlights','category-places','category-events','detail-helpers','place-events-native','radar-helpers','special-helpers','native-adapter'])vm.runInContext(fs.readFileSync(root+'app/src/main/assets/shared/'+name+'.js','utf8'),context);
const N=context.window.NativeFood,R=context.window.RPGReact,point={lat:51.6572,lng:6.6186,city:'Wesel'};
for(const [token,category] of [['e19383','events'],['p8577','activities'],['p10377','food'],['p22451','buedchen'],['p10387','wurst']]){
 const payload=JSON.parse(fs.readFileSync(root+'app/src/test/resources/search/share-'+token+'.json'));
 const raw=payload.item||payload.element,entry={category,raw,descriptor:{token,category}};
 N.reset('culture');const row=N.restoreSaved(entry,point);
 const normalized=category==='events'?R.normalizeEvent(raw,point):R.normalizePlace(raw,category,point);
 assert.equal(row.canonicalId,normalized.id);assert.equal(row.title,R.displayPlaceTitle(normalized));assert.equal(row.category,category);
 assert.equal(row.distance,R.formatDistance(normalized.distanceKm));
 assert.equal(N.query('','','',24).total,0,'opening a search result never replaces category candidates');
 const saved=N.snapshot(row.id);assert.equal(saved.id,row.canonicalId);assert.equal(saved.descriptor.token,token);
 assert.equal(N.detail(row.id).id,row.id);assert.equal(saved.category,category);
 const again=N.restoreSaved(saved,point);assert.equal(again.canonicalId,row.canonicalId);
 if(category==='events'){assert.equal(again.event.calendar.start,row.event.calendar.start);assert.ok(saved.calendar.start);}
 else assert.equal(JSON.stringify(saved.raw),JSON.stringify(raw));
}
console.log('Global-search live detail identities, existing detail/snapshot/favorite/calendar handlers and isolated category candidates: PASS (5 result families)');
