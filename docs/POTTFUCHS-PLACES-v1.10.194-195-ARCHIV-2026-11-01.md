# Places 1.10.194 und 1.10.195 - Archiv (01.11.2026)

Status: archiviert und lokal auf PHP-Syntax geprueft. NICHT als installiert oder live verifiziert zu werten (1.10.195 ist ausgeliefert, aber nicht installiert; 1.10.194 laut Betreiber seit 29.10.2026 installiert, Livemessung dort nur Ortsseiten-Marke).

## 1.10.194
- Herkunft: vom Betreiber hochgeladene ZIP (SHA-256 9efe484af18a79273211cd087d3db8b7845225ad14e06d6447a17ea2ce27d71f) auf Basis 1.10.193 (Commit 4f5e8614). Aenderung: Markenname PottFuchs in Ortsseiten-Link, Brotkrumen Stadtseite, Teilen-Seiten-Titel und Ersatztexten. Nicht aus GitHub gebaut.

## 1.10.195 (Aenderung gegenueber 1.10.194, nur ruhrpott-guru-places.php)
- Vorbeigegangene Termine liefern bis zur Aufbewahrung (Standard 10 Tage, wie die Loeschfrist in event_cleanup_expired) eine Seite "Dieser Termin ist vorbei." (HTTP 200, X-Robots-Tag: noindex, follow) statt sofort 404. Ohne Ticket-/Buchungslinks (Seitencode, Event-Strukturdaten ohne offers, share_event_payload).
- Hinweis zeigt, wenn vorhanden, den naechsten aktiven Termin mit exakt gleichem Titel in derselben Stadt ("Naechster Termin: TT.MM.JJJJ", im Seitencode als Link).
- Filter rg_event_past_page_days (Standard 10; 0 = sofort 404) fuer spaetere Ausnahmen je Quelle. Abgesagte/entfallene Events (cancelled, inactive) bleiben 404. Sitemap, Loeschlauf, Event-Abgleich, Kategorien, Filter, Ranking unveraendert.
- Pruefungen: php -l ohne Fehler; Dateivergleich gegen 1.10.194 (nur die eine PHP-Datei); Diff-Durchsicht. NICHT geprueft: Lauf in WordPress, Browser, Android-App, Zeitbasis (SEO-Abfrage vergleicht Ortszeit mit UTC wie bisher).
- ZIP: release-artifacts/pottfuchs-places-v1.10.195/ruhrpott-guru-places-1.10.195.zip (SHA-256 siehe SHA256SUMS.txt). Rollback: release-artifacts/pottfuchs-places-v1.10.194/ruhrpott-guru-places-1.10.194.zip.
- Installation: durch den Betreiber (WordPress, Plugins, hochladen, ersetzen). Registerfelder (docs/release-registry.json) bewusst nicht geaendert: kein Installations- oder Live-Nachweis.
