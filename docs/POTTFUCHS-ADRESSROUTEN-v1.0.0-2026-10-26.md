# PottFuchs Adressrouten v1.0.0 – finale Lieferung

Datum: 26.10.2026. Nutzer konkretisiert den bestehenden Standortvertrag: Eine vollständige verlässliche Adresse darf direkt als Routenziel verwendet werden. Vorherige Koordinatenberechnung ist dafür nicht erforderlich. Frühere v190-Testaussage, bei vollständiger Adresse ohne Koordinaten sei das Fehlen des Routenbuttons das gewünschte Ergebnis, ist als Sollanforderung ausdrücklich überholt. v190 bleibt die abgeschlossene Sichtbarkeitskorrektur; dieses Paket korrigiert die Routenwahl.

## Änderung und Schutz
Bestehender Web-/App-Owner eventAddress() wird für die Zielprüfung wiederverwendet. Bestehende Detailansicht und natives Detail-DTO verwenden dieselbe directionsUrl()-Funktion: verlässliche Koordinaten zuerst, andernfalls vollständige strukturierte Ortsadresse. Straße/Hausnummer/PLZ/Stadt erforderlich; keine Route nur zum Stadtzentrum oder zu PLZ/Stadt. Veranstalter-, Kontakt- und Provideradressen werden nicht als Veranstaltungsort verwendet. Bei kanonischem DB-Event wird eine unvollständige kanonische Adresse nicht durch eine ältere Raw-Adresse ersetzt. Keine Koordinaten/Entfernung erzeugt, keine Geokodierungsdienste oder Gebühren, keine Änderungen an Sichtbarkeit, Quellen, Kategorien, Sortierung, Radius, Importen oder Lifecycle.

## Web-Paket und Ausgangsprüfung
Aktives Theme 351.0 und vollständige Hashes der benötigten Dateien authentifiziert lesend geprüft. runtime.js SHA-256 58380d237b696409722c878a92f2d453381296c024be7acd5262a5f62a548393 unverändert. Original api.js d1098bf1515d9d581a7857c416eaf5ffcda56e5804780badd5152284c1210e43; Original components.js 41128c31e8f47f8afe09bce6be87216f885826f089a583b27e25a0897be1a6b5. Diese drei Dateien stimmen mit dem verfügbaren Original-Theme-Paket überein.

Installierbares einmaliges Patch-Plugin ändert ausschließlich api.js und components.js nach Prüfung beider Originalhashes, ohne Theme-/Backend-Downgrade. Theme wird als 351.0 plus Adressrouten-Patch 1.0.0 geführt; Backend v1.10.190 bleibt unverändert. Unbekannter Dateistand wird abgelehnt. Keine API-Schlüsseldateien im Installations-/Rollback-Paket; kein vollständiger Theme-Export benötigt. Deaktivierung des Installationsplugins nimmt die Änderung nicht zurück.

Installation: PottFuchs-Adressrouten-v1.0.0-Installation.zip unter Plugins hochladen und aktivieren. Rollback: separates PottFuchs-Adressrouten-v1.0.0-Rollback.zip hochladen und aktivieren; stellt nach Hashprüfung genau beide Originaldateien wieder her. Kein Datenbank-/Schema-Update, kein Importneustart, keine Automatikaktivierung.

## Android
Basis: aktueller privater play-build-Stand bcbd8c696f347b23ed572e568417325596bbb95f, mit bereits vorhandenen PottFuchs-/beta.14-Änderungen. Das ältere main ist NICHT die Basis. Neues signiertes Bundle 0.20.0-beta.15, versionCode 41, bestehende Play-App-ID und Upload-Zertifikat erhalten. Originaler Launcher und übrige App-Funktionen bewahrt. Keine automatische Veröffentlichung/kein Upload zu Google Play. Verteilung über den bestehenden Play-Testkanal erforderlich.

Android-Quellrollback bleibt der Basiscommit; nach verteilter v41 bei notwendiger Rücknahme mit höherem versionCode neu bauen. Keine irreführende Zusage eines Play-Downgrades auf kleinere Versionsnummer.

## Tatsächliche Prüfungen
- 37 funktionale Web-Routenassertions und 40 Android-Assertions einschließlich echter nativer Liste/Detail bestanden.
- Vier zusätzliche Assertions gegen die tatsächliche Web-DetailOverlay-Renderfunktion bestanden: Adressroute sichtbar, reiner Stadtbezug ohne Route, Koordinatenziel erhalten. Wiederverwendbarer Test: tools/pottfuchs_address_route_render.cjs mit dem Quellverzeichnis als Argument.
- Echte isolierte WordPress-Installation und Aktivierung des finalen Installationsplugins: sechs Assertions einschließlich Hashprüfung, Idempotenz und Ablehnung fremder Änderungen ohne Überschreiben der anderen Datei. Echte Rollback-Aktivierung: drei Assertions, bytegenauer Ausgangsstand und idempotente Rücknahme. Beide Plugin-PHP-Dateien syntaktisch fehlerfrei.
- Sämtliche 14 vorhandenen Android-JavaScript-Testskripte, bestehende JVM-Tests und Android-Lint bestanden. Enthält u. a. 1635 Kultur-/Aktivitäten-Filtervergleiche, 176 Web-/native Detailvergleiche, 366 Event-Filtervergleiche, 195 Büdchen-/Imbissvergleiche und 504 Special-Vergleiche; keine Summe aus unterschiedlichen Nachweisarten als einheitliche Testzahl gebildet.
- Signiertes AAB erfolgreich gebaut; vorhandenes Upload-Zertifikat geprüft. Build-/Prüflauf: https://github.com/ruhrpottguru/ruhrpottguru-android/actions/runs/40412704412.
- Tatsächliche private Release-Assets erneut heruntergeladen, alle ZIP-/AAB-Prüfsummen und Integrität sowie Signatur/Zertifikat erneut geprüft: https://github.com/ruhrpottguru/ruhrpottguru-android/actions/runs/40414232675 (erfolgreich).
- Workflow-Artefakte zusätzlich in den Arbeitsbereich wiederabgerufen; beide ZIPs und AAB-Integrität/SHA-256 stimmen mit Release-Digests überein.

## Dateien und Archiv
- PottFuchs-Adressrouten-v1.0.0-Installation.zip: 62253 Bytes; SHA-256 2aa21ee6de7cb864e63765e21c6166bd579b984e03657a8bcb837d1602985241.
- PottFuchs-Adressrouten-v1.0.0-Rollback.zip: 61850 Bytes; SHA-256 d0e2157d7bc4839e1af78b1d85b8dd62f128c2dc6ac6fe044345ad3bb19c3a25.
- PottFuchs-0.20.0-beta.15-v41.aab: 12490473 Bytes; SHA-256 4dc00248fb54a579d6a9492c2f652f585d57eb285113126d0c3e72f82395b7cf.

Source-/Release-Commit 2a6af591966a0d263071feffb71f90486746488d im bestehenden privaten Android-Repository, Branch fix/address-route-1. Release mit Quellstand, Paketen und Prüfsummen: https://github.com/ruhrpottguru/ruhrpottguru-android/releases/tag/pottfuchs-address-routes-v1.0.0. Originale und geänderte benötigte JavaScript-Dateien sowie Builder, Installer-/Rollback-Tests und Prüfworkflow im Quellcommit; keine API-Zugangsdaten archiviert.

## Noch offen
Keine produktive Aktivierung durch den Assistenten. Produktive Web-Abnahme nach Betreiberaktivierung und echter Handy-/Maps-Test nach Verteilung der neuen App-Version bleiben offen. Der zuvor abgeschlossene 53/53-Import wird nicht neu gestartet. Die getrennte Adress-zu-Koordinaten-Anreicherung und eventuelle OasenTag-Dubletten sind hier nicht geändert.
