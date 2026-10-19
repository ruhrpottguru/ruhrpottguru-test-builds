(function(){
'use strict';
const endpoint=(window.RPG_CONFIG&&RPG_CONFIG.restBase?RPG_CONFIG.restBase:'/wp-json/ruhrpottguru/v1/')+'analytics';
const socialHosts=['instagram.com','facebook.com','m.facebook.com','l.facebook.com','tiktok.com','linkedin.com','x.com','twitter.com','pinterest.com','youtube.com','youtu.be'];
const searchHosts=['google.','bing.com','duckduckgo.com','ecosia.org','yahoo.'];
function clean(v,n){return String(v||'').trim().slice(0,n||120);}
function hostFrom(url){if(!url)return '';try{return new URL(url,location.href).hostname.replace(/^www\./,'').toLowerCase();}catch(_){return '';}}
function isInternalReferrer(){const h=hostFrom(document.referrer);return !!h&&h===location.hostname.replace(/^www\./,'').toLowerCase();}
function hostMatches(host,list){return list.some(x=>x.endsWith('.')?host.indexOf(x)>=0:(host===x||host.endsWith('.'+x)));}
function classify(source,medium,refHost,flags){
  const s=(source||'').toLowerCase(),m=(medium||'').toLowerCase();
  if(flags&&flags.gclid)return 'Paid Search';
  if(flags&&flags.meta)return 'Paid Social';
  if(/cpc|ppc|paid[_ -]?search|sem/.test(m))return 'Paid Search';
  if(/paid[_ -]?social|social[_ -]?paid/.test(m))return 'Paid Social';
  if(/email|newsletter/.test(m))return 'E-Mail';
  const socialSource=/^(instagram|facebook|meta|tiktok|linkedin|twitter|x|pinterest|youtube)$/i.test(s);
  const sh=hostMatches(refHost,socialHosts)||hostMatches(s,socialHosts)||socialSource||/social/.test(m);
  if(sh)return /cpc|paid|ads?/.test(m)?'Paid Social':'Organic Social';
  if(hostMatches(refHost,searchHosts)||hostMatches(s,searchHosts))return 'Organic Search';
  if(m==='referral'||refHost)return 'Referral';
  if((!source||source==='direct')&&(!medium||medium==='(none)'))return 'Direct';
  return 'Other';
}
function navigationType(){
  try{
    const entries=performance&&performance.getEntriesByType?performance.getEntriesByType('navigation'):[];
    if(entries&&entries[0]&&entries[0].type)return entries[0].type;
    if(performance&&performance.navigation&&performance.navigation.type===1)return 'reload';
    if(performance&&performance.navigation&&performance.navigation.type===2)return 'back_forward';
  }catch(_){}
  return 'navigate';
}
function isVisitEntry(){
  const type=navigationType();
  if(type==='reload'||type==='back_forward')return false;
  return !isInternalReferrer();
}
function deriveAttribution(){
  const q=new URLSearchParams(location.search||'');
  const utm={
    source:clean(q.get('utm_source'),100), medium:clean(q.get('utm_medium'),60),
    campaign:clean(q.get('utm_campaign'),120), content:clean(q.get('utm_content'),120)
  };
  const hasUtm=!!(utm.source||utm.medium||utm.campaign||utm.content);
  const flags={gclid:!!(q.get('gclid')||q.get('msclkid')),meta:!!(q.get('fbclid')||q.get('igshid'))};
  const internal=isInternalReferrer();
  const refHost=internal?'':hostFrom(document.referrer);
  let attr=null;
  if(hasUtm||flags.gclid||flags.meta||refHost){
    const source=utm.source||(flags.gclid?'google':flags.meta?'meta':refHost||'direct');
    const medium=utm.medium||(flags.gclid?'cpc':flags.meta?'paid_social':refHost?'referral':'(none)');
    attr={source,medium,campaign:utm.campaign,content:utm.content,referrer:refHost,channel:classify(source,medium,refHost,flags),landing:location.pathname||'/',device:device()};
  }else if(!internal&&isVisitEntry()){
    attr={source:'direct',medium:'(none)',campaign:'',content:'',referrer:'',channel:'Direct',landing:location.pathname||'/',device:device()};
  }
  return {attr,landing:!!(attr&&isVisitEntry())};
}
let state=null;
function getAttribution(){if(!state)state=deriveAttribution();return state;}
function transmit(event_type,data){
  const m=getAttribution(),a=m.attr||{};
  const marketing={mk_source:a.source||'',mk_medium:a.medium||'',mk_campaign:a.campaign||'',mk_content:a.content||'',mk_channel:a.channel||'',mk_referrer:a.referrer||'',mk_landing_path:a.landing||'',mk_device:a.device||device()};
  if(event_type==='page_view'&&m.landing){marketing.mk_landing=1;m.landing=false;}
  const payload=Object.assign({event_type},marketing,data||{});
  const body=JSON.stringify(payload);
  try{
    if(navigator.sendBeacon){
      const ok=navigator.sendBeacon(endpoint,new Blob([body],{type:'application/json'}));
      if(ok)return;
    }
    fetch(endpoint,{method:'POST',headers:{'Content-Type':'application/json'},body,keepalive:true,credentials:'same-origin'}).catch(()=>{});
  }catch(_){}
}
function pageMeta(){return {page_path:location.pathname||'/',page_title:document.title||'',page_type:pageType(),device:device()};}
function send(event_type,data){transmit(event_type,data);}
function device(){
  const w=Math.max(window.innerWidth||0,(window.screen&&screen.width)||0);
  const touch=(navigator.maxTouchPoints||0)>0;
  if(w&&w<768)return 'mobile';
  if(touch&&w&&w<1180)return 'tablet';
  return 'desktop';
}
function pageType(){
  const b=document.body;
  if(b&&b.classList.contains('home'))return 'home';
  if(b&&b.classList.contains('search'))return 'search';
  if(b&&b.classList.contains('single-post'))return 'post';
  if(/^\/(events?|food|highlights?|kultur|culture|aktiv|activities|buedchen)/i.test(location.pathname))return 'category';
  return 'page';
}
function pageView(){
  if(isVisitEntry())transmit('visit_start',pageMeta());
  send('page_view',pageMeta());
}
function burgerItemLabel(el){
  if(!el)return '';
  if(el.id==='btn-menu-cookie-settings')return 'Cookie-Einstellungen';
  if(el.id==='btn-drawer-open-favorites')return 'Merkzettel';
  const named=el.querySelector&&el.querySelector('.font-extrabold');
  const raw=(named&&named.textContent)||el.getAttribute('aria-label')||el.textContent||'';
  return clean(raw.replace(/\s+/g,' '),120);
}
function bindBurgerTracking(){
  const drawer=document.getElementById('mobile-menu-drawer');
  if(!drawer||drawer.dataset.rpgAnalyticsBound==='1')return;
  drawer.dataset.rpgAnalyticsBound='1';
  drawer.addEventListener('click',function(ev){
    const el=ev.target&&ev.target.closest?ev.target.closest('a,button'):null;
    if(!el||!drawer.contains(el)||el.id==='btn-close-menu')return;
    const label=burgerItemLabel(el);
    if(!label)return;
    send('burger_menu_click',{placement:'burger-menu',content_id:label});
  });
}
function boot(){bindBurgerTracking();pageView();}
window.RPG_ANALYTICS={send};
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot,{once:true});else boot();
})();
