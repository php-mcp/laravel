<?php

declare(strict_types=1);

namespace PhpMcp\Laravel\Server\Commands;

use Illuminate\Console\Command;
use PhpMcp\Server\Transports\StdioTransportHandler;
use Psr\Log\LoggerInterface;

class ServeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:serve';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Starts the MCP server using the configured STDIO transport.';

    /**
     * Execute the console command.
     *
     * The StdioTransportHandler's start() method contains the blocking loop
     * for reading STDIN and writing to STDOUT.
     */
    public function handle(StdioTransportHandler $handler, LoggerInterface $logger): int
    {
        if (! config('mcp.transports.stdio.enabled', false)) {
            $this->error('MCP STDIO transport is disabled. Cannot run mcp:serve.');

            return Command::FAILURE;
        }

        $logger->info('Starting MCP server via mcp:serve (STDIO)...');
        $this->info('MCP server starting via STDIO. Listening for requests...');

        $exitCode = $handler->start();

        $logger->info('MCP server (mcp:serve) stopped.', ['exitCode' => $exitCode]);
        $this->info('MCP server stopped.');

        return $exitCode;
    }
}
