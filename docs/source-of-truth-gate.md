# PottFuchs – Quellstand-Gate und Release-Register (27.10.2026)

**Geltung:** GitHub-Quellenauswahl und Release-Nachweise; kein WordPress-Deployment. Ergänzt AGENTS.md, POTTFUCHS-ARBEITSSTANDARD.md, storage-policy.md und current-state.md; fachliche Regeln bleiben unverändert.

## Maßgebliche Quellenauswahl
1. Die Dokumente in AGENTS.md und globale Standort-/Sichtbarkeitsregeln lesen. Danach `docs/release-registry.json` und GitHub-Releases **beider Repositories**, nicht nur main, lesen.
2. Komponente und benötigte Fachänderung identifizieren. Für jeden Kandidaten Release-Tag, **tatsächlichen Commit**, Source-Verzeichnis, Paket, SHA-256, Tests, Rollback und Kompatibilität vergleichen.
3. Archiviert ≠ geprüft ≠ Betreiberinstallation ≠ live verifiziert. Ein höherer Tag, ein neuerer Branch oder das GitHub-main-Datum beweist weder Fachrichtigkeit noch Installation. Unbekannte Felder bleiben null/unverified.
4. Ist der letzte geprüfte Quellcommit nicht in main, **von diesem Commit einen Feature-Branch ableiten**. Niemals einen älteren main-Bestand über aktuellere Komponenten kopieren. Änderungen anderer Komponenten nicht blind zusammenführen.
5. Vor dem Patch Diff-/Hashprüfung des tatsächlich benötigten Quellbaums, vorhandene Tests und gegenläufige GitHub-Branches/PRs prüfen. Vor Release lokal bauen und regressionsprüfen; Deploy nach Freigabe gesondert.
6. GitHub Actions validiert Registerstruktur und Referenzen; **grünes CI ist kein Live-Nachweis**. Stand pro Komponente nur mit belegter Evidence ändern.

## Repository-Zuständigkeiten
- WordPress-Theme und eigene Plugins: dieses Repository, jeweils bestehende Verzeichnisse unter `wordpress/`.
- Android-App: [privates Android-Repository](https://github.com/ruhrpottguru/ruhrpottguru-android); Release-Basis ist dessen genau bezeichnete Source-Commit, nicht dessen main.
- REST-Verträge: Backend-Owner in WordPress; Android prüft als Verbraucher über Fixtures/Kompatibilitätstests. Historisches `ruhrpottguru/v1` erhalten.
- Tests/Fixtures in den bisherigen `tests/`-, `tools/`- und Android-Verzeichnissen. Nicht duplizieren.
- GitHub Releases enthalten installierbare/Play-Artefakte, Hashliste, Manifest, Testreferenzen und Rollback. Private Signierschlüssel/Token niemals ins Repo.
- `main`: langfristig integrierter **verifizierter Quellstand**, nicht automatisch produktiv installiert; Herkunftsdifferenzen zunächst auf isolierten Governance-/Release-PRs bewerten. Vorher keinen ungeprüften Source-Merge.
- `feature/*`, `fix/*`, `release/*`: kurz- bzw. releasebezogene Entwicklung. Historische Branches/Tags bleiben unangetastet.

## Bekannte Abgrenzung
- Places 1.10.190: Quellcommit `fd6e0818f67a0e7a67cc01467acefef7937bcdfc`, Release im WordPress-Repo; 26.10. live verifiziert laut dokumentierter SHA-Hauptdatei. Die Komponente ist nicht deshalb durch main repräsentiert.
- Theme 351.0: live als Basis für einmaliges Adressrouten-Patch 1.0.0 dokumentiert. Die gepatchten Dateien und Rollback sind im **privaten Android-Release** archiviert; vollständige Theme-351-Quellparität mit main nicht als nachgewiesen markieren.
- Android beta.15 / versionCode 41: Release `pottfuchs-address-routes-v1.0.0`, Source `2a6af591966a0d263071feffb71f90486746488d`, Basis `bcbd8c696f347b23ed572e568417325596bbb95f`. Play-Veröffentlichung/Geräteinstallation nicht automatisch angenommen.

**Wichtig:** Das Register ist ein Nachweisindex, keine alternative Kopie von Artefakten oder eine Live-Abfrageschnittstelle. Änderungen an Installations-/Live-Feldern benötigen neue unabhängige Belege, niemals nur einen Versionsvergleich. Branch Protection erfordert ggf. GitHub-UI-Adminzugriff.
