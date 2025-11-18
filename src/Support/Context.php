<?php

namespace Kantui\Support;

use Illuminate\Support\Collection;
use Kantui\Contracts\ContextInterface;
use RuntimeException;

use function Kantui\kantui_path;

/**
 * Manages application context and configuration.
 *
 * The Context class handles loading and managing configuration settings,
 * ensuring required directories and files exist, and providing access to
 * context-specific paths and settings.
 */
class Context implements ContextInterface
{
    /**
     * The filename for the configuration file.
     */
    private const CONFIG_FILE_NAME = 'config.json';

    /**
     * The filename for the data file.
     */
    private const DATA_FILE_NAME = 'data.json';

    /**
     * The configuration for the context.
     */
    protected Collection $config;

    /**
     * Create a new Context instance.
     *
     * @param  string  $name  The context name (must contain only alphanumeric, hyphens, underscores)
     *
     * @throws RuntimeException if the context name is invalid
     */
    public function __construct(protected string $name)
    {
        $this->validateContextName($name);
        $this->loadConfig();
    }

    /**
     * Validate that the context name is safe and contains only allowed characters.
     *
     * @throws RuntimeException if the context name is invalid
     */
    private function validateContextName(string $name): void
    {
        if (empty($name)) {
            throw new RuntimeException('Context name cannot be empty');
        }

        if (! preg_match('/^[a-zA-Z0-9_-]+$/', $name)) {
            throw new RuntimeException(
                'Context name can only contain alphanumeric characters, hyphens, and underscores'
            );
        }

        if (str_contains($name, '..')) {
            throw new RuntimeException('Context name cannot contain path traversal sequences');
        }
    }

    /**
     * Get the context name when the context is used as a string.
     *
     * @return string The context name
     */
    public function __toString(): string
    {
        return $this->name;
    }

    /**
     * Load the configuration for the context.
     *
     * Searches for and loads configuration from either the context-specific
     * config file or the global config file, whichever is found first.
     *
     * @return static Returns this instance for method chaining
     *
     * @throws RuntimeException if config file cannot be read or contains invalid JSON
     */
    public function loadConfig(): static
    {
        $config = [];
        $configPath = null;

        // context config
        if (is_file($configPath = $this->path('/'.self::CONFIG_FILE_NAME))) {
            $contents = file_get_contents($configPath);
            if ($contents === false) {
                throw new RuntimeException("Failed to read config file: $configPath");
            }
            $config = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            // global config
        } elseif (is_file($configPath = kantui_path('/'.self::CONFIG_FILE_NAME))) {
            $contents = file_get_contents($configPath);
            if ($contents === false) {
                throw new RuntimeException("Failed to read config file: $configPath");
            }
            $config = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        }

        // Validate configuration
        if (! empty($config)) {
            $config = ConfigValidator::validate($config);
        }

        $this->config = Collection::make($config);

        return $this;
    }

    /**
     * Get the configuration for the context or the default value.
     *
     * Returns the entire configuration collection if no key is provided,
     * or a specific configuration value if a key is provided.
     *
     * @param  string|null  $key  The configuration key to retrieve
     * @param  mixed  $default  The default value if key is not found
     * @return mixed The configuration value or collection
     */
    public function config(?string $key = null, mixed $default = null): mixed
    {
        if (is_null($key)) {
            return $this->config;
        }

        return $this->config->get($key, $default);
    }

    /**
     * Get the timezone for the context.
     *
     * Returns the configured timezone or the system default timezone.
     *
     * @return string The timezone string (e.g., 'America/New_York')
     */
    public function getTimezone(): string
    {
        return $this->config('timezone', date_default_timezone_get());
    }

    /**
     * Make path relative to the context directory.
     *
     * Constructs an absolute path within the context directory.
     *
     * @param  string  $path  The relative path within the context directory
     * @return string The absolute path to the context resource
     */
    public function path(string $path = ''): string
    {
        return rtrim(kantui_path("contexts/{$this->name}/{$path}", '/'));
    }

    /**
     * Ensure the context has default files present for writing.
     *
     * Creates the context directory if it doesn't exist and initializes
     * the data file with default empty todo collections.
     *
     * @throws RuntimeException if directory or file creation fails
     */
    public function ensureDefaults(): void
    {
        if (! is_dir($contextPath = $this->path())) {
            $old = umask(0);
            $result = @mkdir($contextPath, 0755, true);
            umask($old);

            if (! $result) {
                throw new RuntimeException("Failed to create context directory: $contextPath");
            }
        }

        if (! is_file($this->path(self::DATA_FILE_NAME))) {
            $result = file_put_contents(
                $this->path(self::DATA_FILE_NAME),
                json_encode(DataManager::defaultData(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
            );

            if ($result === false) {
                throw new RuntimeException('Failed to create default data file.');
            }
        }
    }
}
