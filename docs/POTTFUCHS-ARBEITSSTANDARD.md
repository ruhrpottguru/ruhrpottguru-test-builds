# PottFuchs – verbindlicher Arbeitsablauf für neue Aufgaben und Chats

Stand: 26.10.2026
Geltung: Arbeits- und Lieferprozess; keine Änderung an Produktarchitektur, WordPress-Live-System oder fachlichen Regeln.
Herkunft: bestehende Projektvereinbarungen (Projektvertrag, Entscheidungsregister, Masterliste, GitHub-Ablageregel vom 19.10.) und der Wunsch nach konsequenter eigenständiger ZIP-/Code-Lieferung. Spätere konkrete Nutzerentscheidungen gehen vor.

## 1. Verlässlicher Einstieg – auch in neuen Projekt-Chats

1. Bei jeder technischen PottFuchs-Aufgabe zuerst dieses Dokument, `AGENTS.md`, `docs/storage-policy.md`, `docs/current-state.md` und die relevanten GitHub-Quelldateien tatsächlich öffnen. Nicht behaupten, etwas gelesen zu haben, das nicht erreichbar war.
2. Aktuelle Projektanweisungen, Projektvertrag, neuestes erreichbares Entscheidungsregister, operative Masterliste und spätere Nutzerentscheidungen berücksichtigen. Insbesondere: PottFuchs ist die Nachfolgemarke und `www.pottfuchs.de` die neue primäre öffentliche Domain; `ruhrpottguru.de` ist Alt-Domain für Weiterleitungen. Historische technische IDs dürfen erhalten bleiben.
3. Ein neuer Chat muss nicht die ZIP-Historie aus anderen Chats kennen: GitHub dient als reproduzierbarer Quellbestand. Niemals Chat-Erinnerung als Nachweis für einen aktuellen Release- oder Live-Stand verwenden.
4. Keine Zusage automatischer Wiedervorlage: Projektkontext und GitHub sind Hilfen, aber eine neue Unterhaltung außerhalb des Projekts oder ohne GitHub-Zugriff lädt diese Regeln nicht garantiert. Ist der Einstieg nicht möglich, Lücke einmal klar kennzeichnen.

## 2. Reihenfolge bei Problemen

1. Fehler/Ziel und Änderungsgrenze aus dem Nutzerauftrag präzise übernehmen; bereits gegebene Antworten nicht erneut erfragen.
2. GitHub prüfen: vorhandener Owner, Quellcode, Version, relevante Tests, frühere Releases und dokumentierte Entscheidungen.
3. Aktuellsten nachvollziehbaren Quellstand getrennt vom zuletzt belegten Live-Stand bewerten. Ein veraltetes Archiv nicht als aktuelle Installation ausgeben.
4. Bestehenden Eigentümer minimal korrigieren, keine neue parallele Architektur und keine ungefragten Nebenkorrekturen.
5. Lokal Syntax, konkrete Problemfälle, Regression, Diff und Installationsstruktur prüfen; nicht prüfbare E2E-/Live-Fälle ausdrücklich benennen.
6. Eine saubere finale Lieferung; bei WordPress-Aufträgen grundsätzlich ein **installierbares Theme- oder Plugin-ZIP** bzw. ausdrücklich beauftragte Einzeldatei. Keine Anleitungs-ZIP als fertige Lösung ausgeben.
7. Quelle, Commit, Installations-ZIP, Prüfsumme SHA-256, Testbericht, Versionshinweis und Rollback gemeinsam ablegen; anschließenden GitHub-Wiederabruf verifizieren. Archivstand und Live-Installation getrennt protokollieren.
8. Abschlussantwort: Was geändert, welches ZIP, wo installieren, was getestet, was nicht getestet, Rollback. Nie „fertig“ oder „installiert“ ohne Nachweis.

## 3. Standard-Werkzeuge und Nutzeraufwand

- **GitHub ist die erste Anlaufstelle für Code und Archive**, nicht WPVibe.
- WPVibe nicht als Entwicklungs- oder Freigabevoraussetzung verwenden und nicht ungefragt einsetzen; nur wenn der Nutzer ausdrücklich einen Livezugriff/WordPress-Vorgang beauftragt.
- Nicht reflexartig den Nutzer nach Zips/Plugins/Code fragen, die nach den Regeln vom Assistenten archiviert sein sollen. Zuerst Repository, Historie, Releases, Projektanhänge und erreichbare Projektquellen durchsuchen.
- Fehlt nachweislich der für ein sicheres Installationspaket notwendige aktuelle Quellstand, eine **konkrete Lücke** benennen und die bestmögliche sichere Alternative liefern. Keine Scheinlösung, kein erfundenes ZIP, keine unnötige Fragerunde.
- Keine kostenpflichtigen APIs, zusätzlichen Plugins, Live-Installationen oder Hosting-Änderungen ohne ausdrücklich dafür erteilte Freigabe.

## 4. Geschützte Architektur und Qualität

Unangetastet ohne gesonderte konkrete Freigabe: Kategorien/Zulassung, Datenquellen, Ranking/Sortierung, Filter/Chips, GPS/PLZ, Radius/Fallbacks, Zeit-/Öffnungslogik, Event-Sync/Lifecycle, SEO-URL-Pfade, Canonicals, API-Verträge, App-Kompatibilität und Deep Links. Fehlende Bilder dürfen keine Einträge ausblenden. Keine pauschalen String-Ersetzungen, keine zweite SEO-Engine. Bei Domain-/Brandingkorrekturen öffentliche Markentexte von historischen technischen Kennungen unterscheiden.

## 5. Kommunikation

- Bei Entwicklungsaufträgen zu Beginn maximal kurz das Ziel und die geschützten Bereiche bestätigen; danach eigenständig durcharbeiten. Nur bei einer echten erforderlichen Entscheidung/Freigabe unterbrechen.
- Keine Rechtfertigungsschleifen, keine Vorschläge zu bloßen Workarounds, die der Nutzer ausdrücklich ausgeschlossen hat.
- Nur tatsächliche Ergebnisse und überprüfbare Blockaden melden; Verantwortung für Suche und Wiederverwendung liegt beim Assistenten.

## 6. Offener, konkret bekannter Domainwechsel-Fall (Stand 26.10.2026)

Beispiel-URL: `https://www.pottfuchs.de/ort/16936/essen/zum-schwarzen/`

Gewünschte Ergebnisse:
1. HTML-/SEO-Titel **`Zum Schwarzen Essen | PottFuchs`** (nicht den fachlichen Restaurantnamen/Permalink ändern).
2. Keine alte öffentliche Bezeichnung `RuhrpottGuru` im Suchergebnistitel; den bestehenden Detailseiten-SEO-Owner verwenden.
3. Google soll nach Möglichkeit als Websitenamen **PottFuchs** statt der Domain anzeigen: WordPress-Name, vorhandene WebSite-Strukturdaten und `og:site_name` konsistent prüfen. Googles Darstellung lässt sich nicht erzwingen.
4. **Das bereits vereinbarte und verwendete Favicon behalten**, nicht neu gestalten/auswählen. Nur die vorhandene Einbindung/Erreichbarkeit prüfen und ggf. gezielt reparieren.

Aus Archiv/Prüfberichten: WPCode #3343 allgemeiner SEO-Owner; #4102 für Teile der Detail-SEO; Places-Backend für Orts-/Event-Detailinformationen; Theme bindet ein `favicon.svg` ein. Vor Patch gegen tatsächlichen aktuellen Code verifizieren. `docs/current-state.md` dokumentiert **Theme v345 / Places v1.10.169 als archivierte Quellen**. Audit vom 23.10. nennt **Theme v350 / Places v1.10.181 als damaligen Live-Stand**, nicht als durch dieses Repository abgedeckte aktuelle Quelle. Aktive WPCode-Snippets und späterer Code fehlen im GitHub-Archiv teilweise. Kein Live-Patch behauptet.

Weitere Domain-Checks (eigener Prüfauftrag, nicht ungefragt ändern): alte Domain → neue Domain pfadgleiches 301, Canonical/Sitemap, www/non-www, App-/REST-Kompatibilität, Search Console Indexierung.

## 7. Erfolgskriterien für diesen Arbeitsstandard

Ein neuer Projekt-Chat lässt sich daran messen, ob der Assistent vor einer neuen Implementation:
- den vorhandenen GitHub-Archivstand und den konkreten Owner **nachweislich** identifiziert;
- keine bekannten Projektentscheidungen wieder zur offenen Frage macht;
- das vereinbarte Lieferformat tatsächlich erfüllt oder eine reale, präzise technische Grenze belegt;
- nach einer Lieferung Quellstand, Prüfsumme, ZIP und Nachweise auffindbar hinterlegt.

Dieses Dokument ist ein **Arbeitsablauf**, kein Garantieversprechen über automatisch geladene Erinnerung und keine Freigabe für technische Änderungen.
