<?php

declare(strict_types=1);

namespace PhpMcp\Laravel\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use PhpMcp\Laravel\Facades\McpAuth;
use PhpMcp\Laravel\Support\McpContext;
use PhpMcp\Laravel\Tests\TestCase;

class AuthenticationTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear any existing context
        McpContext::clear();
    }

    protected function tearDown(): void
    {
        // Clean up context after each test
        McpContext::clear();
        
        parent::tearDown();
    }

    /** @test */
    public function it_can_store_and_retrieve_auth_context(): void
    {
        // Create a mock user object
        $user = (object) [
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
        ];

        $authContext = [
            'user' => $user,
            'guard' => 'sanctum',
            'token' => 'test-token',
            'request_headers' => [
                'Authorization' => 'Bearer test-token',
            ],
        ];

        McpContext::setAuthContext($authContext);

        $this->assertEquals($user, McpContext::user());
        $this->assertTrue(McpContext::check());
        $this->assertEquals('sanctum', McpContext::guard());
        $this->assertEquals('test-token', McpContext::token());
        $this->assertEquals('Bearer test-token', McpContext::header('Authorization'));
    }

    /** @test */
    public function it_returns_null_when_no_auth_context(): void
    {
        $this->assertNull(McpContext::user());
        $this->assertFalse(McpContext::check());
        $this->assertNull(McpContext::guard());
        $this->assertNull(McpContext::token());
        $this->assertEmpty(McpContext::headers());
    }

    /** @test */
    public function it_can_clear_auth_context(): void
    {
        $user = (object) ['id' => 1, 'name' => 'Test User'];

        McpContext::setAuthContext([
            'user' => $user,
            'guard' => 'web',
            'request_headers' => [],
        ]);

        $this->assertTrue(McpContext::check());

        McpContext::clearAuthContext();

        $this->assertFalse(McpContext::check());
        $this->assertNull(McpContext::user());
    }

    /** @test */
    public function mcp_auth_facade_works(): void
    {
        $user = (object) [
            'id' => 1,
            'name' => 'Facade User',
        ];

        McpContext::setAuthContext([
            'user' => $user,
            'guard' => 'api',
            'token' => 'facade-token',
            'request_headers' => [
                'Authorization' => 'Bearer facade-token',
                'X-Custom-Header' => 'custom-value',
            ],
        ]);

        $this->assertEquals($user, McpAuth::user());
        $this->assertTrue(McpAuth::check());
        $this->assertEquals('api', McpAuth::guard());
        $this->assertEquals('facade-token', McpAuth::token());
        $this->assertEquals('Bearer facade-token', McpAuth::header('Authorization'));
        $this->assertEquals('custom-value', McpAuth::header('X-Custom-Header'));
        $this->assertNull(McpAuth::header('Non-Existent-Header'));
        $this->assertEquals('default', McpAuth::header('Non-Existent-Header', 'default'));
    }

    /** @test */
    public function it_handles_mixed_authentication_scenarios(): void
    {
        // Simulate Laravel's Auth system having a user
        $laravelUser = (object) ['id' => 1, 'name' => 'Laravel User'];
        Auth::shouldReceive('user')->andReturn($laravelUser);
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('getDefaultDriver')->andReturn('web');

        // But MCP context is empty
        $this->assertNull(McpAuth::user());
        $this->assertFalse(McpAuth::check());

        // Tool should fallback to Laravel auth
        $result = $this->simulateGetMeTool();
        
        $this->assertEquals($laravelUser, $result['user']);
        $this->assertEquals('laravel_auth', $result['auth_method']);
        $this->assertEquals('web', $result['guard']);
    }

    /** @test */
    public function it_prefers_mcp_context_over_laravel_auth(): void
    {
        // Set up both authentication contexts
        $laravelUser = (object) ['id' => 1, 'name' => 'Laravel User'];
        $mcpUser = (object) ['id' => 2, 'name' => 'MCP User'];

        Auth::shouldReceive('user')->andReturn($laravelUser);
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('getDefaultDriver')->andReturn('web');

        McpContext::setAuthContext([
            'user' => $mcpUser,
            'guard' => 'sanctum',
            'token' => 'mcp-token',
            'request_headers' => [],
        ]);

        // Tool should prefer MCP context
        $result = $this->simulateGetMeTool();
        
        $this->assertEquals($mcpUser, $result['user']);
        $this->assertEquals('mcp_context', $result['auth_method']);
        $this->assertEquals('sanctum', $result['guard']);
    }

    /** @test */
    public function it_handles_unauthenticated_scenarios(): void
    {
        Auth::shouldReceive('user')->andReturn(null);
        Auth::shouldReceive('check')->andReturn(false);

        $result = $this->simulateGetMeTool();

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('No authenticated user found', $result['error']);
        $this->assertArrayHasKey('context', $result);
        $this->assertArrayHasKey('mcp_context', $result);
        $this->assertArrayHasKey('auth_context', $result);
    }

    /**
     * Simulate the get_me tool from the sample routes/mcp.php
     */
    protected function simulateGetMeTool(): array
    {
        // Try MCP context first (for dedicated HTTP), fallback to Laravel Auth
        $user = McpAuth::user() ?? Auth::user();
        
        if (!$user) {
            return [
                'error' => 'No authenticated user found',
                'context' => 'Make sure to include Authorization header with Bearer token',
                'mcp_context' => McpAuth::check() ? 'MCP context available' : 'No MCP context',
                'auth_context' => Auth::check() ? 'Laravel auth available' : 'No Laravel auth',
            ];
        }
        
        return [
            'user' => $user,
            'guard' => McpAuth::guard() ?? Auth::getDefaultDriver(),
            'auth_method' => McpAuth::check() ? 'mcp_context' : 'laravel_auth',
        ];
    }
}
