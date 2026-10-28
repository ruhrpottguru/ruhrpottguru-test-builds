(function (window) {
  'use strict';
  const RPG = window.RPGReact;
  const h = RPG.h;
  const { useState, useEffect, useMemo, useRef } = RPG.React;

  const div = (p,...c) => h('div',p,...c), span = (p,...c) => h('span',p,...c), button=(p,...c)=>h('button',p,...c), a=(p,...c)=>h('a',p,...c), p=(props,...c)=>h('p',props,...c), strong=(props,...c)=>h('strong',props,...c);
  const metric=(event_type,item,placement)=>{try{window.RPG_ANALYTICS&&window.RPG_ANALYTICS.send(event_type,{category:item&&item.category||'',area:item&&item.city||'',placement:placement||'detail',content_id:item&&item.id||''});}catch(_){}};

  function Header(props) {
    const loc = props.location;
    const [searchOpen,setSearchOpen]=useState(/\/suche\/?$/.test(window.location.pathname));
    const [searchValue,setSearchValue]=useState(()=>{try{return new URLSearchParams(window.location.search).get('q')||'';}catch(_){return '';}});
    const [weather,setWeather]=useState(null);
    const [headerHeight,setHeaderHeight]=useState(0);
    const searchRef=useRef(null);
    const headerRef=useRef(null);
    const openDrawer = () => { const d=document.getElementById('mobile-menu-drawer'); if(d){d.classList.remove('hidden');d.classList.add('flex');try{window.RPG_ANALYTICS&&window.RPG_ANALYTICS.send('burger_open',{placement:'burger-menu',content_id:'Burger-Menü'});}catch(_){}} };
    const logoNode=document.querySelector('#ruhrpott-wp-custom-logo-source img');
    const logo=logoNode&&logoNode.src?h('img',{className:'rpgx-brand-logo',src:logoNode.src,alt:logoNode.alt||'PottFuchs'}):span({className:'rpgx-brand-mark'},'P');
    const openSearch=()=>{setSearchOpen(true);setTimeout(()=>{if(searchRef.current)searchRef.current.focus();},30);};
    useEffect(function(){const fn=()=>openSearch();window.addEventListener('rpg:focus-search',fn);return()=>window.removeEventListener('rpg:focus-search',fn);},[]);
    useEffect(function(){
      const el=headerRef.current;
      if(!el)return;
      const adminBar=document.getElementById('wpadminbar');
      const syncAdminBarOffset=()=>{
        let offset=0;
        try{
          if(adminBar){
            const cs=window.getComputedStyle(adminBar);
            const rect=adminBar.getBoundingClientRect();
            const opacity=Number.parseFloat(cs.opacity);
            const visible=cs.display!=='none'&&cs.visibility!=='hidden'&&(!Number.isFinite(opacity)||opacity>0)&&rect.height>0&&rect.bottom>0&&rect.top<=1;
            if(visible)offset=Math.max(0,Math.ceil(rect.bottom));
          }
          document.documentElement.style.setProperty('--rpg-admin-bar-offset',offset+'px');
        }catch(_){
          try{document.documentElement.style.setProperty('--rpg-admin-bar-offset','0px');}catch(__){}
        }
        return offset;
      };
      const measure=()=>{
        syncAdminBarOffset();
        const rect=el.getBoundingClientRect();
        const next=Math.max(1,Math.ceil(rect.height||el.offsetHeight||0));
        setHeaderHeight(prev=>prev===next?prev:next);
        // Keep the actual fixed-header geometry available to desktop overlays.
        try{
          document.documentElement.style.setProperty('--rpg-fixed-header-height',next+'px');
          document.documentElement.style.setProperty('--rpg-fixed-header-bottom',Math.max(0,Math.ceil(rect.bottom))+'px');
        }catch(_){}
      };
      measure();
      let ro=null,adminRo=null;
      if(typeof ResizeObserver==='function'){
        ro=new ResizeObserver(measure);ro.observe(el);
        if(adminBar){adminRo=new ResizeObserver(measure);adminRo.observe(adminBar);}
      }
      window.addEventListener('resize',measure,{passive:true});
      window.addEventListener('orientationchange',measure,{passive:true});
      return()=>{if(ro)ro.disconnect();if(adminRo)adminRo.disconnect();window.removeEventListener('resize',measure);window.removeEventListener('orientationchange',measure);};
    },[]);
    useEffect(function(){
      if(!loc||!Number.isFinite(Number(loc.lat))||!Number.isFinite(Number(loc.lng)))return;
      const lat=Number(loc.lat),lng=Number(loc.lng);
      const city=String(loc.city||loc.name||'').trim().toLowerCase();
      const locationKey=[city,lat.toFixed(3),lng.toFixed(3)].join('|');
      const cacheKey='ruhrpottguru_header_weather_v2_'+locationKey;
      const maxAge=15*60*1000;
      // Never leave the previous location's weather visible while the new place loads.
      setWeather(null);
      try{
        const cached=JSON.parse(sessionStorage.getItem(cacheKey)||'null');
        if(cached&&cached.locationKey===locationKey&&Date.now()-Number(cached.ts||0)<maxAge){setWeather(cached);return;}
      }catch(_){}
      let cancelled=false;
      const url='https://api.open-meteo.com/v1/forecast?latitude='+encodeURIComponent(lat)+'&longitude='+encodeURIComponent(lng)+'&current=temperature_2m,weather_code&timezone=auto';
      fetch(url,{method:'GET',credentials:'omit'}).then(r=>{if(!r.ok)throw new Error('weather '+r.status);return r.json();}).then(data=>{
        if(cancelled)return;
        const current=data&&data.current?data.current:null;
        if(!current||!Number.isFinite(Number(current.temperature_2m)))throw new Error('weather payload');
        const next={locationKey,lat,lng,ts:Date.now(),temp:Math.round(Number(current.temperature_2m)),code:Number(current.weather_code)};
        setWeather(next);
        try{sessionStorage.setItem(cacheKey,JSON.stringify(next));}catch(_){}
      }).catch(()=>{if(!cancelled)setWeather({locationKey,error:true});});
      return()=>{cancelled=true;};
    },[loc&&loc.city,loc&&loc.name,loc&&loc.lat,loc&&loc.lng]);
    const weatherLabel=()=>{
      if(!weather)return 'Wetter …';
      if(weather.error)return 'Wetter –';
      const code=Number(weather.code),temp=Number(weather.temp);
      let icon='☁️',text='bewölkt';
      if(code===0){icon='☀️';text='klar';}
      else if(code===1||code===2){icon='🌤️';text='leicht bewölkt';}
      else if(code===3){icon='☁️';text='bewölkt';}
      else if(code===45||code===48){icon='🌫️';text='Nebel';}
      else if((code>=51&&code<=67)||(code>=80&&code<=82)){icon='🌦️';text='Regen';}
      else if((code>=71&&code<=77)||(code>=85&&code<=86)){icon='❄️';text='Schnee';}
      else if(code>=95){icon='⛈️';text='Gewitter';}
      return icon+' '+temp+'° · '+text;
    };
    const submitSearch=e=>{if(e)e.preventDefault();const q=String(searchValue||'').trim();if(q.length<2){openSearch();return;}const base=RPG.internalNavUrl?RPG.internalNavUrl(RPG.settings.globalSearchPageUrl||'/suche/'):(RPG.settings.globalSearchPageUrl||'/suche/');const u=new URL(base,window.location.origin);u.searchParams.set('q',q);window.location.href=(RPG.internalNavUrl?RPG.internalNavUrl(u.toString()):u.toString());};
    return h(RPG.Fragment,null,
      h('header',{className:'rpgx-header',ref:headerRef},
        div({className:'rpgx-header-inner'},
          a({className:'rpgx-brand'+(props.eventLanding?' rpgx-brand-landing':''),href:RPG.internalNavUrl?RPG.internalNavUrl(RPG.settings.homeUrl||'/'):(RPG.settings.homeUrl||'/'),'aria-label':'PottFuchs Startseite'},logo),
          div({className:'rpgx-header-actions'},
            button({className:'rpgx-icon-btn',type:'button','aria-label':'Globale Suche öffnen',onClick:openSearch},'⌕'),
            button({className:'rpgx-icon-btn',type:'button','aria-label':'Standort ändern',onClick:props.onLocation},'⌖'),
            button({className:'rpgx-icon-btn',type:'button','aria-label':'Merkzettel',onClick:()=>window.dispatchEvent(new Event('rpg:open-favorites'))},'♡'),
            button({className:'rpgx-icon-btn',type:'button','aria-label':'Menü',onClick:openDrawer},'☰')
          )
        ),
        searchOpen?h('form',{className:'rpgx-global-search-bar',onSubmit:submitSearch},
          h('input',{ref:searchRef,type:'search',value:searchValue,placeholder:'Events, Orte, Künstler, Büdchen …',onChange:e=>setSearchValue(e.target.value),'aria-label':'PottFuchs durchsuchen'}),
          h('button',{type:'submit',className:'rpgx-global-search-submit'},'Suchen'),
          !/\/suche\/?$/.test(window.location.pathname)?h('button',{type:'button',className:'rpgx-global-search-close','aria-label':'Suche schließen',onClick:()=>setSearchOpen(false)},'×'):null
        ):null,
        loc ? div({className:'rpgx-location-line'},
          div({className:'rpgx-location-line-mobile'},
            strong({className:'rpgx-location-current'},RPG.locationLabel(loc)+' · '+props.radius+' km'),
            button({className:'rpgx-location-change',type:'button',onClick:props.onLocation},'Ändern'),
            span({className:'rpgx-header-weather','aria-label':'Aktuelles Wetter'},weatherLabel())
          ),
          div({className:'rpgx-location-line-desktop'},
            strong({className:'rpgx-location-current'},RPG.locationLabel(loc)+' · '+props.radius+' km'),
            button({className:'rpgx-location-change',type:'button',onClick:props.onLocation},'Ändern'),
            span({className:'rpgx-header-weather','aria-label':'Aktuelles Wetter'},weatherLabel())
          )
        ) : null
      ),
      div({className:'rpgx-header-spacer','aria-hidden':'true',style:headerHeight?{height:headerHeight+'px'}:null})
    );
  }

  function FavoritesOverlay({open,onClose}) {
    const [items,setItems]=useState([]);
    useEffect(function(){
      if(!open)return;
      let list=[];
      try{const raw=JSON.parse(RPG.storage.get('ruhrpottguru_favorites_v1')||'[]');if(Array.isArray(raw))list=raw;}catch(_){}
      setItems(list.slice().reverse());
      const esc=e=>{if(e.key==='Escape')onClose();};
      document.addEventListener('keydown',esc);
      return()=>document.removeEventListener('keydown',esc);
    },[open]);
    if(!open)return null;
    const remove=id=>{
      const next=items.filter(x=>String(x&&x.id)!==String(id));
      setItems(next);
      RPG.storage.set('ruhrpottguru_favorites_v1',JSON.stringify(next.slice().reverse()));
    };
    return div({className:'rpgx-favorites-backdrop',role:'dialog','aria-modal':'true','aria-label':'Merkzettel',onClick:e=>{if(e.target===e.currentTarget)onClose();}},
      div({className:'rpgx-favorites-panel'},
        div({className:'rpgx-favorites-head'},h('strong',null,'Merkzettel'),button({type:'button','aria-label':'Merkzettel schließen',onClick:onClose},'×')),
        items.length?div({className:'rpgx-favorites-list'},items.map(item=>div({className:'rpgx-favorite-row',key:String(item.id)},
          item.imageUrl?h('img',{src:item.imageUrl,alt:'',style:mediaCropStyle(cropForImage(item,item.imageUrl,'card',null)),loading:'lazy',onLoad:mediaCropOnLoad(cropForImage(item,item.imageUrl,'card',null),null)}):div({className:'rpgx-favorite-fallback'},'♡'),
          div({className:'rpgx-favorite-copy'},h('strong',null,item.title||'Gespeicherter Ort'),h('span',null,[item.city,({events:'Events',food:'Food',highlights:'Highlights',culture:'Kultur',activities:'Aktiv',wurst:'Imbissbuden',buedchen:'Büdchen'}[item.category]||item.category)].filter(Boolean).join(' · '))),
          button({type:'button',onClick:()=>remove(item.id),'aria-label':'Vom Merkzettel entfernen'},'Entfernen')
        ))):div({className:'rpgx-favorites-empty'},h('strong',null,'Noch nichts gemerkt.'),h('p',null,'Öffne einen Ort und tippe auf „Merken“.'))
      )
    );
  }

  function FooterNav() {
    const items = RPG.readJsonScript('ruhrpott-footer-menu-data', []);
    if (!Array.isArray(items) || !items.length) return null;
    const homeIcon=()=>h('svg',{viewBox:'0 0 24 24','aria-hidden':'true',focusable:'false'},h('path',{d:'M3 10.8 12 3l9 7.8v9.2a1 1 0 0 1-1 1h-5.5v-6h-5v6H4a1 1 0 0 1-1-1z',fill:'none',stroke:'currentColor',strokeWidth:'2',strokeLinecap:'round',strokeLinejoin:'round'}));
    const iconFor=x=>{
      const title=String(x.title||'').trim().toLowerCase();
      const url=String(x.url||'');
      const homeUrl=String(RPG.settings.homeUrl||'/').replace(/\/$/,'');
      const isHome=title==='home'||title==='start'||title==='startseite'||(homeUrl&&url.replace(/\/$/,'')===homeUrl);
      if(isHome)return homeIcon();
      return x.category==='events'?'📅':x.category==='food'?'🍔':x.category==='culture'?'🎨':x.category==='activities'?'🚴':'👀';
    };
    return h('nav',{className:'rpgx-footer-nav','aria-label':'Entdecken'},items.map(x=>a({key:x.category||x.url,href:RPG.internalNavUrl?RPG.internalNavUrl(x.url):x.url,className:'rpgx-footer-link'},span({className:'rpgx-footer-icon','aria-hidden':'true'},iconFor(x)),span(null,x.title||x.category))));
  }

  function LocationModal(props) {
    const [draftRadius,setDraftRadius]=useState(props.radius);
    const [postcodeOpen,setPostcodeOpen]=useState(false);
    const [postcode,setPostcode]=useState('');
    const [postcodeBusy,setPostcodeBusy]=useState(false);
    const [addressOpen,setAddressOpen]=useState(false);
    const [addressLoadError,setAddressLoadError]=useState('');
    const [addressBusy,setAddressBusy]=useState(false);
    const [addressStreet,setAddressStreet]=useState('');
    const [addressHouse,setAddressHouse]=useState('');
    const [addressPostcode,setAddressPostcode]=useState('');
    const [addressCity,setAddressCity]=useState('');
    useEffect(function(){
      document.documentElement.classList.toggle('rpgx-modal-open',!!props.open);
      document.body.classList.toggle('rpgx-modal-open',!!props.open);
      return function(){
        document.documentElement.classList.remove('rpgx-modal-open');
        document.body.classList.remove('rpgx-modal-open');
      };
    },[props.open]);
    useEffect(function(){
      if(props.open){
        setDraftRadius(props.radius);
        setPostcode(props.location&&props.location.postcode?String(props.location.postcode):'');
        setPostcodeOpen(false);
        setPostcodeBusy(false);
        setAddressOpen(false);
        setAddressLoadError('');
        setAddressBusy(false);
        setAddressStreet('');
        setAddressHouse('');
        setAddressPostcode(props.location&&props.location.postcode?String(props.location.postcode):'');
        setAddressCity(props.location&&props.location.city?String(props.location.city):'');
      }
    },[props.open,props.radius]);
    if (!props.open) return null;
    const cities = Object.keys(RPG.CITY_CENTERS).sort((a,b)=>a.localeCompare(b,'de'));
    const applyRadius=()=>{
      props.onRadius(draftRadius);
      if(props.location)props.onClose();
    };
    const applyPostcode=async e=>{
      if(e)e.preventDefault();
      if(postcodeBusy)return;
      setPostcodeBusy(true);
      try{await props.onPostcode(postcode);}finally{setPostcodeBusy(false);}
    };

    const normalizeCityKey=value=>String(value||'').trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/ß/g,'ss').replace(/[^a-z0-9]+/g,'');
    const matchCity=value=>{
      const key=normalizeCityKey(value);if(!key)return '';
      const direct=Object.keys(RPG.CITY_CENTERS||{}).find(name=>normalizeCityKey(name)===key);if(direct)return direct;
      const aliases={frondenberg:'Fröndenberg/Ruhr',frondenbergruhr:'Fröndenberg/Ruhr',froendenberg:'Fröndenberg/Ruhr',froendenbergruhr:'Fröndenberg/Ruhr',voerde:'Voerde (Niederrhein)',voerdeniederrhein:'Voerde (Niederrhein)',wetter:'Wetter (Ruhr)',wetterruhr:'Wetter (Ruhr)',mulheim:'Mülheim an der Ruhr',mulheimruhr:'Mülheim an der Ruhr',mulheimanderruhr:'Mülheim an der Ruhr',muelheim:'Mülheim an der Ruhr',muelheimruhr:'Mülheim an der Ruhr',muelheimanderruhr:'Mülheim an der Ruhr'};
      return aliases[key]||'';
    };
    const cleanAddressPart=value=>String(value||'').replace(/[\[\];]/g,' ').replace(/\s+/g,' ').trim();
    const applyAddress=async e=>{
      if(e)e.preventDefault();
      if(addressBusy)return;
      setAddressLoadError('');
      let street=cleanAddressPart(addressStreet),house=cleanAddressPart(addressHouse),zip=String(addressPostcode||'').replace(/\D/g,'').slice(0,5),cityInput=cleanAddressPart(addressCity);
      if(!street&&!house&&zip&&!cityInput){await props.onPostcode(zip);return;}
      if(!street&&!house&&!zip&&cityInput){const city=matchCity(cityInput);if(city){props.onCity(city);return;}setAddressLoadError('Dieser Ort liegt außerhalb des PottFuchs-Gebiets oder konnte nicht eindeutig zugeordnet werden.');return;}
      if(!street&&!house&&zip){await props.onPostcode(zip);return;}
      if(!street){setAddressLoadError('Für eine Adresssuche gib bitte eine Straße ein. Alternativ kannst du nur PLZ oder nur Ort verwenden.');return;}
      if(!zip&&!cityInput){setAddressLoadError('Bitte ergänze zur Straße eine PLZ oder einen Ort.');return;}
      setAddressBusy(true);
      try{
        let city=cityInput?matchCity(cityInput):'';
        if(cityInput&&!city)throw new Error('Diese Adresse liegt außerhalb des PottFuchs-Gebiets. Bitte wähle eine Adresse innerhalb der Metropole Ruhr.');
        if(zip&&!/^\d{5}$/.test(zip))throw new Error('Bitte gib eine gültige 5-stellige PLZ ein.');
        if(!city&&zip){
          if(!RPG.api||typeof RPG.api.postcodeLocation!=='function')throw new Error('Die PLZ-Suche ist gerade nicht verfügbar.');
          const data=await RPG.api.postcodeLocation(zip);
          city=matchCity(data&&data.city)||'';
          if(!city)throw new Error('Diese Adresse liegt außerhalb des PottFuchs-Gebiets. Bitte wähle eine Adresse innerhalb der Metropole Ruhr.');
        }
        if(!RPG.api||typeof RPG.api.addressLocation!=='function')throw new Error('Die Adresssuche ist gerade nicht verfügbar.');
        const data=await RPG.api.addressLocation({street:street,housenumber:house,postcode:zip,city:city});
        const lat=Number(data&&data.lat),lng=Number(data&&data.lng);
        if(!Number.isFinite(lat)||!Number.isFinite(lng))throw new Error('Die Adresse konnte nicht eindeutig gefunden werden. Bitte prüfe Straße, Hausnummer, PLZ und Ort.');
        const feature={type:'Feature',geometry:{type:'Point',coordinates:[lng,lat]},properties:{
          gemeinde:String(data.city||city||''),ort:String(data.city||city||''),plz:String(data.postcode||zip||''),
          strasse:String(data.street||street||''),haus:String(data.housenumber||house||''),text:String(data.label||'')
        }};
        if(props.onAddressResult)props.onAddressResult(feature);
      }catch(err){setAddressLoadError(err&&err.message?String(err.message):'Die Adresse konnte gerade nicht aufgelöst werden.');}
      finally{setAddressBusy(false);}
    };
    return div({className:'rpgx-modal-backdrop',onClick:e=>{if(e.target===e.currentTarget&&props.location)props.onClose();}},
      div({className:'rpgx-location-modal',role:'dialog','aria-modal':'true','aria-label':'Standort wählen'},
        div({className:'rpgx-modal-head'},h('strong',null,'Standort wählen'),props.location?button({type:'button',className:'rpgx-close-mini',onClick:props.onClose},'×'):null),
        props.location?div({className:'rpgx-location-current-box'},h('small',null,'Aktueller Standort'),h('strong',null,String(props.location.locationMode||'')==='address'&&String(props.location.name||'').trim()?String(props.location.name).trim():RPG.locationLabel(props.location))):null,
        props.error?div({className:'rpgx-location-error'},props.error):null,
        div({className:'rpgx-location-methods'},
          button({type:'button',className:'rpgx-primary-btn',onClick:props.onGps},'⌖ GPS-Standort verwenden'),
          button({type:'button',className:'rpgx-secondary-btn rpgx-postcode-toggle','aria-expanded':postcodeOpen?'true':'false',onClick:()=>{setPostcodeOpen(v=>!v);setAddressOpen(false);}},'⌨ PLZ eingeben'),
          button({type:'button',className:'rpgx-secondary-btn rpgx-address-toggle','aria-expanded':addressOpen?'true':'false',onClick:()=>{setAddressOpen(v=>!v);setPostcodeOpen(false);}},'⌕ Adresse / Ort suchen')
        ),
        postcodeOpen?h('form',{className:'rpgx-postcode-form',onSubmit:applyPostcode},
          h('label',{htmlFor:'rpgx-postcode-input'},'Postleitzahl'),
          div({className:'rpgx-postcode-controls'},
            h('input',{id:'rpgx-postcode-input',type:'text',inputMode:'numeric',autoComplete:'postal-code',maxLength:5,pattern:'[0-9]{5}',value:postcode,placeholder:'z. B. 46483',disabled:postcodeBusy,onChange:e=>setPostcode(String(e.target.value||'').replace(/\D/g,'').slice(0,5)),'aria-label':'Postleitzahl'}),
            button({type:'submit',className:'rpgx-radius-apply',disabled:postcodeBusy||!/^[0-9]{5}$/.test(postcode)},postcodeBusy?'Suche …':'Übernehmen')
          ),
          h('small',{className:'rpgx-postcode-help'},'Der Radius wird um den Mittelpunkt der PLZ gelegt. · © OpenStreetMap-Mitwirkende')
        ):null,
        addressOpen?h('form',{className:'rpgx-address-panel',onSubmit:applyAddress},
          h('strong',{className:'rpgx-address-title'},'Adresse / Ort suchen'),
          h('p',{className:'rpgx-address-help'},'Du kannst auch nur eine PLZ oder nur einen Ort eingeben. Für eine genaue Adresse reicht Straße plus PLZ oder Ort; die Hausnummer ist optional.'),
          addressLoadError?div({className:'rpgx-location-error'},addressLoadError):null,
          div({className:'rpgx-address-grid'},
            h('label',null,'Straße',h('input',{type:'text',autoComplete:'address-line1',value:addressStreet,disabled:addressBusy,placeholder:'z. B. Gelsenkirchener Str.',onChange:e=>setAddressStreet(e.target.value)})),
            h('label',null,'Hausnummer',h('input',{type:'text',autoComplete:'address-line2',value:addressHouse,disabled:addressBusy,placeholder:'181',onChange:e=>setAddressHouse(e.target.value)})),
            h('label',null,'PLZ',h('input',{type:'text',inputMode:'numeric',autoComplete:'postal-code',maxLength:5,value:addressPostcode,disabled:addressBusy,placeholder:'45309',onChange:e=>setAddressPostcode(String(e.target.value||'').replace(/\D/g,'').slice(0,5))})),
            h('label',null,'Ort',h('input',{type:'text',autoComplete:'address-level2',value:addressCity,disabled:addressBusy,placeholder:'Essen',onChange:e=>setAddressCity(e.target.value)}))
          ),
          button({type:'submit',className:'rpgx-radius-apply rpgx-address-submit',disabled:addressBusy},addressBusy?'Suche …':'Standort übernehmen'),
          h('small',{className:'rpgx-address-source'},'Adressauflösung: ',a({href:'https://www.geoapify.com/',target:'_blank',rel:'noopener noreferrer'},'Powered by Geoapify'),' · ',a({href:'https://www.openstreetmap.org/copyright',target:'_blank',rel:'noopener noreferrer'},'© OpenStreetMap contributors'))
        ):null,
        div({className:'rpgx-radius-row'},
          h('label',null,'Radius'),
          h('select',{value:draftRadius,onChange:e=>setDraftRadius(e.target.value)},[1,2.5,5,10,25,50].map(v=>h('option',{key:v,value:v},v+' km'))),
          button({type:'button',className:'rpgx-radius-apply',disabled:Number(draftRadius)===Number(props.radius),onClick:applyRadius},'Ändern')
        ),
        props.location?h('p',{className:'rpgx-radius-help'},'Radius unabhängig vom Standort ändern.'):null,
        h('p',{className:'rpgx-location-or'},'oder Stadt auswählen'),
        div({className:'rpgx-city-grid'},cities.map(city=>button({key:city,type:'button',className:'rpgx-city-btn',onClick:()=>props.onCity(city)},city)))
      )
    );
  }


  function FeedbackButton(){
    const [open,setOpen]=useState(false),[text,setText]=useState(''),[sent,setSent]=useState(false);
    const [token,setToken]=useState(''),[loading,setLoading]=useState(false),[error,setError]=useState('');
    const [company,setCompany]=useState('');
    const base=(RPG.settings.restBase||'/wp-json/ruhrpottguru/v1/');
    useEffect(function(){
      const openIssue=e=>{const d=e.detail||{};setText('Problem bei „'+(d.title||'diesem Eintrag')+'“: ');prepare();};
      window.addEventListener('rpg:feedback-item',openIssue);
      return()=>window.removeEventListener('rpg:feedback-item',openIssue);
    },[]);
    const prepare=async()=>{
      setOpen(true);setSent(false);setError('');setToken('');setCompany('');
      try{
        const r=await fetch(base+'feedback-token',{credentials:'same-origin',cache:'no-store'});
        const d=await r.json();
        if(r.ok&&d.token)setToken(d.token);else setError('Feedback ist gerade kurz nicht verfügbar.');
      }catch(_){setError('Feedback ist gerade kurz nicht verfügbar.');}
    };
    const submit=async()=>{
      const msg=text.trim(); if(!msg||!token||loading)return;
      setLoading(true);setError('');
      try{
        const root=document.getElementById('ruhrpott-guru-root');
        const res=await fetch(base+'beta-feedback',{
          method:'POST',
          headers:{'Content-Type':'application/json'},
          body:JSON.stringify({
            message:msg,
            page:location.pathname,
            category:(root&&root.dataset.categoryKey)||'',
            feedback_token:token,
            company:company
          }),
          credentials:'same-origin'
        });
        if(res.ok){setSent(true);setText('');setToken('');}
        else if(res.status===429)setError('Zu viele Anfragen in kurzer Zeit. Bitte versuche es später noch einmal.');
        else if(res.status===409)setError('Dieses Feedback wurde bereits gesendet.');
        else setError('Feedback konnte nicht gesendet werden. Bitte öffne das Formular neu und versuche es noch einmal.');
      }catch(_){setError('Feedback konnte nicht gesendet werden.');}
      finally{setLoading(false);}
    };
    return h(RPG.Fragment,null,
      button({type:'button',className:'rpgx-feedback-fab',onClick:prepare},'Feedback'),
      open?div({className:'rpgx-modal-backdrop rpgx-feedback-backdrop',onClick:e=>{if(e.target===e.currentTarget)setOpen(false);}},
        div({className:'rpgx-feedback-modal',role:'dialog','aria-modal':'true','aria-label':'Feedback'},
          div({className:'rpgx-modal-head'},h('strong',null,'Feedback'),button({type:'button',className:'rpgx-close-mini',onClick:()=>setOpen(false)},'×')),
          sent?h('p',{className:'rpgx-feedback-success'},'Danke – dein Hinweis ist angekommen.'):h(RPG.Fragment,null,
            h('p',null,'Was war falsch, hat gefehlt oder war unklar?'),
            h('div',{className:'rpgx-feedback-hp',hidden:true},h('label',null,'Firma',h('input',{type:'text',value:company,tabIndex:-1,autoComplete:'off',onChange:e=>setCompany(e.target.value)}))),
            h('textarea',{value:text,maxLength:1200,placeholder:'Kurzer Hinweis genügt …',onChange:e=>setText(e.target.value)}),
            error?h('p',{className:'rpgx-feedback-error',role:'alert'},error):null,
            button({type:'button',className:'rpgx-primary-btn',disabled:!text.trim()||!token||loading,onClick:submit},loading?'Wird gesendet …':(!token&&!error?'Schutz wird geladen …':'Feedback senden'))
          )
        )
      ):null
    );
  }

  // V292: automatic venue photos, symbolic images and posters are valid media.
  // Presentation is controlled by reversible crop/focal metadata, not pixel rejection.

  function useMedia(item) {
    const suppressed=!!(item&&item.displayImageSuppressed);
    const preferred=suppressed?'':(item&&(item.displayImageUrl||item.imageUrl)||'');
    const [image,setImage]=useState(preferred);
    const [settled,setSettled]=useState(suppressed||!!preferred);
    const failedRef=useRef('');
    useEffect(function(){
      let live=true;failedRef.current='';
      const blocked=!!(item&&item.displayImageSuppressed);
      const next=blocked?'':(item&&(item.displayImageUrl||item.imageUrl)||'');
      setImage(next);setSettled(blocked||!!next);
      if(item&&!blocked&&!next){RPG.resolveItemMedia(item).then(v=>{if(!live)return;if(v)setImage(v);setSettled(true);}).catch(()=>{if(live)setSettled(true);});}else setSettled(true);
      return()=>{live=false;};
    },[item&&item.id,item&&item.displayImageUrl,item&&item.imageUrl,item&&item.displayImageSuppressed]);
    const onError=()=>{const failed=image||'';if(failedRef.current===failed){setImage('');setSettled(true);return;}failedRef.current=failed;if(item&&item.displayImageSuppressed){setImage('');setSettled(true);return;}const original=item&&item.imageUrl||'';const strictSemantic=!!(item&&item.displayImageUrl&&String(item.mediaSemanticVersion||'')==='v298'&&String(item.displayMediaReason||'').includes('semantic'));if(item&&item.displayImageUrl&&original&&original!==failed&&!strictSemantic){setImage(original);setSettled(true);return;}if(strictSemantic){setImage('');setSettled(true);return;}setImage('');if(!item){setSettled(true);return;}setSettled(false);const retry=Object.assign({},item,{imageUrl:'',displayImageUrl:''});RPG.resolveItemMedia(retry).then(v=>{if(v&&v!==failed)setImage(v);setSettled(true);}).catch(()=>setSettled(true));};
    return {image,onError,onLoad:()=>{},settled,runtimeKind:'',runtimeLabel:''};
  }

  function mediaCropValues(crop){
    if(!crop||!['auto','manual'].includes(String(crop.mode||'')))return null;
    const raw=Number(crop.zoom??1);
    return {x:Math.max(0,Math.min(100,Number(crop.x??50))),y:Math.max(0,Math.min(100,Number(crop.y??50))),zoom:Number.isFinite(raw)?Math.max(0,Math.min(2.5,raw)):1};
  }
  function mediaCropStyle(crop){
    const v=mediaCropValues(crop);if(!v)return null;
    const style={objectPosition:v.x+'% '+v.y+'%',transformOrigin:v.x+'% '+v.y+'%'};
    if(v.zoom>=0.999){style.objectFit='cover';if(v.zoom>1.001)style.transform='scale('+v.zoom+')';}
    else{style.objectFit='contain';style.transform='none';}
    return style;
  }
  function applyMediaCropElement(el,crop){
    const v=mediaCropValues(crop);if(!el||!v)return;
    el.style.objectPosition=v.x+'% '+v.y+'%';el.style.transformOrigin=v.x+'% '+v.y+'%';
    if(v.zoom>=0.999){el.style.objectFit='cover';el.style.transform=v.zoom>1.001?'scale('+v.zoom+')':'none';return;}
    const nw=Number(el.naturalWidth)||0,nh=Number(el.naturalHeight)||0,cw=Number(el.clientWidth)||0,ch=Number(el.clientHeight)||0;
    if(!(nw>0&&nh>0&&cw>0&&ch>0)){el.style.objectFit='contain';el.style.transform='none';return;}
    const sourceRatio=nw/nh,boxRatio=cw/ch,coverFromContain=sourceRatio>boxRatio?sourceRatio/boxRatio:boxRatio/sourceRatio;
    const scale=1+(coverFromContain-1)*v.zoom;
    el.style.objectFit='contain';el.style.transform='scale('+scale+')';
  }
  function mediaCropOnLoad(crop,next){
    return function(e){applyMediaCropElement(e&&e.currentTarget,crop);if(typeof next==='function')next(e);};
  }
  RPG.applyMediaCropElement=applyMediaCropElement;
  function cropForImage(item,image,which,entry){
    if(entry&&entry.url===image){const c=which==='detail'?entry.cropDetail:entry.cropCard;if(c)return c;}
    if(item&&image&&image===item.imageUrl)return which==='detail'?item.imageCropDetail:item.imageCropCard;
    return null;
  }

  function CameraGlyph(){
    return h('svg',{viewBox:'0 0 24 24',width:'34',height:'34',fill:'none',stroke:'currentColor',strokeWidth:'1.8',strokeLinecap:'round',strokeLinejoin:'round','aria-hidden':'true',focusable:'false'},
      h('path',{d:'M4 7.5h3.2l1.4-2h6.8l1.4 2H20a2 2 0 0 1 2 2v8.5a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V9.5a2 2 0 0 1 2-2Z'}),
      h('circle',{cx:'12',cy:'13.5',r:'4'})
    );
  }

  function CommunityPhotoPlaceholder(){
    return div({className:'rpgx-image-fallback rpgx-community-photo-placeholder'},h(CameraGlyph),span({className:'rpgx-community-photo-copy'},'Hier fehlt noch ein gutes Foto'));
  }

  function photoContributionItem(item){return Object.assign({},item,{_rpgContributionIntent:'photo'});}

  // RPG COMMUNITY v305 – mobile-first image optimizer.
  // Keep the original aspect ratio; cards/detail decide only the display crop via object-fit.
  const COMMUNITY_IMAGE_MAX_SOURCE_BYTES=20*1024*1024;
  const COMMUNITY_IMAGE_MAX_UPLOAD_BYTES=2*1024*1024;
  const COMMUNITY_IMAGE_MAX_EDGE=2400;
  const COMMUNITY_IMAGE_MIN_EDGE=1400;
  const COMMUNITY_IMAGE_ALLOWED_SOURCE_TYPES=['image/jpeg','image/png','image/webp','image/heic','image/heif'];
  function communitySourceType(file){
    const type=String(file&&file.type||'').toLowerCase();if(type)return type;
    const ext=String(file&&file.name||'').toLowerCase().split('.').pop();
    return ext==='jpg'||ext==='jpeg'?'image/jpeg':ext==='png'?'image/png':ext==='webp'?'image/webp':ext==='heic'?'image/heic':ext==='heif'?'image/heif':'';
  }
  function communitySafeBaseName(name){
    const raw=String(name||'foto').replace(/\.[^.]+$/,'').trim()||'foto';
    return raw.replace(/[^a-zA-Z0-9._-]+/g,'-').replace(/^-+|-+$/g,'').slice(0,90)||'foto';
  }
  function communityCanvasBlob(canvas,type,quality){return new Promise(resolve=>canvas.toBlob(resolve,type,quality));}
  async function communityLoadImage(file){
    if(window.createImageBitmap){
      let bitmap=null;
      try{bitmap=await window.createImageBitmap(file,{imageOrientation:'from-image'});}catch(_){try{bitmap=await window.createImageBitmap(file);}catch(__){bitmap=null;}}
      if(bitmap)return {source:bitmap,width:bitmap.width,height:bitmap.height,close:()=>{try{bitmap.close();}catch(_){}}};
    }
    return new Promise((resolve,reject)=>{
      const url=URL.createObjectURL(file),img=new Image();
      img.onload=()=>resolve({source:img,width:img.naturalWidth||img.width,height:img.naturalHeight||img.height,close:()=>URL.revokeObjectURL(url)});
      img.onerror=()=>{URL.revokeObjectURL(url);reject(new Error('Bild konnte nicht gelesen werden.'));};img.src=url;
    });
  }
  async function optimizeCommunityImage(file){
    if(!file||Number(file.size||0)<1)throw new Error('Die Bilddatei ist leer.');
    if(Number(file.size||0)>COMMUNITY_IMAGE_MAX_SOURCE_BYTES)throw new Error(file.name+': Ausgangsdatei größer als 20 MB.');
    const sourceType=communitySourceType(file);
    if(!COMMUNITY_IMAGE_ALLOWED_SOURCE_TYPES.includes(sourceType))throw new Error(file.name+': erlaubt sind JPG, PNG, WebP sowie HEIC/HEIF auf unterstützten Geräten.');
    let loaded=null;
    try{
      loaded=await communityLoadImage(file);
      const sw=Math.max(1,Number(loaded.width||0)),sh=Math.max(1,Number(loaded.height||0));
      if(!sw||!sh)throw new Error('Bildabmessungen konnten nicht gelesen werden.');
      let scale=Math.min(1,COMMUNITY_IMAGE_MAX_EDGE/Math.max(sw,sh));
      let width=Math.max(1,Math.round(sw*scale)),height=Math.max(1,Math.round(sh*scale));
      let blob=null;
      for(let pass=0;pass<4;pass++){
        const canvas=document.createElement('canvas');canvas.width=width;canvas.height=height;
        const ctx=canvas.getContext('2d',{alpha:false});if(!ctx)throw new Error('Bildoptimierung wird von diesem Browser nicht unterstützt.');
        ctx.fillStyle='#fff';ctx.fillRect(0,0,width,height);ctx.drawImage(loaded.source,0,0,width,height);
        for(const quality of [0.82,0.76,0.70,0.64]){
          blob=await communityCanvasBlob(canvas,'image/webp',quality);
          if(blob&&blob.type==='image/webp'&&blob.size<=COMMUNITY_IMAGE_MAX_UPLOAD_BYTES)break;
        }
        if(blob&&blob.type==='image/webp'&&blob.size<=COMMUNITY_IMAGE_MAX_UPLOAD_BYTES)break;
        if(Math.max(width,height)<=COMMUNITY_IMAGE_MIN_EDGE)break;
        const shrink=Math.max(COMMUNITY_IMAGE_MIN_EDGE/Math.max(width,height),0.82);
        width=Math.max(1,Math.round(width*shrink));height=Math.max(1,Math.round(height*shrink));
      }
      if(!blob||blob.type!=='image/webp'||blob.size>COMMUNITY_IMAGE_MAX_UPLOAD_BYTES)throw new Error(file.name+': konnte nicht sicher unter 2 MB optimiert werden. Bitte ein anderes Foto wählen.');
      const out=new File([blob],communitySafeBaseName(file.name)+'.webp',{type:'image/webp',lastModified:Number(file.lastModified||Date.now())});
      try{Object.defineProperty(out,'_rpgSourceKey',{value:[file.name,file.size,file.lastModified].join('|'),enumerable:false});Object.defineProperty(out,'_rpgOriginalBytes',{value:Number(file.size||0),enumerable:false});}catch(_){}
      return out;
    }catch(err){
      if(sourceType==='image/heic'||sourceType==='image/heif')throw new Error(file.name+': HEIC/HEIF kann dieser Browser nicht verarbeiten. Bitte das Foto als JPEG exportieren.');
      throw err;
    }finally{if(loaded&&loaded.close)loaded.close();}
  }

  function Media(props) {
    const media=useMedia(props.item), image=media.image;
    if(image){const crop=cropForImage(props.item,image,'card',null);return h('img',{src:image,alt:'',style:mediaCropStyle(crop),loading:props.eager?'eager':'lazy',decoding:'async',referrerPolicy:'no-referrer',onLoad:mediaCropOnLoad(crop,media.onLoad),onError:media.onError});}
    if(props.communityPhoto&&props.item.category==='activities'&&media.settled)return h(CommunityPhotoPlaceholder);
    return div({className:'rpgx-image-fallback'},props.item.category==='food'?'🍴':props.item.category==='events'?'🎟️':props.item.category==='culture'?'🎨':props.item.category==='activities'?'⚡':'🏭');
  }

  const CONTRIBUTION_FIELDS={
    food:[['opening_hours','Öffnungszeiten','text'],['website','Website','url'],['phone','Telefon','tel'],['menu_url','Speisekarte / Menü','url'],['reservation_url','Reservierungslink','url'],['cuisine','Küche / Angebot','text'],['price_tier','Preis-Hinweis','text'],['description','Beschreibung / Hinweis','textarea'],['community_status','Status des Ortes','status']],
    highlights:[['opening_hours','Öffnungszeiten','text'],['website','Website','url'],['phone','Telefon','tel'],['access','Zugang / Zugänglichkeit','text'],['accessibility','Barrierefreiheit','text'],['admission','Eintritt / Kosten','text'],['description','Beschreibung / Hinweis','textarea'],['community_status','Status des Ortes','status']],
    culture:[['opening_hours','Öffnungszeiten','text'],['website','Website','url'],['phone','Telefon','tel'],['ticket_url','Tickets','url'],['program_url','Programm','url'],['admission','Eintritt / Kosten','text'],['description','Beschreibung / Hinweis','textarea'],['community_status','Status des Ortes','status']],
    activities:[['opening_hours','Öffnungszeiten','text'],['website','Website','url'],['phone','Telefon','tel'],['booking_url','Buchung / Reservierung','url'],['activity_type','Art der Aktivität / Ausstattung','text'],['price_tier','Preis-Hinweis','text'],['admission','Eintritt / Kosten','text'],['description','Beschreibung / Hinweis','textarea'],['community_status','Status des Ortes','status']],
    buedchen:[['opening_hours','Öffnungszeiten','text'],['website','Website','url'],['phone','Telefon','tel'],['instagram','Instagram','text'],['description','Beschreibung / Hinweis','textarea'],['community_status','Status des Ortes','status']],
    wurst:[['opening_hours','Öffnungszeiten','text'],['website','Website','url'],['phone','Telefon','tel'],['menu_url','Speisekarte / Menü','url'],['cuisine','Angebot / Spezialitäten','text'],['price_tier','Preis-Hinweis','text'],['description','Beschreibung / Hinweis','textarea'],['community_status','Status des Ortes','status']]
  };

  function ContributionForm({item,onClose,intent}){
    const photoOnly=intent==='photo';
    const config=photoOnly?[]:(CONTRIBUTION_FIELDS[item.category]||[]);
    const [fields,setFields]=useState({});
    const [sender,setSender]=useState('community');
    const [images,setImages]=useState([]);
    const [rights,setRights]=useState(false);
    const [submitterName,setSubmitterName]=useState('');
    const [submitterEmail,setSubmitterEmail]=useState('');
    const [status,setStatus]=useState('');
    const [processingImages,setProcessingImages]=useState(false);
    const change=(key,value)=>setFields(v=>Object.assign({},v,{[key]:value}));
    const chooseImages=async e=>{
      const picked=Array.from(e.target.files||[]).slice(0,5);e.target.value='';if(!picked.length)return;
      setProcessingImages(true);setStatus('Fotos werden fürs Web optimiert …');setRights(false);
      const next=[];let firstError='';
      for(const file of picked){try{next.push({file:await optimizeCommunityImage(file),copyright:'',sourceUrl:''});}catch(err){if(!firstError)firstError=err&&err.message?err.message:'Ein Foto konnte nicht optimiert werden.';}}
      setImages(next);setProcessingImages(false);setStatus(firstError);
    };
    const setCopyright=(idx,value)=>setImages(list=>list.map((x,i)=>i===idx?Object.assign({},x,{copyright:value}):x));
    const setSourceUrl=(idx,value)=>setImages(list=>list.map((x,i)=>i===idx?Object.assign({},x,{sourceUrl:value}):x));
    const hasData=Object.values(fields).some(v=>String(v||'').trim()!=='');
    const copyrightOk=images.every(x=>String(x.copyright||'').trim()!=='');
    const contactOk=!images.length||(submitterName.trim()!=='' && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(submitterEmail.trim()));
    const canSend=!processingImages&&(hasData||images.length>0)&&(!images.length||(copyrightOk&&rights&&contactOk));
    const validationMessage=()=>{
      if(!hasData&&!images.length)return photoOnly?'Bitte wähle mindestens ein Foto aus.':'Bitte ergänze mindestens eine Angabe oder wähle ein Bild aus.';
      if(images.length&&!copyrightOk)return 'Bitte trage für jedes Bild Copyright / Urheber bzw. Rechteinhaber ein.';
      if(images.length&&submitterName.trim()==='')return 'Bitte trage deinen Namen oder deine Firma ein.';
      if(images.length&&!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(submitterEmail.trim()))return 'Bitte trage eine gültige E-Mail-Adresse ein.';
      if(images.length&&!rights)return 'Bitte bestätige die Rechteerklärung für den Bild-Upload.';
      return '';
    };
    const submit=async()=>{
      if(status==='sending'||processingImages)return;
      if(!canSend){setStatus(validationMessage());return;}
      const clean={};Object.entries(fields).forEach(([k,v])=>{if(String(v||'').trim()!=='')clean[k]=v;});
      const fd=new FormData();fd.append('place_id',item.id);fd.append('category',item.category);fd.append('submitter_type',sender);fd.append('fields_json',JSON.stringify(clean));fd.append('rights_confirmed',rights?'1':'0');fd.append('submitter_name',submitterName.trim());fd.append('submitter_email',submitterEmail.trim());
      images.forEach((x,i)=>{fd.append('image_'+i,x.file,x.file.name);fd.append('copyright_'+i,x.copyright.trim());if(String(x.sourceUrl||'').trim())fd.append('source_url_'+i,String(x.sourceUrl).trim());});
      setStatus('sending');
      try{await RPG.api.submitPlaceContribution(fd);setStatus(images.length?'Danke! Bitte bestätige jetzt den Link in der E-Mail. Erst danach erscheint die Einsendung in der Redaktion.':'Danke! Deine Angaben warten jetzt auf Freigabe.');setFields({});setImages([]);setRights(false);setSubmitterName('');setSubmitterEmail('');}catch(e){setStatus(e&&e.message?e.message:'Senden fehlgeschlagen. Bitte später erneut versuchen.');}
    };
    return div({className:'rpgx-bude-report-backdrop',onClick:e=>{if(e.target===e.currentTarget)onClose();}},
      div({className:'rpgx-bude-report rpgx-bude-report-complete rpgx-contribution-form'+(photoOnly?' is-photo-only':''),role:'dialog','aria-modal':'true','aria-label':photoOnly?'Foto für diesen Ort beisteuern':'Daten ergänzen oder Bild hochladen'},
        div({className:'rpgx-bude-report-head'},div(null,strong(null,photoOnly?'Foto beisteuern':'Daten ergänzen oder Bild hochladen'),span({className:'rpgx-bude-report-subtitle'},item.title)),button({type:'button',onClick:onClose,'aria-label':'Formular schließen'},'×')),
        p({className:'rpgx-bude-report-warning'},photoOnly?'Danke fürs Mitmachen: Dein Foto wird vor der Veröffentlichung geprüft.':'Alle Einsendungen werden vor Veröffentlichung geprüft.'),
        config.length?div({className:'rpgx-bude-report-grid'},config.map(([key,label,type])=>h('label',{key},label,
          type==='textarea'?h('textarea',{rows:3,value:fields[key]||'',onChange:e=>change(key,e.target.value)}):
          type==='status'?h('select',{value:fields[key]||'',onChange:e=>change(key,e.target.value)},h('option',{value:''},'Bitte auswählen'),h('option',{value:'active'},'Geöffnet / aktiv'),h('option',{value:'temporarily_closed'},'Vorübergehend geschlossen'),h('option',{value:'permanently_closed'},'Dauerhaft geschlossen')):
          h('input',{type:type||'text',value:fields[key]||'',onChange:e=>change(key,e.target.value)})
        ))):null,
        h('fieldset',{className:'rpgx-contribution-images'},h('legend',null,photoOnly?'Foto hochladen':'Bilder hochladen'),
          h('p',null,photoOnly?'Bis zu 5 Ortsfotos. Wir optimieren sie vor dem Upload automatisch auf maximal 2400 px und 2 MB je Foto; das Seitenverhältnis bleibt erhalten. JPG, PNG, WebP sowie HEIC/HEIF auf unterstützten Geräten.':'Bis zu 5 Bilder. Wir optimieren sie vor dem Upload automatisch auf maximal 2400 px und 2 MB je Foto; das Seitenverhältnis bleibt erhalten. Jedes Bild benötigt einen eigenen Copyright-Eintrag.'),
          h('input',{type:'file',accept:'image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif',multiple:true,onChange:chooseImages,autoFocus:photoOnly,disabled:processingImages}),
          images.map((x,i)=>div({key:i,className:'rpgx-contribution-image-row'},span(null,x.file.name),div(null,h('label',null,'Copyright / Urheber bzw. Rechteinhaber *',h('input',{type:'text',required:true,value:x.copyright,onChange:e=>setCopyright(i,e.target.value),placeholder:'z. B. © Max Mustermann'})),h('label',null,'Quelle / Website (optional)',h('input',{type:'url',value:x.sourceUrl||'',onChange:e=>setSourceUrl(i,e.target.value),placeholder:'https://…'}))))),
          images.length?div({className:'rpgx-contribution-contact'},
            h('p',null,'Für Bild-Uploads benötigen wir deinen Namen und eine E-Mail-Adresse. Die E-Mail wird einmalig bestätigt und zusammen mit der Rechteerklärung als Nachweis zur Einsendung gespeichert.'),
            h('label',null,'Name / Firma *',h('input',{type:'text',required:true,value:submitterName,onChange:e=>setSubmitterName(e.target.value),autoComplete:'name'})),
            h('label',null,'E-Mail-Adresse *',h('input',{type:'email',required:true,value:submitterEmail,onChange:e=>setSubmitterEmail(e.target.value),autoComplete:'email'})),
            h('label',{className:'rpgx-contribution-rights'},h('input',{type:'checkbox',checked:rights,onChange:e=>setRights(e.target.checked)}),h('span',null,'Ich bestätige, dass ich zur Übermittlung dieser Bilder berechtigt bin und PottFuchs das Bild auf der Website verwenden darf. *'))
          ):null
        ),
        h('label',null,'Du meldest als',h('select',{value:sender,onChange:e=>setSender(e.target.value)},h('option',{value:'community'},'Besucher/in'),h('option',{value:'operator'},'Betreiber/in'))),
        h('button',{type:'button',className:'rpgx-bude-primary',disabled:status==='sending'||processingImages,onClick:submit},processingImages?'Fotos werden optimiert …':status==='sending'?'Wird gesendet …':'Zur Prüfung senden'),
        status&&status!=='sending'?h('p',{className:'rpgx-bude-report-status'},status):null
      )
    );
  }


  function CommunityStoryForm({item,onClose,onSubmitted}){
    const [title,setTitle]=useState(''),[content,setContent]=useState(''),[nickname,setNickname]=useState(''),[email,setEmail]=useState(''),[instagram,setInstagram]=useState('');
    const [images,setImages]=useState([]),[rights,setRights]=useState(false),[terms,setTerms]=useState(false),[privacy,setPrivacy]=useState(false),[status,setStatus]=useState(''),[submitted,setSubmitted]=useState(false),[processingImages,setProcessingImages]=useState(false);
    const fileRef=useRef(null);
    const maxFiles=5;
    const chooseImages=async e=>{
      const picked=Array.from(e.target.files||[]);e.target.value='';if(!picked.length)return;
      const existing=images.slice(),next=existing.slice(),rejected=[];setProcessingImages(true);setStatus('Fotos werden fürs Web optimiert …');
      for(const file of picked){
        if(next.length>=maxFiles){rejected.push('Maximal 5 Fotos pro Beitrag');break;}
        const key=[file.name,file.size,file.lastModified].join('|');if(next.some(x=>(x._rpgSourceKey||[x.name,x.size,x.lastModified].join('|'))===key))continue;
        try{next.push(await optimizeCommunityImage(file));}catch(err){rejected.push(err&&err.message?err.message:'Ein Foto konnte nicht optimiert werden.');}
      }
      setImages(next.slice(0,maxFiles));setProcessingImages(false);setStatus(rejected.length?rejected[0]:'');
    };
    const removeImage=index=>{setImages(prev=>prev.filter((_,i)=>i!==index));setStatus('');};
    const submit=async()=>{
      if(status==='sending'||submitted||processingImages)return;
      if(!images.length){setStatus('Bitte wähle mindestens ein Foto aus. Ein Bild ist für Community-Beiträge Pflicht.');return;}
      if(title.trim().length<4){setStatus('Bitte gib eine kurze Überschrift mit mindestens 4 Zeichen ein.');return;}
      if(content.trim().length<20){setStatus('Dein Tipp sollte mindestens 20 Zeichen lang sein.');return;}
      if(!nickname.trim()){setStatus('Bitte trage einen Namen oder Nickname ein.');return;}
      if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim())){setStatus('Bitte trage eine gültige E-Mail-Adresse ein.');return;}
      if(!rights){setStatus('Bitte bestätige die Bildrechte.');return;}
      if(!terms){setStatus('Bitte stimme den Einreichungsbedingungen zu.');return;}
      if(!privacy){setStatus('Bitte stimme der Datenschutzerklärung zu.');return;}
      const fd=new FormData();fd.append('place_id',item.id);fd.append('category',item.category);fd.append('title',title.trim());fd.append('content',content.trim());fd.append('nickname',nickname.trim());fd.append('email',email.trim());fd.append('instagram',instagram.trim());fd.append('terms_accepted','1');fd.append('privacy_accepted','1');fd.append('image_rights_confirmed','1');fd.append('company','');images.slice(0,maxFiles).forEach((f,i)=>fd.append('image_'+i,f,f.name));
      setStatus('sending');
      try{
        const r=await RPG.api.submitCommunityStory(fd);
        setSubmitted(true);setStatus(r.message||'Danke! Dein Beitrag wartet jetzt auf redaktionelle Freigabe.');
        if(onSubmitted)onSubmitted();
      }catch(e){setStatus(e.message||'Senden fehlgeschlagen. Bitte versuche es später erneut.');}
    };
    return div({className:'rpgx-bude-report-backdrop',onClick:e=>{if(e.target===e.currentTarget)onClose();}},div({className:'rpgx-bude-report rpgx-community-story-form',role:'dialog','aria-modal':'true','aria-label':'Community-Beitrag schreiben'},
      div({className:'rpgx-bude-report-head'},div(null,strong(null,'Beitrag erstellen'),span({className:'rpgx-bude-report-subtitle'},item.title)),button({type:'button',onClick:onClose,'aria-label':'Formular schließen'},'×')),
      submitted?div({className:'rpgx-community-submit-success',role:'status'},
        h('strong',null,'Danke fürs Mitmachen!'),
        h('p',null,status||'Dein Beitrag wartet jetzt auf redaktionelle Freigabe.'),
        h('p',{className:'rpgx-community-submit-note'},'Er erscheint erst am Ort, nachdem die Redaktion ihn geprüft und freigegeben hat.'),
        h('button',{type:'button',className:'rpgx-bude-primary',onClick:onClose},'Schließen')
      ):h(RPG.Fragment,null,
        p({className:'rpgx-bude-report-warning'},'Kein Konto nötig. Dein Beitrag erscheint erst nach redaktioneller Freigabe.'),
        h('label',null,'Überschrift *',h('input',{type:'text',maxLength:120,value:title,onChange:e=>setTitle(e.target.value),placeholder:'z. B. Perfekt kurz vor Sonnenuntergang'})),
        h('label',null,'Dein Tipp *',h('textarea',{rows:7,maxLength:6000,value:content,onChange:e=>setContent(e.target.value),placeholder:'Was sollten andere über diesen Ort wissen?'})),
        div({className:'rpgx-bude-report-grid'},h('label',null,'Name / Nickname *',h('input',{type:'text',value:nickname,onChange:e=>setNickname(e.target.value),autoComplete:'name'})),h('label',null,'E-Mail *',h('input',{type:'email',value:email,onChange:e=>setEmail(e.target.value),autoComplete:'email'})),h('label',null,'Instagram (optional)',h('input',{type:'text',value:instagram,onChange:e=>setInstagram(e.target.value),placeholder:'@deinname',autoComplete:'off'}))),
        div({className:'rpgx-community-upload'},
          h('span',{className:'rpgx-community-upload-label'},'Fotos * (1–5)'),
          h('span',{className:'rpgx-community-upload-note'},'Mindestens ein Foto ist Pflicht. Vor dem Upload automatisch optimiert: maximal 2400 px längste Kante und 2 MB je Foto; das Seitenverhältnis bleibt erhalten. JPG, PNG, WebP sowie HEIC/HEIF auf unterstützten Geräten.'),
          h('input',{ref:fileRef,className:'rpgx-community-file-input',type:'file',accept:'image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif',multiple:true,'aria-required':'true',onChange:chooseImages,disabled:processingImages}),
          h('button',{type:'button',className:'rpgx-community-file-button',disabled:processingImages||images.length>=maxFiles,onClick:()=>fileRef.current&&fileRef.current.click()},processingImages?'Fotos werden optimiert …':images.length>=maxFiles?'5 Fotos ausgewählt':'⇧  Dateien auswählen'),
          images.length?h('div',{className:'rpgx-community-file-list'},images.map((file,i)=>div({key:file.name+'-'+file.lastModified+'-'+i,className:'rpgx-community-file-row'},div({className:'rpgx-community-file-info'},span({className:'rpgx-community-file-name'},file.name),span({className:'rpgx-community-file-size'},Math.max(.1,file.size/1024/1024).toFixed(1)+' MB')),button({type:'button',onClick:()=>removeImage(i),'aria-label':file.name+' entfernen'},'×')))):null
        ),
        images.length?h('label',{className:'rpgx-contribution-rights'},h('input',{type:'checkbox',checked:rights,onChange:e=>setRights(e.target.checked)}),h('span',null,'Ich bestätige, dass ich die Rechte an den hochgeladenen Bildern habe und PottFuchs sie veröffentlichen darf. *')):null,
        h('label',{className:'rpgx-contribution-rights'},h('input',{type:'checkbox',checked:terms,onChange:e=>setTerms(e.target.checked)}),h('span',null,'Ich stimme den Einreichungsbedingungen zu. *')),
        h('label',{className:'rpgx-contribution-rights'},h('input',{type:'checkbox',checked:privacy,onChange:e=>setPrivacy(e.target.checked)}),h('span',null,'Ich stimme der Speicherung meiner Angaben zur Bearbeitung dieses Beitrags gemäß Datenschutzerklärung zu. *')),
        h('button',{type:'button',className:'rpgx-bude-primary',disabled:status==='sending'||processingImages,onClick:submit},processingImages?'Fotos werden optimiert …':status==='sending'?'Wird gesendet …':'Beitrag einreichen'),status&&status!=='sending'&&!processingImages?h('p',{className:'rpgx-bude-report-status',role:'status'},status):null
      )
    ));
  }

  function communityPlainText(value){return String(value||'').replace(/<[^>]*>/g,' ').replace(/&nbsp;/gi,' ').replace(/\s+/g,' ').trim();}

  function CommunityStoryGallery({story}){
    const initial=()=>{
      const out=[];const add=url=>{url=String(url||'').trim();if(url&&!out.includes(url))out.push(url);};
      (Array.isArray(story&&story.images)?story.images:[]).forEach(x=>add(typeof x==='string'?x:(x&&x.url)||''));
      (Array.isArray(story&&story.imageUrls)?story.imageUrls:[]).forEach(add);
      add(story&&story.imageUrl);
      return out;
    };
    const [images,setImages]=useState(initial),[index,setIndex]=useState(0);
    useEffect(()=>{
      let alive=true;setImages(initial());setIndex(0);
      if(!story||!story.id||!RPG.api.communityStoryMedia)return()=>{alive=false;};
      RPG.api.communityStoryMedia(story.id).then(items=>{
        if(!alive||!Array.isArray(items)||!items.length)return;
        const urls=[];const add=url=>{url=String(url||'').trim();if(url&&!urls.includes(url))urls.push(url);};
        items.forEach(m=>{const sizes=m&&m.media_details&&m.media_details.sizes||{};add((sizes.large&&sizes.large.source_url)||(sizes.medium_large&&sizes.medium_large.source_url)||m.source_url);});
        initial().forEach(add);if(urls.length)setImages(urls.slice(0,5));
      }).catch(()=>{});
      return()=>{alive=false;};
    },[story&&story.id]);
    if(!images.length)return null;
    const active=images[Math.min(index,images.length-1)];
    return div({className:'rpgx-community-gallery'},
      div({className:'rpgx-community-gallery-main'},h('img',{src:active,alt:'Community-Foto zu '+(story.title||'diesem Ort'),loading:'lazy',decoding:'async'}),images.length>1?span({className:'rpgx-community-gallery-count'},(index+1)+' / '+images.length):null),
      images.length>1?div({className:'rpgx-community-gallery-thumbs'},images.map((url,i)=>button({type:'button',key:url,className:i===index?'is-active':'',onClick:()=>setIndex(i),'aria-label':'Foto '+(i+1)+' anzeigen'},h('img',{src:url,alt:'',loading:'lazy'})))):null
    );
  }

  function CommunityStoryTeaser({story,active,onToggle}){
    const text=communityPlainText(story.content);const excerpt=text.length>118?text.slice(0,115).trim()+'…':text;
    return button({type:'button',className:'rpgx-community-story-teaser'+(active?' is-active':''),onClick:onToggle,'aria-expanded':active?'true':'false'},
      story.imageUrl?h('img',{src:story.imageUrl,alt:'',loading:'lazy',decoding:'async'}):span({className:'rpgx-community-story-placeholder','aria-hidden':'true'},'PottFuchs'),
      span({className:'rpgx-community-story-teaser-body'},
        span({className:'rpgx-community-story-title'},story.title),
        span({className:'rpgx-community-by'},'von '+(story.nickname||'PottFuchs-Community')),
        excerpt?span({className:'rpgx-community-story-excerpt'},excerpt):null,
        span({className:'rpgx-community-story-open'},active?'Beitrag geöffnet ↑':'Beitrag lesen →')
      )
    );
  }

  function communityInstagram(value){
    const raw=String(value||'').trim();if(!raw)return null;
    let handle=raw.replace(/^https?:\/\/(?:www\.)?instagram\.com\//i,'').replace(/^@/,'').split(/[\/?#]/)[0].trim();
    if(!/^[A-Za-z0-9._]{1,30}$/.test(handle))return {label:raw,url:''};
    return {label:'@'+handle,url:'https://www.instagram.com/'+handle+'/'};
  }

  function CommunityStoryExpanded({story,onClose}){
    const insta=communityInstagram(story.instagram);
    const byline=['von '+(story.nickname||'PottFuchs-Community')];
    return h('article',{className:'rpgx-community-story-expanded'},
      div({className:'rpgx-community-story-expanded-head'},div(null,h('h4',null,story.title),h('p',{className:'rpgx-community-by'},byline[0],insta?' · ':null,insta?(insta.url?h('a',{href:insta.url,target:'_blank',rel:'ugc nofollow noopener noreferrer'},insta.label):insta.label):null)),button({type:'button',onClick:onClose,'aria-label':'Beitrag einklappen'},'×')),
      h(CommunityStoryGallery,{story}),
      h('div',{className:'rpgx-community-story-copy',dangerouslySetInnerHTML:{__html:String(story.content||'')}})
    );
  }

  function CommunityStories({item,onWrite}){
    const [stories,setStories]=useState([]),[loaded,setLoaded]=useState(false),[loadError,setLoadError]=useState(false),[openId,setOpenId]=useState(null),[reloadKey,setReloadKey]=useState(0);
    useEffect(()=>{let alive=true;setLoaded(false);setLoadError(false);setOpenId(null);RPG.api.communityStories(item.id).then(r=>{if(alive){setStories(Array.isArray(r.items)?r.items:[]);setLoaded(true);}}).catch(()=>{if(alive){setStories([]);setLoadError(true);setLoaded(true);}});return()=>{alive=false;};},[item&&item.id,reloadKey]);
    const rows=[];for(let i=0;i<stories.length;i+=2)rows.push(stories.slice(i,i+2));
    return h('section',{className:'rpgx-community-stories'},
      h('div',{className:'rpgx-community-stories-head'},div(null,h('h3',null,'Aus der Community'),h('p',null,stories.length?stories.length+' freigegebene '+(stories.length===1?'Beitrag':'Beiträge'):'Kennst du diesen Ort?')),button({type:'button',onClick:onWrite},'✍️ Beitrag erstellen')),
      !loaded?h('p',{className:'rpgx-community-empty'},'Community-Beiträge werden geladen …'):loadError?div({className:'rpgx-community-load-error',role:'status'},h('p',null,'Community-Beiträge konnten gerade nicht geladen werden.'),h('button',{type:'button',onClick:()=>setReloadKey(x=>x+1)},'Erneut versuchen')):stories.length?div({className:'rpgx-community-story-list'+(stories.length===1?' is-single':'')},rows.map((row,rowIndex)=>div({key:'row-'+rowIndex,className:'rpgx-community-story-row'},
        row.map(story=>h(CommunityStoryTeaser,{key:story.id,story,active:openId===story.id,onToggle:()=>setOpenId(current=>current===story.id?null:story.id)})),
        row.some(story=>story.id===openId)?h(CommunityStoryExpanded,{key:'expanded-'+openId,story:row.find(story=>story.id===openId),onClose:()=>setOpenId(null)}):null
      ))):h('p',{className:'rpgx-community-empty'},'Noch keine freigegebenen Beiträge. Sei der Erste, der etwas zu diesem Ort beiträgt.')
    );
  }

  function DetailGallery({item,image,onLoad,onError,fallback}){
    const list=[];
    (Array.isArray(item.galleryImages)?item.galleryImages:[]).forEach(x=>{if(x&&x.url&&!list.some(v=>v.url===x.url))list.push(x);});
    if(image&&!list.some(v=>v.url===image))list.unshift({url:image,credit:''});
    const [index,setIndex]=useState(0);
    useEffect(()=>setIndex(0),[item&&item.id,list.length]);
    if(!list.length)return fallback||div({className:'w-full h-full flex items-center justify-center bg-slate-950 text-slate-500 font-black'},'PottFuchs');
    const current=list[Math.min(index,list.length-1)];
    return h(RPG.Fragment,null,
      h('img',{src:current.url,alt:'Foto von '+item.title,className:'w-full h-full object-cover',style:mediaCropStyle(cropForImage(item,current.url,'detail',current)),decoding:'async',referrerPolicy:'no-referrer',onLoad:mediaCropOnLoad(cropForImage(item,current.url,'detail',current),onLoad),onError:onError}),
      current.credit?h('span',{className:'rpgx-gallery-credit'},current.credit):null,
      list.length>1?h(RPG.Fragment,null,
        h('button',{type:'button',className:'rpgx-gallery-nav prev',onClick:e=>{e.stopPropagation();setIndex(i=>(i-1+list.length)%list.length)},'aria-label':'Vorheriges Bild'},'‹'),
        h('button',{type:'button',className:'rpgx-gallery-nav next',onClick:e=>{e.stopPropagation();setIndex(i=>(i+1)%list.length)},'aria-label':'Nächstes Bild'},'›'),
        h('div',{className:'rpgx-gallery-dots'},list.map((_,i)=>h('button',{type:'button',key:i,className:i===index?'active':'',onClick:e=>{e.stopPropagation();setIndex(i)},'aria-label':'Bild '+(i+1)})))
      ):null
    );
  }

  function usefulCardBadge(value,item) {
    const text=String(value||'').replace(/\s+/g,' ').trim();
    if(!text)return '';
    const generic=/^(?:food(?:\s*&\s*drinks)?|events?|kultur|culture|aktiv(?:itäten)?|activities|highlights?|divers|sonstiges?|allgemein|location|place)$/i;
    if(generic.test(text))return '';
    if(item&&item.city&&text.toLocaleLowerCase('de')===String(item.city).trim().toLocaleLowerCase('de'))return '';
    return text;
  }

  function eventTypeBadgeMeta(item) {
    if(!item||item.category!=='events'||RPG.settings.eventTypeBadgesEnabled===false)return null;
    const bits=[];
    const push=value=>{
      if(value==null)return;
      if(typeof value==='string'||typeof value==='number'){const v=String(value).trim();if(v)bits.push(v);return;}
      if(Array.isArray(value)){value.forEach(push);return;}
      if(typeof value==='object'){
        ['name','title','label','value','category','type','classification','eventType'].forEach(k=>{if(value[k]!=null)push(value[k]);});
      }
    };
    const info=item.eventInfo&&typeof item.eventInfo==='object'?item.eventInfo:{};
    push(info.categories);push(info.features);push(info.keywords);
    const raw=item.raw&&typeof item.raw==='object'?item.raw:{};
    push(raw.categories);push(raw.keywords);push(raw.features);push(raw.type);push(raw.types);push(raw.classification);push(raw.classifications);
    push(item.title);
    const text=bits.join(' ').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('de');
    if(!text)return null;

    // Specific signals win over broad umbrella categories. If no explicit signal
    // exists we intentionally render no badge rather than inventing an event type.
    if(/raetsel|ratsel|escape|quiz|schnitzeljagd|detektiv/.test(text))return {key:'puzzle',label:'Rätsel'};
    if(/fuehrung|fuhrung|guided\s+tour|rundgang|stadtrundgang|werksfuehrung|werksfuhrung/.test(text))return {key:'tour',label:'Führung'};
    if(/ausstellung|exhibition|vernissage|galerie|kunstschau/.test(text))return {key:'exhibition',label:'Ausstellung'};
    if(/konzert|live\s*music|musikveranstaltung|chor|orchester|\bband\b|\bjazz\b|\brock\b|\bpop\b/.test(text))return {key:'concert',label:'Konzert'};
    if(/theater|schauspiel|operette|\boper\b|musical|performance|buehne|buhne/.test(text))return {key:'stage',label:'Bühne'};
    if(/workshop|kurs|seminar|mitmach|atelierkurs/.test(text))return {key:'workshop',label:'Workshop'};
    if(/vortrag|lesung|lecture|talk|diskussion|podium/.test(text))return {key:'talk',label:'Vortrag'};
    if(/kino|filmvorfuehrung|filmvorfuhrung|screening|\bfilm\b/.test(text))return {key:'film',label:'Film'};
    if(/kinder|familien|familie|kids|ferienprogramm/.test(text))return {key:'family',label:'Familie'};
    if(/flohmarkt|troedelmarkt|trodelmarkt|weihnachtsmarkt|\bmarkt\b|stadtfest|strassenfest|straßenfest|kirmes|volksfest/.test(text))return {key:'market',label:'Markt/Fest'};
    if(/sport|marathon|laufveranstaltung|turnier|rennen|fussball|fußball|handball|basketball|radsport/.test(text))return {key:'sport',label:'Sport'};
    if(/party|clubnacht|nightlife|disco|tanzparty/.test(text))return {key:'party',label:'Party'};
    return null;
  }

  function EventTypeIcon({type}) {
    const common={viewBox:'0 0 24 24',width:'12',height:'12',fill:'none',stroke:'currentColor',strokeWidth:'2',strokeLinecap:'round',strokeLinejoin:'round','aria-hidden':'true',focusable:'false'};
    const paths={
      puzzle:[['circle',{cx:'11',cy:'11',r:'7'}],['path',{d:'m20 20-3.5-3.5'}],['path',{d:'M9.5 9a1.8 1.8 0 1 1 2.8 1.5c-.8.5-1.3.9-1.3 1.8'}],['path',{d:'M11 15h.01'}]],
      tour:[['path',{d:'M5 19V5'}],['path',{d:'M5 6c4-3 7 3 11 0v8c-4 3-7-3-11 0'}]],
      exhibition:[['rect',{x:'4',y:'4',width:'16',height:'16',rx:'1'}],['path',{d:'m8 15 3-3 2 2 3-4 2 5'}]],
      concert:[['path',{d:'M9 18V5l10-2v13'}],['circle',{cx:'6',cy:'18',r:'3'}],['circle',{cx:'16',cy:'16',r:'3'}]],
      stage:[['path',{d:'M4 20V6h16v14'}],['path',{d:'M4 10h16'}],['path',{d:'M9 6v4M15 6v4'}]],
      workshop:[['path',{d:'m14.7 6.3 3 3'}],['path',{d:'m5 19 4.5-1 8.8-8.8a2.1 2.1 0 0 0-3-3L6.5 15z'}]],
      talk:[['path',{d:'M5 4h14v11H9l-4 4z'}],['path',{d:'M9 8h6M9 11h4'}]],
      film:[['rect',{x:'3',y:'6',width:'18',height:'12',rx:'2'}],['path',{d:'m8 6 2-3M14 6l2-3M3 10h18'}]],
      family:[['circle',{cx:'9',cy:'8',r:'3'}],['circle',{cx:'17',cy:'9',r:'2'}],['path',{d:'M3 20c0-4 2.5-6 6-6s6 2 6 6'}],['path',{d:'M15 14c3 0 5 2 5 5'}]],
      market:[['path',{d:'M4 10h16l-2-5H6z'}],['path',{d:'M6 10v9h12v-9M9 19v-5h6v5'}]],
      sport:[['circle',{cx:'12',cy:'12',r:'9'}],['path',{d:'m8 4 4 3 4-3M3 11l4 3-1 5M21 11l-4 3 1 5M8 14h8'}]],
      party:[['path',{d:'m5 19 5-14 4 4z'}],['path',{d:'M15 4h.01M19 8h.01M16 13h.01'}],['path',{d:'m14 3 1-2M20 5l2-1M18 11l3 1'}]]
    };
    const nodes=paths[type]||[];
    return h('svg',common,nodes.map((p,i)=>h(p[0],Object.assign({key:i},p[1]))));
  }

  function EventGlanceIcon({kind,type}) {
    if(kind==='type'&&type)return h(EventTypeIcon,{type});
    const common={viewBox:'0 0 24 24',width:'15',height:'15',fill:'none',stroke:'currentColor',strokeWidth:'2',strokeLinecap:'round',strokeLinejoin:'round','aria-hidden':'true',focusable:'false'};
    const paths={
      free:[['circle',{cx:'12',cy:'12',r:'9'}],['path',{d:'m8 12 2.5 2.5L16 9'}]],
      price:[['path',{d:'M20 12 12 20 4 12l8-8 8 8Z'}],['path',{d:'M12 8v8M9.5 10.5h4a1.5 1.5 0 0 1 0 3h-3a1.5 1.5 0 0 0 0 3h4'}]],
      duration:[['circle',{cx:'12',cy:'12',r:'9'}],['path',{d:'M12 7v5l3 2'}]],
      audience:[['circle',{cx:'9',cy:'8',r:'3'}],['circle',{cx:'17',cy:'9',r:'2'}],['path',{d:'M3 20c0-4 2.5-6 6-6s6 2 6 6M15 14c3 0 5 2 5 5'}]],
      access:[['circle',{cx:'10',cy:'5',r:'2'}],['path',{d:'M10 8v5h4l3 5M7 11a6 6 0 1 0 8 7'}]],
      indoor:[['path',{d:'m3 11 9-7 9 7'}],['path',{d:'M5 10v10h14V10M9 20v-6h6v6'}]],
      outdoor:[['circle',{cx:'12',cy:'12',r:'4'}],['path',{d:'M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4'}]],
      language:[['path',{d:'M4 5h11v8H8l-4 4z'}],['path',{d:'M12 17h8v-7h-3'}]]
    };
    const nodes=paths[kind]||[];
    return h('svg',common,nodes.map((p,i)=>h(p[0],Object.assign({key:i},p[1]))));
  }

  function EventTypeBadge({item}) {
    const meta=eventTypeBadgeMeta(item);
    if(!meta)return null;
    return span({className:'rpgx-event-type-badge is-'+meta.key,title:meta.label},h(EventTypeIcon,{type:meta.key}),span(null,meta.label));
  }

  function cardHref(item){return String(RPG.buildShareUrl(item)||'#');}
  function openCardClick(e,item,onOpen){
    if(e.button!==0||e.metaKey||e.ctrlKey||e.shiftKey||e.altKey)return;
    e.preventDefault();onOpen(item);
  }

  function Card({item,onOpen,large}) {
    const date=eventCardDate(item);
    const openingPilot=RPG.isOpeningHoursPilot(item);
    const openingStatus=openingPilot?RPG.placeOpeningStatus(item,item.openingHours):null;
    const subtitle=usefulCardBadge(item.subtitle,item);
    const foodPrice=item.category==='food'?String(item.priceTier||''):'';
    const foodLabels=item.category==='food'&&Array.isArray(item.labels)?item.labels.map(v=>usefulCardBadge(v,item)).filter(Boolean).slice(0,3):[];
    const media=useMedia(item),image=media.image;
    const eventMediaKind=media.runtimeKind||item.displayMediaKind||item.mediaKind||'';
    const eventMediaLabel=image?(media.runtimeLabel||item.displayMediaLabel||item.mediaLabel||''):'';
    const communityPhoto=item.category==='activities'&&media.settled&&!image;
    const metaNodes=[];
    if(item.city)metaNodes.push(span({key:'city',className:'rpgx-card-city'},'📍 '+item.city));
    if(Number.isFinite(item.distanceKm))metaNodes.push(span({key:'distance',className:'rpgx-card-distance'},'↗ '+RPG.formatDistance(item.distanceKm)));
    if(date)metaNodes.push(span({key:'date',className:'rpgx-card-date'},'📅 '+date));
    const mediaNode=image?h('img',{src:image,alt:'',style:mediaCropStyle(cropForImage(item,image,'card',null)),loading:large?'eager':'lazy',decoding:'async',referrerPolicy:'no-referrer',onLoad:mediaCropOnLoad(cropForImage(item,image,'card',null),media.onLoad),onError:media.onError}):communityPhoto?h(CommunityPhotoPlaceholder):div({className:'rpgx-image-fallback'},item.category==='food'?'🍴':item.category==='events'?'🎟️':item.category==='culture'?'🎨':item.category==='activities'?'⚡':'🏭');
    const content=h(RPG.Fragment,null,
      div({className:'rpgx-card-media'},mediaNode,item.category==='events'?h(EventTypeBadge,{item}):null,item.category==='events'&&eventMediaLabel?span({className:'rpgx-event-media-kind '+(eventMediaKind==='symbolic'?'is-symbolic':eventMediaKind==='context'?'is-context':'is-original')},eventMediaLabel):null,item.routeIndustrial&&usefulCardBadge(item.routeBadge,item)?span({className:'rpgx-route-type-badge'},usefulCardBadge(item.routeBadge,item)):null),
      div({className:'rpgx-card-overlay'},
        h('h3',null,String(RPG.displayPlaceTitle(item)||item.title||'PottFuchs').trim()),
        subtitle?h('p',null,subtitle):null,
        openingStatus&&openingStatus.shortLabel?span({className:'rpgx-opening-chip is-'+openingStatus.state},span({'aria-hidden':'true'},'●'),openingStatus.shortLabel):null,
        item.category==='food'&&(foodPrice||foodLabels.length)?div({className:'rpgx-food-facts'},
          foodPrice?span({className:'rpgx-food-price',title:'Preisniveau'},foodPrice):null,
          foodLabels.map(v=>span({key:v,className:'rpgx-food-tag'},v))
        ):null,
        metaNodes.length?div({className:'rpgx-card-meta'},metaNodes):null
      )
    );
    if(!communityPhoto)return a({href:cardHref(item),className:'rpgx-card'+(large?' rpgx-card-large':''),onClick:e=>openCardClick(e,item,onOpen)},content);
    return div({className:'rpgx-card rpgx-card-community-photo'+(large?' rpgx-card-large':'')},
      button({type:'button',className:'rpgx-card-open',onClick:()=>onOpen(item),'aria-label':String(RPG.displayPlaceTitle(item)||item.title||'Ort')+' öffnen'},content),
      button({type:'button',className:'rpgx-community-photo-cta',onClick:()=>onOpen(photoContributionItem(item)),'aria-label':'Foto für '+String(item.title||'diesen Ort')+' beisteuern'},span(null,'Foto beisteuern'))
    );
  }

  function SectionHeader({category,location,radius,url,routeLink}) {
    const meta=RPG.CATEGORIES[category];
    return div({className:'rpgx-section-head'},div(null,h('h2',null,meta.title),h('p',null,meta.subtitle)),div({className:'rpgx-section-actions'},location?span({className:'rpgx-section-location'},RPG.locationLabel(location)+' · '+radius+' km'):null,routeLink?a({href:RPG.settings.industrialRoutePageUrl||'/route-industriekultur/',className:'rpgx-text-link'},'Route Industriekultur'):null,url?a({href:url,className:'rpgx-all-link'},'Alle ansehen →'):null));
  }

  // V96: The source-owned React app now reproduces the exact visual language of
  // the proven V94 WordPress home modules instead of flattening every category
  // into one generic card layout.
  function LegacyHomeSectionHeader({category,location,radius,url,onLocation,subtitle,onAllClick}) {
    const meta=RPG.CATEGORIES[category] || {title:category,subtitle:''};
    return div({className:'rg-home-category-section-head'},
      div({className:'rg-home-category-section-title'},h('h2',null,meta.title),h('p',null,subtitle||meta.subtitle)),
      div({className:'rg-home-category-section-actions'},
        button({type:'button',className:'rg-home-live-location rg-home-location-trigger',onClick:onLocation,'aria-label':'Standort ändern'},location?[span({className:'rg-home-location-text'},RPG.locationLabel(location)+' · '+radius+' km'),span({className:'rg-home-location-edit','aria-hidden':'true'},'✎')]:null),
        url?a({className:'rg-home-all-button',href:url,onClick:onAllClick||undefined},'Alle ansehen ',span({'aria-hidden':'true'},'→')):null
      )
    );
  }

  function homeMeta(item) {
    const nodes=[];
    if(item.city) nodes.push(span({key:'city',className:'rg-home-meta-city'},'📍 '+item.city));
    if(Number.isFinite(item.distanceKm)) nodes.push(span({key:'distance',className:'rg-home-meta-distance'},'↗ '+RPG.formatDistance(item.distanceKm)));
    if(item.category==='events'&&item.start){
      const txt=eventCardDate(item);
      nodes.push(span({key:'date',className:'rg-home-meta-date'},'📅 '+txt));
    }
    return nodes.length?span({className:'rg-home-mag-meta'},nodes):null;
  }

  function LegacyImage({item,eager,fallback}) {
    const media=useMedia(item), image=media.image;
    return h(RPG.Fragment,null,
      span({className:'rg-home-mag-fallback','aria-hidden':'true',style:image?{display:'none'}:null},fallback),
      image?h('img',{src:image,alt:'',style:mediaCropStyle(cropForImage(item,image,'card',null)),loading:eager?'eager':'lazy',decoding:'async',referrerPolicy:'no-referrer',onLoad:mediaCropOnLoad(cropForImage(item,image,'card',null),media.onLoad),onError:media.onError}):null
    );
  }

  function LegacyOverlayCard({item,onOpen,className,eager,timeHint}) {
    const fallback=item.category==='food'?'🍔':item.category==='events'?'📅':item.category==='activities'?'🚴':'👀';
    const labels=(item.category==='food'||item.category==='activities'||item.category==='highlights')&&Array.isArray(item.labels)?item.labels.map(v=>usefulCardBadge(v,item)).filter(v=>v&&!/^(Route(?: der)? Industriekultur|Ankerpunkt|Panorama|Siedlung|Primärstandort)$/i.test(v)).slice(0,2):[];
    const foodPrice=item.category==='food'?String(item.priceTier||''):'';
    const openingPilot=RPG.isOpeningHoursPilot(item);
    const openingStatus=openingPilot?RPG.placeOpeningStatus(item,item.openingHours):null;
    const media=useMedia(item),image=media.image;
    const eventMediaKind=media.runtimeKind||item.displayMediaKind||item.mediaKind||'';
    const eventMediaLabel=image?(media.runtimeLabel||item.displayMediaLabel||item.mediaLabel||''):'';
    const communityPhoto=item.category==='activities'&&media.settled&&!image;
    const mediaNode=image?h('img',{src:image,alt:'',style:mediaCropStyle(cropForImage(item,image,'card',null)),loading:eager?'eager':'lazy',decoding:'async',referrerPolicy:'no-referrer',onLoad:mediaCropOnLoad(cropForImage(item,image,'card',null),media.onLoad),onError:media.onError}):communityPhoto?h(CommunityPhotoPlaceholder):span({className:'rg-home-mag-fallback','aria-hidden':'true'},fallback);
    const content=span({className:'rg-home-mag-media'},
      mediaNode,
      item.category==='events'?h(EventTypeBadge,{item}):null,
      item.category==='events'&&eventMediaLabel?span({className:'rpgx-event-media-kind '+(eventMediaKind==='symbolic'?'is-symbolic':eventMediaKind==='context'?'is-context':'is-original')},eventMediaLabel):null,
      span({className:'rg-home-mag-shade'}),
      span({className:'rg-home-mag-copy'},
        item.category==='events'&&timeHint?span({className:'rg-home-event-time-hint'},timeHint):null,
        h('strong',null,RPG.displayPlaceTitle(item)||item.title),
        item.category==='food'&&(foodPrice||labels.length)?span({className:'rg-home-experience-tags rg-home-food-facts'},
          foodPrice?span({className:'is-price',title:'Preisniveau'},foodPrice):null,
          labels.map(v=>span({key:v},v))
        ):labels.length?span({className:'rg-home-experience-tags'},labels.map(v=>span({key:v},v))):null,
        openingStatus&&openingStatus.shortLabel?span({className:'rg-home-opening-chip is-'+openingStatus.state},span({'aria-hidden':'true'},'●'),openingStatus.shortLabel):null,
        homeMeta(item)
      )
    );
    if(!communityPhoto)return a({href:cardHref(item),className,onClick:e=>openCardClick(e,item,onOpen),'aria-label':item.category==='events'&&timeHint?String(item.title||'')+' – '+timeHint:item.title},content);
    return div({className:className+' rpgx-home-community-photo'},
      button({type:'button',className:'rpgx-home-card-open',onClick:()=>onOpen(item),'aria-label':String(RPG.displayPlaceTitle(item)||item.title)+' öffnen'},content),
      button({type:'button',className:'rpgx-community-photo-cta is-home',onClick:()=>onOpen(photoContributionItem(item)),'aria-label':'Foto für '+item.title+' beisteuern'},span(null,'Foto beisteuern'))
    );
  }

  function LegacyCultureRow({item,onOpen}) {
    const media=useMedia(item), image=media.image;
    const sub=usefulCardBadge(item.subtitle,item);
    const openingPilot=RPG.isOpeningHoursPilot(item);
    const openingStatus=openingPilot?RPG.placeOpeningStatus(item,item.openingHours):null;
    return h('article',{className:'rg-home-culture-row'},
      a({href:cardHref(item),className:'rg-home-culture-main',onClick:e=>openCardClick(e,item,onOpen)},
        span({className:'rg-home-culture-media'},
          span({className:'rg-home-mag-fallback','aria-hidden':'true',style:image?{display:'none'}:null},'🎨'),
          image?h('img',{src:image,alt:'',style:mediaCropStyle(cropForImage(item,image,'card',null)),loading:'lazy',decoding:'async',referrerPolicy:'no-referrer',onLoad:mediaCropOnLoad(cropForImage(item,image,'card',null),media.onLoad),onError:media.onError}):null
        ),
        span({className:'rg-home-culture-copy'},
          h('strong',null,RPG.displayPlaceTitle(item)||item.title),
          sub?span({className:'rg-home-culture-tags'},span(null,sub)):null,
          openingStatus&&openingStatus.shortLabel?span({className:'rg-home-opening-chip is-'+openingStatus.state},span({'aria-hidden':'true'},'●'),openingStatus.shortLabel):null,
          homeMeta(item)
        ),
        span({className:'rg-home-culture-details'},'Details →')
      )
    );
  }

  function routeTypeLabel(value) {
    const v=String(value||'').toLowerCase();
    if(v==='anchor'||v==='ankerpunkt')return 'Ankerpunkt';
    if(v==='panorama')return 'Panorama';
    if(v==='settlement'||v==='siedlung')return 'Siedlung';
    return String(value||'');
  }

  // V174: Restore the proven event calendar actions from the pre-source-React
  // build. Apple/iPhone receives a standards-compliant .ics file; Android uses
  // Google Calendar with the same title, time, location and description.
  function calendarUtc(value){
    const date=value instanceof Date?value:new Date(value);
    return isNaN(date.getTime())?'':date.toISOString().replace(/[-:]/g,'').replace(/\.\d{3}Z$/,'Z');
  }

  function calendarEscape(value){
    return String(value||'').replace(/\\/g,'\\\\').replace(/\r?\n/g,'\\n').replace(/,/g,'\\,').replace(/;/g,'\\;');
  }

  function calendarDateOnly(value){
    const date=value instanceof Date?value:new Date(value);
    if(isNaN(date.getTime()))return '';
    const pad=n=>String(n).padStart(2,'0');
    return String(date.getFullYear())+pad(date.getMonth()+1)+pad(date.getDate());
  }

  function eventDateIsMidnight(value){
    const date=value instanceof Date?value:new Date(value);
    return !isNaN(date.getTime())&&date.getHours()===0&&date.getMinutes()===0&&date.getSeconds()===0;
  }

  function eventAllDayValue(item,start,end){
    if(item&&item.allDayKnown)return !!item.allDay;
    if(item&&item.allDay===true)return true;
    const a=start instanceof Date?start:new Date(start||0),b=end instanceof Date?end:new Date(end||0);
    const validA=!isNaN(a.getTime())&&a.getTime()>0,validB=!isNaN(b.getTime())&&b.getTime()>a.getTime();
    return validA&&eventDateIsMidnight(a)&&(!validB||eventDateIsMidnight(b));
  }

  function eventCardDate(item){
    if(!item||!item.start)return '';
    const start=item.start instanceof Date?item.start:new Date(item.start);if(isNaN(start.getTime()))return '';
    const end=item.end instanceof Date?item.end:new Date(item.end||0);
    const hasEnd=!isNaN(end.getTime())&&end.getTime()>start.getTime();
    const allDay=eventAllDayValue(item,start,end);

    // Running multi-day events must not look expired just because their original
    // start date is in the past. For date-only feeds that encode the end as the
    // exclusive midnight of the following day, subtract one second for display
    // purposes only so a one-day event remains a one-day card.
    if(hasEnd&&start.getTime()<=Date.now()&&end.getTime()>=Date.now()){
      let displayEnd=end;
      if(allDay&&eventDateIsMidnight(end))displayEnd=new Date(end.getTime()-1000);
      const differentDay=start.getFullYear()!==displayEnd.getFullYear()||start.getMonth()!==displayEnd.getMonth()||start.getDate()!==displayEnd.getDate();
      const genuinelyMultiDay=allDay||(end.getTime()-start.getTime())>=24*60*60*1000;
      if(differentDay&&genuinelyMultiDay){
        return 'bis '+new Intl.DateTimeFormat('de-DE',{day:'2-digit',month:'2-digit'}).format(displayEnd);
      }
    }

    return new Intl.DateTimeFormat('de-DE',allDay?{day:'2-digit',month:'2-digit'}:{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'}).format(start);
  }

  function eventCalendarData(item){
    if(!item||item.category!=='events')return null;
    const start=item.start instanceof Date?item.start:new Date(item.start||0);
    if(isNaN(start.getTime())||start.getTime()<=0)return null;
    let end=item.end instanceof Date?item.end:new Date(item.end||0);
    const hasValidEnd=!isNaN(end.getTime())&&end.getTime()>start.getTime();
    // ET4 often uses 00:00 -> 00:00 next day for date-only occurrences.
    // Treat those values as day boundaries, never as evidence of a midnight start.
    const allDay=eventAllDayValue(item,start,end);
    if(allDay){
      if(!hasValidEnd){end=new Date(start);end.setDate(end.getDate()+1);}
    }else if(!hasValidEnd){
      // No invented duration for timed ET4 events without an explicit end.
      end=null;
    }
    const location=String(item.address||item.city||'').trim();
    const description=String(item.description||'').replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim();
    const officialWebsite=String(item.website||'').trim();
    const canonicalWebsite=String(item.canonicalUrl||RPG.buildShareUrl(item)||'').trim();
    const details=[description,officialWebsite?'Offizielle Event-Seite: '+officialWebsite:'',canonicalWebsite?'PottFuchs: '+canonicalWebsite:''].filter(Boolean).join('\n\n');
    return {title:String(item.title||'Veranstaltung').trim(),start,end,allDay,location,description:details,website:canonicalWebsite||officialWebsite};
  }

  function downloadEventIcs(item){
    const data=eventCalendarData(item);
    if(!data)return false;
    const start=data.allDay?calendarDateOnly(data.start):calendarUtc(data.start),end=data.end?(data.allDay?calendarDateOnly(data.end):calendarUtc(data.end)):'',stamp=calendarUtc(new Date());
    if(!start)return false;
    const uidBase=String(item.id||data.title+'-'+data.start.getTime()).replace(/[^a-zA-Z0-9_-]+/g,'-').slice(0,80)||'event';
    const lines=[
      'BEGIN:VCALENDAR',
      'VERSION:2.0',
      'PRODID:-//RuhrpottGuru//Event Calendar//DE',
      'CALSCALE:GREGORIAN',
      'METHOD:PUBLISH',
      'BEGIN:VEVENT',
      'UID:'+uidBase+'@ruhrpottguru.de',
      'DTSTAMP:'+stamp,
      (data.allDay?'DTSTART;VALUE=DATE:':'DTSTART:')+start,
      end?((data.allDay?'DTEND;VALUE=DATE:':'DTEND:')+end):null,
      'SUMMARY:'+calendarEscape(data.title),
      data.location?'LOCATION:'+calendarEscape(data.location):null,
      data.description?'DESCRIPTION:'+calendarEscape(data.description):null,
      data.website?'URL:'+data.website:null,
      'END:VEVENT',
      'END:VCALENDAR'
    ].filter(Boolean);
    const blob=new Blob([lines.join('\r\n')],{type:'text/calendar;charset=utf-8'});
    const url=URL.createObjectURL(blob);
    const link=document.createElement('a');
    const filename=data.title.replace(/[\/:*?"<>|]+/g,'-').replace(/\s+/g,' ').trim().slice(0,70)||'PottFuchs-Event';
    link.href=url;
    link.download=filename+'.ics';
    link.rel='noopener';
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(()=>URL.revokeObjectURL(url),1500);
    return true;
  }

  function openGoogleCalendar(item){
    const data=eventCalendarData(item);
    if(!data)return false;
    const start=data.allDay?calendarDateOnly(data.start):calendarUtc(data.start),end=data.end?(data.allDay?calendarDateOnly(data.end):calendarUtc(data.end)):start;
    if(!start)return false;
    const params=new URLSearchParams({
      action:'TEMPLATE',
      text:data.title,
      dates:start+'/'+end,
      details:data.description,
      location:data.location,
      ctz:'Europe/Berlin'
    });
    window.open('https://calendar.google.com/calendar/render?'+params.toString(),'_blank','noopener,noreferrer');
    return true;
  }

  // V345: One sharing controller/dialog for standard details and the independent Buedchen detail.
  // The existing normal-share payload remains unchanged. Invitations are text only;
  // no user location, active-session record, or lifetime is created.
  function useSharing(item){
    const [mode,setMode]=useState(null);
    const [inviteMessage,setInviteMessage]=useState('Ich bin gerade hier. Wer kommt rum? 🍻');
    const [inviteDuration,setInviteDuration]=useState('60');
    const [copied,setCopied]=useState(false);
    const dialogRef=useRef(null);
    const triggerRef=useRef(null);
    const close=()=>{
      setMode(null);
      window.requestAnimationFrame(()=>{if(triggerRef.current&&triggerRef.current.isConnected)triggerRef.current.focus();});
    };
    useEffect(function(){
      if(!mode)return;
      document.body.classList.add('rpgx-share-dialog-open');
      const dialog=dialogRef.current;
      const selected=dialog&&dialog.querySelector('input[type="radio"]:checked');
      if(selected&&typeof selected.focus==='function')selected.focus();
      const onKey=e=>{
        if(e.key==='Escape'){e.preventDefault();e.stopPropagation();close();}
        else if(e.key==='Tab'&&dialog){
          const focusable=Array.from(dialog.querySelectorAll('button:not([disabled]),input:not([disabled]),textarea:not([disabled])'));
          if(!focusable.length)return;
          const first=focusable[0],last=focusable[focusable.length-1];
          if(e.shiftKey&&(document.activeElement===first||!dialog.contains(document.activeElement))){e.preventDefault();last.focus();}
          else if(!e.shiftKey&&(document.activeElement===last||!dialog.contains(document.activeElement))){e.preventDefault();first.focus();}
        }
      };
      document.addEventListener('keydown',onKey,true);
      return()=>{document.body.classList.remove('rpgx-share-dialog-open');document.removeEventListener('keydown',onKey,true);};
    },[mode]);
    useEffect(function(){setMode(null);},[item&&item.id]);
    const open=(selectedMode,event)=>{
      triggerRef.current=event&&event.currentTarget||null;
      setMode(selectedMode);
      setInviteMessage('Ich bin gerade hier. Wer kommt rum? 🍻');
      setInviteDuration('60');
    };
    const submit=()=>{
      let url=RPG.buildShareUrl(item);
      // Buedchen without a canonical numeric place ID cannot use the /teilen/
      // resolver. Share the actual coordinates instead of a misleading radar homepage.
      // This fallback never changes SEO routes or normal item URL generation.
      if(item&&item.category==='buedchen'&&!RPG.shareDescriptor(item)&&!RPG.safeUrl(item.canonicalUrl)){
        const coords=item.coords||{};
        const lat=Number(coords.lat),lng=Number(coords.lng);
        if(Number.isFinite(lat)&&Number.isFinite(lng)&&Math.abs(lat)<=90&&Math.abs(lng)<=180){
          const p=lat.toFixed(6)+','+lng.toFixed(6);
          url='https://www.openstreetmap.org/?mlat='+encodeURIComponent(lat.toFixed(6))+'&mlon='+encodeURIComponent(lng.toFixed(6))+'#map=17/'+p.replace(',', '/');
        }
      }
      // The original v344 detail payload for the normal mode must remain exact.
      let text=(item.subtitle?item.subtitle+' – ':'')+(item.city||'Ruhrgebiet');
      if(item.category==='events'&&item.start instanceof Date&&!isNaN(item.start)){
        text=new Intl.DateTimeFormat('de-DE',eventAllDayValue(item,item.start,item.end)?{dateStyle:'medium'}:{dateStyle:'medium',timeStyle:'short'}).format(item.start)+(item.city?' · '+item.city:'');
      }
      if(mode==='invite'){
        const durationText=inviteDuration==='30'?'Ich bin ungefähr noch 30 Minuten hier.':inviteDuration==='60'?'Ich bin ungefähr noch 1 Stunde hier.':inviteDuration==='120'?'Ich bin ungefähr noch 2 Stunden hier.':'';
        text=[inviteMessage.trim(),durationText].filter(Boolean).join('\n');
      }
      const payload={title:item.title,text,url};
      if(navigator.share){navigator.share(payload).then(close).catch(close);return;}
      if(navigator.clipboard&&navigator.clipboard.writeText){
        const copyText=mode==='invite'?[text,url].filter(Boolean).join('\n'):url;
        navigator.clipboard.writeText(copyText).then(()=>{setCopied(true);window.setTimeout(()=>setCopied(false),1800);close();}).catch(()=>{});
      }
    };
    return {mode,setMode,inviteMessage,setInviteMessage,inviteDuration,setInviteDuration,copied,dialogRef,open,close,submit};
  }

  function ShareDialog({sharing}){
    if(!sharing||!sharing.mode)return null;
    return div({className:'rpgx-share-dialog-backdrop',onClick:e=>{e.stopPropagation();if(e.target===e.currentTarget)sharing.close();}},
      div({className:'rpgx-share-dialog',ref:sharing.dialogRef,role:'dialog','aria-modal':'true','aria-labelledby':'rpgx-share-dialog-title',onClick:e=>e.stopPropagation()},
        div({className:'rpgx-share-dialog-head'},
          h('h2',{id:'rpgx-share-dialog-title'},sharing.mode==='invite'?'Komm rum, ich bin hier!':'Location teilen'),
          button({type:'button','aria-label':'Teilen-Dialog schließen',onClick:sharing.close},'×')
        ),
        div({className:'rpgx-share-dialog-options',role:'radiogroup','aria-label':'Teilen-Modus'},
          h('label',null,h('input',{type:'radio',name:'rpgx-share-mode',value:'share',checked:sharing.mode==='share',onChange:()=>sharing.setMode('share')}),span(null,'Location teilen')),
          h('label',null,h('input',{type:'radio',name:'rpgx-share-mode',value:'invite',checked:sharing.mode==='invite',onChange:()=>sharing.setMode('invite')}),span(null,'Komm rum, ich bin hier!'))
        ),
        sharing.mode==='invite'?div({className:'rpgx-share-dialog-invite'},
          h('label',{htmlFor:'rpgx-invite-message'},'Deine Nachricht'),
          h('textarea',{id:'rpgx-invite-message',rows:3,maxLength:500,value:sharing.inviteMessage,onChange:e=>sharing.setInviteMessage(e.target.value)}),
          h('fieldset',null,
            h('legend',null,'Wie lange bist du ungefähr noch dort?'),
            [['30','30 Minuten'],['60','1 Stunde'],['120','2 Stunden'],['none','Keine Angabe']].map(([value,label])=>h('label',{key:value},h('input',{type:'radio',name:'rpgx-share-duration',value,checked:sharing.inviteDuration===value,onChange:()=>sharing.setInviteDuration(value)}),span(null,label)))
          )
        ):null,
        div({className:'rpgx-share-dialog-actions'},
          button({type:'button',className:'rpgx-share-dialog-cancel',onClick:sharing.close},'Abbrechen'),
          button({type:'button',className:'rpgx-share-dialog-submit',onClick:sharing.submit},sharing.mode==='invite'?'Einladung teilen':'Jetzt teilen')
        )
      )
    );
  }

  // V96: Source-owned reconstruction of the original AI-Studio detail modal.
  // It intentionally keeps the proven V94 visual hierarchy while removing the
  // image badges requested in V91/V92. Mobile always starts at the top edge.
  function DetailOverlay({item,onClose,onNavigate,onTagFilter,standalone=false,overviewUrl=''}) {
    // V298 semantic replacements are correctness fixes, not decorative list-card
    // diversity. Keep a strict theme-matched replacement in the detail view too.
    // Older display-only diversity variants still return to stored/original media.
    const keepSemanticDisplay=!!(item&&item.displayImageUrl&&String(item.mediaSemanticVersion||'')==='v298'&&String(item.displayMediaReason||'').includes('semantic'));
    const detailMediaItem=item&&item.displayImageUrl&&!keepSemanticDisplay?Object.assign({},item,{displayImageUrl:''}):item;
    const media=useMedia(detailMediaItem), image=media.image;
    const overlayRef=useRef(null);
    const [saved,setSaved]=useState(false);
    const sharing=useSharing(item);
    const [contributionOpen,setContributionOpen]=useState(!!(item&&item._rpgContributionIntent==='photo'));
    const [contributionMode,setContributionMode]=useState(item&&item._rpgContributionIntent==='photo'?'photo':'full');
    const [communityStoryOpen,setCommunityStoryOpen]=useState(false);
    const [foodInfo,setFoodInfo]=useState(null);
    const [relatedSeries,setRelatedSeries]=useState(null);
    const [relatedExpanded,setRelatedExpanded]=useState(false);
    const [relatedLoadingId,setRelatedLoadingId]=useState(0);
    const [relatedNavError,setRelatedNavError]=useState('');

    useEffect(function(){
      const photoIntent=!!(item&&item._rpgContributionIntent==='photo');
      setContributionMode(photoIntent?'photo':'full');
      setContributionOpen(photoIntent);
    },[item&&item.id,item&&item._rpgContributionIntent]);
    useEffect(function(){
      if(!item)return;
      if(!standalone){
        document.documentElement.classList.add('rg-direct-detail-open');
        document.body.classList.add('rg-direct-detail-open');
      }
      try {
        const list=JSON.parse(RPG.storage.get('ruhrpottguru_favorites_v1')||'[]');
        setSaved(Array.isArray(list)&&list.some(x=>String(x&&x.id)===String(item.id)));
      } catch(_) { setSaved(false); }
      const esc=e=>{if(!standalone&&e.key==='Escape')onClose();};
      if(!standalone)document.addEventListener('keydown',esc);
      if(!standalone)window.requestAnimationFrame(()=>{if(overlayRef.current)overlayRef.current.scrollTop=0;});
      return()=>{
        if(!standalone)document.removeEventListener('keydown',esc);
        if(!standalone){
          document.documentElement.classList.remove('rg-direct-detail-open');
          document.body.classList.remove('rg-direct-detail-open');
        }
      };
    },[item&&item.id,standalone]);
    useEffect(function(){
      let live=true;
      setFoodInfo(null);
      if(!item||item.category!=='food'||!RPG.api||typeof RPG.api.foodInfo!=='function') return()=>{live=false;};
      RPG.api.foodInfo(item).then(data=>{
        if(!live||!data||data.status!=='success')return;
        if(Array.isArray(data.foodTags)&&typeof RPG.rememberFoodOfficialTags==='function')RPG.rememberFoodOfficialTags(item,data.foodTags);
        setFoodInfo(data);
      }).catch(()=>{});
      return()=>{live=false;};
    },[item&&item.id]);
    useEffect(function(){
      let live=true;
      setRelatedSeries(null);setRelatedExpanded(false);setRelatedLoadingId(0);setRelatedNavError('');
      if(!item||item.category!=='events'||!RPG.api||typeof RPG.api.eventRelated!=='function')return()=>{live=false;};
      RPG.api.eventRelated(item).then(data=>{
        if(!live||!data||!data.ok||!data.series||!Array.isArray(data.items)||!data.items.length)return;
        setRelatedSeries({series:data.series,count:Number(data.count||data.items.length),items:data.items});
      }).catch(()=>{});
      return()=>{live=false;};
    },[item&&item.id]);
    if(!item)return null;

    const meta=RPG.CATEGORIES[item.category]||{};
    const categoryTitle=meta.title||item.category||'';
    const displayTitle=RPG.displayPlaceTitle(item)||item.title;
    const routeUrl=RPG.settings.industrialRoutePageUrl||'/route-industriekultur/';
    const directions=item.coords?'https://www.google.com/maps/dir/?api=1&destination='+encodeURIComponent(item.coords.lat+','+item.coords.lng):'';
    const eventDescriptionText=String(item.description||'').replace(/<\s*br\s*\/?\s*>/gi,'\n').replace(/<\/\s*(?:p|div|li|h[1-6]|section|article)\s*>/gi,'\n').replace(/<[^>]*>/g,' ').replace(/&#x20;|&#0*32;|&#0*160;|&nbsp;/gi,' ').replace(/[ \t]+/g,' ').replace(/ *\n */g,'\n').replace(/\n{3,}/g,'\n\n').trim();
    const description=item.category==='events'
      ?eventDescriptionText.replace(/\[[^\]]+\]\(\s*(https?:\/\/[^\s)]+)\s*\)/gi,(whole,url)=>{
        // Do not rewrite unrelated editorial Markdown links.
        const resolved=RPG.eventTicketLink&&RPG.eventTicketLink(url);
        return resolved&&resolved===item.ticketUrl?'Tickets über „Tickets / Buchung“ öffnen':whole;
      })
      :String(item.description||'').replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim();
    const tags=RPG.categoryFilterTags(item);
    const tagFilterActive=RPG.isTagFilterCategory(item.category)&&typeof onTagFilter==='function';
    const routeType=routeTypeLabel(item.routeType);
    const effectiveWebsite=(foodInfo&&foodInfo.website)||item.website||'';
    const effectiveMenuUrl=(foodInfo&&foodInfo.menuUrl)||item.menuUrl||'';
    const effectiveReservationUrl=(foodInfo&&foodInfo.reservationUrl)||item.reservationUrl||'';
    const openingPilot=RPG.isOpeningHoursPilot(item);
    const effectiveOpeningHours=openingPilot?(item.openingHours||(item.tags&&item.tags.opening_hours)||(foodInfo&&foodInfo.openingHours)||''):((foodInfo&&foodInfo.openingHours)||item.openingHours||(item.tags&&item.tags.opening_hours)||'');
    const opening=RPG.formatOpeningHours(String(effectiveOpeningHours).trim());
    const openingStatus=openingPilot?RPG.placeOpeningStatus(item,effectiveOpeningHours):null;
    const parseEventLocalDate=value=>{if(value instanceof Date)return isNaN(value)?null:value;const text=String(value||'').trim();if(!text)return null;const m=text.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/);if(m){const d=new Date(Number(m[1]),Number(m[2])-1,Number(m[3]),Number(m[4]||0),Number(m[5]||0),Number(m[6]||0));return isNaN(d)?null:d;}const d=new Date(text);return isNaN(d)?null:d;};
    const formatEventTiming=(start,end,allDayFlag=false)=>{const a=parseEventLocalDate(start),b=parseEventLocalDate(end);if(!a)return '';const dateLong=new Intl.DateTimeFormat('de-DE',{weekday:'long',day:'numeric',month:'long',year:'numeric'});const dateShort=new Intl.DateTimeFormat('de-DE',{day:'numeric',month:'long',year:'numeric'});const time=new Intl.DateTimeFormat('de-DE',{hour:'2-digit',minute:'2-digit'});const midnight=d=>!!d&&d.getHours()===0&&d.getMinutes()===0&&d.getSeconds()===0;const sameDay=(x,y)=>x.getFullYear()===y.getFullYear()&&x.getMonth()===y.getMonth()&&x.getDate()===y.getDate();const allDay=!!allDayFlag||(midnight(a)&&(!b||!(b>a)||midnight(b)));if(allDay){if(b&&b>a&&midnight(b)){const last=new Date(b);last.setDate(last.getDate()-1);if(!sameDay(a,last))return dateShort.format(a)+' – '+dateShort.format(last);}return dateLong.format(a);}if(b&&b>a){if(sameDay(a,b))return dateLong.format(a)+' · '+time.format(a)+'–'+time.format(b)+' Uhr';return dateLong.format(a)+' · '+time.format(a)+' – '+dateLong.format(b)+' · '+time.format(b)+' Uhr';}return dateLong.format(a)+' · '+time.format(a)+' Uhr';};
    const eventDate=item.start?formatEventTiming(item.start,item.end,eventAllDayValue(item,item.start,item.end)):'';
    const eventDocuments=item.category==='events'?(Array.isArray(item.documents)?item.documents:[]).filter(d=>d&&String(d.url||'').trim()).slice(0,8):[];
    const eventVisitorHours=item.category==='events'?String(item.visitorHours||'').trim():'';
    const eventDayHours=item.category==='events'?String(item.dayHours||'').trim():'';
    const collapseExactRepeat=value=>{let text=String(value==null?'':value).replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim();for(let pass=0;pass<3;pass++){const words=text.split(/\s+/);if(words.length<2||words.length%2)break;const half=words.length/2;const a=words.slice(0,half).join(' '),b=words.slice(half).join(' ');const norm=v=>v.toLocaleLowerCase('de').replace(/[.,;:!?]+$/g,'').trim();if(norm(a)!==norm(b))break;text=a;}return text;};
    const foodPriceTier=item.category==='food'?String(item.priceTier||''):'';
    // V317: canonical Wesel prices can arrive compact as e.g. `45.00EUR`.
    // Normalize currency notation for visitors before deciding whether a usable
    // price signal exists. This keeps the source value authoritative while
    // presenting it in German notation.
    const formatEventPrice=value=>{let text=collapseExactRepeat(value||'');if(!text)return '';text=text.replace(/(\d+(?:[.,]\d{1,2})?)\s*EUR\b/ig,(_,raw)=>{const n=Number(String(raw).replace(',','.'));if(!Number.isFinite(n))return raw+' €';const hasCents=Math.abs(n-Math.round(n))>0.0001;return n.toLocaleString('de-DE',{minimumFractionDigits:hasCents?2:0,maximumFractionDigits:2})+' €';});return text;};
    const price=item.category==='events'?formatEventPrice(item.priceText||''):(item.category==='food'?foodPriceTier:((item.tags&&(item.tags['charge']||item.tags['fee']))||''));
    const eventFacts=item.category==='events'&&item.eventInfo&&typeof item.eventInfo==='object'?item.eventInfo:{};
    const cleanEventInfoList=values=>{const seen=new Set();return (Array.isArray(values)?values:[]).map(v=>String(v||'').trim()).filter(v=>{const k=v.toLocaleLowerCase('de');if(!v||/^(?:divers|sonstiges?|weitere events?)$/i.test(v)||seen.has(k))return false;seen.add(k);return true;});};
    const eventFeatures=cleanEventInfoList(eventFacts.features).slice(0,12);
    const eventCategories=cleanEventInfoList(eventFacts.categories).slice(0,10);
    const eventKeywords=cleanEventInfoList(eventFacts.keywords).slice(0,20);
    const eventAttributes=Array.isArray(eventFacts.attributes)?eventFacts.attributes:[];
    const eventNumbers=Array.isArray(eventFacts.numbers)?eventFacts.numbers:[];
    const isRichEvent=item.category==='events';
    const cleanRepeatedText=collapseExactRepeat;
    const cleanPriceLabel=value=>String(value||'').replace(/[_-]+/g,' ').replace(/\b(price|preis|admission|eintritt|fee|cost|charge)\b/ig,'').replace(/\s+/g,' ').trim();
    const structuredPrices=isRichEvent&&item.prices&&Array.isArray(item.prices.items)?(()=>{const seen=new Set();return item.prices.items.map(row=>row&&typeof row==='object'?Object.assign({},row,{value:cleanRepeatedText(row.value)}):row).filter(row=>{if(!row||!String(row.value||'').trim())return false;const key=(String(row.label||'')+'|'+String(row.value||'')).toLocaleLowerCase('de');if(seen.has(key))return false;seen.add(key);return true;}).slice(0,14);})():[];
    const priceHasSignal=value=>/(?:€|EUR\b|\beuro\b|kostenlos|eintritt\s+frei|freier\s+eintritt|ermäß|ermaess|familienkarte|\bpreis\b|\bgebühr\b|\bgebuehr\b|\d+[,.]\d{1,2}\s*(?:€|EUR|euro))/i.test(String(value||''));
    const displayPrice=structuredPrices.length||priceHasSignal(price)?price:'';
    // V319: ticket URLs sometimes arrive embedded in free-form price text (e.g.
    // "Tickets - Ticketlink: https://…; 15 € An der Abendkasse … 20 €").
    // Pull the URL out into its own action and keep only visitor-readable price facts.
    const embeddedTicketUrl=(()=>{
      const t=String(displayPrice||'');
      const markdown=t.match(/\[[^\]]+\]\(\s*(https?:\/\/[^\s)]+)\s*\)/i);
      const plain=t.match(/https?:\/\/[^\s;|•]+/i);
      return RPG.eventTicketLink?RPG.eventTicketLink(markdown?markdown[0]:(plain?plain[0]:'')):'';
    })();
    const eventTicketUrl=item.category==='events'?(String(item.ticketUrl||embeddedTicketUrl||'').trim()):'';
    const cleanFreePriceText=value=>{let text=String(value||'');if(embeddedTicketUrl)text=text.replace(embeddedTicketUrl,' ');text=text.replace(/\bTickets?\s*-?\s*Ticketlink\s*:?/ig,' ').replace(/\bTicketlink\s*:?/ig,' ').replace(/\s+/g,' ').trim();return text.replace(/^[;|•,:\-\s]+|[;|•,:\-\s]+$/g,'').trim();};
    const fallbackPriceRows=(()=>{if(!displayPrice||structuredPrices.length)return [];const cleaned=cleanFreePriceText(displayPrice);if(!cleaned)return [];let rows=cleaned.split(/\s*(?:;|•|\|)\s*/).filter(Boolean);rows=rows.flatMap(v=>String(v).split(/\s+(?=An der Abendkasse\b|Abendkasse\b)/i)).filter(Boolean);if(rows.length<2&&cleaned.length>90)rows=cleaned.split(/,\s+(?=[A-ZÄÖÜ0-9])/).filter(Boolean);const seen=new Set();return rows.map(cleanRepeatedText).map(v=>v.replace(/^An der Abendkasse kostet (?:der )?Einlass\s*/i,'Abendkasse: ').replace(/^An der Abendkasse\s*/i,'Abendkasse: ')).filter(v=>{const k=v.toLocaleLowerCase('de');if(!v||seen.has(k))return false;seen.add(k);return true;}).slice(0,14).map(v=>({label:'',value:v}));})();
    const priceRows=structuredPrices.length?structuredPrices:fallbackPriceRows;
    const hasEventOrganizers=item.category==='events'&&Array.isArray(item.organizers)&&item.organizers.length>0;
    const hasEventParticipants=item.category==='events'&&Array.isArray(item.participants)&&item.participants.length>0;
    // V318: event type remains exclusively a discovery facet under
    // "Mehr davon entdecken". Timing, price and ET4 participants are grouped
    // in the upper Veranstaltungshinweis card next to Adresse & Ort.

    // V250: visitor-facing event facts. Keep the complete ET4 metadata internally,
    // but surface only a small set of facts that help a visitor decide quickly.
    // No fact is guessed from unrelated data: absent/ambiguous values simply stay hidden.
    const eventGlanceFacts=(()=>{
      if(item.category!=='events')return [];
      const out=[];const seen=new Set();
      const norm=v=>String(v==null?'':v).normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('de').replace(/\s+/g,' ').trim();
      const push=(key,label,kind,type)=>{const text=String(label||'').replace(/\s+/g,' ').trim();const k=norm(text);if(!text||seen.has(k)||out.length>=6)return;seen.add(k);out.push({key,label:text,kind:kind||key,type:type||''});};
      const values=eventCategories.concat(eventFeatures,eventKeywords);
      const valueText=values.join(' · ');
      const valueNorm=norm(valueText);
      const typedRows=eventAttributes.concat(eventNumbers).filter(r=>r&&typeof r==='object');
      const typedValue=(pattern)=>{for(const row of typedRows){const key=norm(row.key||row.type||row.label||'');const value=String(row.value==null?'':row.value).trim();if(pattern.test(key)&&value)return value;}return '';};
      const positiveTyped=(pattern)=>{for(const row of typedRows){const key=norm(row.key||row.type||row.label||'');const value=norm(row.value);if(!pattern.test(key))continue;if(/^(?:1|true|yes|ja|y|on|voll|full|accessible|barrierefrei|rollstuhlgerecht)$/.test(value))return true;}return false;};

      const typeMeta=eventTypeBadgeMeta(item);
      if(typeMeta)push('type',typeMeta.label,'type',typeMeta.key);

      // Price summary: exact free-admission signals first, otherwise only a short
      // explicit price string. Detailed tiers remain in the separate price block.
      const priceSignals=[displayPrice].concat(values).join(' · ');
      if(/(?:eintritt\s+frei|freier\s+eintritt|kostenlos|free\s+admission)/i.test(priceSignals)){
        push('price','Eintritt frei','free');
      }else{
        let quick='';
        if(structuredPrices.length===1)quick=cleanRepeatedText(structuredPrices[0].value||'');
        if(!quick&&displayPrice&&String(displayPrice).length<=42&&!/[;|•]/.test(String(displayPrice)))quick=cleanRepeatedText(displayPrice);
        if(quick&&/(?:€|\beuro\b|\d+[,.]?\d*\s*(?:€|euro)|\bab\s+\d+)/i.test(quick))push('price',quick,'price');
      }

      // Duration is derived only from one concrete same-day timed occurrence.
      const a=parseEventLocalDate(item.start),b=parseEventLocalDate(item.end);
      if(a&&b&&b>a&&a.toDateString()===b.toDateString()&&(a.getHours()||a.getMinutes())&&(b.getHours()||b.getMinutes())){
        const minutes=Math.round((b-a)/60000);
        if(minutes>0&&minutes<=12*60){
          const label=minutes<60?minutes+' Min.':(minutes%60?Math.floor(minutes/60)+' Std. '+(minutes%60)+' Min.':Math.floor(minutes/60)+' Std.');
          push('duration',label,'duration');
        }
      }

      // Explicit audience signals only. More specific labels win.
      if(/(?:\bfamilie(?:n)?\b|familienfreund|\bkinder\b|\bkids\b)/.test(valueNorm))push('audience','Familien','audience');
      else if(/(?:\bjugend(?:liche)?\b|teenager)/.test(valueNorm))push('audience','Jugendliche','audience');
      else if(/(?:\bsenior(?:en|innen)?\b)/.test(valueNorm))push('audience','Senioren','audience');
      else if(/(?:\berwachsene\b|adults?)/.test(valueNorm))push('audience','Erwachsene','audience');

      // Accessibility only on explicit positive source data.
      const negativeAccess=/(?:nicht\s+barrierefrei|nicht\s+rollstuhl|wheelchair\s*[:=]?\s*(?:no|false|0))/.test(valueNorm);
      if(!negativeAccess&&(/(?:barrierefrei|rollstuhlgerecht|wheelchair\s+accessible)/.test(valueNorm)||positiveTyped(/(?:barrier|accessib|wheelchair|rollstuhl)/)))push('access','Barrierefrei','access');

      // Indoor/outdoor only when the source says one side unambiguously.
      const hasIndoor=/(?:^|[ ·,;/])indoor(?:$|[ ·,;/])/.test(valueNorm);
      const hasOutdoor=/(?:^|[ ·,;/])outdoor(?:$|[ ·,;/])/.test(valueNorm);
      if(hasIndoor&&!hasOutdoor)push('setting','Indoor','indoor');
      else if(hasOutdoor&&!hasIndoor)push('setting','Outdoor','outdoor');

      // Language is shown only from an explicitly typed source field.
      let language=typedValue(/(?:^|\b)(?:language|sprache)(?:$|\b)/);
      if(language){const n=norm(language);if(/^(?:de|deu|ger|deutsch|german)$/.test(n))language='Deutsch';else if(/^(?:en|eng|englisch|english)$/.test(n))language='Englisch';else if(/^(?:fr|fra|fre|franzosisch|french)$/.test(n))language='Französisch';if(String(language).length<=28)push('language',language,'language');}
      return out.slice(0,6);
    })();

    function toggleSaved(){
      let list=[];
      try{const raw=JSON.parse(RPG.storage.get('ruhrpottguru_favorites_v1')||'[]');if(Array.isArray(raw))list=raw;}catch(_){}
      const exists=list.some(x=>String(x&&x.id)===String(item.id));
      if(exists)list=list.filter(x=>String(x&&x.id)!==String(item.id));
      else list.push({id:item.id,title:item.title,category:item.category,city:item.city,imageUrl:item.category==='events'?(image||''):(item.imageUrl||'')});
      RPG.storage.set('ruhrpottguru_favorites_v1',JSON.stringify(list.slice(-100)));
      setSaved(!exists);
    }

    // Same complete detail body for both contexts. Only the outer semantics and
    // back action differ; content fields, actions, data and chips are untouched.
    return h('div',{ref:overlayRef,className:standalone?'rpgx-detail-landing':'rpgx-global-detail-overlay fixed inset-0 z-50 flex items-start justify-center p-4 bg-slate-950/80 backdrop-blur-md overflow-y-auto',style:standalone?undefined:{paddingTop:'20px'},role:standalone?undefined:'dialog','aria-modal':standalone?undefined:'true','aria-label':standalone?undefined:'Detailansicht '+displayTitle,onClick:standalone?undefined:e=>{if(e.target===e.currentTarget)onClose();}},
      h(standalone?'article':'div',{className:'rpgx-direct-detail-panel relative w-full max-w-3xl border rounded-3xl shadow-2xl overflow-hidden mb-4 sm:mb-8 transition-colors bg-slate-900 text-slate-100 border-slate-800','aria-hidden':sharing.mode?true:undefined,inert:sharing.mode?'':undefined},
        div({className:(standalone?'relative':'sticky top-0 z-30')+' flex items-center gap-2 px-3 py-2 border-b backdrop-blur-md bg-slate-950/95 border-slate-800 text-white'},
          standalone?span({className:'truncate flex-1 font-display text-sm font-bold'},categoryTitle):span({className:'truncate flex-1 font-display text-sm font-bold'},displayTitle),
          standalone?a({href:overviewUrl||(RPG.settings.homeUrl||'/'),className:'flex items-center gap-1.5 min-h-[44px] px-3.5 py-1.5 rounded-full bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-extrabold shadow-md shrink-0'},span({'aria-hidden':'true'},'←'),span(null,item.category==='events'?'Alle Events':'Entdecken')):button({type:'button',onClick:onClose,className:'flex items-center gap-1.5 min-h-[44px] px-3.5 py-1.5 rounded-full bg-amber-500 hover:bg-amber-400 active:scale-95 text-slate-950 text-xs font-extrabold transition-all shadow-md shrink-0 cursor-pointer','aria-label':'Detailansicht schließen',title:'Detailansicht schließen'},span({'aria-hidden':'true',className:'text-lg leading-none font-black'},'×'),span(null,'Schließen'))
        ),
        div({className:'rpgx-direct-detail-hero relative h-64 sm:h-80 w-full bg-slate-950 flex items-center justify-center'},
          h(DetailGallery,{item,image,onLoad:media.onLoad,onError:media.onError}),
          div({className:'absolute inset-0 bg-gradient-to-t from-slate-900 via-slate-900/40 to-transparent pointer-events-none'}),
          div({className:'absolute bottom-4 left-4 right-4'},
            h(standalone?'h1':'h2',{className:'text-2xl sm:text-3xl font-extrabold text-white font-display leading-tight'},displayTitle),
            item.subtitle?h('p',{className:'text-sm text-slate-300 mt-1 font-medium'},item.subtitle):null
          )
        ),
        div({className:'rpgx-direct-detail-body p-6 flex flex-col gap-6'},
          div({className:'flex flex-wrap items-center justify-between gap-3 p-4 bg-slate-950 rounded-2xl border border-slate-800'},
            div({className:'flex flex-wrap items-center gap-2'},
              button({type:'button',onClick:toggleSaved,className:'flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all '+(saved?'bg-red-500 text-white shadow-md':'bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700')},span({'aria-hidden':'true'},saved?'♥':'♡'),span(null,saved?'Auf Merkzettel':'Merken')),
              directions?a({href:directions,target:'_blank',rel:'noopener noreferrer',onClick:()=>metric('route_click',item,'detail'),className:'flex items-center gap-2 px-4 py-2 rounded-xl bg-teal-500 hover:bg-teal-400 text-slate-950 font-extrabold text-xs transition-all shadow-md cursor-pointer'},span({'aria-hidden':'true'},'↗'),span(null,'Route planen')):null,
              button({type:'button',onClick:e=>sharing.open('share',e),className:'flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-100 border border-slate-700 font-extrabold text-xs transition-all shadow-md cursor-pointer',title:'Dieses Ergebnis teilen'},span({'aria-hidden':'true'},'↥'),span(null,sharing.copied?'Kopiert ✓':'Teilen')),
              button({type:'button',onClick:e=>sharing.open('invite',e),className:'rpgx-kommrum-trigger',title:'Freunde zu diesem Ort einladen'},span({'aria-hidden':'true'},'🍻'),span(null,'Komm rum, ich bin hier!')),
              item.ticketUrl?a({href:item.ticketUrl,target:'_blank',rel:'noopener noreferrer',onClick:()=>metric('ticket_click',item,'detail'),className:'flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold text-xs transition-all shadow-md'},span({'aria-hidden':'true'},'↗'),span(null,'Tickets / Buchung')):null,
              effectiveWebsite?a({href:effectiveWebsite,target:'_blank',rel:'noopener noreferrer',onClick:()=>metric('website_click',item,'detail'),className:'flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-500/10 hover:bg-amber-500/20 text-amber-300 border border-amber-500/30 font-extrabold text-xs transition-all shadow-md'},span({'aria-hidden':'true'},'⌂'),span(null,item.category==='events'?'Event-Webseite':'Website')):null,
              item.routeIndustrial&&item.routeOfficialUrl?a({href:item.routeOfficialUrl,target:'_blank',rel:'noopener noreferrer',onClick:()=>metric('website_click',item,'detail-route-official'),className:'flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-100 border border-slate-700 font-extrabold text-xs transition-all shadow-md',title:'Offizielle Standortseite der Route der Industriekultur'},span(null,'Zur offiziellen Seite ↗')):null
            )
          ),
          item.routeIndustrial?div({className:'p-3.5 bg-slate-950/60 rounded-2xl border border-slate-800'},
            h('div',{className:'text-xs font-bold text-slate-400 uppercase tracking-wider'},'Route der Industriekultur'),
            h('p',{className:'text-sm font-semibold text-amber-300 mt-0.5'},routeType||'Primärstandort')
          ):null,
          div({className:'grid grid-cols-1 sm:grid-cols-2 gap-4'},
            div({className:'flex items-start gap-3 p-3.5 bg-slate-950/60 rounded-2xl border border-slate-800'},
              span({className:'text-teal-400 shrink-0 mt-0.5','aria-hidden':'true'},'⌖'),
              div({className:'min-w-0 flex-1'},
                h('h4',{className:'text-xs font-bold text-slate-400 uppercase tracking-wider'},'Adresse & Ort'),
                h('p',{className:'text-sm font-semibold text-slate-100 mt-0.5'},item.address||item.city||'Ruhrgebiet'),
                item.city&&(!item.address||!String(item.address).toLocaleLowerCase('de').includes(String(item.city).toLocaleLowerCase('de')))?h('p',{className:'text-xs text-slate-400'},item.city):null,
                Number.isFinite(item.distanceKm)?h('p',{className:'text-xs text-slate-400 mt-1'},RPG.formatDistance(item.distanceKm)+' entfernt'):null,
                item.category==='food'&&(effectiveMenuUrl||effectiveReservationUrl)?div({className:'flex flex-wrap gap-2 mt-3'},
                  effectiveMenuUrl?a({href:effectiveMenuUrl,target:'_blank',rel:'noopener noreferrer',onClick:()=>metric('website_click',item,'detail-menu'),className:'flex items-center gap-2 px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-100 border border-slate-700 font-extrabold text-xs'},span({'aria-hidden':'true'},'☷'),span(null,'Speisekarte')):null,
                  effectiveReservationUrl?a({href:effectiveReservationUrl,target:'_blank',rel:'noopener noreferrer',onClick:()=>metric('reservation_click',item,'detail'),className:'flex items-center gap-2 px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-100 border border-slate-700 font-extrabold text-xs'},span({'aria-hidden':'true'},'◷'),span(null,'Reservieren')):null
                ):null
              )
            ),
            div({className:'flex items-start gap-3 p-3.5 bg-slate-950/60 rounded-2xl border border-slate-800'},
              item.category==='events'?null:span({className:(openingPilot&&openingStatus&&(openingStatus.state==='open'||openingStatus.state==='public')?'text-teal-400':'text-amber-400')+' shrink-0 mt-0.5','aria-hidden':'true'},'◷'),
              item.category==='events'?div({className:'flex-1 min-w-0'},
                h('h4',{className:'text-xs font-bold text-slate-400 uppercase tracking-wider'},'Veranstaltungshinweis'),
                div({className:'mt-2'},
                  h('div',{className:'flex items-center gap-2 text-[11px] font-bold text-slate-500 uppercase tracking-wider'},span({className:'text-amber-400 text-sm leading-none','aria-hidden':'true'},'◷'),span(null,'Termin')),
                  eventDate?h('p',{className:'text-sm font-semibold text-amber-300 mt-0.5'},eventDate):h('p',{className:'text-sm font-semibold text-amber-300 mt-0.5'},'Infos laut Anbieter'),
                  eventDayHours&&!eventDate?h('p',{className:'text-xs text-slate-300 mt-1.5'},'Geöffnet: '+eventDayHours.replace(/^[^·]+·\s*/,'')):null,
                  eventVisitorHours&&!eventDayHours?h('p',{className:'text-xs text-slate-400 mt-1.5 leading-relaxed'},eventVisitorHours):null
                ),
                (displayPrice||priceRows.length)?div({className:'mt-3 pt-3 border-t border-slate-800'},
                  h('div',{className:'flex items-center gap-2 text-[11px] font-bold text-slate-500 uppercase tracking-wider'},span({className:'inline-flex items-center justify-center w-4 h-4 rounded-full border border-amber-400/60 text-amber-300 text-[10px] font-black leading-none','aria-hidden':'true'},'€'),span(null,'Preis')),
                  displayPrice&&!priceRows.length?h('div',{className:'mt-1 text-sm font-extrabold text-amber-300 leading-relaxed'},cleanFreePriceText(displayPrice)):null,
                  priceRows.length?div({className:'mt-2 flex flex-col gap-2'},priceRows.map((row,i)=>{const label=cleanPriceLabel(row.label);return div({key:'hint-price-'+i,className:'text-sm leading-snug'},label?h('span',{className:'text-slate-400 font-bold'},label+': '):null,h('span',{className:'text-slate-100 font-extrabold'},String(row.value||'')));})):null
                ):null,
                eventTicketUrl?div({className:'mt-3 pt-3 border-t border-slate-800'},
                  h('div',{className:'flex items-center gap-2 text-[11px] font-bold text-slate-500 uppercase tracking-wider'},span({className:'text-amber-400 text-sm leading-none','aria-hidden':'true'},'↗'),span(null,'Tickets')),
                  a({href:eventTicketUrl,target:'_blank',rel:'noopener noreferrer',className:'inline-flex items-center gap-2 mt-2 px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-amber-300 border border-slate-700 font-extrabold text-xs break-all'},span(null,'Ticketlink öffnen'),span({'aria-hidden':'true'},'→'))
                ):null,
                hasEventParticipants?div({className:'mt-3 pt-3 border-t border-slate-800'},
                  h('div',{className:'text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2'},'Mitwirkende'),
                  div({className:'flex flex-col gap-2'},item.participants.map((person,i)=>div({key:(person.name||'person')+'-'+i,className:'flex items-start gap-2.5 p-2.5 rounded-xl bg-slate-900/70 border border-slate-800 min-w-0'},
                    span({className:'flex items-center justify-center w-8 h-8 rounded-full bg-amber-500/15 border border-amber-500/30 text-amber-300 font-extrabold text-xs shrink-0','aria-hidden':'true'},String(person.name||'?').trim().slice(0,1).toLocaleUpperCase('de')),
                    div({className:'min-w-0 flex-1'},
                      person.url?a({href:person.url,target:'_blank',rel:'noopener noreferrer',className:'block text-sm font-extrabold text-amber-300 hover:text-amber-200 break-all'},person.name):h('div',{className:'text-sm font-extrabold text-slate-100 break-all'},person.name),
                      person.role?span({className:'inline-flex mt-1 px-2 py-0.5 rounded-full bg-slate-800 border border-slate-700 text-xs font-bold text-slate-300'},person.role):null
                    )
                  )))
                ):null
              ):openingPilot?div({className:'flex-1 min-w-0'},
                h('h4',{className:'text-xs font-bold text-slate-400 uppercase tracking-wider'},openingStatus&&openingStatus.heading?openingStatus.heading:'Öffnungszeiten'),
                openingStatus&&(openingStatus.shortLabel||openingStatus.label)?h('p',{className:'rpgx-opening-detail-status is-'+openingStatus.state},span({'aria-hidden':'true'},'●'),openingStatus.shortLabel||openingStatus.label):h('p',{className:'text-sm font-semibold text-slate-400 mt-0.5'},openingStatus&&openingStatus.mode==='public_outdoor'?'Nutzungszeiten nicht bekannt':'Öffnungszeiten nicht bekannt'),
                opening?h('p',{className:'text-xs text-slate-300 mt-2 leading-relaxed break-words'},opening):null,
                price?h('p',{className:'text-xs text-slate-400 mt-2'},item.category==='food'?'Preisniveau: '+String(price):'Preisinfo: '+String(price)):null
              ):div({className:'flex-1'},
                h('h4',{className:'text-xs font-bold text-slate-400 uppercase tracking-wider'},'Preise & Zeiten'),
                h('p',{className:'text-sm font-semibold text-amber-300 mt-0.5'},price?String(price):(item.category==='food'?'Keine Preisinfos vorhanden':'Infos laut Anbieter')),
                opening?h('p',{className:'text-xs text-slate-300 mt-1.5 break-all'},opening):null
              )
            )
          ),
          description?div({className:standalone?'rpgx-detail-landing-description':undefined},
            h('h3',{className:'text-lg font-extrabold text-white mb-2'},'Darum geht’s'),
            div({className:'text-sm text-slate-300 leading-relaxed'},
              String(description).split(/\n{2,}/).map(v=>v.trim()).filter(Boolean).map((paragraph,i,all)=>h('p',{key:'desc-'+i,style:{whiteSpace:'pre-line',margin:i===all.length-1?'0':'0 0 12px'}},paragraph))
            )
          ):null,
          item.category==='events'&&eventDocuments.length?div({className:'p-4 bg-slate-950/70 rounded-2xl border border-slate-800'},
            h('div',{className:'text-xs font-bold text-slate-400 uppercase tracking-wider'},'Programm & Downloads'),
            div({className:'mt-3 flex flex-wrap gap-2'},eventDocuments.map((doc,i)=>a({key:String(doc.url||i),href:doc.url,target:'_blank',rel:'noopener noreferrer',className:'inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-100 border border-slate-700 font-extrabold text-xs'},span({'aria-hidden':'true'},/flyer/i.test(String(doc.label||''))?'▤':'☷'),span(null,String(doc.label||'Weitere Informationen')),span({'aria-hidden':'true'},'↗'))))
          ):null,
          item.category==='events'&&eventCalendarData(item)?div({className:'p-4 bg-slate-950/80 rounded-2xl border border-amber-500/30 flex flex-col gap-3'},
            h('h3',{className:'text-sm font-extrabold text-amber-400 uppercase tracking-wider'},'📅 Event in den Kalender übernehmen'),
            h('p',{className:'text-xs text-slate-400'},'Datum, Uhrzeit (falls vorhanden), Ort und Beschreibung werden automatisch übernommen.'),
            div({className:'grid grid-cols-1 sm:grid-cols-2 gap-2'},
              button({type:'button',onClick:()=>downloadEventIcs(item),className:'w-full px-4 py-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-white border border-slate-700 font-extrabold text-xs cursor-pointer','aria-label':'Event in Apple Kalender oder iPhone Kalender übernehmen'},'📅 iPhone / Apple Kalender'),
              button({type:'button',onClick:()=>openGoogleCalendar(item),className:'w-full px-4 py-3 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold text-xs cursor-pointer','aria-label':'Event in Android oder Google Kalender übernehmen'},'📱 Android / Google Kalender')
            )
          ):null,
          item.category==='events'&&hasEventOrganizers?div({className:'p-3.5 bg-slate-950/60 rounded-2xl border border-slate-800 min-w-0'},
            h('div',{className:'text-xs font-bold text-slate-400 uppercase tracking-wider'},'Veranstalter & Kontakt'),
            div({className:'mt-2 flex flex-col gap-2'},item.organizers.map((org,i)=>{const name=String(org.name||org.company||'Veranstalter').trim();const addr=[[org.street,org.houseNumber].filter(Boolean).join(' '),[org.zip,org.city].filter(Boolean).join(' ')].filter(Boolean).join(', ');return div({key:name+'-'+i,className:'p-2.5 rounded-xl bg-slate-900/70 border border-slate-800 min-w-0'},h('div',{className:'text-sm font-bold text-slate-100 break-all'},name),addr?h('div',{className:'text-xs text-slate-400 mt-1 break-all'},addr):null,div({className:'flex flex-wrap gap-x-3 gap-y-1 mt-1.5'},org.phone?a({href:'tel:'+org.phone,className:'text-xs font-bold text-amber-300 break-all'},org.phone):null,org.email?a({href:'mailto:'+org.email,className:'text-xs font-bold text-amber-300 break-all'},org.email):null,org.web?a({href:org.web,target:'_blank',rel:'noopener noreferrer',className:'text-xs font-bold text-amber-300'},'Website →'):null));}))
          ):null,
          item.category==='events'&&relatedSeries&&Array.isArray(relatedSeries.items)&&relatedSeries.items.length?div({className:'p-4 bg-slate-950/70 rounded-2xl border border-slate-800'},
            h('div',{className:'flex items-start justify-between gap-3'},
              div({className:'min-w-0'},
                h('div',{className:'text-xs font-bold text-slate-400 uppercase tracking-wider'},relatedSeries.items.every(x=>String(x&&x.relation||'')==='same-event-occurrence')?'Weitere Termine':'Weitere Events / Termine dieser Reihe'),
                relatedSeries.series&&relatedSeries.series.label?h('div',{className:'text-sm font-extrabold text-amber-300 mt-1 break-words'},String(relatedSeries.series.label)):null
              ),
              relatedSeries.count>1?span({className:'shrink-0 inline-flex px-2.5 py-1 rounded-full bg-amber-500/10 border border-amber-500/25 text-[11px] font-extrabold text-amber-300'},String(relatedSeries.count)+' weitere'):null
            ),
            div({className:'mt-3 flex flex-col gap-2'},(relatedExpanded?relatedSeries.items:relatedSeries.items.slice(0,6)).map((related,i)=>{
              const when=formatEventTiming(related.start,related.end,!!related.allDay);
              const venue=String(related.venueName||related.city||'').trim();
              const href=String(related.canonicalUrl||'').trim()||'#';
              const relatedId=Number(related.eventId||0);
              const loading=relatedLoadingId===relatedId&&relatedId>0;
              const openInline=e=>{
                // Normal click stays inside the current overlay. Ctrl/Cmd/Shift/
                // Alt click deliberately keeps native link behaviour so users can
                // still open the canonical event URL in another tab/window.
                if(!onNavigate||e.ctrlKey||e.metaKey||e.shiftKey||e.altKey||(typeof e.button==='number'&&e.button!==0))return;
                e.preventDefault();
                if(relatedLoadingId)return;
                setRelatedNavError('');setRelatedLoadingId(relatedId||-1);
                Promise.resolve(onNavigate(related)).catch(()=>setRelatedNavError('Der Termin konnte nicht geladen werden.')).finally(()=>setRelatedLoadingId(0));
              };
              return a({key:String(related.eventId||i),href,onClick:openInline,'aria-busy':loading?'true':undefined,className:'group flex items-start justify-between gap-3 p-3 rounded-xl bg-slate-900/70 hover:bg-slate-800 border border-slate-800 hover:border-amber-500/35 transition-colors'},
                div({className:'min-w-0 flex-1'},
                  h('div',{className:'text-sm font-extrabold text-slate-100 group-hover:text-amber-300 break-words'},String(related.title||'Weiteres Event')),
                  when?h('div',{className:'text-xs text-amber-300 mt-1'},when):null,
                  venue&&venue!==String(item.subtitle||'').trim()?h('div',{className:'text-xs text-slate-400 mt-1 break-words'},venue):null
                ),
                span({className:'shrink-0 text-amber-300 font-extrabold mt-0.5','aria-hidden':'true'},loading?'…':'→')
              );
            })),
            relatedNavError?h('div',{className:'mt-2 text-xs font-bold text-rose-300','role':'status'},relatedNavError):null,
            relatedSeries.items.length>6?button({type:'button',onClick:()=>setRelatedExpanded(v=>!v),className:'mt-3 inline-flex items-center gap-2 text-xs font-extrabold text-amber-300 hover:text-amber-200 bg-transparent border-0 p-0 cursor-pointer'},relatedExpanded?'Weniger anzeigen ↑':'Alle Events dieser Reihe anzeigen ↓'):null
          ):null,
          item.category==='events'&&eventGlanceFacts.length?div({className:'rpgx-event-glance','aria-label':'Event auf einen Blick'},
            h('div',{className:'rpgx-event-glance-title'},'Auf einen Blick'),
            div({className:'rpgx-event-glance-list'},eventGlanceFacts.map((fact,i)=>span({key:fact.key+'-'+i,className:'rpgx-event-glance-chip is-'+fact.kind},
              h(EventGlanceIcon,{kind:fact.kind,type:fact.type}),
              span(null,fact.label)
            )))
          ):null,
          item.category==='events'&&(item.sourceAttribution||item.sourceName)?div({className:'p-3.5 bg-slate-950/60 rounded-2xl border border-slate-800'},
            h('div',{className:'text-xs font-bold text-slate-400 uppercase tracking-wider'},'Datenquelle & Lizenz'),
            h('p',{className:'text-xs text-slate-300 mt-1'},item.sourceAttribution||('Quelle: '+item.sourceName)),
            item.sourceLicense?h('p',{className:'text-xs text-slate-500 mt-1'},item.sourceLicense):null,
            item.sourcePageUrl?a({href:item.sourcePageUrl,target:'_blank',rel:'noopener noreferrer',className:'inline-block text-xs font-bold text-amber-300 mt-2'},'Originalquelle öffnen →'):
            item.sourceTermsUrl?a({href:item.sourceTermsUrl,target:'_blank',rel:'noopener noreferrer',className:'inline-block text-xs font-bold text-amber-300 mt-2'},'Quellenbedingungen öffnen →'):null
          ):null,
          item.category!=='events'&&RPG.Components.PlaceEvents?h(RPG.Components.PlaceEvents,{key:item.id,item,onNavigate}):null,
          item.category!=='events'?h(CommunityStories,{item,onWrite:()=>setCommunityStoryOpen(true)}):null,
          communityStoryOpen?h(CommunityStoryForm,{item,onClose:()=>setCommunityStoryOpen(false)}):null,
          item.category!=='events'?div({className:'rpgx-place-community'},h('h3',null,'Gemeinsam fürs Revier'),button({type:'button',className:'rpgx-report-item',onClick:()=>{setContributionMode('full');setContributionOpen(true);}},'Daten ergänzen oder Bild hochladen')):button({type:'button',className:'rpgx-report-item',onClick:()=>window.dispatchEvent(new CustomEvent('rpg:feedback-item',{detail:{title:item.title,id:item.id}}))},'Stimmt hier etwas nicht?'),
          contributionOpen?h(ContributionForm,{item,intent:contributionMode,onClose:()=>setContributionOpen(false)}):null,
          tags.length?div({className:'rpgx-detail-tag-section'},h('h3',{className:'text-xs font-bold text-slate-400 uppercase tracking-wider mb-2'},'Mehr davon entdecken'),div({className:'rpgx-detail-tag-list'},tags.map(v=>tagFilterActive?button({key:v,type:'button',className:'rpgx-detail-tag-filter',onClick:()=>onTagFilter(item,v),'aria-label':'Mehr '+v+' in '+categoryTitle+' anzeigen'},span(null,v),span({'aria-hidden':'true',className:'rpgx-detail-tag-arrow'},'→')):span({key:v,className:'rpgx-detail-tag-static'},v)))):null,
        )
      ),
      sharing.mode?h(ShareDialog,{sharing}):null
    );
  }

  RPG.Components={FeedbackButton,Header,FavoritesOverlay,FooterNav,LocationModal,Card,SectionHeader,LegacyHomeSectionHeader,LegacyOverlayCard,LegacyCultureRow,DetailOverlay,Media,ContributionForm,CommunityStoryForm,CommunityStories,DetailGallery,useSharing,ShareDialog};
})(window);
