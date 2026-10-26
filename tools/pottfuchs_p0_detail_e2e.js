// PottFuchs P0 – publicly read-only direct-entry and internal-detail regression.
// Browser clicks are confined to throwaway local context; no admin or user-content POST.
const fs=require('fs'),{chromium}=require('playwright');
const OUT='audit/p0-detail';
fs.mkdirSync(OUT,{recursive:true});
const BASE='https://www.pottfuchs.de';
const testUrls=[
 ['essen-place','/ort/16936/essen/zum-schwarzen/','place'],
 ['wesel-food','/ort/10523/wesel/weseler-wirtshaus/','place'],
 ['essen-event','/veranstaltung/6936/essen/77-essener-lichtwochen/','event']
];
const report={at:new Date().toISOString(),scope:'P0 public desktop+mobile, direct URL, reload, SEO and internal overlay; synthetic GPS; no production writes',checks:[]};
function assert(b,msg){if(!b)throw Error(msg);}
(async()=>{
 const browser=await chromium.launch({headless:true,args:['--no-sandbox']});
 for(const variant of [
  {name:'mobile',opts:{viewport:{width:390,height:844},isMobile:true,hasTouch:true}},
  {name:'desktop',opts:{viewport:{width:1440,height:900},isMobile:false,hasTouch:false}}
 ]){
  const ctx=await browser.newContext({...variant.opts,locale:'de-DE',timezoneId:'Europe/Berlin',geolocation:{latitude:51.66,longitude:6.63,accuracy:75},permissions:['geolocation'],serviceWorkers:'block'});
  const page=await ctx.newPage(),errors=[];
  page.on('pageerror',e=>errors.push(String(e.message).slice(0,250)));
  async function check(name,fn) {
   try {const details=await fn();report.checks.push({viewport:variant.name,name,status:'PASS',...details});}
   catch(e){const info=await page.evaluate(()=>({url:location.href,title:document.title,text:(document.body.innerText||'').slice(0,240)})).catch(()=>({}));
    report.checks.push({viewport:variant.name,name,status:'FAIL',reason:String(e).slice(0,550),info});
    await page.screenshot({path:`${OUT}/fail-${variant.name}-${name}.png`,animations:'disabled'}).catch(()=>{});
   }
  }
  for(const [id,route,type] of testUrls){
   await check('direct-'+id,async()=>{
    const started=Date.now();const res=await page.goto(BASE+route,{waitUntil:'domcontentloaded',timeout:35000});
    assert(res&&res.status()===200,'direct URL HTTP != 200');
    const html=await page.content();
    const raw=await res.text();
    const rawArticle=raw.includes('rpgx-direct-ssr')||raw.includes('rpgx-detail-landing')||raw.includes('rpgx-direct-detail-panel');
    await page.waitForTimeout(3500);
    const m=await page.evaluate(()=>{
      const visible=el=>!!el&&getComputedStyle(el).display!=='none'&&el.getBoundingClientRect().height>1;
      const canonical=[...document.querySelectorAll('link[rel="canonical"]')].map(x=>x.href);
      const schemas=[...document.querySelectorAll('script[type="application/ld+json"]')].map(x=>{try{return JSON.parse(x.textContent)}catch(_){return null}});
      return {h1:document.querySelectorAll('h1').length,canonicals:canonical,
        descriptionCount:document.querySelectorAll('meta[name="description"]').length,
        footerVisible:[...document.querySelectorAll('footer')].some(visible),
        headerVisible:[...document.querySelectorAll('header')].some(visible),
        chromeNodes:['header','footer','.rpgx-header','.rpgx-footer','#ruhrpott-guru-root'].map(sel=>({
          sel,nodes:[...document.querySelectorAll(sel)].slice(0,2).map(el=>({class:el.className||'',
            display:getComputedStyle(el).display,visibility:getComputedStyle(el).visibility,
            rect:el.getBoundingClientRect().toJSON(),htmlSnippet:el.outerHTML.slice(0,200)}))})),
        bodyClass:document.body.className,
        htmlClass:document.documentElement.className,
        rawFooterCount:document.querySelectorAll('footer').length,
        rawHeaderCount:document.querySelectorAll('header').length,
        detailVisible:[...document.querySelectorAll('.rpgx-detail-landing,.rpgx-direct-detail-panel,.rpgx-direct-ssr')].some(visible),
        mobileOverflow:document.documentElement.scrollWidth>innerWidth+8,
        ldScripts:schemas.filter(Boolean).length,
        title:document.title,renderedText:(document.querySelector('main')?.innerText||'').slice(0,180)
      };
    });
    assert(rawArticle,'no server-rendered detail HTML in initial response');
    assert(m.h1===1,'direct detail H1 count '+m.h1);
    assert(m.canonicals.length===1&&m.canonicals[0]===BASE+route,'canonical mismatch');
    assert(m.descriptionCount===1,'meta description duplicate/missing');
    assert(m.footerVisible&&m.headerVisible,'header/footer not visible after hydration '+JSON.stringify({chrome:m.chromeNodes,bodyClass:m.bodyClass,htmlClass:m.htmlClass}));
    assert(m.detailVisible,'detail not visible after hydration');
    assert(!m.mobileOverflow,'horizontal overflow');
    if(type==='event')assert(/Lichtwochen/i.test(raw),'event description absent initial response');
    await page.reload({waitUntil:'domcontentloaded',timeout:35000});
    await page.waitForTimeout(2500);
    assert(await page.locator('h1').count()===1,'reload changed h1');
    await page.screenshot({path:`${OUT}/${variant.name}-${id}.png`,animations:'disabled'});
    return {initialHtmlHasDetail:true,msUntilLoaded:Date.now()-started,header:m.headerVisible,footer:m.footerVisible,canonical:m.canonicals[0],title:m.title,errorCount:errors.length};
   });
  }
  for(const [id,route] of [['place','/highlights/?place=wesel&radius=10'],['event','/veranstaltungen/?place=wesel&radius=10']]){
   await check('internal-overlay-'+id,async()=>{
    await page.goto(BASE+route,{waitUntil:'domcontentloaded',timeout:35000});
    await page.waitForTimeout(3000);
    const consent=page.getByRole('button',{name:/Nur Wesentliche/i});
    if(await consent.count()&&await consent.first().isVisible())await consent.first().click().catch(()=>{});
    const cards=page.locator('.rpgx-grid a.rpgx-card').filter({has:page.locator('h3')});
    await cards.first().waitFor({state:'visible',timeout:23000});
    const cardTitle=(await cards.first().locator('h3').textContent()||'').trim();
    const href=await cards.first().getAttribute('href');
    assert(href&&(/\/ort\/|\/veranstaltung\//).test(href),'missing canonical card deep link');
    const before=page.url();
    await cards.first().click();
    const overlay=page.locator('.rpgx-global-detail-overlay[role="dialog"]');
    await overlay.waitFor({state:'visible',timeout:12000});
    assert(page.url()===before,'internal overlay unexpectedly navigates away');
    const dialogTitle=await overlay.getAttribute('aria-label');
    await page.keyboard.press('Escape');
    await overlay.waitFor({state:'detached',timeout:12000});
    assert(page.url()===before,'escape did not preserve listing URL');
    assert(await page.locator('.rpgx-grid a.rpgx-card').count()>0,'category listing lost after close');
    return {cardTitle,href,dialogTitle,overlayClosed:true,listingPreserved:true};
   });
  }
  await check('direct-invalid-detail-url',async()=>{
    const r=await page.goto(BASE+'/ort/999999999999/test/kein-ort/',{waitUntil:'domcontentloaded',timeout:35000});
    const status=r.status();const text=(await page.locator('body').innerText()).slice(0,220);
    assert([404,410].includes(status),'invalid place must be 404/410; got '+status);
    return {status,text};
  });
  report.checks.push({viewport:variant.name,name:'js-runtime-errors',status:errors.length?'FAIL':'PASS',errors});
  await ctx.close();
 }
 await browser.close();
 fs.writeFileSync(OUT+'/report.json',JSON.stringify(report,null,2)+'\n');
 const pass=report.checks.filter(x=>x.status==='PASS').length,fail=report.checks.filter(x=>x.status==='FAIL').length;
 for(const x of report.checks)console.log(JSON.stringify(x));
 console.log('P0_REGRESSION_SUMMARY '+JSON.stringify({total:report.checks.length,pass,fail}));
 if(fail)process.exitCode=1;
})().catch(e=>{console.error(e);process.exit(2)});
