(function (window) {
  'use strict';
  const RPG = window.RPGReact = window.RPGReact || {};
  const wpElement = window.wp && window.wp.element;
  if (!wpElement) throw new Error('RuhrpottGuru React: wp.element is unavailable.');

  RPG.React = wpElement;
  RPG.h = wpElement.createElement;
  RPG.Fragment = wpElement.Fragment;
  RPG.settings = window.ruhrpottSettings || {};
  RPG.CATEGORIES = {
    events: { title: 'Events', subtitle: 'Konzerte, Märkte, Festivals & mehr', icon: '📅' },
    food: { title: 'Food & Drinks', subtitle: 'Gastronomie, Cafés, Buden & Bars', icon: '🍔' },
    highlights: { title: 'Highlights', subtitle: 'Besondere Orte, Industriekultur & Ausblicke', icon: '👀' },
    culture: { title: 'Kultur & Szene', subtitle: 'Museen, Streetart, Theater & Kleinkunst', icon: '🎨' },
    activities: { title: 'Aktivitäten', subtitle: 'Action, Wasser, Outdoor & besondere Erlebnisse', icon: '🚴' }
  };

  // V269 – Detail tags are active for all four stable Place categories.
  // Activities was held back during the 53-municipality coverage wave and is
  // activated only after the completed V2 Phase-2C visitor-feed rollout.
  RPG.TAG_FILTER_ACTIVE_CATEGORIES = ['events','food','highlights','culture','activities'];
  RPG.CATEGORY_PATHS = {events:'veranstaltungen',food:'food',highlights:'highlights',culture:'kultur',activities:'aktiv'};

  // V311 — keep RuhrpottGuru navigation on the browser's current origin.
  // WordPress menu/home URLs may be stored with or without "www". localStorage
  // is origin-scoped, so crossing that host boundary used to discard an exact
  // postcode/GPS snapshot and fall back to the shared coarse city cookie.
  // Same-site navigation is therefore emitted as path/query/hash only; external
  // links keep their absolute URL untouched.
  RPG.internalNavUrl = function(value){
    const raw=String(value||'').trim();
    if(!raw)return raw;
    try{
      const u=new URL(raw,window.location.href);
      const normHost=h=>String(h||'').toLowerCase().replace(/^www\./,'');
      if((u.protocol==='http:'||u.protocol==='https:')&&normHost(u.hostname)===normHost(window.location.hostname)){
        return (u.pathname||'/')+(u.search||'')+(u.hash||'');
      }
      return u.toString();
    }catch(_){return raw;}
  };
  // V312 — legacy/PHP-rendered WordPress links do not pass through React's
  // internalNavUrl() helper. Normalize those anchors too so a menu item stored as
  // www.ruhrpottguru.de can never move a non-www visitor (or vice versa) onto a
  // different localStorage origin. This preserves exact PLZ/GPS state.
  RPG.normalizeInternalAnchor = function(anchor){
    if(!anchor||typeof anchor.getAttribute!=='function'||typeof anchor.setAttribute!=='function')return false;
    const raw=String(anchor.getAttribute('href')||'').trim();
    if(!raw||raw[0]==='#'||/^(?:mailto:|tel:|javascript:|data:)/i.test(raw))return false;
    let parsed;try{parsed=new URL(raw,window.location.href);}catch(_){return false;}
    const normHost=h=>String(h||'').toLowerCase().replace(/^www\./,'');
    if(!/^(?:http:|https:)$/.test(parsed.protocol)||normHost(parsed.hostname)!==normHost(window.location.hostname))return false;
    if(parsed.origin===window.location.origin)return false;
    const next=(parsed.pathname||'/')+(parsed.search||'')+(parsed.hash||'');
    anchor.setAttribute('href',next);
    return true;
  };
  RPG.tagSlug = function(value){
    return String(value||'').trim().toLocaleLowerCase('de')
      .normalize('NFD').replace(/[\u0300-\u036f]/g,'')
      .replace(/ß/g,'ss').replace(/&/g,' und ')
      .replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'');
  };
  RPG.isTagFilterCategory = function(category){
    return RPG.TAG_FILTER_ACTIVE_CATEGORIES.indexOf(String(category||''))!==-1;
  };

  // V267 – official Food facets discovered by the identity-verified food-info
  // resolver are cached locally per exact Place. This avoids bulk website crawling
  // on category pages while making a tag discovered in the detail layer available
  // immediately to the radius filter after the user clicks it.
  const FOOD_OFFICIAL_TAG_CACHE_KEY='ruhrpottguru_food_official_tags_v267';
  let foodOfficialTagCache=null;
  function loadFoodOfficialTagCache(){
    if(foodOfficialTagCache)return foodOfficialTagCache;
    try{
      const raw=JSON.parse(RPG.storage.get(FOOD_OFFICIAL_TAG_CACHE_KEY)||'{}');
      foodOfficialTagCache=raw&&typeof raw==='object'&&!Array.isArray(raw)?raw:{};
    }catch(_){foodOfficialTagCache={};}
    const cutoff=Date.now()-30*24*60*60*1000;
    Object.keys(foodOfficialTagCache).forEach(key=>{const row=foodOfficialTagCache[key];if(!row||Number(row.savedAt||0)<cutoff)delete foodOfficialTagCache[key];});
    return foodOfficialTagCache;
  }
  RPG.foodOfficialTagsForItem=function(item){
    if(!item||item.category!=='food'||!item.id)return [];
    const row=loadFoodOfficialTagCache()[String(item.id)];
    if(!row||!Array.isArray(row.tags))return [];
    // Guard against a recycled/non-stable id by retaining basic identity evidence.
    if(row.title&&String(row.title)!==String(item.title||''))return [];
    if(row.city&&String(row.city)!==String(item.city||''))return [];
    return row.tags.map(v=>String(v||'').trim()).filter(Boolean).slice(0,6);
  };
  RPG.rememberFoodOfficialTags=function(item,tags){
    if(!item||item.category!=='food'||!item.id||!Array.isArray(tags))return;
    const clean=[],seen=new Set();
    tags.forEach(value=>{const label=String(value||'').replace(/^#+/,'').replace(/_/g,' ').replace(/\s+/g,' ').trim();const key=RPG.tagSlug(label);if(!label||!key||seen.has(key))return;seen.add(key);clean.push(label);});
    if(!clean.length)return;
    const cache=loadFoodOfficialTagCache();
    cache[String(item.id)]={title:String(item.title||''),city:String(item.city||''),tags:clean.slice(0,6),savedAt:Date.now()};
    // Bounded local cache: keep the most recently resolved 200 Places.
    const keys=Object.keys(cache).sort((a,b)=>Number(cache[b]&&cache[b].savedAt||0)-Number(cache[a]&&cache[a].savedAt||0));
    keys.slice(200).forEach(key=>delete cache[key]);
    RPG.storage.set(FOOD_OFFICIAL_TAG_CACHE_KEY,JSON.stringify(cache));
  };

  // V275 – visitor-facing discovery labels. Intelligence/profile labels may
  // contain internal quality signals that are useful for ranking but too vague
  // for navigation. Keep those signals in the item/backend; translate them only
  // when the category itself provides enough visitor-facing evidence.
  RPG.publicDiscoveryLabel = function(item,value){
    const label=String(value||'').replace(/^#+/,'').replace(/_/g,' ').replace(/\s+/g,' ').trim();
    if(!label)return '';
    const category=String(item&&item.category||'');
    if(!/^Bekannter Ort$/i.test(label))return label;

    // Highlights already passed the strict Highlight Experience admission gate.
    // In this category the public meaning is therefore a genuine sightseeing
    // destination, not merely "known in the database".
    if(category==='highlights')return 'Sehenswürdigkeit';

    if(category==='culture'){
      const tags=item&&item.tags&&typeof item.tags==='object'?item.tags:{};
      const amenity=String(tags.amenity||'').toLowerCase();
      const tourism=String(tags.tourism||'').toLowerCase();
      const building=String(tags.building||'').toLowerCase();
      const historic=String(tags.historic||'').toLowerCase();
      const canonicalCulture = ['theatre','cinema','planetarium','arts_centre','cultural_center','concert_hall','music_venue'].indexOf(amenity)!==-1
        || ['museum','gallery','artwork'].indexOf(tourism)!==-1
        || ['museum','theatre'].indexOf(building)!==-1;
      const destinationEvidence = tourism==='attraction' || !!String(tags.wikipedia||'').trim()
        || !!String(tags.heritage||tags['heritage:operator']||'').trim()
        || !!historic;
      // A canonical Culture venue with the existing notability signal is useful
      // to visitors as "Sehenswert". Generic/broad venues need an additional
      // destination/heritage/Wikipedia signal before receiving that label.
      if(canonicalCulture || destinationEvidence)return 'Sehenswert';
    }

    // Food/Activities and weak generic Culture records do not expose the internal
    // "Bekannter Ort" signal at all.
    return '';
  };

  // V316 – Source-faithful event type facets. Exact structured source types
  // remain visitor-visible and filterable; broad classifications may supplement them
  // later, but never replace the provider's own Veranstaltungstyp label.
  RPG.eventSourceTypeTags = function(item){
    if(!item||String(item.category||'')!=='events')return [];
    const out=[],seen=new Set();
    const add=value=>{
      let label=String(value||'').replace(/^#+/,'').replace(/_/g,' ').replace(/\s+/g,' ').trim();
      label=label.replace(/^\d{1,2}\.\d{1,2}(?:\.\d{2,4})?\s*[-–]\s*\d{1,2}\.\d{1,2}\.\d{4}\s*/,'').trim();
      const key=RPG.tagSlug(label);
      if(!label||!key||seen.has(key)||/^(?:divers|sonstiges?|weitere events?|veranstaltung(?:en)?|event(?:s)?)$/i.test(label))return;
      seen.add(key);out.push(label);
    };
    const info=item.eventInfo&&typeof item.eventInfo==='object'?item.eventInfo:{};
    (Array.isArray(info.categories)?info.categories:[]).forEach(add);
    add(item.eventType);
    return out.slice(0,6);
  };

  // V295 – Event detail discovery facets. The venue is a first-class filter,
  // followed by deterministic visitor-facing event types derived from structured
  // ET4/ODF event metadata. Titles are deliberately not classified here: missing
  // or ambiguous source taxonomy stays broad instead of being guessed in-browser.
  RPG.eventDiscoveryTags = function(item){
    if(!item||String(item.category||'')!=='events')return [];
    const out=[],seen=new Set();
    const add=value=>{
      const label=String(value||'').replace(/^#+/,'').replace(/_/g,' ').replace(/\s+/g,' ').trim();
      const key=RPG.tagSlug(label);if(!label||!key||seen.has(key))return;seen.add(key);out.push(label);
    };
    const venue=String(item.subtitle||'').replace(/\s+/g,' ').trim();
    const city=String(item.city||'').replace(/\s+/g,' ').trim();
    if(venue&&RPG.tagSlug(venue)!==RPG.tagSlug(city)&&!/^(?:veranstaltungsort|event-?ort|location)$/i.test(venue))add(venue);

    // V314: all events that originate from, or are additionally linked to, the
    // official WeselMarketing calendar share one visitor-facing discovery facet.
    // Only the short requested label is shown; rights/permission metadata remains
    // internal and is not rendered as a chip.
    const sourceValues=[];
    const pushSource=value=>{if(value==null)return;if(Array.isArray(value)){value.forEach(pushSource);return;}if(typeof value==='object'){['source','source_name','key','name'].forEach(k=>{if(value[k]!=null)pushSource(value[k]);});return;}sourceValues.push(String(value));};
    pushSource(item.sourceName);pushSource(item.sources);
    const raw=item.raw&&typeof item.raw==='object'?item.raw:{};pushSource(raw.provider_source);pushSource(raw.source_name);pushSource(raw.rpg_sources);
    const weselMarketing=sourceValues.some(value=>{const key=String(value||'').trim().toLocaleLowerCase('de').replace(/[\s.-]+/g,'_');return key==='wesel_marketing_calendar'||/wesel_?marketing/.test(key);});
    if(weselMarketing)add('Wesel Marketing');

    const sourceTypes=typeof RPG.eventSourceTypeTags==='function'?RPG.eventSourceTypeTags(item):[];
    sourceTypes.forEach(add);
    const info=item.eventInfo&&typeof item.eventInfo==='object'?item.eventInfo:{};
    const values=[];
    ['categories','features','keywords'].forEach(key=>{if(Array.isArray(info[key]))info[key].forEach(v=>values.push(String(v||'')));});
    const typeLabels=[];const typeSeen=new Set();
    const addType=label=>{const key=RPG.tagSlug(label);if(!key||typeSeen.has(key))return;typeSeen.add(key);typeLabels.push(label);};
    const classify=value=>{
      const raw=String(value||'').replace(/[_-]+/g,' ').replace(/\s+/g,' ').trim();
      const n=raw.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('de');
      if(!n||/^(?:divers|sonstiges?|weitere events?|veranstaltung(?:en)?|event(?:s)?)$/.test(n))return;
      if(/krimi|kriminal|crime/.test(n))return addType('Krimi');
      if(/kabarett/.test(n))return addType('Kabarett');
      if(/comedy|stand\s*up/.test(n))return addType('Comedy');
      if(/komodie|komoedie/.test(n))return addType('Komödie');
      if(/lesung|literatur/.test(n))return addType('Lesung');
      if(/konzert|live\s*musik|live\s*music|orchester|chor/.test(n))return addType('Konzert');
      if(/musical/.test(n))return addType('Musical');
      if(/operette|\boper\b/.test(n))return addType('Oper');
      if(/theater|schauspiel|buhne/.test(n))return addType('Theater');
      if(/ausstellung|vernissage|galerie|exhibition/.test(n))return addType('Ausstellung');
      if(/fuhrung|rundgang|stadtfuhrung|tour/.test(n))return addType('Führung');
      if(/workshop|kurs|seminar/.test(n))return addType('Workshop');
      if(/vortrag|lecture/.test(n))return addType('Vortrag');
      if(/party|nightlife|disco/.test(n))return addType('Party');
      if(/festival/.test(n))return addType('Festival');
      if(/markt|kirmes|stadtfest|messe/.test(n))return addType('Markt & Fest');
      if(/sport|lauf|marathon|turnier|rennen/.test(n))return addType('Sport');
      if(/familie|kinder|kids/.test(n))return addType('Familie');
      if(/variete|show|zauber|zirkus|circus/.test(n))return addType('Show');
      if(/tanz|dance/.test(n))return addType('Tanz');
    };
    values.forEach(classify);
    if(!typeLabels.length){
      const broad=String(item.eventType||'').trim();
      if(broad&&!/^Weitere Events$/i.test(broad))addType(broad);
    }
    typeLabels.slice(0,4).forEach(add);
    return out.slice(0,6);
  };

  RPG.categoryFilterTags = function(item){
    if(!item||!item.category)return [];
    const category=String(item.category);
    if(['events','food','highlights','culture','activities'].indexOf(category)===-1)return [];
    if(category==='events')return RPG.eventDiscoveryTags(item);
    const out=[],seen=new Set();
    const add=value=>{
      const label=RPG.publicDiscoveryLabel(item,value);
      if(!label||/^(?:Route(?: der)? Industriekultur|Ankerpunkt|Panorama|Siedlung|Primärstandort)$/i.test(label))return;
      if(category==='highlights'&&/^Highlights\s*&\s*Ausflugsziele$/i.test(label))return;
      if(category==='food'&&/^Gastronomie$/i.test(label))return;
      const key=RPG.tagSlug(label);if(!key||seen.has(key))return;seen.add(key);out.push(label);
    };
    // Keep the venue identity and the broad visitor-facing type visible even
    // when the source supplies more labels than the bounded chip list can show.
    if(category==='highlights'||category==='culture'){
      const name=String(item.title||'').replace(/\s+/g,' ').trim();
      if(name&&!/^(?:Unbenannter Ort|Unbekannter Ort|Location)$/i.test(name)){
        const key=RPG.tagSlug(name);
        if(key){seen.add(key);out.push(name);}
      }
    }
    if(category==='highlights'||category==='culture')add(item.subtitle);
    (Array.isArray(item.labels)?item.labels:[]).forEach(add);
    if(category==='food')RPG.foodOfficialTagsForItem(item).forEach(add);
    add(item.subtitle);
    return out.slice(0,6);
  };
  RPG.categoryTagUrl = function(category,label,radius,contextItem){
    const slug=RPG.tagSlug(label);
    const path=RPG.CATEGORY_PATHS[String(category||'')];
    if(!slug||!path)return '';
    // Reuse the actual WordPress category target used by the footer, so custom
    // page slugs work from Home, specials, search and direct detail pages too.
    const menu=RPG.readJsonScript('ruhrpott-footer-menu-data',[]);
    const target=Array.isArray(menu)?menu.find(row=>row&&row.category===category&&row.url):null;
    let url;try{url=new URL(target?target.url:path.replace(/^\/+|\/+$/g,'')+'/',RPG.settings.homeUrl||window.location.origin+'/');}catch(_){return '';}
    url.searchParams.set('rg_tag',slug);
    const r=Number(radius);if(Number.isFinite(r)&&r>=1&&r<=50)url.searchParams.set('radius',String(r));
    const coords=contextItem&&contextItem.coords;
    if(coords&&Number.isFinite(Number(coords.lat))&&Number.isFinite(Number(coords.lng))){
      url.searchParams.set('rg_lat',String(Number(coords.lat)));url.searchParams.set('rg_lng',String(Number(coords.lng)));
      const city=String(contextItem.city||'').trim();if(city)url.searchParams.set('rg_city',city);
    }
    return RPG.internalNavUrl(url.toString());
  };

  // V269 – Activities availability semantics. Public outdoor sport spaces are
  // not businesses. Existing time windows remain visible, but are presented as
  // Nutzungszeiten rather than as generic shop-style "geschlossen/geöffnet".
  // We never infer 24/7 from public access alone.
  RPG.activityPublicOutdoorMode = function(item){
    if(!item||String(item.category||'')!=='activities')return false;
    const tags=item.tags&&typeof item.tags==='object'?item.tags:{};
    const type=String(tags['rg:activity_v2_primary']||'').toLowerCase();
    const publicTypes=['skatepark','pumptrack','bikepark','bmx','basketball','beach_volleyball','table_tennis','outdoor_fitness','disc_golf','bolzplatz','multi_sport_park'];
    if(publicTypes.indexOf(type)===-1)return false;
    const access=String(tags.access||'').toLowerCase();
    const fee=String(tags.fee||'').toLowerCase();
    const indoor=String(tags.indoor||'').toLowerCase();
    const covered=String(tags.covered||'').toLowerCase();
    const building=String(tags.building||'').toLowerCase();
    if(['private','no','members','customers','permit'].indexOf(access)!==-1)return false;
    if(['yes','required','1','true'].indexOf(fee)!==-1)return false;
    if(indoor==='yes'||covered==='yes'||(building&&building!=='no'))return false;
    if(type==='table_tennis'){
      const leisure=String(tags.leisure||'').toLowerCase();
      const informal=String(tags.informal||'').toLowerCase();
      const explicitlyPublic=['yes','public','permissive','designated'].indexOf(access)!==-1;
      if(!explicitlyPublic&&informal!=='yes'&&leisure!=='park')return false;
    }
    return true;
  };

  RPG.placeOpeningStatus = function(item,raw,nowValue){
    const base=RPG.openingStatus(raw,nowValue);
    if(!RPG.activityPublicOutdoorMode(item))return Object.assign({},base,{heading:'Öffnungszeiten',mode:'opening_hours'});
    const text=String(raw||'').trim();
    if(!text)return {state:'public',label:'Öffentlicher Zugang · Nutzungszeiten nicht bekannt',shortLabel:'',formatted:'',heading:'Zugang & Nutzungszeiten',mode:'public_outdoor'};
    if(/^24\/7$/i.test(text))return {state:'open',label:'Rund um die Uhr nutzbar',shortLabel:'Rund um die Uhr nutzbar',formatted:base.formatted,heading:'Zugang & Nutzungszeiten',mode:'public_outdoor'};
    let label=String(base.label||'');
    let shortLabel=String(base.shortLabel||'');
    if(base.state==='open'){
      label=label.replace(/^Jetzt geöffnet/i,'Jetzt nutzbar');
      shortLabel=shortLabel.replace(/^Jetzt geöffnet/i,'Jetzt nutzbar');
    }else if(base.state==='closed'){
      label=label.replace(/^Geschlossen · öffnet\s+/i,'Nutzbar ab ').replace(/^Aktuell geschlossen$/i,'Aktuell nicht nutzbar');
      shortLabel=shortLabel.replace(/^Geschlossen · öffnet\s+/i,'Nutzbar ab ').replace(/^Aktuell geschlossen$/i,'Aktuell nicht nutzbar');
    }else if(base.state==='known'){
      label='Nutzungszeiten vorhanden';
      shortLabel='Nutzungszeiten vorhanden';
    }
    return Object.assign({},base,{label,shortLabel,heading:'Zugang & Nutzungszeiten',mode:'public_outdoor'});
  };

  RPG.displayPlaceTitle = function(item){
    const title=String(item&&item.title||'').trim();
    if(!item||String(item.category||'')!=='activities'||!title)return title;
    if(!/^(?:Basketballplatz|Skateanlage|Skatepark|Tischtennisanlage|Beachvolleyballfeld|Bolzplatz|Outdoor-Fitnessanlage|Fitnessanlage|Sportplatz)$/i.test(title))return title;
    const tags=item.tags&&typeof item.tags==='object'?item.tags:{};
    const suburb=String(tags['addr:suburb']||tags['addr:district']||'').trim();
    const street=String(tags['addr:street']||'').trim();
    const context=suburb||street;
    if(!context||context.toLocaleLowerCase('de')===String(item.city||'').toLocaleLowerCase('de'))return title;
    return title+' · '+context;
  };
  RPG.CITY_CENTERS = {
    'Alpen':[51.5753,6.5147],'Bergkamen':[51.6167,7.6333],'Bochum':[51.4818,7.2162],'Bönen':[51.5986,7.7583],
    'Bottrop':[51.5216,6.9298],'Breckerfeld':[51.2618,7.4578],'Castrop-Rauxel':[51.5542,7.3114],'Datteln':[51.6533,7.3456],
    'Dinslaken':[51.5647,6.7369],'Dorsten':[51.6596,6.9658],'Dortmund':[51.5136,7.4653],'Duisburg':[51.4344,6.7623],
    'Ennepetal':[51.2982,7.3512],'Essen':[51.4556,7.0116],'Fröndenberg/Ruhr':[51.4725,7.7667],'Gelsenkirchen':[51.5175,7.1003],
    'Gevelsberg':[51.3235,7.3375],'Gladbeck':[51.5728,6.9839],'Hagen':[51.3671,7.4633],'Haltern am See':[51.7483,7.1869],
    'Hamm':[51.6812,7.8183],'Hamminkeln':[51.7303,6.5889],'Hattingen':[51.3986,7.1858],'Herdecke':[51.3989,7.4339],
    'Herne':[51.5426,7.2188],'Herten':[51.5975,7.1389],'Holzwickede':[51.5019,7.6167],'Hünxe':[51.6428,6.7686],
    'Kamen':[51.5919,7.6622],'Kamp-Lintfort':[51.5039,6.5414],'Lünen':[51.6153,7.5258],'Marl':[51.6575,7.0958],
    'Moers':[51.4518,6.6256],'Mülheim an der Ruhr':[51.4272,6.8826],'Neukirchen-Vluyn':[51.4422,6.5583],'Oberhausen':[51.4699,6.8514],
    'Oer-Erkenschwick':[51.6425,7.2522],'Recklinghausen':[51.6142,7.1983],'Rheinberg':[51.5458,6.6028],'Schermbeck':[51.6933,6.8719],
    'Schwelm':[51.2882,7.2947],'Schwerte':[51.4419,7.5683],'Selm':[51.6919,7.4667],'Sonsbeck':[51.6106,6.3769],
    'Sprockhövel':[51.3683,7.2519],'Unna':[51.5347,7.6889],'Voerde (Niederrhein)':[51.6033,6.6858],'Waltrop':[51.6242,7.3969],
    'Werne':[51.6631,7.6358],'Wesel':[51.6572,6.6186],'Wetter (Ruhr)':[51.3853,7.3917],'Witten':[51.4386,7.3364],'Xanten':[51.6617,6.4522]
  };

  RPG.clamp = (n, min, max) => Math.max(min, Math.min(max, n));
  RPG.escapeText = (v) => String(v == null ? '' : v);
  RPG.distanceKm = function (aLat, aLng, bLat, bLng) {
    const toRad = Math.PI / 180;
    const dLat = (bLat - aLat) * toRad;
    const dLng = (bLng - aLng) * toRad;
    const x = Math.sin(dLat / 2) ** 2 + Math.cos(aLat * toRad) * Math.cos(bLat * toRad) * Math.sin(dLng / 2) ** 2;
    return 6371.0088 * 2 * Math.atan2(Math.sqrt(x), Math.sqrt(Math.max(0, 1 - x)));
  };
  RPG.formatDistance = function (km) {
    if (!Number.isFinite(km)) return '';
    if (km < 1) return Math.max(10, Math.round(km * 1000 / 10) * 10) + ' m';
    return (km < 10 ? km.toFixed(1).replace('.', ',') : Math.round(km).toString()) + ' km';
  };
  RPG.locationLabel = function (location) {
    if (!location) return 'Standort';
    const postcode=String(location.postcode||'').trim();
    // Full street addresses are deliberately visible only inside the location
    // dialog. Everywhere else RuhrpottGuru keeps the established coarse label.
    if(String(location.locationMode||'')==='address')return [postcode,String(location.city||'').trim()].filter(Boolean).join(' ')||String(location.city||'Standort');
    if (/^\d{5}$/.test(postcode)) return postcode;
    return String(location.city||location.name||'Standort');
  };

  RPG.normalizeOpeningHoursDays = function(raw){
    const map={Di:'Tu',Mi:'We',Do:'Th',So:'Su'};
    return String(raw||'').replace(/\b(?:Di|Mi|Do|So)\b/g,token=>map[token]||token).trim();
  };
  RPG.openingHoursPlausible = function(raw){
    const text=RPG.normalizeOpeningHoursDays(raw);
    if(!text||/^24\/7$/i.test(text))return true;
    let total=0;
    for(const segment of text.split(';')){
      const seg=segment.trim();if(!seg)continue;
      const m=seg.match(/^((?:(?:Mo|Tu|We|Th|Fr|Sa|Su|PH|SH)(?:-(?:Mo|Tu|We|Th|Fr|Sa|Su|PH|SH))?(?:,\s*)?)+)\s+(.+)$/i);
      if(!m)continue;
      const body=m[2].trim();if(/^(?:off|closed|geschlossen)$/i.test(body))continue;
      const ranges=Array.from(body.matchAll(/(\d{1,2}):(\d{2})\s*-\s*(\d{1,2}):(\d{2})/g));
      if(ranges.length>3)return false;
      for(const row of ranges){
        const sh=Number(row[1]),sm=Number(row[2]),eh=Number(row[3]),em=Number(row[4]);
        if(sm>59||em>59||sh>24||eh>24||(sh===24&&sm!==0)||(eh===24&&em!==0))return false;
      }
      total+=ranges.length;if(total>21)return false;
    }
    return true;
  };

  RPG.formatOpeningHours = function(raw){
    if(!RPG.openingHoursPlausible(raw))return '';
    const text=RPG.normalizeOpeningHoursDays(raw);
    if(!text)return '';
    if(/^24\/7$/i.test(text))return 'Täglich rund um die Uhr';

    const day={Mo:'Mo',Tu:'Di',We:'Mi',Th:'Do',Fr:'Fr',Sa:'Sa',Su:'So',PH:'Feiertage',SH:'Schulferien'};
    const formatDays=spec=>String(spec||'').trim().split(',').map(part=>{
      const p=part.trim();
      const m=p.match(/^(Mo|Tu|We|Th|Fr|Sa|Su|PH|SH)-(Mo|Tu|We|Th|Fr|Sa|Su|PH|SH)$/);
      if(m)return (day[m[1]]||m[1])+'–'+(day[m[2]]||m[2]);
      return day[p]||p;
    }).join(', ');

    return text.split(';').map(segment=>{
      let s=segment.trim();
      if(!s)return '';
      s=s.replace(/\boff\b/gi,'geschlossen').replace(/\bopen\b/gi,'geöffnet');
      const m=s.match(/^((?:(?:Mo|Tu|We|Th|Fr|Sa|Su|PH|SH)(?:-(?:Mo|Tu|We|Th|Fr|Sa|Su|PH|SH))?(?:,\s*)?)+)\s+(.+)$/);
      if(m)return formatDays(m[1])+' '+m[2].replace(/(\d{1,2}:\d{2})-(\d{1,2}:\d{2})/g,'$1–$2');
      return s.replace(/\b(Mo|Tu|We|Th|Fr|Sa|Su|PH|SH)\b/g,x=>day[x]||x)
        .replace(/(\d{1,2}:\d{2})-(\d{1,2}:\d{2})/g,'$1–$2');
    }).filter(Boolean).join(' · ');
  };

  // V214: Opening-hours display released globally for all place categories.
  // The Places backend already persists canonical schedules centrally; the theme
  // now renders those same schedules everywhere instead of limiting them to the
  // former Wesel/Oberhausen pilot. Events keep their separate event-time logic.
  RPG.isOpeningHoursPilot = function(item){
    if(!item)return false;
    return String(item.category||'').toLowerCase()!=='events';
  };

  RPG.openingStatus = function(raw, nowValue){
    const original=String(raw||'').trim();
    if(!original)return {state:'unknown',label:'Öffnungszeiten nicht bekannt',shortLabel:'',formatted:''};
    if(!RPG.openingHoursPlausible(original))return {state:'unknown',label:'Öffnungszeiten werden geprüft',shortLabel:'',formatted:''};
    const text=RPG.normalizeOpeningHoursDays(original);
    const formatted=RPG.formatOpeningHours(text);
    if(/^24\/7$/i.test(text))return {state:'open',label:'Jetzt geöffnet · rund um die Uhr',shortLabel:'Jetzt geöffnet · 24/7',formatted};

    const dayCodes=['Mo','Tu','We','Th','Fr','Sa','Su'];
    const dayNames=['Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag','Sonntag'];
    const schedule=dayCodes.map(()=>[]);
    const expandDays=function(spec){
      const out=[];
      String(spec||'').split(',').forEach(part=>{
        const p=part.trim();
        if(!p)return;
        const range=p.match(/^(Mo|Tu|We|Th|Fr|Sa|Su)-(Mo|Tu|We|Th|Fr|Sa|Su)$/);
        if(range){
          const a=dayCodes.indexOf(range[1]),b=dayCodes.indexOf(range[2]);
          if(a<0||b<0)return;
          let i=a;
          for(let guard=0;guard<7;guard++){out.push(i);if(i===b)break;i=(i+1)%7;}
          return;
        }
        const idx=dayCodes.indexOf(p);if(idx>=0)out.push(idx);
      });
      return Array.from(new Set(out));
    };
    const toMinutes=function(h,m){
      h=Number(h);m=Number(m);
      if(!Number.isFinite(h)||!Number.isFinite(m)||m<0||m>59||h<0||h>24||(h===24&&m!==0))return null;
      return h*60+m;
    };
    let parsedRanges=0;
    text.split(';').forEach(segment=>{
      const seg=segment.trim();if(!seg)return;
      const m=seg.match(/^((?:(?:Mo|Tu|We|Th|Fr|Sa|Su)(?:-(?:Mo|Tu|We|Th|Fr|Sa|Su))?(?:,\s*)?)+)\s+(.+)$/i);
      if(!m)return;
      const days=expandDays(m[1]);if(!days.length)return;
      const body=m[2].trim();
      if(/^(?:off|closed|geschlossen)$/i.test(body))return;
      const ranges=[];
      const re=/(\d{1,2}):(\d{2})\s*-\s*(\d{1,2}):(\d{2})/g;
      let tm;
      while((tm=re.exec(body))){
        const start=toMinutes(tm[1],tm[2]),end=toMinutes(tm[3],tm[4]);
        if(start===null||end===null)continue;
        ranges.push({start,end});parsedRanges++;
      }
      days.forEach(idx=>{schedule[idx].push(...ranges);});
    });
    if(!parsedRanges)return {state:'known',label:'Öffnungszeiten vorhanden',shortLabel:'Öffnungszeiten vorhanden',formatted};

    const now=nowValue instanceof Date?nowValue:new Date(nowValue||Date.now());
    let parts;
    try{
      const dtf=new Intl.DateTimeFormat('de-DE',{timeZone:'Europe/Berlin',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',hourCycle:'h23'});
      parts=Object.fromEntries(dtf.formatToParts(now).filter(x=>x.type!=='literal').map(x=>[x.type,x.value]));
    }catch(_){parts={year:String(now.getFullYear()),month:String(now.getMonth()+1),day:String(now.getDate()),hour:String(now.getHours()),minute:String(now.getMinutes())};}
    const y=Number(parts.year),mo=Number(parts.month),d=Number(parts.day),hour=Number(parts.hour),minute=Number(parts.minute);
    const jsDay=new Date(Date.UTC(y,mo-1,d)).getUTCDay();
    const today=(jsDay+6)%7; // Monday=0
    const mins=hour*60+minute;
    const hhmm=function(value){
      const v=((value%1440)+1440)%1440;
      return String(Math.floor(v/60)).padStart(2,'0')+':'+String(v%60).padStart(2,'0');
    };

    let active=null;
    // Normal/current-day ranges and the current-day portion of overnight ranges.
    schedule[today].forEach(r=>{
      if(active)return;
      if(r.end>r.start){if(mins>=r.start&&mins<r.end)active={close:r.end};}
      else if(r.end<r.start){if(mins>=r.start)active={close:r.end+1440};}
      else if(r.start===0&&r.end===0){active={close:1440};}
    });
    // An overnight interval that started yesterday can still be open after midnight.
    if(!active){
      const prev=(today+6)%7;
      schedule[prev].forEach(r=>{if(!active&&r.end<r.start&&mins<r.end)active={close:r.end};});
    }
    if(active){
      const close=hhmm(active.close);
      return {state:'open',label:'Jetzt geöffnet · bis '+close,shortLabel:'Jetzt geöffnet · bis '+close,formatted};
    }

    let next=null;
    for(let delta=0;delta<=7&&!next;delta++){
      const idx=(today+delta)%7;
      const starts=schedule[idx].map(r=>r.start).sort((a,b)=>a-b);
      for(const start of starts){
        if(delta===0&&start<=mins)continue;
        next={delta,start,day:idx};break;
      }
    }
    if(next){
      let when=next.delta===0?'heute':next.delta===1?'morgen':dayNames[next.day];
      const label='Geschlossen · öffnet '+when+' um '+hhmm(next.start);
      return {state:'closed',label,shortLabel:label,formatted};
    }
    return {state:'closed',label:'Aktuell geschlossen',shortLabel:'Aktuell geschlossen',formatted};
  };

  RPG.safeUrl = function (value) {
    try {
      if (!value) return '';
      const u = new URL(String(value), window.location.origin);
      return /^https?:$/.test(u.protocol) ? u.href : '';
    } catch (_) { return ''; }
  };
  RPG.shareCategoryCode = function(category){
    const map={events:'e',food:'f',highlights:'h',culture:'c',activities:'a',wurst:'w',buedchen:'b'};
    return map[String(category||'').toLowerCase()]||'';
  };
  RPG.shareCategoryFromCode = function(code){
    const map={e:'events',f:'food',h:'highlights',c:'culture',a:'activities',w:'wurst',b:'buedchen'};
    return map[String(code||'').toLowerCase()]||'';
  };
  RPG.shareCategorySegment = function(category){
    const map={events:'event',food:'food',highlights:'highlight',culture:'kultur',activities:'aktiv',wurst:'imbiss',buedchen:'buedchen'};
    return map[String(category||'').toLowerCase()]||'ort';
  };
  RPG.shareSlug = function(value){
    return String(value||'tipp').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/ß/g,'ss').replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'').slice(0,90)||'tipp';
  };
  RPG.shareDescriptor = function(item){
    if(!item||typeof item!=='object')return null;
    const category=String(item.category||'').toLowerCase();
    const code=RPG.shareCategoryCode(category);
    if(!code)return null;
    const idText=String(item.id==null?'':item.id);
    if(category==='events'||item.kind==='event'){
      const db=item.raw&&item.raw.rpg_event_db&&typeof item.raw.rpg_event_db==='object'?Number(item.raw.rpg_event_db.eventId):NaN;
      const m=idText.match(/^event:(\d+)$/i);
      const eventId=Number.isFinite(db)&&db>0?db:(m?Number(m[1]):NaN);
      if(!Number.isFinite(eventId)||eventId<=0)return null;
      return {token:'e'+eventId,category:'events',code:'e',segment:'event',id:String(eventId),slug:RPG.shareSlug(item.title)};
    }
    const routeSlug=item.routeIndustrial&&item.tags?String(item.tags['rg:industrial_route_slug']||'').trim():'';
    if(/^rdi:/i.test(idText)||routeSlug){
      const rdiSlug=(routeSlug||idText.replace(/^rdi:/i,'')).trim();
      if(!rdiSlug)return null;
      return {token:'rdi:'+rdiSlug,category:'highlights',code:'h',segment:'industriekultur',id:encodeURIComponent(rdiSlug),slug:RPG.shareSlug(item.title)};
    }
    const m=idText.match(/^(?:place:)?(\d+)$/i);
    if(!m)return null;
    return {token:'p'+Number(m[1]),category,code,segment:RPG.shareCategorySegment(category),id:String(Number(m[1])),slug:RPG.shareSlug(item.title)};
  };
  RPG.buildShareUrl = function(item){
    // V276: once the backend supplies an indexable canonical URL, all share
    // actions use it. Older backends/records keep the proven /teilen/ fallback.
    const canonical=RPG.safeUrl(item&&item.canonicalUrl||'');
    if(canonical)return canonical;
    const d=RPG.shareDescriptor(item);
    if(!d)return window.location.href;
    const base=String(RPG.settings.shareBaseUrl||((RPG.settings.homeUrl||window.location.origin+'/').replace(/\/?$/,'/')+'teilen/')).replace(/\/?$/,'/');
    return base+d.segment+'/'+d.id+'/'+encodeURIComponent(d.slug)+'/';
  };
  // V263: external Event direct links close into a temporary Event-location
  // context. The URL carries coordinates only for the destination page; location.js
  // explicitly treats them as non-persistent, so the user's saved GPS/city remains intact.
  RPG.eventContextUrl = function(item,radius){
    if(!item||String(item.category||'').toLowerCase()!=='events')return '';
    const home=String(RPG.settings.homeUrl||window.location.origin+'/').replace(/\/?$/,'/');
    let url;try{url=new URL('veranstaltungen/',home);}catch(_){return '';}
    const coords=item.coords&&typeof item.coords==='object'?item.coords:{};
    const lat=Number(coords.lat),lng=Number(coords.lng);
    if(Number.isFinite(lat)&&Number.isFinite(lng)&&Math.abs(lat)<=90&&Math.abs(lng)<=180){
      url.searchParams.set('rg_lat',lat.toFixed(6));
      url.searchParams.set('rg_lng',lng.toFixed(6));
      if(item.city)url.searchParams.set('rg_city',String(item.city));
    }else if(item.city){
      url.searchParams.set('place',String(item.city));
    }
    const r=Number(radius);if(Number.isFinite(r)&&r>=1&&r<=50)url.searchParams.set('radius',String(r));
    return url.toString();
  };
  RPG.getCoords = function (el) {
    if (!el) return null;
    const lat = Number(el.lat != null ? el.lat : el.center && el.center.lat);
    const lng = Number(el.lon != null ? el.lon : el.center && el.center.lon);
    return Number.isFinite(lat) && Number.isFinite(lng) ? { lat, lng } : null;
  };
  RPG.tags = function (el) { return el && el.tags && typeof el.tags === 'object' ? el.tags : {}; };
  RPG.storage = {
    get(key) { try { return window.localStorage.getItem(key); } catch (_) { return null; } },
    set(key, value) { try { window.localStorage.setItem(key, value); } catch (_) {} }
  };
  RPG.readJsonScript = function (id, fallback) {
    const node = document.getElementById(id);
    if (!node) return fallback;
    try { return JSON.parse(node.textContent || ''); } catch (_) { return fallback; }
  };
  RPG.nearestCity = function (lat, lng) {
    let best = 'Essen', bestD = Infinity;
    Object.entries(RPG.CITY_CENTERS).forEach(([name, pair]) => {
      const d = RPG.distanceKm(lat, lng, pair[0], pair[1]);
      if (d < bestD) { best = name; bestD = d; }
    });
    return best;
  };
  RPG.personHint = function (text) {
    return /\b(person|people|portrait|selfie|staff|team|employee|owner|chef|waiter|guest|customer|crowd|family|friends|mann|frau|menschen|gruppe|gaeste|gäste|mitarbeiter|koch|kellner|besucher|men|women|man|woman)\b/i.test(String(text || ''));
  };
})(window);
