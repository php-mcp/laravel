<?php

declare(strict_types=1);

namespace PhpMcp\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use PhpMcp\Laravel\Commands\DiscoverCommand;
use PhpMcp\Laravel\Commands\ListCommand;
use PhpMcp\Laravel\Commands\ServeCommand;
use PhpMcp\Laravel\Events\PromptsListChanged;
use PhpMcp\Laravel\Events\ResourcesListChanged;
use PhpMcp\Laravel\Events\ToolsListChanged;
use PhpMcp\Laravel\Listeners\McpNotificationListener;
use PhpMcp\Laravel\Transports\LaravelHttpTransport;
use PhpMcp\Server\Model\Capabilities;
use PhpMcp\Server\Registry;
use PhpMcp\Server\Server;

class McpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/mcp.php', 'mcp');

        $this->app->singleton(McpRegistrar::class, fn() => new McpRegistrar());

        $this->app->alias(McpRegistrar::class, 'mcp.registrar');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            McpRegistrar::class,
            Server::class,
            Registry::class,
            LaravelHttpTransport::class,
        ];
    }

    public function boot(): void
    {
        $this->loadMcpDefinitions();
        $this->buildServer();
        $this->bootConfig();
        $this->bootRoutes();
        $this->bootEvents();
        $this->bootCommands();
        $this->bootEventListeners();
    }

    protected function loadMcpDefinitions(): void
    {
        $definitionsPath = config('mcp.discovery.definitions_file', base_path('routes/mcp.php'));
        if ($definitionsPath && file_exists($definitionsPath)) {
            require $definitionsPath;
        }
    }

    protected function buildServer(): void
    {
        $this->app->singleton(Server::class, function (Application $app) {
            $serverName = config('mcp.server.name', config('app.name', 'Laravel') . ' MCP Server');
            $serverVersion = config('mcp.server.version', '1.0.0');
            $logger = $app['log']->channel(config('mcp.logging.channel'));
            $cache = $app['cache']->store($app['config']->get('mcp.cache.store'));
            $capabilities = Capabilities::forServer(
                toolsEnabled: config('mcp.capabilities.tools.enabled', true),
                toolsListChanged: config('mcp.capabilities.tools.listChanged', true),
                resourcesEnabled: config('mcp.capabilities.resources.enabled', true),
                resourcesSubscribe: config('mcp.capabilities.resources.subscribe', true),
                resourcesListChanged: config('mcp.capabilities.resources.listChanged', true),
                promptsEnabled: config('mcp.capabilities.prompts.enabled', true),
                promptsListChanged: config('mcp.capabilities.prompts.listChanged', true),
                loggingEnabled: config('mcp.capabilities.logging.enabled', true),
                instructions: config('mcp.server.instructions')
            );

            $builder = Server::make()
                ->withServerInfo($serverName, $serverVersion)
                ->withLogger($logger)
                ->withContainer($app)
                ->withCache($cache, (int) config('mcp.cache.ttl', 3600))
                ->withCapabilities($capabilities);

            $registrar = $app->make(McpRegistrar::class);
            $registrar->applyBlueprints($builder);

            $server = $builder->build();

            if (config('mcp.discovery.auto_discover', true)) {
                $server->discover(
                    basePath: config('mcp.discovery.base_path', base_path()),
                    scanDirs: config('mcp.discovery.directories', ['app/Mcp']),
                    excludeDirs: config('mcp.discovery.exclude_dirs', []),
                    saveToCache: config('mcp.discovery.save_to_cache', true)
                );
            }

            return $server;
        });

        $this->app->singleton(Registry::class, fn($app) => $app->make(Server::class)->getRegistry());

        $this->app->alias(Server::class, 'mcp.server');
        $this->app->alias(Registry::class, 'mcp.registry');

        $this->app->singleton(LaravelHttpTransport::class, function (Application $app) {
            $server = $app->make(Server::class);

            return new LaravelHttpTransport($server->getClientStateManager());
        });
    }

    protected function bootConfig(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__ . '/../config/mcp.php' => config_path('mcp.php')], 'mcp-config');
        }
    }

    protected function bootRoutes(): void
    {
        if (config('mcp.transports.http_integrated.enabled', true)) {
            $routePrefix = config('mcp.transports.http_integrated.route_prefix', 'mcp');
            $middleware = config('mcp.transports.http_integrated.middleware', ['web']);
            $domain = config('mcp.transports.http_integrated.domain');

            Route::group([
                'domain' => $domain,
                'prefix' => $routePrefix,
                'middleware' => $middleware,
            ], function () {
                $this->loadRoutesFrom(__DIR__ . '/../routes/mcp_http_integrated.php');
            });
        }
    }

    protected function bootCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ServeCommand::class,
                DiscoverCommand::class,
                ListCommand::class,
            ]);
        }
    }

    protected function bootEvents(): void
    {
        Event::listen(
            [ToolsListChanged::class, ResourcesListChanged::class, PromptsListChanged::class],
            McpNotificationListener::class,
        );
    }

    protected function bootEventListeners(): void
    {
        $server = $this->app->make(Server::class);
        $registry = $server->getRegistry();

        $registry->setToolsChangedNotifier(ToolsListChanged::dispatch(...));
        $registry->setResourcesChangedNotifier(ResourcesListChanged::dispatch(...));
        $registry->setPromptsChangedNotifier(PromptsListChanged::dispatch(...));
    }
}
