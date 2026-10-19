const fs=require('fs'),vm=require('vm'),assert=require('assert');
const dir=require('path').join(__dirname,'../src/react-app/');
const data={}; const document={getElementById:id=>data[id]?{textContent:JSON.stringify(data[id])}:null};
const window={wp:{element:{createElement:(type,props,...children)=>({type,props:props||{},children}),Fragment:'fragment'}},ruhrpottSettings:{homeUrl:'https://www.ruhrpottguru.de/',root:'https://www.ruhrpottguru.de/wp-json/'},location:{origin:'https://www.ruhrpottguru.de',search:'',href:'https://www.ruhrpottguru.de/kultur/'},localStorage:{getItem:()=>null,setItem:()=>{}},sessionStorage:{getItem:()=>null,setItem:()=>{}}};
const context=vm.createContext({window,document,URL,URLSearchParams,Date,Intl,console,Set,Map,setTimeout,clearTimeout,navigator:{},fetch:()=>{throw Error('Unexpected HTTP')}});
for(const file of ['runtime.js','api.js'])vm.runInContext(fs.readFileSync(dir+file,'utf8'),context);
const RPG=window.RPGReact; let checks=0;
const ok=(value,message)=>{assert(value,message);checks++};
const place=(category,title,subtitle,tags={})=>RPG.normalizePlace({type:'node',id:title,lat:51.456,lon:7.011,tags:{name:title,'addr:city':'Essen','rg:subcategory':subtitle,...tags}},category,{lat:51.456,lng:7.011,city:'Essen'});
const fixtures=[
place('culture','Stratmanns Theater','Theater & Bühne',{'rg:culture_tags':'Theater|Bekannter Ort',amenity:'theatre'}),
place('culture','Grillo-Theater','Theater & Bühne',{'rg:culture_tags':'Theater',amenity:'theatre'}),
place('highlights','Baedeker-Haus','Highlights & Ausflugsziele',{'rg:highlight_tags':'Besonderer Ort|Fotospot'}),
place('highlights','Wasserturm','Landmarken & Architektur',{'rg:highlight_tags':'Fotospot'}),
place('food','Hans im Glück','Gastronomie',{cuisine:'burger',outdoor_seating:'yes'}),
place('food','Anderer Burgergrill','Gastronomie',{cuisine:'burger'}),
place('activities','RuhrEscape Essen','Games & Gruppen',{'rg:experience_tags':'Escape Room'}),
place('activities','RuhrEscape','Games & Gruppen',{'rg:experience_tags':'Escape Room'}),
RPG.normalizeEvent({id:'e1',title:'ALIVE',venueName:'GOP Varieté-Theater Essen',city:'Essen',geo:{latitude:51.456,longitude:7.011},rpg_event_details:{info:{categories:['Theater','Musical']}}},null),
RPG.normalizeEvent({id:'e2',title:'Andere Show',venueName:'Anderes Theater',rpg_event_details:{info:{categories:['Theater']}}},null)
];
const views=fs.readFileSync(dir+'views.js','utf8');
const start=views.indexOf('const filtered=s.items.filter(x=>{'),end=views.indexOf('\n    });',start);
const predicateSource=views.slice(start,end).split('filter(x=>{')[1];
const predicate=new Function('RPG','category','q','sub','tagFilter','dateFrom','dateTo','eventOverlapsDateRange','x',predicateSource);
const filtered=(category,tag,q='',sub='')=>fixtures.filter(x=>x.category===category).filter(x=>predicate(RPG,category,q,sub,RPG.tagSlug(tag),'','',()=>true,x));
const section=fs.readFileSync(dir+'components.js','utf8').split('\n').find(line=>line.includes("tags.length?div({className:'rpgx-detail-tag-section'}"));
const sectionExpression=section.trim().replace(/,$/,'');
const h=window.wp.element.createElement;
const renderChips=new Function('tags','tagFilterActive','onTagFilter','item','categoryTitle','div','h','button','span','return '+sectionExpression);
const collect=(node,result=[])=>{if(!node)return result;if(Array.isArray(node)){node.forEach(n=>collect(n,result));return result;}if(node.type==='button')result.push(node);(node.children||[]).forEach(n=>collect(n,result));return result;};
const main=fs.readFileSync(dir+'main.js','utf8');
const handlerSource=main.slice(main.indexOf('const applyDetailTagFilter='),main.indexOf('\n    return h(RPG.Fragment',main.indexOf('const applyDetailTagFilter=')));
const handlerFactory=new Function('RPG','window','loc','categoryKey','setDetail','CustomEvent','const directBoot={};const shareBoot={};'+handlerSource+';return applyDetailTagFilter;');
for(const item of fixtures){
 const tags=RPG.categoryFilterTags(item);ok(tags.length>0,item.category+' chips exist');
 const buttons=collect(renderChips(tags,true,()=>{},item,RPG.CATEGORIES[item.category].title,(p,...c)=>h('div',p,...c),h,(p,...c)=>h('button',p,...c),(p,...c)=>h('span',p,...c)));
 ok(buttons.length===tags.length,'all chips are buttons');
 for(let i=0;i<buttons.length;i++){
  let clicked=null;const b=collect(renderChips(tags,true,(row,label)=>{clicked=label},item,'Category',(p,...c)=>h('div',p,...c),h,(p,...c)=>h('button',p,...c),(p,...c)=>h('span',p,...c)))[i];b.props.onClick();
  ok(clicked===tags[i],'actual button callback label');
  ok(filtered(item.category,clicked).some(x=>x.id===item.id),'clicked chip matches source item');
  let event=null,closed=false,assigned='';
  const win={dispatchEvent:e=>event=e,location:{assign:url=>assigned=url}};
  const handler=handlerFactory(RPG,win,{location:{city:'Essen'},radius:5},item.category,()=>closed=true,function(type,options){this.type=type;this.detail=options.detail});handler(item,clicked);
  ok(closed&&event&&event.detail.slug===RPG.tagSlug(clicked),'same category closes and dispatches exact slug');
  const other=handlerFactory(RPG,win,{location:{city:'Essen'},radius:5},null,()=>{},function(){});other(item,clicked);
  const u=new URL(assigned);ok(u.searchParams.get('rg_tag')===RPG.tagSlug(clicked)&&u.searchParams.get('radius')==='5','Home/detail navigation preserves radius and filter');
 }
}
ok(filtered('culture','Stratmanns Theater').length===1,'location chip excludes Grillo');
ok(filtered('culture','Theater').length===2,'type chip finds both theatres');
ok(filtered('highlights','Baedeker-Haus').length===1,'highlight name chip');
ok(filtered('highlights','Fotospot').length===2,'highlight logical facet');
ok(filtered('events','GOP Varieté-Theater Essen').length===1,'event venue filter');
ok(filtered('events','Theater').length===2,'event type filter');
ok(filtered('food','Burger').length===2,'Food regression');
ok(filtered('activities','Escape Room').length===2,'Activities regression');
for(const category of Object.keys(RPG.CATEGORIES)){
 ok(filtered(category,'nonexistent-facet').length===0,'empty result '+category);
 ok(filtered(category,'').length===2,'clear filter restores '+category);
 ok(filtered(category,'','impossible-search').length===0,'search still intersects '+category);
}
const many=place('culture','Haus mit vielen Labels','Theater & Bühne',{'rg:culture_tags':'A|B|C|D|E|F|G'});
ok(RPG.categoryFilterTags(many)[0]===many.title&&RPG.categoryFilterTags(many)[1]===many.subtitle,'name/type survive cap');
ok(RPG.categoryFilterTags(many).length===6,'bounded list');
ok(!RPG.categoryFilterTags(place('culture','Unbenannter Ort','Theater & Bühne')).includes('Unbenannter Ort'),'no fake location');
ok(RPG.categoryFilterTags(null).length===0,'null safe');
ok(RPG.categoryFilterTags({category:'unsupported'}).length===0,'unknown category safe');
ok(!RPG.categoryFilterTags(fixtures[8]).some(x=>/Std|Min/.test(x)),'no duration badge in discovery chips');
data['ruhrpott-footer-menu-data']=[{category:'food',url:'https://www.ruhrpottguru.de/custom-food/?existing=1'},{category:'activities',url:'https://www.ruhrpottguru.de/custom-aktiv/'}];
ok(new URL(RPG.categoryTagUrl('food','Burger',25)).pathname==='/custom-food/','actual WP menu target');
ok(new URL(RPG.categoryTagUrl('food','Burger',25)).searchParams.get('existing')==='1','target parameters preserved');
ok(new URL(RPG.categoryTagUrl('activities','Escape Room',2.5)).searchParams.get('radius')==='2.5','fractional radius');
delete data['ruhrpott-footer-menu-data'];
ok(new URL(RPG.categoryTagUrl('food','Burger',5)).pathname==='/food/','correct Food fallback');
ok(new URL(RPG.categoryTagUrl('activities','Escape Room',5)).pathname==='/aktiv/','correct Activities fallback');
console.log('PASS '+checks+' functional assertions: normalization → real chip expression → real click handler → URL → real category predicate; empty cases and adjacent regressions.');
