<?php

namespace PhpMcp\Laravel\Tests\Feature;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PhpMcp\Laravel\McpServiceProvider;
use PhpMcp\Laravel\McpRegistrar;
use PhpMcp\Laravel\Tests\TestCase;
use PhpMcp\Laravel\Transports\StreamableHttpServerTransport;
use PhpMcp\Server\Protocol;
use PhpMcp\Server\Registry;
use PhpMcp\Server\Server;
use PhpMcp\Server\Session\SessionManager;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

class McpServiceProviderTest extends TestCase
{
    protected function useTestServerConfig($app)
    {
        $app['config']->set('mcp.server.name', 'My Awesome MCP Test Server');
        $app['config']->set('mcp.server.version', 'v2.test');
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
        $this->assertInstanceOf(SessionManager::class, $server1->getSessionManager());
        $this->assertInstanceOf(McpRegistrar::class, $this->app->make('mcp.registrar'));
        $this->assertInstanceOf(StreamableHttpServerTransport::class, $this->app->make(StreamableHttpServerTransport::class));

        $configVO = $server1->getConfiguration();
        $this->assertInstanceOf(LoggerInterface::class, $configVO->logger);
        $this->assertInstanceOf(CacheInterface::class, $configVO->cache);
        $this->assertInstanceOf(Container::class, $configVO->container);
    }

    #[DefineEnvironment('useTestServerConfig')]
    public function test_configuration_values_are_correctly_applied_to_server()
    {
        $server = $this->app->make('mcp.server');
        $configVO = $server->getConfiguration();

        $this->assertEquals('My Awesome MCP Test Server', $configVO->serverInfo->name);
        $this->assertEquals('v2.test', $configVO->serverInfo->version);
        $this->assertEquals(50, $configVO->paginationLimit);
        $this->assertTrue($configVO->capabilities->prompts->listChanged ?? true);
    }

    public function test_auto_discovery_is_triggered_when_enabled()
    {
        $server = $this->app->make('mcp.server');
        $registry = $server->getRegistry();
        $this->assertNotNull($registry->getTool('stub_tool_one'), "Discovered tool 'stub_tool_one' not found in registry.");
    }

    #[DefineEnvironment('disableAutoDiscovery')]
    public function test_auto_discovery_is_skipped_if_disabled()
    {
        $server = $this->app->make('mcp.server');
        $registry = $server->getRegistry();

        $this->assertNull($registry->getTool('stub_tool_one'), "Tool 'stub_tool_one' should not be found if auto-discovery is off.");
    }

    public function test_http_integrated_routes_are_registered_if_enabled()
    {
        $this->assertTrue(Route::has('mcp.streamable.get'));
        $this->assertTrue(Route::has('mcp.streamable.post'));
        $this->assertTrue(Route::has('mcp.streamable.delete'));
        $this->assertStringContainsString('/mcp', route('mcp.streamable.get'));
    }

    #[DefineEnvironment('disableHttpIntegratedRoutes')]
    public function test_http_integrated_routes_are_not_registered_if_disabled()
    {
        $this->assertFalse(Route::has('mcp.streamable.get'));
        $this->assertFalse(Route::has('mcp.streamable.post'));
        $this->assertFalse(Route::has('mcp.streamable.delete'));
    }
}
