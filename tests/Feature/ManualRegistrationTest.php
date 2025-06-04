<?php

namespace PhpMcp\Laravel\Tests\Feature;

use PhpMcp\Laravel\Tests\Stubs\App\Mcp\ManualTestHandler;
use PhpMcp\Laravel\Tests\Stubs\App\Mcp\ManualTestInvokableHandler;
use PhpMcp\Laravel\Tests\TestCase;
use PhpMcp\Server\Definitions\PromptDefinition;
use PhpMcp\Server\Definitions\ResourceDefinition;
use PhpMcp\Server\Definitions\ResourceTemplateDefinition;
use PhpMcp\Server\Definitions\ToolDefinition;

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

        $tool = $registry->findTool('manual_test_tool');

        $this->assertInstanceOf(ToolDefinition::class, $tool);
        $this->assertEquals('manual_test_tool', $tool->getName());
        $this->assertEquals('A manually registered test tool.', $tool->getDescription());
        $this->assertEquals(ManualTestHandler::class, $tool->getClassName());
        $this->assertEquals('handleTool', $tool->getMethodName());
        $this->assertArrayHasKey('input', $tool->getInputSchema()['properties']);
        $this->assertEquals('string', $tool->getInputSchema()['properties']['input']['type']);
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
        $tool = $registry->findTool('handleTool');

        $this->assertNotNull($tool);
        $this->assertEquals(ManualTestHandler::class, $tool->getClassName());
        $this->assertEquals('handleTool', $tool->getMethodName());
        $this->assertEquals('A sample tool handler.', $tool->getDescription());
    }

    public function test_can_manually_register_a_resource()
    {
        $definitionsContent = <<<'PHP'
        <?php
        use PhpMcp\Laravel\Facades\Mcp;
        use PhpMcp\Laravel\Tests\Stubs\App\Mcp\ManualTestHandler;

        Mcp::resource('manual://config/app-setting', [ManualTestHandler::class, 'handleResource'])
            ->name('manual_app_setting')
            ->mimeType('application/json')
            ->size(1024)
            ->annotations(['category' => 'config']);
        PHP;
        $this->setMcpDefinitions($definitionsContent);

        $registry = $this->app->make('mcp.registry');
        $resource = $registry->findResourceByUri('manual://config/app-setting');

        $this->assertInstanceOf(ResourceDefinition::class, $resource);
        $this->assertEquals('manual_app_setting', $resource->getName());
        $this->assertEquals('A sample resource handler.', $resource->getDescription());
        $this->assertEquals('application/json', $resource->getMimeType());
        $this->assertEquals(1024, $resource->getSize());
        $this->assertEquals(['category' => 'config'], $resource->getAnnotations());
        $this->assertEquals(ManualTestHandler::class, $resource->getClassName());
        $this->assertEquals('handleResource', $resource->getMethodName());
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
        $prompt = $registry->findPrompt('manual_invokable_prompt');

        $this->assertInstanceOf(PromptDefinition::class, $prompt);
        $this->assertEquals('manual_invokable_prompt', $prompt->getName());
        $this->assertEquals('A prompt handled by an invokable class.', $prompt->getDescription());
        $this->assertEquals(ManualTestInvokableHandler::class, $prompt->getClassName());
        $this->assertEquals('__invoke', $prompt->getMethodName());
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
        $templateMatch = $registry->findResourceTemplateByUri('manual://item/123/details');

        $this->assertNotNull($templateMatch);
        $template = $templateMatch['definition'];
        $this->assertInstanceOf(ResourceTemplateDefinition::class, $template);
        $this->assertEquals('manual://item/{itemId}/details', $template->getUriTemplate());
        $this->assertEquals('manual_item_details_template', $template->getName());
        $this->assertEquals('A sample resource template handler.', $template->getDescription());
        $this->assertEquals('application/vnd.api+json', $template->getMimeType());
        $this->assertEquals(ManualTestHandler::class, $template->getClassName());
        $this->assertEquals('handleTemplate', $template->getMethodName());
    }
}
