<?php

declare(strict_types=1);

namespace PhpMcp\Laravel\Server\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use PhpMcp\Server\Definitions\PromptDefinition;
use PhpMcp\Server\Definitions\ResourceDefinition;
use PhpMcp\Server\Definitions\ResourceTemplateDefinition;
use PhpMcp\Server\Definitions\ToolDefinition;
use PhpMcp\Server\Registry;

class ListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:list {type? : The type of element to list (tools, resources, prompts, templates)} {--json : Output the list as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lists discovered MCP elements (tools, resources, prompts, templates).';

    /**
     * Execute the console command.
     */
    public function handle(Registry $registry): int
    {
        $registry->loadElementsFromCache(); // Ensure elements are loaded

        $type = $this->argument('type');
        $outputJson = $this->option('json');

        $validTypes = ['tools', 'resources', 'prompts', 'templates'];

        if ($type && ! in_array($type, $validTypes)) {
            $this->error("Invalid element type '{$type}'. Valid types are: ".implode(', ', $validTypes));

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
            $this->info(ucfirst($type).': None found.');

            return;
        }

        $this->info(ucfirst($type).':');

        $data = match ($type) {
            'tools' => $collection->map(fn (ToolDefinition $def) => [
                'name' => $def->getName(),
                'description' => $def->getDescription(),
                'class' => $def->getClassName(),
                'method' => $def->getMethodName(),
                // 'inputSchema' => json_encode($def->getInputSchema(), JSON_UNESCAPED_SLASHES),
            ])->all(),
            'resources' => $collection->map(fn (ResourceDefinition $def) => [
                'uri' => $def->getUri(),
                'name' => $def->getName(),
                'description' => $def->getDescription(),
                'mimeType' => $def->getMimeType(),
                'class' => $def->getClassName(),
                'method' => $def->getMethodName(),
            ])->all(),
            'prompts' => $collection->map(fn (PromptDefinition $def) => [
                'name' => $def->getName(),
                'description' => $def->getDescription(),
                'class' => $def->getClassName(),
                'method' => $def->getMethodName(),
                // 'inputSchema' => json_encode($def->getInputSchema(), JSON_UNESCAPED_SLASHES),
            ])->all(),
            'templates' => $collection->map(fn (ResourceTemplateDefinition $def) => [
                'uriTemplate' => $def->getUriTemplate(),
                'name' => $def->getName(),
                'description' => $def->getDescription(),
                'mimeType' => $def->getMimeType(),
                'class' => $def->getClassName(),
                'method' => $def->getMethodName(),
                // 'inputSchema' => json_encode($def->getInputSchema(), JSON_UNESCAPED_SLASHES),
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
        // Convert definitions to arrays for JSON output
        return $collection->map(fn ($item) => $item instanceof \JsonSerializable ? $item->jsonSerialize() : (array) $item)->values()->all();
    }
}
