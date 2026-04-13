<?php

declare(strict_types=1);

namespace JoshDaugherty\IpaUnicodeInventory\Tests;

use JoshDaugherty\IpaUnicodeInventory\TranscriptionValidator;
use PHPUnit\Framework\TestCase;

final class TranscriptionValidatorGoldenStringsTest extends TestCase
{
    private static function inventoryPath(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'inventory.json';
    }

    private static function normalizationPath(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'normalization.json';
    }

    public function testEmptyStringIsValid(): void
    {
        $v = TranscriptionValidator::fromDisk(
            self::inventoryPath(),
            self::normalizationPath(),
            TranscriptionValidator::STRIP_DELIMITERS_NONE,
        );
        $this->assertTrue($v->isValid(''));
    }

    public function testTeshAndCombiningSequences(): void
    {
        $v = TranscriptionValidator::fromDisk(
            self::inventoryPath(),
            self::normalizationPath(),
            TranscriptionValidator::STRIP_DELIMITERS_NONE,
        );
        $this->assertTrue($v->isValid("\u{02A7}"));
        $this->assertTrue($v->isValid("a\u{0301}"));
    }

    public function testSlashesRemovedWithInventoryDelimiterStrip(): void
    {
        $v = TranscriptionValidator::fromDisk(
            self::inventoryPath(),
            self::normalizationPath(),
            TranscriptionValidator::STRIP_DELIMITERS_INVENTORY,
        );
        $this->assertTrue($v->isValid('/ʧ/'));
    }

    public function testBracketsPreservedWhenStripNone(): void
    {
        $v = TranscriptionValidator::fromDisk(
            self::inventoryPath(),
            self::normalizationPath(),
            TranscriptionValidator::STRIP_DELIMITERS_NONE,
        );
        $this->assertTrue($v->isValid('[ˈa]'));
    }

    public function testRightSingleQuoteNormalizesToModifierApostropheWhenNotStrippedFirst(): void
    {
        $v = TranscriptionValidator::fromDisk(
            self::inventoryPath(),
            self::normalizationPath(),
            TranscriptionValidator::STRIP_DELIMITERS_NONE,
            null,
            true,
            false,
        );
        $this->assertTrue($v->isValid("\u{2019}"));
    }

    public function testWikimediaAsciiApostropheToStressWhenStripNone(): void
    {
        $v = TranscriptionValidator::fromDisk(
            self::inventoryPath(),
            self::normalizationPath(),
            TranscriptionValidator::STRIP_DELIMITERS_NONE,
            null,
            true,
            true,
        );
        $this->assertTrue($v->isValid("ta'"));
    }

    public function testInvalidUtf8Rejected(): void
    {
        $v = TranscriptionValidator::fromDisk(
            self::inventoryPath(),
            self::normalizationPath(),
            TranscriptionValidator::STRIP_DELIMITERS_NONE,
        );
        $this->assertFalse($v->isValid("\xFF\xFE"));
    }

    public function testNonInventoryScalarRejected(): void
    {
        $v = TranscriptionValidator::fromDisk(
            self::inventoryPath(),
            self::normalizationPath(),
            TranscriptionValidator::STRIP_DELIMITERS_NONE,
        );
        $this->assertFalse($v->isValid("\u{2603}"));
    }

    public function testGoogleTtsRequiresWikimediaLegacyAsciiFromDisk(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Google TTS normalization requires Wikimedia legacy ASCII');
        TranscriptionValidator::fromDisk(
            self::inventoryPath(),
            self::normalizationPath(),
            TranscriptionValidator::STRIP_DELIMITERS_NONE,
            null,
            true,
            false,
            true,
        );
    }

    public function testGoogleTtsSuperscriptNMapsToAsciiN(): void
    {
        $out = TranscriptionValidator::applyGoogleTtsNormalization('tⁿ');
        $this->assertSame('tn', $out);
    }

    public function testGoogleTtsStripsCombiningMarks0300(): void
    {
        $out = TranscriptionValidator::applyGoogleTtsNormalization("e\u{0301}");
        $this->assertSame('e', $out);
    }

    public function testGoogleTtsRemovesParentheses(): void
    {
        $out = TranscriptionValidator::applyGoogleTtsNormalization('(a)');
        $this->assertSame('a', $out);
    }

    public function testIsValidWithGoogleTtsAfterCombiningStrip(): void
    {
        $v = TranscriptionValidator::fromDisk(
            self::inventoryPath(),
            self::normalizationPath(),
            TranscriptionValidator::STRIP_DELIMITERS_NONE,
            null,
            true,
            true,
            true,
        );
        $this->assertTrue($v->isValid("e\u{0301}"));
    }

    public function testIsValidBaseModeKeepsCombiningAcute(): void
    {
        $v = TranscriptionValidator::fromDisk(
            self::inventoryPath(),
            self::normalizationPath(),
            TranscriptionValidator::STRIP_DELIMITERS_NONE,
            null,
            true,
            true,
            false,
        );
        $this->assertTrue($v->isValid("e\u{0301}"));
    }

    public function testNonInventoryStillFailsUnderGoogleTts(): void
    {
        $v = TranscriptionValidator::fromDisk(
            self::inventoryPath(),
            self::normalizationPath(),
            TranscriptionValidator::STRIP_DELIMITERS_NONE,
            null,
            true,
            true,
            true,
        );
        $this->assertFalse($v->isValid("\u{2603}\u{0301}"));
    }

    public function testWikimediaSlashBracketStripValidatesNarrowTranscription(): void
    {
        $v = TranscriptionValidator::fromDisk(
            self::inventoryPath(),
            self::normalizationPath(),
            TranscriptionValidator::STRIP_DELIMITERS_WIKIMEDIA_SLASH_BRACKETS,
        );
        $this->assertTrue($v->isValid('/[ˈtɛst]/'));
    }

    public function testWikimediaSlashBracketStripPreservesAsciiApostropheForLegacyAscii(): void
    {
        $v = TranscriptionValidator::fromDisk(
            self::inventoryPath(),
            self::normalizationPath(),
            TranscriptionValidator::STRIP_DELIMITERS_WIKIMEDIA_SLASH_BRACKETS,
            null,
            true,
            true,
        );
        $this->assertTrue($v->isValid("/[ta']/"));
    }

    public function testWikimediaSlashBracketStripScalarSetMatchesPregReplace(): void
    {
        $map = TranscriptionValidator::wikimediaSlashBracketStripScalarSet();
        $samples = ['/foo[bar]/', 'ˈtɛst', '/[ˈtɛst]/', 'a/b[c]d', ''];
        foreach ($samples as $s) {
            $expected = \preg_replace('/[\/\[\]]/u', '', $s);
            $this->assertIsString($expected);
            $this->assertSame($expected, self::stripUtf8ByCpAllowlist($s, $map));
        }
    }

    public function testInvalidSegmentationModeThrowsFromDisk(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid segmentation mode');
        TranscriptionValidator::fromDisk(
            self::inventoryPath(),
            self::normalizationPath(),
            TranscriptionValidator::STRIP_DELIMITERS_NONE,
            null,
            true,
            false,
            false,
            99,
        );
    }

    public function testGraphemeModeRequiresIntlFromDisk(): void
    {
        if (TranscriptionValidator::graphemeSegmentationAvailable()) {
            $this->markTestSkipped('intl extension is loaded; cannot assert missing-ext behavior in this environment');
        }
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SEGMENT_GRAPHEME_CLUSTER requires the intl extension');
        TranscriptionValidator::fromDisk(
            self::inventoryPath(),
            self::normalizationPath(),
            TranscriptionValidator::STRIP_DELIMITERS_NONE,
            null,
            true,
            false,
            false,
            TranscriptionValidator::SEGMENT_GRAPHEME_CLUSTER,
        );
    }

    public function testGraphemeModeCombiningAcuteWithBaseIsOneClusterAndValid(): void
    {
        if (!TranscriptionValidator::graphemeSegmentationAvailable()) {
            $this->markTestSkipped('ext-intl required for SEGMENT_GRAPHEME_CLUSTER');
        }
        $s = "a\u{0301}";
        $iter = \IntlBreakIterator::createCharacterInstance(null);
        $iter->setText($s);
        $clusters = [];
        foreach ($iter->getPartsIterator() as $part) {
            if (\is_string($part) && $part !== '') {
                $clusters[] = $part;
            }
        }
        $this->assertCount(1, $clusters, 'NFC/NFD: base + U+0301 should be one ICU grapheme cluster');
        $this->assertSame($s, $clusters[0]);

        $v = TranscriptionValidator::fromDisk(
            self::inventoryPath(),
            self::normalizationPath(),
            TranscriptionValidator::STRIP_DELIMITERS_NONE,
            null,
            true,
            false,
            false,
            TranscriptionValidator::SEGMENT_GRAPHEME_CLUSTER,
        );
        $this->assertTrue($v->isValid($s));
    }

    public function testGraphemeModeAgreesWithScalarModeForSimpleIpa(): void
    {
        if (!TranscriptionValidator::graphemeSegmentationAvailable()) {
            $this->markTestSkipped('ext-intl required for SEGMENT_GRAPHEME_CLUSTER');
        }
        $scalar = TranscriptionValidator::fromDisk(
            self::inventoryPath(),
            self::normalizationPath(),
            TranscriptionValidator::STRIP_DELIMITERS_NONE,
        );
        $grapheme = TranscriptionValidator::fromDisk(
            self::inventoryPath(),
            self::normalizationPath(),
            TranscriptionValidator::STRIP_DELIMITERS_NONE,
            null,
            true,
            false,
            false,
            TranscriptionValidator::SEGMENT_GRAPHEME_CLUSTER,
        );
        foreach (["\u{02A7}", 'ˈtɛst', "e\u{0301}"] as $s) {
            $this->assertSame($scalar->isValid($s), $grapheme->isValid($s), 'string: ' . $s);
        }
    }

    public function testGraphemeModeNonInventoryScalarStillFails(): void
    {
        if (!TranscriptionValidator::graphemeSegmentationAvailable()) {
            $this->markTestSkipped('ext-intl required for SEGMENT_GRAPHEME_CLUSTER');
        }
        $v = TranscriptionValidator::fromDisk(
            self::inventoryPath(),
            self::normalizationPath(),
            TranscriptionValidator::STRIP_DELIMITERS_NONE,
            null,
            true,
            false,
            false,
            TranscriptionValidator::SEGMENT_GRAPHEME_CLUSTER,
        );
        $this->assertFalse($v->isValid("\u{2603}"));
    }

    /**
     * @param  array<int, true>  $stripThese  Code points to remove (same semantics as {@see TranscriptionValidator} delimiter strip)
     */
    private static function stripUtf8ByCpAllowlist(string $s, array $stripThese): string
    {
        if ($s === '') {
            return '';
        }
        $chars = \mb_str_split($s);
        if ($chars === false) {
            return '';
        }
        $out = '';
        foreach ($chars as $ch) {
            $cp = \mb_ord($ch, 'UTF-8');
            if ($cp === false || !isset($stripThese[$cp])) {
                $out .= $ch;
            }
        }

        return $out;
    }
}
