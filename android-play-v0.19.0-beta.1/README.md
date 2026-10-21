# RuhrpottGuru Android Play internal test 0.19.0-beta.1

Source base: commit 020ea2a17851bdb6340eb3168e9fe633df527acb, Android 0.19.0-test. Restore original base and all documented overlays, then apply this manifest. No app-function or website changes.

User confirmed Play package de.ruhrpottguru.app on 2026-10-21. Build opt-in -PplayStore=true selects this applicationId, versionCode 25, versionName 0.19.0-beta.1, launcher label RuhrpottGuru and a regular RSA upload signing key supplied via environment. Existing namespace/classes remain unchanged; merged manifest entry points must remain fully qualified de.ruhrpottguru.testapp.*. Default APK build remains package de.ruhrpottguru.testapp/code24/name0.19.0-test with its unchanged private test key and label.

Private upload key is NOT in this public repository. Preserve the supplied private key backup separately. Configure environment RPG_UPLOAD_STORE_FILE, RPG_UPLOAD_STORE_PASSWORD, RPG_UPLOAD_KEY_ALIAS and RPG_UPLOAD_KEY_PASSWORD before running Gradle. Missing Play credentials fail immediately; no silent fallback to test key. Google Play should generate/manage the actual app signing key; this local key is upload-only.

Local command (JDK17 / Gradle8.13 / SDK36 / buildtools36.0.0): gradle --no-daemon --max-workers=2 -PplayStore=true clean testReleaseUnitTest bundleRelease lintRelease. No Actions build. Build tools require standard Android SDK license acceptance and configured sdk.dir/ANDROID_HOME. SDK paths and private signing files are machine-local, not source overrides.

Verify AAB with jarsigner, bundletool validate, dump merged manifest, generate universal APK and Android26/36 device split APKs, verify signatures. Inspect 16KB ZIP/ELF alignment of native libraries. Run all 11 existing JS suites and 43 JUnit tests. See release-checks.json for actual outcomes and SHA256. These are build/static/JVM/JS tests, not a physical install, Play review or actual notification-delivery test.

App functions and existing offline privacy DRAFT remain byte-identical. This package is intended only for the initial INTERNAL test; do not treat the draft as a publication-ready policy. Final privacy/data safety/store listing and real-device acceptance remain before closed/public rollout. No Play upload, Console mutation, account registration, production rollout or website publication is performed by this source change.

Separate package means the existing test app stays installed; favorites/settings are not automatically migrated. Never uninstall the old app expecting a migration. No cross-app data transfer was added.

Rollback: use the previous source for direct test APKs; for an already-uploaded Play release rebuild restored source with the SAME Play package/upload identity and HIGHER Play versionCode. No private keys, password files or personal data belong in this archive.
