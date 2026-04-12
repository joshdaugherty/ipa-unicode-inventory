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
}
