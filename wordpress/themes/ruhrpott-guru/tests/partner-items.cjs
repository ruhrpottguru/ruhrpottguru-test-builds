// V353.9: Reservix-Termine ohne eigenen Eintrag – Normalisierung, Termine, Chip, Anfrage-Fähigkeit, Sicherheitsregeln.
// Fixture = echte Ausgabe des Ticketpartner-Plugins 0.4.0 (Testumgebung, nicht Live-Daten).
const fs=require('fs'),vm=require('vm'),assert=require('assert'),path=require('path');
const dir=path.join(__dirname,'../src/react-app/');
const window={wp:{element:{createElement:(type,props,...children)=>({type,props:props||{},children}),Fragment:'fragment'}},ruhrpottSettings:{homeUrl:'https://www.pottfuchs.de/',root:'https://www.pottfuchs.de/wp-json/'},location:{origin:'https://www.pottfuchs.de',search:'',href:'https://www.pottfuchs.de/veranstaltungen/'},localStorage:{getItem:()=>null,setItem:()=>{}},sessionStorage:{getItem:()=>null,setItem:()=>{}}};
const calls=[];
const fetchStub=(url,opt)=>{calls.push(JSON.parse(opt.body));return Promise.resolve({ok:true,json:()=>Promise.resolve({ok:true,items:[]})});};
const context=vm.createContext({window,document:{getElementById:()=>null},URL,URLSearchParams,Date,Intl,console,Set,Map,setTimeout,clearTimeout,navigator:{},fetch:fetchStub});
for(const file of ['runtime.js','api.js'])vm.runInContext(fs.readFileSync(dir+file,'utf8'),context);
const RPG=window.RPGReact;let checks=0;const ok=(v,m)=>{assert(v,m);checks++;};
const items=JSON.parse(fs.readFileSync(path.join(__dirname,'fixtures/partner-items.json'),'utf8'));
const loc={lat:51.4556,lng:7.0116,city:'Essen'};
(async()=>{
  await RPG.api.events(loc,10);
  ok(calls[0].clientCaps.length===1&&calls[0].clientCaps[0]==='partner-v1','ohne Option nur partner-v1 (Orte, Startseite, Specials)');
  await RPG.api.events(loc,10,undefined,{partnerItems:true});
  ok(calls[1].clientCaps.includes('partner-v1')&&calls[1].clientCaps.includes('partner-items-v1'),'Hauptliste fragt zusaetzlich partner-items-v1');
  await RPG.api.events(loc,10,30);
  ok(calls[2].untilDays===30&&calls[2].clientCaps.length===1,'untilDays-Anfragen (Specials) ohne Reservix-Karten');

  const a=RPG.normalizeEvent(items[0],loc), b=RPG.normalizeEvent(items[1],loc);
  ok(a.kind==='event'&&a.category==='events'&&a.title==='Testshow Alpha','laeuft durch dieselbe Event-Normalisierung');
  ok(a.start instanceof Date&&!isNaN(a.start)&&a.coords&&Math.abs(a.coords.lat-51.4556)<1e-6,'Start (UTC-ISO) und Koordinaten wie bei eigenen Events');
  ok(Number.isFinite(a.distanceKm)&&a.distanceKm<0.1,'Entfernung wird wie bei eigenen Events berechnet');
  ok(a.cityScope===false,'kein City-Scope');
  ok(a.imageUrl==='https://cdn.reservix.com/core/img/event/detailEvent_1.jpg','Reservix-Bild wird als Eventbild verwendet');
  ok(b.imageUrl===''&&b.title==='Einzelkonzert','ohne Bild: Karte bleibt vorhanden (kein Ausblenden)');
  ok(a.partnerItem&&a.partnerItem.dates.length===20&&a.partnerItem.total===30,'20 Termine im Detail, Gesamtzahl 30');
  ok(a.partnerItem.dates.every(d=>d.start instanceof Date&&/^https:\/\/www\.awin1\.com\/pclick\.php/.test(d.url)),'jeder Termin mit eigenem AWIN-Link');
  ok(a.partnerItem.moreUrl===a.partnerItem.dates[0].url&&a.partnerItem.moreLabel==='Weitere Termine bei Reservix ansehen und buchen','Weitere-Termine-Link = naechster Termin');
  ok(a.partnerItem.adLabel==='Anzeige'&&a.partnerOffer&&a.partnerOffer.adLabel==='Anzeige','Anzeige-Kennzeichnung');
  ok(!a.ticketUrl&&!a.website&&!b.ticketUrl,'Partner-Links werden nie zu eigenem Ticketlink oder Event-Webseite');
  ok(a.series&&a.series.count===30&&a.series.nearTerm.length===2&&a.series.laterSeries&&a.series.laterSeries.start,'Serienfelder im Format der eigenen Events');
  ok(a.sourceName==='Reservix'&&/Anzeige/.test(a.sourceAttribution),'Quelle/Attribution Reservix + Anzeige');
  ok(a.mediaLabel===''&&a.mediaKind==='original','kein Symbolfoto-/Kontext-Label, Bild ist Original');

  const tagsA=RPG.categoryFilterTags(a),tagsB=RPG.categoryFilterTags(b);
  ok(tagsA.includes('Reservix')&&tagsB.includes('Reservix'),'Chip "Reservix" unter "Mehr davon entdecken"');
  ok(tagsA.length<=6,'Chip-Liste bleibt beschraenkt');
  ok(RPG.tagSlug('Reservix')==='reservix','Filter-Kennung reservix');
  const own=RPG.normalizeEvent({id:'e1',title:'Eigenes Konzert',venueName:'Halle',city:'Essen',lat:51.45,lng:7.01,start:'2026-12-01T19:00:00+00:00'},loc);
  ok(!RPG.categoryFilterTags(own).includes('Reservix'),'eigene Events ohne Reservix-Angebot bekommen keinen Reservix-Chip');
  const ownWith=RPG.normalizeEvent({id:'e2',title:'Eigenes Konzert 2',venueName:'Halle',city:'Essen',lat:51.45,lng:7.01,start:'2026-12-01T19:00:00+00:00',rpg_partner_offers:[{provider:'reservix',url:'https://www.awin1.com/pclick.php?p=1',label:'Tickets bei Reservix',adLabel:'Anzeige'}]},loc);
  ok(RPG.categoryFilterTags(ownWith).includes('Reservix')&&!ownWith.partnerItem,'eigenes Event mit Reservix-Angebot: Chip ja, aber keine Reservix-Karte/Anzeige auf der Kachel');
  const listed=RPG.dedupeEventsForListing([a,b,own]);
  ok(listed.length===3,'Duplikatpruefung der Liste behaelt alle Karten');
  const tagFilter=[a,b,own,ownWith].filter(x=>RPG.categoryFilterTags(x).some(l=>RPG.tagSlug(l)==='reservix'));
  ok(tagFilter.length===3&&tagFilter.includes(a)&&tagFilter.includes(ownWith),'Filter "Reservix" = alle Events mit Reservix-Angebot, gleiche Listenlogik');

  const bad=(arr)=>RPG.normalizeEvent(Object.assign({},items[0],{rpg_partner_item:Object.assign({},items[0].rpg_partner_item,arr)}),loc).partnerItem;
  const good=items[0].rpg_partner_item.dates[0];
  ok(bad({dates:[{start:good.start,url:'http://www.awin1.com/x'}]})===null,'http-Termin wird verworfen');
  ok(bad({dates:[{start:good.start,url:'javascript:alert(1)'}]})===null,'javascript: wird verworfen');
  ok(bad({dates:[{start:'kein datum',url:good.url}]})===null,'ungueltiges Datum wird verworfen');
  ok(bad({dates:[]})===null&&bad({dates:'x'})===null,'leere/falsche Terminliste -> kein Block');
  ok(bad({dates:new Array(50).fill(good)}).dates.length===20,'hoechstens 20 Termine, auch wenn mehr geliefert werden');
  ok(bad({moreUrl:'http://x.example/'}).moreUrl==='','Weitere-Termine-Link nur mit https');

  const comp=fs.readFileSync(dir+'components.js','utf8'),views=fs.readFileSync(dir+'views.js','utf8'),apis=fs.readFileSync(dir+'api.js','utf8');
  ok(comp.includes("data-rpg-partner-dates")&&comp.includes("rel:'sponsored noopener noreferrer',onClick:()=>metric('ticket_click',item,'detail-partner-date')"),'Terminliste im Detail mit rel=sponsored und eigener Messung');
  ok((comp.match(/rpgx-event-media-kind is-partner/g)||[]).length===2,'Anzeige-Schild auf beiden Kartenarten (Liste und Startseiten-Karte)');
  ok(views.includes('{partnerItems:true}')&&!views.includes('{partnerItems:!home}'),'Reservix-Karten in Veranstaltungsliste UND Startseite (353.10.3)');
  ok(apis.includes("item.raw.rpg_partner_item")&&apis.includes("k==='rpg_partner_offers'||k==='rpg_partner_item'"),'keine Live-Bildsuche je Reservix-Karte; Partnerdaten nicht als eigener Ticketlink');
  // Aufruf in views.js wirklich auswerten (Fehler 353.9: Klammer falsch gesetzt -> Option kam nie an)
  const line=views.split('\n').find(l=>l.includes('await RPG.api.events('));
  const expr=line.slice(line.indexOf('await RPG.api.events(')+'await '.length).replace(/\)\);\s*$/,')');
  const run=(home,mode)=>{let got=null;const API={events:(...a)=>{got=a;return 0;}};const RPGx={api:API};
    new Function('RPG','location','eventFetchRadius','mode','home','martinUntilDays','christmasUntilDays','undef','return '+expr.replace('undefined','undefined'))(RPGx,loc,10,mode,home,()=>7,()=>9);return got;};
  let g=run(false,'');ok(g[2]===undefined&&g[3]&&g[3].partnerItems===true,'Veranstaltungsliste: 3. Parameter leer, 4. Parameter {partnerItems:true}');
  g=run(true,'');ok(g[2]===undefined&&g[3].partnerItems===true,'Startseite: gleiche Reservix-Anfrage wie die Veranstaltungsliste (353.10.3)');
  g=run(false,'martin');ok(g[2]===7&&g[3].partnerItems===true,'Martin: untilDays bleibt erhalten');
  g=run(false,'weihnachten');ok(g[2]===9&&g[3].partnerItems===true,'Weihnachten: untilDays bleibt erhalten');
  console.log('PASS '+checks+' Reservix-Karten assertions');
})().catch(e=>{console.error(e);process.exit(1);});
