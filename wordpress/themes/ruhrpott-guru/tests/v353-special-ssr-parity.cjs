// V353: Die PHP-Auswahlregeln (inc/special-ssr.php) müssen dieselben Treffer liefern wie die
// Regeln im JavaScript (src/react-app/views.js). Aufruf: node tests/v353-special-ssr-parity.cjs
'use strict';
const fs=require('fs'),path=require('path'),vm=require('vm'),cp=require('child_process'),assert=require('assert').strict;
process.env.TZ='Europe/Berlin';
const root=path.resolve(__dirname,'..'),src=fs.readFileSync(path.join(root,'src/react-app/views.js'),'utf8');
function grab(name){const m=src.match(new RegExp('  function '+name+'\\([^)]*\\)\\{[\\s\\S]*?\\n  \\}\\n'));if(!m)throw new Error('missing '+name);return m[0];}
const code=['christmasFold','christmasDate','christmasSeasonYear','christmasSeasonEvent','christmasCategories','martinSeasonYear','martinSeasonEvent','martinCategories'].map(grab).join('\n')+'\nthis.m=martinCategories;this.c=christmasCategories;';
const cx={Date,console};vm.createContext(cx);vm.runInContext(code,cx);
const cases=JSON.parse(fs.readFileSync(path.join(__dirname,'fixtures/special-ssr-cases.json'),'utf8'));
const NOW='2026-10-28T12:00:00';
const js=cases.map(c=>{const item={title:c.title,start:new Date(c.start),description:c.description||'',raw:{keywords:c.keywords||'',category:c.category||''}};
 const now=new Date(NOW);return {m:cx.m(item,now),x:cx.c(item,now).includes('weihnachtsmaerkte')};});
const php=cp.spawnSync('php',[path.join(__dirname,'v353-special-ssr-parity.php'),path.join(__dirname,'fixtures/special-ssr-cases.json'),NOW],{encoding:'utf8'});
assert.equal(php.status,0,php.stderr+php.stdout);
const out=JSON.parse(php.stdout.split('\n').filter(l=>l.startsWith('['))[0]);
let bad=0;cases.forEach((c,i)=>{const a=JSON.stringify(js[i].m),b=JSON.stringify(out[i].m);const ok=a===b&&js[i].x===out[i].x;if(!ok){bad++;console.log('UNGLEICH',c.title,'JS',a,js[i].x,'PHP',b,out[i].x);}});
assert.equal(bad,0);
console.log('OK: '+cases.length+' Fälle, PHP und JS wählen gleich aus');
