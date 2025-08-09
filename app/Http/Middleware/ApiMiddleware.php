<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Middleware
 * 
 * Handles API-specific concerns like content type validation,
 * request/response formatting, and API-specific headers.
 */
class ApiMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Ensure JSON content type for POST/PUT/PATCH requests
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH']) && 
            !$request->isJson() && 
            !$request->hasFile('file')) {
            
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_CONTENT_TYPE',
                    'message' => 'Content-Type must be application/json for this request',
                ],
                'meta' => [
                    'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
                    'version' => $request->attributes->get('api_version', 'v1'),
                ]
            ], 415);
        }
        
        // Set default Accept header if not provided
        if (!$request->hasHeader('Accept')) {
            $request->headers->set('Accept', 'application/json');
        }
        
        $response = $next($request);
        
        // Add API-specific headers to response
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        
        return $response;
    }
}