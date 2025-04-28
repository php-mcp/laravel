<?php

declare(strict_types=1);

namespace PhpMcp\Laravel\Server\Commands;

use Illuminate\Console\Command;
use PhpMcp\Server\Registry;
use PhpMcp\Server\Server;
use Psr\Log\LoggerInterface;
use Throwable;

class DiscoverCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:discover {--no-cache : Perform discovery but do not update the cache}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Discovers MCP tools, resources, and prompts and updates the cache.';

    /**
     * Execute the console command.
     */
    public function handle(Server $server, Registry $registry, LoggerInterface $logger): int
    {
        $noCache = $this->option('no-cache');

        $this->info('Starting MCP element discovery...');

        if ($noCache) {
            $this->warn('Performing discovery without updating the cache.');
        }

        try {
            $server->discover(true);

            $toolsCount = $registry->allTools()->count();
            $resourcesCount = $registry->allResources()->count();
            $templatesCount = $registry->allResourceTemplates()->count();
            $promptsCount = $registry->allPrompts()->count();

            $this->info('Discovery complete.');
            $this->table(
                ['Element Type', 'Count'],
                [
                    ['Tools', $toolsCount],
                    ['Resources', $resourcesCount],
                    ['Resource Templates', $templatesCount],
                    ['Prompts', $promptsCount],
                ]
            );

            if (! $noCache) {
                $this->info('Element cache updated.');
            }

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $logger->error('MCP Discovery failed', ['exception' => $e]);
            $this->error('Discovery failed: '.$e->getMessage());
            if ($this->getOutput()->isVeryVerbose()) {
                $this->line($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }
}
