#!/usr/bin/env node
/**
 * Compare Wikimedia mediawiki-libs-IPAValidator $ipaRegex character class to data/inventory.json.
 *
 * Upstream: https://github.com/wikimedia/mediawiki-libs-IPAValidator/blob/main/src/Validator.php
 *
 * Usage:
 *   node scripts/compare-mediawiki-validator.mjs
 *   node scripts/compare-mediawiki-validator.mjs --strict   # exit 1 if any MW scalar is missing from inventory
 *
 * With network, the script fetches Validator.php and parses the class body. If fetch or parse fails,
 * it falls back to an embedded snapshot (update FALLBACK_CLASS_BODY when upstream changes).
 */
import { readFileSync } from "node:fs";
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

function extractClassBodyFromPhp(text) {
  const m = text.match(
    /\$ipaRegex\s*=\s*<<<EOD\s*\r?\n\/\^\[([\s\S]*?)\]\+\$\/ui\s*\r?\nEOD\s*;/m
  );
  return m ? m[1].trim() : null;
}

async function loadClassBody() {
  try {
    const res = await fetch(UPSTREAM_RAW, {
      headers: { "user-agent": "ipa-unicode-inventory-compare/1.3" },
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const text = await res.text();
    const body = extractClassBodyFromPhp(text);
    if (body) {
      console.log("Using character class from upstream (fetched).\n");
      return body;
    }
    throw new Error("Could not parse $ipaRegex heredoc");
  } catch (e) {
    console.warn(`Upstream fetch/parse failed (${e.message}); using embedded FALLBACK_CLASS_BODY.\n`);
    return FALLBACK_CLASS_BODY;
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

const cls = await loadClassBody();
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

if (strict && missing.length > 0) {
  process.exit(1);
}
