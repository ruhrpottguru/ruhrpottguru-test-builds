# PottFuchs – Search Console „Neue Gründe, dass Seiten nicht indexiert werden“

**Datum:** 27.10.2026, unmittelbar nach dem Domainwechsel auf `https://www.pottfuchs.de` und dem von Google am 24.10. akzeptierten Adresswechsel.

**Anlass:** Screenshot der Search Console nennt drei Ausschlussgründe: 
1. `Duplikat – Google hat eine andere Seite als der Nutzer als kanonische Seite bestimmt`
2. `Seite mit Weiterleitung`
3. `Durch "noindex"-Tag ausgeschlossen`

**Wichtige methodische Grenze:** Der Screenshot enthält weder die Anzahl noch die Liste der betroffenen URLs oder Googles **tatsächlich ausgewählte** kanonische URL. Diese Information lässt sich nicht zuverlässig aus öffentlichen HTML-Tests rekonstruieren. Die Einträge sind keine pauschal zu beseitigenden Fehler.

## Unabhängiger öffentlicher Live-Test

Prüfsoftware: [`tools/pottfuchs_indexability_public.py`](../tools/pottfuchs_indexability_public.py), Workflow [`.github/workflows/pottfuchs-gsc-exclusions.yml`](../.github/workflows/pottfuchs-gsc-exclusions.yml).

**Tatsächlich ausgeführter erfolgreicher GitHub-Action-Lauf:** [40472909783](https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40472909783). Ausschließlich öffentliche GET-Anfragen, keine WordPress-, Hosting-, App-, WPVibe- oder GSC-Schreibaktion.

- Zwei bestehende PottFuchs-Sitemaps antworten HTTP 200: `rpg-sitemap-places.xml` **15.060** `loc`-Einträge und `rpg-sitemap-events.xml` **10.581** `loc`-Einträge (Zählstand nur für diesen Abruf, keine Indexierungszahlen).
- `wp-sitemap.xml` antwortet HTTP 200 und verweist auf fünf weitere Sitemap-URLs (Sitemap-Index).
- **21 verschiedene HTTP-URLs** geprüft: **20 HTTP 200** und **eine absichtlich ungültige Place-URL HTTP 404**.
- **Keiner der 20 erfolgreichen Beispielaufrufe** zeigt ein `noindex` in `meta[name=robots]`, `meta[name=googlebot]` oder `X-Robots-Tag`.
- Die **einzige** `noindex`-Ausgabe ist der absichtlich ungültige Place `/ort/999999999999/test/kein-ort/`: HTTP 404 mit `X-Robots-Tag: noindex, follow`. Das ist sinnvoll und darf nicht global beseitigt werden.
- Alle vorhandenen Canonical-Links in dieser Stichprobe verweisen auf `www.pottfuchs.de`. Die geprüften Startseiten, Hauptkategorien und realen Event-/Ortsdetails zeigen jeweils ihren gewünschten neuen kanonischen Pfad. Kein festgestellter falscher *ausgegebener* Host.
- **Separater Prüfpunkt:** `/suche/?q=essen` liefert **HTTP 200** mit `robots: index, follow` und **ohne Canonical-Link**. Diese interne Suchseite steht nicht in den oben genannten individuellen Events-/Places-Sitemaps. Ob sie indexierbar sein *soll*, muss vor Änderung am bestehenden SEO-Owner entschieden werden. Die konkrete GSC-Warnung wird dadurch noch nicht erklärt. Keine ungefragte neue SEO-Regel / keine pauschale `noindex`-Anweisung.
- **Weiterer, unabhängiger Rebranding-Befund**: Fünf Hauptkategorie-Titel tragen weiterhin öffentlich `| RuhrpottGuru`; das verhindert nach dieser Stichprobe zwar nicht die technische Indexierbarkeit, bleibt aber als separater P0-Markenfix offen. Place und Event-Beispieltitel sind korrekt `| PottFuchs`.

## Einordnung der drei Search-Console-Meldungen

- **Seite mit Weiterleitung**: Für alte Domain, HTTP oder Non-www ist dies **erwartetes Soll-Verhalten**. Google soll diese Ausgangs-URLs nicht indexieren; das jeweilige kanonische Ziel wird separat beurteilt. Alte 301 nicht abschalten.
- **Google hat eine andere kanonische Seite gewählt**: nach Domainwechsel für alte Varianten, Filterparameter oder sehr ähnliche Datensätze zeitweilig möglich, aber **nicht automatisch unbedenklich**, falls Google für eine wichtige neue URL einen fremden/alten Host als Canonical wählt. Die Search-Console-URL-Inspektion (Google-gewählte kanonische URL, Nutzer-Canonical, letzter Crawl, Weiterleitungsziel, Beispiel-URL) entscheidet.
- **Durch noindex ausgeschlossen**: für ungültige, absichtlich deaktivierte oder interne Seiten ggf. korrekt, **für reguläre öffentliche Places/Event-/Kategorie-URLs ein echter Fehler**. Die eigenen Stichproben zeigen kein noindex auf regulären öffentlichen Beispielen.

## Noch unvermeidbar offene GSC-Verifikation

Ohne Search-Console-Einzelbeispiele kann kein seriöser Befund über die **tatsächlich gemeldeten URLs** der drei Kategorien oder eine eventuelle Google-Canonical-Fehlwahl gemacht werden. Dem Betreiber bei Bedarf gezielt aus der Property der neuen Hauptdomain die URL-Beispiele unter den drei Ausschlussgründen und den Google-Canonical der kritischen Fälle abnehmen lassen, *bevor* Änderungen an produktiven Canonicals/robots/noindex erfolgen. Keine kostenpflichtigen Dienste, keine Massen-Neuindexierung, keine neue Architektur.

Google-Dokumentation:
- https://developers.google.com/search/docs/crawling-indexing/site-move-with-url-changes
- https://developers.google.com/search/docs/crawling-indexing/canonicalization-troubleshooting
- https://support.google.com/webmasters/answer/7440203

**Status:** Öffentliche Indexability-Stichprobe abgeschlossen, GSC-Meldung für die tatsächlichen Einzelfälle ohne Google-URL-Beispiele **nicht vollständig aufgeklärt**.
