<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * API Service Provider
 * 
 * Configures API-specific services including rate limiting,
 * authentication, and other API concerns.
 */
class ApiServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // General API rate limiting
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code' => 'RATE_LIMIT_EXCEEDED',
                            'message' => 'Too many requests. Please try again later.',
                            'details' => [
                                'retry_after' => $headers['Retry-After'] ?? null,
                            ],
                        ],
                        'meta' => [
                            'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
                            'version' => 'v1',
                        ]
                    ], 429, $headers);
                });
        });

        // Authentication rate limiting (stricter for login attempts)
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code' => 'LOGIN_RATE_LIMIT_EXCEEDED',
                            'message' => 'Too many login attempts. Please try again later.',
                            'details' => [
                                'retry_after' => $headers['Retry-After'] ?? null,
                            ],
                        ],
                        'meta' => [
                            'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
                            'version' => 'v1',
                        ]
                    ], 429, $headers);
                });
        });

        // Upload rate limiting
        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code' => 'UPLOAD_RATE_LIMIT_EXCEEDED',
                            'message' => 'Too many upload requests. Please try again later.',
                            'details' => [
                                'retry_after' => $headers['Retry-After'] ?? null,
                            ],
                        ],
                        'meta' => [
                            'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
                            'version' => 'v1',
                        ]
                    ], 429, $headers);
                });
        });

        // Email verification rate limiting
        RateLimiter::for('verification-notification', function (Request $request) {
            return Limit::perMinute(3)->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code' => 'VERIFICATION_RATE_LIMIT_EXCEEDED',
                            'message' => 'Too many verification requests. Please try again later.',
                            'details' => [
                                'retry_after' => $headers['Retry-After'] ?? null,
                            ],
                        ],
                        'meta' => [
                            'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
                            'version' => 'v1',
                        ]
                    ], 429, $headers);
                });
        });
    }
}