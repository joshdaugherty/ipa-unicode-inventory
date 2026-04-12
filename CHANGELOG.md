# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
for `dataset_version` and `schema_version` as described in `schema/` and inventory `meta`.

## [Unreleased]

## [1.2.0] - 2026-04-13

### Added

- **PHP (Composer):** `Inventory` (cached allowlist via `fromDisk()` / constructor map, `isScalarAllowed(int $cp)` with surrogate and out-of-range rejection); `TranscriptionValidator` (delimiter stripping: none, inventory `delimiter` rows, or custom code points; `normalization.json` longest-`from` first; optional Wikimedia ASCII `'`→ˈ, `:`→ː, `,`→ˌ; `isValid()` per scalar); `InventoryLoader::delimiterScalarSet()`.
- Require **`ext-mbstring`** for UTF-8 scalar iteration in PHP.
- **PHPUnit** (`composer test`), `phpunit.xml.dist`, golden-string tests under `tests/` (ʧ, combining marks, delimiters, normalization, Wikimedia ASCII, invalid UTF-8). GitHub Actions runs `composer install && composer test` after `npm test`.
- **README:** Unicode **scalar** vs grapheme-cluster validation guarantee; **migrating from Wikimedia IPAValidator** comparison table (normalize, delimiters, `@`, Google mode, pipeline order).

### Changed

- `dataset_version` **1.2.0** (npm package and inventory `meta`; allowlist bytes unchanged; `schema_version` remains **1.0.0**).

## [1.1.0] - 2026-04-12

### Added

- Composer / [Packagist](https://packagist.org/) support: root `composer.json` (`joshdaugherty/ipa-unicode-inventory`), PSR-4 namespace `JoshDaugherty\IpaUnicodeInventory\` with `Resources` and `InventoryLoader`, and `.gitattributes` `export-ignore` rules to slim distribution archives.

### Changed

- `dataset_version` **1.1.0** (npm package and inventory `meta`; `schema_version` remains **1.0.0**).

## [1.0.0] - 2026-04-12

### Added

- Initial `schema_version` **1.0.0** and `dataset_version` **1.0.0** (no public release or git tag yet).
- Canonical `data/inventory.json` under policy `ipa-extipa-corpus-inclusive`: core IPA blocks (IPA Extensions, spacing modifiers U+02B0–02FF, combining marks U+0300–036F, phonetic extensions U+1D00–1DBF) plus extIPA-oriented Unicode (Combining Extended U+1AB0–1AFF, Combining Supplement U+1DC0–1DFF, Latin Extended-G U+1DF00–1DFFF, Modifier Letters Supplement U+10780–107BF), superscripts/subscripts U+2070–209C, circled wildcards U+24B6–24E9, modifier tone letters U+A700–A71F, Latin Extended-D ꞎ/ꞯ, practical Latin/Greek IPA letters and clicks U+01C0–01C3, undertie, double vertical line, labiodental flap, prosodic brackets, ¡, airstream arrows U+2191/U+2193, **global rise/fall** U+2197/U+2198 ([westonruter/ipa-chart](https://github.com/westonruter/ipa-chart) suprasegmentals), combining circle, backslash for reiterated articulation, musical dynamics U+1D18F–1D193, ASCII Latin, **transcription/corpus delimiters** (brackets, slashes, braces, angle quotes, punctuation tier markers, typographic quotes), ASCII digits, and ASCII space.
- Optional `data/normalization.json`: U+2018 and U+2019 → U+02BC (modifier letter apostrophe); intentionally no ASCII stress/length shortcuts (see `meta.policy_description`).
- JSON Schemas (draft 2020-12) under `schema/`.
- Node.js `scripts/build.js` producing `build/output/` artifacts: `inventory.min.json`, `code_points.txt`, `pcre-class-fragment.txt`, `php/AllowedCodePoints.php`, `manifest.json`.
- CI workflow: schema validation, build, reference tests, manifest digest check.
- Maintainer tool `scripts/gen-inventory.py` to regenerate the default inventory from Unicode ranges (Python `unicodedata`).
- Maintainer scripts `scripts/compare-mediawiki-validator.mjs` and `scripts/compare-ipa-chart-westonruter.mjs` (`npm run compare:mediawiki`, `npm run compare:ipa-chart`) to diff against [mediawiki-libs-IPAValidator](https://github.com/wikimedia/mediawiki-libs-IPAValidator) and [westonruter/ipa-chart](https://github.com/westonruter/ipa-chart).

### Removed

- `ipa-unicode-inventory-repository-spec.md`; repository layout and versioning are documented in `README.md`, `CONTRIBUTING.md`, `schema/`, and `data/inventory.json` → `meta`.

[1.2.0]: https://github.com/joshdaugherty/ipa-unicode-inventory/releases/tag/v1.2.0
[1.1.0]: https://github.com/joshdaugherty/ipa-unicode-inventory/releases/tag/v1.1.0
[1.0.0]: https://github.com/joshdaugherty/ipa-unicode-inventory/releases/tag/v1.0.0
