<?php

use App\Mcp\GenerateSeoKeywordsPrompt;
use App\Mcp\GenerateWelcomeMessage;
use App\Mcp\GetArticleContent;
use App\Mcp\GetAppVersion;
use PhpMcp\Laravel\Facades\Mcp;
use PhpMcp\Laravel\Facades\McpAuth;
use Illuminate\Support\Facades\Auth;

Mcp::tool('welcome_message', GenerateWelcomeMessage::class);

Mcp::resource('app://version', GetAppVersion::class)
    ->name('laravel_app_version')
    ->mimeType('text/plain');

Mcp::tool('get_me', function () {
    // Try MCP context first (for dedicated HTTP), fallback to Laravel Auth
    $user = McpAuth::user() ?? Auth::user();
    
    if (!$user) {
        return [
            'error' => 'No authenticated user found',
            'context' => 'Make sure to include Authorization header with Bearer token',
            'mcp_context' => McpAuth::check() ? 'MCP context available' : 'No MCP context',
            'auth_context' => Auth::check() ? 'Laravel auth available' : 'No Laravel auth',
        ];
    }
    
    return [
        'user' => $user,
        'guard' => McpAuth::guard() ?? Auth::getDefaultDriver(),
        'auth_method' => McpAuth::check() ? 'mcp_context' : 'laravel_auth',
    ];
});

Mcp::resourceTemplate('content://articles/{articleId}', GetArticleContent::class)
    ->name('article_content')
    ->mimeType('application/json');

Mcp::prompt('seo_keywords_generator', GenerateSeoKeywordsPrompt::class);
