#!/usr/bin/env node
// Fail CI if any tracked file contains Windows-1252 double-encoded UTF-8 byte
// sequences (see issue #1). The detection targets bytes that can only result
// from a cp1252 round-trip applied to an originally valid UTF-8 IPA / Latin
// scalar, while staying narrow enough to avoid flagging legitimate text that
// just contains adjacent Latin-1 supplement characters.

import { readFileSync } from "node:fs";
import { execFileSync } from "node:child_process";
import { extname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

// `\xC3[\x89-\x8D]` is the UTF-8 encoding of U+00C9..U+00CD — the Latin
// capital letters É Ê Ë Ì Í, which are also the cp1252 codepoints that
// correspond to the lead byte of every IPA-relevant UTF-8 sequence
// (C9 XX = IPA Extensions block, CA/CB XX = Spacing Modifier Letters,
// CC/CD XX = Combining Diacritics). When the original IPA continuation byte
// (0x80..0xBF) is itself reinterpreted via cp1252 and re-encoded, it lands
// in one of the byte ranges below. That dual-occurrence is the fingerprint.
const DOUBLE_ENCODED = new RegExp(
  [
    "\\xC3[\\x89-\\x8D]",
    "(?:",
    "\\xC2[\\x80-\\xBF]",
    "|\\xC5[\\x92\\x93\\xA0\\xA1\\xB8\\xBD\\xBE]",
    "|\\xC6\\x92",
    "|\\xCB[\\x86\\x9C]",
    "|\\xE2\\x80[\\x80-\\xBF]",
    "|\\xE2\\x82\\xAC",
    "|\\xE2\\x84\\xA2",
    ")",
  ].join(""),
  "g",
);

const BINARY_EXTENSIONS = new Set([
  ".png",
  ".jpg",
  ".jpeg",
  ".gif",
  ".webp",
  ".ico",
  ".pdf",
  ".zip",
  ".gz",
  ".tar",
  ".woff",
  ".woff2",
  ".ttf",
  ".otf",
  ".eot",
  ".mp3",
  ".mp4",
  ".wav",
]);

function selfTest() {
  // Canonical UTF-8 for `ʰ` is CA B0. cp1252 round-trip yields C3 8A C2 B0.
  const sample = Buffer.from([0xc3, 0x8a, 0xc2, 0xb0]).toString("latin1");
  if (!DOUBLE_ENCODED.test(sample)) {
    throw new Error(
      "check-double-encoding self-test failed: regex did not match known mojibake sample",
    );
  }
  // Reset lastIndex since DOUBLE_ENCODED is /g.
  DOUBLE_ENCODED.lastIndex = 0;
  // Canonical bytes for `ʰə̥` must not match.
  const clean = Buffer.from([0xca, 0xb0, 0xc9, 0x99, 0xcc, 0xa5]).toString(
    "latin1",
  );
  if (DOUBLE_ENCODED.test(clean)) {
    throw new Error(
      "check-double-encoding self-test failed: regex matched clean UTF-8 IPA bytes",
    );
  }
  DOUBLE_ENCODED.lastIndex = 0;
}

function listTrackedFiles() {
  // -z to handle paths with whitespace; trim trailing NUL.
  const out = execFileSync("git", ["ls-files", "-z"], {
    maxBuffer: 64 * 1024 * 1024,
  });
  return out
    .toString("utf8")
    .split("\0")
    .filter(Boolean);
}

function shouldScan(path) {
  const ext = extname(path).toLowerCase();
  if (BINARY_EXTENSIONS.has(ext)) return false;
  // The guard script itself encodes the patterns as JS string escapes (not
  // raw bytes), so it is safe — but skipping avoids any confusion if a
  // future refactor inlines a literal byte sample.
  if (path === "scripts/check-double-encoding.mjs") return false;
  return true;
}

function formatBytes(str, start, length) {
  const hex = [];
  for (let i = 0; i < length; i++) {
    hex.push(str.charCodeAt(start + i).toString(16).padStart(2, "0").toUpperCase());
  }
  return hex.join(" ");
}

function scan(files) {
  const findings = [];
  for (const path of files) {
    if (!shouldScan(path)) continue;
    let buf;
    try {
      buf = readFileSync(path, "latin1");
    } catch {
      continue;
    }
    DOUBLE_ENCODED.lastIndex = 0;
    let match;
    while ((match = DOUBLE_ENCODED.exec(buf)) !== null) {
      findings.push({
        path,
        offset: match.index,
        bytes: formatBytes(buf, match.index, match[0].length),
      });
    }
  }
  return findings;
}

function main() {
  selfTest();
  const files = listTrackedFiles();
  const findings = scan(files);
  if (findings.length === 0) {
    console.log(
      `No Windows-1252 double-encoded UTF-8 sequences found in ${files.length} tracked files.`,
    );
    return 0;
  }
  console.error(
    `Found ${findings.length} suspected Windows-1252 double-encoded UTF-8 sequence(s):`,
  );
  for (const f of findings) {
    console.error(`  ${f.path} @ byte ${f.offset}: ${f.bytes}`);
  }
  console.error(
    "\nSee the README section 'Authoring fixtures and source files with correct UTF-8' for how to verify and repair affected files.",
  );
  return 1;
}

// Only run when invoked directly (not when imported).
const invokedDirectly = resolve(fileURLToPath(import.meta.url)) === resolve(process.argv[1] ?? "");
if (invokedDirectly) {
  process.exit(main());
}

export { DOUBLE_ENCODED, scan, listTrackedFiles };
