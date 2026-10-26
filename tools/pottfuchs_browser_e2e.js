// Read-only public user journeys, never writes WordPress/hosting/application data.
// Cookies/localStorage are deliberately changed in an isolated throwaway browser context.
const fs = require('fs');
const {chromium} = require('playwright');
const OUT='audit/e2e';
fs.mkdirSync(OUT,{recursive:true});
function result(name, status, evidence={}, error=null){return {name,status,evidence,error};}
const tick = ms => new Promise(ok=>setTimeout(ok,ms));
(async()=>{
 const browser=await chromium.launch({headless:true,args:['--no-sandbox']});
 const ctx=await browser.newContext({
   viewport:{width:390,height:844},isMobile:true,hasTouch:true,
   locale:'de-DE',timezoneId:'Europe/Berlin',
   geolocation:{latitude:51.66,longitude:6.63,accuracy:75},
   permissions:['geolocation'],serviceWorkers:'block'
 });
 const page=await ctx.newPage();
 const report={at:new Date().toISOString(), environment:'Chromium mobile synthetic Wesel GPS; isolated cookies, no WP writes',tests:[]};
 const pageErrors=[];page.on('pageerror',e=>pageErrors.push(e.message.slice(0,180)));
 async function test(name,action){
  const start=Date.now();
  try{const evidence=await action();report.tests.push({...result(name,'PASS',evidence),duration_ms:Date.now()-start});}
  catch(e){
    const diagnostics=await page.evaluate(()=>({url:location.href,headings:[...document.querySelectorAll('h1')].map(x=>x.textContent.trim()).slice(0,3),
      modalError:document.querySelector('.rpgx-location-error')?.textContent?.trim()||null,
      loading:!!document.querySelector('.rpgx-loading'),
      cards:document.querySelectorAll('.rpgx-grid .rpgx-card').length,
      visibleText:(document.body.innerText||'').slice(0,450)})).catch(()=>({}));
    await page.screenshot({path:OUT+'/failure-'+name+'.png'}).catch(()=>{});
    report.tests.push({...result(name,'FAIL',diagnostics,String(e).slice(0,900)),duration_ms:Date.now()-start});
  }
  console.log(JSON.stringify(report.tests[report.tests.length-1]));
 }
 const select=()=>page.locator('.rpgx-location-modal');
 async function cookie(){
  const btn=page.getByRole('button',{name:/Nur Wesentliche/i});
  if(await btn.count() && await btn.first().isVisible()){await btn.first().click({timeout:12000});await page.waitForTimeout(750);return 'essential chosen in isolated test browser';}
  return 'no consent dialog shown';
 }
 async function open(url){
  const res=await page.goto(url,{waitUntil:'domcontentloaded',timeout:35000});
  await page.waitForTimeout(4500);
  if(res.status()!==200)throw new Error('HTTP '+res.status()+' '+url);
  return res.status();
 }
 function requireThat(ok,msg){if(!ok)throw new Error(msg);}
 await test('cookie-banner-essential-only',async()=>{
  await open('https://www.pottfuchs.de/?place=wesel&radius=5');
  const consent=await cookie();
  await page.screenshot({path:OUT+'/home-after-consent.png'});
  return {consent, bannerVisible:await page.getByRole('button',{name:/Nur Wesentliche/i}).first().isVisible().catch(()=>false)};
 });
 await test('five-categories-after-consent',async()=>{
  const paths=['/veranstaltungen/','/food/','/highlights/','/kultur/','/aktiv/'];
  const evidence=[];
  for(const path of paths){
   await open('https://www.pottfuchs.de'+path+'?place=wesel&radius=5');
   // Skeletons use the same card class; require an actual clickable card with title.
   await page.locator('.rpgx-grid a.rpgx-card h3').first().waitFor({state:'visible',timeout:25000}).catch(()=>{});
   const data=await page.evaluate(()=>{
    const all=[...document.querySelectorAll('.rpgx-grid .rpgx-card')];
    const els=[...document.querySelectorAll('.rpgx-grid a.rpgx-card')].filter(x=>x.querySelector('h3')?.textContent?.trim());
    return {cards:els.length,skeletonOrOtherCards:all.length-els.length,
      first:els[0]?.querySelector('h3')?.textContent?.trim()||'',
      renderedVisible:els.some(el=>{const style=getComputedStyle(el);const box=el.getBoundingClientRect();return box.width>10&&box.height>10&&style.visibility!=='hidden'&&style.display!=='none'}),
      hasCategoryHeading:!!document.querySelector('main h1')};
   });
   evidence.push({path,...data});
   requireThat(data.cards>0&&data.renderedVisible,'missing LOADED clickable cards '+path);
  }
  await page.screenshot({path:OUT+'/activities-after-consent.png'});
  return {paths:evidence};
 });
 await test('filter-chip-from-category-detail',async()=>{
  await open('https://www.pottfuchs.de/highlights/?place=wesel&radius=10');
  const cards=page.locator('.rpgx-grid a.rpgx-card');
  await cards.first().waitFor({state:'visible',timeout:18000}).catch(()=>{});
  requireThat(await cards.count()>0,'no clickable card in highlights after 18s');
  const label=(await cards.first().locator('h3').textContent())?.trim();
  await cards.first().click({timeout:12000});
  await page.waitForTimeout(700);
  const tagBtns=page.locator('button.rpgx-detail-tag-filter');
  const tags=await tagBtns.allTextContents();
  requireThat(tags.length>0,'no clickable detail chips');
  const first=(tags[0]||'').trim();
  await tagBtns.first().click({timeout:12000});
  await page.waitForTimeout(950);
  const active=await page.locator('.rpgx-active-place-tag').allTextContents();
  const url=page.url();
  requireThat(active.length>0 || url.includes('rg_tag='),'chip did not activate category tag filter');
  await page.screenshot({path:OUT+'/after-detail-chip.png'});
  return {card:label,chip:first,tagUrl:url.replace(/https:\/\/www\.pottfuchs\.de/,'').slice(0,180),active:active.slice(0,3)};
 });
 await test('events-calendar-filter',async()=>{
  await open('https://www.pottfuchs.de/veranstaltungen/?place=wesel&radius=10');
  const cal=page.locator('button.rpgx-event-calendar-btn');
  requireThat(await cal.count()===1,'calendar button missing');
  await cal.click();
  await page.locator('.rpgx-event-date-presets').getByRole('button',{name:'Morgen'}).click();
  await page.waitForTimeout(650);
  const chip=await page.locator('button.rpgx-event-date-chip').allTextContents();
  requireThat(chip.length===1,'tomorrow calendar chip missing');
  await page.locator('button.rpgx-event-date-chip').click();
  return {tomorrowChip:chip,resetDone:await page.locator('button.rpgx-event-date-chip').count()===0};
 });
 await test('manual-city-persists-across-navigation-and-reload',async()=>{
  await open('https://www.pottfuchs.de/');
  await cookie();
  const change=page.locator('button.rpgx-location-change').first();
  requireThat(await change.count()>0,'location edit button missing');
  await change.click({timeout:10000});
  requireThat(await select().count()===1,'location modal missing');
  await select().locator('button.rpgx-city-btn').filter({hasText:/^Essen$/}).click({timeout:10000});
  await page.waitForTimeout(1200);
  const before=await page.locator('.rpgx-location-current').first().textContent();
  await open('https://www.pottfuchs.de/food/');
  const navigated=await page.locator('.rpgx-location-current').first().textContent();
  await page.reload({waitUntil:'domcontentloaded',timeout:35000});
  await page.waitForTimeout(3300);
  const reloaded=await page.locator('.rpgx-location-current').first().textContent();
  requireThat(/Essen/i.test(before||'')&&/Essen/i.test(navigated||'')&&/Essen/i.test(reloaded||''),'manual Essen selection not preserved');
  await page.screenshot({path:OUT+'/manual-city-reload.png'});
  return {before,navigated,reloaded};
 });
 await test('radius-persists-across-reload',async()=>{
  const change=page.locator('button.rpgx-location-change').first();
  await change.click();
  const radiusSelect=select().locator('.rpgx-radius-row select');
  requireThat(await radiusSelect.count()===1,'radius selector missing');
  await radiusSelect.selectOption('10');
  await select().locator('.rpgx-radius-row button.rpgx-radius-apply').click();
  await page.waitForTimeout(700);
  const before=await page.locator('.rpgx-location-current').first().textContent();
  await page.reload({waitUntil:'domcontentloaded',timeout:35000});await page.waitForTimeout(3300);
  const after=await page.locator('.rpgx-location-current').first().textContent();
  requireThat(/10\s*km/.test(before||'')&&/10\s*km/.test(after||''),'radius 10km not preserved');
  return {before,after};
 });
 await test('postcode-45888-resolves',async()=>{
  await page.locator('button.rpgx-location-change').first().click();
  await select().locator('button.rpgx-postcode-toggle').click();
  await select().locator('#rpgx-postcode-input').fill('45888');
  await select().locator('form.rpgx-postcode-form button[type="submit"]').click();
  await page.waitForFunction(()=>!document.querySelector('.rpgx-location-modal'),null,{timeout:12500}).catch(()=>{});
  const stillModal=await select().count();
  const location=await page.locator('.rpgx-location-current').first().textContent();
  requireThat(!stillModal&&/Gelsenkirchen|45888/i.test(location||''),'postcode lookup failed or modal stayed open: modal='+stillModal+' location='+JSON.stringify(location));
  await page.screenshot({path:OUT+'/postcode-45888.png'});
  return {location};
 });
 await test('global-search-topgolf-variants-in-real-browser',async()=>{
   const queries=['topgolf','topgolf oberhausen','top golf oberhausen'];
   const checks=[];
   for(const term of queries){
     const url='https://www.pottfuchs.de/suche/?q='+encodeURIComponent(term);
     await open(url);
     await page.locator('.rpgx-search-summary').waitFor({state:'visible',timeout:14000});
     const count=await page.locator('.rpgx-search-result').count();
     const text=await page.locator('.rpgx-search-result-copy').allInnerTexts();
     checks.push({term,count,items:text.slice(0,4)});
     requireThat(count===1 && /Topgolf/i.test(text.join(' ')) && /Oberhausen/i.test(text.join(' ')),
       'global search does not show exactly one Topgolf Oberhausen for '+term);
   }
   await page.screenshot({path:OUT+'/topgolf-global-search.png'});
   return {checks};
 });
 await test('apex-domain-city-radius-state-after-301',async()=>{
   // Selection from earlier 45888 postcode check is expected to persist at 10 km.
   const old=await page.locator('.rpgx-location-current').first().textContent().catch(()=>null);
   const status=await open('https://pottfuchs.de/food/');
   const finalUrl=page.url();
   const after=await page.locator('.rpgx-location-current').first().textContent().catch(()=>null);
   requireThat(/^https:\/\/www\.pottfuchs\.de\/food\/?/.test(finalUrl),'apex did not arrive at HTTPS www');
   requireThat(/45888/i.test(after||'') && /10\s*km/i.test(after||''),'selected location/radius lost after apex redirect');
   return {status,finalUrl,old,after};
 });
 await test('denied-gps-first-load-fallback-manual-city',async()=>{
   const denied=await browser.newContext({viewport:{width:390,height:844},isMobile:true,hasTouch:true,locale:'de-DE',serviceWorkers:'block'});
   await denied.clearPermissions();
   const deniedPage=await denied.newPage();
   try{
     const response=await deniedPage.goto('https://www.pottfuchs.de/',{waitUntil:'domcontentloaded',timeout:35000});
     await deniedPage.waitForTimeout(2500);
     // Initial browser has no prior location or persisted consent.
     const before=await deniedPage.evaluate(()=>({cards:document.querySelectorAll('.rpgx-card').length,
       modal:!!document.querySelector('.rpgx-location-modal'),
       text:(document.body.innerText||'').slice(0,150)}));
     requireThat(before.cards===0,'initial cards rendered before position known');
     await deniedPage.locator('.rpgx-location-modal').waitFor({state:'visible',timeout:16000});
     const essential=deniedPage.getByRole('button',{name:/Nur Wesentliche/i}).first();
     if(await essential.count() && await essential.isVisible())await essential.click();
     await deniedPage.locator('button.rpgx-city-btn').filter({hasText:/^Essen$/}).click({timeout:14000});
     await deniedPage.waitForTimeout(1900);
     const after=await deniedPage.locator('.rpgx-location-current').first().textContent();
     requireThat(/Essen/i.test(after||''),'manual city fallback failed after GPS rejection');
     await deniedPage.screenshot({path:OUT+'/denied-gps-fallback.png'});
     return {http:response.status(),initial:before,selected:after};
   } finally{await denied.close();}
 });
 await test('site-specials-read-only',async()=>{
   const paths=['/buedchen-radar/','/imbissbuden/','/halloween-im-pott/','/zeitreise-von-oben/'];
   const records=[];
   for(const p of paths){
    const r=await page.goto('https://www.pottfuchs.de'+p,{waitUntil:'domcontentloaded',timeout:35000});
    await page.waitForTimeout(2800);
    const h1=await page.locator('h1').allTextContents();
    records.push({path:p,http:r.status(),h1:h1.slice(0,2)});
   }
   return {records};
 });
 report.pageErrors=pageErrors;
 await browser.close();
 fs.writeFileSync(OUT+'/public-browser-e2e.json',JSON.stringify(report,null,2)+'\n');
 console.log('SUMMARY',JSON.stringify({pass:report.tests.filter(x=>x.status==='PASS').length,fail:report.tests.filter(x=>x.status==='FAIL').length,total:report.tests.length,errors:pageErrors.length}));
 if(report.tests.some(x=>x.status==='FAIL'))process.exitCode=1;
})().catch(e=>{console.error(e);process.exit(1)});
