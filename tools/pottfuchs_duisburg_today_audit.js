// Independent read-only browser reproduction of the documented Duisburg "Heute noch · 5 km" case.
// Does not modify WordPress, user accounts, production cookies or data sources.
const fs=require('fs'),{chromium}=require('playwright');
(async()=>{
const out='audit/duisburg';
fs.mkdirSync(out,{recursive:true});
const browser=await chromium.launch({headless:true,args:['--no-sandbox']});
const ctx=await browser.newContext({viewport:{width:390,height:844},isMobile:true,hasTouch:true,
 locale:'de-DE',timezoneId:'Europe/Berlin',permissions:['geolocation'],
 geolocation:{latitude:51.4344,longitude:6.7623,accuracy:70},serviceWorkers:'block'});
const records=[],errors=[];const page=await ctx.newPage();
page.on('pageerror',e=>errors.push(e.message.slice(0,220)));
page.on('response',r=>{if(r.status()>=500)errors.push('HTTP '+r.status()+' '+r.url().split('?')[0]);});
async function check(place,radius){
 const q='?place='+place+'&radius='+radius;
 const res=await page.goto('https://www.pottfuchs.de/'+q,{waitUntil:'domcontentloaded',timeout:35000});
 const essential=page.getByRole('button',{name:/Nur Wesentliche/i}).first();
 if(await essential.count() && await essential.isVisible())await essential.click({timeout:12000});
 await page.waitForTimeout(9500);
 const d=await page.evaluate(()=>{
   const categoryLinks=[...document.querySelectorAll('.rg-home-event-mag-card')];
   const first=categoryLinks.slice(0,4).map(x=>(x.innerText||'').trim().slice(0,110));
   const body=document.body.innerText||'';
   const active=[...document.querySelectorAll('button.rg-home-event-time-button')].map(x=>({text:(x.innerText||'').trim(),pressed:x.getAttribute('aria-pressed')}));
   return {locationLine:document.querySelector('.rpgx-location-current')?.textContent||'',
     activeTabs:active,cardCount:categoryLinks.length,first,
     noTodayText:body.includes('Für heute werden hier derzeit keine passenden Termine mehr angezeigt'),
     loadingVisible:[...document.querySelectorAll('.rpgx-loading')].some(x=>getComputedStyle(x).display!=='none'),
     hasMoreLink:body.includes('Alle Events')};
 });
 await page.screenshot({path:out+'/home-'+place+'-'+radius+'.png',animations:'disabled'});
 records.push({test:'home-today',place,radius,http:res.status(),url:page.url(),...d});
 const target='https://www.pottfuchs.de/veranstaltungen/'+q;
 const category=await page.goto(target,{waitUntil:'domcontentloaded',timeout:35000});
 await page.waitForTimeout(8500);
 const todayBefore=await page.locator('.rpgx-grid a.rpgx-card h3').allTextContents();
 const button=page.locator('button.rpgx-event-calendar-btn');
 if(await button.count()){
   await button.click();
   await page.locator('.rpgx-event-date-presets').getByRole('button',{name:'Heute',exact:true}).click();
   await page.waitForTimeout(800);
 }
 const todayAfter=await page.locator('.rpgx-grid a.rpgx-card h3').allTextContents();
 const pill=await page.locator('button.rpgx-event-date-chip').allTextContents();
 records.push({test:'events-today-filter',place,radius,http:category.status(),totalBefore:todayBefore.length,
  totalAfter:todayAfter.length,activeDatePill:pill,firstTitles:todayAfter.slice(0,4).map(x=>x.slice(0,100)),
  pageUrl:page.url()});
 await page.screenshot({path:out+'/events-'+place+'-'+radius+'.png',animations:'disabled'});
}
try{
 await check('duisburg',5);
 await check('duisburg',10);
 await check('wesel',5);
}finally{await ctx.close();await browser.close();}
const report={at:new Date().toISOString(),
 method:'Public Chromium mobile; consent left unchanged; site uses synthetic city URL selection; GET-only UI',
 records,errors};
fs.writeFileSync(out+'/results.json',JSON.stringify(report,null,2)+'\n');
records.forEach(x=>console.log('CITY_TIME',JSON.stringify(x)));
console.log('JS_ERRORS',JSON.stringify(errors));
if(errors.length)process.exitCode=1;
})().catch(e=>{console.error(e);process.exit(1)});
