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
use PhpMcp\Schema\ServerCapabilities;
use PhpMcp\Server\Registry;
use PhpMcp\Server\Server;
use PhpMcp\Server\Session\SessionManager;
use PhpMcp\Server\Contracts\SessionHandlerInterface;
use PhpMcp\Server\Session\ArraySessionHandler;
use PhpMcp\Server\Session\CacheSessionHandler;
use PhpMcp\Laravel\Session\DatabaseSessionHandler;
use PhpMcp\Laravel\Session\FileSessionHandler;

class McpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/mcp.php', 'mcp');

        $this->app->singleton(McpRegistrar::class, fn() => new McpRegistrar());

        $this->app->alias(McpRegistrar::class, 'mcp.registrar');
    }

    public function boot(): void
    {
        $this->loadMcpDefinitions();
        $this->buildServer();
        $this->bootPublishables();
        $this->bootRoutes();
        $this->bootEvents();
        $this->bootCommands();
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
            $capabilities = ServerCapabilities::make(
                tools: (bool) config('mcp.capabilities.tools', true),
                toolsListChanged: (bool) config('mcp.capabilities.toolsListChanged', true),
                resources: (bool) config('mcp.capabilities.resources', true),
                resourcesSubscribe: (bool) config('mcp.capabilities.resourcesSubscribe', true),
                resourcesListChanged: (bool) config('mcp.capabilities.resourcesListChanged', true),
                prompts: (bool) config('mcp.capabilities.prompts', true),
                promptsListChanged: (bool) config('mcp.capabilities.promptsListChanged', true),
                logging: (bool) config('mcp.capabilities.logging', true),
                completions: (bool) config('mcp.capabilities.completions', true),
                experimental: config('mcp.capabilities.experimental', null),
            );

            $sessionHandler = $this->createSessionHandler($app);
            $sessionTtl = (int) config('mcp.session.ttl', 3600);

            $builder = Server::make()
                ->withServerInfo($serverName, $serverVersion)
                ->withLogger($logger)
                ->withContainer($app)
                ->withCache($cache)
                ->withSessionHandler($sessionHandler, $sessionTtl)
                ->withCapabilities($capabilities)
                ->withPaginationLimit((int) config('mcp.pagination_limit', 50))
                ->withInstructions(config('mcp.server.instructions'));

            $registrar = $app->make(McpRegistrar::class);
            $registrar->applyBlueprints($builder);

            $server = $builder->build();
            $registry = $server->getRegistry();

            if (config('mcp.discovery.auto_discover', true)) {
                $registry->disableNotifications();

                $server->discover(
                    basePath: config('mcp.discovery.base_path', base_path()),
                    scanDirs: config('mcp.discovery.directories', ['app/Mcp']),
                    excludeDirs: config('mcp.discovery.exclude_dirs', []),
                    saveToCache: config('mcp.discovery.save_to_cache', true)
                );

                $registry->enableNotifications();
            }

            return $server;
        });

        $this->app->singleton(Registry::class, fn($app) => $app->make(Server::class)->getRegistry());
        $this->app->singleton(SessionManager::class, fn($app) => $app->make(Server::class)->getSessionManager());

        $this->app->alias(Server::class, 'mcp.server');
        $this->app->alias(Registry::class, 'mcp.registry');
    }

    /**
     * Create appropriate session handler based on configuration.
     */
    private function createSessionHandler(Application $app): SessionHandlerInterface
    {
        $driver = config('mcp.session.driver', 'cache');
        $ttl = (int) config('mcp.session.ttl', 3600);

        return match ($driver) {
            'array' => new ArraySessionHandler($ttl),

            'cache', 'redis', 'memcached', 'dynamodb' => new CacheSessionHandler(
                $app['cache']->store(config('mcp.session.store', config('cache.default'))),
                $ttl
            ),

            'file' => new FileSessionHandler(
                $app['files'],
                config('mcp.session.path', storage_path('framework/mcp_sessions')),
                $ttl
            ),

            'database' => new DatabaseSessionHandler(
                $app['db']->connection(config('mcp.session.connection')),
                config('mcp.session.table', 'mcp_sessions'),
                $ttl
            ),

            default => throw new \InvalidArgumentException("Unsupported MCP session driver: {$driver}")
        };
    }

    protected function bootPublishables(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__ . '/../config/mcp.php' => config_path('mcp.php')], 'mcp-config');

            $this->publishes([
                __DIR__ . '/../database/migrations/create_mcp_sessions_table.php' => database_path('migrations/' . date('Y_m_d_His', time()) . '_create_mcp_sessions_table.php'),
            ], 'mcp-migrations');
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
                $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
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
}
