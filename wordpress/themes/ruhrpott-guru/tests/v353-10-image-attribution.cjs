const fs=require('fs'),vm=require('vm'),assert=require('assert'),path=require('path');
const dir=path.join(__dirname,'../src/react-app/');
const data={};const document={getElementById:id=>data[id]?{textContent:JSON.stringify(data[id])}:null};
const window={wp:{element:{createElement:(type,props,...children)=>({type,props:props||{},children}),Fragment:'fragment'}},ruhrpottSettings:{homeUrl:'https://www.ruhrpottguru.de/',root:'https://www.ruhrpottguru.de/wp-json/'},location:{origin:'https://www.ruhrpottguru.de',pathname:'/',search:''}};
const context=vm.createContext({window,document,URL,URLSearchParams,Date,Intl,console,Set,Map,setTimeout,clearTimeout,navigator:{},fetch:()=>{throw Error('Unexpected HTTP')}});
for(const file of ['runtime.js','api.js'])vm.runInContext(fs.readFileSync(dir+file,'utf8'),context);
const RPG=window.RPGReact;let checks=0;const ok=(v,m)=>{assert(v,m);checks++;};
const thumb='https://thumb.wikimedia.org/wikipedia/commons/thumb/7/74/Villa_H%C3%BCgel_in_Essen.jpg/1920px-Villa_H%C3%BCgel_in_Essen.jpg?utm_source=commons.wikimedia.org';
const item={galleryImages:[
 {url:thumb,artist:'Max <b>Muster</b>',license:'CC BY-SA 4.0',credit:'Foto via Wikimedia Commons',source:'wikidata'},
 {url:'https://upload.wikimedia.org/wikipedia/commons/8/86/Tueshaus-Muehle.jpg',artist:'Eva',license:'CC BY-SA 3.0 de',credit:'',source:''},
 {url:'https://commons.wikimedia.org/wiki/Special:FilePath/Schwerte-00030.JPG?width=1200',artist:'A',license:'CC0',credit:'',source:''},
 {url:'https://images.pexels.com/photos/1/x.jpeg',artist:'',license:'',credit:'Foto: Pexels',source:'pexels'},
 {url:'https://example.org/a.jpg',artist:'',license:'',credit:'',source:''}
]};
const L=RPG.imageAttributions(item,'');
ok(L.length===4,'image without any data is skipped, others kept');
ok(L[0].filePage==='https://commons.wikimedia.org/wiki/File:Villa_H%C3%BCgel_in_Essen.jpg','thumb url -> Commons file page');
ok(L[0].artist==='Max Muster','html stripped from author');
ok(L[0].licenseUrl==='https://creativecommons.org/licenses/by-sa/4.0/','CC BY-SA 4.0 link');
ok(L[1].filePage==='https://commons.wikimedia.org/wiki/File:Tueshaus-Muehle.jpg','upload url -> file page');
ok(L[1].licenseUrl==='https://creativecommons.org/licenses/by-sa/3.0/deed.de','CC BY-SA 3.0 de link');
ok(L[2].filePage==='https://commons.wikimedia.org/wiki/File:Schwerte-00030.JPG','FilePath url -> file page');
ok(L[2].licenseUrl==='https://creativecommons.org/publicdomain/zero/1.0/deed.de','CC0 link');
ok(L[3].filePage===''&&L[3].credit==='Foto: Pexels','non-commons keeps credit text, no invented link');
ok(RPG.imageAttributions({galleryImages:[]},'').length===0,'empty -> none');
ok(RPG.imageAttributions({galleryImages:[{url:'https://example.org/a.jpg'}]},'').length===0,'no data -> none');
ok(RPG.imageAttributions({galleryImages:[]},thumb).length===1,'primary image alone is covered');
// component wiring
const comp=fs.readFileSync(dir+'components.js','utf8');
ok(comp.includes("'Bildnachweis'"),'section title exists');
ok(comp.includes("item.category!=='events'?h(ImageAttribution,{item,image}):null"),'rendered on place detail, not events');
ok(/DetailGallery,ImageAttribution,useSharing/.test(comp),'exported');
const tagIdx=comp.indexOf("tags.length?div({className:'rpgx-detail-tag-section'}"),attrIdx=comp.indexOf("h(ImageAttribution,{item,image})");
ok(tagIdx>0&&attrIdx>tagIdx,'Bildnachweis sits below the tag section (lower on the page)');
console.log('v353.10 image attribution: '+checks+' checks passed');
// real render of the component source
const srcStart=comp.indexOf('function ImageAttribution('),srcEnd=comp.indexOf('function usefulCardBadge');
const h=window.wp.element.createElement,mk=t=>(p,...c)=>h(t,p,...c);
const ImageAttribution=new Function('RPG','h','div','span','p','a','strong',comp.slice(srcStart,srcEnd)+';return ImageAttribution;')(RPG,h,mk('div'),mk('span'),mk('p'),mk('a'),mk('strong'));
const text=n=>n==null?'':typeof n==='string'?n:Array.isArray(n)?n.map(text).join(''):text(n.children);
const links=(n,r=[])=>{if(!n||typeof n==='string')return r;if(Array.isArray(n)){n.forEach(x=>links(x,r));return r;}if(n.type==='a')r.push(n.props.href);links(n.children,r);return r;};
const tree=ImageAttribution({item,image:''});
const t=text(tree);
ok(t.includes('Bildnachweis')&&t.includes('Urheber/in: Max Muster')&&t.includes('Lizenz: CC BY-SA 4.0')&&t.includes('verkleinert'),'rendered text');
ok(links(tree).includes('https://commons.wikimedia.org/wiki/File:Villa_H%C3%BCgel_in_Essen.jpg')&&links(tree).includes('https://creativecommons.org/licenses/by-sa/4.0/'),'rendered links');
ok(ImageAttribution({item:{galleryImages:[]},image:''})===null,'renders nothing without data');
console.log('render checks ok: '+checks);
