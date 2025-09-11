<?php

/**
 * Example MCP tools demonstrating authentication usage
 * 
 * This file shows various patterns for handling authentication
 * in MCP tools with both HTTP Integrated and HTTP Dedicated modes.
 */

use PhpMcp\Laravel\Facades\Mcp;
use PhpMcp\Laravel\Facades\McpAuth;
use Illuminate\Support\Facades\Auth;

/**
 * Basic authenticated user retrieval
 * Works with both transport modes
 */
Mcp::tool('get_current_user', function () {
    // Try MCP context first (for dedicated HTTP), fallback to Laravel Auth
    $user = McpAuth::user() ?? Auth::user();
    
    if (!$user) {
        return [
            'error' => 'Authentication required',
            'message' => 'Please include a valid Authorization header with Bearer token',
        ];
    }
    
    return [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'created_at' => $user->created_at,
    ];
});

/**
 * Authentication debugging tool
 * Useful for troubleshooting authentication issues
 */
Mcp::tool('debug_auth_context', function () {
    return [
        'mcp_auth' => [
            'authenticated' => McpAuth::check(),
            'user_id' => McpAuth::user()?->id,
            'user_name' => McpAuth::user()?->name,
            'guard' => McpAuth::guard(),
            'has_token' => McpAuth::token() !== null,
            'token_type' => McpAuth::token() ? get_class(McpAuth::token()) : null,
        ],
        'laravel_auth' => [
            'authenticated' => Auth::check(),
            'user_id' => Auth::user()?->id,
            'user_name' => Auth::user()?->name,
            'default_guard' => Auth::getDefaultDriver(),
        ],
        'request_headers' => [
            'authorization' => McpAuth::header('Authorization') ? 'Present' : 'Missing',
            'session_id' => McpAuth::header('Mcp-Session-Id'),
            'all_headers' => array_keys(McpAuth::headers()),
        ],
    ];
});

/**
 * Protected action requiring authentication
 * Demonstrates error handling for unauthenticated requests
 */
Mcp::tool('protected_action', function () {
    $user = McpAuth::user() ?? Auth::user();
    
    if (!$user) {
        throw new \Exception('Authentication required to perform this action');
    }
    
    // Perform some protected operation
    return [
        'success' => true,
        'message' => "Protected action performed successfully by {$user->name}",
        'timestamp' => now()->toISOString(),
        'user_id' => $user->id,
    ];
});

/**
 * Sanctum token-specific operations
 * Shows how to work with Sanctum tokens and abilities
 */
Mcp::tool('check_token_abilities', function () {
    $user = McpAuth::user() ?? Auth::user();
    
    if (!$user) {
        return ['error' => 'Authentication required'];
    }
    
    $token = McpAuth::token();
    
    if (!$token) {
        return [
            'user' => $user->name,
            'auth_method' => 'session_based',
            'message' => 'No token found, likely using session-based authentication',
        ];
    }
    
    $abilities = [];
    if (method_exists($token, 'abilities')) {
        $abilities = $token->abilities;
    }
    
    return [
        'user' => $user->name,
        'token_name' => $token->name ?? 'Unknown',
        'token_abilities' => $abilities,
        'can_admin' => method_exists($token, 'can') ? $token->can('admin') : false,
        'can_read' => method_exists($token, 'can') ? $token->can('read') : false,
        'can_write' => method_exists($token, 'can') ? $token->can('write') : false,
        'token_created' => $token->created_at ?? null,
        'token_last_used' => $token->last_used_at ?? null,
    ];
});

/**
 * User-specific resource access
 * Shows how to filter data based on authenticated user
 */
Mcp::tool('get_user_posts', function (int $limit = 10) {
    $user = McpAuth::user() ?? Auth::user();
    
    if (!$user) {
        return ['error' => 'Authentication required'];
    }
    
    // Assuming you have a Post model with user relationship
    // This is just a demonstration - adjust based on your models
    try {
        $posts = $user->posts()
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function ($post) {
                return [
                    'id' => $post->id,
                    'title' => $post->title,
                    'excerpt' => substr($post->content, 0, 100) . '...',
                    'created_at' => $post->created_at,
                    'updated_at' => $post->updated_at,
                ];
            });
            
        return [
            'user' => $user->name,
            'posts_count' => $posts->count(),
            'posts' => $posts,
        ];
    } catch (\Exception $e) {
        return [
            'user' => $user->name,
            'message' => 'Posts feature not available in this demo',
            'error' => $e->getMessage(),
        ];
    }
});

/**
 * Multi-guard authentication example
 * Shows how to handle different authentication guards
 */
Mcp::tool('check_multi_guard_auth', function () {
    $results = [];
    
    // Check MCP context first
    if (McpAuth::check()) {
        $results['mcp'] = [
            'authenticated' => true,
            'user' => McpAuth::user()->name,
            'guard' => McpAuth::guard(),
        ];
    }
    
    // Check different Laravel guards
    $guards = ['web', 'api', 'sanctum'];
    
    foreach ($guards as $guard) {
        try {
            if (Auth::guard($guard)->check()) {
                $results[$guard] = [
                    'authenticated' => true,
                    'user' => Auth::guard($guard)->user()->name,
                ];
            } else {
                $results[$guard] = ['authenticated' => false];
            }
        } catch (\Exception $e) {
            $results[$guard] = [
                'authenticated' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    return $results;
});

/**
 * Permission-based access control
 * Demonstrates how to implement permission checks
 */
Mcp::tool('admin_only_action', function () {
    $user = McpAuth::user() ?? Auth::user();
    
    if (!$user) {
        return ['error' => 'Authentication required'];
    }
    
    // Check if user has admin role (adjust based on your role system)
    $isAdmin = false;
    
    // Example permission checks - adjust based on your implementation
    if (method_exists($user, 'hasRole')) {
        $isAdmin = $user->hasRole('admin');
    } elseif (method_exists($user, 'can')) {
        $isAdmin = $user->can('admin-access');
    } elseif (isset($user->role)) {
        $isAdmin = $user->role === 'admin';
    }
    
    // Also check token abilities for Sanctum
    $token = McpAuth::token();
    if ($token && method_exists($token, 'can')) {
        $isAdmin = $isAdmin && $token->can('admin');
    }
    
    if (!$isAdmin) {
        return [
            'error' => 'Insufficient permissions',
            'message' => 'This action requires administrator privileges',
            'user' => $user->name,
        ];
    }
    
    return [
        'success' => true,
        'message' => 'Admin action performed successfully',
        'user' => $user->name,
        'timestamp' => now()->toISOString(),
    ];
});

/**
 * Resource with authentication context
 * Shows how to use authentication in resources
 */
Mcp::resource('user://profile', function () {
    $user = McpAuth::user() ?? Auth::user();
    
    if (!$user) {
        return json_encode(['error' => 'Authentication required']);
    }
    
    return json_encode([
        'profile' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ],
        'auth_context' => [
            'guard' => McpAuth::guard() ?? Auth::getDefaultDriver(),
            'authenticated_via' => McpAuth::check() ? 'mcp_context' : 'laravel_auth',
        ],
    ]);
})->name('user_profile')->mimeType('application/json');

/**
 * Dynamic resource template with user context
 * Shows parameterized resources with authentication
 */
Mcp::resourceTemplate('user://data/{dataType}', function (string $dataType) {
    $user = McpAuth::user() ?? Auth::user();
    
    if (!$user) {
        return json_encode(['error' => 'Authentication required']);
    }
    
    $data = match ($dataType) {
        'basic' => [
            'id' => $user->id,
            'name' => $user->name,
        ],
        'contact' => [
            'email' => $user->email,
            'email_verified' => $user->email_verified_at !== null,
        ],
        'timestamps' => [
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ],
        default => ['error' => "Unknown data type: {$dataType}"],
    };
    
    return json_encode([
        'user_id' => $user->id,
        'data_type' => $dataType,
        'data' => $data,
        'retrieved_at' => now()->toISOString(),
    ]);
})->name('user_data')->mimeType('application/json');
