<?php

namespace PhpMcp\Laravel\Server\Events;

/**
 * Event dispatched when the list of available prompts changes.
 */
class PromptsListChanged extends McpNotificationEvent
{
    /**
     * Create a new event instance.
     */
    public function __construct()
    {
        parent::__construct('notifications/prompts/list_changed');
    }
}
