# PottFuchs Places v1.10.187 – nur Entwicklungsstand, nicht freigegeben

Ausgangsdatei: lokal vorliegendes Original `PottFuchs-Places-Backend-v1.10.186-Import-Diagnose-FINAL.zip` (SHA-256 `edc6c5ea145823d1400118e6908333d12b41b5bcc4a2dca2ab0b22fdb280df29`). **Nicht als mit der Live-Installation abgeglichen.**

## Gezielter Backend-Diff
Im `frontend_events_query()` ersetzt der geplante Patch die Wesel-only-SQL-Zulassung und die fixe 500er-Grenze durch einen nur bei ausdrücklich angegebener Stadt aktiven, quellenneutralen City-Scope-Pfad für aktive Eventzeilen mit fehlenden Koordinaten. SQL sucht `e.city=%s AND e.id>%d` und paginiert `ORDER BY e.id ASC LIMIT 300` bis zum Ende, ohne ein stadtübergreifendes Radiusversprechen. Bereits vorhandene `_rpg_city_scope`-Kennzeichnung und `rpg_geo_scope='city'` werden beibehalten; nur der erfundene Default `'Wesel'` beim City-Label wird entfernt. Der genaue Haversine-Radius-Algorithmus bleibt unverändert. Version im Header / Konstante 1.10.187.

## Lokaler Nachweis, jedoch keine Release-Freigabe
- Ausgangsarchiv und resultierendes Archiv: ZIP-Integrität bestanden.
- PHP-Lint: **5/5** PHP-Dateien bestanden.
- 16 statische Vertragsprüfungen gegen den Entwicklungsstand bestanden.
- Eine SQLite-Simulation mit 701 gültigen Duisburger Eventzeilen aus drei Quellen und weiteren Fremd-/inaktiven/mit Koordinaten versehenen Zeilen: 701/701 ohne Quell- oder 500er-Verlust in 300er-Schritten gefunden. Dies ist **kein WordPress-E2E**.
- Ergebnis-ZIP nur als **ENTWICKLUNGSSTAND**, Prüfsumme SHA-256 `4b696d6e35e17ca916931332158dffecc7a60e7d308cbf6aae66fd4dc4ccad10`. Kein GitHub-Release-Asset hochgeladen und keine Installation freigegeben.

## Fehlende Abnahme / Release-Sperren
1. Aktuellen installierten Backend-/Theme-Code und aktive Frontend-/App-Filter prüfen: Die v1.10.186-Originaldatei ist archiviert/lokal verfügbar, aber nicht beweisbar letzter Live-Stand.
2. Aktuelle Duisburger DB-Ereignisse und echte Nichtanzeigegründe fehlen; `Heute noch`/5→10-km-Filter und ET4-Zulassung benötigen Live-Nachweis.
3. Theme/Android-App: City-Scope und Routen-CTA sichtbar/funktional verifizieren. Gilt auch für Food, Highlights, Kultur, Aktivitäten, Büdchen/Specials.
4. Integration/Regression in WordPress, Quellarchivierung und Installations-ZIP als zugehöriges Release mit SHA-256/Wiederabruf.
5. Kein ungefragter WPVibe-/Live-Adminzugriff.

**Status:** P0 Duisburg nicht behoben; bisher nur eingegrenzte Backend-Anpassung als lokaler Entwicklungsstand.