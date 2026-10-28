(function(window){
  'use strict';
  const RPG=window.RPGReact,h=RPG.h;
  const {useState,useEffect}=RPG.React,C=RPG.Components,V=RPG.Views;

  function closeLegacyDrawer(){const d=document.getElementById('mobile-menu-drawer');if(d){d.classList.add('hidden');d.classList.remove('flex');}}
  function installSureCookieAccessibleNameFix(){
    const selectors=[
      ['a.surecookie-branding-mobile','Powered by SureCookie'],
      ['a.surecookie-branding-desktop','Powered by SureCookie'],
      ['button.surecookie-accept-all-btn','Alle Akzeptieren'],
      ['button.surecookie-preferences-btn','Präferenzen']
    ];
    const normalizeText=value=>String(value||'').replace(/\s+/g,' ').trim();
    const reconcile=()=>{
      selectors.forEach(([selector,fallback])=>{
        document.querySelectorAll(selector).forEach(el=>{
          const visible=normalizeText(el.innerText||el.textContent)||fallback;
          if(!visible)return;
          if(el.getAttribute('aria-label')!==visible)el.setAttribute('aria-label',visible);
          if(el.hasAttribute('aria-labelledby'))el.removeAttribute('aria-labelledby');
        });
      });
    };
    reconcile();
    if(typeof MutationObserver==='function'){
      const observer=new MutationObserver(()=>reconcile());
      observer.observe(document.documentElement,{subtree:true,childList:true,characterData:true,attributes:true,attributeFilter:['aria-label','aria-labelledby','class']});
    }
  }

  function installAriaHiddenFocusGuard(){
    const focusable='a[href],button,input:not([type=\"hidden\"]),select,textarea,[tabindex],[contenteditable=\"true\"],audio[controls],video[controls],summary';
    const reconcile=()=>{
      document.querySelectorAll('[data-rpg-a11y-inert=\"1\"]').forEach(el=>{
        if(el.getAttribute('aria-hidden')!=='true'){el.removeAttribute('inert');el.removeAttribute('data-rpg-a11y-inert');}
      });
      document.querySelectorAll('[aria-hidden=\"true\"]').forEach(el=>{
        if(el.hidden){el.removeAttribute('aria-hidden');return;}
        const child=el.querySelector(focusable);
        if(child){el.setAttribute('inert','');el.setAttribute('data-rpg-a11y-inert','1');}
      });
    };
    reconcile();
    if(typeof MutationObserver==='function'){
      const observer=new MutationObserver(()=>reconcile());
      observer.observe(document.documentElement,{subtree:true,childList:true,attributes:true,attributeFilter:['aria-hidden','hidden']});
    }
  }
  function initialDirectBuedchenItem(){
    try{
      const root=document.getElementById('ruhrpott-guru-root');
      const source=document.getElementById('rpg-direct-detail-bootstrap');
      if(!root||!source||root.dataset.directCategory!=='buedchen')return null;
      const data=JSON.parse(source.textContent||'');
      const path=/^\/ort\/([1-9]\d*)\/[^/]+\/[^/]+\/?$/i.exec(window.location.pathname);
      if(!path||root.dataset.directToken!=='p'+path[1]||data.token!==root.dataset.directToken||data.kind!=='place'||data.category!=='buedchen')return null;
      if(String(data.element&&data.element.tags&&data.element.tags['rg:place_id']||'')!==path[1])return null;
      const item=RPG.normalizePlace(data.element,'buedchen',null);
      return item&&(RPG.shareDescriptor(item)||{}).token===data.token?item:null;
    }catch(_){return null;}
  }
  function App(){
    const [detail,setDetail]=useState(initialDirectBuedchenItem);
    const relatedFromPlace=RPG.React.useRef(false);
    const [shareError,setShareError]=useState('');
    const [favoritesOpen,setFavoritesOpen]=useState(false);
    const shareBoot=RPG.React.useRef((()=>{try{const p=new URLSearchParams(window.location.search);return {token:p.get('rg_open')||'',code:p.get('rgc')||''};}catch(_){return {token:'',code:''};}})()).current;
    const root=document.getElementById('ruhrpott-guru-root');
    const directBoot=RPG.React.useRef({token:root&&root.dataset.directToken||'',category:root&&root.dataset.directCategory||'',overview:root&&root.dataset.directOverview||''}).current;
    // The existing backend resolves canonical IDs for both route types.
    // A fresh direct URL (including Google, bookmarks and reloads) uses the
    // page presentation; clicks within the running app still use the overlay.
    const directPath=window.location.pathname;
    const eventPath=/^\/veranstaltung\/([1-9]\d*)\/[^/]+\/[^/]+\/?$/i.exec(directPath);
    const placePath=/^\/ort\/([1-9]\d*)\/[^/]+\/[^/]+\/?$/i.exec(directPath);
    const directRouteMatches=!!((eventPath&&directBoot.token==='e'+eventPath[1])||(placePath&&directBoot.token==='p'+placePath[1]));
    const isHome=!!(root&&root.dataset.wpHomeShell==='1');
    const categoryKey=root&&root.dataset.categoryKey;
    const pageSource=document.getElementById('ruhrpott-wp-page-content-source');
    const isRoute=!!(pageSource&&pageSource.querySelector('[data-rg-industrial-route]')) || /\/route-industriekultur\/?$/.test(window.location.pathname);
    const isBuedchen=!!(pageSource&&pageSource.querySelector('[data-rg-buedchen-radar]')) || /\/buedchen-radar\/?$/.test(window.location.pathname);
    const isWurst=!!(pageSource&&pageSource.querySelector('[data-rg-wurst-page]')) || /\/imbissbuden\/?$/.test(window.location.pathname);
    const isGlobalSearch=!!(root&&root.dataset.globalSearchShell==='1') || /\/suche\/?$/.test(window.location.pathname);
    const isQuiz=!!(root&&root.dataset.view==='quiz') || /\/pott-quiz\/?$/.test(window.location.pathname);
    const isHalloween=!!(root&&root.dataset.view==='halloween') || /\/halloween-im-pott\/?$/.test(window.location.pathname);
    const isChristmas=!!(root&&root.dataset.view==='weihnachten') || /\/weihnachten-im-pott\/?$/.test(window.location.pathname);
    const isMartin=!!(root&&root.dataset.view==='martin') || /\/martinszuege-im-pott\/?$/.test(window.location.pathname);
    const needsLocation=(isHome||!!(categoryKey&&RPG.CATEGORIES[categoryKey])||isRoute||isBuedchen||isWurst||isHalloween||isChristmas)&&!shareBoot.token&&!directBoot.token;
    const loc=RPG.useLocation(needsLocation);
    // 25 km is a local-only initial default for Christmas. An explicit URL,
    // saved preference or shared radius cookie always wins. No implicit write.
    const [christmasRadiusOverride,setChristmasRadiusOverride]=useState(()=>{
      if(!/\/weihnachten-im-pott\/?$/.test(window.location.pathname))return null;
      try{
        const qs=new URLSearchParams(window.location.search||'');
        if(qs.has('radius')||RPG.storage.get('ruhrpottguru_radius_km_v1'))return null;
        if(document.cookie.split(';').some(x=>x.trim().startsWith('rg_radius_km_v1=')))return null;
      }catch(_){return null;}
      return 25;
    });
    const christmasRadius=isChristmas&&christmasRadiusOverride!==null?christmasRadiusOverride:loc.radius;
    const setChristmasRadius=n=>{setChristmasRadiusOverride(null);loc.setRadius(n);};
    // V352: a visitor without a location (including search-engine crawlers, which
    // have none) sees the Christmas page for a fixed Ruhrgebiet centre instead of
    // an empty "Standort auswählen" shell. A real location always wins; the
    // normal location prompt stays available.
    const christmasFallback=isChristmas&&!loc.location&&!shareBoot.token&&!directBoot.token;
    const [christmasFallbackRadius,setChristmasFallbackRadius]=useState(50);
    const christmasFallbackLocation=RPG.React.useMemo(()=>{
      const pair=(RPG.CITY_CENTERS&&RPG.CITY_CENTERS['Essen'])||[51.4556,7.0116];
      return {name:'Ruhrgebiet (Essen-Mitte)',city:'Essen',postcode:'',lat:Number(pair[0]),lng:Number(pair[1]),isGPS:false,locationMode:'default'};
    },[]);
    
    const trackedRadius=RPG.React.useRef(null);
    useEffect(function(){
      if(!loc.location)return;
      if(trackedRadius.current!==null&&trackedRadius.current!==loc.radius){
        try{window.RPG_ANALYTICS&&window.RPG_ANALYTICS.send('radius_change',{area:loc.location.city||loc.location.name||'',radius:loc.radius,placement:'location'});}catch(_){}
      }
      trackedRadius.current=loc.radius;
    },[loc.radius,loc.location&&loc.location.lat,loc.location&&loc.location.lng]);
    useEffect(function(){
      const close=document.getElementById('btn-close-menu'),back=document.getElementById('mobile-menu-backdrop'),cookie=document.getElementById('btn-menu-cookie-settings'),drawerFav=document.getElementById('btn-drawer-open-favorites');
      const openCookies=()=>{const bridge=document.getElementById('rg-surecookie-reconsent-bridge');const trigger=bridge&&bridge.querySelector('button,a');if(trigger)trigger.click();closeLegacyDrawer();};
      const openFav=()=>{closeLegacyDrawer();window.dispatchEvent(new Event('rpg:open-favorites'));};
      if(close)close.addEventListener('click',closeLegacyDrawer);if(back)back.addEventListener('click',closeLegacyDrawer);if(cookie)cookie.addEventListener('click',openCookies);if(drawerFav)drawerFav.addEventListener('click',openFav);
      return()=>{if(close)close.removeEventListener('click',closeLegacyDrawer);if(back)back.removeEventListener('click',closeLegacyDrawer);if(cookie)cookie.removeEventListener('click',openCookies);if(drawerFav)drawerFav.removeEventListener('click',openFav);};
    },[]);
    useEffect(function(){const open=()=>setFavoritesOpen(true);window.addEventListener('rpg:open-favorites',open);return()=>window.removeEventListener('rpg:open-favorites',open);},[]);
    useEffect(function(){
      if(!shareBoot.token)return;
      let live=true;
      const category=RPG.shareCategoryFromCode(shareBoot.code);
      RPG.api.shareItem(shareBoot.token,category).then(data=>{
        if(!live)return;
        let item=null;
        if(data&&data.kind==='event'&&data.item)item=RPG.normalizeEvent(data.item,loc.location||null);
        else if(data&&data.kind==='place'&&data.element)item=RPG.normalizePlace(data.element,data.category||category||'highlights',loc.location||null);
        else if(data&&data.kind==='direct'&&data.item)item=data.item;
        if(item){
          setDetail(item);setShareError('');
          try{const u=new URL(window.location.href);u.searchParams.delete('rg_open');u.searchParams.delete('rgc');history.replaceState(null,'',u.pathname+(u.searchParams.toString()?'?'+u.searchParams.toString():'')+u.hash);}catch(_){}
        }else setShareError('Der geteilte Tipp konnte nicht geöffnet werden.');
      }).catch(()=>{if(live)setShareError('Der geteilte Tipp konnte nicht geöffnet werden.');});
      return()=>{live=false;};
    },[]);
    useEffect(function(){
      if(!directBoot.token)return;
      // A validated visitor element from this response is already the detail model.
      if(detail&&detail.category==='buedchen'&&(RPG.shareDescriptor(detail)||{}).token===directBoot.token)return;
      let live=true;
      RPG.api.shareItem(directBoot.token,directBoot.category||'').then(data=>{
        if(!live)return;
        let item=null;
        if(data&&data.kind==='event'&&data.item)item=RPG.normalizeEvent(data.item,null);
        else if(data&&data.kind==='place'&&data.element)item=RPG.normalizePlace(data.element,data.category||directBoot.category||'highlights',null);
        else if(data&&data.kind==='direct'&&data.item)item=data.item;
        if(item){setDetail(item);setShareError('');}
        else setShareError('Der Tipp konnte nicht geöffnet werden.');
      }).catch(()=>{if(live)setShareError('Der Tipp konnte nicht geöffnet werden.');});
      return()=>{live=false;};
    },[]);
    useEffect(function(){
      const openLocation=()=>loc.setModalOpen(true);
      const setSharedRadius=e=>{const n=e&&e.detail?Number(e.detail.radius):NaN;if(Number.isFinite(n))loc.setRadius(n);};
      window.addEventListener('rpg:open-location',openLocation);
      window.addEventListener('rpg:set-radius',setSharedRadius);
      return()=>{window.removeEventListener('rpg:open-location',openLocation);window.removeEventListener('rpg:set-radius',setSharedRadius);};
    },[loc.setModalOpen,loc.setRadius]);
    let content;
    if(directBoot.token){content=directRouteMatches&&detail?null:h('main',{className:'rpgx-main'},h('div',{className:shareError?'rpgx-error':'rpgx-loading'},shareError||'Detail wird geöffnet …'));}
    else if(shareBoot.token&&!loc.location){content=h('main',{className:'rpgx-main'},h('div',{className:shareError?'rpgx-error':'rpgx-loading'},shareError||'Geteilter Tipp wird geöffnet …'));}
    else if(needsLocation&&!loc.location&&!christmasFallback){content=h('main',{className:'rpgx-main'},h('div',{className:'rpgx-loading'},loc.status==='locating'?'Standort wird bestimmt …':'Standort auswählen …'));}
    else if(isHome)content=h(V.HomeView,{location:loc.location,radius:loc.radius,onOpen:setDetail,onLocation:()=>loc.setModalOpen(true)});
    else if(categoryKey&&RPG.CATEGORIES[categoryKey])content=h(V.CategoryView,{category:categoryKey,location:loc.location,radius:loc.radius,onOpen:setDetail,onRadius:loc.setRadius});
    else if(isRoute)content=h(V.RouteView,{location:loc.location,onOpen:setDetail});
    else if(isBuedchen)content=h(V.BuedchenRadarView,{location:loc.location,radius:loc.radius,onRadius:loc.setRadius,onLocation:()=>loc.setModalOpen(true)});
    else if(isWurst)content=h(V.WurstView,{location:loc.location,radius:loc.radius,onRadius:loc.setRadius,onLocation:()=>loc.setModalOpen(true),onOpen:setDetail});
    else if(isGlobalSearch)content=h(V.SearchView,{location:loc.location,onOpen:setDetail});
    else if(isQuiz)content=h(V.QuizView);
    else if(isHalloween)content=h(V.HalloweenView,{location:loc.location,radius:loc.radius,onRadius:loc.setRadius,onLocation:()=>loc.setModalOpen(true),onOpen:setDetail});
    else if(isChristmas)content=h(V.ChristmasView,{location:christmasFallback?christmasFallbackLocation:loc.location,radius:christmasFallback?christmasFallbackRadius:christmasRadius,onRadius:christmasFallback?setChristmasFallbackRadius:setChristmasRadius,onLocation:()=>loc.setModalOpen(true),onOpen:setDetail});
    else if(isMartin)content=h(V.MartinView,{onOpen:setDetail});
    else content=h(V.PlainWpView);
    // Verify the resolved record agrees with the backend token and route.
    // No referrer detection, category reclassification or new REST contract.
    const loadedDetailToken=detail&&typeof RPG.shareDescriptor==='function'?(RPG.shareDescriptor(detail)||{}).token:'';
    const standaloneDetail=!!(directRouteMatches&&detail&&loadedDetailToken===directBoot.token);
    // The initial, server-rendered article stays readable and accessible during
    // network failure, JS failure or a rejected detail response. Only swap it out
    // after the existing event detail resolver has supplied the matching event.
    useEffect(function(){
      if(!standaloneDetail||!root||root.dataset.rpgDirectSsr!=='1')return;
      root.style.removeProperty('display');
      root.removeAttribute('data-rpg-direct-ssr');
      const ssr=document.getElementById('rpgx-direct-ssr');
      if(ssr)ssr.remove();
    },[standaloneDetail]);
    const closeDetail=()=>{
      if(directBoot.token){window.location.assign(directBoot.overview||RPG.settings.homeUrl||'/');return;}
      if(relatedFromPlace.current){relatedFromPlace.current=false;setDetail(null);return;}
      // V263: an externally opened Event returns to Events around the temporary
      // Event location. The coordinate query is non-persistent; saved GPS/city is
      // therefore untouched and resumes on the next normal navigation.
      if(shareBoot.token&&detail&&detail.category==='events'){
        // New direct links already run on /veranstaltungen/ with a temporary
        // eventContext, so X only removes the overlay and reveals that list.
        if(loc.location&&loc.location.eventContext&&/\/veranstaltungen\/?$/.test(window.location.pathname)){setDetail(null);return;}
        // Backward-compatible fallback for an older cached share landing.
        const contextUrl=typeof RPG.eventContextUrl==='function'?RPG.eventContextUrl(detail,loc.radius):'';
        if(contextUrl){window.location.assign(contextUrl);return;}
      }
      if(shareBoot.token&&!loc.location){window.location.assign(RPG.settings.homeUrl||'/');return;}
      setDetail(null);
    };
    // V249: Related events inside an open detail overlay are resolved through
    // the existing single-event REST/share read model. Only the overlay item is
    // replaced; Home/category/search below stays mounted and is not navigated or
    // reloaded. Modifier-clicks remain normal browser links in DetailOverlay.
    const openRelatedDetail=async related=>{
      const eventId=Number(related&&related.eventId||0);
      if(!Number.isFinite(eventId)||eventId<=0)throw new Error('Event-ID fehlt.');
      const data=await RPG.api.shareItem('e'+String(eventId),'events');
      if(!data||data.kind!=='event'||!data.item)throw new Error('Event konnte nicht geladen werden.');
      const next=RPG.normalizeEvent(data.item,loc.location||null);
      if(!next)throw new Error('Event konnte nicht aufbereitet werden.');
      if(detail&&detail.category!=='events')relatedFromPlace.current=true;
      setDetail(next);
      return true;
    };
    const applyDetailTagFilter=(item,label)=>{
      if(!item||!RPG.isTagFilterCategory(item.category))return;
      const slug=RPG.tagSlug(label);if(!slug)return;
      try{window.RPG_ANALYTICS&&window.RPG_ANALYTICS.send('filter_change',{category:item.category,area:loc.location&&(loc.location.city||loc.location.name)||'',radius:loc.radius,placement:'detail_tag',tag_filter:slug});}catch(_){}
      const directContext=!!(directBoot.token||shareBoot.token||(loc.location&&loc.location.urlContext));
      if(categoryKey===item.category&&!directContext){
        setDetail(null);
        window.dispatchEvent(new CustomEvent('rpg:category-tag-filter',{detail:{category:item.category,label,slug}}));
        return;
      }
      const url=RPG.categoryTagUrl(item.category,label,loc.radius,directContext?item:null);
      if(url)window.location.assign(url);
    };
    return h(RPG.Fragment,null,h('div',{className:'rpgx-beta-badge','aria-label':'Beta-Version'},'BETA'),h(C.Header,{location:loc.location,radius:isChristmas?christmasRadius:loc.radius,onLocation:()=>loc.setModalOpen(true)}),content,standaloneDetail?h('main',{className:'rpgx-detail-landing-page'},h(detail.category==='buedchen'?V.BudeDetail:C.DetailOverlay,{item:detail,onClose:closeDetail,onNavigate:null,onTagFilter:applyDetailTagFilter,standalone:true,overviewUrl:directBoot.overview}),detail.category==='events'?h(V.EventLandingMore,{item:detail}):null):null,h(C.FooterNav),h(C.FeedbackButton),h(C.LocationModal,{open:loc.modalOpen,location:loc.location,error:loc.error,radius:isChristmas?christmasRadius:loc.radius,onClose:()=>loc.setModalOpen(false),onGps:loc.requestGps,onPostcode:loc.choosePostcode,onAddressResult:loc.chooseAddressResult,onCity:loc.chooseCity,onRadius:isChristmas?setChristmasRadius:loc.setRadius}),detail&&!directBoot.token&&!standaloneDetail?h(C.DetailOverlay,{item:detail,onClose:closeDetail,onNavigate:openRelatedDetail,onTagFilter:applyDetailTagFilter}):null,h(C.FavoritesOverlay,{open:favoritesOpen,onClose:()=>setFavoritesOpen(false)}));
  }
  function installSameSiteAnchorOriginGuard(){
    if(typeof RPG.normalizeInternalAnchor!=='function')return;
    const scan=node=>{
      if(!node)return;
      if(node.nodeType===1&&node.matches&&node.matches('a[href]'))RPG.normalizeInternalAnchor(node);
      if(node.querySelectorAll)node.querySelectorAll('a[href]').forEach(RPG.normalizeInternalAnchor);
    };
    scan(document);
    if(!window.MutationObserver)return;
    const observer=new MutationObserver(records=>{records.forEach(record=>{
      if(record.type==='attributes'){RPG.normalizeInternalAnchor(record.target);return;}
      Array.from(record.addedNodes||[]).forEach(scan);
    });});
    observer.observe(document.documentElement,{subtree:true,childList:true,attributes:true,attributeFilter:['href']});
  }

  function mount(){installAriaHiddenFocusGuard();installSureCookieAccessibleNameFix();installSameSiteAnchorOriginGuard();const root=document.getElementById('ruhrpott-guru-root');if(!root)return;RPG.React.createRoot(root).render(h(App));}
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',mount,{once:true});else mount();
})(window);
