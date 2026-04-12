<?php

declare(strict_types=1);

namespace JoshDaugherty\IpaUnicodeInventory;

/**
 * Cached Unicode scalar allowlist from inventory.json (integer code point => true).
 *
 * Validation is per scalar (code point), not grapheme cluster. Surrogates and
 * out-of-range integers are never allowed.
 */
final class Inventory
{
    /** @var array<int, true> */
    private array $allowed;

    /**
     * @param array<int, true> $allowedScalars Map from code point to true (e.g. from {@see InventoryLoader::codePointLookup}).
     */
    public function __construct(array $allowedScalars)
    {
        $this->allowed = $allowedScalars;
    }

    /**
     * Load the bundled inventory (or the file at $inventoryJsonPath) and cache the allowlist in this instance.
     *
     * @throws \JsonException
     */
    public static function fromDisk(?string $inventoryJsonPath = null): self
    {
        return new self(InventoryLoader::codePointLookup($inventoryJsonPath));
    }

    /**
     * Whether this Unicode scalar is listed in the inventory.
     *
     * Returns false for surrogate scalars (U+D800–U+DFFF) and for $cp outside 0..0x10FFFF.
     */
    public function isScalarAllowed(int $cp): bool
    {
        if ($cp < 0 || $cp > 0x10FFFF) {
            return false;
        }
        if ($cp >= 0xD800 && $cp <= 0xDFFF) {
            return false;
        }

        return isset($this->allowed[$cp]);
    }
}
