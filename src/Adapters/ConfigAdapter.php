<?php

declare(strict_types=1);

namespace PhpMcp\Laravel\Server\Adapters;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use PhpMcp\Server\Contracts\ConfigurationRepositoryInterface;

/**
 * Adapts Laravel's Config Repository to the interface needed by php-mcp.
 */
class ConfigAdapter implements ConfigurationRepositoryInterface
{
    public function __construct(protected ConfigRepository $config) {}

    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config->get($key, $default);
    }

    /**
     * {@inheritdoc}
     * Note: Persisting config changes depends on Laravel's setup
     * (e.g., packages like `config-writer` might be needed for `set` to persist).
     * This adapter mainly reads config and supports runtime changes.
     */
    public function set(string $key, mixed $value): void
    {
        $this->config->set($key, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return $this->config->has($key);
    }
}
