<?php

namespace PhpMcp\Laravel\Tests\Stubs\App\Mcp;

class ManualTestInvokableHandler
{
    /**
     * Handles an invokable prompt.
     * @param string $query The user's query for the prompt.
     * @return array Generated prompt messages.
     */
    public function __invoke(string $query): array
    {
        return [
            ['role' => 'user', 'content' => "Invokable prompt responding to: {$query}"]
        ];
    }
}
