# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
for `dataset_version` and `schema_version` as described in `schema/` and inventory `meta`.

## [Unreleased]

### Added

- **MediaWiki parity visibility:** `scripts/compare-mediawiki-validator.mjs` **`--write-markdown <path>`**; committed **`docs/mediawiki-parity.md`**; **`npm run compare:mediawiki:doc`**; CI uploads **`mediawiki-parity`** artifact (`.md` + `.log`); **`.github/workflows/release-parity.yml`** attaches the same files to **published** GitHub Releases.

### Changed

- **Documentation:** **README** — **Distribution** subsection (Composer dist vs GitHub source vs release assets; **`pcre-class-fragment.txt`** and **`build/output/`** not shipped via Composer). **CONTRIBUTING** — dist zip notes and **GitHub Releases** artifact checklist.

## [1.4.0] - 2026-04-12

### Added

- **Policy profiles:** `data/inventory.phonetic-strict.json` (**`phonetic_strict`**, `policy_id` **`ipa-extipa-phonetic-strict`**) — phonetic subset without `delimiter` rows, ASCII space, or ASCII digits; `meta.profile_id` on both inventories (`corpus_inclusive` | `phonetic_strict`) in `schema/inventory.meta.schema.json`.
- **PHP:** `PolicyProfile` and `Resources::inventoryJsonPathForProfile()`; `composer.json` → **`extra.ipa-unicode-inventory.paths.profiles`**.
- **Build:** `inventory.phonetic-strict.min.json`, `code_points.phonetic-strict.txt`, `pcre-class-fragment.phonetic-strict.txt`, `php/AllowedCodePoints.phonetic-strict.php` and manifest hashes for all eight artifacts.
- **`MetaConstants::PROFILE_ID`** (default bundle).
- **Maintainer:** `scripts/gen-inventory.py` writes both inventory files; `scripts/validate-schemas.mjs` validates the strict file and checks normalization rule targets against it.

### Changed

- `dataset_version` **1.4.0** (npm package, both inventories, `normalization.json`; allowlist rows unchanged aside from the new strict file; `schema_version` **1.0.0**).

## [1.3.0] - 2026-04-14

### Added

- **Build-time PHP constants:** `MetaConstants` in `src/MetaConstants.php` generated from `inventory.json` → `meta` via `scripts/meta-constants-php.mjs` (`npm run build`); `scripts/verify-meta-constants.mjs` in `npm test` keeps the file in sync.
- **`composer.json` `extra.ipa-unicode-inventory.paths`** — `inventory_json`, `normalization_json`, `schema_directory` (paths relative to package root).
- **Optional strict JSON Schema (PHP):** `BundleSchemaValidator` and `validateSchema` on `InventoryLoader`, `Inventory::fromDisk`, `TranscriptionValidator::fromDisk`; optional **`justinrainbow/json-schema`** (`require-dev` + `suggest`).
- **PHPUnit:** `BundleSchemaValidatorTest`, `InventoryLoaderStrictSchemaTest`; expanded PHPDoc on PHP public API and tests.

### Changed

- `dataset_version` **1.3.0** (npm package and inventory `meta`; allowlist bytes unchanged; `schema_version` remains **1.0.0**).
- Compare scripts User-Agent **`ipa-unicode-inventory-compare/1.3`**.

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

[1.4.0]: https://github.com/joshdaugherty/ipa-unicode-inventory/releases/tag/v1.4.0
[1.3.0]: https://github.com/joshdaugherty/ipa-unicode-inventory/releases/tag/v1.3.0
[1.2.0]: https://github.com/joshdaugherty/ipa-unicode-inventory/releases/tag/v1.2.0
[1.1.0]: https://github.com/joshdaugherty/ipa-unicode-inventory/releases/tag/v1.1.0
[1.0.0]: https://github.com/joshdaugherty/ipa-unicode-inventory/releases/tag/v1.0.0
