const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict'),crypto=require('node:crypto');
const assets=__dirname+'/../app/src/main/assets/shared/';
const memory={};const context={console,URL,URLSearchParams,window:{wp:{element:{}},ruhrpottSettings:{root:'https://www.ruhrpottguru.de/wp-json/',homeUrl:'https://www.ruhrpottguru.de/',foodMediaUrl:'https://www.ruhrpottguru.de/wp-json/ruhrpottguru/v1/food-media'},location:{href:'https://www.ruhrpottguru.de/',origin:'https://www.ruhrpottguru.de',hostname:'www.ruhrpottguru.de'},localStorage:{getItem:k=>memory[k]||null,setItem:(k,v)=>memory[k]=v,removeItem:k=>delete memory[k]}}};
vm.createContext(context);for(const name of ['runtime','api','media-layout','location-helpers','category-food','native-adapter'])vm.runInContext(fs.readFileSync(assets+name+'.js','utf8'),context,{filename:name+'.js'});
const api=context.window.NativeFood,R=context.window.RPGReact;
const source=JSON.parse(fs.readFileSync(assets+'source-manifest.json'));
for(const name of ['runtime','api'])assert.equal(crypto.createHash('sha256').update(fs.readFileSync(assets+name+'.js')).digest('hex'),source.files[name+'.js']);
const rawViews=fs.readFileSync(__dirname+'/../shared-source/views.js','utf8');
const generated=fs.readFileSync(assets+'category-food.js','utf8');
const sortBegin=rawViews.indexOf('  function sortDistance(items){');
const sortEnd=rawViews.indexOf('\n\n  // V104:',sortBegin);
const filterBegin=rawViews.indexOf('    const filtered=s.items.filter(x=>{');
const filterEnd=rawViews.indexOf('\n    const visibleItems=',filterBegin);
assert.ok(generated.includes(rawViews.slice(sortBegin,sortEnd)));
assert.ok(generated.includes(rawViews.slice(filterBegin,filterEnd)));
for(const name of ['views','location'])assert.equal(crypto.createHash('sha256').update(fs.readFileSync(__dirname+'/../shared-source/'+name+'.js')).digest('hex'),source.files[name+'.js']);
assert.equal(api.metadata().length,53);assert.equal(api.metadata().find(x=>x.name==='Wesel').lat,51.6572);
const fixture=JSON.parse(fs.readFileSync(__dirname+'/fixtures/food-wesel-10km.json'));
assert.equal(fixture.ok,true);assert.ok(fixture.elements.length>100,'real backend candidates');
const point={lat:51.6572,lng:6.6186,city:'Wesel'};
const native=api;
function ingest(rows){native.reset();for(let i=0;i<rows.length;i+=80)native.append(rows.slice(i,i+80));native.commit(point,10);}
ingest(fixture.elements);
const baseline=context.window.rpgSourceSort(fixture.elements.map(x=>R.normalizePlace(x,'food',point)).filter(x=>Number.isFinite(x.distanceKm)&&x.distanceKm<=10));
let page=native.query('','','',24);
assert.equal(page.total,baseline.length);assert.equal(page.items.length,24);
assert.equal(JSON.stringify(page.items.map(x=>x.id)),JSON.stringify(baseline.slice(0,24).map(x=>x.id)));
let cases=0;
function compare(q,t,s){const types={restaurant:'Restaurant',fast_food:'Imbiss & Streetfood',cafe:'Café',ice_cream:'Eisdiele',bar:'Bar',pub:'Kneipe',biergarten:'Biergarten'};const selected=s?baseline.filter(x=>[types[x.tags.amenity]||x.subtitle,...R.categoryFilterTags(x)].includes(s)):baseline;const expected=context.window.rpgSourceFilter(selected,q,t,'');const result=native.query(q,t,s,1500);assert.equal(JSON.stringify(result.items.map(x=>x.id)),JSON.stringify(expected.map(x=>x.id)));assert.equal(result.total,expected.length);cases++;}
compare('','','');compare('pizza','','');compare('Wesel','','');compare('KEINE TREFFER 123','','');
for(const t of page.tags)compare('',t.slug,'');for(const t of page.subtypes)compare('','',t);
const detail=native.detail(baseline[0].id);assert.equal(detail.title,R.displayPlaceTitle(baseline[0]));assert.equal(detail.distance,R.formatDistance(baseline[0].distanceKm));
assert.equal(JSON.stringify(detail.labels.map(t=>t.label)),JSON.stringify(R.categoryFilterTags(baseline[0])));
// A place without media must remain discoverable and open normally.
const noPhoto={type:'node',id:999999999,tags:{name:'Foto-unabhängiger Ort','rg:subcategory':'Café','addr:city':'Wesel'},lat:point.lat,lon:point.lng};
ingest([noPhoto]);assert.equal(native.query('','','',24).total,1);assert.ok(native.detail('node:999999999'));
// A new location/load replaces the previous data, including empty results.
native.reset();native.append([]);native.commit({lat:51.5175,lng:7.1003,city:'Gelsenkirchen'});assert.equal(native.query('','','',24).total,0);
const postcode=JSON.parse(fs.readFileSync(__dirname+'/fixtures/postcode-45888.json'));assert.equal(postcode.ok,true);assert.equal(postcode.city,'Gelsenkirchen');assert.equal(postcode.postcode,'45888');
console.log(JSON.stringify({source:'live 2026-10-19',candidates:baseline.length,cities:53,filterParityCases:cases,tests:'PASS'}));

assert.equal(api.location(postcode,false).city,R.nearestCity(postcode.lat,postcode.lng));
assert.equal(api.location({lat:51.4,lng:6.8,city:'Muelheim an der Ruhr'},true).city,'Mülheim an der Ruhr');
assert.throws(()=>api.location({lat:52.5,lng:13.4,city:'Berlin'},true));
console.log('Location helper parity: PASS');

ingest(fixture.elements);
const originalIds=JSON.stringify(native.query('','','',1500).items.map(x=>x.id));
const wirtshaus=baseline.find(x=>x.title==='Weseler Wirtshaus');
const photo=native.detail(wirtshaus.id).media;
assert.equal(photo.url,wirtshaus.imageUrl);assert.ok(photo.url.includes('weseler-wirtshaus'));
assert.equal(photo.credit,wirtshaus.tags['rg:image_credit']);
const withGallery=baseline.find(x=>x.galleryImages && x.galleryImages.length>1);
if(withGallery)assert.equal(native.detail(withGallery.id).gallery.length,withGallery.galleryImages.length);
const missing=baseline.find(x=>!x.imageUrl);assert.ok(missing);
const request=native.mediaRequest(missing.id,'');
assert.equal(request.url,'https://www.ruhrpottguru.de/wp-json/ruhrpottguru/v1/food-media');
assert.equal(request.body.title,missing.title);assert.equal(request.body.category,'food');assert.equal(request.body.allowSymbolic,true);
const response={imageUrl:'https://images.example.org/place.jpg',credit:'Foto: Testautor',license:'Testlizenz',sourceUrl:'https://example.org/source'};
assert.equal(native.mediaResponse(missing.id,response,''),true);
assert.equal(native.detail(missing.id).media.url,response.imageUrl);
assert.equal(native.detail(missing.id).media.credit,response.credit);
assert.equal(JSON.stringify(native.query('','','',1500).items.map(x=>x.id)),originalIds);
assert.equal(native.mediaResponse(missing.id,response,response.imageUrl),false);
const layout=fs.readFileSync(assets+'media-layout.js','utf8');
const webComponents=fs.readFileSync(__dirname+'/../shared-source/components.js','utf8');
assert.ok(layout.includes(webComponents.slice(webComponents.indexOf('  function mediaCropValues(crop){'),webComponents.indexOf('  function mediaCropStyle('))));
assert.equal(context.window.rpgMediaCropValues({mode:'manual',x:10,y:90,zoom:1.5}).zoom,1.5);
console.log('Database photos, attribution, gallery, existing media resolver and unchanged place visibility/order: PASS');
