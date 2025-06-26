# Laravel MCP Server SDK

[![Latest Version on Packagist](https://img.shields.io/packagist/v/php-mcp/laravel.svg?style=flat-square)](https://packagist.org/packages/php-mcp/laravel)
[![Total Downloads](https://img.shields.io/packagist/dt/php-mcp/laravel.svg?style=flat-square)](https://packagist.org/packages/php-mcp/laravel)
[![License](https://img.shields.io/packagist/l/php-mcp/laravel.svg?style=flat-square)](LICENSE)

**A comprehensive Laravel SDK for building [Model Context Protocol (MCP)](https://modelcontextprotocol.io/introduction) servers with enterprise-grade features and Laravel-native integrations.**

This SDK provides a Laravel-optimized wrapper for the powerful [`php-mcp/server`](https://github.com/php-mcp/server) library, enabling you to expose your Laravel application's functionality as standardized MCP **Tools**, **Resources**, **Prompts**, and **Resource Templates** for AI assistants like Anthropic's Claude, Cursor IDE, OpenAI's ChatGPT, and others.

## Key Features

- **Laravel-Native Integration**: Deep integration with Laravel's service container, configuration, caching, logging, sessions, and Artisan console
- **Fluent Element Definition**: Define MCP elements with an elegant, Laravel-style API using the `Mcp` facade
- **Attribute-Based Discovery**: Use PHP 8 attributes (`#[McpTool]`, `#[McpResource]`, etc.) with automatic discovery and caching
- **Advanced Session Management**: Laravel-native session handlers (file, database, cache, redis) with automatic garbage collection
- **Flexible Transport Options**:
  - **Integrated HTTP**: Serve through Laravel routes with middleware support
  - **Dedicated HTTP Server**: High-performance standalone ReactPHP server
  - **STDIO**: Command-line interface for direct client integration
- **Streamable Transport**: Enhanced HTTP transport with resumability and event sourcing
- **Artisan Commands**: Commands for serving, discovery, and element management
- **Full Test Coverage**: Comprehensive test suite ensuring reliability

This package supports the **2025-03-26** version of the Model Context Protocol.

## Requirements

- **PHP** >= 8.1
- **Laravel** >= 10.0
- **Extensions**: `json`, `mbstring`, `pcre` (typically enabled by default)

## Installation

Install the package via Composer:

```bash
composer require php-mcp/laravel
```

Publish the configuration file:

```bash
php artisan vendor:publish --provider="PhpMcp\Laravel\McpServiceProvider" --tag="mcp-config"
```

For database session storage, publish the migration:

```bash
php artisan vendor:publish --provider="PhpMcp\Laravel\McpServiceProvider" --tag="mcp-migrations"
php artisan migrate
```

## Configuration

All MCP server settings are managed through `config/mcp.php`, which contains comprehensive documentation for each option. The configuration covers server identity, capabilities, discovery settings, session management, transport options, caching, and logging. All settings support environment variables for easy deployment management.

Key configuration areas include:
- **Server Info**: Name, version, and basic identity
- **Capabilities**: Control which MCP features are enabled (tools, resources, prompts, etc.)
- **Discovery**: How elements are found and cached from your codebase
- **Session Management**: Multiple storage backends (file, database, cache, redis) with automatic garbage collection
- **Transports**: STDIO, integrated HTTP, and dedicated HTTP server options
- **Performance**: Caching strategies and pagination limits

Review the published `config/mcp.php` file for detailed documentation of all available options and their environment variable overrides.

## Defining MCP Elements

Laravel MCP provides two powerful approaches for defining MCP elements: **Manual Registration** (using the fluent `Mcp` facade) and **Attribute-Based Discovery** (using PHP 8 attributes). Both can be combined, with manual registrations taking precedence.

### Element Types

- **Tools**: Executable functions/actions (e.g., `calculate`, `send_email`, `query_database`)
- **Resources**: Static content/data accessible via URI (e.g., `config://settings`, `file://readme.txt`)
- **Resource Templates**: Dynamic resources with URI patterns (e.g., `user://{id}/profile`)
- **Prompts**: Conversation starters/templates (e.g., `summarize`, `translate`)

### 1. Manual Registration

Define your MCP elements using the elegant `Mcp` facade in `routes/mcp.php`:

```php
<?php

use PhpMcp\Laravel\Facades\Mcp;
use App\Services\{CalculatorService, UserService, EmailService, PromptService};

// Register a simple tool
Mcp::tool([CalculatorService::class, 'add'])
    ->name('add_numbers')
    ->description('Add two numbers together');

// Register an invokable class as a tool
Mcp::tool(EmailService::class)
    ->description('Send emails to users');

// Register a resource with metadata
Mcp::resource('config://app/settings', [UserService::class, 'getAppSettings'])
    ->name('app_settings')
    ->description('Application configuration settings')
    ->mimeType('application/json')
    ->size(1024);

// Register a resource template for dynamic content
Mcp::resourceTemplate('user://{userId}/profile', [UserService::class, 'getUserProfile'])
    ->name('user_profile')
    ->description('Get user profile by ID')
    ->mimeType('application/json');

// Register a prompt generator
Mcp::prompt([PromptService::class, 'generateWelcome'])
    ->name('welcome_user')
    ->description('Generate a personalized welcome message');
```

**Available Fluent Methods:**

**For All Elements:**
- `name(string $name)`: Override the inferred name
- `description(string $description)`: Set a custom description

**For Resources:**
- `mimeType(string $mimeType)`: Specify content type
- `size(int $size)`: Set content size in bytes
- `annotations(array|Annotations $annotations)`: Add MCP annotations

**Handler Formats:**
- `[ClassName::class, 'methodName']` - Class method
- `InvokableClass::class` - Invokable class with `__invoke()` method

### 2. Attribute-Based Discovery

Alternatively, you can use PHP 8 attributes to mark your methods or classes as MCP elements, in which case, you don't have to register them in them `routes/mcp.php`:

```php
<?php

namespace App\Services;

use PhpMcp\Server\Attributes\{McpTool, McpResource, McpResourceTemplate, McpPrompt};

class UserService
{
    /**
     * Create a new user account.
     */
    #[McpTool(name: 'create_user')]
    public function createUser(string $email, string $password, string $role = 'user'): array
    {
        // Create user logic
        return [
            'id' => 123,
            'email' => $email,
            'role' => $role,
            'created_at' => now()->toISOString(),
        ];
    }

    /**
     * Get application configuration.
     */
    #[McpResource(
        uri: 'config://app/settings',
        mimeType: 'application/json'
    )]
    public function getAppSettings(): array
    {
        return [
            'theme' => config('app.theme', 'light'),
            'timezone' => config('app.timezone'),
            'features' => config('app.features', []),
        ];
    }

    /**
     * Get user profile by ID.
     */
    #[McpResourceTemplate(
        uriTemplate: 'user://{userId}/profile',
        mimeType: 'application/json'
    )]
    public function getUserProfile(string $userId): array
    {
        return [
            'id' => $userId,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'profile' => [
                'bio' => 'Software developer',
                'location' => 'New York',
            ],
        ];
    }

    /**
     * Generate a welcome message prompt.
     */
    #[McpPrompt(name: 'welcome_user')]
    public function generateWelcome(string $username, string $role = 'user'): array
    {
        return [
            [
                'role' => 'user',
                'content' => "Create a personalized welcome message for {$username} with role {$role}. Be warm and professional."
            ]
        ];
    }
}
```

**Discovery Process:**

Elements marked with attributes are automatically discovered when:
- `auto_discover` is enabled in configuration (default: `true`)
- You run `php artisan mcp:discover` manually

```bash
# Discover and cache MCP elements
php artisan mcp:discover

# Force re-discovery (ignores cache)
php artisan mcp:discover --force

# Discover without saving to cache
php artisan mcp:discover --no-cache
```

### Element Precedence

- **Manual registrations** always override discovered elements with the same identifier
- **Discovered elements** are cached for performance
- **Cache** is automatically invalidated on fresh discovery runs

## Running the MCP Server

Laravel MCP offers three transport options, each optimized for different deployment scenarios:

### 1. STDIO Transport

**Best for:** Direct client execution, Cursor IDE, command-line tools

```bash
php artisan mcp:serve --transport=stdio
```

**Client Configuration (Cursor IDE):**

```json
{
    "mcpServers": {
        "my-laravel-app": {
            "command": "php",
            "args": [
                "/absolute/path/to/your/laravel/project/artisan",
                "mcp:serve",
                "--transport=stdio"
            ]
        }
    }
}
```

> ⚠️ **Important**: When using STDIO transport, never write to `STDOUT` in your handlers (use Laravel's logger or `STDERR` for debugging). `STDOUT` is reserved for JSON-RPC communication.

### 2. Integrated HTTP Transport

**Best for:** Development, applications with existing web servers, quick setup

The integrated transport serves MCP through your Laravel application's routes:

```php
// Routes are automatically registered at:
// GET  /mcp       - Streamable connection endpoint
// POST /mcp       - Message sending endpoint  
// DELETE /mcp     - Session termination endpoint

// Legacy mode (if enabled):
// GET  /mcp/sse   - Server-Sent Events endpoint
// POST /mcp/message - Message sending endpoint
```

**CSRF Protection Configuration:**

Add the MCP routes to your CSRF exclusions:

**Laravel 11+:**
```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'mcp',           // For streamable transport (default)
        'mcp/*',   // For legacy transport (if enabled)
    ]);
})
```

**Laravel 10 and below:**
```php
// app/Http/Middleware/VerifyCsrfToken.php
protected $except = [
    'mcp',           // For streamable transport (default)
    'mcp/*',   // For legacy transport (if enabled)
];
```

**Configuration Options:**

```php
'http_integrated' => [
    'enabled' => true,
    'route_prefix' => 'mcp',           // URL prefix
    'middleware' => ['api'],           // Applied middleware
    'domain' => 'api.example.com',     // Optional domain
    'legacy' => false,                 // Use legacy SSE transport instead
],
```

**Client Configuration:**

```json
{
    "mcpServers": {
        "my-laravel-app": {
            "url": "https://your-app.test/mcp"
        }
    }
}
```

**Server Environment Considerations:**

Standard synchronous servers struggle with persistent SSE connections, as each active connection ties up a worker process. This affects both development and production environments.

**For Development:**
- **PHP's built-in server** (`php artisan serve`) won't work - the SSE stream locks the single process
- **Laravel Herd** (recommended for local development)
- **Properly configured Nginx** with multiple PHP-FPM workers
- **Laravel Octane** with Swoole/RoadRunner for async handling
- **Dedicated HTTP server** (`php artisan mcp:serve --transport=http`)

**For Production:**
- **Dedicated HTTP server** (strongly recommended)
- **Laravel Octane** with Swoole/RoadRunner
- **Properly configured Nginx** with sufficient PHP-FPM workers

### 3. Dedicated HTTP Server (Recommended for Production)

**Best for:** Production environments, high-traffic applications, multiple concurrent clients

Launch a standalone ReactPHP-based HTTP server:

```bash
# Start dedicated server
php artisan mcp:serve --transport=http

# With custom configuration
php artisan mcp:serve --transport=http \
    --host=0.0.0.0 \
    --port=8091 \
    --path-prefix=mcp_api
```

**Configuration Options:**

```php
'http_dedicated' => [
    'enabled' => true,
    'host' => '127.0.0.1',              // Bind address
    'port' => 8090,                     // Port number
    'path_prefix' => 'mcp',             // URL path prefix
    'legacy' => false,                  // Use legacy transport
    'enable_json_response' => false,    // JSON mode vs SSE streaming
    'event_store' => null,              // Event store for resumability
    'ssl_context_options' => [],        // SSL configuration
],
```

**Transport Modes:**

- **Streamable Mode** (`legacy: false`): Enhanced transport with resumability and event sourcing
- **Legacy Mode** (`legacy: true`): Deprecated HTTP+SSE transport. 

**JSON Response Mode:**

```php
'enable_json_response' => true,  // Returns immediate JSON responses
'enable_json_response' => false, // Uses SSE streaming (default)
```

- **JSON Mode**: Returns immediate responses, best for fast-executing tools
- **SSE Mode**: Streams responses, ideal for long-running operations

**Production Deployment:**

This creates a long-running process that should be managed with:

- **Supervisor** (recommended)
- **systemd** 
- **Docker** containers
- **Process managers**

Example Supervisor configuration:

```ini
[program:laravel-mcp]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/laravel/artisan mcp:serve --transport=http
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/laravel-mcp.log
```

For comprehensive production deployment guides, see the [php-mcp/server documentation](https://github.com/php-mcp/server#-production-deployment).

## Artisan Commands

Laravel MCP includes several Artisan commands for managing your MCP server:

### Discovery Command

Discover and cache MCP elements from your codebase:

```bash
# Discover elements and update cache
php artisan mcp:discover

# Force re-discovery (ignore existing cache)
php artisan mcp:discover --force

# Discover without updating cache
php artisan mcp:discover --no-cache
```

**Output Example:**
```
Starting MCP element discovery...
Discovery complete.

┌─────────────────────┬───────┐
│ Element Type        │ Count │
├─────────────────────┼───────┤
│ Tools               │ 5     │
│ Resources           │ 3     │
│ Resource Templates  │ 2     │
│ Prompts             │ 1     │
└─────────────────────┴───────┘

MCP element definitions updated and cached.
```

### List Command

View registered MCP elements:

```bash
# List all elements
php artisan mcp:list

# List specific type
php artisan mcp:list tools
php artisan mcp:list resources
php artisan mcp:list prompts
php artisan mcp:list templates

# JSON output
php artisan mcp:list --json
```

**Output Example:**
```
Tools:
┌─────────────────┬──────────────────────────────────────────────┐
│ Name            │ Description                                  │
├─────────────────┼──────────────────────────────────────────────┤
│ add_numbers     │ Add two numbers together                     │
│ send_email      │ Send email to specified recipient            │
│ create_user     │ Create a new user account with validation    │
└─────────────────┴──────────────────────────────────────────────┘

Resources:
┌─────────────────────────┬───────────────────┬─────────────────────┐
│ URI                     │ Name              │ MIME                │
├─────────────────────────┼───────────────────┼─────────────────────┤
│ config://app/settings   │ app_settings      │ application/json    │
│ file://readme.txt       │ readme_file       │ text/plain          │
└─────────────────────────┴───────────────────┴─────────────────────┘
```

### Serve Command

Start the MCP server with various transport options:

```bash
# Interactive mode (prompts for transport selection)
php artisan mcp:serve

# STDIO transport
php artisan mcp:serve --transport=stdio

# HTTP transport with defaults
php artisan mcp:serve --transport=http

# HTTP transport with custom settings
php artisan mcp:serve --transport=http \
    --host=0.0.0.0 \
    --port=8091 \
    --path-prefix=api/mcp
```

**Command Options:**
- `--transport`: Choose transport type (`stdio` or `http`)
- `--host`: Host address for HTTP transport
- `--port`: Port number for HTTP transport  
- `--path-prefix`: URL path prefix for HTTP transport

## Dynamic Updates & Events

Laravel MCP integrates with Laravel's event system to provide real-time updates to connected clients:

### List Change Events

Notify clients when your available elements change:

```php
use PhpMcp\Laravel\Events\{ToolsListChanged, ResourcesListChanged, PromptsListChanged};

// Notify clients that available tools have changed
ToolsListChanged::dispatch();

// Notify about resource list changes
ResourcesListChanged::dispatch();

// Notify about prompt list changes  
PromptsListChanged::dispatch();
```

### Resource Update Events

Notify clients when specific resource content changes:

```php
use PhpMcp\Laravel\Events\ResourceUpdated;

// Update a file and notify subscribers
file_put_contents('/path/to/config.json', json_encode($newConfig));
ResourceUpdated::dispatch('file:///path/to/config.json');

// Update database content and notify
User::find(123)->update(['status' => 'active']);
ResourceUpdated::dispatch('user://123/profile');
```

## Advanced Features

### Schema Validation

The server automatically generates JSON schemas for tool parameters from PHP type hints and docblocks. You can enhance this with the `#[Schema]` attribute for advanced validation:

```php
use PhpMcp\Server\Attributes\Schema;

class PostService
{
    public function createPost(
        #[Schema(minLength: 5, maxLength: 200)]
        string $title,
        
        #[Schema(minLength: 10)]
        string $content,
        
        #[Schema(enum: ['draft', 'published', 'archived'])]
        string $status = 'draft',
        
        #[Schema(type: 'array', items: ['type' => 'string'])]
        array $tags = []
    ): array {
        return Post::create([
            'title' => $title,
            'content' => $content,
            'status' => $status,
            'tags' => $tags,
        ])->toArray();
    }
}
```

**Schema Features:**
- **Automatic inference** from PHP type hints and docblocks
- **Parameter-level validation** using `#[Schema]` attributes
- **Support for** string constraints, numeric ranges, enums, arrays, and objects
- **Works with both** manual registration and attribute-based discovery

For comprehensive schema documentation and advanced features, see the [php-mcp/server Schema documentation](https://github.com/php-mcp/server#-schema-generation-and-validation).

### Completion Providers

Provide auto-completion suggestions for resource template variables and prompt arguments to help users discover available options:

```php
use PhpMcp\Server\Contracts\CompletionProviderInterface;
use PhpMcp\Server\Contracts\SessionInterface;
use PhpMcp\Server\Attributes\CompletionProvider;

class UserIdCompletionProvider implements CompletionProviderInterface
{
    public function getCompletions(string $currentValue, SessionInterface $session): array
    {
        return User::where('username', 'like', $currentValue . '%')
            ->limit(10)
            ->pluck('username')
            ->toArray();
    }
}

class UserService
{
    public function getUserData(
        #[CompletionProvider(UserIdCompletionProvider::class)]
        string $userId
    ): array {
        return User::where('username', $userId)->first()->toArray();
    }
}
```

**Completion Features:**
- **Auto-completion** for resource template variables and prompt arguments
- **Laravel integration** - use Eloquent models, collections, etc.
- **Session-aware** - completions can vary based on user session
- **Real-time filtering** based on user input

For detailed completion provider documentation, see the [php-mcp/server Completion documentation](https://github.com/php-mcp/server#completion-providers).

### Dependency Injection

Your MCP handlers automatically benefit from Laravel's service container:

```php
class OrderService
{
    public function __construct(
        private PaymentGateway $gateway,
        private NotificationService $notifications,
        private LoggerInterface $logger
    ) {}

    #[McpTool(name: 'process_order')]
    public function processOrder(array $orderData): array
    {
        $this->logger->info('Processing order', $orderData);
        
        $payment = $this->gateway->charge($orderData['amount']);
        
        if ($payment->successful()) {
            $this->notifications->sendOrderConfirmation($orderData['email']);
            return ['status' => 'success', 'order_id' => $payment->id];
        }
        
        throw new \Exception('Payment failed: ' . $payment->error);
    }
}
```


### Exception Handling

Tool handlers can throw exceptions that are automatically converted to proper JSON-RPC error responses:

```php
#[McpTool(name: 'get_user')]
public function getUser(int $userId): array
{
    $user = User::find($userId);
    
    if (!$user) {
        throw new \InvalidArgumentException("User with ID {$userId} not found");
    }
    
    if (!$user->isActive()) {
        throw new \RuntimeException("User account is deactivated");
    }
    
    return $user->toArray();
}
```

### Logging and Debugging

Configure comprehensive logging for your MCP server:

```php
// config/mcp.php
'logging' => [
    'channel' => 'mcp',  // Use dedicated log channel
    'level' => 'debug',  // Set appropriate log level
],
```

Create a dedicated log channel in `config/logging.php`:

```php
'channels' => [
    'mcp' => [
        'driver' => 'daily',
        'path' => storage_path('logs/mcp.log'),
        'level' => env('MCP_LOG_LEVEL', 'info'),
        'days' => 14,
    ],
],
```

## Migration Guide

### From v2.x to v3.x

**Configuration Changes:**

```php
// Old structure
'capabilities' => [
    'tools' => ['enabled' => true, 'listChanged' => true],
    'resources' => ['enabled' => true, 'subscribe' => true],
],

// New structure  
'capabilities' => [
    'tools' => true,
    'toolsListChanged' => true,
    'resources' => true,
    'resourcesSubscribe' => true,
],
```

**Session Configuration:**

```php
// Old: Basic configuration
'session' => [
    'driver' => 'cache',
    'ttl' => 3600,
],

// New: Enhanced configuration
'session' => [
    'driver' => 'cache',
    'ttl' => 3600,
    'store' => config('cache.default'),
    'lottery' => [2, 100],
],
```

**Transport Updates:**

- Default transport changed from sse to streamable
- New CSRF exclusion pattern: `mcp` instead of `mcp/*`
- Enhanced session management with automatic garbage collection

**Breaking Changes:**

- Removed deprecated methods in favor of new registry API
- Updated element registration to use new schema format
- Changed configuration structure for better organization

## Examples & Use Cases

### E-commerce Integration

```php
class EcommerceService
{
    #[McpTool(name: 'get_product_info')]
    public function getProductInfo(int $productId): array
    {
        return Product::with(['category', 'reviews'])
            ->findOrFail($productId)
            ->toArray();
    }

    #[McpTool(name: 'search_products')]
    public function searchProducts(
        string $query,
        ?string $category = null,
        int $limit = 10
    ): array {
        return Product::search($query)
            ->when($category, fn($q) => $q->where('category', $category))
            ->limit($limit)
            ->get()
            ->toArray();
    }

    #[McpResource(uri: 'config://store/settings', mimeType: 'application/json')]
    public function getStoreSettings(): array
    {
        return [
            'currency' => config('store.currency'),
            'tax_rate' => config('store.tax_rate'),
            'shipping_zones' => config('store.shipping_zones'),
        ];
    }
}
```

### Content Management

```php
class ContentService
{
    #[McpResourceTemplate(uriTemplate: 'post://{slug}', mimeType: 'text/markdown')]
    public function getPostContent(string $slug): string
    {
        return Post::where('slug', $slug)
            ->firstOrFail()
            ->markdown_content;
    }

    #[McpPrompt(name: 'content_summary')]
    public function generateContentSummary(string $postSlug, int $maxWords = 50): array
    {
        $post = Post::where('slug', $postSlug)->firstOrFail();
        
        return [
            [
                'role' => 'user',
                'content' => "Summarize this blog post in {$maxWords} words or less:\n\n{$post->content}"
            ]
        ];
    }
}
```

### API Integration

```php
class ApiService
{
    #[McpTool(name: 'send_notification')]
    public function sendNotification(
        #[Schema(format: 'email')]
        string $email,
        
        string $subject,
        string $message
    ): array {
        $response = Http::post('https://api.emailservice.com/send', [
            'to' => $email,
            'subject' => $subject,
            'body' => $message,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Failed to send notification: ' . $response->body());
        }

        return $response->json();
    }
}
```

## Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

### Development Setup

```bash
# Clone the repository
git clone https://github.com/php-mcp/laravel.git
cd laravel

# Install dependencies
composer install

# Run tests
./vendor/bin/pest

# Check code style
./vendor/bin/pint
```

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.

## Acknowledgments

- Built on the [Model Context Protocol](https://modelcontextprotocol.io/) specification
- Powered by [`php-mcp/server`](https://github.com/php-mcp/server) for core MCP functionality
- Leverages [Laravel](https://laravel.com/) framework features for seamless integration
- Uses [ReactPHP](https://reactphp.org/) for high-performance async operations
