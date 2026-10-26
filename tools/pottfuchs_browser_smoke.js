// Read-only PottFuchs browser smoke checks. Uses synthetic Wesel GPS, never
// submits forms, logs in, consents, or alters WordPress / remote state.
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

(async () => {
  const out = 'audit/browser';
  fs.mkdirSync(out, { recursive: true });
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const cases = [
    ['home-wesel', 'https://www.pottfuchs.de/?place=wesel&radius=5'],
    ['events-wesel', 'https://www.pottfuchs.de/veranstaltungen/?place=wesel&radius=5'],
    ['food-wesel', 'https://www.pottfuchs.de/food/?place=wesel&radius=5'],
    ['highlights-wesel', 'https://www.pottfuchs.de/highlights/?place=wesel&radius=5'],
    ['culture-wesel', 'https://www.pottfuchs.de/kultur/?place=wesel&radius=5'],
    ['activities-wesel', 'https://www.pottfuchs.de/aktiv/?place=wesel&radius=5'],
    ['detail-zum-schwarzen', 'https://www.pottfuchs.de/ort/16936/essen/zum-schwarzen/'],
    ['detail-lichtwochen', 'https://www.pottfuchs.de/veranstaltung/6936/essen/77-essener-lichtwochen/']
  ];
  const context = await browser.newContext({
    viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true,
    locale: 'de-DE', geolocation: { latitude: 51.66, longitude: 6.63, accuracy: 80 },
    permissions: ['geolocation'], serviceWorkers: 'block'
  });
  const report = { at: new Date().toISOString(),
    note: 'Synthetic public mobile Chrome only. Screenshots and DOM smoke; not full user GPS/regression proof.',
    cases: [] };
  for (const [key, url] of cases) {
    const page = await context.newPage();
    const errors = [], serverFailures = [];
    page.on('pageerror', err => errors.push(String(err.message).slice(0,250)));
    page.on('response', response => { if (response.status() >= 500) serverFailures.push({
      status: response.status(), url: response.url().replace(/[?].*$/, '').slice(0,180)
    }); });
    let code = null, failure = null;
    try {
      const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });
      code = response ? response.status() : null;
      await page.waitForTimeout(7000);
      const info = await page.evaluate(() => {
        const root = document.querySelector('#ruhrpott-guru-root');
        const texts = Array.from(document.querySelectorAll('main h1, h1')).map(n => (n.innerText||'').trim()).filter(Boolean);
        const cards = document.querySelectorAll('main article, [data-testid*="card"], .rpgx-card, .rpg-card').length;
        const pending = Array.from(document.querySelectorAll('.rpgx-loading')).some(n => {
          const r = n.getBoundingClientRect(), s = getComputedStyle(n);
          return r.width>0 && r.height>0 && s.visibility!=='hidden' && s.display!=='none';
        });
        const body = document.body.innerText||'';
        return { title:document.title,headings:texts.slice(0,4),reactRoot:!!root,
          cards,pending,footer:!!document.querySelector('footer'),
          locationText:body.match(/(?:Wesel|Essen|Standort wird bestimmt|Standort auswählen|Standort ändern)/i)?.[0]||null,
          bodySample:body.slice(0,300) };
      });
      report.cases.push({ key,url,http:code, ...info, errors,serverFailures,
        screenshot: out+'/'+key+'.png'});
      await page.screenshot({path:out+'/'+key+'.png',fullPage:false,animations:'disabled'});
    } catch (err) {
      failure = String(err).slice(0,400);
      report.cases.push({key,url,http:code,error:failure,errors,serverFailures});
    } finally {await page.close();}
  }
  await browser.close();
  fs.writeFileSync(out+'/pottfuchs-browser-smoke.json',JSON.stringify(report,null,2)+'\n');
  console.log('MOBILE POTT FUCHS READ-ONLY BROWSER CHECK:',report.cases.length,'cases');
  for (const a of report.cases) console.log(JSON.stringify({case:a.key,http:a.http,root:a.reactRoot,
    h1:a.headings,loading:a.pending,cards:a.cards,errors:a.errors,failures:a.serverFailures,error:a.error}));
})().catch(e => { console.error(e); process.exit(1); });
