(function(){
 const R=window.RPGReact;let items=[],staged=[];let mediaOverrides=new Map(),category="food",eventBase=[],eventResolve=null;let radarFilters={},radarSort="distance",viewMode="",specialFilter="all",specialCity="";let extras=new Map(),mediaJobs=new Map(),foodInfo=new Map(),placeJobs=new Map(),placeTransports=new Map(),placeTicket=0;
 function foodFacets(x){
  const amenity=String((x.tags||{}).amenity||'').toLowerCase();
  const types={restaurant:'Restaurant',fast_food:'Imbiss & Streetfood',cafe:'Café',ice_cream:'Eisdiele',bar:'Bar',pub:'Kneipe',biergarten:'Biergarten'};
  return Array.from(new Set([types[amenity]||x.subtitle,...R.categoryFilterTags(x)].filter(Boolean)));
 }
 function key(x){return String(x._rpgNearTermKey||x.id);}
 function find(id){return extras.get(id)||items.find(x=>key(x)===id)||items.find(x=>x.id===id);}
 // Curated correction for one verified building; museum umbrella Wikidata P18
 // supplied a different site. Keep original raw data and all selection rules.
 function correctedMedia(x){
  const t=x.tags||{},raw=x.raw||{};
  if(x.kind!=='place'||!(String(t['rg:place_id']||'')==='8407'||(raw.type==='way'&&String(raw.id)==='37642158')))return null;
  return {url:'file:///android_asset/media/peter-behrens-bau-tuxyso-2025.jpg',credit:'Peter-Behrens-Bau, Oberhausen · Foto: Tuxyso · Original unverändert, Anzeige eingepasst',artist:'Tuxyso',license:'CC BY-SA 4.0',licenseUrl:'https://creativecommons.org/licenses/by-sa/4.0/',source:'Wikimedia Commons',sourceUrl:'https://commons.wikimedia.org/wiki/File:Peter-Behrens-Bau-Oberhausen-2025.jpg',cropCard:{x:50,y:50,zoom:0},cropDetail:{x:50,y:50,zoom:0}};
 }
 function gallery(x){
  const fixed=correctedMedia(x),original=(x.galleryImages||[]).map(e=>mediaEntry(x,e));
  if(!fixed)return original;
  return [fixed,...original.filter(e=>{let url=e.url;try{url=decodeURIComponent(url);}catch(_){}return !/Rheinisches[ _]Industriemuseum\.jpg/i.test(url)&&e.url!==fixed.url;})];
 }
 function mediaEntry(x,e){
  const t=x.tags||{},primary=e.url===x.imageUrl;
  return {url:e.url,credit:String(e.credit||(primary?t['rg:image_credit']:'')||''),artist:String(e.artist||''),license:String(e.license||(primary?t['rg:image_license']:'')||''),source:String(e.source||(primary?t['rg:image_source']:'')||''),sourceUrl:String(e.sourceUrl||(primary?(t['rg:image_source_url']||(x.raw&&x.raw.rpg_event_media&&x.raw.rpg_event_media.sourceUrl)):'')||''),cropCard:window.rpgMediaCropValues(window.rpgCropForImage(x,e.url,'card',e)),cropDetail:window.rpgMediaCropValues(window.rpgCropForImage(x,e.url,'detail',e))};
 }
 function media(x){const fixed=correctedMedia(x);if(fixed)return fixed;const override=mediaOverrides.get(key(x));if(override)return override;
  if(x.displayImageSuppressed)return {url:''};
  if(x.displayImageUrl && x.displayImageUrl!==x.imageUrl)return {url:x.displayImageUrl,credit:x.displayMediaCredit||'',artist:x.displayMediaArtist||'',license:x.displayMediaLicense||'',source:x.displayMediaSource||'',sourceUrl:x.displayMediaSourceUrl||'',cropCard:null,cropDetail:null};
  return mediaEntry(x,(x.galleryImages||[]).find(e=>e.url===x.imageUrl)||{url:x.imageUrl||''});}
 function dto(x,full=false){const extra=full&&window.rpgDetailFacts?window.rpgDetailFacts(x,foodInfo.get(key(x))||null):null;const h=x.category==='events'?{}:R.placeOpeningStatus(x,x.openingHours);return {
  routeLabel:x._nativeRoute?String(x._nativeRoute.typeLabel||''):'',special:['buedchen','wurst'].includes(x.category)&&window.RadarRules?window.RadarRules.facts(x):null,coords:x.coords||null,sourceName:x.sourceName||'',sourceAttribution:x.sourceAttribution||'',sourceLicense:x.sourceLicense||'',sourcePageUrl:x.sourcePageUrl||'',sourceTermsUrl:x.sourceTermsUrl||'',details:extra,contributionFields:full&&window.rpgContributionFields?window.rpgContributionFields[x.category]||[]:[],category:x.category, event:x.category==='events'?window.rpgEventFacts(x):null,
  media:media(x),gallery:gallery(x),id:key(x),canonicalId:x.id,title:R.displayPlaceTitle(x),subtitle:x.subtitle,city:x.city,address:x.address,
  distance:R.formatDistance(x.distanceKm),labels:R.categoryFilterTags(x).map(label=>({label,slug:R.tagSlug(label)})),
  description:x.description,price:x.category==='food'?(x.priceTier||''):((x.tags&&(x.tags['charge']||x.tags['fee']))||''),hours:h.label||'',hoursFormatted:h.formatted||'',hoursHeading:h.heading||'',
  website:x.website,menu:x.menuUrl,reservation:x.reservationUrl,phone:x.phone,
  directions:x.coords?'https://www.google.com/maps/dir/?api=1&destination='+encodeURIComponent(x.coords.lat+','+x.coords.lng):'',
  share:R.buildShareUrl(x)||''
 };}
 window.NativeFood={
 metadata(){return Object.entries(R.CITY_CENTERS).map(([name,p])=>({name,lat:p[0],lng:p[1]}));},
 nearest(lat,lng){return R.nearestCity(lat,lng);},
 location(data,address){const city=address?window.rpgNativeMatchCity(data.city):R.nearestCity(Number(data.lat),Number(data.lng));if(!city)throw new Error("Diese Adresse liegt außerhalb des RuhrpottGuru-Gebiets.");return {city};},
 reset(next="food",mode=""){category=next;viewMode=mode;specialFilter=mode?window.rpgSpecialDefault(mode):"all";specialCity="";radarFilters={};radarSort="distance";staged=[];items=[];eventBase=[];eventResolve=null;extras=new Map();mediaJobs=new Map();foodInfo=new Map();placeJobs=new Map();placeTransports=new Map();if(window.rpgResetPlaceEventCache)window.rpgResetPlaceEventCache();mediaOverrides=new Map();return true;},
 append(a){staged.push(...a);return true;},
 commit(point,radius=5){if(viewMode==='route'){items=staged.map(x=>window.rpgRouteItem(x,point&&point.mode==='none'?null:point));}else if(category==='events'){const event=viewMode?window.rpgSpecialListing(staged,point,radius,viewMode):window.rpgEventListing(staged,point,radius);items=event.items;eventBase=event.baseItems;}else if(['buedchen','wurst'].includes(category))items=window.RadarRules.select(staged,point,category);else items=window.rpgSourceSort(staged.map(x=>R.normalizePlace(x,category,point)));if(category==='food')items=items.filter(x=>Number.isFinite(x.distanceKm)&&x.distanceKm<=radius);staged=[];return viewMode==='halloween'?window.rpgSpecialQuery(items,viewMode,"all","").count:items.length;},
 radarToggle(key){radarFilters=window.RadarRules.toggle(radarFilters,key);return radarFilters;},
 radarClear(){radarFilters={};radarSort="distance";return true;},
 radarSetSort(value){radarSort=value==='new'?'new':'distance';return true;},
 specialSelect(filter,city=""){specialFilter=filter;specialCity=city;return true;},
 query(query,tag,sub,limit,dateFrom='',dateTo=''){const specialInfo=viewMode?window.rpgSpecialQuery(items,viewMode,specialFilter,specialCity):null;const f=specialInfo?specialInfo.items:category==='buedchen'?window.RadarRules.buedchen(items,radarFilters):(category==='wurst'?window.RadarRules.wurst(items,tag||'all',query,radarSort):category==='events'?window.rpgEventFilter(items,query,tag,sub,dateFrom,dateTo):(['culture','activities'].includes(category)?window.rpgPlaceFilter(category,items,query,tag,sub):(category==='highlights'?window.rpgHighlightsFilter:window.rpgSourceFilter)(category==='food'&&sub?items.filter(x=>foodFacets(x).includes(sub)):items,query,tag,category==='food'?'':sub)));const tags=[];items.forEach(x=>R.categoryFilterTags(x).forEach(label=>{const slug=R.tagSlug(label);if(slug&&!tags.some(y=>y.slug===slug))tags.push({slug,label});}));if(category==='wurst'){tags.length=0;const available=new Set();items.forEach(x=>window.RadarRules.facts(x).menu.forEach(v=>available.add(v)));window.RadarRules.filters.filter(x=>available.has(x[0])).forEach(x=>tags.push({slug:x[0],label:x[2]}));}return {specialInfo:specialInfo?{filters:specialInfo.filters,cities:specialInfo.cities,count:specialInfo.count,kicker:specialInfo.kicker}:null,mapItems:category==='buedchen'?f.slice(0,120).map(x=>({id:key(x),title:R.displayPlaceTitle(x),coords:x.coords||null})):[],total:f.length,items:f.slice(0,limit).map(dto),tags,subtypes:Array.from(new Set(category==='food'?items.flatMap(foodFacets):items.map(x=>category==='events'?x.eventType:x.subtitle).filter(Boolean))).sort((a,b)=>a.localeCompare(b,'de'))};},
 mediaRequest(id,failedUrl){const item=find(id);if(!item||correctedMedia(item)||item.displayImageSuppressed)return null;
  let captured=null;const post=R.api.post;
  R.api.post=(url,body)=>{captured={url,body};return Promise.reject(new Error('Native transport capture'));};
  try{R.resolveItemMedia(Object.assign({},item,{imageUrl:'',displayImageUrl:''})).catch(()=>{});}finally{R.api.post=post;}
  return captured;
 },
 mediaResponse(id,data,failedUrl){const item=find(id);if(!item||correctedMedia(item))return false;
  const image=window.rpgNativeMediaResponse(item,data);
  if(!image||image===failedUrl)return false;
  mediaOverrides.set(id,{url:image,credit:String(data.credit||''),artist:String(data.artist||''),license:String(data.license||''),source:String(data.source||''),sourceUrl:String(data.sourceUrl||''),cropCard:null,cropDetail:null});return true;
 },
 // Resume the unchanged asynchronous web resolver through native HTTP. Each
 // evaluateJavascript turn drains microtasks before another job can resume.
 highlightsMediaRequest(id,failedUrl){const item=find(id);if(!item||correctedMedia(item))return null;
  const job={pending:null,done:false,image:'',data:null,failedUrl};mediaJobs.set(id,job);
  const post=R.api.post;R.api.post=(url,body)=>{job.request={url,body};return new Promise((resolve,reject)=>{job.pending={resolve,reject};});};
  try{R.resolveItemMedia({...item,imageUrl:'',displayImageUrl:''}).then(image=>{job.image=image||'';job.done=true;}).catch(()=>{job.done=true;});}finally{R.api.post=post;}
  return job.request||null;
 },
 highlightsMediaAdvance(id,data){const job=mediaJobs.get(id);if(!job||!job.pending)return false;
  const pending=job.pending;job.pending=null;job.request=null;const post=R.api.post;
  R.api.post=(url,body)=>{job.request={url,body};return new Promise((resolve,reject)=>{job.pending={resolve,reject};});};
  if(data&&data.imageUrl)job.data=data;
  if(data)pending.resolve(data);else pending.reject(new Error('Native media request failed'));
  Promise.resolve().then(()=>{R.api.post=post;});return true;
 },
 highlightsMediaState(id){const job=mediaJobs.get(id);if(!job)return null;
  if(!job.done)return job.request?{request:job.request}:null;
  const item=find(id),data=job.data;mediaJobs.delete(id);
  if(!item||!data||!job.image||job.image===job.failedUrl)return {done:true,applied:false};
  mediaOverrides.set(id,{url:job.image,credit:String(data.credit||''),artist:String(data.artist||''),license:String(data.license||''),source:String(data.source||''),sourceUrl:String(data.sourceUrl||''),cropCard:null,cropDetail:null});return {done:true,applied:true};
 },
 foodInfoRequest(id){const item=find(id);if(!item||item.category!=='food')return null;let request=null;const post=R.api.post;R.api.post=(url,body)=>{request={url,body};return Promise.reject(new Error('Native capture'));};try{R.api.foodInfo(item).catch(()=>{});}finally{R.api.post=post;}return request;},
 foodInfoResponse(id,data){const item=find(id);if(!item||!data||data.status!=='success')return null;if(Array.isArray(data.foodTags)&&typeof R.rememberFoodOfficialTags==='function')R.rememberFoodOfficialTags(item,data.foodTags);foodInfo.set(id,data);return this.detail(id);},
 placeEventsRequest(id){const item=find(id);if(!item||item.category==='events')return null;const job={done:false,items:[]};placeJobs.set(id,job);const post=R.api.post;R.api.post=(url,body)=>{const ticket=++placeTicket;job.request={url,body,ticket};return new Promise((resolve,reject)=>{placeTransports.set(ticket,{resolve,reject});});};try{R.api.eventsForPlace(item).then(events=>{job.items=events.map(e=>{extras.set(key(e),e);return {...window.rpgPlaceEventRow(e),id:key(e)};});job.done=true;}).catch(()=>{job.done=true;job.error='Events konnten gerade nicht geladen werden.';});}finally{R.api.post=post;}return job.request||null;},
 placeEventsResponse(ticket,data){const j=placeTransports.get(ticket);placeTransports.delete(ticket);if(j){if(data)j.resolve(data);else j.reject(new Error('Events unavailable'));}return true;},
 placeEventsState(id){const j=placeJobs.get(id);return j&&j.done?{items:j.items,error:j.error||''}:null;},
 sharePayload(id,mode,message,duration){const x=find(id);return x?window.rpgDetailShare(x,mode,message,duration):null;},
 formPayload(id,mode,data){const x=find(id);return x?(mode==='story'?window.rpgStoryPayload(x,data):window.rpgContributionPayload(x,data)):{error:'Ort nicht verfügbar.'};},
 correctedSavedMedia(entries){return entries.map(saved=>{if(saved.category==='events'||!saved.raw)return saved;const x=R.normalizePlace(saved.raw,saved.category,null),fixed=correctedMedia(x);return fixed?{...saved,imageUrl:fixed.url,media:fixed,gallery:gallery(x)}:saved;});},
 snapshot(id){const x=find(id);return x?{id:String(x.id),title:x.title,category:x.category,city:x.city,imageUrl:media(x).url,media:media(x),gallery:gallery(x),raw:x.raw,calendar:x.category==='events'?window.rpgEventFacts(x).calendar:null,routeRecord:x._nativeRoute||null,descriptor:R.shareDescriptor(x)}:null;},
 restoreSaved(saved,point){const x=saved.routeRecord&&window.rpgRouteItem?window.rpgRouteItem({...saved.routeRecord,element:saved.routeRecord.element?saved.raw:saved.routeRecord.element},point):saved.category==='events'?R.normalizeEvent(saved.raw,point):R.normalizePlace(saved.raw,saved.category,point);if(saved.routeRecord)x.distanceKm=x.coords&&point?R.distanceKm(point.lat,point.lng,x.coords.lat,x.coords.lng):null;x._rpgNearTermKey="saved:"+saved.category+":"+x.id;extras.set(key(x),x);return dto(x,true);},
 relatedRequest(id){const item=find(id);if(!item)return null;let request=null;const get=R.api.get;R.api.get=url=>{request={url};return Promise.reject(new Error('Native transport capture'));};try{R.api.eventRelated(item).catch(()=>{});}finally{R.api.get=get;}return request;},
 relatedRows(data){return (Array.isArray(data.items)?data.items:[]).map(x=>({...x,date:window.rpgEventFacts({...x,category:'events'}).date}));},
 externalDetail(raw,point){const x=R.normalizeEvent(raw,point);extras.set(key(x),x);return dto(x,true);},
 preset(mode){return window.rpgEventPreset(mode);},
 rangeLabel(from,to){return window.rpgEventRangeLabel(from,to);},
 eventMediaRequest(){if(category!=='events')return null;let captured=null;const post=R.api.post;const base=eventBase;
  R.api.post=(url,body)=>{captured={url,body};return new Promise((resolve,reject)=>{eventResolve={resolve,reject};});};
  try{R.diversifyEventMedia(base.slice(0,300)).then(diverse=>{if(category==='events'&&eventBase===base&&Array.isArray(diverse)&&diverse.length===Math.min(base.length,300))items=diverse.concat(base.slice(300));}).catch(()=>{});}finally{R.api.post=post;}
  return captured;
 },
 eventMediaResponse(data){const pending=eventResolve;eventResolve=null;if(pending){if(data)pending.resolve(data);else pending.reject(new Error('Image repair unavailable'));}return true;},
 detail(id){const x=find(id);if(!x)return null;const keep=x.displayImageUrl&&String(x.mediaSemanticVersion||'')==='v298'&&String(x.displayMediaReason||'').includes('semantic');return dto(x.category==='events'&&x.displayImageUrl&&!keep?Object.assign({},x,{displayImageUrl:''}):x,true);}

 };
})();
