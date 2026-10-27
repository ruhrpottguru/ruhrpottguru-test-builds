# PottFuchs P0 – Fünf Hauptkategorie-SEO-Titel
Stand 27.10.2026 · Revision v1.1.0

## Eigentümer nach einmaliger genehmigter Read-only-WPVibe-Prüfung
WPCode #3354 („RuhrpottGuru SEO Pflegefelder“) registriert `pre_get_document_title` (Priorität 20) und verwendet das bereits bestehende Postmeta `rpg_seo_title`. Die fünf tatsächlichen produktiven Metawerte wurden über `wp post meta get` gelesen. WPCode #3343 enthält **keine** fünf behaupteten Kategorie-Titel-Literale. Der ältere Branch-Ansatz, die fünf Zeichenfolgen in #3343 zu ersetzen, ist ausdrücklich verworfen.

| Seiten-ID | Slug | Bisheriger tatsächlicher `rpg_seo_title` |
|---|---|---|
| 21 | veranstaltungen | Veranstaltungen im Ruhrgebiet – heute & am Wochenende \| RuhrpottGuru |
| 2284 | food | Restaurants & Essen im Ruhrgebiet \| RuhrpottGuru |
| 29 | highlights | Sehenswürdigkeiten & Highlights im Ruhrgebiet \| RuhrpottGuru |
| 2729 | kultur | Kultur im Ruhrgebiet – Museen, Theater & mehr \| RuhrpottGuru |
| 2812 | aktiv | Freizeitaktivitäten im Ruhrgebiet \| RuhrpottGuru |

## Lösung
Einmaliger WordPress-Plugin-Aktivierungslauf mit exakter Quellwertprüfung aller fünf Seiten; bei Abweichung vollständiger Abbruch ohne Änderung. Bestehende Metawerte gezielt mit `update_post_meta` ändern. Backup in eigener Option, Rollback nur bei identischen Zielwerten. Keine neuen SEO-Hooks, keine Änderungen an #3343/#4102/#3354/#4152, Theme 351.0 oder Places 1.10.190.

Installations-ZIP: `PottFuchs-P0-Kategorie-SEO-v1.1.0.zip`  
SHA-256: `2c5c189deb811c7dc4ac1d45727b95e9209947205edbc69ed5df150ab4aed6aa`  
Rollback-ZIP: `PottFuchs-P0-Kategorie-SEO-Rollback-v1.1.0.zip`  
SHA-256: `45ad87fed31047a8e91e355cf31f4f02822d38e17d1c991f2f0967533541e83c`

Lokale Prüfungen: PHP 8.4 Syntax für beide Hauptdateien, 16 assertions zu Abbruch/Update/Rollback/Schreibfehler, ZIP-Integrität bestanden. **Keine Liveinstallation und keine Live-E2E nach dem Patch.** Keine unaufgeforderte Änderung am Live-System. Die zwei Zips wurden lokal erzeugt; kein GitHub Release-Asset-Upload durch die verfügbare GitHub-Schnittstelle möglich. Quelle lokal erhältlich in der aktuellen Conversation-Lieferung; die nachstehende Dokumentation ist bereits im GitHub-Branch gespeichert.

Installation durch Betreiber: Plugins → Installieren → Plugin hochladen → Haupt-ZIP aktivieren, Admin-Hinweis prüfen; alle fünf HTML-Titel per Direktaufruf kontrollieren. Rollback nur bei Bedarf als eigenes Plugin hochladen und aktivieren. Keine Reaktivierung der Hauptmigration nach Rollback, da die Sicherung erneute Ausführung absichtlich verhindert.
