<?php

namespace App\Mcp;

class GenerateSeoKeywordsPrompt
{
    /**
     * Generates a prompt structure for SEO keyword generation related to a topic.
     *
     * @param string $topic The topic for which to generate SEO keywords.
     * @return array The prompt structure.
     */
    public function __invoke(string $topic): array
    {
        return [
            [
                'role' => 'user',
                'content' => "Please generate 5 SEO-friendly keywords for the following topic: {$topic}.",
            ],
            [
                'role' => 'assistant',
                'content' => "Okay, I will generate 5 SEO-friendly keywords for '{$topic}'. Here they are:"
            ]
        ];
    }
}
