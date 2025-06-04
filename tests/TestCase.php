<?php

namespace PhpMcp\Laravel\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use PhpMcp\Laravel\McpServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected string $definitionsFilePath;

    protected function getPackageProviders($app)
    {
        return [
            McpServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app): void
    {
        $this->definitionsFilePath = __DIR__ . '/Stubs/routes/mcp-definitions.php';

        $app['config']->set('mcp.discovery.definitions_file', $this->definitionsFilePath);
        $app['config']->set('mcp.discovery.base_path', __DIR__ . '/Stubs');

        $app['config']->set('mcp.logging.channel', 'null');
    }

    /**
     * Overwrites the content of the test MCP definitions file and refreshes the application.
     */
    protected function setMcpDefinitions(string $content): void
    {
        file_put_contents($this->definitionsFilePath, $content);
        $this->refreshApplication();
    }

    /**
     * Creates a temporary MCP handler class file within the Stubs/App/Mcp directory.
     */
    protected function createStubMcpHandlerFile(string $className, string $content, string $subDir = 'App/Mcp'): string
    {
        $basePath = __DIR__ . '/Stubs/' . $subDir;
        if (!is_dir($basePath)) {
            mkdir($basePath, 0777, true);
        }
        $filePath = $basePath . '/' . $className . '.php';
        file_put_contents($filePath, $content);
        return $filePath;
    }

    protected function tearDown(): void
    {
        file_put_contents($this->definitionsFilePath, '<?php // Test MCP definitions' . PHP_EOL);
        parent::tearDown();
    }
}
