<?php

namespace PhpMcp\Laravel\Tests\Feature\Commands;

use PhpMcp\Laravel\Tests\Stubs\App\Mcp\ManualTestHandler;
use PhpMcp\Laravel\Tests\TestCase;
use PhpMcp\Server\Registry;
use PhpMcp\Server\Server;
use PhpMcp\Schema\Tool;
use PhpMcp\Schema\Resource;
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
        $tool1 = Tool::make('list_tool_1', ['type' => 'object'], 'Desc 1');
        $resource1 = Resource::make('res://list/1', 'list_res_1', 'Desc Res 1', 'text/plain');

        $registry->registerTool($tool1, ManualTestHandler::class, 'handleTool', true);
        $registry->registerResource($resource1, ManualTestHandler::class, 'handleResource', true);
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
        $this->assertEquals('list_tool_1', $jsonData['tools'][0]['name']);
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
            ->expectsOutputToContain('MCP Registry is empty.')
            ->expectsOutputToContain('Run `php artisan mcp:discover` to discover MCP elements.')
            ->assertSuccessful();
    }

    public function test_list_command_warns_if_discovery_ran_but_no_elements_found()
    {
        $registryMock = $this->mock(Registry::class);
        $registryMock->shouldReceive('hasElements')->andReturn(false);
        $registryMock->shouldReceive('getTools')->andReturn([]);
        $registryMock->shouldReceive('getResources')->andReturn([]);
        $registryMock->shouldReceive('getPrompts')->andReturn([]);
        $registryMock->shouldReceive('getResourceTemplates')->andReturn([]);

        $serverMock = $this->mock(Server::class, function ($mock) use ($registryMock) {
            $mock->shouldReceive('getRegistry')->andReturn($registryMock);
        });
        $this->app->instance(Server::class, $serverMock);

        $this->artisan('mcp:list')
            ->expectsOutputToContain('MCP Registry is empty.')
            ->expectsOutputToContain('Run `php artisan mcp:discover` to discover MCP elements.')
            ->assertSuccessful();
    }
}
