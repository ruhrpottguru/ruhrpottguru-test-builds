'use strict';
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict'),path=require('node:path');
const dir=process.argv[2]||path.join(__dirname,'../wordpress/themes/ruhrpott-guru/src/react-app');
const payload=JSON.parse(fs.readFileSync(path.join(__dirname,'../cityscope-release/integration-payload.json'),'utf8'));
const window={wp:{element:{createElement:(type,props,...children)=>({type,props:props||{},children}),Fragment:'fragment'}},ruhrpottSettings:{homeUrl:'https://www.pottfuchs.de/',root:'https://www.pottfuchs.de/wp-json/'},location:{origin:'https://www.pottfuchs.de',search:'',href:'https://www.pottfuchs.de/veranstaltungen/'},localStorage:{getItem:()=>null,setItem:()=>{}},sessionStorage:{getItem:()=>null,setItem:()=>{}}};
const cx=vm.createContext({window,document:{getElementById:()=>null},URL,URLSearchParams,Date,Intl,console,Set,Map,setTimeout,clearTimeout,navigator:{},fetch:()=>{throw Error('Unexpected HTTP')}});
for(const f of ['runtime.js','api.js'])vm.runInContext(fs.readFileSync(path.join(dir,f),'utf8'),cx);
const R=window.RPGReact,point={lat:51.435,lng:6.763,city:'Duisburg'};
let n=0;const ok=(v,m)=>{assert.ok(v,m);n++;};
for(const key of ['city','partial']){
 const item=R.normalizeEvent(payload[key],point);
 ok(item.cityScope===true,key+' cityScope');ok(item.coords===null,key+' no coordinates');
 ok(item.distanceKm===null,key+' no distance');ok(item.city==='Duisburg',key+' actual city');
 ok(item.title===payload[key].title,key+' unchanged title');
 ok(!item.imageUrl,key+' missing image retained');
}
const precise=R.normalizeEvent(payload.precise,point);
ok(precise.cityScope===false,'precise scope');ok(precise.coords.lat===51.435&&precise.coords.lng===6.763,'actual route coordinates');
ok(precise.distanceKm===0,'exact distance retained');
// Stale raw coordinates must not override authoritative cityScope flags.
const tainted=R.normalizeEvent({...payload.city,geo:{latitude:51.435,longitude:6.763},latitude:51.435,longitude:6.763},point);
ok(tainted.coords===null&&tainted.distanceKm===null,'city-only event cannot acquire raw centroid route or distance');
console.log('PASS '+n+' payload assertions using '+dir);
