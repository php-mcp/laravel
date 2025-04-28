<?php

namespace PhpMcp\Laravel\Server\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use PhpMcp\Server\JsonRpc\Notification;

/**
 * Base event class for MCP notifications that should be sent to clients.
 */
abstract class McpNotificationEvent
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  string  $method  The MCP notification method name
     */
    public function __construct(public readonly string $method) {}

    /**
     * Get the notification method.
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get the notification parameters.
     *
     * @return array Parameters for the notification
     */
    public function getParams(): array
    {
        return [];
    }

    /**
     * Get the notification as a Response object.
     */
    public function toNotification(): Notification
    {
        return Notification::make($this->method, $this->getParams());
    }
}
