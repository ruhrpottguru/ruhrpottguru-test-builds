# Places Backend v1.10.170 – Mehrwortsuche

Status: Installationspaket für den vorhandenen Plugin-Slug ruhrpott-guru-places; noch keine Installation/Livetest bestätigt. Theme unverändert.

Ausgangsversion: 1.10.169. Vom Nutzer bereitgestellter Diagnosebericht vom 19.10.2026 15:06:33 UTC bestätigt SHA-256 dc6d447f1baa4710b08a00d0a3ffb6c4e274505d16e90ac258446ff63fa1bce0 der installierten Backend-Hauptdatei. Sie entspricht bytegenau der importierten Ausgangsdatei. Die übrigen 214 Dateien des Pakets sind unverändert aus dem Backend-Originalarchiv; für diese 214 Dateien liegt kein eigener Live-Hashvergleich vor.

Änderung: Place-Kandidaten im bestehenden global-search-Handler verknüpfen durch Leerraum getrennte Suchwörter mit UND über dieselben sechs vorhandenen Felder. Einmaliges wpdb-prepare mit maskierten LIKE-Werten. Keine Änderung von Zulassung, Ranking/Sortierung, Radius, GPS, Quellen, Detailrouting oder Sitemap. Versionsheader und VERSION-Konstante auf 1.10.170 angehoben. Nur eine Backend-Datei geändert.

Validierung: Syntax bestanden. Funktionsgleicher Code vor reiner Versionsanhebung hat 25 PHP-/SQLite-SQL-Fälle und 26 echte WordPress-/wpdb-/MySQL-REST-Fälle bestanden, einschließlich GET/POST, Topgolf+Stadt, Top Golf+Stadt, falsche Stadt, Sonderzeichen, aktive/inaktive Einträge und Skate-Alias. Test-Run https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/39271609125 . Versionierter Code-Commit: 6a5799ad87bfa7de435c5bed80901ce3a6aed98b; dessen CI ist separat unter Actions einsehbar. Kein produktiver Browser-/Mobile-E2E-Nachweis vor Installation.

ZIP: RuhrpottGuru-Places-Backend-v1.10.170-Mehrwortsuche.zip
Dateien: 215; Bytes: 708111.
SHA-256: e30593802f46735b7b2fe6745c64a28f3e5e91cf055b26747705466fb802eaf3.
ZIP-Integrität sowie jede enthaltene Datei gegen den Release-Quellbaum geprüft.

Installation: In WordPress Plugins > Installieren > Plugin hochladen, bestehendes Places-Backend ersetzen. Keine Deaktivierung/Löschung des bisherigen Plugins erforderlich. Danach die drei Abfragen Topgolf / Topgolf Oberhausen / Top Golf Oberhausen prüfen; weiterhin drei IDs erwartbar, da keine Deduplizierung implementiert wurde.

Rollback: ursprüngliche v1.10.169-Plugin-ZIP wieder installieren. Keine Datenmigration, kein Reprocessing und kein neuer Cronjob; bestehende Aktivierungslogik bleibt unverändert.

Separater nächster Schritt: Livebefund bestätigt alle drei Profile admission=bedingt, primary_experience=golf, keine hard_blockers, seo_category leer. Jede ID hat genau eine eigene OSM-Quelle. ID8577 besitzt vollständige Adresse und ein Medium; die zwei weiteren IDs haben kein eigenes Medium. Keine Overrides/Einreichungen vorhanden. Hieraus folgt keine Freigabe einer Löschung oder pauschalen Golf-Zulassungsänderung. Besuchereignung gezielt belegen, vorhandenen redaktionellen Pfad und persistente Zusammenführung/Weiterleitung zunächst prüfen. 404 und Google-Sichtbarkeit bleiben offen.
