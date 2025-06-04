<?php

declare(strict_types=1);

namespace PhpMcp\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpMcp\Server\Definitions\PromptDefinition;
use PhpMcp\Server\Definitions\ResourceDefinition;
use PhpMcp\Server\Definitions\ResourceTemplateDefinition;
use PhpMcp\Server\Definitions\ToolDefinition;
use PhpMcp\Server\Server;

class ListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:list 
                            {type? : The type of element to list (tools, resources, prompts, templates)} 
                            {--json : Output the list as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lists discovered MCP elements (tools, resources, prompts, templates).';

    /**
     * Execute the console command.
     */
    public function handle(Server $server): int
    {
        $registry = $server->getRegistry();

        if (! $registry->hasElements() && ! $registry->discoveryRanOrCached()) {
            $this->comment('No MCP elements are manually registered, and discovery has not run (or cache is empty).');
            $this->comment('Run `php artisan mcp:discover` or ensure auto-discovery is enabled in dev.');
        } elseif (! $registry->hasElements() && $registry->discoveryRanOrCached()) {
            $this->comment('Discovery/cache load ran, but no MCP elements were found.');
        }

        $type = $this->argument('type');
        $outputJson = $this->option('json');

        $validTypes = ['tools', 'resources', 'prompts', 'templates'];

        if ($type && ! in_array($type, $validTypes)) {
            $this->error("Invalid element type '{$type}'. Valid types are: " . implode(', ', $validTypes));

            return Command::INVALID;
        }

        $elements = [
            'tools' => new Collection($registry->allTools()),
            'resources' => new Collection($registry->allResources()),
            'prompts' => new Collection($registry->allPrompts()),
            'templates' => new Collection($registry->allResourceTemplates()),
        ];

        if ($outputJson) {
            $outputData = [];
            if ($type) {
                $outputData[$type] = $this->formatCollectionForJson($elements[$type]);
            } else {
                foreach ($elements as $key => $collection) {
                    $outputData[$key] = $this->formatCollectionForJson($collection);
                }
            }
            $this->line(json_encode($outputData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return Command::SUCCESS;
        }

        if ($type) {
            $this->displayTable($type, $elements[$type]);
        } else {
            foreach ($elements as $key => $collection) {
                $this->displayTable($key, $collection);
                $this->line(''); // Add space between tables
            }
        }

        return Command::SUCCESS;
    }

    private function displayTable(string $type, Collection $collection): void
    {
        if ($collection->isEmpty()) {
            $this->info(ucfirst($type) . ': None found.');

            return;
        }

        $this->info(ucfirst($type) . ':');

        $data = match ($type) {
            'tools' => $collection->map(fn(ToolDefinition $def) => [
                'Name' => $def->getName(),
                'Description' => Str::limit($def->getDescription() ?? '-', 60),
                'Handler' => $def->getClassName() . '::' . $def->getMethodName(),
            ])->all(),
            'resources' => $collection->map(fn(ResourceDefinition $def) => [
                'URI' => $def->getUri(),
                'Name' => $def->getName(),
                'MIME' => $def->getMimeType() ?? '-',
                'Handler' => $def->getClassName() . '::' . $def->getMethodName(),
            ])->all(),
            'prompts' => $collection->map(fn(PromptDefinition $def) => [
                'Name' => $def->getName(),
                'Description' => Str::limit($def->getDescription() ?? '-', 60),
                'Handler' => $def->getClassName() . '::' . $def->getMethodName(),
            ])->all(),
            'templates' => $collection->map(fn(ResourceTemplateDefinition $def) => [
                'URI Template' => $def->getUriTemplate(),
                'Name' => $def->getName(),
                'MIME' => $def->getMimeType() ?? '-',
                'Handler' => $def->getClassName() . '::' . $def->getMethodName(),
            ])->all(),
            default => [],
        };

        if (! empty($data)) {
            $firstItem = reset($data);
            $headers = $firstItem ? array_keys($firstItem) : [];
            $this->table($headers, $data);
        } else {
            $this->line("No {$type} found or could not format data.");
        }
    }

    private function formatCollectionForJson(Collection $collection): array
    {
        return $collection->map(fn($item) => $item instanceof \JsonSerializable ? $item->jsonSerialize() : (array) $item)->values()->all();
    }
}
