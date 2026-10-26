# Bestandsstatus vom 19.10.2026

## Tatsächlich wiedergewonnene Archive

| Komponente | Archivstand | Beleg |
|---|---|---|
| Theme | v345, Komm-rum/Trinkhallen, OFFLINE-GEPRUEFT-INSTALLATION | Originalarchiv geöffnet; 367 Dateien übernommen |
| Places-Backend | v1.10.169, Gemeinsame Detailseiten, LIVE-TEST | Originalarchiv geöffnet; 215 Dateien übernommen |

Die Dateinamen beschreiben historische Lieferungen und sind keine heutige Installationsbestätigung. Dieser Import verändert den Code nicht und führt keine neuen Funktionstests aus. `docs/source-inventory.json` dokumentiert Herkunft und Byte-Identität. Automatische Prüfung auf übliche Token-/Private-Key-Muster sowie Prüfung der gefundenen Verdachtsstellen ergab keine bestätigten Zugangsdaten; das ist keine vollständige Sicherheitsprüfung.

## Live-Abgleich und weitere Projektbestandteile

Die bereitgestellten Projektquellen enthalten Projektvertrag v1.2 (10.10.) und Architekturregister/Masterliste v1.6 (18.10., aus dem SEO-P0-Projektupdate). Sie bleiben verbindliche externe Projektquellen und werden in dieses öffentliche Repository nicht vollständig kopiert. Frühere v1.4-Dokumente sind nicht der neueste bereitgestellte Stand.

Das Register v1.6 berichtet frühere Live-Prüfungen des Backends v1.10.169 sowie spätere Theme-/WPCode-Eingriffe. Insbesondere der SEO-H1-Patch vom 18.10. auf Basis v343 und die aktiven WPCode-Snippets müssen separat mit diesem Archivstand abgeglichen werden. Ein bloß höherer Theme-Dateiname beweist keine vollständige Übereinstimmung.

Noch nicht importiert: aktuelle aktive WPCode-Snippets samt Aktivstatus, weitere eigene Plugins, Hosting-/Cron-Konfiguration und vollständiger heutiger Live-Dateiabgleich. Keine Zugangsdaten oder Datenbankinhalte übernehmen. Dieser Bestand ist deshalb noch keine vollständige reproduzierbare Sicherung des produktiven WordPress-Systems.

## Offener Suchfall Topgolf

Laut Nutzer funktioniert `topgolf`; `topgolf oberhausen` und `top golf oberhausen` funktionieren nicht. Drei Treffer sind gemeldet, ihre Place-IDs und die Ursache der Mehrfachausgabe sind noch zu prüfen. Keine Umbenennung und keine Löschung. Nächster Code-Schritt: tatsächlichen `/global-search`-Handler im importierten Backend und dessen Aufruf im Theme untersuchen; Mehrwort-/Name+Stadt-Verhalten gezielt prüfen. Historische isolierte Tests ersetzen keine Integration. Dieser Archivimport enthält keinen Suchpatch und keine Änderung von Kategorie-, Ranking-, Entfernungs- oder SEO-Regeln.

## GitHub-Übernahme am 19.10.2026

Nach Installation der GitHub-App war der tatsächliche Blob-Schreibtest erfolgreich. Dieser Commit übernimmt den unveränderten Archivbestand samt Ablageregeln und Dateiinventar. Anschließend werden der gespeicherte Git-Tree und der Wiederabruf gegen den lokalen Bestand geprüft. WordPress wird dadurch nicht verändert.


## Domainwechsel-SEO Entwicklungszweig 26.10.2026 – nicht installieren

Branch `pottfuchs-seo-fix` enthält eine gezielte, noch nicht freigegebene Änderung am archivierten Places-Backend v1.10.169: Ortsnamen plus Stadt nur im HTML-SEO-Titel; WordPress-Sitenamen in bestehenden öffentlich sichtbaren Detail-, Share- und Stadt-Metadaten; kein Eingriff in URL, H1, Canonical, JSON-LD-Entity-Namen, Kategorien oder Ranking. Das vorhandene SVG-Favicon bleibt unverändert.

**Beispiel:** Bei WordPress-Sitenamen `PottFuchs` wird aus dem Datensatzname `Zum Schwarzen` in `Essen` der Dokumenttitel `Zum Schwarzen Essen | PottFuchs`.

**Keine Produktionsfreigabe:** Archivstand ist älter als der im Audit vom 23.10. dokumentierte Live-Stand (Theme v350, Places v1.10.181). Das aktuelle Places-Original und aktive WPCode-Snippets #3343/#4102 sind hier noch nicht vollständig archiviert; keine sichere Live-Installations-ZIP daraus ableiten. Vollständiger PHP-Lint, isolierte Titel-/Kanonikal-Regressionen und Google-HTML-E2E müssen vor Release nachgewiesen werden. Auch der tatsächliche neue-Domain-Redirect, HTTP/HTTPS, Sitemap/robots und Android-App-Verträge benötigen gesonderte Prüfung. Weder WPVibe noch WordPress-Live wurden für diesen Entwicklungsschritt verändert.
