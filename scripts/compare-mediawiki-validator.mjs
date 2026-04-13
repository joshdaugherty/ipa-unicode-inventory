#!/usr/bin/env node
/**
 * Compare Wikimedia mediawiki-libs-IPAValidator $ipaRegex character class to data/inventory.json.
 *
 * Upstream: https://github.com/wikimedia/mediawiki-libs-IPAValidator/blob/main/src/Validator.php
 *
 * Usage:
 *   node scripts/compare-mediawiki-validator.mjs
 *   node scripts/compare-mediawiki-validator.mjs --strict   # exit 1 if any MW scalar is missing from inventory
 *   node scripts/compare-mediawiki-validator.mjs --write-markdown path/to/report.md
 *
 * With network, the script fetches Validator.php and parses the class body. If fetch or parse fails,
 * it falls back to an embedded snapshot (update FALLBACK_CLASS_BODY when upstream changes).
 */
import { readFileSync, writeFileSync, mkdirSync } from "node:fs";
import { join, dirname } from "node:path";
import { fileURLToPath } from "node:url";

const UPSTREAM_RAW =
  "https://raw.githubusercontent.com/wikimedia/mediawiki-libs-IPAValidator/main/src/Validator.php";

/** Snapshot if raw fetch fails (body only, between [ and ] in /^[...]+$/ui). */
const FALLBACK_CLASS_BODY =
  "().a-z|æçðøħŋœǀ-ǃɐ-ɻɽɾʀ-ʄʈ-ʒʔʕʘʙʛ-ʝʟʡʢʰʲʷʼˀˈˌːˑ˞ˠˡˤ-˩̴̘̙̜̝̞̟̠̤̥̩̪̬̯̰̹̺̻̼̀́̂̃̄̆̈̊̋̌̏̽̚͜͡βθχ᷄᷅᷈‖‿ⁿⱱ";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");
const strict = process.argv.includes("--strict");

function parseWriteMarkdownPath() {
  const i = process.argv.indexOf("--write-markdown");
  if (i === -1 || i + 1 >= process.argv.length) return null;
  return process.argv[i + 1];
}

/**
 * @param {object} opts
 * @param {string} opts.datasetVersion
 * @param {string} opts.policyId
 * @param {string} opts.sourceNote
 * @param {number} opts.mwSize
 * @param {number} opts.oursSize
 * @param {number[]} opts.missingSorted
 * @param {number} opts.extraInOurs
 */
function renderMarkdownReport(opts) {
  const rows = [
    "| Metric | Value |",
    "|--------|-------|",
    "| Inventory `dataset_version` | `" + opts.datasetVersion + "` |",
    "| Inventory `policy_id` | `" + opts.policyId + "` |",
    "| Upstream class source | " + opts.sourceNote + " |",
    "| MediaWiki `$ipaRegex` class (expanded scalars) | " + opts.mwSize + " |",
    "| This repo `inventory.json` (scalars) | " + opts.oursSize + " |",
    "| MW scalars **missing** from our inventory | **" + opts.missingSorted.length + "** |",
    "| Our scalars not in MW class (expected superset) | " + opts.extraInOurs + " |",
  ];
  const genAt = new Date().toISOString();
  let body =
    "# MediaWiki IPAValidator parity\n\n" +
    "Static comparison of the character class inside [mediawiki-libs-IPAValidator `Validator.php`](https://github.com/wikimedia/mediawiki-libs-IPAValidator/blob/main/src/Validator.php) (`$ipaRegex`) vs this package’s **`data/inventory.json`** (corpus_inclusive). " +
    "This is **not** full PHP validator behavior (strip/normalize run before match upstream).\n\n" +
    "**Generated:** `" +
    genAt +
    "` (UTC)\n\n" +
    "## Summary\n\n" +
    rows.join("\n") +
    "\n\n";

  if (opts.missingSorted.length === 0) {
    body += "## MW scalars missing from our inventory\n\n**None** — full literal parity for the expanded class.\n\n";
  } else {
    body +=
      "## MW scalars missing from our inventory\n\n" +
      "Add to policy / `inventory.json` if you want these code points allowed.\n\n" +
      "| Code point | Character |\n" +
      "|------------|----------|\n";
    for (const cp of opts.missingSorted) {
      const ch = String.fromCodePoint(cp);
      body +=
        "| U+" +
        cp.toString(16).toUpperCase().padStart(4, "0") +
        " | " +
        JSON.stringify(ch) +
        " |\n";
    }
    body += "\n";
  }

  body +=
    "## Notes\n\n" +
    "- Upstream may strip `/ [ ]` before matching when `strip=true`; `( ) . |` in the class are still literal code points in the pattern.\n" +
    "- Regenerate: `npm run compare:mediawiki:doc` (or `npm run compare:mediawiki -- --write-markdown docs/mediawiki-parity.md`)\n";

  return body;
}

function extractClassBodyFromPhp(text) {
  const m = text.match(
    /\$ipaRegex\s*=\s*<<<EOD\s*\r?\n\/\^\[([\s\S]*?)\]\+\$\/ui\s*\r?\nEOD\s*;/m
  );
  return m ? m[1].trim() : null;
}

/**
 * @returns {Promise<{ body: string, sourceNote: string }>}
 */
async function loadClassBody() {
  try {
    const res = await fetch(UPSTREAM_RAW, {
      headers: { "user-agent": "ipa-unicode-inventory-compare/1.6" },
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const text = await res.text();
    const body = extractClassBodyFromPhp(text);
    if (body) {
      console.log("Using character class from upstream (fetched).\n");
      return { body, sourceNote: "Live fetch from `main` on GitHub (raw)" };
    }
    throw new Error("Could not parse $ipaRegex heredoc");
  } catch (e) {
    console.warn(`Upstream fetch/parse failed (${e.message}); using embedded FALLBACK_CLASS_BODY.\n`);
    return { body: FALLBACK_CLASS_BODY, sourceNote: "Embedded `FALLBACK_CLASS_BODY` in `scripts/compare-mediawiki-validator.mjs`" };
  }
}

function addRange(a, b, set) {
  const ca = a.codePointAt(0);
  const cb = b.codePointAt(0);
  for (let cp = ca; cp <= cb; cp++) set.add(cp);
}

/** Expand a regex character-class body to a Set of scalar values (PCRE-style ranges). */
function classBodyToCodePointSet(cls) {
  const need = new Set();
  let i = 0;
  while (i < cls.length) {
    const cp = cls.codePointAt(i);
    const adv = cp > 0xffff ? 2 : 1;
    if (i + adv < cls.length && cls[i + adv] === "-") {
      const cp2 = cls.codePointAt(i + adv + 1);
      if (cp2 !== undefined) {
        const adv2 = cp2 > 0xffff ? 2 : 1;
        addRange(String.fromCodePoint(cp), String.fromCodePoint(cp2), need);
        i += adv + 1 + adv2;
        continue;
      }
    }
    need.add(cp);
    i += adv;
  }
  return need;
}

const inv = JSON.parse(readFileSync(join(root, "data", "inventory.json"), "utf8"));
const allowed = new Set(inv.code_points.map((r) => r.cp));

const { body: cls, sourceNote } = await loadClassBody();
const mw = classBodyToCodePointSet(cls);

const missing = [...mw].filter((cp) => !allowed.has(cp)).sort((a, b) => a - b);
const extraInOurs = [...allowed].filter((cp) => !mw.has(cp)).length;

console.log("MediaWiki IPAValidator — expanded scalars in $ipaRegex class:", mw.size);
console.log("ipa-unicode-inventory — scalars in data/inventory.json:", allowed.size);
console.log("MW scalars missing from our inventory:", missing.length);
console.log("Our scalars not present in MW class (superset):", extraInOurs);
console.log("");

if (missing.length) {
  console.log("Missing (add to policy/inventory if you want parity with that regex):");
  for (const cp of missing) {
    console.log(
      "  U+" + cp.toString(16).toUpperCase().padStart(4, "0"),
      JSON.stringify(String.fromCodePoint(cp))
    );
  }
  console.log("");
  console.log(
    "Note: Validator strips / [ ] before match when strip=true (default); ( ) . | in the class are still literal allowed code points in the pattern."
  );
} else {
  console.log("Full parity: every scalar in the MW class is in our inventory.");
}

const mdPath = parseWriteMarkdownPath();
if (mdPath) {
  const abs = mdPath.startsWith("/") || /^[A-Za-z]:[\\/]/.test(mdPath) ? mdPath : join(root, mdPath);
  const dir = dirname(abs);
  try {
    mkdirSync(dir, { recursive: true });
  } catch {
    /* exists */
  }
  const md = renderMarkdownReport({
    datasetVersion: inv.meta?.dataset_version ?? "?",
    policyId: inv.meta?.policy_id ?? "?",
    sourceNote,
    mwSize: mw.size,
    oursSize: allowed.size,
    missingSorted: missing,
    extraInOurs,
  });
  writeFileSync(abs, md, "utf8");
  console.log(`Wrote markdown report: ${abs}`);
}

if (strict && missing.length > 0) {
  process.exit(1);
}
