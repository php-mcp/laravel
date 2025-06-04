# Changelog

All notable changes to `php-mcp/laravel` will be documented in this file.

## v1.1.1 - 2025-05-12

### What's Changed
* McpServiceProvider File And loadElements function are not found by @tsztodd in https://github.com/php-mcp/laravel/pull/2

### New Contributors
* @tsztodd made their first contribution in https://github.com/php-mcp/laravel/pull/2

**Full Changelog**: https://github.com/php-mcp/laravel/compare/1.1.0...1.1.1

## v1.1.0 - 2025-05-01

This release updates the package for compatibility with `php-mcp/server` v1.1.0.

### What Changed

*   Updated dependency requirement to `php-mcp/server: ^1.1.0`.
*   Modified `McpServiceProvider` to correctly provide `ConfigurationRepositoryInterface`, `LoggerInterface`, and `CacheInterface` bindings to the underlying `Server` instance when resolved from the Laravel container.
*   Updated `ServeCommand` and `McpController` to inject the `Server` instance and instantiate `TransportHandler` classes according to `php-mcp/server` v1.1.0 constructor changes.

### Fixed

*   Ensures compatibility with the refactored dependency injection and transport handler instantiation logic in `php-mcp/server` v1.1.0.

**Full Changelog**: https://github.com/php-mcp/laravel/compare/1.0.0...1.1.0

# Release v1.0.0 - Initial Release

**Initial Release**

Welcome to the first release of `php-mcp/laravel`! This package provides seamless integration of the core [`php-mcp/server`](https://github.com/php-mcp/server) package with your Laravel application, allowing you to expose application functionality as Model Context Protocol (MCP) tools, resources, and prompts using simple PHP attributes.

## Key Features

*   **Effortless Integration:** Automatically wires up Laravel's Cache, Logger, and Service Container for use by the MCP server.
*   **Attribute-Based Definition:** Define MCP tools, resources, and prompts using PHP attributes (`#[McpTool]`, `#[McpResource]`, etc.) within your Laravel application structure. Leverage Laravel's Dependency Injection within your MCP element classes.
*   **Configuration:** Provides a publishable configuration file (`config/mcp.php`) for fine-grained control over discovery, transports, caching, and capabilities.
*   **Artisan Commands:** Includes commands for element discovery (`mcp:discover`), listing discovered elements (`mcp:list`), and running the server via stdio (`mcp:serve`).
*   **HTTP+SSE Transport:** Sets up routes (`/mcp/message`, `/mcp/sse` by default) and a controller for handling MCP communication over HTTP, enabling browser-based clients and other HTTP consumers.
*   **Automatic Discovery (Dev):** Automatically discovers MCP elements in development environments on first use, improving developer experience (no need to manually run `mcp:discover` after changes).
*   **Manual Discovery (Prod):** Requires manual discovery (`mcp:discover`) for production environments, ensuring optimal performance via caching (integrates well with deployment workflows).
*   **Event Integration:** Dispatches Laravel events (`ToolsListChanged`, `ResourcesListChanged`, `PromptsListChanged`) when element lists change, allowing for custom integrations or notifications.

## Installation

Installation is straightforward using Composer. See the [README Installation Guide](https://github.com/php-mcp/laravel/blob/main/README.md#installation) for full details.

```bash
# 1. Require the package
composer require php-mcp/laravel

# 2. Publish the configuration file (optional but recommended)
php artisan vendor:publish --provider="PhpMcp\Laravel\Server\McpServiceProvider" --tag="mcp-config"
```

## Getting Started

1.  **Define Elements:** Create PHP classes with methods annotated with `#[McpTool]`, `#[McpResource]`, etc., within directories specified in `config/mcp.php` (e.g., `app/Mcp`). Inject dependencies as needed. See [Defining MCP Elements](https://github.com/php-mcp/laravel/blob/main/README.md#defining-mcp-elements).
2.  **Discovery:**
    *   In development, discovery runs automatically when needed.
    *   In production, run `php artisan mcp:discover` during your deployment process. See [Automatic Discovery vs. Manual Discovery](https://github.com/php-mcp/laravel/blob/main/README.md#automatic-discovery-development-vs-manual-discovery-production).
3.  **Run the Server:**
    *   For **Stdio Transport:** Use `php artisan mcp:serve` and configure your client to execute this command (using the full path to `artisan`).
    *   For **HTTP+SSE Transport:** Ensure `transports.http.enabled` is true, run your Laravel app on a suitable web server (Nginx+FPM, Octane, etc. - **not** `php artisan serve`), exclude the MCP route from CSRF protection, and configure your client with the SSE URL (e.g., `http://your-app.test/mcp/sse`). See [Running the Server](https://github.com/php-mcp/laravel/blob/main/README.md#running-the-server) for critical details.

## Important Notes

*   **HTTP Transport Server Requirement:** The standard `php artisan serve` development server is **not suitable** for the HTTP+SSE transport due to its single-process nature. Use a proper web server setup like Nginx/Apache + PHP-FPM or Laravel Octane.
*   **CSRF Exclusion:** If using the default `web` middleware group for the HTTP transport, you **must** exclude the MCP message route (default: `mcp` or `mcp/*`) from CSRF protection in your application to avoid `419` errors.
*   **Dependencies:** Requires PHP >= 8.1 and Laravel >= 10.0.

## Links

*   **GitHub Repository:** https://github.com/php-mcp/laravel
*   **Packagist:** https://packagist.org/packages/php-mcp/laravel

Please report any issues or provide feedback on the GitHub repository.