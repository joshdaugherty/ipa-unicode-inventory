<?php

declare(strict_types=1);

namespace JoshDaugherty\IpaUnicodeInventory;

/**
 * Loads canonical `inventory.json` and `normalization.json` from disk as PHP arrays.
 *
 * By default only minimal structural checks are applied (`meta` + `code_points` / `rules`).
 * Set **`$validateSchema` to `true`** (and install **`justinrainbow/json-schema`**) to validate
 * full documents against bundled wrappers, or call {@see BundleSchemaValidator} on decoded data.
 */
final class InventoryLoader
{
    /**
     * Loads and returns the full inventory document.
     *
     * @param  string|null  $path  Path to `inventory.json`, or `null` for {@see Resources::inventoryJsonPath}
     * @param  bool  $validateSchema  When `true`, run {@see BundleSchemaValidator::assertInventoryDocumentValid} after structural checks
     *
     * @return array{meta: array<string, mixed>, code_points: list<array<string, mixed>>, $schema?: string} Decoded root object (assoc)
     *
     * @throws \JsonException if the file is not valid JSON (`JSON_THROW_ON_ERROR`)
     * @throws \RuntimeException if `meta` or `code_points` is missing or not an array, the file is unreadable, strict schema validation is requested but the validator package is missing, or schema validation fails
     */
    public static function loadInventory(?string $path = null, bool $validateSchema = false): array
    {
        $path ??= Resources::inventoryJsonPath();
        $data = self::decodeJsonFile($path);
        if (!isset($data['meta'], $data['code_points']) || !\is_array($data['meta']) || !\is_array($data['code_points'])) {
            throw new \RuntimeException('inventory.json: missing meta or code_points');
        }
        if ($validateSchema) {
            BundleSchemaValidator::assertInventoryDocumentValid($data);
        }

        return $data;
    }

    /**
     * Loads and returns the full normalization rules document.
     *
     * @param  string|null  $path  Path to `normalization.json`, or `null` for {@see Resources::normalizationJsonPath}
     * @param  bool  $validateSchema  When `true`, run {@see BundleSchemaValidator::assertNormalizationDocumentValid} after structural checks
     *
     * @return array{meta: array<string, mixed>, rules: list<array<string, mixed>>, $schema?: string} Decoded root object (assoc)
     *
     * @throws \JsonException if the file is not valid JSON (`JSON_THROW_ON_ERROR`)
     * @throws \RuntimeException if `meta` or `rules` is missing or not an array, the file is unreadable, strict schema validation is requested but the validator package is missing, or schema validation fails
     */
    public static function loadNormalization(?string $path = null, bool $validateSchema = false): array
    {
        $path ??= Resources::normalizationJsonPath();
        $data = self::decodeJsonFile($path);
        if (!isset($data['meta'], $data['rules']) || !\is_array($data['meta']) || !\is_array($data['rules'])) {
            throw new \RuntimeException('normalization.json: missing meta or rules');
        }
        if ($validateSchema) {
            BundleSchemaValidator::assertNormalizationDocumentValid($data);
        }

        return $data;
    }

    /**
     * Builds a set of allowed scalar values from `inventory.json` for fast membership tests.
     *
     * Rows without an integer `cp` field are skipped.
     *
     * @param  string|null  $inventoryPath  Path to `inventory.json`, or `null` for the bundled default
     * @param  bool  $validateSchema  When `true`, validate the document against JSON Schema before building the map
     *
     * @return array<int, true> Map from each listed code point to `true`
     *
     * @throws \JsonException if JSON decoding fails
     * @throws \RuntimeException if the inventory document fails structural or schema validation
     */
    public static function codePointLookup(?string $inventoryPath = null, bool $validateSchema = false): array
    {
        $doc = self::loadInventory($inventoryPath, $validateSchema);
        $map = [];
        foreach ($doc['code_points'] as $row) {
            if (!isset($row['cp']) || !\is_int($row['cp'])) {
                continue;
            }
            $map[$row['cp']] = true;
        }

        return $map;
    }

    /**
     * Collects code points whose `category` field in `inventory.json` equals **`delimiter`**.
     *
     * @param  string|null  $inventoryPath  Path to `inventory.json`, or `null` for the bundled default
     * @param  bool  $validateSchema  When `true`, validate the document against JSON Schema first
     *
     * @return array<int, true> Map from delimiter code point to `true`
     *
     * @throws \JsonException if JSON decoding fails
     * @throws \RuntimeException if the inventory document fails structural or schema validation
     */
    public static function delimiterScalarSet(?string $inventoryPath = null, bool $validateSchema = false): array
    {
        $doc = self::loadInventory($inventoryPath, $validateSchema);
        $set = [];
        foreach ($doc['code_points'] as $row) {
            if (!isset($row['cp'], $row['category']) || !\is_int($row['cp']) || !\is_string($row['category'])) {
                continue;
            }
            if ($row['category'] === 'delimiter') {
                $set[$row['cp']] = true;
            }
        }

        return $set;
    }

    /**
     * Reads a UTF-8 JSON file and decodes it to an associative array.
     *
     * @param  string  $path  Filesystem path to the JSON file
     *
     * @return array<string, mixed> Root decoded as associative array
     *
     * @throws \JsonException on invalid JSON (`JSON_THROW_ON_ERROR`)
     * @throws \RuntimeException if the path is not readable or `file_get_contents` fails
     */
    private static function decodeJsonFile(string $path): array
    {
        if (!\is_readable($path)) {
            throw new \RuntimeException('File not readable: ' . $path);
        }
        $json = \file_get_contents($path);
        if ($json === false) {
            throw new \RuntimeException('Could not read: ' . $path);
        }

        return \json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }
}
