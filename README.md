# PHP MCP Server for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/php-mcp/laravel.svg?style=flat-square)](https://packagist.org/packages/php-mcp/laravel)
[![Total Downloads](https://img.shields.io/packagist/dt/php-mcp/laravel.svg?style=flat-square)](https://packagist.org/packages/php-mcp/laravel)
[![License](https://img.shields.io/packagist/l/php-mcp/laravel.svg?style=flat-square)](LICENSE)

**Seamlessly integrate the [Model Context Protocol (MCP)](https://modelcontextprotocol.io/introduction) into your Laravel applications.**

This package is a Laravel compatible wrapper for the powerful [`php-mcp/server`](https://github.com/php-mcp/server) library. It allows you to effortlessly expose parts of your Laravel application as MCP **Tools**, **Resources**, and **Prompts**, enabling standardized communication with AI assistants like Anthropic's Claude, Cursor IDE, and others.

**Key Features:**

*   **Effortless Integration:** Designed from the ground up for Laravel, leveraging its service container, configuration, caching, logging, and Artisan console.
*   **Fluent Element Definition:** Define MCP elements programmatically with a clean, Laravely API using the `Mcp` Facade (e.g., `Mcp::tool(...)->description(...)`).
*   **Attribute-Based Discovery:** Alternatively, use PHP 8 attributes (`#[McpTool]`, etc.) on your classes and methods, then run a simple Artisan command to discover and cache them.
*   **Flexible Transports:**
    *   **Integrated HTTP+SSE:** Serve MCP requests directly through your Laravel application's routes, ideal for many setups.
    *   **Dedicated HTTP+SSE Server:** Launch a high-performance, standalone ReactPHP-based HTTP server via an Artisan command for demanding scenarios.
    *   **STDIO:** Run an MCP server over standard input/output, perfect for CLI-driven clients.
*   **Robust Configuration:** Manage all aspects of your MCP server via the `config/mcp.php` file.
*   **Artisan Commands:** Includes commands for serving, discovering elements, and listing registered components.
*   **Event-Driven Updates:** Integrates with Laravel's event system to notify clients of dynamic changes to your MCP elements.

This package utilizes `php-mcp/server` v2.1.0+ which supports the `2024-11-05` version of the Model Context Protocol.

## Requirements

*   PHP >= 8.1
*   Laravel >= 10.0
*   [`php-mcp/server`](https://github.com/php-mcp/server) ^2.1.0 (automatically installed)

## Installation

1.  **Require the Package:**
    ```bash
    composer require php-mcp/laravel
    ```

2.  **Publish Configuration:**
    ```bash
    php artisan vendor:publish --provider="PhpMcp\Laravel\Server\McpServiceProvider" --tag="mcp-config"
    ```

## Configuration

All MCP server settings are managed in `config/mcp.php`. Here are the key sections:

### Server Information
*   **`server`**: Basic server identity settings
    *   `name`: Your MCP server's name (default: 'Laravel MCP')
    *   `version`: Server version number
    *   `instructions`: Optional initialization instructions for clients

### Discovery Settings
*   **`discovery`**: Controls how MCP elements are discovered
    *   `base_path`: Root directory for scanning (defaults to `base_path()`)
    *   `directories`: Paths to scan for MCP attributes (default: `['app/Mcp']`)
    *   `exclude_dirs`: Directories to skip during scans (e.g., 'vendor', 'tests', etc.)
    *   `definitions_file`: Path to manual element definitions (default: `routes/mcp.php`)
    *   `auto_discover`: Enable automatic discovery in development (default: `true`)
    *   `save_to_cache`: Cache discovery results (default: `true`)

### Transport Configuration
*   **`transports`**: Available communication methods
    *   **`stdio`**: CLI-based transport
        *   `enabled`: Enable the `mcp:serve` command with `stdio` option.
    *   **`http_dedicated`**: Standalone HTTP server
        *   `enabled`: Enable the `mcp:serve` command with `http` option.
        *   `host`, `port`, `path_prefix` settings
    *   **`http_integrated`**: Laravel route-based server
        *   `enabled`: Serve through Laravel routes
        *   `route_prefix`: URL prefix (default: 'mcp')
        *   `middleware`: Applied middleware (default: 'web')

### Cache & Performance
*   **`cache`**: Caching configuration
    *   `store`: Laravel cache store to use
    *   `ttl`: Cache lifetime in seconds
*   **`pagination_limit`**: Maximum items returned in list operations

### Feature Control
*   **`capabilities`**: Toggle MCP features
    *   Enable/disable tools, resources, prompts
    *   Control subscriptions and change notifications
*   **`logging`**: Server logging configuration
    *   `channel`: Laravel log channel
    *   `level`: Default log level

Review the published `config/mcp.php` file for detailed documentation of all available options and their descriptions.

## Defining MCP Elements

PHP MCP Laravel provides two approaches to define your MCP elements: manual registration using a fluent API or attribute-based discovery.

### Manual Registration

The recommended approach is using the fluent `Mcp` facade to manually register your elements in `routes/mcp.php` (this path can be changed in config/mcp.php via the discovery.definitions_file key).

```php
Mcp::tool([MyHandlers::class, 'calculateSum']);

Mcp::resource( 'status://app/health', [MyHandlers::class, 'getAppStatus']);

Mcp::prompt(MyInvokableTool::class);

Mcp::resourceTemplate('user://{id}/data', [MyHandlers::class, 'getUserData']);
```

The facade provides several registration methods, each with optional fluent configuration methods:

#### Tools (`Mcp::tool()`)

Defines an action or function the MCP client can invoke. Register a tool by providing either:
- Just the handler: `Mcp::tool(MyTool::class)` 
- Name and handler: `Mcp::tool('my_tool', [MyClass::class, 'method'])`

Available configuration methods:
- `name()`: Override the inferred name
- `description()`: Set a custom description

#### Resources (`Mcp::resource()`)

Defines a specific, static piece of content or data identified by a URI. Register a resource by providing:
- `$uri` (`required`): The unique URI for this resource instance (e.g., `config://app/settings`).
- `$handler`: The handler that will return the resource's content.

Available configuration methods:
- `name(string $name): self`: Sets a human-readable name. Inferred if omitted.
- `description(string $description): self`: Sets a description. Inferred if omitted.
- `mimeType(string $mimeType): self`: Specifies the resource's MIME type. Can sometimes be inferred from the handler's return type or content.
- `size(?int $size): self`: Specifies the resource size in bytes, if known.
- `annotations(array $annotations): self`: Adds MCP-standard annotations (e.g., ['audience' => ['user']]).

#### Resource Template (`Mcp::resourceTemplate()`)

Defines a handler for resource URIs that contain variable parts, allowing dynamic resource instance generation. Register a resource template by providing:
- `$uriTemplate` (`required`): The URI template string (`RFC 6570`), e.g., `user://{userId}/profile`.
- `$handler`: The handler method. Its parameters must match the variables in the `$uriTemplate`.

Available configuration methods:
- `name(string $name): self`: Sets a human-readable name for the template type.
- `description(string $description): self`: Sets a description for the template.
- `mimeType(string $mimeType): self`: Sets a default MIME type for resources resolved by this template.
- `annotations(array $annotations): self`: Adds MCP-standard annotations.

#### Prompts (`Mcp::prompt()`)

Defines a generator for MCP prompt messages, often used to construct conversations for an LLM. Register a prompt by providing just the handler, or the name and handler.
- `$name` (`optional`): The MCP prompt name. Inferred if omitted.
- `$handler`: The handler method. Its parameters become the prompt's input arguments.


The package automatically resolves handlers through Laravel's service container, allowing you to inject dependencies through constructor injection. Each registration method accepts either an invokable class or a `[class, method]` array.

The fluent methods like `description()`, `name()`, and `mimeType()` are optional. When omitted, the package intelligently infers these values from your handler's method signatures, return types, and DocBlocks. Use these methods only when you need to override the automatically generated metadata.

Manually registered elements are always available regardless of cache status and take precedence over discovered elements with the same identifier.

### Attribute-Based Discovery

As an alternative, you can use PHP 8 attributes to mark your methods or invokable classes as MCP elements. That way, you don't have to manually register them in the definitions file:

```php
namespace App\Mcp;

use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\McpResource;

class DiscoveredElements
{
    #[McpTool(name: 'echo_discovered')]
    public function echoMessage(string $message): string
    {
        return "Discovered echo: {$message}";
    }
    
    #[McpResource(uri: 'status://server/health', mimeType: 'application/json')]
    public function getServerHealth(): array
    {
        return ['status' => 'healthy', 'uptime' => 123];
    }
}
```

When `auto_discover` enabled in your config, these elements are automatically discovered when needed. For production or to manually trigger discovery, run:

```bash
php artisan mcp:discover
```

This command scans the configured directories, registers the discovered elements, and caches the results for improved performance. Use the `--no-cache` flag to skip caching or `--force` to perform a fresh scan regardless of cache status.

See the [`php-mcp/server` documentation](https://github.com/php-mcp/server?tab=readme-ov-file#attribute-details--return-formatting) for detailed information on attribute parameters and return value formatting.

## Running the MCP Server

PHP MCP Laravel offers three transport options to serve your MCP elements.

### Integrated HTTP+SSE via Laravel Routes

The most convenient option for getting started is serving MCP directly through your Laravel application's routes:

```php
// Client connects to: http://your-app.test/mcp/sse
// No additional processes needed
```

**Configuration**:
- Ensure `mcp.transports.http_integrated.enabled` is `true` in your config
- The package registers routes at `/mcp/sse` (GET) and `/mcp/message` (POST) by default
- You can customize the prefix, middleware, and domain in `config/mcp.php`

**CSRF Protection**: You must exclude the MCP message endpoint from CSRF verification:

For Laravel 11+:
```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
         'mcp/message', // Adjust if you changed the route prefix
    ]);
})
```

For Laravel 10 and below:
```php
// app/Http/Middleware/VerifyCsrfToken.php
protected $except = [
    'mcp/message', // Adjust if you changed the route prefix
];
```

**Server Environment Considerations**:
Standard synchronous servers like PHP's built-in server or basic PHP-FPM setups can struggle with SSE connections. For eg, a single PHP-FPM worker will be tied up for each active SSE connection. For production, consider using Laravel Octane with Swoole/RoadRunner or properly configured Nginx with sufficient PHP-FPM workers.

### Dedicated HTTP+SSE Server (Recommended)

For production environments or high-traffic applications, the dedicated HTTP server provides better performance and isolation:

```bash
php artisan mcp:serve --transport=http
```

This launches a standalone ReactPHP-based HTTP server specifically for MCP traffic:

**Configuration**:
- Ensure `mcp.transports.http_dedicated.enabled` is `true` in your config
- Default server listens on `127.0.0.1:8090` with path prefix `/mcp`
- Configure through any of these methods:
  - Environment variables: `MCP_HTTP_DEDICATED_HOST`, `MCP_HTTP_DEDICATED_PORT`, `MCP_HTTP_DEDICATED_PATH_PREFIX`
  - Edit values directly in `config/mcp.php`
  - Override at runtime: `--host=0.0.0.0 --port=8091 --path-prefix=custom_mcp`

This is a blocking, long-running process that should be managed with Supervisor, systemd, or Docker in production environments.

### STDIO Transport for Direct Client Integration

Ideal for Cursor IDE and other MCP clients that directly launch server processes:

```bash
php artisan mcp:serve
# or explicitly:
php artisan mcp:serve --transport=stdio
```

**Client Configuration**:
Configure your MCP client to execute this command directly. For example, in Cursor:

```json
// .cursor/mcp.json
{
    "mcpServers": {
        "my-laravel-stdio": {
            "command": "php",
            "args": [
                "/full/path/to/your/laravel/project/artisan",
                "mcp:serve",
                "--transport=stdio"
            ]
        }
    }
}
```

**Important**: When using STDIO transport, your handler code must not write to STDOUT using echo, print, or similar functions. Use Laravel's logger or STDERR for any debugging output.

## Listing Registered Elements

To see which MCP elements your server has registered (both manual and discovered/cached):

```bash
php artisan mcp:list
# Specific types:
php artisan mcp:list tools
php artisan mcp:list resources
# JSON output:
php artisan mcp:list --json
```

## Dynamic Updates (Events)

If your available MCP elements or resource content change while the server is running, you can notify connected clients (most relevant for HTTP transports).

*   **List Changes (Tools, Resources, Prompts):**
    Dispatch the corresponding Laravel event. The package includes listeners to send the appropriate MCP notification.
    ```php
    use PhpMcp\Laravel\Events\ToolsListChanged;
    use PhpMcp\Laravel\Events\ResourcesListChanged;
    use PhpMcp\Laravel\Events\PromptsListChanged;

    ToolsListChanged::dispatch();
    // ResourcesListChanged::dispatch();
    // PromptsListChanged::dispatch();
    ```

*   **Specific Resource Content Update:**
    Dispatch the `PhpMcp\Laravel\Events\ResourceUpdated` event with the URI of the changed resource.
    ```php
    use PhpMcp\Laravel\Events\ResourceUpdated;

    $resourceUri = 'file:///path/to/updated_file.txt';
    // ... your logic that updates the resource ...
    ResourceUpdated::dispatch($resourceUri);
    ```
    The `McpNotificationListener` will handle sending the `notifications/resource/updated` MCP notification to clients subscribed to that URI.

## Testing

For your application tests, you can mock the `Mcp` facade or specific MCP handlers as needed. When testing MCP functionality itself, consider integration tests that make HTTP requests to your integrated MCP endpoints (if used) or command tests for Artisan commands.

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) in the main [`php-mcp/server`](https://github.com/php-mcp/server) repository for general contribution guidelines. For issues or PRs specific to this Laravel package, please use this repository's issue tracker.

## License

The MIT License (MIT). See [LICENSE](LICENSE).
