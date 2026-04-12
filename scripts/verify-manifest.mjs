#!/usr/bin/env node
import { createHash } from "node:crypto";
import { readFileSync } from "node:fs";
import { join, dirname } from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = dirname(fileURLToPath(import.meta.url));
const outDir = join(__dirname, "..", "build", "output");
const manifest = JSON.parse(readFileSync(join(outDir, "manifest.json"), "utf8"));

for (const [name, expectedHex] of Object.entries(manifest.files)) {
  const buf = readFileSync(join(outDir, name));
  const hex = createHash("sha256").update(buf).digest("hex");
  if (hex !== expectedHex) {
    console.error(`Hash mismatch for ${name}: expected ${expectedHex}, got ${hex}`);
    process.exit(1);
  }
}
console.log("manifest.json digests match build/output files.");
