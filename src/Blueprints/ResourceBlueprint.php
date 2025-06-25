<?php

declare(strict_types=1);

namespace PhpMcp\Laravel\Blueprints;

use PhpMcp\Schema\Annotations;

class ResourceBlueprint
{
    public ?string $name = null;

    public ?string $description = null;

    public ?string $mimeType = null;

    public ?int $size = null;

    public ?Annotations $annotations = null;

    public function __construct(
        public string $uri,
        public array|string $handler,
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

    public function mimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function size(int $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function annotations(Annotations $annotations): static
    {
        $this->annotations = $annotations;

        return $this;
    }
}
