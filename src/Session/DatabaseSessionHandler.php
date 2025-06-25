<?php

declare(strict_types=1);

namespace PhpMcp\Laravel\Session;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use PhpMcp\Server\Contracts\SessionHandlerInterface;

class DatabaseSessionHandler implements SessionHandlerInterface
{
    /**
     * The database connection instance.
     */
    protected ConnectionInterface $connection;

    /**
     * The name of the session table.
     */
    protected string $table;

    /**
     * The number of seconds the session should be valid.
     */
    protected int $ttl;

    /**
     * The existence state of the session.
     */
    protected bool $exists = false;

    /**
     * Create a new database session handler instance.
     */
    public function __construct(ConnectionInterface $connection, string $table, int $ttl = 3600)
    {
        $this->connection = $connection;
        $this->table = $table;
        $this->ttl = $ttl;
    }

    /**
     * {@inheritdoc}
     */
    public function read(string $sessionId): string|false
    {
        $session = (object) $this->getQuery()->find($sessionId);

        if ($this->expired($session)) {
            $this->exists = true;
            return false;
        }

        if (isset($session->payload)) {
            $this->exists = true;
            return base64_decode($session->payload);
        }

        $this->exists = false;
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $sessionId, string $data): bool
    {
        $payload = $this->getDefaultPayload($data);

        if (!$this->exists) {
            $this->read($sessionId);
        }

        if ($this->exists) {
            $this->performUpdate($sessionId, $payload);
        } else {
            $this->performInsert($sessionId, $payload);
        }

        return $this->exists = true;
    }

    /**
     * {@inheritdoc}
     */
    public function destroy(string $sessionId): bool
    {
        $this->getQuery()->where('id', $sessionId)->delete();
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function gc(int $maxLifetime): array
    {
        // Get session IDs that will be deleted
        $deletedSessions = $this->getQuery()
            ->where('last_activity', '<=', $this->currentTime() - $maxLifetime)
            ->pluck('id')
            ->toArray();

        // Delete the sessions
        $this->getQuery()
            ->where('last_activity', '<=', $this->currentTime() - $maxLifetime)
            ->delete();

        return $deletedSessions;
    }

    /**
     * Determine if the session is expired.
     */
    protected function expired(object $session): bool
    {
        return isset($session->last_activity) &&
            $session->last_activity < Carbon::now()->subSeconds($this->ttl)->getTimestamp();
    }

    /**
     * Perform an insert operation on the session ID.
     */
    protected function performInsert(string $sessionId, array $payload): ?bool
    {
        try {
            return $this->getQuery()->insert(Arr::set($payload, 'id', $sessionId));
        } catch (QueryException) {
            $this->performUpdate($sessionId, $payload);
            return null;
        }
    }

    /**
     * Perform an update operation on the session ID.
     */
    protected function performUpdate(string $sessionId, array $payload): int
    {
        return $this->getQuery()->where('id', $sessionId)->update($payload);
    }

    /**
     * Get the default payload for the session.
     */
    protected function getDefaultPayload(string $data): array
    {
        return [
            'payload' => base64_encode($data),
            'last_activity' => $this->currentTime(),
        ];
    }

    /**
     * Get the current UNIX timestamp.
     */
    protected function currentTime(): int
    {
        return Carbon::now()->getTimestamp();
    }

    /**
     * Get a fresh query builder instance for the table.
     */
    protected function getQuery(): \Illuminate\Database\Query\Builder
    {
        return $this->connection->table($this->table)->useWritePdo();
    }
}
