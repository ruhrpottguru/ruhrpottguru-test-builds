# PottFuchs Places 1.10.190 – stadtbezogene Events ohne Koordinaten

## Freigabe und Ausgangsstand
Jürgen hat die zuvor konkret angefragte Erweiterung des Wesel-Sonderpfads auf alle Städte und zugelassenen Quellen ausdrücklich freigegeben. Liveinstallation ist ein separater Vorgang. Kein neuer Quellenimport, kein Importneustart, keine Automatikaktivierung.

Ausgangsbasis: vollständig authentifiziert gelesene installierte v189-Hauptdatei, SHA-256 c5351681b2a5a0006219db99df995f1201b764075a6b9531ecfbb205a47bd9b1. Sie stimmt bytegenau mit dem geprüften v189-Quellarchiv überein. Bestehende SEO-Korrekturen, Prioritätsfix v188 und Watchdogfix v189 bleiben erhalten.

## Minimaler Eingriff
Ausschließlich frontend_events_query(): Bei ausdrücklich ausgewählter Stadt bestehende aktive kanonische Events ohne vollständige Koordinaten über denselben City-Scope-Pfad zulassen. Keine neue Quellenregistrierung/-beschaffung. Stadt- und Quellsonderbegrenzung des Weseler Kandidatenpfads entfällt; feste 500er-Grenze wird durch ID-Paginierung in 300er-Schritten ersetzt. Falscher Wesel-Default im öffentlichen Label entfällt.

Bestehende Cluster-, Quellenowner-, Datum-, Medien- und JSON-Verarbeitung bleibt bestehen. Kein schema-/datenverändernder Migrationscode hinzugefügt. Exakter Haversine-Radius, Zeit-/Lifecycle-Regeln, Sortierung, GPS, Radius-Fallbacks, SEO und andere Kategorien unverändert. Ohne ausdrücklich übergebene Stadt bleibt es bei nachgewiesenen Radius-Treffern. City-Scope bedeutet Stadtbezug, keine bestätigte Lage innerhalb des gewählten Radius. Öffentliche Entfernung null, keine erfundenen Koordinaten, bekannte Adresse bleibt erhalten. Fehlende Bilder bleiben ohne Einfluss auf Sichtbarkeit.

## Prüfungen und Grenzen
CI prüft fünf Plugin-PHP-Dateien und Integrationsskript, vorhandene 227 Such-/Importassertions, vier globale Anforderungsprüfungen, ZIP-Integrität und byte-exaktes v189-Rollback. Zusätzlich echte isolierte WordPress-/MariaDB-Integration: Installation der finalen ZIP, Originalschema und vollständiger produktiver Query-/Cluster-/Payloadpfad, 701 Stadttermine über mehrere Seiten, alle 53 Kommunen, Quellenneutralität bestehender kanonischer Datensätze, fehlende/teilweise Koordinaten, bekannte Adresse, fehlendes Medium, Fremdstadt, leerer Stadtparameter, abgelaufene/inaktive/abgesagte Events, echte 5/10/50-km-Geometrie und sicher gebundener Stadtparameter. Unveränderte Web-/Android-Normalisierung wird gegen den erzeugten echten PHP-Payload geprüft. Bestehende Web-Sortier-/Halloween-/City-Scope-Tests bleiben enthalten.

Das ist keine produktive Live-Abnahme vor Installation und keine Android-Geräteprüfung. Die Freigabe betrifft diesen Event-Backendpfad, nicht eine pauschale abgeschlossene globale Standortimplementierung in allen Places-Kategorien/Specials. Die Duisburger Heute-Nulltreffer werden nicht als hierdurch behoben behauptet: im geprüften offenen Quellfeed gab es für den Tag ebenfalls keine Termine.

## Installation und Rollback
Plugins → Plugin hinzufügen → Plugin hochladen → PottFuchs-Places-v1.10.190-Stadtsichtbarkeit.zip → vorhandenes Places-Backend ersetzen. Bestehenden Pluginordner nicht vorher löschen. Kein Importneustart erforderlich.

Rollback: PottFuchs-Places-v1.10.189-Rollback.zip auf dieselbe Weise installieren. Hauptdatei wird bytegenau auf den gelesenen Live-Ausgangsstand zurückgesetzt, SEO und beide Importreparaturen bleiben darin erhalten. Vorhandene Daten/Optionen werden durch das Paket nicht gezielt verändert. Installation/Abnahme nach Betreiberbestätigung separat protokollieren.

## Tatsächliches Ergebnis
- Quell-/Release-Commit: fd6e0818f67a0e7a67cc01467acefef7937bcdfc.
- CI: https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40407428227 — erfolgreich; PHP 8.4, WordPress 6.8.3 und MariaDB 10.11.
- 2932 vollständige WordPress-/MariaDB-Assertions bestanden; Installation der echten finalen ZIP erfolgreich.
- 227 erhaltene Such-/Importassertions, vier globale Anforderungen und 16 Web-Payloadassertions bestanden; vier vorhandene Web-City-Scope-/Radius-/Sortier-/Halloween-Testskripte bestanden. Fünf Plugin-PHP-Dateien plus Integrationsskript syntaktisch fehlerfrei.
- Gegen das unveränderte v189-Rollback scheitern alle vier globalen Anforderungen erwartungsgemäß; der neue Test weist die ursprüngliche Begrenzung nach.
- Android-Quellstand 7ea6fa42134b1a14409455a8b34513606d793bee über GitHub lesend geprüft. Außerhalb der WordPress-CI 16 Assertions gegen dessen unveränderte runtime.js/api.js mit dem aus CI wiederabgerufenen WordPress-Payload bestanden. Weitere 16 Assertions gegen dessen echte category-events.js/native-adapter.js prüfen native Liste, Detail, fehlendes Bild, unbekannte Entfernung und fehlendes Routenziel bei City-Scope sowie echtes Koordinatenziel beim präzisen Event. Kein Android-Gerätetest und kein App-Release behauptet. Testwerkzeuge: tests/city_scope_clients.cjs und tests/city_scope_android.cjs; natives Quellverzeichnis als Argument, Integration-Payload aus dem verknüpften CI-Artefakt.
- Beide GitHub-Release-ZIPs tatsächlich wiederabgerufen; Integrität, SHA-256 und Quellidentität geprüft. Einziger geänderter Pluginbestandteil gegenüber Rollback ist die Hauptdatei.
- Installation: PottFuchs-Places-v1.10.190-Stadtsichtbarkeit.zip, 729544 Bytes, SHA-256 fe58db4d267e4dea92b04a0977f21488f1fdf21298cb00119d05bb27f2b4d9a0.
- Rollback: PottFuchs-Places-v1.10.189-Rollback.zip, 729483 Bytes, SHA-256 43aa7de41d0a6f4d2750df09600474b2cfb95fb2cc146ef5297d8189a2e091c5; identisch mit dem bereits geprüften v189-Installationspaket.
- Release: https://github.com/ruhrpottguru/ruhrpottguru-test-builds/releases/tag/pottfuchs-places-v1.10.190.
- Noch nicht produktiv installiert. Nach Betreiberinstallation bleibt die lesende produktive Abnahme erforderlich; der bisher abgeschlossene 53/53-Import wird nicht erneut gestartet.
