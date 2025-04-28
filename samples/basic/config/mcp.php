<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MCP Server Information
    |--------------------------------------------------------------------------
    */
    'server' => [
        'name' => env('MCP_SERVER_NAME', 'Laravel MCP Sample'),
        'version' => env('MCP_SERVER_VERSION', '1.0.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP Discovery Configuration
    |--------------------------------------------------------------------------
    */
    'discovery' => [
        // Relative paths from project root (base_path()) to scan for MCP elements.
        'directories' => [
            env('MCP_DISCOVERY_PATH', 'app/Mcp'),
        ],
        // If true, discovery cache will be cleared when DiscoverCommand runs.
        'clear_cache_on_discover' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP Cache Configuration
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'store' => env('MCP_CACHE_STORE', null),
        'elements_key' => env('MCP_CACHE_ELEMENTS_KEY', 'mcp:elements'),
        'state_prefix' => env('MCP_CACHE_STATE_PREFIX', 'mcp:state:'),
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
            'path' => env('MCP_HTTP_PATH', 'mcp'),
            'middleware' => ['web'],
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
    'protocol_versions' => [
        '2024-11-05',
    ],
    'pagination_limit' => env('MCP_PAGINATION_LIMIT', 50),
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
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'channel' => env('MCP_LOG_CHANNEL'),
        'level' => env('MCP_LOG_LEVEL', 'info'),
    ],
];
