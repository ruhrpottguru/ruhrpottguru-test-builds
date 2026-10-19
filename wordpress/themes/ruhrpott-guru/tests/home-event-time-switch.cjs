'use strict';
const fs=require('fs'),vm=require('vm'),assert=require('node:assert/strict'),path=require('path');
const root=path.resolve(__dirname,'..');
const views=fs.readFileSync(path.join(root,'src/react-app/views.js'),'utf8');
const components=fs.readFileSync(path.join(root,'src/react-app/components.js'),'utf8');
const css=fs.readFileSync(path.join(root,'css/rpg-react-source.css'),'utf8');
const beginning=views.indexOf('  function homeEventDayAccess(');
const ending=views.indexOf('  function eventUrgencyCompare(',beginning);
assert(beginning>=0&&ending>beginning,'Home event time helpers exist');
const script=views.slice(beginning,ending)+'\nthis.match=homeEventMatchesTime;this.hint=homeEventTimeHint;';
const ctx={Date,console};vm.createContext(ctx);vm.runInContext(script,ctx);
const now=new Date(2026,9,10,18,0,0,0);
const make=(start,end,extras={})=>Object.assign({start:new Date(start),end:end?new Date(end):null,allDay:false},extras);
const at=(hour,min=0,day=10,seconds=0)=>new Date(2026,9,day,hour,min,seconds,0);
for(const [label,start,yes] of [
  ['30 minutes before start',at(18,30),true],
  ['30 minutes and 1 second before start',at(18,30,10,1),false],
  ['15 minutes before start',at(18,15),true],
  ['starts now',at(18),true],
  ['5 minutes after start',at(17,55),true],
  ['5 minutes and 1 second after start',at(17,54,10,59),false],
  ['6 minutes after start',at(17,54),false],
  ['later today',at(20),false],
]){
  const x=make(start,at(22));
  assert.equal(ctx.match(x,'now',now),yes,label);
}
const started=make(at(17,55),at(20));
assert.equal(ctx.hint(started,'now',now),'Gerade gestartet');
assert.equal(ctx.hint(make(at(18,15),at(20)),'now',now),'Beginnt in 15 Min.');
assert.equal(ctx.hint(make(at(18,30),at(20)),'now',now),'Beginnt in 30 Min.');
assert.equal(ctx.hint(make(at(18),at(20)),'now',now),'Gerade gestartet');
assert(!ctx.match(make(at(17,54),at(20)),'now',now),'Older running event not in Jetzt');
assert(!ctx.match(make(at(17,55),at(17,56)),'now',now),'Known ended event excluded');
assert(ctx.match(make(at(17,55),null),'now',now),'Unknown end allowed in brief start window');
assert(!ctx.match(make(at(18,15),at(23),{allDay:true}),'now',now),'Future all-day event not marked spontaneous');
assert(!ctx.match(started,'today',now),'Earlier start not in Heute noch');
assert(!ctx.match(make(at(18),at(20)),'today',now),'Start right now not in Heute noch');
assert(ctx.match(make(at(18,15),at(20)),'today',now),'Upcoming overlap 15 minutes');
assert(ctx.match(make(at(18,30),at(20)),'today',now),'Upcoming overlap 30 minutes');
assert(ctx.match(make(at(20),at(22)),'today',now),'Later today in Heute noch');
assert(!ctx.match(make(at(17,54),at(20)),'today',now),'Already started not in Heute noch');
assert.equal(ctx.hint(make(at(18,15),at(20)),'today',now),'Heute 18:15');
const nextDay=make(at(18,0,11),at(20,0,11));
assert(ctx.match(nextDay,'tomorrow',now),'Tomorrow instance included');
assert.equal(ctx.hint(nextDay,'tomorrow',now),'Morgen 18:00');
assert(!ctx.match(nextDay,'today',now),'Tomorrow not today');
const crossMidnight=make(at(23,30),at(2,0,11));
assert(ctx.match(crossMidnight,'today',now),'Today start before midnight');
assert(ctx.match(crossMidnight,'tomorrow',now),'Overnight event still runs tomorrow');
assert.equal(ctx.hint(crossMidnight,'tomorrow',now),'Läuft bis morgen','Overnight event explicitly noted');
assert(ctx.match(make(at(23,0,9),at(19,0,10)),'today',now),'Valid running multi-day event as today fallback');
const justBeforeMidnight=new Date(2026,9,10,23,45,0,0);
assert(ctx.match(make(at(0,10,11),at(1,0,11)),'now',justBeforeMidnight),'Jetzt crosses date boundary');
assert(!ctx.match(make(at(0,10,11),at(1,0,11)),'today',justBeforeMidnight),'Heute noch respects midnight');
assert(ctx.match(make(at(0,10,11),at(1,0,11)),'tomorrow',justBeforeMidnight),'Morgen starts next calendar day');
const nextDaySameTitle=make(at(18,0,11),at(20,0,11));
assert(ctx.match(nextDaySameTitle,'tomorrow',now),'Explicit daily occurrence of repeated event is included');
// Known contract limit: if the backend supplies only a representative for today,
// its tomorrow recurrence cannot be inferred from the first instance's dates.
assert(!ctx.match(make(at(18),at(20)),'tomorrow',now),'Representative first occurrence alone does not expose tomorrow');
assert(views.includes('visible=visible.filter(x=>homeEventMatchesTime('),'Window selects before card limit');
assert(views.indexOf('visible=visible.filter(x=>homeEventMatchesTime(')<views.indexOf('visible=home?visible.slice(0,4):visible;'),'Time selection precedes four-card cap');
assert(views.includes("if(home&&visible.length<4&&radius<10){effective=10;visible=items.filter"),'Original home fallback preserved');
assert(views.includes(':eventUrgencyCompare);'),'Original urgency ordering preserved as default');
assert(views.includes('RPG.dedupeEventsForListing(items);'),'Original dedupe preserved');
assert(components.includes('subtitle||meta.subtitle'),'Original header accepts an Event-only subtitle');
assert(components.includes('timeHint?span({className:\'rg-home-event-time-hint\''),'Existing card gains a display-only time hint');
assert(css.includes('rg-home-event-time-button:focus-visible'),'Selector has visible keyboard focus');
assert(css.includes('flex-wrap:wrap'),'Selector fits mobile without horizontal scrolling');
assert(views.includes("document.addEventListener('visibilitychange',refreshVisible)"),'Tabs refresh promptly on tab return');
assert(views.includes('window.setInterval(refreshVisible,30000)'),'Time selection updates within 30 seconds');
assert(views.includes("radius,new Date().toDateString()].join('|')"),'Event feed cache not invalidated by tab or timer; day rollover resets key');
assert(views.includes('homeEventMediaCache.current.has(mediaKey)'),'Unchanged home event shortlist reuses media resolution across clock ticks');
assert(views.includes('Für morgen werden hier derzeit keine passenden Termine angezeigt.'),'Empty state does not claim that backend exhaustively covers tomorrow');
assert(components.includes("String(item.title||'')+' – '+timeHint"),'Time label is accessible as part of link aria-label');
console.log('PASS v0.3: now [-5m,+30m], today future-only, tomorrow, cross-midnight, cache and scope assertions (offline QA)');

assert(views.includes('eventNearTermCards(items,new Date())'),'Tomorrow repetition is drawn from verified backend occurrences');
