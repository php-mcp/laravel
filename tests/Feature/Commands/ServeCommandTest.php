<?php

namespace PhpMcp\Laravel\Tests\Feature\Commands;

use PhpMcp\Laravel\Tests\TestCase;
use PhpMcp\Server\Server;
use PhpMcp\Server\Transports\HttpServerTransport;
use PhpMcp\Server\Transports\StdioServerTransport;
use Mockery;
use Orchestra\Testbench\Attributes\DefineEnvironment;

class ServeCommandTest extends TestCase
{
    protected function disableAutoDiscovery($app)
    {
        $app['config']->set('mcp.discovery.auto_discover', false);
    }

    protected function setHttpDedicatedTransportConfig($app)
    {
        $app['config']->set('mcp.transports.http_dedicated.enabled', true);
        $app['config']->set('mcp.transports.http_dedicated.host', '0.0.0.0');
        $app['config']->set('mcp.transports.http_dedicated.port', 8888);
        $app['config']->set('mcp.transports.http_dedicated.path_prefix', 'configured_prefix');
    }

    protected function disableStdioTransport($app)
    {
        $app['config']->set('mcp.transports.stdio.enabled', false);
    }

    protected function disableHttpDedicatedTransport($app)
    {
        $app['config']->set('mcp.transports.http_dedicated.enabled', false);
    }

    public function test_serve_command_defaults_to_stdio_and_calls_server_listen()
    {
        $serverMock = $this->spy(Server::class);
        $this->app->instance(Server::class, $serverMock);

        $serverMock->shouldReceive('listen')->once()->with(
            Mockery::type(StdioServerTransport::class)
        );

        $this->artisan('mcp:serve --transport=stdio')
            ->expectsOutputToContain('Starting MCP server with STDIO transport...')
            ->assertSuccessful();
    }

    public function test_serve_command_uses_http_transport_when_specified()
    {
        $serverMock = $this->spy(Server::class);
        $this->app->instance(Server::class, $serverMock);

        $serverMock->shouldReceive('listen')->once()->with(
            Mockery::type(HttpServerTransport::class),
        );

        $this->artisan('mcp:serve --transport=http --host=localhost --port=9091 --path-prefix=mcp_test_http')
            ->expectsOutputToContain('Starting MCP server with dedicated HTTP transport on http://localhost:9091 (prefix: /mcp_test_http)...')
            ->assertSuccessful();
    }

    #[DefineEnvironment('setHttpDedicatedTransportConfig')]
    public function test_serve_command_uses_http_transport_config_fallbacks()
    {
        $serverMock = $this->spy(Server::class);
        $this->app->instance(Server::class, $serverMock);

        $serverMock->shouldReceive('listen')->once()->with(
            Mockery::on(function ($transport) {
                $reflection = new \ReflectionClass($transport);
                $hostProp = $reflection->getProperty('host');
                $hostProp->setAccessible(true);
                $portProp = $reflection->getProperty('port');
                $portProp->setAccessible(true);
                $prefixProp = $reflection->getProperty('mcpPathPrefix');
                $prefixProp->setAccessible(true);

                return $transport instanceof HttpServerTransport &&
                    $hostProp->getValue($transport) === '0.0.0.0' &&
                    $portProp->getValue($transport) === 8888 &&
                    $prefixProp->getValue($transport) === 'configured_prefix';
            }),
        );

        $this->artisan('mcp:serve --transport=http') // No CLI overrides
            ->expectsOutputToContain('Starting MCP server with dedicated HTTP transport on http://0.0.0.0:8888 (prefix: /configured_prefix)...')
            ->assertSuccessful();
    }

    #[DefineEnvironment('disableStdioTransport')]
    public function test_serve_command_fails_if_stdio_disabled_in_config()
    {
        $this->artisan('mcp:serve --transport=stdio')
            ->expectsOutputToContain('MCP STDIO transport is disabled in config/mcp.php.')
            ->assertFailed();
    }

    #[DefineEnvironment('disableHttpDedicatedTransport')]
    public function test_serve_command_fails_if_http_dedicated_disabled_in_config()
    {
        $this->artisan('mcp:serve --transport=http')
            ->expectsOutputToContain('Dedicated MCP HTTP transport is disabled in config/mcp.php.')
            ->assertFailed();
    }

    public function test_serve_command_fails_for_invalid_transport_option()
    {
        $this->artisan('mcp:serve --transport=websocket')
            ->expectsOutputToContain("Invalid transport specified: websocket. Use 'stdio' or 'http'.")
            ->assertFailed();
    }

    public function test_serve_command_handles_server_listen_exception()
    {
        $serverMock = $this->mock(Server::class, function ($mock) {
            $mock->shouldReceive('listen')->andThrow(new \RuntimeException("Simulated listen failure!"));
            $mock->shouldIgnoreMissing();
        });
        $this->app->instance(Server::class, $serverMock);


        $this->artisan('mcp:serve --transport=stdio')
            ->expectsOutputToContain('Simulated listen failure!')
            ->assertFailed();
    }
}
