(function(){
'use strict';
function boot(){
const appRoot=document.getElementById('ruhrpott-guru-root');
if(!appRoot)return false;
const root=appRoot.querySelector('[data-rg-night]');
// Never bind the hidden WordPress source copy. PlainWpView clones that HTML into
// #ruhrpott-guru-root; only the rendered clone is interactive.
if(!root)return false;
if(root.dataset.rgNightBound==='1')return true;
root.dataset.rgNightBound='1';
const scope=root.closest('.rpgx-wp-page')||root.parentElement||document;
const settings=window.ruhrpottSettings||{};
const RPG=window.RPGReact||{};
const endpoint=settings.nightUrl||'/wp-json/ruhrpottguru/v1/places/night';
const submitUrl=settings.nightSubmitUrl||'/wp-json/ruhrpottguru/v1/places/night/submit';
const nonce=settings.nonce||'';
const locEl=root.querySelector('[data-rg-night-location]');
const locationChange=root.querySelector('[data-rg-night-location-change]');
const radiusBtns=[...root.querySelectorAll('[data-rg-night-radius]')];
const statusEl=root.querySelector('[data-rg-night-status]');
const grid=root.querySelector('[data-rg-night-grid]');
const filterBtns=[...root.querySelectorAll('[data-night-filter]')];
const locationModal=scope.querySelector('[data-rg-night-location-modal]');
const locationClose=locationModal&&locationModal.querySelector('[data-rg-night-location-close]');
const gpsBtn=locationModal&&locationModal.querySelector('[data-rg-night-gps]');
const cityGrid=locationModal&&locationModal.querySelector('[data-rg-night-city-grid]');
const radiusSelect=locationModal&&locationModal.querySelector('[data-rg-night-radius-select]');
const radiusApply=locationModal&&locationModal.querySelector('[data-rg-night-radius-apply]');
const locationError=locationModal&&locationModal.querySelector('[data-rg-night-location-error]');

const uploadModal=scope.querySelector('[data-rg-night-modal]');
const uploadClose=uploadModal&&uploadModal.querySelector('[data-rg-night-close]');
const uploadPlace=uploadModal&&uploadModal.querySelector('[data-rg-night-modal-place]');
const placeInput=uploadModal&&uploadModal.querySelector('[data-rg-night-place-id]');
const form=uploadModal&&uploadModal.querySelector('[data-rg-night-upload-form]');
const formStatus=uploadModal&&uploadModal.querySelector('[data-rg-night-form-status]');

const detailModal=scope.querySelector('[data-rg-night-detail]');
const detailClose=detailModal&&detailModal.querySelector('[data-rg-night-detail-close]');
const detailHero=detailModal&&detailModal.querySelector('[data-rg-night-detail-hero]');
const detailTitle=detailModal&&detailModal.querySelector('[data-rg-night-detail-title]');
const detailCount=detailModal&&detailModal.querySelector('[data-rg-night-detail-count]');
const detailMeta=detailModal&&detailModal.querySelector('[data-rg-night-detail-meta]');
const detailReason=detailModal&&detailModal.querySelector('[data-rg-night-detail-reason]');
const detailAccess=detailModal&&detailModal.querySelector('[data-rg-night-detail-access]');
const detailGallery=detailModal&&detailModal.querySelector('[data-rg-night-detail-gallery]');
const detailCredit=detailModal&&detailModal.querySelector('[data-rg-night-detail-credit]');
const detailUpload=detailModal&&detailModal.querySelector('[data-rg-night-detail-upload]');
const detailWebsite=detailModal&&detailModal.querySelector('[data-rg-night-detail-website]');

let allItems=[],activeFilter='all',location=null,effectiveRadius=0,radius=5,currentDetail=null,loadSeq=0,loadAbort=null;
const SNAPSHOT='ruhrpottguru_location_snapshot_v2';
const PREF='ruhrpottguru_location_preference_v1';
const GPS='ruhrpottguru_last_gps_v1';
const RADIUS='ruhrpottguru_radius_km_v1';
const CITY_COOKIE='rg_location_city_v1';

function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));}
function centers(){return RPG.CITY_CENTERS||{};}
function nearestCity(lat,lng){return typeof RPG.nearestCity==='function'?RPG.nearestCity(lat,lng):'';}
function readCookieCity(){try{const hit=document.cookie.split(';').map(x=>x.trim()).find(x=>x.startsWith(CITY_COOKIE+'='));return hit?decodeURIComponent(hit.slice(CITY_COOKIE.length+1)):'';}catch(_){return '';}}
function readLocation(){
  try{const getter=(RPG.storage&&typeof RPG.storage.get==='function')?RPG.storage.get.bind(RPG.storage):localStorage.getItem.bind(localStorage);const x=JSON.parse(getter(SNAPSHOT)||'null');if(x&&Number.isFinite(Number(x.lat))&&Number.isFinite(Number(x.lng)))return {lat:Number(x.lat),lng:Number(x.lng),city:x.city||nearestCity(Number(x.lat),Number(x.lng)),name:x.name||'',isGPS:!!x.isGPS,accuracyMeters:x.accuracyMeters||null};}catch(_){}
  try{const city=readCookieCity(),c=centers()[city];if(city&&c)return {lat:Number(c[0]),lng:Number(c[1]),city,name:city+' (Zentrum)',isGPS:false};}catch(_){}
  return null;
}
function savedRadius(){const getter=(RPG.storage&&typeof RPG.storage.get==='function')?RPG.storage.get.bind(RPG.storage):localStorage.getItem.bind(localStorage);const r=Number(getter(RADIUS)||5);return Number.isFinite(r)&&r>=1&&r<=50?r:5;}
function writeCookieCity(city){if(!city)return;try{const host=String(window.location.hostname||'').toLowerCase();const domain=(host==='pottfuchs.de'||host.endsWith('.pottfuchs.de'))?'; Domain=.pottfuchs.de':((host==='ruhrpottguru.de'||host.endsWith('.ruhrpottguru.de'))?'; Domain=.ruhrpottguru.de':'');document.cookie=CITY_COOKIE+'='+encodeURIComponent(city)+'; Path=/; Max-Age=31536000; SameSite=Lax'+domain;}catch(_){}}
function storeLocation(loc){if(!loc)return;const snapshot={name:loc.name||'',city:loc.city||nearestCity(loc.lat,loc.lng),lat:Number(loc.lat),lng:Number(loc.lng),accuracyMeters:loc.accuracyMeters||null,isGPS:!!loc.isGPS,savedAt:Date.now()};try{const setter=(RPG.storage&&typeof RPG.storage.set==='function')?RPG.storage.set.bind(RPG.storage):localStorage.setItem.bind(localStorage);setter(SNAPSHOT,JSON.stringify(snapshot));writeCookieCity(snapshot.city);if(snapshot.isGPS){setter(PREF,JSON.stringify({mode:'gps'}));setter(GPS,JSON.stringify({lat:snapshot.lat,lng:snapshot.lng,accuracyMeters:snapshot.accuracyMeters,savedAt:Date.now()}));setter('ruhrpottguru_gps_granted','true');}else{setter(PREF,JSON.stringify({mode:'manual',location:{name:snapshot.name,lat:snapshot.lat,lng:snapshot.lng}}));}}catch(_){}}
function setRadius(value,reload){const n=Math.max(1,Math.min(50,Number(value)||5));radius=n;try{if(RPG.storage&&typeof RPG.storage.set==='function')RPG.storage.set(RADIUS,String(n));else localStorage.setItem(RADIUS,String(n));}catch(_){}if(radiusSelect)radiusSelect.value=String(n);radiusBtns.forEach(b=>b.classList.toggle('active',Number(b.dataset.rgNightRadius)===n));if(reload&&location)load();}
function locationLabel(){return location?(location.city||location.name||'Standort'):'Standort wählen';}
function updateLocationUi(){if(locEl)locEl.textContent=locationLabel();setRadius(radius,false);}
function openLocation(){if(!locationModal)return;if(locationError){locationError.hidden=true;locationError.textContent='';}if(radiusSelect)radiusSelect.value=String(radius);locationModal.hidden=false;document.documentElement.classList.add('rpgx-modal-open');document.body.classList.add('rpgx-modal-open');}
function closeLocation(){if(!locationModal||!location)return;locationModal.hidden=true;document.documentElement.classList.remove('rpgx-modal-open');document.body.classList.remove('rpgx-modal-open');}
function showLocationError(msg){if(!locationError)return;locationError.textContent=msg;locationError.hidden=false;}
function chooseCity(city){const c=centers()[city];if(!c)return;location={name:city+' (Zentrum)',city,lat:Number(c[0]),lng:Number(c[1]),isGPS:false};storeLocation(location);updateLocationUi();if(locationModal)locationModal.hidden=true;document.documentElement.classList.remove('rpgx-modal-open');document.body.classList.remove('rpgx-modal-open');load();}
function buildCityGrid(){if(!cityGrid)return;const cities=Object.keys(centers()).sort((a,b)=>a.localeCompare(b,'de'));cityGrid.innerHTML='';cities.forEach(city=>{const b=document.createElement('button');b.type='button';b.className='rpgx-city-btn';b.textContent=city;b.addEventListener('click',()=>chooseCity(city));cityGrid.appendChild(b);});}
function tagLabel(t){return ({industrial:'Industriekultur',view:'Aussicht',light:'Licht & Kulisse',photo:'Nachtfoto',water:'Wasser',architecture:'Architektur'})[t]||t;}
function accessText(x){if(x==='verified_open')return '✓ Nachtzugang redaktionell verifiziert';if(x==='restricted')return '⚠ Zugang nachts eingeschränkt';return 'Zugänglichkeit nachts nicht verifiziert';}
function photoCountLabel(n){return n===1?'1 Nachtfoto':n+' Nachtfotos';}

function render(){
  let items=allItems;if(activeFilter==='photo')items=items.filter(x=>x.imageKind==='night'||Number(x.nightPhotoCount||0)>0);else if(activeFilter!=='all')items=items.filter(x=>(x.nightTags||[]).includes(activeFilter));
  statusEl.textContent=items.length+' Nacht-Tipp'+(items.length===1?'':'s')+' · Umkreis bis '+effectiveRadius+' km'+(location&&location.city?' · '+location.city:'');
  if(!items.length){grid.innerHTML='<div class="rg-night-empty">Für diesen Filter gibt es im aktuellen Umkreis noch keinen passenden Nacht-Tipp.</div>';return;}
  grid.innerHTML=items.map(x=>{
    const hasNight=x.imageKind==='night';
    const image=x.imageUrl?'<img loading="lazy" src="'+esc(x.imageUrl)+'" alt="'+esc(x.name)+'">':'';
    const tags=(x.nightTags||[]).filter(t=>t!=='photo'||hasNight||Number(x.nightPhotoCount||0)>0).slice(0,4).map(t=>'<span class="rg-night-tag">'+esc(tagLabel(t))+'</span>').join('');
    const count=Number(x.nightPhotoCount||0);
    return '<article class="rg-night-card">'+
      '<button type="button" class="rg-night-card-open" data-night-detail="'+x.id+'" aria-label="'+esc(x.name)+' bei Nacht ansehen"><div class="rg-night-image">'+image+'<span class="rg-night-image-badge '+(hasNight?'':'is-day')+'">'+(hasNight?'🌙 echtes Nachtfoto':'☀ Tagesbild · Nachtfoto gesucht')+'</span><span class="rg-night-score">'+Math.round(Number(x.nightScore)||0)+'%</span>'+(count?'<span class="rg-night-photo-count">'+esc(photoCountLabel(count))+'</span>':'')+'</div></button>'+
      '<div class="rg-night-card-body"><button type="button" class="rg-night-title-button" data-night-detail="'+x.id+'"><h3>'+esc(x.name)+'</h3></button><div class="rg-night-meta">'+esc(x.city||'Ruhrgebiet')+' · '+Number(x.distanceKm||0).toLocaleString('de-DE',{maximumFractionDigits:1})+' km</div><p class="rg-night-reason">'+esc(x.nightReason||'Besonderer Ort für den Abend.')+'</p><div class="rg-night-tags">'+tags+'</div><div class="rg-night-access">'+esc(accessText(x.accessStatus))+'</div><div class="rg-night-card-actions"><button class="rg-night-detail-btn" type="button" data-night-detail="'+x.id+'">🌙 Ansehen</button><button class="rg-night-upload" type="button" data-night-upload="'+x.id+'" data-night-name="'+esc(x.name)+'">📷 Foto</button></div></div></article>';
  }).join('');
  grid.querySelectorAll('[data-night-upload]').forEach(btn=>btn.addEventListener('click',e=>{e.stopPropagation();openUpload(btn.dataset.nightUpload,btn.dataset.nightName);}));
  grid.querySelectorAll('[data-night-detail]').forEach(btn=>btn.addEventListener('click',()=>{const id=Number(btn.dataset.nightDetail);const item=allItems.find(x=>Number(x.id)===id);if(item)openDetail(item);}));
}

async function request(r,signal){const u=new URL(endpoint,window.location.origin);u.searchParams.set('lat',location.lat);u.searchParams.set('lng',location.lng);u.searchParams.set('radiusKm',r);u.searchParams.set('limit','60');const res=await fetch(u.toString(),{credentials:'same-origin',signal});if(!res.ok)throw new Error('HTTP '+res.status);const j=await res.json();if(!j||!j.ok)throw new Error('Ungültige Antwort');return j;}
async function load(){const seq=++loadSeq;if(loadAbort)try{loadAbort.abort();}catch(_){}loadAbort=new AbortController();const timeout=setTimeout(()=>{try{loadAbort.abort();}catch(_){}},20000);updateLocationUi();if(!location){clearTimeout(timeout);statusEl.textContent='Wähle zuerst deinen Standort.';grid.innerHTML='';try{window.dispatchEvent(new Event('rpg:open-location'));}catch(_){}return;}statusEl.textContent='Nachtergebnisse werden geladen …';grid.innerHTML='<div class="rg-night-empty">Wir suchen besondere Nachtorte rund um deinen Standort …</div>';const first=Math.max(1,Math.min(50,radius));const chain=[first,5,10,25,50].filter((v,i,a)=>v>=first&&a.indexOf(v)===i).sort((a,b)=>a-b);try{let data=null;for(const r of chain){data=await request(r,loadAbort.signal);if(seq!==loadSeq)return;effectiveRadius=r;if(data.elements&&data.elements.length)break;}if(seq!==loadSeq)return;allItems=(data&&data.elements)||[];render();}catch(e){if(seq!==loadSeq)return;if(e&&e.name==='AbortError'){statusEl.textContent='Die Nachtsuche hat zu lange gedauert.';grid.innerHTML='<div class="rg-night-empty">Bitte Radius oder Standort ändern und erneut versuchen.</div>';}else{statusEl.textContent='Nachtergebnisse konnten gerade nicht geladen werden.';grid.innerHTML='<div class="rg-night-empty">Bitte versuche es später noch einmal.</div>';}}finally{clearTimeout(timeout);}}

function lockPage(on){document.body.style.overflow=on?'hidden':'';}
function openUpload(id,name){if(!uploadModal)return;if(form)form.reset();placeInput.value=id;uploadPlace.textContent=name||'Ausgewählte Location';if(formStatus){formStatus.textContent='';formStatus.classList.remove('is-error','is-success');}uploadModal.hidden=false;lockPage(true);}
function closeUpload(){if(!uploadModal)return;uploadModal.hidden=true;if(!detailModal||detailModal.hidden)lockPage(false);}

function setDetailHero(url,name,credit){if(!detailHero)return;if(url){detailHero.innerHTML='<img src="'+esc(url)+'" alt="'+esc(name)+'">';}else{detailHero.innerHTML='<div class="rg-night-detail-placeholder">Noch kein freigegebenes Nachtfoto.</div>';}if(detailCredit)detailCredit.textContent=credit||'';}
function renderGallery(photos,item){
  if(!detailGallery)return;
  if(!photos.length){detailGallery.innerHTML='<div class="rg-night-empty">Für diese Location ist noch kein freigegebenes Nachtfoto vorhanden. Du kannst das erste einsenden.</div>';if(item&&item.imageUrl)setDetailHero(item.imageUrl,item.name,'Tagesbild als Vorschau');return;}
  detailGallery.innerHTML=photos.map((p,i)=>'<button type="button" class="rg-night-thumb '+(i===0?'is-active':'')+'" data-night-photo-index="'+i+'"><img src="'+esc(p.url)+'" alt="Nachtfoto '+(i+1)+' von '+esc(item.name)+'"></button>').join('');
  const activate=i=>{const p=photos[i];if(!p)return;setDetailHero(p.url,item.name,p.credit||p.artist||'');detailGallery.querySelectorAll('.rg-night-thumb').forEach((b,j)=>b.classList.toggle('is-active',j===i));};
  detailGallery.querySelectorAll('[data-night-photo-index]').forEach(btn=>btn.addEventListener('click',()=>activate(Number(btn.dataset.nightPhotoIndex))));activate(0);
}
async function openDetail(item){
  if(!detailModal)return;currentDetail=item;detailTitle.textContent=item.name||'Location';detailCount.textContent=photoCountLabel(Number(item.nightPhotoCount||0));detailMeta.textContent=(item.address||item.city||'Ruhrgebiet')+' · '+Number(item.distanceKm||0).toLocaleString('de-DE',{maximumFractionDigits:1})+' km';detailReason.textContent=item.nightReason||'Besonderer Ort für den Abend.';detailAccess.textContent=accessText(item.accessStatus);if(detailWebsite){detailWebsite.hidden=!item.website;if(item.website)detailWebsite.href=item.website;}if(detailUpload){detailUpload.dataset.placeId=item.id;detailUpload.dataset.placeName=item.name;}setDetailHero(item.imageUrl||'',item.name,item.imageKind==='night'?(item.imageCredit||''):'Tagesbild als Vorschau');detailGallery.innerHTML='<div class="rg-night-empty">Nachtfotos werden geladen …</div>';detailModal.hidden=false;lockPage(true);
  try{const u=endpoint.replace(/\/$/,'')+'/'+encodeURIComponent(item.id)+'/photos';const res=await fetch(u,{credentials:'same-origin'});const j=await res.json().catch(()=>({}));if(!res.ok||!j.ok)throw new Error('Galerie konnte nicht geladen werden');const photos=Array.isArray(j.photos)?j.photos:[];detailCount.textContent=photoCountLabel(photos.length);renderGallery(photos,item);}catch(_){detailGallery.innerHTML='<div class="rg-night-empty">Die Nachtfoto-Galerie konnte gerade nicht geladen werden.</div>';}
}
function closeDetail(){if(!detailModal)return;detailModal.hidden=true;currentDetail=null;if(!uploadModal||uploadModal.hidden)lockPage(false);}

root.addEventListener('click',e=>{
  const filter=e.target.closest('[data-night-filter]');
  if(filter&&root.contains(filter)){e.preventDefault();activeFilter=filter.dataset.nightFilter||'all';filterBtns.forEach(b=>b.classList.toggle('is-active',b===filter));render();return;}
  const rb=e.target.closest('[data-rg-night-radius]');
  if(rb&&root.contains(rb)){e.preventDefault();const n=Number(rb.dataset.rgNightRadius)||5;setRadius(n,true);try{window.dispatchEvent(new CustomEvent('rpg:set-radius',{detail:{radius:n}}));}catch(_){}return;}
  const lc=e.target.closest('[data-rg-night-location-change]');
  if(lc&&root.contains(lc)){e.preventDefault();try{window.dispatchEvent(new Event('rpg:open-location'));}catch(_){}return;}
},true);
if(locationClose)locationClose.addEventListener('click',closeLocation);
if(locationModal)locationModal.addEventListener('click',e=>{if(e.target===locationModal&&location)closeLocation();});
if(radiusApply)radiusApply.addEventListener('click',()=>{setRadius(radiusSelect?radiusSelect.value:radius,true);if(location)closeLocation();});
if(gpsBtn)gpsBtn.addEventListener('click',()=>{if(!navigator.geolocation){showLocationError('Standort wird von diesem Browser nicht unterstützt.');return;}gpsBtn.disabled=true;gpsBtn.textContent='Standort wird ermittelt …';navigator.geolocation.getCurrentPosition(pos=>{location={lat:pos.coords.latitude,lng:pos.coords.longitude,city:nearestCity(pos.coords.latitude,pos.coords.longitude),name:'Dein Standort (GPS)',accuracyMeters:pos.coords.accuracy,isGPS:true};storeLocation(location);updateLocationUi();gpsBtn.disabled=false;gpsBtn.textContent='⌖ GPS-Standort verwenden';if(locationModal)locationModal.hidden=true;document.documentElement.classList.remove('rpgx-modal-open');document.body.classList.remove('rpgx-modal-open');load();},err=>{gpsBtn.disabled=false;gpsBtn.textContent='⌖ GPS-Standort verwenden';showLocationError(err&&err.code===1?'Standortzugriff wurde blockiert. Bitte wähle eine Stadt.':'Standort konnte nicht bestimmt werden. Bitte wähle eine Stadt.');},{enableHighAccuracy:true,timeout:25000,maximumAge:300000});});

if(uploadClose)uploadClose.addEventListener('click',closeUpload);if(uploadModal)uploadModal.addEventListener('click',e=>{if(e.target===uploadModal)closeUpload();});
if(detailClose)detailClose.addEventListener('click',closeDetail);if(detailModal)detailModal.addEventListener('click',e=>{if(e.target===detailModal)closeDetail();});
if(detailUpload)detailUpload.addEventListener('click',()=>{const id=detailUpload.dataset.placeId,name=detailUpload.dataset.placeName;closeDetail();openUpload(id,name);});
document.addEventListener('keydown',e=>{if(e.key==='Escape'){if(uploadModal&&!uploadModal.hidden)closeUpload();else if(detailModal&&!detailModal.hidden)closeDetail();else if(locationModal&&!locationModal.hidden&&location)closeLocation();}});

if(form)form.addEventListener('submit',async e=>{
  e.preventDefault();const submit=form.querySelector('.rg-night-submit');const file=form.querySelector('input[type="file"]')&&form.querySelector('input[type="file"]').files[0];
  if(!file){if(formStatus){formStatus.textContent='Bitte wähle ein Foto aus.';formStatus.className='rg-night-form-status is-error';}return;}
  if(file.size>8*1024*1024){if(formStatus){formStatus.textContent='Das Foto ist größer als 8 MB.';formStatus.className='rg-night-form-status is-error';}return;}
  submit.disabled=true;if(formStatus){formStatus.textContent='Foto wird übermittelt …';formStatus.className='rg-night-form-status';}
  try{
    const fd=new FormData(form);const headers={};if(nonce)headers['X-WP-Nonce']=nonce;
    const r=await fetch(submitUrl,{method:'POST',body:fd,credentials:'same-origin',headers});
    const raw=await r.text();let j={};try{j=JSON.parse(raw);}catch(_){}
    if(!r.ok||!j.ok)throw new Error(j.message||j.error||('Upload nicht möglich (HTTP '+r.status+')'));
    if(formStatus){formStatus.textContent=j.message||'Danke! Das Foto wurde gespeichert.';formStatus.className='rg-night-form-status is-success';}
    form.reset();placeInput.value='';
  }catch(err){if(formStatus){formStatus.textContent=err.message||'Upload nicht möglich. Bitte später erneut versuchen.';formStatus.className='rg-night-form-status is-error';}}
  finally{submit.disabled=false;}
});

function isCurrentVisibleRoot(){
  const liveApp=document.getElementById('ruhrpott-guru-root');
  return !!(liveApp&&root.isConnected&&liveApp.contains(root)&&liveApp.querySelector('[data-rg-night]')===root);
}
window.addEventListener('rpg:location-changed',e=>{
  if(!isCurrentVisibleRoot())return;
  const d=e&&e.detail||{};
  if(!Number.isFinite(Number(d.lat))||!Number.isFinite(Number(d.lng)))return;
  location={lat:Number(d.lat),lng:Number(d.lng),city:d.city||nearestCity(Number(d.lat),Number(d.lng)),name:d.name||'',isGPS:!!d.isGPS,accuracyMeters:d.accuracyMeters||null};
  updateLocationUi();load();
  // React may replace PlainWpView immediately after the shared location state changes.
  // The persistent watcher below will bind that fresh visible instance; these nudges
  // make the hand-off immediate on browsers where MutationObserver batches updates.
  setTimeout(boot,0);setTimeout(boot,80);setTimeout(boot,250);
});
window.addEventListener('rpg:radius-changed',e=>{
  if(!isCurrentVisibleRoot())return;
  const n=e&&e.detail?Number(e.detail.radius):NaN;
  if(!Number.isFinite(n))return;
  const changed=Math.abs(n-radius)>.001;
  setRadius(n,changed);
  setTimeout(boot,0);setTimeout(boot,80);
});

radius=savedRadius();location=readLocation();buildCityGrid();updateLocationUi();load();
return true;
}
let nightWatcherStarted=false;
function start(){
  if(nightWatcherStarted){boot();return;}
  nightWatcherStarted=true;
  boot();
  const target=document.getElementById('ruhrpott-guru-root')||document.body;
  if(!target)return;
  // Keep watching for the lifetime of this page. PlainWpView is allowed to be
  // replaced by React after a shared location/radius state change. Disconnecting
  // after the first successful bind would leave the replacement non-interactive.
  const observer=new MutationObserver(()=>{boot();});
  observer.observe(target,{childList:true,subtree:true});
  // Same-origin storage writes do not emit a native `storage` event in the current
  // tab. This lightweight watchdog therefore also detects a freshly rendered night
  // root and rebinds it even if a React replacement happens between mutations.
  setInterval(()=>{boot();},400);
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',()=>setTimeout(start,0),{once:true});else setTimeout(start,0);
})();
