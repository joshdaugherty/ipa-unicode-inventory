#!/usr/bin/env node
import Ajv2020 from "ajv/dist/2020.js";
import addFormats from "ajv-formats";
import { readFileSync, readdirSync } from "node:fs";
import { join, dirname } from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");
const schemaDir = join(root, "schema");

const ajv = new Ajv2020({ allErrors: true, strict: false });
addFormats(ajv);

for (const name of readdirSync(schemaDir).filter((f) => f.endsWith(".json"))) {
  const schema = JSON.parse(readFileSync(join(schemaDir, name), "utf8"));
  ajv.addSchema(schema);
}

function validateFile(schemaId, dataPath) {
  const data = JSON.parse(readFileSync(dataPath, "utf8"));
  const validate = ajv.getSchema(schemaId);
  if (!validate) {
    throw new Error(`Schema not registered: ${schemaId}`);
  }
  if (!validate(data)) {
    console.error(validate.errors);
    throw new Error(`Schema validation failed: ${dataPath}`);
  }
}

const invId =
  "https://raw.githubusercontent.com/ipa-unicode-inventory/ipa-unicode-inventory/main/schema/inventory.wrapper.schema.json";
const normId =
  "https://raw.githubusercontent.com/ipa-unicode-inventory/ipa-unicode-inventory/main/schema/normalization.wrapper.schema.json";

validateFile(invId, join(root, "data", "inventory.json"));
validateFile(invId, join(root, "data", "inventory.phonetic-strict.json"));
validateFile(normId, join(root, "data", "normalization.json"));

const inventory = JSON.parse(readFileSync(join(root, "data", "inventory.json"), "utf8"));
const inventoryStrict = JSON.parse(
  readFileSync(join(root, "data", "inventory.phonetic-strict.json"), "utf8")
);
const norm = JSON.parse(readFileSync(join(root, "data", "normalization.json"), "utf8"));

const META_KEYS = [
  "policy_id",
  "policy_title",
  "dataset_version",
  "schema_version",
  "unicode_version_min",
];
for (const k of META_KEYS) {
  const a = inventory.meta[k];
  const b = norm.meta[k];
  if (a !== b) {
    throw new Error(
      `inventory.json and normalization.json disagree on meta.${k}: ${JSON.stringify(a)} vs ${JSON.stringify(b)}`
    );
  }
}

const ALIGN_STRICT_WITH_NORM = ["dataset_version", "schema_version", "unicode_version_min"];
for (const k of ALIGN_STRICT_WITH_NORM) {
  const a = inventoryStrict.meta[k];
  const b = norm.meta[k];
  if (a !== b) {
    throw new Error(
      `inventory.phonetic-strict.json and normalization.json disagree on meta.${k}: ${JSON.stringify(a)} vs ${JSON.stringify(b)}`
    );
  }
}

if (inventoryStrict.meta.profile_id !== "phonetic_strict") {
  throw new Error(
    `inventory.phonetic-strict.json meta.profile_id must be "phonetic_strict", got ${JSON.stringify(inventoryStrict.meta.profile_id)}`
  );
}
if (inventory.meta.profile_id !== "corpus_inclusive") {
  throw new Error(
    `inventory.json meta.profile_id must be "corpus_inclusive", got ${JSON.stringify(inventory.meta.profile_id)}`
  );
}

const allowed = new Set(inventory.code_points.map((r) => r.cp));
const allowedStrict = new Set(inventoryStrict.code_points.map((r) => r.cp));
function* scalars(str) {
  for (let i = 0; i < str.length; ) {
    const cp = str.codePointAt(i);
    i += cp > 0xffff ? 2 : 1;
    yield cp;
  }
}
for (const rule of norm.rules || []) {
  for (const cp of scalars(rule.to)) {
    if (!allowed.has(cp)) {
      throw new Error(
        `Normalization rule "${rule.id}" maps to scalar U+${cp.toString(16).toUpperCase()} not in inventory.`
      );
    }
    if (!allowedStrict.has(cp)) {
      throw new Error(
        `Normalization rule "${rule.id}" maps to scalar U+${cp.toString(16).toUpperCase()} not in inventory.phonetic-strict.json.`
      );
    }
  }
}

for (const cp of allowedStrict) {
  if (!allowed.has(cp)) {
    throw new Error(
      `phonetic_strict contains U+${cp.toString(16).toUpperCase()} not present in corpus_inclusive inventory.`
    );
  }
}

console.log(
  "All data files validate against JSON Schema; inventory and normalization meta fields are aligned."
);
