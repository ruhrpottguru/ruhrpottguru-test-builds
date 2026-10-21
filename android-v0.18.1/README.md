# Android v0.18.1 source overlay

Apply after android-v0.18.0 and all its bases. Only the introduction removes the visible pool count: "Pro Runde 10 gemischte Fragen." The remaining wording, 200-question data, selection, scoring, state and every other feature are unchanged. app/build.gradle only increments versionCode 23 and versionName 0.18.1-test. No website changes.

Local JDK 17 / Gradle 8.13 / SDK 36 release build; existing private test signing key. See release-checks.json for checks and physical-device limitations. Rollback preserving local data: restore prior intro and rebuild with a higher versionCode and same key; do not uninstall.
