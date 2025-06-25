<?php

declare(strict_types=1);

namespace PhpMcp\Laravel\Transports;

use Evenement\EventEmitterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PhpMcp\Schema\JsonRpc\Error;
use PhpMcp\Schema\JsonRpc\Parser;
use PhpMcp\Server\Contracts\ServerTransportInterface;
use PhpMcp\Server\Session\SessionManager;
use PhpMcp\Schema\JsonRpc\Message;
use React\Promise\PromiseInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

use function React\Promise\resolve;

class HttpServerTransport implements ServerTransportInterface
{
    use EventEmitterTrait;

    protected SessionManager $sessionManager;

    public function __construct(SessionManager $sessionManager)
    {
        $this->sessionManager = $sessionManager;

        $this->on('message', function (Message $message, string $sessionId) {
            $session = $this->sessionManager->getSession($sessionId);
            if ($session !== null) {
                $session->save(); // This updates the session timestamp
            }
        });
    }

    protected function generateId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * For this integrated transport, 'listen' doesn't start a network listener.
     * It signifies the transport is ready to be used by the Protocol handler.
     * The actual listening is done by Laravel's HTTP kernel.
     */
    public function listen(): void
    {
        $this->emit('ready');
    }

    /**
     * Sends a message to a specific client session by queueing it in the SessionManager.
     * The SSE streams will pick this up.
     */
    public function sendMessage(Message $message, string $sessionId, array $context = []): PromiseInterface
    {
        $rawMessage = json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (empty($rawMessage)) {
            return resolve(null);
        }

        $this->sessionManager->queueMessage($sessionId, $rawMessage);

        return resolve(null);
    }

    /**
     * Handle incoming HTTP POST message requests
     */
    public function handleMessageRequest(Request $request): Response
    {
        $this->collectSessionGarbage();

        if (!$request->isJson()) {
            Log::warning('Received POST request with invalid Content-Type');

            $error = Error::forInvalidRequest('Content-Type must be application/json');

            return response()->json($error, 415);
        }

        $sessionId = $request->query('clientId');
        if (!$sessionId || !is_string($sessionId)) {
            Log::error('Received POST request with missing or invalid sessionId');

            $error = Error::forInvalidRequest('Missing or invalid clientId query parameter');

            return response()->json($error, 400);
        }

        $content = $request->getContent();
        if (empty($content)) {
            Log::warning('Received POST request with empty body');

            $error = Error::forInvalidRequest('Empty body');

            return response()->json($error, 400);
        }

        try {
            $message = Parser::parse($content);
        } catch (Throwable $e) {
            Log::error('MCP: Failed to parse message', ['error' => $e->getMessage()]);

            $error = Error::forParseError('Invalid JSON-RPC message: ' . $e->getMessage());

            return response()->json($error, 400);
        }

        $this->emit('message', [$message, $sessionId]);

        return response()->json([
            'jsonrpc' => '2.0',
            'result' => null,
            'id' => 1,
        ], 202);
    }

    /**
     * Handle SSE connection requests - moved from McpController
     */
    public function handleSseRequest(Request $request): StreamedResponse
    {
        $sessionId = $this->generateId();

        $this->emit('client_connected', [$sessionId]);

        $pollInterval = (int) config('mcp.transports.http_integrated.sse_poll_interval', 1);
        if ($pollInterval < 1) {
            $pollInterval = 1;
        }

        return response()->stream(function () use ($sessionId, $pollInterval) {
            @set_time_limit(0);

            try {
                $postEndpointUri = route('mcp.message', ['clientId' => $sessionId], false);

                $this->sendSseEvent('endpoint', $postEndpointUri, "mcp-endpoint-{$sessionId}");
            } catch (Throwable $e) {
                Log::error('Error sending initial endpoint event', ['sessionId' => $sessionId, 'exception' => $e]);

                return;
            }

            while (true) {
                if (connection_aborted()) {
                    break;
                }

                $messages = $this->sessionManager->dequeueMessages($sessionId);
                foreach ($messages as $message) {
                    $this->sendSseEvent('message', rtrim($message, "\n"));
                }

                static $keepAliveCounter = 0;
                $keepAliveInterval = (int) round(15 / $pollInterval);
                if (($keepAliveCounter++ % $keepAliveInterval) == 0) {
                    echo ": keep-alive\n\n";
                    $this->flushOutput();
                }

                usleep($pollInterval * 1000000);
            }

            $this->emit('client_disconnected', [$sessionId, 'SSE stream closed']);
        }, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
            'Access-Control-Allow-Origin' => config('mcp.transports.http_integrated.cors_origin', '*'),
        ]);
    }

    /**
     * Send an SSE event
     */
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

    /**
     * Flush output buffer
     */
    protected function flushOutput(): void
    {
        if (function_exists('ob_flush')) {
            @ob_flush();
        }
        @flush();
    }

    protected function collectSessionGarbage(): void
    {
        $lottery = config('mcp.session.lottery', [2, 100]);

        if (random_int(1, $lottery[1]) <= $lottery[0]) {
            $this->sessionManager->gc();
        }
    }

    /**
     * 'Closes' the transport.
     */
    public function close(): void
    {
        $this->emit('close', ['Transport closed.']);
        $this->removeAllListeners();
    }
}
