# Android v0.17.1 source overlay

Apply after android-source.zip, android-v0.16.1 and android-v0.17.0 overlays. This overlay changes only ReminderUi.kt and the app version (versionCode 20). The existing reminder handlers, storage, notification scheduling and backend requests are unchanged. No website changes.

Event reminder details start collapsed for each event. The full compact status row toggles the existing controls and explanatory information. Long status text may wrap; the tap target is at least 56 dp. Button role, action label and expanded/collapsed accessibility state are provided.

Local build: JDK 17, Gradle 8.13, Android SDK/build tools 36.0.0. Restore the existing test keystore privately; never replace it. No GitHub Actions build is used.

Check release-checks.json for the local build/test/signature result. Real-device UI and notification acceptance remain open. Rollback without losing favorites: restore the prior ReminderUi.kt, retain a higher versionCode, and rebuild with the same key. Do not uninstall to downgrade.
