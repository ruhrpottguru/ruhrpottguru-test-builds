# 0.4.0 (04.11.2026) – Stufe 2: Reservix-Termine ohne eigenen Eintrag als Karten der Veranstaltungsliste
- Neue Client-Fähigkeit `partner-items-v1` (zusätzlich zu `partner-v1`), nur Hauptliste, nur ohne `untilDays` (keine Specials), nur mit Freigabe `PFT_TICKETPARTNERS_PUBLIC_APPROVED` und frischem Import (<36 h).
- Je Reservix-Veranstaltung (Titel+Stadt+Ort) EINE Karte (Serie): `rpg_event_series` (count/first/last/nearTerm/laterSeries) im Format der eigenen Events; bis zu 20 Termine mit eigenem Link in `rpg_partner_item`; "Weitere Termine bei Reservix ansehen und buchen" verweist auf den nächsten Termin.
- Gibt es zu irgendeinem Termin der Serie schon ein eigenes Event, erscheint KEINE zusätzliche Karte (nur der Zusatz-Button aus 0.3.0).
- Umkreis (lat/lng/maxRadiusKm der Anfrage), höchstens 1500 Serien, Genre "Gutscheine" ausgeschlossen, ein Anbieter je Event (Reservix vor Eventim).
- Bilder: nur cdn.reservix.com (Hotlink, `PFT_RESERVIX_IMAGE_HOTLINK_APPROVED`), als `rpg_event_media` mit Quelle/Credit Reservix; fehlendes Bild blendet nie eine Karte aus.
- Schreibt nichts in rg_events; Datenbank-Schema unverändert (DB_VERSION 2). Fail-open: bei jedem Fehler Originalantwort.
- Rollback: Plugin 0.3.0 erneut installieren oder `PFT_TICKETPARTNERS_PUBLIC_APPROVED` entfernen.

# 0.3.0 (03.11.2026) – Stufe 1: Ticket-Angebot an bestehenden Events

Neu: Filter `rest_post_dispatch` auf `POST /wp-json/ruhrpottguru/v1/events`. Er ergänzt bestehende Events der Eventliste um `rpg_partner_offers` (ein Eintrag: provider, url, label "Tickets bei Reservix", adLabel "Anzeige", rel "sponsored noopener noreferrer", nextStart in UTC).

Schutz (alles muss zutreffen, sonst bleibt die Antwort BYTE-GLEICH wie bisher):
- `PFT_TICKETPARTNERS_PUBLIC_APPROVED === true` UND Speicherfreigabe UND letzter Import jünger als 36 Stunden
- Anfrage ist `POST` auf genau diese Route, Antwort HTTP 200 mit `ok` und `items`
- Der Client sendet im JSON-Body `"clientCaps":["partner-v1"]` (alte Web-Caches und alte App-Versionen tun das nicht)
- Bei jedem Fehler (Datenbank, unerwartetes Format) wird die ursprüngliche Antwort geliefert (fail-open)

Regeln: Pro Termin nur EIN Anbieter (Rangfolge Reservix vor Eventim, änderbar mit `PFT_PROVIDER_PRIORITY`, kein Preisvergleich). Bei Serien führt der Link zum nächsten zukünftigen passenden Termin. Vorhandene Felder (auch `ticketUrl`) werden nicht verändert, nur `rpg_partner_offers` und `meta.partners` kommen hinzu. Genre-Sperrliste standardmäßig nur "Gutscheine" (Entscheidung Nutzer 03.11.2026), änderbar mit `PFT_RESERVIX_GENRE_DENY`.

NICHT enthalten: Anzeige im Frontend (Theme/App müssen `clientCaps` senden und `rpg_partner_offers` darstellen), Reservix-Karten für Termine ohne eigenes Event (Stufe 2), eigene Detailseiten (Stufe 3). Ohne Client-Änderung ist nach der Installation für Besucher nichts sichtbar.

Rollback: Plugin deaktivieren oder `PFT_TICKETPARTNERS_PUBLIC_APPROVED` entfernen. Die Tabelle bleibt unverändert (DB_VERSION 2, keine Schemaänderung).

# PottFuchs Ticketpartner Backend 0.2.1

**Lieferart:** installierbare WordPress-Plugin-ZIP. **Status:** passiver Backend-Unterbau, keine Freigabe zur produktiven Reservix-Datennutzung und keine Frontend-Integration.

## Zweck

Anbieterneutrale, getrennte Datenebene zur Vorbereitung von Ticketpartnern. Reservix-CSV (Awin-Spalten wie in der bereitgestellten Datei) wird lesend geprueft, nach den 53 Ruhrgebiets-Kommunen und zukuenftigen Terminen gefiltert und erst bei bestaetigter Speicherfreigabe importiert. Eventim besitzt bewusst **nur einen vorgesehenen Provider-Slot**; sein Schema wird nicht geraten.

**Beschaedigt keine bestehende Eventarchitektur:** Das Plugin schreibt **nicht** in `wp_rg_events`, erweitert weder die bestehende REST-Eventliste noch Theme/App und aendert keinerlei Klassifikation, Ranking, Radiusregeln, Synchronisierung, SEO oder Detail-URLs. Das Plugin legt ausschliesslich eine **eigene, anfangs leere** Tabelle `<WP-Präfix>pft_partner_occurrences` an. Kein Zugriff auf Reservix/Adserver ohne explizit konfigurierte Rechte.


## Änderungen in 0.2.1 (02.11.2026)

- Feed-Dateien im gzip-Format (Awin-Link mit `compression/gzip`) werden jetzt erkannt und direkt gelesen. Getestet mit der echten Feed-Datei als .gz (gleiches Ergebnis wie unkomprimiert: 2.189 Termine). Sonst unverändert gegenüber 0.2.0.

## Änderungen in 0.2.0 (02.11.2026)

- **Reservix-Rechte geklärt (schriftlich, 02.11.2026):** tägliche Aktualisierung nötig, Bilder nur per Reservix-Adresse (ohne Kopie), vergangene bzw. aus dem Feed verschwundene Events spätestens am Folgetag entfernen, Android-App erlaubt. Eigene Detailseiten für nur bei Reservix vorhandene Events: nicht beantwortet, bleibt aus.
- **Toleranter Abgleich mit bestehenden Events (nur lesend):** gleiche Stadt, Startzeit als Zeitstempel (rg_events speichert UTC, Reservix-Feed Berliner Zeit) innerhalb ±3 Stunden, Titel gleich oder ähnlich (Zusätze wie "- Tourname", Präfixe, GROSSBUCHSTABEN, "AUSVERKAUFT"), Spielstätte ähnlich. Ersetzt den bisherigen reinen Identitätsvergleich.
- **Neue Spalte `genre`** (Datenbankversion 2, wird beim Laden des Plugins automatisch ergänzt). Standardmäßig ausgeblendet: Führungen, Erlebnisgastronomie, Tourismus, Bus & Schifffahrten, Gutscheine. Änderbar mit `define('PFT_RESERVIX_GENRE_DENY', array(...));` in wp-config.php.
- Weiterhin inaktiv ohne Freigabe-Schalter. Keine Änderung an Events, Places, Theme oder App.

**Test 0.2.0 (ohne WordPress, ohne MySQL):** PHP-Syntax; Probelauf mit der echten Feed-Datei (2.189 Termine); Abgleich gegen 43 echte eigene Events: 35 Treffer, die übrigen 8 stehen nicht im Feed; keine Treffer bei 5 Stunden Zeitverschiebung; Genre-Sperre blendet 144 von 2.189 aus. **Nicht getestet:** Aktivierung, Datenbank-Upgrade, REST-Antworten, MySQL.

**Update von 0.1.0:** ZIP über das alte Plugin hochladen (ersetzen); danach wird die Spalte `genre` automatisch angelegt. Rollback: 0.1.0-ZIP erneut hochladen (die zusätzliche Spalte stört nicht).

## Installieren (noch NICHT zur Aktivierung empfohlen)

WordPress > Plugins > Installieren > Plugin hochladen > `PottFuchs-Ticketpartner-Backend-v0.1.0.zip` hochladen. Aktivierung erzeugt eine leere eigene Datenbanktabelle. Ohne konfigurierte Rechte passiert sonst nichts. Es werden **keine** Eventdaten eingelesen, keine Bilder kopiert, keine Frontend-Karten und keine SEO-Detailseiten erzeugt. Die Installationsfreigabe bleibt beim Betreiber; keine Live-Installation wurde durchgeführt.

**Rechtefreigabe noch offen:** Reservix muss Titel/Datum/Ort/Link-Speicherung, Bild-Hotlinking, Entfernen ab Start/Folgetag, App-Nutzung und Feed-Bezug beantworten. Nicht allein aufgrund dieses Pakets importieren.

## Nach schriftlicher Rechtefreigabe (separate Freigabe nötig)

Nur nach positiver schriftlicher Klarstellung und konkreter Freigabe für die Konfiguration **in wp-config.php**:

```php
// NUR NACH gesicherter Reservix-Zustimmung und Betreiberfreigabe setzen!
define('PFT_RESERVIX_STORAGE_APPROVED', true);
// Falls das direkte Einbetten von Bild-URLs gesondert bestätigt ist:
define('PFT_RESERVIX_IMAGE_HOTLINK_APPROVED', true);
// Feed-URL gemäß Awin-Dokumentation, HTTPS; nur zugelassene Awin-Hosts:
define('PFT_RESERVIX_FEED_URL', 'https://productdata.awin.com/DEIN-FREIGEGEBENER-FEED');
// Nur nach gesondert geprüfter Hosting-Last / Zeitplanfreigabe:
define('PFT_RESERVIX_AUTO_REFRESH_APPROVED', true);
// NUR nach abgeschlossenen Daten-, Gruppen-, Dedupe-, Radius- und Web-/App-Tests:
define('PFT_TICKETPARTNERS_PUBLIC_APPROVED', true);
```

Keine echte URL / Zugangsdaten in GitHub, Plugin oder Tickets veröffentlichen. Eine tägliche WP-Cron-Aktualisierung startet erst nach der zusätzlichen AUTO_REFRESH-Freigabe; andernfalls ausschließlich manueller WP-CLI-Import. Feed wird temporär zur Verarbeitung geladen und umgehend gelöscht; Bilder werden **nie lokal heruntergeladen**. Die temporäre Verarbeitung ist ebenfalls bei Reservix rechtlich zu klären. Bei Fehler/Schrumpfung wird keine alte Datenmenge blind gelöscht, aber eine veraltete Bereitstellung wird nach 36 Stunden automatisch unterbunden. Ohne Endzeit gilt konservativ: ab Startzeit ist das Angebot nicht mehr öffentlich.

Optional WP-CLI (falls verfügbar):

```
wp pftickets import reservix /pfad/zur/feed.csv --dry-run
wp pftickets import reservix /pfad/zur/feed.csv --confirm
wp pftickets status
```

Der Schreibbefehl wird ohne gesetzte Speicherfreigabe verweigert. **Es ist kein Upload der 111-MB-CSV ueber WordPress erforderlich.**

## Backend-Schnittstellen (derzeit inert)

- `GET /wp-json/ruhrpottguru/v1/ticket-partners/catalog?city=Bochum&limit=30` – kuratierungsfreie Partnerkandidaten in einer städtischen Ansicht (nicht die bestehende Ranking-Suche). Nur nach beiden Freigaben und aktuellem Feed. Liefert eine pro Identitaet gruppierte Partnerkachel-Vorlage mit direkten Awin-URLs. **Noch keine finale sichtbare Eventliste**.
- `GET /wp-json/ruhrpottguru/v1/ticket-partners/event/123` – konservative, exakte Titel/Ort/Datum-Kandidaten zu einem vorhandenen Event. Kein automatischer Patch seiner Detailseite.
- WP-Admin unter **Werkzeuge → PottFuchs Ticketpartner**: nur Rechte-/Laufstatus, keine gefährlichen öffentlichen Schreibschalter.

**Wichtig:** Die bestehenden `event_listing_cluster_rows()` und Kategorie-/Radiusverträge des Places-Backends sind bei der spaeteren Integration die einzige authoritative Gruppierungs- und Zulassungslogik. Die Partner-REST-Schnittstelle ist bewusst nur ein vorbereitender Katalog-/Angebotsbaustein. Der Dedupe-Abgleich ist aktuell konservativ (gleiche Identität plus Startnähe) und ersetzt *keinen* Web/App-E2E-Test; bei abweichenden Titeln/Serien kann weiterer Abgleich erforderlich sein. Keine Mehrfachkarte pro Uhrzeit als Produktziel. Kein Ticketkauf und keine Reservix-eigene Event-Detailseite in PottFuchs.

## Rollback

Plugin deaktivieren: stoppt den täglichen Scheduler und jede Nutzung der neuen Endpunkte; bestehende Events bleiben unverändert. Optional Plugin löschen. Die eigene Tabelle verbleibt absichtlich, damit keine Daten versehentlich verloren gehen. Nach rechtlich gebotener Löschung separat und nur mit ausdrücklicher Betriebsfreigabe `DROP TABLE <praefix>pft_partner_occurrences;` durchführen (entsprechendes aktuelles Präfix vorher prüfen). **Keine** Änderungen an Places, Theme oder Android sind zurückzunehmen.

## Qualitätsstand

PHP 8.4 CLI Syntaxprüfung; isolierte Parser-/Sicherheits-Tests, vollständiger read-only-Scan des bereitgestellten 60.720-Zeilen-Feeds. **Nicht** durchgeführt: echte WordPress-7.1/MySQL-Installationsintegration, Produktiv-GPS-/Radiusregression, UI-, Android- oder Live-Feed-E2E, Verfügbarkeit von Awin-Feed-URLs und Reservix-Rechtefreigaben.

Keine Feed-Datei, Partnerdaten, Tokens, Schlüssel, Bilddateien oder privaten Reservix-Mails sind Bestandteil der ZIP.
