# PottFuchs P0 – Detailseiten und SEO: nachgewiesene Abnahmegrenze, 26.10.2026

## 1. Priorität und Owner
P0 aus Projektleiter-Masterliste v2.0: bestehende Event-/Place-URLs sollen als vollständige Direkt-Einstiege mit bereits vorhandenen Theme-/React-Headern und -Footern, lesbarem SSR-Inhalt, Canonical und internen Overlays funktionieren. Nach Rebranding öffentliche Marke PottFuchs. Keine neue SEO-/Routing-Architektur, Kategorien, Ranking, Quellen, Radius und App-REST unverändert.

## 2. Produktives öffentliches HTML und Chromium-E2E – tatsächlich geprüft
Read-only-Synthetikbrowser Chromium, 390×844 Mobile und 1440×900 Desktop, simuliertes GPS Wesel. Originalcode unverändert, keine Logins, Formulareinsendungen oder WP-Änderungen.

**[Unabhängiger P0-Browserlauf 40349053035 – 14/14 PASS](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40349053035)**. Testcode: [`tools/pottfuchs_p0_detail_e2e.js`](../tools/pottfuchs_p0_detail_e2e.js); [fokussierter GitHub Actions Runner](../.github/workflows/pottfuchs-p0-focused-browser.yml). Screenshots und JSON-Ergebnis im GitHub Actions Artefakt (30 Tage Retention), Testquellen dauerhaft versioniert.

- Mobile und Desktop für `/ort/16936/essen/zum-schwarzen/`, `/ort/10523/wesel/weseler-wirtshaus/`, `/veranstaltung/6936/essen/77-essener-lichtwochen/`: jedes Mal HTTP 200, sofort lesbarer serverseitiger Detailartikel im Roh-HTML, nach Hydration sichtbarer **bestehender Header sowie FooterNav** (FooterNav ist `<nav class="rpgx-footer-nav">` und kein `<footer>`), Detailinhalt, genau eine H1, ein Canonical auf neuer korrekter Host-/Pfad-URL, eine Meta-Description, kein horizontaler Überlauf. Neuladen erhält H1 und Seite. Keine beobachteten JS-Pageerrors.
- Place-/Event-Kachel aus Listing öffnet bestehendes Overlay als Dialog und Escape schließt es ohne die Kategorie-/Listenansicht oder URL zu verlieren: Mobile und Desktop jeweils PASS.
- Ungültige Place-ID produziert HTTP 404 statt Fantasiedetail: Mobile und Desktop PASS.
- Der initiale E2E-Runner prüfte fehlerhaft ausschließlich ein HTML-`footer`-Element, obwohl die bestehende fachlich korrekte Implementierung `nav.rpgx-footer-nav` nutzt. Nach **DOM-Nachweis** wurde nur die Testassertion korrigiert; die echte Web-Implementierung wurde **nicht** verändert. Der neue fokussierte Lauf ist 14/14 PASS.
- Gemessene Testdauer enthält explizite künstliche Wartezeiten und Reloads; ist **kein** belastbarer LCP-/Ladezeit-Benchmark. P0-Vollabnahme für jedes Sonder-/Altevent, reale Mobilgeräte/GPS, Google-Indexierung und App-Geräte-Links ist damit nicht bewiesen.

## 3. Weiterhin reproduzierte P0-Fehler (nicht behoben)
Die **Produktivantwort** zeigt weiterhin alte SEO-Marke (kein bloßer Google-Cache):
- Place 16936: `Zum Schwarzen | RuhrpottGuru`, Soll **`Zum Schwarzen Essen | PottFuchs`**.
- Weseler Wirtshaus: `Weseler Wirtshaus | RuhrpottGuru`, Soll Public Brand PottFuchs bei unverändertem Eintragsnamen.
- Essen Event 6936: altes Marken-Suffix.
- Homepage-WebSite-/Organization-JSON-LD trägt alte Marke; weitere Kategorie-Titel, OpenGraph-Felder und Zeitreise-Marker betroffen. [Öffentlicher SEO-Befund vom 26.10.](POTTFUCHS-SEO-DOMAIN-LIVEBEFUND-2026-10-26.md).

**Favicon P0 behoben:** Betreiber hat echte Förderturm-`favicon.ico` im Webroot abgelegt; Live-Check HTTP 200, gültige 6 ICO-Einträge, erwarteter SHA-256. 301-Weiterleitungen, SSL, 9 Sitemaps / Canonicals im bisherigen definierten Prüfumfang ohne Migrationseingriff PASS. WP REST `site_icon=0` ist getrennt und beeinträchtigt die funktionierende Root-Datei nicht.

## 4. Vorbereitete, aber NICHT freigegebene Quellkorrekturen
Historischer PHP-Entwicklungszweig [`pottfuchs-seo-fix`](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/tree/pottfuchs-seo-fix):
- Der ursprünglich zu breite City-Suffix-Helper wurde durch eine **einzige explizit genehmigte Titel-Ausnahme nur für Place-ID 16936** ersetzt. Andere Place-Namen unverändert. SEO-Brand im bestehenden SSR-Owner kommt aus WordPress-Sitename. Entity-H1/URL/JSON-LD/REST geschützt.
- **[PHP-Regression 40346373196 – SUCCESS](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40346373196)**: Ganzdatei-`php -l` der alten Places-Hauptdatei, Theme-Funktions- und Header-PHP, beide PHP-Testdateien; 12 isolierte ID-Titeltestfälle und statische Source-Contracts. Archiv ist **Places v1.10.169**, kein Live-Stand.
- Separater offline-only [WPCode-Patch-Branch](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/tree/pottfuchs-wpcode-branding-readonly-20261026) mit exakten Guarded-Replacements für Snippets #3343/#4102 und unittest/CI. **Kein nachgewiesener Export der aktiven Originalsnippets**, keine produktive Integration.
- Die aktuelle originale Theme-/Places- und WPCode-Quelle ist für diesen Chat **nicht zur Rohbyte-Verarbeitung verfügbar**. Library-Metadaten nennen Theme v351, Places v1.10.186 und WPCode-Archiv, aber mehrfacher `files.materialize`-Versuch erhielt `This Project file does not have an authorized raw-byte materialization path`. GitHub main enthält ältere Theme v345 / Places v1.10.169. Die jüngere v1.10.187 ist ausdrücklich ein **nicht-installierbarer Entwicklungsstand**, keine automatische aktuelle Produktionsbasis.
- **Sicherheitsfolge: Keine installierbare aktuelle SEO-ZIP, keine WordPress-Installation und keine GitHub-Release-Behauptung.** Alte Plugins nie über neuere Live-Version installieren.

## 5. Website/App-Verträge
Android [`ruhrpottguru/ruhrpottguru-android`](https://github.com/ruhrpottguru/ruhrpottguru-android) auf main: `AndroidManifest.xml` registriert für die bestehenden Zeitreise-Intentfilter nur `www.ruhrpottguru.de` und `ruhrpottguru.de`, nicht `pottfuchs.de`; `android:autoVerify="false"`. Im archivierten beta2-Release `de.ruhrpottguru.app` stabil. Das ist ein konkretes Risiko für neue Host-Links, aber weder ein Beleg zum Verhalten einer installierten APK noch die Freigabe eines App-Releases. Alte Host-Intentfilter und API-Namespaces müssen für Bestands-Apps bestehen bleiben. 301 von Webseitenpfaden bleibt; alte REST-Endpunkte intentional erreichbar.

## 6. Unüberwindbare Release-Grenze und exakte Fortsetzung
Erforderlich für sichere Implementation/Versionierung/ZIP:
1. **Rohbytes** der tatsächlich produktiv eingesetzten Theme-/Places-Basis, einschließlich aller neuer als Repo-Archiv installierten Änderungen.
2. **Aktive Originaltexte und Aktivzustand** der bestehenden SEO-/WPCode-Snippets #3343, #4102 (ggf. #4152) samt Markup-Ownern. Keine Rekonstruktion aus Schnipseln.
3. Danach Rebase des minimalen P0 SEO-Diffs, vollständige relevante Regression Website und App-Vertrag, PHP/JS-Lint, ein finales installierbares ZIP, Commit/Release Asset/SHA256/Wiederabruf, Betreibereinspielung und Live-HTML-Abnahme; keine Live-Admin-/DB-Änderung ohne eigene konkrete Freigabe.

Die P0-Direktseiten-/Overlay-Szenarien oben sind im geprüften Umfang abgeschlossen. **P0 SEO-Rebranding als Gesamtauftrag ist mangels aktueller Quelloriginale noch nicht vollständig abschließbar.**
