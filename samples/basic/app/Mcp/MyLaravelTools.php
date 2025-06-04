<?php

namespace App\Mcp;

use Illuminate\Support\Facades\Cache;
use PhpMcp\Server\Attributes\McpResource;
use PhpMcp\Server\Attributes\McpTool;
use Psr\Log\LoggerInterface;

class MyLaravelTools
{
    public function __construct(private LoggerInterface $logger)
    {
        $this->logger->info('MyLaravelTools instance created via container.');
    }

    /**
     * Gets the Laravel application name from config.
     *
     * @return string The application name.
     */
    #[McpResource(uri: 'config://app/name', name: 'laravel_app_name', mimeType: 'text/plain')]
    public function getAppName(): string
    {
        $appName = config('app.name', 'Laravel');
        $this->logger->debug('MCP Resource Read', ['uri' => 'config://app/name', 'value' => $appName]);

        return $appName;
    }

    /**
     * Adds two numbers using a tool.
     *
     * @param  int  $a  The first number.
     * @param  int  $b  The second number.
     * @return int The sum.
     */
    #[McpTool(name: 'laravel_adder')]
    public function add(int $a, int $b): int
    {
        $sum = $a + $b;
        $this->logger->info('MCP Tool Called', ['tool' => 'laravel_adder', 'result' => $sum]);

        Cache::put('last_mcp_sum', $sum, now()->addMinutes(5));

        return $sum;
    }

    /**
     * Gets the last calculated sum from the cache.
     *
     * @return string Description of the last sum or a default message.
     */
    #[McpTool(name: 'get_last_sum')]
    public function getLastSum(): string
    {
        $lastSum = Cache::get('last_mcp_sum', 'Not calculated yet');

        return "Last sum calculated via MCP: {$lastSum}";
    }
}
