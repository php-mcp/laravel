<?php

declare(strict_types=1);

namespace PhpMcp\Laravel\Commands;

use Illuminate\Console\Command;
use PhpMcp\Server\Server;
use PhpMcp\Server\Transports\HttpServerTransport;
use PhpMcp\Server\Transports\StdioServerTransport;

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

        $host = $this->option('host');
        $port = $this->option('port');
        $pathPrefix = $this->option('path-prefix');

        if ($transportOption === 'stdio') {
            if (! config('mcp.transports.stdio.enabled', true)) {
                $this->error('MCP STDIO transport is disabled in config/mcp.php.');

                return Command::FAILURE;
            }

            $this->info('Starting MCP server with STDIO transport...');

            try {
                $transport = new StdioServerTransport;
                $server->listen($transport);
            } catch (\Exception $e) {
                $this->error("Failed to start MCP server with STDIO transport: {$e->getMessage()}");

                return Command::FAILURE;
            }
        } elseif ($transportOption === 'http') {
            if (! config('mcp.transports.http_dedicated.enabled', true)) {
                $this->error('Dedicated MCP HTTP transport is disabled in config/mcp.php.');

                return Command::FAILURE;
            }

            $host = $this->option('host') ?? config('mcp.transports.http_dedicated.host', '127.0.0.1');
            $port = (int) ($this->option('port') ?? config('mcp.transports.http_dedicated.port', 8090));
            $pathPrefix = $this->option('path-prefix') ?? config('mcp.transports.http_dedicated.path_prefix', 'mcp_server');
            $sslContextOptions = config('mcp.transports.http_dedicated.ssl_context_options'); // For HTTPS

            $this->info("Starting MCP server with dedicated HTTP transport on http://{$host}:{$port} (prefix: /{$pathPrefix})...");
            $transport = new HttpServerTransport(
                host: $host,
                port: $port,
                mcpPathPrefix: $pathPrefix,
                sslContext: $sslContextOptions
            );

            try {
                $server->listen($transport);
            } catch (\Exception $e) {
                $this->error("Failed to start MCP server with dedicated HTTP transport: {$e->getMessage()}");

                return Command::FAILURE;
            }
        } else {
            $this->error("Invalid transport specified: {$transportOption}. Use 'stdio' or 'http'.");

            return Command::INVALID;
        }

        $this->info("MCP Server ({$transportOption}) stopped.");

        return Command::SUCCESS;
    }
}
