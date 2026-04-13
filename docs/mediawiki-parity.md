# MediaWiki IPAValidator parity

Static comparison of the character class inside [mediawiki-libs-IPAValidator `Validator.php`](https://github.com/wikimedia/mediawiki-libs-IPAValidator/blob/main/src/Validator.php) (`$ipaRegex`) vs this package’s **`data/inventory.json`** (corpus_inclusive). This is **not** full PHP validator behavior (strip/normalize run before match upstream).

**Generated:** `2026-04-12T23:22:36.150Z` (UTC)

## Summary

| Metric | Value |
|--------|-------|
| Inventory `dataset_version` | `1.5.0` |
| Inventory `policy_id` | `ipa-extipa-corpus-inclusive` |
| Upstream class source | Live fetch from `main` on GitHub (raw) |
| MediaWiki `$ipaRegex` class (expanded scalars) | 175 |
| This repo `inventory.json` (scalars) | 933 |
| MW scalars **missing** from our inventory | **0** |
| Our scalars not in MW class (expected superset) | 758 |

## MW scalars missing from our inventory

**None** — full literal parity for the expanded class.

## Notes

- Upstream may strip `/ [ ]` before matching when `strip=true`; `( ) . |` in the class are still literal code points in the pattern.
- Regenerate: `npm run compare:mediawiki:doc` (or `npm run compare:mediawiki -- --write-markdown docs/mediawiki-parity.md`)
