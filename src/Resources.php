<?php

declare(strict_types=1);

namespace JoshDaugherty\IpaUnicodeInventory;

/**
 * Paths to canonical files shipped with this package (Composer vendor tree).
 */
final class Resources
{
    public static function basePath(): string
    {
        return dirname(__DIR__);
    }

    public static function inventoryJsonPath(): string
    {
        return self::basePath() . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'inventory.json';
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
