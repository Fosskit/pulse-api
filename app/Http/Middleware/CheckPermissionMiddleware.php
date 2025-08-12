<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Spatie\Permission\Exceptions\UnauthorizedException;

/**
 * Permission Check Middleware
 * 
 * Validates user permissions for API endpoints using Spatie Laravel Permission.
 * Supports both role and permission-based authorization.
 */
class CheckPermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission = null, string $guard = 'api'): Response
    {
        $user = $request->user($guard);
        
        if (!$user) {
            return $this->unauthorizedResponse('Authentication required');
        }
        
        // If no specific permission is required, just check authentication
        if (!$permission) {
            return $next($request);
        }
        
        // Check if user has the required permission
        if (!$user->can($permission)) {
            return $this->forbiddenResponse($permission);
        }
        
        return $next($request);
    }
    
    /**
     * Return unauthorized response.
     */
    protected function unauthorizedResponse(string $message): Response
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => $message,
            ],
            'meta' => [
                'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
            ]
        ], 401);
    }
    
    /**
     * Return forbidden response.
     */
    protected function forbiddenResponse(string $permission): Response
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'FORBIDDEN',
                'message' => 'Insufficient permissions to access this resource',
                'details' => [
                    'required_permission' => $permission,
                ]
            ],
            'meta' => [
                'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
            ]
        ], 403);
    }
}