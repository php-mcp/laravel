<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MCP Server Information
    |--------------------------------------------------------------------------
    |
    | This section defines basic information about your MCP server instance,
    | including its name, version, and any initialization instructions that
    | should be provided to clients during the initial handshake.
    |
    */
    'server' => [
        'name' => env('MCP_SERVER_NAME', 'Laravel MCP'),
        'version' => env('MCP_SERVER_VERSION', '1.0.0'),
        'instructions' => env('MCP_SERVER_INSTRUCTIONS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP Discovery Configuration
    |--------------------------------------------------------------------------
    |
    | These options control how the MCP server discovers and registers tools,
    | resources and prompts in your application. You can configure which
    | directories to scan, what to exclude, and how discovery behaves.
    |
    */
    'discovery' => [
        'base_path' => base_path(),
        'directories' => [
            env('MCP_DISCOVERY_PATH', 'app/Mcp'),
        ],
        'exclude_dirs' => [
            'vendor',
            'tests',
            'storage',
            'public',
            'resources',
            'bootstrap',
            'config',
            'database',
            'routes',
            'node_modules',
            '.git',
        ],
        'definitions_file' => base_path('routes/mcp.php'),
        'auto_discover' => env('MCP_AUTO_DISCOVER', true),
        'save_to_cache' => env('MCP_DISCOVERY_SAVE_TO_CACHE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how the MCP server caches discovered elements and transport
    | state using Laravel's cache system. You can specify which store to use
    | and how long items should be cached.
    |
    */
    'cache' => [
        'store' => env('MCP_CACHE_STORE', config('cache.default')),
        'ttl' => env('MCP_CACHE_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP Transport Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the available transports for MCP communication. Three types are
    | supported: stdio for CLI clients, http_dedicated for a standalone server,
    | and http_integrated for serving through Laravel's routing system.
    |
    */
    'transports' => [
        'stdio' => [
            'enabled' => env('MCP_STDIO_ENABLED', true),
        ],

        'http_dedicated' => [
            'enabled' => (bool) env('MCP_HTTP_DEDICATED_ENABLED', true),
            'legacy' => (bool) env('MCP_HTTP_DEDICATED_LEGACY', false),
            'host' => env('MCP_HTTP_DEDICATED_HOST', '127.0.0.1'),
            'port' => (int) env('MCP_HTTP_DEDICATED_PORT', 8090),
            'path_prefix' => env('MCP_HTTP_DEDICATED_PATH_PREFIX', 'mcp'),
            'ssl_context_options' => [],
            'enable_json_response' => (bool) env('MCP_HTTP_DEDICATED_JSON_RESPONSE', true),
            'event_store' => env('MCP_HTTP_DEDICATED_EVENT_STORE'), // FQCN or null
        ],

        'http_integrated' => [
            'enabled' => (bool) env('MCP_HTTP_INTEGRATED_ENABLED', true),
            'legacy' => (bool) env('MCP_HTTP_INTEGRATED_LEGACY', false),
            'route_prefix' => env('MCP_HTTP_INTEGRATED_ROUTE_PREFIX', 'mcp'),
            'middleware' => explode(',', env('MCP_HTTP_INTEGRATED_MIDDLEWARE', 'web')),
            'domain' => env('MCP_HTTP_INTEGRATED_DOMAIN'),
            'sse_poll_interval' => (int) env('MCP_HTTP_INTEGRATED_SSE_POLL_SECONDS', 1),
            'cors_origin' => env('MCP_HTTP_INTEGRATED_CORS_ORIGIN', '*'),
            'enable_json_response' => (bool) env('MCP_HTTP_INTEGRATED_JSON_RESPONSE', false),
            'json_response_timeout' => (int) env('MCP_HTTP_INTEGRATED_JSON_TIMEOUT', 30),
            'event_store' => env('MCP_HTTP_INTEGRATED_EVENT_STORE'), // FQCN or null
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination Limit
    |--------------------------------------------------------------------------
    |
    | This value determines the maximum number of items that will be returned
    | by list methods in the MCP server.
    |
    */
    'pagination_limit' => env('MCP_PAGINATION_LIMIT', 50),

    /*
    |--------------------------------------------------------------------------
    | MCP Capabilities Configuration
    |--------------------------------------------------------------------------
    |
    | Define which MCP features are enabled in your server instance. This includes
    | support for tools, resources, prompts, and their related functionality like
    | subscriptions and change notifications.
    |
    */
    'capabilities' => [
        'tools' => [
            'enabled' => env('MCP_CAP_TOOLS_ENABLED', true),
            'listChanged' => env('MCP_CAP_TOOLS_LIST_CHANGED', true),
        ],

        'resources' => [
            'enabled' => env('MCP_CAP_RESOURCES_ENABLED', true),
            'subscribe' => env('MCP_CAP_RESOURCES_SUBSCRIBE', true),
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
    |
    | Configure how the MCP server handles logging. You can specify which Laravel
    | log channel to use and set the default log level.
    |
    */
    'logging' => [
        'channel' => env('MCP_LOG_CHANNEL', config('logging.default')),
        'level' => env('MCP_LOG_LEVEL', 'info'),
    ],
];
