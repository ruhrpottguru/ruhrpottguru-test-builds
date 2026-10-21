# Android Play 0.20.0-beta.2 – compact search and quiz navigation

Apply these two source files after source commit 51715efdb21eaef7471150bc9f1fd5a7d7a11984 (0.20.0-beta.1). Play versionCode28, package de.ruhrpottguru.app; retain existing private upload key and Google Play App Signing. Direct test configuration remains de.ruhrpottguru.testapp / 0.20.0-test; not a new direct APK delivery.

User scope: replace the always-visible header search field with a 48dp accessible magnifier next to the crosshair. It opens the existing native search screen; the existing search field appears only on that screen, hidden while a detail is open. The magnifier is available on ordinary header screens, including details; fullscreen time travel retains its hidden header. The footer third destination now opens the existing Pott-Quiz with a question-mark icon. Food remains on Home and in the existing menu.

No API, search policy/state, GPS, categories, quiz content, images, reminders, favorites or website changes. All previous search behavior retained. No parallel search logic and no new dependencies. Only MainActivity and build version changed. Tests and evidence in release-checks.json. Physical phone navigation, keyboard and visual acceptance remain pending. Preview is a source-based illustration, not a device screenshot.

Rollback: use the preceding source and rebuild with the same Play package/key and a higher versionCode; do not uninstall or downgrade. Not uploaded to Play Console and not deployed to the website.
