# Contributing

Thank you for helping improve the IPA Unicode inventory.

Maintainer: [Josh Daugherty](https://github.com/joshdaugherty).

## Tracing changes to authorities (for PRs)

There is no official **`Is_IPA`** API or UCD property. Pull requests should still **tie decisions to traceable sources** so the inventory is not arbitrary:

- **IPA** — chart / handbook alignment when adding or excluding a *linguistic* symbol ([International Phonetic Association](https://www.internationalphoneticassociation.org/)).
- **Unicode** — code point identity, block, and name ([charts](https://www.unicode.org/charts/), [UCD](https://www.unicode.org/ucd/)); cite the scalar and block when the change is encoding-level.
- **SIL** (optional third leg) — practical IPA ↔ Unicode mapping when it clarifies what linguists type ([SIL](https://www.sil.org/)).

**extIPA** and further extensions are **policy-defined**: the default dataset (`ipa-extipa-corpus-inclusive`) includes extIPA Unicode scalars plus transcription/corpus delimiters; anything beyond `meta.policy_description` still needs an explicit policy bump and maintainer review, because Unicode does not expose an `Is_IPA` / `Is_extIPA` property.

For the full discussion (secondary references, community charts, and policy-defined scope), see **README.md → “Sources, authorities, and why this repo is still ‘policy-defined’”.**

In your PR description, a short bullet such as “U+XXXX — Unicode IPA Extensions; IPA chart cell …” (or equivalent) is enough when the mapping is straightforward.

## Proposing code points

1. **Policy first.** Read `data/inventory.json` → `meta.policy_description` and confirm your code point fits the stated policy, or open a discussion to extend policy (which may require a `schema_version` or `dataset_version` bump per `schema/` and `CHANGELOG.md`).
2. **Edit the canonical files.** Prefer **`python scripts/gen-inventory.py`** so `data/inventory.json` and `data/inventory.phonetic-strict.json` stay consistent; or edit `data/inventory.json` by hand and regenerate the strict profile with the same tool if range logic changes (not generated files under `build/`).
3. **Sort order.** Keep `code_points` sorted by numeric `cp` for stable diffs.
4. **Fields.** Each entry needs `cp` (decimal), `cp_hex` (`U+` + uppercase hex, minimum four digits), and `category` from the normative enum in `schema/inventory.code_points.schema.json`. Optional: `aliases`, `notes`, `deprecated`.
5. **Run checks locally:**
   - `npm run validate` — JSON Schema + normalization target checks
   - `npm test` — full pipeline including build (refreshes `src/MetaConstants.php` from `meta`), `MetaConstants` sync check, reference validator fixtures, and manifest digests
   - `composer install` then `composer test` — PHPUnit (golden strings, strict JSON Schema when `justinrainbow/json-schema` is present in dev)

If you change `dataset_version`, `policy_id`, or `schema_version` in `data/inventory.json` → `meta`, run **`npm run build`** (or **`npm test`**) and commit the updated **`src/MetaConstants.php`**.

If you **move** bundled JSON or the schema directory, update **`composer.json` → `extra.ipa-unicode-inventory.paths`** so tooling that reads `extra` stays correct.

## Normalization rules

- Edit `data/normalization.json`.
- Keep `meta.policy_id`, `policy_title`, `dataset_version`, `schema_version`, and `unicode_version_min` **identical** to `data/inventory.json` → `meta` (enforced by `scripts/validate-schemas.mjs`).
- Every Unicode scalar in each rule’s `to` string **must** appear in `data/inventory.json` (same script).
- Prefer single-character `from` values unless you document longest-match behavior.
- Use stable `id` slugs (lowercase, hyphen-separated).

## Reference comparisons

After changing the inventory, optionally run `npm run compare:mediawiki` and `npm run compare:ipa-chart` (network) to see gaps vs [mediawiki-libs-IPAValidator](https://github.com/wikimedia/mediawiki-libs-IPAValidator) and [westonruter/ipa-chart](https://github.com/westonruter/ipa-chart).

## Composer / Packagist

- Run `composer validate` after editing `composer.json`.
- The package name is **`joshdaugherty/ipa-unicode-inventory`**. Public PHP API lives under `src/` (PSR-4: `JoshDaugherty\IpaUnicodeInventory\`).
- New **Git tags** (e.g. `v1.4.0`) drive new versions on [Packagist](https://packagist.org/) once the repo is connected; enable the GitHub webhook so Packagist updates automatically.

## Schemas and breaking changes

- Structural or semantic breaking changes require a **major** `schema_version` bump and an entry in `CHANGELOG.md`.
- Adding new `category` values requires a **minor** `schema_version` bump and schema updates.

## Python inventory generator

To regenerate the default range-based inventory after changing `scripts/gen-inventory.py`:

```bash
python scripts/gen-inventory.py
```

Then review the diff, adjust categories or policy text as needed, and run `npm test`.
