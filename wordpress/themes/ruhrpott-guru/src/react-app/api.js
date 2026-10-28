(function (window) {
  'use strict';
  const RPG = window.RPGReact;

  async function jsonFetch(url, options) {
    const response = await fetch(url, Object.assign({ credentials: 'same-origin', headers: { 'Content-Type': 'application/json' } }, options || {}));
    const payload = await response.json().catch(() => ({}));
    if (!response.ok || payload.ok === false) {
      const err = new Error(payload.message || payload.error || ('HTTP ' + response.status));
      err.status = response.status;
      err.payload = payload;
      throw err;
    }
    return payload;
  }

  RPG.api = {
    post(url, body) { return jsonFetch(url, { method: 'POST', body: JSON.stringify(body || {}) }); },
    get(url) { return jsonFetch(url, { method: 'GET' }); },
    async postcodeLocation(postcode) {
      const url = RPG.settings.postcodeLocationUrl || (RPG.settings.root + 'ruhrpottguru/v1/postcode-location');
      return this.post(url, { postcode:String(postcode||'').trim() });
    },
    async addressLocation(payload) {
      const url = RPG.settings.addressLocationUrl || (RPG.settings.root + 'ruhrpottguru/v1/address-location');
      const tokenUrl = RPG.settings.addressLocationTokenUrl || (RPG.settings.root + 'ruhrpottguru/v1/address-location-token');
      // HTML can be cached longer than a WordPress nonce. Request a fresh
      // nonce only on address submission; never trust one baked into a page.
      const fresh = await jsonFetch(tokenUrl, { method:'GET', cache:'no-store' });
      if (!fresh || !fresh.token) throw new Error('Die Adresssuche konnte nicht gestartet werden. Bitte versuche es erneut.');
      return jsonFetch(url, { method:'POST', cache:'no-store', headers:{'Content-Type':'application/json','X-RPG-Address-Token':String(fresh.token)}, body:JSON.stringify(payload||{}) });
    },
    async shareItem(token, category) {
      const url = new URL(RPG.settings.shareItemUrl || (RPG.settings.root + 'ruhrpottguru/v1/share-item'), window.location.origin);
      url.searchParams.set('token',String(token||''));
      if(category)url.searchParams.set('category',String(category));
      return this.get(url.toString());
    },
    async places(category, location, radiusKm, limit) {
      const url = RPG.settings.placesHomeUrl || (RPG.settings.root + 'ruhrpottguru/v1/places/home');
      const data = await this.post(url, { category, lat: location.lat, lng: location.lng, radiusKm, limit: limit || 500 });
      return Array.isArray(data.elements) ? data.elements : [];
    },
    async foodInfo(item) {
      const tags=(item&&item.tags)||{};
      const coords=item&&item.coords||{};
      const url=RPG.settings.foodInfoUrl || (RPG.settings.root + 'ruhrpottguru/v1/food-info');
      return this.post(url, {
        title:item&&item.title||'', city:item&&item.city||'', address:item&&item.address||'',
        website:item&&item.website||tags.website||tags['contact:website']||'',
        wikidata:tags.wikidata||'', facebook:tags['contact:facebook']||tags.facebook||'', instagram:tags['contact:instagram']||tags.instagram||'',
        lat:Number.isFinite(Number(coords.lat))?Number(coords.lat):null, lng:Number.isFinite(Number(coords.lng))?Number(coords.lng):null
      });
    },
    async events(location, maxRadiusKm, untilDays) {
      const url = RPG.settings.eventDataUrl || (RPG.settings.root + 'ruhrpottguru/v1/events');
      const body = { lat: location.lat, lng: location.lng, city: location.city || RPG.nearestCity(location.lat, location.lng), maxRadiusKm: maxRadiusKm || 50 };
      // V353.2: optionaler Zeithorizont (Backend ab Places 1.10.191); Specials fragen nur ihre Saison ab.
      if (Number.isFinite(untilDays) && untilDays > 0) body.untilDays = Math.ceil(untilDays);
      const data = await this.post(url, body);
      return Array.isArray(data.items) ? data.items : [];
    },
    async eventRelated(item) {
      let eventId=Number(item&&item.raw&&item.raw.rpg_event_db&&item.raw.rpg_event_db.eventId||0);
      if((!Number.isFinite(eventId)||eventId<=0)&&item&&/^event:\d+$/i.test(String(item.id||'')))eventId=Number(String(item.id).split(':')[1]);
      if(!Number.isFinite(eventId)||eventId<=0)return {ok:true,series:null,count:0,items:[]};
      const base=RPG.settings.root || '/wp-json/';
      return this.get(base+'ruhrpottguru/v1/events/'+encodeURIComponent(String(eventId))+'/related');
    },
    async eventMediaDiversity(items) {
      const url = RPG.settings.eventMediaDiversityUrl || (RPG.settings.root + 'ruhrpottguru/v1/event-media-diversity');
      if (!url || !Array.isArray(items) || !items.length) return [];
      const data = await this.post(url, { items:items.slice(0,24) });
      return Array.isArray(data.items) ? data.items : [];
    },
    async eventMediaQualityFallback(items) {
      const url = RPG.settings.eventMediaDiversityUrl || (RPG.settings.root + 'ruhrpottguru/v1/event-media-diversity');
      if (!url || !Array.isArray(items) || !items.length) return [];
      const data = await this.post(url, { purpose:'quality-fallback', items:items.slice(0,12) });
      return Array.isArray(data.items) ? data.items : [];
    },
    async eventMediaSemanticFallback(items) {
      const url = RPG.settings.eventMediaDiversityUrl || (RPG.settings.root + 'ruhrpottguru/v1/event-media-diversity');
      if (!url || !Array.isArray(items) || !items.length) return [];
      const data = await this.post(url, { purpose:'semantic-fallback', items:items.slice(0,36) });
      return Array.isArray(data.items) ? data.items : [];
    },
    async industrialRoute(location) {
      let url = RPG.settings.industrialRouteUrl || (RPG.settings.root + 'ruhrpottguru/v1/places/industrial-route');
      const q = new URL(url, window.location.origin);
      if (location) { q.searchParams.set('lat', location.lat); q.searchParams.set('lng', location.lng); }
      const data = await this.get(q.toString());
      return Array.isArray(data.items) ? data.items : [];
    },
    async buedchen(location, radiusKm, limit) {
      const url = RPG.settings.buedchenRadarUrl || (RPG.settings.root + 'ruhrpottguru/v1/places/buedchen');
      const data = await this.post(url, { lat: location.lat, lng: location.lng, radiusKm, limit: limit || 200 });
      return Array.isArray(data.elements) ? data.elements : [];
    },
    async submitBuedchen(payload) {
      const url = RPG.settings.buedchenSubmitUrl || (RPG.settings.root + 'ruhrpottguru/v1/places/buedchen/submit');
      return this.post(url, payload || {});
    },
    async submitPlaceContribution(formData) {
      const url = RPG.settings.placeContributionUrl || (RPG.settings.root + 'ruhrpottguru/v1/places/contribute');
      const response = await fetch(url,{method:'POST',credentials:'same-origin',body:formData});
      const payload = await response.json().catch(()=>({}));
      if(!response.ok || payload.ok===false){const err=new Error(payload.message||payload.error||('HTTP '+response.status));err.status=response.status;err.payload=payload;throw err;}
      return payload;
    },
    async communityStories(placeId) {
      const base=RPG.settings.communityStoriesBaseUrl || (RPG.settings.root + 'ruhrpottguru/v1/places/');
      return this.get(base+encodeURIComponent(placeId)+'/community-stories');
    },
    async communityStoryMedia(postId) {
      // Read-only display enrichment: uploaded gallery images remain attached to
      // the already moderated community post. The submission/moderation flow is
      // deliberately untouched.
      const base=RPG.settings.root || '/wp-json/';
      const q=new URL(base+'wp/v2/media',window.location.origin);
      q.searchParams.set('parent',String(postId||''));
      q.searchParams.set('per_page','5');
      q.searchParams.set('media_type','image');
      q.searchParams.set('orderby','id');
      q.searchParams.set('order','asc');
      q.searchParams.set('_fields','id,source_url,media_details,alt_text');
      try{const data=await this.get(q.toString());return Array.isArray(data)?data:[];}catch(_){return [];}
    },
    async submitCommunityStory(formData) {
      const url=RPG.settings.communityStorySubmitUrl || (RPG.settings.root + 'ruhrpottguru/v1/places/community-story');
      const response=await fetch(url,{method:'POST',credentials:'same-origin',body:formData});
      const payload=await response.json().catch(()=>({}));
      if(!response.ok||payload.ok===false){const err=new Error(payload.message||payload.error||('HTTP '+response.status));err.status=response.status;err.payload=payload;throw err;}
      return payload;
    },
    async wurst(location, radiusKm, limit) {
      const url = RPG.settings.wurstUrl || (RPG.settings.root + 'ruhrpottguru/v1/places/wurst');
      const body = { lat: location.lat, lng: location.lng, radiusKm, limit: limit || 200 };
      let data;
      try {
        data = await this.post(url, body);
      } catch (err) {
        const noRoute = err && (err.status === 404 || (err.payload && (err.payload.code === 'rest_no_route' || err.payload.error === 'rest_no_route')));
        if (!noRoute) throw err;
        const ajaxBase = RPG.settings.wurstAjaxUrl || '/wp-admin/admin-ajax.php';
        const q = new URL(ajaxBase, window.location.origin);
        q.searchParams.set('action','rg_wurst_frontend');
        q.searchParams.set('lat',body.lat); q.searchParams.set('lng',body.lng);
        q.searchParams.set('radiusKm',body.radiusKm); q.searchParams.set('limit',body.limit);
        const response = await fetch(q.toString(), { credentials:'same-origin' });
        const payload = await response.json().catch(()=>({}));
        if (!response.ok || payload.success !== true || !payload.data || payload.data.ok === false) {
          const fallbackErr = new Error((payload.data && payload.data.error) || 'Wurst-Daten konnten nicht geladen werden.');
          fallbackErr.status = response.status; fallbackErr.payload = payload; throw fallbackErr;
        }
        data = payload.data;
      }
      return { elements:Array.isArray(data.elements)?data.elements:[], verifiedTotal:Number(data.verifiedTotal)||0 };
    },
    async submitWurst(payload) {
      const url = RPG.settings.wurstSubmitUrl || (RPG.settings.root + 'ruhrpottguru/v1/places/wurst/submit');
      return this.post(url,payload||{});
    }
  };

  function addressFromTags(tags) {
    return [tags['addr:street'] && (tags['addr:street'] + (tags['addr:housenumber'] ? ' ' + tags['addr:housenumber'] : '')), tags['addr:postcode'], tags['addr:city']]
      .filter(Boolean).join(', ');
  }
  function commonsUrl(value) {
    const s = String(value || '').trim();
    if (!s) return '';
    const m = s.match(/^File\s*:\s*(.+)$/i);
    if (m) return 'https://commons.wikimedia.org/wiki/Special:FilePath/' + encodeURIComponent(m[1]) + '?width=1200';
    if (!/^https?:\/\//i.test(s)) return '';
    // wikimedia_commons frequently points at a Category: page. A Commons page is
    // not an image and must go through the server-side resolver instead.
    if (/commons\.wikimedia\.org\/wiki\/(?:Category|Special:MediaSearch|Main_Page):?/i.test(s)) return '';
    if (/commons\.wikimedia\.org\/wiki\/File:/i.test(s)) {
      const file = decodeURIComponent(s.replace(/^.*\/wiki\/File:/i, '').replace(/[?#].*$/, '').replace(/_/g, ' '));
      return file ? 'https://commons.wikimedia.org/wiki/Special:FilePath/' + encodeURIComponent(file) + '?width=1200' : '';
    }
    if (/upload\.wikimedia\.org\//i.test(s) || /Special:FilePath\//i.test(s) || /\.(?:jpe?g|png|webp|gif|avif)(?:[?#].*)?$/i.test(s)) return s;
    return '';
  }
  function directImageUrl(value) {
    const s = String(value || '').trim();
    if (!s) return '';
    const commons = commonsUrl(s);
    if (commons) return commons;
    if (/^https?:\/\//i.test(s) && !/\/(?:Category|File):/i.test(s)) return s;
    return '';
  }
  function splitTags(value) {
    return String(value || '').split('|').map(v => v.trim()).filter(Boolean).slice(0, 6);
  }

  // V265 – restore the proven Food data-intelligence presentation without
  // inventing facts. Price tiers are accepted only from explicit 1–3 / € fields;
  // cuisine labels are derived only from source cuisine/diet/seating evidence.
  function foodPriceTier(tags) {
    tags=tags&&typeof tags==='object'?tags:{};
    const fields=[tags['rg:price_tier'],tags.price_tier,tags.price_level,tags['price:level']];
    for(const raw of fields){
      const value=String(raw==null?'':raw).trim();
      if(!value)continue;
      if(/^[€]{1,3}$/.test(value))return value;
      const m=value.match(/^([1-3])(?:\.0+)?$/);
      if(m)return '€'.repeat(Number(m[1]));
    }
    return '';
  }
  function foodLabels(tags) {
    tags=tags&&typeof tags==='object'?tags:{};
    const out=[];const seen=new Set();
    const add=value=>{const label=String(value||'').replace(/^#+/,'').replace(/_/g,' ').replace(/\s+/g,' ').trim();if(!label)return;const key=label.toLocaleLowerCase('de');if(seen.has(key)||out.length>=4)return;seen.add(key);out.push(label);};
    const cuisine=String(tags.cuisine||'').toLocaleLowerCase('de');
    const tokens=cuisine.split(/[;,|/]+/).map(v=>v.trim()).filter(Boolean);
    const map={
      sushi:'Sushi',japanese:'Japanisch',japanisch:'Japanisch',ramen:'Ramen',
      kebab:'Döner',doner:'Döner','döner':'Döner',turkish:'Türkisch','türkisch':'Türkisch',
      chinese:'Chinesisch',chinesisch:'Chinesisch',asian:'Asiatisch',asiatisch:'Asiatisch',
      italian:'Italienisch',italienisch:'Italienisch',german:'Deutsch',deutsch:'Deutsch',
      pizza:'Pizza',pasta:'Pasta',tacos:'Tacos',burger:'Burger',steak_house:'Steak',steakhouse:'Steak',barbecue:'BBQ',bbq:'BBQ',
      ice_cream:'Eis','ice cream':'Eis',eis:'Eis',coffee:'Café',cafe:'Café','café':'Café',
      bowls:'Bowls',bowl:'Bowls',healthy:'Healthy Food',regional:'Regional',international:'International',
      indian:'Indisch',indisch:'Indisch',greek:'Griechisch',griechisch:'Griechisch',
      mexican:'Mexikanisch',mexikanisch:'Mexikanisch',thai:'Thai',
      vietnamese:'Vietnamesisch',vietnamesisch:'Vietnamesisch',korean:'Koreanisch',
      french:'Französisch',spanish:'Spanisch',mediterranean:'Mediterran',seafood:'Fisch & Seafood',
      chicken:'Chicken',sandwich:'Sandwiches',breakfast:'Frühstück',brunch:'Frühstück'
    };
    tokens.forEach(token=>{const key=token.replace(/\s+/g,'_');if(map[token])add(map[token]);else if(map[key])add(map[key]);});
    // Amenity is explicit source evidence too. Only meaningful Food facets are
    // exposed; generic restaurant/fast_food values remain hidden.
    const amenity=String(tags.amenity||'').toLowerCase();
    if(amenity==='ice_cream')add('Eis');
    if(amenity==='cafe')add('Café');
    if(amenity==='bar'||amenity==='pub')add('Bar');
    if(String(tags['diet:vegan']||'').toLowerCase()==='yes')add('Vegan');
    if(String(tags['diet:vegetarian']||'').toLowerCase()==='yes')add('Vegetarisch');
    if(String(tags.outdoor_seating||'').toLowerCase()==='yes')add('Außengastronomie');
    return out.slice(0,4);
  }
  RPG.foodPriceTier=foodPriceTier;
  RPG.foodLabels=foodLabels;

  RPG.normalizePlace = function (element, category, location) {
    const tags = RPG.tags(element);
    const coords = RPG.getCoords(element);
    const distanceKm = coords && location ? RPG.distanceKm(location.lat, location.lng, coords.lat, coords.lng) : null;
    const labels = category === 'food' ? foodLabels(tags)
      : category === 'activities' ? splitTags(tags['rg:experience_tags'])
      : category === 'highlights' ? splitTags(tags['rg:highlight_tags'])
      : category === 'culture' ? splitTags(tags['rg:culture_tags'])
      : [];
    const priceTier = category === 'food' ? foodPriceTier(tags) : '';
    const id = tags['rg:place_id'] || (element.type + ':' + element.id);
    const parseCrop=value=>{try{const v=typeof value==='string'?JSON.parse(value):value;const mode=v&&String(v.mode||'');if(!v||!['auto','manual'].includes(mode))return null;return {mode,x:Number(v.x??50),y:Number(v.y??50),zoom:Number(v.zoom??1)};}catch(_){return null;}};
    let galleryImages=[];
    try{
      const rawGallery=tags['rg:media_gallery'];
      const parsed=rawGallery?JSON.parse(rawGallery):[];
      if(Array.isArray(parsed)) galleryImages=parsed.filter(x=>x&&directImageUrl(x.url)).map(x=>({url:directImageUrl(x.url),credit:String(x.credit||''),artist:String(x.artist||''),source:String(x.source||''),license:String(x.license||''),cropCard:parseCrop(x.cropCard),cropDetail:parseCrop(x.cropDetail)}));
    }catch(_){}
    const primaryImage=directImageUrl(tags.image || tags['image:url']) || commonsUrl(tags.wikimedia_commons);
    const imageCropCard=parseCrop(tags['rg:image_crop_card']),imageCropDetail=parseCrop(tags['rg:image_crop_detail']);
    if(primaryImage && !galleryImages.some(x=>x.url===primaryImage)) galleryImages.unshift({url:primaryImage,credit:String(tags['rg:image_credit']||''),artist:'',source:String(tags['rg:image_source']||''),license:String(tags['rg:image_license']||''),cropCard:imageCropCard,cropDetail:imageCropDetail});
    return {
      kind: 'place', id: String(id), category,
      title: tags.name || 'Unbenannter Ort',
      subtitle: tags['rg:subcategory'] || '',
      city: tags['addr:city'] || (location && location.city) || '',
      address: addressFromTags(tags), coords, distanceKm,
      tags, labels, priceTier,
      description: tags.description || tags['description:de'] || tags.note || '',
      website: tags.website || tags['contact:website'] || '',
      canonicalUrl: httpUrl(tags['rg:canonical_url']) || '',
      menuUrl: tags.menu || '', reservationUrl: tags['reservation:website'] || '',
      openingHours: tags.opening_hours || '',
      phone: tags.phone || tags['contact:phone'] || '',
      imageUrl: primaryImage, imageCropCard, imageCropDetail, galleryImages,
      routeIndustrial: tags['rg:industrial_route'] === '1',
      routeType: tags['rg:industrial_route_type'] || '',
      routeTitle: tags['rg:industrial_route_title'] || '',
      routeOfficialUrl: tags['rg:industrial_route_official_url'] || '',
      raw: element
    };
  };

  function deepFind(obj, keys, depth) {
    if (!obj || depth > 5) return '';
    if (typeof obj === 'string') return '';
    if (Array.isArray(obj)) {
      for (const x of obj) { const v = deepFind(x, keys, depth + 1); if (v) return v; }
      return '';
    }
    if (typeof obj !== 'object') return '';
    for (const key of keys) {
      const v = obj[key];
      if (typeof v === 'string' && v.trim()) return v.trim();
    }
    for (const v of Object.values(obj)) { const found = deepFind(v, keys, depth + 1); if (found) return found; }
    return '';
  }
  function eventCoords(item) {
    const candidates = [item.geo, item.location, item.address, item];
    for (const c of candidates) {
      if (!c || typeof c !== 'object') continue;
      const lat = Number(c.latitude != null ? c.latitude : c.lat != null ? c.lat : c.geo && c.geo.latitude);
      const lng = Number(c.longitude != null ? c.longitude : c.lng != null ? c.lng : c.lon != null ? c.lon : c.geo && c.geo.longitude);
      if (Number.isFinite(lat) && Number.isFinite(lng)) return { lat, lng };
    }
    if (item.geo && item.geo.main) {
      const lat = Number(item.geo.main.latitude || item.geo.main.lat);
      const lng = Number(item.geo.main.longitude || item.geo.main.lng || item.geo.main.lon);
      if (Number.isFinite(lat) && Number.isFinite(lng)) return { lat, lng };
    }
    return null;
  }
  function eventDates(item) {
    const out = [];
    const push = (start, end) => {
      const s = start ? new Date(start) : null; const e = end ? new Date(end) : null;
      if (s && !isNaN(s)) out.push({ start: s, end: e && !isNaN(e) ? e : null });
    };

    // V175: A DB-backed row already IS one canonical occurrence. Its start/end
    // values were generated and persisted by the Event master/supplemental
    // pipeline. The raw ET4 payload may still contain the complete parent
    // timeIntervals series. Re-reading that series here makes every occurrence
    // snap back to the first interval (e.g. dozens of Christmas-market cards
    // all showing 11.11. 11:00). For DB rows, trust only the canonical row.
    if (item && item.rpg_event_db) {
      if (item.start || item.end) push(item.start, item.end);
      return out;
    }

    if (item.start || item.end) push(item.start, item.end);
    const arrays = [item.timeIntervals, item.time_intervals, item.intervals, item.dates];
    arrays.forEach(arr => {
      if (!arr) return;
      const rows=(arr&&typeof arr==='object'&&!Array.isArray(arr)&&arr.timeInterval!=null)?arr.timeInterval:arr;
      asArray(rows).forEach(x => {
        if (!x || typeof x !== 'object') return;
        push(x.start || x.startDate || x.from || x.date, x.end || x.endDate || x.to || x.end_date);
      });
    });
    out.sort((a,b) => a.start - b.start);
    return out;
  }
  function httpUrl(value) {
    if (!value) return '';
    if (typeof value === 'string') {
      const s=value.trim();
      return /^https?:\/\//i.test(s) ? s : '';
    }
    if (Array.isArray(value)) {
      for (const v of value) { const url=httpUrl(v); if(url) return url; }
      return '';
    }
    if (typeof value === 'object') {
      return httpUrl(value.url) || httpUrl(value.contentUrl) || httpUrl(value.thumbnailUrl) || httpUrl(value['@id']) || httpUrl(value.href) || httpUrl(value.src) || '';
    }
    return '';
  }
  function asArray(value) {
    if (value == null) return [];
    return Array.isArray(value) ? value : [value];
  }
  // Ported from the proven pre-takeover ET4 parser. ET2014A carries its real
  // event artwork in media_objects/media_object with rel/type/prio metadata.
  // Do not recursively treat arbitrary contentUrl values as images: those can
  // be normal event/detail pages and were the cause of the broken-image cards.
  function selectEt4Image(value) {
    let media=value;
    if (media && typeof media === 'object' && !Array.isArray(media) && media.media_object != null) media=media.media_object;
    const candidates=[];
    for (const entry of asArray(media)) {
      if (!entry) continue;
      if (typeof entry === 'string') {
        const url=httpUrl(entry); if(url) candidates.push({url,rel:'other',type:'',prio:9999});
        continue;
      }
      if (typeof entry !== 'object') continue;
      const url=httpUrl(entry.url || entry.contentUrl || entry.href || entry.src || entry['@id']);
      const type=String(entry.type || entry.mimeType || '').toLowerCase();
      const rel=String(entry.rel || 'other').toLowerCase();
      const prio=Number.isFinite(Number(entry.prio)) ? Number(entry.prio) : 9999;
      if (!url) continue;
      const nonImage=type.startsWith('video/') || type.startsWith('audio/') || type === 'application/pdf';
      const imageRel=['default','gallery','mobile','print','summer','winter'].includes(rel);
      const nonImageRel=['video','audio','booking','homepage','socialmedia','rating'].includes(rel);
      const looksImage=!nonImage && !nonImageRel && (type==='' || type==='image' || type.startsWith('image/') || /\.(?:jpe?g|png|webp|gif|avif)(?:[?#].*)?$/i.test(url) || imageRel);
      if (looksImage) candidates.push({url,rel,type,prio});
    }
    if (!candidates.length) return '';
    const rank={default:0,gallery:1,mobile:2,print:3,summer:4,winter:4,other:5,download:6};
    candidates.sort((a,b)=>(rank[a.rel]??9)-(rank[b.rel]??9) || a.prio-b.prio);
    return candidates[0].url;
  }
  function eventImage(item) {
    if(!item||typeof item!=='object') return '';
    // V232: when the backend already proved an exact ET4 global_id mapping,
    // use the original ET4 media URL directly. Do not insert another transport
    // or resolver between the authoritative ET4 image and the browser.
    if(item.rpg_event_media && String(item.rpg_event_media.source||'').toLowerCase()==='et4-global-id'){
      const originalEt4=httpUrl(item.rpg_event_media.originalEt4Url);
      if(originalEt4) return originalEt4;
    }
    const source=String(item.provider_source || item.source_name || item.source || '').toLowerCase();
    const isEt4=source==='open_data_finder' || source==='destination_one_open_data' || source.includes('et4') || source.includes('destination.one');
    const isDbBacked=!!(item.rpg_event_db&&typeof item.rpg_event_db==='object');
    // V286 defense-in-depth: DB-backed Events never trust source/raw media directly.
    // The backend rights-aware handoff must provide rpg_event_media explicitly.
    if(isDbBacked){
      if(item.rpg_event_media && item.rpg_event_media.verified){
        const dbMedia=httpUrl(item.rpg_event_media.imageUrl || item.rpg_event_media.url || item.rpg_event_media.contentUrl);
        if(dbMedia) return dbMedia;
      }
      return '';
    }
    // V229: For ET4/Open Data Finder events the image attached to the event
    // record itself is authoritative. Never replace it with later enrichment.
    const pools=[
      item.media_objects, item.mediaObjects, item.media_object, item.mediaObject, item.media,
      item.images, item.image, item.photos, item.photo
    ];
    const fromEventRecord=()=>{
      for(const pool of pools){
        const hit=selectEt4Image(pool);
        if(hit)return hit;
        const direct=httpUrl(pool);
        if(direct && /\.(?:jpe?g|png|webp|gif|avif)(?:[?#].*)?$/i.test(direct)) return direct;
      }
      return '';
    };
    if(isEt4){
      const et4=fromEventRecord();
      if(et4) return et4;
    }
    if(item.rpg_event_media && item.rpg_event_media.verified){
      const dbMedia=httpUrl(item.rpg_event_media.imageUrl || item.rpg_event_media.url || item.rpg_event_media.contentUrl);
      if(dbMedia) return dbMedia;
    }
    // Non-ET4 feeds may still expose a genuine event-owned image.
    return fromEventRecord();
  }
  function eventIsProviderUrl(value){
    const url=httpUrl(value);
    if(!url)return false;
    try{
      const host=new URL(url).hostname.toLowerCase().replace(/^www\./,'');
      return ['destination.one','et4.de','pages.et4.de','open-data-finder.de','opendata.destination.one'].some(blocked=>host===blocked||host.endsWith('.'+blocked));
    }catch(_){return true;}
  }
  function eventPublicUrl(value){
    const url=httpUrl(value);
    return url&&!eventIsProviderUrl(url)?url:'';
  }
  // PottFuchs 2026-10-24: ticket links from ET4 can be HTML-entity encoded
  // or wrapped in Markdown, e.g. &#x20;[https://ticket.io/x/:](https://ticket.io/x/:).
  // Only ticket-action URLs use this normalizer; media, source and ranking URLs
  // continue through their existing validators unchanged.
  function eventTicketLink(value) {
    if (Array.isArray(value)) {
      for (const part of value) { const candidate=eventTicketLink(part); if(candidate)return candidate; }
      return '';
    }
    if (value && typeof value === 'object') {
      return eventTicketLink(value.url)||eventTicketLink(value.href)||eventTicketLink(value.link)||eventTicketLink(value.contentUrl)||'';
    }
    const raw=String(value==null?'':value).trim().replace(/&#x20;|&#0*32;|&#0*160;|&nbsp;/gi,' ').replace(/&amp;/gi,'&');
    if (!raw) return '';
    const markdown=raw.match(/\[[^\]]+\]\(\s*(https?:\/\/[^\s)]+)\s*\)/i);
    const direct=raw.match(/^https?:\/\/[^\s<>'"\[\]()]+/i);
    let link=markdown?markdown[1]:(direct?direct[0]:'');
    if(!link)return '';
    // A colon after the final slash is a copied punctuation artifact, not a
    // ticket.io path segment. Keep internal colons, query strings and fragments.
    link=link.replace(/\/:(?=$|[?#])/g,'/').replace(/[.,;:]+$/,'');
    if(!/^https?:\/\//i.test(link))return '';
    try {
      const parsed=new URL(link);
      if(!/^(?:http:|https:)$/.test(parsed.protocol)||!parsed.hostname||parsed.username||parsed.password)return '';
    } catch(_) {return '';}
    return eventPublicUrl(link);
  }
  function eventTicketLinkFromText(value) {
    const text=String(value==null?'':value);
    // Scan only short fragments after an explicit ticket label. A different
    // editorial hyperlink elsewhere in the description is not a ticket URL.
    const labels=/(?:ticket(?:verkauf|link|s)?|karten(?:kauf|verkauf)?|buchung)/gi;
    let label;
    while((label=labels.exec(text))!==null){
      const fragment=text.slice(label.index, label.index+180);
      const markdown=fragment.match(/\[[^\]]+\]\(\s*(https?:\/\/[^\s)]+)\s*\)/i);
      if(markdown){const link=eventTicketLink(markdown[0]);if(link)return link;}
      const context=fragment.match(/https?:\/\/[^\s<>'"\[\]()]+/i);
      if(context){const link=eventTicketLink(context[0]);if(link)return link;}
    }
    return '';
  }
  RPG.eventTicketLink=eventTicketLink;
  function eventMediaRelUrl(item, wantedRel) {
    const wanted=String(wantedRel||'').toLowerCase();
    if(!item||typeof item!=='object'||!wanted)return '';
    let found='';
    const walk=(node,depth=0)=>{
      if(found||!node||typeof node!=='object'||depth>8)return;
      if(!Array.isArray(node)){
        const rel=String(node.rel||node.relation||'').toLowerCase();
        if(rel===wanted){
          const url=eventPublicUrl(node.url)||eventPublicUrl(node.contentUrl)||eventPublicUrl(node.href)||eventPublicUrl(node.link);
          if(url){found=url;return;}
        }
      }
      Object.values(node).forEach(v=>{if(!found&&v&&typeof v==='object')walk(v,depth+1);});
    };
    walk(item);return found;
  }
  function eventUrl(item) {
    if (!item || typeof item !== 'object') return '';
    return eventPublicUrl(item.website) || eventPublicUrl(item.homepage) || eventPublicUrl(item.officialUrl) || eventPublicUrl(item.eventUrl) || eventPublicUrl(item.sameAs) || eventMediaRelUrl(item,'homepage') || eventPublicUrl(item.url) || eventPublicUrl(item.detailUrl) || '';
  }
  function venueName(item) {
    if (!item || typeof item !== 'object') return '';
    // V115/V244: venue identity must come from the event/location itself. A recursive
    // deepFind can accidentally pick provider/organisation names from ET4 metadata.
    const direct = [
      item.venueName, item.venueTitle, item.locationName, item.placeName,
      item.location && typeof item.location === 'object' ? item.location.name : '',
      item.location && typeof item.location === 'object' ? item.location.title : ''
    ].find(v=>typeof v==='string' && v.trim());
    if (direct) return direct.trim();

    const loc = item.location && typeof item.location==='object' ? item.location : null;
    if (loc) {
      const nested=[loc.venueName,loc.venueTitle,loc.locationName,loc.placeName];
      const hit=nested.find(v=>typeof v==='string'&&v.trim());
      if(hit)return hit.trim();
    }
    const rows=[];
    const add=value=>{if(!value)return;if(Array.isArray(value)){value.forEach(add);return;}if(typeof value!=='object')return;if(value.address&&typeof value.address==='object')add(value.address);if(value.address&&Array.isArray(value.address))add(value.address);if(value.addresses)add(value.addresses);if(value.address)rows.push(value.address);rows.push(value);};
    add(item.address);add(item.addresses);add(item.location&&item.location.address);add(item.location&&item.location.addresses);
    let best='';let bestScore=-Infinity;
    rows.forEach(a=>{if(!a||typeof a!=='object')return;const rel=String(a.rel||a.type||'').toLowerCase();if(/organizer|organiser|organisation|author|support|contact|lessor|veranstalter/.test(rel))return;let score=/event|venue|location|place|main|veranstaltungsort/.test(rel)?50:(rel?0:5);const name=String(a.name||a.company||'').trim();if(name&&score>bestScore){best=name;bestScore=score;}});
    return best;
  }

  function eventType(item) {
    const bits=[];
    const push=v=>{
      if(v==null)return;
      if(typeof v==='string'||typeof v==='number')bits.push(String(v));
      else if(Array.isArray(v))v.forEach(push);
      else if(typeof v==='object'){
        ['name','title','label','value','category','type','classification','eventType'].forEach(k=>{if(v[k]!=null)push(v[k]);});
      }
    };
    ['category','categories','type','types','classification','classifications','eventType','eventTypes','keywords','tags','themes','theme'].forEach(k=>push(item&&item[k]));
    push(item&&item.title); push(item&&item.name);
    const hay=bits.join(' ').toLowerCase();

    if(/konzert|musik|music|festival|live\s*music|chor|orchester|band|dj\b/.test(hay))return 'Konzert & Musik';
    if(/party|club|nightlife|disco|tanz|dance|nachtleben/.test(hay))return 'Party & Nightlife';
    if(/theater|bühne|buehne|oper|operette|musical|schauspiel|performance/.test(hay))return 'Theater & Bühne';
    if(/comedy|kabarett|show|variet[eé]|zauber|circus|zirkus/.test(hay))return 'Comedy & Show';
    if(/ausstellung|museum|vernissage|kunst|galerie|exhibition/.test(hay))return 'Ausstellung & Kunst';
    if(/markt|kirmes|stadtfest|straßenfest|strassenfest|weihnachtsmarkt|trödel|troedel|messe/.test(hay))return 'Markt & Stadtfest';
    if(/sport|lauf|marathon|fußball|fussball|handball|basketball|radsport|turnier|rennen/.test(hay))return 'Sport';
    if(/familie|kinder|kids|kindertheater|familien|ferienprogramm/.test(hay))return 'Familie';
    if(/führung|fuehrung|tour|rundgang|vortrag|lesung|workshop|seminar/.test(hay))return 'Tour, Wissen & Workshop';
    return 'Weitere Events';
  }
  function eventAddress(item) {
    if(!item||typeof item!=='object')return '';
    const candidates=[];
    const add=(a,depth=0)=>{if(!a||depth>5)return;if(typeof a==='string'){if(a.trim())candidates.push({text:a.trim(),score:20});return;}if(Array.isArray(a)){a.forEach(v=>add(v,depth+1));return;}if(typeof a!=='object')return;
      // ET2014A may wrap address arrays as addresses.address.
      if(a.address!=null)add(a.address,depth+1);
      if(a.addresses!=null)add(a.addresses,depth+1);
      const street=String(a.street||a.streetAddress||a.strasse||a.addressLine1||'').trim();
      const house=String(a.houseNumber||a.house_number||a.housenumber||a.houseNo||'').trim();
      const zip=String(a.zip||a.postalCode||a.postcode||a.plz||'').trim();
      const city=String(a.city||a.addressLocality||a.locality||a.ort||'').trim();
      const rel=String(a.rel||a.type||'').toLowerCase();
      let score=(street?70:0)+(house?20:0)+(zip?35:0)+(city?20:0);
      if(/event|venue|location|place|veranstaltungsort|main/.test(rel))score+=100;
      if(/organizer|organiser|organisation|veranstalter|contact|contact_person|author|support|lessor|billing|invoice|provider|publisher/.test(rel))score-=220;
      if(street||zip||city){const line1=[street,house].filter(Boolean).join(' ');const line2=[zip,city].filter(Boolean).join(' ');candidates.push({text:[line1,line2].filter(Boolean).join(', '),score});}
    };
    add(item.address); add(item.addresses); add(item.location&&item.location.address); add(item.location&&item.location.addresses);
    candidates.sort((a,b)=>b.score-a.score);
    const best=candidates.find(x=>x&&x.score>0);
    return best?best.text:'';
  }
  function eventParticipants(item,extended=false) {
    const out=[]; const seen=new Set();
    const roles={performer:'Mitwirkende',performers:'Mitwirkende',artist:'Künstler/in',artists:'Künstler/in',contributor:'Mitwirkende',contributors:'Mitwirkende',participant:'Mitwirkende',participants:'Mitwirkende',actor:'Schauspiel',actors:'Schauspiel',musician:'Musik',musicians:'Musik',band:'Band',bands:'Band',ensemble:'Ensemble',ensembles:'Ensemble',speaker:'Sprecher/in',speakers:'Sprecher/in'};
    if(extended)Object.assign(roles,{person:'Mitwirkende',persons:'Mitwirkende',cast:'Besetzung',lineup:'Line-up',organist:'Organist/in',organists:'Organist/in',soloist:'Solist/in',soloists:'Solist/in',singer:'Gesang',singers:'Gesang',vocalist:'Gesang',vocalists:'Gesang',choir:'Chor',choirs:'Chor',orchestra:'Orchester',orchestras:'Orchester'});
    const add=(value,role)=>{
      if(value==null)return;
      if(Array.isArray(value)){value.forEach(v=>add(v,role));return;}
      if(typeof value==='string'||typeof value==='number'){const name=String(value).trim();const key=name.toLocaleLowerCase('de');if(name&&!seen.has(key)){seen.add(key);out.push({name,role,url:''});}return;}
      if(typeof value!=='object')return;
      const name=String(value.name||value.title||value.label||value.displayName||value.artistName||value.performerName||'').trim();
      if(!name){Object.values(value).forEach(v=>{if(Array.isArray(v)||(v&&typeof v==='object'))add(v,role);});return;}
      const key=name.toLocaleLowerCase('de');if(seen.has(key))return;seen.add(key);
      const actual=String(value.role||value.jobTitle||value.type||role||'Mitwirkende').trim();
      const url=/^https?:\/\//i.test(String(value.url||value.website||value.sameAs||''))?String(value.url||value.website||value.sameAs):'';
      out.push({name,role:actual,url});
    };
    if(Array.isArray(item&&item.rpg_participants))item.rpg_participants.filter(x=>x&&x.name).forEach(x=>add(x,x.role||'Mitwirkende'));
    const walk=(node,depth=0)=>{if(!node||typeof node!=='object'||depth>5)return;Object.keys(node).forEach(k=>{const lower=k.toLowerCase();if(roles[lower])add(node[k],roles[lower]);else if(node[k]&&typeof node[k]==='object')walk(node[k],depth+1);});};
    walk(item);

    if(extended){
      const roleFromContext=value=>{
        const t=String(value||'').toLocaleLowerCase('de');
        if(/organist/.test(t))return 'Organist/in';
        if(/pianist|klavier/.test(t))return 'Klavier';
        if(/dirigent|leitung/.test(t))return 'Leitung';
        if(/sänger|saenger|sopran|tenor|bariton|alt\b|gesang/.test(t))return 'Gesang';
        if(/gitarr/.test(t))return 'Gitarre';
        if(/violin|geige/.test(t))return 'Violine';
        if(/cellist|violoncello|cello/.test(t))return 'Violoncello';
        if(/schauspiel/.test(t))return 'Schauspiel';
        if(/autor|schriftsteller|lesung/.test(t))return 'Autor/in';
        return 'Mitwirkende';
      };
      const textParts=[];
      const pushText=v=>{const x=String(v||'').replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim();if(x)textParts.push(x);};
      pushText(item&&item.description); pushText(item&&item.shortDescription); pushText(item&&item.text);
      if(item&&item.rpg_event_details){pushText(item.rpg_event_details.description);pushText(item.rpg_event_details.shortDescription);}
      if(item&&item.texts){
        const rows=Array.isArray(item.texts)?item.texts:(item.texts.text?(Array.isArray(item.texts.text)?item.texts.text:[item.texts.text]):[]);
        rows.forEach(r=>pushText(typeof r==='string'?r:(r&&typeof r==='object'?(r.value||r.text||r.content||r.description):'')));
      }
      const source=[...new Set(textParts)].join(' ');
      const name="([A-ZÄÖÜ][A-Za-zÀ-ÖØ-öø-ÿÄÖÜäöüß’'\\-]+(?:\\s+(?:(?:van|von|de|der|den|du|di|da|la|le|ten|ter)\\s+)?[A-ZÄÖÜ][A-Za-zÀ-ÖØ-öø-ÿÄÖÜäöüß’'\\-]+){1,5})";
      const patterns=[
        new RegExp('\\bspielt\\s+'+name+'\\s*,\\s*([^.!?]{0,120})','gu'),
        new RegExp('\\bmit\\s+'+name+'(?:\\s*\\(([^)]{1,80})\\))?','gu'),
        new RegExp('\\bunter\\s+der\\s+Leitung\\s+von\\s+'+name,'gu')
      ];
      patterns.forEach((re,idx)=>{
        let m;
        while((m=re.exec(source))){
          const person=String(m[1]||'').trim();
          if(!person)continue;
          const context=idx===2?'Leitung':String(m[2]||'');
          add({name:person,role:roleFromContext(context||source.slice(Math.max(0,m.index-80),m.index+m[0].length+120))},roleFromContext(context));
        }
      });
    }
    return out.slice(0,40);
  }
  // V215 – global rich-event payload reuse. No audit and no extra request:
  // Reuse only additional information already present in the event payload.
  function eventRichList(value, child='') {
    if(!value)return [];
    let v=value;
    if(child&&v&&typeof v==='object'&&!Array.isArray(v)&&v[child]!=null)v=v[child];
    if(Array.isArray(v))return v;
    if(v&&typeof v==='object')return [v];
    return [v];
  }
  function eventRichPlainText(value) {
    let text=String(value==null?'':value)
      .replace(/<\s*br\s*\/?\s*>/gi,'\n')
      .replace(/<\/\s*(?:p|div|li|h[1-6]|section|article|tr)\s*>/gi,'\n')
      .replace(/<\s*li\b[^>]*>/gi,'• ')
      .replace(/<[^>]*>/g,'');
    text=text.replace(/&nbsp;|&#160;/gi,' ').replace(/&amp;/gi,'&').replace(/&quot;/gi,'"').replace(/&#0*39;|&apos;/gi,"'").replace(/&lt;/gi,'<').replace(/&gt;/gi,'>')
      .replace(/&auml;/gi,'ä').replace(/&ouml;/gi,'ö').replace(/&uuml;/gi,'ü').replace(/&Auml;/g,'Ä').replace(/&Ouml;/g,'Ö').replace(/&Uuml;/g,'Ü').replace(/&szlig;/gi,'ß').replace(/&euro;/gi,'€').replace(/&ndash;/gi,'–').replace(/&mdash;/gi,'—').replace(/&hellip;/gi,'…')
      .replace(/&#(\d+);/g,(_,n)=>{const c=Number(n);return Number.isFinite(c)?String.fromCodePoint(c):_;}).replace(/&#x([0-9a-f]+);/gi,(_,n)=>{const c=parseInt(n,16);return Number.isFinite(c)?String.fromCodePoint(c):_;});
    const lines=text.replace(/\r\n?/g,'\n').split('\n').map(line=>line.replace(/[ \t]+/g,' ').trim());
    const out=[];let blank=false;lines.forEach(line=>{if(!line){if(out.length&&!blank){out.push('');blank=true;}return;}out.push(line);blank=false;});
    while(out.length&&out[out.length-1]==='')out.pop();
    return out.join('\n').trim();
  }
  function eventRichTexts(item) {
    const rows=eventRichList(item&&item.texts,'text');
    return rows.map(row=>{
      if(typeof row==='string'||typeof row==='number')return {rel:'details',value:eventRichPlainText(row)};
      if(!row||typeof row!=='object')return null;
      const value=eventRichPlainText(row.value||row.text||row.content||row.description||'');
      return value?{rel:String(row.rel||row.type||'').toLowerCase(),value}:null;
    }).filter(Boolean);
  }
  function eventNestedActionUrl(node,depth=0,path='') {
    if(!node||typeof node!=='object'||depth>8)return '';
    if(!Array.isArray(node)){
      const rel=String(node.rel||node.relation||'').toLowerCase();
      if(/^(booking|ticket|tickets|reservation|registration)$/.test(rel)){
        const linked=eventTicketLink(node.url)||eventTicketLink(node.contentUrl)||eventTicketLink(node.href)||eventTicketLink(node.link);
        if(linked)return linked;
      }
    }
    for(const [k,v] of Object.entries(node)){
      const p=(path+'.'+String(k).toLowerCase()).replace(/^\./,'');
      if(typeof v==='string'&&/ticket|booking|book|order|reserve|reservation|registration|anmeld|karte|shop|sale|offer|admission|eintritt/.test(p)){
        const linked=eventTicketLink(v);if(linked)return linked;
      }
      if(v&&typeof v==='object'){const found=eventNestedActionUrl(v,depth+1,p);if(found)return found;}
    }
    return '';
  }
  function eventCleanRepeatedText(value) {
    let text=String(value==null?'':value).replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim();
    if(!text)return '';
    // Collapse exact repeated halves such as "Vorabbuchung nicht erforderlich" twice.
    // This deliberately does not rewrite merely similar wording.
    for(let pass=0;pass<3;pass++){
      const words=text.split(/\s+/);
      if(words.length<2||words.length%2)break;
      const half=words.length/2;
      const a=words.slice(0,half).join(' ');
      const b=words.slice(half).join(' ');
      const norm=v=>v.toLocaleLowerCase('de').replace(/[.,;:!?]+$/g,'').trim();
      if(norm(a)!==norm(b))break;
      text=a;
    }
    return text;
  }
  function eventPilotRich(item) {
    const texts=eventRichTexts(item);
    let description=''; const priceTexts=[];
    texts.forEach(t=>{if(!description&&/detail|description|long|body|text/.test(t.rel))description=t.value;if(/price|preis|admission|eintritt|fee|cost/.test(t.rel))priceTexts.push(t.value);});
    const priceItems=[];
    eventRichList(item&&item.numbers,'number').forEach(r=>{if(!r||typeof r!=='object')return;const label=String(r.type||r.key||'');const value=String(r.value||r.text||r.content||'').trim();if(value&&/price|preis|admission|eintritt|fee|cost|charge/i.test(label))priceItems.push({label,value,kind:'number'});});
    eventRichList(item&&item.attributes,'attribute').forEach(r=>{if(!r||typeof r!=='object')return;const label=String(r.key||r.type||'');let value=String(r.value||r.text||r.content||'').trim();if(!value||!/price|preis|admission|eintritt|fee|cost|charge|free|kostenlos/i.test(label))return;if(/free|kostenlos/i.test(label)&&/^(1|true|yes|ja|y|on)$/i.test(value))value='Kostenlos';if(/free|kostenlos/i.test(label)&&/^(0|false|no|nein|n|off)$/i.test(value))return;priceItems.push({label,value,kind:'attribute'});});
    ['price','priceInfo','price_info','priceFrom','price_from','priceTo','price_to','minPrice','min_price','maxPrice','max_price','admission','admissionPrice','admission_price','fee','charge'].forEach(k=>{const v=item&&item[k];if((typeof v==='string'||typeof v==='number')&&String(v).trim())priceTexts.push(String(v).trim());});
    if(!priceTexts.length&&priceItems.length)priceItems.forEach(r=>priceTexts.push((r.label?r.label+': ':'')+r.value));
    const organizers=[]; const primaryOrg=new Map(); const supportOrg=[];
    const orgNorm=v=>String(v||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('de').replace(/[^a-z0-9]+/g,' ').trim();
    const orgRow=r=>{if(!r)return null;if(typeof r==='string')r={name:r};if(typeof r!=='object')return null;const name=String(r.name||r.company||r.title||'').trim();if(!name)return null;const web=eventPublicUrl(r.web)||eventPublicUrl(r.website)||eventPublicUrl(r.homepage)||eventPublicUrl(r.url)||'';return {name,company:String(r.company||''),street:String(r.street||r.streetAddress||''),houseNumber:String(r.housenumber||r.houseNumber||''),zip:String(r.zip||r.postalCode||''),city:String(r.city||r.addressLocality||''),phone:String(r.phone||''),email:String(r.email||''),web};};
    const mergeOrg=(base,extra)=>{['company','street','houseNumber','zip','city','phone','email','web'].forEach(k=>{if(!base[k]&&extra[k])base[k]=extra[k];});return base;};
    const addPrimary=r=>{const o=orgRow(r);if(!o)return;const key=orgNorm(o.name||o.company);if(!key)return;if(primaryOrg.has(key))mergeOrg(primaryOrg.get(key),o);else primaryOrg.set(key,o);};
    eventRichList(item&&item.addresses,'address').forEach(r=>{if(!r||typeof r!=='object')return;const rel=String(r.rel||r.type||'').toLowerCase();if(/^(organizer|organiser|organisation|veranstalter)$/.test(rel))addPrimary(r);else if(/^(contact_person|contactperson|contact|author|support|lessor)$/.test(rel)){const o=orgRow(r);if(o)supportOrg.push(o);}});
    ['organizer','organizers','organiser','organisers','veranstalter'].forEach(k=>eventRichList(item&&item[k]).forEach(r=>{
      // V248: Preserve relation semantics even inside organizer-like containers.
      // ET4 payloads may mix author/support/provider rows into such structures;
      // those are not organizers and may only enrich an already known organizer
      // of the same identity.
      if(r&&typeof r==='object'){
        const rel=String(r.rel||r.relation||r.role||r.type||'').toLowerCase().trim();
        if(rel&&!/^(organizer|organiser|organisation|veranstalter)$/.test(rel)){
          if(/^(contact_person|contactperson|contact|author|support|lessor|provider|publisher|source|rights|copyright|creator|artist)$/.test(rel)){const o=orgRow(r);if(o)supportOrg.push(o);}
          return;
        }
      }
      addPrimary(r);
    }));
    supportOrg.forEach(o=>{const key=orgNorm(o.name||o.company);if(key&&primaryOrg.has(key))mergeOrg(primaryOrg.get(key),o);});
    primaryOrg.forEach(o=>organizers.push(o));
    const listify=(value,child)=>eventRichList(value,child).map(r=>typeof r==='string'||typeof r==='number'?String(r).trim():r&&typeof r==='object'?String(r.value||r.name||r.title||r.label||'').trim():'').filter(Boolean).filter((v,i,a)=>a.indexOf(v)===i);
    return {
      description,
      priceText:[...new Set(priceTexts.map(eventCleanRepeatedText).filter(Boolean))].slice(0,8).join(' · '),
      prices:{items:priceItems.slice(0,30)},
      organizers:organizers.slice(0,12),
      ticketUrl:eventNestedActionUrl(item),
      info:{categories:listify(item&&item.categories,'category'),features:listify(item&&item.features,'feature'),keywords:listify(item&&item.keywords,'keyword'),areas:listify(item&&item.areas,'area')}
    };
  }


  function eventCanonicalOrganizers(detailRows, richRows, hasCanonicalDetails) {
    const detail=Array.isArray(detailRows)?detailRows:[];
    const rich=Array.isArray(richRows)?richRows:[];
    const norm=v=>String(v||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('de').replace(/[^a-z0-9]+/g,' ').trim();
    const clean=r=>{if(!r||typeof r!=='object')return null;const name=String(r.name||r.company||'').trim();if(!name)return null;return Object.assign({},r,{name});};
    const merge=(a,b)=>{['company','street','houseNumber','zip','city','phone','email','web'].forEach(k=>{if(!a[k]&&b[k])a[k]=b[k];});return a;};
    // V252: once the backend supplied the canonical organizer identity, raw ET4
    // data may only complete contact/address fields. In particular it may not
    // donate a website from a provider/media/author branch back into the UI.
    const mergeCanonicalContact=(a,b)=>{['company','street','houseNumber','zip','city','phone','email'].forEach(k=>{if(!a[k]&&b[k])a[k]=b[k];});return a;};
    const out=new Map(), order=[];

    // V248: rpg_event_details is the server-side canonical visitor model. Once it
    // is present, only organizer identities approved by that model may reach the
    // card/detail UI. Raw ET4 rich data may enrich the SAME organizer with missing
    // contact fields, but it must never add a second identity (for example an
    // image author, data partner or provider such as Kreis Wesel/EAW).
    if(hasCanonicalDetails){
      detail.forEach(r=>{const o=clean(r);if(!o)return;const rel=String(o.rel||'').toLowerCase().trim();if(rel&&!/^(organizer|organiser|organisation|veranstalter)$/.test(rel))return;const key=norm(o.name||o.company);if(!key)return;if(out.has(key))merge(out.get(key),o);else{out.set(key,o);order.push(key);}});
      rich.forEach(r=>{const o=clean(r);if(!o)return;const key=norm(o.name||o.company);if(key&&out.has(key))mergeCanonicalContact(out.get(key),o);});
      return order.map(k=>out.get(k)).slice(0,12);
    }

    // Pure live-feed fallback: there is no canonical DB detail model yet. Keep
    // the existing structured ET4 organizer extraction for this exceptional path.
    rich.forEach(r=>{const o=clean(r);if(!o)return;const key=norm(o.name||o.company);if(!key)return;if(out.has(key))merge(out.get(key),o);else{out.set(key,o);order.push(key);}});
    return order.map(k=>out.get(k)).slice(0,12);
  }

  RPG.normalizeEvent = function (item, location) {
    const cityScope=String(item&&item.rpg_geo_scope||'').toLowerCase()==='city'||!!(item&&item.rpg_event_db&&item.rpg_event_db.cityScope);
    const coords = cityScope?null:eventCoords(item);
    const dates = eventDates(item);
    const next = dates.find(x => !x.end || x.end.getTime() >= Date.now() - 3600000) || dates[0] || null;
    const title = item.title || item.name || 'Event';
    const id = item.global_id || item.id || (title + '|' + (next ? next.start.toISOString() : ''));
    const city=deepFind(item,['city','addressLocality'],0) || (location && location.city) || '';
    const hasCanonicalEventDetails=!!(item&&item.rpg_event_details&&typeof item.rpg_event_details==='object');
    const details=hasCanonicalEventDetails?item.rpg_event_details:{};
    // V215: the Wesel/Oberhausen rich-event pilot proved stable. Reuse the same
    // already-delivered payload fields for every event; this performs no extra
    // request and does not invent missing data.
    const rich=eventPilotRich(item);
    const organizers=eventCanonicalOrganizers(details.organizers,rich.organizers,hasCanonicalEventDetails);
    const priceText=eventCleanRepeatedText(details.priceText||rich.priceText||'');
    const rawDescription=item.description || details.shortDescription || rich.description || item.shortDescription || item.text || '';
    const ticketUrl=eventTicketLink(details.ticketUrl)||eventTicketLink(item.ticketUrl)||eventTicketLink(item.ticket_url)||eventTicketLink(item.bookingUrl)||eventTicketLink(item.booking_url)||eventTicketLink(rich.ticketUrl)||eventNestedActionUrl(item)||eventTicketLinkFromText(rawDescription)||'';
    const canonicalUrl=httpUrl(details.canonicalUrl)||httpUrl(item.canonicalUrl)||'';
    const allDayKnown=Object.prototype.hasOwnProperty.call(details,'allDay')||Object.prototype.hasOwnProperty.call(item,'allDay');
    const boolish=v=>v===true||v===1||String(v||'').trim()==='1'||/^(?:true|yes|ja|on)$/i.test(String(v||'').trim());
    const allDay=Object.prototype.hasOwnProperty.call(details,'allDay')?boolish(details.allDay):boolish(item.allDay);
    const documents=[];const docSeen=new Set();
    (Array.isArray(details.documents)?details.documents:[]).forEach(d=>{if(!d||typeof d!=='object')return;const url=httpUrl(d.url);if(!url||docSeen.has(url))return;docSeen.add(url);documents.push({url,label:String(d.label||'Weitere Informationen').trim()||'Weitere Informationen',rel:String(d.rel||''),type:String(d.type||'')});});
    const fallbackProgram=httpUrl(details.programUrl)||httpUrl(item.programUrl)||'';if(fallbackProgram&&!docSeen.has(fallbackProgram))documents.push({url:fallbackProgram,label:String(details.programLabel||item.programLabel||'Programm'),rel:'program',type:''});
    const prices=details.prices&&typeof details.prices==='object'&&Object.keys(details.prices).length?details.prices:rich.prices;
    const eventInfo=details.info&&typeof details.info==='object'&&Object.keys(details.info).length?details.info:rich.info;
    const imageUrl=eventImage(item);
    const eventMedia=item.rpg_event_media&&typeof item.rpg_event_media==='object'?item.rpg_event_media:{};
    const eventMediaSource=String(eventMedia.source||'').toLowerCase();
    let mediaKind='original',mediaLabel='';
    if(eventMediaSource==='venue-editorial-pool'||eventMediaSource==='venue-commons-context'){mediaKind='context';mediaLabel='Event-Ort';}
    else if(eventMediaSource==='pexels-symbolic-event'){mediaKind='symbolic';mediaLabel='Symbolfoto';}
    // V313: `official-approved` is an internal rights state, not a visitor-facing
    // image type. Keep the approval in the payload/raw data but do not show a
    // technical "Freigegebenes Veranstaltungsbild" badge on cards or details.
    else if(eventMediaSource==='official-approved'){mediaKind='original';mediaLabel='';}
    else if(eventMediaSource==='editorial-override'){mediaKind='original';mediaLabel='PottFuchs Redaktion';}
    const rawMediaCredit=String(eventMedia.credit||'').trim();
    const rawMediaLicense=String(eventMedia.license||'').trim();
    // V313: WeselMarketing's written third-channel approval remains stored in the
    // backend rights metadata. The two synthetic proof strings are not image
    // credits/licences and therefore must not be rendered to visitors. Exact-match
    // suppression deliberately leaves real photographer/creator credits untouched.
    const weselApprovalCredit='WeselMarketing GmbH / jeweiliger Veranstalter · Nutzung fuer RuhrpottGuru schriftlich bestaetigt';
    const weselApprovalLicense='Schriftliche Drittkanal-Freigabe WeselMarketing';
    const mediaCredit=rawMediaCredit===weselApprovalCredit?'':rawMediaCredit;
    const mediaLicense=rawMediaLicense===weselApprovalLicense?'':rawMediaLicense;
    const mediaArtist=String(eventMedia.artist||'').trim();
    const mediaSemanticVersion=String(eventMedia.semanticVersion||'').trim();
    const eventCrop=v=>v&&['auto','manual'].includes(String(v.mode||''))?{mode:String(v.mode),x:Number(v.x??50),y:Number(v.y??50),zoom:Number(v.zoom??1)}:null;
    const imageCropCard=eventCrop(eventMedia.cropCard),imageCropDetail=eventCrop(eventMedia.cropDetail);
    const galleryImages=imageUrl?[{url:imageUrl,credit:[mediaLabel,mediaCredit,mediaLicense].filter(Boolean).join(' · '),artist:mediaArtist,source:String(eventMedia.source||''),license:mediaLicense,cropCard:imageCropCard,cropDetail:imageCropDetail}]:[];
    let publicWebsite=eventPublicUrl(details.website)||eventUrl(item);
    if(publicWebsite&&ticketUrl&&publicWebsite.replace(/\/$/,'')===ticketUrl.replace(/\/$/,''))publicWebsite='';
    return {
      kind:'event', id:String(id), category:'events', title,
      subtitle: venueName(item), eventType:eventType(item),
      city,
      address: eventAddress(item), coords, cityScope,
      distanceKm: !cityScope && coords && location ? RPG.distanceKm(location.lat, location.lng, coords.lat, coords.lng) : null,
      start: next && next.start, end: next && next.end, allDay, allDayKnown,
      description: rawDescription,
      shortDescription:String(details.shortDescription||item.shortDescription||''),
      website: publicWebsite, ticketUrl, canonicalUrl, documents, programUrl:documents.length?documents[0].url:'', programLabel:documents.length?documents[0].label:'Programm', imageUrl, imageCropCard, imageCropDetail, galleryImages, mediaKind, mediaLabel, mediaCredit, mediaLicense, mediaArtist, mediaSemanticVersion, labels:[],
      visitorHours:String(details.visitorHours||item.visitorHours||''), dayHours:String(details.dayHours||item.dayHours||''), visitorHoursSource:httpUrl(details.visitorHoursSource)||'',
      priceText, prices, organizers, eventInfo,
      sourceCreatedAt:String(details.sourceCreatedAt||''), sourceChangedAt:String(details.sourceChangedAt||''),
      sourceName: item.source_name || item.sourceName || '',
      sources: Array.isArray(item.rpg_sources)?item.rpg_sources:(Array.isArray(item.sources)?item.sources:[]),
      sourceLicense: item.source_license || item.sourceLicense || '',
      sourceAttribution: item.source_attribution || '',
      sourceTermsUrl: item.source_terms_url || '',
      sourcePageUrl: httpUrl(item.source_page_url)||httpUrl(item.sourcePageUrl)||'',
      sources: Array.isArray(item.rpg_sources) ? item.rpg_sources : [],
      participants: eventParticipants(item,true),
      series:item.rpg_event_series&&typeof item.rpg_event_series==='object'?item.rpg_event_series:{count:1,first:'',last:'',identity:''},
      raw:item
    };
  };

  function eventListNorm(value) {
    return String(value == null ? '' : value)
      .normalize('NFD').replace(/[\u0300-\u036f]/g,'')
      .toLocaleLowerCase('de')
      .replace(/[^a-z0-9]+/g,' ')
      .trim();
  }

  // V175: Visitor-facing lists show one card per logical event series. The
  // persistent DB intentionally stores concrete recurrence occurrences for
  // lifecycle/calendar correctness; rendering every future occurrence would let
  // Christmas markets, exhibitions or weekly series occupy whole result pages.
  // Keep the nearest relevant occurrence and also suppress exact cross-source
  // duplicates that happen to carry different external IDs.
  RPG.dedupeEventsForListing = function (items) {
    const source = Array.isArray(items) ? items.slice() : [];
    source.sort((a,b)=>{
      const at=a&&a.start instanceof Date?a.start.getTime():Number.POSITIVE_INFINITY;
      const bt=b&&b.start instanceof Date?b.start.getTime():Number.POSITIVE_INFINITY;
      if(at!==bt)return at-bt;
      const ad=Number.isFinite(a&&a.distanceKm)?a.distanceKm:Number.POSITIVE_INFINITY;
      const bd=Number.isFinite(b&&b.distanceKm)?b.distanceKm:Number.POSITIVE_INFINITY;
      return ad-bd;
    });

    const seenSeries=new Set();
    const seenExact=new Set();
    const out=[];
    for(const item of source){
      if(!item)continue;
      const raw=item.raw&&typeof item.raw==='object'?item.raw:{};
      const external=String(raw.global_id||raw.id||item.id||'').trim();
      if(external){
        const seriesKey='id:'+external;
        if(seenSeries.has(seriesKey))continue;
        seenSeries.add(seriesKey);
      }

      const start=item.start instanceof Date&&!isNaN(item.start)?item.start.toISOString().slice(0,16):'';
      const place=eventListNorm(item.subtitle||item.address||'');
      const exactKey=[eventListNorm(item.title),start,eventListNorm(item.city),place].join('|');
      if(exactKey.replace(/\|/g,'') && seenExact.has(exactKey))continue;
      if(exactKey.replace(/\|/g,''))seenExact.add(exactKey);
      out.push(item);
    }
    return out;
  };

  // V289 – last-mile safety for automatic Event-Ort fallbacks.
  // The backend owns rights/provenance and persistent media. This queue is only
  // a bounded display fallback when the browser proves that a localized venue
  // context image is a text/poster-like visual. Requests are batched so a grid
  // with many affected cards never creates one network request per card.
  const eventQualityFallbackCache=new Map();
  const eventQualityFallbackQueue=new Map();
  let eventQualityFallbackTimer=0;
  async function flushEventQualityFallbackQueue(){
    eventQualityFallbackTimer=0;
    const batch=Array.from(eventQualityFallbackQueue.values()).slice(0,12);
    batch.forEach(x=>eventQualityFallbackQueue.delete(x.key));
    if(!batch.length)return;
    let rows=[];
    try{
      rows=await RPG.api.eventMediaQualityFallback(batch.map(x=>x.payload));
    }catch(_){rows=[];}
    const byId=new Map((Array.isArray(rows)?rows:[]).filter(x=>x&&x.id&&x.imageUrl).map(x=>[String(x.id),x]));
    batch.forEach(x=>{
      const media=byId.get(String(x.payload.id||''))||null;
      eventQualityFallbackCache.set(x.key,media);
      x.waiters.forEach(resolve=>resolve(media));
    });
    if(eventQualityFallbackQueue.size&&!eventQualityFallbackTimer)eventQualityFallbackTimer=window.setTimeout(flushEventQualityFallbackQueue,90);
  }
  RPG.requestEventQualityFallback=function(item){
    if(!item||item.category!=='events')return Promise.resolve(null);
    const id=String(item.id||'').trim();if(!id)return Promise.resolve(null);
    const key=id+'|'+eventMediaSemanticBucket(item);
    if(eventQualityFallbackCache.has(key))return Promise.resolve(eventQualityFallbackCache.get(key));
    return new Promise(resolve=>{
      let queued=eventQualityFallbackQueue.get(key);
      if(!queued){
        queued={key,payload:{id,title:String(item.title||''),city:String(item.city||''),eventType:String(item.eventType||'')},waiters:[]};
        eventQualityFallbackQueue.set(key,queued);
      }
      queued.waiters.push(resolve);
      if(!eventQualityFallbackTimer)eventQualityFallbackTimer=window.setTimeout(flushEventQualityFallbackQueue,90);
    });
  };

  function eventDisplayMediaKey(value) {
    const raw=String(value||'').trim();
    if(!raw)return '';
    try{
      const u=new URL(raw,window.location.origin);
      const host=String(u.hostname||'').toLowerCase().replace(/^www\./,'');
      let path=decodeURIComponent(String(u.pathname||'')).toLowerCase().replace(/\/{2,}/g,'/');
      // Wikimedia thumbnail widths create different URLs for the same underlying
      // file. Collapse them to the source-file identity so 640/1024/1280px
      // variants are still recognised as the same Event-Ort image.
      if(host==='upload.wikimedia.org'){
        const thumb=path.match(/^(\/wikipedia\/commons)\/thumb(\/[^/]+\/[^/]+\/[^/]+)(?:\/[^/]+)?$/);
        if(thumb)path=thumb[1]+thumb[2];
      }
      const redirect=path.match(/\/special:redirect\/file\/(.+)$/);
      if(redirect)path='/file/'+redirect[1];
      return host+path;
    }catch(_){return raw.split(/[?#]/)[0].toLowerCase();}
  }

  function eventMediaSemanticBucket(item) {
    const raw=item&&item.raw&&typeof item.raw==='object'?item.raw:{};
    let extra='';
    try{extra=JSON.stringify([raw.categories||[],raw.keywords||[],raw.features||[],raw.type||'',raw.types||[],raw.classification||'',raw.classifications||[]]);}catch(_){extra='';}
    const norm=v=>String(v||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('de');
    const title=norm(item&&item.title);
    const type=norm(item&&item.eventType);
    const structured=norm(extra);
    const all=(title+' '+type+' '+structured).trim();

    // Strong title signals override broad audience/location metadata.
    if(/\b(halloween|party|clubnacht|nightlife|disco|dj|tanzparty|dance\s*party)\b/.test(title))return 'party';
    if(/\b(parkour|sport|marathon|lauf(?:veranstaltung)?|turnier|rennen|fussball|handball|basketball|radsport|cycling)\b/.test(title))return 'sports';
    if(/\b(workshop|kurs|seminar|mitmach|atelierkurs)\b/.test(title))return 'workshop';
    if(/\b(fuehrung|fuhrung|rundgang|stadtrundgang|werksfuehrung|werksfuhrung|tour)\b/.test(title))return 'tour';
    if(/\b(vortrag|lesung|lecture|talk|diskussion|podium)\b/.test(title))return 'talk';
    if(/\b(kino|filmvorfuehrung|filmvorfuhrung|screening)\b/.test(title))return 'film';
    if(/\b(raetsel|ratsel|escape|quiz|schnitzeljagd|detektiv)\b/.test(title))return 'puzzle';

    // Frontend-normalized event type is the primary semantic contract.
    if(/party|nightlife|nachtleben|club|disco|tanz/.test(type))return 'party';
    if(/konzert|musik|music/.test(type))return 'concert';
    if(/comedy|kabarett|show|variet|zauber|circus|zirkus/.test(type))return 'comedy';
    if(/theater|buhne|oper|musical|schauspiel|performance/.test(type))return 'theater';
    if(/ausstellung|kunst|exhibition|galerie|museum/.test(type))return 'exhibition';
    if(/markt|stadtfest|kirmes|messe|volksfest/.test(type))return 'market';
    if(/sport/.test(type))return 'sports';
    if(/familie|kinder|family/.test(type))return 'family';
    if(/tour|wissen|workshop/.test(type)){
      if(/fuehrung|fuhrung|rundgang|tour|industriekultur/.test(all))return 'tour';
      if(/vortrag|lesung|lecture|talk|diskussion|podium/.test(all))return 'talk';
      if(/workshop|kurs|seminar|mitmach|atelier/.test(all))return 'workshop';
      return 'knowledge';
    }

    if(/\b(halloween|party|clubnacht|nightlife|disco|tanzparty)\b/.test(structured))return 'party';
    if(/\b(konzert|musikveranstaltung|live\s*music|chor|orchester|jazz|rock|pop)\b/.test(structured))return 'concert';
    if(/\b(comedy|kabarett|variet|zauber|circus|zirkus)\b/.test(structured))return 'comedy';
    if(/\b(theater|buhne|schauspiel|oper|musical|performance)\b/.test(structured))return 'theater';
    if(/\b(ausstellung|exhibition|vernissage|galerie|kunstschau)\b/.test(structured))return 'exhibition';
    if(/\b(flohmarkt|trodelmarkt|markt|stadtfest|kirmes|volksfest|messe)\b/.test(structured))return 'market';
    if(/\b(parkour|sport|marathon|laufveranstaltung|turnier|rennen|fussball|handball|basketball|radsport)\b/.test(structured))return 'sports';
    if(/\b(kinder|familien|familie|kids|ferienprogramm)\b/.test(structured))return 'family';
    if(/\b(fuehrung|fuhrung|rundgang|tour|industriekultur)\b/.test(structured))return 'tour';
    if(/\b(workshop|kurs|seminar|mitmach|atelierkurs)\b/.test(structured))return 'workshop';
    if(/\b(vortrag|lesung|lecture|talk|diskussion|podium)\b/.test(structured))return 'talk';
    if(/\b(kino|filmvorfuehrung|filmvorfuhrung|screening|film)\b/.test(structured))return 'film';
    if(/\b(raetsel|ratsel|escape|quiz|schnitzeljagd|detektiv)\b/.test(structured))return 'puzzle';
    if(/\b(natur|garten|park|pflanzen|wald|forest)\b/.test(structured))return 'nature';

    if(/konzert|musik|band|orchester|jazz|rock|pop|ska/.test(title))return 'concert';
    if(/comedy|kabarett|show|variet|zauber|circus|zirkus/.test(title))return 'comedy';
    if(/theater|buhne|schauspiel|oper|musical/.test(title))return 'theater';
    if(/ausstellung|vernissage|galerie|kunst/.test(title))return 'exhibition';
    if(/markt|kirmes|stadtfest|volksfest|messe/.test(title))return 'market';
    if(/familie|kinder|kids/.test(title))return 'family';
    if(/natur|garten|park|wald/.test(title))return 'nature';
    return 'generic';
  }
  RPG.eventMediaSemanticBucketV298=eventMediaSemanticBucket;

  function eventDiversityHash(value){
    const str=String(value||''); let hash=2166136261;
    for(let i=0;i<str.length;i++){hash^=str.charCodeAt(i);hash=Math.imul(hash,16777619);}
    return hash>>>0;
  }

  function eventDisplaySymbol(item,media,reason){
    const url=String(media&&media.imageUrl||'');
    const credit=[String(media&&media.mediaLabel||'Symbolfoto'),String(media&&media.credit||''),String(media&&media.license||'')].filter(Boolean).join(' · ');
    return Object.assign({},item,{
      displayImageUrl:url,
      displayImageSuppressed:false,
      displayMediaKind:'symbolic',
      displayMediaLabel:String(media&&media.mediaLabel||'Symbolfoto'),
      displayMediaCredit:String(media&&media.credit||''),
      displayMediaLicense:String(media&&media.license||''),
      displayMediaArtist:String(media&&media.artist||''),
      displayMediaSource:String(media&&media.source||'pexels-symbolic-event'),
      displayMediaSourceUrl:String(media&&media.sourceUrl||''),
      mediaSemanticVersion:String(media&&media.semanticVersion||'v298'),
      displayMediaReason:String(reason||'semantic-symbol'),
      displayImageCropCard:null,displayImageCropDetail:null,
      galleryImages:url?[{url,credit,artist:String(media&&media.artist||''),source:String(media&&media.source||'pexels-symbolic-event'),license:String(media&&media.license||'')}]:[]
    });
  }

  function eventSuppressSymbol(item,reason){
    return Object.assign({},item,{
      displayImageUrl:'',
      displayImageSuppressed:true,
      displayMediaKind:'',
      displayMediaLabel:'',
      displayMediaCredit:'',
      displayMediaLicense:'',
      displayMediaArtist:'',
      displayMediaSource:'',
      displayMediaSourceUrl:'',
      displayMediaReason:String(reason||'semantic-symbol-awaiting-refresh'),
      displayImageCropCard:null,displayImageCropDetail:null,
      galleryImages:[]
    });
  }

  function eventDiversityTargets(items,scanLimit,windowSize){
    const targets=[];
    const lastKept=new Map();
    items.slice(0,scanLimit).forEach((item,index)=>{
      if(!item||item.category!=='events'||!item.imageUrl)return;
      const key=eventDisplayMediaKey(item.imageUrl); if(!key)return;
      const rawMedia=item.raw&&item.raw.rpg_event_media&&typeof item.raw.rpg_event_media==='object'?item.raw.rpg_event_media:{};
      // Manual editorial Place pools remain authoritative and are never replaced.
      if(String(rawMedia.source||'').toLowerCase()==='venue-editorial-pool')return;
      const previous=lastKept.has(key)?lastKept.get(key):null;
      const bucket=eventMediaSemanticBucket(item);
      if(item.mediaKind==='context'&&previous!==null&&(index-previous)<windowSize&&bucket!=='generic'){
        targets.push({
          id:String(item.id||''),title:String(item.title||''),city:String(item.city||''),
          eventType:String(item.eventType||''),index,bucket,originalKey:key,kind:'duplicate-context'
        });
        return;
      }
      lastKept.set(key,index);
    });
    return targets;
  }

  function eventSemanticRefreshTargets(items,scanLimit){
    const targets=[];
    items.slice(0,scanLimit).forEach((item,index)=>{
      if(!item||item.category!=='events'||item.mediaKind!=='symbolic'||item.mediaSemanticVersion==='v298')return;
      const bucket=eventMediaSemanticBucket(item);
      if(bucket==='generic')return;
      targets.push({
        id:String(item.id||''),title:String(item.title||''),city:String(item.city||''),
        eventType:String(item.eventType||''),index,bucket,
        originalKey:eventDisplayMediaKey(item.imageUrl),kind:'symbol-refresh'
      });
    });
    return targets;
  }

  /**
   * V298 – relevance-first Event fallback presentation.
   * Persisted legacy Symbolfotos are suppressed synchronously and may reappear
   * only after the strict semantic resolver returns a theme-matching image.
   * Repeated Event-Ort images are diversified only when a non-generic event
   * theme is known. Unknown themes keep the location image or neutral ticket
   * placeholder; unrelated stock is never borrowed from another card.
   */
  function eventMediaDiversityLocalPass(items){
    if(!Array.isArray(items)||!items.length)return {items:[],targets:[],unresolved:[]};
    const scanLimit=Math.min(items.length,60),windowSize=10;
    const out=items.map(item=>item&&item.category==='events'&&item.mediaKind==='symbolic'&&item.mediaSemanticVersion!=='v298'?eventSuppressSymbol(item,'legacy-symbol-semantic-recheck-v298'):item);
    if(!RPG.settings.eventMediaDiversityEnabled){
      return {items:out,targets:[],unresolved:[]};
    }
    const targets=eventDiversityTargets(items,scanLimit,windowSize);
    const refresh=eventSemanticRefreshTargets(items,scanLimit);
    const byIndex=new Map();
    refresh.concat(targets).forEach(t=>{if(!byIndex.has(t.index)||t.kind==='symbol-refresh')byIndex.set(t.index,t);});
    const unresolved=Array.from(byIndex.values()).sort((a,b)=>a.index-b.index);
    return {items:out,targets:unresolved,unresolved};
  }

  RPG.diversifyEventMediaLocal=function(items){
    return eventMediaDiversityLocalPass(items).items;
  };

  /**
   * V298 – bounded async semantic repair. This does not change event order,
   * event eligibility, radius or persisted data. It replaces only display media.
   */
  RPG.diversifyEventMedia = async function(items) {
    const local=eventMediaDiversityLocalPass(items);
    if(!RPG.settings.eventMediaDiversityEnabled||!Array.isArray(items)||!items.length||!local.unresolved.length)return local.items;
    const pending=local.unresolved.slice(0,36);
    try{
      const response=await RPG.api.eventMediaSemanticFallback(pending.map(({index,bucket,originalKey,kind,...x})=>x));
      const mediaById=new Map((Array.isArray(response)?response:[]).filter(x=>x&&x.id&&x.imageUrl).map(x=>[String(x.id),x]));
      const out=local.items.slice();
      pending.forEach(target=>{
        const m=mediaById.get(String(target.id||'')); if(!m)return;
        const candidateKey=eventDisplayMediaKey(m.imageUrl); if(!candidateKey)return;
        if(target.kind==='duplicate-context'&&candidateKey===target.originalKey)return;
        out[target.index]=eventDisplaySymbol(out[target.index],m,target.kind==='symbol-refresh'?'legacy-symbol-semantic-refresh-v298':'duplicate-context-semantic-v298');
      });
      return out;
    }catch(_){return local.items;}
  };

  RPG.resolveItemMedia = async function (item) {
    if (item.imageUrl && (!RPG.personHint(item.imageUrl) || item.category === 'events')) return item.imageUrl;
    if (item.category === 'events') {
      // DB-backed events are enriched server-side. Never launch one live media
      // resolver request per visible card; that caused request storms and slow pages.
      if(item.raw && item.raw.rpg_event_db) return '';
      const url = RPG.settings.eventMediaUrl;
      if (!url) return '';
      try {
        const data = await RPG.api.post(url, {
          title:item.title, city:item.city, venueName:item.subtitle || '', website:item.website || '', address:item.address || '', coords:item.coords || null, eventType:item.eventType || '',
          start:item.start instanceof Date ? item.start.toISOString() : (item.start || ''),
          raw:item.raw
        });
        return data.imageUrl || '';
      } catch (_) { return ''; }
    }

    const mediaUrl = RPG.settings.foodMediaUrl;
    if (!mediaUrl) return '';
    const t = item.tags || {};
    let officialWebsite = item.website || '';

    // V112: Highlights, Kultur und Aktiv first try to resolve an identity-bound
    // official website (OSM -> Wikidata P856 -> matching eT4 POI). This unlocks
    // original venue/location imagery that was previously missed when OSM had
    // Wikidata but no website tag.
    if (item.category !== 'food' && !officialWebsite && RPG.settings.placeInfoUrl) {
      try {
        const info = await RPG.api.post(RPG.settings.placeInfoUrl, {
          title:item.title, category:item.category, city:item.city, address:item.address,
          website:'', wikidata:t.wikidata || '',
          lat:item.coords && item.coords.lat, lng:item.coords && item.coords.lng
        });
        if (info && info.status === 'success' && /^https?:\/\//i.test(String(info.website || ''))) {
          officialWebsite = info.website;
        }
      } catch (_) {}
    }

    try {
      const data = await RPG.api.post(mediaUrl, {
        title:item.title, city:item.city, address:item.address, website:officialWebsite,
        wikidata:t.wikidata || '', wikipedia:t.wikipedia || '', osmImage:t.image || '', wikimediaCommons:t.wikimedia_commons || '',
        lat:item.coords && item.coords.lat, lng:item.coords && item.coords.lng,
        subcategory:item.subtitle, cuisine:t.cuisine || '', tags:item.labels || [],
        allowSymbolic:item.category === 'food', category:item.category || ''
      });
      const image = data.imageUrl || '';
      const meta = [data.alt,data.credit,data.reason,data.sourceUrl,image].join(' ');
      if (item.category !== 'events' && RPG.personHint(meta)) return '';
      return image;
    } catch (_) { return ''; }
  };
})(window);
