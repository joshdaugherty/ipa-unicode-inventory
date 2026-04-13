<?php

declare(strict_types=1);

namespace JoshDaugherty\IpaUnicodeInventory;

/**
 * Paths to canonical files shipped with this package (Composer vendor tree).
 *
 * The default {@see inventoryJsonPath()} is the **corpus_inclusive** profile (`data/inventory.json`).
 * The **phonetic_strict** subset omits delimiter rows and ASCII space/digits; see `data/inventory.phonetic-strict.json`.
 */
final class Resources
{
    public static function basePath(): string
    {
        return dirname(__DIR__);
    }

    public static function inventoryJsonPath(): string
    {
        return self::inventoryJsonPathForProfile(PolicyProfile::CORPUS_INCLUSIVE);
    }

    /**
     * @param  string  $profileId  One of {@see PolicyProfile}::* constants
     *
     * @throws \InvalidArgumentException if the profile is not bundled
     */
    public static function inventoryJsonPathForProfile(string $profileId): string
    {
        $base = self::basePath() . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR;

        return match ($profileId) {
            PolicyProfile::CORPUS_INCLUSIVE => "{$base}inventory.json",
            PolicyProfile::PHONETIC_STRICT => "{$base}inventory.phonetic-strict.json",
            default => throw new \InvalidArgumentException("Unknown policy profile: {$profileId}"),
        };
    }

    public static function normalizationJsonPath(): string
    {
        return self::basePath() . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'normalization.json';
    }

    public static function schemaDirectory(): string
    {
        return self::basePath() . DIRECTORY_SEPARATOR . 'schema';
    }
}
