<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Request Validation Middleware
 * 
 * Provides comprehensive request validation including:
 * - Input sanitization
 * - Security headers validation
 * - Request size limits
 * - Content type validation
 */
class ValidateApiRequestMiddleware
{
    /**
     * Maximum request size in bytes (10MB)
     */
    protected const MAX_REQUEST_SIZE = 10 * 1024 * 1024;
    
    /**
     * Allowed content types for API requests
     */
    protected const ALLOWED_CONTENT_TYPES = [
        'application/json',
        'multipart/form-data',
        'application/x-www-form-urlencoded'
    ];
    
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Validate request size
        if ($this->exceedsMaxSize($request)) {
            return $this->payloadTooLargeResponse();
        }
        
        // Validate content type for data-sending methods
        if ($this->requiresContentTypeValidation($request) && !$this->hasValidContentType($request)) {
            return $this->unsupportedMediaTypeResponse();
        }
        
        // Sanitize input data
        $this->sanitizeInput($request);
        
        // Validate common security headers
        $this->validateSecurityHeaders($request);
        
        return $next($request);
    }
    
    /**
     * Check if request exceeds maximum allowed size.
     */
    protected function exceedsMaxSize(Request $request): bool
    {
        $contentLength = $request->header('Content-Length');
        
        if ($contentLength && (int) $contentLength > self::MAX_REQUEST_SIZE) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if request method requires content type validation.
     */
    protected function requiresContentTypeValidation(Request $request): bool
    {
        return in_array($request->method(), ['POST', 'PUT', 'PATCH']);
    }
    
    /**
     * Check if request has valid content type.
     */
    protected function hasValidContentType(Request $request): bool
    {
        $contentType = $request->header('Content-Type');
        
        if (!$contentType) {
            return false;
        }
        
        // Extract main content type (ignore charset, boundary, etc.)
        $mainContentType = strtok($contentType, ';');
        
        return in_array($mainContentType, self::ALLOWED_CONTENT_TYPES);
    }
    
    /**
     * Sanitize input data to prevent common attacks.
     */
    protected function sanitizeInput(Request $request): void
    {
        // Get all input data
        $input = $request->all();
        
        // Recursively sanitize input
        $sanitized = $this->sanitizeArray($input);
        
        // Replace request input with sanitized data
        $request->replace($sanitized);
    }
    
    /**
     * Recursively sanitize array data.
     */
    protected function sanitizeArray(array $data): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value);
            } elseif (is_string($value)) {
                // Remove null bytes and control characters
                $sanitized[$key] = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
                
                // Trim whitespace
                $sanitized[$key] = trim($sanitized[$key]);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Validate security headers.
     */
    protected function validateSecurityHeaders(Request $request): void
    {
        // Check for suspicious user agents
        $userAgent = $request->header('User-Agent');
        if ($userAgent && $this->isSuspiciousUserAgent($userAgent)) {
            // Log suspicious activity but don't block
            logger()->warning('Suspicious user agent detected', [
                'user_agent' => $userAgent,
                'ip' => $request->ip(),
                'url' => $request->fullUrl(),
            ]);
        }
        
        // Validate origin header for CORS
        $origin = $request->header('Origin');
        if ($origin && !$this->isAllowedOrigin($origin)) {
            logger()->warning('Request from disallowed origin', [
                'origin' => $origin,
                'ip' => $request->ip(),
                'url' => $request->fullUrl(),
            ]);
        }
    }
    
    /**
     * Check if user agent is suspicious.
     */
    protected function isSuspiciousUserAgent(string $userAgent): bool
    {
        $suspiciousPatterns = [
            '/bot/i',
            '/crawler/i',
            '/spider/i',
            '/scraper/i',
            '/curl/i',
            '/wget/i',
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if origin is allowed.
     */
    protected function isAllowedOrigin(string $origin): bool
    {
        $allowedOrigins = config('cors.allowed_origins', ['*']);
        
        if (in_array('*', $allowedOrigins)) {
            return true;
        }
        
        return in_array($origin, $allowedOrigins);
    }
    
    /**
     * Return payload too large response.
     */
    protected function payloadTooLargeResponse(): Response
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'PAYLOAD_TOO_LARGE',
                'message' => 'Request payload exceeds maximum allowed size',
                'details' => [
                    'max_size_mb' => self::MAX_REQUEST_SIZE / (1024 * 1024),
                ]
            ],
            'meta' => [
                'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
            ]
        ], 413);
    }
    
    /**
     * Return unsupported media type response.
     */
    protected function unsupportedMediaTypeResponse(): Response
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'UNSUPPORTED_MEDIA_TYPE',
                'message' => 'Content-Type not supported for this request',
                'details' => [
                    'allowed_types' => self::ALLOWED_CONTENT_TYPES,
                ]
            ],
            'meta' => [
                'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
            ]
        ], 415);
    }
}