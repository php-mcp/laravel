<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MCP Server Information
    |--------------------------------------------------------------------------
    */
    'server' => [
        'name' => env('MCP_SERVER_NAME', 'Laravel MCP'),
        'version' => env('MCP_SERVER_VERSION', '1.0.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP Discovery Configuration
    |--------------------------------------------------------------------------
    */
    'discovery' => [
        'base_path' => base_path(),

        // Relative paths from project root (base_path()) to scan for MCP elements.
        'directories' => [
            env('MCP_DISCOVERY_PATH', 'app/Mcp'),
            // Add more paths if needed
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP Cache Configuration
    |--------------------------------------------------------------------------
    | Configures caching for both discovered elements (via Registry) and
    | transport state (via TransportState). Uses Laravel's cache system.
    */
    'cache' => [
        // The Laravel cache store to use (e.g., 'file', 'redis', 'database').
        'store' => env('MCP_CACHE_STORE', config('cache.default')),

        // The prefix for the cache keys.
        'prefix' => env('MCP_CACHE_PREFIX', 'mcp_'),

        // Default TTL in seconds for cached items (null = forever).
        'ttl' => env('MCP_CACHE_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP Transport Configuration
    |--------------------------------------------------------------------------
    */
    'transports' => [

        'http' => [
            'enabled' => env('MCP_HTTP_ENABLED', true),

            // URL path prefix for the HTTP endpoints (e.g., /mcp and /mcp/sse).
            'prefix' => env('MCP_HTTP_PREFIX', 'mcp'),

            // Middleware group(s) to apply to the HTTP routes.
            'middleware' => ['web'],

            // Optional domain for the HTTP routes.
            'domain' => env('MCP_HTTP_DOMAIN'),
        ],

        'stdio' => [
            'enabled' => env('MCP_STDIO_ENABLED', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP Protocol & Capabilities
    |--------------------------------------------------------------------------
    */

    // Max items for list methods.
    'pagination_limit' => env('MCP_PAGINATION_LIMIT', 50),

    'capabilities' => [
        'tools' => [
            'enabled' => env('MCP_CAP_TOOLS_ENABLED', true),
            'listChanged' => env('MCP_CAP_TOOLS_LIST_CHANGED', true),
        ],

        'resources' => [
            'enabled' => env('MCP_CAP_RESOURCES_ENABLED', true),
            'subscribe' => env('MCP_CAP_RESOURCES_SUBSCRIBE', true), // Enable resource subscriptions
            'listChanged' => env('MCP_CAP_RESOURCES_LIST_CHANGED', true),
        ],

        'prompts' => [
            'enabled' => env('MCP_CAP_PROMPTS_ENABLED', true),
            'listChanged' => env('MCP_CAP_PROMPTS_LIST_CHANGED', true),
        ],

        'logging' => [
            'enabled' => env('MCP_CAP_LOGGING_ENABLED', true),
            'setLevel' => env('MCP_CAP_LOGGING_SET_LEVEL', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */
    'logging' => [
        // Log channel to use for MCP logs. Uses default channel if null.
        'channel' => env('MCP_LOG_CHANNEL', config('logging.default')),

        // Default log level for the MCP logger (used by Server if not overridden).
        'level' => env('MCP_LOG_LEVEL', 'info'),
    ],
];
