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


## Bestandsnachtrag 26.10.2026 – verfügbare Dateien und tatsächliche Zugriffsgrenze

Lesende Inventur der Projekt-Dateibibliothek hat folgende **Dateinamen/Metadaten**, aber noch keine extrahierten Originalbytes bestätigt:

- `PottFuchs-Places-Backend-v1.10.184-Importzentrale-Karten-FINAL.zip` (Library; neuer als Repo-Archiv v1.10.169; nicht als live bestätigt)
- `PottFuchs-Theme-v351.0-Markenreste-FINAL-2026-10-24.zip` (Library; neuer als Repo-Archiv v345; nicht als live bestätigt)
- `PottFuchs-WPCode-SEO-Markenfix-v1.0-2026-10-24.zip` (Library; Inhalt nicht geprüft)
- Weitere alte Backend-/Theme-/Domain-Cutover-ZIPs sind in der Library-Liste nachgewiesen, aber noch nicht in GitHub archiviert.

Die Dateiliste und Library-Einträge sind zugänglich. Der Raw-Byte-Materialisierungsaufruf für die drei genannten ZIPs wurde am 26.10. tatsächlich versucht und mit `This Project file does not have an authorized raw-byte materialization path` abgewiesen. Eine Dateilistung ersetzt keinen herunterladbaren Source-Stand; installierbare ZIPs dürfen nicht aus deren Namen rekonstruiert werden. Diese Grenze ist **keine WPVibe-Grenze**.

Der Entwicklungszweig [`pottfuchs-seo-fix`](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/tree/pottfuchs-seo-fix) enthält einen minimalen **nicht zur Liveinstallation freigegebenen** SEO-Titel-/Branding-Patch gegen das älteste GitHub-Archiv v1.10.169. Neun statische Quellcode-Vertragsprüfungen wurden über die GitHub-Rücklesung durchgeführt; ein isolierter PHP-Helfertest mit neun Fällen wurde lokal bestanden; vollständiger PHP-Lint der 1,95-MB-Plugin-Hauptdatei und aktuelle Live-E2E sind nicht nachgewiesen. Das unveränderte Theme-Asset `favicon.svg` ist im Repository gefunden worden. WPCode #3343/#4102 fehlen als geprüfte aktive Originalsnippets weiterhin im GitHub-Quellarchiv.

**Nächster zwingender Schritt für sicheren WordPress-Release:** zugängliche Originalbytes des aktuell beauftragten Theme-/Plugin-Ausgangspakets gewinnen, Version/Hash gegen dokumentierten Installationsstand abgleichen und minimale SEO-Korrektur auf genau diesem Stand mit vollständigem PHP-/Regressionslauf integrieren. Kein Downgrade durch Installation des Archivstands. Keine Live-Admin-Zugriffe beauftragt oder vorgenommen.
