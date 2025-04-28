<?php

namespace PhpMcp\Laravel\Server\Listeners;

use PhpMcp\Laravel\Server\Events\McpNotificationEvent;
use PhpMcp\Laravel\Server\Events\ResourceUpdated;
use PhpMcp\Server\State\TransportState;

/**
 * Handles routing MCP notifications to the appropriate transport.
 */
class McpNotificationListener
{
    /**
     * Create a new event handler instance.
     */
    public function __construct(
        private TransportState $transportState
    ) {}

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
        $subscribers = $this->transportState->getResourceSubscribers($event->uri);

        foreach ($subscribers as $clientId) {
            $this->transportState->queueMessage($clientId, $event->toNotification());
        }
    }

    /**
     * Handle list changed notifications (tools, prompts and resources)
     */
    private function handleListChanged(McpNotificationEvent $event): void
    {
        $activeClients = $this->transportState->getActiveClients();

        foreach ($activeClients as $clientId) {
            $this->transportState->queueMessage($clientId, $event->toNotification());
        }
    }
}
