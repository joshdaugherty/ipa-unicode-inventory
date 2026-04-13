#!/usr/bin/env node
/**
 * Compare data/inventory.json to code points declared in westonruter/ipa-chart HTML.
 *
 * The chart marks each pickable symbol with <span title="U+XXXX: NAME">…</span>.
 * Sources:
 *   - https://github.com/westonruter/ipa-chart/blob/master/index.html
 *   - https://github.com/westonruter/ipa-chart/blob/master/accessiblechart.html
 *
 * Usage:
 *   node scripts/compare-ipa-chart-westonruter.mjs
 *   node scripts/compare-ipa-chart-westonruter.mjs --strict   # exit 1 if any chart scalar is missing from inventory
 */
import { readFileSync } from "node:fs";
import { join, dirname } from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");
const strict = process.argv.includes("--strict");

const RAW_BASE = "https://raw.githubusercontent.com/westonruter/ipa-chart/master";

const FILES = ["index.html", "accessiblechart.html"];

/** title="U+0288: LATIN ..." or title="U+1D43: ..." */
const TITLE_U_RE = /title="U\+([0-9A-Fa-f]{4,6}):/gi;

function extractFromHtml(html) {
  const set = new Set();
  for (const m of html.matchAll(TITLE_U_RE)) {
    const cp = parseInt(m[1], 16);
    if (cp >= 0 && cp <= 0x10ffff && !(cp >= 0xd800 && cp <= 0xdfff)) {
      set.add(cp);
    }
  }
  return set;
}

async function loadChartCodePoints() {
  const merged = new Set();
  for (const name of FILES) {
    const url = `${RAW_BASE}/${name}`;
    try {
      const res = await fetch(url, {
        headers: { "user-agent": "ipa-unicode-inventory-compare/1.5" },
      });
      if (!res.ok) throw new Error(`${name}: HTTP ${res.status}`);
      const html = await res.text();
      const found = extractFromHtml(html);
      console.log(`${name}: ${found.size} distinct code points (title=U+…)`);
      for (const cp of found) merged.add(cp);
    } catch (e) {
      console.error(`Failed to fetch ${url}: ${e.message}`);
      throw e;
    }
  }
  return merged;
}

const inv = JSON.parse(readFileSync(join(root, "data", "inventory.json"), "utf8"));
const allowed = new Set(inv.code_points.map((r) => r.cp));

console.log("westonruter/ipa-chart — https://github.com/westonruter/ipa-chart\n");
const chart = await loadChartCodePoints();
console.log("\nCombined distinct chart code points:", chart.size);
console.log("Inventory code points:", allowed.size);

const missing = [...chart].filter((cp) => !allowed.has(cp)).sort((a, b) => a - b);
const extraVsChart = [...allowed].filter((cp) => !chart.has(cp)).length;

console.log("Chart scalars missing from our inventory:", missing.length);
console.log("Our scalars not on this chart (expected; we include extIPA, delimiters, etc.):", extraVsChart);

if (missing.length) {
  console.log("\nMissing:");
  for (const cp of missing) {
    console.log(
      "  U+" + cp.toString(16).toUpperCase().padStart(4, "0"),
      JSON.stringify(String.fromCodePoint(cp))
    );
  }
} else {
  console.log("\nEvery chart-declared scalar is in our inventory.");
}

if (strict && missing.length > 0) {
  process.exit(1);
}
