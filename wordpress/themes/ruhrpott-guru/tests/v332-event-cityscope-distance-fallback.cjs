'use strict';
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),assert=require('node:assert/strict');
process.env.TZ='Europe/Berlin';
const root=path.resolve(__dirname,'..');
const code=fs.readFileSync(path.join(root,'src/react-app/views.js'),'utf8');
const begin=code.indexOf('  function homeEventDayAccess('),end=code.indexOf('  function useCategoryItems(',begin);
assert(begin>=0&&end>begin,'sort helper in real theme source');
const cx={Date,console};vm.createContext(cx);
vm.runInContext(code.slice(begin,end)+'\nthis.priority=eventPriorityCompare;',cx);
const now=new Date(2026,9,11,23,20),dt=(day,h)=>new Date(2026,9,day,h);
function e(id,km,city='Wesel',day=13,h=10,scope=false){return {id,title:id,city,cityScope:scope,
 distanceKm:km,start:dt(day,h),end:dt(day,h+1),allDay:false};}
function order(items,mode='tomorrow',city='Wesel'){
 return items.slice().sort(cx.priority(items,mode,{city,lat:51.6585,lng:6.611},now)).map(x=>x.id);
}
// User's real case: Exkursion (city-scope/no coordinates), geocoded local
// events and a more distant event in Oberhausen in the SAME other-day tier.
const weselA=e('Wesel110m',.110),weselB=e('Wesel770m',.770),oberhausen=e('Oberhausen25km',25,'Oberhausen');
const exkursion=e('ExkursionWesel',null,'Wesel',13,10,true);
assert.deepEqual(order([oberhausen,exkursion,weselB,weselA]),
 ['Wesel110m','ExkursionWesel','Wesel770m','Oberhausen25km']);
// The same fallback must work in the selected "tomorrow" tier.
const tomorrow=[e('B_Oberhausen',25,'Oberhausen',12,9),e('A_Wesel',.2,'Wesel',12,8),e('C_CityScoped',null,'Wesel',12,10,true)];
assert.deepEqual(order(tomorrow),['A_Wesel','C_CityScoped','B_Oberhausen']);
// Existing exact geo distances are never regrouped by city name.
assert.deepEqual(order([e('FarWesel',8,'Wesel',12),e('NearOther',2,'Rheinberg',12)]),
 ['NearOther','FarWesel']);
// Without same-city point evidence there is NO made-up centroid or 0 km.
assert.deepEqual(order([e('Unverified',null,'Wesel',12,8,true),e('Oberhausen',25,'Oberhausen',12,8)]),
 ['Oberhausen','Unverified']);
// CityScope is mandatory: unrelated missing points are not favoured.
assert.deepEqual(order([e('NoScope',null,'Wesel',12,8,false),e('Local',1,'Wesel',12,10)]),
 ['Local','NoScope']);
// The proxy is not a public km field; no mutation of source items.
assert.equal(exkursion.distanceKm,null);
assert(code.includes('x.cityScope===true&&selectedCity'),'fallback limited to explicitly city-scoped data');
assert(code.includes('visible=items.filter(x=>x.cityScope===true||x.distanceKm<=radius)'),
 'original radius and city-scope admission unchanged');
assert(code.includes('return Infinity;'),'unknown city stays unknown');
console.log('PASS v332: real Wesel city-scope sorting, no faked km, existing radius/true geo intact');
