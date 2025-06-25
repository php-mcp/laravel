<?php

declare(strict_types=1);

namespace PhpMcp\Laravel\Http\Controllers;

use Illuminate\Http\Request;
use PhpMcp\Laravel\Transports\LaravelHttpTransport;
use PhpMcp\Server\Server;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SseTransportController
{
    protected LaravelHttpTransport $transport;

    /**
     * MCP Controller Constructor
     *
     * Inject dependencies resolved by the service container.
     */
    public function __construct(Server $server)
    {
        $this->transport = new LaravelHttpTransport($server->getSessionManager());
        $server->listen($this->transport, false);
    }

    /**
     * Handle client message (HTTP POST endpoint).
     * Delegates to the transport for processing.
     */
    public function handleMessage(Request $request): Response
    {
        return $this->transport->handleMessageRequest($request);
    }

    /**
     * Handle SSE (GET endpoint).
     * Delegates to the transport for streaming.
     */
    public function handleSse(Request $request): StreamedResponse
    {
        return $this->transport->handleSseRequest($request);
    }
}
