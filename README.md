# PHP MCP Server for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/php-mcp/laravel.svg?style=flat-square)](https://packagist.org/packages/php-mcp/laravel)
[![Total Downloads](https://img.shields.io/packagist/dt/php-mcp/laravel.svg?style=flat-square)](https://packagist.org/packages/php-mcp/laravel)
[![License](https://img.shields.io/packagist/l/php-mcp/laravel.svg?style=flat-square)](LICENSE)

Integrates the core [`php-mcp/server`](https://github.com/php-mcp/server) package seamlessly into your Laravel application, allowing you to expose parts of your application as **Model Context Protocol (MCP)** tools, resources, and prompts using simple PHP attributes.

This package handles:

*   Automatically wiring Laravel's Cache, Logger, and Container for use by the MCP server.
*   Providing configuration options via `config/mcp.php`.
*   Registering Artisan commands (`mcp:serve`, `mcp:discover`, `mcp:list`).
*   Setting up HTTP+SSE transport routes and controllers.
*   Integrating with Laravel's event system for dynamic updates.

## Requirements

*   PHP >= 8.1
*   Laravel >= 10.0 (May work with older versions, but tested with 10+)
*   [`php-mcp/server`](https://github.com/php-mcp/server) (Installed as a dependency)

## Installation

1.  Require the package via Composer:
    ```bash
    composer require php-mcp/laravel
    ```
2.  The `McpServiceProvider` will be automatically discovered and registered by Laravel.
3.  Publish the configuration file:
    ```bash
    php artisan vendor:publish --provider="PhpMcp\Laravel\Server\McpServiceProvider" --tag="mcp-config"
    ```
    This will create a `config/mcp.php` file where you can customize the server's behavior.

## Configuration

The primary way to configure the MCP server in Laravel is through the `config/mcp.php` file.

*   **`server`**: Basic server information (name, version).
*   **`discovery`**: 
    *   `base_path`: The root path for discovery (defaults to `base_path()`).
    *   `directories`: An array of paths *relative* to `base_path` to scan for MCP attributes (defaults to `['app/Mcp']`). Add the directories where you define your MCP element classes here.
*   **`cache`**:
    *   `store`: The Laravel cache store to use (e.g., `file`, `redis`). Uses the default store if `null`.
    *   `prefix`: The cache prefix to use for caching internally.
    *   `ttl`: Default cache TTL in seconds for discovered elements and transport state.
*   **`transports`**:
    *   **`http`**: Configures the built-in HTTP+SSE transport.
        *   `enabled`: Set to `false` to disable the HTTP routes.
        *   `prefix`: URL prefix for the MCP routes (defaults to `mcp`, resulting in `/mcp` and `/mcp/sse`).
        *   `middleware`: Array of middleware groups to apply. Defaults to `['web']`. **Important:** The `web` middleware group (or another group that enables sessions) is generally required for the HTTP transport to correctly identify clients using session IDs.
        *   `domain`: Optional route domain.
    *   **`stdio`**: Configures the stdio transport.
        *   `enabled`: Set to `false` to disable the `mcp:serve` command.
*   **`protocol_versions`**: Array of supported MCP protocol versions (only `'2024-11-05'` currently).
*   **`pagination_limit`**: Default number of items returned by list methods.
*   **`capabilities`**: Enable/disable specific MCP features (tools, resources, prompts, logging) and list change notifications.
*   **`logging`**:
    *   `channel`: Specific Laravel log channel to use. Defaults to the application's default channel.
    *   `level`: Default log level if not provided by the core server.

## Usage

### Defining MCP Elements

Define your MCP Tools, Resources, and Prompts by decorating methods **or invokable classes** with attributes from the `php-mcp/server` package (`#[McpTool]`, `#[McpResource]`, `#[McpPrompt]`, `#[McpResourceTemplate]`).

Place these classes in a directory included in the `discovery.directories` config array (e.g., `app/Mcp/MyTools.php`).

**Example (`app/Mcp/MyTools.php`):**
```php
<?php

namespace App\Mcp;

use Illuminate\Support\Facades\Config;
use PhpMcp\Server\Attributes\McpResource;
use PhpMcp\Server\Attributes\McpTool;
use Psr\Log\LoggerInterface;

class MyTools
{
    public function __construct(private LoggerInterface $logger) {}

    #[McpResource(uri: 'laravel://config/app.name', mimeType: 'text/plain')]
    public function getAppName(): string
    {
        return Config::get('app.name', 'Laravel');
    }

    #[McpTool]
    public function add(int $a, int $b): int
    {
        $this->logger->info('Adding numbers via MCP');
        return $a + $b;
    }
}
```

*   **Dependency Injection:** Your classes' constructors (or invokable classes) will be resolved using Laravel's service container, so you can inject any application dependencies (like the `LoggerInterface` above).
*   **Attribute Usage:** Refer to the [`php-mcp/server` README](https://github.com/php-mcp/server/blob/main/README.md#attributes-for-discovery) for detailed information on defining elements (both on methods and invokable classes) and formatting return values.

### Automatic Discovery (Development) vs. Manual Discovery (Production)

The server needs to discover your annotated elements before clients can use them.

*   **Development:** In non-production environments (e.g., `APP_ENV=local`), the server will **automatically discover** elements the first time the MCP server is needed (like on the first relevant HTTP request or Artisan command). You generally **do not** need to run the command manually during development after adding or changing elements.

*   **Production:** For performance reasons, automatic discovery is **disabled** in production environments (`APP_ENV=production`). You **must run the discovery command manually** as part of your deployment process:

    ```bash
    php artisan mcp:discover
    ```

    This command scans the configured directories and caches the found elements using the configured Laravel cache store. Running it during deployment ensures your production environment uses the pre-discovered, cached elements for optimal performance.

    *(You can still run `mcp:discover` manually in development if you wish, for example, to pre-populate the cache.)*

### Running the Server

You can expose your MCP server using either the stdio or HTTP+SSE transport.

**Stdio Transport:**

*   Configure your MCP client (Cursor, Claude Desktop) to connect via command. **Important:** Ensure you provide the **full path** to your project's `artisan` file.

    *Example Client Config (e.g., `.cursor/mcp.json`):*
    ```json
    {
        "mcpServers": {
            "my-laravel-mcp": {
                "command": "php",
                "args": [
                    "/full/path/to/your/laravel/project/artisan", 
                    "mcp:serve"
                ],
            }
        }
    }
    ```
    *(Replace `/full/path/to/...` with the correct absolute paths)*

**HTTP+SSE Transport:**

*   **Enable:** Ensure `transports.http.enabled` is `true` in `config/mcp.php`.
*   **Routes:** The package automatically registers two routes (by default `/mcp/sse` [GET] and `/mcp/message` [POST]) using the configured prefix and middleware (`web` by default).
*   
*   **Web Server Environment (CRITICAL):** 
    *   The built-in `php artisan serve` development server **cannot reliably handle** the concurrent nature of SSE (long-running GET request) and subsequent POST requests from the MCP client. This is because it runs as a single PHP process. You will likely encounter hangs or requests not being processed.
    *   For the HTTP+SSE transport to function correctly, you **must** run your Laravel application using a web server setup capable of handling concurrent requests properly:
        *   **Nginx + PHP-FPM** or **Apache + PHP-FPM** (Recommended for typical deployments): Ensure FPM is configured to handle multiple worker processes.
        *   **Laravel Octane** (with Swoole or RoadRunner): Optimized for high concurrency and suitable for this use case.
        *   Other async runtimes capable of handling concurrent I/O.
    *   You also need to ensure your web server (Nginx/Apache) and PHP-FPM configurations allow for long-running requests (`set_time_limit(0)` is handled by the controller, but server/FPM timeouts might interfere) and do *not* buffer the `text/event-stream` response (e.g., `X-Accel-Buffering: no` for Nginx).
*   
*   **Middleware:** Make sure the middleware applied (usually `web` in `config/mcp.php`) correctly handles sessions or provides another way to consistently identify the client across requests if you modify the default `McpController` behaviour.
*   
*   **CSRF Protection Exclusion (Important!):** The default `web` middleware group includes CSRF protection. Since MCP clients do not send CSRF tokens, you **must** exclude the MCP POST route from CSRF verification to prevent `419` errors. 
    *   The specific URI to exclude depends on the `prefix` configured in `config/mcp.php`. By default, the prefix is `mcp`, so you should exclude `mcp` or `mcp/*`.
    *   **Laravel 10 and below:** Add the pattern to the `$except` array in `app/Http/Middleware/VerifyCsrfToken.php`:
        ```php
        // app/Http/Middleware/VerifyCsrfToken.php
        protected $except = [
            // ... other routes
            'mcp', // Or config('mcp.transports.http.prefix', 'mcp').'/*'
        ];
        ```
    *   **Laravel 11+:** Add the pattern within the `bootstrap/app.php` file's `withMiddleware` call:
        ```php
        // bootstrap/app.php
        ->withMiddleware(function (Middleware $middleware) {
            $mcpPrefix = config('mcp.transports.http.prefix', 'mcp');
            $middleware->validateCsrfTokens(except: [
                $mcpPrefix, // Or $mcpPrefix.'/*'
                // ... other routes
            ]);
        })
        ```
*   **Client Configuration:** Configure your MCP client to connect via URL, using the **SSE endpoint URL**.

    *Example Client Config:*
    ```json
    {
        "mcpServers": {
            "my-laravel-mcp-http": {
                "url": "http://your-laravel-app.test/mcp/sse" // Adjust URL as needed
            }
        }
    }
    ```
    The server will automatically inform the client about the correct POST endpoint URL (including a unique `?clientId=...` query parameter) via the initial `endpoint` event sent over the SSE connection.

### Other Commands

*   **List Elements:** View the discovered MCP elements.
    ```bash
    php artisan mcp:list
    # Or list specific types:
    php artisan mcp:list tools
    php artisan mcp:list resources
    php artisan mcp:list prompts
    php artisan mcp:list templates
    # Output as JSON:
    php artisan mcp:list --json 
    ```

### Dynamic Updates (Notifications)

If the list of available tools, resources, or prompts changes while the server is running, or if a specific resource's content is updated, you can notify connected clients (primarily useful for HTTP+SSE).

*   **List Changes:** Dispatch the corresponding event from anywhere in your Laravel application:
    ```php
    use PhpMcp\Laravel\Server\Events\ToolsListChanged;
    use PhpMcp\Laravel\Server\Events\ResourcesListChanged;
    use PhpMcp\Laravel\Server\Events\PromptsListChanged;

    // When tools have changed:
    ToolsListChanged::dispatch();

    // When resources have changed:
    ResourcesListChanged::dispatch();

    // When prompts have changed:
    PromptsListChanged::dispatch();
    ```
    The service provider includes listeners that automatically send the appropriate `*ListChanged` notifications to clients.

*   **Specific Resource Content Change:** Inject or resolve the `PhpMcp\Server\Registry` and call `notifyResourceChanged`:
    ```php
    use PhpMcp\Server\Registry;

    public function updateMyResource(Registry $registry, string $resourceUri)
    {
        // ... update the resource data ...

        $registry->notifyResourceChanged($resourceUri);
    }
    ```
    This will trigger a `resources/didChange` notification for clients subscribed to that specific URI.

## Contributing

Please see CONTRIBUTING.md for details.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Support & Feedback

Please open an issue on the [GitHub repository](https://github.com/php-mcp/laravel) for bugs, questions, or feedback. 
