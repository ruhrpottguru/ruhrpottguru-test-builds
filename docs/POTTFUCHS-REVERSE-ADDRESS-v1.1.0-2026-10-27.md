# PottFuchs reverse address v1.1.0 — 27.10.2026

## Component
New independent WordPress plugin at `wordpress/plugins/pottfuchs-address-display/`, built from the existing `rpg_geoapify_api_key` and REST conventions. Existing backend source Places v1.10.190 is not changed; no WordPress production installation performed by the developer.

## Functional contract
Enrich existing `ruhrpottguru/v1` REST items only where a reliable non-city-scope coordinate exists and no full trusted venue address is present. Preserve item position/visibility/order/image/radius and current direction links. Add flat `address` plus structured `location.address` so Web/Android `eventAddress(item)` read the result through their existing parser. For unknown address or low-confidence reverse result, do not invent a house number. Existing Theme Geoapify option is the default credential. Opt-in via Settings, daily budget and transient cache.

## Artifact from independent local PHP/ZIP build
`PottFuchs-Koordinaten-zu-Adresse-v1.1.0.zip`
SHA256 `2a267abcae741e8898b93cd0351da08dfa1b439b0d6d56d40d5f973ea53bfd7f`.
Contains only `pottfuchs-address-display/pottfuchs-address-display.php` and `README.txt`; source Git blob `1dd33769cae12b3b3de39db1a3c9a0cc2bf93748`, README blob `e74ebc9a151135252aa635e0c693babb9ffb9749`.

## Tests and release boundary
Local PHP 8.4.24 syntax check passed; 10 isolated mock assertions passed, including new address, existing address, city scope, response filtering, caching, no-call while disabled, existing key usage; ZIP integrity passed. The archived test code lives in `tests/pottfuchs-address-display.test.php`. A GitHub workflow is configured to repeat tests and publish ZIP plus SHA256SUMS, but workflow completion and GitHub release asset availability must be verified separately; do not claim them completed without evidence.

Not performed: full WordPress MariaDB/browser E2E, live Geoapify provider request, Android hardware test, operator activation, public live confirmation. Backend 1.10.190 and Android beta.15 source contracts inspected. Rollback: disable the plugin; no data storage/schema migration.
