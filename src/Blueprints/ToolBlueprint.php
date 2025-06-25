<?php

declare(strict_types=1);

namespace PhpMcp\Laravel\Blueprints;

use PhpMcp\Schema\ToolAnnotations;

class ToolBlueprint
{
    public ?string $description = null;
    public ?ToolAnnotations $annotations = null;

    public function __construct(
        public array|string $handler,
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
}
