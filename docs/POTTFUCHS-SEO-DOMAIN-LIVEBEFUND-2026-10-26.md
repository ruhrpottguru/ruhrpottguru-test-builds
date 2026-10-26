# PottFuchs – belegte SEO- und Domainwechselbefunde vom 26.10.2026

**Modus:** öffentlich lesende HTTP-GET-Prüfung über GitHub Actions, keine WordPress-/Hosting-/WPCode-Änderung und kein WPVibe.

**Prüfprotokolle:**
- [Live-SEO-Titel, Canonicals, Site-Name, Favicons und Weiterleitungen](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40324577427)
- [Identitätsvergleich bestehendes Favicon live vs. GitHub-SVG](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40324837378)
- [Domain-TLS und vollständige XML-Sitemaps](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40322949565)
- [Breiter öffentlicher Audit mit 23 SEO-/Markenbefunden](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40323670975)
- [PHP-Syntax und Regressionen des älteren, **nicht freigegebenen** Entwicklungszweigs](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40324097670)

## Tatsächliche HTML-Ausgabe, nicht bloß Google-Cache

| URL | HTTP | Titel | Canonical | og:site_name |
|---|---|---|---|---|
| / | 200 | PottFuchs – Events, Essen & Freizeit im Ruhrgebiet | neuer Host, 1 | PottFuchs |
| /ort/16936/essen/zum-schwarzen/ | 200 | **Zum Schwarzen \| RuhrpottGuru** | neuer Host, 1 | **RuhrpottGuru** |
| /ort/10523/wesel/weseler-wirtshaus/ | 200 | **Weseler Wirtshaus \| RuhrpottGuru** | neuer Host, 1 | **RuhrpottGuru** |
| /veranstaltung/6936/essen/77-essener-lichtwochen/ | 200 | **77. Essener Lichtwochen – 15. November 2026 in Essen \| RuhrpottGuru** | neuer Host, 1 | **RuhrpottGuru** |
| /veranstaltungen/ | 200 | **Veranstaltungen im Ruhrgebiet – heute & am Wochenende \| RuhrpottGuru** | neuer Host, 1 | PottFuchs |
| /stadt/wesel/sehenswuerdigkeiten/ | 200 | Sehenswürdigkeiten in Wesel – PottFuchs | neuer Host, 1 | **RuhrpottGuru** |

Homepage-JSON-LD enthält **Organization.name = RuhrpottGuru** und **WebSite.name = RuhrpottGuru**. Der neue Google-Websitename ist daher noch nicht konsistent ausgezeichnet, obwohl die HTML-Homepage bereits PottFuchs heißt. Die Webseitenamen-Ausgabe ist nach Google nicht erzwingbar, aber das bestehende WebSite-Markup hat hohe Priorität.

**SEO-Owner prüfen, nicht ersetzen:** historisch WPCode #3343 für WebSite/Organization und Kategorien, WPCode #4102 für Event-Titel, vorhandener Places-SSR für Ortsdetails, bestehende Theme-Title-Guard. Tatsächlich aktive Snippet-Originalversionen fehlen im GitHub-Archiv. Nur gezielte Änderungen an den zuständigen bestehenden Quellen, keine zusätzliche SEO-Schicht.

## Domain und technische Basis: erfolgreich verifiziert

- Die alten Hosts `https://www.ruhrpottguru.de` und `http://ruhrpottguru.de` leiten den getesteten Ortsdetail-Pfad jeweils mit **301 direkt auf den identischen Pfad bei https://www.pottfuchs.de/** weiter.
- `https://pottfuchs.de` leitet den getesteten Pfad mit **301 auf den www-Host** weiter.
- Die öffentliche TLS-Prüfung meldet gültige Let's-Encrypt-Zertifikate für beide Hostvarianten der alten und neuen Domains.
- `/wp-sitemap.xml` und `/rpg-sitemap.xml` samt 7 Kind-Dateien: alle 9 Anfragen HTTP 200 und ausschließlich `www.pottfuchs.de` in den `loc`-URLs. `rpg-sitemap-places.xml`: 15.058 URLs; `rpg-sitemap-events.xml`: 10.356 URLs. Das belegt XML-Ausgabe, **nicht** Indexierung bei Google.
- Vorhandene WordPress-REST-Namespaces und Android-POST-Kompatibilität dürfen durch den SEO-Fix nicht verändert werden.

## Favicon: reproduzierbare konkrete Ursache für das WordPress-W

Im Head der geprüften HTML-Seiten liegt **genau eine** `rel=icon`-Verknüpfung vor und verweist auf das bereits vorhandene `/wp-content/themes/ruhrpott-guru/favicon.svg`. Die Datei antwortet mit **HTTP 200** und MIME **image/svg+xml**.

Der Live-Datei-Hash und das GitHub-Archiv-SVG sind **byteidentisch**: SHA-256 `16bf0971d5c1bf85e22f7898d78ee5ea7fb9dd05b133fe3f705b6fb96b6333ad` (1.412 Bytes).

**Aber:** `https://www.pottfuchs.de/favicon.ico` antwortet derzeit mit **302 auf `/wp-includes/images/w-logo-gray-white-bg.png`**. Hier liegt eine konkrete konkurrierende WordPress-Standardicon-Ausgabe vor. Googles momentane Anzeige kann auch gecacht sein; eine sichere Aussage, welchen Pfad Google konkret gewählt hat, ist ohne Google-Indexdaten nicht möglich. Eine zusätzliche, technisch sinnvolle Prüfung/Freigabe ist die Verwendung des bestehenden Symbols als 512×512-PNG in WordPress **Website-Icon** (keine Neugestaltung); dadurch kann das Core-Standard-W ersetzt werden. Erst den tatsächlichen aktuellen Stand der WordPress-Site-Icon-Option bestätigen, bevor der Betreiber die Einstellung anfasst. Alternativ bestehenden Icon-Owner direkt reparieren, nicht einen parallelen Favicon-Generator einführen.

Google: [Websitenamen](https://developers.google.com/search/docs/appearance/site-names?hl=de) · [Favicon](https://developers.google.com/search/docs/appearance/favicon-in-search?hl=de).

## Release-Schutzgrenze

GitHub `main` enthält lediglich Theme v345 und Places v1.10.169 als Archiv. Der unfreigegebene Zweig [`pottfuchs-seo-fix`](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/tree/pottfuchs-seo-fix) baut auf diesem **veralteten** Places-Code auf. Neuere Library-ZIPs v351 und v1.10.184 sind anhand von Dateinamen bekannt, aber beim Rohbyte-Download über den verfügbaren Library-Zugriff derzeit nicht zugänglich. **Kein Downgrade, kein installierbares Plugin aus der v1.10.169-Basis.** Nicht behaupten, dass die aktuellen PHP-/WPCode-Quellen identisch sind.

Priorität nächste sichere Entwicklung: aktuelle originale Betreiberdateien über eine autorisierte Quelle wiederabrufen/archivieren; bestehende Snippet-Owner bei Titeln/JSON-LD minimal korrigieren; Tests; ein finales installierbares ZIP samt SHA-256 und GitHub Release.
