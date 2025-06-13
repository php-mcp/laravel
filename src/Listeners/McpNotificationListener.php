<?php

namespace PhpMcp\Laravel\Listeners;

use PhpMcp\Laravel\Events\McpNotificationEvent;
use PhpMcp\Laravel\Events\PromptsListChanged;
use PhpMcp\Laravel\Events\ResourceUpdated;
use PhpMcp\Laravel\Events\ResourcesListChanged;
use PhpMcp\Laravel\Events\ToolsListChanged;
use PhpMcp\Server\Registry;

/**
 * Handles routing MCP notifications to the appropriate transport.
 */
class McpNotificationListener
{
    /**
     * Create a new event handler instance.
     */
    public function __construct(private Registry $registry) {}

    /**
     * Handle the event.
     */
    public function handle(McpNotificationEvent $event): void
    {
        match (true) {
            $event instanceof ResourceUpdated => $this->registry->notifyResourceUpdated($event->uri),
            $event instanceof ToolsListChanged => $this->registry->notifyToolsListChanged(),
            $event instanceof ResourcesListChanged => $this->registry->notifyResourcesListChanged(),
            $event instanceof PromptsListChanged => $this->registry->notifyPromptsListChanged(),
        };
    }
}
