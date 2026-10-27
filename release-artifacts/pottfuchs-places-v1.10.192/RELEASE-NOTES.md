# PottFuchs Places 1.10.192 (Entwurf, Stand 28.10.2026)

Basis: 1.10.191 (Commit 2450d49). Ein Fehler, zwei Anzeigestellen, minimaler Diff. Keine Änderung an Daten, Kategorien, Ranking, Radius, Filtern, SEO, Schnittstellen oder WordPress-Zeitzone.

## Fehler
Die eigene Event-Detailseite `/veranstaltung/<id>/…` zeigte im Detailbereich die gespeicherte Weltzeit (UTC) ohne Umrechnung: BOSY Extra (ID 19097) 19:30 statt 20:30 (Quelle ADticket 20:30). Datenbankbeleg 28.10.2026: `start_at` = `2026-11-06 19:30:00` (UTC). WordPress-Zeitzone ist UTC+0 und bleibt so (Umstellung würde alle Events/Zeitpläne verschieben).

## Änderung
Neue private Hilfsfunktion `event_when_berlin_v101192`: liest `start_at` als UTC (oder in `_rpg_visitor_timezone`, falls die Zeile eine hat) und gibt `j. F Y · H:i` in Europe/Berlin aus. Ganztagstermine (`all_day`) bleiben unverändert. Eingesetzt an zwei Stellen:
1. Detailbereich der Detailseite (`$when`, Zeile ca. 2378).
2. Teilen-Text-Fallback (`$fallback`, Zeile ca. 1868).
Nicht angefasst: Vorschautext/Meta-Description (WPCode #4102, rechnet bereits richtig), Fenster über der Karte (React, rechnet richtig, am Beispiel Kummerschalen Wesel belegt), Kartenliste (Zeile ca. 3372).

## Tests
- `tests/event_when_berlin_unit.php` (ohne WordPress): 10 Prüfungen bestanden (Winterzeit, Sommerzeit, Umstellungstag 25.10., Ganztag, eigene Zeitzone, leer/ungültig).
- `tests/events_gzip_unit.php` weiterhin bestanden. PHP-Syntaxprüfung der Hauptdatei und der entpackten ZIP-Datei bestanden. Dateiliste des ZIP identisch zu 1.10.191.
- **Nicht getestet:** Installation auf der Live-Seite, Seiten-Cache. Nach der Installation prüfen: `/veranstaltung/19097/bochum/bosy-extra-geburtstags-lounge/` zeigt im Detailbereich 20:30 (Cache ggf. leeren). Gegenprobe mit einem zweiten Event.
- **Offen (nicht Teil dieses Pakets):** andere Stellen mit `wp_date(…strtotime(start_at))` wurden nicht gesucht außer den zwei genannten; Prüfung weiterer Detailseiten-Texte nach der Installation.

## Installation und Rückgang
Plugin-ZIP in WordPress hochladen und die bestehende Version ersetzen. Rückgang: ZIP 1.10.191 (`release-artifacts/pottfuchs-places-v1.10.191/`) erneut installieren.
SHA-256: siehe `SHA256SUMS.txt`.
