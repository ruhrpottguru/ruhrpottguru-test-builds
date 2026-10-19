(function () {
  'use strict';
  if (!window.RGPlacesBootstrap || !window.fetch) return;
  var cfg = window.RGPlacesBootstrap;
  var startedAt = Date.now();
  var maxRuntimeMs = 12 * 60 * 1000;
  var stopped = false;
  var totals = { cities: 0, imported: 0 };
  var consecutiveFailures = 0;

  function headers() {
    return { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce };
  }

  function sleep(ms) { return new Promise(function (resolve) { window.setTimeout(resolve, ms); }); }

  function badge(text, bad) {
    var el = document.getElementById('rg-places-bootstrap-badge');
    if (!el) {
      el = document.createElement('div');
      el.id = 'rg-places-bootstrap-badge';
      el.style.cssText = 'position:fixed;right:18px;bottom:18px;z-index:999999;background:#111827;color:#fff;border:1px solid #374151;border-radius:10px;padding:9px 12px;font:12px/1.35 system-ui;box-shadow:0 6px 20px rgba(0,0,0,.25);max-width:330px';
      document.body.appendChild(el);
    }
    el.style.borderColor = bad ? '#b91c1c' : '#374151';
    el.textContent = text;
  }

  async function jsonFetch(url, options) {
    var res = await fetch(url, options || { credentials: 'same-origin' });
    var text = await res.text();
    var data;
    try { data = text ? JSON.parse(text) : {}; } catch (_) { throw new Error('Nicht-JSON von ' + url); }
    if (!res.ok || data.ok === false) {
      var err = new Error(data.error || data.message || ('HTTP ' + res.status));
      err.status = res.status;
      throw err;
    }
    return data;
  }

  async function overpass(query) {
    var last = null;
    for (var i = 0; i < cfg.providers.length; i++) {
      var controller = new AbortController();
      var timer = setTimeout(function () { controller.abort(); }, i === 0 ? 30000 : (i === 1 ? 25000 : 22000));
      try {
        var body = new URLSearchParams();
        body.set('data', query);
        var res = await fetch(cfg.providers[i], { method: 'POST', mode: 'cors', credentials: 'omit', body: body, signal: controller.signal });
        if (!res.ok) throw new Error('Overpass HTTP ' + res.status);
        var payload = await res.json();
        if (!payload || !Array.isArray(payload.elements) || payload.remark) throw new Error(payload && payload.remark ? String(payload.remark) : 'Ungültige Overpass-Antwort');
        clearTimeout(timer);
        return payload.elements;
      } catch (e) {
        clearTimeout(timer);
        last = e;
      }
      await sleep(1200);
    }
    throw last || new Error('Kein Overpass-Provider erreichbar');
  }

  async function importCity(next) {
    var city = next.municipality;
    badge('Places DB: ' + city.name + (next.runType === 'refresh' ? ' wird automatisch aktualisiert …' : ' wird automatisch importiert …'));
    var queries = Array.isArray(next.queries) && next.queries.length ? next.queries : [next.query];
    var merged = new Map();
    for (var q = 0; q < queries.length; q++) {
      badge('Places DB: ' + city.name + ' · Datenblock ' + (q + 1) + '/' + queries.length);
      var part = await overpass(queries[q]);
      part.forEach(function (el) {
        if (!el || !el.type || el.id == null) return;
        merged.set(String(el.type) + ':' + String(el.id), el);
      });
      await sleep(650);
    }
    var elements = Array.from(merged.values());
    if (elements.length < 5) throw new Error('Zu wenige Rohdaten für ' + city.name + ': ' + elements.length);
    var imported = 0;
    for (var i = 0; i < elements.length; i += 250) {
      var chunk = elements.slice(i, i + 250);
      var answer = await jsonFetch(cfg.importUrl, {
        method: 'POST', credentials: 'same-origin', headers: headers(),
        body: JSON.stringify({ municipality: city.slug, elements: chunk })
      });
      imported += answer.stats && answer.stats.accepted ? Number(answer.stats.accepted) : 0;
      badge('Places DB: ' + city.name + ' · ' + Math.min(i + chunk.length, elements.length) + '/' + elements.length);
    }
    await jsonFetch(cfg.completeUrl, {
      method: 'POST', credentials: 'same-origin', headers: headers(),
      body: JSON.stringify({ municipality: city.slug, rawCount: elements.length, importedCount: imported, runType: next.runType || 'bootstrap' })
    });
    totals.cities += 1;
    totals.imported += imported;
    return imported;
  }

  async function loop() {
    // A soft local lock avoids duplicate work in two open admin tabs. Server-side upserts remain idempotent anyway.
    try {
      var lock = Number(localStorage.getItem('rg_places_bootstrap_lock') || 0);
      if (lock && Date.now() - lock < 45000) return;
      localStorage.setItem('rg_places_bootstrap_lock', String(Date.now()));
    } catch (_) {}

    while (!stopped && Date.now() - startedAt < maxRuntimeMs) {
      var next;
      try {
        next = await jsonFetch(cfg.nextUrl, { credentials: 'same-origin', headers: { 'X-WP-Nonce': cfg.nonce } });
      } catch (e) {
        badge('Places DB: Status konnte nicht geladen werden · wird später automatisch fortgesetzt', true);
        break;
      }
      if (next.done) {
        var status = next.status || {};
        badge(status.mode === 'database'
          ? 'Places DB bereit · ' + (status.activePlaces || 0) + ' Orte · Datenbankmodus aktiv'
          : 'Places DB Import abgeschlossen · Validierung: ' + (status.status || 'prüfen'), status.mode !== 'database');
        break;
      }
      if (next.waiting) {
        var retrySec = Math.max(1, Number(next.retryAfter || 30));
        var retryName = next.nextRetry && next.nextRetry.name ? next.nextRetry.name : 'nächste Kommune';
        badge('Places DB: ' + retryName + ' wartet auf Retry · weiter in ca. ' + Math.max(1, Math.ceil(retrySec / 60)) + ' Min.');
        // Keep the admin page alive, but poll at most once per minute.
        await sleep(Math.min(60000, retrySec * 1000));
        try { localStorage.setItem('rg_places_bootstrap_lock', String(Date.now())); } catch (_) {}
        continue;
      }
      try {
        await importCity(next);
        consecutiveFailures = 0;
      } catch (e) {
        consecutiveFailures += 1;
        badge('Places DB: ' + next.municipality.name + ' nicht erreichbar · automatischer Retry später' + (e && e.message ? ' (' + e.message + ')' : ''), true);
        try {
          await jsonFetch(cfg.completeUrl, {
            method: 'POST', credentials: 'same-origin', headers: headers(),
            body: JSON.stringify({ municipality: next.municipality.slug, rawCount: 0, importedCount: 0, runType: next.runType || 'bootstrap' })
          });
        } catch (_) {}
        await sleep(1200);
        if (consecutiveFailures >= 2) {
          badge('Places DB: mehrere Fehler · andere Kommunen bzw. Cooldowns werden weiter geprüft', true);
          consecutiveFailures = 0;
          await sleep(20000);
        }
        continue;
      }
      try { localStorage.setItem('rg_places_bootstrap_lock', String(Date.now())); } catch (_) {}
      await sleep(1400);
    }
    try { localStorage.removeItem('rg_places_bootstrap_lock'); } catch (_) {}
  }

  window.addEventListener('beforeunload', function () { stopped = true; try { localStorage.removeItem('rg_places_bootstrap_lock'); } catch (_) {} });
  window.setTimeout(loop, 1500);
})();
