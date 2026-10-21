const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const assets=__dirname+'/../app/src/main/assets/shared/';
const c={console,URL,URLSearchParams,window:{wp:{element:{}},ruhrpottSettings:{root:'https://www.ruhrpottguru.de/wp-json/',homeUrl:'https://www.ruhrpottguru.de/'},location:{href:'https://www.ruhrpottguru.de/',origin:'https://www.ruhrpottguru.de',hostname:'www.ruhrpottguru.de'}}};
vm.createContext(c);for(const n of ['runtime','api','media-layout','category-food','native-adapter'])vm.runInContext(fs.readFileSync(assets+n+'.js','utf8'),c);
const R=c.window.RPGReact,N=c.window.NativeFood,raw=JSON.parse(fs.readFileSync(__dirname+'/fixtures/food-wesel-10km.json')).elements;
for(const [point,km] of [[{lat:51.6572,lng:6.6186,city:'Wesel'},5],[{lat:51.6572,lng:6.6186,city:'Wesel'},10],[{lat:51.4556,lng:7.0116,city:'Essen'},5]]){
 N.reset('food');N.append(raw);N.commit(point,km);
 const expected=c.window.rpgSourceSort(raw.map(x=>R.normalizePlace(x,'food',point)).filter(x=>Number.isFinite(x.distanceKm)&&x.distanceKm<=km));
 const page=N.query('','','',9999);assert.equal(JSON.stringify(page.items.map(x=>x.id)),JSON.stringify(expected.map(x=>x.id)));
 if(point.city==='Wesel'){
  assert.ok(page.subtypes.includes('Restaurant'));
  const restaurants=N.query('','','Restaurant',9999).items;
  assert.ok(restaurants.length>0);assert.ok(restaurants.every(x=>expected.find(y=>y.id===x.id).tags.amenity==='restaurant'));
 }
 console.log(point.city,km,'km:',page.total,'within radius out of',raw.length);
}
const point={lat:51.6572,lng:6.6186};const row=(id,lat,lon)=>({id,type:'node',lat,lon,tags:{name:'Ort '+id,amenity:'restaurant'}});
N.reset('food');N.append([row(1,point.lat,point.lng),row(2,52,7),row(3,undefined,undefined)]);N.commit(point,5);
assert.equal(N.query('','','',99).total,1,'outside/unknown distances never enter radius results');
N.reset('food');N.append(raw);N.commit(point,0.01);assert.equal(N.query('','','',99).total,0,'no implicit wider-radius fallback');
console.log('Strict radius, changed location, restaurant facet and empty-radius regression: PASS');
