<?php

namespace PhpMcp\Laravel\Tests\Feature;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PhpMcp\Laravel\McpServiceProvider;
use PhpMcp\Laravel\Events\ToolsListChanged;
use PhpMcp\Laravel\McpRegistrar;
use PhpMcp\Laravel\Tests\Stubs\App\Mcp\ManualTestHandler;
use PhpMcp\Laravel\Tests\TestCase;
use PhpMcp\Laravel\Transports\LaravelHttpTransport;
use PhpMcp\Server\Definitions\ToolDefinition;
use PhpMcp\Server\Protocol;
use PhpMcp\Server\Registry;
use PhpMcp\Server\Server;
use PhpMcp\Server\State\ClientStateManager;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use React\EventLoop\LoopInterface;

class McpServiceProviderTest extends TestCase
{
    protected function useTestServerConfig($app)
    {
        $app['config']->set('mcp.server.name', 'My Awesome MCP Test Server');
        $app['config']->set('mcp.server.version', 'v2.test');
        $app['config']->set('mcp.server.instructions', 'Test instructions from config.');
        $app['config']->set('mcp.cache.ttl', 7200);
    }

    protected function disableAutoDiscovery($app)
    {
        $app['config']->set('mcp.discovery.auto_discover', false);
    }

    protected function disableHttpIntegratedRoutes($app)
    {
        $app['config']->set('mcp.transports.http_integrated.enabled', false);
    }

    public function test_provider_is_registered_and_boots_core_server_and_components()
    {
        $providers = $this->app->getLoadedProviders();
        $this->assertArrayHasKey(McpServiceProvider::class, $providers);
        $this->assertTrue($providers[McpServiceProvider::class]);

        $server1 = $this->app->make('mcp.server');
        $this->assertInstanceOf(Server::class, $server1);

        $server2 = $this->app->make(Server::class);
        $this->assertSame($server1, $server2, "Server should be a singleton.");

        $this->assertInstanceOf(Registry::class, $server1->getRegistry());
        $this->assertInstanceOf(Protocol::class, $server1->getProtocol());
        $this->assertInstanceOf(ClientStateManager::class, $server1->getClientStateManager());
        $this->assertInstanceOf(McpRegistrar::class, $this->app->make('mcp.registrar'));
        $this->assertInstanceOf(LaravelHttpTransport::class, $this->app->make(LaravelHttpTransport::class));

        $configVO = $server1->getConfiguration();
        $this->assertInstanceOf(LoggerInterface::class, $configVO->logger);
        $this->assertInstanceOf(LoopInterface::class, $configVO->loop);
        $this->assertInstanceOf(CacheInterface::class, $configVO->cache);
        $this->assertInstanceOf(Container::class, $configVO->container);
    }

    #[DefineEnvironment('useTestServerConfig')]
    public function test_configuration_values_are_correctly_applied_to_server()
    {
        $server = $this->app->make('mcp.server');
        $configVO = $server->getConfiguration();

        $this->assertEquals('My Awesome MCP Test Server', $configVO->serverName);
        $this->assertEquals('v2.test', $configVO->serverVersion);
        $this->assertEquals('Test instructions from config.', $configVO->capabilities->instructions);
        $this->assertEquals(7200, $configVO->definitionCacheTtl);
        $this->assertTrue($configVO->capabilities->promptsEnabled);
    }

    public function test_auto_discovery_is_triggered_when_enabled()
    {
        $server = $this->app->make('mcp.server');
        $registry = $server->getRegistry();
        $this->assertNotNull($registry->findTool('stub_tool_one'), "Discovered tool 'stub_tool_one' not found in registry.");
    }

    #[DefineEnvironment('disableAutoDiscovery')]
    public function test_auto_discovery_is_skipped_if_disabled()
    {
        $server = $this->app->make('mcp.server');
        $registry = $server->getRegistry();

        $this->assertNull($registry->findTool('stub_tool_one'), "Tool 'stub_tool_one' should not be found if auto-discovery is off.");
    }

    public function test_event_notifiers_are_set_on_core_registry_and_dispatch_laravel_events()
    {
        Event::fake();

        $server = $this->app->make('mcp.server');
        $registry = $server->getRegistry();

        $newToolName = 'dynamic_tool_for_event_test';
        $this->assertNull($registry->findTool($newToolName));

        $registry->registerTool(
            new ToolDefinition(ManualTestHandler::class, 'handleTool', $newToolName, 'd', [])
        );

        Event::assertDispatched(ToolsListChanged::class);
    }

    public function test_http_integrated_routes_are_registered_if_enabled()
    {
        $this->assertTrue(Route::has('mcp.sse'));
        $this->assertTrue(Route::has('mcp.message'));
        $this->assertStringContainsString('/mcp/sse', route('mcp.sse'));
    }

    #[DefineEnvironment('disableHttpIntegratedRoutes')]
    public function test_http_integrated_routes_are_not_registered_if_disabled()
    {
        $this->assertFalse(Route::has('mcp.sse'));
        $this->assertFalse(Route::has('mcp.message'));
    }
}
