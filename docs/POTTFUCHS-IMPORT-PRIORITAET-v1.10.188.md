# PottFuchs Places 1.10.188 – manuelle Importpriorität

## Beleg und minimaler Fix
Original am 26.10.2026 authentifiziert und ausschließlich lesend aus WordPress-Dateieditor exportiert: Header/Konstante 1.10.185. Vollständige Hauptdatei (2.001.651 Zeichen), drei Intelligence-Klassen, bootstrap.js und bestehende PHP/JSON-QA-Dateien übernommen. Ältere Textdokumentation aus dem Repository bleibt historische Dokumentation. Kein Datenbankexport, keine Zugangsdaten.

Live-Reproduktion: Zusatzfeed 33/53, Wartegrund Kurzrefresh-Vorrang. Freigegebener Hot14-Lauf 15:45:26–15:48:40 laut WordPress-Zeit erfolgreich (1 neu, 279 aktualisiert, 280 verarbeitet, 0 Fehler), anschließend gleicher Wartegrund.

Ursache: event_supplemental_short_refresh_pending_v101182 berücksichtigt jede hot14/plan90-Anforderung. Der Dispatcher startet in manueller Betriebsart jedoch nur explizit ausgewählte Aufträge. Nicht ausgewählte Anfragen sperren dadurch den Zusatzfeed dauerhaft.

Fix ausschließlich in der vorhandenen Pending-Abfrage: In manueller Betriebsart nur ausgewählte und vorhandene Kurzaufträge priorisieren; automatische Betriebsart unverändert. Laufende Kurzimporte werden weiterhin separat vor dieser Abfrage erkannt. Globale Sperre, Inflight-Schutz, Stadtcursor, Datenquellen, Lifecycle, Frontend/REST/App bleiben unverändert. Keine globale Standortkorrektur aus dem unbestätigten 1.10.187-Entwurf enthalten.

## Installation und Rücknahme
Noch nicht live installiert. Plugin-ZIP in WordPress unter Plugins → Plugin hinzufügen → Plugin hochladen ersetzen. Vorher vorhandenes Plugin sichern. Rücknahme: gesicherte unveränderte 1.10.185-Pluginversion wiederherstellen. Keine Schema- oder Optionsmigration. Nach Installation freigegebenen Zusatzfeed fortsetzen und Fortschritt sowie Duisburger Daten prüfen. Import kann produktive Daten aktualisieren; Code-Rollback macht Importdaten nicht rückgängig.

## Nachweisgrenzen
PHP-Funktionstests und Syntaxprüfung werden per CI ausgeführt. Keine vorgetäuschte produktive Abnahme: Datenabdeckung und Android-Endgerät bleiben bis Installation/Importabschluss offen.
