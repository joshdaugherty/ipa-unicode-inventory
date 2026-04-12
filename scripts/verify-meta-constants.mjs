#!/usr/bin/env node
/**
 * Ensures src/MetaConstants.php matches data/inventory.json meta (same output as build.js).
 */
import { readFileSync } from "node:fs";
import { join, dirname } from "node:path";
import { fileURLToPath } from "node:url";
import { renderMetaConstantsPhp } from "./meta-constants-php.mjs";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");
const inv = JSON.parse(readFileSync(join(root, "data", "inventory.json"), "utf8"));
const expected = renderMetaConstantsPhp(inv.meta);
const actual = readFileSync(join(root, "src", "MetaConstants.php"), "utf8");
if (actual !== expected) {
  console.error(
    "src/MetaConstants.php is out of sync with data/inventory.json meta. Run: npm run build",
  );
  process.exit(1);
}
console.log("src/MetaConstants.php matches inventory meta.");
