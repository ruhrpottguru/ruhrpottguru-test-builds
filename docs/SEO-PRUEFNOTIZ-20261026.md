# PottFuchs – SEO-Entwicklungsnachweis (26.10.2026)

## Stand und Grenzen
Quelle des Patches: historischer Places-Backend-Archivstand v1.10.169 im GitHub-Repository, separater Branch `pottfuchs-seo-fix`. **Keine Installationsfreigabe und keine Live-Änderung.**

Neuere Versionen im GitHub-Branch `backend-search-v1.10.180` enthalten die Backend-ZIP v1.10.180; deren Deploymentstatus dokumentiert ausdrücklich **nicht installiert**. Neuere Library-Archive sind als Metadaten auffindbar: Backend v1.10.184 und Theme v351.0, wurden aber nicht als Originalbytes bereitgestellt. Deshalb kein ungefährlicher neuer WordPress-Release auf v1.10.169.

## Konkret programmiert
- Vorhandenen Detail-SSR-Owner wiederverwendet: Place-SEO-Titel erhält Stadtbezeichnung ohne Duplikat; Place-Datensatzname, H1, Schema-Name, Canonical und URL bleiben unverändert.
- In vorhandenen Backend-Metadaten und Share-/Stadt-Fallbacks öffentliche Markenangaben aus WordPress-Blogname bezogen.
- Bei der Ausgabe des Head-Titels wurde die notwendige PHP-Closure-Bindung von `$seo_title` ergänzt.
- Das vorhandene `favicon.svg` wurde **nicht** verändert.
- Theme-Archivstand v345 enthält die bestehende Abwehr doppelter WordPress-Core-Titelausgabe (`rpg_v310_prepare_canonical_detail_head`), nicht als heutiger Live-Nachweis missverstehen.

## Tatsächlich ausgeführte Prüfungen
- 12 isolierte Titel-Testfälle mit PHP 8.4: **bestanden** (Beispiel `Zum Schwarzen` + `Essen` → `Zum Schwarzen Essen`).
- `php -l` auf dem isolierten PHP-Testskript: **bestanden**.
- 9 statische Vertragsprüfungen direkt auf dem in GitHub rückgelesenen Patchquelltext: **bestanden**.
- Testquellen: `tests/pottfuchs-seo-title-20261026.php` und `tests/pottfuchs-seo-source-contract.php` liegen im Entwicklungszweig. Der zweite Test erfordert ein vollständiges Checkout; ein tatsächlicher Komplettlauf ist **noch nicht erfolgt**.
- Ganze Plugin-Hauptdatei 1,95 MB: **PHP-Lint nicht nachgewiesen**.
- Live-HTTP 301/SSL/robots/Sitemaps/Google-HTML: **kein direkter Response-/Header-Nachweis**, die öffentliche Browser-Abfrage war nicht zugänglich.
- Lesende Websuche zeigte am 26.10.2026 beispielhaft eine neu gecrawlte PottFuchs-Veranstaltung mit altem Suffix `| RuhrpottGuru`. Suchindex-Repräsentation ist **kein** Beweis des aktuellen HTML-Quellstands.
- WPCode-Snippets #3343/#4102 im derzeit aktiven Produktivstand: **nicht verifiziert**.

## Sichere nächste Schritte
1. Erreichbare Originaldateien der zuletzt produzierten Theme-/Places-Versionen aus der Library oder GitHub wiederherstellen; nie den älteren Archivstand als Update installieren.
2. SEO-Owner mit den bestehenden WPCode-Snippets auf dem jüngsten Quellstand abgleichen, erst dann minimal integrieren.
3. Gesamten Plugin- und Theme-PHP-Lint, Regressionen sowie vollständige HTML-SEO-Ausgabe prüfen.
4. Nur dann releasefähiges ZIP, Quellcommit, SHA-256 und verifizierten GitHub-Release bereitstellen.
5. Betreiber installiert selbst. Keine unangekündigten Live-Adminzugriffe und kein Eingriff in die Android-API.

## Sicherheitskorrektur und reproduzierte PHP-Abnahme 26.10.2026

Die erste Archiv-Entwicklungsversion verwendete irrtümlich eine allgemeine Stadt-Suffixregel für **alle** Places. Diese nicht freigegebene allgemeine Regel wurde **vor jeder Installation** korrigiert. Jetzt gilt im Archivzweig ausschließlich die ausdrücklich vereinbarte Place-ID `16936`: `Zum Schwarzen` → SEO-Dokumenttitel `Zum Schwarzen Essen | PottFuchs`. Alle anderen Orte, etwa das Weseler Wirtshaus, behalten ihren bisherigen Entity-/SEO-Basisnamen; nur der öffentliche Markensuffix des SEO-Dokumenttitels wird nach WordPress-Sitenamen aktualisiert. Fach-Entity-Titel, H1, JSON-LD, Canonical, URL, REST und GPS bleiben unberührt.

**CI-Nachweis:** [GitHub Actions 40346373196](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40346373196), `success`: PHP-Lint auf gesamter archivierter Places-Hauptdatei sowie Theme-`functions.php`, `header.php` und beiden SEO-PHP-Testdateien; 12 isolierte Titel-Fälle; 9 statische Quellcodevertragsprüfungen. Erwarteter Archiv-Code-Blob: `4ccbb36e97c28e3afa16a8dfb281e8ad7d39c95c`. Der allgemeine City-Title-Helper ist nicht mehr vorhanden. Der Test ist eine **Archiv-/Codeprüfung**, keine Integration mit der aktuellen Live-Installation.

**Sicherheitsgrenze bleibt:** Dieses Backend ist Version **1.10.169**. Neuere 1.10.186-/Theme-351-/WPCode-Originalarchive liegen nur als in diesem Lauf nicht materialisierbare persönliche Library-ZIPs vor. Die aktive WordPress-Installation und WPCode-Snippets konnten nicht versionsgleich nachgewiesen werden. Nicht als Installations-ZIP verteilen oder WordPress-Deployment freigeben; keinesfalls ältere Version über aktuellen Stand installieren.
