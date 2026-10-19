# Topgolf: Place-Mehrwortsuche, Entwurf vom 19.10.2026

## Umfang
Basis: archiviertes Places-Backend v1.10.169, Git-Commit b18db1c84fdfc9a4e662c6bab4bc02e175a47cf2. Kein Live-Code-Abgleich und kein Installationsrelease. Der bestehende Handler rest_global_search erhält für Place-Kandidaten eine UND-Verknüpfung der durch Leerraum getrennten Wörter. Jedes Wort darf in einem der bisher durchsuchten Felder name, city, district, street, primary_subcategory oder raw_tags stehen. SQL-Parameter werden genau einmal in der bestehenden wpdb->prepare-Abfrage gebunden. %, _ und Backslash bleiben maskiert. Einzelwort-Suchmuster und bestehender Skate-Alias bleiben erhalten.

Eine Backend-Datei verändert; hinzu kommen SQL-Regressionstests und CI. Keine Änderung an Events-/Wurst-/Artikel-Suche, Kategorie-/Zulassungsregeln, Quelle, Ranking, Sortierung, Limits, GPS, Radius, Bildresolver, SEO, Sitemap oder Detailrouting. Keine Datenmigration oder Versionsanhebung. Die lokale Quellinventur beschreibt weiterhin den Basisimport; der neue Commit dokumentiert den Patch-Diff.

## Nachgewiesene Ausgangsbefunde
Öffentliche Suche am 19.10.2026: topgolf liefert Place 8577 (OSM way 1051311896, Gebäude/clubhouse), 8274 (node 10225168937, sports_centre), 8576 (way 1051311895, driving_range). Gleicher Name/Website, nahe Koordinaten; keine drei unabhängigen Attraktionen. topgolf oberhausen und top golf oberhausen: je 0 Treffer. Drei canonicalUrl-Pfade liefern 404, sind nicht in der RPG-Places-Sitemap enthalten und Google URL Inspection meldet alle als unbekannt.

Archivcode: SEO verlangt eine zulässige Kategorieprojektion; Golf benötigt Besucher-/Zugangssignal. Die öffentlich gelieferten Tags enthalten diese Signale nicht. Die Suche gibt die Zeile dagegen auch bei fehlender Aktivitätenprojektion aus. Das erklärt den beobachteten Widerspruch im Archivcode; den aktuellen Live-Handler und interne Overrides konnten wir wegen WPVibe-Kontingent noch nicht abgleichen. Kein bestätigter Live-Datenbanknachweis der exakten 404-Abbruchstelle.

## Tests und Grenzen
25 SQL-Fälle führen den aus dem tatsächlichen Backend extrahierten PHP-Helper gegen SQLite-Fixtures aus. Abgedeckt: Topgolf/Top Golf plus Stadt, falsche Stadt, zusätzliche unerfüllte Bedingung, Wortreihenfolge, mehrfacher/Unicode-Leerraum, Einzelwörter, mehrere vorhandene Felder, literal %, _, Apostroph, SQL-Injection-Gegenprobe, aktive/inaktive Zeilen. PHP-Syntax und Testausführung werden separat durch CI protokolliert. SQLite ist kein MySQL-/wpdb-/WordPress-E2E-Nachweis. Skate-Alias, Join, aktive Filter, Ordering und Limit werden im Bestandsdiff unverändert gehalten. Live-Integration, echte Mehrworttrefferqualität und Latenz sind vor Produktionsfreigabe offen.

## Offene Arbeit und Rücknahme
Dieser Patch löst weder Dubletten noch die 404. Vor Datenbereinigung interne Beziehungen, Medien/Overrides und Importidentitäten read-only ermitteln; keine Löschung. Belegten Besucherzugang über vorhandenen redaktionellen Mechanismus pflegen, ohne globale Golfzulassung zu lockern. Hauptidentität und eventuelle 301-Ziele erst danach festlegen. Anschließend Detailseite, Suche, Kategorie, Sitemap und GSC prüfen. Keine zusätzlichen Live-Zugänge eingerichtet.

Rollback des Suchentwurfs: Änderung im Backend auf Basiscommit zurücksetzen. Es wurden keine Daten verändert. Branch und PR dienen Review und Tests; kein automatisches Deployment.
