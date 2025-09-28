<?php

namespace Kantui\Support;

use Illuminate\Support\Collection;
use RuntimeException;

use function Kantui\kantui_path;

class Context
{
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
        // context config
        if (is_file($configPath = $this->path('/config.json'))) {
            $config = json_decode(file_get_contents($configPath), true);
            // global config
        } elseif (is_file($configPath = kantui_path('/config.json'))) {
            $config = json_decode(file_get_contents($configPath), true);
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
            @mkdir($contextPath, 0774, true);
            umask($old);
        }

        if (! is_file($this->path('data.json'))) {
            file_put_contents(
                $this->path('data.json'),
                json_encode(DataManager::defaultData(), JSON_PRETTY_PRINT)
            );
        }
    }
}
