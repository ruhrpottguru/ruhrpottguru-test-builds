# Android v0.17.0 source overlay

Base: existing android-source.zip, then android-v0.16.1/manifest.json overrides, then this directory's manifest.json overrides. Keep all original source, resources and fixtures. Restore the existing test keystore privately to signing/test-debug.jks; never generate a replacement key for an update.

Local JDK 17 / Gradle 8.13 / SDK 36: testReleaseUnitTest assembleRelease lintRelease, plus node --test tests/*.test.cjs. No GitHub Actions build is required.

Saved favorites retain the native-location-v2 / saved-v1 storage and original canonical snapshots. Reminder metadata is additive. Worker checks use the existing read-only share-item and events APIs. Public feed disappearance pauses reminders without claiming a confirmed cancellation. No website changes. Android background/permission/device acceptance remains separate from JVM and source regression tests.
