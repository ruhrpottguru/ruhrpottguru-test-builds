# Android Play 0.19.0-beta.2 – launcher and launch-screen branding

Base commit 2a7f93fd9fbcebc1390216fd55f6c0ce8a4648fe (Play code25). Apply this manifest after that source reconstruction. User requested round Instagram-style app logo filling the launcher circle, plus a black launch screen with this logo and white border. No website or other app functions changed.

The provided 150x150 reference was restored to a 1254x1254 transparent PNG using the built-in image editor (background extraction, faithful logo restoration, smooth white circular border). Retained exact visible text RUHRPOTT / GURU / ENTDECKEN. ERLEBEN. LIEBEN., orange sun and location pin, white mine tower and black skyline. AI-restored small details are not asserted pixel-identical to the low-resolution reference. The existing header logo remains unchanged.

Launcher: Android adaptive icon with black background, roundIcon registration and centered foreground occupying two-thirds of the 108-unit canvas. Badge fills approximately 94% of the circular launcher mask while critical lettering stays inside the safe region; OEM icon masks may vary. minSdk26, so adaptive icon is used across supported devices. Badge transparency/white border retained.

Launch: API26–30 black starting-window drawable, centered 192dp badge; API31+ explicit black system SplashScreen background and separate 288dp canvas with 192dp inner icon. No forced delay, extra splash Activity or new dependency. MainActivity only adds setTheme(AppTheme) before super.onCreate to restore the existing app appearance; GPS and all existing code remain unchanged.

VersionCode26; Play 0.19.0-beta.2, direct APK configuration 0.19.1-test. Same Play package de.ruhrpottguru.app and same private upload key as code25. Default direct APK identity and private test key remain unchanged. This release supplies the Play AAB only. Upload as a NEW internal test release; it is an update, not a new Play app. Keep current Play App Signing settings. No private key in this public archive.

Tests: existing 43 JUnit / 11 JS suites, release build/Lint, bundletool validation, manifest/entry-point/icon/theme checks, signatures, Android26/36 split generation and 16KB ELF/ZIP alignment; see release-checks.json for actual results. Resource checks do not prove a real launcher/splash rendering on every Android device. Physical device acceptance and actual Play rollout are separate. Existing privacy DRAFT unchanged; no public/closed production readiness claim.

Rollback: restore preceding branding resources/manifest/theme call and rebuild same package/signature with a higher versionCode. Never uninstall to downgrade or lose local data. No changes to categories, filters, radius/GPS, quiz, favorites, reminders, sharing or backend.
