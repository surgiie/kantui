<?php

namespace Kantui\Support;

use Illuminate\Support\Collection;
use RuntimeException;

use function Kantui\kantui_path;

class Context
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
     **/
    protected Collection $config;

    public function __construct(protected string $name)
    {
        $this->loadConfig();
    }

    /**
     * Get the context name when the context is used as a string.
     */
    public function __toString(): string
    {
        return $this->name;
    }

    /**
     * Load the configuration for the context.
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
            $config = json_decode($contents, true);
            // global config
        } elseif (is_file($configPath = kantui_path('/'.self::CONFIG_FILE_NAME))) {
            $contents = file_get_contents($configPath);
            if ($contents === false) {
                throw new RuntimeException("Failed to read config file: $configPath");
            }
            $config = json_decode($contents, true);
        }

        if (is_null($config)) {
            $message = json_last_error_msg();
            throw new RuntimeException("Invalid json: $message");
        }

        $this->config = Collection::make($config);

        return $this;
    }

    /**
     * Get the configuration for the context or the default value.
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
     */
    public function getTimezone(): string
    {
        return $this->config('timezone', date_default_timezone_get());
    }

    /**
     * Make path relative to the context directory.
     */
    public function path(string $path = ''): string
    {
        return rtrim(kantui_path("contexts/{$this->name}/{$path}", '/'));
    }

    /**
     * Ensure the context has default files present for writing.
     */
    public function ensureDefaults(): void
    {
        if (! is_dir($contextPath = $this->path())) {
            $old = umask(0);
            $result = @mkdir($contextPath, 0774, true);
            umask($old);

            if (! $result) {
                throw new RuntimeException("Failed to create context directory: $contextPath");
            }
        }

        if (! is_file($this->path(self::DATA_FILE_NAME))) {
            $result = file_put_contents(
                $this->path(self::DATA_FILE_NAME),
                json_encode(DataManager::defaultData(), JSON_PRETTY_PRINT)
            );

            if ($result === false) {
                throw new RuntimeException('Failed to create default data file.');
            }
        }
    }
}
