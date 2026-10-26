# PottFuchs P0 canonical category titles – cautious one-time WPCode owner migration

Source basis: user-uploaded PottFuchs Theme v351.0 ZIP (24.10.2026), visually reviewed locally (368 files). No current category title text or title filter is found in that theme; therefore do not blindly ship or install a v351 theme replacement. Historical WPCode SEO owner: snippet post #3343.

This guarded migration targets only five category title suffixes in post_content of the existing WPCode #3343 snippet and invalidates the WPCode snippet cache. If exact five source patterns are not present, activation makes **no change** and reports the mismatch in WordPress admin; do not retry repeatedly. It never changes canonical paths, site names, categories, REST, import, GPS, app APIs or schema definitions.

**QA limits**: PHP lint, three local replacement regression assertions and ZIP integrity passed. The actual live WPCode snippet after admin resave is not available to locally execute under the current free-plan cap. Do **not** call this a fully verified production release or assume the WordPress activation succeeds. Do not install until current snippet source and owner are confirmed to match; this is an isolated candidate, not a final install recommendation.

Rollback: separate rollback.php must be packaged with its plugin folder, and activates only when current WPCode #3343 post_content matches the SHA-256 of the migration output; its stored original copy is in WordPress option pottfuchs_p0_titles_rollback. Failure is non-mutating. Do not publish the stored original in public GitHub.

Local ZIP SHA-256 for candidate: 9f959a96e4f6088c1d33cf310292c2a71ae19d7520542ee565c2a3ddcefc4563. GitHub release asset not uploaded yet.
