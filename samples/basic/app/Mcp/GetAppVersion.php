<?php

namespace App\Mcp;

class GetAppVersion
{
    /**
     * Provides the Laravel application version.
     *
     * @return string The application version.
     */
    public function __invoke(): string
    {
        return '1.2.3';
    }
}
