<?php

namespace Kantui\Support;

use RuntimeException;

/**
 * Validates configuration values against a schema.
 *
 * This class ensures that configuration values loaded from JSON files
 * conform to expected types and constraints.
 */
class ConfigValidator
{
    /**
     * Configuration schema defining expected keys and their types.
     */
    private const SCHEMA = [
        'timezone' => 'string',
        'delete_done' => 'boolean',
        'human_readable_date' => 'boolean',
    ];

    /**
     * Validate configuration array against the schema.
     *
     * @param  array  $config  The configuration array to validate
     * @return array The validated configuration
     *
     * @throws RuntimeException if validation fails
     */
    public static function validate(array $config): array
    {
        foreach ($config as $key => $value) {
            if (! isset(self::SCHEMA[$key])) {
                throw new RuntimeException(
                    "Unknown configuration key: '{$key}'."
                );
            }

            $expectedType = self::SCHEMA[$key];
            $actualType = gettype($value);

            if (! self::isValidType($value, $expectedType)) {
                throw new RuntimeException(
                    "Configuration key '{$key}' must be of type {$expectedType}, ".
                    "but got {$actualType}"
                );
            }

            // Additional validation for specific keys
            if ($key === 'timezone') {
                self::validateTimezone($value);
            }
        }

        return $config;
    }

    /**
     * Check if a value matches the expected type.
     *
     * @param  mixed  $value  The value to check
     * @param  string  $expectedType  The expected type name
     * @return bool True if the value matches the expected type
     */
    private static function isValidType(mixed $value, string $expectedType): bool
    {
        return match ($expectedType) {
            'string' => is_string($value),
            'boolean' => is_bool($value),
            'integer' => is_int($value),
            'array' => is_array($value),
            default => false,
        };
    }

    /**
     * Validate that a timezone string is valid.
     *
     * @param  string  $timezone  The timezone string to validate
     *
     * @throws RuntimeException if the timezone is invalid
     */
    private static function validateTimezone(string $timezone): void
    {
        try {
            new \DateTimeZone($timezone);
        } catch (\Exception $e) {
            throw new RuntimeException(
                "Invalid timezone '{$timezone}': {$e->getMessage()}"
            );
        }
    }

    /**
     * Get the list of valid configuration keys.
     *
     * @return array Array of valid configuration key names
     */
    public static function getValidKeys(): array
    {
        return array_keys(self::SCHEMA);
    }

    /**
     * Get the configuration schema.
     *
     * @return array The configuration schema
     */
    public static function getSchema(): array
    {
        return self::SCHEMA;
    }
}
