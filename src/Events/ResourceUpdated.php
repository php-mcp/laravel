<?php

namespace PhpMcp\Laravel\Server\Events;

/**
 * Event dispatched when a resource has been updated.
 */
class ResourceUpdated extends McpNotificationEvent
{
    /**
     * Create a new event instance.
     *
     * @param  string  $uri  The URI of the updated resource
     */
    public function __construct(public string $uri)
    {
        parent::__construct('notifications/resource/updated');
    }

    /**
     * Get the notification parameters.
     */
    public function getParams(): array
    {
        return [
            'uri' => $this->uri,
        ];
    }
}
