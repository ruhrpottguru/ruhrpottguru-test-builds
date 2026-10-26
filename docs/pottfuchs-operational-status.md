# PottFuchs – operativer Prüfstand und aktuelle Aufgaben

**Stand:** 26.10.2026. PottFuchs ist die neue Marke; `https://www.pottfuchs.de` ist die primäre Domain. `ruhrpottguru.de` bleibt für 301. Der Domainwechsel und der Adresswechsel in Google Search Console fanden bereits statt. Keine Wiederholung.

## Aktuellster geprüfter Stand – 26.10.2026, nach Abschluss der v2.3-Prüfungen

| Bereich | Status | Unabhängiger Nachweis |
|---|---|---|
| HTTPS/SSL | **Erledigt: 4/4 gültig** | pottfuchs.de/www: Zertifikat gültig bis **21.01.2027**; ruhrpottguru.de/www: bis **11.01.2027**; gültige Hostnamen und Let's Encrypt; automatische Erneuerung selbst nicht getestet |
| Öffentliche 301 | **14/14 erfolgreich** | Alte öffentliche Domain/URL-Familien auf www.pottfuchs.de pfadgleich; REST-Ausnahme ausdrücklich beibehalten |
| Sitemap und robots | **7/7 Sitemap-Unterdateien gelesen**, 0 falsche Hostnamen | **15.058 Places-, 10.356 Event-URLs** im RPG-Sitemapteil; **346** weitere WordPress-Sitemap-URLs; alle erfassten `<loc>` auf www.pottfuchs.de; robots.txt verweist auf beide vorhandenen Indizes |
| Öffentliche Such-POST-Kompatibilität | **8/8 erfolgreich** | Auf alter und neuer Domain je 4 gültige JSON-POST-Suchanfragen, HTTP 200 ohne Redirect; inklusive null Treffer bei Unsinnssuche; andere REST-POST-Methoden weiter separat offen |
| Historischer Topgolf-Suchfehler | **Für drei konkrete Eingaben nicht mehr reproduzierbar** | `topgolf`, `topgolf oberhausen`, `top golf oberhausen`: je **genau ein identischer Place** Topgolf, Oberhausen, ID `1051311896` – REST und tatsächlicher Browser |
| Mobile Website/Funktionen | **11/11 Browser-E2E bestanden** | Fünf Kategorien, Consent, Detailchip/Filter, Kalender, Standort Essen/Reload, Radius 10 km/Reload, PLZ 45888, Suche 3 Varianten, www/Apex-Wechsel, GPS-Verweigerung mit 0 Karten vor Stadtwahl und manuellem Fallback, vier Specials |
| Google-/SEO-Markenfelder | **Offen** | In 13 Seiten **23 Altmarken-Feldbefunde**; keine automatische Reparatur durch Site-Name-Option; siehe SEO-Owner #3343/#4102/Places |
| Aktuelle Live-Plugin-/Theme-Releasequelle | **Blockiert** | Auf GitHub verfügbar sind historische Theme v345 / Places v1.10.169. Library listet neuere ZIPs bis Places v1.10.185, Materialisierung der tatsächlichen Bytes weiterhin verweigert. Kein sicheres Installations-ZIP daraus erzeugbar |
| Synchronisierungs-Laufprotokolle / Backup-Restore | **Offen** | Cron eingeplant und Spawn historisch bestätigt; jüngster erfolgreicher abgeschlossener Import oder echte Rücksicherung bisher **nicht** belegt |

**Neue Nachweise:**
[TLS und alle Sitemap-Unterdateien – GitHub Actions 40322949565](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40322949565);
[REST-Suche alt/neu – 40322085271](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40322085271);
[11/11 Browser-Journeys – 40322506314](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40322506314).
Die resultierenden ZIPs wurden wieder heruntergeladen; SHA-256 und ZIP-Integrität erfolgreich überprüft (TLS/Sitemaps: `8b6c3386698cef2a9960161678644d1d1748d6d93ae190b3d4e7d61decbfb2fc`; REST: `cac8abb0ec3af17c29a4a6777a335503db1a70780673676d51c0b405fb45e628`; Browser: `f51244402e1ca30f4912b42c3d786edd41cf92dd9c8af18204e2aac7a43518da`).

**Freigabegrenze:** Kein WordPress-/WPCode-/Hosting-/Datenbank-/App-Livewrite und keine ungefragte WPVibe-Nutzung. Alle Repo-Änderungen dieser Runde betreffen Prüfskripte, Tests oder Dokumentation. Keine als produktionsreif deklarierte SEO-ZIP. Website und App-Bestand sind getrennt zu bewerten.

## Verifizierte Prüfergebnisse

Ein unabhängiger, schreibfreier GitHub-Actions-Runner hat am 26.10.2026 öffentlich erreichbare URLs mit geprüftem HTTPS abgerufen:

- **10/10 Redirect-Testfälle bestanden**: alle alten HTTP-/HTTPS-, Apex-/www-Stammhosts per **301** direkt auf `https://www.pottfuchs.de/`; neue Apex-/HTTP-Hosts ebenfalls sauber auf HTTPS-www. Alte Pfade `/impressum/` und `/ort/16936/essen/zum-schwarzen/` wurden **pfadgleich** mit 301 weitergeleitet; Endstatus jeweils 200.
- **8/8 HTML-Seiten HTTP 200**; jede mit genau einem Canonical, genau einer Meta-Description und genau einem H1. Getestet wurden Home, zwei Orte, ein Event, Impressum, Datenschutz, Zeitreise sowie Stadt-Wesel-Sehenswürdigkeiten. Detail-Canonicals weisen auf die neue jeweilige URL.
- **Drei Ressourcen erreichbar** (HTTP 200): `/robots.txt`, `/wp-sitemap.xml`, `/rpg-sitemap.xml`. Die Sitemap-Inhaltsprüfung war nur eine Stichprobe, keine vollständige Google-Indexierungsprüfung.
- **Mobile Chromium-Browserprobe 8/8 erfolgreich**: Home, Events, Food, Highlights, Kultur, Aktivitäten und zwei direkte Detail-URLs liefern HTTP 200, reagieren nach Hydration ohne sichtbaren Ladezustand, ohne JavaScript-Ausnahme und ohne beobachtete Server-5xx. Mit synthetischem Wesel-Standort und 5 km wurden sichtbare Karten gezählt: Home 4, Events 24, Food 24, Highlights 8, Kultur 20, Aktivitäten 23. Beide Detailseiten zeigten Inhalte. Screenshots und JSON [im Lauf 40311271386](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40311271386). Es war nur ein Browser-Smoke-Test; Filter/Chips, Reload-Persistenz, reale GPS-Geräte und alle Sonderfälle bleiben offen.
- Technisch eindeutig **noch fehlerhaftes öffentliches Branding**: Startseite und WordPress-Seiten im `WebSite`-/`Organization`-JSON-LD teils weiterhin `RuhrpottGuru`; Orts- und Event-Detailtitel sowie `og:site_name` noch alt; Zeitreise zeigt `RuhrpottGuru-Standort`. Das **ausdrücklich beizubehaltende orange Förderturm-Favicon** (Vereinbarung 23.10.) ist als Theme-`favicon.svg` erreichbar (HTTP 200) und korrekt im HTML eingebunden. Die alten 2021-Mediathek-Icons mit `C80` sind nicht das vereinbarte Favicon. **Keines dieser Assets wurde verändert.**
- Beispiel der faktisch ausgelieferten Detailseite: `/ort/16936/essen/zum-schwarzen/` hat noch den Title `Zum Schwarzen | RuhrpottGuru`; vereinbart ist **`Zum Schwarzen Essen | PottFuchs`**. Die URL, H1, Canonical und die Fachbezeichnung des Datensatzes werden nicht umbenannt.

Aktuellster, korrigierter Nachweis: [HTTP/SEO-Audit-Lauf 40311669897](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40311669897) – **neun** öffentliche Altmarken-Befunde (Titel, Strukturierte Daten, Zeitreise); das vereinbarte Förderturm-Favicon ist als korrekt ausgeklammert. ZIP-Artefakt erneut abgerufen und geprüft, SHA-256 `e1ff23640bfe1047f30879f7442ba95d2f86ddc047a15ebe0d43ff72f82b59dd`. Die erste [Prüfung 40309681328](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40309681328) zählt das gewünschte Favicon noch irrtümlich als zehnten Befund und ist durch den neueren Lauf ersetzt. Drei Parser-Unit-Tests und der CI-Job bestanden. **„Job erfolgreich“ heißt, dass die Prüfung durchlief; die neun öffentlichen Altmarken sind nicht repariert.**

## Letzter geprüfter Fortschritt – 26.10.2026, 12:10 UTC

**Schreibfreie unabhängige Prüfungen ohne WPVibe; keine Änderung am laufenden WordPress.**

- **14/14 öffentliche Domain-/Pfadvarianten nach neuer Prüfung korrekt**: 301 auf die neue HTTPS-www-Domain, gleichbleibender Pfad, HTTP 200 am Ziel. Zusätzlich ist die *bewusst ausgenommene* alte REST-API `https://www.ruhrpottguru.de/wp-json/` mit HTTP 200 erreichbar **ohne Redirect**. Dies entspricht dem Cutover-Runbook §3.2 (App-POST-Kompatibilität nicht durch pauschales 301 zerstören). Das ist **kein Redirect-Fehler**; die API-POST-Verträglichkeit älterer APKs ist separat offen.
- **13/13 Seiten HTTP 200** mit je genau einer Canonical, Meta-Description und H1. Einschließlich aller fünf Hauptkategorien, zwei Place-Details, Event-Detail, Stadtseite, rechtlichen Seiten, Zeitreise, Home. **23 genau bezeichnete alte SEO-/Markenfeld-Vorkommen** in diesen 13 Seiten (Titel, JSON-LD, `og:site_name`, Zeitreise-Text), keine 23 separaten Defekte. `/ort/16936/essen/zum-schwarzen/` bleibt weiterhin bei *`Zum Schwarzen | RuhrpottGuru`* statt vereinbart *`Zum Schwarzen Essen | PottFuchs`*.
- **8/8 interaktive Chromium-Mobile-Szenarien PASS**: Cookie-Dialog nur im isolierten Testbrowser auf „Nur Wesentliche“ geschlossen; tatsächlich geladene, anklickbare Cards aller fünf Kategorien; Filterchip `Berliner Tor` aktiviert `rg_tag=berliner-tor`; Event-Datumsfilter „Morgen“ an/aus; manuelle Stadt Essen bleibt beim Seitenwechsel und Reload erhalten; Radius 10 km bleibt nach Reload; PLZ 45888 erfolgreich angenommen; vier Specials (Büdchen-Radar, Imbissbuden, Halloween, Zeitreise) HTTP 200. Synthetischer GPS-Ort Wesel; kein physisches Endgerät. Ein vorheriger Test zählte fälschlich Skelette als Karten und prüfte das Etikett „Ändern“ anstelle des echten Standorttextes; die Testassertions wurden korrigiert und **danach mit 8/8 bestanden**. Kein Nachweis für alle Filter/Android.
- **PHP-Syntax im bestehenden SEO-Entwicklungszweig `pottfuchs-seo-fix` erfolgreich geprüft**: vier PHP-Dateien des **historischen** Places-Backends einschließlich Hauptdatei mit `php -l`, ohne Syntaxfehler. Dieser Nachweis ist **keine Live-/Versionskompatibilitäts- oder Releasefreigabe** für den aktuellen Backend-Code. [GitHub-Lauf 40319118206](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40319118206).
- Neuere **Library-Metadaten** nennen v1.10.185 (Manuelle Importsteuerung), v1.10.184 sowie Theme v351/WPCode-SEO-Paket. **Die Original-ZIP-Bytes konnten trotz tatsächlichem Materialisierungsversuch nicht autorisiert in den Arbeitscontainer gebracht werden** (`authorized raw-byte materialization path`). Die ZIP-Namen beweisen keine Live-Installation. Keine aus älterem Quellstand abgeleitete Installations-ZIP!
- Android-Sourceschnappschuss `android-play-v0.20.0-beta.2-source` hat `de.ruhrpottguru.app` und ein `RuhrpottGuru`-Label. Der stabile Paketname muss bleiben; ein neuerer Library-Beta-/Play-Livestand ist hier **nicht nachgewiesen**. Keine App-Änderung.

**Abnahme-Artefakte, erneut heruntergeladen und auf ZIP-Integrität geprüft:**
[Domain-/SEO-Lauf 40320168645](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40320168645), SHA-256 Ergebnis-ZIP `d4cc3fbfed6b3da56f100ab1742cddd8e38ab8965557e848ce0f672cf155766b`; [Chromium-E2E-Lauf 40319800577](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40319800577), SHA-256 Ergebnis-ZIP `a62b3345ed7b6608ad42d332818cc071afa01721c46b3b9bf282f60c9a3eacd4`.

**Offen (P0):** echte öffentliche SEO-Markenfixes im neuesten freigegebenen Places-/WPCode-Original, insbesondere Titel/OG/Organization/Sitenamen und Zeitreise-Text; P0-Sicherheits-/Releasegates beibehalten. **Offen (P1):** reale GPS-/Android-POST-/Chipvollregression, Event-/Places-Importabschlusslogs, tatsächliche Backup-Restoreprobe, GSC-Indexierung. Für diese Themen sind keine stillen Liveeingriffe erlaubt.

## Offene Aufgaben in fester Reihenfolge

| Priorität | Aufgabe | Nächste sichere Aktion / Grenze |
|---|---|---|
| P0-1 | Öffentlicher Detailseiten-SEO-Titel `Zum Schwarzen Essen \| PottFuchs` und weitere alte Titel | Der GitHub-Branch `pottfuchs-seo-fix` enthält bereits einen nicht freigegebenen Minimalpatch auf historischem Places-Stand **v1.10.169**. Nicht erneut implementieren oder mit dem zuletzt belegten Live-Backend **v1.10.182** verwechseln. Aktuelle Originalquelle + WPCode-Ausgabe-Eigentümer prüfen, minimal korrigieren und regressionsprüfen, erst dann installierbares ZIP. |
| P0-2 | `WebSite`/`Organization`/OG-Markennamen | Vorhandene WPCode-Snippets #3343 / #4102 sowie Places-SEO-Owner prüfen. Nur öffentliche Markennamen, niemals REST-Namespaces, technische IDs, Canonicals, Ranking, Kategorien, Filter oder Datenquellen pauschal ersetzen. Keine zweite SEO-Engine. |
| **Abgeschlossen (technische Favicon-Einbindung)** | Förderturm-`favicon.svg` | Gewünschtes bestehendes Favicon (23.10.) unverändert, unter neuer Domain mit **HTTP 200** abrufbar, im HTML referenziert. **Nicht ersetzen.** Google-Suchergebnis kann wegen Cache zeitversetzt abweichen; das ist kein neuer WordPress-Fehlerbeleg. |
| P0-4 | Zeitreise-Altmarke | Sichtbarer `RuhrpottGuru-Standort`-Text kommt aus einem Renderer, nicht aus dem WordPress-`post_content` (bisheriger Match-only-Test); Owner recherchieren und nur sichtbare Marke ändern. |
| P0-5 | Search Console: Site Name, Indexierung, Rich Results | Suchergebnis-Darstellung kann Google zeitversetzt/anders zeigen. GSC Wizard ist derzeit ohne aktives Abo nicht nutzbar; keine Kostenfreigabe. Suchmaschinenstatus getrennt vom technischen HTML-Nachweis halten. |
| P0-6 | App-/REST-Verträglichkeit | **Alte REST-API-Root per GET weiterhin 200 unter altem Host** (bewusste Cutover-Ausnahme); App-Package `de.ruhrpottguru.app` im GitHub-Beta2-Quellstand stabil. **Offen:** alte APK-/REST-POST-Methoden, echte App-Links sowie neuerer Play-/Beta-Livestand; keine parallele API, keine Package-Umbenennung. |
| P1-1 | GPS, PLZ, Radius, Chips und Kategorien: vollständiges Browser-End-to-End | **Synthetische Mobile-E2E bestanden**: Essen-Stadtwahl + Reload, 10-km-Radius + Reload, PLZ 45888, anklickbarer Highlight-Detailchip, Event-Datumsfilter, fünf Kategorien nach Consent, vier Specials. **Offen:** reales Endgerät/GPS-Erlaubnis und -Ablehnung, www/apex-Origin, alle Chips/Fallbacks und interne Layer-Regressionsmatrix. Fachlogik nicht ändern. |
| P1-2 | Event-/Places-Importe und Monitoring | WP-Cron-Startfähigkeit/eingeplante Hooks aus früherer Diagnose belegt, **erfolgreiche jüngste Läufe nicht**. Ohne Auftrag keine Jobs starten/umstellen. |
| P1-3 | Volles Backup/Restore | Ein Restorebeleg aller Komponenten liegt noch nicht vor; produktive Restoreprobe benötigt eigenes Gate. |
| P1-4 | Saison-Engine Weihnachten/Halloween/Ostern/Winter | Existierendes Halloween zuerst wiederverwenden und fachliche Profile getrennt halten; redaktionelle Weihnachtsseite allein ist noch keine abgenommene dynamische Eventliste. |

## Rechte und Nachweisklassen

**Kein Live-Release/keine unautorisierte WordPress-Änderung in diesem Audit.** Aktueller Live-Code ist neuer als das 19.10. auf GitHub archivierte Theme/Places-Backend. Der SEO-Branch ist ausdrücklich **nicht fertig/installierbar**, bis vollständige aktuelle Quellbasis und Regression vorliegen. WPVibe wird ohne ausdrücklichen Live-Auftrag nicht eingesetzt. Keine zusätzlichen kostenpflichtigen APIs. Auch fehlende Bilder dürfen weiterhin niemals Einträge ausblenden.

**Falschen Testpfad nicht als Fehler verkaufen:** `/stadt/wesel/highlights/` war in einem früheren Lighthouse-Test ein falsch angenommener URL-Pfad. Die veröffentlichte Stadt-Sehenswürdigkeiten-Seite `/stadt/wesel/sehenswuerdigkeiten/` lieferte beim unabhängigen Check HTTP 200 mit Canonical, H1 und Description.
