#!/usr/bin/env node
/**
 * Reference validator fixtures (uses data/*.json only).
 * Run after `npm run validate` and `npm run build` when using the full `npm test` script.
 */
import { readFileSync } from "node:fs";
import { join, dirname } from "node:path";
import { fileURLToPath } from "node:url";
import { validateWithInventory } from "./reference-validator.mjs";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const inventory = JSON.parse(readFileSync(join(root, "data", "inventory.json"), "utf8"));
const normalization = JSON.parse(readFileSync(join(root, "data", "normalization.json"), "utf8"));
const fixtures = JSON.parse(readFileSync(join(__dirname, "fixtures.json"), "utf8"));

let failed = 0;
for (const f of fixtures) {
  const result = validateWithInventory(inventory, normalization, f.string, {
    normalize: f.normalize === true,
  });
  const ok = result.ok === f.allowed;
  if (!ok) {
    failed++;
    console.error(
      `FAIL: ${f.description}\n  expected allowed=${f.allowed}, got ok=${result.ok}` +
        (result.firstDisallowed !== undefined
          ? ` (first disallowed U+${result.firstDisallowed.toString(16).toUpperCase()})`
          : "")
    );
  }
}

if (failed > 0) {
  console.error(`\n${failed} fixture(s) failed.`);
  process.exit(1);
}

console.log(`All ${fixtures.length} reference validator fixtures passed.`);
