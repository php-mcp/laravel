<?php

declare(strict_types=1);

namespace PhpMcp\Laravel\Blueprints;

use Closure;
use PhpMcp\Schema\Annotations;

class ResourceBlueprint
{
    public ?string $name = null;

    public ?string $description = null;

    public ?string $mimeType = null;

    public ?int $size = null;

    public ?Annotations $annotations = null;

    /**
     * @param string|array|callable $handler
     */
    public function __construct(
        public string $uri,
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
