<?php

declare(strict_types=1);

namespace JoshDaugherty\IpaUnicodeInventory;

/**
 * Validates UTF-8 transcription text per Unicode scalar against {@see Inventory}.
 *
 * Pipeline (in order): optional delimiter stripping, `normalization.json` rules (longest `from`
 * first), optional legacy ASCII normalization matching
 * {@link https://github.com/wikimedia/mediawiki-libs-IPAValidator Wikimedia IPAValidator}
 * ('→ˈ, :→ː, ,→ˌ), then per-scalar allowlist checks.
 *
 * Does not implement Wikimedia `stripRegex`, Google mode, or grapheme-cluster semantics.
 *
 * Delimiter stripping runs before Wikimedia ASCII normalization. ASCII apostrophe (U+0027) is an
 * inventory delimiter; with `STRIP_DELIMITERS_INVENTORY` it is removed before `'`→ˈ can apply.
 * Use `STRIP_DELIMITERS_NONE` or `STRIP_DELIMITERS_CUSTOM` if you need that legacy mapping.
 */
final class TranscriptionValidator
{
    /** Strip no code points before normalize/validate. */
    public const STRIP_DELIMITERS_NONE = 0;

    /** Strip scalars whose inventory category is "delimiter". */
    public const STRIP_DELIMITERS_INVENTORY = 1;

    /** Strip scalars listed in {@see fromDisk()} as `$customDelimiterScalars`. */
    public const STRIP_DELIMITERS_CUSTOM = 2;

    private readonly Inventory $inventory;

    /** @var array<int, true> */
    private readonly array $delimiterScalarsToStrip;

    /** @var list<array{from: string, to: string}> */
    private readonly array $normalizationRulesLongestFirst;

    private readonly bool $wikimediaLegacyAsciiNormalization;

    /**
     * @param array<int, true> $delimiterScalarsToStrip Code points removed before normalization; empty = strip none
     * @param list<array{from: string, to: string}> $normalizationRulesLongestFirst Rules from `normalization.json`, sorted longest `from` first
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
     * @param int $delimiterStripMode one of {@see STRIP_DELIMITERS_NONE}, {@see STRIP_DELIMITERS_INVENTORY}, {@see STRIP_DELIMITERS_CUSTOM}
     * @param list<int>|array<int, true>|null $customDelimiterScalars Used when mode is {@see STRIP_DELIMITERS_CUSTOM} (list of ints or cp=>true map)
     *
     * @throws \InvalidArgumentException
     * @throws \JsonException
     */
    public static function fromDisk(
        ?string $inventoryJsonPath = null,
        ?string $normalizationJsonPath = null,
        int $delimiterStripMode = self::STRIP_DELIMITERS_INVENTORY,
        ?array $customDelimiterScalars = null,
        bool $applyNormalizationJson = true,
        bool $wikimediaLegacyAscii = false,
    ): self {
        $inventory = Inventory::fromDisk($inventoryJsonPath);
        $stripMap = match ($delimiterStripMode) {
            self::STRIP_DELIMITERS_NONE => [],
            self::STRIP_DELIMITERS_INVENTORY => InventoryLoader::delimiterScalarSet($inventoryJsonPath),
            self::STRIP_DELIMITERS_CUSTOM => self::normalizeDelimiterScalarMap($customDelimiterScalars ?? []),
            default => throw new \InvalidArgumentException('Invalid delimiter strip mode: ' . $delimiterStripMode),
        };

        $rules = [];
        if ($applyNormalizationJson) {
            $doc = InventoryLoader::loadNormalization($normalizationJsonPath);
            $rules = self::sortNormalizationRules($doc['rules']);
        }

        return new self($inventory, $stripMap, $rules, $wikimediaLegacyAscii);
    }

    /**
     * Whether `$text` is well-formed UTF-8 and every scalar survives the pipeline and is allowed.
     *
     * Empty string is valid. Invalid UTF-8 returns false.
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
     * @param list<array<string, mixed>> $rules Raw `rules` array from normalization.json
     *
     * @return list<array{from: string, to: string}>
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
     * Same character map as Wikimedia `Validator::normalizeIPA` (ASCII stress/length shortcuts only).
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
     * @param list<int>|array<int, true> $custom
     *
     * @return array<int, true>
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
