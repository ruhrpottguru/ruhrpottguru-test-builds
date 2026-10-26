# PottFuchs – Globale Standort-, Sichtbarkeits- und Routenregeln

**Entscheidung:** 26.10.2026 · **Status:** fachlich verbindlich, technische Umsetzung und Regressionstests ausstehend.

## Geltungsbereich
Alle 53 Städte, alle bestehenden Kategorien (Veranstaltungen, Food, Sehenswürdigkeiten, Kultur, Aktivitäten) und alle Specials einschließlich Büdchen. Gleiche Regeln für alle bereits zugelassenen Datenquellen, auch kommunale Veranstaltungskalender. Keine stadtspezifische Sonderbehandlung der grundsätzlichen Sichtbarkeit. Diese Entscheidung erweitert **nicht** die Liste zugelassener Quellen und ändert keine fachliche Kategoriezuordnung.

## Verbindliche Fachregeln
1. **Sichtbarkeit und Datenqualität trennen:** Fehlende Adresse, Koordinaten oder Bilder sind allein kein Grund, einen ansonsten fachlich zulässigen Datensatz auszublenden oder beim Import zu verwerfen. Bestehende Zeit-, Lifecycle- und Zulassungsregeln gelten weiterhin.
2. **Geografische Auffindbarkeit getrennt behandeln:** Exakte Radiuszugehörigkeit und Entfernungsangaben nur anhand verlässlicher Positionen bestimmen. Ein nur der Stadt zugeordneter Eintrag darf nicht mit erfundener Entfernung oder als nachgewiesener Treffer innerhalb von 5 km erscheinen. Einträge ohne präzise Position müssen über geeignete allgemeine/städtische Suchansichten auffindbar bleiben. Die konkrete UI-Behandlung bei Radiusfiltern ist vor Implementierung anhand der bestehenden Verträge zu prüfen.
3. **Standortdaten wiederverwenden:** Verlässliche Adresse, Koordinaten und eindeutig verknüpfte Location nach vorhandener Resolver-Logik zusammenführen. Eine bekannte verlässliche Adresse ist anzuzeigen, soweit die betreffende Ansicht Adressen darstellt. Keine erfundenen Adressen oder Zielpunkte.
4. **Routen nur zu konkretem Ziel:** Routenbutton nur bei verlässlicher vollständiger Adresse, verlässlichen Zielkoordinaten oder eindeutig zugeordneter Location mit konkretem Routenziel. Nur ein Stadtname oder Stadtmittelpunkt genügt nicht. Fehlt die Textadresse, sind verlässliche Koordinaten als Ziel zulässig; dann keine erfundene Adresse ausgeben.
5. **Quellenneutralität:** Standort- und Sichtbarkeitsregeln für ET4/ODF und andere freigegebene Quellen identisch anwenden. Weseler Kalender-Sonderlogik auf Wiederverwendbarkeit prüfen, nicht blind auf 53 Städte kopieren.
6. **Kompatibilität:** Bestehende Radius-Fallbacks, Sortierung, Kategorie-/Quellenregeln, SEO, REST-/App-Verträge, Import-Sync und Event-Lifecycle nicht ohne gesonderte Prüfung verändern.

## Operative Aufgaben – hohe Priorität
- Bestand in Backend, Theme, REST und Android-App auf fehlende Adresse/Koordinaten, Radiusauswahl, Sichtbarkeit und Routenbuttons prüfen.
- Duisburg-Fall `Heute noch · 5 km` mit echten gespeicherten Datensätzen, Quellabdeckung, Zeiten, Positionen und Fallback prüfen.
- Bestehende Wesel-Implementierung ermitteln und globale wiederverwendbare Verarbeitung entwerfen; nur nach Minimal-Diff und Regressionen umsetzen.
- Tests: ohne Adresse/mit Koordinaten; mit Adresse/ohne Koordinaten; nur Stadt; ohne Bild; ohne Standort; verknüpfte Location; Radius/10-km-Fallback; Web und App.
- Implementierung, Version, Tests, ZIP und SHA-256 gesondert archivieren; **dieses Dokument ist keine Implementierungs- oder Live-Freigabe**.

**Projekteinordnung:** Architektur- und Entscheidungsregister: verbindliche Entscheidung; operative Projektleiter-Masterliste: Umsetzung/Abnahme offen; Produkt-Roadmap: kein neues Feature.
