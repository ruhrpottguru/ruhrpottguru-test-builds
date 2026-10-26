const fs=require('node:fs'),vm=require('node:vm'),path=require('node:path'),assert=require('node:assert/strict');
const dir=path.resolve(process.argv[2]||path.resolve(__dirname,'../web/src/react-app'));
const React={createElement:(type,props,...children)=>({type,props:props||{},children}),useState:v=>[typeof v==='function'?v():v,()=>{}],useEffect:()=>{},useMemo:f=>f(),useRef:v=>({current:v})};
const window={wp:{element:React},ruhrpottSettings:{homeUrl:'https://www.pottfuchs.de/',root:'https://www.pottfuchs.de/wp-json/'},location:{origin:'https://www.pottfuchs.de',href:'https://www.pottfuchs.de/',search:''},localStorage:{getItem:()=>null},sessionStorage:{getItem:()=>null}};
const cx=vm.createContext({window,document:{getElementById:()=>null},URL,URLSearchParams,Date,Intl,Set,Map,console,setTimeout,clearTimeout,navigator:{}});
for(const file of ['runtime.js','api.js','components.js'])vm.runInContext(fs.readFileSync(path.join(dir,file),'utf8'),cx);
const R=window.RPGReact;
function routes(node,out=[]){if(Array.isArray(node)){for(const x of node)routes(x,out);}else if(node&&typeof node==='object'){if(node.type==='a'&&String(node.props.href||'').includes('/maps/dir/'))out.push(node.props.href);routes(node.children,out);}return out;}
function render(address,geo){const item=R.normalizeEvent({id:'19722',title:'OasenTag',start:'2027-10-31T10:00:00+01:00',address,geo,rpg_event_db:{eventId:19722,cityScope:!geo},rpg_geo_scope:geo?undefined:'city'},null);return routes(R.Components.DetailOverlay({item,onClose:()=>{},standalone:true}));}
const complete=render({street:'Dieselweg',houseNumber:'7',postalCode:'59379',city:'Selm',type:'event'});
assert.equal(complete.length,1);assert.equal(new URL(complete[0]).searchParams.get('destination'),'Dieselweg 7, 59379 Selm');
assert.equal(render({postalCode:'59379',city:'Selm'}).length,0);
const precise=render({city:'Selm'},{latitude:51.6908443,longitude:7.4662943});assert.equal(new URL(precise[0]).searchParams.get('destination'),'51.6908443,7.4662943');
console.log('PASS 4 actual web DetailOverlay route-link rendering assertions');
