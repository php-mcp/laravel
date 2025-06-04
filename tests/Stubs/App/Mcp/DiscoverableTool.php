<?php

namespace PhpMcp\Laravel\Tests\Stubs\App\Mcp;

use PhpMcp\Server\Attributes\McpTool;

class DiscoverableTool
{
    #[McpTool(name: 'stub_tool_one')]
    public function toolOne(): string
    {
        return "from stub tool one";
    }
}
