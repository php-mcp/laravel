<?php

declare(strict_types=1);

namespace PhpMcp\Laravel\Blueprints;

use PhpMcp\Schema\Annotations;

class ResourceTemplateBlueprint
{
    public ?string $name = null;

    public ?string $description = null;

    public ?string $mimeType = null;

    public ?Annotations $annotations = null;

    public function __construct(
        public string $uriTemplate,
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

    public function annotations(Annotations $annotations): static
    {
        $this->annotations = $annotations;

        return $this;
    }
}
