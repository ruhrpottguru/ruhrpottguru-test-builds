const fs=require('fs'),vm=require('vm'),path=require('path');
const root=path.resolve(__dirname,'..');
function assert(ok,msg){if(!ok)throw new Error(msg);console.log('PASS '+msg);}

// A. current-origin navigation normalizer, executed from the shipped runtime.
const win={location:{href:'https://ruhrpottguru.de/highlights/',hostname:'ruhrpottguru.de',origin:'https://ruhrpottguru.de'},wp:{element:{createElement(){},Fragment:{}}}};
win.window=win;
const ctx={window:win,URL,console,Map,Set,JSON,String,Number,Object,Array,RegExp,Math,Date};
vm.createContext(ctx);vm.runInContext(fs.readFileSync(path.join(root,'src/react-app/runtime.js'),'utf8'),ctx);
const RPG=win.RPGReact;
const anchor=href=>({v:href,getAttribute(k){return k==='href'?this.v:null;},setAttribute(k,v){if(k==='href')this.v=v;}});
let a=anchor('https://www.ruhrpottguru.de/kultur/?radius=25#x');
assert(RPG.normalizeInternalAnchor(a)===true && a.v==='/kultur/?radius=25#x','www menu link stays on non-www current origin');
a=anchor('https://ruhrpottguru.de/kultur/');
assert(RPG.normalizeInternalAnchor(a)===false && a.v==='https://ruhrpottguru.de/kultur/','same-origin link is left untouched');
a=anchor('https://example.org/kultur/');
assert(RPG.normalizeInternalAnchor(a)===false && a.v==='https://example.org/kultur/','external link is left untouched');
win.location={href:'https://www.ruhrpottguru.de/highlights/',hostname:'www.ruhrpottguru.de',origin:'https://www.ruhrpottguru.de'};
a=anchor('https://ruhrpottguru.de/kultur/');
assert(RPG.normalizeInternalAnchor(a)===true && a.v==='/kultur/','non-www menu link stays on www current origin');


const main=fs.readFileSync(path.join(root,'src/react-app/main.js'),'utf8');
assert(main.includes('installSameSiteAnchorOriginGuard()'),'mount installs cross-host anchor normalization');
assert(main.includes("attributeFilter:['href']"),'anchor normalization watches dynamically rendered React links');

// B. place search must use the same Media component/resolver path as category cards.
const views=fs.readFileSync(path.join(root,'src/react-app/views.js'),'utf8');
assert(/r\.kind==='place'[\s\S]{0,500}\?h\(C\.Media,\{item:view\}\)/.test(views),'global-search Place card delegates rendering to C.Media');
const components=fs.readFileSync(path.join(root,'src/react-app/components.js'),'utf8');
assert(/if\(item&&!blocked&&!next\)\{RPG\.resolveItemMedia\(item\)/.test(components),'C.Media resolves missing Place images through shared resolver');
assert(/referrerPolicy:'no-referrer'/.test(components),'shared card media keeps no-referrer image transport');

// C. CSS supports the generic C.Media fallback in search without layout regression.
const css=fs.readFileSync(path.join(root,'style.css'),'utf8');
assert(css.includes('.rpgx-search-result>.rpgx-image-fallback{width:86px;height:76px'), 'desktop search fallback retains search-card dimensions');
assert(css.includes('.rpgx-search-result>.rpgx-image-fallback{width:72px;height:68px'), 'mobile search fallback retains search-card dimensions');

console.log('V312 regression: OK');
