<?php

use Illuminate\Support\Facades\Route;
use PhpMcp\Laravel\Http\Controllers\StreamableTransportController;
use PhpMcp\Laravel\Http\Controllers\SseTransportController;

if (config('mcp.transports.http_integrated.legacy', false)) {
    Route::get('/sse', [SseTransportController::class, 'handleSse'])
        ->name('mcp.sse');

    Route::post('/message', [SseTransportController::class, 'handleMessage'])
        ->name('mcp.message');
} else {
    Route::get('/', [StreamableTransportController::class, 'handleGet'])
        ->name('mcp.streamable.get');

    Route::post('/', [StreamableTransportController::class, 'handlePost'])
        ->name('mcp.streamable.post');

    Route::delete('/', [StreamableTransportController::class, 'handleDelete'])
        ->name('mcp.streamable.delete');
}
