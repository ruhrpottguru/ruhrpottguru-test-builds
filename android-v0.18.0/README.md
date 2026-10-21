# Android v0.18.0 – Pott-Quiz 200 questions

Apply after android-source.zip and overlays android-v0.16.1, android-v0.17.0, android-v0.17.1, android-v0.17.2. Keep the existing test key privately; no new signing identity. No website changes and no GitHub Actions build.

Canonical source: PottQuiz Web plugin v2.3.0 questions.json. The 200 records, all four choices and explanations are copied byte-for-byte into assets/quiz/questions.json. Read-only live /wp-json/rpg-quiz/v1/questions matched all questions and poolVersion 2.3.0 on 2026-10-21. Content is reused, not independently rewritten or fully fact-checked here.

The existing native quiz UI/transitions, rank boundaries, percentage highscore preference and share action remain. Each round draws 10 distinct questions in balanced category passes, shuffling choices and remapping the correct index exactly as the Web helper does. tests/fixtures/quiz-web-round-selection.js is that original helper; node tools/quiz-web-goldens.cjs regenerates five deterministic reference rounds. Kotlin tests compare exact parity, 1,000 additional balanced rounds, all 800 answer cases, scoring and stable round/choice order on state restoration. Old saved quiz state from the 10-question implementation is safely discarded; the existing highscore remains.

The packaged pool works offline and does not introduce a new REST dependency or automatic content updates. Further pool revisions require a new app build. Other location, category, search, favorites, reminder and privacy files are unchanged.

Local JDK 17 / Gradle 8.13 / SDK 36: testReleaseUnitTest assembleRelease lintRelease; node --test tests/*.test.cjs. See release-checks.json for outcomes and physical-device limitations. Rollback: restore prior quiz files and rebuild using a higher versionCode and same key to preserve app data; do not uninstall.
