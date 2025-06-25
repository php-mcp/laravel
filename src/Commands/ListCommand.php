<?php

declare(strict_types=1);

namespace PhpMcp\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpMcp\Server\Server;
use PhpMcp\Schema\Tool;
use PhpMcp\Schema\Resource;
use PhpMcp\Schema\Prompt;
use PhpMcp\Schema\ResourceTemplate;

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

        if (! $registry->hasElements()) {
            $this->comment('MCP Registry is empty.');
            $this->comment('Run `php artisan mcp:discover` to discover MCP elements.');

            return Command::SUCCESS;
        }

        $type = $this->argument('type');
        $outputJson = $this->option('json');

        $validTypes = ['tools', 'resources', 'prompts', 'templates'];

        if ($type && ! in_array($type, $validTypes)) {
            $this->error("Invalid element type '{$type}'. Valid types are: " . implode(', ', $validTypes));

            return Command::INVALID;
        }

        $elements = [
            'tools' => new Collection($registry->getTools()),
            'resources' => new Collection($registry->getResources()),
            'prompts' => new Collection($registry->getPrompts()),
            'templates' => new Collection($registry->getResourceTemplates()),
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
            'tools' => $collection->map(fn(Tool $def) => [
                'Name' => $def->name,
                'Description' => Str::limit($def->description ?? '-', 60),
                // 'Handler' => $def->handler,
            ])->all(),
            'resources' => $collection->map(fn(Resource $def) => [
                'URI' => $def->uri,
                'Name' => $def->name,
                'MIME' => $def->mimeType ?? '-',
                // 'Handler' => $def->handler,
            ])->all(),
            'prompts' => $collection->map(fn(Prompt $def) => [
                'Name' => $def->name,
                'Description' => Str::limit($def->description ?? '-', 60),
                // 'Handler' => $def->handler,
            ])->all(),
            'templates' => $collection->map(fn(ResourceTemplate $def) => [
                'URI Template' => $def->uriTemplate,
                'Name' => $def->name,
                'MIME' => $def->mimeType ?? '-',
                // 'Handler' => $def->handler,
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
