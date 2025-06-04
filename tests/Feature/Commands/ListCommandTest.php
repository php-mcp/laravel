<?php

namespace PhpMcp\Laravel\Tests\Feature\Commands;

use PhpMcp\Laravel\Tests\Stubs\App\Mcp\ManualTestHandler;
use PhpMcp\Laravel\Tests\TestCase;
use PhpMcp\Server\Definitions\ToolDefinition;
use PhpMcp\Server\Definitions\ResourceDefinition;
use PhpMcp\Server\Registry;
use PhpMcp\Server\Server;
use PhpMcp\Server\Support\DocBlockParser;
use PhpMcp\Server\Support\SchemaGenerator;
use ArrayObject;
use Illuminate\Support\Facades\Artisan;
use Psr\Log\NullLogger;

class ListCommandTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('mcp.discovery.auto_discover', false);
        $app['config']->set('mcp.discovery.definitions_file', null);
    }

    private function populateRegistry(Registry $registry)
    {
        $logger = new NullLogger;
        $docBlockParser = new DocBlockParser($logger);
        $schemaGenerator = new SchemaGenerator($docBlockParser);

        $tool1 = ToolDefinition::fromReflection(
            new \ReflectionMethod(ManualTestHandler::class, 'handleTool'),
            'list_tool_1',
            'Desc 1',
            $docBlockParser,
            $schemaGenerator
        );
        $resource1 = ResourceDefinition::fromReflection(
            new \ReflectionMethod(ManualTestHandler::class, 'handleResource'),
            'list_res_1',
            'Desc Res 1',
            'res://list/1',
            'text/plain',
            null,
            [],
            $docBlockParser
        );
        $registry->registerTool($tool1, true);
        $registry->registerResource($resource1, true);
    }

    public function test_list_command_shows_all_types_by_default()
    {
        $server = $this->app->make(Server::class);
        $this->populateRegistry($server->getRegistry());

        $this->artisan('mcp:list')
            ->expectsOutputToContain('Tools:')
            ->expectsOutputToContain('list_tool_1')
            ->expectsOutputToContain('Resources:')
            ->expectsOutputToContain('res://list/1')
            ->expectsOutputToContain('Prompts: None found.')
            ->expectsOutputToContain('Templates: None found.')
            ->assertSuccessful();
    }

    public function test_list_command_shows_specific_type_tools()
    {
        $server = $this->app->make(Server::class);
        $this->populateRegistry($server->getRegistry());

        $this->artisan('mcp:list tools')
            ->expectsOutputToContain('Tools:')
            ->expectsOutputToContain('list_tool_1')
            ->doesntExpectOutputToContain('Resources:')
            ->assertSuccessful();
    }

    public function test_list_command_json_output_is_correct()
    {
        $server = $this->app->make(Server::class);
        $this->populateRegistry($server->getRegistry());

        Artisan::call('mcp:list --json');

        $output = Artisan::output();
        $jsonData = json_decode($output, true);

        $this->assertIsArray($jsonData);
        $this->assertArrayHasKey('tools', $jsonData);
        $this->assertArrayHasKey('resources', $jsonData);
        $this->assertCount(1, $jsonData['tools']);
        $this->assertEquals('list_tool_1', $jsonData['tools'][0]['toolName']);
        $this->assertEquals('res://list/1', $jsonData['resources'][0]['uri']);
    }

    public function test_list_command_handles_empty_registry_for_type()
    {
        $server = $this->app->make(Server::class);
        $this->populateRegistry($server->getRegistry());

        $this->artisan('mcp:list prompts')
            ->expectsOutputToContain('Prompts: None found.')
            ->assertSuccessful();
    }

    public function test_list_command_warns_if_discovery_not_run_and_no_manual_elements()
    {
        $this->artisan('mcp:list')
            ->expectsOutputToContain('No MCP elements are manually registered, and discovery has not run')
            ->assertSuccessful();
    }

    public function test_list_command_warns_if_discovery_ran_but_no_elements_found()
    {
        $registryMock = $this->mock(Registry::class);
        $registryMock->shouldReceive('hasElements')->andReturn(false);
        $registryMock->shouldReceive('discoveryRanOrCached')->andReturn(true); // Key difference
        $registryMock->shouldReceive('allTools')->andReturn(new ArrayObject());
        $registryMock->shouldReceive('allResources')->andReturn(new ArrayObject());
        $registryMock->shouldReceive('allPrompts')->andReturn(new ArrayObject());
        $registryMock->shouldReceive('allResourceTemplates')->andReturn(new ArrayObject());


        $serverMock = $this->mock(Server::class, function ($mock) use ($registryMock) {
            $mock->shouldReceive('getRegistry')->andReturn($registryMock);
        });
        $this->app->instance(Server::class, $serverMock);

        $this->artisan('mcp:list')
            ->expectsOutputToContain('Discovery/cache load ran, but no MCP elements were found.')
            ->assertSuccessful();
    }
}
