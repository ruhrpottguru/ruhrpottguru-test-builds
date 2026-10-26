# PottFuchs – operativer Prüfstand und aktuelle Aufgaben

**Stand:** 26.10.2026. PottFuchs ist die neue Marke; `https://www.pottfuchs.de` ist die primäre Domain. `ruhrpottguru.de` bleibt für 301. Der Domainwechsel und der Adresswechsel in Google Search Console fanden bereits statt. Keine Wiederholung.

## Verifizierte Prüfergebnisse

Ein unabhängiger, schreibfreier GitHub-Actions-Runner hat am 26.10.2026 öffentlich erreichbare URLs mit geprüftem HTTPS abgerufen:

- **10/10 Redirect-Testfälle bestanden**: alle alten HTTP-/HTTPS-, Apex-/www-Stammhosts per **301** direkt auf `https://www.pottfuchs.de/`; neue Apex-/HTTP-Hosts ebenfalls sauber auf HTTPS-www. Alte Pfade `/impressum/` und `/ort/16936/essen/zum-schwarzen/` wurden **pfadgleich** mit 301 weitergeleitet; Endstatus jeweils 200.
- **8/8 HTML-Seiten HTTP 200**; jede mit genau einem Canonical, genau einer Meta-Description und genau einem H1. Getestet wurden Home, zwei Orte, ein Event, Impressum, Datenschutz, Zeitreise sowie Stadt-Wesel-Sehenswürdigkeiten. Detail-Canonicals weisen auf die neue jeweilige URL.
- **Drei Ressourcen erreichbar** (HTTP 200): `/robots.txt`, `/wp-sitemap.xml`, `/rpg-sitemap.xml`. Die Sitemap-Inhaltsprüfung war nur eine Stichprobe, keine vollständige Google-Indexierungsprüfung.
- **Mobile Chromium-Browserprobe 8/8 erfolgreich**: Home, Events, Food, Highlights, Kultur, Aktivitäten und zwei direkte Detail-URLs liefern HTTP 200, reagieren nach Hydration ohne sichtbaren Ladezustand, ohne JavaScript-Ausnahme und ohne beobachtete Server-5xx. Mit synthetischem Wesel-Standort und 5 km wurden sichtbare Karten gezählt: Home 4, Events 24, Food 24, Highlights 8, Kultur 20, Aktivitäten 23. Beide Detailseiten zeigten Inhalte. Screenshots und JSON [im Lauf 40311271386](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40311271386). Es war nur ein Browser-Smoke-Test; Filter/Chips, Reload-Persistenz, reale GPS-Geräte und alle Sonderfälle bleiben offen.
- Technisch eindeutig **noch fehlerhaftes öffentliches Branding**: Startseite und WordPress-Seiten im `WebSite`-/`Organization`-JSON-LD teils weiterhin `RuhrpottGuru`; Orts- und Event-Detailtitel sowie `og:site_name` noch alt; Zeitreise zeigt `RuhrpottGuru-Standort`. Das **ausdrücklich beizubehaltende orange Förderturm-Favicon** (Vereinbarung 23.10.) ist als Theme-`favicon.svg` erreichbar (HTTP 200) und korrekt im HTML eingebunden. Die alten 2021-Mediathek-Icons mit `C80` sind nicht das vereinbarte Favicon. **Keines dieser Assets wurde verändert.**
- Beispiel der faktisch ausgelieferten Detailseite: `/ort/16936/essen/zum-schwarzen/` hat noch den Title `Zum Schwarzen | RuhrpottGuru`; vereinbart ist **`Zum Schwarzen Essen | PottFuchs`**. Die URL, H1, Canonical und die Fachbezeichnung des Datensatzes werden nicht umbenannt.

Aktuellster, korrigierter Nachweis: [HTTP/SEO-Audit-Lauf 40311669897](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40311669897) – **neun** öffentliche Altmarken-Befunde (Titel, Strukturierte Daten, Zeitreise); das vereinbarte Förderturm-Favicon ist als korrekt ausgeklammert. ZIP-Artefakt erneut abgerufen und geprüft, SHA-256 `e1ff23640bfe1047f30879f7442ba95d2f86ddc047a15ebe0d43ff72f82b59dd`. Die erste [Prüfung 40309681328](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40309681328) zählt das gewünschte Favicon noch irrtümlich als zehnten Befund und ist durch den neueren Lauf ersetzt. Drei Parser-Unit-Tests und der CI-Job bestanden. **„Job erfolgreich“ heißt, dass die Prüfung durchlief; die neun öffentlichen Altmarken sind nicht repariert.**

## Offene Aufgaben in fester Reihenfolge

| Priorität | Aufgabe | Nächste sichere Aktion / Grenze |
|---|---|---|
| P0-1 | Öffentlicher Detailseiten-SEO-Titel `Zum Schwarzen Essen \| PottFuchs` und weitere alte Titel | Der GitHub-Branch `pottfuchs-seo-fix` enthält bereits einen nicht freigegebenen Minimalpatch auf historischem Places-Stand **v1.10.169**. Nicht erneut implementieren oder mit dem zuletzt belegten Live-Backend **v1.10.182** verwechseln. Aktuelle Originalquelle + WPCode-Ausgabe-Eigentümer prüfen, minimal korrigieren und regressionsprüfen, erst dann installierbares ZIP. |
| P0-2 | `WebSite`/`Organization`/OG-Markennamen | Vorhandene WPCode-Snippets #3343 / #4102 sowie Places-SEO-Owner prüfen. Nur öffentliche Markennamen, niemals REST-Namespaces, technische IDs, Canonicals, Ranking, Kategorien, Filter oder Datenquellen pauschal ersetzen. Keine zweite SEO-Engine. |
| **Abgeschlossen (technische Favicon-Einbindung)** | Förderturm-`favicon.svg` | Gewünschtes bestehendes Favicon (23.10.) unverändert, unter neuer Domain mit **HTTP 200** abrufbar, im HTML referenziert. **Nicht ersetzen.** Google-Suchergebnis kann wegen Cache zeitversetzt abweichen; das ist kein neuer WordPress-Fehlerbeleg. |
| P0-4 | Zeitreise-Altmarke | Sichtbarer `RuhrpottGuru-Standort`-Text kommt aus einem Renderer, nicht aus dem WordPress-`post_content` (bisheriger Match-only-Test); Owner recherchieren und nur sichtbare Marke ändern. |
| P0-5 | Search Console: Site Name, Indexierung, Rich Results | Suchergebnis-Darstellung kann Google zeitversetzt/anders zeigen. GSC Wizard ist derzeit ohne aktives Abo nicht nutzbar; keine Kostenfreigabe. Suchmaschinenstatus getrennt vom technischen HTML-Nachweis halten. |
| P0-6 | App-/REST-Verträglichkeit | Android-Kennung `de.ruhrpottguru.app` und bestehende technische API-Namen erhalten. GitHub-App-Quellen versionsgenau prüfen, kein paralleles Backend. |
| P1-1 | GPS, PLZ, Radius, Chips und Kategorien: vollständiges Browser-End-to-End | **Öffentlicher mobiler Smoke-Test für sechs Ansichten plus zwei Direktdetails bestanden.** Reale GPS-Geräte, manuelle Standortwahl, PLZ, www/apex-Cookies, Reload-Persistenz, Chip-Klicks und Overlay noch nicht geprüft. Geschützte Fachlogik nicht ungefragt ändern. |
| P1-2 | Event-/Places-Importe und Monitoring | WP-Cron-Startfähigkeit/eingeplante Hooks aus früherer Diagnose belegt, **erfolgreiche jüngste Läufe nicht**. Ohne Auftrag keine Jobs starten/umstellen. |
| P1-3 | Volles Backup/Restore | Ein Restorebeleg aller Komponenten liegt noch nicht vor; produktive Restoreprobe benötigt eigenes Gate. |
| P1-4 | Saison-Engine Weihnachten/Halloween/Ostern/Winter | Existierendes Halloween zuerst wiederverwenden und fachliche Profile getrennt halten; redaktionelle Weihnachtsseite allein ist noch keine abgenommene dynamische Eventliste. |

## Rechte und Nachweisklassen

**Kein Live-Release/keine unautorisierte WordPress-Änderung in diesem Audit.** Aktueller Live-Code ist neuer als das 19.10. auf GitHub archivierte Theme/Places-Backend. Der SEO-Branch ist ausdrücklich **nicht fertig/installierbar**, bis vollständige aktuelle Quellbasis und Regression vorliegen. WPVibe wird ohne ausdrücklichen Live-Auftrag nicht eingesetzt. Keine zusätzlichen kostenpflichtigen APIs. Auch fehlende Bilder dürfen weiterhin niemals Einträge ausblenden.

**Falschen Testpfad nicht als Fehler verkaufen:** `/stadt/wesel/highlights/` war in einem früheren Lighthouse-Test ein falsch angenommener URL-Pfad. Die veröffentlichte Stadt-Sehenswürdigkeiten-Seite `/stadt/wesel/sehenswuerdigkeiten/` lieferte beim unabhängigen Check HTTP 200 mit Canonical, H1 und Description.
