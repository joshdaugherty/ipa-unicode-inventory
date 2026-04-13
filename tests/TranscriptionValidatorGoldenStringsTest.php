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
}
