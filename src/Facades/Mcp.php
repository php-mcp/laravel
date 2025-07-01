<?php

declare(strict_types=1);

namespace PhpMcp\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use PhpMcp\Laravel\Blueprints\PromptBlueprint;
use PhpMcp\Laravel\Blueprints\ResourceBlueprint;
use PhpMcp\Laravel\Blueprints\ResourceTemplateBlueprint;
use PhpMcp\Laravel\Blueprints\ToolBlueprint;

/**
 * @method static ToolBlueprint tool(string|callable|array $handlerOrName, callable|array|string|null $handler = null)
 * @method static ResourceBlueprint resource(string $uri, callable|array|string $handler)
 * @method static ResourceTemplateBlueprint resourceTemplate(string $uriTemplate, callable|array|string $handler)
 * @method static PromptBlueprint prompt(string|callable|array $handlerOrName, callable|array|string|null $handler = null)
 *
 * @see \PhpMcp\Laravel\McpRegistrar
 */
class Mcp extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'mcp.registrar';
    }
}
