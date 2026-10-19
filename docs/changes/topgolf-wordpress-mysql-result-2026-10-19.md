# WordPress-/MySQL-Integration und Diagnose-ZIP, 19.10.2026

Geprüfter Commit: e8735a47e46137616f176f88209b6374a87c03c6.
Run: https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/39271609125

Ergebnis: success. WordPress 7.1.3, MySQL 8.0.46. 26 GET-/POST-REST-Fälle mit vollständig geladenem Backend, tatsächlichem wpdb und isolierten Testtabellen bestanden; zusätzlich 25 PHP-/SQLite-SQL-Fälle und Syntaxprüfung. Keine produktiven Daten oder Zugänge verwendet. Die ursprüngliche fixture-bedingte Fehlannahme (Essener Ort mit Oberhausener Website) wurde in der Testfixture korrigiert; dies war kein Backend-Fix.

Diagnose-Plugin: Administratorberechtigung, nur SELECT/SHOW während Bericht, exakt drei IDs, korrekte Quellcode-Prüfsumme, private Notiz ausgeschlossen und kein nopriv-Export-Hook im selben WordPress-/MySQL-Lauf geprüft. Statische Prüfung ohne SQL-Schreibbefehle, externe Netzwerkaufrufe oder Schedules; PHP-Syntax und ZIP-Integrität geprüft.

ZIP: RuhrpottGuru-Topgolf-Diagnose-v1.0.0.zip, 3693 Bytes.
SHA-256: 0a17184826bf09109d992ba1e06a2d45e23eaed3589ddbc8169717722056ecdb.
Enthält ausschließlich rpg-topgolf-diagnostics.php und README.txt im gleichnamigen Plugin-Verzeichnis. Temporäres administratives Lesewerkzeug; kein Suchfix, keine Datenkorrektur. Deaktivieren/Löschen entfernt das Werkzeug ohne eigene persistente Daten.

Öffentlicher /places/status-Abruf am 19.10. meldet Live-Backend v1.10.169, Schema 1.10.129, 24713 aktive Places. Versionsgleichheit ist kein Codegleichheitsnachweis. Vor Produktionspaket für den Suchfix werden Live-Codehash und Topgolf-Relationen/Overrides benötigt. Dafür den Diagnosebericht im Projektchat auswerten. Keine Änderung an Produktions-Theme/Backend oder Places ausgeführt. Kein Browser-/Mobile-/Produktiv-E2E-Nachweis durch diese Tests.
