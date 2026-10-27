# PottFuchs Places 1.10.191 (Entwurf, Stand 27.10.2026)

Basis: Release 1.10.190, Quellcommit fd6e0818. Ein Plugin-Update, zwei kleine Änderungen an der bestehenden Event-Schnittstelle `POST /wp-json/ruhrpottguru/v1/events`. Keine neue Schnittstelle, keine Änderung an Antwortfeldern, Kategorien, Ranking, Radius, Filtern oder SEO.

1. **Komprimierung (gzip):** Die Event-Antwort wird komprimiert ausgeliefert, wenn der Client gzip anmeldet und Server/PHP nicht schon komprimieren. Gemessen am 27.10.2026 vor der Änderung: unkomprimiert, Essen 5 km = 4,99 MB (5,5 s), Essen 50 km = 13,4 MB (14,4 s). Mit den archivierten Echtdaten (Essen 5 km, 5,26 MB) ergibt gzip 0,46 MB (9 %). Abschalten: `define('RPG_DISABLE_EVENTS_GZIP', true);` in wp-config.php.
2. **Optionales Feld `untilDays` (1–730):** Begrenzt, wie weit in die Zukunft Events *beginnen* dürfen. Ohne das Feld oder bei ungültigem Wert: Verhalten unverändert. Webseite und installierte ältere Apps senden es nicht. Die neue App (Version 43) sendet `untilDays: 60` und lädt den Rest bei Bedarf nach.

## Tests
- `tests/events_gzip_unit.php` (ohne WordPress lauffähig): 8 Prüfungen bestanden (identisches JSON, kein Accept-Encoding, identity, q=0, q=0.8, Nicht-200, andere Route, zlib bereits aktiv).
- `tests/city_scope_wordpress.php`: 6 neue Prüfungen für `untilDays` ergänzt. **Nicht ausgeführt** (braucht WordPress + MariaDB wie in der GitHub-Automatik).
- PHP-Syntaxprüfung beider Dateien bestanden.
- **Nicht getestet:** Installation auf der Live-Seite, Verhalten hinter dem Server von 1blu, Zusammenspiel mit evtl. vorhandenem Seiten-Cache.

## Installation und Rückgang
Plugin-ZIP in WordPress hochladen und die bestehende Version ersetzen. Danach prüfen: Antwort von `/events` hat `Content-Encoding: gzip`, Webseite und App zeigen Events wie vorher.
Rückgang: ZIP von Version 1.10.190 (Release `pottfuchs-places-v1.10.190`) erneut installieren. Schnellster Notausgang ohne Neuinstallation: `RPG_DISABLE_EVENTS_GZIP` in wp-config.php setzen.

## Ablage
Das ZIP liegt direkt im Zweig (SHA-256 in `SHA256SUMS.txt`), weil das Anlegen eines GitHub-Releases von dieser Umgebung aus nicht möglich war (keine gültige GitHub-Anmeldung für Releases).
