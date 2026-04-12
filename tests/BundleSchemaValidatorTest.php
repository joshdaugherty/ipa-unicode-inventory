<?php

declare(strict_types=1);

namespace JoshDaugherty\IpaUnicodeInventory\Tests;

use JoshDaugherty\IpaUnicodeInventory\BundleSchemaValidator;
use JoshDaugherty\IpaUnicodeInventory\InventoryLoader;
use PHPUnit\Framework\TestCase;

/**
 * Tests {@see BundleSchemaValidator} against the repository’s bundled `data/*.json` and schema wrappers.
 *
 * Assumes **`justinrainbow/json-schema`** is installed (this package lists it under **`require-dev`**).
 */
final class BundleSchemaValidatorTest extends TestCase
{
    public function testPackageAvailableInDev(): void
    {
        $this->assertTrue(BundleSchemaValidator::isAvailable());
    }

    public function testBundledInventoryPassesSchema(): void
    {
        $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'inventory.json';
        $data = InventoryLoader::loadInventory($path, false);
        BundleSchemaValidator::assertInventoryDocumentValid($data);
        $this->addToAssertionCount(1);
    }

    public function testBundledNormalizationPassesSchema(): void
    {
        $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'normalization.json';
        $data = InventoryLoader::loadNormalization($path, false);
        BundleSchemaValidator::assertNormalizationDocumentValid($data);
        $this->addToAssertionCount(1);
    }

    public function testInvalidInventoryThrows(): void
    {
        $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'inventory.json';
        $data = InventoryLoader::loadInventory($path, false);
        unset($data['meta']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JSON Schema validation failed');
        BundleSchemaValidator::assertInventoryDocumentValid($data);
    }
}
