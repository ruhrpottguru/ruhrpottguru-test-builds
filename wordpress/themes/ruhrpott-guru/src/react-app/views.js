(function(window){
  'use strict';
  const RPG=window.RPGReact,h=RPG.h;
  const {useState,useEffect,useMemo,useRef}=RPG.React;
  const C=RPG.Components;
  const track=(event_type,data)=>{try{window.RPG_ANALYTICS&&window.RPG_ANALYTICS.send(event_type,data||{});}catch(_){}};

  function EmptyRadiusState({radius,onExpand,filtered}){
    const steps=[1,2.5,5,10,25,50],current=Number(radius)||5,next=steps.find(x=>x>current);
    return h('div',{className:'rpgx-smart-empty'},h('strong',null,filtered?'Für diesen Filter gibt es gerade keine Treffer.':'Direkt um dich herum ist gerade nichts Passendes.'),h('p',null,filtered?'Ändere den Filter oder die Suche.':(next?'Erweitere den Suchbereich, ohne deinen Standort zu ändern.':'Für diesen Bereich haben wir aktuell keine passenden Treffer.')),!filtered&&next&&onExpand?h('button',{type:'button',className:'rpgx-smart-empty-btn',onClick:()=>onExpand(next)},'Radius auf '+next+' km erweitern'):null);
  }

  function homeBlueprint(){
    const source=document.getElementById('ruhrpott-wp-page-content-source');
    const editor=source&&source.querySelector('.rg-wp-home-editor-content');
    const fallback=['events','food','highlights','culture','activities'].map(category=>({type:'category',category,url:''}));
    if(!editor)return fallback;

    const result=[];
    const seen=new Set();
    Array.from(editor.childNodes).forEach(node=>{
      if(node.nodeType===3){
        const text=(node.textContent||'').trim();
        if(text)result.push({type:'editorial',html:text});
        return;
      }
      if(node.nodeType!==1)return;
      const categoryNode=node.matches&&node.matches('[data-rg-home-category]')?node:(node.querySelector?node.querySelector('[data-rg-home-category]'):null);
      if(categoryNode){
        const category=categoryNode.dataset.rgHomeCategory||'';
        if(!category||seen.has(category))return;
        seen.add(category);
        const all=categoryNode.querySelector('.rg-home-all-button');
        result.push({type:'category',category,url:(all&&all.href)||categoryNode.dataset.rgHomeUrl||''});
        return;
      }
      // The old all-categories shortcode is a dynamic navigation module, not
      // free Gutenberg copy. Do not duplicate it inside the source-owned Home.
      if(node.matches&&node.matches('.rg-home-categories'))return;
      let html=node.outerHTML||'';
      // V110: this Gutenberg card is full-width in the app shell. Give the
      // browser the actual display width so srcset selects a sharper image on
      // high-density mobile screens.
      if(node.matches&&node.matches('.wp-block-latest-posts')){
        if(/\ssizes=(["'])[^"']*\1/i.test(html)){
          html=html.replace(/\ssizes=(["'])[^"']*\1/gi,' sizes="(max-width: 780px) calc(100vw - 34px), 746px"');
        }else{
          html=html.replace(/<img\b/gi,'<img sizes="(max-width: 780px) calc(100vw - 34px), 746px"');
        }
      }
      if(html.trim())result.push({type:'editorial',html});
    });

    // A partially edited Home should keep the editor's exact order while still
    // ensuring that all five live modules remain available.
    fallback.forEach(entry=>{if(!seen.has(entry.category))result.push(entry);});
    return result.length?result:fallback;
  }

  // V285: category pages stay distance-first. If multiple events resolve to the
  // same exact distance (for example several dates at one venue), order them by
  // the next start time before the title. The media resolver remains global and
  // source-neutral; Selm receives no frontend media special case.
  function sortDistance(items){
    return items.slice().sort((a,b)=>{
      const ad=Number.isFinite(a&&a.distanceKm)?a.distanceKm:Number.POSITIVE_INFINITY;
      const bd=Number.isFinite(b&&b.distanceKm)?b.distanceKm:Number.POSITIVE_INFINITY;
      if(ad!==bd)return ad-bd;
      const at=a&&a.start instanceof Date&&!isNaN(a.start)?a.start.getTime():Number.POSITIVE_INFINITY;
      const bt=b&&b.start instanceof Date&&!isNaN(b.start)?b.start.getTime():Number.POSITIVE_INFINITY;
      if(at!==bt)return at-bt;
      return String(a&&a.title||'').localeCompare(String(b&&b.title||''),'de');
    });
  }

  // V104: Activities Home uses both Activity Intelligence and distance. The
  // backend supplies an already-qualified/diversified candidate pool. We choose
  // the six cards with a hybrid utility score, cap repetitive buckets during the
  // first pass, then present the chosen cards nearest-first. This means distance
  // is visible to the user without letting a nearby low-value OSM object crowd
  // out a genuinely useful activity.
  function activityHomeSelection(items,limit){
    const max=Math.max(1,limit||6);
    const enriched=items.map((item,index)=>{
      const tags=item.tags||{};
      const activityScore=Number(tags['rg:activity_score']);
      const score=Number.isFinite(activityScore)?activityScore:0;
      const distance=Number.isFinite(item.distanceKm)?item.distanceKm:999;
      const bucket=String(tags['rg:activity_bucket']||'experience');
      const water=/^water_/.test(bucket)||bucket==='wellness';
      // Distance is a meaningful part of Home relevance, but experience quality
      // remains strong enough that a special spot can beat a merely ordinary one.
      const hybrid=score-(Math.min(distance,50)*2.0);
      return {item,index,score,distance,bucket,water,hybrid};
    }).sort((a,b)=>b.hybrid-a.hybrid||a.distance-b.distance||a.index-b.index);

    const picked=[];
    const ids=new Set();
    const bucketCounts={};
    let waterCount=0;
    const take=(x)=>{
      const key=String(x.item.id||x.index);
      if(ids.has(key)||picked.length>=max)return false;
      ids.add(key);picked.push(x);
      bucketCounts[x.bucket]=(bucketCounts[x.bucket]||0)+1;
      if(x.water)waterCount++;
      return true;
    };

    // Diversity pass: up to two cards from the same exact bucket and, when the
    // local pool allows it, no more than two water cards among the first six.
    enriched.forEach(x=>{
      if(picked.length>=max)return;
      if((bucketCounts[x.bucket]||0)>=2)return;
      if(x.water&&waterCount>=2)return;
      take(x);
    });
    // Sparse towns must never look artificially empty. Fill remaining slots from
    // the best hybrid candidates even if only one activity type exists locally.
    enriched.forEach(x=>{if(picked.length<max)take(x);});

    // The visible Home sequence itself is distance-sorted, as requested.
    return sortDistance(picked.map(x=>x.item));
  }

  // Home Events time windows are presentation-only; preserve the already-
  // admitted provider results, dedupe, ranking, radius and category contracts.
  // Inclusive Jetzt window: starts from 30 minutes ahead through 5 minutes ago.
  // Heute noch deliberately overlaps Jetzt for upcoming events only.
  // A valid day-specific visit can be represented by a concrete occurrence OR
  // a verified spanning event. The badge never claims opening hours when unknown.
  // V330: A spanning event date does not prove daily opening. Interpret only
  // regular visiting hours from existing canonical fields or a clearly labelled
  // opening-hours passage. Unknown access is a neutral fallback, never "open".
  function homeEventDayAccess(item,nowDate){
    const now=nowDate instanceof Date?nowDate:new Date();
    const dayCodes=['SO','MO','DI','MI','DO','FR','SA'];
    const day=dayCodes[now.getDay()];
    const minute=now.getHours()*60+now.getMinutes();
    const texts=[String(item&&item.dayHours||''),String(item&&item.visitorHours||'')];
    const description=String(item&&item.description||'').replace(/<[^>]*>/g,' ').replace(/&(?:nbsp|#160);/gi,' ');
    const heading=description.search(/Öffnungszeiten|Besuchszeiten/i);
    if(heading>=0)texts.push(description.slice(heading,heading+320));
    const codes=['MO','DI','MI','DO','FR','SA','SO'];
    for(const original of texts){
      const text=original.replace(/[–—−]/g,'-').replace(/\s+/g,' ').trim();
      if(!text)continue;
      // Free admission / free access alone is not proof of 24-hour access.
      if(/^(?:24\s*\/\s*7|täglich\s+rund\s+um\s+die\s+uhr)\s*$/i.test(text)||
        /(?:jederzeit|rund\s+um\s+die\s+uhr|24\s*\/\s*7).{0,55}(?:frei\s+zugänglich|öffentlich\s+zugänglich)/i.test(text)||
        /(?:frei\s+zugänglich|öffentlich\s+zugänglich).{0,55}(?:jederzeit|rund\s+um\s+die\s+uhr|24\s*\/\s*7)/i.test(text))
        return {state:'confirmed',openNow:true,availableToday:true,until:'',access:'24h'};
      const re=/(?:^|[^A-ZÄÖÜ])(MO|DI|MI|DO|FR|SA|SO)(?:\s*-\s*(MO|DI|MI|DO|FR|SA|SO))?\s*(?:\||:|\s)\s*(\d{1,2})(?::(\d{2}))?\s*-\s*(\d{1,2})(?::(\d{2}))?\s*(?:Uhr)?/gi;
      const windows=[];
      let m,valid=0;
      while((m=re.exec(text))){
        const a=codes.indexOf(m[1].toUpperCase()),b=m[2]?codes.indexOf(m[2].toUpperCase()):a;
        const from=Number(m[3])*60+Number(m[4]||0),to=Number(m[5])*60+Number(m[6]||0);
        if(a<0||b<0||from>=to||to>1440||Number(m[4]||0)>59||Number(m[6]||0)>59)continue;
        valid++;
        for(let d=a,guard=0;guard<7;guard++){
          if(codes[d]===day)windows.push({from,to});
          if(d===b)break;
          d=(d+1)%7;
        }
      }
      if(!valid)continue;
      const openNow=windows.some(w=>minute>=w.from&&minute<w.to);
      const remaining=windows.filter(w=>minute<w.to).sort((a,b)=>a.to-b.to);
      const until=remaining.length?String(Math.floor(remaining[0].to/60)).padStart(2,'0')+':'+String(remaining[0].to%60).padStart(2,'0'):'';
      return {state:remaining.length?'confirmed':'closed',openNow,availableToday:!!remaining.length,until,access:'regular'};
    }
    return {state:'unknown',openNow:false,availableToday:false,until:'',access:''};
  }

  function homeEventMatchesTime(item,windowMode,nowDate){
    const now=nowDate instanceof Date?nowDate:new Date();
    const start=item&&item.start instanceof Date?item.start:new Date(item&&item.start||0);
    const end=item&&item.end instanceof Date?item.end:(item&&item.end?new Date(item.end):null);
    if(isNaN(start.getTime()))return false;
    const st=start.getTime(),nt=now.getTime();
    const en=end instanceof Date&&!isNaN(end.getTime())?end.getTime():NaN;
    const nextDay=new Date(now.getFullYear(),now.getMonth(),now.getDate()+1).getTime();
    const dayAfter=new Date(now.getFullYear(),now.getMonth(),now.getDate()+2).getTime();
    const spans=eventIsLongRunning(item);
    const hasEnd=Number.isFinite(en)&&en>=st;
    if(windowMode==='now'){
      const concrete=!spans&&st>=nt-5*60000&&st<=nt+30*60000&&(!hasEnd||en>nt);
      const access=spans?homeEventDayAccess(item,now):null;
      const longActive=spans&&st<=nt&&hasEnd&&en>nt&&(!access||access.state==='unknown'||access.openNow);
      return concrete||longActive;
    }
    if(windowMode==='today'){
      const futureConcrete=!spans&&st>nt&&st<nextDay;
      const access=spans?homeEventDayAccess(item,now):null;
      const longVisit=spans&&st<nextDay&&hasEnd&&en>nt&&(!access||access.state==='unknown'||access.availableToday);
      return futureConcrete||longVisit;
    }
    if(windowMode==='tomorrow'){
      const tomorrowConcrete=!spans&&st>=nextDay&&st<dayAfter;
      const overnight=!spans&&st<nextDay&&hasEnd&&en>nextDay;
      const longVisit=spans&&st<dayAfter&&hasEnd&&en>nextDay;
      return tomorrowConcrete||overnight||longVisit;
    }
    return false;
  }

  function homeEventTimeHint(item,windowMode,nowDate){
    if(!homeEventMatchesTime(item,windowMode,nowDate))return '';
    const now=nowDate instanceof Date?nowDate:new Date();
    const start=item.start instanceof Date?item.start:new Date(item.start);
    const hhmm=d=>String(d.getHours()).padStart(2,'0')+':'+String(d.getMinutes()).padStart(2,'0');
    if(eventIsLongRunning(item)){
      if(windowMode==='tomorrow')return 'Morgen besuchbar · Zeiten prüfen';
      const access=homeEventDayAccess(item,now);
      if(access.state==='confirmed')return access.access==='24h'?'Laut Angaben jederzeit zugänglich':
        (windowMode==='now'?'Reguläre Besuchszeit':'Heute noch Besuchszeit')+(access.until?' · bis '+access.until:'');
      return 'Laufende Veranstaltung · Öffnungszeiten unbekannt';
    }
    if(windowMode==='tomorrow'&&start.getTime()<new Date(now.getFullYear(),now.getMonth(),now.getDate()+1).getTime())return 'Läuft bis morgen';
    if(windowMode==='now')return start<=now?'Gerade gestartet':'Beginnt in '+Math.ceil((start.getTime()-now.getTime())/60000)+' Min.';
    if(item.allDay)return windowMode==='tomorrow'?'Morgen · ganztägig':'Heute · ganztägig';
    return (windowMode==='tomorrow'?'Morgen ':'Heute ')+hhmm(start);
  }

  // Recreate only source-proven today/tomorrow occurrences.  Preserve the
  // canonical event identity, rights/media and details of the series owner.
  // No daily fan-out of any later date (no duplicate multi-day result flood).
  function eventNearTermCards(items,nowDate){
    const now=nowDate instanceof Date?nowDate:new Date();
    const today=new Date(now.getFullYear(),now.getMonth(),now.getDate());
    const after=new Date(now.getFullYear(),now.getMonth(),now.getDate()+2);
    const result=[];
    items.forEach(item=>{
      const series=item&&item.series||{};
      const near=Array.isArray(series.nearTerm)?series.nearTerm:[];
      const unique=new Set(),selected=[];
      near.forEach(occ=>{
        const st=new Date(occ&&occ.start||'');
        const en=occ&&occ.end?new Date(occ.end):null;
        if(isNaN(st.getTime())||st>=after)return;
        if(st<today&&(!en||isNaN(en.getTime())||en<=today))return;
        if(en instanceof Date&&!isNaN(en.getTime())&&en.getTime()<now.getTime()-3600000)return;
        const key=st.toISOString()+'|'+(en&&!isNaN(en.getTime())?en.toISOString():'');
        if(unique.has(key))return;unique.add(key);
        selected.push({start:st,end:en&&!isNaN(en.getTime())?en:null,key,
          eventId:Number(occ.eventId)||0,canonicalUrl:String(occ.canonicalUrl||'')});
      });
      const later=series.laterSeries;
      const laterStart=new Date(later&&later.start||'');
      const hasLater=!isNaN(laterStart.getTime())&&laterStart>=after;
      const laterEnd=later&&later.end?new Date(later.end):null;
      const cloneFor=(source,properties)=>{
        const eventId=Number(source&&source.eventId)||0;
        const canonicalUrl=String(source&&source.canonicalUrl||'').trim();
        const raw=item.raw&&typeof item.raw==='object'?item.raw:{};
        const originalDb=raw.rpg_event_db&&typeof raw.rpg_event_db==='object'?raw.rpg_event_db:{};
        return Object.assign({},item,properties,canonicalUrl?{canonicalUrl}:{},
          eventId?{raw:Object.assign({},raw,{rpg_event_db:Object.assign({},originalDb,{eventId})})}:{});
      };
      const laterItem=()=>cloneFor(later,{start:laterStart,
        end:laterEnd&&!isNaN(laterEnd.getTime())?laterEnd:null,
        _rpgCollapsedFutureSeries:true,
        _rpgNearTermKey:String(item.id)+'@future-series'});
      if(!selected.length){result.push(hasLater?laterItem():item);return;}
      selected.forEach(occ=>result.push(cloneFor(occ,{start:occ.start,end:occ.end,
        _rpgNearTermKey:String(item.id)+'@'+occ.key})));
      // Keep one collapsed future-series card after tomorrow. The full catalog
      // must remain reachable without one card for every distant day.
      if(hasLater)result.push(laterItem());
    });
    return result;
  }

  // V332: In the handoff time tiers, exact coordinates keep exact precedence.
  // Only source-verified city-scoped events without a point coordinate receive
  // an INTERNAL proximity proxy: median of already-geocoded event distances
  // in that same explicitly selected city. This is not an event's real km,
  // is never displayed, and does not form city groups or change radius rules.
  // If there are no reliable same-city samples, these events remain unsorted
  // by geography (Infinity), as in v331; do not invent a point/centroid.
  const HOME_EVENT_PRIORITY_KEY='rpg-home-events-priority-v1';
  function eventIsLongRunning(item){
    if(item&&item.allDay)return true;
    const start=item&&item.start instanceof Date?item.start:new Date(item&&item.start||0);
    const end=item&&item.end instanceof Date?item.end:new Date(item&&item.end||0);
    return !isNaN(start.getTime())&&!isNaN(end.getTime())&&end.getTime()-start.getTime()>=20*3600000;
  }
  function eventPriorityCompare(items,mode,location,nowDate){
    const now=nowDate instanceof Date?nowDate:new Date();
    const todayStart=new Date(now.getFullYear(),now.getMonth(),now.getDate()).getTime();
    const cityKey=value=>String(value||'').normalize('NFKC').trim().toLocaleLowerCase('de');
    const selectedCity=cityKey(location&&location.city);
    const localSamples=selectedCity?(Array.isArray(items)?items:[])
      .filter(x=>cityKey(x&&x.city)===selectedCity&&Number.isFinite(x&&x.distanceKm)&&x.distanceKm>=0)
      .map(x=>x.distanceKm).sort((a,b)=>a-b):[];
    // Use only real existing coordinates as evidence for a sort-only local
    // proxy. It is not a geocode, real event distance, or a displayed km.
    const mid=Math.floor(localSamples.length/2);
    const localProxy=localSamples.length
      ?(localSamples.length%2?localSamples[mid]:(localSamples[mid-1]+localSamples[mid])/2)
      :Infinity;
    const distance=x=>{
      if(x&&Number.isFinite(x.distanceKm)&&x.distanceKm>=0)return x.distanceKm;
      if(x&&x.cityScope===true&&selectedCity&&cityKey(x.city)===selectedCity)return localProxy;
      return Infinity;
    };
    const startTime=x=>{
      const d=x&&x.start instanceof Date?x.start:new Date(x&&x.start||0);
      return isNaN(d.getTime())?Infinity:d.getTime();
    };
    const calendarDay=x=>{
      const d=x&&x.start instanceof Date?x.start:new Date(x&&x.start||0);
      if(isNaN(d.getTime()))return Infinity;
      // An already-running multi-day event has no new occurrence on its
      // original start date: place it in today's remaining catalogue, not in
      // a historical day. This affects only the priority-enabled view.
      const day=new Date(d.getFullYear(),d.getMonth(),d.getDate()).getTime();
      return eventIsLongRunning(x)&&day<todayStart?todayStart:day;
    };
    const rankCache=new WeakMap();
    const group=x=>{
      if(rankCache.has(x))return rankCache.get(x);
      const match=homeEventMatchesTime(x,mode,now);
      let rank=2;
      if(match){
        if(!eventIsLongRunning(x))rank=0;
        else rank=(mode==='now'||mode==='today')&&homeEventDayAccess(x,now).state==='unknown'?1.5:1;
      }
      rankCache.set(x,rank);
      return rank;
    };
    return (a,b)=>{
      const ga=group(a),gb=group(b);
      if(ga!==gb)return ga-gb;
      // The selected Home window has already been grouped above. Only the
      // remaining catalogue uses day-first sorting, then exact proximity.
      if(ga===2){
        const ad=calendarDay(a),bd=calendarDay(b);
        if(ad!==bd)return ad-bd;
      }
      const ad=distance(a),bd=distance(b);
      if(ad!==bd)return ad-bd;
      const at=startTime(a),bt=startTime(b);
      if(at!==bt)return at-bt;
      const ai=String(a&&a._rpgNearTermKey||a&&a.id||'');
      const bi=String(b&&b._rpgNearTermKey||b&&b.id||'');
      if(ai!==bi)return ai.localeCompare(bi,'de');
      return String(a&&a.title||'').localeCompare(String(b&&b.title||''),'de');
    };
  }
  function storeHomeEventPriority(mode,location,radius){
    if(!['now','today','tomorrow'].includes(mode))return;
    try{
      window.sessionStorage.setItem(HOME_EVENT_PRIORITY_KEY,JSON.stringify({mode,at:Date.now(),lat:location&&location.lat,lng:location&&location.lng,radius}));
    }catch(_){}
  }
  function consumeHomeEventPriority(location,radius){
    try{
      const raw=window.sessionStorage.getItem(HOME_EVENT_PRIORITY_KEY);
      window.sessionStorage.removeItem(HOME_EVENT_PRIORITY_KEY);
      const d=JSON.parse(raw||'null');
      if(!d||!['now','today','tomorrow'].includes(d.mode)||Date.now()-d.at>300000||Date.now()<d.at)return '';
      if(Number(d.radius)!==Number(radius))return '';
      if(location&&Number.isFinite(Number(d.lat))&&Number.isFinite(Number(d.lng))&&
        (Math.abs(Number(d.lat)-Number(location.lat))>0.001||Math.abs(Number(d.lng)-Number(location.lng))>0.001))return '';
      return d.mode;
    }catch(_){return '';}
  }

  function eventUrgencyCompare(a,b){
    const now=Date.now();
    const dayKey=d=>{const x=d instanceof Date?d:new Date(d||0);return isNaN(x)?'':x.getFullYear()+'-'+String(x.getMonth()+1).padStart(2,'0')+'-'+String(x.getDate()).padStart(2,'0');};
    const today=dayKey(new Date(now));
    const rank=item=>{
      const startValue=item&&item.start;
      const endValue=item&&item.end;
      const start=startValue instanceof Date?startValue:(startValue?new Date(startValue):null);
      const end=endValue instanceof Date?endValue:(endValue?new Date(endValue):null);
      const st=!start||isNaN(start)?Number.POSITIVE_INFINITY:start.getTime();
      const et=!end||isNaN(end)?Number.NaN:end.getTime();
      const startDay=dayKey(start);
      const endDay=dayKey(end);
      const running=st<=now&&(!Number.isFinite(et)||et>=now);
      const multiDay=Boolean(startDay&&endDay&&startDay!==endDay);

      // V296: today's concrete appointments must not disappear behind exhibitions,
      // fairs or other long-running events that merely remain active today.
      // Keep the established radius boundary and date-first principle; only split
      // the former "running" bucket into current same-day appointments and true
      // multi-day/older running events.
      if(running&&!multiDay&&startDay===today)return {bucket:0,time:st};
      if(st>now&&startDay===today)return {bucket:1,time:st};
      if(running)return {bucket:2,time:Number.isFinite(et)?et:st};
      return {bucket:3,time:st};
    };
    const ar=rank(a),br=rank(b);
    if(ar.bucket!==br.bucket)return ar.bucket-br.bucket;
    if(ar.time!==br.time)return ar.time-br.time;
    const ad=Number.isFinite(a&&a.distanceKm)?a.distanceKm:Number.POSITIVE_INFINITY;
    const bd=Number.isFinite(b&&b.distanceKm)?b.distanceKm:Number.POSITIVE_INFINITY;
    if(ad!==bd)return ad-bd;
    return String(a&&a.title||'').localeCompare(String(b&&b.title||''),'de');
  }

  function useCategoryItems(category,location,radius,home,mode,eventWindow,refreshToken){
    const [state,setState]=useState({loading:true,error:'',items:[],effectiveRadius:radius});
    const eventCache=useRef(null);
    const homeEventMediaCache=useRef(new Map());
    useEffect(function(){ if(!location)return; let live=true;
      const homeEventCacheKey=category==='events'&&home&&mode!=='halloween'?[location.lat,location.lng,location.city||'',radius,new Date().toDateString()].join('|'):'';
      const hasCachedEvents=homeEventCacheKey&&eventCache.current&&eventCache.current.key===homeEventCacheKey;
      setState(s=>Object.assign({},s,{loading:!hasCachedEvents,error:''}));
      (async()=>{
        try{
          if(category==='events'){
            const eventFetchRadius=(home&&radius<10)?10:radius;
            const raw=hasCachedEvents?eventCache.current.items:await RPG.api.events(location,eventFetchRadius,mode==='martin'?martinUntilDays(new Date()):(mode==='weihnachten'?christmasUntilDays(new Date()):undefined));
            if(homeEventCacheKey&&!hasCachedEvents)eventCache.current={key:homeEventCacheKey,items:raw};
            let items=raw.map(x=>RPG.normalizeEvent(x,location)).filter(x=>{
              if(!x.start)return false;
              if(!x.end||x.end.getTime()>=Date.now()-3600000)return true;
              const series=x.series||{};
              const near=Array.isArray(series.nearTerm)?series.nearTerm:[];
              return near.some(o=>new Date(o&&o.end||o&&o.start||0).getTime()>=Date.now()-3600000)
                ||new Date(series.laterSeries&&series.laterSeries.start||0).getTime()>Date.now();
            });
            // V315: official city-scoped municipal events have deliberately no fake
            // point coordinate. The backend only returns them for their selected
            // city, so they remain eligible without displaying a made-up distance.
            items=items.filter(x=>Number.isFinite(x.distanceKm)||x.cityScope===true);
            items=RPG.dedupeEventsForListing(items);
            if(mode!=='halloween'&&mode!=='weihnachten'&&mode!=='martin')items=eventNearTermCards(items,new Date());
            // V296: radius remains the eligibility boundary. Within that radius,
            // current/later-today appointments come before long-running events;
            // long-running events still remain ahead of tomorrow/later dates.
            // Distance remains only the tie-breaker inside the same urgency/time tier.
            // V329: selected Home time or one-shot Home→Events priority controls
            // presentation order only. The full category remains available.
            const priorityEventView=eventWindow&&['now','today','tomorrow'].includes(eventWindow);
            items.sort(priorityEventView?eventPriorityCompare(items,eventWindow,location,new Date()):eventUrgencyCompare);
            // Event eligibility never depends on the image source. Original ET4/ODF
            // artwork, approved symbolic/context images and events without an image all
            // remain in the same listing pool; cards fall back to the standard ticket
            // placeholder when no image can be resolved.
            let effective=radius, visible=items.filter(x=>x.cityScope===true||x.distanceKm<=radius);
            // V299: seasonal specials must filter from the complete radius-eligible
            // event pool before presentation limits (the full category now paginates).
            // Otherwise late-October Halloween events disappear behind hundreds of
            // earlier September/October events even though they are in the database.
            if(mode==='halloween') visible=visible.filter(isHalloweenEvent);
            if(mode==='weihnachten') visible=visible.filter(x=>christmasCategories(x).length>0);
            if(mode==='martin') visible=visible.filter(x=>martinCategories(x).length>0);
            if(home&&visible.length<4&&radius<10){effective=10;visible=items.filter(x=>x.cityScope===true||x.distanceKm<=10);}
            // Rank the final radius-admitted pool, never farther-away candidates.
            if(!home&&priorityEventView)visible.sort(eventPriorityCompare(visible,eventWindow,location,new Date()));
            // Only the Home Event card shortlist gains a time selection.
            if(home&&eventWindow){
              const now=new Date();
              visible=visible.filter(x=>homeEventMatchesTime(x,eventWindow,now));
              visible.sort(eventPriorityCompare(visible,eventWindow,location,now));
            }
            visible=home?visible.slice(0,4):visible;
            const baseItems=visible.slice();
            const mediaHead=home?baseItems:baseItems.slice(0,300);
            const mediaTail=home?[]:baseItems.slice(300);
            let localItems=baseItems;
            if(typeof RPG.diversifyEventMediaLocal==='function')localItems=RPG.diversifyEventMediaLocal(mediaHead).concat(mediaTail);
            if(live)setState({loading:false,error:'',items:localItems,effectiveRadius:effective});
            // V298 remains display-only: legacy Symbolfotos are hidden before the
            // first paint and only theme-matching replacements may appear later.
            // Event count/order, radius, ET4 media and persisted assignments stay untouched.
            if(live&&RPG.settings.eventMediaDiversityEnabled&&typeof RPG.diversifyEventMedia==='function'&&mediaHead.length){
              const mediaKey=home&&category==='events'?baseItems.map(x=>[x.id,x.imageUrl||'',x.displayImageUrl||''].join(':')).join('|'):'';
              const mediaPromise=mediaKey&&homeEventMediaCache.current.has(mediaKey)
                ?homeEventMediaCache.current.get(mediaKey)
                :RPG.diversifyEventMedia(mediaHead);
              if(mediaKey&&!homeEventMediaCache.current.has(mediaKey))homeEventMediaCache.current.set(mediaKey,mediaPromise);
              Promise.resolve(mediaPromise).then(diverse=>{
                if(!live||!Array.isArray(diverse)||diverse.length!==mediaHead.length)return;
                const compare=Array.isArray(localItems)&&localItems.length===baseItems.length?localItems:baseItems;
                const changed=diverse.some((x,i)=>String(x&&x.displayImageUrl||'')!==String(compare[i]&&compare[i].displayImageUrl||''));
                if(changed)setState({loading:false,error:'',items:diverse.concat(mediaTail),effectiveRadius:effective});
              }).catch(()=>{if(mediaKey)homeEventMediaCache.current.delete(mediaKey);});
            }
            return;
          }
          const fetchAt=async(r)=>{const raw=await RPG.api.places(category,location,r,home?500:1500);return raw.map(x=>RPG.normalizePlace(x,category,location));};
          let effective=radius,items=await fetchAt(radius);
          if(home&&category==='highlights'&&items.length<4&&radius<10){effective=10;items=await fetchAt(10);} if(home&&category==='highlights'&&items.length<4&&effective<25){effective=25;items=await fetchAt(25);}
          // Activities Home is a hybrid: Activity Intelligence determines the
          // candidate quality/diversity, distance participates in selection, and the
          // six visible cards are finally ordered nearest-first. Other place feeds
          // keep their established distance order.
          if(home&&category==='activities') items=activityHomeSelection(items,6);
          else items=sortDistance(items);
          if(home){const limit=category==='activities'?6:category==='highlights'?4:4;items=items.slice(0,limit);}
          if(live)setState({loading:false,error:'',items,effectiveRadius:effective});
        }catch(e){if(live)setState({loading:false,error:e.message||'Daten konnten nicht geladen werden',items:[],effectiveRadius:radius});}
      })(); return()=>{live=false;};
    },[category,location&&location.lat,location&&location.lng,radius,home,mode,eventWindow,refreshToken]);
    return state;
  }

  function HomeCategorySkeleton({category}){
    const sr=h('span',{className:'rpgx-sr-only',role:'status'},'Live-Daten werden geladen …');
    if(category==='culture'){
      return h(RPG.Fragment,null,sr,h('div',{className:'rg-home-culture-list rpgx-skeleton-set','aria-hidden':'true'},Array.from({length:4},(_,i)=>h('div',{key:i,className:'rg-home-culture-row rpgx-skeleton-culture-row'},h('span',{className:'rpgx-skeleton-culture-media'}),h('span',{className:'rpgx-skeleton-culture-copy'},h('span',{className:'rpgx-skeleton-line is-title'}),h('span',{className:'rpgx-skeleton-line is-short'}))))));
    }
    const count=category==='activities'?6:4;
    const containerClass=category==='events'||category==='activities'?'rg-home-events-track'+(category==='activities'?' rg-home-activities-track':''):category==='food'?'rg-home-food-grid':'rg-home-highlight-mosaic';
    const cardClass=category==='events'||category==='activities'?'rg-home-event-mag-card':category==='food'?'rg-home-food-mag-card':'rg-home-highlight-mag-card';
    return h(RPG.Fragment,null,sr,h('div',{className:containerClass+' rpgx-skeleton-set','aria-hidden':'true'},Array.from({length:count},(_,i)=>h('div',{key:i,className:cardClass+' rpgx-skeleton-card'+(category==='highlights'&&i===0?' is-featured':'')},h('span',{className:'rpgx-skeleton-media'}),h('span',{className:'rpgx-skeleton-copy'},h('span',{className:'rpgx-skeleton-line is-title'}),h('span',{className:'rpgx-skeleton-line is-medium'}),h('span',{className:'rpgx-skeleton-line is-short'}))))));
  }

  function CategoryGridSkeleton(){
    const cards=Array.from({length:8},(_,i)=>h('div',{key:i,className:'rpgx-card rpgx-skeleton-card'},
      h('span',{className:'rpgx-skeleton-media'}),
      h('span',{className:'rpgx-skeleton-copy'},
        h('span',{className:'rpgx-skeleton-line is-title'}),
        h('span',{className:'rpgx-skeleton-line is-medium'}),
        h('span',{className:'rpgx-skeleton-line is-short'})
      )
    ));
    return h(RPG.Fragment,null,
      h('span',{className:'rpgx-sr-only',role:'status'},'Daten werden geladen …'),
      h('div',{className:'rpgx-grid rpgx-skeleton-set rpgx-skeleton-grid','aria-hidden':'true'},cards)
    );
  }

  function HomeCategory({category,location,radius,onOpen,url,onLocation}){
    const [eventWindow,setEventWindow]=useState('today');
    const [eventRefresh,setEventRefresh]=useState(0);
    useEffect(()=>{
      if(category!=='events')return;
      // Refresh displayed time gates without re-downloading the multi-MB event feed.
      const refreshVisible=()=>{if(document.visibilityState!=='hidden')setEventRefresh(n=>n+1);};
      const id=window.setInterval(refreshVisible,30000);
      document.addEventListener('visibilitychange',refreshVisible);
      return()=>{window.clearInterval(id);document.removeEventListener('visibilitychange',refreshVisible);};
    },[category]);
    const s=useCategoryItems(category,location,radius,true,'',category==='events'?eventWindow:'',category==='events'?eventRefresh:0);
    const eventSubtitles={now:'Was startet jetzt gleich in deiner Nähe?',today:'Was steht heute noch an?',tomorrow:'Was steht morgen an?'};
    const header=h(C.LegacyHomeSectionHeader,{category,location,radius:s.effectiveRadius,url,onLocation,subtitle:category==='events'?eventSubtitles[eventWindow]:null,
      onAllClick:category==='events'?e=>{if(e.button===0&&!e.ctrlKey&&!e.metaKey&&!e.shiftKey&&!e.altKey)storeHomeEventPriority(eventWindow,location,radius);}:null});
    const eventSwitch=category==='events'?h('div',{className:'rg-home-event-time-switch',role:'group','aria-label':'Zeitraum für Veranstaltungen auswählen'},
      [['now','Jetzt'],['today','Heute noch'],['tomorrow','Morgen']].map(([value,label])=>h('button',{type:'button',key:value,className:'rg-home-event-time-button'+(eventWindow===value?' is-active':''),'aria-pressed':eventWindow===value?'true':'false',onClick:()=>setEventWindow(value)},label))
    ):null;
    const eventBetaNote=null;
    if(s.loading)return h('section',{className:'rg-home-module rg-home-category-module rg-home-category-module-'+category},header,h('div',{className:'rg-home-category-module-body'},eventSwitch,eventBetaNote,h(HomeCategorySkeleton,{category})));
    if(s.error)return h('section',{className:'rg-home-module rg-home-category-module rg-home-category-module-'+category},header,h('div',{className:'rg-home-category-module-body'},eventSwitch,eventBetaNote,h('div',{className:'rg-home-live-error'},'Live-Daten konnten gerade nicht geladen werden. '+s.error)));

    let items=s.items.slice();
    const trackedOpen=(item)=>{track('card_open',{category,area:location.city||location.name||'',radius:s.effectiveRadius,placement:'home',content_id:item&&item.id||''});onOpen(item);};

    let body;
    if(!items.length){
      const empty=category==='events'?(eventWindow==='now'?'Gerade startet hier keine Veranstaltung innerhalb von 30 Minuten.':eventWindow==='tomorrow'?'Für morgen werden hier derzeit keine passenden Termine angezeigt.':'Für heute werden hier derzeit keine passenden Termine mehr angezeigt.'):'Aktuell keine passenden Treffer im Umkreis.';
      body=h('div',{className:'rg-home-live-empty',role:'status'},empty);
    }else if(category==='events'){
      body=h('div',{className:'rg-home-events-track'},items.map(item=>h(C.LegacyOverlayCard,{key:item._rpgNearTermKey||item.id,item,onOpen:trackedOpen,timeHint:homeEventTimeHint(item,eventWindow,new Date()),className:'rg-home-event-mag-card'})));
    }else if(category==='food'){
      body=h('div',{className:'rg-home-food-grid'},items.map(item=>h(C.LegacyOverlayCard,{key:item.id,item,onOpen:trackedOpen,className:'rg-home-food-mag-card'})));
    }else if(category==='highlights'){
      body=h('div',{className:'rg-home-highlight-mosaic'},items.map((item,i)=>h(C.LegacyOverlayCard,{key:item.id,item,onOpen:trackedOpen,eager:i===0,className:'rg-home-highlight-mag-card'+(i===0?' is-featured':'')})));
    }else if(category==='culture'){
      body=h('div',{className:'rg-home-culture-list'},items.map(item=>h(C.LegacyCultureRow,{key:item.id,item,onOpen:trackedOpen})));
    }else{
      body=h('div',{className:'rg-home-events-track rg-home-activities-track'},items.map(item=>h(C.LegacyOverlayCard,{key:item.id,item,onOpen:trackedOpen,className:'rg-home-event-mag-card rg-home-activity-event-card'})));
    }
    return h('section',{className:'rg-home-module rg-home-category-module rg-home-category-module-'+category},header,h('div',{className:'rg-home-category-module-body'},eventSwitch,eventBetaNote,body));
  }

  function sourceHtml(id){
    const node=document.getElementById(id);
    return node&&node.innerHTML&&node.innerHTML.trim()?node.innerHTML.trim():'';
  }

  function IndustrialRouteTeaser({location}){
    const [item,setItem]=useState(null);
    useEffect(function(){
      if(!location){setItem(null);return;}
      let live=true;
      RPG.api.industrialRoute(location).then(items=>{
        const candidates=(items||[])
          .filter(x=>x&&x.matched&&x.element&&Number.isFinite(x.distanceKm))
          .sort((a,b)=>a.distanceKm-b.distanceKm);
        if(!candidates.length){if(live)setItem(null);return;}
        const x=candidates[0];
        const normalized=RPG.normalizePlace(x.element,'highlights',location);
        normalized.title=x.title||normalized.title;
        normalized.city=x.city||normalized.city;
        normalized.routeIndustrial=true;
        normalized.routeType=x.type;
        if(live)setItem(normalized);
      }).catch(()=>{if(live)setItem(null);});
      return()=>{live=false;};
    },[location&&location.lat,location&&location.lng]);

    const url=RPG.settings.industrialRoutePageUrl||'/route-industriekultur/';
    return h('section',{className:'rg-home-route-teaser','aria-label':'Route der Industriekultur'},
      h('a',{href:url,className:'rg-home-route-teaser-link'},
        h('div',{className:'rg-home-route-teaser-media'},
          item?h(C.Media,{item,eager:false}):h('div',{className:'rg-home-route-teaser-fallback','aria-hidden':'true'},'🏭'),
          h('div',{className:'rg-home-route-teaser-shade','aria-hidden':'true'}),
          h('div',{className:'rg-home-route-teaser-copy'},
            h('h3',null,'Route der Industriekultur'),
            h('span',{className:'rg-home-route-teaser-cta'},'Entdecken →')
          )
        )
      )
    );
  }


  function radiusLabel(radius){return String(Number(radius)||5).replace('.',',');}

  function useHomeRadarCount(kind,location,radius){
    const [state,setState]=useState({count:null,capped:false});
    useEffect(function(){
      if(!location||!Number.isFinite(Number(location.lat))||!Number.isFinite(Number(location.lng))){setState({count:null,capped:false});return;}
      let live=true;
      setState({count:null,capped:false});
      const run=kind==='buedchen'?RPG.api.buedchen(location,radius,200).then(raw=>{
        const items=Array.isArray(raw)?raw.map(x=>RPG.normalizePlace(x,'buedchen',location)):[];
        const tierA=items.filter(x=>budeVerified(x)||budeTier(x)==='A');
        const tierB=items.filter(x=>!budeVerified(x)&&budeTier(x)==='B');
        const visible=tierA.length>=12?tierA:tierA.concat(tierB.slice(0,Math.max(0,12-tierA.length)));
        return {count:visible.length,capped:items.length>=200&&visible.length>=200};
      }):RPG.api.wurst(location,radius,800).then(data=>({count:data&&Array.isArray(data.elements)?data.elements.length:0,capped:data&&Array.isArray(data.elements)&&data.elements.length>=800}));
      run.then(next=>{if(live)setState(next);}).catch(()=>{if(live)setState({count:null,capped:false});});
      return()=>{live=false;};
    },[kind,location&&location.lat,location&&location.lng,radius]);
    return state;
  }

  function BuedchenHomeTeaser({location,radius}){
    const url=RPG.settings.buedchenPageUrl||'/buedchen-radar/';
    const nearby=useHomeRadarCount('buedchen',location,radius);
    const dynamicCopy=Number.isFinite(nearby.count)&&nearby.count>0?(nearby.capped?'200+':nearby.count)+' Büdchen in deinem '+radiusLabel(radius)+'-km-Radius.':'Trinkhallen und Büdchen rund um deinen Standort.';
    return h('section',{className:'rg-home-buedchen-teaser','aria-label':'Büdchen- und Trinkhallen-Radar'},
      h('a',{href:url,className:'rg-home-buedchen-teaser-link'},
        h('div',{className:'rg-home-buedchen-teaser-media'},
          h('div',{className:'rg-home-buedchen-teaser-art','aria-hidden':'true'},
            h('span',{className:'rg-home-buedchen-neon'},'TRINKHALLE'),
            h('span',{className:'rg-home-buedchen-pin'},'⌖')
          ),
          h('div',{className:'rg-home-buedchen-teaser-shade','aria-hidden':'true'}),
          h('div',{className:'rg-home-buedchen-teaser-copy'},
            h('span',{className:'rg-home-buedchen-kicker'},'ECHTE REVIERKULTUR'),
            h('h3',null,'Büdchen-Radar'),
            h('p',null,dynamicCopy),
            h('span',{className:'rg-home-buedchen-teaser-cta'},'Radar öffnen →')
          )
        )
      )
    );
  }


  function WurstHomeTeaser({location,radius}){
    const url=RPG.settings.wurstPageUrl||'/imbissbuden/';
    const nearby=useHomeRadarCount('wurst',location,radius);
    const noun=nearby.count===1?'Imbissbude':'Imbissbuden';
    const dynamicCopy=Number.isFinite(nearby.count)&&nearby.count>0?(nearby.capped?'800+':nearby.count)+' '+noun+' in deinem '+radiusLabel(radius)+'-km-Radius.':'Currywurst, Pommes und klassische Imbissbuden rund um deinen Standort.';
    return h('section',{className:'rg-home-wurst-teaser','aria-label':"Hier geht's um die Wurst"},
      h('a',{href:url,className:'rg-home-wurst-teaser-link'},
        h('div',{className:'rg-home-wurst-teaser-media'},
          h('div',{className:'rg-home-wurst-teaser-art','aria-hidden':'true'}),
          h('div',{className:'rg-home-wurst-teaser-shade','aria-hidden':'true'}),
          h('div',{className:'rg-home-wurst-teaser-copy'},
            h('span',{className:'rg-home-wurst-kicker'},'ECHTE IMBISSKULTUR'),
            h('h3',null,"Hier geht's um die Wurst"),
            h('p',null,dynamicCopy),
            h('span',{className:'rg-home-wurst-teaser-cta'},'Imbissbuden entdecken →')
          )
        )
      )
    );
  }

  // V171 – visual Pott-Quiz teaser. The approved artwork already contains
  // the headline and CTA, so the entire banner is the accessible link target.
  function PottQuizHomeTeaser(){
    const url=RPG.settings.pottQuizPageUrl||'/pott-quiz/';
    return h('section',{className:'rg-home-pottquiz-teaser','aria-label':'Pott-Quiz – Teste dein Ruhrpott-Wissen'},
      h('a',{href:url,className:'rg-home-pottquiz-teaser-link','aria-label':'Pott-Quiz jetzt spielen'},
        h('div',{className:'rg-home-pottquiz-teaser-media'},
          h('span',{className:'rg-home-pottquiz-teaser-art','aria-hidden':'true'})
        )
      )
    );
  }

  // V276 – Halloween is a seasonal, theme-only special. The Home teaser is
  // visible from 15 September through 1 November and links to the dedicated
  // landing page. It does not trigger an additional data request on Home.
  function halloweenTargetDate(now){
    const d=now instanceof Date?now:new Date();
    const thisYear=new Date(d.getFullYear(),9,31,0,0,0,0);
    const endThisYear=new Date(d.getFullYear(),10,1,23,59,59,999);
    return d<=endThisYear?thisYear:new Date(d.getFullYear()+1,9,31,0,0,0,0);
  }
  function halloweenCountdown(now){
    const d=now instanceof Date?now:new Date();
    const target=halloweenTargetDate(d);
    if(d.getFullYear()===target.getFullYear()&&d.getMonth()===9&&d.getDate()===31)return 'Heute ist Halloween 🎃';
    const today=new Date(d.getFullYear(),d.getMonth(),d.getDate());
    const days=Math.max(0,Math.ceil((target.getTime()-today.getTime())/86400000));
    return 'Noch '+days+' Tag'+(days===1?'':'e')+' bis Halloween';
  }
  function halloweenSeasonActive(now){
    const d=now instanceof Date?now:new Date();
    const start=new Date(d.getFullYear(),8,15,0,0,0,0);
    const end=new Date(d.getFullYear(),10,1,23,59,59,999);
    return d>=start&&d<=end;
  }
  function HalloweenHomeTeaser({location}){
    if(!halloweenSeasonActive(new Date()))return null;
    const url=RPG.settings.halloweenPageUrl||'/halloween-im-pott/';
    const area=location&&(location.city||location.name)?' rund um '+(location.city||location.name):' in deiner Nähe';
    return h('section',{className:'rpgx-halloween-home','aria-label':'Halloween im Pott'},
      h('a',{href:url,className:'rpgx-halloween-home-link'},
        h('div',{className:'rpgx-halloween-home-art','aria-hidden':'true'},h('span',null,'🎃'),h('span',null,'👻'),h('span',null,'🕸️')),
        h('div',{className:'rpgx-halloween-home-copy'},
          h('span',{className:'rpgx-halloween-kicker'},halloweenCountdown(new Date())),
          h('h2',null,'Halloween im Pott'),
          h('p',null,'Partys, Grusel, Kürbis & Familien-Events'+area+'.'),
          h('strong',null,'Halloween entdecken →')
        )
      )
    );
  }

  function HomeView({location,radius,onOpen,onLocation}){
    // V105: the Gutenberg sequence is authoritative. Any normal WordPress
    // block (Query Loop, Latest Posts, image, text, selected post teaser, etc.)
    // placed between category shortcodes is treated as editorial content and
    // stays exactly there. No editorial is injected from tags or keywords.
    const blueprint=useMemo(homeBlueprint,[]);
    const children=[];
    const halloweenTeaser=h(HalloweenHomeTeaser,{key:'halloween-home-teaser',location});
    if(halloweenTeaser)children.push(halloweenTeaser);
    let editorialIndex=0;
    const categoryOrder=blueprint.filter(x=>x.type==='category').map(x=>x.category);
    const foodIndex=categoryOrder.indexOf('food'),eventsIndex=categoryOrder.indexOf('events');
    const firstFoodEvent=(foodIndex>=0&&eventsIndex>=0)?(foodIndex<eventsIndex?'food':'events'):null;
    let buedchenInserted=false;
    blueprint.forEach(entry=>{
      if(entry.type==='editorial'){
        children.push(h('div',{key:'wp-editorial-'+(editorialIndex++),className:'rg-wp-page-editor-content rg-wp-home-editor-content rpgx-home-wp-editorial',dangerouslySetInnerHTML:{__html:entry.html}}));
        return;
      }
      const k=entry.category;
      children.push(h(HomeCategory,{key:'category-'+k,category:k,location,radius,onOpen,onLocation,url:entry.url||''}));
      if(!buedchenInserted&&k===firstFoodEvent){
        children.push(h(BuedchenHomeTeaser,{key:'buedchen-home-teaser',location,radius}));
        children.push(h(WurstHomeTeaser,{key:'wurst-home-teaser',location,radius}));
        buedchenInserted=true;
      }
      if(k==='events')children.push(h(IndustrialRouteTeaser,{key:'industrial-route-teaser',location}));
      if(k==='activities')children.push(h(PottQuizHomeTeaser,{key:'pottquiz-home-teaser'}));
    });
    return h('main',{className:'rpgx-main rpgx-home'},children);
  }

  // V228 – Event date filter. This stays entirely in the theme and filters the
  // already loaded event pool, so the Places backend / opening-hours worker is untouched.
  function localIsoDate(date){
    const d=date instanceof Date?date:new Date(date);
    const y=d.getFullYear(),m=String(d.getMonth()+1).padStart(2,'0'),day=String(d.getDate()).padStart(2,'0');
    return y+'-'+m+'-'+day;
  }
  function isoDayBoundary(iso,end){
    const p=String(iso||'').split('-').map(Number);
    if(p.length!==3||!p[0]||!p[1]||!p[2])return null;
    return new Date(p[0],p[1]-1,p[2],end?23:0,end?59:0,end?59:0,end?999:0);
  }
  function eventOverlapsDateRange(item,fromIso,toIso){
    if(!fromIso)return true;
    const rangeStart=isoDayBoundary(fromIso,false),rangeEnd=isoDayBoundary(toIso||fromIso,true);
    const eventStart=item&&item.start instanceof Date?item.start:new Date(item&&item.start||0);
    let eventEnd=item&&item.end instanceof Date?item.end:new Date(item&&item.end||0);
    if(!rangeStart||!rangeEnd||isNaN(eventStart.getTime()))return false;
    if(isNaN(eventEnd.getTime())||eventEnd.getTime()<eventStart.getTime())eventEnd=eventStart;
    return eventStart.getTime()<=rangeEnd.getTime()&&eventEnd.getTime()>=rangeStart.getTime();
  }
  function eventDatePreset(mode){
    const now=new Date();
    if(mode==='today'){
      const d=localIsoDate(now); return {from:d,to:d,label:'Heute'};
    }
    if(mode==='tomorrow'){
      const d=new Date(now.getFullYear(),now.getMonth(),now.getDate()+1); const iso=localIsoDate(d);
      return {from:iso,to:iso,label:'Morgen'};
    }
    if(mode==='weekend'){
      const day=now.getDay();
      let delta;
      if(day===5)delta=0; else if(day===6)delta=-1; else if(day===0)delta=-2; else delta=5-day;
      const fri=new Date(now.getFullYear(),now.getMonth(),now.getDate()+delta);
      const sun=new Date(fri.getFullYear(),fri.getMonth(),fri.getDate()+2);
      return {from:localIsoDate(fri),to:localIsoDate(sun),label:'Am Wochenende'};
    }
    return {from:'',to:'',label:''};
  }
  function formatEventRangeLabel(fromIso,toIso){
    if(!fromIso)return '';
    const a=isoDayBoundary(fromIso,false),b=isoDayBoundary(toIso||fromIso,false);
    if(!a||!b)return '';
    const fmt=d=>d.toLocaleDateString('de-DE',{day:'2-digit',month:'2-digit',year:'numeric'});
    return fromIso===(toIso||fromIso)?fmt(a):(fmt(a)+' – '+fmt(b));
  }
  function CalendarGlyph(){
    return h('svg',{viewBox:'0 0 24 24',width:'20',height:'20','aria-hidden':'true',focusable:'false',fill:'none',stroke:'currentColor',strokeWidth:'2',strokeLinecap:'round',strokeLinejoin:'round'},
      h('rect',{x:'3',y:'5',width:'18',height:'16',rx:'2'}),
      h('path',{d:'M16 3v4M8 3v4M3 11h18'}),
      h('path',{d:'M8 15h.01M12 15h.01M16 15h.01M8 18h.01M12 18h.01'})
    );
  }

  function CategoryView({category,location,radius,onOpen,onRadius}){
    const [homePriority,setHomePriority]=useState(()=>category==='events'?consumeHomeEventPriority(location,radius):'');
    const s=useCategoryItems(category,location,radius,false,'',homePriority); const meta=RPG.CATEGORIES[category]||{title:category};
    const readTagFilter=()=>{try{return RPG.isTagFilterCategory(category)?RPG.tagSlug(new URLSearchParams(window.location.search||'').get('rg_tag')||''):'';}catch(_){return '';}};
    const [query,setQuery]=useState(''),[sub,setSub]=useState(''),[visible,setVisible]=useState(24);
    const [tagFilter,setTagFilter]=useState(readTagFilter);
    const [calendarOpen,setCalendarOpen]=useState(false);
    const [dateMode,setDateMode]=useState(''),[dateFrom,setDateFrom]=useState(''),[dateTo,setDateTo]=useState(''),[dateLabel,setDateLabel]=useState('');
    const [draftFrom,setDraftFrom]=useState(''),[draftTo,setDraftTo]=useState('');
    const writeTagUrl=slug=>{try{const u=new URL(window.location.href);if(slug)u.searchParams.set('rg_tag',slug);else u.searchParams.delete('rg_tag');const r=Number(radius);if(Number.isFinite(r)&&r>=1&&r<=50)u.searchParams.set('radius',String(r));history.replaceState(null,'',u.pathname+(u.searchParams.toString()?'?'+u.searchParams.toString():'')+u.hash);}catch(_){}};
    useEffect(()=>{setVisible(24);},[category,location&&location.lat,location&&location.lng,radius,query,sub,tagFilter,dateMode,dateFrom,dateTo]);
    useEffect(()=>{setTagFilter(readTagFilter());},[category]);
    useEffect(()=>{
      const apply=e=>{const d=e&&e.detail||{};if(!RPG.isTagFilterCategory(category)||String(d.category||'')!==String(category))return;const slug=RPG.tagSlug(d.slug||d.label||'');if(!slug)return;setQuery('');setSub('');setDateMode('');setDateFrom('');setDateTo('');setDateLabel('');setDraftFrom('');setDraftTo('');setCalendarOpen(false);setTagFilter(slug);writeTagUrl(slug);window.requestAnimationFrame(()=>window.scrollTo({top:0,behavior:'smooth'}));};
      const pop=()=>setTagFilter(readTagFilter());
      window.addEventListener('rpg:category-tag-filter',apply);window.addEventListener('popstate',pop);
      return()=>{window.removeEventListener('rpg:category-tag-filter',apply);window.removeEventListener('popstate',pop);};
    },[category,radius]);
    useEffect(()=>{track('category_view',{category,area:location.city||location.name||'',radius,placement:'category'});},[category,location&&location.lat,location&&location.lng,radius]);
    useEffect(()=>{if(query||sub||tagFilter||dateFrom)track('filter_change',{category,area:location.city||location.name||'',radius,placement:'category',date_filter:dateMode||'range',tag_filter:tagFilter||''});},[query,sub,tagFilter,dateMode,dateFrom,dateTo]);
    useEffect(()=>{if(category!=='events'){setCalendarOpen(false);setDateMode('');setDateFrom('');setDateTo('');setDateLabel('');setDraftFrom('');setDraftTo('');}},[category]);
    // V115: "Alle Arten" filters by actual event type. Venue/provider names
    // never belong in this selector.
    const subs=category==='events'
      ? Array.from(new Set(s.items.map(x=>x.eventType).filter(Boolean))).sort((a,b)=>a.localeCompare(b,'de'))
      : Array.from(new Set(s.items.map(x=>x.subtitle).filter(Boolean))).sort((a,b)=>a.localeCompare(b,'de'));
    const applyPreset=mode=>{
      setHomePriority('');
      const p=eventDatePreset(mode);
      setDateMode(mode);setDateFrom(p.from);setDateTo(p.to);setDateLabel(p.label);setDraftFrom(p.from);setDraftTo(p.to);setCalendarOpen(false);
    };
    const applyRange=()=>{
      if(!draftFrom)return;
      setHomePriority('');
      let from=draftFrom,to=draftTo||draftFrom;
      if(to<from){const tmp=from;from=to;to=tmp;}
      setDateMode('range');setDateFrom(from);setDateTo(to);setDateLabel(formatEventRangeLabel(from,to));setDraftFrom(from);setDraftTo(to);setCalendarOpen(false);
    };
    const clearDate=()=>{setDateMode('');setDateFrom('');setDateTo('');setDateLabel('');setDraftFrom('');setDraftTo('');setCalendarOpen(false);};
    const q=query.trim().toLowerCase();
    const tagOptions=RPG.isTagFilterCategory(category)?s.items.reduce((out,x)=>{RPG.categoryFilterTags(x).forEach(label=>{const slug=RPG.tagSlug(label);if(slug&&!out.some(v=>v.slug===slug))out.push({slug,label});});return out;},[]):[];
    const tagLabel=(tagOptions.find(v=>v.slug===tagFilter)||{}).label||String(tagFilter||'').replace(/-/g,' ').replace(/^./,c=>c.toUpperCase());
    const clearTagFilter=()=>{setTagFilter('');writeTagUrl('');};
    const filtered=s.items.filter(x=>{
      const facet=category==='events'?x.eventType:x.subtitle;
      const textOk=!q||[x.title,x.subtitle,x.eventType,x.city,x.address].concat(Array.isArray(x.labels)?x.labels:[]).join(' ').toLowerCase().includes(q);
      const tagOk=!tagFilter||RPG.categoryFilterTags(x).some(label=>RPG.tagSlug(label)===tagFilter);
      const dateOk=category!=='events'||!dateFrom||eventOverlapsDateRange(x,dateFrom,dateTo);
      return (!sub||facet===sub)&&textOk&&tagOk&&dateOk;
    });
    const visibleItems=filtered.slice(0,visible);
    const promotedItems=category==='events'&&homePriority?visibleItems.filter(x=>homeEventMatchesTime(x,homePriority,new Date())):[];
    const otherItems=category==='events'&&homePriority?visibleItems.filter(x=>!homeEventMatchesTime(x,homePriority,new Date())):[];
    const renderCards=items=>h('div',{className:'rpgx-grid'},items.map(item=>h(C.Card,{key:item._rpgNearTermKey||item.id,item,onOpen:(x)=>{track('card_open',{category,area:location.city||location.name||'',radius,placement:'category',content_id:x.id});onOpen(x);}})));
    const before=document.getElementById('ruhrpott-wp-category-before-source'),after=document.getElementById('ruhrpott-wp-category-after-source');
    const toolsClass='rpgx-category-tools'+(category==='events'?' rpgx-event-category-tools':'');
    return h('main',{className:'rpgx-main'},
      before&&before.innerHTML.trim()?h('div',{className:'rpgx-editorial',dangerouslySetInnerHTML:{__html:before.innerHTML}}):null,
      h('section',{className:'rpgx-category-header'},h('h1',null,meta.title),h('p',null,RPG.locationLabel(location)+' · '+radius+' km')),
      h('div',{className:toolsClass},
        h('input',{id:'rpgx-category-search',type:'search',value:query,placeholder:'Suchen …',onChange:e=>setQuery(e.target.value),'aria-label':meta.title+' durchsuchen'}),
        category==='events'?h('button',{type:'button',className:'rpgx-event-calendar-btn'+(dateFrom?' is-active':''),'aria-label':'Events nach Datum filtern','aria-expanded':calendarOpen?'true':'false','aria-controls':'rpgx-event-calendar-panel',title:dateLabel||'Datum auswählen',onClick:()=>setCalendarOpen(v=>!v)},h(CalendarGlyph)):null,
        subs.length?h('select',{value:sub,onChange:e=>setSub(e.target.value),'aria-label':'Art auswählen'},h('option',{value:''},'Alle Arten'),subs.map(v=>h('option',{key:v,value:v},v))):null,
        category==='events'&&calendarOpen?h('div',{id:'rpgx-event-calendar-panel',className:'rpgx-event-calendar-panel'},
          h('div',{className:'rpgx-event-date-presets'},
            h('button',{type:'button',className:dateMode==='today'?'is-active':'',onClick:()=>applyPreset('today')},'Heute'),
            h('button',{type:'button',className:dateMode==='tomorrow'?'is-active':'',onClick:()=>applyPreset('tomorrow')},'Morgen'),
            h('button',{type:'button',className:dateMode==='weekend'?'is-active':'',onClick:()=>applyPreset('weekend')},'Am Wochenende')
          ),
          h('div',{className:'rpgx-event-range-title'},'Zeitraum wählen'),
          h('div',{className:'rpgx-event-date-range'},
            h('label',null,h('span',null,'Von'),h('input',{type:'date',value:draftFrom,onChange:e=>{setDraftFrom(e.target.value);if(!draftTo)setDraftTo(e.target.value);}})),
            h('label',null,h('span',null,'Bis'),h('input',{type:'date',value:draftTo,onChange:e=>setDraftTo(e.target.value),min:draftFrom||undefined}))
          ),
          h('div',{className:'rpgx-event-calendar-actions'},
            h('button',{type:'button',className:'rpgx-event-calendar-reset',onClick:clearDate},'Alle Termine'),
            h('button',{type:'button',className:'rpgx-event-calendar-apply',disabled:!draftFrom,onClick:applyRange},'Übernehmen')
          )
        ):null,
        category==='events'&&dateFrom?h('button',{type:'button',className:'rpgx-event-date-chip',onClick:clearDate,'aria-label':'Datumsfilter '+dateLabel+' entfernen'},h(CalendarGlyph),h('span',null,dateLabel),h('strong',{'aria-hidden':'true'},'×')):null
      ),
      tagFilter&&RPG.isTagFilterCategory(category)?h('div',{className:'rpgx-active-place-tag-wrap'},h('button',{type:'button',className:'rpgx-active-place-tag',onClick:clearTagFilter,'aria-label':'Tag-Filter '+tagLabel+' entfernen'},h('span',null,tagLabel),h('strong',{'aria-hidden':'true'},'×'))):null,
      category==='events'&&homePriority?h('div',{className:'rpgx-event-home-priority-note',role:'status'},
        h('span',null,(homePriority==='now'?'Jetzt':homePriority==='today'?'Heute noch':'Morgen')+' zuerst · danach alle weiteren Termine'),
        h('button',{type:'button',onClick:()=>setHomePriority('')},'Normale Reihenfolge')):null,
      s.loading?h(CategoryGridSkeleton):s.error?h('div',{className:'rpgx-error'},s.error):h(RPG.Fragment,null,
        filtered.length?(category==='events'&&homePriority?h(RPG.Fragment,null,
          promotedItems.length?h('section',{className:'rpgx-event-priority-section'},h('h2',null,homePriority==='now'?'Beginnt jetzt':homePriority==='today'?'Heute noch':'Morgen'),renderCards(promotedItems)):null,
          otherItems.length?h('section',{className:'rpgx-event-priority-section'},h('h2',null,'Weitere Veranstaltungen'),renderCards(otherItems)):null
        ):renderCards(visibleItems)):h(EmptyRadiusState,{radius,onExpand:onRadius,filtered:!!(query||sub||tagFilter||dateFrom)}),
        visible<filtered.length?h('div',{className:'rpgx-load-more'},h('button',{type:'button',className:'rpgx-secondary-btn',onClick:()=>setVisible(v=>v+24)},'Weitere '+Math.min(24,filtered.length-visible)+' anzeigen')):null),
      after&&after.innerHTML.trim()?h('div',{className:'rpgx-editorial',dangerouslySetInnerHTML:{__html:after.innerHTML}}):null
    );
  }

  const BUDE_FEATURES=[
    ['mixed_candy','🍬','Gemischte Tüte'],
    ['coffee','☕','Kaffee'],
    ['sandwiches','🥪','Belegte Brötchen'],
    ['seating','🪑','Sitzplätze'],
    ['lottery','🎟️','Lotto'],
    ['parcel_shop','📦','Paketshop'],
    ['tobacco','◼','Tabak'],
    ['late_night','🌙','Spät geöffnet']
  ];

  function budeFeature(item,key){
    const tags=item.tags||{};
    if(tags['rg:buedchen_feature_'+key]==='yes')return true;
    if(tags['rg:buedchen_feature_'+key]==='no')return false;
    if(key==='coffee')return tags['drink:coffee']==='yes'||tags.coffee==='yes';
    if(key==='seating')return tags.seating==='yes'||tags.outdoor_seating==='yes';
    if(key==='lottery')return tags.lottery==='yes';
    if(key==='tobacco')return tags.tobacco==='yes';
    if(key==='parcel_shop')return tags.parcel_pickup==='yes'||tags.post_office==='yes'||!!tags.parcel_mail_in;
    if(key==='late_night'){
      const oh=String(item.openingHours||'');
      return /(?:23|24):\d{2}|(?:00|01|02):\d{2}/.test(oh);
    }
    return false;
  }

  function budeTier(item){return String((item.tags||{})['rg:buedchen_tier']||'');}
  function budeVerified(item){return String((item.tags||{})['rg:buedchen_review_status']||'')==='verified';}

  function openingState(raw){
    const text=String(raw||'').trim();
    if(!text)return {state:'unknown',label:'Öffnungszeiten nicht bekannt'};
    if(/^24\/7$/i.test(text))return {state:'open',label:'Jetzt geöffnet · '+RPG.formatOpeningHours(text)};
    const days=['Su','Mo','Tu','We','Th','Fr','Sa'];
    const now=new Date(),day=days[now.getDay()],mins=now.getHours()*60+now.getMinutes();
    const dayAliases={Mo:'Mo',Tu:'Tu',We:'We',Th:'Th',Fr:'Fr',Sa:'Sa',Su:'Su'};
    const order=['Mo','Tu','We','Th','Fr','Sa','Su'];
    function dayMatches(spec){
      spec=spec.trim();
      if(!spec)return false;
      for(const part of spec.split(',')){
        const p=part.trim();
        if(p===day)return true;
        const m=p.match(/^(Mo|Tu|We|Th|Fr|Sa|Su)-(Mo|Tu|We|Th|Fr|Sa|Su)$/);
        if(m){
          const a=order.indexOf(m[1]),b=order.indexOf(m[2]),d=order.indexOf(day);
          if(a<=b&&d>=a&&d<=b)return true;
          if(a>b&&(d>=a||d<=b))return true;
        }
      }
      return false;
    }
    for(const segment of text.split(';')){
      const m=segment.trim().match(/^((?:(?:Mo|Tu|We|Th|Fr|Sa|Su)(?:-(?:Mo|Tu|We|Th|Fr|Sa|Su))?(?:,\s*)?)+)\s+(\d{1,2}):(\d{2})-(\d{1,2}):(\d{2})/);
      if(!m||!dayMatches(m[1]))continue;
      const start=Number(m[2])*60+Number(m[3]),end=Number(m[4])*60+Number(m[5]);
      const open=end<start?(mins>=start||mins<=end):(mins>=start&&mins<=end);
      return {state:open?'open':'closed',label:(open?'Jetzt geöffnet':'Aktuell geschlossen')+' · '+RPG.formatOpeningHours(text)};
    }
    return {state:'known',label:'Öffnungszeiten: '+RPG.formatOpeningHours(text)};
  }

  function BudePlaceholder(){
    return h('div',{className:'rpgx-bude-placeholder','aria-hidden':'true'},
      h('div',{className:'rpgx-bude-neon'},'TRINKHALLE'),
      h('div',{className:'rpgx-bude-window'},h('span'),h('span'),h('span')));
  }

  function BudeImage({item}){
    const [image,setImage]=useState(item.imageUrl||'');
    const [failed,setFailed]=useState(false);
    useEffect(()=>{
      let live=true;
      if(image||failed)return()=>{live=false;};
      RPG.resolveItemMedia(item).then(url=>{
        if(live&&url)setImage(url);
      }).catch(()=>{});
      return()=>{live=false;};
    },[item.id,image,failed]);
    if(image&&!failed){
      return h('img',{src:image,alt:'Foto von '+item.title,loading:'lazy',decoding:'async',onError:()=>setFailed(true)});
    }
    return h(BudePlaceholder);
  }


  function BudeReport({item,mode,onClose}){
    const initial=mode||'note';
    const [fields,setFields]=useState({
      opening_hours:'',
      website:'',
      phone:'',
      instagram:'',
      description:'',
      note:'',
      mixed_candy:false,
      coffee:false,
      sandwiches:false,
      seating:false,
      lottery:false,
      parcel_shop:false,
      closed:initial==='closed'
    });
    const [sender,setSender]=useState('community');
    const [status,setStatus]=useState('');

    useEffect(()=>{
      // A context button may open the same complete form at a relevant field,
      // but never limits the user to that one field.
      setTimeout(()=>{
        const id=initial==='opening_hours'?'rpgx-bude-report-opening':initial==='closed'?'rpgx-bude-report-closed':'rpgx-bude-report-note';
        const el=document.getElementById(id);
        if(el&&el.focus)el.focus();
      },30);
    },[]);

    const change=(key,value)=>setFields(current=>Object.assign({},current,{[key]:value}));

    const submit=async()=>{
      if(status==='sending')return;
      const payloadFields={};

      ['opening_hours','website','phone','instagram','description','note'].forEach(key=>{
        const value=String(fields[key]||'').trim();
        if(value)payloadFields[key]=value;
      });
      ['mixed_candy','coffee','sandwiches','seating','lottery','parcel_shop'].forEach(key=>{
        if(fields[key])payloadFields[key]=true;
      });
      if(fields.closed)payloadFields.closed=true;

      if(!Object.keys(payloadFields).length){
        setStatus('Bitte ergänze mindestens eine Angabe.');
        return;
      }

      setStatus('sending');
      try{
        await RPG.api.submitBuedchen({
          placeId:item.id,
          fields:payloadFields,
          submitterType:sender,
          company:''
        });
        setStatus('Danke! Deine Angaben wurden gemeinsam übermittelt und werden geprüft.');
      }catch(e){
        setStatus(e.message||'Hinweis konnte nicht gesendet werden.');
      }
    };

    const featureOptions=[
      ['mixed_candy','🍬','Gemischte Tüte'],
      ['coffee','☕','Kaffee'],
      ['sandwiches','🥪','Belegte Brötchen'],
      ['seating','🪑','Sitzplätze'],
      ['lottery','♧','Lotto'],
      ['parcel_shop','▣','Paketshop']
    ];

    return h('div',{className:'rpgx-bude-report-backdrop',onClick:e=>{if(e.target===e.currentTarget)onClose();}},
      h('div',{className:'rpgx-bude-report rpgx-bude-report-complete',role:'dialog','aria-modal':'true'},
        h('div',{className:'rpgx-bude-report-head'},
          h('div',null,
            h('strong',null,'Angaben zu '+item.title),
            h('span',{className:'rpgx-bude-report-subtitle'},'Ergänze alles, was du weißt – oder nur einzelne Angaben.')
          ),
          h('button',{type:'button',onClick:onClose,'aria-label':'Schließen'},'×')
        ),

        h('div',{className:'rpgx-bude-report-grid'},
          h('label',null,'Öffnungszeiten',
            h('textarea',{id:'rpgx-bude-report-opening',rows:3,value:fields.opening_hours,onChange:e=>change('opening_hours',e.target.value),placeholder:'z. B. Mo–Fr 06:00–22:00, Sa 07:00–23:00'})
          ),
          h('label',null,'Website',
            h('input',{type:'url',value:fields.website,onChange:e=>change('website',e.target.value),placeholder:'https://…'})
          ),
          h('label',null,'Telefon',
            h('input',{type:'tel',value:fields.phone,onChange:e=>change('phone',e.target.value),placeholder:'z. B. 0201 123456'})
          ),
          h('label',null,'Instagram',
            h('input',{type:'text',value:fields.instagram,onChange:e=>change('instagram',e.target.value),placeholder:'@name oder Profil-Link'})
          )
        ),

        h('fieldset',{className:'rpgx-bude-report-features'},
          h('legend',null,'Was gibt es hier?'),
          h('p',null,'Nur auswählen, was du sicher bestätigen kannst. Nicht ausgewählt bedeutet „unbekannt“.'),
          h('div',{className:'rpgx-bude-report-feature-grid'},
            featureOptions.map(([key,icon,label])=>
              h('label',{key},
                h('input',{type:'checkbox',checked:!!fields[key],onChange:e=>change(key,e.target.checked)}),
                h('span',null,icon+' '+label)
              )
            )
          )
        ),

        h('label',null,'Beschreibung / weitere Informationen',
          h('textarea',{rows:3,value:fields.description,onChange:e=>change('description',e.target.value),placeholder:'Was sollte man über das Büdchen wissen?'})
        ),
        h('label',null,'Sonstiger Hinweis',
          h('textarea',{id:'rpgx-bude-report-note',rows:3,value:fields.note,onChange:e=>change('note',e.target.value),placeholder:'z. B. falscher Name, Besonderheit oder andere Korrektur'})
        ),

        h('label',{className:'rpgx-bude-report-closed'},
          h('input',{id:'rpgx-bude-report-closed',type:'checkbox',checked:!!fields.closed,onChange:e=>change('closed',e.target.checked)}),
          h('span',null,h('strong',null,'Dauerhaft geschlossen'),h('small',null,'Diese Meldung wird vor einer Ausblendung geprüft.'))
        ),

        h('label',null,'Du meldest als',
          h('select',{value:sender,onChange:e=>setSender(e.target.value)},
            h('option',{value:'community'},'Besucher/in'),
            h('option',{value:'operator'},'Betreiber/in')
          )
        ),

        h('button',{type:'button',className:'rpgx-bude-primary',onClick:submit,disabled:status==='sending'},
          status==='sending'?'Wird gemeinsam gesendet …':'Angaben senden'
        ),
        status&&status!=='sending'?h('p',{className:'rpgx-bude-report-status'},status):null
      )
    );
  }

  function BudeDetail({item,onClose,origin,standalone=false,overviewUrl=''}){
    const [report,setReport]=useState('');
    const sharing=C.useSharing(item);
    const opening=openingState(item.openingHours);
    const features=BUDE_FEATURES.filter(x=>budeFeature(item,x[0]));
    const route=item.coords&&origin?'https://www.openstreetmap.org/directions?engine=fossgis_osrm_car&route='+encodeURIComponent(origin.lat+','+origin.lng)+'%3B'+encodeURIComponent(item.coords.lat+','+item.coords.lng):(standalone&&item.coords?'https://www.google.com/maps/search/?api=1&query='+encodeURIComponent(item.coords.lat+','+item.coords.lng):'');
    useEffect(()=>{if(standalone)return;document.documentElement.classList.add('rpgx-modal-open');document.body.classList.add('rpgx-modal-open');return()=>{document.documentElement.classList.remove('rpgx-modal-open');document.body.classList.remove('rpgx-modal-open');};},[standalone]);
    return h('div',{className:standalone?'rpgx-bude-detail-landing':'rpgx-detail-backdrop rpgx-bude-detail-backdrop',style:standalone?{display:'flex',justifyContent:'center',padding:'16px'}:undefined,onClick:standalone?undefined:e=>{if(e.target===e.currentTarget)onClose();}},
      h('article',{className:'rpgx-bude-detail',style:standalone?{width:'100%',maxWidth:'768px',maxHeight:'none',overflow:'visible'}:undefined,role:standalone?undefined:'dialog','aria-modal':standalone?undefined:'true','aria-hidden':sharing.mode?true:undefined,inert:sharing.mode?'':undefined},
        h('div',{className:'sticky top-0 z-30 flex items-center gap-2 px-3 py-2 border-b backdrop-blur-md bg-slate-950/95 border-slate-800 text-white'},
          h('span',{className:'truncate flex-1 font-display text-sm font-bold'},item.title),
          h(standalone?'a':'button',{type:standalone?undefined:'button',href:standalone?(overviewUrl||'/buedchen-radar/'):undefined,onClick:standalone?undefined:onClose,className:'flex items-center gap-1.5 min-h-[44px] px-3.5 py-1.5 rounded-full bg-amber-500 hover:bg-amber-400 active:scale-95 text-slate-950 text-xs font-extrabold transition-all shadow-md shrink-0 cursor-pointer','aria-label':standalone?'Zum Trinkhallen-Radar':'Detailansicht schließen',title:standalone?'Zum Trinkhallen-Radar':'Detailansicht schließen'},
            h('span',{'aria-hidden':'true',className:'text-lg leading-none font-black'},standalone?'←':'×'),
            h('span',null,standalone?'Zum Trinkhallen-Radar':'Schließen')
          )
        ),
        h('div',{className:'rpgx-bude-detail-media'},h(C.DetailGallery,{item,image:item.imageUrl||'',fallback:h(BudePlaceholder)})),
        h('div',{className:'rpgx-bude-detail-body'},
          h('div',{className:'rpgx-bude-detail-title'},h('div',null,h(standalone?'h1':'h2',{style:standalone?{fontSize:'clamp(22px,3vw,30px)',margin:'0 0 4px',color:'#fff'}:undefined},item.title),h('p',null,item.address||item.city)),Number.isFinite(item.distanceKm)?h('strong',null,item.distanceKm<1?Math.round(item.distanceKm*1000)+' m':item.distanceKm.toFixed(1)+' km'):null),
          h('div',{className:'rpgx-bude-opening '+opening.state},opening.label),
          features.length?h('div',{className:'rpgx-bude-feature-row'},features.map(x=>h('span',{key:x[0]},x[1]+' '+x[2]))):null,
          h('div',{className:'rpgx-bude-actions'},
            route?h('a',{href:route,target:'_blank',rel:'noopener noreferrer',className:'rpgx-bude-primary'},'↗ Route öffnen'):null,
            item.website?h('a',{href:item.website,target:'_blank',rel:'noopener noreferrer',className:'rpgx-bude-secondary'},'Website ↗'):null
          ),
          h('div',{className:'rpgx-bude-actions rpgx-bude-share-actions'},
            h('button',{type:'button',className:'rpgx-bude-secondary',title:'Dieses Büdchen teilen',onClick:e=>sharing.open('share',e)},sharing.copied?'Kopiert ✓':'↥ Teilen'),
            h('button',{type:'button',className:'rpgx-kommrum-trigger',title:'Freunde zu diesem Büdchen einladen',onClick:e=>sharing.open('invite',e)},'🍻 Komm rum, ich bin hier!')
          ),
          item.openingHours?h('section',{className:'rpgx-bude-detail-section'},h(standalone?'h2':'h3',{style:standalone?{fontSize:'14px',fontWeight:700,margin:'0 0 8px'}:undefined},'Öffnungszeiten'),h('p',null,RPG.formatOpeningHours(item.openingHours))):h('section',{className:'rpgx-bude-unknown'},h('strong',null,'Öffnungszeiten nicht bekannt'),h('p',null,'Weißt du mehr? Hilf mit, den Radar besser zu machen.')),
          item.description?h('section',{className:'rpgx-bude-detail-section'},h(standalone?'h2':'h3',{style:standalone?{fontSize:'14px',fontWeight:700,margin:'0 0 8px'}:undefined},'Über dieses Büdchen'),h('p',null,item.description)):null,
          h('section',{className:'rpgx-bude-community'},h(standalone?'h2':'h3',{style:standalone?{fontSize:'14px',fontWeight:700,margin:'0 0 8px'}:undefined},'Gemeinsam fürs Revier'),
            h('button',{type:'button',className:'rpgx-report-item',onClick:()=>setReport('contribution')},'Daten ergänzen oder Bild hochladen')
          )
        ),
        report?h(C.ContributionForm,{item,onClose:()=>setReport('')}):null
      ),
      sharing.mode?h(C.ShareDialog,{sharing}):null
    );
  }

  function BudeMap({items,location,radius,onPick,onViewport}){
    const host=React.useRef(null);
    const pointers=React.useRef(new Map());
    const drag=React.useRef(null);
    const pinch=React.useRef(null);
    const settleTimer=React.useRef(null);
    const gestureActive=React.useRef(false);
    const pendingViewport=React.useRef(null);
    const [size,setSize]=useState({w:320,h:360});
    const initialZoom=Number(radius)<=1?16:Number(radius)<=2.5?15:14;
    const [zoom,setZoom]=useState(initialZoom);
    const [center,setCenter]=useState({lat:location.lat,lng:location.lng});
    const [moved,setMoved]=useState(false);

    const clampLat=lat=>Math.max(-85.0511,Math.min(85.0511,lat));
    const world=(lat,lng,z)=>{
      const scale=Math.pow(2,z)*256;
      const sin=Math.sin(clampLat(lat)*Math.PI/180);
      return {x:(lng+180)/360*scale,y:(.5-Math.log((1+sin)/(1-sin))/(4*Math.PI))*scale};
    };
    const fromWorld=(x,y,z)=>{
      const scale=Math.pow(2,z)*256;
      const lng=x/scale*360-180;
      const n=Math.PI-2*Math.PI*y/scale;
      const lat=180/Math.PI*Math.atan(.5*(Math.exp(n)-Math.exp(-n)));
      return {lat:clampLat(lat),lng:((lng+540)%360)-180};
    };
    const viewportRadiusKm=(c,z)=>{
      const metersPerPx=Math.cos(c.lat*Math.PI/180)*156543.03392/Math.pow(2,z);
      const halfDiag=Math.sqrt(Math.pow(size.w/2,2)+Math.pow(size.h/2,2));
      return Math.max(.5,Math.min(50,(halfDiag*metersPerPx)/1000));
    };
    const viewportPayload=(c,z,wasMoved=true)=>({
      center:c,
      zoom:z,
      radiusKm:viewportRadiusKm(c,z),
      moved:wasMoved
    });
    const commitViewport=(payload,delay=0)=>{
      if(!onViewport||!payload)return;
      if(settleTimer.current)clearTimeout(settleTimer.current);
      if(delay>0){
        settleTimer.current=setTimeout(()=>onViewport(payload),delay);
      }else{
        onViewport(payload);
      }
    };
    const setView=(c,z,wasMoved=true,commit=true)=>{
      const nz=Math.max(10,Math.min(18,Number(z)));
      setCenter(c);setZoom(nz);
      if(wasMoved)setMoved(true);
      const payload=viewportPayload(c,nz,wasMoved);
      pendingViewport.current=payload;
      // Never query while fingers are still on the map.
      if(commit&&!gestureActive.current)commitViewport(payload,350);
    };

    useEffect(()=>{
      const z=Number(radius)<=1?16:Number(radius)<=2.5?15:14;
      const c={lat:location.lat,lng:location.lng};
      setCenter(c);setZoom(z);setMoved(false);
      pendingViewport.current=viewportPayload(c,z,false);
    },[location.lat,location.lng,radius]);

    useEffect(()=>{
      if(!host.current)return;
      const update=()=>setSize({w:host.current.clientWidth||320,h:host.current.clientHeight||360});
      update();
      if(window.ResizeObserver){const ro=new ResizeObserver(update);ro.observe(host.current);return()=>ro.disconnect();}
      window.addEventListener('resize',update);return()=>window.removeEventListener('resize',update);
    },[]);

    useEffect(()=>()=>{if(settleTimer.current)clearTimeout(settleTimer.current);},[]);

    const centerWorld=world(center.lat,center.lng,zoom),tileSize=256,n=Math.pow(2,zoom);
    const minTx=Math.floor((centerWorld.x-size.w/2)/tileSize)-1,maxTx=Math.floor((centerWorld.x+size.w/2)/tileSize)+1;
    const minTy=Math.max(0,Math.floor((centerWorld.y-size.h/2)/tileSize)-1),maxTy=Math.min(n-1,Math.floor((centerWorld.y+size.h/2)/tileSize)+1);
    const tileBase=RPG.settings.buedchenTileBaseUrl||'/wp-admin/admin-ajax.php?action=rg_places_map_tile';
    const tiles=[];
    for(let tx=minTx;tx<=maxTx;tx++)for(let ty=minTy;ty<=maxTy;ty++){
      const wrapped=((tx%n)+n)%n;
      const tileUrl=tileBase+(tileBase.includes('?')?'&':'?')+'z='+zoom+'&x='+wrapped+'&y='+ty;
      tiles.push(h('img',{key:tx+':'+ty,className:'rpgx-bude-map-tile',src:tileUrl,alt:'',draggable:false,style:{left:(tx*tileSize-centerWorld.x+size.w/2)+'px',top:(ty*tileSize-centerWorld.y+size.h/2)+'px'}}));
    }

    const markerPoints=items.slice(0,120).map(item=>{
      if(!item.coords)return null;
      const p=world(item.coords.lat,item.coords.lng,zoom),left=p.x-centerWorld.x+size.w/2,top=p.y-centerWorld.y+size.h/2;
      if(left<-30||top<-30||left>size.w+30||top>size.h+30)return null;
      return {item,left,top};
    }).filter(Boolean);

    // A marker opens its detail directly only when it is visually unambiguous.
    // If two Büdchen markers overlap (or are so close that the hit targets
    // collide), both stay visible but are intentionally not clickable.
    const MARKER_COLLISION_PX=24;
    markerPoints.forEach((point,index)=>{
      point.overlaps=markerPoints.some((other,otherIndex)=>{
        if(index===otherIndex)return false;
        return Math.hypot(point.left-other.left,point.top-other.top)<MARKER_COLLISION_PX;
      });
    });
    const markers=markerPoints.map(({item,left,top,overlaps})=>{
      const props={
        key:item.id,
        type:'button',
        className:'rpgx-bude-map-marker'+(overlaps?' is-overlapping':' is-clickable'),
        style:{left:left+'px',top:top+'px'},
        title:overlaps?item.title+' · Zum Öffnen näher heranzoomen':item.title,
        'aria-label':overlaps?item.title+' – Marker überlappt, bitte näher heranzoomen':item.title+' – Details öffnen',
        'aria-disabled':overlaps?'true':undefined
      };
      if(!overlaps){
        // Marker interaction must never enter the map's drag/pan state.
        // Without stopping pointer-down first, desktop React bubbling lets the
        // parent map capture the pointer; even a tiny mouse movement then
        // changes the viewport and reloads the list instead of opening details.
        props.onPointerDown=e=>{e.stopPropagation();};
        props.onPointerUp=e=>{e.stopPropagation();};
        props.onClick=e=>{
          e.preventDefault();
          e.stopPropagation();
          onPick(item);
        };
      }else{
        props.tabIndex=-1;
      }
      return h('button',props,'●');
    });

    // The blue marker represents the actual selected/GPS location, not the
    // current viewport centre. On initial load/reset both are identical, so the
    // location marker is exactly centred. After panning it moves correctly with
    // the map instead of falsely staying in the middle.
    const userPoint=world(location.lat,location.lng,zoom);
    const userLeft=userPoint.x-centerWorld.x+size.w/2;
    const userTop=userPoint.y-centerWorld.y+size.h/2;
    const userVisible=userLeft>=-40&&userTop>=-40&&userLeft<=size.w+40&&userTop<=size.h+40;

    const metersPerPx=Math.cos(center.lat*Math.PI/180)*156543.03392/Math.pow(2,zoom);
    const radiusPx=Math.min(Math.max((Number(radius)*1000)/metersPerPx,24),Math.max(size.w,size.h)*1.4);

    const panBy=(dx,dy)=>{
      const cw=world(center.lat,center.lng,zoom);
      const c=fromWorld(cw.x-dx,cw.y-dy,zoom);
      setView(c,zoom,true,!gestureActive.current);
    };
    const zoomAt=(targetZoom,clientX,clientY)=>{
      const nz=Math.max(10,Math.min(18,targetZoom));
      if(nz===zoom)return;
      const rect=host.current&&host.current.getBoundingClientRect();
      if(!rect){setView(center,nz,true);return;}
      const px=clientX-rect.left,py=clientY-rect.top;
      const before={x:centerWorld.x+(px-size.w/2),y:centerWorld.y+(py-size.h/2)};
      const geo=fromWorld(before.x,before.y,zoom);
      const after=world(geo.lat,geo.lng,nz);
      const newCenterWorld={x:after.x-(px-size.w/2),y:after.y-(py-size.h/2)};
      setView(fromWorld(newCenterWorld.x,newCenterWorld.y,nz),nz,true,!gestureActive.current);
    };
    const pointerDown=e=>{
      if(e.button!=null&&e.button!==0)return;
      const touch=e.pointerType==='touch';

      // Critical mobile rule: the first touch is NOT pointer-captured.
      // It stays completely available to the browser for normal page scrolling.
      if(!touch&&host.current&&host.current.setPointerCapture){
        host.current.setPointerCapture(e.pointerId);
      }

      pointers.current.set(e.pointerId,{x:e.clientX,y:e.clientY,type:e.pointerType||'mouse'});

      if(touch&&pointers.current.size>=2){
        gestureActive.current=true;
        if(settleTimer.current)clearTimeout(settleTimer.current);
        // Only once the second finger is present does the map take ownership
        // of the gesture. Capture both touch pointers for a stable pinch/pan.
        if(host.current&&host.current.setPointerCapture){
          [...pointers.current.keys()].forEach(id=>{try{host.current.setPointerCapture(id);}catch(_){}});
        }
        e.preventDefault();
      }

      if(pointers.current.size===1){
        // Mouse/pen: one pointer drags. Touch: one finger scrolls the page.
        drag.current=touch?null:{x:e.clientX,y:e.clientY};
        pinch.current=null;
      }else if(pointers.current.size===2){
        const pts=[...pointers.current.values()];
        pinch.current={
          distance:Math.hypot(pts[0].x-pts[1].x,pts[0].y-pts[1].y),
          zoom,
          midX:(pts[0].x+pts[1].x)/2,
          midY:(pts[0].y+pts[1].y)/2
        };
        drag.current=null;
      }
    };
    const pointerMove=e=>{
      if(!pointers.current.has(e.pointerId))return;
      const current=pointers.current.get(e.pointerId);
      pointers.current.set(e.pointerId,{x:e.clientX,y:e.clientY,type:e.pointerType||current.type||'mouse'});
      if(pointers.current.size===1&&drag.current){
        // Mouse/pen desktop drag only. One-finger touch must keep scrolling the page.
        e.preventDefault();
        const dx=e.clientX-drag.current.x,dy=e.clientY-drag.current.y;
        drag.current={x:e.clientX,y:e.clientY};
        panBy(dx,dy);
      }else if(pointers.current.size===2){
        e.preventDefault();
        const pts=[...pointers.current.values()];
        const midX=(pts[0].x+pts[1].x)/2,midY=(pts[0].y+pts[1].y)/2;
        const d=Math.hypot(pts[0].x-pts[1].x,pts[0].y-pts[1].y);
        if(!pinch.current)pinch.current={distance:d,zoom,midX,midY};

        // Two-finger pan: move the midpoint.
        const dx=midX-pinch.current.midX,dy=midY-pinch.current.midY;
        if(Math.abs(dx)>1||Math.abs(dy)>1)panBy(dx,dy);

        // Two-finger pinch zoom.
        const ratio=d/Math.max(1,pinch.current.distance);
        if(ratio>1.18){
          zoomAt(zoom+1,midX,midY);
          pinch.current={distance:d,zoom:zoom+1,midX,midY};
        }else if(ratio<.84){
          zoomAt(zoom-1,midX,midY);
          pinch.current={distance:d,zoom:zoom-1,midX,midY};
        }else{
          pinch.current={distance:d,zoom:pinch.current.zoom,midX,midY};
        }
      }
    };
    const pointerUp=e=>{
      const wasTouch=e.pointerType==='touch';
      if(host.current&&host.current.hasPointerCapture&&host.current.hasPointerCapture(e.pointerId)){
        try{host.current.releasePointerCapture(e.pointerId);}catch(_){}
      }
      pointers.current.delete(e.pointerId);
      if(pointers.current.size===1){
        const pt=[...pointers.current.values()][0];
        // A remaining touch finger never becomes a one-finger map drag.
        drag.current=pt&&pt.type!=='touch'?{x:pt.x,y:pt.y}:null;
        pinch.current=null;
      }else{
        drag.current=null;
        pinch.current=null;
      }

      // Mobile: querying starts only after the whole two-finger gesture is finished.
      if(wasTouch&&gestureActive.current&&pointers.current.size===0){
        gestureActive.current=false;
        commitViewport(pendingViewport.current,0);
      }
    };
    const wheel=e=>{
      e.preventDefault();
      zoomAt(zoom+(e.deltaY<0?1:-1),e.clientX,e.clientY);
    };
    const reset=()=>{
      const z=Number(radius)<=1?16:Number(radius)<=2.5?15:14;
      const c={lat:location.lat,lng:location.lng};
      setCenter(c);setZoom(z);setMoved(false);
      const payload=viewportPayload(c,z,false);
      pendingViewport.current=payload;
      commitViewport(payload,0);
    };

    return h('div',{ref:host,className:'rpgx-bude-map interactive','aria-label':'Interaktive Karte mit Büdchen in deiner Nähe',
      onPointerDown:pointerDown,onPointerMove:pointerMove,onPointerUp:pointerUp,onPointerCancel:pointerUp,onWheel:wheel},
      h('div',{className:'rpgx-bude-map-tiles'},tiles),
      !moved?h('div',{className:'rpgx-bude-map-radius',style:{width:(radiusPx*2)+'px',height:(radiusPx*2)+'px'}}):null,
      userVisible?h('div',{className:'rpgx-bude-map-center rpgx-bude-user-location',style:{left:userLeft+'px',top:userTop+'px'},title:'Mein Standort','aria-label':'Mein Standort'},'●'):null,
      markers,
      h('div',{className:'rpgx-bude-map-controls'},
        h('button',{type:'button',onClick:e=>{e.stopPropagation();zoomAt(zoom+1,size.w/2,size.h/2);},'aria-label':'Karte vergrößern'},'+'),
        h('button',{type:'button',onClick:e=>{e.stopPropagation();zoomAt(zoom-1,size.w/2,size.h/2);},'aria-label':'Karte verkleinern'},'−'),
        moved?h('button',{type:'button',className:'reset',onClick:e=>{e.stopPropagation();reset();},'aria-label':'Zum Standort zurück'},'⌖'):null
      ),
      moved?h('div',{className:'rpgx-bude-map-moved'},'Kartenausschnitt · Liste aktualisiert'):null,
      h('a',{className:'rpgx-bude-map-attribution',href:'https://www.openstreetmap.org/copyright',target:'_blank',rel:'noopener noreferrer',onPointerDown:e=>e.stopPropagation()},'© OpenStreetMap contributors')
    );
  }

  function BuedchenRadarView({location,radius,onRadius,onLocation}){
    const [state,setState]=useState({loading:true,error:'',items:[]});
    const [filters,setFilters]=useState({});
    const [detail,setDetail]=useState(null);
    const [visible,setVisible]=useState(20);
    const [mapScope,setMapScope]=useState(null);

    useEffect(function(){
      if(!location)return;
      setMapScope(null);
      let live=true;setState({loading:true,error:'',items:[]});setVisible(20);
      RPG.api.buedchen(location,radius,200).then(raw=>{
        if(!live)return;
        const normalized=raw.map(x=>RPG.normalizePlace(x,'buedchen',location)).sort((a,b)=>(a.distanceKm??999)-(b.distanceKm??999));
        const a=normalized.filter(x=>budeVerified(x)||budeTier(x)==='A');
        const b=normalized.filter(x=>!budeVerified(x)&&budeTier(x)==='B');
        // The public result set is fixed for this location + radius.
        // Tier A is shown by default; Tier B only fills local gaps up to 12.
        // Filters later work exclusively inside this same fixed set.
        const publicItems=a.length>=12?a:a.concat(b.slice(0,Math.max(0,12-a.length)));
        setState({loading:false,error:'',items:publicItems});
        track('category_view',{category:'buedchen-radar',area:location.city||location.name||'',radius,count:publicItems.length});
      }).catch(e=>{if(live)setState({loading:false,error:e.message||'Büdchen konnten nicht geladen werden.',items:[]});});
      return()=>{live=false;};
    },[location&&location.lat,location&&location.lng,radius]);

    const qualitySelect=(normalized)=>{
      const a=normalized.filter(x=>budeVerified(x)||budeTier(x)==='A');
      const b=normalized.filter(x=>!budeVerified(x)&&budeTier(x)==='B');
      return a.length>=12?a:a.concat(b.slice(0,Math.max(0,12-a.length)));
    };
    const updateMapScope=viewport=>{
      if(!viewport||!viewport.moved){
        setMapScope(null);
        return;
      }
      const center=viewport.center,radiusKm=Math.max(.5,Math.min(50,viewport.radiusKm||5));
      setMapScope({loading:true,center,radiusKm,zoom:viewport.zoom,items:[]});
      RPG.api.buedchen(center,radiusKm,200).then(raw=>{
        const normalized=raw.map(x=>RPG.normalizePlace(x,'buedchen',center))
          .sort((a,b)=>(a.distanceKm??999)-(b.distanceKm??999));
        setMapScope({loading:false,center,radiusKm,zoom:viewport.zoom,items:qualitySelect(normalized)});
        setVisible(20);
      }).catch(()=>setMapScope({loading:false,center,radiusKm,zoom:viewport.zoom,items:[]}));
    };
    const activeItems=mapScope&&mapScope.items?mapScope.items:state.items;

    // Radius + quality selection define one fixed public result set.
    // Every filter may only reduce this set; a filter can never introduce
    // additional Büdchen that were not already part of the current radius result.
    const filtered=useMemo(()=>activeItems.filter(item=>{
      const opening=openingState(item.openingHours);
      if(filters.open&&opening.state!=='open')return false;
      if(filters.lottery&&!budeFeature(item,'lottery'))return false;
      for(const key of ['mixed_candy','coffee','seating','parcel_shop']){
        if(filters[key]&&!budeFeature(item,key))return false;
      }
      return true;
    }),[activeItems,filters]);

    const timeKeys=['open'],featureKeys=['mixed_candy','coffee','seating','parcel_shop','lottery'];
    const toggle=key=>setFilters(current=>{
      const next=Object.assign({},current);
      const activating=!next[key];
      // Time filters and assortment filters are independent modes. Choosing
      // coffee/seating/etc. clears an old "opened" constraint, and vice versa.
      if(activating&&featureKeys.includes(key))timeKeys.forEach(k=>{next[k]=false;});
      if(activating&&timeKeys.includes(key))featureKeys.forEach(k=>{next[k]=false;});
      next[key]=activating;
      return next;
    });
    const radiusSteps=[1,2.5,5,10,25];

    return h('main',{className:'rpgx-main rpgx-bude-page'},
      h('section',{className:'rpgx-bude-hero'},
        h('div',{className:'rpgx-bude-hero-art'},h(BudePlaceholder)),
        h('div',{className:'rpgx-bude-hero-copy'},h('span',{className:'rpgx-bude-kicker'},'ECHTE REVIERKULTUR'),h('h1',null,'BÜDCHEN-RADAR ',h('span',null,'◔')),h('p',null,'Gemischte Tüte, kalte Pulle, netter Schnack.'),h('small',null,'Finde Trinkhallen und Büdchen rund um deinen Standort.')),
        h('div',{className:'rpgx-bude-location-card'},
          h('div',null,h('span',null,'📍'),h('div',null,h('small',null,'Mein Standort'),h('strong',null,RPG.locationLabel(location)))),
          h('button',{type:'button',onClick:onLocation},'Standort ändern')
        ),
        h('div',{className:'rpgx-bude-radius'},h('small',null,'Radius'),h('div',null,radiusSteps.map(x=>h('button',{key:x,type:'button',className:Number(radius)===x?'active':'',onClick:()=>onRadius(x)},x+' km'))))
      ),

      h('section',{className:'rpgx-bude-filters'},h('div',{className:'rpgx-bude-section-title'},h('h2',null,'Schnellfilter'),Object.values(filters).some(Boolean)?h('button',{type:'button',onClick:()=>setFilters({})},'Zurücksetzen'):null),
        h('div',{className:'rpgx-bude-filter-grid'},
          [['open','●','Jetzt geöffnet'],['lottery','🎟️','Lotto'],['mixed_candy','🍬','Gemischte Tüte'],['coffee','☕','Kaffee'],['seating','🪑','Sitzplätze'],['parcel_shop','📦','Paketshop']].map(x=>h('button',{key:x[0],type:'button',className:filters[x[0]]?'active':'',onClick:()=>toggle(x[0])},h('span',null,x[1]),x[2]))
        )
      ),

      state.loading?h('div',{className:'rpgx-loading'},'Büdchen werden geortet …'):state.error?h('div',{className:'rpgx-error'},state.error):h(RPG.Fragment,null,
        h('section',{className:'rpgx-bude-radar-section'},h('div',{className:'rpgx-bude-section-title'},h('h2',null,'Radar-Ansicht'),h('span',null,filtered.length+' Treffer')),h(BudeMap,{items:filtered,location,radius,onViewport:updateMapScope,onPick:item=>setDetail(item)})),
        h('section',{className:'rpgx-bude-results'},h('div',{className:'rpgx-bude-section-title'},h('h2',null,filtered.length+' Büdchen gefunden'),h('span',null,mapScope?'im Kartenausschnitt':'nach Entfernung')),
          filtered.length?h('div',{className:'rpgx-bude-list'},filtered.slice(0,visible).map(item=>{
            const opening=openingState(item.openingHours),features=BUDE_FEATURES.filter(x=>budeFeature(item,x[0])).slice(0,3);
            return h('article',{key:item.id,id:'bude-'+item.id,className:'rpgx-bude-card',onClick:()=>setDetail(item)},
              h('div',{className:'rpgx-bude-card-media'},h(BudeImage,{item})),
              h('div',{className:'rpgx-bude-card-body'},h('h3',null,item.title),h('p',{className:'rpgx-bude-address'},item.address||item.city),
                h('p',{className:'rpgx-bude-opening '+opening.state},opening.label),
                features.length?h('div',{className:'rpgx-bude-mini-tags'},features.map(x=>h('span',{key:x[0]},x[1]+' '+x[2]))):null
              ),
              Number.isFinite(item.distanceKm)?h('strong',{className:'rpgx-bude-distance'},item.distanceKm<1?Math.round(item.distanceKm*1000)+' m':item.distanceKm.toFixed(1)+' km'):null
            );
          })):h('div',{className:'rpgx-smart-empty'},h('strong',null,'Für diese Filter gibt es gerade keine Treffer.'),h('p',null,'Setze einen Filter zurück oder erweitere den Radius.')),
          visible<filtered.length?h('button',{type:'button',className:'rpgx-bude-more',onClick:()=>setVisible(x=>x+20)},'Mehr Büdchen laden'):null
        ),
        h('section',{className:'rpgx-bude-culture'},h('article',null,h('span',null,'☆'),h('div',null,h('h3',null,'Kult. Nah. Echt.'),h('p',null,'Trinkhallen sind mehr als ein Laden – sie sind Treffpunkt und ein Stück Ruhrpott-Kultur.'))),h('article',null,h('span',null,'＋'),h('div',null,h('h3',null,'Dein Wissen zählt'),h('p',null,'Fehlende Öffnungszeiten oder falsche Angaben kannst du direkt am Büdchen melden.'))))
      ),
      h('p',{className:'rpgx-bude-disclaimer'},'Hinweis: Öffnungszeiten und Angaben können sich ändern. Community-Hinweise werden vor Veröffentlichung geprüft.'),
      detail?h(BudeDetail,{item:detail,origin:location,onClose:()=>setDetail(null)}):null
    );
  }


  const WURST_FILTERS=[
    ['currywurst','🌭','Currywurst'],['pommes','🍟','Pommes-Schmiede'],['schnitzel','🍳','Aus der Pfanne'],
    ['frikadelle','●','Wie bei Omma'],['schaschlik','◆','Schaschlik'],['bratwurst','♨','Bratwurst']
  ];
  function wurstMenuItems(item){return String((item.tags||{})['rg:wurst_menu_items']||'').split('|').map(x=>x.trim()).filter(Boolean);}
  function WurstPlaceholder(){return h('div',{className:'rpgx-wurst-placeholder','aria-hidden':'true'},h('span',{className:'rpgx-wurst-placeholder-top'},'ECHTE BUDE'),h('span',{className:'rpgx-wurst-placeholder-mark'},'WURST'),h('span',{className:'rpgx-wurst-placeholder-bottom'},'POMMES · CURRY · POTT'));}
  function WurstCard({item,onOpen}){
    const menu=wurstMenuItems(item), district=(item.tags||{})['addr:suburb']||'';
    return h('button',{type:'button',className:'rpgx-wurst-card',onClick:()=>onOpen(item),'aria-label':item.title+' öffnen'},
      h('div',{className:'rpgx-wurst-card-media'},item.imageUrl?h('img',{src:item.imageUrl,alt:'',loading:'lazy',decoding:'async',referrerPolicy:'no-referrer',onError:e=>{e.currentTarget.style.display='none';e.currentTarget.nextSibling&&(e.currentTarget.nextSibling.style.display='flex');}}):null,h('div',{style:item.imageUrl?{display:'none'}:null,className:'rpgx-wurst-card-fallback'},h(WurstPlaceholder)),h('div',{className:'rpgx-wurst-card-shade'}),
        Number.isFinite(item.distanceKm)?h('span',{className:'rpgx-wurst-distance'},RPG.formatDistance(item.distanceKm)):null,
        h('span',{className:'rpgx-wurst-tier '+(((item.tags||{})['rg:wurst_tier']==='B' && (item.tags||{})['rg:wurst_review_status']!=='confirmed')?'tier-b':'tier-a')},((item.tags||{})['rg:wurst_tier']==='B' && (item.tags||{})['rg:wurst_review_status']!=='confirmed')?'Im Radar':'Geprüft'),
        menu.length?h('div',{className:'rpgx-wurst-specialties'},menu.slice(0,3).map(m=>h('span',{key:m,title:m},m==='currywurst'?'🌭':m==='pommes'?'🍟':m==='schnitzel'?'🍳':m==='frikadelle'?'●':m==='schaschlik'?'◆':'♨'))):null,
        h('div',{className:'rpgx-wurst-card-copy'},h('h3',null,item.title),h('p',null,[item.city,district].filter(Boolean).join(' · ')||item.address||'Ruhrgebiet'))
      ),
      h('div',{className:'rpgx-wurst-card-foot'},menu.length?h('span',null,menu.slice(0,3).map(x=>x.charAt(0).toUpperCase()+x.slice(1)).join(' · ')):h('span',{className:'muted'},'Angebot noch nicht vollständig erfasst'),h('strong',null,'Details →'))
    );
  }
  function WurstSubmit(){
    const [open,setOpen]=useState(false);
    const [sent,setSent]=useState(false);
    const [loading,setLoading]=useState(false);
    const [error,setError]=useState('');
    const [form,setForm]=useState({name:'',city:'',address:'',website:'',note:'',company:''});
    const update=(key,val)=>setForm(x=>Object.assign({},x,{[key]:val}));
    const submit=async()=>{
      if(!form.name.trim()||loading)return;
      setLoading(true);setError('');
      try{await RPG.api.submitWurst(form);setSent(true);}
      catch(e){setError('Das hat gerade nicht geklappt. Bitte später noch einmal versuchen.');}
      finally{setLoading(false);}
    };
    let action;
    if(sent){
      action=h('div',{className:'rpgx-wurst-submit-success'},
        h('strong',null,'Danke, ist angekommen.'),
        h('p',null,'Die Bude bleibt zunächst ungeprüft und wird nicht automatisch veröffentlicht.')
      );
    }else if(!open){
      action=h('button',{type:'button',className:'rpgx-wurst-community-btn',onClick:()=>setOpen(true)},'Stammbude vorschlagen →');
    }else{
      action=h('div',{className:'rpgx-wurst-submit'},
        h('div',{className:'rpgx-wurst-hp',hidden:true},h('input',{tabIndex:-1,autoComplete:'off',value:form.company,onChange:e=>update('company',e.target.value)})),
        h('label',null,'Name der Bude *',h('input',{value:form.name,maxLength:190,onChange:e=>update('name',e.target.value),placeholder:'z. B. Achims Grillhütte'})),
        h('div',{className:'rpgx-wurst-submit-grid'},
          h('label',null,'Stadt',h('input',{value:form.city,maxLength:120,onChange:e=>update('city',e.target.value),placeholder:'z. B. Wesel'})),
          h('label',null,'Adresse',h('input',{value:form.address,maxLength:240,onChange:e=>update('address',e.target.value),placeholder:'Straße, Hausnummer'}))
        ),
        h('label',null,'Website, falls vorhanden',h('input',{value:form.website,onChange:e=>update('website',e.target.value),placeholder:'https://…'})),
        h('label',null,'Warum gehört sie hier rein?',h('textarea',{value:form.note,maxLength:1000,onChange:e=>update('note',e.target.value),placeholder:'Currywurst, Pommes, Schnitzel …'})),
        error?h('p',{className:'rpgx-wurst-submit-error'},error):null,
        h('div',{className:'rpgx-wurst-submit-actions'},
          h('button',{type:'button',onClick:()=>setOpen(false)},'Abbrechen'),
          h('button',{type:'button',className:'primary',disabled:!form.name.trim()||loading,onClick:submit},loading?'Wird gesendet …':'Zur Prüfung schicken')
        )
      );
    }
    return h('section',{className:'rpgx-wurst-community'},
      h('div',{className:'rpgx-wurst-community-copy'},
        h('span',null,'DEIN POTT. DEINE BUDE.'),
        h('h2',null,'Deine Stammbude fehlt?'),
        h('p',null,'Wir sortieren Systemgastro und unpassende Konzepte aus. Schick uns deinen Lieblings-Grill – veröffentlicht wird erst nach Prüfung.')
      ),
      action
    );
  }
  function WurstView({location,radius,onRadius,onLocation,onOpen}){
    const [state,setState]=useState({loading:true,error:'',items:[],verifiedTotal:0,radarTotal:0});
    const [filter,setFilter]=useState('all');
    const [sort,setSort]=useState('distance');
    const [query,setQuery]=useState('');
    const [visible,setVisible]=useState(18);

    useEffect(function(){
      let live=true;
      setState(s=>Object.assign({},s,{loading:true,error:''}));
      RPG.api.wurst(location,radius,600).then(data=>{
        if(!live)return;
        const items=data.elements.map(x=>{const item=RPG.normalizePlace(x,'wurst',location);item.labels=wurstMenuItems(item).map(v=>v.charAt(0).toUpperCase()+v.slice(1));item.subtitle='Imbissbude';return item;});
        setState({loading:false,error:'',items,verifiedTotal:Number(data.verifiedTotal||0),radarTotal:Number(data.radarTotal||data.verifiedTotal||items.length)});
        setVisible(18);
      }).catch(e=>{
        if(live)setState({loading:false,error:e.message||'Imbissbuden konnten nicht geladen werden.',items:[],verifiedTotal:0,radarTotal:0});
      });
      return()=>{live=false;};
    },[location&&location.lat,location&&location.lng,radius]);

    const available=new Set();
    state.items.forEach(x=>wurstMenuItems(x).forEach(v=>available.add(v)));
    const items=useMemo(()=>{
      let out=state.items.filter(x=>filter==='all'||wurstMenuItems(x).includes(filter));
      const q=query.trim().toLocaleLowerCase('de');
      if(q)out=out.filter(x=>[x.title,x.city,x.address].concat(wurstMenuItems(x)).filter(Boolean).join(' ').toLocaleLowerCase('de').includes(q));
      if(sort==='new'){
        out=out.slice().sort((a,b)=>String((b.tags||{})['rg:wurst_first_seen']||'').localeCompare(String((a.tags||{})['rg:wurst_first_seen']||''))||((a.distanceKm??999)-(b.distanceKm??999)));
      }else{
        out=out.slice().sort((a,b)=>(a.distanceKm??999)-(b.distanceKm??999));
      }
      return out;
    },[state.items,filter,sort,query]);
    const radiusSteps=[1,2.5,5,10,25,50];

    const hero=h('section',{className:'rpgx-wurst-hero'},
      h('div',{className:'rpgx-wurst-texture'}),
      h('div',{className:'rpgx-wurst-hero-inner'},
        h('span',{className:'rpgx-wurst-kicker'},'ECHTE IMBISSKULTUR'),
        h('h1',null,'HIER GEHT’S UM DIE ',h('em',null,'WURST')),
        h('p',null,'Currywurst, Pommes und ehrliche Imbisskultur im Pott. Keine Systemgastro. Sondern die Buden, wegen denen man noch ’nen Umweg fährt.'),
        h('div',{className:'rpgx-wurst-stats'},
          state.verifiedTotal?h('span',null,h('strong',null,state.verifiedTotal),' redaktionell geprüft (gesamt)'):null,
          h('span',null,state.radarTotal?h(RPG.Fragment,null,h('strong',null,state.radarTotal),' Kandidaten im gesamten Radar'):'Kandidaten im gesamten Radar')
        ),
        h('div',{className:'rpgx-wurst-location-card'},
          h('div',null,h('span',null,'📍'),h('div',null,h('small',null,'Mein Standort'),h('strong',null,RPG.locationLabel(location)))),
          h('button',{type:'button',onClick:onLocation},'Standort ändern')
        ),
        h('div',{className:'rpgx-wurst-radius'},
          h('small',null,'Radius'),
          h('div',null,radiusSteps.map(x=>h('button',{key:x,type:'button',className:Number(radius)===x?'active':'',onClick:()=>onRadius(x)},String(x).replace('.',',')+' km')))
        )
      )
    );

    const toolbar=h('section',{className:'rpgx-wurst-toolbar'},
      h('div',{className:'rpgx-wurst-search-wrap'},h('input',{id:'rpgx-wurst-search',type:'search',value:query,placeholder:'Bude, Stadt oder Spezialität suchen …',onChange:e=>{setQuery(e.target.value);setVisible(18);},'aria-label':'Imbissbuden durchsuchen'})),
      h('div',{className:'rpgx-wurst-pills'},
        h('button',{type:'button',className:filter==='all'?'active':'',onClick:()=>setFilter('all')},'Alle'),
        WURST_FILTERS.filter(x=>available.has(x[0])).map(x=>h('button',{key:x[0],type:'button',className:filter===x[0]?'active':'',onClick:()=>setFilter(x[0])},h('span',null,x[1]),x[2]))
      ),
      h('label',{className:'rpgx-wurst-sort'},
        h('span',null,'Sortierung'),
        h('select',{value:sort,onChange:e=>setSort(e.target.value)},
          h('option',{value:'distance'},'Entfernung'),
          h('option',{value:'new'},'Neu im Radar')
        )
      )
    );

    let body;
    if(state.loading){
      body=h('div',{className:'rpgx-loading'},'Imbissbuden werden gesucht …');
    }else if(state.error){
      body=h('div',{className:'rpgx-error'},state.error);
    }else{
      body=h(RPG.Fragment,null,
        h('section',{className:'rpgx-wurst-results'},
          h('div',{className:'rpgx-wurst-results-head'},
            h('div',null,h('span',null,'DEINE AUSWAHL'),h('h2',null,items.length+' Treffer im aktuellen Umkreis')),
            h('p',null,RPG.locationLabel(location)+' · '+String(radius).replace('.',',')+' km')
          ),
          items.length
            ? h('div',{className:'rpgx-wurst-grid'},items.slice(0,visible).map(item=>h(WurstCard,{key:item.id,item,onOpen})))
            : h('div',{className:'rpgx-smart-empty'},
                h('strong',null,filter==='all'?'In diesem Radius ist noch keine passende Imbissbude im Radar.':'Für diesen Filter ist im aktuellen Radius noch nichts im Radar.'),
                h('p',null,'Erweitere den Radius oder setze den Filter zurück.')
              ),
          visible<items.length?h('button',{type:'button',className:'rpgx-wurst-more',onClick:()=>setVisible(v=>v+18)},'Mehr Buden laden'):null
        ),
        h(WurstSubmit)
      );
    }
    return h('main',{className:'rpgx-main rpgx-wurst-page'},hero,toolbar,body);
  }


  function RouteFallbackCard({item,onOpen}){
    const [resolved,setResolved]=useState(String(item.fallbackImage||''));
    useEffect(function(){
      let live=true;
      const preset=String(item.fallbackImage||'');
      setResolved(preset);
      if(!preset){
        const lat=Number(item.fallbackLat),lng=Number(item.fallbackLng);
        const pseudo={id:'rdi:'+item.slug,category:'highlights',title:item.title,city:item.city||'',subtitle:item.typeLabel||'',imageUrl:'',website:'',address:'',tags:{},labels:['Route Industriekultur',item.typeLabel||''],coords:Number.isFinite(lat)&&Number.isFinite(lng)?{lat,lng}:null};
        RPG.resolveItemMedia(pseudo).then(url=>{if(live&&url)setResolved(url);}).catch(()=>{});
      }
      return()=>{live=false;};
    },[item&&item.slug,item&&item.fallbackImage]);
    const image=String(resolved||'');
    const distance=Number(item.distanceKm);
    const lat=Number(item.fallbackLat),lng=Number(item.fallbackLng);
    const detailItem={
      kind:'place',id:'rdi:'+item.slug,category:'highlights',title:item.title,subtitle:'',city:item.city||'',address:'',
      coords:Number.isFinite(lat)&&Number.isFinite(lng)?{lat,lng}:null,distanceKm:Number.isFinite(distance)?distance:null,
      tags:{},labels:[],description:'',website:'',menuUrl:'',reservationUrl:'',openingHours:'',phone:'',
      imageUrl:image,galleryImages:image?[{url:image,credit:String(item.fallbackCredit||''),artist:'',source:image===String(item.fallbackImage||'')?'Wikimedia Commons':'PottFuchs Medienresolver',license:String(item.fallbackLicense||'')}]:[],
      routeIndustrial:true,routeType:item.type||'',routeTitle:item.title,routeBadge:item.typeLabel||'Primärstandort',routeOfficialUrl:String(item.officialUrl||'')
    };
    return h('button',{type:'button',className:'rpgx-card rpgx-route-fallback-card',onClick:()=>onOpen(detailItem),'aria-label':item.title+' öffnen'},
      h('div',{className:'rpgx-card-media'},
        image?h('img',{src:image,alt:'',loading:'lazy',decoding:'async',referrerPolicy:'no-referrer',onError:e=>{e.currentTarget.style.display='none';const f=e.currentTarget.nextSibling;if(f)f.style.display='grid';}}):null,
        h('div',{className:'rpgx-image-fallback','aria-hidden':'true',style:image?{display:'none'}:null},'🏭'),
        h('span',{className:'rpgx-route-type-badge'},item.typeLabel||'Primärstandort'),
        image&&item.fallbackCredit&&image===String(item.fallbackImage||'')?h('span',{className:'rpgx-route-image-credit'},item.fallbackCredit+(item.fallbackLicense?' · '+item.fallbackLicense:'')):null
      ),
      h('div',{className:'rpgx-card-overlay'},
        h('h3',null,item.title),
        h('div',{className:'rpgx-card-meta'},
          item.city?h('span',{className:'rpgx-card-city'},'📍 '+item.city):null,
          Number.isFinite(distance)?h('span',null,RPG.formatDistance(distance)):null
        )
      )
    );
  }

  function RouteView({location,onOpen}){
    const [state,setState]=useState({loading:true,items:[],error:''}),[type,setType]=useState('all'),[city,setCity]=useState('');
    useEffect(function(){let live=true;RPG.api.industrialRoute(location).then(items=>{if(live)setState({loading:false,items,error:''});}).catch(e=>{if(live)setState({loading:false,items:[],error:e.message});});return()=>{live=false;};},[location&&location.lat,location&&location.lng]);
    const cities=Array.from(new Set(state.items.map(x=>x.city).filter(Boolean))).sort((a,b)=>a.localeCompare(b,'de'));
    const items=state.items.filter(x=>(type==='all'||x.type===type)&&(!city||x.city===city));
    return h('main',{className:'rpgx-main'},h('section',{className:'rpgx-route-head'},h('h1',null,'Route der Industriekultur'),h('p',null,'57 offizielle Primärstandorte quer durchs Ruhrgebiet. Gematchte Orte nach Entfernung sortiert.')),
      h('div',{className:'rpgx-route-controls'},h('select',{value:type,onChange:e=>setType(e.target.value)},h('option',{value:'all'},'Alle 57'),h('option',{value:'anchor'},'Ankerpunkte'),h('option',{value:'panorama'},'Panoramen'),h('option',{value:'settlement'},'Siedlungen')),h('select',{value:city,onChange:e=>setCity(e.target.value)},h('option',{value:''},'Alle Städte'),cities.map(c=>h('option',{key:c,value:c},c)))),
      state.loading?h('div',{className:'rpgx-loading'},'Route wird geladen …'):state.error?h('div',{className:'rpgx-error'},state.error):h('div',{className:'rpgx-grid'},items.map(x=>{
        if(!x.element)return h(RouteFallbackCard,{key:x.slug,item:x,onOpen});
        const normalized=RPG.normalizePlace(x.element,'highlights',location);
        const apiDistance=Number(x.distanceKm);
        if(Number.isFinite(apiDistance)) normalized.distanceKm=apiDistance;
        if(x.fallbackImage&&(x.forceFallbackImage||!normalized.imageUrl)){
          normalized.imageUrl=String(x.fallbackImage);
          const fallbackGallery=[{url:String(x.fallbackImage),credit:String(x.fallbackCredit||''),source:'Wikimedia Commons',license:String(x.fallbackLicense||'')}];
          normalized.galleryImages=x.forceFallbackImage?fallbackGallery:fallbackGallery.concat(Array.isArray(normalized.galleryImages)?normalized.galleryImages.filter(g=>g&&g.url!==String(x.fallbackImage)):[]);
        }
        Object.assign(normalized,{title:x.title,subtitle:'',city:x.city,routeIndustrial:true,routeType:x.type,routeTitle:x.title,routeBadge:x.typeLabel||'Primärstandort',routeOfficialUrl:String(x.officialUrl||'')});
        return h(C.Card,{key:x.slug,item:normalized,onOpen});
      })));
  }

  const SEARCH_GROUPS=[
    ['events','Events'],['food','Food'],['highlights','Highlights'],['culture','Kultur'],['activities','Aktiv'],
    ['buedchen','Büdchen-Radar'],['wurst','Hier geht’s um die Wurst'],['specials','Specials'],['articles','Beiträge']
  ];
  function SearchView({location,onOpen}){
    const params=new URLSearchParams(window.location.search), initial=params.get('q')||'';
    const [query,setQuery]=useState(initial),[executedQuery,setExecutedQuery]=useState(initial),[state,setState]=useState({loading:!!initial,error:'',groups:{},total:0});
    const run=q=>{
      q=String(q||'').trim(); if(q.length<2){setState({loading:false,error:'Bitte mindestens 2 Zeichen eingeben.',groups:{},total:0});return;}
      setExecutedQuery(q);setState(x=>Object.assign({},x,{loading:true,error:''}));
      RPG.api.post(RPG.settings.globalSearchUrl||((RPG.settings.root||'/wp-json/')+'ruhrpottguru/v1/global-search'),{q,limit:30})
        .then(d=>setState({loading:false,error:'',groups:d.groups||{},total:Number(d.total)||0}))
        .catch(e=>setState({loading:false,error:e.message||'Suche fehlgeschlagen.',groups:{},total:0}));
    };
    useEffect(()=>{if(initial)run(initial);},[]);
    const submit=e=>{e.preventDefault();const q=query.trim();const base=RPG.internalNavUrl?RPG.internalNavUrl(RPG.settings.globalSearchPageUrl||'/suche/'):(RPG.settings.globalSearchPageUrl||'/suche/');const u=new URL(base,window.location.origin);u.searchParams.set('q',q);history.replaceState(null,'',RPG.internalNavUrl?RPG.internalNavUrl(u.toString()):u.toString());run(q);};
    // V311: Search Places carry the exact visitor `element` produced by the same
    // backend rows_to_elements() path as category pages/direct details. Normalize
    // that element here instead of rebuilding a reduced Place DTO. This keeps
    // image/gallery/crop, visitor category, Route metadata, address and detail
    // chips identical no matter whether the Place was opened from search or from
    // Highlights/Kultur/Aktiv/Food. Older backend payloads keep the compatibility
    // fallback below.
    const placeFromResult=r=>{
      const category=r.category==='wurst'||r.category==='buedchen'?'food':r.category;
      if(r&&r.element&&typeof r.element==='object'){
        const normalized=RPG.normalizePlace(r.element,category,location);
        if(normalized){
          if(location&&normalized.coords)normalized.distanceKm=RPG.distanceKm(location.lat,location.lng,normalized.coords.lat,normalized.coords.lng);
          return normalized;
        }
      }
      return {kind:'place',id:r.id,category,title:r.title,subtitle:r.subtitle||'',city:r.city||'',address:r.address||'',coords:Number.isFinite(Number(r.lat))&&Number.isFinite(Number(r.lng))?{lat:Number(r.lat),lng:Number(r.lng)}:null,distanceKm:location&&Number.isFinite(Number(r.lat))&&Number.isFinite(Number(r.lng))?RPG.distanceKm(location.lat,location.lng,Number(r.lat),Number(r.lng)):null,website:r.website||'',openingHours:r.openingHours||'',phone:r.phone||'',imageUrl:r.imageUrl||'',imageCropCard:r.imageCropCard||null,imageCropDetail:r.imageCropDetail||null,raw:r};
    };
    const detailFor=r=>{
      if(r.kind==='event') return {kind:'event',id:r.id,category:'events',title:r.title,subtitle:r.subtitle||'',city:r.city||'',address:r.address||'',coords:Number.isFinite(Number(r.lat))&&Number.isFinite(Number(r.lng))?{lat:Number(r.lat),lng:Number(r.lng)}:null,distanceKm:location&&Number.isFinite(Number(r.lat))&&Number.isFinite(Number(r.lng))?RPG.distanceKm(location.lat,location.lng,Number(r.lat),Number(r.lng)):null,start:r.start?new Date(String(r.start).replace(' ','T')):null,end:r.end?new Date(String(r.end).replace(' ','T')):null,allDay:!!r.allDay,allDayKnown:Object.prototype.hasOwnProperty.call(r,'allDay'),description:r.description||'',website:r.website||'',ticketUrl:r.ticketUrl||'',canonicalUrl:r.canonicalUrl||'',documents:Array.isArray(r.documents)?r.documents:[],programUrl:r.programUrl||'',visitorHours:r.visitorHours||'',dayHours:r.dayHours||'',imageUrl:r.imageUrl||'',imageCropCard:r.imageCropCard||null,imageCropDetail:r.imageCropDetail||null,priceText:r.priceText||'',prices:r.prices&&typeof r.prices==='object'?r.prices:{},organizers:Array.isArray(r.organizers)?r.organizers:[],eventInfo:r.eventInfo&&typeof r.eventInfo==='object'?r.eventInfo:{},participants:Array.isArray(r.participants)?r.participants:[],sourceName:r.sourceName||'',sources:Array.isArray(r.sources)?r.sources:[],sourceAttribution:r.sourceAttribution||'',sourceLicense:r.sourceLicense||'',sourceTermsUrl:r.sourceTermsUrl||'',sourcePageUrl:r.sourcePageUrl||'',raw:{rpg_event_db:true}};
      return placeFromResult(r);
    };
    const resultCard=(r,key)=>{
      const view=r.kind==='place'?placeFromResult(r):r;
      const imageUrl=view.imageUrl||'';
      const crop=view.imageCropCard||null;
      const mediaNode=r.kind==='place'
        // V312: exact parity with category cards. Search used to inspect only the
        // persisted imageUrl, while C.Card/C.Media also runs the bounded identity
        // resolver when a Place has no stored image. Reuse that same component.
        ?h(C.Media,{item:view})
        :(imageUrl?h('img',{src:imageUrl,alt:'',loading:'lazy',referrerPolicy:'no-referrer',style:crop&&['auto','manual'].includes(String(crop.mode||''))?{objectPosition:(Number(crop.x??50))+'% '+(Number(crop.y??50))+'%',transformOrigin:(Number(crop.x??50))+'% '+(Number(crop.y??50))+'%',objectFit:Number(crop.zoom)<1?'contain':'cover'}:null,onLoad:e=>{if(RPG.applyMediaCropElement)RPG.applyMediaCropElement(e.currentTarget,crop);}}):h('div',{className:'rpgx-search-result-fallback','aria-hidden':'true'},'📅'));
      return h('article',{className:'rpgx-search-result',key},
        mediaNode,
        h('div',{className:'rpgx-search-result-copy'},h('h3',null,view.title||r.title),h('p',null,[view.subtitle||r.subtitle,view.city||r.city].filter(Boolean).join(' · ')),r.kind==='event'&&r.start?h('small',null,new Date(String(r.start).replace(' ','T')).toLocaleString('de-DE',r.allDay?{dateStyle:'medium'}:{dateStyle:'medium',timeStyle:'short'})):null),
        r.url?h('a',{href:RPG.internalNavUrl?RPG.internalNavUrl(r.url):r.url,className:'rpgx-search-result-cta'},'Öffnen →'):h('button',{type:'button',className:'rpgx-search-result-cta',onClick:()=>onOpen(detailFor(r))},'Details →')
      );
    };
    return h('main',{className:'rpgx-main rpgx-search-page'},
      h('section',{className:'rpgx-search-hero'},h('span',{className:'rpgx-search-kicker'},'ALLES IM REVIER'),h('h1',null,'Suche'),h('p',null,'Durchsuche Events, Orte, Kategorien, Specials, Künstler und Beiträge im gesamten Ruhrgebiet.'),
        h('form',{className:'rpgx-search-page-form',onSubmit:submit},h('input',{type:'search',value:query,onChange:e=>setQuery(e.target.value),placeholder:'z. B. Lisa Feller, Zollverein, Currywurst …',autoFocus:true}),h('button',{type:'submit'},'Suchen'))),
      state.loading?h('div',{className:'rpgx-loading'},'PottFuchs durchsucht das Revier …'):state.error?h('div',{className:'rpgx-error'},state.error):executedQuery&&state.total===0?h('div',{className:'rpgx-smart-empty'},h('strong',null,'Keine Treffer gefunden.'),h('p',null,'Versuche einen Namen, Ort, Künstler oder ein Stichwort.')):h('div',{className:'rpgx-search-groups'},
        executedQuery?h('div',{className:'rpgx-search-summary'},h('strong',null,state.total+' Treffer'),h('span',null,' für „'+executedQuery+'“')):null,
        SEARCH_GROUPS.map(([key,label])=>{const arr=state.groups[key]||[];if(!arr.length)return null;return h('section',{className:'rpgx-search-group',key},h('div',{className:'rpgx-search-group-head'},h('h2',null,label),h('span',null,arr.length)),h('div',{className:'rpgx-search-result-list'},arr.map((r,i)=>resultCard(r,key+':'+i))));})
      )
    );
  }

  // V170 – restored standalone Pott-Quiz. This is the original RuhrpottGuru
  // knowledge quiz moved into the source-owned React runtime so the WordPress
  // shortcode no longer depends on the retired legacy bundle.
  const POTT_QUIZ_QUESTIONS=[
    {id:1,category:'Bergbau & Tradition',question:'Was bedeutet der traditionelle Bergmannsgruß „Glückauf!“ im Ruhrgebiet?',options:['Wunsch für ein fröhliches Feierabendbier','Wunsch zum Öffnen neuer Erzgänge & gesundes Ausfahren aus dem Schacht','Hoffnung auf einen Sieg des Lieblings-Fußballvereins','Segen beim Betreten der Zeche am Morgen'],correctIndex:1,explanation:'„Glückauf!“ drückt den Wunsch der Bergleute aus, dass sich neue Erzgänge auftun („Glück auf!“) und sie nach der Schicht wieder gesund aus der Grube ausfahren.'},
    {id:2,category:'Industriekultur',question:'Welche Zeche in Essen gilt als „die schönste Zeche der Welt“ und ist UNESCO-Welterbe?',options:['Zeche Zollverein','Zeche Ewald','Zeche Nachtigall','Zeche Consolidation'],correctIndex:0,explanation:'Zeche Zollverein im Essener Norden ist ein herausragendes Denkmal der Industriekultur und seit 2001 UNESCO-Welterbe.'},
    {id:3,category:'Kult & Kiosk',question:'Was versteht man im Ruhrpott liebevoll unter einer „Trinkhalle“ oder einem „Büdchen“?',options:['Eine Halle zur Mineralwasser-Abfüllung','Das Nachbarschafts-Wohnzimmer für Gemischte Tüten, Kaltgetränke & Schnack','Eine überdachte Haltestelle der Straßenbahn','Eine kleine Brauerei im Hinterhof'],correctIndex:1,explanation:'Das Büdchen ist gelebte Revierkultur: Treffpunkt für Nachbarn, Gemischte Tüte, Kaltgetränk und den schnellen Schnack.'},
    {id:4,category:'Kult-Kulinaria',question:'Welche Kombination bezeichnet man im Ruhrpott als legendäre „Manta-Platte“?',options:['Fischbrötchen mit Zwiebeln & Essiggurke','Currywurst mit Pommes rot-weiß („Schranke“)','Schnitzel mit Bratkartoffeln','Döner Kebab mit Schafskäse'],correctIndex:1,explanation:'Die Manta-Platte besteht klassisch aus Currywurst und Pommes mit Ketchup und Mayonnaise – rot-weiß, also „Schranke“.'},
    {id:5,category:'Pott-Sprache',question:'Was meint jemand im Ruhrgebiet, wenn er sagt: „Mach nich so’n Eiertanz!“?',options:['Du sollst beim Tanzen nicht stolpern','Drucks nicht herum und komm direkt auf den Punkt!','Zerbrich keine frischen Ostereier','Lauf vorsichtiger über die Straße'],correctIndex:1,explanation:'Ein Musterbeispiel für Ruhrpott-Direktheit: nicht lange herumreden, sondern auf den Punkt kommen.'},
    {id:6,category:'Halden & Panoramen',question:'Was sind die begrünten Bergehalden des Ruhrgebiets heute größtenteils?',options:['Abgesperrte Mülldeponien ohne Zutritt','Beliebte Freizeit- & Panoramaziele mit Haldenkunst','Unterirdische U-Bahn-Depots','Private Firmengelände'],correctIndex:1,explanation:'Viele ehemalige Bergehalden des Bergbaus wurden zu Freizeit- und Landschaftsräumen mit Aussichtspunkten und Landmarken wie Tetraeder oder Tiger & Turtle.'},
    {id:7,category:'Pott-Sprache',question:'Woher stammt das typische Ruhrpott-Wort „Malochen“ für harte Arbeit?',options:['Aus dem Französischen','Aus dem Jiddischen beziehungsweise Rotwelschen','Aus dem Altgriechischen','Aus dem Englischen'],correctIndex:1,explanation:'„Malochen“ geht auf jiddische beziehungsweise rotwelsche Sprachwurzeln zurück und wurde im Deutschen zum Ausdruck für schwere Arbeit.'},
    {id:8,category:'Highlights & Kultur',question:'Welcher Gasometer im Ruhrgebiet ist 117 Meter hoch und heute für große Ausstellungen bekannt?',options:['Gasometer Oberhausen','Gasometer Dortmund','Gasometer Bochum','Gasometer Duisburg'],correctIndex:0,explanation:'Der Gasometer Oberhausen ist 117 Meter hoch und zählt zu den bekanntesten Ausstellungshallen und Industriedenkmälern des Ruhrgebiets.'},
    {id:9,category:'Bier & Brauerei',question:'Welche Bierart ist besonders eng mit traditionsreichen Ruhrgebietsmarken wie Stauder, Fiege oder Brinkhoff’s verbunden?',options:['Pils','Kölsch','Altbier','Weißbier'],correctIndex:0,explanation:'Pils prägt viele klassische Ruhrgebietsmarken und gehört seit Jahrzehnten zur Kneipen-, Büdchen- und Feierabendkultur des Reviers.'},
    {id:10,category:'Routen & Freizeit',question:'Wie lang ist die Route der Industriekultur ungefähr, die die großen Standorte im Ruhrgebiet verbindet?',options:['Ca. 50 km','Ca. 400 km','Ca. 1.500 km','Exakt 100 km'],correctIndex:1,explanation:'Die Route der Industriekultur erschließt auf rund 400 Kilometern Straßenverbindung die wichtigsten Zeugnisse der Industriegeschichte im Ruhrgebiet.'}
  ];
  const POTT_QUIZ_RANKS=[
    {min:80,title:'Echter Ruhrpott Guru',badge:'👑',description:'Stark! Du kennst Zechen, Halden, Pott-Sprache und Revierkultur richtig gut.'},
    {min:60,title:'Erfahrener Steiger',badge:'🕯️',description:'Starke Leistung. Im Revier macht dir so schnell keiner etwas vor.'},
    {min:35,title:'Wegbier-Genießer',badge:'🍺',description:'Du bist schon ziemlich gut auf Büdchen und Pott-Kultur eingeschworen.'},
    {min:0,title:'Gruben-Lehrling',badge:'⛏️',description:'Da geht noch was – aber jeder echte Guru hat mal am Schacht angefangen.'}
  ];

  function QuizView(){
    const [stage,setStage]=useState('intro');
    const [index,setIndex]=useState(0);
    const [selected,setSelected]=useState(null);
    const [locked,setLocked]=useState(false);
    const [score,setScore]=useState(0);
    const [copied,setCopied]=useState(false);
    const question=POTT_QUIZ_QUESTIONS[index];
    const highScore=Number(RPG.storage.get('ruhrpott_quiz_highscore')||0);
    const highRank=RPG.storage.get('ruhrpott_quiz_highrank')||'Gruben-Lehrling';
    const percentage=Math.round((score/POTT_QUIZ_QUESTIONS.length)*100);
    const rank=POTT_QUIZ_RANKS.find(x=>percentage>=x.min)||POTT_QUIZ_RANKS[POTT_QUIZ_RANKS.length-1];

    function startQuiz(){setStage('playing');setIndex(0);setSelected(null);setLocked(false);setScore(0);setCopied(false);}
    function checkAnswer(){if(selected===null||locked)return;setLocked(true);if(selected===question.correctIndex)setScore(x=>x+1);}
    function nextQuestion(){
      if(index+1<POTT_QUIZ_QUESTIONS.length){setIndex(x=>x+1);setSelected(null);setLocked(false);return;}
      const finalScore=score+(!locked&&selected===question.correctIndex?1:0);
      const pct=Math.round((finalScore/POTT_QUIZ_QUESTIONS.length)*100);
      const finalRank=POTT_QUIZ_RANKS.find(x=>pct>=x.min)||POTT_QUIZ_RANKS[POTT_QUIZ_RANKS.length-1];
      if(pct>highScore){RPG.storage.set('ruhrpott_quiz_highscore',String(pct));RPG.storage.set('ruhrpott_quiz_highrank',finalRank.title);}
      setStage('result');
    }
    function shareResult(){
      const text='🏆 Mein Pott-Quiz-Rang: '+rank.badge+' '+rank.title+' ('+percentage+' %). Teste dein Ruhrpott-Wissen auf PottFuchs!';
      if(navigator.share){navigator.share({title:'PottFuchs Pott-Quiz',text,url:window.location.href}).catch(()=>{});return;}
      if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(text+' '+window.location.href).then(()=>{setCopied(true);setTimeout(()=>setCopied(false),2500);}).catch(()=>{});}
    }

    const hero=h('section',{className:'rpgx-quiz-hero'},
      h('div',{className:'rpgx-quiz-hero-copy'},
        h('span',{className:'rpgx-quiz-kicker'},'POTT-WISSEN'),
        h('h1',null,'Das große ',h('em',null,'Ruhrgebiet-Quiz')),
        h('p',null,'Teste dein Wissen über Zechen, Welterbe, Pott-Sprache, Kult-Imbisse und Haldenkunst.')
      ),
      h('div',{className:'rpgx-quiz-crown','aria-hidden':'true'},'👑')
    );

    if(stage==='intro')return h('main',{className:'rpgx-main rpgx-quiz-page'},hero,
      h('section',{className:'rpgx-quiz-panel rpgx-quiz-intro'},
        h('h2',null,'Welcher Ruhrpott-Rang steckt in dir?'),
        h('p',null,'10 Fragen. Vier Antworten. Am Ende bekommst du deinen persönlichen Pott-Rang.'),
        h('div',{className:'rpgx-quiz-facts'},
          h('div',null,h('strong',null,'10'),h('span',null,'Fragen')),
          h('div',null,h('strong',null,'4'),h('span',null,'Ränge')),
          h('div',null,h('strong',null,highScore+' %'),h('span',null,'Highscore'))
        ),
        highScore>0?h('p',{className:'rpgx-quiz-highscore'},'Dein bisher bester Rang: ',h('strong',null,highRank)):null,
        h('button',{type:'button',className:'rpgx-quiz-primary',onClick:startQuiz},'Quiz starten →')
      )
    );

    if(stage==='result')return h('main',{className:'rpgx-main rpgx-quiz-page'},hero,
      h('section',{className:'rpgx-quiz-panel rpgx-quiz-result'},
        h('div',{className:'rpgx-quiz-result-badge','aria-hidden':'true'},rank.badge),
        h('span',{className:'rpgx-quiz-result-label'},'DEIN RUHRPOTT-RANG'),
        h('h2',null,rank.title),
        h('p',null,rank.description),
        h('div',{className:'rpgx-quiz-result-stats'},
          h('div',null,h('span',null,'Richtige Antworten'),h('strong',null,score+' / '+POTT_QUIZ_QUESTIONS.length)),
          h('div',null,h('span',null,'Trefferquote'),h('strong',null,percentage+' %'))
        ),
        h('div',{className:'rpgx-quiz-actions'},
          h('button',{type:'button',className:'rpgx-quiz-primary',onClick:startQuiz},'Nochmal spielen'),
          h('button',{type:'button',className:'rpgx-quiz-secondary',onClick:shareResult},copied?'Ergebnis kopiert ✓':'Ergebnis teilen')
        ),
        h('div',{className:'rpgx-quiz-discover'},
          h('span',null,'Danach weiter durchs Revier:'),
          h('div',null,
            h('a',{href:'/veranstaltungen/'},'Events'),h('a',{href:'/gastronomie/'},'Food'),h('a',{href:'/highlights/'},'Highlights'),h('a',{href:'/kultur/'},'Kultur'),h('a',{href:'/aktivitaeten/'},'Aktiv')
          )
        )
      )
    );

    return h('main',{className:'rpgx-main rpgx-quiz-page'},hero,
      h('section',{className:'rpgx-quiz-panel rpgx-quiz-playing'},
        h('div',{className:'rpgx-quiz-meta'},
          h('span',null,question.category),
          h('strong',null,'Frage '+(index+1)+' von '+POTT_QUIZ_QUESTIONS.length)
        ),
        h('div',{className:'rpgx-quiz-progress'},h('span',{style:{width:(((index+1)/POTT_QUIZ_QUESTIONS.length)*100)+'%'}})),
        h('h2',null,question.question),
        h('div',{className:'rpgx-quiz-options'},question.options.map((option,i)=>{
          let cls='rpgx-quiz-option';
          if(!locked&&selected===i)cls+=' is-selected';
          if(locked&&i===question.correctIndex)cls+=' is-correct';
          if(locked&&selected===i&&i!==question.correctIndex)cls+=' is-wrong';
          if(locked&&i!==question.correctIndex&&selected!==i)cls+=' is-muted';
          return h('button',{type:'button',key:i,disabled:locked,className:cls,onClick:()=>!locked&&setSelected(i)},
            h('span',{className:'rpgx-quiz-letter'},String.fromCharCode(65+i)),
            h('span',{className:'rpgx-quiz-option-copy'},option),
            locked&&i===question.correctIndex?h('span',{className:'rpgx-quiz-mark'},'✓'):locked&&selected===i&&i!==question.correctIndex?h('span',{className:'rpgx-quiz-mark'},'×'):null
          );
        })),
        locked?h('div',{className:'rpgx-quiz-explanation '+(selected===question.correctIndex?'is-right':'is-near')},
          h('strong',null,selected===question.correctIndex?'Richtig! Revier-Wissen:':'Nicht ganz. Hintergrund:'),
          h('p',null,question.explanation)
        ):null,
        h('div',{className:'rpgx-quiz-bottom'},
          h('span',null,'Punktestand: ',h('strong',null,score),' / ',POTT_QUIZ_QUESTIONS.length),
          locked
            ?h('button',{type:'button',className:'rpgx-quiz-primary',onClick:nextQuestion},index+1<POTT_QUIZ_QUESTIONS.length?'Nächste Frage →':'Ergebnis anzeigen →')
            :h('button',{type:'button',className:'rpgx-quiz-primary',disabled:selected===null,onClick:checkAnswer},'Antwort einloggen')
        )
      )
    );
  }

  function halloweenFold(value){
    return String(value==null?'':value).normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('de').replace(/ß/g,'ss');
  }
  function halloweenFieldText(value){
    if(typeof value==='string'||typeof value==='number')return String(value);
    if(Array.isArray(value))return value.map(halloweenFieldText).filter(Boolean).join(' ');
    if(value&&typeof value==='object'){
      return ['name','title','label','value'].map(key=>halloweenFieldText(value[key])).filter(Boolean).join(' ');
    }
    return '';
  }
  function halloweenEventText(item){
    const raw=item&&item.raw&&typeof item.raw==='object'?item.raw:{};
    const fields=[item&&item.title,item&&item.eventType,item&&item.description,item&&item.shortDescription];
    ['keywords','categories','category','tags','type','name','description','shortDescription','teaser'].forEach(key=>{
      const value=halloweenFieldText(raw[key]);
      if(value)fields.push(value);
    });
    // V279: deliberately no JSON-wide raw fallback. That fallback made unrelated
    // provider/meta text eligible for the seasonal special. Only visitor-facing
    // event text and explicit taxonomy fields may qualify an event now.
    return halloweenFold(fields.filter(Boolean).join(' '));
  }
  function halloweenEventDateInWindow(item){
    const start=item&&item.start instanceof Date?item.start:new Date(item&&item.start||0);
    if(isNaN(start.getTime()))return false;
    const target=halloweenTargetDate(new Date());
    const year=target.getFullYear();
    const from=new Date(year,8,15,0,0,0,0);
    const through=new Date(year,10,1,23,59,59,999);
    return start>=from&&start<=through;
  }
  function halloweenExplicitSignal(text){
    const value=halloweenFold(text);
    return /\bhalloween\b|\bhallowe'en\b|\bhorror[a-z]*\b|\bgrusel[a-z]*\b|\bspuk[a-z]*\b|\bgeister[a-z]*\b|\bgespenst[a-z]*\b|\bzombie[a-z]*\b|\bvampir[a-z]*\b|\bdracula\b|\bfrankenstein\b|\bnosferatu\b|\bparanormal\b|\bhaunted\b|\bghost(?:s)?\b|\bdamon[a-z]*\b|\bhexennacht\b|\brocky\s+horror\b|\btrick\s*(?:or|oder|&)\s*treat\b|\bsuss(?:es)?\s+oder\s+saures\b|\bkurbis(?:fest|schnitz[a-z]*|leucht[a-z]*|nacht|markt)\b/.test(value);
  }
  function halloweenEventKind(item){
    const text=halloweenEventText(item);
    if(/\bfamil[a-z]*\b|\bkinder[a-z]*\b|\bkindertheater\b|\bkids\b|\bkurbis[a-z]*\b|\btrick\s*(?:or|oder|&)\s*treat\b|\bsuss(?:es)?\s+oder\s+saures\b/.test(text))return 'family';
    if(/\bparty\b|\bclub\b|\bdj\b|\bdisco\b|\btanz[a-z]*\b|\bnachtparty\b|\baftershow\b/.test(text))return 'party';
    if(/\bhorror[a-z]*\b|\bgrusel[a-z]*\b|\bspuk[a-z]*\b|\bgeister[a-z]*\b|\bgespenst[a-z]*\b|\bzombie[a-z]*\b|\bvampir[a-z]*\b|\bdracula\b|\bfrankenstein\b|\bnosferatu\b|\bparanormal\b|\bhaunted\b|\bghost(?:s)?\b|\bdamon[a-z]*\b|\brocky\s+horror\b/.test(text))return 'horror';
    return 'special';
  }
  function isHalloweenEvent(item){
    if(!halloweenEventDateInWindow(item))return false;
    return halloweenExplicitSignal(halloweenEventText(item));
  }
  function halloweenSeriesText(value){
    return halloweenFold(value).replace(/[^a-z0-9]+/g,'').trim();
  }
  function halloweenSeriesTitleKey(value){
    return halloweenFold(value)
      .replace(/\b20\d{2}\b/g,' ')
      .replace(/\b\d{1,2}[.\/-]\d{1,2}(?:[.\/-]\d{2,4})?\b/g,' ')
      .replace(/[^a-z0-9]+/g,' ')
      .replace(/\b(?:am|im|in|der|die|das|von|vom|zum|zur)\b/g,' ')
      .replace(/\s+/g,' ').trim();
  }
  function halloweenSeriesTokens(value){
    return halloweenSeriesTitleKey(value).split(' ').filter(token=>token.length>=3);
  }
  function halloweenVenueKey(item){
    return halloweenSeriesText((item&&item.subtitle)||(item&&item.address)||'');
  }
  function halloweenStartsNear(a,b){
    const at=a&&a.start instanceof Date?a.start.getTime():NaN;
    const bt=b&&b.start instanceof Date?b.start.getTime():NaN;
    return Number.isFinite(at)&&Number.isFinite(bt)&&Math.abs(at-bt)<=45*60*1000;
  }
  function halloweenOrganizerKey(item){
    const list=item&&Array.isArray(item.organizers)?item.organizers:[];
    return halloweenSeriesText(list.map(x=>typeof x==='string'?x:(x&&x.name)||'').filter(Boolean).join(' '));
  }
  function halloweenSameSeries(a,b){
    const ai=halloweenSeriesText(a&&a.series&&a.series.identity);
    const bi=halloweenSeriesText(b&&b.series&&b.series.identity);
    if(ai&&bi&&ai===bi)return true;
    const ac=halloweenSeriesText(a&&a.city),bc=halloweenSeriesText(b&&b.city);
    if(ac!==bc)return false;
    const at=halloweenSeriesText(a&&a.title),bt=halloweenSeriesText(b&&b.title);
    const av=halloweenVenueKey(a),bv=halloweenVenueKey(b);
    const sameVenue=!!(av&&bv&&(av===bv||((av.length>=8&&bv.length>=8)&&(av.includes(bv)||bv.includes(av)))));
    if(at===bt){
      if(sameVenue)return true;
      const ao=halloweenOrganizerKey(a),bo=halloweenOrganizerKey(b);
      return !!(ao&&bo&&ao===bo);
    }
    // V299: cross-source Halloween duplicates often differ only by a year suffix
    // or a venue word (e.g. "Halloween Horror Festival" vs "... 2026",
    // or editorial "Halloween-Party am 31.10.2026" vs provider title).
    // Collapse only when the venue is the same; near-identical title keys may be
    // recurring dates, while looser token overlap additionally requires the same
    // start time (±45 min) to avoid merging distinct parties at one location.
    if(sameVenue){
      const ak=halloweenSeriesTitleKey(a&&a.title),bk=halloweenSeriesTitleKey(b&&b.title);
      if(ak&&bk&&ak===bk)return true;
      if(halloweenStartsNear(a,b)){
        const aset=new Set(halloweenSeriesTokens(a&&a.title));
        const shared=halloweenSeriesTokens(b&&b.title).filter(token=>aset.has(token));
        if(shared.length>=2&&shared.some(token=>/^(?:halloween|horror|party|festival|grusel)$/.test(token)))return true;
      }
    }
    return false;
  }
  function halloweenCalendarDay(item){
    const start=item&&item.start instanceof Date?item.start:new Date(item&&item.start||0);
    if(isNaN(start.getTime()))return Number.POSITIVE_INFINITY;
    return new Date(start.getFullYear(),start.getMonth(),start.getDate()).getTime();
  }
  function halloweenSpecialCompare(a,b){
    // V332: Halloween is an event-catalogue subset, not a countdown ranking.
    // Upcoming dates first (16.10 precedes 31.10); within a calendar date
    // use the existing exact distanceKm and then the appointment start time.
    // City names never form groups; the established Halloween relevance,
    // source, radius, fallback and dedupe rules remain unchanged.
    const aday=halloweenCalendarDay(a),bday=halloweenCalendarDay(b);
    if(aday!==bday)return aday-bday;
    const ax=Number.isFinite(a&&a.distanceKm)&&a.distanceKm>=0?a.distanceKm:Number.POSITIVE_INFINITY;
    const bx=Number.isFinite(b&&b.distanceKm)&&b.distanceKm>=0?b.distanceKm:Number.POSITIVE_INFINITY;
    if(ax!==bx)return ax-bx;
    const at=a&&a.start instanceof Date?a.start.getTime():Number.POSITIVE_INFINITY;
    const bt=b&&b.start instanceof Date?b.start.getTime():Number.POSITIVE_INFINITY;
    if(at!==bt)return at-bt;
    const ai=String(a&&a.id||''),bi=String(b&&b.id||'');
    if(ai!==bi)return ai.localeCompare(bi,'de');
    return String(a&&a.title||'').localeCompare(String(b&&b.title||''),'de');
  }
  function dedupeHalloweenSeries(items){
    const sorted=(Array.isArray(items)?items:[]).slice().sort(halloweenSpecialCompare);
    const out=[];
    for(const item of sorted){
      if(out.some(existing=>halloweenSameSeries(existing,item)))continue;
      out.push(item);
    }
    return out;
  }
  function HalloweenView({location,radius,onRadius,onLocation,onOpen}){
    const s=useCategoryItems('events',location,radius,false,'halloween');
    const [filter,setFilter]=useState('all');
    useEffect(()=>{track('special_view',{special:'halloween',area:location.city||location.name||'',radius,placement:'special'});},[location&&location.lat,location&&location.lng,radius]);
    const matches=useMemo(()=>dedupeHalloweenSeries(s.items.filter(isHalloweenEvent)).map(item=>Object.assign({},item,{halloweenKind:halloweenEventKind(item)})),[s.items]);
    // V280: keep the Halloween fallback monotonic and identical in spirit to the
    // event radius expansion: 5 km is checked first, then 10 km, and only if
    // there are still no strict Halloween matches do we expand to 25 km.
    const fallbackRadius=!s.loading&&!s.error&&matches.length===0
      ?(Number(radius)===5?10:(Number(radius)===10?25:null))
      :null;
    useEffect(()=>{
      if(fallbackRadius!==null)onRadius(fallbackRadius);
    },[fallbackRadius,onRadius]);
    const visible=filter==='all'?matches:matches.filter(item=>item.halloweenKind===filter);
    const filterCounts=matches.reduce((out,item)=>{out[item.halloweenKind]=(out[item.halloweenKind]||0)+1;return out;},{});
    const radii=[1,2.5,5,10,25,50];
    const next=radii.find(v=>v>Number(radius));
    const filters=[['all','Alle'],['family','Familie'],['party','Party & Nacht'],['horror','Grusel & Horror']];
    return h('main',{className:'rpgx-main rpgx-halloween-page'},
      h('section',{className:'rpgx-halloween-hero'},
        h('div',{className:'rpgx-halloween-hero-copy'},
          h('span',{className:'rpgx-halloween-kicker'},halloweenCountdown(new Date())),
          h('h1',null,'Halloween im Pott'),
          h('p',null,'Gruselige Nächte, Halloween-Partys, Kürbis und Familien-Events im Ruhrgebiet – passend zu deinem Standort.')
        )
      ),
      h('section',{className:'rpgx-halloween-controls','aria-label':'Halloween filtern'},
        h('div',{className:'rpgx-halloween-radius'},h('strong',null,'Umkreis'),radii.map(v=>h('button',{key:v,type:'button',className:Number(radius)===v?'is-active':'',onClick:()=>onRadius(v)},String(v).replace('.',',')+' km'))),
        h('div',{className:'rpgx-halloween-filters'},filters.map(([key,label])=>h('button',{key,type:'button',className:filter===key?'is-active':'',onClick:()=>setFilter(key)},label+(key==='all'?'':filterCounts[key]?' · '+filterCounts[key]:''))))
      ),
      (s.loading||fallbackRadius!==null)?h(CategoryGridSkeleton):s.error?h('div',{className:'rpgx-error'},s.error):h(RPG.Fragment,null,
        h('div',{className:'rpgx-halloween-summary'},
          h('strong',null,matches.length+' Halloween-Tipp'+(matches.length===1?'':'s')),
          h('span',null,' im Umkreis von '+radius+' km')
        ),
        visible.length?h('div',{className:'rpgx-grid rpgx-halloween-grid'},visible.map(item=>h(C.Card,{key:item.id,item,onOpen:x=>{track('card_open',{category:'events',special:'halloween',area:location.city||location.name||'',radius,placement:'halloween',content_id:x.id});onOpen(x);}}))):
          h('div',{className:'rpgx-halloween-empty'},
            h('span',{'aria-hidden':'true'},'🎃'),
            h('strong',null,filter==='all'?'In diesem Umkreis haben wir noch keinen klaren Halloween-Treffer.':'Für diesen Halloween-Filter gibt es im aktuellen Umkreis noch keinen Treffer.'),
            h('p',null,filter==='all'?'Die Seite nutzt bereits die vorhandenen PottFuchs-Events und zeigt nur klar passende Halloween-Inhalte.':'Wähle „Alle“ oder einen größeren Umkreis.'),
            filter!=='all'?h('button',{type:'button',onClick:()=>setFilter('all')},'Alle Halloween-Tipps'):next?h('button',{type:'button',onClick:()=>onRadius(next)},'Radius auf '+String(next).replace('.',',')+' km erweitern'):null
          )
      )
      ,h(SpecialInfo,{
        title:'Halloween im Ruhrgebiet: gut zu wissen',
        intro:'Halloween am 31. Oktober: Familienfeste mit Kürbis und Kostümen, Partys und Nachtveranstaltungen sowie Grusel- und Horrorangebote im Ruhrgebiet. Die Termine stammen aus den PottFuchs-Veranstaltungsquellen und erscheinen hier, sobald sie dort stehen.',
        faqs:[
          {q:'Was ist an Halloween im Ruhrgebiet los?',a:'Rund um den 31. Oktober gibt es Kinder- und Familienfeste, Kostümpartys, Grusel- und Horrorshows und Führungen. Stelle oben Standort und Umkreis ein, dann siehst du die Termine in deiner Nähe.'},
          {q:'Wie aktuell sind die Termine?',a:'Die Termine werden automatisch aus unseren Veranstaltungsquellen übernommen. Prüfe Zeiten, Orte und Altersangaben vor dem Besuch beim Veranstalter.'}
        ],
        current:'halloween',
        seo:{name:'Halloween im Pott: Veranstaltungen im Ruhrgebiet',crumb:'Halloween im Pott',url:(RPG.settings&&RPG.settings.halloweenPageUrl)||window.location.href,items:visible}
      })
    );
  }


  // P0 Event detail landing: reuse the exact unmodified CategoryView candidate
  // hook (including event source gates, lifecycle, ordering and media handling).
  // This module ONLY selects up to four same-city neighbors from its results;
  // it never changes ranking, radius fallbacks or the public Events listing.
  // Christmas is a new isolated seasonal profile. Halloween and normal event
  // eligibility/ranking stay completely unchanged. Never infer a market from a
  // nearby concert mentioning its location in the description.
  const CHRISTMAS_CATEGORIES=[
    ['weihnachtsmaerkte','Weihnachtsmärkte'],
    ['kinder-familie','Kinder & Familie'],
    ['konzerte-musik','Konzerte & Musik'],
    ['advent-mitmachen','Advent & Mitmachen'],
    ['theater-shows','Theater & Shows'],
    ['lichter-ausfluege','Lichter & Ausflüge']
  ];
  function christmasFold(value){
    return String(value||'').toLowerCase().replace(/ä/g,'ae').replace(/ö/g,'oe').replace(/ü/g,'ue').replace(/ß/g,'ss')
      .normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/g,' ').trim();
  }
  function christmasDate(item){
    const value=item&&item.start;
    const d=value instanceof Date?value:new Date(value||0);
    return Number.isFinite(d.getTime())?d:null;
  }
  function christmasSeasonYear(now){
    const d=now instanceof Date?now:new Date(now||Date.now());
    return d.getMonth()===0?d.getFullYear()-1:d.getFullYear();
  }
  // Tage von jetzt bis 6. Januar (Ende der Weihnachtssaison); nur so weit muss die Eventquelle liefern.
  function christmasUntilDays(now){
    const d=now instanceof Date?now:new Date(now||Date.now());
    const end=new Date(christmasSeasonYear(d)+1,0,6,23,59,59,999);
    return Math.max(1,Math.ceil((end.getTime()-d.getTime())/86400000)+1);
  }
  function christmasSeasonEvent(item,now){
    const start=christmasDate(item);
    if(!start)return false;
    const year=christmasSeasonYear(now||new Date());
    return start>=new Date(year,10,1)&&start<=new Date(year+1,0,6,23,59,59,999);
  }
  function christmasCategories(item,now){
    if(!christmasSeasonEvent(item,now))return [];
    const raw=item&&item.raw&&typeof item.raw==='object'?item.raw:{};
    const title=christmasFold(item&&item.title);
    const taxonomy=christmasFold([raw.keywords,raw.tags,raw.categories,raw.category,item&&item.eventType]
      .filter(x=>typeof x==='string').join(' '));
    const description=christmasFold([item&&item.description,item&&item.shortDescription]
      .filter(x=>typeof x==='string').join(' '));
    const text=title+' '+taxonomy+' '+description;
    const seasonal=/\b(weihnacht[a-z]*|advent[a-z]*|nikolaus[a-z]*|christkind[a-z]*|christmas|xmas|winterleuchten|winterzauber)\b/;
    if(!seasonal.test(text))return [];
    const result=[];
    const market=/\b(weihnachtsmarkt|weihnachtsmaerkte|adventsmarkt|adventsmaerkte|christkindlmarkt|christkindlesmarkt|nikolausmarkt|weihnachtsdorf)\b/;
    const otherEventTitle=/\b(?:konzert|musik|chor|theater|musical|show|lesung|workshop|basteln|backen|(?:weihnachts|advents)(?:konzert|musik|theater|musical|show|maerchen))\b/;
    // Market evidence must describe the event, not merely its venue.
    if((market.test(title)&&!otherEventTitle.test(title))||(!otherEventTitle.test(title)&&market.test(taxonomy)))result.push('weihnachtsmaerkte');
    if(/\b(kinder|familie|familien|kids|nikolausbesuch|weihnachtsmannbesuch)\b/.test(title+' '+taxonomy)
       ||/\b(familienweihnacht|kinderweihnacht|familienadvent|nikolausfeier)\b/.test(text))result.push('kinder-familie');
    if(/\b(konzert|chor|musik|oratorium|kantate|carols|weihnachtslieder)\b/.test(title+' '+taxonomy)
       ||/\b(weihnachtskonzert|adventskonzert|weihnachtsoratorium|weihnachtsmusik)\b/.test(text))result.push('konzerte-musik');
    if(/\b(workshop|basteln|backen|mitmachen|werkstatt|adventskranz|kreativ)\b/.test(title+' '+taxonomy)
       ||/\b(adventswerkstatt|weihnachtswerkstatt|weihnachtsbasteln|weihnachtsbacken)\b/.test(text))result.push('advent-mitmachen');
    // A market's generic ET4 taxonomy (e.g. 'Bühne' or 'Theater') is not evidence
    // for an actual performance. Only the event title may opt a market into shows.
    const hasMarketIdentity=market.test(title);
    const theaterTitle=/\b(theater|maerchen|musical|show|nussknacker|buehne|ballett|theaterauffuehrung|weihnachtsmaerchen|weihnachtsshow|weihnachtstheater)\b/.test(title);
    const theaterTaxonomy=/\b(theater|maerchen|musical|show|nussknacker|buehne|ballett)\b/.test(taxonomy);
    if(theaterTitle||(!hasMarketIdentity&&theaterTaxonomy))result.push('theater-shows');
    if(/\b(lichter|lichterwelt|lichterpark|lichterglanz|illumination|winterleuchten|christmas garden)\b/.test(title+' '+taxonomy))result.push('lichter-ausfluege');
    return result;
  }
  function ChristmasView({location,radius,onRadius,onLocation,onOpen}){
    const s=useCategoryItems('events',location,radius,false,'weihnachten');
    const [filter,setFilter]=useState('weihnachtsmaerkte');
    const matches=useMemo(()=>s.items.map(item=>({item,categories:christmasCategories(item)})).filter(x=>x.categories.length),[s.items]);
    const visible=matches.filter(x=>x.categories.includes(filter)).map(x=>x.item);
    const counts=Object.fromEntries(CHRISTMAS_CATEGORIES.map(([key])=>[key,matches.filter(x=>x.categories.includes(key)).length]));
    const marketMatches=matches.filter(x=>x.categories.includes('weihnachtsmaerkte')).map(x=>x.item).sort((p,q)=>christmasDate(p)-christmasDate(q));
    const christmasLead=s.loading?'':(marketMatches.length
      ?'Aktuell zeigen wir '+marketMatches.length+(marketMatches.length===1?' Weihnachtsmarkt':' Weihnachtsmärkte')+' im Umkreis von '+radius+' km ('+RPG.locationLabel(location)+'). Der erste beginnt am '+specialDate(christmasDate(marketMatches[0]))+(marketMatches[0].city?' in '+marketMatches[0].city:'')+'.'
      :'Aktuell liegen für diesen Umkreis noch keine bestätigten Weihnachtsmärkte vor. Neue Termine erscheinen automatisch.');
    const radii=[1,2.5,5,10,25,50];
    useEffect(()=>{track('special_view',{special:'weihnachten',area:location.city||location.name||'',radius,placement:'special'});},[location&&location.lat,location&&location.lng,radius]);
    return h('main',{className:'rpgx-main rpgx-halloween-page rpgx-christmas-page'},
      h('section',{className:'rpgx-halloween-hero'},
        h('div',{className:'rpgx-halloween-hero-copy'},
          h('span',{className:'rpgx-halloween-kicker'},'Advent & Weihnachten im Ruhrgebiet'),
          h('h1',null,'Weihnachten im Pott'),
          h('p',null,'Entdecke Weihnachtsmärkte, Familienaktionen, Musik und weitere weihnachtliche Veranstaltungen in deiner Nähe.'))),
      h('section',{className:'rpgx-halloween-controls','aria-label':'Weihnachten filtern'},
        h('div',{className:'rpgx-halloween-radius'},
          h('strong',null,'Umkreis'),
          h('button',{type:'button',onClick:onLocation,className:'rpgx-christmas-place'},RPG.locationLabel(location)+' · Ändern'),
          radii.map(v=>h('button',{key:v,type:'button',className:Number(radius)===v?'is-active':'',onClick:()=>onRadius(v)},String(v).replace('.',',')+' km'))),
        h('div',{className:'rpgx-halloween-filters'},CHRISTMAS_CATEGORIES.map(([key,label])=>h('button',{
          key,type:'button',className:filter===key?'is-active':'',
          'aria-pressed':filter===key?'true':'false',onClick:()=>setFilter(key)
        },label+(counts[key]?' · '+counts[key]:''))))),
      s.loading?h(CategoryGridSkeleton):s.error?h('div',{className:'rpgx-error'},s.error):h(RPG.Fragment,null,
        h('div',{className:'rpgx-halloween-summary'},h('strong',null,visible.length+' '+(visible.length===1?'Treffer':'Treffer')),
          h('span',null,' · '+RPG.locationLabel(location)+' · '+radius+' km')),
        visible.length?h('div',{className:'rpgx-grid rpgx-halloween-grid'},visible.map(item=>h(C.Card,{key:item.id,item,onOpen:x=>{
          track('card_open',{category:'events',special:'weihnachten',area:location.city||location.name||'',radius,placement:'weihnachten',content_id:x.id});onOpen(x);
        }}))):h('div',{className:'rpgx-halloween-empty'},
          h('span',{'aria-hidden':'true'},'🎄'),
          h('strong',null,'Für diesen Weihnachtsfilter gibt es hier derzeit keine bestätigten Treffer.'),
          h('p',null,'Du kannst den Umkreis vergrößern oder einen anderen Weihnachtstipp auswählen.'))
      ),
      (function(){const tx=specialTexts('weihnachten',christmasLead);return tx?h(SpecialInfo,{
        title:tx.title,intro:tx.intro,faqs:tx.faqs,
        current:'weihnachten',
        seo:{name:'Weihnachten im Pott: Weihnachtsmärkte und Adventsveranstaltungen',crumb:'Weihnachten im Pott',url:(RPG.settings&&RPG.settings.weihnachtenPageUrl)||window.location.href,items:visible}
      }):null})()
    );
  }
  // Pure helpers are exposed read-only for isolated regression fixtures.
  RPG.ChristmasQA={christmasSeasonYear,christmasSeasonEvent,christmasCategories,filters:CHRISTMAS_CATEGORIES.map(x=>x[0])};

  // V352 – SEO/GEO building blocks shared by the seasonal specials (Christmas, Martin).
  // The text and the structured data are derived from the same matches that the
  // visitor sees, so there is no second list to maintain. JSON-LD only describes
  // visible content: an ItemList of the shown events and the visible FAQ.
  function specialDate(d){
    try{return d.toLocaleDateString('de-DE',{day:'numeric',month:'long'});}catch(_){return '';}
  }
  function specialAbs(href){
    const v=String(href||'').trim();
    if(!v||v==='#')return '';
    try{return new URL(v,window.location.origin).href;}catch(_){return '';}
  }
  function specialJsonLd(spec){
    const graph=[];
    const listed=(Array.isArray(spec.items)?spec.items:[]).map(item=>({item,url:specialAbs(RPG.buildShareUrl&&RPG.buildShareUrl(item))}))
      .filter(x=>x.url&&x.item&&x.item.title).slice(0,100);
    if(listed.length){
      graph.push({'@type':'ItemList',name:spec.name,numberOfItems:listed.length,
        itemListElement:listed.map((x,i)=>({'@type':'ListItem',position:i+1,url:x.url,name:String(x.item.title)}))});
    }
    if(Array.isArray(spec.faqs)&&spec.faqs.length){
      graph.push({'@type':'FAQPage',mainEntity:spec.faqs.map(f=>({'@type':'Question',name:f.q,acceptedAnswer:{'@type':'Answer',text:f.a}}))});
    }
    graph.push({'@type':'BreadcrumbList',itemListElement:[
      {'@type':'ListItem',position:1,name:'PottFuchs',item:specialAbs((RPG.settings&&RPG.settings.homeUrl)||'/')},
      {'@type':'ListItem',position:2,name:spec.crumb,item:specialAbs(spec.url||window.location.href)}]});
    return JSON.stringify({'@context':'https://schema.org','@graph':graph}).replace(/</g,'\\u003c');
  }
  // V353: Texte stehen nur in inc/special-ssr.php (PHP) und kommen per Einstellung hierher,
  // damit Seitencode und Anzeige dieselben Worte nutzen. {lead} = Satz aus den sichtbaren Treffern.
  function specialTexts(key,lead){
    const t=RPG.settings&&RPG.settings.specialTexts&&RPG.settings.specialTexts[key];
    if(!t||!Array.isArray(t.faqs))return null;
    return {title:t.title,intro:t.intro,faqs:t.faqs.map(f=>({q:f.q,a:String(f.a).split('{lead}').join(lead||'').trim()}))};
  }
  function SpecialInfo({title,intro,faqs,current,seo}){
    const s=RPG.settings||{};
    const links=[
      [s.martinPageUrl,'Martinszüge & Martinsfeste','martin'],
      [s.weihnachtenPageUrl,'Weihnachten im Pott','weihnachten'],
      [s.halloweenPageUrl,'Halloween im Pott','halloween'],
      [s.eventsPageUrl||'/veranstaltungen/','Alle Veranstaltungen im Pott','events']
    ].filter(x=>x[0]&&x[2]!==current);
    return h('section',{className:'rpgx-special-info','aria-label':title},
      h('h2',null,title),
      intro?h('p',null,intro):null,
      (faqs||[]).map((f,i)=>h(RPG.Fragment,{key:i},h('h3',null,f.q),h('p',null,f.a))),
      links.length?h('nav',{className:'rpgx-special-links','aria-label':'Weitere Themen im Pott'},
        h('strong',null,'Mehr im Pott: '),
        links.map(x=>h('a',{key:x[2],href:x[0]},x[1]))):null,
      h('script',{type:'application/ld+json',dangerouslySetInnerHTML:{__html:specialJsonLd(Object.assign({faqs},seo))}})
    );
  }

  // V352 – Seasonal special: Martinszüge & Martinsfeste. Isolated profile on the
  // existing event endpoint, cards and detail overlay. It has no radius control:
  // it reads a fixed Ruhrgebiet centre (50 km, the backend maximum) and adds the
  // coordinate-less city-scoped events of the cities the backend reports via
  // RPG.settings.cityScopeCities (e.g. approved municipal calendars). Normal
  // event eligibility, ranking, radius fallbacks and location logic stay unchanged.
  const MARTIN_CATEGORIES=[
    ['umzuege','Martinszüge & Laternenumzüge'],
    ['maerkte-kirmes','Märkte & Kirmes'],
    ['sonstiges','Weitere Martinsaktionen']
  ];
  function martinSeasonYear(now){
    const d=now instanceof Date?now:new Date(now||Date.now());
    return d.getMonth()===11?d.getFullYear()+1:d.getFullYear();
  }
  // Tage von jetzt bis Ende der Martin-Saison (30. November); nur so weit muss die Eventquelle liefern.
  function martinUntilDays(now){
    const d=now instanceof Date?now:new Date(now||Date.now());
    const end=new Date(martinSeasonYear(d),10,30,23,59,59,999);
    return Math.max(1,Math.ceil((end.getTime()-d.getTime())/86400000)+1);
  }
  function martinSeasonEvent(item,now){
    const start=christmasDate(item);
    if(!start)return false;
    const year=martinSeasonYear(now||new Date());
    return start>=new Date(year,9,1)&&start<=new Date(year,10,30,23,59,59,999);
  }
  function martinCategories(item,now){
    if(!martinSeasonEvent(item,now))return [];
    const title=christmasFold(item&&item.title);
    const raw=item&&item.raw&&typeof item.raw==='object'?item.raw:{};
    const taxonomy=christmasFold([raw.keywords,raw.tags,raw.categories,raw.category,item&&item.eventType]
      .filter(x=>typeof x==='string').join(' '));
    const description=christmasFold([item&&item.description,item&&item.shortDescription]
      .filter(x=>typeof x==='string').join(' '));
    // Title/taxonomy are the evidence. A venue or church called "St. Martin"
    // (concert, organ recital, tour) is not a Martin event.
    const notMartin=/(konzert|orgel|\bchor|lesung|kabarett|comedy|fuehrung|gottesdienst|ausstellung|\bkino|theater|luther)/;
    const marketTitle=/\b(martinikirmes|martinimarkt|martinsmarkt|martinsfest|martinifest|martinisonntag)\b/.test(title);
    const parade=/\b(martinszug|martinsumzug|martinszuege|martinsumzuege|laternenumzug|laternenzug|laternenlauf|lampionumzug|martinsfeuer|martinssingen|martinsfeier)\b/;
    const saint=/\b(st martin|st martins|sankt martin|martinus)\b/;
    const paradeTitle=parade.test(title)||(saint.test(title)&&!notMartin.test(title));
    const paradeText=!notMartin.test(title)&&parade.test(christmasFold(raw&&raw.keywords)+' '+description);
    const generic=/\b(martin(s|i)[a-z]*|laternen(fest|basteln)|martinsgans|martinsgaense)\b/.test(title)&&!notMartin.test(title);
    const result=[];
    if(paradeTitle||paradeText)result.push('umzuege');
    if(marketTitle)result.push('maerkte-kirmes');
    if(!result.length&&(generic||(taxonomy&&saint.test(taxonomy)&&!notMartin.test(title))))result.push('sonstiges');
    return result;
  }
  function martinHub(){
    const pair=(RPG.CITY_CENTERS&&RPG.CITY_CENTERS['Essen'])||[51.4556,7.0116];
    return {name:'Ruhrgebiet (Essen-Mitte)',city:'Essen',postcode:'',lat:Number(pair[0]),lng:Number(pair[1]),isGPS:false,locationMode:'default'};
  }
  function martinListed(item){
    const raw=item&&item.raw&&typeof item.raw==='object'?item.raw:{};
    return Object.assign({},item,{distanceKm:null,raw});
  }
  function MartinView({onOpen}){
    const hub=useMemo(martinHub,[]);
    const s=useCategoryItems('events',hub,50,false,'martin');
    const scopes=Array.isArray(RPG.settings&&RPG.settings.cityScopeCities)?RPG.settings.cityScopeCities:[];
    const scopeKey=scopes.join('|');
    const [extra,setExtra]=useState({loading:scopes.length>0,items:[]});
    const [filter,setFilter]=useState('alle');
    useEffect(()=>{
      let live=true;
      if(!scopes.length){setExtra({loading:false,items:[]});return undefined;}
      (async()=>{
        const out=[];
        const days=martinUntilDays(new Date());
        await Promise.all(scopes.map(async city=>{
          const pair=RPG.CITY_CENTERS&&RPG.CITY_CENTERS[city];
          if(!pair)return;
          const loc={name:city+' (Zentrum)',city,postcode:'',lat:Number(pair[0]),lng:Number(pair[1]),isGPS:false,locationMode:'manual'};
          try{
            const raw=await RPG.api.events(loc,1,days);
            raw.map(x=>RPG.normalizeEvent(x,loc)).filter(x=>x.cityScope===true&&x.start&&(!x.end||x.end.getTime()>=Date.now()-3600000)).forEach(x=>out.push(x));
          }catch(_){}
        }));
        if(live)setExtra({loading:false,items:out});
      })();
      return()=>{live=false;};
    },[scopeKey]);
    useEffect(()=>{track('special_view',{special:'martin',area:'Ruhrgebiet',radius:50,placement:'special'});},[]);
    const matches=useMemo(()=>{
      const seen=new Set();
      const merged=[];
      s.items.concat(extra.items).forEach(item=>{
        const cats=martinCategories(item);
        if(!cats.length)return;
        const id=String(item.id);
        if(seen.has(id))return;
        seen.add(id);
        merged.push({item:martinListed(item),categories:cats});
      });
      // The same Martin event on several days (e.g. a Kirmes) is one listing: the
      // next date of each title+city stays, the rest are the same event.
      const byEvent=new Map();
      merged.sort((a,b)=>christmasDate(a.item)-christmasDate(b.item)).forEach(x=>{
        const key=christmasFold(x.item.title)+'|'+christmasFold(x.item.city);
        if(!byEvent.has(key))byEvent.set(key,x);
      });
      return Array.from(byEvent.values());
    },[s.items,extra.items]);
    const counts=Object.fromEntries(MARTIN_CATEGORIES.map(([key])=>[key,matches.filter(x=>x.categories.includes(key)).length]));
    const visible=(filter==='alle'?matches:matches.filter(x=>x.categories.includes(filter))).map(x=>x.item);
    const loading=s.loading||extra.loading;
    const shownFilters=[['alle','Alle']].concat(MARTIN_CATEGORIES.filter(([key])=>counts[key]>0));
    const martinLead=loading?'':(matches.length
      ?'Aktuell listen wir '+matches.length+' Martinstermine im Ruhrgebiet, vom '+specialDate(christmasDate(matches[0].item))+' bis zum '+specialDate(christmasDate(matches[matches.length-1].item))+'.'
      :'Aktuell liegen uns noch keine Martinstermine vor. Neue Termine erscheinen automatisch, sobald sie in unseren Veranstaltungsquellen stehen.');
    return h('main',{className:'rpgx-main rpgx-halloween-page rpgx-christmas-page rpgx-martin-page'},
      h('section',{className:'rpgx-halloween-hero'},
        h('div',{className:'rpgx-halloween-hero-copy'},
          h('span',{className:'rpgx-halloween-kicker'},'Martinstag am 11. November'),
          h('h1',null,'Martinszüge & Martinsfeste im Pott'),
          h('p',null,'Alle Martinszüge, Martinsumzüge, Laternenumzüge, Martinsmärkte und die Martinikirmes, die uns im Ruhrgebiet vorliegen, auf einen Blick und nach Datum sortiert.'))),
      h('section',{className:'rpgx-halloween-controls','aria-label':'Martinstermine filtern'},
        h('div',{className:'rpgx-halloween-filters'},shownFilters.map(([key,label])=>h('button',{
          key,type:'button',className:filter===key?'is-active':'',
          'aria-pressed':filter===key?'true':'false',onClick:()=>setFilter(key)
        },label+(key==='alle'?(matches.length?' · '+matches.length:''):' · '+counts[key]))))),
      loading?h(CategoryGridSkeleton):s.error&&!matches.length?h('div',{className:'rpgx-error'},s.error):h(RPG.Fragment,null,
        h('div',{className:'rpgx-halloween-summary'},h('strong',null,visible.length+' '+(visible.length===1?'Termin':'Termine')),
          h('span',null,' · Ruhrgebiet')),
        visible.length?h('div',{className:'rpgx-grid rpgx-halloween-grid'},visible.map(item=>h(C.Card,{key:item.id,item,onOpen:x=>{
          track('card_open',{category:'events',special:'martin',area:x.city||'',radius:50,placement:'martin',content_id:x.id});onOpen(x);
        }}))):h('div',{className:'rpgx-halloween-empty'},
          h('span',{'aria-hidden':'true'},'🏮'),
          h('strong',null,'Für diesen Filter gibt es derzeit keine bestätigten Termine.'),
          h('p',null,'Neue Martinstermine erscheinen hier automatisch, sobald sie in unseren Veranstaltungsquellen stehen.'),
          filter!=='alle'?h('button',{type:'button',onClick:()=>setFilter('alle')},'Alle Martinstermine'):null)
      ),
      (function(){const tx=specialTexts('martin',martinLead);return tx?h(SpecialInfo,{
        title:tx.title,intro:tx.intro,faqs:tx.faqs,
        current:'martin',
        seo:{name:'Martinszüge und Martinsfeste im Ruhrgebiet',crumb:'Martinszüge & Martinsfeste',url:(RPG.settings&&RPG.settings.martinPageUrl)||window.location.href,items:visible}
      }):null})()
    );
  }
  RPG.MartinQA={martinSeasonYear,martinSeasonEvent,martinCategories,filters:MARTIN_CATEGORIES.map(x=>x[0])};

  function EventLandingMore({item}){
    const city=String(item&&item.city||'').trim();
    const slug=RPG.tagSlug(city);
    const matchCity=Object.keys(RPG.CITY_CENTERS||{}).find(x=>RPG.tagSlug(x)===slug);
    const pair=matchCity&&RPG.CITY_CENTERS[matchCity];
    // Don't pretend to have a verified city lookup if the city isn't registered.
    const location=pair?{city:matchCity,name:matchCity,lat:Number(pair[0]),lng:Number(pair[1])}:null;
    const results=useCategoryItems('events',location,5,false,'','');
    if(!location||!item||item.category!=='events')return null;
    const currentToken=typeof RPG.shareDescriptor==='function'?(RPG.shareDescriptor(item)||{}).token:'';
    const matches=(Array.isArray(results.items)?results.items:[]).filter(x=>{
      if(!x||RPG.tagSlug(x.city)!==slug)return false;
      const token=typeof RPG.shareDescriptor==='function'?(RPG.shareDescriptor(x)||{}).token:'';
      return token&&token!==currentToken;
    }).slice(0,4);
    if(results.loading||results.error||!matches.length)return null;
    const openUrl=x=>{const url=RPG.buildShareUrl(x);if(url)window.location.assign(url);};
    return h('section',{className:'rpgx-event-landing-more','aria-labelledby':'rpgx-event-landing-more-title'},
      h('div',{className:'rpgx-event-landing-more-head'},
        h('h2',{id:'rpgx-event-landing-more-title'},'Was ist sonst noch los in '+city+'?'),
        h('a',{href:(RPG.settings.homeUrl||'/').replace(/\/?$/,'/')+'veranstaltungen/'},'Alle Veranstaltungen →')
      ),
      h('div',{className:'rg-home-events-track'},matches.map(x=>h(C.LegacyOverlayCard,{key:x.id,item:x,onOpen:openUrl,className:'rg-home-event-mag-card'})))
    );
  }

  function PlainWpView(){const source=document.getElementById('ruhrpott-wp-page-content-source');return h('main',{className:'rpgx-main rpgx-wp-page',dangerouslySetInnerHTML:{__html:source?source.innerHTML:''}});}

  RPG.Views={HomeView,CategoryView,RouteView,BuedchenRadarView,BudeDetail,WurstView,SearchView,QuizView,HalloweenView,ChristmasView,MartinView,PlainWpView,EventLandingMore};
})(window);
