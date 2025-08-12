<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Rate Limiting Middleware
 * 
 * Provides comprehensive rate limiting for different API endpoints
 * with different limits based on user authentication status and endpoint type.
 */
class ApiRateLimitMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $limiter = 'api'): Response
    {
        $key = $this->resolveRequestSignature($request, $limiter);
        
        $maxAttempts = $this->getMaxAttempts($limiter, $request);
        $decayMinutes = $this->getDecayMinutes($limiter);
        
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return $this->buildTooManyAttemptsResponse($key, $maxAttempts, $decayMinutes);
        }
        
        RateLimiter::hit($key, $decayMinutes * 60);
        
        $response = $next($request);
        
        return $this->addHeaders(
            $response,
            $maxAttempts,
            RateLimiter::retriesLeft($key, $maxAttempts),
            RateLimiter::availableIn($key)
        );
    }
    
    /**
     * Resolve the request signature for rate limiting.
     */
    protected function resolveRequestSignature(Request $request, string $limiter): string
    {
        $user = $request->user();
        
        if ($user) {
            return sprintf('%s:%s:%s', $limiter, $user->id, $request->ip());
        }
        
        return sprintf('%s:%s', $limiter, $request->ip());
    }
    
    /**
     * Get the maximum number of attempts for the given limiter.
     */
    protected function getMaxAttempts(string $limiter, Request $request): int
    {
        $user = $request->user();
        
        return match ($limiter) {
            'login' => 5,
            'register' => 3,
            'password-reset' => 5,
            'verification-notification' => 3,
            'uploads' => $user ? 50 : 10,
            'api' => $user ? 1000 : 100,
            'sensitive' => $user ? 20 : 5,
            default => $user ? 60 : 30,
        };
    }
    
    /**
     * Get the decay time in minutes for the given limiter.
     */
    protected function getDecayMinutes(string $limiter): int
    {
        return match ($limiter) {
            'login' => 15,
            'register' => 60,
            'password-reset' => 60,
            'verification-notification' => 60,
            'uploads' => 60,
            'sensitive' => 60,
            default => 1,
        };
    }
    
    /**
     * Build the "too many attempts" response.
     */
    protected function buildTooManyAttemptsResponse(string $key, int $maxAttempts, int $decayMinutes): Response
    {
        $retryAfter = RateLimiter::availableIn($key);
        
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'RATE_LIMIT_EXCEEDED',
                'message' => 'Too many requests. Please try again later.',
                'details' => [
                    'max_attempts' => $maxAttempts,
                    'retry_after_seconds' => $retryAfter,
                    'retry_after_minutes' => ceil($retryAfter / 60),
                ]
            ],
            'meta' => [
                'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
            ]
        ], 429, [
            'Retry-After' => $retryAfter,
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => 0,
        ]);
    }
    
    /**
     * Add rate limit headers to the response.
     */
    protected function addHeaders(Response $response, int $maxAttempts, int $remainingAttempts, int $retryAfter): Response
    {
        $response->headers->add([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => max(0, $remainingAttempts),
        ]);
        
        if ($remainingAttempts <= 0) {
            $response->headers->set('Retry-After', $retryAfter);
        }
        
        return $response;
    }
}