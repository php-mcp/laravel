<?php

declare(strict_types=1);

namespace PhpMcp\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use PhpMcp\Laravel\Support\McpContext;

/**
 * Middleware to handle authentication context for MCP requests.
 *
 * This middleware extracts authentication information from HTTP requests
 * and stores it in a context that can be accessed by MCP tools and resources.
 */
class McpAuthenticationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Extract authentication information from the request
        $authContext = $this->extractAuthContext($request);
        
        // Store in MCP context for later access by tools
        McpContext::setAuthContext($authContext);
        
        // Log for debugging purposes
        Log::debug('MCP Authentication context set', [
            'has_user' => !empty($authContext['user']),
            'auth_guard' => $authContext['guard'] ?? null,
            'session_id' => $request->header('Mcp-Session-Id'),
        ]);
        
        $response = $next($request);
        
        // Clear the context after request processing
        McpContext::clearAuthContext();
        
        return $response;
    }
    
    /**
     * Extract authentication context from the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    protected function extractAuthContext(Request $request): array
    {
        $context = [
            'request_headers' => $this->getRelevantHeaders($request),
            'user' => null,
            'guard' => null,
            'token' => null,
        ];
        
        // Try different authentication guards
        foreach ($this->getAuthGuards() as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();
                $context['user'] = $user;
                $context['guard'] = $guard;
                
                // For Sanctum, also store the token
                if ($guard === 'sanctum' && method_exists($user, 'currentAccessToken')) {
                    $context['token'] = $user->currentAccessToken();
                }
                
                break;
            }
        }
        
        // If no authenticated user found, try to authenticate using Bearer token
        if (!$context['user'] && $request->bearerToken()) {
            $context = $this->attemptTokenAuthentication($request, $context);
        }
        
        return $context;
    }
    
    /**
     * Get relevant headers for authentication context.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    protected function getRelevantHeaders(Request $request): array
    {
        $headers = [];
        
        $relevantHeaders = [
            'Authorization',
            'X-API-KEY', 
            'X-Auth-Token',
            'Cookie',
            'Mcp-Session-Id',
        ];
        
        foreach ($relevantHeaders as $header) {
            if ($request->hasHeader($header)) {
                $headers[$header] = $request->header($header);
            }
        }
        
        return $headers;
    }
    
    /**
     * Get the authentication guards to try.
     *
     * @return array
     */
    protected function getAuthGuards(): array
    {
        return [
            'sanctum',
            'api',
            'web',
            config('auth.defaults.guard'),
        ];
    }
    
    /**
     * Attempt to authenticate using a bearer token.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array  $context
     * @return array
     */
    protected function attemptTokenAuthentication(Request $request, array $context): array
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return $context;
        }
        
        // Try Sanctum token authentication
        if (class_exists(\Laravel\Sanctum\PersonalAccessToken::class)) {
            try {
                $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
                
                if ($accessToken && $accessToken->can('*')) {
                    $user = $accessToken->tokenable;
                    
                    if ($user) {
                        $context['user'] = $user;
                        $context['guard'] = 'sanctum';
                        $context['token'] = $accessToken;
                        
                        // Set the authenticated user for this request
                        Auth::guard('sanctum')->setUser($user);
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to authenticate with Sanctum token', [
                    'error' => $e->getMessage(),
                    'token_prefix' => substr($token, 0, 10) . '...'
                ]);
            }
        }
        
        return $context;
    }
}
