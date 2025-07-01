<?php

declare(strict_types=1);

namespace PhpMcp\Laravel\Blueprints;

use Closure;
use PhpMcp\Schema\ToolAnnotations;

class ToolBlueprint
{
    public ?string $description = null;
    public ?ToolAnnotations $annotations = null;
    public ?array $inputSchema = null;

    /**
     * @param string|array|callable $handler
     */
    public function __construct(
        public mixed $handler,
        public ?string $name = null
    ) {}

    public function name(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function annotations(ToolAnnotations $annotations): static
    {
        $this->annotations = $annotations;

        return $this;
    }

    public function inputSchema(array $inputSchema): static
    {
        $this->inputSchema = $inputSchema;

        return $this;
    }
}
