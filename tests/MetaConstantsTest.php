<?php

declare(strict_types=1);

namespace JoshDaugherty\IpaUnicodeInventory\Tests;

use JoshDaugherty\IpaUnicodeInventory\InventoryLoader;
use JoshDaugherty\IpaUnicodeInventory\MetaConstants;
use PHPUnit\Framework\TestCase;

final class MetaConstantsTest extends TestCase
{
    public function testMatchesBundledInventoryMeta(): void
    {
        $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'inventory.json';
        $meta = InventoryLoader::loadInventory($path)['meta'];
        $this->assertSame($meta['dataset_version'], MetaConstants::DATASET_VERSION);
        $this->assertSame($meta['policy_id'], MetaConstants::POLICY_ID);
        $this->assertSame($meta['schema_version'], MetaConstants::SCHEMA_VERSION);
    }
}
