<?php

declare(strict_types=1);

namespace PhpMcp\Laravel\Http\Controllers;

use Illuminate\Http\Request;
use PhpMcp\Laravel\Transports\LaravelStreamableHttpTransport;
use PhpMcp\Server\Contracts\EventStoreInterface;
use PhpMcp\Server\Server;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamableTransportController
{
    private LaravelStreamableHttpTransport $transport;

    public function __construct(Server $server)
    {
        $eventStore = $this->createEventStore();
        $sessionManager = $server->getSessionManager();

        $this->transport = new LaravelStreamableHttpTransport($sessionManager, $eventStore);
        $server->listen($this->transport, false);
    }

    public function handleGet(Request $request): Response|StreamedResponse
    {
        return $this->transport->handleGetRequest($request);
    }

    public function handlePost(Request $request): Response|StreamedResponse
    {
        return $this->transport->handlePostRequest($request);
    }

    public function handleDelete(Request $request): Response
    {
        return $this->transport->handleDeleteRequest($request);
    }

    /**
     * Create event store instance from configuration
     */
    private function createEventStore(): ?EventStoreInterface
    {
        $eventStoreFqcn = config('mcp.transports.http_integrated.event_store');

        if (!$eventStoreFqcn) {
            return null;
        }

        if (is_object($eventStoreFqcn) && $eventStoreFqcn instanceof EventStoreInterface) {
            return $eventStoreFqcn;
        }

        if (is_string($eventStoreFqcn) && class_exists($eventStoreFqcn)) {
            $instance = app($eventStoreFqcn);

            if (!$instance instanceof EventStoreInterface) {
                throw new \InvalidArgumentException(
                    "Event store class {$eventStoreFqcn} must implement EventStoreInterface"
                );
            }

            return $instance;
        }

        throw new \InvalidArgumentException(
            "Invalid event store configuration: {$eventStoreFqcn}"
        );
    }
}
