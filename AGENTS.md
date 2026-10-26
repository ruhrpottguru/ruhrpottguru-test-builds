# Arbeitsanweisungen RuhrpottGuru

> **PottFuchs ab 26.10.2026:** Bei jeder neuen Projektaufgabe zuerst [docs/POTTFUCHS-ARBEITSSTANDARD.md](docs/POTTFUCHS-ARBEITSSTANDARD.md) lesen. GitHub zuerst für Quellcode/Archive nutzen; fertige installierbare ZIPs statt Anleitungs-ZIPs liefern. WPVibe nicht ungefragt nutzen oder als Voraussetzung behandeln. Aktuellen Live-Stand nicht aus archivierten Versionen ableiten.

## Start jeder Aufgabe

1. `docs/POTTFUCHS-ARBEITSSTANDARD.md`, `README.md`, `docs/storage-policy.md`, `docs/current-state.md` und `docs/source-inventory.json` tatsächlich lesen.
2. Aktuellen Projektvertrag, Architekturregister, Masterliste und relevante spätere Nutzerentscheidungen aus den Projektquellen abgleichen. Die Dateien dieses Repositories ersetzen keine späteren Entscheidungen. Fehlen verbindliche Unterlagen, ausdrücklich kennzeichnen und vor widersprechenden Änderungen klären.
3. Vorhandene Implementierung und Tests zuerst prüfen. GitHub ist die primäre Quellbasis; WPVibe nicht ungefragt verwenden oder zur Voraussetzung machen. Live-Stände nur aus belegten Quellen behaupten.
4. Archiviert, lokal geprüft, vom Nutzer installiert gemeldet und live verifiziert strikt unterscheiden. Keine Version aus einem Dateinamen als live behaupten.

## Geschützte Bereiche

Keine ungefragten Änderungen an Kategorien, Klassifikation, Quellenzulassung, Ranking, Sortierung, Filtern/Chips, Entfernung, GPS, Radius-Fallbacks, Öffnungszeiten, SEO, Canonicals oder Deep-Links. Fehlende Bilder dürfen niemals Einträge ausblenden. Keine unbelegten Aussagen über Öffnung, Preise oder Verfügbarkeit. Vorhandene Suche und Kategorie-Handler wiederverwenden; keine parallele Sucharchitektur. Spätere konkrete Nutzerfreigaben gelten nur für ihren Umfang.

## Umsetzung und Release

Minimaler Diff, keine Nebenkorrekturen. Vor Patch Ausgangscode, relevante Live-Abweichungen und erlaubten Umfang feststellen. Vor Freigabe Syntax, sinnvolle Regressionen, Bestands-Diff und notwendige Integration prüfen; Grenzen des Nachweises dokumentieren. Keine Try-and-Error-Installationsserien. Neue Builds sind ohne bestandene notwendige Prüfungen keine finalen Installationspakete. Deployment ist ein separater Vorgang.

## Ablagepflicht des Systemarchitekten

Quellcodeänderungen samt vorhandenen Build-Dateien und benötigten Assets, sinnvollen Tests, Änderungsnachweis und Versionsbezug committen. Ein Release braucht Commit, Versionsnummer, ZIP-Prüfsumme, Teststand, Rollback und gesonderten Installations-/Live-Nachweis. Nicht den Nutzer bitten, jede gelieferte ZIP zusätzlich als Projektquelle zu verwalten. Speicherung und anschließenden Abruf tatsächlich prüfen; Verbindungsprobleme ehrlich benennen. Keine Garantie für dauerhafte Connector-Verfügbarkeit oder automatische Kontextladung geben.

Dieses Repository ist öffentlich. Vor jedem Import Inhalte auf Geheimnisse und private Daten prüfen. Keine Tokens, Kennwörter, `.env`, `wp-config.php`, Datenbankdumps, private Korrespondenz oder nicht freigegebene interne Unterlagen veröffentlichen. `.gitignore` ist kein Geheimnisschutz für explizite API-Uploads.
