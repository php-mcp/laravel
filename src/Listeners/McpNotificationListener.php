<?php

namespace PhpMcp\Laravel\Listeners;

use PhpMcp\Laravel\Events\McpNotificationEvent;
use PhpMcp\Laravel\Events\ResourceUpdated;
use PhpMcp\Server\Server;
use PhpMcp\Server\State\ClientStateManager;

/**
 * Handles routing MCP notifications to the appropriate transport.
 */
class McpNotificationListener
{
    private ClientStateManager $clientStateManager;
    /**
     * Create a new event handler instance.
     */
    public function __construct(
        private Server $server
    ) {
        $this->clientStateManager = $server->getClientStateManager();
    }

    /**
     * Handle the event.
     */
    public function handle(McpNotificationEvent $event): void
    {
        if ($event instanceof ResourceUpdated) {
            $this->handleResourceUpdated($event);

            return;
        }

        $this->handleListChanged($event);
    }

    /**
     * Handle resource updated notifications.
     */
    private function handleResourceUpdated(ResourceUpdated $event): void
    {
        $subscribers = $this->clientStateManager->getResourceSubscribers($event->uri);

        $message = json_encode($event->toNotification()->toArray());
        foreach ($subscribers as $clientId) {
            $this->clientStateManager->queueMessage($clientId, $message);
        }
    }

    /**
     * Handle list changed notifications (tools, prompts and resources)
     */
    private function handleListChanged(McpNotificationEvent $event): void
    {
        $activeClients = $this->clientStateManager->getActiveClients();

        $message = json_encode($event->toNotification()->toArray());
        foreach ($activeClients as $clientId) {
            $this->clientStateManager->queueMessage($clientId, $message);
        }
    }
}
