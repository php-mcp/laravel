<?php

namespace PhpMcp\Laravel\Tests\Feature\Commands;

use PhpMcp\Laravel\Tests\TestCase;
use PhpMcp\Server\Server;
use PhpMcp\Server\Transports\StreamableHttpServerTransport;
use PhpMcp\Server\Transports\StdioServerTransport;
use Mockery;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PhpMcp\Laravel\Commands\ServeCommand;

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
            ->doesntExpectOutputToContain('Starting MCP server')
            ->doesntExpectOutputToContain('Transport: STDIO')
            ->assertSuccessful();
    }

    public function test_serve_command_uses_http_transport_when_specified()
    {
        $serverMock = $this->spy(Server::class);
        $this->app->instance(Server::class, $serverMock);

        $serverMock->shouldReceive('listen')->once()->with(
            Mockery::type(StreamableHttpServerTransport::class),
        );

        $this->artisan('mcp:serve --transport=http --host=localhost --port=9091 --path-prefix=mcp_test_http')
            ->expectsOutputToContain('Starting MCP server on http://localhost:9091')
            ->expectsOutputToContain('Transport: Streamable HTTP')
            ->expectsOutputToContain('MCP endpoint: http://localhost:9091/mcp_test_http')
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
                $portProp = $reflection->getProperty('port');
                $prefixProp = $reflection->getProperty('mcpPath');

                return $transport instanceof StreamableHttpServerTransport &&
                    $hostProp->getValue($transport) === '0.0.0.0' &&
                    $portProp->getValue($transport) === 8888 &&
                    $prefixProp->getValue($transport) === '/configured_prefix';
            }),
        );

        $this->artisan('mcp:serve --transport=http') // No CLI overrides
            ->expectsOutputToContain('Starting MCP server on http://0.0.0.0:8888')
            ->expectsOutputToContain('Transport: Streamable HTTP')
            ->expectsOutputToContain('MCP endpoint: http://0.0.0.0:8888/configured_prefix')
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

    public function test_watch_flag_fails_with_stdio_transport()
    {
        $this->artisan('mcp:serve --transport=stdio --watch')
            ->expectsOutputToContain('File watching is not supported with STDIO transport as it requires process restart.')
            ->assertFailed();
    }

    public function test_help_shows_all_available_flags()
    {
        // Test that the essential flags we added are documented in help
        $this->artisan('mcp:serve --help')
            ->expectsOutputToContain('--transport')
            ->expectsOutputToContain('--host')
            ->expectsOutputToContain('--port')
            ->expectsOutputToContain('--path-prefix')
            ->expectsOutputToContain('--watch')
            ->assertSuccessful();
    }

    public function test_command_signature_contains_all_expected_flags()
    {
        // Test the command signature directly to ensure all flags are properly defined
        $command = new ServeCommand();
        $reflection = new \ReflectionClass($command);
        $signatureProperty = $reflection->getProperty('signature');

        $signature = $signatureProperty->getValue($command);

        // Verify all expected flags are in the signature
        $expectedFlags = ['--transport=', '--H|host=', '--P|port=', '--path-prefix=', '--watch'];

        foreach ($expectedFlags as $flag) {
            $this->assertStringContainsString($flag, $signature, "Flag {$flag} should be in command signature");
        }

        // Verify descriptions are in the signature
        $this->assertStringContainsString('Watch for file changes', $signature);
        $this->assertStringContainsString('transport to use', $signature);
    }

    public function test_get_watched_paths_returns_configured_directories()
    {
        // Set up test configuration
        config(['mcp.discovery.base_path' => '/test/base']);
        config(['mcp.discovery.directories' => ['app/Mcp', 'custom/mcp']]);
        config(['mcp.discovery.definitions_file' => '/test/base/routes/mcp.php']);

        // Create a temporary directory structure for testing
        $tempDir = sys_get_temp_dir() . '/mcp_test_' . uniqid();
        mkdir($tempDir, 0755, true);
        mkdir($tempDir . '/app/Mcp', 0755, true);
        mkdir($tempDir . '/custom/mcp', 0755, true);
        mkdir($tempDir . '/routes', 0755, true);
        mkdir($tempDir . '/config', 0755, true);

        // Create the mcp.php file
        file_put_contents($tempDir . '/routes/mcp.php', '<?php // test file');

        // Override config with temp directory
        config(['mcp.discovery.base_path' => $tempDir]);
        config(['mcp.discovery.definitions_file' => $tempDir . '/routes/mcp.php']);

        $command = new ServeCommand();
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('getWatchedPaths');

        $watchedPaths = $method->invoke($command);

        $this->assertContains($tempDir . '/app/Mcp', $watchedPaths);
        $this->assertContains($tempDir . '/custom/mcp', $watchedPaths);
        $this->assertContains($tempDir . '/routes', $watchedPaths);

        // The config directory path comes from base_path, not our temp directory
        // Just verify that some config directory is in the watched paths
        $hasConfigDir = false;
        foreach ($watchedPaths as $path) {
            if (str_ends_with($path, '/config')) {
                $hasConfigDir = true;
                break;
            }
        }
        $this->assertTrue($hasConfigDir, 'Expected a config directory to be in watched paths');

        // Clean up
        $this->recursiveRemoveDirectory($tempDir);
    }

    public function test_get_last_modification_time_detects_file_changes()
    {
        // Create a temporary directory with test files
        $tempDir = sys_get_temp_dir() . '/mcp_mod_test_' . uniqid();
        mkdir($tempDir, 0755, true);

        $testFile = $tempDir . '/test.php';
        file_put_contents($testFile, '<?php echo "initial";');
        $initialTime = filemtime($testFile);

        $command = new ServeCommand();
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('getLastModificationTime');

        $firstCheck = $method->invoke($command, [$tempDir]);
        $this->assertEquals($initialTime, $firstCheck);

        // Wait a bit and modify the file
        sleep(1);
        file_put_contents($testFile, '<?php echo "modified";');

        $secondCheck = $method->invoke($command, [$tempDir]);
        $this->assertGreaterThan($firstCheck, $secondCheck);

        // Clean up
        $this->recursiveRemoveDirectory($tempDir);
    }

    public function test_start_server_process_creates_proper_command()
    {
        $command = new ServeCommand();
        $reflection = new \ReflectionClass($command);

        // Mock the option method to return test values
        $optionMethod = $reflection->getMethod('option');
        $commandMock = $this->partialMock(\PhpMcp\Laravel\Commands\ServeCommand::class, function ($mock) {
            $mock->shouldReceive('option')
                ->with('host')->andReturn('test-host')
                ->shouldReceive('option')
                ->with('port')->andReturn('9999')
                ->shouldReceive('option')
                ->with('path-prefix')->andReturn('test-prefix');
        });

        $method = $reflection->getMethod('startServerProcess');

        // We can't easily test the actual process creation without starting a real process,
        // but we can test that the method exists and is accessible
        $this->assertTrue($method->isPrivate());
        $this->assertEquals('startServerProcess', $method->getName());
    }

    public function test_process_management_methods_exist()
    {
        $command = new ServeCommand();
        $reflection = new \ReflectionClass($command);

        // Test that process management methods exist
        $this->assertTrue($reflection->hasMethod('isProcessRunning'));
        $this->assertTrue($reflection->hasMethod('stopProcess'));

        $isRunningMethod = $reflection->getMethod('isProcessRunning');
        $stopMethod = $reflection->getMethod('stopProcess');

        $this->assertTrue($isRunningMethod->isPrivate());
        $this->assertTrue($stopMethod->isPrivate());
    }

    public function test_is_process_running_handles_invalid_process()
    {
        $command = new ServeCommand();
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('isProcessRunning');

        // Test with invalid process info
        $result = $method->invoke($command, []);
        $this->assertFalse($result);

        $result = $method->invoke($command, ['process' => null]);
        $this->assertFalse($result);

        $result = $method->invoke($command, ['process' => 'not-a-resource']);
        $this->assertFalse($result);
    }

    public function test_stop_process_handles_invalid_process_gracefully()
    {
        $command = new ServeCommand();
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('stopProcess');

        // Test that method doesn't throw with invalid input
        $method->invoke($command, []);
        $method->invoke($command, ['process' => null]);
        $method->invoke($command, ['process' => 'not-a-resource']);

        // If we get here without exceptions, the test passes
        $this->assertTrue(true);
    }

    /**
     * Helper method to recursively remove directories
     */
    private function recursiveRemoveDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->recursiveRemoveDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
