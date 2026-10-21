# Android v0.17.2 source overlay – app privacy draft

Apply after android-source.zip and overlays android-v0.16.1, android-v0.17.0, android-v0.17.1. Reuses the existing Information/Datenschutz route and local HTML renderer, adds in-form access with form state retained, and reuses the existing safe external-link handler (visibility changed from private to internal only). No changes to submission payloads, permission requests, data processing, search, location, categories, reminders, quiz or website.

The privacy text is an explicitly labeled TEST DRAFT, not a legal/publication approval. Its data-flow statements were audited against native source. Operator name/contact were compared with public Impressum and existing website policy on 2026-10-21. Do not deploy the text as a legally verified public policy.

Before public app release: verify actual hosting retention/processor agreement and backup deletion; active geocoding provider/retention; actual external image/CDN recipients and third-country safeguards; submission/contact/rights-record retention and publication legal basis; beta-feedback deletion job and 12-month promise; any DPO/representative obligations. The offline page openly identifies unresolved facts instead of making unsupported promises.

Legal references reviewed: https://www.gesetze-im-internet.de/ttdsg/__25.html ; https://www.bfdi.bund.de/DE/Buerger/Inhalte/Allgemein/Datenschutz/Informationspflichten.html (search excerpt only; full fetch blocked); https://www.ldi.nrw.de/beschwerde ; https://www.ruhrpottguru.de/datenschutzerklaerung/ ; https://www.ruhrpottguru.de/impressum/ . Official EUR-Lex HTML blocked by robot challenge; no claim to have read its full text through that fetch.

Local release build JDK 17 / Gradle 8.13 / SDK 36. See release-checks.json for checks and actual limitations. Same private existing test key, no Actions build. Restore prior UI/asset and rebuild with higher versionCode and same key for rollback preserving app data; do not uninstall.
