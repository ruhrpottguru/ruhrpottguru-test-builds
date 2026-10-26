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

## Favicon: konkrete vorbereitete Abhilfe (ohne neues Motiv)

Am 26.10. wurde **das exakt gleiche** SVG-Motiv in ein WordPress-kompatibles PNG (512×512 Pixel) gerendert. Die Live-SVG-Datei und GitHub-Vorlage wurden zuvor per SHA-256 als byteidentisch geprüft. PNG-SHA-256: `e0df54cd75052526ff5ac26cc16296b10985ab6da630f41d69c87cba086a9ca9`, 26.726 Bytes. Die Original-Datei `favicon.svg` bleibt unverändert; das PNG ändert lediglich das Dateiformat, nicht das Motiv.

**Dauerhaftes GitHub-Archiv, rückgelesener Tree-Blob**: [`docs/assets/pottfuchs-bestehendes-favicon-512.png`](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/blob/pottfuchs-seo-fix/docs/assets/pottfuchs-bestehendes-favicon-512.png) (Entwicklungszweig; Git-Blob `7e35808e7b7310bc65b99f5ae6d6057a9217cb97`). [Github-Render-und-Archivierungsnachweis](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40325394310).

**Einmaliger Betreiber-Schritt**, da keine Berechtigung für ungefragte WordPress-Administration besteht: Im vorhandenen WordPress-Website-Icon-Feld das vorbereitete PNG auswählen/einsetzen. Bestehendes Theme-SVG nicht löschen. Danach prüfen, ob `/favicon.ico` nicht mehr zum WordPress-W führt; Googles Suchdarstellung benötigt erneutes Crawling und kann nicht garantiert werden. Eine Korrektur der JSON-LD-Marken und der HTML-Titel wird dadurch **nicht** ersetzt.

## Nachtrag: Favicon-Ursache aus öffentlicher WordPress-REST-API ermittelt

Im rein lesenden GitHub-Action-Test am 26.10.2026 meldet `https://www.pottfuchs.de/wp-json/` ausdrücklich:

- `name = "PottFuchs"`, `url = "https://www.pottfuchs.de"`
- `site_logo = 4215` (**Logo**-Kennung, keine Site-Icon-Kennung)
- `site_icon = 0`
- `site_icon_url = ""` (leer)

Damit ist erklärt, **warum WordPress Core den Aufruf `/favicon.ico` mit 302 zur WordPress-W-Grafik schickt**: Die öffentlich ausgelieferte Website hat derzeit aus Core-Sicht kein registriertes Website-Icon. Das korrekte `favicon.svg` im HTML-Head kommt separat aus dem Theme. WordPress-Core-Hintergrund: [`do_favicon()`](https://developer.wordpress.org/reference/functions/do_favicon/) benutzt bei fehlender Website-Icon-Option das Standard-W. [Live-REST-Prüfnachweis](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40328351939).

Das Screenshot des WordPress-Einstellungsdialogs zeigt trotzdem den gelben Förderturm als Website-Icon. **Dieser Widerspruch ist weiterhin offen**: Was der Admin-Dialog zeigt, ist nicht identisch mit der von Besuchern über REST ausgelieferten `site_icon`-Option. Ein reines Ersetzen der Theme-Datei oder Neudesign des Icons wäre die falsche Lösung; auch das HTML-Head-Link ist nicht defekt. Nur ein autorisierter WordPress-Admin-/Hosting-Schritt kann den produktiven Icon-Zustand ändern.

**Minimaler, sicherer Betreiberpfad (ohne neues Motiv):**
1. Im bestehenden WordPress-Einstellungsdialog das bereits vorhandene gelbe Förderturm-Icon erneut auswählen und die Seite wirklich speichern; dabei kein neues Bild gestalten. Anschließend zuerst den öffentlichen REST-Wert `site_icon` und die `/favicon.ico`-Weiterleitung nachprüfen. Ergibt der Wert weiterhin `0`, nicht durch wiederholte Uploads experimentieren; dann konkrete WordPress-Options-/Media-Zuordnung gesondert diagnostizieren.
2. Als **separater technischer Fallback** ist eine echte Root-Datei `favicon.ico` aus genau demselben SVG generiert, die ein Administrator ohne zusätzliches Plugin im Webroot ablegen könnte. WordPress/Core lässt reale Dateien durch den Webserver direkt ausliefern. Das ist derzeit **nur eine geprüfte Datei, keine Installation oder Freigabe zum unkontrollierten Überschreiben**.

Abgeleitet aus exakt dem Live-SVG mit SHA-256 `16bf0971d5c1bf85e22f7898d78ee5ea7fb9dd05b133fe3f705b6fb96b6333ad`:

- [Originalgetreues Favicon-ICO, GitHub-Entwicklungszweig](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/blob/pottfuchs-seo-fix/docs/assets/pottfuchs-bestehendes-favicon.ico)
- ICO-Formate enthalten **16, 32, 48, 64, 128 und 256 Pixel**; Dateigröße **28.849 Byte**.
- ICO-SHA-256 `a367a6ea0b7ba28b4bdf34ebf4f1e82e6ebcb6e10f08619329bcb68e9bada9c5`; identisch zwischen GitHub-Actions-Build und lokal unabhängig erstellter Datei.
- [GitHub-Build- und Archivierungsnachweis](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40328688469). Der Datei-Blob ist dauerhaft im Branch versioniert.

Es erfolgte **keine Änderung an der Live-Website**, an der Datenbank oder an Hosting. Eine Bereitstellung im Root wäre eine separate Freigabe-/Betreiberhandlung; kein neues Plugin.

## Nachkontrolle nach vom Betreiber bestätigtem Speichern – 26.10.2026, 15:10 Uhr MESZ

Der Betreiber hat die erneute Auswahl/Speicherung des bestehenden WordPress-Website-Icons bestätigt. Anschließend erfolgte ein **neuer, lesender öffentlicher Live-Aufruf**, mit einmaligem Zufallsparameter zur Cache-Umgehung. [GitHub-Actions-Nachweis](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40337026066).

**Beobachtet:** `/wp-json/` und die Cache-umgehende REST-Abfrage geben weiterhin `site_icon: 0`, `site_icon_url: ""`, `site_logo: 4215` zurück. Das Root-`/favicon.ico` liefert weiterhin **302 → `/wp-includes/images/w-logo-gray-white-bg.png`**. Gleichzeitig bleibt die Theme-SVG-Einbindung richtig und die Datei erreichbar (SHA-256 byteidentisch). **Kein öffentlicher Erfolg nach der WordPress-Speicherung nachweisbar.**

Die bereits erfolgte Aktion nicht nochmals verlangen; **keine weiteren mehrfachen Icon-Uploads oder Try-and-Error-Schritte**. Sicherer nächster Schritt ist eine lesende Prüfung der realen Site-Icon-Option und etwaiger Filter `pre_option_site_icon`/`option_site_icon`, der WP-Admin-Werte und des tatsächlich angesprochenen WordPress-Site-Kontexts sowie der `/favicon.ico`-Behandlung. Diese WordPress-Administrations- und Hostingprüfung erfordert den ausdrücklichen Livezugriffsauftrag oder eine entsprechende vom Betreiber bereitgestellte Diagnose. Externe Lesetests allein können den falschen Core-Wert nicht beheben. Keine automatische Datenbankkorrektur und kein neues Plugin ohne ausdrückliche Freigabe.

Diese Nachkontrolle betrifft nur das Favicon; die bestehenden getrennten SEO-Markenfehler in `WebSite`/`Organization` und Detailseitentiteln sind unverändert offen.


## Ursachenprüfung 26.10.2026 – getrennte Root-Icon-Ausgabe, keine 301-Ursache

[Cross-Host-Icon-Audit mit Code, Syntaxprüfung und Live-Protokoll](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40338892326) wurde rein lesend ausgeführt. Die neue kanonische REST-Index-Antwort von `https://www.pottfuchs.de/wp-json/` zeigt bei Cache-Busting weiterhin `site_icon: 0`, `site_icon_url: ""`, `site_logo: 4215`.

- Die Alt-Domain-Varianten `https://www.ruhrpottguru.de/favicon.ico` und `https://ruhrpottguru.de/favicon.ico` antworteten mit pfadgleichem **301** auf die neue kanonische Domain. Ebenso leitet `https://pottfuchs.de/favicon.ico` per **301** auf `https://www.pottfuchs.de/favicon.ico` weiter. Diese Regeln sind **nicht** die Ursache des W.
- Erst **auf der neuen kanonischen WordPress-Domain selbst** liefert `/favicon.ico` einen **302** auf `/wp-includes/images/w-logo-gray-white-bg.png`. Das ist die WordPress-Core-Fallbackantwort bei nicht konfiguriertem `site_icon`, bestätigt durch den offiziellen Core-Code [`do_favicon()`](https://developer.wordpress.org/reference/functions/do_favicon/). Das Theme-Head-SVG ist getrennt davon vorhanden, gültig und online.
- Media ID `4215` ist **nicht das quadratische Favicon**, sondern der transparente Header-/Website-Logo-PNG-Anhang `cropped-Pottfuchs_Web_Transparent.png` mit **1455 × 719** Pixeln, MIME `image/png`. Keine Verwechslung `site_logo` ↔ `site_icon`.
- Bereits das dem Projekt vorliegende `Pottfuchs-Domainwechsel-Cutover-Runbook-v1.0-2026-10-23.md` (Abschnitt 3.1, Grundlage: damalige WPVibe-Liveinventur) führt `WP-Site-Icon aktuell 0` auf. **Diese Konfiguration war also bereits vor dem Domain-Cutover auffällig.** Daher keine neuen 301-Anpassungen zur Favicon-Reparatur vornehmen.
- Der öffentliche Core-REST-Index gibt den Wert via `get_option('site_icon', 0)` aus ([WP_REST_Server::add_site_icon_to_index](https://developer.wordpress.org/reference/classes/wp_rest_server/add_site_icon_to_index/)). Der HTTP-302-Fallback und REST 0 widersprechen nicht einander, sondern sind dieselbe WordPress-Option.
- Im derzeit archivierten Theme v345 wird keine `site_icon`-Option gesetzt/überschrieben; `header.php` gibt nur ein unabhängiges `favicon.svg`-Tag aus. Auch das archivierte Places-Backend v1.10.169 enthält keine `site_icon`-Logik. Das ist **keine Prüfung** der aktuell installierten v351/v1.10.184/WPCode-Versionen oder aktiver MU-Plugins.

**Bereits ohne Live-Adminzugriff vorbereitet:** Das vorhandene Originalmotiv liegt in GitHub als korrekt mehrgrößiges `favicon.ico` mit sechs Auflösungen und als 512×512-PNG. Eine auf genau `favicon.ico` benannte Datei kann als *serverseitiger Root-Datei-Fallback* in den bestehenden gemeinsamen 1blu-Webroot `/www/` hochgeladen werden. Laut Betreiber-Runbook zeigen alte und neue Domains auf denselben Webspace. Bei normaler WordPress-/Apache-Rewritekonfiguration wird eine physisch vorhandene Datei unter `/favicon.ico` unmittelbar geliefert und der WP-Fallback nicht aufgerufen. **Diese Maßnahme ist noch NICHT durchgeführt; vor Installation bestätigen, dass der echte aktuelle Webroot weiterhin `/www/` ist.** Live-Testkriterium: `https://www.pottfuchs.de/favicon.ico` muss ohne Redirect mit HTTP 200 und `image/x-icon` (oder kompatibler ICO-MIME) antworten; ansonsten zurückrollen. Root-ICO ist nur eine Lösung für den Favicon-Pfad und behebt die WordPress-REST-Option `site_icon=0` oder SEO-JSON-LD nicht.

Eindeutiger Entwickler-Status: Alt-301-Rewrite **nicht ändern**; Website-Icon im Admin **nicht mehrfach neu auswählen**; aktuelle WP-Site-Icon-Option bzw. filternde Plugins vor einem Schreibzugriff read-only ermitteln. Direkte Live-/DB-/Hostingänderung weiter nur nach gesonderter Freigabe.

## Verifizierter Upload durch Betreiber – 26.10.2026, HTTP-Nachtest

Nach Betreiberbestätigung einer neuen Datei im gemeinsamen 1blu-Webroot `/www` wurde ein neuer öffentlicher, rein lesender GitHub-Actions-Test durchgeführt: [Run 40341074322](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40341074322).

**Positiv:** `https://www.pottfuchs.de/favicon.ico` liefert nun **HTTP 200 statt 302**; der echte Webroot stimmt somit für diese Datei. Alte Domainvarianten behalten die richtigen **301** zur neuen Domain. Kein WPVibe/keine Änderungen durch den Assistenten.

**Noch falsch:** Der Server liefert unter der Endung `.ico` tatsächlich einen **PNG-Dateiinhalt**, nicht eine echte ICO-Datei. Der öffentliche Response hat `Content-Type: image/vnd.microsoft.icon`, aber **PNG-Magic** (89 50 4E 47...) und SHA-256 `e0df54cd75052526ff5ac26cc16296b10985ab6da630f41d69c87cba086a9ca9` mit 26.726 Bytes, byteidentisch zur zuvor hochgeladenen `/PottFuchs-Favicon-512x512.png`. Der Betreiber hat also effektiv das PNG lediglich auf `favicon.ico` umbenannt. Manche Browser erkennen das Format dennoch, Google/Clients können die fehlende echte ICO-Struktur aber beanstanden.

**Eine einmalige, konkrete Korrektur:** `/www/favicon.ico` **durch die echte, bereits erstellte ICO-Datei** ersetzen (nicht PNG umbenennen), archiviert unter [docs/assets/pottfuchs-bestehendes-favicon.ico](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/blob/pottfuchs-seo-fix/docs/assets/pottfuchs-bestehendes-favicon.ico). Erwartete ICO-Magic: `00 00 01 00`, Größe 28.849 Bytes, sechs Icon-Einträge, SHA-256 `a367a6ea0b7ba28b4bdf34ebf4f1e82e6ebcb6e10f08619329bcb68e9bada9c5`. Danach öffentlich erneut prüfen.

Unverändert offen: Core-REST `site_icon=0`; Root-ICO umgeht nur den Fallback, setzt die WordPress-Option nicht. SEO-Markenreste sind separat zu korrigieren.

## Abnahme Favicon-Rootdatei nach Betreiberupload – 26.10.2026

**Live-Fix erfolgreich nachgewiesen.** Der Betreiber hat die zuvor fälschlich nur umbenannte PNG unter `/www/favicon.ico` durch die tatsächlich generierte ICO-Datei ersetzt. Danach bestand der neue, rein lesende, Cache-umgehende öffentliche GitHub-Actions-Test: [Run 40341937067](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40341937067).

- `https://www.pottfuchs.de/favicon.ico`: **HTTP 200**, **ohne Redirect**, `Content-Type: image/vnd.microsoft.icon`.
- **Echtes ICO**, Dateikennung `00 00 01 00`, **6 Icon-Einträge**.
- **28.849 Bytes**; SHA-256 **`a367a6ea0b7ba28b4bdf34ebf4f1e82e6ebcb6e10f08619329bcb68e9bada9c5`** — exakt identisch mit dem GitHub-Original des bereits vereinbarten Förderturm-Motivs auf dem Entwicklungszweig.
- Die alte Domain und Non-www bleiben auf ihren geprüften Favicon-Pfaden beim gewünschten **301** zur neuen kanonischen Domain.
- Einschränkung: WordPress-REST meldet weiterhin **`site_icon: 0`** und leere `site_icon_url`. Das separate Root-ICO behebt **die öffentliche /favicon.ico-Auslieferung**, nicht die interne Site-Icon-Option. Googles konkrete Auswahl ist nicht unmittelbar verifizierbar und kann nach Crawling zeitversetzt aktualisiert werden.
- **Keine weiteren Betreiberhandlungen am Favicon nötig.** Nicht nochmals Datei umbenennen/hochladen, nicht an der 301-Konfiguration drehen. Spätere WordPress-Optionsdiagnose nur bei tatsächlich benötigter Core-Icon-Funktion und mit gesonderter Freigabe.

**Status:** Öffentliche Favicon-Rootauslieferung 🟢 bestanden und abgeschlossen. Allgemeine SEO-Rebranding-Korrektur (Titel, WebSite-/Organization-JSON-LD) bleibt separat offen.
