<?php

declare(strict_types=1);

namespace JoshDaugherty\IpaUnicodeInventory\Tests;

use JoshDaugherty\IpaUnicodeInventory\InventoryLoader;
use JoshDaugherty\IpaUnicodeInventory\PolicyProfile;
use JoshDaugherty\IpaUnicodeInventory\Resources;
use PHPUnit\Framework\TestCase;

final class PolicyProfileTest extends TestCase
{
    public function testPhoneticStrictIsSubsetOfCorpus(): void
    {
        $corpus = InventoryLoader::codePointLookup(Resources::inventoryJsonPathForProfile(PolicyProfile::CORPUS_INCLUSIVE));
        $strict = InventoryLoader::codePointLookup(Resources::inventoryJsonPathForProfile(PolicyProfile::PHONETIC_STRICT));
        $this->assertLessThan(\count($corpus), \count($strict));
        foreach ($strict as $cp => $_) {
            $this->assertArrayHasKey($cp, $corpus);
        }
    }

    public function testPhoneticStrictHasNoDelimiterCategory(): void
    {
        $path = Resources::inventoryJsonPathForProfile(PolicyProfile::PHONETIC_STRICT);
        $doc = InventoryLoader::loadInventory($path);
        foreach ($doc['code_points'] as $row) {
            $this->assertNotSame('delimiter', $row['category'] ?? null);
        }
    }

    public function testPhoneticStrictMeta(): void
    {
        $doc = InventoryLoader::loadInventory(Resources::inventoryJsonPathForProfile(PolicyProfile::PHONETIC_STRICT));
        $this->assertSame('phonetic_strict', $doc['meta']['profile_id']);
        $this->assertSame('ipa-extipa-phonetic-strict', $doc['meta']['policy_id']);
    }

    public function testUnknownProfileThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Resources::inventoryJsonPathForProfile('nope');
    }
}
