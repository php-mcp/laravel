<?php

declare(strict_types=1);

namespace PhpMcp\Laravel\Blueprints;


class ToolBlueprint
{
    public ?string $description = null;

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
}
