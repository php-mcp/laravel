<?php

declare(strict_types=1);

namespace PhpMcp\Laravel\Support;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * MCP Context manager for storing request-specific information
 * that needs to be accessible during tool and resource execution.
 */
class McpContext
{
    /**
     * Storage for authentication context.
     *
     * @var array|null
     */
    protected static ?array $authContext = null;
    
    /**
     * Storage for request context.
     *
     * @var array|null
     */
    protected static ?array $requestContext = null;
    
    /**
     * Set authentication context.
     *
     * @param  array  $context
     * @return void
     */
    public static function setAuthContext(array $context): void
    {
        static::$authContext = $context;
    }
    
    /**
     * Get authentication context.
     *
     * @return array|null
     */
    public static function getAuthContext(): ?array
    {
        return static::$authContext;
    }
    
    /**
     * Clear authentication context.
     *
     * @return void
     */
    public static function clearAuthContext(): void
    {
        static::$authContext = null;
    }
    
    /**
     * Get the authenticated user from context.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
     public static function user()
    {
        return static::$authContext['user'] ?? null;
    }
    
    /**
     * Check if a user is authenticated in the current context.
     *
     * @return bool
     */
    public static function check(): bool
    {
        return static::user() !== null;
    }
    
    /**
     * Get the authentication guard used.
     *
     * @return string|null
     */
    public static function guard(): ?string
    {
        return static::$authContext['guard'] ?? null;
    }
    
    /**
     * Get the authentication token.
     *
     * @return mixed
     */
    public static function token()
    {
        return static::$authContext['token'] ?? null;
    }
    
    /**
     * Get request headers from context.
     *
     * @return array
     */
    public static function headers(): array
    {
        return static::$authContext['request_headers'] ?? [];
    }
    
    /**
     * Get a specific header value.
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    public static function header(string $key, $default = null)
    {
        return static::headers()[$key] ?? $default;
    }
    
    /**
     * Set request context.
     *
     * @param  array  $context
     * @return void
     */
    public static function setRequestContext(array $context): void
    {
        static::$requestContext = $context;
    }
    
    /**
     * Get request context.
     *
     * @return array|null
     */
    public static function getRequestContext(): ?array
    {
        return static::$requestContext;
    }
    
    /**
     * Clear request context.
     *
     * @return void
     */
    public static function clearRequestContext(): void
    {
        static::$requestContext = null;
    }
    
    /**
     * Clear all context data.
     *
     * @return void
     */
    public static function clear(): void
    {
        static::clearAuthContext();
        static::clearRequestContext();
    }
    
    /**
     * Get all context data for debugging.
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return [
            'auth' => static::$authContext,
            'request' => static::$requestContext,
        ];
    }
}
