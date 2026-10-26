# Duisburg P0 – authentifizierter Lesebefund, 26.10.2026

WordPress-Anmeldung in Cloud Browser erfolgreich; kein WPVibe. Ausschließlich lesende Diagnose, keine Installation und kein Importstart.

## Tatsächlich beobachtet
- Plugins: Places Backend v1.10.185 aktiv. Theme-Dateiprüfung: ruhrpott-guru v351.0.
- RP Redaktion > Events: Stadt Duisburg, Termin Heute, alle Quellen/Status: 0 Zeilen.
- Gleiche Stadt, nächste 7 Tage: 4 Zeilen, alle am 31.10.: IDs 8292, 18813, 18811, 18812.
- Kontrollabfrage ohne Terminfilter, Status Aktiv, Limit 200: erster aktiver Duisburger Termin ebenfalls 31.10.; keine heute überlappenden aktiven Duisburger Termine in dieser Ergebnisliste.
- Die bekannten vier Halloweenzeilen haben vollständige Adressen. Kein Nachweis, dass die Wesel-Koordinatenregel die heutigen Nulltreffer verursacht.
- Importzentrale: manuelle Einzelsteuerung; Automatik abgeschaltet; drei Aufträge in Warteschlange, Lock frei. Zusatzfeed-Pause angefordert.
- 53-Städte-Zusatzfeed laut gespeichertem Status 33/53, Beginn 27.09.; kein abgeschlossener aktueller Gesamtlauf. Letzter vollständiger Master 27.09.
- Hot14-Lauf am 26.10. 11:40–11:57 abgeschlossen, 68 neu / 211 aktualisiert. Fehlende aktuelle Duisburger Zeilen sind deshalb nicht allein mit einer global abgeschalteten Automatik erklärt.

## Konsequenz und Freigabegrenze
P0 nicht behoben. Fehlende aktuelle Termine im Bestand sind belegt; vollständige Ursache der Quelllücke und Frontend-Fallback noch offen. v1.10.187 bleibt Entwicklungsstand; nicht auf Basis dieser Diagnose installieren. Die stadtübergreifende Koordinatenänderung ist kein belegter Fix für fehlende heutige DB-Zeilen.

Ein kontrolliertes Fortsetzen des vorhandenen pausierten 53-Städte-Abgleichs ist ein Live-Schreibvorgang (bestehende Events werden aktualisiert, neue angelegt). Dafür ist nach Projektregeln eine ausdrückliche Freigabe erforderlich. Keine pauschale Reaktivierung der Automatik, kein Masterstart und kein Umgehen des Locks. Bis dahin nur lesen.
