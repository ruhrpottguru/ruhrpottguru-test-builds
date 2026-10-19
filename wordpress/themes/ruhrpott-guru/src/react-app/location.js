(function (window) {
  'use strict';
  const RPG = window.RPGReact;
  const { useState, useEffect, useCallback } = RPG.React;
  const PREF_KEY = 'ruhrpottguru_location_preference_v1';
  const GPS_KEY = 'ruhrpottguru_last_gps_v1';
  const RADIUS_KEY = 'ruhrpottguru_radius_km_v1';
  const RADIUS_COOKIE = 'rg_radius_km_v1';
  const SNAPSHOT_KEY = 'ruhrpottguru_location_snapshot_v2';
  const FALLBACK_COOKIE = 'rg_location_city_v1';
  const FALLBACK_MODE_COOKIE = 'rg_location_mode_v1';
  let reloadGpsRefreshStarted = false;

  // V256 — URL-selected test/direct location.
  // ?place=essen (optionally &radius=10) must be resolved before the first
  // React render so accessibility crawlers never hit the geolocation prompt.
  // The URL override is intentionally not persisted to localStorage/cookies.
  function normalizePlaceKey(value){
    return String(value||'').trim().toLowerCase()
      .normalize('NFD').replace(/[\u0300-\u036f]/g,'')
      .replace(/ß/g,'ss')
      .replace(/[^a-z0-9]+/g,'');
  }
  function readUrlPlace(){
    try{
      const params=new URLSearchParams(window.location.search||'');
      // V263: temporary precise Event context from an external direct link. This
      // branch is intentionally read-only: it is never passed to storeLocation().
      if(params.has('rg_lat')&&params.has('rg_lng')){
        const lat=Number(params.get('rg_lat')),lng=Number(params.get('rg_lng'));
        if(Number.isFinite(lat)&&Number.isFinite(lng)&&Math.abs(lat)<=90&&Math.abs(lng)<=180){
          const requestedCity=normalizePlaceKey(params.get('rg_city'));
          const matchedCity=requestedCity?Object.keys(RPG.CITY_CENTERS||{}).find(name=>normalizePlaceKey(name)===requestedCity):'';
          const city=matchedCity||RPG.nearestCity(lat,lng)||'';
          const eventContext=/\/veranstaltungen\/?$/.test(window.location.pathname);
          return {name:(city?city+' · ':'')+(eventContext?'Event-Ort':'Ort'),city,lat,lng,isGPS:false,urlOverride:true,urlContext:true,eventContext};
        }
      }
      const requested=normalizePlaceKey(params.get('place'));
      if(!requested)return null;
      const city=Object.keys(RPG.CITY_CENTERS||{}).find(name=>normalizePlaceKey(name)===requested);
      if(!city)return null;
      const pair=RPG.CITY_CENTERS[city];
      if(!pair||!Number.isFinite(Number(pair[0]))||!Number.isFinite(Number(pair[1])))return null;
      return {name:city+' (Zentrum)',city,lat:Number(pair[0]),lng:Number(pair[1]),isGPS:false,urlOverride:true};
    }catch(_){return null;}
  }
  function readUrlRadius(){
    try{
      const params=new URLSearchParams(window.location.search||'');
      if(!params.has('radius'))return null;
      const n=Number(String(params.get('radius')||'').replace(',','.'));
      return Number.isFinite(n)&&n>=1&&n<=50?n:null;
    }catch(_){return null;}
  }

  function readFallbackCity(){
    try{
      const hit=document.cookie.split(';').map(x=>x.trim()).find(x=>x.startsWith(FALLBACK_COOKIE+'='));
      if(!hit)return '';
      const value=decodeURIComponent(hit.slice(FALLBACK_COOKIE.length+1));
      return RPG.CITY_CENTERS[value]?value:'';
    }catch(_){return '';}
  }
  function readFallbackMode(){
    try{
      const hit=document.cookie.split(';').map(x=>x.trim()).find(x=>x.startsWith(FALLBACK_MODE_COOKIE+'='));
      if(!hit)return '';
      const value=decodeURIComponent(hit.slice(FALLBACK_MODE_COOKIE.length+1));
      return value==='gps'||value==='manual'?value:'';
    }catch(_){return '';}
  }
  function storeFallbackCity(city,isGPS){
    if(!city||!RPG.CITY_CENTERS[city])return;
    try{
      const host=String(window.location.hostname||'').toLowerCase();
      const domain=(host==='ruhrpottguru.de'||host.endsWith('.ruhrpottguru.de'))?'; Domain=.ruhrpottguru.de':'';
      document.cookie=FALLBACK_COOKIE+'='+encodeURIComponent(city)+'; Path=/; Max-Age=31536000; SameSite=Lax'+domain;
      document.cookie=FALLBACK_MODE_COOKIE+'='+(isGPS?'gps':'manual')+'; Path=/; Max-Age=31536000; SameSite=Lax'+domain;
    }catch(_){}
  }

  // V294 — radius needs the same cross-host persistence as the location.
  // localStorage is origin-scoped, so ruhrpottguru.de and www.ruhrpottguru.de
  // can otherwise keep Selm via the shared location cookie but silently fall
  // back to 5 km. The cookie stores only the coarse radius, never coordinates.
  function readFallbackRadius(){
    try{
      const hit=document.cookie.split(';').map(x=>x.trim()).find(x=>x.startsWith(RADIUS_COOKIE+'='));
      if(!hit)return null;
      const n=Number(String(decodeURIComponent(hit.slice(RADIUS_COOKIE.length+1))||'').replace(',','.'));
      return Number.isFinite(n)&&n>=1&&n<=50?n:null;
    }catch(_){return null;}
  }
  function storeFallbackRadius(value){
    const n=Number(value);
    if(!Number.isFinite(n)||n<1||n>50)return;
    try{
      const host=String(window.location.hostname||'').toLowerCase();
      const domain=(host==='ruhrpottguru.de'||host.endsWith('.ruhrpottguru.de'))?'; Domain=.ruhrpottguru.de':'';
      document.cookie=RADIUS_COOKIE+'='+encodeURIComponent(String(n))+'; Path=/; Max-Age=31536000; SameSite=Lax'+domain;
    }catch(_){}
  }

  function isRealReload(){
    try{
      if(window.performance&&typeof window.performance.getEntriesByType==='function'){
        const nav=window.performance.getEntriesByType('navigation');
        if(nav&&nav[0]&&nav[0].type)return nav[0].type==='reload';
      }
      return !!(window.performance&&window.performance.navigation&&window.performance.navigation.type===1);
    }catch(_){return false;}
  }

  function normalizeSavedLocation(raw) {
    if (!raw || !Number.isFinite(Number(raw.lat)) || !Number.isFinite(Number(raw.lng))) return null;
    const lat=Number(raw.lat),lng=Number(raw.lng),isGPS=!!raw.isGPS;
    const city=raw.city || (isGPS ? RPG.nearestCity(lat,lng) : String(raw.name||'').replace(/ \(Zentrum\)$/,''));
    return {
      name:raw.name || (isGPS ? 'Dein Standort (GPS)' : (city ? city+' (Zentrum)' : 'Gewählter Standort')),
      city:city || RPG.nearestCity(lat,lng),
      postcode:/^\d{5}$/.test(String(raw.postcode||''))?String(raw.postcode):'',
      locationMode:raw.locationMode || (isGPS?'gps':(/^\d{5}$/.test(String(raw.postcode||''))?'postcode':'manual')),
      lat,lng,
      accuracyMeters:raw.accuracyMeters || null,
      isGPS,
      savedAt:Number(raw.savedAt)||null,
      fallbackCoarse:!!raw.fallbackCoarse
    };
  }

  function readSaved() {
    try {
      // One persistent cross-page snapshot is authoritative for normal navigation.
      // V227 may refresh an existing GPS snapshot only on a real browser reload,
      // and only when geolocation permission is already granted.
      const snapshot=normalizeSavedLocation(JSON.parse(RPG.storage.get(SNAPSHOT_KEY) || 'null'));
      if(snapshot)return snapshot;

      // Backward compatibility with all earlier RuhrpottGuru builds.
      const pref = JSON.parse(RPG.storage.get(PREF_KEY) || 'null');
      if (pref && (pref.mode === 'manual' || pref.mode === 'postcode' || pref.mode === 'address') && pref.location) {
        const manual=normalizeSavedLocation({
          name:pref.location.name || 'Gewählter Standort',
          city:pref.location.city || (pref.location.name || '').replace(/ \(Zentrum\)$/,''),
          postcode:pref.location.postcode || '',locationMode:pref.mode,
          lat:Number(pref.location.lat),lng:Number(pref.location.lng),isGPS:false
        });
        if(manual)return manual;
      }
      const gps = JSON.parse(RPG.storage.get(GPS_KEY) || 'null');
      if (gps) {
        const savedGps=normalizeSavedLocation({
          name:'Dein Standort (GPS)',
          city:RPG.nearestCity(Number(gps.lat),Number(gps.lng)),
          lat:Number(gps.lat),lng:Number(gps.lng),
          accuracyMeters:gps.accuracyMeters || null,isGPS:true
        });
        if(savedGps)return savedGps;
      }
    } catch (_) {}

    // Desktop hardening: if an internal navigation changes between
    // ruhrpottguru.de and www.ruhrpottguru.de, localStorage belongs to a
    // different origin. We keep only the coarse city in a first-party,
    // same-site cookie so the app can resume without another GPS prompt.
    // Precise GPS coordinates remain localStorage-only.
    const city=readFallbackCity();
    if(city){
      const pair=RPG.CITY_CENTERS[city];
      const isGPS=readFallbackMode()==='gps';
      return {
        name:isGPS?'Dein Standort (GPS)':city+' (Zentrum)',
        city,lat:pair[0],lng:pair[1],isGPS,
        fallbackCoarse:isGPS
      };
    }
    return null;
  }
  function savedRadius() {
    const fromUrl=readUrlRadius();
    if(fromUrl!==null)return fromUrl;
    const n = Number(RPG.storage.get(RADIUS_KEY));
    if(Number.isFinite(n) && n >= 1 && n <= 50){storeFallbackRadius(n);return n;}
    const fallback=readFallbackRadius();
    if(fallback!==null){RPG.storage.set(RADIUS_KEY,String(fallback));return fallback;}
    return 5;
  }
  function storeLocation(loc) {
    if (!loc) return;
    const snapshot={
      name:loc.name || '',
      city:loc.city || '',
      postcode:/^\d{5}$/.test(String(loc.postcode||''))?String(loc.postcode):'',
      locationMode:loc.locationMode || (loc.isGPS?'gps':(/^\d{5}$/.test(String(loc.postcode||''))?'postcode':'manual')),
      lat:Number(loc.lat),
      lng:Number(loc.lng),
      accuracyMeters:loc.accuracyMeters || null,
      isGPS:!!loc.isGPS,
      savedAt:Date.now()
    };
    RPG.storage.set(SNAPSHOT_KEY,JSON.stringify(snapshot));
    storeFallbackCity(loc.city || RPG.nearestCity(Number(loc.lat),Number(loc.lng)),!!loc.isGPS);

    if (loc.isGPS) {
      RPG.storage.set(PREF_KEY, JSON.stringify({ mode:'gps' }));
      RPG.storage.set(GPS_KEY, JSON.stringify({ lat:loc.lat,lng:loc.lng,accuracyMeters:loc.accuracyMeters || null,savedAt:Date.now() }));
      RPG.storage.set('ruhrpottguru_gps_granted','true');
    } else {
      RPG.storage.set(PREF_KEY, JSON.stringify({ mode:loc.locationMode==='address'?'address':(loc.postcode?'postcode':'manual'), location:{ name:loc.name,city:loc.city||'',postcode:loc.postcode||'',lat:loc.lat,lng:loc.lng } }));
    }
    try{window.dispatchEvent(new CustomEvent('rpg:location-changed',{detail:snapshot}));}catch(_){}
  }

  function rpgAddressFeaturePoint(data) {
    let feature=data;
    if(data&&data.type==='FeatureCollection'&&Array.isArray(data.features))feature=data.features[0]||null;
    if(Array.isArray(data)&&data.length)feature=data[0];
    if(!feature||typeof feature!=='object')return null;
    const geometry=feature.geometry||null;
    if(geometry&&geometry.type==='Point'&&Array.isArray(geometry.coordinates)&&geometry.coordinates.length>=2){
      const lng=Number(geometry.coordinates[0]),lat=Number(geometry.coordinates[1]);
      if(Number.isFinite(lat)&&Number.isFinite(lng)&&Math.abs(lat)<=90&&Math.abs(lng)<=180)return {lat,lng,feature};
    }
    const bbox=Array.isArray(feature.bbox)?feature.bbox:(Array.isArray(data&&data.bbox)?data.bbox:null);
    if(bbox&&bbox.length>=4){
      const west=Number(bbox[0]),south=Number(bbox[1]),east=Number(bbox[2]),north=Number(bbox[3]);
      if([west,south,east,north].every(Number.isFinite))return {lat:(south+north)/2,lng:(west+east)/2,feature};
    }
    return null;
  }
  function rpgMatchRuhrCity(value) {
    const key=normalizePlaceKey(value);
    if(!key)return '';
    const direct=Object.keys(RPG.CITY_CENTERS||{}).find(name=>normalizePlaceKey(name)===key);
    if(direct)return direct;
    const aliases={
      froendenberg:'Fröndenberg/Ruhr',froendenbergruhr:'Fröndenberg/Ruhr',
      voerde:'Voerde (Niederrhein)',voerdeniederrhein:'Voerde (Niederrhein)',
      wetter:'Wetter (Ruhr)',wetterruhr:'Wetter (Ruhr)',
      muelheimruhr:'Mülheim an der Ruhr',muelheimanderruhr:'Mülheim an der Ruhr'
    };
    return aliases[key]||'';
  }
  function rpgAddressCity(feature) {
    const props=feature&&feature.properties&&typeof feature.properties==='object'?feature.properties:{};
    const candidates=[props.gemeinde,props.verwgem,props.ort];
    for(const value of candidates){const city=rpgMatchRuhrCity(value);if(city)return city;}
    return '';
  }
  function rpgAddressLabel(feature,city) {
    const props=feature&&feature.properties&&typeof feature.properties==='object'?feature.properties:{};
    const text=String(props.text||props.label||'').trim();
    if(text)return text;
    const street=String(props.strasse||'').trim(),house=String(props.haus||'').trim(),postcode=String(props.plz||'').trim();
    return [street+(house?' '+house:''),[postcode,city].filter(Boolean).join(' ')].filter(Boolean).join(', ') || city || 'Gewählte Adresse';
  }

  RPG.useLocation = function (required) {
    required = required !== false;
    const urlLocation=readUrlPlace();
    const [location, setLocation] = useState(function(){return urlLocation || readSaved();});
    const [radius, setRadiusState] = useState(savedRadius);
    const [modalOpen, setModalOpen] = useState(false);
    const [status, setStatus] = useState(location ? 'ready' : 'prompt');
    const [error, setError] = useState('');

    const requestGps = useCallback(function () {
      if (!navigator.geolocation) { setError('Standort wird von diesem Browser nicht unterstützt. Bitte wähle eine Stadt, gib eine PLZ ein oder nutze die Adresssuche.'); setModalOpen(true); return; }
      setStatus('locating'); setError('');
      navigator.geolocation.getCurrentPosition(function (pos) {
        const loc = { name:'Dein Standort (GPS)', city:RPG.nearestCity(pos.coords.latitude,pos.coords.longitude), lat:pos.coords.latitude,lng:pos.coords.longitude,accuracyMeters:pos.coords.accuracy,isGPS:true,locationMode:'gps' };
        storeLocation(loc); setLocation(loc); setStatus('ready'); setModalOpen(false);
      }, function (err) {
        setStatus('needs-selection'); setError(err && err.code === 1 ? 'Standortzugriff wurde blockiert. Bitte wähle eine Stadt, gib eine PLZ ein oder nutze die Adresssuche.' : 'Standort konnte nicht bestimmt werden. Bitte wähle eine Stadt, gib eine PLZ ein oder nutze die Adresssuche.'); setModalOpen(true);
      }, { enableHighAccuracy:true, timeout:25000, maximumAge:0 });
    }, []);

    const refreshGpsOnReload = useCallback(function (current) {
      if(!current||!current.isGPS||!isRealReload()||reloadGpsRefreshStarted)return;
      reloadGpsRefreshStarted=true;

      // Never trigger a browser permission dialog during an automatic refresh.
      // We only ask for a fresh fix when the browser confirms that geolocation
      // is already granted. Browsers without Permissions API keep the saved GPS
      // location until the visitor explicitly chooses GPS again.
      if(!navigator.geolocation||!navigator.permissions||typeof navigator.permissions.query!=='function')return;
      navigator.permissions.query({name:'geolocation'}).then(function(permission){
        if(!permission||permission.state!=='granted')return;
        navigator.geolocation.getCurrentPosition(function(pos){
          const next={
            name:'Dein Standort (GPS)',
            city:RPG.nearestCity(pos.coords.latitude,pos.coords.longitude),
            lat:pos.coords.latitude,
            lng:pos.coords.longitude,
            accuracyMeters:pos.coords.accuracy,
            isGPS:true,
            locationMode:'gps'
          };
          const movedKm=RPG.distanceKm(Number(current.lat),Number(current.lng),next.lat,next.lng);
          const accuracyThresholdKm=Math.max(
            0.075,
            Math.min(0.25,Math.max(Number(current.accuracyMeters)||0,Number(next.accuracyMeters)||0)/1000)
          );
          const changedCity=String(current.city||'')!==String(next.city||'');
          if(current.fallbackCoarse||changedCity||movedKm>=accuracyThresholdKm){
            storeLocation(next);
            setLocation(next);
            setStatus('ready');
          }
        },function(){
          // Silent refresh failure must not disturb the current page or open a modal.
        },{enableHighAccuracy:true,timeout:15000,maximumAge:0});
      }).catch(function(){
        // Permission state could not be determined: keep the saved location silently.
      });
    },[]);

    useEffect(function () {
      // URL-selected locations are authoritative for this page load and must
      // never trigger geolocation. This is the accessibility/crawler path.
      if(urlLocation)return;

      // First-ever visit: normal GPS bootstrap may request permission.
      if(required&&!location){requestGps();return;}

      // Existing GPS mode: only an actual browser reload may refresh the fix.
      // Ordinary RuhrpottGuru page navigation keeps the current coordinates frozen.
      if(required&&location&&location.isGPS)refreshGpsOnReload(location);
    },[required]);

    const chooseCity = useCallback(function (city) {
      const pair = RPG.CITY_CENTERS[city]; if (!pair) return;
      const loc = { name:city + ' (Zentrum)', city, postcode:'', lat:pair[0], lng:pair[1], isGPS:false, locationMode:'manual' };
      storeLocation(loc); setLocation(loc); setStatus('ready'); setModalOpen(false); setError('');
    }, []);
    const choosePostcode = useCallback(async function (value) {
      const postcode=String(value||'').replace(/\s+/g,'').trim();
      if(!/^\d{5}$/.test(postcode)){
        setStatus('needs-selection');
        setError('Bitte gib eine gültige 5-stellige PLZ ein.');
        setModalOpen(true);
        return false;
      }
      setStatus('resolving-postcode'); setError('');
      try{
        if(!RPG.api||typeof RPG.api.postcodeLocation!=='function')throw new Error('PLZ-Suche ist gerade nicht verfügbar.');
        const data=await RPG.api.postcodeLocation(postcode);
        const lat=Number(data&&data.lat),lng=Number(data&&data.lng);
        if(!Number.isFinite(lat)||!Number.isFinite(lng))throw new Error('Für diese PLZ wurde kein Mittelpunkt gefunden.');
        const city=RPG.nearestCity(lat,lng)||'';
        const loc={name:'PLZ '+postcode,city,postcode,lat,lng,isGPS:false,locationMode:'postcode'};
        storeLocation(loc); setLocation(loc); setStatus('ready'); setModalOpen(false); setError('');
        return true;
      }catch(err){
        setStatus('needs-selection');
        const message=err&&err.message?String(err.message):'Die PLZ konnte gerade nicht aufgelöst werden.';
        setError(message); setModalOpen(true);
        return false;
      }
    }, []);

    const chooseAddressResult = useCallback(function (data) {
      const point=rpgAddressFeaturePoint(data);
      if(!point){
        setStatus('needs-selection');
        setError('Die Adresse konnte nicht eindeutig verortet werden. Bitte wähle einen Treffer aus der amtlichen NRW-Suche.');
        setModalOpen(true);
        return false;
      }
      const city=rpgAddressCity(point.feature);
      if(!city){
        setStatus('needs-selection');
        setError('Diese Adresse liegt außerhalb des RuhrpottGuru-Gebiets. Bitte wähle eine Adresse innerhalb der Metropole Ruhr.');
        setModalOpen(true);
        return false;
      }
      const props=point.feature&&point.feature.properties&&typeof point.feature.properties==='object'?point.feature.properties:{};
      const postcode=/^\d{5}$/.test(String(props.plz||''))?String(props.plz):'';
      const loc={
        name:rpgAddressLabel(point.feature,city),city,postcode,
        lat:point.lat,lng:point.lng,isGPS:false,locationMode:'address'
      };
      storeLocation(loc);setLocation(loc);setStatus('ready');setModalOpen(false);setError('');
      return true;
    }, []);

    const setRadius = useCallback(function (value) {
      const n = RPG.clamp(Number(value) || 5,1,50); RPG.storage.set(RADIUS_KEY,String(n)); storeFallbackRadius(n); setRadiusState(n);
      try{window.dispatchEvent(new CustomEvent('rpg:radius-changed',{detail:{radius:n}}));}catch(_){}
    }, []);

    return { location, radius, setRadius, status, error, modalOpen, setModalOpen, requestGps, chooseCity, choosePostcode, chooseAddressResult };
  };
})(window);
