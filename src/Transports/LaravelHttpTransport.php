<?php

declare(strict_types=1);

namespace PhpMcp\Laravel\Transports;

use Evenement\EventEmitterTrait;
use PhpMcp\Server\Contracts\LoggerAwareInterface;
use PhpMcp\Server\Contracts\ServerTransportInterface;
use PhpMcp\Server\State\ClientStateManager;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;

class LaravelHttpTransport implements ServerTransportInterface, LoggerAwareInterface
{
    use EventEmitterTrait;

    protected LoggerInterface $logger;

    protected ClientStateManager $clientStateManager;

    public function __construct(ClientStateManager $clientStateManager)
    {
        $this->clientStateManager = $clientStateManager;
        $this->logger = new NullLogger;

        $this->on('message', function (string $message, string $clientId) {
            $this->clientStateManager->updateClientActivity($clientId);
        });
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
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
     * Queues a message to be sent to the client via the ClientStateManager.
     * The McpController's SSE loop will pick this up.
     * The $rawFramedMessage is expected to be a complete JSON-RPC string (usually ending with \n, but we'll trim).
     */
    public function sendToClientAsync(string $clientId, string $rawFramedMessage): PromiseInterface
    {
        $messagePayload = rtrim($rawFramedMessage, "\n");

        if (empty($messagePayload)) {
            return resolve(null);
        }

        $this->clientStateManager->queueMessage($clientId, $messagePayload);

        return resolve(null);
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
