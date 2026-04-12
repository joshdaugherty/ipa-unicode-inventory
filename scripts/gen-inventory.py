#!/usr/bin/env python3
"""One-time / maintainer tool: emit data/inventory.json for the default policy ranges."""
from __future__ import annotations

import json
import unicodedata
from pathlib import Path


def cp_hex(n: int) -> str:
    return "U+" + format(n, "X").zfill(4)


def add_corpus_in_band(seen: set[int], entries: list[dict]) -> None:
    """Delimiters and tier punctuation common beside IPA in transcriptions and corpora."""
    delimiter_rows: list[tuple[int, str]] = [
        (0x0028, "Delimiter: left parenthesis; optional segments, grouping, pause/silent-articulation markup."),
        (0x0029, "Delimiter: right parenthesis."),
        (0x005B, "Delimiter: left square bracket; narrow (phonetic) transcription."),
        (0x005D, "Delimiter: right square bracket."),
        (0x007B, "Delimiter: left brace; prosodic / VoQS commentary spans."),
        (0x007D, "Delimiter: right brace."),
        (0x003C, "Delimiter: less-than; grapheme or orthographic tier (ASCII)."),
        (0x003E, "Delimiter: greater-than."),
        (0x27E8, "Delimiter: mathematical left angle bracket; grapheme notation ⟨ ⟩."),
        (0x27E9, "Delimiter: mathematical right angle bracket."),
        (0x002E, "Delimiter: full stop; abbreviations and mixed prose beside IPA."),
        (0x002F, "Delimiter: solidus; phonemic slashes and similar tiering."),
        (0x007C, "Delimiter: vertical bar; alternation or parallel gloss conventions."),
        (0x002C, "Delimiter: comma; minor boundaries and lists in running text."),
        (0x003A, "Delimiter: colon; timing, lists, or orthographic snippets."),
        (0x003B, "Delimiter: semicolon."),
        (0x002D, "Delimiter: hyphen-minus; morpheme or compound boundaries next to IPA."),
        (0x003D, "Delimiter: equals sign; clitic boundaries (e.g. Leipzig-style glossing)."),
        (0x002B, "Delimiter: plus; morphological boundary in some gloss tiers."),
        (0x005F, "Delimiter: low line; pause slots or format conventions."),
        (0x0023, "Delimiter: number sign; word-boundary or rule notation in some linguistics corpora."),
        (0x002A, "Delimiter: asterisk; ungrammatical/reconstructed forms adjacent to IPA in examples."),
        (0x003F, "Delimiter: question mark; uncertain segment in some transcription styles."),
        (0x007E, "Delimiter: tilde; fusion or informal variant notation."),
        (0x0025, "Delimiter: percent; rare tier or label conventions."),
        (0x0040, "Delimiter: commercial at; annotation conventions in some datasets."),
        (0x0022, "Delimiter: ASCII quotation mark; cited forms beside IPA."),
        (0x0027, "Delimiter: ASCII apostrophe; orthographic; prefer MODIFIER LETTER APOSTROPHE (U+02BC) for IPA glottals when normalizing."),
        (0x00AB, "Delimiter: left-pointing double angle quotation mark."),
        (0x00BB, "Delimiter: right-pointing double angle quotation mark."),
        (0x2018, "Delimiter: left single quotation mark; cited forms."),
        (0x2019, "Delimiter: right single quotation mark; may normalize to U+02BC for IPA."),
        (0x201C, "Delimiter: left double quotation mark."),
        (0x201D, "Delimiter: right double quotation mark."),
        (0x2026, "Delimiter: horizontal ellipsis; truncation or pause in commentary."),
    ]
    for cp, note in delimiter_rows:
        if cp in seen or 0xD800 <= cp <= 0xDFFF:
            continue
        try:
            unicodedata.name(chr(cp))
        except ValueError:
            continue
        seen.add(cp)
        entries.append({"cp": cp, "cp_hex": cp_hex(cp), "category": "delimiter", "notes": note})

    for cp in range(0x0030, 0x003A):
        if cp in seen:
            continue
        seen.add(cp)
        entries.append(
            {
                "cp": cp,
                "cp_hex": cp_hex(cp),
                "category": "other",
                "notes": "ASCII digit; tone digits, stress levels, pause-duration labels, and mixed corpus text.",
            }
        )

    sp = 0x0020
    if sp not in seen:
        seen.add(sp)
        entries.append(
            {
                "cp": sp,
                "cp_hex": cp_hex(sp),
                "category": "other",
                "notes": "ASCII space; word or syllable separation in running transcription.",
            }
        )


def category_bucket(cp: int) -> str | None:
    c = chr(cp)
    try:
        name = unicodedata.name(c)
    except ValueError:
        return None
    gc = unicodedata.category(c)
    if gc == "Lm":
        return "modifier_letter"
    if gc in ("Mn", "Me", "Mc"):
        return "combining_mark"
    if gc in ("Lo", "Lu", "Ll"):
        return "letter"
    if gc == "Sk":
        return "diacritic_spacing"
    if gc == "Po" and cp in (0x02C8, 0x02CC):
        return "suprasegmental"
    if "TIE BELOW" in name or "TIE ABOVE" in name or "DOUBLE BREVE" in name:
        return "connector"
    if "TONE" in name and gc != "Lo":
        return "tone"
    if 0x1D18F <= cp <= 0x1D193:
        return "suprasegmental"
    if 0x24B6 <= cp <= 0x24E9:
        return "other"
    if cp == 0x005C:
        return "other"
    return "other"


def main() -> None:
    # Ranges: core IPA blocks, then extIPA / clinical (ICPLA chart) Unicode additions
    # and practical IPA scalars (Latin/Greek spellings, clicks, superscripts).
    # See README and https://en.wikipedia.org/wiki/Extensions_to_the_International_Phonetic_Alphabet
    range_pairs = [
        (0x0250, 0x02AF),
        (0x02B0, 0x02FF),
        (0x0300, 0x036F),
        (0x1D00, 0x1DBF),
        (0x02C8, 0x02CC),
        (0x02D0, 0x02D1),
        (0x02BC, 0x02BC),
        (0x02BE, 0x02BF),
        (0x02C0, 0x02C1),
        (0x02E4, 0x02E4),
        (0x1AB0, 0x1AFF),
        (0x1DC0, 0x1DFF),
        (0x1DF00, 0x1DFFF),
        (0x10780, 0x107BF),
        (0x2070, 0x209C),
        (0x24B6, 0x24E9),
        (0xA700, 0xA71F),
        (0xA78B, 0xA78F),
        (0xA7AE, 0xA7B0),
        (0x00E6, 0x00E7),
        (0x00F0, 0x00F0),
        (0x00F8, 0x00F8),
        (0x0127, 0x0127),
        (0x014B, 0x014B),
        (0x0153, 0x0153),
        (0x01C0, 0x01C3),
        (0x03B2, 0x03B2),
        (0x03B8, 0x03B8),
        (0x03C7, 0x03C7),
        (0x2016, 0x2016),
        (0x203F, 0x203F),
        (0x2C71, 0x2C71),
        (0x2E28, 0x2E29),
        (0x2987, 0x2988),
    ]
    explicit_cps = [
        0x00A1,
        0x005C,
        0x2191,
        0x2193,
        0x2197,
        0x2198,
        0x20DD,
        0x1D18F,
        0x1D191,
        0x1D192,
        0x1D193,
    ]
    seen: set[int] = set()
    entries: list[dict] = []
    for start, end in range_pairs:
        for cp in range(start, end + 1):
            if cp in seen or 0xD800 <= cp <= 0xDFFF:
                continue
            try:
                unicodedata.name(chr(cp))
            except ValueError:
                continue
            cat = category_bucket(cp)
            if cat is None:
                continue
            seen.add(cp)
            row: dict = {"cp": cp, "cp_hex": cp_hex(cp), "category": cat}
            if 0x24B6 <= cp <= 0x24E9:
                row["notes"] = "Enclosed alphanumerics; extIPA indeterminate-segment wildcards (see ICPLA / extIPA chart)."
            entries.append(row)

    for cp in explicit_cps:
        if cp in seen or 0xD800 <= cp <= 0xDFFF:
            continue
        try:
            unicodedata.name(chr(cp))
        except ValueError:
            continue
        cat = category_bucket(cp)
        if cat is None:
            continue
        seen.add(cp)
        row = {"cp": cp, "cp_hex": cp_hex(cp), "category": cat}
        if cp == 0x005C:
            row["notes"] = "extIPA reiterated articulation (stutter) marker in narrow transcription."
        if cp in (0x2191, 0x2193):
            row["notes"] = "extIPA egressive / ingressive airstream arrows (adjacent to letter or isolated)."
        if cp in (0x2197, 0x2198):
            row["notes"] = "IPA chart global rise / global fall (suprasegmentals; westonruter/ipa-chart index.html)."
        if cp == 0x20DD:
            row["notes"] = "Combining enclosing circle; extIPA indeterminate segment (font-dependent)."
        if cp == 0x00A1:
            row["notes"] = "Inverted exclamation; extIPA sublaminal percussive with click letters."
        if 0x1D18F <= cp <= 0x1D193:
            row["notes"] = "Musical dynamics symbols used in extIPA / VoQS-style prosodic markup."
        entries.append(row)

    add_corpus_in_band(seen, entries)

    for cp in list(range(ord("A"), ord("Z") + 1)) + list(range(ord("a"), ord("z") + 1)):
        if cp not in seen:
            seen.add(cp)
            entries.append(
                {
                    "cp": cp,
                    "cp_hex": cp_hex(cp),
                    "category": "letter",
                    "notes": "ASCII Latin; included for mixed orthography and normalization targets.",
                }
            )

    entries.sort(key=lambda e: e["cp"])
    meta = {
        "schema_version": "1.0.0",
        "dataset_version": "1.0.0",
        "unicode_version_min": "15.1.0",
        "policy_id": "ipa-extipa-corpus-inclusive",
        "policy_title": "IPA and extIPA with modifiers, transcription delimiters, and corpus tier punctuation",
        "policy_description": (
            "Core IPA: IPA Extensions (U+0250 through U+02AF), spacing modifier letters U+02B0 "
            "through U+02FF, combining diacritical marks U+0300 through U+036F, phonetic extensions "
            "U+1D00 through U+1DBF. extIPA / disordered-speech extensions: Combining Diacritical Marks "
            "Extended U+1AB0 through U+1AFF, Combining Diacritical Marks Supplement U+1DC0 through U+1DFF, "
            "Latin Extended-G U+1DF00 through U+1DFFF, Modifier Letters Supplement U+10780 through U+107BF, "
            "modifier tone letters U+A700 through U+A71F, Latin Extended-D letters ꞎ U+A78E and ꞯ U+A7AF, "
            "superscripts and subscripts U+2070 through U+209C, circled Latin wildcards U+24B6 through "
            "U+24E9, prosodic brackets U+2E28/U+2E29 and U+2987/U+2988, inverted exclamation U+00A1, "
            "airstream arrows U+2191/U+2193, global rise/fall U+2197/U+2198 (IPA chart suprasegmentals), "
            "combining enclosing circle U+20DD, backslash U+005C for "
            "reiterated articulation, and selected musical dynamics U+1D18F through U+1D193. "
            "Transcription and corpus-in-band delimiters (category delimiter): parentheses, square brackets, "
            "slashes, braces, ASCII and mathematical angle brackets ⟨ ⟩ (U+27E8/U+27E9), full stop, "
            "pipe, comma, colon, semicolon, hyphen-minus, equals, plus, low line, commercial at, number "
            "sign, asterisk, question mark, tilde, percent, ASCII and typographic quotes, guillemets, "
            "horizontal ellipsis. ASCII digits and ASCII space (category other) for tone indices, timing "
            "labels, and running text. Also common Latin/Greek IPA letters (æ ç ð ø ħ ŋ œ, β θ χ), Latin "
            "clicks U+01C0 through U+01C3, undertie U+203F, double vertical line U+2016, labiodental flap "
            "U+2C71, and ASCII Latin letters. Does not include arbitrary CJK or whole mathematical blocks. "
            "Consumers may strip category delimiter before phonetic-only validation. Surrogate code points "
            "are never listed."
        ),
    }
    doc = {
        "$schema": "https://raw.githubusercontent.com/ipa-unicode-inventory/ipa-unicode-inventory/main/schema/inventory.wrapper.schema.json",
        "meta": meta,
        "code_points": entries,
    }
    out = Path(__file__).resolve().parent.parent / "data" / "inventory.json"
    out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text(json.dumps(doc, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(f"Wrote {len(entries)} code points to {out}")


if __name__ == "__main__":
    main()
