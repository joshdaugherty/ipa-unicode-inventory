<?php

declare(strict_types=1);

namespace JoshDaugherty\IpaUnicodeInventory;

/**
 * Stable ids for bundled policy inventories; use with {@see Resources::inventoryJsonPathForProfile()}.
 */
final class PolicyProfile
{
    public const CORPUS_INCLUSIVE = 'corpus_inclusive';

    public const PHONETIC_STRICT = 'phonetic_strict';
}
