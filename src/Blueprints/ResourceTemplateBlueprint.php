<?php

declare(strict_types=1);

namespace PhpMcp\Laravel\Blueprints;

use Closure;
use PhpMcp\Schema\Annotations;

class ResourceTemplateBlueprint
{
    public ?string $name = null;

    public ?string $description = null;

    public ?string $mimeType = null;

    public ?Annotations $annotations = null;

    /**
     * @param string|array|callable $handler
     */
    public function __construct(
        public string $uriTemplate,
        public mixed $handler,
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
