# Standort-/Sichtbarkeitsregeln – Codebefund und Umsetzungsstatus (26.10.2026)

**Status:** Read-only-Codeprüfung, kein Patch, keine Installation, keine Live-Datenabfrage.

## Verbindliche Entscheidung
Siehe [globale Standort-, Sichtbarkeits- und Routenregeln](POTTFUCHS-GLOBALE-STANDORT-UND-SICHTBARKEITSREGELN-2026-10-26.md). Pflichtverweise in `AGENTS.md` und `docs/POTTFUCHS-ARBEITSSTANDARD.md` sind bereits vorhanden.

## Konkreter Quellcodebefund
Untersucht wurde das lokal verfügbare historische Installationsarchiv `PottFuchs-Places-Backend-v1.10.186-Import-Diagnose-FINAL.zip` (SHA-256 `edc6c5ea145823d1400118e6908333d12b41b5bcc4a2dca2ab0b22fdb280df29`), darin `ruhrpott-guru-places/ruhrpott-guru-places.php`.

- Um **Zeile 15361** wird der zusätzliche stadtbezogene Eventkandidaten-Pfad nur bei `strcasecmp(trim((string)$city),'Wesel')===0` ausgeführt.
- Um **Zeile 15368** schränkt die SQL-Abfrage diese Kandidaten zusätzlich auf `e.source='wesel_marketing_calendar'` ein. Zulassung ist damit bei fehlenden Koordinaten **nachweislich nicht stadt- und quellenneutral**.
- Um **Zeile 15376** werden solche Kandidaten als `_rpg_city_scope` markiert und mit einem Sortierwert 99999 versehen. Der Sortierwert darf niemals als reale Entfernung an Besucher ausgegeben werden.
- Um **Zeile 15570** werden bei city-scoped Einträgen Koordinaten aus dem öffentlichen Payload entfernt und `rpg_geo_scope='city'` gesetzt. Diese bestehende Kennzeichnung ist ein Wiederverwendungsansatz.
- Um **Zeile 14861** nutzt der ET4-Dispatcher manuelle Steuerung und eine Busy-Prüfung; `hot14` hat bei automatischer Auswahl Priorität. Daraus folgt **nicht**, dass die Duisburger Quelle vollständig ist oder der 5→10-km-Fallback funktioniert.

**Folgerung:** Der Wesel-Sonderpfad ist eine konkret belegte Abweichung von der neuen globalen Entscheidung. Die Duisburger Nulltreffer-Ursache ist damit **nicht bewiesen**, da aktuelle DB-/Quellabdeckung und Frontend-Filter nicht live geprüft wurden. Kein ungetesteter globaler Ersatz des SQL-Filters.

## Prüfungen
Sieben statische Quelltext-Vertragsprüfungen für die oben genannten Bedingungen: **7/7 bestanden**. Dies sind **Nachweise der Bestandsimplementierung**, keine Funktions- oder Regressionstests des gewünschten neuen Verhaltens.

## Operative Abnahme (offen)
1. Aktuellen installierten Originalstand von Backend, Theme und App sowie vorhandene Resolver/Tests verifizieren.
2. Duisburg: reale Eventzeilen und Ausschlussgründe inklusive Zeitfenster, Quelle, Stadt, Adresse, Koordinaten und 5→10-km-Fallback prüfen.
3. Bestehende city-scoped-Verarbeitung quellenneutral für alle freigegebenen Eventquellen und Städte gestalten, ohne exakte Radiuszugehörigkeit zu behaupten.
4. Entsprechende Sichtbarkeits- und Routenkontrakte für Food, Highlights, Kultur, Aktivitäten, Büdchen und Specials mit bestehendem Code abgleichen.
5. Web-/App-Regressions- und Syntaxprüfungen; erst dann installierbares Release mit SHA-256 und Rollback.

## Ablage der vollständigen Führungsdokumente
Die aktualisierten **privaten** Gesamtfassungen `PottFuchs-03-Architektur-und-Entscheidungsregister-v2.0-2026-10-26.md` und `PottFuchs-01-Projektleiter-Masterliste-v2.0-2026-10-26.md` sind als Projektdateien vorhanden. Wegen `docs/storage-policy.md` werden sie **nicht ungefragt vollständig in das öffentliche GitHub-Repository veröffentlicht**. Die öffentlich geeignete verbindliche Regel und dieser technische Audit sind im Repository abrufbar.