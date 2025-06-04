<?php

namespace PhpMcp\Laravel\Events;

/**
 * Event dispatched when the list of available tools changes.
 */
class ToolsListChanged extends McpNotificationEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct()
    {
        parent::__construct('notifications/tools/list_changed');
    }
}
