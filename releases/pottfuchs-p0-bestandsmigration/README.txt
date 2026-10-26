=== PottFuchs P0 – einmalige SEO-Bestandsmigration ===
Version: 1.0.1
Requires PHP: 7.4
Requires: WordPress with active Theme ruhrpott-guru, published WPCode #3343/#4102, Places v1.10.188.

ZWECK
Einmalige, rücknehmbare Änderung ausschließlich der BESTEHENDEN WPCode-Quelltexte und des vorhandenen Places-Detail-SEO-Renderers. Keine zusätzliche SEO-Engine und keine Veränderung an Such-APIs, Canonicals, Filtern, Standortlogik, Kategorien oder Event-Synchronisierung.

INSTALLATION
Nur auf dem autorisierten WordPress-Stand: Plugins > Installieren > Plugin hochladen > ZIP > Aktivieren. Die Aktivierung führt VOR jeder Änderung eine vollständige strukturgebundene Quellprüfung und ein Backup in der WordPress-Datenbank aus. Unbekannte Varianten brechen ab, ohne die Website zu ändern.

SICHERHEIT
- Exakte Blogname-/Domainprüfung: PottFuchs, www.pottfuchs.de.
- Quellowner-Signaturen und eindeutige String-Matches; Backend nur v1.10.185/186.
- WPCode-Snippets #3343/#4102 müssen veröffentlicht sein.
- Keine fremden Theme-/Plugindateien und keine neuen SEO-Filter.
- Bytegenaue Kopie der Backend-Datei und beiden Snippets vor Änderungen in nicht autoloadbarer WordPress-Option.
- PHP-Syntax- und Readback-Prüfung, Best-effort-Rollback bei jeder fehlgeschlagenen Schreibaktion.
- Kein autonomes Update aus dem Internet.

ROLLBACK
Solange diese Plugin-Version aktiv ist: WordPress > Werkzeuge > PottFuchs P0 Rollback. Die Wiederherstellung ist nur bei unverändertem Folge-Stand erlaubt, damit keine späteren Änderungen überschrieben werden. Originalbytes bleiben in der Datenbank bis zum erfolgreichen Rollback.

ABGRENZUNG UND TESTBASIS
Dieses Release basiert erstmals auf der vollständigen 1.10.188-Quellbasis aus dem originalen, geprüften GitHub-Zweig fix/manual-supplemental-priority, Commit 0ce8a7734bb96545431f766e320c4f054adb590f. PHP-Lint, PHP-Regressions- und Rollback-Tests gegen die echte archivierte 1.10.188-Hauptdatei müssen vor Freigabe bestehen. 1.10.185/186 werden absichtlich abgelehnt, weil das installierte Backend inzwischen 1.10.188 ist.

Die Aktivierung prüft Pluginheader, PHP-Versionskonstante, genaue HTML-Owner-Signaturen und WPCode-Bodies, bevor sie schreibt. Wenn das installierte Backend danach noch verändert wurde oder Quellen abweichen, bricht die Migration ohne Änderung ab. Der separate Liveabgleich ist erst nach Betreiberinstallation möglich.

INSTALLATION BEI ALTBESTAND
Falls PottFuchs P0 SEO Bestandsmigration 1.0.0 im WordPress-Pluginverzeichnis liegt, bei Plugin > Hinzufügen > Hochladen die neue Version 1.0.1 hochladen und die bestehende Version ersetzen. Nach dem bisherigen Sicherheitsabbruch 1.0.0 hat die Aktivierung keine Änderungen vorgenommen; nicht die funktionierende Places-Version ersetzen. Diese ZIP ist das kleine Migrationsplugin, NICHT das komplette Places-Backend.

ROLLBACK
Das Plugin muss nach erfolgreicher Aktivierung für das Rückroll-Menü aktiv bleiben: Werkzeuge > PottFuchs P0 Rollback. Ein erfolgreicher Rückrollvorgang stellt den exakten früheren 1.10.188-Places-Hauptdatei-Inhalt und die zwei WPCode-Posts wieder her, sofern seitdem nichts anderes daran verändert wurde. Originalbackup liegt in nicht automatisch geladenen WordPress-Optionen.
