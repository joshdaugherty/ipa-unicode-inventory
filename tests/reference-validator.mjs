/**
 * Minimal reference: every Unicode scalar in the string must appear in the inventory set
 * after optional normalization (longest `from` first).
 */

/** @param {string} str */
export function* scalars(str) {
  for (let i = 0; i < str.length; ) {
    const cp = str.codePointAt(i);
    i += cp > 0xffff ? 2 : 1;
    yield cp;
  }
}

/** Apply normalization rules: longest match first, then array order for same length. */
export function applyNormalization(str, rules) {
  if (!rules?.length) return str;
  const ordered = [...rules].sort((a, b) => b.from.length - a.from.length || 0);
  let out = str;
  for (const { from, to } of ordered) {
    if (!from) continue;
    out = out.split(from).join(to);
  }
  return out;
}

/**
 * @param {Set<number>} allowed
 * @param {string} str
 * @returns {{ ok: boolean, firstDisallowed?: number }}
 */
export function validateTranscription(allowed, str) {
  for (const cp of scalars(str)) {
    if (!allowed.has(cp)) {
      return { ok: false, firstDisallowed: cp };
    }
  }
  return { ok: true };
}

/**
 * @param {object} inventory - parsed inventory.json
 * @param {object | null} normalization - parsed normalization.json or null
 * @param {string} str
 * @param {{ normalize?: boolean }} opts
 */
export function validateWithInventory(inventory, normalization, str, opts = {}) {
  const allowed = new Set(inventory.code_points.map((r) => r.cp));
  let s = str;
  if (opts.normalize && normalization?.rules?.length) {
    s = applyNormalization(s, normalization.rules);
  }
  return validateTranscription(allowed, s);
}
