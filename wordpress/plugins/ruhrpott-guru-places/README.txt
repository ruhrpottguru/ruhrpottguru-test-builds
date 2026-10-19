RUHRPOTTGURU PLACES BACKEND 1.10.101 – TIERED EVENT SYNC
===========================================================
- 0–14 Tage: ET4/Open Data Finder alle 4 Stunden.
- 15–90 Tage: ET4/Open Data Finder täglich um 01:00 Uhr.
- 0–365 Tage: vollständiger Master sonntags um 03:00 Uhr als konsistente Wochen-Reconciliation; damit bleibt die 91–365-Tage-Langzeitabdeckung vollständig und die bestehende Master-/Supplemental-Logik stabil.
- Globaler Event-Import-Lock + Queue verhindern parallele Master-, Kurz-/Planungs- und 53-Städte-Zusatzfeed-Läufe.
- Ein beim Update bereits laufender alter 03:00-Master wird nicht beendet oder zurückgesetzt; v1.10.101 übernimmt ihn und wartet mit neuen Tier-Läufen bis zum Abschluss.
- Kurz-/Planungsrefreshes verwenden denselben autoritativen ET4-Medienpfad, verändern aber bewusst nicht last_master_run.
- Besucher-DB bleibt zwischen den wöchentlichen Vollabgleichen durch erfolgreiche Kurz-/Planungs-Ingests frisch; keine unnötige Live-Fallback-Umschaltung nach 48 Stunden.
- Keine Schema-Migration und kein Reset vorhandener Event-, Halloween-, SEO-, Places- oder Medienbestände.

--- Vorheriger Stand 1.10.100 ---
RUHRPOTTGURU PLACES BACKEND 1.10.100 – HALLOWEEN GEO-FIX
==========================================================
- Exakt 9 bereits verifizierte Halloween-2026-Research-Anker aus dem 53-Kommunen-Sweep erhalten ihre verifizierte Venue-Adresse und Geoposition.
- Kein neuer Kommunen-Sweep und keine neuen Eventquellen werden aktiviert.
- Vorhandene ET4/Open-Data-Events behalten Vorrang; die bestehende Deduplizierung läuft vor dem redaktionellen Fallback.
- Ortsbild-Worker, REST/Radius, SEO, Reprocessing, Eventbilder und Theme bleiben unverändert.

RUHRPOTTGURU PLACES BACKEND 1.10.80 – PLACES-DATENQUALITÄT END-TO-END
======================================================================
- Neuer Besucher-Nutzwert-Resolver für Kultur: breite OSM-Tags wie community_centre/events_venue werden nicht mehr blind ausgespielt.
- City Highlight Intelligence berücksichtigt die heutige Primärnutzung historischer Gebäude; Hotel/Pflege/Soziales/Gesundheit/Bildung/Büro werden ohne echtes Attraktions-/Route-Signal nicht zum Highlight.
- Bestehende Activity Intelligence bleibt erhalten und wird nicht umgebaut.
- OSM-Core folgt bei derselben Primärquelle künftig der aktuellen Quelle statt alten reicheren Tags.
- Nach erfolgreichem Kommunen-Snapshot werden stale Primary Sources auf eine aktuell gesehene relevante OSM-Quelle reconciled.
- Eine einmalige nicht-destruktive 53-Kommunen-Refresh-Welle aktualisiert den Altbestand serverseitig.
- RP Redaktion -> Places erhält einen Mehrstadt-Places-Finalcheck für Highlights/Kultur/Aktivitäten + Lifecycle/Altbestand.
- Event-/ET4- und Öffnungszeiten-Logik bleiben unverändert.

--- Vorheriger Stand ---
RUHRPOTTGURU PLACES BACKEND 1.10.50 – ZENTRALE WORKER-INVENTUR / COVERAGE-GATE

Neu in 1.10.50:
- RP Redaktion → Events zeigt alle relevanten Hintergrundprozesse in einer gemeinsamen Worker-Tabelle.
- Unterscheidung zwischen lokalen Jobs und extern/ressourcenintensiven Jobs.
- Sichtbarer Status „Coverage-Audit: frei/wartet“ inklusive konkreter Blocker.
- Der future-safe Coverage-Gate ist bereits im Backend vorhanden, startet aber bewusst noch keinen neuen 53-Städte-Import.
- Bestehende ET4-, destination.one-, Places-, Bild-, Enrichment- und Öffnungszeiten-Worker werden NICHT umgebaut oder zurückgesetzt.
- Damit ist vor dem Quellen-Coverage-Ausbau transparent, welche Queues parallel laufen dürfen.

--- Vorheriger Stand 1.10.49 ---
RUHRPOTTGURU PLACES BACKEND 1.10.49 – BILDZENTRIERTE RECHTEPRÜFUNG
====================================================================
- Offizielle Eventbild-Kandidaten werden in RP Redaktion -> Events nicht mehr pro Termin, sondern pro eindeutigem Bild zusammengefasst.
- Gleiche stabilisierte Bild-URLs werden sofort als eine Arbeitsposition dargestellt. Tracking-Parameter wie utm_*, fbclid oder gclid werden für die Identität ignoriert; echte Transformationsparameter bleiben erhalten.
- Ein Hintergrund-Worker bildet zusätzlich SHA-256 über den vollständigen Bildinhalt. Dadurch werden byte-identische Dateien auch bei unterschiedlichen Quell-URLs erkannt.
- Sobald für dieselbe URL unterschiedliche Hashes bekannt sind, wird die URL nicht mehr als alleiniger Dublettenbeweis verwendet. Nur ähnlich aussehende Motive werden niemals automatisch zusammengelegt.
- Eine Freigabe, Ablehnung oder "Unklar"-Entscheidung wird auf alle aktuell identischen Kandidaten übertragen. Freigegebene Dubletten verwenden dasselbe bereits lokal gespeicherte Bild statt erneut herunterzuladen.
- Bereits vor 1.10.49 freigegebene Bilder übernehmen ihren vorhandenen lokalen SHA-256 ohne Netzwerkabruf; neue Kandidaten mit exakt demselben Inhalt können diese Rechteentscheidung automatisch erben.
- Alte abgelehnte Kandidaten werden beim Hash-Backfill ebenfalls berücksichtigt. Der Scan arbeitet in kleinen Batches und versucht nicht erreichbare Quellen in bis zu drei Runden erneut.
- Die Rechteprüfungs-Liste zeigt pro Bild die Anzahl der Verwendungen und den Matching-Typ URL/SHA-256. Ein separater Statusblock zeigt den Fortschritt des Dubletten-Scans.
- Das Schema wurde ausschließlich um source_content_hash + Index erweitert; bestehende Event-, ET4-, Rechte- und lokale Mediendaten bleiben erhalten.

RUHRPOTTGURU PLACES BACKEND 1.10.45 – EVENTBILD-RECHTEWORKFLOW
================================================================
- Offizielle Eventbild-Kandidaten bleiben bis zu einer dokumentierten Rechtefreigabe offline.
- RP Redaktion -> Events zeigt Vorschau, Quelle, Rechteampel und standardisierte Nutzungsgrundlagen.
- Rechteinhaber, Nachweis-URL/Notiz, Credit, Fotograf, Prüfzeitpunkt und Prüfer werden nachvollziehbar gespeichert.
- Kandidaten können freigegeben, als unklar markiert oder dauerhaft verworfen werden.
- Bei vorhandenem Veranstalter-E-Mailkontakt erzeugt "Freigabe anfragen" eine vorbefüllte Mail; Versand erfolgt nicht automatisch.
- Bestehende 1.10.44-Bildpipeline, Fallback-Audit und Eventauslieferung bleiben unverändert.

RUHRPOTTGURU PLACES BACKEND 1.10.41 – MULTI-OWNER LOCAL EVENT MEDIA
=======================================================================
- Fehlerursache aus 1.10.40 geschlossen: destination.one-Zusatzfeed-Bilder konnten bereits als exaktes same-object `et4-feed`-Medium gespeichert sein, wurden aber vom lokalen Cache-Worker nicht verarbeitet und vom defensiven REST-Handoff anschließend entfernt.
- Der lokale Eventbild-Cache verarbeitet jetzt zwei strikt getrennte Source-Owner: aktueller Open-Data-Finder/ET4-Master (`et4-global-id`) und `destination_one_open_data` (`et4-feed`).
- Zuordnung bleibt ausschließlich `global_id -> Bild desselben Quellobjekts`; keine Titel-/Venue-Heuristik, keine Cross-Source-Bildvererbung und keine Websuche.
- Bestehende lokale Master-Bilder in `wp-content/uploads/rpg-et4-event-media/` bleiben unverändert erhalten. Zusatzfeed-Bilder werden automatisch in denselben statischen Same-Origin-Cache lokalisiert.
- Bei identischer `global_id` hat der aktuelle lizenzierte Master Vorrang; der Zusatzfeed darf keinen Master-Datensatz überschreiben.
- REST liefert nur lokal fertige exakte Eventbilder als `media_objects`, `rpg_event_media`, `image` und `imageUrl`; kein ET4-/destination.one-Hotlink und kein PHP/AJAX-Bildproxy im Besucherpfad.
- Admin-Status trennt Master- und destination.one-Zusatzfeed-Bildbestand. REST-Meta trennt `matchedMasterGlobalIds` und `matchedSupplementalGlobalIds`.
- ET4-/destination.one-Original-URL, Lizenz, Fotograf und Credit bleiben als Provenienz in `rg_event_media` gespeichert.

RUHRPOTTGURU PLACES BACKEND 1.10.40 – ET4 LOCAL IMAGE HANDOFF
===============================================================
- Bestehende lokale ET4-Bilder aus wp-content/uploads/rpg-et4-event-media/ werden unverändert weiterverwendet; kein neuer Downloadlauf nötig.
- Besucherbilder werden nach dem finalen Event-Clustering direkt per ET4 global_id/external_id an die bereits lokale et4-global-id-Medienzeile gebunden.
- Eine ältere ET4-Feed-/Enrichment-Medienzeile ohne local_path kann das vorhandene lokale Bild nicht mehr verdrängen.
- REST liefert die statische lokale HTTPS-URL direkt als media_objects, rpg_event_media, image und imageUrl.
- Kein ET4-Hotlink und kein PHP/AJAX-Bildproxy im Besucherpfad.
- ET4-Original-URL, Lizenz, Fotograf und Credit bleiben als Provenienz in rg_event_media gespeichert.
- API-Bilder bleiben bewusst außerhalb der WordPress-Mediathek.

Version 1.2.5 – Activity Intelligence / Visitor Utility
- Reine Häfen, Marinas und Slipways sind kein Aktivitäts-Tipp mehr, sofern kein konkretes öffentliches Verleih-/Tour-/Kursangebot erkennbar ist.
- Kanu-, Ruder-, Segel- und Yachtvereine ohne direkt nutzbares Besucherangebot werden aus dem Aktivitätsfeed entfernt.
- Öffentliche Verleihe/Tourangebote bleiben erhalten und werden als direkt nutzbar markiert.
- Nahe OSM-Dubletten desselben Aktivitätsortes werden zusammengeführt (z. B. Rheinbad Wesel + Rheinbad).
- Hallen-/Freibäder bleiben als lokale Grundversorgung sichtbar; Erlebnis-/Action-Angebote bleiben höher priorisiert.

RUHRPOTTGURU PLACES BACKEND 1.2.4
===============================


EXPERIENCE INTELLIGENCE 1.2.4
-----------------------------
Aktivitaeten werden ausschliesslich im Backend optimiert:
- Lokale Vollstaendigkeit: normale aktive Hallen-/Freibäder bleiben auffindbar und werden nicht wegen eines niedrigeren Experience-Scores entfernt.
- Harte Ausschluesse: leisure=fitness_centre, leisure=pitch, sport=yoga/pilates/gymnastics/soccer.
- Klare Kategorien-Trennung: reine tourism/historic-POIs qualifizieren nicht fuer Aktivitaeten; ein echtes Sport-/Action-Signal darf mit tourism/historic koexistieren. Zoos/Freizeitparks werden im Highlight-Pfad behandelt.
- Wasser-Diversitaet: normales Schwimmbad = lokale Basis, Erlebnisbad/water_park = hoher Boost, Wakeboard/Wasserski/Scuba = Action-Boost.
- Action-Boost: Skateboard/Skateboarding/BMX, Climbing/Bouldering/Climbing-Adventure, Karting.
- Wikidata/Wikipedia ist nur noch ein sekundaerer Vertrauensbonus nach erfolgreicher Aktivitaets-Qualifikation.
- Lifecycle: source_missing/source-inactive sowie disused/abandoned/demolished/razed/removed und eindeutige closed-Signale werden ausgeblendet.
- Home/Discovery erhaelt einen kuratierten Mix aus bestem und naechstem Vertreter je Erlebnisart; Kategorieseiten behalten den vollstaendigen qualifizierten lokalen Katalog.
- Keine React-/Frontend-Komponenten veraendert. Kein Reimport erforderlich; kuenftige Syncs profitieren zusaetzlich von der aktualisierten Klassifizierung.

ZIEL
----
Persistenter Places-Katalog für die vier ortsfesten Kategorien:
- Food
- Highlights
- Kultur
- Aktivitäten

Events bleiben vollständig im vorhandenen ET4-Livepfad.

GEBIET
------
Ausschließlich die 53 Kommunen der Metropole Ruhr:
- 11 kreisfreie Städte
- 42 Städte/Gemeinden in Ennepe-Ruhr-Kreis, Kreis Recklinghausen, Kreis Unna und Kreis Wesel

Die OSM-Abfragen werden kommunenscharf über boundary=administrative + admin_level 6/8 ausgeführt. Es wird kein NRW-Gesamtkatalog in WordPress gespeichert.

KEINE MANUELLE DATENBEFÜLLUNG
-----------------------------
Nach Plugin-Aktivierung:
1. Tabellen werden automatisch angelegt.
2. 53 Kommunen werden automatisch in eine Bootstrap-Queue geschrieben.
3. WP-Cron versucht selbständig, die Kommunen nacheinander zu synchronisieren.
4. Falls das Hosting öffentliche Overpass-Verbindungen blockiert, unterstützt der eingeloggte WP-Admin-Browser denselben Bootstrap automatisch und sequentiell. Nach READY dient derselbe Mechanismus als automatische Catch-up-Hilfe für über 24 Stunden alte Kommunen, ohne manuellen Startknopf.
5. Alle Imports sind idempotent und jederzeit fortsetzbar.
6. Erst 53/53 + Plausibilitätschecks => Status READY => automatische Umschaltung auf Datenbank.

Solange das nicht erfüllt ist, bleibt der v70 OSM-Laufzeitpfad unverändert aktiv.

WARUM KEIN STATISCHER SEED IM ZIP?
---------------------------------
Ein eingebetteter Snapshot wäre bei Installation bereits veraltet und würde die Herkunft des tatsächlichen OSM-Stands verschleiern. 1.1 baut deshalb den aktuellen Katalog selbständig auf. Der Besucher sieht währenddessen weiterhin den bestehenden v70-Pfad.

DATENMODELL
-----------
wp_rg_places
  Canonical Place: stabile interne ID, Name, Koordinaten, Adresse, Kategorie, Lifecycle, Quality.

wp_rg_place_sources
  Rohquellen-Identität (OSM Node/Way/Relation), Version, Timestamp und Raw Tags.

wp_rg_place_categories
  Kategorie/Subkategorie getrennt vom OSM-Rohobjekt.

wp_rg_place_links
  Website / Speisekarte / Reservierung mit Quelle, Confidence, Prüfdatum und Lock.

wp_rg_place_schedules
  Öffnungszeiten mit Schedule-Typ, Quelle, Confidence und Lock.

wp_rg_place_media
  Media-Metadaten / Lizenz / Attribution; keine Bild-Binaries in MySQL.

wp_rg_place_enrichment
  RuhrpottGuru-/offizielle Enrichment-Daten getrennt vom OSM-Source-Layer.

wp_rg_places_municipalities
  Vollständigkeits- und Syncstatus der 53 Kommunen.

wp_rg_places_sync_runs
  Audit-Log der Bootstrap-/Refresh-Läufe.

ENTITY / DEDUPE
---------------
OSM Node/Way/Relation ist nicht die RuhrpottGuru-ID.
Ein Canonical-Key führt mehrere OSM-Repräsentationen desselben physischen Orts zusammen.
Das Frontend erhält rg:place_id und verwendet im DB-Modus stabile IDs wie rg-place-12345.

FOOD IDENTITÄT
--------------
Die vorhandenen engen Wesel-Korrekturen bleiben erhalten:
- San Marino / Berliner Stube: user-bestätigte lokale Ausschlüsse
- Mezzomar am bekannten Wesel-Standort -> Café-Bistro Oliv
Keine generischen fuzzy Ausschlüsse.

ENRICHMENT
----------
OSM bleibt Source Layer. Offizielle/identitätsgeprüfte Daten dürfen ergänzen:
- Website
- Speisekarte
- Reservierung
- Öffnungszeiten

Das vorhandene Food-Info-System wird weiterverwendet. Erfolgreiche Resolver-Antworten werden in Places persistiert. Höherwertige Links erhalten Confidence und können später redaktionell gelockt werden.

SYNC
----
Cron-Tick: alle 15 Minuten.
- Während Bootstrap: nächste noch nicht vollständige Kommune.
- Nach READY: nur Kommunen mit mindestens 24 Stunden altem Datenstand werden aktualisiert; dadurch verteilt sich die Aktualisierung kontrolliert über die Region.
- Fehler ändern keine vorhandenen Besucherdaten.
- Fehlgeschlagene Aktualisierung lässt letzten erfolgreichen Datenstand aktiv. Erst nach einem vollständig erfolgreichen Kommunen-Snapshot werden zuvor bekannte, aber nicht mehr gesehene OSM-Orte als source_missing ausgeblendet.

VALIDIERUNG VOR UMSCHALTUNG
---------------------------
Harte Gates:
- 53 / 53 Kommunen vollständig
- >= 10.000 aktive Canonical Places
- Food >= 3.000
- Highlights >= 300
- Kultur >= 300
- Aktivitäten >= 300
- Wesel Food im 1-km-Kontrollradius >= 50 (bewusste Teilimport-Sicherung; bisheriger OSM-Livebestand lag deutlich darüber)

Diese Schwellen sind absichtlich konservative Plausibilitäts-Minima, keine Soll-Trefferzahlen.

FRONTEND-KOMPATIBILITÄT
-----------------------
Der Theme-Adapter nutzt das bestehende Frontend weiter:
- Home: max. 20 DB-Orte pro Kategorie
- Kategorie-Seite: bestehendes OSM-kompatibles Antwortformat, intern aus MySQL
- Radius 1 / 2,5 / 5 / 10 / 25 / 50 km bleibt möglich
- bestehende Detailkarten, Öffnungsstatus, Website/Menu/Reservierung, Favoriten und Filter bleiben erhalten
- Events bleiben ET4

ROLLBACK
--------
Festgelegter Rollback-Punkt:
RuhrpottGuru-v70-browser-overpass-no-server-curl.zip
SHA256:
206358974c8494c1928360f3c2bb88007a76556ec15273213f1869f30425f8d8

Vollständiger Runtime-Rollback:
1. v70 Theme installieren/aktivieren.
2. Places-Plugin deaktivieren, wenn auch Hintergrund-Sync vollständig gestoppt werden soll.

Die Places-Tabellen bleiben dabei unangetastet und können später wiederverwendet werden.

KOSTEN
------
Keine kostenpflichtigen APIs oder SaaS-Abhängigkeiten werden eingeführt.
Laufende externe Komponenten:
- OpenStreetMap / öffentliche Overpass-Instanzen nur für Hintergrundimport
- vorhandenes ET4 nur für Events
- bestehende freie Quellen/Resolver

Zusätzliche Kosten können daher nur aus dem bereits vorhandenen Hosting, Speicher und Traffic entstehen.

OSM / ODbL
----------
Attribution: © OpenStreetMap contributors · ODbL 1.0
OSM-basierter Derived Layer und unabhängiges RuhrpottGuru-Enrichment werden getrennt gehalten.
Nach READY erzeugt das Plugin einen komprimierten NDJSON-Export des OSM-basierten Layers im WordPress-Uploads-Verzeichnis.
REST-Metadaten: /wp-json/ruhrpottguru/v1/places/export

WICHTIG
-------
Bei einem Update von 1.1.4 auf 1.1.6 bleiben Tabellen, READY-Status und der vorhandene Places-Katalog vollständig erhalten; es ist kein Reimport nötig. Bei einer Neuinstallation gilt weiterhin: Erst nach vollständig erfolgreichem 53-Kommunen-Bootstrap und den Plausibilitätschecks wird automatisch auf den DB-Modus umgeschaltet.


1.1.2
- Fix: Overpass bootstrap query now contains real line breaks instead of literal \n sequences.
- Admin retry badge now includes the concrete transport/parser error.
- Existing Places tables and import state are preserved during the update.


1.1.3
- Admin: vollständige 53-Kommunen-Übersicht mit Status, Roh-/Importzahl, Versuchen, letztem Sync und aktivem Fehler.
- Erfolgreich nachgeholte Bootstrap-Fehler werden nicht mehr als aktueller Fehler angezeigt.
- Klarstellung im Admin: Im READY-Modus lesen Food, Highlights, Kultur und Aktivitäten aus MySQL; Overpass bleibt Hintergrund-Sync.
- Kein Schemawechsel; bestehende 20k+ Places und Importstatus bleiben erhalten.


1.1.4
- Fix: broad Kultur/Highlights/Aktivitäten requests no longer collapse to one subcategory.
- Fix: generic mixed discovery queries return a distance-sorted union from all four DB categories.
- Runtime adapter version bumped for deterministic DB cutover.


1.1.6
- Fix: Aktivitäten-Schnellfilter „Wasser / See“ wird im DB-Adapter eindeutig als activities erkannt und anhand der gespeicherten OSM-Roh-Tags gefiltert; kein versehentliches Highlights-Fallback mehr.
- Admin: Backend-Version wird dynamisch angezeigt.
- Admin: DB-Runtime-Selbsttest für READY/53-53, Runtime-Transport, Query-Matrix, DB-Elementformat und Wasser/See-Referenzfilter.
- Frontend: eventuell vorhandene v73-Session-Caches werden beim ersten Seitenaufruf unter 1.1.6 automatisch verworfen; kein Theme-Update nötig.
- Kein Schemawechsel und kein Reimport erforderlich.


1.1.6
- /places/home accepts up to 1500 results for canonical category pages; magazine Home remains at its requested limit 20.
- One-time frontend cache reset removes stale empty OSM/category snapshots from the v75/v76 transition.
- Theme v77 can use the direct DB path for broad Food category queries.


Version 1.1.7 – Activities Experience Intelligence
- Standard-Fitnessstudios, Yoga/Pilates/EMS/Personal Training und generische Sporthallen/-plaetze werden bei Besucherabfragen ausgefiltert.
- Erlebnis-Spots werden anhand gespeicherter OSM-Tags, Datenqualitaet und erst danach Entfernung gerankt.
- /places/home fuer activities kann geeignete Aussicht/Outdoor-Hangouts aus dem bestehenden Highlights-Bestand in die Empfehlungsauswahl aufnehmen, ohne DB-Kategorien umzuschreiben.
- Antwort liefert rg:activity_score, rg:activity_bucket und rg:experience_tags fuer die Home-Darstellung.
- Kein Reimport und keine Schema-Migration erforderlich.


Version 1.1.8 – Highlights Experience Intelligence
---------------------------------------------------
- Highlights werden als „Orte, für die sich ein Umweg lohnt“ bewertet, nicht nach reiner Entfernung.
- Hard Exclusions für generische Gedenksteine, gewöhnliche Parks/Museen und schwache POI-Signale.
- Experience-Profile: Industriekultur, Landmarke/Fotospot, Urban Art, Aussicht/Sunset, Heritage und besondere Naturorte.
- Score verbindet Erlebniswert, Visualität, Ruhrgebietsidentität, Nutzbarkeit, Budget und nur schwach die Distanz.
- Kultur-/Aktiv-Orte dürfen nur mit eigenständiger Highlight-Evidenz in den Highlight-Pool.
- Kein Reimport und keine Schemaänderung.


1.2.3
- Activities: lokale Vollstaendigkeit + Experience-Mix statt reinem Best-of.
- Normale aktive Hallen-/Freibäder bleiben auffindbar; Erlebnisbad/Wake/Scuba/Action-Sport werden priorisiert.
- Geschlossene/verlassene/entfernte Lifecycle-Signale werden strenger ausgeblendet.
- Zoos/Freizeitparks werden aus Activities herausgehalten und ueber Highlight Intelligence bedient.
- Kein Schemawechsel und kein Reimport erforderlich.


V1.2.5 – Büdchen/Trinkhallen Candidate Import
- OSM shop=kiosk and shop=convenience added to all 53 municipality sync queries.
- Separate primary category "buedchen"; it does not leak into Food/Highlights/Kultur/Aktiv.
- Confidence tiers A/B/C persisted in raw_tags.
- One safe refresh wave is triggered after update; the existing complete catalog stays live.
- WordPress admin gets a Büdchen inventory overview by municipality.
- Public /places/buedchen endpoint exposes active Tier A/B candidates only.
- Tier C remains inventory-only for later verification.


V1.2.6 – Retry / Large-City Reliability Fix
- Essen/Dortmund and other large municipalities no longer use one giant Overpass union.
- Municipality query is split into 3 smaller data blocks and merged/deduplicated before import.
- A failed refresh gets a cooldown (5/15/30/60 min) and yields to other municipalities.
- Failed bootstrap rows also honor locked_until; no immediate retry loop.
- Refresh scheduling prioritizes the lowest current attempt count so one hard city cannot starve the queue.
- Historical attempt counters are reset once on upgrade; admin attempts now reflect the 1.2.6 retry epoch.
- Existing live places remain untouched on failed refreshes.


V1.2.6.1 – Cooldown Queue Resume Fix
- A cooldown is no longer interpreted as "import finished".
- /bootstrap/next returns waiting=true + retryAfter + nextRetry while work exists but is locked.
- Browser importer stays active and polls again automatically (max once/minute).
- bootstrap.js is enqueued even when all remaining work is currently cooling down.
- Two consecutive Overpass failures no longer terminate the importer permanently.
- Admin page explicitly shows the next municipality waiting for retry.


V1.2.7 – Editorial Overrides & Büdchen Review
- New 1:1 table wp_rg_place_overrides with sparse JSON overrides; imported Places remain untouched.
- New wp_rg_place_override_submissions table prepares later community/operator submissions.
- Final REST records merge approved overrides over imported OSM values.
- Every override stores source type/label and verification timestamp.
- Büdchen review workflow in WP admin: Confirmed / Not a Büdchen / Automatic.
- Manual verified candidates may later appear even if Tier C; rejected candidates are excluded from /places/buedchen.
- Admin editor supports name, address, phone, website, opening hours, Instagram, description and Büdchen features.
- No public operator/community form yet. Submission storage is only prepared.


V1.2.8 – Radar Community Intake
- Public POST /wp-json/ruhrpottguru/v1/places/buedchen/submit.
- Strict field whitelist, honeypot and short-lived rate limiting; no visitor IP is written to the database.
- Suggestions remain pending and never alter live data automatically.
- WordPress moderation inbox can approve/reject submissions.
- Approved suggestions are merged into the existing sparse editorial override layer.


V1.2.9 – Büdchen Media + Map Tile Proxy
- Existing verified place_media is exposed to Büdchen REST records.
- OSM image/wikimedia tags continue to work as before.
- Adds a same-origin cached OSM raster tile proxy for the radar map.
- Visitors' browsers do not contact the OSM tile host directly.


V1.3.0 – Reliable Map Tile Transport
- Adds a real binary admin-ajax tile endpoint for PNG map tiles.
- Fixes the REST binary encoding issue that prevented raster tiles from displaying.
- Tiles remain same-origin and locally cached; browser does not contact tile.openstreetmap.org.


V1.3.1 – Büdchen Media Enrichment
- Adds an admin-side batch enrichment for Büdchen/Trinkhallen images.
- Reuses the active theme's identity-safe place media resolver internally.
- Persists accepted image results into rg_place_media.
- High-confidence identity matches (>= 0.88) become primary place images.
- Lower-confidence contextual imagery is stored but not silently published as primary.
- No visitor-time image crawling is required once a place has a stored primary image.
- Event artwork (including generic "Tag der Trinkhallen" artwork) is intentionally not auto-promoted to a place photo unless a future dedicated venue-image validation proves the image belongs to that exact place.


1.3.2:
- Büdchen-Hinweise können mehrere optionale Felder in einer einzigen Einreichung enthalten.
- Admin prüft/übernimmt diese Angaben gemeinsam.
- Legacy-Einzelfeld-Requests bleiben kompatibel.


1.3.3 – „Hier geht’s um die Wurst“ (nur Backend)
--------------------------------------------------
Separates Spezial-Inventar für den klassischen Ruhrpott-Imbiss.

- Keine Umklassifizierung bestehender Food-Orte.
- Automatische Analyse des vorhandenen MySQL-/OSM-Bestands.
- Neue OSM-Syncs halten die Einordnung aktuell.
- Harte Ausschlüsse: Restaurants, Döner/Kebab, Pizza/Pizzeria, Asia/Sushi,
  offensichtliche Burger-Konzepte und Systemgastronomie/Ketten.
- Positive Evidenz: fast_food/takeaway, currywurst/bratwurst/sausage sowie
  Imbiss/Wurst/Curry/Pommes/Fritten/Schnitzel/Frikadelle/Schaschlik.
- Tier A = starke Kandidaten; B/C = Review; X = ausgeschlossen.
- Admin-Review: bestätigt / kein Wurst-Imbiss / Automatik.
- Offizielle Websites/Speisekarten können serverseitig auf typische Gerichte
  geprüft werden. Es gibt keine Browser-Drittanbieter-Abfrage.
- Fehlende Angaben bleiben unbekannt.
- Frontend und Kartenentscheidung sind nicht Bestandteil dieses Builds.


1.3.4 – Website-Prüfung Hotfix
-------------------------------
Behebt eine Endlosschleife der Wurst-Website-Prüfung:
- Nicht erreichbare/fehlerhafte Websites wurden in 1.3.3 als "failed" gespeichert,
  aber sofort wieder in den nächsten Batch aufgenommen.
- Dadurch konnte "verbleibend" dauerhaft unverändert bleiben.
- 1.3.4 verarbeitet im automatischen Lauf nur noch wirklich offene ("pending")
  Websites. Fehlerhafte Websites bleiben transparent als "nicht erreichbar"
  gespeichert und blockieren den Abschluss nicht mehr.
- Admin zeigt zusätzlich offene und nicht erreichbare Websites.
- Bereits erfolgreich geprüfte Daten und Menü-Evidenz bleiben erhalten.


1.3.5 – Wurst-Kandidaten-Audit
-------------------------------
- Admin-Audit für A/B/C/X statt einer unfilterbaren Kandidatenliste.
- Filter: Tier, Stadt, Gericht, Review-Status, Website-Status und Freitext.
- Zeigt je Kandidat positive Signale, harte Ausschlussgründe, erkannte Gerichte,
  OSM-cuisine, Website-Status und offizielle Website.
- Manuelle Bestätigung / Ablehnung bleibt direkt in der Tabelle möglich.
- "Website offen" zählt jetzt nur noch tatsächlich prüfbare pending Websites:
  Website vorhanden + nicht Tier X.
- Keine Frontend- oder Kartenänderung.


1.3.6 – Tier-Filter Hotfix
---------------------------
- Behebt den Admin-Filter für Tier A/B/C/X.
- Ursache: WordPress sanitize_key() wandelt A/B/C/X in Kleinbuchstaben um.
  Dadurch wurde die Auswahl nach dem Reload als ungültig erkannt und auf "Alle"
  zurückgesetzt.
- Stadt- und alle übrigen Audit-Filter bleiben unverändert.


1.3.7 – Identitätslogik statt Gericht=Konzept
----------------------------------------------
- Trennt Betriebsidentität und erkannte Gerichte.
- fast_food allein erzeugt keinen Wurst-Kandidaten mehr.
- Reine Currywurst-/Wurstbuden können weiterhin ohne Schnitzel/Frikadelle Tier A werden.
- Namen wie Imbiss, Metzgergrill, Grillimbiss, Grillhaus bleiben als klassische
  Identitätssignale erhalten.
- Fremdbegriffe im eindeutigen Namen/Brand (Döner, Pizzeria, Sushi, Burger usw.)
  bleiben harte Ausschlüsse.
- Fremde cuisine-Tags bei neutralem Namen werden als Konflikt behandelt und
  landen im Review-Pool statt automatisch in X.
- Pizza/Döner/Sushi irgendwo auf einer offiziellen Speisekarte ist kein
  automatischer Ausschluss mehr. Website-Scan sammelt Gerichte, nicht Identität.
- Bestehende Kandidaten werden nach Upgrade vollständig neu klassifiziert.
- Keine Frontend- oder Kartenänderung.


1.3.8 – Garantierte Neuklassifizierung
- Eigener sichtbarer Reclass-Lauf für identity-v2-1.3.8.
- Iteriert den gesamten relevanten aktiven Food-Bestand unabhängig vom alten Backfill-Status.
- Zeigt Fortschritt, Änderungen und ob die aktuelle Klassifikation vollständig angewendet ist.
- Fortsetzbar nach Seitenwechsel/Unterbrechung.
- Website-/Menü-Evidenz und manuelle Reviews bleiben erhalten.


1.3.9 – X-False-Negative-Fix / Identitätslogik v3
--------------------------------------------------
- Tier X nur noch bei positiv belegtem Ausschluss/Fremdkonzept.
- Fehlendes Wurstsignal allein ist KEIN harter Ausschluss mehr.
- Erweitert Namensidentität um Ruhr-Imbiss-Komposita:
  Grillhütte, Brückengrill/…grill, Brutzelhütte, Imbissbude, Imbissstube,
  Imbisseck, Wurstgrill, Currybude, Pommesbude/-stube, Grillstation u.a.
- Hütte/Bude/Stube wird zusammen mit regional/german oder fast_food als
  prüfenswertes Identitätssignal behandelt.
- Explizite Fremdidentitäten (Kebap/Döner, Asia/Sushi, Pizzeria, Burger,
  Systemgastronomie etc.) bleiben X und überstimmen schwache Grill-Signale.
- Neue Klassifikation identity-v3-1.3.9; eigener Reclass-State erzwingt einen
  frischen vollständigen Lauf nach Installation.
- Keine Frontend-/Kartenänderung.


1.4.0 – Tier-B-Evidenzpipeline
- Normalisiert Namensscores: synonyme Signale werden nicht mehr mehrfach voll addiert.
- A bleibt evidenzbasiert; hohe Scorezahl allein veröffentlicht keinen unbestätigten Imbiss.
- Verbesserte Erkennung typischer Gerichte auf offiziellen Websites/Speisekarten.
- Neuer gezielter Tier-B-Lauf in kleinen Paketen; Fortschritt und A/X-Übergänge sichtbar.
- Bestehende Website-/Menü-Evidenz bleibt erhalten; Reset setzt nur den Prüfcursor zurück.
- Neue Klassifikation identity-v4-1.4.0 erzwingt vollständige Neuberechnung.
- Kein Frontend-/Kartenumbau.


1.4.1 – Quellenneutrale Wurst-Klassifikation
---------------------------------------------
- Website vorhanden / nicht vorhanden: 0 Punkte, kein Tier-Einfluss.
- Öffnungszeiten vorhanden / nicht vorhanden: 0 Punkte, kein Tier-Einfluss.
- Websites/Speisekarten bleiben ausschließlich optionale Quellen, um konkrete Gerichte
  zu erkennen. Nicht erreichbare oder fehlende Quellen sind niemals ein negatives Signal.
- Tier A wird semantisch vergeben:
  * explizite Wurst-/Curry-/Bratwurst-Identität ohne Ausschlusskonflikt, oder
  * klassische Imbiss-Identität plus bestätigtes Wurstangebot.
- Tier B bleibt für plausible klassische Imbisse mit noch unvollständigem Angebot.
- Tier X bleibt ausschließlich positiv belegten Fremd-/Restaurant-/Kettenkonzepten vorbehalten.
- Score dient nur noch als Diagnose/Rangsignal innerhalb der semantischen Entscheidung,
  nicht als Ersatz für Identität.
- Neue Klassifikation: identity-v5-1.4.1; kompletter Reclass wird erzwungen.


1.4.2 – U = Unklar / Kandidatenbereinigung
- Neuer Tier U für Datensätze mit zu wenig positiver Identitäts-/Angebotsevidenz.
- Fehlende Information ist weder B noch X.
- B bleibt konkreten plausiblen klassischen Imbissen bzw. vorhandener typischer Angebots-Evidenz vorbehalten.
- X bleibt ausschließlich positiv belegten Ausschlüssen vorbehalten.
- Neuer Tier-A-Freigabe-Audit-Schnellzugriff.
- identity-v6-1.4.2 erzwingt vollständige Neuklassifizierung.


1.4.3 – Scharfer B-Pool + protokollierter A-Audit
--------------------------------------------------
- Tier B erfordert zwingend einen klassischen Namensindikator:
  grill, hütte/huette, bude, fritten, metzger, brutzel, pommes, schmiede oder imbiss.
- Gerichte oder generische fast_food/takeaway-Tags ohne solchen Namen landen nicht in B.
- U bleibt der Standard-Fallback für unklare Datensätze.
- X bleibt ausschließlich positiv belegten Ausschlüssen vorbehalten.
- Neues persistentes Feld audit_reason.
- Tier A protokolliert einen kompakten Freigabegrund aus Identität und ggf. Wurstangebot.
- Die Audit-Ansicht zeigt diesen Grund prominent unter „Warum?“.
- Neue Klassifikation identity-v7-1.4.3 erzwingt vollständige Neuklassifizierung.


1.4.4 – Admin-Anzeige Hotfix
-----------------------------
- Behebt den kritischen Fehler direkt unterhalb der Wurst-Klassifikation.
- Ursache: Der neue Tier-A-Freigabe-Audit verwendete im Admin-Renderer eine lokale
  $wpdb-Variable, die dort nicht initialisiert war.
- Der A-Zähler verwendet jetzt die bereits geladenen Wurst-Statistiken.
- Klassifikationslogik identity-v7-1.4.3 bleibt unverändert.
- Keine erneute Neuklassifizierung erforderlich; die bereits berechneten A/B/U/X-Werte bleiben bestehen.


1.4.5 – Finaler Qualitätsstand vor Frontend
--------------------------------------------
- identity-v8-1.4.5: fachlich bestätigte A/B/U/X-Logik bleibt unverändert.
- Score nur diagnostisch, auf 95 gedeckelt; Tier-Entscheidung hängt nicht von dieser Deckelung ab.
- Tier-B-Qualitätsblock: B gesamt sowie mit/ohne erkannte Angebots-Evidenz.
- Frontend-Freigaberegel festgeschrieben: Tier A + manuell bestätigt sichtbar; B/U/X unsichtbar.
- Website vorhanden/nicht vorhanden bleibt tier-neutral.


1.4.6 – Statistik-Anzeige Hotfix
---------------------------------
- Behebt ausschließlich die falschen A/B-Zähler in den neuen Qualitätsboxen.
- Ursache: v1.4.5 verwendete tier_a/tier_b statt der vorhandenen Stats-Keys a/b.
- B mit/ohne Angebots-Evidenz wird nun jeweils direkt aus dem Kandidatenbestand gezählt.
- Klassifikation identity-v8-1.4.5 und sämtliche Kandidatendaten bleiben unverändert.
- Keine erneute Neuklassifizierung erforderlich.


1.4.7 – Finale Admin-Zählerkorrektur
-------------------------------------
- A-Freigabe-Audit liest die Anzahl Tier A direkt aus rg_wurst_candidates.
- Tier-B-Qualitätscheck liest B gesamt direkt aus rg_wurst_candidates.
- Tier-B-Anreicherung zeigt beim Laden ebenfalls den aktuellen B-Bestand statt eines alten Laufstatus-Zählers.
- Klassifikation identity-v8-1.4.5 bleibt unverändert.
- Keine Neuklassifizierung erforderlich.


1.4.8 – Audit-Komfort & Mehrfachauswahl
----------------------------------------
- Einzelne Review-Aktionen springen nach dem Speichern zurück zum Kandidaten-Audit.
- Auch das Filtern zielt direkt auf den Audit-Bereich.
- Checkbox pro sichtbarem Kandidaten und „Alle sichtbaren auswählen“.
- Bulk-Aktionen: bestätigen, als Kein Wurst-Imbiss markieren, auf Automatik zurücksetzen.
- Bulk-Speicherung erfolgt per WordPress Admin-AJAX; danach bleibt die Ansicht am Audit.
- Manuelle Entscheidung bleibt stärker als die Automatik.
- Klassifikation identity-v8-1.4.5 bleibt unverändert; keine Neuklassifizierung erforderlich.


1.4.9 – Öffentliche Imbissbuden-Schnittstelle
---------------------------------------------
- Neuer öffentlicher Endpoint /places/wurst, strikt Standort + Radius.
- Sichtbar sind nur public_eligible=1 und nicht manuell abgelehnte Datensätze.
- Tier A und manuelle Bestätigung bleiben die einzigen automatischen Freigabewege.
- Menü-Evidenz wird als positive Filterinformation ausgeliefert; fehlende Evidenz bleibt unbekannt.
- Neuer Community-Endpunkt /places/wurst/submit für fehlende Stammbuden; Einsendungen landen ungeprüft in der Moderationswarteschlange.
- Klassifikation identity-v8-1.4.5 bleibt unverändert.


1.5.3: Kategoriebezogene Community-Daten/Bild-Einsendungen, Pflicht-Copyright pro Bild, Rechtebestätigung, Einzelbild-Moderation und Gallery-Ausgabe.


Version 1.5.4 – Medienverwaltung / Löschanfragen
- Neue Admin-Verwaltung für bereits freigegebene Bilder aus place_media.
- Suche nach Copyright/Urheber, Credit, Location, Stadt, Adresse, Quelle, Bild-ID und Location-ID.
- Filter nach Quelle und Status (aktiv/deaktiviert).
- Bilder können deaktiviert, reaktiviert oder endgültig aus place_media gelöscht werden.
- Deaktivierte Bilder werden nicht mehr an das Frontend/Slider ausgeliefert.
- Wenn ein Hauptbild deaktiviert/gelöscht wird, wird automatisch das beste verbleibende aktive Bild zum Hauptbild.
- Für Deaktivieren/Löschen ist ein Grund Pflicht; optional kann „Anfrage von“ dokumentiert werden.
- Jede Maßnahme wird in place_media_removal_log mit Snapshot des Bilddatensatzes protokolliert.
- Bei lokalen Community-Uploads wird die lokale Datei beim endgültigen Löschen ebenfalls entfernt.
- Externe Quelldateien werden selbstverständlich nicht auf der Fremdquelle gelöscht; nur die RuhrpottGuru-Zuordnung wird entfernt.
- Der Places-Katalog wird nach Medienmaßnahmen versioniert, damit Änderungen im Frontend nicht an alten Katalog-Caches hängen.


Version 1.7.4 – Masterimport mit lokalen konkreten Wiederholungsterminen
---------------------------------------------------------------------
- ET4-Unroll-Ergebnisse werden als 365-Tage-Trefferfilter verwendet, nicht mehr als Canonical-Terminquelle.
- Wiederkehrende Eventserien werden anhand der ET2014A-Intervalle lokal in konkrete Termine aufgelöst.
- Laufweite Quellobjekt-Deduplizierung verhindert tausende unnötige Wiederholungs-Upserts.
- Neue getrennte Audit-Zähler für Trefferzeilen, Quellobjekte, erzeugte Termine und gespeicherte Termine.
- Die vorherigen 282/3979-Auditwerte werden nicht weiter als belastbare Termin-/Dublettenwerte dargestellt.

1.9.6 – Kultur-Reparatur
- SQL-seitigen Stolperstein-Filter aus der öffentlichen Kultur-Abfrage entfernt.
- Stolpersteine werden erst nach erfolgreicher Datenbankabfrage in PHP ausgefiltert.
- Größerer interner Kandidatenpool für Kultur, damit echte Kulturorte trotz vieler Stolpersteine im Nahbereich sichtbar bleiben.
- Keine Änderung an Food, Highlights, Aktiv, Events oder den gespeicherten Stolperstein-Datensätzen.

1.9.7 – Globale Teilen-Deep-Links
---------------------------------
- Exakte öffentliche Share-Auflösung für Events und gespeicherte Places über stabile interne IDs.
- Neuer öffentlicher Endpoint /ruhrpottguru/v1/share-item für direktes Öffnen unabhängig von GPS und Radius.
- Share-Landing unter /teilen/<typ>/<id>/<slug>/ liefert eigene Open-Graph-/Twitter-Metadaten mit Titel, Beschreibung und Bild.
- Unterstützt Events, Food, Highlights, Kultur, Aktiv, Imbissbuden sowie Route-der-Industriekultur-Fallbacks.
- Die Kultur-Reparatur aus 1.9.6 bleibt unverändert erhalten.


RPG Events 1.10.51 – Coverage-Pilot Wesel
- Read-only Coverage-Audit gegen weseltickets.de mit persistenter Kandidaten-Tabelle.
- Automatische Deduplizierung/Klassifikation in vorhanden, ergänzbar, fehlt und Konflikt.
- Coverage-Worker respektiert das zentrale Schwerlast-Gate aus 1.10.50 und läuft automatisch täglich; bei Blockern wartet er.
- Noch keine automatische Veröffentlichung lokaler Kandidaten; dieser Build validiert zuerst den vollständigen Coverage-Pfad.

=== v1.10.55 Worker-Watchdog + Rechte-Gate ===
- Repariert festhaengende/ueberfaellige destination.one-Supplemental- und SHA-256-Worker nach harten HTTP/PHP-Abbruechen.
- Eventbild-Fallback pausiert waehrend Supplemental statt parallel zu arbeiten.
- Lokale Event-Coverage ist bis zur dokumentierten Nutzungsfreigabe technisch deaktiviert; Wesel Tourismus/WeselMarketing ist als vorgesehene Hauptquelle hinterlegt. ET4/Open Data Finder bleibt unveraendert aktiv.
- Worker-Panel zeigt zusaetzlichen Fortschritt statt nur generischem running.

=== v1.10.56 ET4-Fokus bis Rechteklärung ===
- Automatische Bildrecherche außerhalb des bestehenden ET4/ODF-/destination.one-Pfads ist technisch pausiert.
- Eventbild-Fallback, Event-Ort/Pexels-Upgrade und SHA-256-Rechte-Dublettenlauf starten nicht mehr neu und werden als paused_scope angezeigt.
- Event-Enrichment verarbeitet weiterhin strukturierte Eventdaten, erzeugt aber bis zur Rechteklärung keine neuen Bildkandidaten aus externen offiziellen Webseiten.
- Bestehende bereits gespeicherte Medien werden nicht gelöscht oder umklassifiziert.
- ET4 Masterimport, destination.one-Zusatzfeed, ET4-Bildkatalog und ET4-Bild-Lokalisierung bleiben unverändert aktiv.


=== v1.10.57 ET4 Event Final Pass ===
- Direct/share event payload uses the same exact global_id -> local ET4 image mapping as the normal event REST response.
- destination.one/eT4 provider URLs stay internal provenance; only validated public websites reach visitors.
- Event payload exposes a canonical RuhrpottGuru share URL for share/calendar actions.
- No local tourism/marketing source is activated.

=== v1.10.58 ET4 Eventdaten-Vollständigkeit ===
Vorhandene ET4-Felder werden vollständiger aus dem rich payload übernommen: Absätze/Listen in Beschreibungen, Preisfelder, strukturierte Mitwirkende, Venue-Adresse sowie ET2014A media_objects rel=booking/homepage. Providerseiten bleiben öffentlich blockiert. Ein lokaler One-shot-Backfill aktualisiert bereits gespeicherte ET4 raw_payloads ohne externe Requests und wartet bei laufendem Event-Sync/Enrichment.

=== v1.10.73 Öffnungszeiten Unknown-Delta 1.10.33 ===
- Nach Abschluss des laufenden 1.10.32-Salvage startet serverseitig ein einmaliger Delta-Pass auf historische bestätigte Unknowns mit vorhandenem offiziellem URL-Ziel.
- Die alte Recherchekette (Live-OSM, Wikidata, Resolver, öffentliche Discovery) wird dabei bewusst nicht wiederholt.
- Geprüft werden nur die neu hinzugekommenen offiziellen Root-/www-/Scheme- und typischen Öffnungszeiten-/Besuchspfade.
- Treffer werden normal dauerhaft gespeichert; Nichttreffer bleiben bestätigt unbekannt.
- Direkt zuvor durch 1.10.31/1.10.32 abgeschlossene Final-Salvage-Unknowns werden nicht sofort erneut bearbeitet.
- Läuft im vorhandenen Background-Worker weiter; ein offener Browser-Tab ist nicht erforderlich.

1.10.103 Selm Listing Parser Fix
- Selm detail routes are now detected independent of a specific <a href> shape (absolute/root-relative/relative/form/data/JS URL markup).
- Calendar image paths are excluded from ID discovery.
- No visitor layout or Theme change; Selm remains a normal Event source in the existing Events view.
