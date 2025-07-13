<?php

declare(strict_types=1);

namespace PhpMcp\Laravel\Commands;

use Illuminate\Console\Command;
use PhpMcp\Server\Server;
use PhpMcp\Server\Contracts\EventStoreInterface;
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
                            {--path-prefix= : URL path prefix for the HTTP transport (overrides config)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Starts the MCP server using the specified transport (stdio or http).';

    /**
     * Execute the console command.
     *
     * The StdioTransportHandler's start() method contains the blocking loop
     * for reading STDIN and writing to STDOUT.
     */
    public function handle(Server $server): int
    {
        $transportOption = $this->getTransportOption();

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
            $output->getErrorOutput()->writeln("Starting MCP server");
            $output->getErrorOutput()->writeln("  - Transport: STDIO");
            $output->getErrorOutput()->writeln("  - Communication: STDIN/STDOUT");
            $output->getErrorOutput()->writeln("  - Mode: JSON-RPC over Standard I/O");
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
        $this->info("Starting MCP server on http://{$host}:{$port}");
        $this->line("  - Transport: Legacy HTTP");
        $this->line("  - SSE endpoint: http://{$host}:{$port}/{$pathPrefix}/sse");
        $this->line("  - Message endpoint: http://{$host}:{$port}/{$pathPrefix}/message");
        $this->line("  - Mode: Server-Sent Events");

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

        $this->info("Starting MCP server on http://{$host}:{$port}");
        $this->line("  - Transport: Streamable HTTP");
        $this->line("  - MCP endpoint: http://{$host}:{$port}/{$pathPrefix}");
        $this->line("  - Mode: " . ($enableJsonResponse ? 'JSON' : 'SSE Streaming'));

        $transport = new StreamableHttpServerTransport(
            host: $host,
            port: $port,
            mcpPath: $pathPrefix,
            sslContext: $sslContextOptions,
            enableJsonResponse: $enableJsonResponse,
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

        if (!$eventStoreFqcn) {
            return null;
        }

        if (is_object($eventStoreFqcn) && $eventStoreFqcn instanceof EventStoreInterface) {
            return $eventStoreFqcn;
        }

        if (is_string($eventStoreFqcn) && class_exists($eventStoreFqcn)) {
            $instance = app($eventStoreFqcn);

            if (!$instance instanceof EventStoreInterface) {
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

    private function handleInvalidTransport(string $transportOption): int
    {
        $this->error("Invalid transport specified: {$transportOption}. Use 'stdio' or 'http'.");

        return Command::INVALID;
    }
}
