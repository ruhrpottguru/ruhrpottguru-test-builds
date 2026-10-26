# Bestandsstatus vom 19.10.2026

> **Aktualisierung am 26.10.2026 – neue höchste Priorität:** Das zuvor als live gemeldete Places v1.10.185 wurde inzwischen durch **v1.10.188** ersetzt; der Nutzer hat v1.10.188 ausdrücklich als aktiv bestätigt. Vollständige 1.10.188-Originalquelle, Importsteuerungs-Fix (33 funktionale Assertions) und installierbare ZIP liegen nun tatsächlich auf dem GitHub-Zweig [`fix/manual-supplemental-priority`](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/tree/fix/manual-supplemental-priority) und im [Places-Release v1.10.188](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/releases/tag/pottfuchs-places-v1.10.188). **Wichtig: Bei jedem neuen SEO-/Places-Auftrag 1.10.188 als relevante Ausgangsbasis verwenden, nicht auf v185 zurückfallen.**
>
> **P0-SEO-Versionfehler vom 26.10. behoben, aber Live-Abnahme noch ausstehend:** Die frühere Migrations-ZIP **v1.0.0** brach beim Nutzer ab, weil ihr Header-Guard ausschließlich Places v1.10.185/186 akzeptierte – **keine Änderung am WordPress**. Die korrigierte, am **vollständigen originalen v1.10.188-Backendcode** getestete P0-Migration **v1.0.1** liegt im [GitHub-Release](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/releases/tag/pottfuchs-p0-seo-migration-v1.0.1-places188), Quellbranch [`release/pottfuchs-p0-seo-v1.0.1-places188`](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/tree/release/pottfuchs-p0-seo-v1.0.1-places188) und hat PHP-Syntax, echte v188-Owner-Patch- und Rollback-Funktionsprüfungen sowie ZIP-Integrität bestanden ([CI 40368342345](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40368342345)). **Noch keine tatsächliche Installation oder SEO-Live-Abnahme der v1.0.1 gemeldet.** Keine ungefragten Liveeingriffe / kein WPVibe.

> **Live-Stand 26.10.2026, nach ausdrücklicher rein lesender WordPress-Freigabe:** WPVibe `site_info`/Pluginliste bestätigt **Theme v351.0** (Slug `ruhrpott-guru`), **Places-Backend v1.10.185** und **WPCode Lite v2.4.0 aktiv**. Die tatsächlichen WPCode-Posts **#3343** und **#4102** (`post_status=publish`) wurden lesend aus WordPress geprüft. Daraus sind **vier** öffentliche Altmarken-Literale in #3343 (Taxonomie-Fallback, Organization, WebSite, Breadcrumb) und **ein** Event-Titelsuffix in #4102 exakt lokalisiert. Der Place-Teil von #4102 ändert keinen Titel: Für `Zum Schwarzen Essen | PottFuchs` ist der aktuelle Places-Detail-SEO-Owner nötig. `site_icon=0` ist weiterhin direkt bestätigt. **Keine Liveänderungen:** Genehmigung umfasst nur Lesen. Detailnachweis und bewahrte Freigabegrenze: [docs/pottfuchs-operational-status.md](pottfuchs-operational-status.md). Der Export der vollständigen installierten Theme-/Plugin-Dateien ist weiter nicht gelungen; neuere Library-ZIP-Materialisierung meldet nach erneutem Versuch fehlende autorisierte Rohbyte-Pfade. WPVibe-Free-Kontingent derzeit erschöpft; kein kostenpflichtiges Upgrade.


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

## Historischer Suchfall Topgolf – öffentlich reproduziert und am 26.10.2026 nicht mehr vorhanden

Laut Nutzer funktioniert `topgolf`; `topgolf oberhausen` und `top golf oberhausen` funktionieren nicht. Drei Treffer sind gemeldet, ihre Place-IDs und die Ursache der Mehrfachausgabe sind noch zu prüfen. Keine Umbenennung und keine Löschung. Nächster Code-Schritt: tatsächlichen `/global-search`-Handler im importierten Backend und dessen Aufruf im Theme untersuchen; Mehrwort-/Name+Stadt-Verhalten gezielt prüfen. Historische isolierte Tests ersetzen keine Integration. Dieser Archivimport enthält keinen Suchpatch und keine Änderung von Kategorie-, Ranking-, Entfernungs- oder SEO-Regeln.

**Nachweis vom 26.10.2026 – kein neuer Suchpatch:** Die produktive, lesende öffentliche POST-Suche `/wp-json/ruhrpottguru/v1/global-search` ergab für `topgolf`, `topgolf oberhausen` und `top golf oberhausen` auf **beiden Hosts** (pottfuchs.de und alte ruhrpottguru.de) jeweils **HTTP 200 und genau einen identischen Treffer** (ID `1051311896`, Topgolf, Oberhausen). Zusätzlich zeigte der echte mobile Chromium-Browser bei allen drei Anfragen jeweils nur **eine** Karte „Topgolf“ (Oberhausen). Das vorherige Fehlverhalten aus dem historischen Bestand war bei diesen Abfragen am 26.10. nicht mehr reproduzierbar. Kein Eingriff in Suche, Ranking oder Backend. [REST-Prüfung](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40322085271) · [Browser-E2E](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40322506314).

## GitHub-Übernahme am 19.10.2026

Nach Installation der GitHub-App war der tatsächliche Blob-Schreibtest erfolgreich. Dieser Commit übernimmt den unveränderten Archivbestand samt Ablageregeln und Dateiinventar. Anschließend werden der gespeicherte Git-Tree und der Wiederabruf gegen den lokalen Bestand geprüft. WordPress wird dadurch nicht verändert.


## Bestandsnachtrag 26.10.2026 – verfügbare Dateien und tatsächliche Zugriffsgrenze

Lesende Inventur der Projekt-Dateibibliothek hat folgende **Dateinamen/Metadaten**, aber noch keine extrahierten Originalbytes bestätigt:

- `PottFuchs-Places-Backend-v1.10.184-Importzentrale-Karten-FINAL.zip` (Library; neuer als Repo-Archiv v1.10.169; nicht als live bestätigt)
- `PottFuchs-Theme-v351.0-Markenreste-FINAL-2026-10-24.zip` (Library; neuer als Repo-Archiv v345; nicht als live bestätigt)
- `PottFuchs-WPCode-SEO-Markenfix-v1.0-2026-10-24.zip` (Library; Inhalt nicht geprüft)
- Weitere alte Backend-/Theme-/Domain-Cutover-ZIPs sind in der Library-Liste nachgewiesen, aber noch nicht in GitHub archiviert.

Die Dateiliste und Library-Einträge sind zugänglich. Der Raw-Byte-Materialisierungsaufruf für die drei genannten ZIPs wurde am 26.10. tatsächlich versucht und mit `This Project file does not have an authorized raw-byte materialization path` abgewiesen. Eine Dateilistung ersetzt keinen herunterladbaren Source-Stand; installierbare ZIPs dürfen nicht aus deren Namen rekonstruiert werden. Diese Grenze ist **keine WPVibe-Grenze**.

Der Entwicklungszweig [`pottfuchs-seo-fix`](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/tree/pottfuchs-seo-fix) enthält einen minimalen **nicht zur Liveinstallation freigegebenen** SEO-Titel-/Branding-Patch gegen das älteste GitHub-Archiv v1.10.169. Neun statische Quellcode-Vertragsprüfungen wurden über die GitHub-Rücklesung durchgeführt. Am 26.10. wurde ein isolierter PHP-8.4-Helfertest mit zwölf Fällen ausgeführt: 12/12 erfolgreich, Skript-PHP-Lint bestanden. Zwei weitere PHP-Testquellen sind unter dem SEO-Entwicklungszweig archiviert; die source-contract-Testdatei wurde noch nicht gegen einen vollständigen lokalen GitHub-Checkout ausgeführt. Vollständiger PHP-Lint der 1,95-MB-Plugin-Hauptdatei und aktuelle Live-E2E sind nicht nachgewiesen. Das unveränderte Theme-Asset `favicon.svg` ist im Repository gefunden worden. WPCode #3343/#4102 fehlen als geprüfte aktive Originalsnippets weiterhin im GitHub-Quellarchiv.

**Nächster zwingender Schritt für sicheren WordPress-Release:** zugängliche Originalbytes des aktuell beauftragten Theme-/Plugin-Ausgangspakets gewinnen, Version/Hash gegen dokumentierten Installationsstand abgleichen und minimale SEO-Korrektur auf genau diesem Stand mit vollständigem PHP-/Regressionslauf integrieren. Kein Downgrade durch Installation des Archivstands. Keine Live-Admin-Zugriffe beauftragt oder vorgenommen.


### Ergänzende Quellenprüfung 26.10.2026

- GitHub-Branch `backend-search-v1.10.180` enthält tatsächlich installierbare historische Backend-ZIP `RuhrpottGuru-Places-Backend-v1.10.180-Suche-Kueche-Stadt-FINAL.zip` und Prüfprotokolle. `backend-search-v1.10.180/deployment-status.json` nennt SHA-256 `db492a2eada200b5772f959fefdb24289e92a7c96e34d9bb43073c5980c6f0ed`, 174 damalige lokale Tests und **nicht installiert**. Das ist eine zusätzliche **historische** Archivquelle, keine Grundlage für Downgrade der neueren Website.
- Die Dateibibliothek listet weiterhin die neueren v1.10.184-/v351-ZIPs, deren Originalbytes beim Materialisierungsversuch nicht freigegeben waren. Vor einem Produktionsrelease bleibt der Original-Dateiabgleich zwingend.
- Eine read-only Abfrage von Google-Suchtreffern am 26.10. zeigt für eine auf `pottfuchs.de` adressierte Veranstaltungsseite den alten Titelzusatz `| RuhrpottGuru`. Such-Cache/Index ist kein Beweis, welche HTML-Tags der Server zum Prüfzeitpunkt ausliefert. Direkte HTTP-Abfrage lieferte in der verfügbaren Browserumgebung keinen verifizierbaren Response.
- Der verbundene GSC-Wizard-Zugriff verweigerte schon die lesende Property-Liste wegen abgelaufener Test-/Aboperiode. Es wurde weder ein kostenpflichtiger Dienst aktiviert noch Search-Console-Daten erfunden.


## Live-SEO-Domainbefund und Favicon 26.10.2026

Die früher formulierte Einschränkung, dass kein direkter öffentlicher HTML-Response vorliege, wurde durch spätere **GitHub-Actions-GET-Prüfungen** am 26.10. aufgehoben. Beleg: [Live-SEO-HTML und Weiterleitungen](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40324316764), [Live-REST-Site-Icon](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40328351939). Vollständiger [Befundbericht](docs/POTTFUCHS-SEO-DOMAIN-LIVEBEFUND-2026-10-26.md).

- HTML-Hauptseite trägt PottFuchs im Titel und og:site_name; **WebSite- und Organization-JSON-LD tragen noch RuhrpottGuru**.
- Erfolgreiche Place-/Event-/Kategorieseiten tragen teilweise **noch RuhrpottGuru** als HTML-Titel und/oder og:site_name, u. a. `/ort/16936/essen/zum-schwarzen/`. Keine alleinige Google-Cache-Ursache.
- `/favicon.ico` (www) liefert **302** auf WordPress-W. Das richtige, unveränderte SVG wird im HTML-Head mit `rel=icon` eingebunden und antwortet 200; Live- und Repository-Bytes identisch.
- `/wp-json/` auf der neuen Primärdomain meldet **site_icon=0**, **site_icon_url=""**, aber `site_logo=4215`; die WordPress-Admin-Oberfläche zeigt nach Betreiber-Screenshot dagegen das gelbe Icon. Die Einstellung ist also aus Live-Core-Sicht nicht wirksam; den Zustand nicht als „korrekt konfiguriert“ ausgeben. Kein Administratorzugriff erfolgte.
- Ein ICO mit demselben alten Motiv ist ausschließlich als [geprüftes Development-Asset](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/blob/pottfuchs-seo-fix/docs/assets/pottfuchs-bestehendes-favicon.ico) gespeichert; **nicht** auf dem Webserver abgelegt.
- Pfadgleiche 301-Beispiele, TLS-Zertifikate und 9 Sitemaps mit ausschließlich neuem Host wurden öffentlich erfolgreich geprüft. Keine Änderung erforderlich, solange dies so bleibt.
- Der vorhandene Places-SEO-Patch auf Archivquelle v1.10.169 hat einen [bestandenen GitHub-PHP-Lint-/Regressionstest](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40324097670); weiterhin **nicht installierbar auf dem aktuelleren Live-Stand**. Fehlende Originaldateien v351/v1.10.184 und aktuelle WPCode-Bodies bleiben Release-Sperre.

**Keine Live-WordPress-/Hosting-/WPVibe-Änderungen.** Bevor eine neue ZIP als final bezeichnet wird, aktuellen Theme-/Places-/WPCode-Quellstand beschaffen und mit Tests vollständig abgleichen.

## Live-Abnahme Favicon nach Betreiber-Upload, 26.10.2026

**Öffentliches Favicon-Problem /favicon.ico erfolgreich gelöst und geprüft.** Echte Root-ICO im Host `www.pottfuchs.de` antwortet HTTP 200 ohne Redirect, MIME `image/vnd.microsoft.icon`, ICO-Signatur 00 00 01 00, 6 Einträge, 28.849 Bytes und SHA-256 `a367a6ea0b7ba28b4bdf34ebf4f1e82e6ebcb6e10f08619329bcb68e9bada9c5`. Live-Hash entspricht exakt dem archivierten GitHub-Entwicklungsasset. Alte Domainvarianten behalten 301 zu neuer Domain. **Nachweis:** [Read-only GitHub Action 40341937067](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40341937067). Betreiber hat die Datei installiert, der Assistent hat ausschließlich gelesen. Details im [SEO-Livebefund](docs/POTTFUCHS-SEO-DOMAIN-LIVEBEFUND-2026-10-26.md).

**Restzustand getrennt:** WordPress-REST `site_icon=0` weiterhin vorhanden, jedoch jetzt kein /favicon.ico-Fehler mehr. Nicht das funktionierende Favicon nochmals ändern. Alte `RuhrpottGuru`-SEO-Titel und WebSite-/Organization-Strukturdaten bleiben ein eigener Auftrag mit fehlendem aktuellem Snippet-/Plugin-Originalquellstand; kein neues Plugin-Release freigegeben.
