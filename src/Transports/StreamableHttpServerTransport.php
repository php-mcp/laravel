<?php

declare(strict_types=1);

namespace PhpMcp\Laravel\Transports;

use Evenement\EventEmitterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PhpMcp\Schema\JsonRpc\BatchRequest;
use PhpMcp\Schema\JsonRpc\Error;
use PhpMcp\Schema\JsonRpc\Parser;
use PhpMcp\Schema\JsonRpc\Request as JsonRpcRequest;
use PhpMcp\Schema\JsonRpc\Response as JsonRpcResponse;
use PhpMcp\Server\Contracts\EventStoreInterface;
use PhpMcp\Server\Contracts\ServerTransportInterface;
use PhpMcp\Server\Session\SessionManager;
use PhpMcp\Schema\JsonRpc\Message;
use React\Promise\PromiseInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

use function React\Promise\resolve;

class StreamableHttpServerTransport implements ServerTransportInterface
{
    use EventEmitterTrait;

    public function __construct(
        protected SessionManager $sessionManager,
        protected ?EventStoreInterface $eventStore = null,
        protected bool $stateless = false
    ) {}

    protected function generateId(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function listen(): void
    {
        $this->emit('ready');
    }

    public function sendMessage(Message $message, string $sessionId, array $context = []): PromiseInterface
    {
        $rawMessage = json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (empty($rawMessage)) {
            return resolve(null);
        }

        $eventId = null;
        if ($this->eventStore && isset($context['type']) && in_array($context['type'], ['get_sse', 'post_sse'])) {
            $streamId = $context['streamId'];
            $eventId = $this->eventStore->storeEvent($streamId, $rawMessage);
        }

        $messageData = [
            'id' => $eventId ?? $this->generateId(),
            'data' => $rawMessage,
            'context' => $context,
            'timestamp' => time()
        ];

        $this->sessionManager->queueMessage($sessionId, json_encode($messageData));

        return resolve(null);
    }

    /**
     * Handle incoming HTTP POST message requests
     */
    public function handlePostRequest(Request $request): Response
    {
        $this->collectSessionGarbage();

        $acceptHeader = $request->header('Accept', '');
        if (!str_contains($acceptHeader, 'application/json') && !str_contains($acceptHeader, 'text/event-stream')) {
            $error = Error::forInvalidRequest('Not Acceptable: Client must accept application/json or text/event-stream');
            return response()->json($error, 406, $this->getCorsHeaders());
        }

        if (!$request->isJson()) {
            $error = Error::forInvalidRequest('Unsupported Media Type: Content-Type must be application/json');
            return response()->json($error, 415, $this->getCorsHeaders());
        }

        $content = $request->getContent();
        if (empty($content)) {
            Log::warning('Received POST request with empty body');
            $error = Error::forInvalidRequest('Empty request body');
            return response()->json($error, 400, $this->getCorsHeaders());
        }

        try {
            $message = Parser::parse($content);
        } catch (Throwable $e) {
            Log::error('Failed to parse MCP message from POST body', ['error' => $e->getMessage()]);
            $error = Error::forParseError('Invalid JSON: ' . $e->getMessage());
            return response()->json($error, 400, $this->getCorsHeaders());
        }

        $isInitializeRequest = ($message instanceof JsonRpcRequest && $message->method === 'initialize');
        $sessionId = null;

        if ($this->stateless) {
            $sessionId = $this->generateId();
            $this->emit('client_connected', [$sessionId]);
        } else {
            if ($isInitializeRequest) {
                if ($request->hasHeader('Mcp-Session-Id')) {
                    Log::warning('Client sent Mcp-Session-Id with InitializeRequest. Ignoring.', ['clientSentId' => $request->header('Mcp-Session-Id')]);
                    $error = Error::forInvalidRequest('Invalid request: Session already initialized. Mcp-Session-Id header not allowed with InitializeRequest.', $message->getId());
                    return response()->json($error, 400, $this->getCorsHeaders());
                }

                $sessionId = $this->generateId();
                $this->emit('client_connected', [$sessionId]);
            } else {
                $sessionId = $request->header('Mcp-Session-Id');

                if (empty($sessionId)) {
                    Log::warning('POST request without Mcp-Session-Id');
                    $error = Error::forInvalidRequest('Mcp-Session-Id header required for POST requests', $message->getId());
                    return response()->json($error, 400, $this->getCorsHeaders());
                }
            }
        }

        $context = [
            'is_initialize_request' => $isInitializeRequest,
            'stateless' => $this->stateless,
        ];

        $nRequests = match (true) {
            $message instanceof JsonRpcRequest => 1,
            $message instanceof BatchRequest => $message->nRequests(),
            default => 0,
        };

        if ($nRequests === 0) {
            $context['type'] = 'post_202';
            $this->emit('message', [$message, $sessionId, $context]);

            return response()->json(JsonRpcResponse::make(1, []), 202, $this->getCorsHeaders());
        }

        $enableJsonResponse = config('mcp.transports.http_integrated.enable_json_response', true);

        return $enableJsonResponse
            ? $this->handleJsonResponse($message, $sessionId, $context)
            : $this->handleSseResponse($message, $sessionId, $nRequests, $context);
    }

    /**
     * Handle direct JSON response mode
     */
    protected function handleJsonResponse(Message $message, string $sessionId, array $context): Response
    {
        try {
            $context['type'] = 'post_json';
            $this->emit('message', [$message, $sessionId, $context]);

            $messages = $this->dequeueMessagesForContext($sessionId, 'post_json');

            if (empty($messages)) {
                $error = Error::forInternalError('Internal error');
                return response()->json($error, 500, $this->getCorsHeaders());
            }

            $responseMessage = $messages[0];
            $data = $responseMessage['data'];

            $headers = [
                'Content-Type' => 'application/json',
                ...$this->getCorsHeaders()
            ];

            if ($context['is_initialize_request'] ?? false && !$this->stateless) {
                $headers['Mcp-Session-Id'] = $sessionId;
            }

            if ($this->stateless) {
                $this->emit('client_disconnected', [$sessionId, 'Stateless request completed']);
            }

            return response()->make($data, 200, $headers);
        } catch (Throwable $e) {
            Log::error('JSON response mode error', ['exception' => $e]);
            $error = Error::forInternalError('Internal error');
            return response()->json($error, 500, $this->getCorsHeaders());
        }
    }

    /**
     * Handle SSE streaming response mode
     */
    protected function handleSseResponse(Message $message, string $sessionId, int $nRequests, array $context): StreamedResponse
    {
        $headers = array_merge([
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ], $this->getCorsHeaders());

        if ($context['is_initialize_request'] ?? false && !$this->stateless) {
            $headers['Mcp-Session-Id'] = $sessionId;
        }

        return response()->stream(function () use ($sessionId, $nRequests, $message, $context) {
            $streamId = $this->generateId();
            $context['type'] = 'post_sse';
            $context['streamId'] = $streamId;
            $context['nRequests'] = $nRequests;

            $this->emit('message', [$message, $sessionId, $context]);

            $messages = $this->dequeueMessagesForContext($sessionId, 'post_sse', $streamId);

            $this->sendSseEvent($messages[0]['data'], $messages[0]['id']);

            if ($this->stateless) {
                $this->emit('client_disconnected', [$sessionId, 'Stateless request completed']);
            }
        }, headers: $headers);
    }

    /**
     * Handle GET request with event replay support
     */
    public function handleGetRequest(Request $request): StreamedResponse|Response
    {
        if ($this->stateless) {
            return new Response('SSE not available in stateless mode', 405, [
                'Allow' => 'POST, DELETE, OPTIONS'
            ]);
        }

        $acceptHeader = $request->header('Accept');
        if (!str_contains($acceptHeader, 'text/event-stream')) {
            $error = Error::forInvalidRequest("Not Acceptable: Client must accept text/event-stream for GET requests.");
            return response()->json($error, 406, $this->getCorsHeaders());
        }

        $sessionId = $request->header('Mcp-Session-Id');
        if (empty($sessionId)) {
            Log::warning("GET request without Mcp-Session-Id.");
            $error = Error::forInvalidRequest("Mcp-Session-Id header required for GET requests.");
            return response()->json($error, 400, $this->getCorsHeaders());
        }

        $lastEventId = $request->header('Last-Event-ID');

        $pollInterval = (int) config('mcp.transports.http_integrated.sse_poll_interval', 1);
        if ($pollInterval < 1) {
            $pollInterval = 1;
        }

        return response()->stream(function () use ($sessionId, $pollInterval, $lastEventId) {
            @set_time_limit(0);

            if ($lastEventId && $this->eventStore) {
                $this->replayEvents($lastEventId, $sessionId);
            }

            while (true) {
                if (connection_aborted()) {
                    break;
                }

                if (!$this->sessionManager->hasSession($sessionId)) {
                    break;
                }

                $messages = $this->dequeueMessagesForContext($sessionId, 'get_sse');
                foreach ($messages as $messageData) {
                    $this->sendSseEvent(rtrim($messageData['data'], "\n"), $messageData['id']);
                }

                usleep($pollInterval * 1000000);
            }
        }, headers: array_merge([
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ], $this->getCorsHeaders()));
    }

    /**
     * Handle DELETE request for session termination
     */
    public function handleDeleteRequest(Request $request): Response
    {
        if ($this->stateless) {
            return response()->noContent(headers: $this->getCorsHeaders());
        }

        $sessionId = $request->header('Mcp-Session-Id');
        if (empty($sessionId)) {
            Log::warning("DELETE request without Mcp-Session-Id.");
            $error = Error::forInvalidRequest("Mcp-Session-Id header required for DELETE requests.");
            return response()->json($error, 400, $this->getCorsHeaders());
        }

        $this->sessionManager->dequeueMessages($sessionId);

        $this->emit('client_disconnected', [$sessionId, 'Session terminated by DELETE request']);

        return response()->noContent(headers: $this->getCorsHeaders());
    }

    /**
     * Dequeue messages for specific context, requeue others
     */
    protected function dequeueMessagesForContext(string $sessionId, string $type, ?string $streamId = null): array
    {
        $allMessages = $this->sessionManager->dequeueMessages($sessionId);
        $contextMessages = [];
        $requeueMessages = [];

        foreach ($allMessages as $rawMessage) {
            $messageData = json_decode($rawMessage, true);
            $context = $messageData['context'] ?? [];

            if ($messageData) {
                $matchesContext = $context['type'] === $type;

                if ($type === 'post_sse' && $streamId) {
                    $matchesContext = $matchesContext && isset($context['streamId']) && $context['streamId'] === $streamId;
                }

                if ($matchesContext) {
                    $contextMessages[] = $messageData;
                } else {
                    $requeueMessages[] = $rawMessage;
                }
            }
        }

        foreach ($requeueMessages as $requeueMessage) {
            $this->sessionManager->queueMessage($sessionId, $requeueMessage);
        }

        return $contextMessages;
    }

    /**
     * Replay events from event store
     */
    protected function replayEvents(string $lastEventId, string $sessionId): void
    {
        if (!$this->eventStore) {
            return;
        }

        try {
            $streamKey = "get_stream_{$sessionId}";
            $this->eventStore->replayEventsAfter(
                $lastEventId,
                function (string $replayedEventId, string $json) {
                    Log::debug('Replaying event', ['replayedEventId' => $replayedEventId]);
                    $this->sendSseEvent($json, $replayedEventId);
                }
            );
        } catch (Throwable $e) {
            Log::error('Error during event replay', ['sessionId' => $sessionId, 'exception' => $e]);
        }
    }

    /**
     * Send an SSE event
     */
    private function sendSseEvent(string $data, ?string $id = null): void
    {
        if (connection_aborted()) {
            return;
        }

        echo "event: message\n";
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
    private function flushOutput(): void
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
     * Get CORS headers
     */
    protected function getCorsHeaders(): array
    {
        return [
            'Access-Control-Allow-Origin' => config('mcp.transports.http_integrated.cors_origin', '*'),
            'Access-Control-Allow-Methods' => 'GET, POST, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Mcp-Session-Id, Last-Event-ID, Authorization, Accept',
        ];
    }

    public function close(): void
    {
        $this->emit('close', ['Transport closed.']);
        $this->removeAllListeners();
    }
}
