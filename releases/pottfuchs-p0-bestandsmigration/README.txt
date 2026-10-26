PottFuchs P0 SEO-Bestandsmigration 1.0.2
========================================

ZWECK
Update des bestehenden installierten Plugins "PottFuchs P0 – einmalige
SEO-Bestandsmigration" 1.0.1, KEINE neue SEO-Engine, KEINE neue Plugin-ID.
Die v1.0.1 hatte zwei WPCode-Quelltexte direkt aktualisiert. WPCode Lite
führt aktive Snippets jedoch auch aus einem separaten Preload-Cache aus.
Der neue Abgleich stellt diesen bestehenden Cache über die WPCode-eigene
Methode cache_all_loaded_snippets() aus vorhandenen veröffentlichten Snippets
erneut her. Die WPCode-Option wird bewusst NICHT gelöscht.

ZUSÄTZLICH: Die einzelne öffentliche Zeichenfolge
"RuhrpottGuru-Standort" auf der schon vorhandenen WordPress-Seite
"zeitreise-von-oben" (Post-ID 4115) wird nach Vorprüfung minimal zu
"PottFuchs-Standort" geändert. Sonst keine Zeitreise-Funktion.

INSTALLATION
WordPress -> Plugins -> Plugin hinzufügen -> Plugin hochladen. ZIP 1.0.2
über der gleichnamigen, bereits aktiven v1.0.1 installieren/ersetzen.
Das Places-Backend nicht ersetzen. Nach der Aktualisierung eine WordPress-
Adminseite laden; dieser erste administrative Aufruf führt den einmaligen
Abgleich aus. Bei der erfolgreichen Ausführung erscheint eine Erfolgsmeldung.
Bei unerwarteten Quellständen erscheint nur ein Sicherheitsabbruch ohne
neue WordPress-Korrektur.

BESTANDSCHUTZ
* P0 v1.0.1-Sicherungszustand wird nicht verändert.
* Falls P0 v1.0.1 bereits gelaufen ist: niemals erneut den Places-Dateipatch
  ausführen, insbesondere nicht gegen das nun aktuelle Backend v1.10.189.
* Die Website/Marke, veröffentlichte Original-Snippets #3343/#4102,
  native WPCode-API, vorhandener Cache und die exakt benannte Zeitreise-Seite
  werden vor der Änderung geprüft. Fällt der Check durch, kein Eingriff.
* WPCode-Quellen und alle anderen Snippets bleiben unverändert. Kein
  REST-/Theme-/Backend-/App-Code wird verändert.
* Backup vor jedem Schreibvorgang als nicht automatisch geladene Option.
* Bei Fehlern automatischer Rollback, fehlgeschlagenes Rollback bewahrt Backup.
* Einmalige Durchführung, keine automatische Wiederholung.

ROLLBACK
WordPress -> Werkzeuge -> PottFuchs P0 Cache-Rollback (NEUE Option).
Diese stellt den vorherigen WPCode-Preload-Cache sowie den ursprünglichen
Zeitreise-Inhalt wieder her, aber NUR wenn zwischenzeitlich keine
neuen Änderungen an den geprüften Quellen/Cache/Inhalten erfolgt sind.
Wichtig: Die ALTE 1.0.1-Rollbackfunktion für die gesamte Places-Hauptdatei
nicht benutzen, nachdem Places auf v1.10.189 aktualisiert wurde.

TESTS UND ABSCHLUSSGRENZE
Syntax, frühere P0-Funktionstests, native Cache-/Rollback-Simulationsprüfung
und installierbare ZIP-Struktur werden im zugehörigen GitHub-CI geprüft.
Eine wirkliche produktive Aktivierung und ihre öffentliche Wirkung sind
durch den Build nicht bewiesen. Kategorie-SEO-Titel mit alter Marke kommen
möglicherweise von einem anderen bisherigen SEO-Owner. Dieses Plugin
erstellt KEINE zweite Titel-Engine und überschreibt sie nicht auf Verdacht.
P0 ist erst nach den öffentlichen SEO-/Browser-Regressionstests abgeschlossen.
