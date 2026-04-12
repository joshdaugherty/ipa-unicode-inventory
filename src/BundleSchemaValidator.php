<?php

declare(strict_types=1);

namespace JoshDaugherty\IpaUnicodeInventory;

/**
 * Validates decoded inventory and normalization JSON documents against the bundled JSON Schema wrappers.
 *
 * Wrappers live under {@see Resources::schemaDirectory()} (`inventory.wrapper.schema.json`,
 * `normalization.wrapper.schema.json`) and reference draft **2020-12** sub-schemas via `$ref`.
 *
 * Validation is implemented with the **optional** Composer package `justinrainbow/json-schema`.
 * Call {@see isAvailable()} before {@see assertInventoryDocumentValid} or
 * {@see assertNormalizationDocumentValid} if the package may be absent at runtime.
 */
final class BundleSchemaValidator
{
    /**
     * Whether `justinrainbow/json-schema` is installed and {@see \JsonSchema\Validator} is loadable.
     *
     * @return bool `true` if strict validation can run; `false` if consumers must install the suggested package
     */
    public static function isAvailable(): bool
    {
        return \class_exists(\JsonSchema\Validator::class);
    }

    /**
     * Asserts that `$data` matches the full inventory document schema (`meta` + `code_points` and wrapper rules).
     *
     * @param  array<string, mixed>  $data  Decoded root object from `inventory.json`, typically `json_decode(..., true)`
     *
     * @throws \RuntimeException if `justinrainbow/json-schema` is not installed, the wrapper file is missing or unreadable, or validation fails (message lists JSON Pointer paths and constraint messages)
     * @throws \JsonException if converting `$data` to JSON for the validator fails (`JSON_THROW_ON_ERROR`)
     */
    public static function assertInventoryDocumentValid(array $data): void
    {
        $wrapper = Resources::schemaDirectory() . DIRECTORY_SEPARATOR . 'inventory.wrapper.schema.json';
        self::assertDocumentValid($data, $wrapper, 'inventory');
    }

    /**
     * Asserts that `$data` matches the full normalization document schema (`meta` + `rules` and wrapper rules).
     *
     * @param  array<string, mixed>  $data  Decoded root object from `normalization.json`, typically `json_decode(..., true)`
     *
     * @throws \RuntimeException if `justinrainbow/json-schema` is not installed, the wrapper file is missing or unreadable, or validation fails (message lists JSON Pointer paths and constraint messages)
     * @throws \JsonException if converting `$data` to JSON for the validator fails (`JSON_THROW_ON_ERROR`)
     */
    public static function assertNormalizationDocumentValid(array $data): void
    {
        $wrapper = Resources::schemaDirectory() . DIRECTORY_SEPARATOR . 'normalization.wrapper.schema.json';
        self::assertDocumentValid($data, $wrapper, 'normalization');
    }

    /**
     * Validates `$data` against the JSON Schema at `$wrapperAbsolutePath` using a `file://` `$ref` URI.
     *
     * @param  array<string, mixed>  $data  Same shape as passed to the public `assert*DocumentValid` methods
     * @param  string  $wrapperAbsolutePath  Filesystem path to a `*.wrapper.schema.json` file (may be non-normalized; resolved with `realpath`)
     * @param  string  $label  Short label used only in error messages (e.g. `inventory`, `normalization`)
     *
     * @throws \RuntimeException if the validator package is missing, `$wrapperAbsolutePath` cannot be resolved or read, or the document does not validate
     * @throws \JsonException if `json_encode` / `json_decode` round-trip for the validator payload fails
     */
    private static function assertDocumentValid(array $data, string $wrapperAbsolutePath, string $label): void
    {
        if (!self::isAvailable()) {
            throw new \RuntimeException(
                'Strict JSON Schema validation requires justinrainbow/json-schema. Run: composer require justinrainbow/json-schema',
            );
        }
        $resolved = \realpath($wrapperAbsolutePath);
        if ($resolved === false || !\is_readable($resolved)) {
            throw new \RuntimeException('JSON Schema wrapper not found: ' . $wrapperAbsolutePath);
        }

        $uri = 'file://' . \str_replace(DIRECTORY_SEPARATOR, '/', $resolved);
        if (\str_starts_with($uri, 'file://') && !\str_starts_with($uri, 'file:///') && DIRECTORY_SEPARATOR === '\\') {
            $uri = 'file:///' . \substr($uri, 7);
        }

        $payload = \json_decode(\json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), false, 512, JSON_THROW_ON_ERROR);

        $validator = new \JsonSchema\Validator();
        $validator->validate($payload, (object) ['$ref' => $uri]);

        if ($validator->isValid()) {
            return;
        }

        $lines = [];
        foreach ($validator->getErrors() as $error) {
            $pointer = $error['pointer'] ?? '';
            $message = $error['message'] ?? '';
            $lines[] = ($pointer !== '' ? $pointer . ': ' : '') . $message;
        }

        throw new \RuntimeException(
            "JSON Schema validation failed for {$label}.json:\n" . \implode("\n", $lines),
        );
    }
}
