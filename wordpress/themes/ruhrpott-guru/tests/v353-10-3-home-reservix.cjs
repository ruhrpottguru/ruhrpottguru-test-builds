// V353.10.3: Reservix-Karten auf der Startseite (Jetzt / Heute noch / Morgen). Echte Normalisierung + echte Startseiten-Hilfsfunktionen.
process.env.TZ='Europe/Berlin';
const fs=require('fs'),vm=require('vm'),assert=require('assert'),path=require('path');
const dir=path.join(__dirname,'../src/react-app/');
const window={wp:{element:{createElement:(type,props,...children)=>({type,props:props||{},children}),Fragment:'fragment'}},ruhrpottSettings:{homeUrl:'https://www.pottfuchs.de/',root:'https://www.pottfuchs.de/wp-json/'},location:{origin:'https://www.pottfuchs.de',search:'',href:'https://www.pottfuchs.de/'},localStorage:{getItem:()=>null,setItem:()=>{}},sessionStorage:{getItem:()=>null,setItem:()=>{}}};
const context=vm.createContext({window,document:{getElementById:()=>null},URL,URLSearchParams,Date,Intl,console,Set,Map,setTimeout,clearTimeout,navigator:{},fetch:()=>Promise.reject(new Error('offline'))});
for(const f of ['runtime.js','api.js'])vm.runInContext(fs.readFileSync(dir+f,'utf8'),context);
const RPG=window.RPGReact;
const views=fs.readFileSync(dir+'views.js','utf8');
const s=views.indexOf('  function homeEventDayAccess('),e=views.indexOf('  function useCategoryItems(',s);
assert(s>0&&e>s);
const h=vm.createContext({Date,console,window:{sessionStorage:{setItem(){},getItem:()=>null,removeItem(){}}}});
vm.runInContext(views.slice(s,e)+'\nthis.api={match:homeEventMatchesTime,expand:eventNearTermCards,sort:eventPriorityCompare};',h);
const {match,expand,sort}=h.api;
let checks=0;const ok=(v,m)=>{assert(v,m);checks++;};
const now=new Date('2026-11-05T10:07:00+01:00');
const loc={lat:51.4344,lng:6.7623,city:'Duisburg'};
const mk=(id,title,startIso,near,km)=>({id,title,venueName:'Halle',city:'Duisburg',address:{city:'Duisburg'},lat:loc.lat+km/111,lng:loc.lng,start:startIso,allDay:false,
  rpg_event_series:{count:near.length,first:near[0],last:near[near.length-1],identity:id,nearTerm:near.map(x=>({start:x,end:'',eventId:0,canonicalUrl:''}))},
  rpg_partner_item:{v:1,provider:'reservix',total:near.length,dates:near.map(x=>({start:x,url:'https://www.awin1.com/pclick.php?p=x'}))},
  rpg_partner_offers:[{provider:'reservix',url:'https://www.awin1.com/pclick.php?p=x',label:'Tickets bei Reservix',adLabel:'Anzeige'}]});
const heute=mk('partner:reservix:a','Heute 20 Uhr','2026-11-05T19:00:00+00:00',['2026-11-05T19:00:00+00:00'],4.8);
const morgen=mk('partner:reservix:b','Morgen 20 Uhr','2026-11-06T19:00:00+00:00',['2026-11-06T19:00:00+00:00'],7.5);
const spaet=mk('partner:reservix:c','Naechste Woche','2026-11-12T19:00:00+00:00',['2026-11-12T19:00:00+00:00'],3);
const fern=mk('partner:reservix:d','Zu weit weg','2026-11-05T19:00:00+00:00',['2026-11-05T19:00:00+00:00'],80);
let items=[heute,morgen,spaet,fern].map(x=>RPG.normalizeEvent(x,loc));
ok(items.every(x=>x.partnerItem&&Number.isFinite(x.distanceKm)),'Reservix-Karten werden normalisiert, Entfernung berechnet');
items=items.filter(x=>x.start&&Number.isFinite(x.distanceKm));
items=RPG.dedupeEventsForListing(items);
items=expand(items,now);
const radius=25, visible=items.filter(x=>x.distanceKm<=radius);
ok(visible.length===3&&!visible.some(x=>x.title==='Zu weit weg'),'Radius 25 km gilt wie in der Eventuebersicht');
const win=(m)=>visible.filter(x=>match(x,m,now)).map(x=>x.title).sort();
ok(JSON.stringify(win('today'))==='["Heute 20 Uhr"]','Heute noch: nur der heutige Termin, nicht morgen/spaeter: '+JSON.stringify(win('today')));
ok(JSON.stringify(win('tomorrow'))==='["Morgen 20 Uhr"]','Morgen: nur der morgige Termin: '+JSON.stringify(win('tomorrow')));
ok(win('now').length===0,'Jetzt: 20-Uhr-Termin noch nicht "jetzt"');
ok(/partnerItems:true/.test(views),'Startseite fragt Reservix-Karten an');
console.log('PASS '+checks+' Startseite/Reservix assertions');
