PottFuchs Koordinaten zu Adresse 1.1.0
Stand 27.10.2026

Installation: WordPress > Plugins > Installieren > Plugin hochladen > PottFuchs-Koordinaten-zu-Adresse-v1.1.0.zip. Bei vorhandenem pottfuchs-address-display die bestehende Version ersetzen. In Einstellungen > PottFuchs Adressauflösung > Aktivieren anhaken und speichern.

Die Erweiterung nutzt den bereits bei PottFuchs vorhandenen WordPress-Optionswert rpg_geoapify_api_key. Ohne diesen Wert oder einen im Plugin eingetragenen Geoapify-Key werden KEINE API-Anfragen ausgelöst. Der Schlüssel wird nicht an den Browser ausgegeben. Prüfbar im WP-Admin über den Endpunkt /wp-json/pottfuchs/v1/reverse-address?lat=51.445&lng=7.008 (nur für Admins).

Wirkungsweise: Nur bestehende WP REST-Responses /ruhrpottguru/v1/events, /global-search und /places/home/industrial-route/buedchen/night/wurst werden ergänzt; die JSON-Einträge bekommen eine lesbare Adresse und ein strukturiertes Venue-Adressobjekt. Volle Adressen bleiben geschützt, Stadt-Scope-Einträge werden nicht geocodiert, keine Datenbank-Datensätze geändert, keine Routen oder Entfernungen erfunden. Max. 2 nicht gecachte externe Abrufe pro Response und max. 75 täglich; 180 Tage Cache für genaue Treffer. Nicht eindeutig bestimmbaren Hausnummern wird keine erfundene Adresse zugewiesen.

Rücknahme: Plugin deaktivieren (REST-Anreicherung stoppt unmittelbar). Bereits gecachte Transients laufen regulär aus; keine Rohdaten werden geändert. Das alte Plugin v0.1.0 bleibt nicht parallel installiert, weil der Verzeichnis-Slug identisch ist.

Getestet lokal: PHP 8.4.24 php -l; 10 isolierte Assertion-Tests unter WP-Mocks; ZIP-Lesbarkeit; SHA-256. Keine Live-Installation, keine reale Android-Geräteprüfung. Android v0.20.0-beta.15 normalisiert eventAddress(item) inklusive location.address und zeigt daher strukturiert ergänzte Eventadressen an, sofern diese App-Version die aktualisierte REST-Response tatsächlich bezieht. Ältere App-Stände und Caching können abweichen.
