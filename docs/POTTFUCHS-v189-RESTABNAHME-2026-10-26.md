# PottFuchs v189 – verbleibende Abnahmegrenzen, 26.10.2026

## Abgeschlossener Auftrag
Installation und tatsächlicher Importabschluss sind in POTTFUCHS-v1.10.189-IMPORT-ABSCHLUSS-2026-10-26.md nachgewiesen: aktiv v189, finished 53/53, 551 neu, 7711 aktualisiert. Keine erneute Installation und kein Neustart erforderlich.

## Erneute Prüfung der gespeicherten Releasebytes
ZIP-Integrität beider wiederabgerufenen Archive bestanden. Installations-ZIP: 729483 Bytes, SHA-256 43aa7de41d0a6f4d2750df09600474b2cfb95fb2cc146ef5297d8189a2e091c5. SEO-erhaltendes Rollback: 729417 Bytes, SHA-256 44f58e09d8866cbb2f65b0eea278af8dae48801e62468ef45d43b27c6adfcce1.

12 vorhandene öffentliche Audit-/Geodaten-/REST-Parserprüfungen bestanden. Dies sind Werkzeugtests und keine erneuten Live-E2E-Tests.

## Globale Standortanforderungen noch nicht implementiert
Die vorhandenen 12 Charakterisierungsprüfungen test_location_contract_v186.py wurden gegen die tatsächliche v189-Release-ZIP ausgeführt: 12 bestanden. Damit sind die historischen Einschränkungen auch in v189 bestätigt. Alle vier RED-Anforderungsprüfungen test_global_city_scope_requirements.py scheitern erwartungsgemäß: Wesel-Stadtgate, Wesel-Quellgate, unpaginiertes LIMIT 500 und Defaultlabel Wesel bestehen fort. Diese roten Tests gehören zum gesonderten globalen Standortauftrag; die 227 erfolgreichen Import-/Suchassertions des v189-Release sind davon zu unterscheiden.

Kein Beleg, dass diese Regeln die Duisburger Heute-Nulltreffer verursachen: der Quellabgleich und gespeicherte Bestand ergaben für diesen Tag ebenfalls null. Keine Erweiterung zugelassener Quellen.

## Konkrete Änderungsgrenze
Eine mögliche nächste Backend-Änderung betrifft frontend_events_query(): bestehende Stadt-/Quellsonderzulassung für koordinatenlose Events, Kandidatenpaginierung und City-Scope-Label. Sie greift in die geschützte Sichtbarkeits-/Radiusbehandlung ein. Die verbindliche globale Regel erklärt ausdrücklich: dieses Dokument ist keine Implementierungs- oder Live-Freigabe. Projektvertrag Abschnitt A.2 verlangt vorherige konkrete ausdrückliche Freigabe für Filter, Distanzlogik und Radius-Fallbacks. Der allgemeine Arbeitsauftrag wird nicht als stillschweigende Aufhebung dieser konkreten Grenze behandelt. Keine entsprechende Codeänderung, neue ZIP oder Liveänderung vorgenommen. Bestehender v187-Entwurf bleibt nicht freigegebener Entwicklungsstand und ist keine Installationsdatei.

Vor einem solchen Release müssen allgemeine/städtische Auffindbarkeit von exakter Radiuszugehörigkeit getrennt, Koordinaten-/Adressresolver wiederverwendet und alle betroffenen Kategorien/Specials/Web-/App-Verträge geprüft werden. Kein City-Mittelpunkt als Routenziel, keine Entfernung aus Sortiersentinel, keine Eintragsausblendung wegen fehlendem Bild.

## Android-Nachweisgrenze
GitHub-Connector kann die vorhandene Android-Quelle lesen; fehlender Git-CLI-Login ist keine generelle Quellzugriffssperre. In der verfügbaren lokalen Testumgebung gibt es jedoch weder adb noch Android-Emulator und kein konfiguriertes Android-SDK. Ein echtes Android-Endgerät ist nicht angeschlossen. Eine Geräteabnahme kann deshalb hier nicht behauptet werden; Quelltext- oder Browserprüfungen ersetzen sie nicht.

Keine Aktivierung der Automatik, keine weiteren Importstarts, keine kostenpflichtigen Dienste und keine Live-Code-/Datenbank-/Hostingänderungen.
