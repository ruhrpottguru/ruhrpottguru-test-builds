const assert=require('assert');
global.window={RPGReact:{settings:{root:'https://www.ruhrpottguru.de/wp-json/'},distanceKm:()=>0,personHint:()=>false,nearestCity:()=>''},location:{origin:'https://www.ruhrpottguru.de'}};
global.fetch=async()=>{throw new Error('Unexpected HTTP')};
require('../src/react-app/api.js');
const RPG=window.RPGReact;
const base={id:'wesel-fixture',title:'Wesel Testevent',city:'Wesel',startDate:'2026-10-10T19:00:00+02:00',imageUrl:'https://www.ruhrpottguru.de/test.jpg'};
const normalize=media=>RPG.normalizeEvent(Object.assign({},base,{rpg_event_db:{eventId:123},rpg_event_media:Object.assign({verified:true,imageUrl:base.imageUrl},media)}),null);
let checks=0; const eq=(actual,expected,msg)=>{assert.strictEqual(actual,expected,msg);checks++;};

// Main path: current WeselMarketing synthetic rights proof stays internal.
let out=normalize({source:'official-approved',credit:'WeselMarketing GmbH / jeweiliger Veranstalter · Nutzung fuer RuhrpottGuru schriftlich bestaetigt',license:'Schriftliche Drittkanal-Freigabe WeselMarketing'});
eq(out.mediaLabel,'','approved media has no visitor badge');
eq(out.mediaCredit,'','synthetic Wesel approval credit hidden');
eq(out.mediaLicense,'','synthetic Wesel approval licence hidden');
eq(out.galleryImages[0].credit,'','detail image has no synthetic approval line');

// Edge/regression: a real credit on another approved image is preserved.
out=normalize({source:'official-approved',credit:'Foto: Max Mustermann',license:'CC BY 4.0',artist:'Max Mustermann'});
eq(out.mediaLabel,'','approved-state badge remains hidden');
eq(out.mediaCredit,'Foto: Max Mustermann','real image credit preserved');
eq(out.mediaLicense,'CC BY 4.0','real licence preserved');
eq(out.galleryImages[0].credit,'Foto: Max Mustermann · CC BY 4.0','detail real attribution preserved');

// Adjacent regression: contextual Commons media still shows its meaningful visitor label + attribution.
out=normalize({source:'venue-commons-context',credit:'Wikimedia Commons',license:'CC BY-SA 4.0'});
eq(out.mediaLabel,'Event-Ort','context media label unchanged');
eq(out.galleryImages[0].credit,'Event-Ort · Wikimedia Commons · CC BY-SA 4.0','context attribution unchanged');

// Empty metadata stays empty and does not invent text.
out=normalize({source:'official-approved'});
eq(out.mediaCredit,'','empty credit remains empty');
eq(out.mediaLicense,'','empty licence remains empty');
eq(out.galleryImages[0].credit,'','empty detail credit remains empty');
console.log('PASS '+checks+' assertions: approved badge hidden, Wesel proof hidden, real credits/context attribution preserved.');
