<?php

declare(strict_types=1);

namespace JoshDaugherty\IpaUnicodeInventory\Tests;

use JoshDaugherty\IpaUnicodeInventory\Inventory;
use PHPUnit\Framework\TestCase;

final class InventoryGoldenStringsTest extends TestCase
{
    private static function inventoryPath(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'inventory.json';
    }

    public function testTeshDigraphLetterIsAllowed(): void
    {
        $inv = Inventory::fromDisk(self::inventoryPath());
        $this->assertTrue($inv->isScalarAllowed(0x02A7), 'Latin letter tesh (ʧ)');
    }

    public function testLatinWithCombiningAcuteIsAllowedPerScalar(): void
    {
        $inv = Inventory::fromDisk(self::inventoryPath());
        $s = "a\u{0301}";
        $this->assertSame(2, \mb_strlen($s, 'UTF-8'));
        $this->assertTrue($inv->isScalarAllowed(0x0061));
        $this->assertTrue($inv->isScalarAllowed(0x0301));
    }

    public function testDelimiterScalarsAreListed(): void
    {
        $inv = Inventory::fromDisk(self::inventoryPath());
        $this->assertTrue($inv->isScalarAllowed(0x005B), 'left square bracket');
        $this->assertTrue($inv->isScalarAllowed(0x002F), 'solidus');
    }

    public function testSurrogateAndOutOfRangeRejected(): void
    {
        $inv = Inventory::fromDisk(self::inventoryPath());
        $this->assertFalse($inv->isScalarAllowed(0xD800));
        $this->assertFalse($inv->isScalarAllowed(0x110000));
    }

    public function testNonInventoryScalarRejected(): void
    {
        $inv = Inventory::fromDisk(self::inventoryPath());
        $this->assertFalse($inv->isScalarAllowed(0x2603), 'snowman not in policy');
    }
}
