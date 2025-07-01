<?php

declare(strict_types=1);

namespace PhpMcp\Laravel\Blueprints;

class PromptBlueprint
{
    public ?string $description = null;

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
}
