# PottFuchs Places 1.10.189 – Watchdog ohne Selbstblockade

## Beleg und Änderungsumfang
Authentifizierter Live-Abgleich am 26.10.: Version 1.10.188; Zusatzfeed 38/53, letzter Fortschritt 16:30:41 laut WordPress, obwohl später weiter Cron-Läufe und Watchdog-Reparaturen erfolgen. Neuer ursprünglicher Gesamtauftrag und vorhandene Resume-Freigabe bleiben bestehen. Keine Live-Codeänderung durchgeführt.

Die installierte Hauptdatei wurde vollständig gelesen: SHA-256 e0fa17964c622011553d9e2dc40ab4ae6610080b0703764205dfe0f4415012a2. Sie enthält inzwischen zusätzliche SEO-Korrekturen aus dem anderen Projektstrang; diese werden vollständig erhalten. Die alte unveränderte Release-v188-Hauptdatei wäre keine sichere Ausgangsbasis mehr.

Der Watchdog prüft beim Init fehlenden Hook, mehr als 30 Sekunden überfälligen Termin oder 15 Minuten alten Heartbeat. Bisher löscht er in allen drei Fällen den Hook und setzt ihn auf jetzt+3 Sekunden. Das kann beim Init innerhalb von wp-cron einen fälligen Job vor der Ausführung erneut in die Zukunft schieben. Bei altem Heartbeat wiederholt sich dies auch bei normalen Seitenaufrufen.

Minimaler Fix: Vorhandenen Termin niemals durch den Watchdog löschen/verschieben. Nur fehlenden Hook sofort fällig neu anlegen, bei überfälligem/stalem Zustand normalen WordPress-Cron anfordern. Kein direkter Worker-Aufruf, kein Umgehen der Locks und keine neue Quelle. Bestehende manuelle Steuerung, aktive ET4-/Master-Sperren, City-Cursor, Inflight-Schutz, Web-/App-Verträge und Lifecycle bleiben erhalten. Prioritätsfix v188 bleibt enthalten.

## Tests und Installation
CI führt PHP-Lint aller fünf PHP-Dateien, bestehende 174 Suchtests, 33 Prioritätstests und 20 Watchdog-Assertions gegen die echten Methoden aus. Der alte Watchdog muss am neuen Test scheitern. ZIPs deterministisch gebaut, Hauptdatei-Rücknahme muss exakt den live gelesenen SHA-256 ergeben. Live-Endabnahme des Imports und Android-Gerät bleiben nach Installation erforderlich.

Installation durch Betreiber: Plugins → Plugin hinzufügen → Plugin hochladen → bestehendes Backend ersetzen. Vorher Sicherung. Anschließend freigegebenen Zusatzfeed fortsetzen. Rollback: mitgeliefertes v188-Paket mit bewahrtem SEO-Stand installieren. Keine Schema-/Optionsmigration; Importdatenänderungen werden durch Code-Rollback nicht rückgängig gemacht. Keine Automatikaktivierung und kein neuer Masterstart.
