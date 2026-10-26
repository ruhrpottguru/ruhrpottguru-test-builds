=== PottFuchs P0 – einmalige SEO-Bestandsmigration ===
Version: 1.0.0
Requires PHP: 7.4
Requires: WordPress with active Theme ruhrpott-guru, published WPCode #3343/#4102, Places v1.10.185 or v1.10.186.

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

ABGRENZUNG
Die Prüfung im Repository nutzt eine archivierte Places-Originalquelle v1.10.169 als Integrations-FIXTURE mit simulierter Versionskopfzeile v1.10.185, NICHT die derzeit installierte 1.10.185-Originaldatei. Ein bestandener Test ist keine nachgewiesene Liveinstallation. Bei abweichendem Livecode verweigert das Programm die Aktivierung. WPCode wird nur an exakt geprüften fünf öffentlichen Markenstellen geändert.

Es wird ausdrücklich keine Änderung an Zeitreise-rendernden Plugins und keine zusätzliche Google-Sitemap vorgenommen. Die Live-SEO-Abnahme nach Installation ist gesondert erforderlich.
