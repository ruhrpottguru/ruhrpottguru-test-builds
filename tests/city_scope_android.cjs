'use strict';
// Pass a read-only checkout/export of Android app/src/main/assets/shared.
// Native source stays in its own repository; no copy is published here.
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict'),path=require('node:path');
if(!process.argv[2])throw Error('Android shared-source directory required');
const dir=process.argv[2];
const payload=JSON.parse(fs.readFileSync(path.join(__dirname,'../cityscope-release/integration-payload.json'),'utf8'));
const c={Date,URL,URLSearchParams,console,window:{wp:{element:{}},ruhrpottSettings:{root:'https://www.pottfuchs.de/wp-json/',homeUrl:'https://www.pottfuchs.de/',eventMediaDiversityEnabled:true},location:{href:'https://www.pottfuchs.de/',origin:'https://www.pottfuchs.de',hostname:'www.pottfuchs.de'}}};
vm.createContext(c);
for(const f of ['runtime','api','media-layout','location-helpers','category-food','category-events','native-adapter'])vm.runInContext(fs.readFileSync(path.join(dir,f+'.js'),'utf8'),c);
const n=c.window.NativeFood;
let checks=0;const ok=(v,m)=>{assert.ok(v,m);checks++;};
for(const [kind,raw] of Object.entries(payload)){
 n.reset('events');n.append([raw]);n.commit({lat:51.435,lng:6.763,city:'Duisburg'},5);
 const page=n.query('','','',99);ok(page.total===1,kind+' native listing visible');
 const item=page.items[0],detail=n.detail(item.id);
 ok(detail.title===raw.title,kind+' detail opens correct event');
 if(kind==='precise'){
  ok(detail.directions.includes('51.435%2C6.763'),'native route has real coordinates');
 }else{
  ok(detail.distance==='','unknown distance never rendered');
  ok(detail.directions==='','city-only event has no centroid route');
  ok(detail.coords===null,'native detail has no fabricated target');
 }
 ok(item.media.url==='','no image does not hide native card');
}
console.log('PASS '+checks+' native Android listing/detail/route assertions');
