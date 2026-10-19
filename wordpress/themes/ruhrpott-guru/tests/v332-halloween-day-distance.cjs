"use strict";
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
process.env.TZ='Europe/Berlin';
const src=fs.readFileSync(path.join(__dirname,'../src/react-app/views.js'),'utf8');
const start=src.indexOf('  function halloweenCalendarDay(');
const end=src.indexOf('  function dedupeHalloweenSeries(',start);
assert(start>0&&end>start,'actual Halloween sort helpers found');
const context={Date,Number,String};vm.createContext(context);
vm.runInContext(src.slice(start,end)+';this.cmp=halloweenSpecialCompare;',context);
const cmp=context.cmp;
function evt(id,day,hr,dist,city='Wesel'){
 return {id,title:id,start:new Date(2026,9,day,hr,0,0),distanceKm:dist,city};
}
function sort(items){return items.slice().sort(cmp).map(x=>x.id)}
// Chronology must beat proximity to 31.10., and actual current-day start order.
const party16=evt('HorrorFestival16',16,10,25,'Bottrop');
const party31=evt('HalloweenParty31',31,20,1,'Wesel');
const party30=evt('HalloweenParty30',30,20,2,'Rheinberg');
assert.deepEqual(sort([party31,party30,party16]),['HorrorFestival16','HalloweenParty30','HalloweenParty31']);
// Within the SAME date: actual, unrounded location distance trumps time.
const near=evt('NearLate',31,22,1.24,'Rheinberg');
const far=evt('FarEarly',31,9,1.25,'Wesel');
assert.deepEqual(sort([far,near]),['NearLate','FarEarly']);
// No artificially contiguous city groups.
assert.deepEqual(sort([evt('RheinbergA',31,12,2,'Rheinberg'),evt('WeselB',31,14,4,'Wesel'),evt('RheinbergC',31,9,6,'Rheinberg')]),['RheinbergA','WeselB','RheinbergC']);
// Actual distance ties are broken by time, then stable ID.
assert.deepEqual(sort([evt('late',31,22,5),evt('early',31,18,5)]),['early','late']);
assert.deepEqual(sort([evt('b',31,18,5),evt('a',31,18,5)]),['a','b']);
// Unknown distance stays eligible but never falsely becomes zero km.
assert.deepEqual(sort([evt('NoGPS',31,8,null),evt('KnownGPS',31,18,25)]),['KnownGPS','NoGPS']);
// Critical contract boundaries: source, eligibility, dedupe, fallback unchanged.
for(const token of ['visible=visible.filter(isHalloweenEvent)','dedupeHalloweenSeries(s.items.filter(isHalloweenEvent))',"const fallbackRadius=!s.loading&&!s.error&&matches.length===0",'halloweenSameSeries(existing,item)','visible.map(item=>h(C.Card'])assert(src.includes(token),token);
assert(!src.includes('halloweenTargetDayDistance'),'old Halloween 31 October priority removed');
assert(src.includes('items.sort(priorityEventView?eventPriorityCompare(items,eventWindow,location,new Date()):eventUrgencyCompare)'), 'normal Events category ranking unchanged');
console.log('PASS Halloween V332 date ascending / distance / time / no city grouping / safe unknown GPS');
