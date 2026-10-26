# PottFuchs v1.10.190 – produktive Installation und Abnahme

Datum: 26.10.2026. Betreiber meldet Installation; aktive Pluginliste bestätigt Version 1.10.190 mit Deaktivieren-Aktion. Vollständige Hauptdatei authentifiziert rein lesend aus WordPress exportiert und mit Releasequelle verglichen: identische SHA-256 bca751f2f85467882ce74c6599c988a633bda1127b0a4034253cffd888ca1a2d. Keine Live-Codeänderung durch den Assistenten.

## Produktiver REST-/Datenpfad
Zehn echte Besucherabfragen (bestehender POST-Endpunkt /wp-json/ruhrpottguru/v1/events, ausschließlich Suchdaten) auf fünf Städte mit 5 und 10 km bestanden. Alle Antworten HTTP 200, ok, ready und cache=DB. CI: https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40409591912 (erfolgreich, 22:05:28 UTC = 23:05:28 Europe/Berlin).

| Stadt | Radius | Gesamt | Präzise verortet | Nur Stadtbezug |
|---|---:|---:|---:|---:|
| Duisburg | 5 km | 6 | 6 | 0 |
| Duisburg | 10 km | 25 | 25 | 0 |
| Wesel | 5 km | 216 | 174 | 42 |
| Wesel | 10 km | 226 | 184 | 42 |
| Selm | 5 km | 43 | 6 | 37 |
| Selm | 10 km | 98 | 61 | 37 |
| Essen | 5 km | 290 | 290 | 0 |
| Essen | 10 km | 316 | 316 | 0 |
| Dortmund | 5 km | 9 | 9 | 0 |
| Dortmund | 10 km | 21 | 21 | 0 |

Für alle City-Scope-Treffer: öffentliche Entfernung null, keine lat/lng/geo-Felder, Kennzeichnung city, Label entspricht tatsächlich angefragter Stadt. Anzahl und IDs dieser städtischen Kandidaten zwischen 5 und 10 km unverändert. Für alle präzisen Treffer: echte Koordinaten, Entfernung mit Haversine unabhängig nachgerechnet und innerhalb angefragtem Radius. Stadtbezogene Kandidaten sind keine bestätigten Radius-Treffer. Selm mit 37 Stadt-Selm-Kalenderterminen liefert den produktiven positiven Nachweis außerhalb Wesels; Weseler Bestandsverhalten mit 42 Stadtterminen bleibt erhalten.

Duisburg-Zahlen betreffen den gesamten zukünftigen Feed, nicht ausschließlich Heute. Eine fehlende heutige Quellabdeckung wird nicht als durch v190 behoben behauptet.

## Tatsächlich geprüfte Web-Ansichten
Öffentlicher UI-Einstieg /veranstaltungen/?place=selm&radius=5 geladen; Liste enthält stadtbezogene Events ohne Entfernungsangabe sowie präzise verortete Events mit echter Entfernung.

- OasenTag #19722 (Stadt Selm): Karte ohne Bild sichtbar; Detail öffnet. Vorhandene Adresse Dieselweg 7, 59379 Selm sichtbar. Kein Routenziel/keine Entfernung erfunden; Route-planen-Link fehlt. Beide Kalenderaktionen und Location-Chip vorhanden.
- Der kleine Prinz – Jugendkunst-Projekt #19007: nur 59379 Selm als Adresse, keine erfundene Straße, kein Route-planen-Link. Karte und Detail sichtbar.
- Leselicht #19721: Willy-Brandt-Platz 5, 59379 Selm sichtbar, 120 m in diesem Browserkontext; Route-planen-Link mit tatsächlichem Ziel 51.6908443,7.4662943. Keine Änderung des Browsers/GPS zur Herbeiführung dieses Ziels.

Die Browseransicht ist eine produktive Stichprobe, keine Abnahme jeder Karte, jedes direkten Einstiegs, aller Kategorien oder echter Android-Hardware. Der Quellen-/Payload-/native Android-Vertrag wurde bereits vor Release gegen den tatsächlichen Android-Quellstand erfolgreich geprüft; siehe Releasebericht. Echte Geräteabnahme mangels angeschlossenem Gerät weiterhin offen.

## Betrieb und Abschlussgrenze
Importzentrale nach Update erneut lesend kontrolliert: bestehender Zusatzfeed weiterhin Abgeschlossen, 53/53, Ende 26.10.2026 21:23:27 laut WordPress, 551 neu und 7711 aktualisiert. Historischer Retry-Zähler 1 bleibt bestehen. Kein Importneustart und keine Automatikaktivierung ausgeführt.

Der freigegebene v190-Event-Backend-Fix ist installiert und produktiv mit positivem Nicht-Wesel-Fall abgenommen. Keine neue ZIP/Installation erforderlich. Globale Standortregeln in anderen Places-Kategorien/Specials sind dadurch nicht pauschal als umgesetzt erklärt.

Quellcommit fd6e0818f67a0e7a67cc01467acefef7937bcdfc; Release und unverändertes v189-Rollback: https://github.com/ruhrpottguru/ruhrpottguru-test-builds/releases/tag/pottfuchs-places-v1.10.190. Früherer Status „noch nicht installiert“ für v190 hiermit historisch überholt.
