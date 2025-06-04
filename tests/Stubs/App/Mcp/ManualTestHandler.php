<?php

namespace PhpMcp\Laravel\Tests\Stubs\App\Mcp;

class ManualTestHandler
{
    /**
     * A sample tool handler.
     * @param string $input Some input.
     * @return string The result.
     */
    public function handleTool(string $input): string
    {
        return "Tool processed: " . $input;
    }

    /**
     * A sample resource handler.
     * @return array Resource data.
     */
    public function handleResource(): array
    {
        return ['data' => 'manual resource content', 'timestamp' => time()];
    }

    /**
     * A sample prompt handler.
     * @param string $topic The topic for the prompt.
     * @return array Prompt messages.
     */
    public function handlePrompt(string $topic, int $count = 1): array
    {
        return [
            ['role' => 'user', 'content' => "Generate {$count} idea(s) about {$topic}."]
        ];
    }

    /**
     * A sample resource template handler.
     * @param string $itemId The ID from the URI.
     * @return array Item details.
     */
    public function handleTemplate(string $itemId): array
    {
        return ['id' => $itemId, 'name' => "Item {$itemId}", 'source' => 'manual_template'];
    }

    public function anotherTool(): void {} // For testing name override
}
