<?php

namespace App\Mcp;

class GetArticleContent
{
    /**
     * Provides mock article content for a given ID.
     *
     * @param string $articleId The ID of the article.
     * @return array Mock article data.
     */
    public function __invoke(string $articleId): array
    {
        return [
            'id' => $articleId,
            'title' => 'Manually Registered Article Example',
            'content' => "This is sample content for article {$articleId} provided by a manually registered ResourceTemplate.",
            'author' => 'MCP Assistant',
            'published_at' => now()->toIso8601String(),
        ];
    }
}
