<?php

namespace PhpMcp\Laravel\Tests\Feature\Commands;

use PhpMcp\Laravel\Tests\TestCase;
use PhpMcp\Server\Server;
use Mockery;
use PhpMcp\Server\Registry;

class DiscoverCommandTest extends TestCase
{
    public function test_discover_command_displays_correct_element_counts()
    {
        $registryMock = Mockery::mock(Registry::class);
        $registryMock->shouldReceive('allTools->count')->andReturn(2);
        $registryMock->shouldReceive('allResources->count')->andReturn(1);
        $registryMock->shouldReceive('allResourceTemplates->count')->andReturn(0);
        $registryMock->shouldReceive('allPrompts->count')->andReturn(3);
        $registryMock->shouldReceive('discoveryRanOrCached')->andReturn(true);

        $serverMock = $this->mock(Server::class, function ($mock) use ($registryMock) {
            $mock->shouldReceive('discover')->once();
            $mock->shouldReceive('getRegistry')->andReturn($registryMock);
        });
        $this->app->instance(Server::class, $serverMock);
        $this->app->instance(Registry::class, $registryMock);


        $this->artisan('mcp:discover')
            ->expectsTable(['Element Type', 'Count'], [
                ['Tools', 2],
                ['Resources', 1],
                ['Resource Templates', 0],
                ['Prompts', 3],
            ])
            ->assertSuccessful();
    }

    public function test_discover_command_handles_discovery_exception_gracefully()
    {
        $serverMock = $this->mock(Server::class, function ($mock) {
            $mock->shouldReceive('discover')->andThrow(new \RuntimeException("Simulated discovery failure!"));
            $mock->shouldAllowMockingProtectedMethods()->shouldIgnoreMissing();
        });
        $this->app->instance(Server::class, $serverMock);


        $this->artisan('mcp:discover')
            ->expectsOutputToContain('Discovery failed: Simulated discovery failure!')
            ->assertFailed();
    }
}
