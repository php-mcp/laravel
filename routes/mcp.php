<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PhpMcp\Laravel\Server\Http\Controllers\McpController;

/*
|--------------------------------------------------------------------------
| MCP HTTP Routes
|--------------------------------------------------------------------------
|
| These routes handle the HTTP transport for the Model Context Protocol.
| They are automatically loaded by the LaravelMcpServiceProvider.
|
| - POST /message (handled by McpController@handleMessage): Receives client messages.
| - GET /sse (handled by McpController@handleSse): Establishes the SSE connection.
|
*/

Route::post('/message', [McpController::class, 'handleMessage'])
    ->name('mcp.message');

Route::get('/sse', [McpController::class, 'handleSse'])
    ->name('mcp.sse');
