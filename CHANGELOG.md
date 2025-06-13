# Changelog

All notable changes to `php-mcp/laravel` will be documented in this file.

## v2.1.0 - 2025-06-13

### What's Changed

* Update README.md by @taylorotwell in https://github.com/php-mcp/laravel/pull/5
* [docs] Fix publish config command for 2.x by @barryvdh in https://github.com/php-mcp/laravel/pull/7
* [docs] Remove config call from app/bootstrap.php by @barryvdh in https://github.com/php-mcp/laravel/pull/8
* Do not defer ServiceProvider to boot routes by @barryvdh in https://github.com/php-mcp/laravel/pull/9
* Fix: Correct Client State Management in LaravelHttpTransport by @CodeWithKyrian in https://github.com/php-mcp/laravel/pull/17
* chore: Update dependencies and improve MCP notification handling by @CodeWithKyrian in https://github.com/php-mcp/laravel/pull/18
* docs: transport should be explicitly set to stdio for the tool to start by @xel1045 in https://github.com/php-mcp/laravel/pull/15

### New Contributors

* @taylorotwell made their first contribution in https://github.com/php-mcp/laravel/pull/5
* @barryvdh made their first contribution in https://github.com/php-mcp/laravel/pull/7
* @xel1045 made their first contribution in https://github.com/php-mcp/laravel/pull/15

**Full Changelog**: https://github.com/php-mcp/laravel/compare/2.0.0...2.1.0

## v2.0.0 - 2025-06-04

This release marks a **major overhaul**, bringing it into full alignment with `php-mcp/server` v2.1.0+ and introducing a significantly improved, more "Laravely" developer experience.

### Added

* **Fluent Manual Registration API:**
  
  * Introduced the `Mcp` Facade (`PhpMcp\Laravel\Facades\Mcp`).
  * Define Tools, Resources, Prompts, and Resource Templates fluently (e.g., `Mcp::tool(...)->description(...)`).
  * Definitions are typically placed in `routes/mcp.php` (configurable).
  * Handlers are resolved via Laravel's service container, allowing dependency injection.
  
* **Dedicated HTTP Server Transport via `mcp:serve`:**
  
  * The `php artisan mcp:serve --transport=http` command now launches a standalone, high-performance ReactPHP-based HTTP server using `\PhpMcp\Server\Transports\HttpServerTransport`.
  * Configuration for this dedicated server is in `config/mcp.php` under `transports.http_dedicated`.
  * CLI options (`--host`, `--port`, `--path-prefix`) can override config defaults.
  
* **`LaravelHttpTransport` for Integrated HTTP:**
  
  * New `PhpMcp\Laravel\Transports\LaravelHttpTransport` class implements `ServerTransportInterface` to bridge Laravel's HTTP request lifecycle with the core MCP `Protocol` handler.
  
* **Configurable Auto-Discovery:**
  
  * `config('mcp.discovery.auto_discover')` (default: `true`) now controls whether discovery runs automatically or not. You can set it to false in production..
  
* **Interactive Prompt for `mcp:serve`:** If `--transport` is not specified, the command now interactively prompts the user to choose between `stdio` and `http`.
  

### Changed

* **Core Server Integration:** Now uses `\PhpMcp\Server\Server::make()` (ServerBuilder) for all server instantiation, fully leveraging `php-mcp/server` v2.x architecture.
  
* **Namespace:** Base package namespace changed from `PhpMcp\Laravel\Server` to **`PhpMcp\Laravel`**.
  
* **Configuration (`config/mcp.php`):**
  
  * Significantly restructured and updated to align with `ServerBuilder` options.
  * Clearer separation of settings for `http_dedicated` vs. `http_integrated` transports.
  * Simplified cache TTL (`cache.ttl`) and discovery (`discovery.save_to_cache_on_discover`) keys.
  * Added `server.instructions` for the `initialize` MCP response.
  * Added `discovery.exclude_dirs` and `discovery.definitions_file`.
  
* **`McpServiceProvider`:**
  
  * Completely rewritten to correctly build and configure the `\PhpMcp\Server\Server` instance using Laravel's services for logging, caching (with fallback to core `FileCache`), container, and event loop.
  * Loads manual definitions from the configured `definitions_file` via `McpRegistrar`.
  * Sets up core `Registry` notifiers to dispatch Laravel events for list changes.
  
* **`McpController` (Integrated HTTP):** More robustly handles the integrated server behavior, working with a custom `LaravelHttpTransport`.
  
* **Artisan Commands:**
  
  * `mcp:discover`: Now directly calls `Server::discover()` with configured/CLI parameters. `force` option behavior clarified.
  * `mcp:list`: Fetches elements from the live, fully configured `Registry` from the resolved `Server` instance.
  * `mcp:serve`: Refactored to use core `StdioServerTransport` or `HttpServerTransport` directly.
  
* **Dependency:** Updated `php-mcp/server` to `^2.2.0`
  

### Fixed

* More robust error handling and logging in Artisan commands and `McpController`.
* Improved clarity and consistency in how core server components are resolved and used within the Laravel context.

### Removed

* `PhpMcp\Laravel\Server\Adapters\ConfigAdapter`: No longer needed due to changes in `php-mcp/server` v2.x.

### BREAKING CHANGES

* **Namespace Change:** The primary package namespace has changed from `PhpMcp\Laravel\Server` to `PhpMcp\Laravel`. Update all `use` statements and FQCN references in your application. You may have to uninstall and reinstall the package to avoid conflicts.
* **Configuration File:** The `config/mcp.php` file has been significantly restructured. You **must** republish and merge your customizations:
    ```bash
    php artisan vendor:publish --provider="PhpMcp\Laravel\McpServiceProvider" --tag="mcp-config" --force
  
  
    ```
* **`mcp:serve` for HTTP:** The `--transport=http` option for `mcp:serve` now launches a *dedicated* ReactPHP-based server process. For serving MCP via your main Laravel application routes, ensure the `http_integrated` transport is enabled in `config/mcp.php` and your web server is configured appropriately.
* **Event Handling:** If you were directly listening to internal events from the previous version, these may have changed. Rely on the documented Laravel events (`ToolsListChanged`, etc.).
* **Removed Classes:** `PhpMcp\Laravel\Server\Adapters\ConfigAdapter` is removed.

**Full Changelog**: https://github.com/php-mcp/laravel/compare/1.1.1...2.0.0

## v1.1.1 - 2025-05-12

### What's Changed

* McpServiceProvider File And loadElements function are not found by @tsztodd in https://github.com/php-mcp/laravel/pull/2

### New Contributors

* @tsztodd made their first contribution in https://github.com/php-mcp/laravel/pull/2

**Full Changelog**: https://github.com/php-mcp/laravel/compare/1.1.0...1.1.1

## v1.1.0 - 2025-05-01

This release updates the package for compatibility with `php-mcp/server` v1.1.0.

### What Changed

* Updated dependency requirement to `php-mcp/server: ^1.1.0`.
* Modified `McpServiceProvider` to correctly provide `ConfigurationRepositoryInterface`, `LoggerInterface`, and `CacheInterface` bindings to the underlying `Server` instance when resolved from the Laravel container.
* Updated `ServeCommand` and `McpController` to inject the `Server` instance and instantiate `TransportHandler` classes according to `php-mcp/server` v1.1.0 constructor changes.

### Fixed

* Ensures compatibility with the refactored dependency injection and transport handler instantiation logic in `php-mcp/server` v1.1.0.

**Full Changelog**: https://github.com/php-mcp/laravel/compare/1.0.0...1.1.0

# Release v1.0.0 - Initial Release

**Initial Release**

Welcome to the first release of `php-mcp/laravel`! This package provides seamless integration of the core [`php-mcp/server`](https://github.com/php-mcp/server) package with your Laravel application, allowing you to expose application functionality as Model Context Protocol (MCP) tools, resources, and prompts using simple PHP attributes.

## Key Features

* **Effortless Integration:** Automatically wires up Laravel's Cache, Logger, and Service Container for use by the MCP server.
* **Attribute-Based Definition:** Define MCP tools, resources, and prompts using PHP attributes (`#[McpTool]`, `#[McpResource]`, etc.) within your Laravel application structure. Leverage Laravel's Dependency Injection within your MCP element classes.
* **Configuration:** Provides a publishable configuration file (`config/mcp.php`) for fine-grained control over discovery, transports, caching, and capabilities.
* **Artisan Commands:** Includes commands for element discovery (`mcp:discover`), listing discovered elements (`mcp:list`), and running the server via stdio (`mcp:serve`).
* **HTTP+SSE Transport:** Sets up routes (`/mcp/message`, `/mcp/sse` by default) and a controller for handling MCP communication over HTTP, enabling browser-based clients and other HTTP consumers.
* **Automatic Discovery (Dev):** Automatically discovers MCP elements in development environments on first use, improving developer experience (no need to manually run `mcp:discover` after changes).
* **Manual Discovery (Prod):** Requires manual discovery (`mcp:discover`) for production environments, ensuring optimal performance via caching (integrates well with deployment workflows).
* **Event Integration:** Dispatches Laravel events (`ToolsListChanged`, `ResourcesListChanged`, `PromptsListChanged`) when element lists change, allowing for custom integrations or notifications.

## Installation

Installation is straightforward using Composer. See the [README Installation Guide](https://github.com/php-mcp/laravel/blob/main/README.md#installation) for full details.

```bash
# 1. Require the package
composer require php-mcp/laravel

# 2. Publish the configuration file (optional but recommended)
php artisan vendor:publish --provider="PhpMcp\Laravel\Server\McpServiceProvider" --tag="mcp-config"


```
## Getting Started

1. **Define Elements:** Create PHP classes with methods annotated with `#[McpTool]`, `#[McpResource]`, etc., within directories specified in `config/mcp.php` (e.g., `app/Mcp`). Inject dependencies as needed. See [Defining MCP Elements](https://github.com/php-mcp/laravel/blob/main/README.md#defining-mcp-elements).
   
2. **Discovery:**
   
   * In development, discovery runs automatically when needed.
   * In production, run `php artisan mcp:discover` during your deployment process. See [Automatic Discovery vs. Manual Discovery](https://github.com/php-mcp/laravel/blob/main/README.md#automatic-discovery-development-vs-manual-discovery-production).
   
3. **Run the Server:**
   
   * For **Stdio Transport:** Use `php artisan mcp:serve` and configure your client to execute this command (using the full path to `artisan`).
   * For **HTTP+SSE Transport:** Ensure `transports.http.enabled` is true, run your Laravel app on a suitable web server (Nginx+FPM, Octane, etc. - **not** `php artisan serve`), exclude the MCP route from CSRF protection, and configure your client with the SSE URL (e.g., `http://your-app.test/mcp/sse`). See [Running the Server](https://github.com/php-mcp/laravel/blob/main/README.md#running-the-server) for critical details.
   

## Important Notes

* **HTTP Transport Server Requirement:** The standard `php artisan serve` development server is **not suitable** for the HTTP+SSE transport due to its single-process nature. Use a proper web server setup like Nginx/Apache + PHP-FPM or Laravel Octane.
* **CSRF Exclusion:** If using the default `web` middleware group for the HTTP transport, you **must** exclude the MCP message route (default: `mcp` or `mcp/*`) from CSRF protection in your application to avoid `419` errors.
* **Dependencies:** Requires PHP >= 8.1 and Laravel >= 10.0.

## Links

* **GitHub Repository:** https://github.com/php-mcp/laravel
* **Packagist:** https://packagist.org/packages/php-mcp/laravel

Please report any issues or provide feedback on the GitHub repository.
