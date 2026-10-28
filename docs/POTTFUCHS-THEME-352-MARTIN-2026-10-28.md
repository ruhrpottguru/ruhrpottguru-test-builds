# PottFuchs Theme 352.0 – Martinszüge-Seite und Weihnachts-Absicherung (28.10.2026)

Basis: Theme 351.0 (vom Betreiber als ZIP geliefert, SHA-256 `9a12087e496d4394d8a7cfd1f5f410f0c25e48f168abe9a443c280380324c7ff`, Rollback). Paket: `release-artifacts/pottfuchs-theme-v352.0/` (SHA-256 `6f90f4481944e79ec1d6708ae689f9a80e2089ec455284eaea284eecc1b38386`).

Stufe: lokal geprüft (Syntax, Test mit realen Titeln, vorhandene Tests unverändert), **nicht installiert, nicht live verifiziert**.

## Änderungen (minimaler Diff)
- Neue Seite `/martinszuege-im-pott/`: `MartinView` (views.js), feste Mitte Essen, 50 km (Backend-Maximum), keine Umkreisauswahl, Filter nur mit Treffern. Koordinatenlose Stadt-Events kommen über `RPG.settings.cityScopeCities` (functions.php, aus der Event-Tabelle abgeleitet, 6 h Cache) dazu.
- Weihnachten im Pott: Besucher ohne Standort (auch Suchroboter) sehen die Ansicht für Essen-Mitte, 50 km statt „Standort auswählen …“ (main.js). Echter Standort gewinnt.
- page.php: Slug-Zuordnung und Fallbacktext ohne JavaScript für Martin. functions.php: Seite wird einmalig automatisch angelegt. style.css: Version 352.0.
- Unverändert: Kategorien, Filterregeln, Ranking, Radius-Fallbacks, Halloween, normale Eventlisten.

## Tests
- 16 Fälle `martinCategories` (reale Titel aus der Datenbank, Zufallstreffer wie „Martina“, „Martin Brödemann“, Orgelkonzert in St. Martin): bestanden.
- Vorhandene Tests: `place-events`, `v326-geoapify-address`, `v329-home-events` schlagen schon in Theme 351.0 fehl, Ausgabe vor und nach der Änderung identisch (keine Regression, Ursache offen). Übrige 11 bestanden.

## Quellenlage
Dieser Zweig enthält erstmals den vollständigen Theme-351-Quellstand (Basis des Tests). `main` hält noch den alten Stand (~v299). Nicht in `main` gemergt, `docs/release-registry.json` bewusst unverändert (Live-Nachweis fehlt).

## Offen nach Installation
Martin-Seite aufrufen, ohne Standort testen, Weihnachten ohne Standort testen, Meta-Beschreibung und Titel prüfen, Search Console: Indexierung der neuen Seite anfordern.
