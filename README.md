# IPA Unicode Inventory

Standalone, language-agnostic **source data** and **generated artifacts** for Unicode scalars treated as IPA-relevant under an explicit, documented policy. Use this instead of ad hoc regex allowlists inside apps.

- **Canonical data:** `data/inventory.json` (**corpus_inclusive**), `data/inventory.phonetic-strict.json` (**phonetic_strict**), optional `data/normalization.json`
- **Schemas:** `schema/` (JSON Schema draft 2020-12)
- **PHP:** Composer package `joshdaugherty/ipa-unicode-inventory` (see Consumer quick start)
- **Build:** Node.js 18+ — `npm ci` then `npm run build` → `build/output/`

Versioning and data shape for `schema_version`, `dataset_version`, and categories are defined in `schema/*.schema.json` and `data/inventory.json` → `meta`.

## Policy (current release)

| Field | Value |
|--------|--------|
| `policy_id` | `ipa-extipa-corpus-inclusive` (default bundle) |
| `profile_id` | `corpus_inclusive` (in `inventory.json` meta) |
| `dataset_version` | `1.4.0` |
| `schema_version` | `1.0.0` |

`dataset_version` **1.4.0** matches the current npm/Composer release line. **PHP:** install via Composer on [Packagist](https://packagist.org/) as `joshdaugherty/ipa-unicode-inventory` (submit the repo and tag releases such as **`v1.4.0`**).

The inventory covers **core IPA** and **extIPA-oriented Unicode** (as above) plus **in-band transcription and corpus punctuation**: parentheses, square brackets, slashes, braces, angle brackets (ASCII and U+27E8/U+27E9), comma, full stop, pipe, colon, hyphen, equals, plus, underscore, quotes (ASCII and common typographic), guillemets, ellipsis, and similar tier markers, all tagged **`delimiter`** where applicable; **ASCII digits and space** are **`other`** for tone indices, timing labels, and running text. Consumers can **strip `delimiter`** (and optionally space/digits) for phonetic-only checks, or load the bundled **`phonetic_strict`** inventory (below), which omits those rows. It still **does not** assert phonological well-formedness or a Unicode `Is_IPA` property — see the policy paragraph above and [Extensions to the IPA](https://en.wikipedia.org/wiki/Extensions_to_the_International_Phonetic_Alphabet) for the clinical symbol set.

### Policy profiles

| `profile_id` | File | `policy_id` | Role |
|----------------|------|-------------|------|
| `corpus_inclusive` | `data/inventory.json` | `ipa-extipa-corpus-inclusive` | Default: phonetic symbols **plus** delimiter rows and ASCII space/digits for transcriptions and corpora. |
| `phonetic_strict` | `data/inventory.phonetic-strict.json` | `ipa-extipa-phonetic-strict` | Subset: same phonetic Unicode rows **without** `delimiter` category, ASCII space, or ASCII digits (ASCII Latin letters remain for mixed orthography). Normalization targets (e.g. U+02BC) stay valid. |

**`meta.dataset_version`**, **`schema_version`**, and **`unicode_version_min`** match across both profiles and **`normalization.json`**. **`MetaConstants`** reflects the **default** (`corpus_inclusive`) bundle only. PHP: **`Resources::inventoryJsonPathForProfile(PolicyProfile::PHONETIC_STRICT)`** (or **`CORPUS_INCLUSIVE`**) and **`composer.json` → `extra.ipa-unicode-inventory.paths.profiles`**.

## Consumer quick start

1. **JSON:** Read `data/inventory.json` (or **`inventory.phonetic-strict.json`**) or the minified `build/output/inventory.min.json` / **`inventory.phonetic-strict.min.json`**. Build a `Set` of `cp` integers in memory.
2. **PCRE (UTF-8 + `/u`):** Insert `build/output/pcre-class-fragment.txt` or **`pcre-class-fragment.phonetic-strict.txt`** inside a character class, e.g. `/^[...fragment...]+$/u` — the fragment uses `\x{H...}` escapes only (no surrounding `[` `]`).
3. **PHP (Composer):** `composer require joshdaugherty/ipa-unicode-inventory`, then use `JoshDaugherty\IpaUnicodeInventory\Resources` for paths to the bundled JSON and `InventoryLoader::loadInventory()` / `InventoryLoader::codePointLookup()` for decoded data. **Tooling:** `composer.json` → **`extra.ipa-unicode-inventory.paths`** lists **`inventory_json`**, **`normalization_json`**, **`schema_directory`**, and **`profiles`** (`corpus_inclusive`, `phonetic_strict`) relative to the package root. **`MetaConstants`** exposes **`DATASET_VERSION`**, **`POLICY_ID`**, **`PROFILE_ID`**, and **`SCHEMA_VERSION`** from the default `inventory.json` → `meta` (generated into `src/MetaConstants.php` by **`npm run build`**; **`npm test`** checks it stays in sync). For a **cached scalar allowlist**, use `Inventory::fromDisk()` (optional path) and `isScalarAllowed(int $cp)` — surrogates and out-of-range code points return false. **`TranscriptionValidator::fromDisk()`** runs delimiter stripping (**none**, inventory **`delimiter`** rows, **custom** code points, or **`STRIP_DELIMITERS_WIKIMEDIA_SLASH_BRACKETS`** for Wikimedia **`$stripRegex`** — only `/` `[` `]`), optional **`normalization.json`** (longest `from` first), optional **Wikimedia-style ASCII** (`'`→ˈ, `:`→ː, `,`→ˌ), optional **Google/TTS** normalization (parentheses removal, modifier-letter → ASCII map, then strip **U+0300–U+036F**; requires **`wikimediaLegacyAscii`**), then **`isValid()`** per scalar — requires **`ext-mbstring`**. Delimiter stripping runs *before* legacy ASCII; **`'`** is an inventory **`delimiter`**, so **`STRIP_DELIMITERS_INVENTORY`** removes it before **`'`→ˈ** — use **`STRIP_DELIMITERS_NONE`**, **`CUSTOM`**, or **`WIKIMEDIA_SLASH_BRACKETS`** if you need that mapping. For **phonetic-only** validation without corpus delimiters, use **`Resources::inventoryJsonPathForProfile(PolicyProfile::PHONETIC_STRICT)`**. Submit the Git repo to [Packagist](https://packagist.org/) and tag a release (e.g. **`v1.4.0`**) so the package resolves.
4. **PHP (generated array):** After `npm run build`, include `build/output/php/AllowedCodePoints.php` or **`AllowedCodePoints.phonetic-strict.php`** for a `0xNNN => true` map (generated only; not committed).
5. **Integrity:** Check `build/output/manifest.json` SHA-256 digests after downloading release assets.

### Distribution: Composer archives, git clones, and release assets

**Packagist / Composer dist** (what you get from `composer require joshdaugherty/ipa-unicode-inventory`) is a **slim zip** defined by **`composer.json` → `archive.exclude`** and **`.gitattributes` → `export-ignore`**. It **includes** at least:

- **`src/`** — PHP (`Inventory`, `TranscriptionValidator`, `Resources`, `MetaConstants`, etc.)
- **`data/`** — `inventory.json`, `inventory.phonetic-strict.json`, `normalization.json`
- **`schema/`** — JSON Schemas for strict validation
- **`docs/`** — e.g. `mediawiki-parity.md`
- Root **`composer.json`**, **`README.md`**, **`LICENSE`**, **`CONTRIBUTING.md`**, **`CHANGELOG.md`**, **`phpunit.xml.dist`** (present in the archive even though tests are omitted)

It **omits** **`tests/`**, **`scripts/`**, **`package.json`**, **`package-lock.json`**, **`.github/`**, **`.gitignore`**, and **`node_modules/`** (not committed). **`build/output/`** is **not** in the git tag at all (`build/` is gitignored), so **`pcre-class-fragment.txt`**, **`inventory.min.json`**, **`manifest.json`**, and generated **`AllowedCodePoints*.php`** under **`build/output/`** do **not** ship with Composer. **PHP-only consumers** who want the PCRE fragment or minified JSON should **build locally** (`npm ci && npm run build`) or **download release assets** (below).

**GitHub “Source code”** archives (zip/tarball on a tag) are the **full repository tree** at that revision: same as a `git clone` without unpublished files. They still **exclude** generated **`build/output/`** unless you commit it (this project does not).

**GitHub Releases — attached binaries:** This repository’s **maintainer checklist** is to attach **`npm run build`** outputs for consumers who do not use Node, for example **`inventory.min.json`**, **`inventory.phonetic-strict.min.json`**, **`manifest.json`**, **`pcre-class-fragment.txt`**, **`pcre-class-fragment.phonetic-strict.txt`**, **`code_points.txt`**, **`code_points.phonetic-strict.txt`**, and optionally **`php/AllowedCodePoints.php`** / **`php/AllowedCodePoints.phonetic-strict.php`**. Published releases also receive **`mediawiki-parity.md`** (and **`.log`**) from **`.github/workflows/release-parity.yml`** automatically.

### Normalization

If you apply `data/normalization.json`, apply rules **longest-`from` first**, then validate scalars against the inventory. **U+2018** and **U+2019** map to MODIFIER LETTER APOSTROPHE (U+02BC). Both are also listed as in-band **delimiters**, so strings may validate without normalization; use normalization when you want a single preferred glottal apostrophe scalar.

### Optional strict JSON Schema validation (PHP)

By default, **`InventoryLoader`** only checks that **`meta`** and **`code_points`** / **`rules`** exist. To validate the full document against the bundled **draft 2020-12** wrappers under **`schema/`**:

1. Install the optional dependency: **`composer require justinrainbow/json-schema`** (see **`suggest`** in this package’s `composer.json`). The repo’s **`require-dev`** includes it so **`composer test`** can cover strict mode.
2. Pass **`true`** as the second argument: **`InventoryLoader::loadInventory($path, true)`**, **`loadNormalization($path, true)`**, **`codePointLookup($path, true)`**, **`delimiterScalarSet($path, true)`**; **`Inventory::fromDisk($path, true)`**; **`TranscriptionValidator::fromDisk(..., $googleTtsNormalization: false, $validateSchema: true)`** (last parameter is **`$validateSchema`**).
3. Or decode JSON yourself and call **`BundleSchemaValidator::assertInventoryDocumentValid($data)`** / **`assertNormalizationDocumentValid($data)`**. Use **`BundleSchemaValidator::isAvailable()`** if you need to branch before requiring the package.

If strict mode is requested but **`justinrainbow/json-schema`** is not installed, a **`RuntimeException`** explains how to add it. Validation failures throw **`RuntimeException`** with schema error details.

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
| **Delimiter handling** | Optional `stripRegex` when `$strip` | Inventory **`delimiter`** rows, custom code points, none, or **`STRIP_DELIMITERS_WIKIMEDIA_SLASH_BRACKETS`** (only `/` `[` `]` — Wikimedia **`$stripRegex`**) |
| **Pipeline order** | Strip → normalize (normalize may strip again) | Strip delimiters → `normalization.json` → optional Wikimedia ASCII → optional Google/TTS → scalar checks |
| **Google / TTS mode** | `$google` (extra replacements + diacritic stripping) | **`TranscriptionValidator::fromDisk(..., $wikimediaLegacyAscii: true, $googleTtsNormalization: true)`** — same char map + **U+0300–U+036F** removal as upstream; **requires** legacy ASCII enabled |
| **`@` (U+0040)** | Not in `$ipaRegex` (fails validation if present) | In inventory as **`delimiter`** (allowed in-band unless you strip delimiters) |
| **Ligatures / digraph letters** | Allowed only if in `$ipaRegex` | Allowed if listed (e.g. **ʧ** U+02A7); no special “decompose ligature” step |
| **Parity tooling** | — | `npm run compare:mediawiki` diffs **regex class** vs inventory (not full PHP behavior) |

Start from **`TranscriptionValidator::fromDisk()`** if you want strip + normalize + scalar checks in one place. For Wikimedia **`$strip`** parity on **`/` `[` `]`** only, use **`STRIP_DELIMITERS_WIKIMEDIA_SLASH_BRACKETS`** (equivalent to **`preg_replace('/[\/\[\]]/u', '', $s)`** on well-formed UTF-8); that keeps ASCII **`'`** so you can enable **`wikimediaLegacyAscii`** for **`'`→ˈ** without **`STRIP_DELIMITERS_NONE`**. **`STRIP_DELIMITERS_INVENTORY`** removes every inventory **`delimiter`**, including **`'`**, so it does **not** match upstream **`$stripRegex`**. For **`$google`**, pass **`googleTtsNormalization: true`** after **`wikimediaLegacyAscii: true`**; Google **strips combining marks in U+0300–U+036F**, so validate policy implications for narrow IPA.

## Development

```bash
npm ci
npm test        # validate schemas, meta alignment, build, fixture tests, manifest digests
npm run build   # write build/output/ and src/MetaConstants.php from inventory meta
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

**Markdown report (checked in):** [`docs/mediawiki-parity.md`](docs/mediawiki-parity.md) — regenerate with `npm run compare:mediawiki:doc` (or `--write-markdown <path>`). **CI** uploads `mediawiki-parity.md` and a console **`.log`** as workflow artifacts (`mediawiki-parity`). **Published releases** attach the same files via `.github/workflows/release-parity.yml`.

That validator also applies `stripRegex` and optional normalization before matching; the comparison is **only** the static allowlist implied by `$ipaRegex`, not full PHP behavior. With corpus delimiters included, the MediaWiki class should report **zero** missing scalars (full literal parity).

### Compare to westonruter/ipa-chart

[westonruter/ipa-chart](https://github.com/westonruter/ipa-chart) is a Unicode IPA chart (and keyboard) in HTML. Code points are declared on pickable symbols as `title="U+XXXX: …"`. To list any chart scalars **missing** from our inventory (and how many of ours are **not** on that chart):

```bash
npm run compare:ipa-chart
```

This fetches `index.html` and `accessiblechart.html` from the default branch. Use `--strict` for a non-zero exit if anything is missing. Our inventory is expected to be a **superset** of the 2005 chart glyphs; gaps usually mean a deliberate policy choice or a chart update worth reviewing.

**Runtime:** Node **18+** for `scripts/build.js`, `scripts/validate-schemas.mjs`, and tests. **Python 3** is optional, for `scripts/gen-inventory.py` when regenerating the default inventory from Unicode ranges.

`build/output/` is gitignored; CI builds on every push/PR. See **Consumer quick start → Distribution** for what Composer ships vs what to attach to **GitHub Releases**.

## Roadmap

### Feature request: Wikimedia `stripRegex`, Google/TTS mode, grapheme-cluster validation

**Package:** [joshdaugherty/ipa-unicode-inventory](https://github.com/joshdaugherty/ipa-unicode-inventory)  
**Context:** **`TranscriptionValidator`** implements **Wikimedia `stripRegex` parity** (see §1) and **Google/TTS** (§2). **Grapheme-cluster** segmentation remains **not** implemented. Optional EGC mode would further align with UI / `ext-intl` copy-paste behavior.

---

### 1. Wikimedia `stripRegex` parity

**Status:** **Implemented** — **`STRIP_DELIMITERS_WIKIMEDIA_SLASH_BRACKETS`** and **`wikimediaSlashBracketStripScalarSet()`**.

#### Problem

[Wikimedia `Validator`](https://github.com/wikimedia/mediawiki-libs-IPAValidator/blob/main/src/Validator.php) removes **`/` `[` `]`** via `$stripRegex` before matching `$ipaRegex`. Consumers that want **byte-for-byte** parity with that strip step need that exact set, independent of inventory `category === delimiter`.

#### Shipped behavior

- Fourth strip mode removes **only** U+002F, U+005B, U+005D **before** normalization (same order as Wikimedia **`validate()`** when `$strip` is true).
- Documented equivalence to **`preg_replace('/[\/\[\]]/u', '', $s)`** on well-formed UTF-8; **`'`** is **not** removed (supports **`wikimediaLegacyAscii`** **`'`→ˈ** without **`STRIP_DELIMITERS_NONE`**).

#### Acceptance criteria

- [x] Public API constant equivalent to Wikimedia **`$stripRegex`**.
- [x] PHPUnit: **`"/[ˈtɛst]/"`** validates with the preset; scalar-set strip matches **`preg_replace`** on sample strings.
- [x] README **Migrating from Wikimedia** + Consumer quick start.

---

### 2. Google/TTS normalization mode

**Status:** **Implemented** in `TranscriptionValidator` (`googleTtsNormalization` / `applyGoogleTtsNormalization()`). Remaining gaps vs Wikimedia are whole-string **`$ipaRegex`** matching and optional **grapheme** iteration (see migration table).

#### Problem

Wikimedia’s validator optionally applies a **Google TTS–oriented** branch after base normalization: remove parens, map superscript/modifier letters to ASCII-friendly letters, then strip combining marks in **U+0300–U+036F** (see `Validator::normalizeIPA` with `$google`).

#### Shipped behavior

- **`TranscriptionValidator::fromDisk(..., $wikimediaLegacyAscii, $googleTtsNormalization, $validateSchema)`** — Google **requires** legacy ASCII (`InvalidArgumentException` otherwise).
- Pipeline: delimiter strip → `normalization.json` → Wikimedia ASCII → Google char map → **`/[\x{0300}-\x{036F}]/u`** removal → per-scalar allowlist.

#### Acceptance criteria

- [x] Flag(s) and invalid combinations throw or are rejected with clear `InvalidArgumentException` messages.
- [x] PHPUnit golden strings covering at least: superscript ⁿ → `n`, combining-mark behavior under Google mode, and allowlist failure cases.
- [x] README: Google mode vs dataset policy; combining strip behavior documented under **Migrating from Wikimedia IPAValidator**.

---

### 3. Grapheme-cluster (extended grapheme cluster) segmentation

#### Problem

The package correctly documents that validation is per **Unicode scalar** (code point). Some consumers want **user-perceived character** alignment (e.g. base + combining sequence treated as one unit for policy, or alignment with `intl` / CLDR / search UX).

Scalars and **extended grapheme clusters** (EGC) diverge whenever:

- Multiple combining marks attach to one base.
- Regional indicators, emoji ZWJ sequences, etc. (less common in IPA, but policy may still want EGC for consistency with UI copy/paste).

#### Proposed behavior

- Add an optional mode, e.g. **`$segmentation = self::SEGMENT_SCALARS | self::SEGMENT_GRAPHEME_CLUSTER`**, default **`SCALARS`** for backward compatibility.
- When **`GRAPHEME_CLUSTER`**:
  - Require **`ext-intl`** (or polyfill strategy documented and rejected if missing).
  - Iterate with `IntlBreakIterator` (or equivalent) for **grapheme** breaks.
  - Define validation rule precisely in README:
    - **Option A (strict):** every code point within each cluster must be allowed (equivalent to scalars for pure IPA, but failure modes may differ for malformed sequences).
    - **Option B (cluster-as-token):** each cluster must be “well-formed” under a documented table (harder; may be out of scope for v1).

Recommendation: ship **Option A** first: same allowlist as today, but **iteration unit** is EGC; document that the allowlist is still per scalar and the iterator only changes how the string is walked (useful for future cluster-level policies).

#### Acceptance criteria

- [ ] Default behavior unchanged (scalar iteration).
- [ ] With grapheme mode + `ext-intl`, PHPUnit covers: precomposed vs decomposed NFC/NFD for the same linguistic string (both should pass if all scalars allowed).
- [ ] README: explicit guarantee whether IPA combining marks are validated per scalar inside each cluster; dependency on `ext-intl`.

---

### Cross-cutting notes

- **Versioning:** These are additive if defaults preserve current behavior; bump **minor** `dataset_version` only if bundled JSON changes; **package semver** per your policy for new API surface.
- **Downstream:** Consumers (e.g. Laravel validation rules) can rely on packaged Wikimedia **`$stripRegex`** preset and Google/TTS; optional **grapheme** mode (3) remains future work.

---

### Environment

- **PHP:** 8.1+ (package requirement); grapheme mode needs **`ext-intl`** when enabled.
- **Reference:** Wikimedia [Validator.php](https://github.com/wikimedia/mediawiki-libs-IPAValidator/blob/main/src/Validator.php) (`$stripRegex`, `normalizeIPA`, `$google`, `$diacriticsRegex`).

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
