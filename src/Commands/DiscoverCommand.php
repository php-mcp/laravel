<?php

declare(strict_types=1);

namespace PhpMcp\Laravel\Commands;

use Illuminate\Console\Command;
use PhpMcp\Server\Server;

class DiscoverCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:discover 
                            {--no-cache : Perform discovery but do not update the cache}
                            {--force : Force discovery even if already run or cache seems fresh (in dev)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Discovers MCP tools, resources, and prompts and updates the cache.';

    /**
     * Execute the console command.
     */
    public function handle(Server $server): int
    {
        $noCache = $this->option('no-cache');
        $forceDiscovery = $this->option('force') ?? true;

        $this->info('Starting MCP element discovery...');

        if ($noCache) {
            $this->warn('Discovery results will NOT be saved to cache.');
        }

        try {
            $server->discover(
                basePath: config('mcp.discovery.base_path', base_path()),
                scanDirs: config('mcp.discovery.directories', ['app/Mcp']),
                excludeDirs: config('mcp.discovery.exclude_dirs', []),
                force: $forceDiscovery,
                saveToCache: ! $noCache
            );
        } catch (\Exception $e) {
            $this->error('Discovery failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $registry = $server->getRegistry();

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

        if (! $noCache && $registry->discoveryRanOrCached()) {
            $this->info('MCP element definitions updated and cached.');
        }

        return Command::SUCCESS;
    }
}
