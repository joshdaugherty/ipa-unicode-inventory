<?php

declare(strict_types=1);

namespace JoshDaugherty\IpaUnicodeInventory\Tests;

use JoshDaugherty\IpaUnicodeInventory\InventoryLoader;
use PHPUnit\Framework\TestCase;

/**
 * Tests {@see InventoryLoader} with **`$validateSchema === true`** (JSON Schema via `justinrainbow/json-schema`).
 */
final class InventoryLoaderStrictSchemaTest extends TestCase
{
    public function testLoadInventoryWithStrictSchema(): void
    {
        $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'inventory.json';
        $doc = InventoryLoader::loadInventory($path, true);
        $this->assertArrayHasKey('meta', $doc);
        $this->assertArrayHasKey('code_points', $doc);
    }

    public function testLoadNormalizationWithStrictSchema(): void
    {
        $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'normalization.json';
        $doc = InventoryLoader::loadNormalization($path, true);
        $this->assertArrayHasKey('meta', $doc);
        $this->assertArrayHasKey('rules', $doc);
    }
}
