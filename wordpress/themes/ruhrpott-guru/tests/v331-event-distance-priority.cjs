'use strict';
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),assert=require('node:assert/strict');
process.env.TZ='Europe/Berlin';
const root=path.resolve(__dirname,'..');
const source=fs.readFileSync(path.join(root,'src/react-app/views.js'),'utf8');
const beginning=source.indexOf('  function homeEventDayAccess(');
const ending=source.indexOf('  function useCategoryItems(',beginning);
assert(beginning>0&&ending>beginning);
const ctx={Date,console};vm.createContext(ctx);
vm.runInContext(source.slice(beginning,ending)+'\nthis.eventPriority=eventPriorityCompare;this.timeMatch=homeEventMatchesTime;',ctx);
const now=new Date(2026,9,11,22,15);
const date=(day,h,m=0)=>new Date(2026,9,day,h,m);
function evt(id,day,h,km,city='Wesel',endDay=day,endHour=h+1){
  return {id,title:id,start:date(day,h),end:date(endDay,endHour),distanceKm:km,city,allDay:false};
}
function sorted(list,mode='tomorrow'){return list.slice().sort(ctx.eventPriority(list,mode,{city:'Wesel',lat:51.658,lng:6.615},now));}
const nearCity=evt('Wesel3',12,9,3,'Wesel');
const farSameCity=evt('Wesel8',12,10,8,'Wesel');
const nearerOtherCity=evt('Rheinberg2',12,18,2,'Rheinberg');
const distantOtherCity=evt('Oberhausen18',12,8,18,'Oberhausen');
const nextDayEvenNear=evt('TuesdayNear',13,8,.01,'Wesel');
const spanning=evt('LongNear',2,8,.15,'Wesel',26,18);
assert.equal(sorted([spanning,farSameCity,nearCity,distantOtherCity,nearerOtherCity,nextDayEvenNear]).map(x=>x.id).join(','),
  'Rheinberg2,Wesel3,Wesel8,Oberhausen18,LongNear,TuesdayNear',
  'tomorrow exact distance across cities, concrete tomorrow before long-term; other future day remains after');
const monday9=evt('X',12,9,4.20,'Rheinberg'),monday8=evt('Y',12,8,4.20,'Wesel');
assert.equal(sorted([monday9,monday8]).map(x=>x.id).join(','),'Y,X','identical distances use start time across city boundaries');
const preciseA=evt('FarRounding',12,8,4.24,'Wesel'),preciseB=evt('NearRounding',12,18,4.16,'Rheinberg');
assert.equal(sorted([preciseA,preciseB]).map(x=>x.id).join(','),'NearRounding,FarRounding','raw distance, not rounded display kilometre');
const sameTime1=evt('B_ID',12,8,5,'Wesel'),sameTime2=evt('A_ID',12,8,5,'Oberhausen');
assert.equal(sorted([sameTime1,sameTime2]).map(x=>x.id).join(','),'A_ID,B_ID','stable ID is final tie-breaker; never city');
const unknown=evt('NoPoint',12,7,NaN,'Wesel');unknown.cityScope=true;
assert.equal(sorted([unknown,farSameCity]).map(x=>x.id).join(','),'NoPoint,Wesel8',
 'city-scoped unknown uses already observed local distance as sort-only proxy, not zero');
const on13Far=evt('TuesdayFar',13,11,20,'Wesel'),on13Near=evt('TuesdayNear2',13,19,2,'Rheinberg');
const on14Near=evt('WednesdayNear',14,8,.2,'Wesel');
const on12Remaining=evt('MondayExpired',12,3,10,'Oberhausen');
const tomorrowList=[on14Near,on13Far,on13Near,on12Remaining,spanning];
assert.equal(sorted(tomorrowList,'today').map(x=>x.id).join(','),
  'LongNear,MondayExpired,TuesdayNear2,TuesdayFar,WednesdayNear',
  'other catalogue sorts by day before distance, distance before clock within same day');
assert.equal(sorted([on14Near,on13Far,on13Near]).map(x=>x.id).join(','),
 'TuesdayNear2,TuesdayFar,WednesdayNear','other catalogue groups day then accurate distance despite city differences');
// Closed today: full catalogue still includes it, but selected time window does not.
const closed={...spanning,description:'Öffnungszeiten MO-DO | 9-16 Uhr FR | 9-14 Uhr'};
assert.equal(ctx.timeMatch(closed,'today',now),false);
assert.equal(sorted([closed,nearerOtherCity],'today').length,2,'full catalogue retains ineligible events as other results');
assert(source.includes('visible=home?visible.slice(0,4):visible'),'Home preview remains limited to four cards');
assert(source.includes("h('h2',null,'Weitere Veranstaltungen')"),'remaining category events remain visible');
assert(source.includes('items.sort(priorityEventView?eventPriorityCompare(items,eventWindow,location,new Date()):eventUrgencyCompare);'),'normal Events category uses unchanged legacy urgency comparator without handoff');
assert(source.includes('visible=items.filter(x=>x.cityScope===true||x.distanceKm<=radius)'),'existing event radius boundary unchanged');
assert(source.includes('eventNearTermCards(items,new Date())'),'future series expansion contract unchanged');
assert(!source.includes('eventPriorityCity('),'no town-based grouping survives event priority comparator');
console.log('PASS v331 exact distance, no city groups, local day > distance > time > stable key, spanning fallback, city-scope, full catalogue, default sort unchanged');
