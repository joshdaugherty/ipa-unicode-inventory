<?php

declare(strict_types=1);

namespace JoshDaugherty\IpaUnicodeInventory;

/**
 * Validates UTF-8 transcription strings per **Unicode scalar** using {@see Inventory}.
 *
 * **Pipeline (in order):** optional delimiter stripping, `normalization.json` rules (**longest
 * `from` first**), optional legacy ASCII normalization aligned with Wikimedia
 * {@link https://github.com/wikimedia/mediawiki-libs-IPAValidator IPAValidator}
 * (`'`→ˈ, `:`→ː, `,`→ˌ), then allowlist checks for each remaining scalar.
 *
 * **Not implemented:** Wikimedia `stripRegex`, Google/TTS normalization mode, or grapheme-cluster
 * segmentation.
 *
 * **Delimiter vs Wikimedia ASCII:** stripping runs first. ASCII apostrophe (**U+0027**) is a
 * **`delimiter`** in the default inventory, so with {@see STRIP_DELIMITERS_INVENTORY} it is removed
 * before **`'`→ˈ** can apply. Use {@see STRIP_DELIMITERS_NONE} or {@see STRIP_DELIMITERS_CUSTOM}
 * if you need that legacy mapping.
 */
final class TranscriptionValidator
{
    /** Strip no code points before normalization or validation. */
    public const STRIP_DELIMITERS_NONE = 0;

    /** Strip every scalar whose inventory `category` is `delimiter`. */
    public const STRIP_DELIMITERS_INVENTORY = 1;

    /** Strip only the code points given to {@see fromDisk} as `$customDelimiterScalars`. */
    public const STRIP_DELIMITERS_CUSTOM = 2;

    private readonly Inventory $inventory;

    /** @var array<int, true> Code points removed during the delimiter-stripping phase */
    private readonly array $delimiterScalarsToStrip;

    /** @var list<array{from: string, to: string}> Normalization rules, longest `from` first */
    private readonly array $normalizationRulesLongestFirst;

    private readonly bool $wikimediaLegacyAsciiNormalization;

    /**
     * @param  Inventory  $inventory  Allowlist used for the final per-scalar checks
     * @param  array<int, true>  $delimiterScalarsToStrip  Code points to remove before normalization; empty array = no stripping
     * @param  list<array{from: string, to: string}>  $normalizationRulesLongestFirst Rules from `normalization.json`, already sorted longest `from` first (see {@see sortNormalizationRules})
     * @param  bool  $wikimediaLegacyAsciiNormalization  When `true`, apply ASCII `'` / `:` / `,` → IPA stress/length characters after normalization rules
     *
     * @return void
     */
    public function __construct(
        Inventory $inventory,
        array $delimiterScalarsToStrip,
        array $normalizationRulesLongestFirst,
        bool $wikimediaLegacyAsciiNormalization = false,
    ) {
        $this->inventory = $inventory;
        $this->delimiterScalarsToStrip = $delimiterScalarsToStrip;
        $this->normalizationRulesLongestFirst = $normalizationRulesLongestFirst;
        $this->wikimediaLegacyAsciiNormalization = $wikimediaLegacyAsciiNormalization;
    }

    /**
     * Factory that loads inventory (and optionally normalization) from disk and builds the pipeline.
     *
     * @param  string|null  $inventoryJsonPath  Path to `inventory.json`, or `null` for {@see Resources::inventoryJsonPath}
     * @param  string|null  $normalizationJsonPath  Path to `normalization.json`, or `null` for {@see Resources::normalizationJsonPath}
     * @param  int  $delimiterStripMode  One of {@see STRIP_DELIMITERS_NONE}, {@see STRIP_DELIMITERS_INVENTORY}, {@see STRIP_DELIMITERS_CUSTOM}
     * @param  list<int>|array<int, true>|null  $customDelimiterScalars  When mode is {@see STRIP_DELIMITERS_CUSTOM}: list of ints or `cp => true` map; ignored for other modes
     * @param  bool  $applyNormalizationJson  When `false`, skip loading `normalization.json` and use no dataset normalization rules
     * @param  bool  $wikimediaLegacyAscii  When `true`, apply Wikimedia-style ASCII stress/length replacements after dataset rules
     * @param  bool  $validateSchema  When `true`, validate loaded JSON against bundled schema (requires `justinrainbow/json-schema`)
     *
     * @return self Configured validator instance
     *
     * @throws \InvalidArgumentException if `$delimiterStripMode` is not a known constant
     * @throws \JsonException if JSON decoding fails
     * @throws \RuntimeException if files are missing, structurally invalid, or schema validation fails when enabled
     */
    public static function fromDisk(
        ?string $inventoryJsonPath = null,
        ?string $normalizationJsonPath = null,
        int $delimiterStripMode = self::STRIP_DELIMITERS_INVENTORY,
        ?array $customDelimiterScalars = null,
        bool $applyNormalizationJson = true,
        bool $wikimediaLegacyAscii = false,
        bool $validateSchema = false,
    ): self {
        $inventory = Inventory::fromDisk($inventoryJsonPath, $validateSchema);
        $stripMap = match ($delimiterStripMode) {
            self::STRIP_DELIMITERS_NONE => [],
            self::STRIP_DELIMITERS_INVENTORY => InventoryLoader::delimiterScalarSet($inventoryJsonPath, $validateSchema),
            self::STRIP_DELIMITERS_CUSTOM => self::normalizeDelimiterScalarMap($customDelimiterScalars ?? []),
            default => throw new \InvalidArgumentException('Invalid delimiter strip mode: ' . $delimiterStripMode),
        };

        $rules = [];
        if ($applyNormalizationJson) {
            $doc = InventoryLoader::loadNormalization($normalizationJsonPath, $validateSchema);
            $rules = self::sortNormalizationRules($doc['rules']);
        }

        return new self($inventory, $stripMap, $rules, $wikimediaLegacyAscii);
    }

    /**
     * Runs the full pipeline on `$text` and returns whether every resulting scalar is allowed.
     *
     * @param  string  $text  UTF-8 string to validate (may be empty)
     *
     * @return bool `true` if `$text` is empty, well-formed UTF-8, and every scalar after the pipeline is allowed; `false` on invalid UTF-8, `mb_str_split` failure, or any disallowed scalar
     */
    public function isValid(string $text): bool
    {
        if ($text === '') {
            return true;
        }
        if (!\mb_check_encoding($text, 'UTF-8')) {
            return false;
        }

        $s = $this->stripDelimiterScalars($text);
        if ($s === null) {
            return false;
        }
        $s = $this->applyNormalizationRules($s);
        if ($this->wikimediaLegacyAsciiNormalization) {
            $s = self::applyWikimediaLegacyAsciiNormalization($s);
        }

        return $this->allScalarsAllowed($s);
    }

    /**
     * Filters and sorts normalization rules for longest-match-first application.
     *
     * @param  list<array<string, mixed>>  $rules  Raw `rules` array from `normalization.json`
     *
     * @return list<array{from: string, to: string}> Rules with string `from`/`to`, non-empty `from`, sorted by descending UTF-8 length of `from`
     */
    public static function sortNormalizationRules(array $rules): array
    {
        $out = [];
        foreach ($rules as $rule) {
            if (!isset($rule['from'], $rule['to']) || !\is_string($rule['from']) || !\is_string($rule['to'])) {
                continue;
            }
            if ($rule['from'] === '') {
                continue;
            }
            $out[] = ['from' => $rule['from'], 'to' => $rule['to']];
        }
        \usort(
            $out,
            static function (array $a, array $b): int {
                return \mb_strlen($b['from'], 'UTF-8') <=> \mb_strlen($a['from'], 'UTF-8');
            },
        );

        return $out;
    }

    /**
     * Applies the same ASCII replacements as Wikimedia `Validator::normalizeIPA` (stress/length only).
     *
     * @param  string  $utf8  Well-formed UTF-8 input
     *
     * @return string `$utf8` after substituting ASCII `'` → U+02C8, `:` → U+02D0, `,` → U+02CC
     */
    public static function applyWikimediaLegacyAsciiNormalization(string $utf8): string
    {
        $map = [
            ["'", "\u{02C8}"],
            [':', "\u{02D0}"],
            [',', "\u{02CC}"],
        ];
        foreach ($map as [$from, $to]) {
            $utf8 = \str_replace($from, $to, $utf8);
        }

        return $utf8;
    }

    /**
     * Normalizes a custom delimiter list to `array<int, true>`.
     *
     * @param  list<int>|array<int, true>  $custom  Either a list of code points or a prebuilt `cp => true` map (truthy values)
     *
     * @return array<int, true> Map suitable for the delimiter-stripping phase
     */
    private static function normalizeDelimiterScalarMap(array $custom): array
    {
        $out = [];
        if ($custom === []) {
            return $out;
        }
        $isList = \array_is_list($custom);
        foreach ($custom as $k => $v) {
            if ($isList) {
                if (\is_int($v)) {
                    $out[$v] = true;
                }
            } elseif (\is_int($k) && $v) {
                $out[$k] = true;
            }
        }

        return $out;
    }

    /**
     * Removes scalars whose code points appear in `$this->delimiterScalarsToStrip`.
     *
     * @param  string  $s  UTF-8 string (caller must ensure valid UTF-8 when this method is used from {@see isValid})
     *
     * @return string|null Stripped string, or `null` if `mb_str_split` / `mb_ord` fails
     */
    private function stripDelimiterScalars(string $s): ?string
    {
        if ($this->delimiterScalarsToStrip === []) {
            return $s;
        }
        $chars = \mb_str_split($s);
        if ($chars === false) {
            return null;
        }
        $out = '';
        foreach ($chars as $ch) {
            $cp = \mb_ord($ch, 'UTF-8');
            if ($cp === false) {
                return null;
            }
            if (!isset($this->delimiterScalarsToStrip[$cp])) {
                $out .= $ch;
            }
        }

        return $out;
    }

    /**
     * Applies longest-`from`-first replacements from `$this->normalizationRulesLongestFirst`.
     *
     * @param  string  $s  UTF-8 string
     *
     * @return string Transformed UTF-8 string
     */
    private function applyNormalizationRules(string $s): string
    {
        if ($this->normalizationRulesLongestFirst === []) {
            return $s;
        }
        $len = \mb_strlen($s, 'UTF-8');
        $out = '';
        for ($i = 0; $i < $len; ) {
            $matched = false;
            foreach ($this->normalizationRulesLongestFirst as $rule) {
                $from = $rule['from'];
                $fromLen = \mb_strlen($from, 'UTF-8');
                if ($fromLen === 0) {
                    continue;
                }
                if (\mb_substr($s, $i, $fromLen, 'UTF-8') === $from) {
                    $out .= $rule['to'];
                    $i += $fromLen;
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                $out .= \mb_substr($s, $i, 1, 'UTF-8');
                $i += 1;
            }
        }

        return $out;
    }

    /**
     * @param  string  $s  UTF-8 string to check scalar-by-scalar
     *
     * @return bool `true` if every scalar in `$s` is allowed by `$this->inventory`
     */
    private function allScalarsAllowed(string $s): bool
    {
        $chars = \mb_str_split($s);
        if ($chars === false) {
            return false;
        }
        foreach ($chars as $ch) {
            $cp = \mb_ord($ch, 'UTF-8');
            if ($cp === false || !$this->inventory->isScalarAllowed($cp)) {
                return false;
            }
        }

        return true;
    }
}
