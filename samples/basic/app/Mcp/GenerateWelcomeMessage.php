<?php

namespace App\Mcp;

class GenerateWelcomeMessage
{
    /**
     * Generates a personalized welcome message for a user.
     *
     * @param string $name The name of the person to greet.
     * @return string The welcome message.
     */
    public function __invoke(string $name): string
    {
        return "Hello, {$name}! Welcome to our Laravel MCP application example using manual registration.";
    }
}
