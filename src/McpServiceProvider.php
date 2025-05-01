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
use PhpMcp\Server\Contracts\ConfigurationRepositoryInterface;
use PhpMcp\Server\Server;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

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
            $server = Server::make()
                ->withContainer($app)
                ->withBasePath(base_path())
                ->withScanDirectories($app['config']->get('mcp.discovery.directories', ['app/Mcp']));

            if (! $this->app->environment('production')) {
                $server->discover();
            }

            $registry = $server->getRegistry();

            $registry->setToolsChangedNotifier(fn () => ToolsListChanged::dispatch());
            $registry->setResourcesChangedNotifier(fn () => ResourcesListChanged::dispatch());
            $registry->setPromptsChangedNotifier(fn () => PromptsListChanged::dispatch());

            return $server;
        });

        $this->app->bind(ConfigurationRepositoryInterface::class, fn (Application $app) => new ConfigAdapter($app['config']));
        $this->app->bind(LoggerInterface::class, fn (Application $app) => $app['log']->channel($app['config']->get('mcp.logging.channel')));
        $this->app->bind(CacheInterface::class, fn (Application $app) => $app['cache']->store($app['config']->get('mcp.cache.store')));
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
