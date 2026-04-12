<?php

declare(strict_types=1);

namespace JoshDaugherty\IpaUnicodeInventory;

/**
 * In-memory allowlist of Unicode scalar values (code points) taken from `inventory.json`.
 *
 * The map is **`array<int, true>`** for O(1) membership checks. This class does not validate
 * against JSON Schema; use {@see InventoryLoader::loadInventory} with **`$validateSchema`** or
 * {@see BundleSchemaValidator} when you need schema validation.
 *
 * **Semantics:** checks are per **Unicode scalar** (code point), not grapheme cluster. Surrogate
 * code units and integers outside **U+0000..U+10FFFF** are always rejected by {@see isScalarAllowed}.
 */
final class Inventory
{
    /** @var array<int, true> Map from allowed scalar value to `true` */
    private array $allowed;

    /**
     * @param  array<int, true>  $allowedScalars  Allowlist map, typically from {@see InventoryLoader::codePointLookup}
     */
    public function __construct(array $allowedScalars)
    {
        $this->allowed = $allowedScalars;
    }

    /**
     * Builds an {@see Inventory} by loading `inventory.json` and deriving the code-point map.
     *
     * @param  string|null  $inventoryJsonPath  Absolute or relative path to `inventory.json`, or `null` to use {@see Resources::inventoryJsonPath}
     * @param  bool  $validateSchema  When `true`, validate the document against bundled JSON Schema (requires `justinrainbow/json-schema`)
     *
     * @return self Instance whose allowlist reflects the loaded file
     *
     * @throws \JsonException if JSON decoding fails (`JSON_THROW_ON_ERROR`)
     * @throws \RuntimeException if `inventory.json` is structurally invalid, unreadable, or schema validation fails when `$validateSchema` is `true`
     */
    public static function fromDisk(?string $inventoryJsonPath = null, bool $validateSchema = false): self
    {
        return new self(InventoryLoader::codePointLookup($inventoryJsonPath, $validateSchema));
    }

    /**
     * Whether `$cp` is listed in the loaded inventory (and is a valid non-surrogate scalar).
     *
     * @param  int  $cp  Unicode code point to test
     *
     * @return bool `true` if `$cp` is in the allowlist; `false` if not listed, if `$cp` is a surrogate (**U+D800..U+DFFF**), or if `$cp` is outside **0..0x10FFFF**
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
