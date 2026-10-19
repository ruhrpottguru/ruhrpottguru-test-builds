# Ablage- und Versionsregeln

Beschlossen durch Jürgens ausdrückliche Beauftragung vom 19.10.2026: Der Systemarchitekt wählt und pflegt die technischen Dateien im bestehenden GitHub-Repository.

- Quellcode: vollständiges Theme und eigene Plugins; bestehende Tests, Fixtures, Build-Artefakte und benötigte Assets erhalten. Herkunft und Dateihashes dokumentieren.
- Technische Dokumentation: Zuständigkeiten, Schnittstellen, erforderliche Jobs, Tests und Rollback fortschreiben, soweit tatsächlich belegt. Fehlende aktive WPCode-Snippets als Lücke führen, niemals aus Erinnerungen rekonstruieren und als aktuellen Code ausgeben.
- Releases: Quellcodeversion im Commit/Tag; Installations-ZIP und SHA-256 als Release-Asset, sobald die Erstellung und Bereitstellung erfolgreich geprüft ist. Quellcode und ZIP müssen zueinander passen. Keine Installationsbestätigung ohne Nachweis.
- Status: aktueller archivierter Entwicklungsstand und separat belegter Live-Stand. Keine automatisch angenommene Gleichheit.
- Historie: alte Commits/Tags erhalten. Überholte QA-/Testversionen nicht als aktuelle Installationsdateien anbieten.
- Öffentliches Repository: nur zur Veröffentlichung geeignete technische Inhalte. Private Projektunterlagen bleiben in den bestehenden Projektquellen; vor einer vollständigen Übernahme vertraulicher Dokumentation ist ein privater Zielbestand erforderlich.
- Verantwortung: Ablage und Wiederabruf sind Teil jeder Lieferung, keine zusätzliche Nutzeraufgabe. Falls eine Verbindung ausfällt, den konkreten Ausfall dokumentieren und wiederherstellbare vorhandene Quellen nutzen.
