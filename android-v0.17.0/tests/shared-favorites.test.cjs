const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
process.env.TZ='Europe/Berlin';
const now=Date.parse('2026-10-19T21:00:00Z');class Clock extends Date{constructor(...args){super(...(args.length?args:[now]));}static now(){return now;}}
const context={console,Date:Clock,URL,URLSearchParams,window:{wp:{element:{}},ruhrpottSettings:{root:'https://www.ruhrpottguru.de/wp-json/',homeUrl:'https://www.ruhrpottguru.de/'}}};vm.createContext(context);
for(const name of ['runtime','api','media-layout','location-helpers','category-food','category-highlights','category-places','category-events','detail-helpers','place-events-native','radar-helpers','special-helpers','native-adapter'])vm.runInContext(fs.readFileSync(__dirname+'/../app/src/main/assets/shared/'+name+'.js','utf8'),context,{filename:name});
const api=context.window.NativeFood,point={lat:51.6572,lng:6.6186,city:'Wesel'};let count=0;
for(const [category,file] of [['food','food-wesel-10km'],['events','events-wesel-5km'],['highlights','highlights-wesel-25km'],['culture','culture-wesel-25km'],['activities','activities-wesel-25km'],['buedchen','buedchen-wesel-25km'],['wurst','wurst-wesel-25km']]){
 const payload=JSON.parse(fs.readFileSync(__dirname+'/fixtures/'+file+'.json'));
 api.reset(category);api.append(payload.items||payload.elements);api.commit(point,25);
 const rows=api.query('','','',3).items;assert.ok(rows.length,category);
 for(const row of rows){
  const saved=JSON.parse(JSON.stringify(api.snapshot(row.id)));assert.equal(String(saved.id),String(row.canonicalId));assert.equal(saved.category,category);
  const before=api.detail(row.id),restored=api.restoreSaved(saved,point);assert.equal(restored.canonicalId,row.canonicalId);assert.equal(restored.title,row.title);
  assert.equal(restored.event?.calendar?.start,before.event?.calendar?.start);
  if(category==='events')assert.equal(saved.calendar?.start,before.event?.calendar?.start);
  // Existing v0.16.1 favorites have no calendar/reminder fields and must still open.
  delete saved.calendar;delete saved.reminder;
  assert.equal(api.restoreSaved(saved,point).canonicalId,row.canonicalId);
  count++;
 }
}
console.log(JSON.stringify({result:'PASS',categories:7,snapshotAndOldFavoriteRestores:count,canonicalEventTimes:true}));
