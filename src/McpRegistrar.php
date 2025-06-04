<?php

declare(strict_types=1);

namespace PhpMcp\Laravel;

use InvalidArgumentException;
use PhpMcp\Laravel\Blueprints\PromptBlueprint;
use PhpMcp\Laravel\Blueprints\ResourceBlueprint;
use PhpMcp\Laravel\Blueprints\ResourceTemplateBlueprint;
use PhpMcp\Laravel\Blueprints\ToolBlueprint;
use PhpMcp\Server\ServerBuilder;

class McpRegistrar
{
    /** @var ToolBlueprint[] */
    protected array $pendingTools = [];

    /** @var ResourceBlueprint[] */
    protected array $pendingResources = [];

    /** @var ResourceTemplateBlueprint[] */
    protected array $pendingResourceTemplates = [];

    /** @var PromptBlueprint[] */
    protected array $pendingPrompts = [];

    public function __construct() {}

    /**
     * Register a new tool.
     *
     * Usage:
     * Mcp::tool('tool_name', $handler)
     * Mcp::tool($handler) // Name will be inferred
     */
    public function tool(string|array ...$args): ToolBlueprint
    {
        $name = null;
        $handler = null;

        if (count($args) === 1 && (is_array($args[0]) || (is_string($args[0]) && (class_exists($args[0]) || is_callable($args[0]))))) {
            $handler = $args[0];
        } elseif (count($args) === 2 && is_string($args[0]) && (is_array($args[1]) || (is_string($args[1]) && (class_exists($args[1]) || is_callable($args[1]))))) {
            $name = $args[0];
            $handler = $args[1];
        } else {
            throw new InvalidArgumentException('Invalid arguments for Mcp::tool(). Expected (handler) or (name, handler).');
        }

        $pendingTool = new ToolBlueprint($handler, $name);
        $this->pendingTools[] = $pendingTool;

        return $pendingTool;
    }

    /**
     * Register a new resource.
     */
    public function resource(string $uri, array|string $handler): ResourceBlueprint
    {
        $pendingResource = new ResourceBlueprint($uri, $handler);
        $this->pendingResources[] = $pendingResource;

        return $pendingResource;
    }

    /**
     * Register a new resource template.
     */
    public function resourceTemplate(string $uriTemplate, array|string $handler): ResourceTemplateBlueprint
    {
        $pendingResourceTemplate = new ResourceTemplateBlueprint($uriTemplate, $handler);
        $this->pendingResourceTemplates[] = $pendingResourceTemplate;

        return $pendingResourceTemplate;
    }

    /**
     * Register a new prompt.
     *
     * Usage:
     * Mcp::prompt('prompt_name', $handler)
     * Mcp::prompt($handler) // Name will be inferred
     */
    public function prompt(string|array ...$args): PromptBlueprint
    {
        $name = null;
        $handler = null;

        if (count($args) === 1 && (is_array($args[0]) || (is_string($args[0]) && (class_exists($args[0]) || is_callable($args[0]))))) {
            $handler = $args[0];
        } elseif (count($args) === 2 && is_string($args[0]) && (is_array($args[1]) || (is_string($args[1]) && (class_exists($args[1]) || is_callable($args[1]))))) {
            $name = $args[0];
            $handler = $args[1];
        } else {
            throw new InvalidArgumentException('Invalid arguments for Mcp::prompt(). Expected (handler) or (name, handler).');
        }

        $pendingPrompt = new PromptBlueprint($handler, $name);
        $this->pendingPrompts[] = $pendingPrompt;

        return $pendingPrompt;
    }

    public function applyBlueprints(ServerBuilder $builder): void
    {
        foreach ($this->pendingTools as $pendingTool) {
            $builder->withTool($pendingTool->handler, $pendingTool->name, $pendingTool->description);
        }

        foreach ($this->pendingResources as $pendingResource) {
            $builder->withResource(
                $pendingResource->handler,
                $pendingResource->uri,
                $pendingResource->name,
                $pendingResource->description,
                $pendingResource->mimeType,
                $pendingResource->size,
                $pendingResource->annotations
            );
        }

        foreach ($this->pendingResourceTemplates as $pendingTemplate) {
            $builder->withResourceTemplate(
                $pendingTemplate->handler,
                $pendingTemplate->uriTemplate,
                $pendingTemplate->name,
                $pendingTemplate->description,
                $pendingTemplate->mimeType,
                $pendingTemplate->annotations
            );
        }

        foreach ($this->pendingPrompts as $pendingPrompt) {
            $builder->withPrompt($pendingPrompt->handler, $pendingPrompt->name, $pendingPrompt->description);
        }
    }
}
