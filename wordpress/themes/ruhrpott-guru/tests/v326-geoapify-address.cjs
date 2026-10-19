const fs=require('fs'),path=require('path');
const root=path.resolve(__dirname,'..');
const read=f=>fs.readFileSync(path.join(root,f),'utf8');
const style=read('style.css'),c=read('src/react-app/components.js'),a=read('src/react-app/api.js'),l=read('src/react-app/location.js'),php=read('functions.php'),css=read('css/rpg-react-source.css');
const checks=[
 ['theme v331 based on v326',style.includes('Version: 332.0')],
 ['split address form retained',c.includes("'Straße'")&&c.includes("'Hausnummer'")&&c.includes("'PLZ'")&&c.includes("'Ort'")],
 ['map.nrw runtime removed',!c.includes('https://map.nrw/v210/init.js')&&!c.includes('data-itnrw-module-search')&&!c.includes('rpgMapNrwSearchCallback')],
 ['hidden map css removed',!css.includes('.rpgx-mapnrw-geocoder')],
 ['server address API client',a.includes('async addressLocation(payload)')&&a.includes('address-location')],
 ['server route registered',php.includes("register_rest_route('ruhrpottguru/v1', '/address-location'")],
 ['EU Geoapify endpoint',php.includes('https://api-eu.geoapify.com/v1/geocode/search')],
 ['server-side API option',php.includes("get_option('rpg_geoapify_api_key'")],
 ['no API key localized',!php.includes("'geoapifyApiKey'")&&!c.includes('apiKey=')],
 ['country constrained',php.includes("'filter' => 'countrycode:de'")],
 ['53-city validation retained',php.includes('ruhrpott_guru_match_metropole_city_v322')&&php.includes('ruhrpott_guru_geoapify_result_city_v326')],
 ['postcode-only existing path retained',c.includes('await props.onPostcode(zip)')],
 ['city-only existing path retained',c.includes('props.onCity(city)')],
 ['address mode central handler retained',l.includes('chooseAddressResult')&&l.includes("locationMode:'address'")],
 ['required provider attribution visible',c.includes('Powered by Geoapify')&&c.includes('© OpenStreetMap contributors')],
 ['address request nonce',a.includes('X-RPG-Address-Token')&&php.includes("wp_verify_nonce($token, 'rpg_address_lookup_v326')")&&php.includes("wp_create_nonce('rpg_address_lookup_v326')")],
 ['no full-address server cache',!php.includes('rg_address_geoapify_v326_')],
 ['provider key not exposed to client',!a.includes('geoapifyApiKey')&&!c.includes('geoapifyApiKey')&&!c.includes('apiKey=')]
];
let fail=0;for(const [n,ok] of checks){console.log((ok?'OK ':'FAIL ')+n);if(!ok)fail++;}
if(fail)throw new Error(`${fail} v326 checks failed`);
console.log(`OK ${checks.length} v326 checks`);
