# IPA Unicode Inventory

Standalone, language-agnostic **source data** and **generated artifacts** for Unicode scalars treated as IPA-relevant under an explicit, documented policy. Use this instead of ad hoc regex allowlists inside apps.

- **Canonical data:** `data/inventory.json` (required), optional `data/normalization.json`
- **Schemas:** `schema/` (JSON Schema draft 2020-12)
- **PHP:** Composer package `joshdaugherty/ipa-unicode-inventory` (see Consumer quick start)
- **Build:** Node.js 18+ — `npm ci` then `npm run build` → `build/output/`

Versioning and data shape for `schema_version`, `dataset_version`, and categories are defined in `schema/*.schema.json` and `data/inventory.json` → `meta`.

## Policy (current release)

| Field | Value |
|--------|--------|
| `policy_id` | `ipa-extipa-corpus-inclusive` |
| `dataset_version` | `1.1.0` |
| `schema_version` | `1.0.0` |

`dataset_version` **1.1.0** matches the current npm/Composer release line. **PHP:** install via Composer on [Packagist](https://packagist.org/) as `joshdaugherty/ipa-unicode-inventory` (submit the repo and tag releases such as **`v1.1.0`**).

The inventory covers **core IPA** and **extIPA-oriented Unicode** (as above) plus **in-band transcription and corpus punctuation**: parentheses, square brackets, slashes, braces, angle brackets (ASCII and U+27E8/U+27E9), comma, full stop, pipe, colon, hyphen, equals, plus, underscore, quotes (ASCII and common typographic), guillemets, ellipsis, and similar tier markers, all tagged **`delimiter`** where applicable; **ASCII digits and space** are **`other`** for tone indices, timing labels, and running text. Consumers can **strip `delimiter`** (and optionally space/digits) for phonetic-only checks. It still **does not** assert phonological well-formedness or a Unicode `Is_IPA` property — see the policy paragraph above and [Extensions to the IPA](https://en.wikipedia.org/wiki/Extensions_to_the_International_Phonetic_Alphabet) for the clinical symbol set.

## Consumer quick start

1. **JSON:** Read `data/inventory.json` or the minified `build/output/inventory.min.json` (from a release asset). Build a `Set` of `cp` integers in memory.
2. **PCRE (UTF-8 + `/u`):** Insert `build/output/pcre-class-fragment.txt` inside a character class, e.g. `/^[...fragment...]+$/u` — the fragment uses `\x{H...}` escapes only (no surrounding `[` `]`).
3. **PHP (Composer):** `composer require joshdaugherty/ipa-unicode-inventory`, then use `JoshDaugherty\IpaUnicodeInventory\Resources` for paths to the bundled JSON and `InventoryLoader::loadInventory()` / `InventoryLoader::codePointLookup()` for decoded data. For a **cached scalar allowlist**, use `Inventory::fromDisk()` (optional path) and `isScalarAllowed(int $cp)` — surrogates and out-of-range code points return false. **`TranscriptionValidator::fromDisk()`** runs delimiter stripping (none, inventory `delimiter` rows, or a custom code-point set), optional **`normalization.json`** (longest `from` first), optional **Wikimedia-style ASCII** (`'`→ˈ, `:`→ː, `,`→ˌ), then **`isValid()`** per scalar — requires **`ext-mbstring`**. Delimiter stripping happens *before* legacy ASCII; U+0027 is a delimiter, so use `STRIP_DELIMITERS_NONE` or a custom strip set if you need `'`→ˈ. Submit the Git repo to [Packagist](https://packagist.org/) and tag a release (e.g. **`v1.1.0`**) so the package resolves.
4. **PHP (generated array):** After `npm run build`, include `build/output/php/AllowedCodePoints.php` for a `0xNNN => true` map (generated only; not committed).
5. **Integrity:** Check `build/output/manifest.json` SHA-256 digests after downloading release assets.

### Normalization

If you apply `data/normalization.json`, apply rules **longest-`from` first**, then validate scalars against the inventory. **U+2018** and **U+2019** map to MODIFIER LETTER APOSTROPHE (U+02BC). Both are also listed as in-band **delimiters**, so strings may validate without normalization; use normalization when you want a single preferred glottal apostrophe scalar.

### Validation model: Unicode scalars, not grapheme clusters

**Guarantee:** `Inventory::isScalarAllowed()` and `TranscriptionValidator::isValid()` treat the string as a sequence of **Unicode scalar values** (code points). Each scalar is checked against the allowlist independently.

- **In scope:** Supplementary planes, BMP letters, combining marks (e.g. U+0301) as **separate** scalars after the preceding base character, delimiter code points, etc.
- **Out of scope:** **Grapheme clusters** (“user characters”), tailored locale collation, or NFC/NFD canonical equivalence as a validation rule. The same abstract character can be encoded multiple ways (precomposed vs base+combining); this project does **not** merge them unless you normalize first (e.g. via `normalization.json` or your own step) and then validate scalars.
- **PCRE:** A pattern like `/^[…fragment…]+$/u` is also **per UTF-8 code point** in PHP’s UTF-8 mode, not per extended grapheme cluster.

If you need grapheme-level validation, normalize or segment upstream (e.g. `ext-intl` grapheme functions), then decide how each cluster maps to scalars before calling this API.

### Migrating from Wikimedia IPAValidator

Upstream library: [`mediawiki-libs-IPAValidator`](https://github.com/wikimedia/mediawiki-libs-IPAValidator) ([Packagist `wikimedia/ipa-validator`](https://packagist.org/packages/wikimedia/ipa-validator)). It validates against a single **`$ipaRegex`** (whole string must match after optional strip/normalize). This repository is **policy data + optional PHP helpers**; behavior overlaps but is not identical.

| Topic | Wikimedia `IPAValidator\Validator` | This package |
|--------|--------------------------------------|--------------|
| **Primary check** | `preg_match` on normalized string vs `$ipaRegex` | Scalar allowlist from `data/inventory.json` (or generated PCRE class fragment for whole-string regex) |
| **Normalization** | Optional: ASCII `'`→ˈ, `:`→ː, `,`→ˌ (`$normalize`) | `normalization.json` (e.g. U+2018/U+2019→U+02BC, longest-`from` first) **plus** optional same ASCII map in `TranscriptionValidator` (`wikimediaLegacyAscii`) |
| **Delimiter handling** | Optional `stripRegex` when `$strip` | Optional strip of inventory **`delimiter`** rows, custom code-point set, or none (`TranscriptionValidator`) |
| **Pipeline order** | Strip → normalize (normalize may strip again) | Strip delimiters → `normalization.json` → optional Wikimedia ASCII → scalar checks |
| **Google / TTS mode** | `$google` (extra replacements + diacritic stripping) | **Not implemented** |
| **`@` (U+0040)** | Not in `$ipaRegex` (fails validation if present) | In inventory as **`delimiter`** (allowed in-band unless you strip delimiters) |
| **Ligatures / digraph letters** | Allowed only if in `$ipaRegex` | Allowed if listed (e.g. **ʧ** U+02A7); no special “decompose ligature” step |
| **Parity tooling** | — | `npm run compare:mediawiki` diffs **regex class** vs inventory (not full PHP behavior) |

Start from **`TranscriptionValidator::fromDisk()`** if you want strip + normalize + scalar checks in one place; mirror Wikimedia by enabling **`wikimediaLegacyAscii`** and choosing delimiter stripping to approximate `$strip` / `$normalize` (note: U+0027 is an inventory delimiter, so **`STRIP_DELIMITERS_NONE`** is required if you rely on **`'`→ˈ**).

## Development

```bash
npm ci
npm test        # validate schemas, meta alignment, build, fixture tests, manifest digests
npm run build   # write build/output/ only
npm run compare:mediawiki   # optional; needs network
npm run compare:ipa-chart   # optional; needs network
```

**PHP (Composer package):** `composer install` then `composer test` (PHPUnit golden strings under `tests/`).

### Compare to Wikimedia IPAValidator

To diff this repo’s allowlist against the character class baked into [mediawiki-libs-IPAValidator `Validator.php`](https://github.com/wikimedia/mediawiki-libs-IPAValidator/blob/main/src/Validator.php) (`$ipaRegex`):

```bash
npm run compare:mediawiki
```

The script loads `data/inventory.json`, fetches the upstream PHP file when network is available (otherwise uses an embedded snapshot of the class body), expands regex ranges inside `[...]`, and prints any **MediaWiki scalars missing from our inventory**, plus how many **extra** scalars we allow (this project is usually a **superset**). Use `node scripts/compare-mediawiki-validator.mjs --strict` if you want a non-zero exit code when parity is incomplete (for example in a custom CI check).

That validator also applies `stripRegex` and optional normalization before matching; the comparison is **only** the static allowlist implied by `$ipaRegex`, not full PHP behavior. With corpus delimiters included, the MediaWiki class should report **zero** missing scalars (full literal parity).

### Compare to westonruter/ipa-chart

[westonruter/ipa-chart](https://github.com/westonruter/ipa-chart) is a Unicode IPA chart (and keyboard) in HTML. Code points are declared on pickable symbols as `title="U+XXXX: …"`. To list any chart scalars **missing** from our inventory (and how many of ours are **not** on that chart):

```bash
npm run compare:ipa-chart
```

This fetches `index.html` and `accessiblechart.html` from the default branch. Use `--strict` for a non-zero exit if anything is missing. Our inventory is expected to be a **superset** of the 2005 chart glyphs; gaps usually mean a deliberate policy choice or a chart update worth reviewing.

**Runtime:** Node **18+** for `scripts/build.js`, `scripts/validate-schemas.mjs`, and tests. **Python 3** is optional, for `scripts/gen-inventory.py` when regenerating the default inventory from Unicode ranges.

`build/output/` is gitignored; CI builds on every push/PR. **Releases** should attach at least `inventory.min.json`, `manifest.json`, and `pcre-class-fragment.txt` (see build outputs above).

## Roadmap

High-leverage directions beyond shipping JSON, `InventoryLoader`, and path helpers. Order is indicative; issues and PRs can reprioritize.

### Validation and normalization (PHP)

- **Done (in package).** **`TranscriptionValidator`** — `fromDisk()` / constructor; delimiter modes `STRIP_DELIMITERS_NONE`, `STRIP_DELIMITERS_INVENTORY`, `STRIP_DELIMITERS_CUSTOM`; `normalization.json` longest-`from` first; optional Wikimedia ASCII (`'`→ˈ, `:`→ː, `,`→ˌ); `isValid()` per scalar. Does not implement Google-TTS normalization or Wikimedia `stripRegex`.
- **`isScalarAllowed(int $cp): bool`** on a small **`Inventory`** (or similar) facade over the cached map — clearer than raw `isset($map[$cp])`.
- **Normalization / policy profiles** — e.g. `corpus_inclusive` vs `phonetic_strict` as **separate bundled inventories** (or one `meta` flag plus multiple JSON assets) so consumers do not fork data to drop `@` or tier punctuation.

### Discoverability and contracts

- **PHP constants** generated at build time: `DATASET_VERSION`, `POLICY_ID`, `SCHEMA_VERSION` from `meta` for logging and cache keys without parsing JSON first.
- **`composer.json` `extra`** — e.g. default policy path, so tooling can resolve canonical files without hardcoding vendor paths.

### Quality and trust

- **Done (in repo).** **PHPUnit** — `composer test`; golden strings cover **ʧ**, Latin + combining acute (U+0301), delimiter strip vs preserve, normalization (U+2019), Wikimedia ASCII, invalid UTF-8, and non-inventory scalars.
- **Optional strict load** — validate `inventory.json` against JSON Schema when a flag is set (or in dev), e.g. via `justinrainbow/json-schema` as an **optional** dependency.

### Documentation

- **Done.** **Migrating from Wikimedia IPAValidator** — see **Consumer quick start → Migrating from Wikimedia IPAValidator** (table: `@`, delimiters, normalize, Google mode, pipeline order).
- **Done.** **Scalar guarantee** — see **Validation model: Unicode scalars, not grapheme clusters**.

### Nice-to-have

- Publish **`compare:mediawiki` parity** as a CI artifact or a **per-release table** so downstream projects see drift without running Node.
- **Dist clarity** — if Composer archives omit `build/output/`, document whether **release zips** ship `pcre-class-fragment.txt` for PHP-only consumers who want regex without Node.

### Suggested phasing

1. **Phase A** — Core PHP validation surface and tests.
   - **Done.** **`Inventory`** — **`fromDisk(?string $path)`** loads **`inventory.json`** once per instance; **`__construct(array $allowedScalars)`** accepts a prebuilt map for tests; **`isScalarAllowed(int $cp)`** consults the cached allowlist and returns false for surrogates and out-of-range scalars.
   - **Done.** **`TranscriptionValidator`** — same pipeline as above; see class PHPDoc for delimiter vs Wikimedia order (U+0027).
   - **Done.** **PHPUnit** — `tests/*GoldenStringsTest.php`; run via `composer test`.
   - **Done.** **README** — scalar vs grapheme-cluster section and Wikimedia migration table under Consumer quick start.

2. **Phase B** — Contracts and optional strict loading.
   - **Build-time PHP constants** (`DATASET_VERSION`, `POLICY_ID`, `SCHEMA_VERSION`) generated from `meta`.
   - **`composer.json` `extra`** for default asset paths (tooling-friendly).
   - **Optional strict load**: validate bundled JSON against schema in dev or behind a flag (e.g. optional **`justinrainbow/json-schema`**), documented in README.

3. **Phase C** — Profiles, parity visibility, distribution clarity.
   - **Policy profiles**: e.g. separate **`phonetic_strict`** vs **`corpus_inclusive`** inventories (or one `meta` flag plus multiple JSON files).
   - **`compare:mediawiki`** output as **CI artifact** or **per-release parity table** on GitHub.
   - **Document** what Composer archives and **release zips** include (e.g. whether **`pcre-class-fragment.txt`** ships for PHP-only consumers).

The largest payoff for maintainers and consumers is likely a **first-party PHP validator** with an **optional legacy compatibility layer**, so application code becomes a thin wrapper instead of re-implementing Wikimedia steps locally.

## Sources, authorities, and why this repo is still “policy-defined”

No live service exposes a complete, normative **`Is_IPA`** property the way the [UCD](https://www.unicode.org/ucd/) exposes character properties. What you can cite and trace is a **small set of authorities**, then **this repository applies policy** wherever Unicode is ambiguous or broader than you want.

### Strong primary sources (by role)

1. **[International Phonetic Association (IPA)](https://www.internationalphoneticassociation.org/)** — The official chart and handbook define which *linguistic* symbols count as IPA. That is the right basis for “is this symbol on the chart / in official extensions?” The IPA does not typically ship a maintained, machine-readable Unicode table; you still map chart cells to scalars yourself.

2. **[Unicode Consortium](https://www.unicode.org/charts/)** — [Code charts](https://www.unicode.org/charts/) and the [UCD](https://www.unicode.org/ucd/) give **objective encoding**: assigned code points, names, and block boundaries (e.g. **IPA Extensions** U+0250–U+02AF, **Phonetic Extensions**, and related blocks). Treat that as a **mechanical superset**, not “IPA-only,” because those blocks can include historical or non–chart-specific letters until you filter.

3. **[SIL International](https://www.sil.org/)** — Widely used IPA ↔ Unicode reference material (charts, keyboards, which code point corresponds to which glyph). Strong **practical** alignment with what linguists type; still curated documentation, not an `Is_IPA` API.

Together, **IPA (symbols)** + **Unicode (code points)** + optionally **SIL (mapping practice)** give a **documented basis** for “IPA-relevant.” This inventory remains **policy-defined** in the narrower sense: which blocks, which chart edition, whether extIPA, digraphs, or delimiters are in scope, and how you normalize (e.g. ligature vs decomposed spelling), even when each line traces back to those sources.

### Secondary references (useful, not normative alone)

- Wikipedia’s IPA and Unicode articles — quick cross-checks, not a standards body.
- Community tools such as [westonruter/ipa-chart](https://westonruter.github.io/ipa-chart/) — helpful for UX and spot checks; your **policy** should still point at IPA + Unicode (and SIL if you want a third leg).

### Beyond “textbook” IPA

**extIPA** and similar extensions are defined by clinical / phonetic communities (separate charts and guidelines), not by a single Unicode flag. Treat them as an **additional documented policy layer** on top of core IPA if you need them.

### Attribution (encoding facts)

Scalar identities and names follow the [Unicode Standard](https://www.unicode.org/versions/latest/). This dataset is **not** an official Unicode “IPA property”; it is a versioned, machine-readable allowlist plus optional normalization rules under explicit policy in `data/inventory.json` → `meta`.

## Maintainer

[Josh Daugherty](https://github.com/joshdaugherty)

## License

SPDX: **MIT** — see `LICENSE`.
