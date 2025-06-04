<?php

declare(strict_types=1);

namespace PhpMcp\Laravel\Transports;

use Evenement\EventEmitterTrait;
use PhpMcp\Server\Contracts\LoggerAwareInterface;
use PhpMcp\Server\Contracts\ServerTransportInterface;
use PhpMcp\Server\Exception\TransportException;
use PhpMcp\Server\State\ClientStateManager;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use React\Promise\PromiseInterface;

use function React\Promise\reject;
use function React\Promise\resolve;

class LaravelHttpTransport implements ServerTransportInterface, LoggerAwareInterface
{
    use EventEmitterTrait;

    protected LoggerInterface $logger;

    protected ClientStateManager $clientStateManager;

    /** @var array<string, true> Tracks active client IDs managed by this transport */
    private array $activeClients = [];

    public function __construct(ClientStateManager $clientStateManager)
    {
        $this->clientStateManager = $clientStateManager;
        $this->logger = new NullLogger;

        $this->on('client_connected', function (string $clientId) {
            $this->activeClients[$clientId] = true;
            $this->clientStateManager->updateClientActivity($clientId);
        });

        $this->on('client_disconnected', function (string $clientId, string $reason) {
            unset($this->activeClients[$clientId]);
        });

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
        if (! isset($this->activeClients[$clientId])) {
            $this->logger->warning('Attempted to send message to inactive or unknown client.', ['clientId' => $clientId]);

            return reject(new TransportException("Client '{$clientId}' is not actively managed by this transport."));
        }

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
        $activeClientIds = array_keys($this->activeClients);

        foreach ($activeClientIds as $clientId) {
            $this->emit('client_disconnected', [$clientId, 'Transport globally closed']);
            $this->emit('close', ['Transport closed.']);
        }

        $this->removeAllListeners();
    }
}
