# PottFuchs Places 1.10.193 (Entwurf, Stand 28.10.2026)

Basis: 1.10.192 (enthält dessen Zeitkorrektur). Ein Fehler, eine Hilfsfunktion, drei Lesestellen. Keine Änderung an gespeicherten Daten, Kategorien, Ranking, Radius, Filtern, SEO, Schnittstellenfeldern oder Zeitzone.

## Fehler
Preiszeilen zeigten Rohcodes der Quelle: BOSY Extra (ID 19097) „ADULT: 22“ statt „Erwachsene: 22 €“. In der Datenbank steht `PRICE_ADULT` / `22`; der lesbare Text „Normalpreis 22 Euro, es gibt verschiedene Ermäßigungsarten.“ ist vorhanden, wurde aber von der Zeile verdrängt. Datenbankbeleg 28.10.2026 (Events ab heute): 1.153 Events mit `PRICE_…`-Codes (ADULT 1.151, REDUCED 912, CHILD 965, 2 andere).

## Änderung
Neue private Hilfsfunktion `event_prices_visitor_v101193`, nur beim Lesen für Besucher (gespeichert wird nichts, kein Neuimport nötig):
- `PRICE_ADULT` → Erwachsene, `PRICE_CHILD` → Kinder, `PRICE_REDUCED`/`PRICE_CONCESSION` → Ermäßigt, außerdem STUDENT, SENIOR, YOUTH, FAMILY, GROUP.
- Euro-Zeichen nur, wenn der Preistext des Events Euro/EUR/€ nennt und der Wert eine blanke Zahl ist („22“ → „22 €“, „15,50“ → „15,50 €“). Sonst bleibt der Wert wie er ist (keine erfundene Währung).
- Unbekannte Codes (2 Events) und normale Beschriftungen bleiben unverändert.
Eingesetzt nach dem Lesen von `price_json` in: Besuchersuche (`rest_global_search`), Event-Anreicherung (`event_apply_db_enrichment_to_item`), Event-Liste (`frontend_events_query`). Nicht angefasst: Admin-Detailansicht, Import/Speicherung.

## Tests
- `tests/event_prices_visitor_unit.php` (ohne WordPress): 13 Prüfungen bestanden (BOSY-Echtfall, ohne Währung im Text, Cent, fertiger Wert, unbekannter Code, Randfälle).
- Frühere Tests weiterhin bestanden (`event_when_berlin_unit.php` 10, `events_gzip_unit.php`). PHP-Syntaxprüfung, Dateiliste identisch zu 1.10.192.
- **Nicht getestet:** Installation live, Seiten-Cache, App-Darstellung der neuen Beschriftungen (App liest dieselben Felder).

## Installation und Rückgang
Plugin-ZIP in WordPress hochladen und die bestehende Version ersetzen. Prüfen: BOSY-Detailseite zeigt „Erwachsene: 22 €“ statt „ADULT: 22“ (Cache ggf. leeren); eine Veranstaltung mit mehreren Preisen (Erwachsene/Ermäßigt/Kinder) ansehen.
Rückgang: ZIP 1.10.192 (`release-artifacts/pottfuchs-places-v1.10.192/`) erneut installieren.
