<?php

namespace Kantui\Contracts;

/**
 * Contract for context management.
 *
 * Defines the interface for managing application contexts,
 * including configuration loading and path resolution.
 */
interface ContextInterface
{
    /**
     * Load the configuration for the context.
     *
     * @return static Returns this instance for method chaining
     */
    public function loadConfig(): static;

    /**
     * Get the configuration for the context or the default value.
     *
     * @param  string|null  $key  The configuration key to retrieve
     * @param  mixed  $default  The default value if key is not found
     * @return mixed The configuration value or collection
     */
    public function config(?string $key = null, mixed $default = null): mixed;

    /**
     * Get the timezone for the context.
     *
     * @return string The timezone string
     */
    public function getTimezone(): string;

    /**
     * Make path relative to the context directory.
     *
     * @param  string  $path  The relative path within the context directory
     * @return string The absolute path to the context resource
     */
    public function path(string $path = ''): string;

    /**
     * Ensure the context has default files present for writing.
     */
    public function ensureDefaultFiles(): void;
}
