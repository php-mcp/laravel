<?php

declare(strict_types=1);

namespace PhpMcp\Laravel\Server\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PhpMcp\Server\State\TransportState;
use PhpMcp\Server\Transports\HttpTransportHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class McpController
{
    /**
     * MCP Controller Constructor
     *
     * Inject dependencies resolved by the service container.
     */
    public function __construct(
        private readonly HttpTransportHandler $handler,
        private readonly TransportState $state,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Handle client message (HTTP POST endpoint).
     */
    public function handleMessage(Request $request): Response
    {
        // Confirm request is JSON
        if (! $request->isJson()) {
            $this->logger->warning('MCP POST request with invalid Content-Type');

            return response()->json([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32600,
                    'message' => 'Invalid Request: Content-Type must be application/json',
                ],
            ], 400);
        }

        // Confirm request body is not empty
        $content = $request->getContent();
        if ($content === false || empty($content)) {
            $this->logger->warning('MCP POST request with empty body');

            return response()->json([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32600,
                    'message' => 'Invalid Request: Empty body',
                ],
            ], 400);
        }

        $clientId = $request->query('client_id');

        if (! $clientId || ! is_string($clientId)) {
            $this->logger->error('MCP: Missing or invalid clientId');

            return response()->json([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32600,
                    'message' => 'Invalid Request: Missing or invalid clientId query parameter',
                ],
            ], 400);
        }

        $this->handler->handleInput($content, $clientId);

        return response()->json([
            'jsonrpc' => '2.0',
            'result' => null,
            'id' => 1,
        ], 202);
    }

    /**
     * Handle SSE (GET endpoint).
     */
    public function handleSse(Request $request): Response
    {
        $clientId = $request->hasSession() ? $request->session()->getId() : Str::uuid()->toString();

        if (! $clientId) {
            $this->logger->error('MCP: SSE connection failed - Could not determine Client ID.');

            return response()->json([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32600,
                    'message' => 'Could not determine Client ID',
                ],
            ], 400);
        }

        $this->logger->info('MCP: SSE connection opening', ['client_id' => $clientId]);

        set_time_limit(0);

        return response()->stream(function () use ($clientId) {
            try {
                $postEndpointUri = route('mcp.message', ['client_id' => $clientId], false);

                $this->handler->handleSseConnection($clientId, $postEndpointUri);
            } catch (Throwable $e) {
                $this->logger->error('MCP: SSE stream loop terminated', ['client_id' => $clientId, 'reason' => $e->getMessage()]);
            } finally {
                $this->handler->cleanupClient($clientId);
                $this->logger->info('MCP: SSE connection closed and client cleaned up', ['client_id' => $clientId]);
            }
        }, headers: [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // Prevent buffering by proxies like nginx
        ]);
    }
}
