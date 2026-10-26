# Duisburg – Quellabgleich 26.10.2026

## Nachgewiesen
Installierter Backend-Stand 1.10.188 am 26.10. live verifiziert. Korrigierter Zusatzfeed verarbeitet weiter: zuletzt 36/53, neu 316, aktualisiert 7.492, unverändert 1 vorher vorhandener Fehler. Keine neue Installation/Automatikaktivierung und keine weiteren Importaufträge gestartet.

Drei ausschließlich lesende Abfragen des tatsächlich im vorhandenen Supplemental-Transport verwendeten ET4-Endpunkts https://meta.et4.de/rest.ashx/search/ mit experience=open-data, type=Event, template=ET2014A.json, unique=true, mkt=de-DE, sort=title asc:

| Abfrage | HTTP / Providerstatus | Gesamtobjekte |
|---|---|---:|
| city:"Duisburg", 26.10.–26.10.2026 | 200 / OK | 0 |
| city:"Duisburg", 26.10.–01.11.2026 | 200 / OK | 1 |
| ohne Stadtfilter, 26.10.–26.10.2026 | 200 / OK | 266 |

Der positive Duisburger Kontrolltreffer ist e_100790614, Hexenführung. Diese Veranstaltung ist im bestehenden PottFuchs-Bestand (#8292) und öffentlicher Duisburg/5km-Ansicht tatsächlich vorhanden. Die 266 sind Quellobjekte im überregionalen Feed, keine behaupteten Ruhrgebiets- oder Radius-Treffer.

Beleg: https://github.com/ruhrpottguru/ruhrpottguru-test-builds/actions/runs/40359068686 . Reproduzierbarer Read-only-Prüfer: tools/pottfuchs_upstream_duisburg.py. Ursprünglicher Einzelabruf: 40358869648, ebenfalls 0 Duisburg/heute. Keine WordPress-Schreibzugriffe durch diese Abfragen.

## Schlussfolgerung und Grenzen
Für diesen bereits verwendeten offenen Feed ist heute kein Duisburger Quellobjekt vorhanden. Der Nulltreffer im heutigen WordPress-Bestand und Heute-Filter wird nicht durch die Wesel-Koordinaten-Sonderregel erklärt. Es ist ausdrücklich NICHT bewiesen, dass es in Duisburg tatsächlich keine Veranstaltungen gibt. Ein neuer offizieller kommunaler Kalender wäre eine gesonderte Quellen-/Rechteentscheidung; keine ungenehmigte Erweiterung vornehmen. Der lizenzierte Master wurde durch diesen offenen externen Quellenabruf nicht nachgebildet. Der freigegebene Hot14-Lauf war bereits erfolgreich abgeschlossen; heute 0 Duisburger DB-Zeilen unabhängig vom Bild-/Adressfilter.

## Offen
Aktuellen 53-Städte-Lauf vollständig abschließen und Fehlerliste prüfen. Kein neuer Master/Neustart des gesamten Zusatzfeeds ohne ausdrückliche Freigabe. Web-Stichproben nach Installation bestanden; reale Android-Abnahme ausstehend. P0-Gesamtabnahme nicht pauschal als abgeschlossen führen.
