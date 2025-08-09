<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Version Middleware
 * 
 * Handles API versioning by setting version context and validating
 * version-specific headers and requirements.
 */
class ApiVersionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $version = 'v1'): Response
    {
        // Set the API version in the request for use throughout the application
        $request->attributes->set('api_version', $version);
        
        // Add version to request headers for internal use
        $request->headers->set('X-API-Version', $version);
        
        // Validate Accept header if provided
        $acceptHeader = $request->header('Accept');
        if ($acceptHeader && str_contains($acceptHeader, 'application/vnd.emr.')) {
            $this->validateAcceptHeader($acceptHeader, $version);
        }
        
        $response = $next($request);
        
        // Add version information to response headers
        $response->headers->set('X-API-Version', $version);
        $response->headers->set('X-API-Supported-Versions', 'v1');
        
        return $response;
    }
    
    /**
     * Validate the Accept header version
     *
     * @param string $acceptHeader
     * @param string $expectedVersion
     * @throws \Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException
     */
    private function validateAcceptHeader(string $acceptHeader, string $expectedVersion): void
    {
        // Extract version from Accept header (e.g., application/vnd.emr.v1+json)
        if (preg_match('/application\/vnd\.emr\.(\w+)\+json/', $acceptHeader, $matches)) {
            $requestedVersion = $matches[1];
            
            if ($requestedVersion !== $expectedVersion) {
                abort(406, "API version mismatch. Requested: {$requestedVersion}, Available: {$expectedVersion}");
            }
        }
    }
}