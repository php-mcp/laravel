<?php

namespace PhpMcp\Laravel\Server\Events;

/**
 * Event dispatched when the list of available resources changes.
 */
class ResourcesListChanged extends McpNotificationEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct()
    {
        parent::__construct('notifications/resources/list_changed');
    }
}
