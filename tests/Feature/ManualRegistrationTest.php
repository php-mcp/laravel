<?php

namespace PhpMcp\Laravel\Tests\Feature;

use PhpMcp\Laravel\Tests\Stubs\App\Mcp\ManualTestHandler;
use PhpMcp\Laravel\Tests\Stubs\App\Mcp\ManualTestInvokableHandler;
use PhpMcp\Laravel\Tests\TestCase;
use PhpMcp\Server\Elements\RegisteredTool;
use PhpMcp\Server\Elements\RegisteredResource;
use PhpMcp\Server\Elements\RegisteredResourceTemplate;
use PhpMcp\Server\Elements\RegisteredPrompt;

class ManualRegistrationTest extends TestCase
{
    public function test_can_manually_register_a_tool()
    {
        $definitionsContent = <<<'PHP'
        <?php
        use PhpMcp\Laravel\Facades\Mcp;
        use PhpMcp\Laravel\Tests\Stubs\App\Mcp\ManualTestHandler;

        Mcp::tool('manual_test_tool', [ManualTestHandler::class, 'handleTool'])
            ->description('A manually registered test tool.');
        PHP;
        $this->setMcpDefinitions($definitionsContent);

        $registry = $this->app->make('mcp.registry');

        $tool = $registry->getTool('manual_test_tool');

        $this->assertInstanceOf(RegisteredTool::class, $tool);
        $this->assertEquals('manual_test_tool', $tool->schema->name);
        $this->assertEquals('A manually registered test tool.', $tool->schema->description);
        $this->assertEquals([ManualTestHandler::class, 'handleTool'], $tool->handler);
        $this->assertArrayHasKey('input', $tool->schema->inputSchema['properties']);
        $this->assertEquals('string', $tool->schema->inputSchema['properties']['input']['type']);
    }

    public function test_can_manually_register_tool_using_handler_only()
    {
        $definitionsContent = <<<'PHP'
        <?php
        use PhpMcp\Laravel\Facades\Mcp;
        use PhpMcp\Laravel\Tests\Stubs\App\Mcp\ManualTestHandler;

        Mcp::tool([ManualTestHandler::class, 'handleTool']);
        PHP;
        $this->setMcpDefinitions($definitionsContent);

        $registry = $this->app->make('mcp.registry');
        $tool = $registry->getTool('handleTool');

        $this->assertNotNull($tool);
        $this->assertEquals([ManualTestHandler::class, 'handleTool'], $tool->handler);
        $this->assertEquals('A sample tool handler.', $tool->schema->description);
    }

    public function test_can_manually_register_a_resource()
    {
        $definitionsContent = <<<'PHP'
        <?php
        use PhpMcp\Laravel\Facades\Mcp;
        use PhpMcp\Laravel\Tests\Stubs\App\Mcp\ManualTestHandler;
        use PhpMcp\Schema\Annotations;

        Mcp::resource('manual://config/app-setting', [ManualTestHandler::class, 'handleResource'])
            ->name('manual_app_setting')
            ->mimeType('application/json')
            ->size(1024)
            ->annotations(Annotations::make(priority:0.8));
        PHP;
        $this->setMcpDefinitions($definitionsContent);

        $registry = $this->app->make('mcp.registry');
        $resource = $registry->getResource('manual://config/app-setting');

        $this->assertInstanceOf(RegisteredResource::class, $resource);
        $this->assertEquals('manual_app_setting', $resource->schema->name);
        $this->assertEquals('A sample resource handler.', $resource->schema->description);
        $this->assertEquals('application/json', $resource->schema->mimeType);
        $this->assertEquals(1024, $resource->schema->size);
        $this->assertEquals(['priority' => 0.8], $resource->schema->annotations->toArray());
        $this->assertEquals([ManualTestHandler::class, 'handleResource'], $resource->handler);
    }

    public function test_can_manually_register_a_prompt_with_invokable_class_handler()
    {
        $definitionsContent = <<<'PHP'
        <?php
        use PhpMcp\Laravel\Facades\Mcp;
        use PhpMcp\Laravel\Tests\Stubs\App\Mcp\ManualTestInvokableHandler;

        Mcp::prompt('manual_invokable_prompt', ManualTestInvokableHandler::class)
            ->description('A prompt handled by an invokable class.');
        PHP;
        $this->setMcpDefinitions($definitionsContent);

        $registry = $this->app->make('mcp.registry');
        $prompt = $registry->getPrompt('manual_invokable_prompt');

        $this->assertInstanceOf(RegisteredPrompt::class, $prompt);
        $this->assertEquals('manual_invokable_prompt', $prompt->schema->name);
        $this->assertEquals('A prompt handled by an invokable class.', $prompt->schema->description);
        $this->assertEquals(ManualTestInvokableHandler::class, $prompt->handler);
    }

    public function test_can_manually_register_a_resource_template_via_facade()
    {
        $definitionsContent = <<<'PHP'
        <?php
        use PhpMcp\Laravel\Facades\Mcp;
        use PhpMcp\Laravel\Tests\Stubs\App\Mcp\ManualTestHandler;

        Mcp::resourceTemplate('manual://item/{itemId}/details', [ManualTestHandler::class, 'handleTemplate'])
            ->name('manual_item_details_template')
            ->mimeType('application/vnd.api+json');
        PHP;
        $this->setMcpDefinitions($definitionsContent);

        $registry = $this->app->make('mcp.registry');
        $template = $registry->getResource('manual://item/123/details');

        $this->assertNotNull($template);
        $this->assertInstanceOf(RegisteredResourceTemplate::class, $template);
        $this->assertEquals('manual://item/{itemId}/details', $template->schema->uriTemplate);
        $this->assertEquals('manual_item_details_template', $template->schema->name);
        $this->assertEquals('A sample resource template handler.', $template->schema->description);
        $this->assertEquals('application/vnd.api+json', $template->schema->mimeType);
        $this->assertEquals([ManualTestHandler::class, 'handleTemplate'], $template->handler);
    }
}
