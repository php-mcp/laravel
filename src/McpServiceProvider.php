<?php

declare(strict_types=1);

namespace PhpMcp\Laravel\Server;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use PhpMcp\Laravel\Server\Adapters\ConfigAdapter;
use PhpMcp\Laravel\Server\Commands\DiscoverCommand;
use PhpMcp\Laravel\Server\Commands\ListCommand;
use PhpMcp\Laravel\Server\Commands\ServeCommand;
use PhpMcp\Laravel\Server\Events\PromptsListChanged;
use PhpMcp\Laravel\Server\Events\ResourcesListChanged;
use PhpMcp\Laravel\Server\Events\ToolsListChanged;
use PhpMcp\Laravel\Server\Listeners\McpNotificationListener;
use PhpMcp\Server\Processor;
use PhpMcp\Server\Registry;
use PhpMcp\Server\Server;
use PhpMcp\Server\State\TransportState;
use PhpMcp\Server\Transports\HttpTransportHandler;
use PhpMcp\Server\Transports\StdioTransportHandler;

class McpServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        ToolsListChanged::class => [
            McpNotificationListener::class,
        ],
        ResourcesListChanged::class => [
            McpNotificationListener::class,
        ],
        PromptsListChanged::class => [
            McpNotificationListener::class,
        ],
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mcp.php', 'mcp');

        $this->app->singleton(Server::class, function (Application $app) {
            $config = $app['config'];

            $mcpConfig = new ConfigAdapter($config);
            $cacheStore = $app['cache']->store($config->get('mcp.cache.store'));
            $logger = $app['log']->channel($config->get('mcp.logging.channel'));

            $server = Server::make()
                ->withContainer($app)
                ->withConfig($mcpConfig)
                ->withBasePath(base_path())
                ->withLogger($logger)
                ->withCache($cacheStore);

            if (! $this->app->environment('production')) {
                $server->discover();
            }

            return $server;
        });

        $this->app->bind(Processor::class, fn (Application $app) => $app->make(Server::class)->getProcessor());
        $this->app->bind(Registry::class, fn (Application $app) => $app->make(Server::class)->getRegistry());
        $this->app->bind(TransportState::class, fn (Application $app) => $app->make(Server::class)->getStateManager());

        $this->app->bind(HttpTransportHandler::class, function (Application $app) {
            return new HttpTransportHandler(
                $app->make(Processor::class),
                $app->make(TransportState::class),
                $app['log']
            );
        });

        $this->app->bind(StdioTransportHandler::class, function (Application $app) {
            return new StdioTransportHandler(
                $app->make(Processor::class),
                $app->make(TransportState::class),
                $app['log']
            );
        });

        $this->app->afterResolving(Registry::class, function (Registry $registry) {
            $registry->setToolsChangedNotifier(fn () => ToolsListChanged::dispatch());
            $registry->setResourcesChangedNotifier(fn () => ResourcesListChanged::dispatch());
            $registry->setPromptsChangedNotifier(fn () => PromptsListChanged::dispatch());
        });
    }

    public function boot(): void
    {
        $this->bootConfig();
        $this->bootRoutes();
        $this->bootCommands();
    }

    protected function bootConfig(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/mcp.php' => config_path('mcp.php')], 'mcp-config');
        }
    }

    protected function bootRoutes(): void
    {
        $config = $this->app['config'];
        if ($config->get('mcp.transports.http.enabled', true)) {
            $prefix = $config->get('mcp.transports.http.prefix', 'mcp');
            $middleware = $config->get('mcp.transports.http.middleware', ['web']);
            $domain = $config->get('mcp.transports.http.domain');

            Route::group([
                'domain' => $domain,
                'prefix' => $prefix,
                'middleware' => $middleware,
            ], function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/mcp.php');
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
}
