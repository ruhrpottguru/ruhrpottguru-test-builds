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
