# Verifizierte Komponenten-Baselines – 27.10.2026

Dies sind **eigenständige Quellreferenzen**, keine Kopie von Dateien nach `main` und kein Nachweis des aktuell installierten Gesamtsystems.

| Bereich | verifizierter Branch | exakter Quellcommit | Deployment |
|---|---|---|---|
| Places v1.10.190 | `source/places-v1.10.190-verified` | `fd6e0818f67a0e7a67cc01467acefef7937bcdfc` | 26.10. Live-Nachweis in `docs/POTTFUCHS-v1.10.190-LIVE-ABNAHME-2026-10-26.md` |
| Android beta.15 v41 | im privaten Android-Repo `source/android-beta15-v41-verified` | `2a6af591966a0d263071feffb71f90486746488d` | signiertes Release, Play-/Gerätestatus unbekannt |
| Web Adressrouten v1.0.0 | im privaten Android-Repo identischer Android-Releasecommit, Verzeichnis `route-fix/` | `2a6af591966a0d263071feffb71f90486746488d` | Installation nicht belegt |
| Theme v351.0 | **kein vollständig authentifizierter Source-Branch** | unbekannt | Theme 351 als Basis dokumentiert; Parität mit `main` nicht erwiesen |

`main` ist strukturell/administrativ konsolidiert, **nicht** automatisch der sichere Komponentenstand. Kein Branch wird ohne zeilenweisen Konflikt- und Testvergleich in `main` übernommen. GitHub-Release-Assets ersetzen nicht die dauerhaft verfolgbaren Sources.

## Release-Pflicht
Künftige neue Releases benötigen: Quellcommit, reproduzierbares Paket, Manifest, SHA-256, Tests, Rollback, dokumentierte REST-/Clientkompatibilität und ggf. gesonderten Live-Nachweis. Android-App-IDs und Signing-Identität bleiben; Play-`versionCode` muss strikt größer als die höchste tatsächlich hochgeladene Version sein. Keine privaten Keys in Git.

## Nachweislücken
- Branch-Protection-/Ruleset-API 403 für GitHub-App; muss im GitHub-Webinterface konfiguriert und danach überprüft werden.
- Vollständiger Theme-v351-Quellabgleich, aktive WPCode-Snippets, weitere produktive Plugins und neues Live-Release ohne Live-Erlaubnis nicht verifiziert.
- `docs/source-inventory.json` inventarisiert den ursprünglichen 19.10.-Archivimport und ist kein automatisch aktualisiertes Live-Register.
