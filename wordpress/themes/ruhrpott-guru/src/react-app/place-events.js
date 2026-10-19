(function(window){
  'use strict';
  const RPG=window.RPGReact, h=RPG.h;
  const {useState,useEffect}=RPG.React;
  const norm=value=>RPG.tagSlug(value).replace(/-/g,' ');
  const identity=value=>norm(value).replace(/^(?:museum|museumsbereich|veranstaltungsort)\s+/,'').replace(/\s+(?:museum|museumsbereich)$/,'').trim();
  const validCoords=coords=>coords&&Number.isFinite(coords.lat)&&Number.isFinite(coords.lng)&&Math.abs(coords.lat)<=90&&Math.abs(coords.lng)<=180&&(coords.lat!==0||coords.lng!==0);
  RPG.placeEventAliases=function(item){
    if(!item)return [];
    const tags=item.tags||{}, names=[item.title];
    ['name','official_name','alt_name','short_name','loc_name'].forEach(key=>String(tags[key]||'').split(';').forEach(name=>names.push(name)));
    return Array.from(new Set(names.map(identity).filter(name=>name.length>=4&&!/^(?:unbenannter ort|unbekannter ort|museum|theater|kino|schloss|burg|kirche|park|location|veranstaltungsort)$/.test(name))));
  };
  RPG.eventMatchesPlace=function(item,event,now){
    if(!item||item.category==='events'||!event||event.category!=='events'||!validCoords(item.coords)||!validCoords(event.coords))return false;
    const end=event.end instanceof Date?event.end:event.start;
    if(!(event.start instanceof Date)||!Number.isFinite(event.start.getTime())||!(end instanceof Date)||!Number.isFinite(end.getTime())||end.getTime()<(now==null?Date.now():now))return false;
    if(item.city&&event.city&&norm(item.city)!==norm(event.city))return false;
    const distance=RPG.distanceKm(item.coords.lat,item.coords.lng,event.coords.lat,event.coords.lng);
    if(distance>0.15)return false;
    const venue=identity(event.subtitle),aliases=RPG.placeEventAliases(item);
    if(!venue)return false;
    if(aliases.includes(venue))return true;
    // A tightly coincident venue may use an extended official name (for example
    // "Stratmanns Theater im Europahaus"). Only allow containment below 30 m
    // so the existing neighbouring-venue protection remains intact.
    return distance<=0.03&&aliases.some(alias=>alias.length>=7&&venue.length>=7&&(venue.includes(alias)||alias.includes(venue)));
  };
  const cache=new Map();
  RPG.api.eventsForPlace=function(item){
    if(!item||item.category==='events'||!validCoords(item.coords)||!RPG.placeEventAliases(item).length)return Promise.resolve([]);
    const key=JSON.stringify([item.id,item.title,item.city,item.coords.lat,item.coords.lng,RPG.placeEventAliases(item)]);
    const hit=cache.get(key);
    if(hit&&hit.expires>Date.now())return hit.promise.then(events=>events.filter(event=>RPG.eventMatchesPlace(item,event)));
    const promise=RPG.api.events({lat:item.coords.lat,lng:item.coords.lng,city:item.city||''},1).then(raw=>{
      const events=raw.map(row=>RPG.normalizeEvent(row,null)).filter(event=>RPG.eventMatchesPlace(item,event));
      const seen=new Set();
      return events.filter(event=>{const id=event.raw&&event.raw.rpg_event_db&&event.raw.rpg_event_db.eventId;const key=String(id||event.id)+'|'+event.start.toISOString();if(seen.has(key))return false;seen.add(key);return true;}).sort((a,b)=>a.start-b.start);
    }).catch(error=>{cache.delete(key);throw error;});
    cache.set(key,{expires:Date.now()+120000,promise});
    if(cache.size>50)cache.delete(cache.keys().next().value);
    return promise;
  };
  function PlaceEvents({item,onNavigate}){
    const [state,setState]=useState({key:'',items:[],error:''});
    const [expanded,setExpanded]=useState(false),[opening,setOpening]=useState(''),[navError,setNavError]=useState('');
    const key=JSON.stringify([item&&item.id,item&&item.title,item&&item.city,item&&item.coords]);
    useEffect(()=>{
      let live=true;setExpanded(false);setOpening('');setNavError('');setState({key,items:[],error:''});
      RPG.api.eventsForPlace(item).then(items=>{if(live)setState({key,items,error:''});}).catch(()=>{if(live)setState({key,items:[],error:'Events konnten gerade nicht geladen werden.'});});
      return()=>{live=false;};
    },[key]);
    if(state.key!==key||(!state.items.length&&!state.error))return null;
    if(state.error)return h('p',{className:'text-xs text-slate-400',role:'status'},state.error);
    const items=expanded?state.items:state.items.slice(0,4);
    return h('section',{className:'rpgx-place-events p-4 rounded-2xl border border-slate-800 bg-slate-950/50','aria-label':'Events an diesem Ort'},
      h('h3',{className:'text-lg font-extrabold text-white mb-3'},'Events an diesem Ort'),
      h('div',{className:'space-y-2'},items.map(event=>{
        const id=Number(event.raw&&event.raw.rpg_event_db&&event.raw.rpg_event_db.eventId||0);
        const href=event.canonicalUrl||RPG.buildShareUrl(event);
        const dateOptions={day:'2-digit',month:'2-digit',year:'numeric',...(event.allDay?{timeZone:'UTC'}:{})};
        const when=(event.end&&event.start<Date.now()&&event.end>Date.now()?'Läuft bis '+event.end.toLocaleDateString('de-DE',dateOptions):event.start.toLocaleDateString('de-DE',dateOptions)+(event.allDay?'':' · '+event.start.toLocaleTimeString('de-DE',{hour:'2-digit',minute:'2-digit'})+' Uhr'));
        return h('a',{key:event.id+'|'+event.start.toISOString(),href,className:'block p-3 rounded-xl bg-slate-900 border border-slate-700 text-slate-100 hover:border-amber-400','aria-label':event.title+' – '+when,onClick:async e=>{
          if(e.button!==0||e.metaKey||e.ctrlKey||e.shiftKey||e.altKey||!id||typeof onNavigate!=='function')return;
          e.preventDefault();if(opening)return;setOpening(String(id));setNavError('');
          try{await onNavigate({eventId:id});}catch(_){setNavError('Das Event konnte gerade nicht geöffnet werden.');}finally{setOpening('');}
        }},h('strong',{className:'block'},event.title),h('span',{className:'block text-xs text-amber-300 mt-1'},when),h('span',{'aria-hidden':'true'},opening===String(id)?'Wird geöffnet …':'Event ansehen →'));
      })),
      state.items.length>4?h('button',{type:'button',className:'rpgx-detail-tag-filter mt-3',onClick:()=>setExpanded(v=>!v)},expanded?'Weniger anzeigen':'Alle '+state.items.length+' Events anzeigen'):null,
      navError?h('p',{className:'text-xs text-rose-300 mt-2',role:'status'},navError):null
    );
  }
  RPG.Components.PlaceEvents=PlaceEvents;
})(window);
