<?php

declare(strict_types=1);

namespace PhpMcp\Laravel\Commands;

use Illuminate\Console\Command;
use PhpMcp\Server\Contracts\EventStoreInterface;
use PhpMcp\Server\Server;
use PhpMcp\Server\Transports\HttpServerTransport;
use PhpMcp\Server\Transports\StdioServerTransport;
use PhpMcp\Server\Transports\StreamableHttpServerTransport;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

use function Laravel\Prompts\select;

class ServeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:serve
                            {--transport= : The transport to use (stdio or http)}
                            {--H|host= : Host for the HTTP transport (overrides config)}
                            {--P|port= : Port for the HTTP transport (overrides config)}
                            {--path-prefix= : URL path prefix for the HTTP transport (overrides config)}
                            {--watch : Watch for file changes and automatically reload the server}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Starts the MCP server using the specified transport (stdio or http). Use --watch to enable automatic reloading on file changes.';

    /**
     * Execute the console command.
     *
     * The StdioTransportHandler's start() method contains the blocking loop
     * for reading STDIN and writing to STDOUT.
     */
    public function handle(Server $server): int
    {
        $transportOption = $this->getTransportOption();

        if ($this->option('watch')) {
            return $this->handleWithFileWatcher($server, $transportOption);
        }

        return match ($transportOption) {
            'stdio' => $this->handleStdioTransport($server),
            'http' => $this->handleHttpTransport($server),
            default => $this->handleInvalidTransport($transportOption),
        };
    }

    private function getTransportOption(): string
    {
        $transportOption = $this->option('transport');

        if ($transportOption === null) {
            if ($this->input->isInteractive()) {
                $transportOption = select(
                    label: 'Choose transport protocol for MCP server communication',
                    options: [
                        'stdio' => 'STDIO',
                        'http' => 'HTTP',
                    ],
                    default: 'stdio',
                );
            } else {
                $transportOption = 'stdio';
            }
        }

        return $transportOption;
    }

    private function handleStdioTransport(Server $server): int
    {
        if (! config('mcp.transports.stdio.enabled', true)) {
            $this->error('MCP STDIO transport is disabled in config/mcp.php.');

            return Command::FAILURE;
        }

        $output = $this->output->getOutput();

        if ($output instanceof ConsoleOutputInterface) {
            $output->getErrorOutput()->writeln('Starting MCP server');
            $output->getErrorOutput()->writeln('  - Transport: STDIO');
            $output->getErrorOutput()->writeln('  - Communication: STDIN/STDOUT');
            $output->getErrorOutput()->writeln('  - Mode: JSON-RPC over Standard I/O');
        }

        try {
            $transport = new StdioServerTransport;
            $server->listen($transport);
        } catch (\Exception $e) {
            $this->error("Failed to start MCP server with STDIO transport: {$e->getMessage()}");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function handleHttpTransport(Server $server): int
    {
        if (! config('mcp.transports.http_dedicated.enabled', true)) {
            $this->error('Dedicated MCP HTTP transport is disabled in config/mcp.php.');

            return Command::FAILURE;
        }

        $isLegacy = config('mcp.transports.http_dedicated.legacy', false);
        $host = $this->option('host') ?? config('mcp.transports.http_dedicated.host', '127.0.0.1');
        $port = (int) ($this->option('port') ?? config('mcp.transports.http_dedicated.port', 8090));
        $pathPrefix = $this->option('path-prefix') ?? config('mcp.transports.http_dedicated.path_prefix', 'mcp');
        $sslContextOptions = config('mcp.transports.http_dedicated.ssl_context_options');

        return $isLegacy
            ? $this->handleSseHttpTransport($server, $host, $port, $pathPrefix, $sslContextOptions)
            : $this->handleStreamableHttpTransport($server, $host, $port, $pathPrefix, $sslContextOptions);
    }

    private function handleSseHttpTransport(Server $server, string $host, int $port, string $pathPrefix, ?array $sslContextOptions): int
    {
        $this->line("🟢 Starting MCP server on http://{$host}:{$port}");
        $this->line('  - Transport: Legacy HTTP');
        $this->line("  - SSE endpoint: http://{$host}:{$port}/{$pathPrefix}/sse");
        $this->line("  - Message endpoint: http://{$host}:{$port}/{$pathPrefix}/message");
        $this->line('  - Mode: Server-Sent Events');

        $transport = new HttpServerTransport(
            host: $host,
            port: $port,
            mcpPathPrefix: $pathPrefix,
            sslContext: $sslContextOptions
        );

        try {
            $server->listen($transport);
        } catch (\Exception $e) {
            $this->error("Failed to start MCP server with legacy HTTP transport: {$e->getMessage()}");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function handleStreamableHttpTransport(Server $server, string $host, int $port, string $pathPrefix, ?array $sslContextOptions): int
    {
        $enableJsonResponse = config('mcp.transports.http_dedicated.enable_json_response', true);
        $eventStore = $this->createEventStore();
        $stateless = config('mcp.transports.http_dedicated.stateless', false);

        $this->line("🟢 Starting MCP server on http://{$host}:{$port}");
        $this->line('  - Transport: Streamable HTTP');
        $this->line("  - MCP endpoint: http://{$host}:{$port}/{$pathPrefix}");
        $this->line('  - Mode: '.($enableJsonResponse ? 'JSON' : 'SSE Streaming'));

        $transport = new StreamableHttpServerTransport(
            host: $host,
            port: $port,
            mcpPath: $pathPrefix,
            sslContext: $sslContextOptions,
            enableJsonResponse: $enableJsonResponse,
            stateless: $stateless,
            eventStore: $eventStore
        );

        try {
            $server->listen($transport);
        } catch (\Exception $e) {
            $this->error("Failed to start MCP server with streamable HTTP transport: {$e->getMessage()}");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Create event store instance from configuration
     */
    private function createEventStore(): ?EventStoreInterface
    {
        $eventStoreFqcn = config('mcp.transports.http_dedicated.event_store');

        if (! $eventStoreFqcn) {
            return null;
        }

        if (is_object($eventStoreFqcn) && $eventStoreFqcn instanceof EventStoreInterface) {
            return $eventStoreFqcn;
        }

        if (is_string($eventStoreFqcn) && class_exists($eventStoreFqcn)) {
            $instance = app($eventStoreFqcn);

            if (! $instance instanceof EventStoreInterface) {
                throw new \InvalidArgumentException(
                    "Event store class {$eventStoreFqcn} must implement EventStoreInterface"
                );
            }

            return $instance;
        }

        throw new \InvalidArgumentException(
            "Invalid event store configuration: {$eventStoreFqcn}"
        );
    }

    /**
     * Handle the command with file watching enabled
     */
    private function handleWithFileWatcher(Server $server, string $transportOption): int
    {
        if ($transportOption === 'stdio') {
            $this->error('🛑 File watching is not supported with STDIO transport as it requires process restart.');

            return Command::FAILURE;
        }

        $this->line('🟢 Starting MCP server with file watching enabled...');
        $this->line('  - File changes will trigger server reload');

        $watchedPaths = $this->getWatchedPaths();
        $this->line('  - Watching: '.implode(', ', $watchedPaths));

        while (true) {
            $lastModified = $this->getLastModificationTime($watchedPaths);

            $process = $this->startServerProcess($transportOption);

            $this->line('👀 Server started. Watching for file changes...');

            while ($process && $this->isProcessRunning($process)) {
                usleep(2000000); // 2 seconds

                $currentModified = $this->getLastModificationTime($watchedPaths);
                if ($currentModified > $lastModified) {
                    $this->line('⏳ File changes detected. Restarting server...');
                    $this->stopProcess($process);
                    continue 2;
                }
            }

            if (! $this->isProcessRunning($process)) {
                $restartDelay = 5;
                $this->error("🛑 Server process died unexpectedly. Restarting in {$restartDelay}...");
                usleep($restartDelay * 1000000); // 5 seconds
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Get paths to watch for changes
     */
    private function getWatchedPaths(): array
    {
        $basePath = config('mcp.discovery.base_path', base_path());
        $discoveryDirs = config('mcp.discovery.directories', ['app/Mcp']);
        $mcpConfigPath = config('mcp.discovery.definitions_file', base_path('routes/mcp.php'));

        $paths = [];

        foreach ($discoveryDirs as $dir) {
            $fullPath = rtrim($basePath, '/').'/'.ltrim($dir, '/');
            if (is_dir($fullPath)) {
                $paths[] = $fullPath;
            }
        }

        if (file_exists($mcpConfigPath)) {
            $paths[] = dirname($mcpConfigPath);
        }

        $configPath = base_path('config');
        if (is_dir($configPath)) {
            $paths[] = $configPath;
        }

        return array_unique($paths);
    }

    /**
     * Get the latest modification time from watched paths
     */
    private function getLastModificationTime(array $paths): int
    {
        $latestTime = 0;

        foreach ($paths as $path) {
            if (is_file($path)) {
                $latestTime = max($latestTime, filemtime($path));
            } elseif (is_dir($path)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
                );

                foreach ($iterator as $file) {
                    if ($file->isFile() && $file->getExtension() === 'php') {
                        $latestTime = max($latestTime, $file->getMTime());
                    }
                }
            }
        }

        return $latestTime;
    }

    /**
     * Start the server process
     */
    private function startServerProcess(string $transportOption): array
    {
        $command = [
            PHP_BINARY,
            base_path('artisan'),
            'mcp:serve',
            '--transport='.$transportOption,
        ];

        if ($transportOption === 'http') {
            if ($host = $this->option('host')) {
                $command[] = '--host='.$host;
            }
            if ($port = $this->option('port')) {
                $command[] = '--port='.$port;
            }
            if ($pathPrefix = $this->option('path-prefix')) {
                $command[] = '--path-prefix='.$pathPrefix;
            }
        }

        $descriptorSpec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],   // stderr
        ];

        $process = proc_open(implode(' ', array_map('escapeshellarg', $command)), $descriptorSpec, $pipes);

        if (! is_resource($process)) {
            throw new \RuntimeException('Failed to start server process');
        }

        return [
            'process' => $process,
            'pipes' => $pipes,
        ];
    }

    /**
     * Check if process is still running
     */
    private function isProcessRunning(array $processInfo): bool
    {
        if (! isset($processInfo['process']) || ! is_resource($processInfo['process'])) {
            return false;
        }

        $status = proc_get_status($processInfo['process']);

        return $status['running'];
    }

    /**
     * Stop the process
     */
    private function stopProcess(array $processInfo): void
    {
        if (! isset($processInfo['process']) || ! is_resource($processInfo['process'])) {
            return;
        }

        if (isset($processInfo['pipes'])) {
            foreach ($processInfo['pipes'] as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
        }

        proc_terminate($processInfo['process']);
        proc_close($processInfo['process']);
    }

    private function handleInvalidTransport(string $transportOption): int
    {
        $this->error("Invalid transport specified: {$transportOption}. Use 'stdio' or 'http'.");

        return Command::INVALID;
    }
}
