<?php

declare(strict_types=1);

namespace PhpMcp\Laravel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpMcp\Laravel\Transports\LaravelHttpTransport;
use PhpMcp\Server\Server;
use PhpMcp\Server\State\ClientStateManager;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class McpController
{
    private ClientStateManager $clientStateManager;

    /**
     * MCP Controller Constructor
     *
     * Inject dependencies resolved by the service container.
     */
    public function __construct(protected Server $server, protected LaravelHttpTransport $transport)
    {
        $this->clientStateManager = $server->getClientStateManager();

        $server->listen($this->transport, false);
    }

    /**
     * Handle client message (HTTP POST endpoint).
     */
    public function handleMessage(Request $request): Response
    {
        if (! $request->isJson()) {
            Log::warning('MCP POST request with invalid Content-Type');

            return response()->json([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32600,
                    'message' => 'Invalid Request: Content-Type must be application/json',
                ],
            ], 400);
        }

        $clientId = $request->query('clientId');

        if (! $clientId || ! is_string($clientId)) {
            Log::error('MCP: Missing or invalid clientId');

            return response()->json([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32600,
                    'message' => 'Invalid Request: Missing or invalid clientId query parameter',
                ],
            ], 400);
        }

        // Confirm request body is not empty
        $content = $request->getContent();
        if ($content === false || empty($content)) {
            Log::warning('MCP POST request with empty body');

            return response()->json([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32600,
                    'message' => 'Invalid Request: Empty body',
                ],
            ], 400);
        }

        $this->transport->emit('message', [$content, $clientId]);

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

        $this->transport->emit('client_connected', [$clientId]);

        $pollInterval = (int) config('mcp.transports.http_integrated.sse_poll_interval', 1);
        if ($pollInterval < 1) {
            $pollInterval = 1;
        }

        return response()->stream(function () use ($clientId, $pollInterval) {
            @set_time_limit(0);

            try {
                $postEndpointUri = route('mcp.message', ['clientId' => $clientId], false);

                $this->sendSseEvent('endpoint', $postEndpointUri, "mcp-endpoint-{$clientId}");
            } catch (Throwable $e) {
                Log::error('MCP: SSE stream loop terminated', ['client_id' => $clientId, 'reason' => $e->getMessage()]);

                return;
            }

            while (true) {
                if (connection_aborted()) {
                    break;
                }

                $messages = $this->clientStateManager->getQueuedMessages($clientId);
                foreach ($messages as $message) {
                    $this->sendSseEvent('message', rtrim($message, "\n"));
                }

                static $keepAliveCounter = 0;
                if (($keepAliveCounter++ % (15 / $pollInterval)) == 0) {
                    echo ": keep-alive\n\n";
                    $this->flushOutput();
                }

                usleep($pollInterval * 1000000);
            }

            $this->transport->emit('client_disconnected', [$clientId, 'Laravel SSE stream shutdown']);
            $this->server->endListen($this->transport);
        }, headers: [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
            'Access-Control-Allow-Origin' => '*', // TODO: Make this configurable
        ]);
    }

    private function sendSseEvent(string $event, string $data, ?string $id = null): void
    {
        if (connection_aborted()) {
            return;
        }

        echo "event: {$event}\n";
        if ($id !== null) {
            echo "id: {$id}\n";
        }

        foreach (explode("\n", $data) as $line) {
            echo "data: {$line}\n";
        }

        echo "\n";
        $this->flushOutput();
    }

    private function flushOutput(): void
    {
        if (function_exists('ob_flush')) {
            @ob_flush();
        }
        @flush();
    }
}
