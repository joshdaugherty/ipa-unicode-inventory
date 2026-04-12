<?php

declare(strict_types=1);

namespace JoshDaugherty\IpaUnicodeInventory;

/**
 * Loads canonical JSON from disk. Does not validate against JSON Schema (use a schema validator in your app if needed).
 */
final class InventoryLoader
{
    /**
     * @return array{meta: array<string, mixed>, code_points: list<array<string, mixed>>, $schema?: string}
     *
     * @throws \JsonException
     */
    public static function loadInventory(?string $path = null): array
    {
        $path ??= Resources::inventoryJsonPath();
        $data = self::decodeJsonFile($path);
        if (!isset($data['meta'], $data['code_points']) || !is_array($data['meta']) || !is_array($data['code_points'])) {
            throw new \RuntimeException('inventory.json: missing meta or code_points');
        }

        return $data;
    }

    /**
     * @return array{meta: array<string, mixed>, rules: list<array<string, mixed>>, $schema?: string}
     *
     * @throws \JsonException
     */
    public static function loadNormalization(?string $path = null): array
    {
        $path ??= Resources::normalizationJsonPath();
        $data = self::decodeJsonFile($path);
        if (!isset($data['meta'], $data['rules']) || !is_array($data['meta']) || !is_array($data['rules'])) {
            throw new \RuntimeException('normalization.json: missing meta or rules');
        }

        return $data;
    }

    /**
     * Integer code point => true map for fast in_array-style checks.
     *
     * @return array<int, true>
     *
     * @throws \JsonException
     */
    public static function codePointLookup(?string $inventoryPath = null): array
    {
        $doc = self::loadInventory($inventoryPath);
        $map = [];
        foreach ($doc['code_points'] as $row) {
            if (!isset($row['cp']) || !is_int($row['cp'])) {
                continue;
            }
            $map[$row['cp']] = true;
        }

        return $map;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws \JsonException
     */
    private static function decodeJsonFile(string $path): array
    {
        if (!is_readable($path)) {
            throw new \RuntimeException('File not readable: ' . $path);
        }
        $json = file_get_contents($path);
        if ($json === false) {
            throw new \RuntimeException('Could not read: ' . $path);
        }

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }
}
