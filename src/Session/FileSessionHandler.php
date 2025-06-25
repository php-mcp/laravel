<?php

declare(strict_types=1);

namespace PhpMcp\Laravel\Session;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use PhpMcp\Server\Contracts\SessionHandlerInterface;
use Symfony\Component\Finder\Finder;

class FileSessionHandler implements SessionHandlerInterface
{
    /**
     * The filesystem instance.
     */
    protected Filesystem $files;

    /**
     * The path where sessions should be stored.
     */
    protected string $path;

    /**
     * The number of seconds the session should be valid.
     */
    protected int $ttl;

    /**
     * Create a new file driven handler instance.
     */
    public function __construct(Filesystem $files, string $path, int $ttl = 3600)
    {
        if (!$files->isDirectory($path)) {
            $files->makeDirectory($path, 0755, true);
        }

        $this->files = $files;
        $this->path = $path;
        $this->ttl = $ttl;
    }

    /**
     * {@inheritdoc}
     */
    public function read(string $sessionId): string|false
    {
        $path = $this->path . '/' . $sessionId;

        if (
            $this->files->isFile($path) &&
            $this->files->lastModified($path) >= Carbon::now()->subSeconds($this->ttl)->getTimestamp()
        ) {
            return $this->files->sharedGet($path);
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $sessionId, string $data): bool
    {
        $this->files->put($this->path . '/' . $sessionId, $data, true);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function destroy(string $sessionId): bool
    {
        $this->files->delete($this->path . '/' . $sessionId);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function gc(int $maxLifetime): array
    {
        $files = Finder::create()
            ->in($this->path)
            ->files()
            ->ignoreDotFiles(true)
            ->date('<= now - ' . $maxLifetime . ' seconds');

        $deletedSessions = [];

        foreach ($files as $file) {
            $sessionId = $file->getBasename();
            $this->files->delete($file->getRealPath());
            $deletedSessions[] = $sessionId;
        }

        return $deletedSessions;
    }
}
