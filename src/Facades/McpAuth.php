<?php

declare(strict_types=1);

namespace PhpMcp\Laravel\Facades;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Facade;
use PhpMcp\Laravel\Support\McpContext;

/**
 * Facade for accessing MCP authentication context.
 *
 * This provides a convenient way to access authentication information
 * from within MCP tools and resources, similar to Laravel's Auth facade.
 *
 * @method static \Illuminate\Contracts\Auth\Authenticatable|null user()
 * @method static bool check()
 * @method static string|null guard()
 * @method static mixed token()
 * @method static array headers()
 * @method static mixed header(string $key, mixed $default = null)
 * @method static array|null getAuthContext()
 * @method static array all()
 */
class McpAuth extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return McpContext::class;
    }
    
    /**
     * Get the authenticated user from MCP context.
     *
     * This is a convenience method that works similar to Auth::user()
     * but uses the MCP context instead of Laravel's default auth system.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public static function user()
    {
        return McpContext::user();
    }
    
    /**
     * Check if a user is authenticated in the current MCP context.
     *
     * @return bool
     */
    public static function check(): bool
    {
        return McpContext::check();
    }
    
    /**
     * Get the authentication guard used.
     *
     * @return string|null
     */
    public static function guard(): ?string
    {
        return McpContext::guard();
    }
    
    /**
     * Get the authentication token.
     *
     * @return mixed
     */
    public static function token()
    {
        return McpContext::token();
    }
    
    /**
     * Get request headers from MCP context.
     *
     * @return array
     */
    public static function headers(): array
    {
        return McpContext::headers();
    }
    
    /**
     * Get a specific header value from MCP context.
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    public static function header(string $key, $default = null)
    {
        return McpContext::header($key, $default);
    }
}
