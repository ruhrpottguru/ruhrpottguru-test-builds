# Android v0.19.0 source overlay

Apply after android-v0.18.1 and its original bases. Local build only; no website changes.

Events: enter normal overview with Heute selected, reusing the existing Berlin date preset; Heute is first. Custom date selections remain available. No changes to event membership, date predicate, ranking, special modes or reminder logic.
Food: enforce finite normalized distance <= selected radius before existing distance sort/query/pagination; no wider fallback. One Gastronomie dropdown combines source-evidenced amenity types (including Restaurant) with existing cuisine/diet facets. Uses existing normalization, tags and text predicate; no reclassification of backend records. Other categories retain their predicates and radius rules.
Remove Thema dropdown in Events, Food, Highlights and Kultur. Detail tags and their existing handlers remain available. Activities filters unchanged.

Live read-only check 2026-10-21: places/home Wesel 5 km returned 159 Food records; all 159 are actually <=5 km (max 4.9955 km). The count is therefore plausible and not itself a radius defect. Existing app adapter had no local radius gate; regression using the 260-record 10 km fixture now yields 159 at 5 km. Two tested Activities Basketballplatz media requests returned not-found with no reliable real/contextual image. No fake images or category removal. No photo display code changed: recommended 4:3 previews and fit-full-image details await product decision. Remote photographic coverage cannot be fixed by a client-only build.

Protected scope: GPS, search outside the Food facet, category admission/ranking, other category radii/fallbacks, quiz, favorites, reminders, sharing, privacy and website remain byte-identical. See manifest and release-checks for exact diff/testing. Tests are JS/JVM with live read-only API samples, not physical Android UI/notification delivery.

Rollback retaining saved data: restore v0.18.1 overlay and build with a higher versionCode and the same private test key. Do not uninstall. No signing key is published in this public archive.
